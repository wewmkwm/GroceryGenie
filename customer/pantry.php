<?php
// customer/pantry.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['customer_id'])) { header('Location: customer_login.php'); exit(); }
require_once __DIR__ . '/../db_connect.php';

$customer_id = (int)$_SESSION['customer_id'];

// Ensure table exists
@$conn->query("CREATE TABLE IF NOT EXISTS pantry_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  item_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_customer_item (customer_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $qty = max(0, (int)($_POST['qty'] ?? 0));
    if ($item_id > 0 && $qty > 0) {
      $st = $conn->prepare("INSERT INTO pantry_items (customer_id, item_id, qty) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)");
      if ($st) { $st->bind_param('iii', $customer_id, $item_id, $qty); $st->execute(); $st->close(); }
    }
  }
  if (isset($_POST['action']) && $_POST['action'] === 'remove') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    if ($item_id > 0) {
      $st = $conn->prepare("DELETE FROM pantry_items WHERE customer_id=? AND item_id=?");
      if ($st) { $st->bind_param('ii', $customer_id, $item_id); $st->execute(); $st->close(); }
    }
  }
}

// Fetch pantry list
$pantry = [];
$qPantry = $conn->prepare("SELECT p.item_id, p.qty, g.item_name, g.unit FROM pantry_items p JOIN groceryitem g ON g.item_id = p.item_id WHERE p.customer_id = ? ORDER BY g.item_name");
if ($qPantry) { $qPantry->bind_param('i', $customer_id); $qPantry->execute(); $pantry = $qPantry->get_result()->fetch_all(MYSQLI_ASSOC); $qPantry->close(); }

// Fetch some items for dropdown
$items = [];
if ($rs = $conn->query("SELECT item_id, item_name, unit FROM groceryitem ORDER BY item_name ASC LIMIT 200")) {
  while ($r = $rs->fetch_assoc()) { $items[] = $r; }
}

// Suggestions: What can I cook?
$suggestions = [];
$stSug = $conn->prepare(
  "SELECT r.recipe_id, r.recipe_name,
          SUM(GREATEST(ri.quantity - COALESCE(p.qty,0),0)) AS missing_qty,
          SUM(GREATEST(ri.quantity - COALESCE(p.qty,0),0) * gi.price_per_unit) AS est_extra_cost
     FROM recipe r
     JOIN recipe_ingredients ri ON ri.recipe_id = r.recipe_id
     JOIN groceryitem gi ON gi.item_id = ri.item_id
LEFT JOIN pantry_items p ON p.item_id = ri.item_id AND p.customer_id = ?
    WHERE r.status = 'approved'
 GROUP BY r.recipe_id, r.recipe_name
 ORDER BY est_extra_cost ASC, missing_qty ASC
 LIMIT 10"
);
if ($stSug) { $stSug->bind_param('i', $customer_id); $stSug->execute(); $suggestions = $stSug->get_result()->fetch_all(MYSQLI_ASSOC); $stSug->close(); }

$pantryCount = count($pantry);
$totalPantryQty = 0;
foreach ($pantry as $p) {
    $totalPantryQty += (int)$p['qty'];
}
$suggestionCount = count($suggestions);

include 'customer_header.php';
?>

<style>
  .pt-wrapper { margin-top: 2rem; margin-bottom: 4rem; }
  .pt-hero { background: var(--gg-gradient); border-radius: var(--gg-radius-lg); padding: 2.4rem 2rem; color: #fff; box-shadow: var(--gg-shadow-soft); display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1.5rem; }
  .pt-hero h1 { font-weight: 700; margin-bottom: 0.75rem; }
  .pt-hero p { max-width: 520px; color: rgba(255,255,255,0.85); }
  .pt-metrics { display: flex; gap: 1rem; flex-wrap: wrap; }
  .pt-metric { background: rgba(255,255,255,0.18); border-radius: var(--gg-radius-sm); padding: 0.75rem 1rem; min-width: 140px; }
  .pt-metric span { display: block; font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.7); margin-bottom: 0.3rem; }
  .pt-metric strong { font-size: 1.35rem; font-weight: 700; color: #fff; }
  .pt-card { border-radius: var(--gg-radius-md); box-shadow: var(--gg-shadow-soft); border: none; }
  .pt-card-header { padding: 1.4rem 1.6rem 1rem; border-bottom: 1px solid rgba(15, 23, 42, 0.08); display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
  .pt-card-header h5 { margin: 0; font-weight: 700; color: var(--gg-secondary); }
  .pt-card-body { padding: 1.6rem; }
  .pt-card-body .table { margin-bottom: 0; }
  .pt-scroll { max-height: 360px; overflow: auto; }
  .pt-scroll table thead th { position: sticky; top: 0; background: #fff; z-index: 1; box-shadow: inset 0 -1px 0 rgba(15, 23, 42, 0.08); }
  .pt-note { color: var(--gg-muted); font-size: 0.9rem; }
  .pt-search { position: relative; }
  .pt-search i { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--gg-muted); }
  .pt-search input { padding-left: 2.4rem; border: none; box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08); border-radius: var(--gg-radius-sm); }
  .pt-search input:focus { box-shadow: 0 0 0 3px rgba(255, 138, 76, 0.25); }
  .pt-badge-ok { background: rgba(34,197,94,0.18); color: #15803d; border-radius: 999px; padding: 0.3rem 0.75rem; font-weight: 600; }
  .pt-est { color: var(--gg-primary-dark); font-weight: 700; }
  .qty-input { width: 90px; text-align: center; }
  .remove-btn { border: none; background: none; color: #b42318; }
  .remove-btn:hover { color: #7f1d1d; }
</style>

<div class="container pt-wrapper">
  <section class="pt-hero">
    <div>
      <span class="gg-hero-eyebrow"><i class="fas fa-warehouse"></i> Pantry Tracker</span>
      <h1>Keep tabs on your kitchen essentials.</h1>
      <p>Add ingredients you already have, see what’s missing for favourite recipes, and refill the cart in one click.</p>
      <div class="pt-metrics mt-3">
        <div class="pt-metric">
          <span>Items stocked</span>
          <strong><?php echo $pantryCount; ?></strong>
        </div>
        <div class="pt-metric">
          <span>Total quantity</span>
          <strong><?php echo $totalPantryQty; ?></strong>
        </div>
        <div class="pt-metric">
          <span>Recipe ideas</span>
          <strong><?php echo $suggestionCount; ?></strong>
        </div>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-self-start">
      <a href="meal_planner.php" class="gg-btn-outline text-white"><i class="fas fa-calendar-week"></i> Plan meals</a>
      <a href="shopping.php" class="gg-btn-primary"><i class="fas fa-cart-shopping"></i> Shop ingredients</a>
    </div>
  </section>

  <div class="row g-4 mt-4">
    <div class="col-xl-5">
      <div class="card pt-card h-100">
        <div class="pt-card-header">
          <h5><i class="fas fa-boxes-stacked me-2 text-warning"></i>Pantry inventory</h5>
          <span class="pt-note">Remove items as you use them.</span>
        </div>
        <div class="pt-card-body">
          <div class="table-responsive pt-scroll">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Unit</th>
                  <th class="text-end">Qty</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($pantry) : ?>
                  <?php foreach ($pantry as $p) : ?>
                    <tr>
                      <td><?php echo htmlspecialchars($p['item_name']); ?></td>
                      <td><?php echo htmlspecialchars($p['unit']); ?></td>
                      <td class="text-end"><?php echo (int)$p['qty']; ?></td>
                      <td class="text-end">
                        <form method="POST" class="d-inline">
                          <input type="hidden" name="action" value="remove">
                          <input type="hidden" name="item_id" value="<?php echo (int)$p['item_id']; ?>">
                          <button class="remove-btn" title="Remove"><i class="fas fa-trash-alt"></i></button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else : ?>
                  <tr><td colspan="4" class="text-muted text-center py-4">No pantry items yet. Add your first ingredient on the right.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-7">
      <div class="card pt-card mb-4">
        <div class="pt-card-header">
          <h5><i class="fas fa-plus-circle me-2 text-warning"></i>Add ingredients</h5>
          <span class="pt-note">Pull from the marketplace catalogue.</span>
        </div>
        <div class="pt-card-body">
          <div class="row g-2 align-items-center mb-3">
            <div class="col-md-8">
              <div class="pt-search">
                <i class="fas fa-search"></i>
                <input type="text" id="itemSearch" class="form-control" placeholder="Search ingredients...">
              </div>
            </div>
            <div class="col-md-4 text-md-end">
              <span class="pt-note d-block">Showing <?php echo count($items); ?> items</span>
            </div>
          </div>
          <div class="table-responsive pt-scroll">
            <table class="table table-sm align-middle" id="itemTable">
              <thead>
                <tr>
                  <th>Ingredient</th>
                  <th>Unit</th>
                  <th style="width:90px;">Qty</th>
                  <th class="text-end">Add</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $index => $item) : ?>
                  <tr data-item-name="<?php echo htmlspecialchars(strtolower($item['item_name'])); ?>" data-initial="<?php echo $index < 5 ? '1' : '0'; ?>">
                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                    <td><input type="number" class="form-control form-control-sm qty-input" value="1" min="1"></td>
                    <td class="text-end">
                      <button type="button" class="btn btn-sm btn-outline-primary add-item-btn" data-item-id="<?php echo (int)$item['item_id']; ?>"><i class="fas fa-plus me-1"></i>Add</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <form method="POST" id="addPantryForm" class="d-none">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="item_id" id="addItemId">
            <input type="hidden" name="qty" id="addItemQty">
          </form>
        </div>
      </div>

      <div class="card pt-card">
        <div class="pt-card-header">
          <h5><i class="fas fa-lightbulb me-2 text-warning"></i>Recipe suggestions</h5>
          <span class="pt-note">Sorted by lowest extra cost.</span>
        </div>
        <div class="pt-card-body">
          <div class="row g-2 mb-3">
            <div class="col-md-8">
              <div class="pt-search">
                <i class="fas fa-search"></i>
                <input type="text" id="recipeSearch" class="form-control" placeholder="Search recipes...">
              </div>
            </div>
          </div>
          <div class="table-responsive pt-scroll">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Recipe</th>
                  <th class="text-end">Missing Qty</th>
                  <th class="text-end">Est. Extra Cost (RM)</th>
                  <th>Missing Items</th>
                </tr>
              </thead>
              <tbody id="recipeTableBody">
                <?php if ($suggestions) : ?>
                  <?php foreach ($suggestions as $s) : ?>
                    <tr data-recipe-name="<?php echo htmlspecialchars(strtolower($s['recipe_name'])); ?>">
                      <td><a href="recipe_details.php?recipe_id=<?php echo (int)$s['recipe_id']; ?>"><?php echo htmlspecialchars($s['recipe_name']); ?></a></td>
                      <td class="text-end"><?php echo (int)$s['missing_qty']; ?></td>
                      <td class="text-end pt-est"><?php echo number_format((float)$s['est_extra_cost'], 2); ?></td>
                      <td>
                        <?php
                          $miss = [];
                          if ($stmtM = $conn->prepare(
                              "SELECT gi.item_name, gi.unit, (ri.quantity - COALESCE(p.qty,0)) AS missing
                               FROM recipe_ingredients ri
                               JOIN groceryitem gi ON gi.item_id = ri.item_id
                               LEFT JOIN pantry_items p ON p.item_id = ri.item_id AND p.customer_id = ?
                               WHERE ri.recipe_id = ? AND (ri.quantity - COALESCE(p.qty,0)) > 0
                               ORDER BY (ri.quantity - COALESCE(p.qty,0)) DESC")) {
                            $stmtM->bind_param('ii', $customer_id, $s['recipe_id']);
                            $stmtM->execute();
                            $resM = $stmtM->get_result();
                            while ($rowM = $resM->fetch_assoc()) {
                              $mQty = (int)$rowM['missing'];
                              $mName = htmlspecialchars($rowM['item_name']);
                              $mUnit = htmlspecialchars($rowM['unit']);
                              $miss[] = $mName . ' (x' . $mQty . ' ' . $mUnit . ')';
                            }
                            $stmtM->close();
                          }
                          if ($miss) {
                            echo '<ul class="mb-0 ps-3">';
                            foreach ($miss as $m) {
                              echo '<li class="small">' . $m . '</li>';
                            }
                            echo '</ul>';
                          } else {
                            echo '<span class="pt-badge-ok">All ingredients ready</span>';
                          }
                        ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else : ?>
                  <tr data-recipe-name=""><td colspan="4" class="text-muted text-center py-4">No suggestions available yet. Add more pantry items to unlock ideas.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="pt-note mt-3">Tip: Use the Meal Planner to arrange your week and auto-build a shopping list.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'customer_footer.php'; ?>

<script>
  (function(){
    const itemSearch = document.getElementById('itemSearch');
    const itemTable = document.getElementById('itemTable');
    const addForm = document.getElementById('addPantryForm');
    const hiddenItemId = document.getElementById('addItemId');
    const hiddenQty = document.getElementById('addItemQty');

    if (itemSearch && itemTable) {
      const rows = Array.from(itemTable.querySelectorAll('tbody tr'));
      const applyFilter = () => {
        const term = itemSearch.value.trim().toLowerCase();
        rows.forEach(row => {
          const name = (row.dataset.itemName || '').toLowerCase();
          if (!term) {
            row.style.display = row.dataset.initial === '1' ? '' : 'none';
          } else {
            row.style.display = name.includes(term) ? '' : 'none';
          }
        });
      };
      itemSearch.addEventListener('input', applyFilter);
      applyFilter();
    }

    if (addForm && hiddenItemId && hiddenQty) {
      document.querySelectorAll('.add-item-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const itemId = btn.dataset.itemId;
          const row = btn.closest('tr');
          const qtyInput = row ? row.querySelector('.qty-input') : null;
          if (!qtyInput) return;
          const qtyVal = parseInt(qtyInput.value, 10);
          if (!qtyVal || qtyVal <= 0) {
            alert('Please enter a quantity greater than zero before adding.');
            qtyInput.focus();
            return;
          }
          hiddenItemId.value = itemId;
          hiddenQty.value = qtyVal;
          addForm.submit();
        });
      });
    }

    const recipeSearch = document.getElementById('recipeSearch');
    if (recipeSearch) {
      recipeSearch.addEventListener('input', () => {
        const term = recipeSearch.value.trim().toLowerCase();
        document.querySelectorAll('#recipeTableBody tr[data-recipe-name]').forEach(row => {
          const name = (row.dataset.recipeName || '').toLowerCase();
          row.style.display = !term || name.includes(term) ? '' : 'none';
        });
      });
    }
  })();
</script>
