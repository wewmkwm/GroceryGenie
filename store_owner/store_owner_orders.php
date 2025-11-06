<?php
// store_owner/store_owner_orders.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['store_owner_id'])) {
    header('Location: store_owner_login.php');
    exit();
}

$owner_id = (int)$_SESSION['store_owner_id'];

include 'store_owner_header.php';

if (!isset($conn) || $conn->connect_error) {
    $conn = new mysqli('localhost', 'root', '', 'grocerygenie');
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id   = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $new_status = trim($_POST['order_status'] ?? '');
    $allowed    = ['Pending', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'];

    if ($order_id > 0 && in_array($new_status, $allowed, true)) {
        $check = $conn->prepare('SELECT COUNT(*) FROM order_items oi JOIN groceryitem gi ON gi.item_id = oi.item_id WHERE oi.order_id = ? AND gi.owner_id = ?');
        if ($check) {
            $check->bind_param('ii', $order_id, $owner_id);
            $check->execute();
            $check->bind_result($cnt);
            $check->fetch();
            $check->close();

            if ((int)$cnt > 0) {
                $upd = $conn->prepare('UPDATE orders SET order_status = ? WHERE order_id = ?');
                if ($upd) {
                    $upd->bind_param('si', $new_status, $order_id);
                    if ($upd->execute()) {
                        $message = '<div class="alert alert-success shadow-sm mb-4"><i class="fas fa-check-circle me-2"></i>Order #' . $order_id . ' updated to <strong>' . htmlspecialchars($new_status, ENT_QUOTES) . '</strong>.</div>';

                        @$conn->query("CREATE TABLE IF NOT EXISTS notifications (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            customer_id INT NOT NULL,
                            order_id INT NOT NULL,
                            type VARCHAR(50) DEFAULT 'order',
                            message TEXT,
                            is_read TINYINT(1) DEFAULT 0,
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                        $cust_id = 0;
                        if ($st = $conn->prepare('SELECT customer_id FROM orders WHERE order_id = ? LIMIT 1')) {
                            $st->bind_param('i', $order_id);
                            $st->execute();
                            $st->bind_result($cust_id);
                            $st->fetch();
                            $st->close();
                        }
                        if ($cust_id) {
                            $msg = 'Your order #' . $order_id . ' status updated to ' . $new_status . '. You can view/print the invoice.';
                            if ($ins = $conn->prepare('INSERT INTO notifications (customer_id, order_id, type, message) VALUES (?, ?, \'order\', ?)')) {
                                $ins->bind_param('iis', $cust_id, $order_id, $msg);
                                $ins->execute();
                                $ins->close();
                            }
                        }
                    } else {
                        $message = '<div class="alert alert-danger shadow-sm mb-4"><i class="fas fa-exclamation-circle me-2"></i>Failed to update order: ' . htmlspecialchars($upd->error, ENT_QUOTES) . '</div>';
                    }
                    $upd->close();
                } else {
                    $message = '<div class="alert alert-danger shadow-sm mb-4">Database error preparing update.</div>';
                }
            } else {
                $message = '<div class="alert alert-warning shadow-sm mb-4"><i class="fas fa-info-circle me-2"></i>You cannot update this order because it has no items from your store.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger shadow-sm mb-4">Database error verifying order ownership.</div>';
        }
    } else {
        $message = '<div class="alert alert-warning shadow-sm mb-4">Invalid order or status.</div>';
    }
}

function fetch_order_items_for_owner(mysqli $conn, int $order_id, int $owner_id): array {
    $items = [];
    $sql = 'SELECT oi.item_name, oi.unit_price, oi.quantity, oi.subtotal
            FROM order_items oi
            JOIN groceryitem gi ON gi.item_id = oi.item_id
            WHERE oi.order_id = ? AND gi.owner_id = ?';
    if ($st = $conn->prepare($sql)) {
        $st->bind_param('ii', $order_id, $owner_id);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) {
            $items[] = $row;
        }
        $st->close();
    }
    return $items;
}

function fetch_payment_for_order(mysqli $conn, int $order_id): ?array {
    $sql = 'SELECT payment_method, payment_status, amount, proof_image, created_at FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1';
    if ($st = $conn->prepare($sql)) {
        $st->bind_param('i', $order_id);
        $st->execute();
        $res = $st->get_result();
        $row = $res->fetch_assoc();
        $st->close();
        return $row ?: null;
    }
    return null;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$allowed_statuses = ['Pending', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'];

$orders = [];
$baseSql = "SELECT DISTINCT o.order_id, o.customer_id, o.total_amount, o.payment_method, o.payment_status,
                   o.order_status, o.delivery_address, o.created_at,
                   c.name AS customer_name, c.phone_number, c.email
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.order_id
            JOIN groceryitem gi ON gi.item_id = oi.item_id
            JOIN customers c ON c.customer_id = o.customer_id
            WHERE gi.owner_id = ?";
$types = 'i';
$params = [$owner_id];

if ($q !== '') {
    $baseSql .= ' AND (o.order_id LIKE ? OR c.name LIKE ?)';
    $types .= 'ss';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

if ($filter_status !== '' && in_array($filter_status, $allowed_statuses, true)) {
    $baseSql .= ' AND o.order_status = ?';
    $types .= 's';
    $params[] = $filter_status;
}

$baseSql .= ' ORDER BY o.created_at DESC';

if ($stmt = $conn->prepare($baseSql)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}

$ownerRevenue = 0.0;
if ($stmt = $conn->prepare('SELECT COALESCE(SUM(oi.subtotal),0) FROM order_items oi JOIN groceryitem gi ON gi.item_id = oi.item_id WHERE gi.owner_id = ?')) {
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $stmt->bind_result($ownerRevenue);
    $stmt->fetch();
    $stmt->close();
}

$pendingOrders = 0;
$deliveredOrders = 0;
$inProgressOrders = 0;
foreach ($orders as $o) {
    switch ($o['order_status']) {
        case 'Pending':
            $pendingOrders++;
            break;
        case 'Delivered':
            $deliveredOrders++;
            break;
        case 'Preparing':
        case 'Out for Delivery':
            $inProgressOrders++;
            break;
    }
}

function status_badge_class(string $status): string {
    switch ($status) {
        case 'Pending':
            return 'bg-warning text-dark';
        case 'Preparing':
            return 'bg-info text-dark';
        case 'Out for Delivery':
            return 'bg-primary';
        case 'Delivered':
            return 'bg-success';
        case 'Cancelled':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
?>

<style>
  .so-orders {
    margin-top: 2rem;
    margin-bottom: 4rem;
  }
  .so-orders-hero {
    background: var(--gg-gradient);
    border-radius: var(--gg-radius-md);
    color: #fff;
    padding: 2.6rem 2rem;
    box-shadow: var(--gg-shadow-soft);
    position: relative;
    overflow: hidden;
  }
  .so-orders-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.18), transparent 55%);
    pointer-events: none;
  }
  .so-orders-filters {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    padding: 1.5rem;
  }
  .so-orders-filters label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gg-secondary);
  }
  .so-orders-filters .form-control,
  .so-orders-filters .form-select {
    border: none;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
  }
  .so-orders-filters .form-control:focus,
  .so-orders-filters .form-select:focus {
    box-shadow: 0 0 0 3px rgba(255, 138, 76, 0.25);
  }
  .so-orders-metric {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    padding: 1.6rem;
  }
  .so-orders-metric .icon-wrap {
    width: 46px;
    height: 46px;
    border-radius: 16px;
    background: var(--gg-primary-soft);
    color: var(--gg-primary-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.1rem;
    font-size: 1.1rem;
  }
  .so-orders-metric h3 {
    font-weight: 700;
    margin-bottom: 0.3rem;
  }
  .so-orders-metric span {
    color: var(--gg-muted);
  }
  .so-payment-proof-thumb {
    width: 160px;
    border-radius: 14px;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .so-payment-proof-thumb:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 32px rgba(79, 70, 229, 0.25);
  }
  .so-proof-empty {
    padding: 0.75rem 1rem;
    border-radius: 12px;
    background: rgba(253, 230, 138, 0.22);
    color: #92400e;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
  }
  .so-order-card {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    border: none;
  }
  .so-order-card header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.3rem 1.6rem;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    flex-wrap: wrap;
    gap: 0.75rem;
  }
  .so-order-card header h5 {
    margin: 0;
    font-weight: 700;
  }
  .so-order-card .card-body {
    padding: 1.6rem;
  }
  .so-order-infos small {
    color: var(--gg-muted);
  }
  .so-order-table thead {
    background: rgba(15, 23, 42, 0.05);
  }
  .so-empty {
    text-align: center;
    padding: 2.5rem;
    color: var(--gg-muted);
  }
</style>

<div class="container so-orders">
  <section class="so-orders-hero mb-4">
    <span class="gg-hero-eyebrow"><i class="fas fa-shopping-basket"></i> Order Management</span>
    <h1>Track fulfilment, keep customers happy.</h1>
    <p>Review recent purchases, update statuses, and monitor payments so your GroceryGenie shoppers know exactly when to expect their items.</p>
    <div class="d-flex flex-wrap gap-3 mt-4">
      <a href="store_owner_dashboard.php" class="gg-btn-outline text-white"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
      <a href="store_owner_sales_report.php" class="gg-btn-primary"><i class="fas fa-chart-line"></i> View sales report</a>
    </div>
  </section>

  <?php if ($message !== '') { echo $message; } ?>

  <section class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="so-orders-metric h-100">
        <div class="icon-wrap"><i class="fas fa-coins"></i></div>
        <h3>RM <?php echo number_format($ownerRevenue, 2); ?></h3>
        <span>Total revenue (all time)</span>
      </div>
    </div>
    <div class="col-md-3">
      <div class="so-orders-metric h-100">
        <div class="icon-wrap"><i class="fas fa-clock"></i></div>
        <h3><?php echo $pendingOrders; ?></h3>
        <span>Orders awaiting action</span>
      </div>
    </div>
    <div class="col-md-3">
      <div class="so-orders-metric h-100">
        <div class="icon-wrap"><i class="fas fa-truck-moving"></i></div>
        <h3><?php echo $inProgressOrders; ?></h3>
        <span>Currently in progress</span>
      </div>
    </div>
    <div class="col-md-3">
      <div class="so-orders-metric h-100">
        <div class="icon-wrap"><i class="fas fa-check-circle"></i></div>
        <h3><?php echo $deliveredOrders; ?></h3>
        <span>Delivered to customers</span>
      </div>
    </div>
  </section>

  <section class="so-orders-filters mb-4">
    <form class="row g-3 align-items-end" method="GET">
      <div class="col-md-5">
        <label class="form-label">Search</label>
        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>" placeholder="Order ID or customer name">
      </div>
      <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">All statuses</option>
          <?php foreach ($allowed_statuses as $s) : ?>
            <option value="<?php echo htmlspecialchars($s, ENT_QUOTES); ?>" <?php echo $filter_status === $s ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($s); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="gg-btn-primary flex-grow-1"><i class="fas fa-filter me-1"></i>Apply</button>
        <a href="store_owner_orders.php" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
      </div>
    </form>
  </section>

  <?php if (!empty($orders)) : ?>
    <?php foreach ($orders as $o) :
        $order_id = (int)$o['order_id'];
        $items = fetch_order_items_for_owner($conn, $order_id, $owner_id);
        $pay = fetch_payment_for_order($conn, $order_id);
    ?>
      <article class="so-order-card mb-4">
        <header>
          <div class="so-order-infos">
            <h5>Order #<?php echo $order_id; ?></h5>
            <small>Placed <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($o['created_at']))); ?></small>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge <?php echo status_badge_class($o['order_status']); ?>"><?php echo htmlspecialchars($o['order_status']); ?></span>
            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($o['payment_method']); ?></span>
            <span class="badge bg-secondary text-white"><?php echo htmlspecialchars($o['payment_status']); ?></span>
          </div>
        </header>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-lg-4">
              <h6 class="text-uppercase text-muted fw-semibold mb-2">Customer</h6>
              <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($o['customer_name']); ?></p>
              <p class="mb-1"><i class="fas fa-envelope me-2 text-muted"></i><?php echo htmlspecialchars($o['email']); ?></p>
              <p class="mb-0"><i class="fas fa-phone me-2 text-muted"></i><?php echo htmlspecialchars($o['phone_number']); ?></p>
            </div>
            <div class="col-lg-4">
              <h6 class="text-uppercase text-muted fw-semibold mb-2">Delivery</h6>
              <div class="p-3 rounded border bg-light-subtle" style="white-space: pre-wrap;">
                <?php echo htmlspecialchars($o['delivery_address']); ?>
              </div>
            </div>
            <div class="col-lg-4">
              <h6 class="text-uppercase text-muted fw-semibold mb-2">Manage status</h6>
              <form method="POST" class="d-flex flex-wrap gap-2">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                <select name="order_status" class="form-select form-select-sm flex-grow-1">
                  <?php foreach ($allowed_statuses as $s) : ?>
                    <option value="<?php echo htmlspecialchars($s, ENT_QUOTES); ?>" <?php echo $o['order_status'] === $s ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($s); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="update_status" class="btn btn-sm btn-warning text-dark fw-semibold"><i class="fas fa-save me-1"></i>Update</button>
              </form>
              <div class="mt-3 small text-muted">
                Keep customers informed at each handoff. They'll receive an in-app alert automatically.
              </div>
            </div>
          </div>

          <div class="mt-4">
            <h6 class="text-uppercase text-muted fw-semibold mb-2">Items from your store</h6>
            <div class="table-responsive">
              <table class="table table-striped align-middle so-order-table mb-0">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th class="text-end">Unit price (RM)</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Subtotal (RM)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($items)) : ?>
                    <?php foreach ($items as $it) : ?>
                      <tr>
                        <td><?php echo htmlspecialchars($it['item_name']); ?></td>
                        <td class="text-end"><?php echo number_format((float)$it['unit_price'], 2); ?></td>
                        <td class="text-end"><?php echo (int)$it['quantity']; ?></td>
                        <td class="text-end">RM <?php echo number_format((float)$it['subtotal'], 2); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else : ?>
                    <tr><td colspan="4" class="so-empty py-4">No items fulfilled by you.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="d-flex flex-wrap justify-content-between align-items-center mt-4">
            <div class="fw-semibold">
              Gross order (all stores): <span class="text-warning">RM <?php echo number_format((float)$o['total_amount'], 2); ?></span>
            </div>
            <?php if ($pay): ?>
              <div class="text-muted small">
                Payment recorded <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($pay['created_at']))); ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($pay): ?>
            <?php
              $proofUrl = '';
              $hasProofImage = false;
              $cardReference = '';
              if ($pay['payment_method'] === 'Bank Transfer' && !empty($pay['proof_image'])) {
                  $proofFilename = basename($pay['proof_image']);
                  $absolutePath = realpath(__DIR__ . '/../uploads/payments/' . $proofFilename);
                  if ($absolutePath && is_file($absolutePath)) {
                      $proofUrl = '../uploads/payments/' . rawurlencode($proofFilename);
                      $hasProofImage = true;
                  }
              } elseif ($pay['payment_method'] === 'Card' && !empty($pay['proof_image'])) {
                  $cardReference = $pay['proof_image'];
              }
            ?>
            <hr class="my-4">
            <div class="row g-3 align-items-start">
              <div class="col-md-5">
                <h6 class="text-uppercase text-muted fw-semibold mb-2">Payment</h6>
                <ul class="list-unstyled mb-0 small">
                  <li><strong>Method:</strong> <?php echo htmlspecialchars($pay['payment_method']); ?></li>
                  <li><strong>Status:</strong> <?php echo htmlspecialchars($pay['payment_status']); ?></li>
                  <li><strong>Amount:</strong> RM <?php echo number_format((float)$pay['amount'], 2); ?></li>
                  <li><strong>Logged:</strong> <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($pay['created_at']))); ?></li>
                  <?php if ($cardReference): ?>
                    <li class="mt-2"><i class="fas fa-shield-alt me-1 text-info"></i><strong>Card confirmation:</strong> <?php echo htmlspecialchars($cardReference); ?></li>
                  <?php endif; ?>
                </ul>
              </div>
              <div class="col-md-4">
                <h6 class="text-uppercase text-muted fw-semibold mb-2">Proof</h6>
                <?php if ($hasProofImage): ?>
                  <img src="<?php echo $proofUrl; ?>" alt="Payment proof for order <?php echo (int)$o['order_id']; ?>" class="so-payment-proof-thumb mb-2" data-bs-toggle="modal" data-bs-target="#orderProofModal" data-proof="<?php echo $proofUrl; ?>" data-title="Order #<?php echo (int)$o['order_id']; ?> payment proof">
                  <div>
                    <a href="<?php echo $proofUrl; ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-up-right-from-square me-1"></i>Open full image</a>
                  </div>
                <?php elseif ($pay['payment_method'] === 'Bank Transfer'): ?>
                  <div class="so-proof-empty"><i class="fas fa-hourglass-half"></i>No slip uploaded yet.</div>
                <?php else: ?>
                  <div class="text-muted small">No supporting document required for this payment.</div>
                <?php endif; ?>
              </div>
              <div class="col-md-3">
                <h6 class="text-uppercase text-muted fw-semibold mb-2">Next steps</h6>
                <p class="text-muted small mb-2">Once you confirm funds, update the payment and order status so the customer gets notified.</p>
                <a href="#main-content" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-up me-1"></i>Back to filters</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  <?php else : ?>
    <div class="so-empty bg-white rounded shadow-sm">No orders match your filters yet.</div>
  <?php endif; ?>
</div>

<div class="modal fade" id="orderProofModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment proof</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" alt="Payment proof" class="img-fluid rounded shadow">
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const proofModal = document.getElementById('orderProofModal');
    if (!proofModal) return;
    const proofImage = proofModal.querySelector('img');
    const proofTitle = proofModal.querySelector('.modal-title');

    proofModal.addEventListener('show.bs.modal', function (event) {
      const trigger = event.relatedTarget;
      if (!trigger) return;
      const proofUrl = trigger.getAttribute('data-proof') || '';
      const title = trigger.getAttribute('data-title') || 'Payment proof';
      if (proofImage) {
        proofImage.src = proofUrl;
      }
      if (proofTitle) {
        proofTitle.textContent = title;
      }
    });

    proofModal.addEventListener('hidden.bs.modal', function () {
      if (proofImage) {
        proofImage.src = '';
      }
    });
  });
</script>

<?php include 'store_owner_footer.php'; ?>
