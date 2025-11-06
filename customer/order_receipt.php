<?php
// customer/order_receipt.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['customer_id'])) { header('Location: customer_login.php'); exit(); }

require_once __DIR__ . '/../db_connect.php';
$customer_id = (int)$_SESSION['customer_id'];

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) { die('Invalid order.'); }

// Verify ownership
$st = $conn->prepare('SELECT order_id, total_amount, payment_method, payment_status, order_status, delivery_address, created_at FROM orders WHERE order_id = ? AND customer_id = ? LIMIT 1');
$st->bind_param('ii', $order_id, $customer_id);
$st->execute();
$order = $st->get_result()->fetch_assoc();
$st->close();
if (!$order) { die('Order not found.'); }

// Items
$items = [];
$sti = $conn->prepare('SELECT item_name, unit_price, quantity, subtotal FROM order_items WHERE order_id = ?');
$sti->bind_param('i', $order_id);
$sti->execute();
$res = $sti->get_result();
while ($row = $res->fetch_assoc()) { $items[] = $row; }
$sti->close();

// Payment (latest)
$pay = null;
$sp = $conn->prepare('SELECT payment_method, payment_status, amount, proof_image, created_at FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1');
$sp->bind_param('i', $order_id);
$sp->execute();
$pay = $sp->get_result()->fetch_assoc();
$sp->close();

// Distinct store owners involved in this order (for contact on receipt)
$owners = [];
if ($own = $conn->prepare('SELECT DISTINCT so.owner_id, so.store_name, so.email, so.phone_number, so.store_address
                            FROM order_items oi
                            JOIN groceryitem gi ON gi.item_id = oi.item_id
                            JOIN store_owners so ON so.owner_id = gi.owner_id
                           WHERE oi.order_id = ?')) {
  $own->bind_param('i', $order_id);
  $own->execute();
  $res = $own->get_result();
  while ($r = $res->fetch_assoc()) { $owners[] = $r; }
  $own->close();
}

// Deterministic Invoice Code (hash of order id + created time)
$receipt_code = 'RC-' . strtoupper(substr(md5($order_id . '|' . ($order['created_at'] ?? '')), 0, 8));

include 'customer_header.php';
?>

<style>
  /* Thermal receipt-like styling */
  body { background:#f7f7f9; }
  .receipt {
    max-width: 420px;
    margin: 18px auto;
    background:#fff;
    color:#111;
    border:1px solid #eaeaea;
    border-radius: 6px;
    padding: 18px 16px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
    box-shadow: 0 6px 18px rgba(0,0,0,.06);
  }
  .brand { text-align:center; font-weight: 800; letter-spacing: 2px; margin-bottom: 2px; }
  .brand small { display:block; color:#999; letter-spacing: 1px; }
  .meta { font-size: 12px; margin-top: 6px; line-height: 1.4; }
  .divider { border-top:1px dashed #cfcfcf; margin: 10px 0; }
  .row-line { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; }
  .item-name { flex: 1 1 auto; word-break: break-word; }
  .item-qtyprice { white-space: nowrap; text-align:right; }
  .totals { font-weight:700; font-size: 14px; }
  .muted { color:#6c757d; }
  .addr { white-space: pre-wrap; font-size: 12px; }
  .footer-note { text-align:center; margin-top: 10px; font-size: 12px; color:#6c757d; }
  .print-bar { display:flex; justify-content:flex-end; }
  .print-btn { margin-left:auto; }
  .owners { font-size: 12px; }
  .owners .owner { margin-bottom: 6px; }

  @media print {
    nav, .btn, .no-print { display:none !important; }
    body { background:#fff; }
    .receipt { box-shadow:none; border:0; margin:0; }
  }
</style>

<div class="receipt">
  <div class="print-bar no-print">
    <button class="btn btn-outline-secondary print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  </div>
  <div class="brand">GROCERYGENIE <small>Order Invoice</small></div>
  <div class="meta">
    Order #: <?php echo (int)$order['order_id']; ?>
    <br>Placed: <?php echo htmlspecialchars($order['created_at']); ?>
    <br>Status: <?php echo htmlspecialchars($order['order_status']); ?>
    <br>Payment: <?php echo htmlspecialchars($order['payment_method'] ?? ($pay['payment_method'] ?? 'N/A')); ?> (<?php echo htmlspecialchars($order['payment_status'] ?? ($pay['payment_status'] ?? 'Pending')); ?>)
    <br>Invoice Code: <?php echo htmlspecialchars($receipt_code); ?>
  </div>

  <div class="divider"></div>

  <?php foreach ($items as $it): ?>
    <div class="row-line">
      <div class="item-name"><?php echo htmlspecialchars($it['item_name']); ?></div>
      <div class="item-qtyprice">
        <?php echo (int)$it['quantity']; ?> × RM<?php echo number_format((float)$it['unit_price'],2); ?> = RM<?php echo number_format((float)$it['subtotal'],2); ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="divider"></div>

  <div class="row-line totals">
    <div>Total</div>
    <div>RM <?php echo number_format((float)$order['total_amount'],2); ?></div>
  </div>

  <?php if (!empty($order['delivery_address'])): ?>
    <div class="divider"></div>
    <div class="meta">
      <div><strong>Delivery Address</strong></div>
      <div class="addr"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></div>
    </div>
  <?php endif; ?>

  <?php if (!empty($owners)): ?>
    <div class="divider"></div>
    <div class="meta owners">
      <div><strong>Store Owner Contact</strong></div>
      <?php foreach ($owners as $o): ?>
        <div class="owner">
          <div><?php echo htmlspecialchars($o['store_name']); ?></div>
          <div><?php echo htmlspecialchars($o['email']); ?> • <?php echo htmlspecialchars($o['phone_number']); ?></div>
          <?php if (!empty($o['store_address'])): ?>
            <div class="addr"><?php echo nl2br(htmlspecialchars($o['store_address'])); ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($pay['proof_image'])): ?>
    <div class="divider"></div>
    <div class="meta">
      <strong>Payment Proof</strong>
      <div><a href="<?php echo '../uploads/payments/' . htmlspecialchars($pay['proof_image']); ?>" target="_blank">View Uploaded Proof</a></div>
    </div>
  <?php endif; ?>

  <div class="divider"></div>
  <div class="footer-note">Thank you for shopping with GroceryGenie</div>
</div>

<?php include 'customer_footer.php'; ?>
