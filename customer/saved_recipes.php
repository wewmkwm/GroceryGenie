<?php
// customer/saved_recipes.php

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: customer_login.php');
    exit;
}

require_once __DIR__ . '/../db_connect.php';
include 'customer_header.php';

$customerId = intval($_SESSION['customer_id']);

$conn->query("CREATE TABLE IF NOT EXISTS saved_recipes (
    customer_id INT NOT NULL,
    recipe_id INT NOT NULL,
    saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id, recipe_id),
    CONSTRAINT fk_saved_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(recipe_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$search = trim($_GET['q'] ?? '');
$dietaryFilter = trim($_GET['dietary'] ?? '');

$savedRecipes = [];
$types = 'i';
$params = [$customerId];
$sql = "
    SELECT r.recipe_id, r.recipe_name, r.recipe_description, r.recipe_image, r.price_range, r.dietary_tag, r.status, sr.saved_at
    FROM saved_recipes sr
    JOIN recipe r ON r.recipe_id = sr.recipe_id
    WHERE sr.customer_id = ?
";

if ($search !== '') {
    $sql .= " AND (r.recipe_name LIKE ? OR r.recipe_description LIKE ? OR r.price_range LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($dietaryFilter !== '') {
    $sql .= " AND r.dietary_tag = ?";
    $types .= 's';
    $params[] = $dietaryFilter;
}

$sql .= " ORDER BY sr.saved_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $savedRecipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$dietaryOptions = [];
if ($tagStmt = $conn->prepare("SELECT DISTINCT dietary_tag FROM saved_recipes sr JOIN recipe r ON r.recipe_id = sr.recipe_id WHERE sr.customer_id = ? AND r.dietary_tag IS NOT NULL AND r.dietary_tag <> '' ORDER BY r.dietary_tag ASC")) {
    $tagStmt->bind_param('i', $customerId);
    $tagStmt->execute();
    $tagRes = $tagStmt->get_result();
    while ($row = $tagRes->fetch_assoc()) {
        $dietaryOptions[] = $row['dietary_tag'];
    }
    $tagStmt->close();
}


?>

<style>
  .saved-hero {
    background: linear-gradient(135deg, rgba(255, 145, 77, 0.12), rgba(67, 97, 238, 0.14));
    border-radius: 28px;
    padding: 2.5rem 2rem;
    box-shadow: 0 20px 35px rgba(15, 23, 42, 0.1);
  }
  .saved-hero h3 { font-weight: 700; margin-bottom: 0.5rem; }
  .saved-hero p { color: #6b7280; margin-bottom: 0; }
  .saved-filter-card {
    background: #ffffff;
    border-radius: 22px;
    border: 1px solid rgba(226, 232, 240, 0.6);
    box-shadow: 0 18px 30px rgba(15, 23, 42, 0.08);
    padding: 1.75rem;
    margin-top: 1.5rem;
  }
  .saved-filter-card .input-group-text {
    background: transparent;
    border-right: 0;
    color: #4b5563;
  }
  .saved-filter-card .form-control {
    border-left: 0;
    border-radius: 999px;
    box-shadow: none;
  }
  .saved-filter-card select {
    border-radius: 999px;
    box-shadow: none;
  }
  .saved-filter-card .btn {
    border-radius: 999px;
    font-weight: 600;
  }
  .saved-meta {
    color: #9ca3af;
    font-weight: 600;
  }
  .saved-recipes-grid { margin-top: 2rem; }
  .saved-card {
    border-radius: 24px;
    border: none;
    box-shadow: 0 22px 40px rgba(15, 23, 42, 0.12);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #ffffff;
  }
  .saved-card img {
    width: 100%;
    height: 210px;
    object-fit: cover;
  }
  .saved-card-body {
    padding: 1.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    flex-grow: 1;
  }
  .saved-card-body h5 { font-weight: 600; color: #1f2937; margin-bottom: 0; }
  .saved-card-body p { color: #6b7280; font-size: 0.95rem; margin-bottom: 0; }
  .saved-description {
    position: relative;
    overflow: hidden;
    line-height: 1.6rem;
    max-height: calc(1.6rem * 3);
  }
  .saved-description::after {
    content: "";
    position: absolute;
    inset: auto 0 0;
    height: 1.6rem;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0), #ffffff);
  }
  .saved-card-body .badge {
    border-radius: 999px;
    padding: 0.4rem 0.75rem;
    font-weight: 600;
  }
  .saved-card-actions {
    margin-top: auto;
    display: flex;
    gap: 0.6rem;
    align-items: stretch;
  }
  .saved-card-actions .btn {
    border-radius: 999px;
    padding: 0.55rem 1rem;
    font-weight: 600;
    flex: 1;
  }
  .saved-card-actions form { flex: 1; }
  .saved-card-actions form .btn { width: 100%; }
  .saved-empty {
    background: #ffffff;
    border-radius: 24px;
    padding: 3rem 1.5rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.1);
  }
  .saved-empty i {
    font-size: 2.5rem;
    color: #f97316;
  }
  .saved-empty p { color: #6b7280; }
  @media (max-width: 575.98px) {
    .saved-card-actions {
      flex-direction: column;
    }
    .saved-card-actions .btn {
      width: 100%;
    }
  }
</style>

<section class="saved-hero">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div>
      <h3 class="mb-2"><i class="fas fa-heart text-danger me-2"></i>Saved Recipes</h3>
      <p>Keep your favourite dishes in one place. Filter by dietary tags or search to revisit something delicious.</p>
    </div>
    <?php if (!empty($savedRecipes)): ?>
      <div class="badge bg-white text-dark border align-self-start align-self-lg-center px-3 py-2">
        <?php echo count($savedRecipes); ?> recipe(s) saved
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="saved-filter-card">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-4">
    <div>
      <h5 class="mb-1">Filter your favourites</h5>
      <p class="text-muted small mb-0">Refine the list by keyword or dietary preference to jump back into a recipe.</p>
    </div>
    <?php if (!empty($savedRecipes)): ?>
      <span class="saved-meta text-uppercase small"><?php echo count($savedRecipes); ?> recipe<?php echo count($savedRecipes) === 1 ? '' : 's'; ?> found</span>
    <?php elseif ($search !== '' || $dietaryFilter !== ''): ?>
      <span class="saved-meta text-danger text-uppercase small">No recipes found</span>
    <?php endif; ?>
  </div>

  <form method="get" class="row gy-3 gx-3 align-items-center">
    <div class="col-12 col-lg-6">
      <div class="input-group">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Search saved recipes..." value="<?php echo htmlspecialchars($search); ?>">
      </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <select name="dietary" class="form-select">
        <option value="">All dietary tags</option>
        <?php foreach ($dietaryOptions as $tag): ?>
          <option value="<?php echo htmlspecialchars($tag); ?>" <?php echo $dietaryFilter === $tag ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($tag); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-sm-6 col-lg-auto d-flex gap-2">
      <button type="submit" class="btn btn-primary px-4">
        <i class="fas fa-sliders-h me-2"></i>Apply filters
      </button>
      <?php if ($search !== '' || $dietaryFilter !== ''): ?>
        <a href="saved_recipes.php" class="btn btn-outline-secondary px-4">
          <i class="fas fa-undo me-2"></i>Clear
        </a>
      <?php endif; ?>
    </div>
  </form>

  <?php if (!empty($savedRecipes)): ?>
    <div class="saved-recipes-grid">
      <div class="row g-4">
        <?php foreach ($savedRecipes as $recipe): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <article class="saved-card">
              <img src="<?php echo !empty($recipe['recipe_image']) ? '../uploads/recipes/' . htmlspecialchars($recipe['recipe_image']) : '../assets/img/no_image.png'; ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
              <div class="saved-card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <h5 class="mb-0"><?php echo htmlspecialchars($recipe['recipe_name']); ?></h5>
                  <span class="text-muted small"><i class="far fa-clock me-1"></i><?php echo htmlspecialchars(date('d M Y', strtotime($recipe['saved_at']))); ?></span>
                </div>
                <?php if (!empty($recipe['recipe_description'])): ?>
                  <p class="saved-description"><?php echo htmlspecialchars($recipe['recipe_description']); ?></p>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                  <span class="badge bg-success"><i class="fas fa-wallet me-1"></i><?php echo htmlspecialchars($recipe['price_range']); ?></span>
                  <?php if (!empty($recipe['dietary_tag'])): ?>
                    <span class="badge bg-primary"><i class="fas fa-leaf me-1"></i><?php echo htmlspecialchars($recipe['dietary_tag']); ?></span>
                  <?php endif; ?>
                  <?php if (!empty($recipe['status']) && strtolower($recipe['status']) !== 'active'): ?>
                    <span class="badge bg-warning text-dark"><i class="fas fa-info-circle me-1"></i><?php echo htmlspecialchars($recipe['status']); ?></span>
                  <?php endif; ?>
                </div>
                <div class="saved-card-actions">
                  <a href="recipe_details.php?recipe_id=<?php echo (int)$recipe['recipe_id']; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-eye me-2"></i>View recipe
                  </a>
                  <form method="post" action="save_recipe_toggle.php" class="flex-grow-1">
                    <input type="hidden" name="recipe_id" value="<?php echo (int)$recipe['recipe_id']; ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                    <button type="submit" class="btn btn-outline-danger">
                      <i class="fas fa-heart-broken me-2"></i>Remove
                    </button>
                  </form>
                </div>
                <div class="text-muted small"><i class="far fa-calendar-check me-1"></i>Saved on <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($recipe['saved_at']))); ?></div>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="saved-empty">
      <div class="mb-3">
        <i class="fas fa-utensils"></i>
      </div>
      <h5 class="mb-3 text-secondary">You haven't saved any recipes yet.</h5>
      <p class="small mb-4">Browse the catalogue and tap "Save Recipe" to keep your favourites handy for later.</p>
      <a href="all_recipes.php" class="btn btn-primary px-4"><i class="fas fa-compass me-2"></i>Explore recipes</a>
    </div>
  <?php endif; ?>
</div>

<?php
$conn->close();
include 'customer_footer.php';
