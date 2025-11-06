<?php 
// customer/all_recipes.php

include 'customer_header.php'; 
include '../db_connect.php';  // Correct path to db_connect.php

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

$hasCategoryColumn = false;
$hasByoColumn = false;

if ($col = $conn->query("SHOW COLUMNS FROM recipe LIKE 'recipe_category'")) {
    if ($col->num_rows > 0) {
        $hasCategoryColumn = true;
    }
    $col->free();
}
if ($col = $conn->query("SHOW COLUMNS FROM recipe LIKE 'bring_your_own'")) {
    if ($col->num_rows > 0) {
        $hasByoColumn = true;
    }
    $col->free();
}

$baseSelect = "SELECT recipe_id, recipe_name, recipe_description, total_cost, dietary_tag, price_range, recipe_image";
if ($hasCategoryColumn) {
    $baseSelect .= ", recipe_category";
}
if ($hasByoColumn) {
    $baseSelect .= ", bring_your_own";
}
$baseSelect .= " FROM recipe WHERE status = 'approved' ORDER BY created_at DESC";
$result = $conn->query($baseSelect);
$recipes = [];
$minCost = null;
$maxCost = null;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cost = isset($row['total_cost']) ? (float)$row['total_cost'] : 0.0;
        if ($minCost === null || $cost < $minCost) {
            $minCost = $cost;
        }
        if ($maxCost === null || $cost > $maxCost) {
            $maxCost = $cost;
        }
        $recipes[] = $row;
    }
    $result->free();
}
$totalRecipes = count($recipes);
$priceRangeMin = $minCost !== null ? floor($minCost) : 0;
$priceRangeMax = $maxCost !== null ? ceil($maxCost) : 0;
$priceRangeMin = min($priceRangeMin, $priceRangeMax);
?>

<section class="recipes-hero py-5">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <span class="hero-kicker"><i class="fas fa-compass me-2"></i>Discover</span>
        <h1 class="hero-title">Explore every recipe in GroceryGenie</h1>
        <p class="hero-subtitle">
          Filter by diet, budget, or meal type to uncover dishes tailored to your pantry and preferences.
          Save favourites or jump straight into shopping with a single click.
        </p>
        <div class="hero-stats d-flex flex-wrap gap-3">
          <div class="hero-stat">
            <div class="stat-number"><?php echo number_format($totalRecipes); ?></div>
            <div class="stat-label">Recipes curated</div>
          </div>
          <div class="hero-stat">
            <div class="stat-number">Fresh</div>
            <div class="stat-label">New dishes weekly</div>
          </div>
          <div class="hero-stat">
            <div class="stat-number">Smart</div>
            <div class="stat-label">Filters to guide you</div>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="hero-illustration h-100 d-flex flex-column justify-content-center text-center text-lg-start">
          <div class="illustration-icon mb-3">
            <i class="fas fa-clipboard-list"></i>
          </div>
          <p class="mb-0 text-muted">
            Need inspiration? Use the filters to assemble breakfast-to-dinner lineups, or dive into individual recipes
            to add ingredients straight to your cart.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="recipes-content py-4 py-lg-5">
  <div class="container">
    <div class="filter-panel shadow-sm border-0 rounded-4 p-4 p-lg-5 mb-4">
      <div class="d-flex flex-column flex-lg-row align-items-lg-end gap-3">
        <div class="filter-control flex-grow-1">
          <label class="form-label">Search</label>
          <div class="input-group shadow-sm">
            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="keyword" class="form-control border-start-0" placeholder="Search by recipe or ingredient insight">
          </div>
        </div>
        <div class="filter-control">
          <label class="form-label">Dietary</label>
          <select id="dietary" class="form-select shadow-sm">
            <option value="">All</option>
            <option value="none">None</option>
            <option value="vegetarian">Vegetarian</option>
            <option value="vegan">Vegan</option>
            <option value="halal">Halal</option>
            <option value="kosher">Kosher</option>
            <option value="gluten-free">Gluten-Free</option>
            <option value="low-carb">Low-Carb</option>
          </select>
        </div>
        <div class="filter-control">
          <label class="form-label">Budget</label>
          <select id="budget" class="form-select shadow-sm">
            <option value="">All</option>
            <option value="cheap">Cheap</option>
            <option value="moderate">Moderate</option>
            <option value="expensive">Expensive</option>
          </select>
        </div>
        <div class="filter-control">
          <label class="form-label">Category</label>
          <select id="category" class="form-select shadow-sm">
            <option value="">All</option>
            <option value="breakfast">Breakfast &amp; Brunch</option>
            <option value="lunch">Quick Lunch</option>
            <option value="dinner">Dinner</option>
            <option value="snacks">Snacks &amp; Bites</option>
            <option value="dessert">Dessert</option>
            <option value="drinks">Beverages</option>
          </select>
        </div>
        <div class="filter-control flex-grow-1 price-range-group" id="priceRangeGroup" data-min="<?php echo htmlspecialchars($priceRangeMin, ENT_QUOTES); ?>" data-max="<?php echo htmlspecialchars($priceRangeMax, ENT_QUOTES); ?>">
          <label class="form-label">Total cost (RM)</label>
          <div class="price-range card border-0 shadow-sm">
            <div class="price-values d-flex justify-content-between small text-muted mb-2">
              <span>RM <span id="priceMinDisplay"><?php echo number_format($priceRangeMin, 2); ?></span></span>
              <span>RM <span id="priceMaxDisplay"><?php echo number_format($priceRangeMax, 2); ?></span></span>
            </div>
            <div class="position-relative price-range-slider">
              <div class="price-range-track" id="priceRangeTrack"></div>
              <input type="range" class="form-range price-input" id="priceMin" min="<?php echo htmlspecialchars($priceRangeMin, ENT_QUOTES); ?>" max="<?php echo htmlspecialchars($priceRangeMax, ENT_QUOTES); ?>" step="1" value="<?php echo htmlspecialchars($priceRangeMin, ENT_QUOTES); ?>">
              <input type="range" class="form-range price-input" id="priceMax" min="<?php echo htmlspecialchars($priceRangeMin, ENT_QUOTES); ?>" max="<?php echo htmlspecialchars($priceRangeMax, ENT_QUOTES); ?>" step="1" value="<?php echo htmlspecialchars($priceRangeMax, ENT_QUOTES); ?>">
            </div>
          </div>
        </div>
      </div>
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
        <span id="resultsCount" class="results-count text-muted">Showing <?php echo number_format($totalRecipes); ?> recipe<?php echo $totalRecipes === 1 ? '' : 's'; ?></span>
        <button type="button" class="btn btn-light btn-sm shadow-sm" id="clearFilters">
          <i class="fas fa-broom me-1"></i>Clear filters
        </button>
      </div>
    </div>

    <div class="row g-4" id="recipesList">
    <?php
    if (!empty($recipes)) {
        foreach ($recipes as $row) {
            $recipe_id   = $row['recipe_id'];
            $nameRaw     = trim((string)$row['recipe_name']);
            $name        = htmlspecialchars($nameRaw);
            $categoryRaw = $hasCategoryColumn && isset($row['recipe_category']) ? trim((string)$row['recipe_category']) : '';
            $category    = $categoryRaw !== '' ? htmlspecialchars($categoryRaw) : '';
            $categoryKey = strtolower(preg_replace('/\s+/', '-', $categoryRaw));
            $descriptionRaw = trim((string)$row['recipe_description']);
            $description = htmlspecialchars($descriptionRaw);
            $costValue   = isset($row['total_cost']) ? (float)$row['total_cost'] : 0.0;
            $cost        = number_format($costValue, 2);
            $dietaryRaw  = trim((string)($row['dietary_tag'] ?? ''));
            $dietary     = htmlspecialchars($dietaryRaw);
            $dietaryKey  = strtolower($dietaryRaw);
            $priceRange  = trim((string)($row['price_range'] ?? ''));
            $budget      = htmlspecialchars($priceRange);
            $budgetKey   = 'premium';
            if (stripos($priceRange, 'cheap') !== false) {
                $budgetKey = 'cheap';
            } elseif (stripos($priceRange, 'moderate') !== false) {
                $budgetKey = 'moderate';
            } elseif (stripos($priceRange, 'expensive') !== false || stripos($priceRange, 'premium') !== false) {
                $budgetKey = 'expensive';
            }
            $imagePathRaw = trim((string)($row['recipe_image'] ?? ''));
            if ($imagePathRaw !== '') {
                if (preg_match('/^https?:\\/\\//i', $imagePathRaw)) {
                    $image = htmlspecialchars($imagePathRaw);
                } elseif (str_starts_with($imagePathRaw, '../')) {
                    $image = htmlspecialchars($imagePathRaw);
                } elseif (str_starts_with($imagePathRaw, 'uploads/')) {
                    $image = htmlspecialchars('../' . $imagePathRaw);
                } else {
                    $image = "../uploads/recipes/" . htmlspecialchars($imagePathRaw);
                }
            } else {
                $image = "../assets/img/no_image.png";
            }
            $byoText = ($hasByoColumn && !empty($row['bring_your_own'])) ? nl2br(htmlspecialchars(trim($row['bring_your_own']))) : '';
            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                $shortDesc = mb_strlen($descriptionRaw) > 120 ? mb_substr($descriptionRaw, 0, 120) . '…' : $descriptionRaw;
            } else {
                $shortDesc = strlen($descriptionRaw) > 120 ? substr($descriptionRaw, 0, 120) . '…' : $descriptionRaw;
            }
            $shortDesc = htmlspecialchars($shortDesc);

            $nameData = htmlspecialchars(strtolower($nameRaw), ENT_QUOTES);
            $descData = htmlspecialchars(strtolower($descriptionRaw), ENT_QUOTES);
            $dietaryData = htmlspecialchars($dietaryKey, ENT_QUOTES);
            $budgetData = htmlspecialchars($budgetKey, ENT_QUOTES);
            $categoryData = htmlspecialchars($categoryKey !== '' ? $categoryKey : 'uncategorized', ENT_QUOTES);
            $costData = htmlspecialchars(number_format($costValue, 2, '.', ''), ENT_QUOTES);

            echo "
      <div class='col-sm-6 col-xl-4 recipe-card' 
           data-dietary='{$dietaryData}' 
           data-budget='{$budgetData}' 
           data-category='{$categoryData}'
           data-name='{$nameData}'
           data-desc='{$descData}'
           data-cost='{$costData}'>
        <div class='recipe-tile h-100 d-flex flex-column'>
          <div class='recipe-thumb position-relative'>
            <img src='{$image}' class='img-fluid w-100' alt='{$name}'>";
            if (!empty($dietary)) {
                echo "<span class='recipe-chip recipe-chip--dietary'>{$dietary}</span>";
            }
            echo "
          </div>
          <div class='recipe-body d-flex flex-column flex-grow-1'>
            <div class='d-flex justify-content-between align-items-start mb-2'>
              <h5 class='recipe-name mb-0'>{$name}</h5>
              <span class='recipe-cost'>RM {$cost}</span>
            </div>
            <p class='recipe-desc text-muted flex-grow-1 mb-3'>{$shortDesc}</p>";
            echo "<div class='recipe-budget-pill recipe-budget-pill--{$budgetKey}'>
                    <i class='fas fa-wallet me-1'></i>".($budget !== '' ? $budget : 'Budget friendly')."
                  </div>";
            if (!empty($byoText)) {
                echo "<div class='recipe-byo alert alert-warning bg-opacity-25 py-2 px-3 mb-3'><strong>Bring Your Own:</strong> {$byoText}</div>";
            }
            echo "<div class='recipe-tags mb-3 d-flex flex-wrap gap-2'>";
            if (!empty($category)) {
                echo "<span class='recipe-chip recipe-chip--ghost'><i class='fas fa-layer-group me-1'></i>{$category}</span>";
            }
            echo "</div>
            <a href='recipe_details.php?recipe_id={$recipe_id}' class='btn recipe-btn w-100 mt-auto'>
              <i class='fas fa-eye me-1'></i>View Recipe
            </a>
          </div>
        </div>
      </div>";
        }
    } else {
        echo "<div class='col-12'><div class='empty-state text-center py-5'>
                <div class=\"empty-icon mb-3\"><i class=\"fas fa-clipboard-list\"></i></div>
                <h5 class='fw-semibold'>No recipes available yet</h5>
                <p class='text-muted mb-0'>Check back soon or submit your own signature dish!</p>
              </div></div>";
    }
    ?>
  </div>
  <div id="emptyState" class="empty-state text-center py-5 d-none">
    <div class="empty-icon mb-3"><i class="fas fa-search-minus"></i></div>
    <h5 class="fw-semibold">No matching recipes</h5>
    <p class="text-muted mb-0">Try adjusting your filters to discover more meals.</p>
  </div>
</div>
</section>

<style>
.recipes-hero {
  background: linear-gradient(135deg, rgba(255, 145, 77, 0.12), rgba(67, 97, 238, 0.1));
}
.hero-kicker {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-weight: 600;
  color: #ff6a00;
  font-size: 0.9rem;
}
.hero-title {
  font-size: 2.4rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0.5rem 0 1rem;
}
.hero-subtitle {
  font-size: 1.05rem;
  color: #4b5563;
  max-width: 580px;
}
.hero-stats .hero-stat {
  background: rgba(255, 255, 255, 0.65);
  border-radius: 16px;
  padding: 12px 18px;
  backdrop-filter: blur(6px);
  border: 1px solid rgba(255, 255, 255, 0.6);
  min-width: 140px;
}
.hero-stats .stat-number {
  font-size: 1.3rem;
  font-weight: 700;
  color: #111827;
}
.hero-stats .stat-label {
  font-size: 0.85rem;
  color: #6b7280;
}
.hero-illustration {
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.85), rgba(255, 145, 77, 0.25));
  border-radius: 28px;
  padding: 24px;
  box-shadow: 0 22px 45px rgba(255, 145, 77, 0.18);
}
.hero-illustration .illustration-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(255, 145, 77, 0.18);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: #ff6a00;
  box-shadow: inset 0 0 0 2px rgba(255, 145, 77, 0.32);
}
.hero-illustration p {
  font-size: 0.95rem;
  color: #4b5563;
}
.filter-panel {
  background: #ffffff;
  border: 1px solid rgba(255, 145, 77, 0.18);
}
.filter-control .form-label {
  font-weight: 600;
  color: #374151;
}
.price-range.card {
  padding: 1rem 1.2rem;
  background: rgba(15, 23, 42, 0.02);
  border-radius: 16px;
}
.price-range .price-values span {
  font-weight: 600;
  color: #1f2937;
}
.price-range-slider {
  position: relative;
  height: 2.2rem;
}
.price-range-track {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  height: 0.45rem;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.1);
  pointer-events: none;
  left: 0;
  right: 0;
}
.price-range .form-range {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  background: none;
  pointer-events: none;
}
.price-range .form-range::-webkit-slider-thumb {
  pointer-events: auto;
  -webkit-appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #ff8a4c;
  border: 2px solid #ffffff;
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.25);
}
.price-range .form-range::-webkit-slider-runnable-track {
  height: 0.45rem;
  background: transparent;
}
.price-range .form-range::-moz-range-thumb {
  pointer-events: auto;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #ff8a4c;
  border: 2px solid #ffffff;
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.25);
}
.price-range .form-range::-moz-range-track,
.price-range .form-range::-moz-range-progress {
  background: transparent;
}
.results-count {
  font-size: 0.95rem;
}
.recipes-content {
  background: #f9fafb;
}
.recipe-card {
  transition: opacity 0.35s ease, transform 0.35s ease;
}
.recipe-card.hide {
  display: none !important;
}
.recipe-tile {
  background: #ffffff;
  border-radius: 22px;
  overflow: hidden;
  box-shadow: 0 22px 45px rgba(15, 23, 42, 0.12);
  transition: transform 0.35s ease, box-shadow 0.35s ease;
}
.recipe-tile:hover {
  transform: translateY(-6px);
  box-shadow: 0 28px 55px rgba(15, 23, 42, 0.18);
}
.recipe-thumb {
  position: relative;
}
.recipe-thumb img {
  height: 220px;
  object-fit: cover;
  border-bottom: 1px solid rgba(148, 163, 184, 0.2);
}
.recipe-chip {
  position: absolute;
  top: 14px;
  left: 14px;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  color: #0f172a;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(4px);
}
.recipe-chip--dietary {
  top: auto;
  bottom: 14px;
  right: 14px;
  left: auto;
  background: rgba(67, 97, 238, 0.85);
  color: #fff;
}
.recipe-chip--cheap { background: rgba(74, 222, 128, 0.18); color: #0f5132; }
.recipe-chip--moderate { background: rgba(59, 130, 246, 0.18); color: #1d4ed8; }
.recipe-chip--expensive,
.recipe-chip--premium { background: rgba(248, 113, 113, 0.18); color: #991b1b; }
.recipe-body {
  padding: 20px 22px;
}
.recipe-name {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1f2937;
}
.recipe-cost {
  font-size: 0.95rem;
  font-weight: 600;
  color: #f97316;
}
.recipe-desc {
  font-size: 0.95rem;
  line-height: 1.5;
}
.recipe-budget-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  font-weight: 600;
  border-radius: 999px;
  padding: 6px 12px;
  margin-bottom: 1rem;
}
.recipe-budget-pill i {
  color: inherit;
}
.recipe-budget-pill--cheap {
  background: rgba(74, 222, 128, 0.18);
  color: #0f5132;
}
.recipe-budget-pill--moderate {
  background: rgba(59, 130, 246, 0.18);
  color: #1d4ed8;
}
.recipe-budget-pill--expensive,
.recipe-budget-pill--premium {
  background: rgba(248, 113, 113, 0.18);
  color: #991b1b;
}
.recipe-byo {
  border-radius: 14px;
  font-size: 0.85rem;
}
.recipe-tags .recipe-chip--ghost {
  position: relative;
  top: 0;
  left: 0;
  background: rgba(148, 163, 184, 0.12);
  color: #475569;
  border: 1px solid rgba(148, 163, 184, 0.2);
  padding: 6px 12px;
}
.recipe-btn {
  background: linear-gradient(135deg, #ff914d, #ff6a00);
  border: none;
  color: #fff;
  font-weight: 600;
  border-radius: 999px;
  padding: 10px 0;
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.recipe-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 26px rgba(255, 106, 0, 0.32);
  color: #fff;
}
.empty-state .empty-icon {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(67, 97, 238, 0.12);
  color: #4361ee;
  font-size: 2.5rem;
  box-shadow: inset 0 0 0 1px rgba(67, 97, 238, 0.15);
}
.empty-state p {
  max-width: 360px;
  margin-left: auto;
  margin-right: auto;
}

@media (max-width: 767.98px) {
  .hero-title { font-size: 2rem; }
  .hero-illustration { padding: 18px; border-radius: 20px; }
  .recipe-thumb img { height: 200px; }
  .filter-panel { padding: 24px; }
}
</style>

<script>
const priceGroup = document.getElementById('priceRangeGroup');
const priceMinInput = document.getElementById('priceMin');
const priceMaxInput = document.getElementById('priceMax');
const priceTrack = document.getElementById('priceRangeTrack');
const priceMinDisplay = document.getElementById('priceMinDisplay');
const priceMaxDisplay = document.getElementById('priceMaxDisplay');
let defaultPriceMin = priceGroup ? parseFloat(priceGroup.dataset.min) || 0 : 0;
let defaultPriceMax = priceGroup ? parseFloat(priceGroup.dataset.max) || 0 : 0;

if (priceMinInput && priceMaxInput) {
  if (defaultPriceMin > defaultPriceMax) {
    const swap = defaultPriceMin;
    defaultPriceMin = defaultPriceMax;
    defaultPriceMax = swap;
  }
  priceMinInput.value = defaultPriceMin;
  priceMaxInput.value = defaultPriceMax;
}

const sanitizePrice = (value, fallback) => {
  const parsed = parseFloat(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

function updatePriceDisplays() {
  if (!priceMinInput || !priceMaxInput) return;
  const minVal = sanitizePrice(priceMinInput.value, defaultPriceMin);
  const maxVal = sanitizePrice(priceMaxInput.value, defaultPriceMax);
  if (priceMinDisplay) priceMinDisplay.textContent = minVal.toFixed(2);
  if (priceMaxDisplay) priceMaxDisplay.textContent = maxVal.toFixed(2);
  if (priceTrack) {
    const minAttr = sanitizePrice(priceMinInput.min, defaultPriceMin);
    const maxAttr = sanitizePrice(priceMaxInput.max, defaultPriceMax);
    const range = Math.max(maxAttr - minAttr, 1);
    const startPercent = ((minVal - minAttr) / range) * 100;
    const endPercent = ((maxVal - minAttr) / range) * 100;
    const start = Math.max(0, Math.min(100, startPercent));
    const end = Math.max(0, Math.min(100, endPercent));
    priceTrack.style.left = `${start}%`;
    priceTrack.style.right = `${100 - end}%`;
  }
}

function handlePriceInput(event) {
  if (!priceMinInput || !priceMaxInput) return;
  let minVal = sanitizePrice(priceMinInput.value, defaultPriceMin);
  let maxVal = sanitizePrice(priceMaxInput.value, defaultPriceMax);
  if (minVal > maxVal) {
    if (event && event.target === priceMinInput) {
      minVal = maxVal;
      priceMinInput.value = maxVal;
    } else {
      maxVal = minVal;
      priceMaxInput.value = minVal;
    }
  }
  updatePriceDisplays();
  applyFilters();
}

if (priceMinInput && priceMaxInput) {
  priceMinInput.addEventListener('input', handlePriceInput);
  priceMaxInput.addEventListener('input', handlePriceInput);
  updatePriceDisplays();
}

function applyFilters() {
  const dietary = document.getElementById('dietary').value.trim().toLowerCase();
  const budget = document.getElementById('budget').value.trim().toLowerCase();
  const category = document.getElementById('category').value.trim().toLowerCase();
  const keyword = (document.getElementById('keyword').value || '').trim().toLowerCase();
  const minPrice = priceMinInput ? sanitizePrice(priceMinInput.value, defaultPriceMin) : null;
  const maxPrice = priceMaxInput ? sanitizePrice(priceMaxInput.value, defaultPriceMax) : null;
  const hasPriceFilter = priceMinInput && priceMaxInput && minPrice !== null && maxPrice !== null;
  const lowerPrice = hasPriceFilter ? Math.min(minPrice, maxPrice) : null;
  const upperPrice = hasPriceFilter ? Math.max(minPrice, maxPrice) : null;

  const cards = document.querySelectorAll('.recipe-card');

  cards.forEach(card => {
    const cDiet = (card.dataset.dietary || '').toLowerCase();
    const cBudg = (card.dataset.budget || '').toLowerCase();
    const cCat  = (card.dataset.category || '').toLowerCase();
    const cName = (card.dataset.name || '').toLowerCase();
    const cDesc = (card.dataset.desc || '').toLowerCase();
    const cCost = parseFloat(card.dataset.cost || '0');

    const matchDietary = !dietary || cDiet === dietary;
    const matchBudget = !budget || cBudg === budget;
    const matchCategory = !category || cCat === category;
    const matchKeyword = !keyword || cName.includes(keyword) || cDesc.includes(keyword);
    const matchPrice = !hasPriceFilter || (cCost >= lowerPrice && cCost <= upperPrice);

    if (matchDietary && matchBudget && matchCategory && matchKeyword && matchPrice) {
      card.classList.remove('hide');
    } else {
      card.classList.add('hide');
    }
  });

  updateResultsSummary();
}

['dietary','budget','category','keyword'].forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    const evt = id === 'keyword' ? 'input' : 'change';
    el.addEventListener(evt, applyFilters);
  }
});

const clearBtn = document.getElementById('clearFilters');
clearBtn?.addEventListener('click', () => {
  ['dietary','budget','category'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  const keywordInput = document.getElementById('keyword');
  if (keywordInput) keywordInput.value = '';
  if (priceMinInput && priceMaxInput) {
    priceMinInput.value = defaultPriceMin;
    priceMaxInput.value = defaultPriceMax;
    updatePriceDisplays();
  }
  applyFilters();
});

function updateResultsSummary() {
  const cards = document.querySelectorAll('.recipe-card');
  let visible = 0;
  cards.forEach(card => {
    if (!card.classList.contains('hide')) visible++;
  });
  const resultsCount = document.getElementById('resultsCount');
  const emptyState = document.getElementById('emptyState');
  if (resultsCount) {
    const label = visible === 1 ? 'recipe' : 'recipes';
    resultsCount.textContent = `Showing ${visible} ${label}`;
  }
  if (emptyState) {
    emptyState.classList.toggle('d-none', visible > 0);
  }
}

updatePriceDisplays();
applyFilters();
</script>

<?php include 'customer_footer.php'; ?>
