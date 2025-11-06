<?php
// store_owner/store_owner_notifications.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/permissions.php';
set_role('store_owner');
require_role('store_owner');
require_once __DIR__ . '/../db_connect.php';

$ownerId = (int)($_SESSION['store_owner_id'] ?? 0);
if ($ownerId <= 0) {
    header('Location: store_owner_login.php');
    exit;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read_id'])) {
        $nid = (int)$_POST['mark_read_id'];
        if ($stmt = $conn->prepare("UPDATE store_owner_notifications SET is_read = 1 WHERE owner_id = ? AND id = ?")) {
            $stmt->bind_param('ii', $ownerId, $nid);
            $stmt->execute();
            $stmt->close();
        }
    }
    if (isset($_POST['mark_all'])) {
        if ($stmt = $conn->prepare("UPDATE store_owner_notifications SET is_read = 1 WHERE owner_id = ?")) {
            $stmt->bind_param('i', $ownerId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$notifications = [];
if ($stmt = $conn->prepare("SELECT n.id, n.message, n.is_read, n.created_at, gi.item_name, gi.quantity FROM store_owner_notifications n JOIN groceryitem gi ON gi.item_id = n.item_id WHERE n.owner_id = ? ORDER BY n.created_at DESC")) {
    $stmt->bind_param('i', $ownerId);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

include 'store_owner_header.php';
?>

<div class="mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="mb-0"><i class="fas fa-bell"></i> Low Stock Alerts</h2>
    <?php if (!empty($notifications)): ?>
      <form method="post">
        <button type="submit" name="mark_all" class="btn btn-sm btn-outline-secondary">Mark all as read</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!empty($notifications)): ?>
    <div class="list-group shadow-sm">
      <?php foreach ($notifications as $note): ?>
        <div class="list-group-item <?php echo $note['is_read'] ? '' : 'list-group-item-warning'; ?>">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h5 class="mb-1"><?php echo htmlspecialchars($note['item_name'] ?? 'Item'); ?></h5>
              <p class="mb-1"><?php echo nl2br(htmlspecialchars($note['message'])); ?></p>
              <small class="text-muted"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($note['created_at']))); ?></small>
              <?php if (isset($note['quantity'])): ?>
                <div class="text-muted small">Current stock: <?php echo (int)$note['quantity']; ?></div>
              <?php endif; ?>
            </div>
            <?php if (!$note['is_read']): ?>
              <form method="post" class="ms-3">
                <input type="hidden" name="mark_read_id" value="<?php echo (int)$note['id']; ?>">
                <button type="submit" class="btn btn-sm btn-outline-primary">Mark as read</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-success">No low stock alerts at the moment.</div>
  <?php endif; ?>
</div>

<?php include 'store_owner_footer.php'; ?>
