<?php
// customer/notifications.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['customer_id'])) { header('Location: customer_login.php'); exit(); }
require_once __DIR__ . '/../db_connect.php';
$customer_id = (int)$_SESSION['customer_id'];

// Ensure table exists
@$conn->query("CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  order_id INT NOT NULL,
  type VARCHAR(50) DEFAULT 'order',
  message TEXT,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Mark as read
if (isset($_GET['mark']) && $_GET['mark'] === 'all') {
  if ($st = $conn->prepare('UPDATE notifications SET is_read=1 WHERE customer_id=?')) {
    $st->bind_param('i', $customer_id);
    $st->execute();
    $st->close();
  }
  header('Location: notifications.php');
  exit();
}

// Ensure chat_user_id column exists
$col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'chat_user_id'");
if ($col && $col->num_rows === 0) { @ $conn->query("ALTER TABLE notifications ADD COLUMN chat_user_id INT NULL AFTER order_id"); }
if ($col) { $col->close(); }
$col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'recipe_id'");
if ($col && $col->num_rows === 0) { @ $conn->query("ALTER TABLE notifications ADD COLUMN recipe_id INT NULL AFTER chat_user_id"); }
if ($col) { $col->close(); }

// Fetch order notifications
$orders = [];
if ($st = $conn->prepare("SELECT id, order_id, message, is_read, created_at FROM notifications WHERE customer_id=? AND (type='order' OR type IS NULL) ORDER BY created_at DESC LIMIT 100")) {
  $st->bind_param('i', $customer_id);
  $st->execute();
  $res = $st->get_result();
  while ($r = $res->fetch_assoc()) $orders[] = $r;
  $st->close();
}

// Fetch chat notifications
$chats = [];
if ($st = $conn->prepare("SELECT n.id, n.chat_user_id, n.message, n.is_read, n.created_at, c.name AS from_name FROM notifications n LEFT JOIN customers c ON c.customer_id = n.chat_user_id WHERE n.customer_id=? AND n.type='chat' ORDER BY n.created_at DESC LIMIT 100")) {
  $st->bind_param('i', $customer_id);
  $st->execute();
  $res = $st->get_result();
  while ($r = $res->fetch_assoc()) $chats[] = $r;
  $st->close();
}

$shares = [];
if ($st = $conn->prepare("SELECT n.id, n.recipe_id, n.message, n.is_read, n.created_at, r.recipe_name FROM notifications n LEFT JOIN recipe r ON r.recipe_id = n.recipe_id WHERE n.customer_id=? AND n.type='recipe_share' ORDER BY n.created_at DESC LIMIT 100")) {
  $st->bind_param('i', $customer_id);
  $st->execute();
  $res = $st->get_result();
  while ($r = $res->fetch_assoc()) $shares[] = $r;
  $st->close();
}

$moderations = [];
if ($st = $conn->prepare("SELECT n.id, n.recipe_id, n.message, n.is_read, n.created_at, r.recipe_name, r.status FROM notifications n LEFT JOIN recipe r ON r.recipe_id = n.recipe_id WHERE n.customer_id=? AND n.type='recipe_moderation' ORDER BY n.created_at DESC LIMIT 100")) {
  $st->bind_param('i', $customer_id);
  $st->execute();
  $res = $st->get_result();
  while ($r = $res->fetch_assoc()) $moderations[] = $r;
  $st->close();
}

include 'customer_header.php';
?>

<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="fas fa-bell"></i> Notifications</h3>
    <a class="btn btn-sm btn-outline-secondary" href="notifications.php?mark=all">Mark all as read</a>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header"><i class="fas fa-receipt"></i> Order Notifications</div>
        <div class="list-group list-group-flush">
          <?php if ($orders): foreach ($orders as $n): ?>
            <a href="order_receipt.php?order_id=<?php echo (int)$n['order_id']; ?>" class="list-group-item list-group-item-action <?php echo $n['is_read'] ? '' : 'active'; ?>">
              <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1">Order #<?php echo (int)$n['order_id']; ?></h6>
                <small><?php echo htmlspecialchars($n['created_at']); ?></small>
              </div>
              <p class="mb-1"><?php echo htmlspecialchars($n['message']); ?></p>
              <small>Click to view your invoice.</small>
            </a>
          <?php endforeach; else: ?>
            <div class="list-group-item text-muted">No order notifications.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header"><i class="fas fa-comments"></i> Chat Notifications</div>
        <div class="list-group list-group-flush">
          <?php if ($chats): foreach ($chats as $c): ?>
            <a href="chat.php?user_id=<?php echo (int)$c['chat_user_id']; ?>" class="list-group-item list-group-item-action <?php echo $c['is_read'] ? '' : 'active'; ?>">
              <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1">From <?php echo htmlspecialchars($c['from_name'] ?: 'Friend'); ?></h6>
                <small><?php echo htmlspecialchars($c['created_at']); ?></small>
              </div>
              <p class="mb-1"><?php echo htmlspecialchars($c['message']); ?></p>
              <small>Click to open chat.</small>
            </a>
          <?php endforeach; else: ?>
            <div class="list-group-item text-muted">No chat notifications.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-2">
    <div class="col-md-12">
      <div class="card shadow-sm">
        <div class="card-header"><i class="fas fa-clipboard-check"></i> Recipe Review Updates</div>
        <div class="list-group list-group-flush">
          <?php if ($moderations): foreach ($moderations as $m): ?>
            <a href="recipe_details.php?recipe_id=<?php echo (int)$m['recipe_id']; ?>" class="list-group-item list-group-item-action <?php echo $m['is_read'] ? '' : 'active'; ?>">
              <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1"><?php echo htmlspecialchars($m['recipe_name'] ?? 'Your recipe'); ?></h6>
                <small><?php echo htmlspecialchars($m['created_at']); ?></small>
              </div>
              <p class="mb-1"><?php echo nl2br(htmlspecialchars($m['message'])); ?></p>
              <?php if (!empty($m['status'])): ?>
                <small class="text-muted">Current status: <strong><?php echo htmlspecialchars(ucfirst($m['status'])); ?></strong></small>
              <?php else: ?>
                <small class="text-muted">Click to view your submission.</small>
              <?php endif; ?>
            </a>
          <?php endforeach; else: ?>
            <div class="list-group-item text-muted">No recipe moderation updates yet.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-2">
    <div class="col-md-12">
      <div class="card shadow-sm">
        <div class="card-header"><i class="fas fa-share-alt"></i> Shared Recipes</div>
        <div class="list-group list-group-flush">
          <?php if ($shares): foreach ($shares as $s): ?>
            <a href="recipe_details.php?recipe_id=<?php echo (int)$s['recipe_id']; ?>" class="list-group-item list-group-item-action <?php echo $s['is_read'] ? '' : 'active'; ?>">
              <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1">Recipe: <?php echo htmlspecialchars($s['recipe_name'] ?? 'View Recipe'); ?></h6>
                <small><?php echo htmlspecialchars($s['created_at']); ?></small>
              </div>
              <p class="mb-1"><?php echo htmlspecialchars($s['message'] ?: 'A friend shared a recipe with you.'); ?></p>
              <small>Click to view recipe details.</small>
            </a>
          <?php endforeach; else: ?>
            <div class="list-group-item text-muted">No recipe shares yet.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'customer_footer.php'; ?>
