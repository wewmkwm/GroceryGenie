<?php
// customer/process_order_demo.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/upload_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
  echo json_encode(['ok' => false, 'message' => 'Please log in first.']);
  exit;
}

$customer_id = (int)$_SESSION['customer_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
  echo json_encode(['ok' => false, 'message' => 'Security validation failed. Please refresh checkout and try again.']);
  exit;
}

// DB
require_once __DIR__ . '/../db_connect.php'; // provides $conn
if (!isset($conn) || $conn->connect_error) {
  echo json_encode(['ok' => false, 'message' => 'DB connection error.']);
  exit;
}

$cart_json      = $_POST['cart_json']      ?? '';
$total_amount   = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0.0;
$payment_method = $_POST['payment_method'] ?? 'COD';
$card_name      = trim($_POST['card_name'] ?? '');
$card_number    = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
$card_expiry    = trim($_POST['card_expiry'] ?? '');

if ($cart_json === '') {
  echo json_encode(['ok' => false, 'message' => 'Cart is empty.']);
  exit;
}

$cart = json_decode($cart_json, true);
if (!is_array($cart)) {
  echo json_encode(['ok' => false, 'message' => 'Invalid cart format.']);
  exit;
}

if ($payment_method === 'Card') {
  if ($card_name === '' || $card_number === '' || $card_expiry === '') {
    echo json_encode(['ok' => false, 'message' => 'Missing card details.']);
    exit;
  }
  if (strlen($card_number) < 12 || strlen($card_number) > 19) {
    echo json_encode(['ok' => false, 'message' => 'Invalid card number.']);
    exit;
  }
  if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid card expiry.']);
    exit;
  }
}

// Fetch delivery address snapshot (tolerate schema differences)
$delivery_address = '';
if ($stmt = $conn->prepare('SELECT delivery_address FROM customers WHERE customer_id = ?')) {
  $stmt->bind_param('i', $customer_id);
  if ($stmt->execute()) {
    $stmt->bind_result($delivery_address);
    $stmt->fetch();
  }
  $stmt->close();
}

$conn->begin_transaction();

try {
  $pay_status   = 'Pending';
  $order_status = 'Pending';

  // Create order
  $ins = $conn->prepare('INSERT INTO orders (customer_id, total_amount, payment_method, payment_status, order_status, delivery_address) VALUES (?, ?, ?, ?, ?, ?)');
  if (!$ins) throw new Exception('DB error (create order): ' . $conn->error);
  $ins->bind_param('idssss', $customer_id, $total_amount, $payment_method, $pay_status, $order_status, $delivery_address);
  if (!$ins->execute()) throw new Exception('Failed to create order: ' . $ins->error);
  $order_id = $ins->insert_id;
  $ins->close();

  // Prepare statements for items and stock updates
  $oi = $conn->prepare('INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
  if (!$oi) throw new Exception('DB error (prepare order_items): ' . $conn->error);
  $updStock = $conn->prepare('UPDATE groceryitem SET quantity = GREATEST(quantity - ?, 0) WHERE item_id = ?');
  if (!$updStock) throw new Exception('DB error (prepare stock): ' . $conn->error);

  foreach ($cart as $itemName => $d) {
    $qty   = max(0, (int)($d['qty']   ?? 0));
    $price =       (float)($d['price'] ?? 0);
    if ($qty <= 0) continue;

    // Look up item_id by name (ideally send item_id from client)
    $find = $conn->prepare('SELECT item_id, owner_id, quantity FROM groceryitem WHERE item_name = ? LIMIT 1');
    if (!$find) throw new Exception('DB error (prepare find item): ' . $conn->error);
    $find->bind_param('s', $itemName);
    if (!$find->execute()) {
      $find->close();
      throw new Exception('DB error (exec find item): ' . $conn->error);
    }
    $find->bind_result($item_id, $owner_id, $current_qty);
    $found = $find->fetch();
    $find->close();
    if (!$found || empty($item_id)) {
      throw new Exception('Item not found: ' . $itemName);
    }

    $subtotal = $qty * $price;

    // Insert order item
    $oi->bind_param('iisdid', $order_id, $item_id, $itemName, $price, $qty, $subtotal);
    if (!$oi->execute()) throw new Exception('Failed to insert order item: ' . $oi->error);

    // Reduce stock
    $updStock->bind_param('ii', $qty, $item_id);
    if (!$updStock->execute()) throw new Exception('Failed to update stock: ' . $updStock->error);

    // Low stock notification for store owner
    $newQty = max(0, (int)$current_qty - $qty);
    $lowStockThreshold = 5;
    if ($newQty <= $lowStockThreshold) {
      $conn->query("CREATE TABLE IF NOT EXISTS store_owner_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        owner_id INT NOT NULL,
        item_id INT NOT NULL,
        message TEXT,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_store_owner_notifications_owner FOREIGN KEY (owner_id) REFERENCES store_owners(owner_id) ON DELETE CASCADE,
        CONSTRAINT fk_store_owner_notifications_item FOREIGN KEY (item_id) REFERENCES groceryitem(item_id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

      if ($checkNotif = $conn->prepare('SELECT id FROM store_owner_notifications WHERE owner_id = ? AND item_id = ? AND is_read = 0 LIMIT 1')) {
        $checkNotif->bind_param('ii', $owner_id, $item_id);
        $checkNotif->execute();
        $hasExisting = $checkNotif->get_result()->num_rows > 0;
        $checkNotif->close();

        if (!$hasExisting && $insNotif = $conn->prepare('INSERT INTO store_owner_notifications (owner_id, item_id, message) VALUES (?, ?, ?)')) {
          $message = sprintf('Low stock alert: %s has only %d unit(s) remaining.', $itemName, $newQty);
          $insNotif->bind_param('iis', $owner_id, $item_id, $message);
          $insNotif->execute();
          $insNotif->close();
        }
      }
    }
  }
  $oi->close();
  $updStock->close();

  // Optional payment proof upload
  $proof_file = null;
  if ($payment_method === 'Bank Transfer' && !empty($_FILES['proof_image']['name'])) {
    $uploadResult = gg_secure_upload(
        $_FILES['proof_image'],
        dirname(__DIR__) . '/uploads/payments/',
        ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        5 * 1024 * 1024
    );
    if ($uploadResult['success']) {
      $proof_file = $uploadResult['filename'];
    } else {
      throw new Exception('Payment proof upload failed: ' . $uploadResult['message']);
    }
  }

  // Save payment record (Pending)
  $proof_value = $proof_file ?: '';
  if ($payment_method === 'Card') {
    $last4 = substr($card_number, -4);
    $proof_value = 'CARD ****' . $last4 . ' EXP ' . $card_expiry;
  }
  $pay = $conn->prepare('INSERT INTO payments (order_id, customer_id, payment_method, payment_status, amount, proof_image) VALUES (?, ?, ?, "Pending", ?, ?)');
  if (!$pay) throw new Exception('DB error (prepare payment): ' . $conn->error);
  $pay->bind_param('iisds', $order_id, $customer_id, $payment_method, $total_amount, $proof_value);
  if (!$pay->execute()) throw new Exception('Failed to create payment: ' . $pay->error);
  $pay->close();

  $conn->commit();
  echo json_encode(['ok' => true, 'order_id' => $order_id]);
  exit;
} catch (Exception $e) {
  $conn->rollback();
  echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
  exit;
}

// no further output
