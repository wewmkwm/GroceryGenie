<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['customer_id'])) { header('Location: customer_login.php'); exit; }

require_once __DIR__ . '/../db_connect.php';
include 'customer_header.php';

$orderParam = $_GET['order_id'] ?? $_GET['id'] ?? null;
$orderId = $orderParam !== null ? (int)$orderParam : 0;
$customerId = (int)$_SESSION['customer_id'];

if ($orderId <= 0) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Invalid order.</div></div>';
    include 'customer_footer.php';
    exit;
}

$orderStmt = $conn->prepare("SELECT order_id, total_amount, payment_method, payment_status, order_status, delivery_address, created_at FROM orders WHERE order_id = ? AND customer_id = ? LIMIT 1");
$orderStmt->bind_param('ii', $orderId, $customerId);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();
$order = $orderResult->fetch_assoc();
$orderStmt->close();

if (!$order) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Order not found.</div></div>';
    include 'customer_footer.php';
    exit;
}

$itemStmt = $conn->prepare("SELECT item_name, unit_price, quantity, subtotal FROM order_items WHERE order_id = ? ORDER BY item_name");
$itemStmt->bind_param('i', $orderId);
$itemStmt->execute();
$items = $itemStmt->get_result();
$itemStmt->close();

$payStmt = $conn->prepare("SELECT payment_method, payment_status, amount, proof_image, created_at FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
$payStmt->bind_param('i', $orderId);
$payStmt->execute();
$paymentInfo = $payStmt->get_result()->fetch_assoc();
$payStmt->close();

$invoiceUrl = 'order_receipt.php?order_id=' . urlencode($order['order_id']);
?>
<style>
  .order-details-card { background:#fff; border:1px solid #f0d4bd; border-radius:16px; padding:24px; box-shadow:0 18px 36px rgba(255,145,77,0.12); }
  .order-meta span { display:inline-block; margin-right:12px; font-size:0.9rem; color:#7a6453; }
  .items-table th { background:#fff3e8; }
</style>
<div class="container my-4">
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3 no-print">
    <a href="my_orders.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to My Orders</a>
    <a href="<?php echo htmlspecialchars($invoiceUrl); ?>" class="btn btn-primary" target="_blank" rel="noopener">
      <i class="fas fa-file-invoice me-1"></i> View Invoice
    </a>
  </div>
  <div class="order-details-card" id="invoice-area">
    <h4 class="fw-bold text-primary"><i class="fas fa-receipt"></i> Order #<?php echo $order['order_id']; ?></h4>
    <div class="order-meta mt-2">
      <span><strong>Placed:</strong> <?php echo htmlspecialchars($order['created_at']); ?></span>
      <span><strong>Status:</strong> <?php echo htmlspecialchars($order['order_status']); ?></span>
    </div>
    <div class="order-meta mt-1">
      <span><strong>Payment:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></span>
      <span><strong>Payment Status:</strong> <?php echo htmlspecialchars($order['payment_status']); ?></span>
    </div>
    <div class="mt-3">
      <strong>Total Paid:</strong> RM<?php echo number_format((float)$order['total_amount'], 2); ?>
    </div>
    <?php if (!empty($order['delivery_address'])): ?>
      <div class="mt-3">
        <strong>Delivery Address:</strong>
        <div class="border rounded p-2 bg-light"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></div>
      </div>
    <?php endif; ?>

    <?php if ($paymentInfo): ?>
      <div class="mt-3">
        <strong>Latest Payment Entry:</strong>
        <div class="border rounded p-2">
          <div><strong>Method:</strong> <?php echo htmlspecialchars($paymentInfo['payment_method']); ?></div>
          <div><strong>Status:</strong> <?php echo htmlspecialchars($paymentInfo['payment_status']); ?></div>
          <div><strong>Amount:</strong> RM<?php echo number_format((float)$paymentInfo['amount'], 2); ?></div>
          <?php if (!empty($paymentInfo['proof_image'])): ?>
            <div><strong>Reference:</strong> <?php echo htmlspecialchars($paymentInfo['proof_image']); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="mt-4">
      <h5 class="fw-bold"><i class="fas fa-box"></i> Items</h5>
      <div class="table-responsive">
        <table class="table table-sm items-table">
          <thead>
            <tr><th>Item</th><th class="text-end">Unit Price (RM)</th><th class="text-end">Qty</th><th class="text-end">Subtotal (RM)</th></tr>
          </thead>
          <tbody>
            <?php if ($items && $items->num_rows > 0): while ($row = $items->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                <td class="text-end"><?php echo number_format((float)$row['unit_price'], 2); ?></td>
                <td class="text-end"><?php echo (int)$row['quantity']; ?></td>
                <td class="text-end"><?php echo number_format((float)$row['subtotal'], 2); ?></td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="4" class="text-muted">No items found for this order.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php $conn->close(); ?>
<?php include 'customer_footer.php'; ?>
