<?php
include 'customer_header.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['customer_id'])) { header('Location: customer_login.php'); exit; }
$cid = intval($_SESSION['customer_id']);

$search = trim($_GET['q'] ?? '');
$sql = "SELECT order_id, total_amount, payment_method, payment_status, order_status, created_at FROM orders WHERE customer_id = ?";
$types = 'i';
$params = [$cid];

if ($search !== '') {
    $sql .= " AND (CAST(order_id AS CHAR) LIKE ? OR payment_method LIKE ? OR payment_status LIKE ? OR order_status LIKE ? OR created_at LIKE ? )";
    $like = '%' . $search . '%';
    $types .= 'sssss';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY created_at DESC";

$ordersStmt = $conn->prepare($sql);
if (!$ordersStmt) {
    die("<p class='text-danger'>Unable to load orders.</p>");
}
$ordersStmt->bind_param($types, ...$params);
$ordersStmt->execute();
$orders = $ordersStmt->get_result();
?>
<div class="container my-4">
  <h3>My Orders</h3>
  <form method="get" class="row g-2 align-items-center mb-3">
    <div class="col-sm-8 col-md-6">
      <div class="input-group">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Search by order number, payment, status" value="<?php echo htmlspecialchars($search); ?>">
      </div>
    </div>
    <div class="col-auto">
      <button class="btn btn-primary" type="submit">Search</button>
    </div>
    <?php if ($search !== ''): ?>
      <div class="col-auto">
        <a href="my_orders.php" class="btn btn-outline-secondary">Clear</a>
      </div>
    <?php endif; ?>
  </form>
  <?php if ($orders && $orders->num_rows>0): ?>
    <div class="list-group">
      <?php while($o=$orders->fetch_assoc()): ?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div><strong>Order #<?php echo $o['order_id']; ?></strong> &middot; RM<?php echo number_format($o['total_amount'],2); ?></div>
              <div class="text-muted small">
                <?php echo htmlspecialchars($o['payment_method']); ?> &middot;
                Payment: <?php echo htmlspecialchars($o['payment_status']); ?> &middot;
                Status: <?php echo htmlspecialchars($o['order_status']); ?> &middot;
                <?php echo htmlspecialchars($o['created_at']); ?>
              </div>
            </div>
            <a href="order_details.php?id=<?php echo $o['order_id']; ?>" class="btn btn-sm btn-outline-primary">Details</a>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p class="text-muted"><?php echo $search !== '' ? 'No orders match your search.' : 'No orders yet.'; ?></p>
  <?php endif; ?>
</div>
<?php
$ordersStmt->close();
$conn->close();
include 'customer_footer.php';
?>
