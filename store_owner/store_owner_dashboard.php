<?php
// store_owner/store_owner_dashboard.php

session_start();
if (!isset($_SESSION['store_owner_id'])) {
    header('Location: store_owner_login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'grocerygenie');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$owner_id = (int)$_SESSION['store_owner_id'];

$total_items = 0;
if ($stmt = $conn->prepare('SELECT COUNT(*) FROM groceryitem WHERE owner_id = ?')) {
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $stmt->bind_result($total_items);
    $stmt->fetch();
    $stmt->close();
}

$total_categories = 0;
if ($stmt = $conn->prepare('SELECT COUNT(DISTINCT category) FROM groceryitem WHERE owner_id = ?')) {
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $stmt->bind_result($total_categories);
    $stmt->fetch();
    $stmt->close();
}

$inventory_value = 0.0;
if ($stmt = $conn->prepare('SELECT IFNULL(SUM(price_per_unit * quantity), 0) FROM groceryitem WHERE owner_id = ?')) {
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $stmt->bind_result($inventory_value);
    $stmt->fetch();
    $stmt->close();
}

$low_stock_count = 0;
if ($stmt = $conn->prepare('SELECT COUNT(*) FROM groceryitem WHERE owner_id = ? AND quantity <= 5')) {
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $stmt->bind_result($low_stock_count);
    $stmt->fetch();
    $stmt->close();
}

$items = [];
if ($stmt = $conn->prepare('SELECT item_id, item_name, brand, category, price_per_unit, unit, quantity, dietary_tag, item_image, created_at FROM groceryitem WHERE owner_id = ? ORDER BY created_at DESC')) {
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
}

$inventoryCategories = [];
$inventoryDietary = [];
foreach ($items as $itemRow) {
    if (!empty($itemRow['category'])) {
        $inventoryCategories[] = $itemRow['category'];
    }
    if (!empty($itemRow['dietary_tag'])) {
        $inventoryDietary[] = $itemRow['dietary_tag'];
    }
}
$inventoryCategories = array_values(array_unique($inventoryCategories));
$inventoryDietary = array_values(array_unique($inventoryDietary));
sort($inventoryCategories);
sort($inventoryDietary);

$top_categories = [];
if ($stmt = $conn->prepare('SELECT category, COUNT(*) AS total FROM groceryitem WHERE owner_id = ? GROUP BY category ORDER BY total DESC LIMIT 5')) {
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $top_categories[] = $row;
    }
    $stmt->close();
}

$low_stock_items = [];
if ($stmt = $conn->prepare('SELECT item_name, quantity FROM groceryitem WHERE owner_id = ? AND quantity <= 5 ORDER BY quantity ASC LIMIT 5')) {
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $low_stock_items[] = $row;
    }
    $stmt->close();
}

include 'store_owner_header.php';
?>

<style>
  .so-dashboard {
    margin-top: 2rem;
    margin-bottom: 4rem;
  }
  .so-dashboard-hero {
    position: relative;
    background: var(--gg-gradient);
    border-radius: var(--gg-radius-md);
    color: #fff;
    padding: 2.8rem 2.2rem;
    overflow: hidden;
    box-shadow: var(--gg-shadow-soft);
  }
  .so-dashboard-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top left, rgba(255, 255, 255, 0.18), transparent 60%);
    pointer-events: none;
  }
  .so-dashboard-hero h1 {
    font-weight: 700;
    margin-bottom: 1rem;
  }
  .so-dashboard-hero p {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.85);
  }
  .so-dashboard-hero .gg-btn-outline {
    border-color: rgba(255, 255, 255, 0.6);
    color: #fff;
  }
  .so-dashboard-hero .gg-btn-outline:hover {
    border-color: #fff;
  }
  .so-metric-card {
    position: relative;
  }
  .so-metric-card .metric-icon {
    width: 46px;
    height: 46px;
    border-radius: 16px;
    background: var(--gg-primary-soft);
    color: var(--gg-primary-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    margin-bottom: 1rem;
  }
  .so-metric-card h3 {
    font-weight: 700;
    font-size: 1.8rem;
    margin-bottom: 0.2rem;
  }
  .so-metric-card span {
    color: var(--gg-muted);
    font-size: 0.9rem;
  }
  .so-card {
    background: var(--gg-surface);
    border-radius: var(--gg-radius-md);
    box-shadow: var(--gg-shadow-soft);
    border: none;
  }
  .so-card-header {
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    padding: 1.5rem 1.8rem;
  }
  .so-card-header h4 {
    margin: 0;
    font-weight: 700;
    color: var(--gg-secondary);
  }
  .so-card-body {
    padding: 1.8rem;
  }
  .so-table {
    --bs-table-bg: transparent;
    --bs-table-striped-bg: rgba(15, 23, 42, 0.02);
  }
  .so-table thead {
    background: rgba(15, 23, 42, 0.04);
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
  }
  .so-table thead th {
    font-weight: 600;
    color: var(--gg-secondary);
  }
  .so-table tbody td {
    vertical-align: middle;
  }
  .so-inventory-table td:first-child,
  .so-inventory-table th:first-child {
    width: 320px;
  }
  .so-inventory-table td:nth-child(2),
  .so-inventory-table th:nth-child(2) {
    width: 180px;
  }
  .so-inventory-table td:nth-child(3),
  .so-inventory-table th:nth-child(3) {
    width: 140px;
  }
  .so-inventory-table td:nth-child(4),
  .so-inventory-table th:nth-child(4) {
    width: 120px;
  }
  .so-inventory-table td:nth-child(5),
  .so-inventory-table th:nth-child(5) {
    width: 110px;
  }
  .so-inventory-table td:nth-child(6),
  .so-inventory-table th:nth-child(6) {
    width: 150px;
  }
  .so-inventory-table td:nth-child(7),
  .so-inventory-table th:nth-child(7) {
    width: 150px;
  }
  .so-inventory-controls {
    display: grid;
    gap: 0.75rem;
    margin-bottom: 1rem;
  }
  @media (min-width: 768px) {
    .so-inventory-controls {
      grid-template-columns: repeat(12, minmax(0, 1fr));
      align-items: end;
    }
    .so-inventory-controls .so-search-col {
      grid-column: span 5;
    }
    .so-inventory-controls .so-category-col,
    .so-inventory-controls .so-dietary-col {
      grid-column: span 3;
    }
    .so-inventory-controls .so-clear-col {
      grid-column: span 1;
      justify-self: end;
    }
  }
  @media (max-width: 767.98px) {
    .so-inventory-controls .so-clear-col {
      display: flex;
      justify-content: flex-end;
    }
  }
  .so-inventory-controls .form-control,
  .so-inventory-controls .form-select {
    border: none;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
  }
  .so-inventory-controls .form-control:focus,
  .so-inventory-controls .form-select:focus {
    box-shadow: 0 0 0 3px rgba(255, 138, 76, 0.25);
  }
  .so-inventory-controls .so-clear-btn {
    padding: 0.35rem 0.6rem;
    font-size: 0.8rem;
  }
  .so-item-thumb {
    width: 60px;
    height: 60px;
    border-radius: var(--gg-radius-sm);
    object-fit: cover;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
  }
  .so-empty {
    padding: 2rem;
    text-align: center;
    color: var(--gg-muted);
  }
  .so-side-list li {
    display: flex;
    justify-content: space-between;
    padding: 0.65rem 0;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    font-weight: 500;
  }
  .so-side-list li:last-child {
    border-bottom: none;
  }
  .so-side-list span {
    color: var(--gg-muted);
  }
  .so-side-column .so-card-header {
    padding: 1.2rem 1.4rem;
  }
  .so-side-column .so-card-body {
    padding: 1.25rem 1.4rem;
  }
</style>

<div class="container so-dashboard">
  <section class="so-dashboard-hero mb-4">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <span class="gg-hero-eyebrow"><i class="fas fa-warehouse"></i> Inventory at a glance</span>
        <h1>Welcome back! Keep your shelves stocked and shining.</h1>
        <p>Monitor low inventory, refresh product listings, and track how your store is performing in GroceryGenie. Need a boost? Start by adding your freshest arrivals.</p>
        <div class="d-flex flex-wrap gap-3 mt-4">
          <a href="store_owner_add_item.php" class="gg-btn-primary"><i class="fas fa-plus-circle"></i> Add New Item</a>
          <a href="store_owner_sales_report.php" class="gg-btn-outline"><i class="fas fa-chart-line"></i> View Sales Report</a>
        </div>
      </div>
      <div class="col-lg-4 mt-4 mt-lg-0 text-lg-end text-center">
        <div class="text-white-80">
          <h2 class="fw-bold mb-1"><?php echo number_format($inventory_value, 2); ?> <small class="fs-6">RM</small></h2>
          <p class="mb-0">Total inventory value currently listed in your store.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="row g-4 mb-1">
    <div class="col-md-3">
      <div class="gg-metric-card so-metric-card h-100">
        <div class="metric-icon"><i class="fas fa-layer-group"></i></div>
        <h3><?php echo (int)$total_items; ?></h3>
        <span>Total items listed</span>
      </div>
    </div>
    <div class="col-md-3">
      <div class="gg-metric-card so-metric-card h-100">
        <div class="metric-icon"><i class="fas fa-tags"></i></div>
        <h3><?php echo (int)$total_categories; ?></h3>
        <span>Categories covered</span>
      </div>
    </div>
    <div class="col-md-3">
      <div class="gg-metric-card so-metric-card h-100">
        <div class="metric-icon"><i class="fas fa-boxes-stacked"></i></div>
        <h3><?php echo (int)$low_stock_count; ?></h3>
        <span>Items low on stock (≤5)</span>
      </div>
    </div>
    <div class="col-md-3">
      <div class="gg-metric-card so-metric-card h-100">
        <div class="metric-icon"><i class="fas fa-coins"></i></div>
        <h3>RM <?php echo number_format($inventory_value, 2); ?></h3>
        <span>Inventory value</span>
      </div>
    </div>
  </section>

  <div class="row g-4 mt-1">
    <div class="col-12 col-xl-9">
      <div class="so-card mb-4">
        <div class="so-card-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
          <h4 class="mb-0"><i class="fas fa-box-open me-2 text-warning"></i>Your inventory</h4>
          <a href="store_owner_add_item.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-plus me-1"></i> Add item</a>
        </div>
        <div class="so-card-body">
          <div class="so-inventory-controls">
            <div class="so-search-col">
              <label for="inventorySearchInput" class="form-label text-uppercase small fw-semibold text-muted mb-1">Search items</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="search" id="inventorySearchInput" class="form-control" placeholder="Search by name or brand">
              </div>
            </div>
            <div class="so-category-col">
              <label for="inventoryCategoryFilter" class="form-label text-uppercase small fw-semibold text-muted mb-1">Category</label>
              <select id="inventoryCategoryFilter" class="form-select">
                <option value="">All categories</option>
                <?php foreach ($inventoryCategories as $categoryName): ?>
                  <option value="<?php echo htmlspecialchars($categoryName); ?>"><?php echo htmlspecialchars($categoryName); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="so-dietary-col">
              <label for="inventoryDietaryFilter" class="form-label text-uppercase small fw-semibold text-muted mb-1">Dietary</label>
              <select id="inventoryDietaryFilter" class="form-select">
                <option value="">All dietary tags</option>
                <?php foreach ($inventoryDietary as $dietaryTag): ?>
                  <option value="<?php echo htmlspecialchars($dietaryTag); ?>"><?php echo htmlspecialchars($dietaryTag); ?></option>
                <?php endforeach; ?>
                <?php if (empty($inventoryDietary)): ?>
                  <option value="" disabled>(No dietary tags)</option>
                <?php endif; ?>
              </select>
            </div>
            <div class="so-clear-col">
              <button type="button" id="inventoryResetFilters" class="btn btn-outline-secondary so-clear-btn"><i class="fas fa-undo me-1"></i>Clear</button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0 so-table so-inventory-table">
              <thead>
                <tr>
                  <th scope="col">Item</th>
                  <th scope="col">Category</th>
                  <th scope="col">Price (RM)</th>
                  <th scope="col">Unit</th>
                  <th scope="col">Stock</th>
                  <th scope="col">Dietary</th>
                  <th scope="col">Added</th>
                  <th scope="col" class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($items)) : ?>
                  <?php foreach ($items as $item) : ?>
                    <tr class="so-inventory-row"
                        data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                        data-brand="<?php echo htmlspecialchars($item['brand']); ?>"
                        data-category="<?php echo htmlspecialchars($item['category']); ?>"
                        data-dietary="<?php echo htmlspecialchars($item['dietary_tag']); ?>">
                      <td>
                        <div class="d-flex align-items-center gap-3">
                          <?php
                            $imagePath = '../uploads/items/' . $item['item_image'];
                            $fallback = '../assets/default_item.png';
                            $displayImage = (!empty($item['item_image']) && file_exists($imagePath)) ? $imagePath : $fallback;
                          ?>
                          <img src="<?php echo htmlspecialchars($displayImage); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" class="so-item-thumb">
                          <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($item['brand']); ?></small>
                          </div>
                        </div>
                      </td>
                      <td><?php echo htmlspecialchars($item['category']); ?></td>
                      <td>RM <?php echo number_format((float)$item['price_per_unit'], 2); ?></td>
                      <td><?php echo htmlspecialchars($item['unit']); ?></td>
                      <td>
                        <span class="badge <?php echo ((int)$item['quantity'] <= 5) ? 'bg-danger' : 'bg-success'; ?>">
                          <?php echo (int)$item['quantity']; ?>
                        </span>
                      </td>
                      <td><?php echo htmlspecialchars($item['dietary_tag'] ?: '--'); ?></td>
                      <td><?php echo date('d M Y', strtotime($item['created_at'])); ?></td>
                      <td class="text-end">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="store_owner_edit_item.php?item_id=<?php echo (int)$item['item_id']; ?>" class="btn btn-outline-warning"><i class="fas fa-pen"></i></a>
                          <a href="store_owner_delete_item.php?item_id=<?php echo (int)$item['item_id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this item?');"><i class="fas fa-trash"></i></a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr class="so-inventory-empty-message" style="display: none;">
                    <td colspan="8" class="so-empty">
                      <i class="fas fa-search fa-2x mb-2 d-block"></i>
                      No inventory items match your current filters.
                    </td>
                  </tr>
                <?php else : ?>
                  <tr>
                    <td colspan="8" class="so-empty">
                      <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                      You have not added any items yet. Start by adding your first product.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-3 so-side-column">
      <div class="so-card mb-4">
        <div class="so-card-header">
          <h4><i class="fas fa-chart-pie me-2 text-warning"></i>Top categories</h4>
        </div>
        <div class="so-card-body">
          <?php if (!empty($top_categories)) : ?>
            <ul class="so-side-list list-unstyled mb-0">
              <?php foreach ($top_categories as $category) : ?>
                <li>
                  <?php echo htmlspecialchars($category['category']); ?>
                  <span><?php echo (int)$category['total']; ?> items</span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else : ?>
            <div class="so-empty py-3">Add items to see category trends.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="so-card">
        <div class="so-card-header">
          <h4><i class="fas fa-battery-quarter me-2 text-warning"></i>Low stock watch</h4>
        </div>
        <div class="so-card-body">
          <?php if (!empty($low_stock_items)) : ?>
            <ul class="so-side-list list-unstyled mb-0">
              <?php foreach ($low_stock_items as $lowItem) : ?>
                <li>
                  <?php echo htmlspecialchars($lowItem['item_name']); ?>
                  <span><?php echo (int)$lowItem['quantity']; ?> left</span>
                </li>
              <?php endforeach; ?>
            </ul>
            <div class="mt-3 text-end">
              <a href="store_owner_notifications.php" class="btn btn-sm btn-outline-secondary">Manage alerts</a>
            </div>
          <?php else : ?>
            <div class="so-empty py-3">You're all stocked up. Great job!</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const searchInput = document.getElementById('inventorySearchInput');
    const categoryFilter = document.getElementById('inventoryCategoryFilter');
    const dietaryFilter = document.getElementById('inventoryDietaryFilter');
    const resetButton = document.getElementById('inventoryResetFilters');
    const rows = Array.from(document.querySelectorAll('.so-inventory-row'));
    const emptyRow = document.querySelector('.so-inventory-empty-message');

    const normalize = (value) => (value || '').toString().trim().toLowerCase();

    const applyFilters = () => {
      const searchTerm = normalize(searchInput?.value ?? '');
      const categoryValue = normalize(categoryFilter?.value ?? '');
      const dietaryValue = normalize(dietaryFilter?.value ?? '');
      let visibleCount = 0;

      rows.forEach((row) => {
        const name = normalize(row.dataset.name);
        const brand = normalize(row.dataset.brand);
        const category = normalize(row.dataset.category);
        const dietary = normalize(row.dataset.dietary);

        const matchesSearch = !searchTerm || name.includes(searchTerm) || brand.includes(searchTerm);
        const matchesCategory = !categoryValue || category === categoryValue;
        const matchesDietary = !dietaryValue || dietary === dietaryValue;

        const isVisible = matchesSearch && matchesCategory && matchesDietary;
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount += 1;
      });

      if (emptyRow) {
        emptyRow.style.display = visibleCount === 0 ? '' : 'none';
      }
    };

    const resetFilters = () => {
      if (searchInput) searchInput.value = '';
      if (categoryFilter) categoryFilter.selectedIndex = 0;
      if (dietaryFilter) dietaryFilter.selectedIndex = 0;
      applyFilters();
    };

    searchInput?.addEventListener('input', applyFilters);
    categoryFilter?.addEventListener('change', applyFilters);
    dietaryFilter?.addEventListener('change', applyFilters);
    resetButton?.addEventListener('click', resetFilters);

    applyFilters();
  })();
</script>

<?php include 'store_owner_footer.php'; ?>
