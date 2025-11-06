<?php
include 'customer_header.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
?>
<div class="container my-5 text-center">
  <div class="card shadow-sm p-4">
    <h2 class="text-success"><i class="fas fa-check-circle"></i> Order Placed!</h2>
    <p class="mt-2">Your order has been created successfully.</p>
    <?php if ($order_id): ?>
      <p><strong>Order ID:</strong> #<?php echo $order_id; ?></p>
    <?php endif; ?>
    <div class="mt-3">
      <a href="shopping.php" class="btn btn-primary">Continue Shopping</a>
      <a href="my_orders.php" class="btn btn-outline-secondary ms-2">View My Orders</a>
    </div>
  </div>
</div>
<?php include 'customer_footer.php'; ?>
