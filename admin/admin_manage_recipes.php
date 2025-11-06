<?php 
// admin/admin_manage_recipes.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$conn = new mysqli("localhost", "root", "", "grocerygenie");

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Ensure moderation columns/table exist
$conn->query("CREATE TABLE IF NOT EXISTS recipe_moderation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    comment TEXT,
    previous_status VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipe_moderation_logs_recipe (recipe_id),
    CONSTRAINT fk_recipe_moderation_logs_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(recipe_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_id INT NOT NULL DEFAULT 0,
    type VARCHAR(50) DEFAULT 'order',
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure moderation columns exist once
$requiredColumns = [
    'last_moderated_by INT NULL',
    'last_moderated_at DATETIME NULL',
    'last_moderation_comment TEXT NULL'
];

foreach ($requiredColumns as $columnDef) {
    $columnName = trim(strtok($columnDef, ' '));
    $exists = $conn->query("SHOW COLUMNS FROM recipe LIKE '{$columnName}'");
    if ($exists && $exists->num_rows === 0) {
        $conn->query("ALTER TABLE recipe ADD COLUMN {$columnDef} AFTER status");
    }
}

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

// Approve or Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipe_id'], $_POST['moderation_action'])) {
    $recipeId = (int)$_POST['recipe_id'];
    $action = $_POST['moderation_action'];
    $comment = trim($_POST['moderation_comment'] ?? '');
    $adminId = (int)$_SESSION['admin_id'];

    if ($comment === '') {
        $_SESSION['admin_flash'] = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><strong>Action not saved.</strong> Please provide a comment when moderating.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        header("Location: admin_manage_recipes.php");
        exit();
    }

    $currentStatus = null;
    $recipeCreator = null;
    $recipeName = '';
    if ($statusStmt = $conn->prepare("SELECT status, created_by, recipe_name FROM recipe WHERE recipe_id = ?")) {
        $statusStmt->bind_param("i", $recipeId);
        $statusStmt->execute();
        $statusStmt->bind_result($currentStatus, $recipeCreator, $recipeName);
        $statusStmt->fetch();
        $statusStmt->close();
    }

    if ($currentStatus === null) {
        $_SESSION['admin_flash'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Recipe not found.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        header("Location: admin_manage_recipes.php");
        exit();
    }

    $statusMap = [
        'approve' => 'approved',
        'reject' => 'rejected',
        'request_changes' => 'needs_revision'
    ];

    if (!isset($statusMap[$action])) {
        $_SESSION['admin_flash'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Invalid moderation action.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        header("Location: admin_manage_recipes.php");
        exit();
    }

    $newStatus = $statusMap[$action];

    if ($update = $conn->prepare("UPDATE recipe SET status = ?, last_moderated_by = ?, last_moderated_at = NOW(), last_moderation_comment = ? WHERE recipe_id = ?")) {
        $update->bind_param("sisi", $newStatus, $adminId, $comment, $recipeId);
        if ($update->execute()) {
            if ($log = $conn->prepare("INSERT INTO recipe_moderation_logs (recipe_id, admin_id, action, comment, previous_status) VALUES (?, ?, ?, ?, ?)")) {
                $log->bind_param("iisss", $recipeId, $adminId, $action, $comment, $currentStatus);
                $log->execute();
                $log->close();
            }
            $messageMap = [
                'approve' => 'Recipe approved and published.',
                'reject' => 'Recipe rejected.',
                'request_changes' => 'Requested changes from the recipe author.'
            ];
            $_SESSION['admin_flash'] = sprintf('<div class="alert alert-success alert-dismissible fade show" role="alert">%s<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>', $messageMap[$action]);
        } else {
            $_SESSION['admin_flash'] = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Unable to update the recipe status.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
        $update->close();

        if ($recipeCreator) {
            $notificationType = 'recipe_moderation';
            $messageTemplates = [
                'approve' => "Great news! Your recipe \"%s\" has been approved.\n\nAdmin note: %s",
                'reject' => "Unfortunately, your recipe \"%s\" was rejected.\n\nAdmin note: %s",
                'request_changes' => "Your recipe \"%s\" needs some revisions before it can be approved.\n\nAdmin note: %s"
            ];
            $template = $messageTemplates[$action] ?? 'Update on your recipe "%s". Admin note: %s';
            $notificationMessage = sprintf($template, $recipeName ?: 'Your recipe', $comment);

            if ($colCheck = $conn->query("SHOW COLUMNS FROM notifications LIKE 'chat_user_id'")) {
                if ($colCheck->num_rows === 0) {
                    $conn->query("ALTER TABLE notifications ADD COLUMN chat_user_id INT NULL AFTER order_id");
                }
                $colCheck->close();
            }
            if ($colCheck = $conn->query("SHOW COLUMNS FROM notifications LIKE 'recipe_id'")) {
                if ($colCheck->num_rows === 0) {
                    $conn->query("ALTER TABLE notifications ADD COLUMN recipe_id INT NULL AFTER chat_user_id");
                }
                $colCheck->close();
            }

            if ($notifStmt = $conn->prepare("INSERT INTO notifications (customer_id, order_id, type, message, is_read, recipe_id, created_at) VALUES (?, 0, ?, ?, 0, ?, NOW())")) {
                $notifStmt->bind_param("issi", $recipeCreator, $notificationType, $notificationMessage, $recipeId);
                $notifStmt->execute();
                $notifStmt->close();
            }
        }
    }

    header("Location: admin_manage_recipes.php");
    exit();
}

$statusFilter = $_GET['status'] ?? 'all';
$dietaryFilter = trim($_GET['dietary'] ?? '');
$searchTerm = trim($_GET['q'] ?? '');

$pendingCount = 0;
$needsRevisionCount = 0;
if ($res = $conn->query("SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_total,
        SUM(CASE WHEN status = 'needs_revision' THEN 1 ELSE 0 END) AS revision_total
    FROM recipe")) {
    $row = $res->fetch_assoc();
    $pendingCount = (int)($row['pending_total'] ?? 0);
    $needsRevisionCount = (int)($row['revision_total'] ?? 0);
    $res->close();
}

$dietaryOptions = [];
if ($dietRes = $conn->query("SELECT DISTINCT dietary_tag FROM recipe WHERE dietary_tag IS NOT NULL AND dietary_tag <> '' ORDER BY dietary_tag ASC")) {
    while ($opt = $dietRes->fetch_assoc()) {
        $dietaryOptions[] = $opt['dietary_tag'];
    }
    $dietRes->close();
}

$conditions = ["status IN ('pending','needs_revision')"];
$types = '';
$params = [];

if (in_array($statusFilter, ['pending', 'needs_revision'], true)) {
    $conditions[] = 'status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}

if ($dietaryFilter !== '') {
    $conditions[] = 'dietary_tag = ?';
    $types .= 's';
    $params[] = $dietaryFilter;
}

if ($searchTerm !== '') {
    $conditions[] = '(recipe_name LIKE ? OR created_by LIKE ?)';
    $like = '%' . $searchTerm . '%';
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

$sql = 'SELECT recipe_id, recipe_name, recipe_description, steps, total_cost, dietary_tag, price_range, recipe_category, recipe_image, bring_your_own, created_by, created_at, status FROM recipe WHERE ' . implode(' AND ', $conditions) . ' ORDER BY created_at DESC';
$recipeRows = [];
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $recipeRows[] = $row;
    }
    $stmt->close();
}

$logsByRecipe = [];
if ($recipeRows) {
    $recipeIds = array_column($recipeRows, 'recipe_id');
    if (!empty($recipeIds)) {
        $placeholders = implode(',', array_fill(0, count($recipeIds), '?'));
        $typesLogs = str_repeat('i', count($recipeIds));
        if ($stmtLogs = $conn->prepare("SELECT l.*, a.name AS admin_name FROM recipe_moderation_logs l LEFT JOIN admins a ON l.admin_id = a.admin_id WHERE l.recipe_id IN ($placeholders) ORDER BY l.created_at DESC")) {
            $stmtLogs->bind_param($typesLogs, ...$recipeIds);
            $stmtLogs->execute();
            $resLogs = $stmtLogs->get_result();
            while ($logRow = $resLogs->fetch_assoc()) {
                $rid = $logRow['recipe_id'];
                if (!isset($logsByRecipe[$rid])) {
                    $logsByRecipe[$rid] = [];
                }
                $logsByRecipe[$rid][] = $logRow;
            }
            $stmtLogs->close();
        }
    }
}
$totalAwaiting = count($recipeRows);
?>

<?php include 'admin_header.php'; ?> 

<style>
  .recipe-admin-wrapper {
    margin: 2.5rem 0 3.5rem;
    display: flex;
    flex-direction: column;
    gap: 2rem;
  }
  .recipe-hero {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 2rem;
    padding: 2.8rem 2.4rem;
    border-radius: 26px;
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.92), rgba(16, 185, 129, 0.85));
    color: #ffffff;
    box-shadow: 0 32px 70px rgba(15, 23, 42, 0.28);
    overflow: hidden;
  }
  .recipe-hero::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(255,255,255,0.24), transparent 60%);
    pointer-events: none;
  }
  .recipe-hero h1 {
    font-weight: 700;
    font-size: clamp(2rem, 2.8vw, 2.6rem);
    margin-bottom: 0.75rem;
  }
  .recipe-hero p {
    max-width: 560px;
    color: rgba(255, 255, 255, 0.86);
  }
  .recipe-stat-group {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-self: center;
  }
  .recipe-stat {
    background: rgba(255, 255, 255, 0.18);
    border-radius: 18px;
    padding: 1rem 1.4rem;
    min-width: 160px;
    text-align: center;
    backdrop-filter: blur(6px);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
  }
  .recipe-stat small {
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.72);
  }
  .recipe-stat strong {
    display: block;
    font-size: 1.8rem;
    margin-top: 0.35rem;
  }
  .recipe-filters {
    margin-top: 0;
  }
  .filters-card {
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 22px 45px rgba(15, 23, 42, 0.16);
    padding: 2rem 2.2rem;
  }
  .filters-card__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
  }
  .filters-card__header h2 {
    margin: 0;
    font-weight: 700;
    font-size: 1.15rem;
    color: #111827;
  }
  .filters-card__hint {
    font-size: 0.92rem;
    color: #64748b;
  }
  .filters-card label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 0.35rem;
  }
  .filters-card .form-control,
  .filters-card .form-select {
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.4);
    box-shadow: none;
  }
  .filters-card .btn {
    border-radius: 999px;
    font-weight: 600;
  }
  .recipe-grid {
    display: grid;
    gap: 1.75rem;
  }
  @media (min-width: 768px) {
    .recipe-grid {
      grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
    }
  }
  .recipe-card {
    position: relative;
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 26px 60px rgba(15, 23, 42, 0.14);
    display: flex;
    flex-direction: column;
    height: 100%;
    border: 1px solid rgba(15, 23, 42, 0.06);
    overflow: hidden;
  }
  .recipe-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #4361ee, #22c55e);
  }
  .recipe-card--revision::before {
    background: linear-gradient(90deg, #fbbf24, #f97316);
  }
  .recipe-card__header {
    padding: 1.8rem 2rem 1.4rem;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
  }
  .recipe-card__header h3 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 700;
  }
  .badge-status {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    border-radius: 999px;
    padding: 0.4rem 0.8rem;
  }
  .badge-status--pending {
    background: rgba(67, 97, 238, 0.12);
    color: #1d4ed8;
  }
  .badge-status--revision {
    background: rgba(250, 204, 21, 0.18);
    color: #92400e;
  }
  .recipe-card__meta {
    padding: 1.4rem 2rem 1rem;
    display: grid;
    gap: 1rem;
  }
  .recipe-card__meta p {
    margin: 0;
    color: #475569;
  }
  .meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.75rem;
  }
  .meta-pill {
    padding: 0.55rem 0.75rem;
    border-radius: 16px;
    background: rgba(15, 23, 42, 0.05);
    font-size: 0.85rem;
    font-weight: 500;
    color: #1f2937;
  }
  .meta-note {
    border-radius: 16px;
    background: rgba(250, 204, 21, 0.16);
    color: #92400e;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    display: flex;
    gap: 0.6rem;
    align-items: center;
  }
  .ingredient-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  .ingredient-tags span {
    background: rgba(14, 165, 233, 0.1);
    color: #0284c7;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
  }
  .recipe-steps-wrapper {
    margin-top: 0.5rem;
  }
  .recipe-steps {
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 18px;
    background: rgba(248, 250, 252, 0.7);
    padding: 0.9rem 1.1rem;
  }
  .recipe-steps summary {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-weight: 600;
    cursor: pointer;
    color: #1d4ed8;
    outline: none;
  }
  .recipe-steps summary::-webkit-details-marker {
    display: none;
  }
  .step-toggle-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(29, 78, 216, 0.12);
    color: #1d4ed8;
    transition: transform 0.2s ease, background 0.2s ease;
  }
  .recipe-steps summary:hover .step-toggle-icon {
    background: rgba(29, 78, 216, 0.2);
  }
  .recipe-steps[open] summary .step-toggle-icon {
    transform: rotate(90deg);
  }
  .recipe-step-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1rem;
  }
  .recipe-step-item {
    display: flex;
    gap: 1.1rem;
    align-items: flex-start;
    padding: 0.9rem 1rem;
    border-radius: 16px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
  }
  .recipe-step-number {
    flex: 0 0 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(67, 97, 238, 0.16);
    color: #1d4ed8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.95rem;
  }
  .recipe-step-body {
    flex: 1 1 auto;
    color: #1f2937;
    line-height: 1.6;
  }
  .history-card {
    padding: 1.2rem 2rem;
    border-top: 1px solid rgba(15, 23, 42, 0.06);
    background: rgba(248, 250, 252, 0.85);
  }
  .history-entry + .history-entry {
    border-top: 1px solid rgba(15, 23, 42, 0.1);
    margin-top: 0.8rem;
    padding-top: 0.8rem;
  }
  .recipe-card__footer {
    padding: 1.4rem 2rem 1.8rem;
    margin-top: auto;
    border-top: 1px solid rgba(15, 23, 42, 0.06);
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    justify-content: flex-end;
  }
  .recipe-card__footer .btn {
    border-radius: 999px;
    font-weight: 600;
  }
  .recipe-empty {
    background: rgba(255, 255, 255, 0.88);
    border-radius: 22px;
    padding: 3rem 2rem;
    text-align: center;
    box-shadow: 0 26px 60px rgba(15, 23, 42, 0.14);
    color: #64748b;
  }
  @media (max-width: 991.98px) {
    .recipe-hero {
      grid-template-columns: minmax(0, 1fr);
      text-align: left;
    }
    .recipe-stat-group {
      justify-content: flex-start;
    }
  }
</style>

<div class="container recipe-admin-wrapper">
  <?php echo $flash; ?>

  <section class="recipe-hero">
    <div>
      <span class="badge bg-dark-subtle text-dark fw-semibold text-uppercase mb-2"><i class="bi bi-journal-text me-2"></i>Moderation queue</span>
      <h1>Review recipes awaiting approval</h1>
      <p>Give authors timely feedback, approve high-quality submissions, or request revisions with clear guidance. Your decisions keep the catalogue trustworthy.</p>
    </div>
    <div class="recipe-stat-group">
      <div class="recipe-stat">
        <small>Pending review</small>
        <strong><?php echo number_format($pendingCount); ?></strong>
      </div>
      <div class="recipe-stat">
        <small>Needs revision</small>
        <strong><?php echo number_format($needsRevisionCount); ?></strong>
      </div>
      <div class="recipe-stat">
        <small>In current view</small>
        <strong><?php echo number_format($totalAwaiting); ?></strong>
      </div>
    </div>
  </section>

  <section class="recipe-filters">
    <div class="filters-card">
      <div class="filters-card__header">
        <h2><i class="bi bi-funnel me-2 text-primary"></i>Filter submissions</h2>
        <div class="filters-card__hint">Combine search, status, and dietary tags to focus on the recipes that need your attention first.</div>
      </div>
      <form class="row g-3 align-items-end">
        <div class="col-md-4">
          <label for="queueSearchInput" class="form-label">Search</label>
          <input type="text" class="form-control" id="queueSearchInput" name="q" placeholder="Search by recipe or author" value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <div class="col-md-3">
          <label for="statusFilter" class="form-label">Status</label>
          <select class="form-select" id="statusFilter" name="status">
            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Pending & needs revision</option>
            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending only</option>
            <option value="needs_revision" <?php echo $statusFilter === 'needs_revision' ? 'selected' : ''; ?>>Needs revision only</option>
          </select>
        </div>
        <div class="col-md-3">
          <label for="dietaryFilter" class="form-label">Dietary tag</label>
          <select class="form-select" id="dietaryFilter" name="dietary">
            <option value="" <?php echo $dietaryFilter === '' ? 'selected' : ''; ?>>All tags</option>
            <?php foreach ($dietaryOptions as $option): ?>
              <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $dietaryFilter === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-filter me-1"></i>Apply</button>
          <a href="admin_manage_recipes.php" class="btn btn-outline-secondary btn-sm px-3"><i class="fas fa-rotate-left"></i></a>
        </div>
      </form>
    </div>
  </section>

  <?php if (!empty($recipeRows)): ?>
    <div class="recipe-grid">
      <?php foreach ($recipeRows as $row): ?>
        <?php
          $ingredients = [];
          if ($stmt = $conn->prepare("SELECT g.item_name, ri.quantity FROM recipe_ingredients ri JOIN groceryitem g ON ri.item_id = g.item_id WHERE ri.recipe_id = ?")) {
              $stmt->bind_param("i", $row['recipe_id']);
              $stmt->execute();
              $resIng = $stmt->get_result();
              while ($ing = $resIng->fetch_assoc()) {
                  $qty = $ing['quantity'];
                  $ingredients[] = $ing['item_name'] . (!empty($qty) ? " ({$qty})" : '');
              }
              $stmt->close();
          }
          $statusClass = $row['status'] === 'needs_revision' ? 'recipe-card--revision' : '';
          $statusBadgeClass = $row['status'] === 'needs_revision' ? 'badge-status--revision' : 'badge-status--pending';
          $statusLabel = str_replace('_', ' ', ucfirst($row['status']));
        ?>
        <article class="recipe-card <?php echo $statusClass; ?>">
          <div class="recipe-card__header">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <h3><?php echo htmlspecialchars($row['recipe_name']); ?></h3>
                <div class="text-muted small">Submitted by <?php echo htmlspecialchars($row['created_by']); ?> • <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></div>
              </div>
              <span class="badge badge-status <?php echo $statusBadgeClass; ?>">
                <?php echo htmlspecialchars($statusLabel); ?>
              </span>
            </div>
            <?php if (!empty($row['last_moderation_comment'])): ?>
              <div class="alert alert-warning mt-3 mb-0 py-2 px-3 small">
                <strong>Last note:</strong> <?php echo nl2br(htmlspecialchars($row['last_moderation_comment'])); ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="recipe-card__meta">
            <p><?php echo !empty($row['recipe_description']) ? nl2br(htmlspecialchars($row['recipe_description'])) : '<span class="text-muted">No description provided.</span>'; ?></p>
            <div class="meta-grid">
              <div class="meta-pill"><i class="bi bi-cash-stack me-1 text-success"></i>Total cost: RM <?php echo number_format((float)$row['total_cost'], 2); ?></div>
              <div class="meta-pill"><i class="bi bi-tag me-1 text-info"></i>Budget: <?php echo htmlspecialchars($row['price_range'] ?: '—'); ?></div>
              <div class="meta-pill"><i class="bi bi-leaf me-1 text-warning"></i>Dietary: <?php echo htmlspecialchars($row['dietary_tag'] ?: 'None'); ?></div>
            </div>
              <div class="meta-note">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                  <strong class="d-block">Bring your own</strong>
                  <?php echo !empty($row['bring_your_own']) ? nl2br(htmlspecialchars($row['bring_your_own'])) : '<span class="text-muted">No additional notes.</span>'; ?>
                </div>
              </div>
            <div class="recipe-steps-wrapper">
              <?php
                $stepsRaw = trim((string)($row['steps'] ?? ''));
                if ($stepsRaw !== '') {
                    $normalizedSteps = preg_replace("/\r\n?/", "\n", $stepsRaw);
                    $stepsChunks = array_filter(array_map('trim', preg_split('/\n+/', $normalizedSteps) ?: []));
                    if (!empty($stepsChunks)) {
                        $totalSteps = count($stepsChunks);
                        echo '<details class="recipe-steps">';
                        echo '<summary><span class="step-toggle-icon"><i class="bi bi-chevron-right"></i></span><span>View cooking steps (' . $totalSteps . ')</span></summary>';
                        echo '<div class="recipe-step-list">';
                        $stepNumber = 1;
                        foreach ($stepsChunks as $chunk) {
                            echo '<div class="recipe-step-item"><div class="recipe-step-number">' . $stepNumber . '</div><div class="recipe-step-body">' . nl2br(htmlspecialchars($chunk)) . '</div></div>';
                            $stepNumber++;
                        }
                        echo '</div>';
                        echo '</details>';
                    } else {
                        echo '<p class="text-muted mb-0">No steps provided by the creator.</p>';
                    }
                } else {
                    echo '<p class="text-muted mb-0">No steps provided by the creator.</p>';
                }
              ?>
            </div>
            <?php if (!empty($ingredients)): ?>
              <div>
                <strong>Ingredients:</strong>
                <div class="ingredient-tags">
                  <?php foreach ($ingredients as $tag): ?>
                    <span><?php echo htmlspecialchars($tag); ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
            <?php if (!empty($row['recipe_image'])): ?>
              <div class="mt-2">
                <img src="../uploads/recipes/<?php echo htmlspecialchars($row['recipe_image']); ?>" alt="Recipe image" class="img-fluid rounded shadow-sm" style="max-height: 180px; object-fit: cover;">
              </div>
            <?php endif; ?>
          </div>
          <div class="history-card">
            <?php if (!empty($logsByRecipe[$row['recipe_id']])): ?>
              <div class="small text-uppercase fw-semibold text-muted mb-2">Moderation history</div>
              <?php foreach ($logsByRecipe[$row['recipe_id']] as $log): ?>
                <div class="history-entry small">
                  <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))); ?></strong>
                  by <?php echo htmlspecialchars($log['admin_name'] ?? 'Admin #'.$log['admin_id']); ?>
                  <span class="text-muted">— <?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?></span>
                  <?php if (!empty($log['comment'])): ?>
                    <div class="mt-1 text-muted"><?php echo nl2br(htmlspecialchars($log['comment'])); ?></div>
                  <?php endif; ?>
                  <?php if (!empty($log['previous_status'])): ?>
                    <div class="text-muted"><small>Previous status: <?php echo htmlspecialchars($log['previous_status']); ?></small></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="text-muted small">No moderation actions recorded yet.</div>
            <?php endif; ?>
          </div>
          <div class="recipe-card__footer">
            <button type="button"
                    class="btn btn-outline-success btn-sm js-open-moderation"
                    data-recipe-id="<?php echo $row['recipe_id']; ?>"
                    data-recipe-name="<?php echo htmlspecialchars($row['recipe_name']); ?>"
                    data-action="approve">
              <i class="bi bi-check-circle me-1"></i>Approve
            </button>
            <button type="button"
                    class="btn btn-outline-warning btn-sm js-open-moderation"
                    data-recipe-id="<?php echo $row['recipe_id']; ?>"
                    data-recipe-name="<?php echo htmlspecialchars($row['recipe_name']); ?>"
                    data-action="request_changes">
              <i class="bi bi-pencil-square me-1"></i>Request edits
            </button>
            <button type="button"
                    class="btn btn-outline-danger btn-sm js-open-moderation"
                    data-recipe-id="<?php echo $row['recipe_id']; ?>"
                    data-recipe-name="<?php echo htmlspecialchars($row['recipe_name']); ?>"
                    data-action="reject">
              <i class="bi bi-x-circle me-1"></i>Reject
            </button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="recipe-empty">
      <i class="bi bi-emoji-smile mb-3 d-block fs-2"></i>
      No recipes match your current filters. Great job staying on top of approvals!
    </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="moderationModal" tabindex="-1" aria-labelledby="moderationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" class="needs-validation" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="moderationModalLabel">Moderate recipe</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="recipe_id" value="">
          <input type="hidden" name="moderation_action" value="">
          <div class="mb-3">
            <label class="form-label text-muted text-uppercase small">Recipe</label>
            <div class="fw-semibold" id="modalRecipeName"></div>
          </div>
          <div class="mb-3">
            <label for="moderationCommentInput" class="form-label">Leave a note for the author</label>
            <textarea class="form-control" id="moderationCommentInput" name="moderation_comment" rows="4" placeholder="Explain your decision so the author can act quickly." required></textarea>
            <div class="invalid-feedback">Please share a short comment.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit decision</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    const modal = document.getElementById('moderationModal');
    const modalForm = modal.querySelector('form');
    const recipeNameEl = document.getElementById('modalRecipeName');
    const commentInput = document.getElementById('moderationCommentInput');
    const actionLabels = {
      approve: 'Approve recipe',
      request_changes: 'Request edits',
      reject: 'Reject recipe'
    };

    document.querySelectorAll('.js-open-moderation').forEach((button) => {
      button.addEventListener('click', () => {
        const recipeId = button.getAttribute('data-recipe-id');
        const recipeName = button.getAttribute('data-recipe-name');
        const action = button.getAttribute('data-action');

        modalForm.querySelector('input[name="recipe_id"]').value = recipeId;
        modalForm.querySelector('input[name="moderation_action"]').value = action;
        recipeNameEl.textContent = recipeName;
        commentInput.value = '';

        const title = actionLabels[action] || 'Moderate recipe';
        modal.querySelector('.modal-title').textContent = title;

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
      });
    });

    modalForm.addEventListener('submit', (event) => {
      if (!modalForm.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      modalForm.classList.add('was-validated');
    }, false);
  })();
</script>

<?php include 'admin_footer.php'; ?>


