<?php
// admin/admin_manage_users.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit(); }
require_once __DIR__ . '/../db_connect.php';

$flash = '';
$setFlash = function (string $message, string $type = 'success') use (&$flash) {
  $flash = sprintf('<div class="alert alert-%s alert-dismissible fade show" role="alert">%s<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>', htmlspecialchars($type), $message);
};

// Ensure tracking columns exist so selects don't fail
$chk1 = $conn->query("SHOW COLUMNS FROM customers LIKE 'last_login_at'");
if ($chk1 && $chk1->num_rows === 0) {
  @$conn->query("ALTER TABLE customers ADD COLUMN last_login_at DATETIME NULL AFTER created_at");
}
$chk2 = $conn->query("SHOW COLUMNS FROM customers LIKE 'last_login_ip'");
if ($chk2 && $chk2->num_rows === 0) {
  @$conn->query("ALTER TABLE customers ADD COLUMN last_login_ip VARCHAR(45) NULL AFTER last_login_at");
}
$chk3 = $conn->query("SHOW COLUMNS FROM customers LIKE 'is_flagged'");
if ($chk3 && $chk3->num_rows === 0) {
  @$conn->query("ALTER TABLE customers ADD COLUMN is_flagged TINYINT(1) DEFAULT 0 AFTER last_login_ip");
}
$chk4 = $conn->query("SHOW COLUMNS FROM customers LIKE 'flagged_reason'");
if ($chk4 && $chk4->num_rows === 0) {
  @$conn->query("ALTER TABLE customers ADD COLUMN flagged_reason TEXT NULL AFTER is_flagged");
}
$chk5 = $conn->query("SHOW COLUMNS FROM customers LIKE 'flagged_at'");
if ($chk5 && $chk5->num_rows === 0) {
  @$conn->query("ALTER TABLE customers ADD COLUMN flagged_at DATETIME NULL AFTER flagged_reason");
}

// Handle flag/unflag actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['customer_id'])) {
  $customerId = (int)$_POST['customer_id'];
  $action = $_POST['action'];

  if ($action === 'flag') {
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') {
      $setFlash('<strong>Flag not saved.</strong> Please provide a reason when flagging a customer.', 'warning');
    } else {
      if ($stmt = $conn->prepare("UPDATE customers SET is_flagged = 1, flagged_reason = ?, flagged_at = NOW() WHERE customer_id = ?")) {
        $stmt->bind_param('si', $reason, $customerId);
        if ($stmt->execute()) {
          $setFlash('<strong>User flagged.</strong> This account will now appear as suspicious.', 'success');
        } else {
          $setFlash('<strong>Error.</strong> Unable to flag the user at this time.', 'danger');
        }
        $stmt->close();
      }
    }
  } elseif ($action === 'unflag') {
    if ($stmt = $conn->prepare("UPDATE customers SET is_flagged = 0, flagged_reason = NULL, flagged_at = NULL WHERE customer_id = ?")) {
      $stmt->bind_param('i', $customerId);
      if ($stmt->execute()) {
        $setFlash('<strong>User unflagged.</strong> The account is no longer marked as suspicious.', 'info');
      } else {
        $setFlash('<strong>Error.</strong> Unable to update the account.', 'danger');
      }
      $stmt->close();
    }
  }
}

// Search/filter
$q = trim($_GET['q'] ?? '');
$rows = [];
if ($q !== '') {
  $like = '%' . $q . '%';
  $st = $conn->prepare('SELECT customer_id, name, email, phone_number, created_at, last_login_at, last_login_ip, is_flagged, flagged_reason, flagged_at FROM customers WHERE name LIKE ? OR email LIKE ? ORDER BY created_at DESC');
  $st->bind_param('ss', $like, $like);
} else {
  $st = $conn->prepare('SELECT customer_id, name, email, phone_number, created_at, last_login_at, last_login_ip, is_flagged, flagged_reason, flagged_at FROM customers ORDER BY created_at DESC');
}
if ($st) {
  $st->execute();
  $res = $st->get_result();
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $st->close();
}
?>
<?php include 'admin_header.php'; ?>

<h2 class="mb-3"><i class="fas fa-users"></i> Manage Users (Customers)</h2>

<?php echo $flash; ?>

<form method="GET" class="row g-2 mb-3">
  <div class="col-auto">
    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search name or email">
  </div>
  <div class="col-auto">
    <button class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
    <a href="admin_manage_users.php" class="btn btn-outline-secondary">Reset</a>
  </div>
  <div class="col-auto ms-auto">
    <a href="admin_manage_storeowners.php" class="btn btn-warning"><i class="fas fa-store"></i> Manage Store Owners</a>
  </div>
  <div class="col-12"><small class="text-muted">Read-only: Admins can view last login to detect suspicious access attempts.</small></div>
  <hr class="mt-3">
</form>

<div class="table-responsive shadow-sm">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Joined</th>
        <th>Last Login</th>
        <th>Login IP</th>
        <th>Status</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($rows) { foreach ($rows as $u) { ?>
      <tr class="<?php echo !empty($u['is_flagged']) ? 'table-warning' : ''; ?>">
        <td><?php echo (int)$u['customer_id']; ?></td>
        <td><?php echo htmlspecialchars($u['name']); ?></td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td><?php echo htmlspecialchars($u['phone_number']); ?></td>
        <td><?php echo htmlspecialchars($u['created_at'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($u['last_login_at'] ?? '—'); ?></td>
        <td><?php echo htmlspecialchars($u['last_login_ip'] ?? '—'); ?></td>
        <td>
          <?php if (!empty($u['is_flagged'])): ?>
            <span class="badge bg-danger">Flagged</span>
            <?php if (!empty($u['flagged_at'])): ?>
              <div class="small text-muted mt-1">on <?php echo htmlspecialchars($u['flagged_at']); ?></div>
            <?php endif; ?>
            <?php if (!empty($u['flagged_reason'])): ?>
              <div class="small text-muted mt-1"><em><?php echo nl2br(htmlspecialchars($u['flagged_reason'])); ?></em></div>
            <?php endif; ?>
          <?php else: ?>
            <span class="badge bg-success">Clean</span>
          <?php endif; ?>
        </td>
        <td class="text-end">
          <?php if (empty($u['is_flagged'])): ?>
            <form method="POST" class="d-inline flag-form">
              <input type="hidden" name="action" value="flag">
              <input type="hidden" name="customer_id" value="<?php echo (int)$u['customer_id']; ?>">
              <input type="hidden" name="reason" value="">
              <button type="button" class="btn btn-outline-danger btn-sm js-flag-btn">
                <i class="fas fa-flag"></i> Flag
              </button>
            </form>
          <?php else: ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="action" value="unflag">
              <input type="hidden" name="customer_id" value="<?php echo (int)$u['customer_id']; ?>">
              <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-flag-checkered"></i> Clear
              </button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php } } else { ?>
      <tr><td colspan="9" class="text-center text-muted">No users found.</td></tr>
    <?php } ?>
    </tbody>
  </table>
  <div class="p-2 text-muted small">Total: <?php echo count($rows); ?> user(s)</div>
</div>

<script>
  (function () {
    const flagButtons = document.querySelectorAll('.js-flag-btn');
    flagButtons.forEach((button) => {
      button.addEventListener('click', function () {
        const form = this.closest('form');
        if (!form) return;
        const reasonInput = form.querySelector('input[name="reason"]');
        const reason = window.prompt('Provide a reason for flagging this customer (visible to admins):', '');
        if (reason === null) {
          return;
        }
        const trimmed = reason.trim();
        if (!trimmed) {
          alert('Flag not saved. Please provide a reason.');
          return;
        }
        reasonInput.value = trimmed;
        form.submit();
      });
    });
  })();
</script>

<?php include 'admin_footer.php'; ?>
