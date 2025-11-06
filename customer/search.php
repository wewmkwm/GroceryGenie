<?php
// customer/search.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db_connect.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$recipes = [];
$items = [];

$hasByoColumn = false;
if ($col = $conn->query("SHOW COLUMNS FROM recipe LIKE 'bring_your_own'")) {
  if ($col->num_rows > 0) {
    $hasByoColumn = true;
  }
  $col->close();
}

if ($q !== '') {
  $like = '%' . $q . '%';
  // Search approved recipes by name/description/dietary_tag/bring_your_own
  if ($hasByoColumn) {
    $recipeSql = "SELECT recipe_id, recipe_name, recipe_image, dietary_tag, price_range, total_cost, bring_your_own
                  FROM recipe
                  WHERE status='approved' AND (recipe_name LIKE ? OR recipe_description LIKE ? OR dietary_tag LIKE ? OR bring_your_own LIKE ?)
                  ORDER BY created_at DESC LIMIT 50";
    $st = $conn->prepare($recipeSql);
    if ($st) {
      $st->bind_param('ssss', $like, $like, $like, $like);
    }
  } else {
    $recipeSql = "SELECT recipe_id, recipe_name, recipe_image, dietary_tag, price_range, total_cost
                  FROM recipe
                  WHERE status='approved' AND (recipe_name LIKE ? OR recipe_description LIKE ? OR dietary_tag LIKE ?)
                  ORDER BY created_at DESC LIMIT 50";
    $st = $conn->prepare($recipeSql);
    if ($st) {
      $st->bind_param('sss', $like, $like, $like);
    }
  }
  if ($st) {
    $st->execute();
    $recipes = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
  }

  // Search grocery items by name/brand/category
  if ($st = $conn->prepare("SELECT item_id, item_name, brand, unit, price_per_unit, item_image
                            FROM groceryitem
                            WHERE (item_name LIKE ? OR brand LIKE ? OR category LIKE ?)
                            ORDER BY created_at DESC LIMIT 50")) {
    $st->bind_param('sss', $like, $like, $like);
    $st->execute();
    $items = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
  }
}

include 'customer_header.php';
?>
<style>
  .card-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap:16px; }
  .result-card { border:1px solid #eee; border-radius:10px; overflow:hidden; background:#fff; }
  .result-card img { width:100%; height:160px; object-fit:cover; background:#f6f6f6; }
  .result-body { padding:12px; }
  .small-muted { color:#666; font-size:.9rem; }
</style>

<div class="container mt-4 mb-5">
  <h3 class="mb-3"><i class="fas fa-search"></i> Search Results</h3>
  <form class="row g-2 mb-4" method="GET" action="">
    <div class="col-md-10">
      <input type="text" class="form-control" name="q" placeholder="Search recipes or items..." value="<?php echo htmlspecialchars($q); ?>">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
    </div>
  </form>

  <?php if ($q === ''): ?>
    <p class="text-muted">Type something above to search recipes and grocery items.</p>
  <?php else: ?>
    <h5 class="mt-3 mb-2">Recipes</h5>
    <?php if (!$recipes): ?>
      <p class="text-muted">No recipes found for "<?php echo htmlspecialchars($q); ?>".</p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($recipes as $r): $img = !empty($r['recipe_image']) ? '../uploads/recipes/' . htmlspecialchars($r['recipe_image']) : 'https://via.placeholder.com/320x160?text=Recipe'; ?>
        <div class="result-card">
          <img src="<?php echo $img; ?>" alt="recipe">
          <div class="result-body">
            <div class="fw-bold mb-1"><?php echo htmlspecialchars($r['recipe_name']); ?></div>
            <?php if ($hasByoColumn && !empty($r['bring_your_own'])): ?>
              <div class="alert alert-warning bg-opacity-25 py-2 px-3 small text-dark">
                <strong>Bring Your Own:</strong><br>
                <?php echo nl2br(htmlspecialchars($r['bring_your_own'])); ?>
              </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between align-items-center">
              <span class="badge bg-info"><?php echo htmlspecialchars($r['price_range']); ?></span>
              <a class="btn btn-sm btn-outline-primary" href="recipe_details.php?recipe_id=<?php echo (int)$r['recipe_id']; ?>">View</a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h5 class="mt-4 mb-2">Grocery Items</h5>
    <?php if (!$items): ?>
      <p class="text-muted">No items found for "<?php echo htmlspecialchars($q); ?>".</p>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($items as $it): $img = !empty($it['item_image']) ? '../uploads/items/' . htmlspecialchars(basename($it['item_image'])) : 'https://via.placeholder.com/320x160?text=Item'; ?>
        <div class="result-card">
          <img src="<?php echo $img; ?>" alt="item">
          <div class="result-body">
            <div class="fw-bold mb-1"><?php echo htmlspecialchars($it['item_name']); ?></div>
            <div class="small-muted mb-1"><?php echo htmlspecialchars($it['brand'] ?: ''); ?></div>
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-success fw-bold">RM<?php echo number_format((float)$it['price_per_unit'], 2); ?></span>
              <span class="badge bg-secondary"><?php echo htmlspecialchars($it['unit']); ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-3">
        <a href="shopping.php" class="btn btn-outline-secondary"><i class="fas fa-store"></i> Go to Shopping</a>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php include 'customer_footer.php'; ?>
