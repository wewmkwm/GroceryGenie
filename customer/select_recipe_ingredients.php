<?php 
// customer/select_recipe_ingredients.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'customer_header.php';

$conn = new mysqli("localhost", "root", "", "grocerygenie");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['customer_id'] ?? 0;

if ($user_id == 0) {
    die("<p class='text-danger'>You must be logged in to create a recipe.</p>");
}

// Ensure recipe details exist
if (!isset($_SESSION['recipe_data'])) {
    die("<p class='text-danger'>Recipe details missing. Please go back and create recipe first.</p>");
}
$recipe = $_SESSION['recipe_data'];
$recipe['bring_your_own'] = isset($recipe['bring_your_own']) ? trim($recipe['bring_your_own']) : '';

$isEditing = isset($_SESSION['recipe_edit']['recipe_id']);
$editingRecipeId = $isEditing ? (int)$_SESSION['recipe_edit']['recipe_id'] : null;
$existingIngredients = $isEditing ? ($_SESSION['recipe_edit']['ingredients'] ?? []) : [];
$existingImage = $_SESSION['recipe_edit']['original_image'] ?? null;

// Handle recipe saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ingredients'])) {
    $ingredients = json_decode($_POST['ingredients'], true);
    $recipe['bring_your_own'] = trim($_POST['bring_your_own'] ?? '');
    $_SESSION['recipe_data']['bring_your_own'] = $recipe['bring_your_own'];

    // calculate total cost
    $total_cost = 0;
    foreach ($ingredients as $ing) {
        $total_cost += $ing['qty'] * $ing['price'];
    }

    // determine price range
    if ($total_cost <= 20) {
        $price_range = "Cheap (RM0 - RM20)";
    } elseif ($total_cost <= 40) {
        $price_range = "Moderate (RM20 - RM40)";
    } elseif ($total_cost <= 60) {
        $price_range = "Expensive (RM40 - RM60)";
    } else {
        $price_range = "Premium (Above RM60)";
    }

    // Ensure bring_your_own column exists
    $hasByoColumn = false;
    if ($colCheck = $conn->query("SHOW COLUMNS FROM recipe LIKE 'bring_your_own'")) {
        if ($colCheck->num_rows > 0) {
            $hasByoColumn = true;
        }
        $colCheck->close();
    }
    if (!$hasByoColumn) {
        $conn->query("ALTER TABLE recipe ADD COLUMN bring_your_own TEXT NULL AFTER recipe_image");
        if ($colCheck = $conn->query("SHOW COLUMNS FROM recipe LIKE 'bring_your_own'")) {
            if ($colCheck->num_rows > 0) {
                $hasByoColumn = true;
            }
            $colCheck->close();
        }
    }

    // Ensure steps column exists so instructions persist across edits
    $hasStepsColumn = false;
    if ($colCheck = $conn->query("SHOW COLUMNS FROM recipe LIKE 'steps'")) {
        if ($colCheck->num_rows > 0) {
            $hasStepsColumn = true;
        }
        $colCheck->close();
    }
    if (!$hasStepsColumn) {
        $conn->query("ALTER TABLE recipe ADD COLUMN steps TEXT NULL AFTER recipe_description");
        if ($colCheck = $conn->query("SHOW COLUMNS FROM recipe LIKE 'steps'")) {
            if ($colCheck->num_rows > 0) {
                $hasStepsColumn = true;
            }
            $colCheck->close();
        }
    }

    $imageValue = $recipe['recipe_image'] ?? $existingImage;
    $stepsValue = $recipe['steps'] ?? '';
    if (is_string($stepsValue)) {
        $stepsValue = trim($stepsValue) !== '' ? $stepsValue : null;
    } else {
        $stepsValue = null;
    }
    $bringValue = $recipe['bring_your_own'] ?? '';
    if (is_string($bringValue)) {
        $bringValue = trim($bringValue) !== '' ? $bringValue : null;
    } else {
        $bringValue = null;
    }
    $recipeName = $recipe['recipe_name'] ?? '';
    $recipeDescription = $recipe['recipe_description'] ?? '';
    $dietaryTag = $recipe['dietary_tag'] ?? '';
    $priceRange = $price_range ?? '';
    $total_cost = round((float)$total_cost, 2);

    $recipeCategory = $recipe['recipe_category'] ?? '';
    if (is_string($recipeCategory)) {
        $recipeCategory = trim($recipeCategory) !== '' ? $recipeCategory : null;
    } else {
        $recipeCategory = null;
    }


    if ($isEditing && $editingRecipeId) {
        if ($hasStepsColumn) {
            $updateSql = "UPDATE recipe SET 
                recipe_name = ?, 
                recipe_title = ?, 
                recipe_description = ?, 
                steps = ?, 
                total_cost = ?, 
                dietary_tag = ?, 
                price_range = ?, 
                recipe_image = ?, 
                bring_your_own = ?, 
                status = 'pending',
                last_moderated_by = NULL,
                last_moderated_at = NULL,
                last_moderation_comment = NULL
                WHERE recipe_id = ? AND created_by = ?";

            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param(
                "ssssdssssii",
                $recipeName,
                $recipeName,
                $recipeDescription,
                $stepsValue,
                $total_cost,
                $dietaryTag,
                $priceRange,
                $recipeCategory,
                $imageValue,
                $bringValue,
                $editingRecipeId,
                $user_id
            );
        } else {
            $updateSql = "UPDATE recipe SET 
                recipe_name = ?, 
                recipe_title = ?, 
                recipe_description = ?, 
                total_cost = ?, 
                dietary_tag = ?, 
                price_range = ?, 
                recipe_image = ?, 
                bring_your_own = ?, 
                status = 'pending',
                last_moderated_by = NULL,
                last_moderated_at = NULL,
                last_moderation_comment = NULL
                WHERE recipe_id = ? AND created_by = ?";

            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param(
                "sssdssssii",
                $recipeName,
                $recipeName,
                $recipeDescription,
                $total_cost,
                $dietaryTag,
                $priceRange,
                $recipeCategory,
                $imageValue,
                $bringValue,
                $editingRecipeId,
                $user_id
            );
        }
        $stmt->execute();
        $stmt->close();

        if ($del = $conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?")) {
            $del->bind_param("i", $editingRecipeId);
            $del->execute();
            $del->close();
        }
        $recipe_id = $editingRecipeId;
    } else {
        if ($hasStepsColumn) {
            if ($hasByoColumn) {
                $stmt = $conn->prepare("INSERT INTO recipe 
                    (recipe_name, recipe_title, recipe_description, steps, total_cost, dietary_tag, price_range, recipe_image, bring_your_own, created_by, created_at, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
                $stmt->bind_param(
                    "ssssdssssi",
                    $recipeName,
                    $recipeName,
                    $recipeDescription,
                    $stepsValue,
                    $total_cost,
                    $dietaryTag,
                    $priceRange,
                    $imageValue,
                    $bringValue,
                    $user_id
                );
            } else {
                $stmt = $conn->prepare("INSERT INTO recipe 
                    (recipe_name, recipe_title, recipe_description, steps, total_cost, dietary_tag, price_range, recipe_image, created_by, created_at, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
                $stmt->bind_param(
                    "ssssdsssi",
                    $recipeName,
                    $recipeName,
                    $recipeDescription,
                    $stepsValue,
                    $total_cost,
                    $dietaryTag,
                    $priceRange,
                    $imageValue,
                    $user_id
                );
            }
        } else {
            if ($hasByoColumn) {
                $stmt = $conn->prepare("INSERT INTO recipe 
                    (recipe_name, recipe_title, recipe_description, total_cost, dietary_tag, price_range, recipe_image, bring_your_own, created_by, created_at, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
                $stmt->bind_param(
                    "sssdssssi",
                    $recipeName,
                    $recipeName,
                    $recipeDescription,
                    $total_cost,
                    $dietaryTag,
                    $priceRange,
                    $imageValue,
                    $bringValue,
                    $user_id
                );
            } else {
                $stmt = $conn->prepare("INSERT INTO recipe 
                    (recipe_name, recipe_title, recipe_description, total_cost, dietary_tag, price_range, recipe_image, created_by, created_at, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
                $stmt->bind_param(
                    "sssdsssi",
                    $recipeName,
                    $recipeName,
                    $recipeDescription,
                    $total_cost,
                    $dietaryTag,
                    $priceRange,
                    $imageValue,
                    $user_id
                );
            }
        }
        $stmt->execute();
        $recipe_id = $stmt->insert_id;
        $stmt->close();
    }

    $stmtIng = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, item_id, quantity) VALUES (?, ?, ?)");
    foreach ($ingredients as $ing) {
        $itemId = (int)$ing['id'];
        $qty = (int)$ing['qty'];
        $stmtIng->bind_param("iii", $recipe_id, $itemId, $qty);
        $stmtIng->execute();
    }
    $stmtIng->close();

    unset($_SESSION['recipe_data']); // clear after save
    unset($_SESSION['recipe_edit']);

    $message = $isEditing ? 'Your recipe has been updated and resubmitted for approval!' : 'Recipe submitted to admin for approval!';
    echo "<script>alert('".$message."'); window.location='finish_create_recipe.php';</script>";
    exit;
}

// Fetch grocery items
$sql = "SELECT item_id, item_name, brand, unit, price_per_unit, item_image, category 
        FROM groceryitem 
        ORDER BY item_name ASC";
$result = $conn->query($sql);

$categories = [];
if ($catRes = $conn->query("SELECT DISTINCT category FROM groceryitem WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC")) {
    while ($catRow = $catRes->fetch_assoc()) {
        $categories[] = $catRow['category'];
    }
    $catRes->close();
}
?>

<style>
  body { background-color: #f9f9fb; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
  .ingredient-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.08); padding: 15px; text-align: center; transition: all 0.3s ease; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
  .ingredient-card:hover { transform: translateY(-5px); box-shadow: 0 6px 12px rgba(0,0,0,0.15); }
  .ingredient-card img { width: 100px; height: 100px; object-fit: contain; margin-bottom: 10px; border-radius: 8px; background: #f4f4f4; padding: 5px; }
  .ingredient-name { font-weight: 600; font-size: 1rem; margin-bottom: 4px; color: #333; }
  .ingredient-weight { font-size: 0.85rem; color: #777; margin-bottom: 6px; }
  .ingredient-category { font-size: 0.75rem; text-transform: uppercase; letter-spacing: .5px; background: #fff4e6; color: #ff6a00; display: inline-block; padding: 2px 8px; border-radius: 999px; margin-bottom: 6px; }
  .price { font-weight: bold; color: #28a745; margin-bottom: 8px; }
  .quantity-box { margin-bottom: 8px; text-align: center; border-radius: 8px; }
  .add-btn { border-radius: 8px; font-weight: 500; transition: all 0.3s; }
  .add-btn:hover { background-color: #218838; transform: scale(1.03); }
  .selected-sidebar { background: #fff; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); padding: 20px; position: sticky; top: 100px; max-height: 70vh; overflow-y: auto; }
  .selected-sidebar h5 { font-weight: 700; margin-bottom: 15px; color: #007bff; }
  .selected-item { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 8px 12px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; }
  .selected-item .remove-btn { cursor: pointer; color: #dc3545; font-weight: bold; margin-left: 10px; }
  .selected-item .remove-btn:hover { color: #a71d2a; }
  .search-sort-bar { margin-bottom: 20px; }
  #searchInput { border-radius: 20px; padding: 10px 15px; border: 1px solid #ddd; }
  #sortSelect { border-radius: 20px; padding: 8px 12px; border: 1px solid #ddd; }
  #categoryFilter { border-radius: 20px; padding: 8px 12px; border: 1px solid #ddd; }
  .navigation-buttons { margin-top: 25px; display: flex; justify-content: space-between; }
  .navigation-buttons .btn { border-radius: 25px; font-weight: 600; padding: 10px 20px; transition: all 0.3s ease; }
  .navigation-buttons .btn:hover { transform: translateY(-2px); }
  .total-cost { font-weight: bold; font-size: 1rem; margin-top: 10px; text-align: right; color: #28a745; }
  .selected-sidebar .bring-your-own textarea { min-height: 80px; resize: vertical; }
</style>

<div class="container ingredients-container">
  <div class="row">
    <!-- Left: Ingredients -->
    <div class="col-md-8">
      <h3 class="mb-4 text-primary"><i class="fas fa-carrot"></i> Select Ingredients for Your Recipe</h3>
      <div class="row search-sort-bar align-items-center g-2">
        <div class="col-md-4 mb-2"><input type="text" id="searchInput" class="form-control" placeholder="Search ingredients..."></div>
        <div class="col-md-4 mb-2">
          <select id="categoryFilter" class="form-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $categoryOption): ?>
              <option value="<?php echo htmlspecialchars(strtolower($categoryOption)); ?>"><?php echo htmlspecialchars($categoryOption); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 mb-2 text-md-end">
          <select id="sortSelect" class="form-select w-auto d-inline">
            <option value="az">Sort: A-Z</option>
            <option value="za">Sort: Z-A</option>
            <option value="lowhigh">Price: Low to High</option>
            <option value="highlow">Price: High to Low</option>
          </select>
        </div>
      </div>
      <div class="row g-4" id="ingredientsGrid">
        <?php
        if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            $item_id = $row['item_id'];
            $name = htmlspecialchars($row['item_name']);
            $unit = htmlspecialchars($row['unit']);
            $priceValue = (float)$row['price_per_unit'];
            $price = number_format($priceValue, 2);
            $category = isset($row['category']) ? htmlspecialchars($row['category']) : '';
            $categoryAttr = strtolower($category);
            $filename = !empty($row['item_image']) ? basename($row['item_image']) : '';
            $image = $filename ? "../uploads/items/" . $filename : "https://via.placeholder.com/100?text=No+Image";
            echo '
            <div class="col-md-4 ingredient-card-wrapper" data-name="'.$name.'" data-price="'.$priceValue.'" data-category="'.$categoryAttr.'">
              <div class="ingredient-card">
                <img src="'.$image.'" alt="'.$name.'">
                '.($category ? '<div class="ingredient-category">'.$category.'</div>' : '').'
                <div class="ingredient-name">'.$name.'</div>
                <div class="ingredient-weight">'.$unit.'</div>
                <div class="price">RM'.$price.'</div>
                <input type="number" min="1" class="form-control quantity-box" placeholder="Qty"
                       data-id="'.$item_id.'" data-price="'.$priceValue.'" data-name="'.$name.'">
                <button type="button" class="btn btn-sm btn-success w-100 add-btn"><i class="fas fa-plus"></i> Add</button>
              </div>
            </div>';
          }
        } else {
          echo "<p class='text-muted'>No items available in the store yet.</p>";
        }
        ?>
      </div>
    </div>

    <!-- Right: Selected Ingredients Sidebar -->
    <div class="col-md-4">
      <form method="POST" id="recipeForm" class="selected-sidebar">
        <h5><i class="fas fa-shopping-basket"></i> Selected Ingredients</h5>
        <div id="selectedList"><p class="text-muted">No ingredients selected yet.</p></div>
        <div class="total-cost">Total: RM0.00</div>

        <div class="bring-your-own mt-4">
          <h6 class="text-primary"><i class="fas fa-box-open"></i> Bring Your Own Items (Optional)</h6>
          <p class="text-muted small mb-2">List ingredients customers must source themselves because they're not available in the store.</p>
          <textarea name="bring_your_own" id="bringYourOwn" class="form-control" placeholder="Example: Fresh basil leaves, specialty spices..."><?php echo htmlspecialchars($recipe['bring_your_own']); ?></textarea>
        </div>

        <input type="hidden" name="ingredients" id="ingredientsInput">
        <div class="navigation-buttons mt-4">
          <a href="create_recipe.php" class="btn btn-secondary px-4"><i class="fas fa-arrow-left"></i> Previous</a>
          <button type="submit" class="btn btn-primary px-4">Submit Recipe <i class="fas fa-arrow-right"></i></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const initialItems = <?php echo json_encode(array_map(function ($item) {
      return [
          'id' => (int)$item['id'],
          'name' => $item['item_name'] ?? $item['name'],
          'qty' => (int)$item['qty'],
          'price' => (float)$item['price'],
      ];
  }, $existingIngredients), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const selectedItems = [];
  const selectedList = document.getElementById('selectedList');
  const ingredientsInput = document.getElementById('ingredientsInput');
  const searchInput = document.getElementById('searchInput');
  const categoryFilter = document.getElementById('categoryFilter');
  const sortSelect = document.getElementById('sortSelect');
  const ingredientsGrid = document.getElementById('ingredientsGrid');

  function syncSelectedInput() {
    ingredientsInput.value = JSON.stringify(selectedItems);
  }

  function updateTotalCost() {
    const total = selectedItems.reduce((sum, item) => sum + (item.qty * item.price), 0);
    document.querySelector('.total-cost').innerText = 'Total: RM' + total.toFixed(2);
  }

  function ensureListMessage() {
    if (!selectedItems.length) {
      selectedList.innerHTML = '<p class="text-muted">No ingredients selected yet.</p>';
    }
  }

  function createSelectedItem(entry) {
    const itemDiv = document.createElement('div');
    itemDiv.classList.add('selected-item');
    itemDiv.innerHTML = `<span>${entry.name} (x${entry.qty}) - RM${(entry.qty * entry.price).toFixed(2)}</span><span class="remove-btn">&times;</span>`;
    itemDiv.querySelector('.remove-btn').addEventListener('click', () => {
      const index = selectedItems.findIndex(i => i.id === entry.id && i.qty === entry.qty && i.price === entry.price);
      if (index > -1) {
        selectedItems.splice(index, 1);
        itemDiv.remove();
        syncSelectedInput();
        updateTotalCost();
        ensureListMessage();
      }
    });
    return itemDiv;
  }

  function addSelectedItem(item) {
    if (selectedList.querySelector('p')) {
      selectedList.innerHTML = '';
    }
    const entry = {
      id: String(item.id),
      name: item.name,
      qty: Number(item.qty),
      price: Number(item.price)
    };
    selectedItems.push(entry);
    selectedList.appendChild(createSelectedItem(entry));
    syncSelectedInput();
    updateTotalCost();
  }

  ensureListMessage();
  initialItems.forEach(addSelectedItem);

  function filterAndSort() {
    const sortOption = sortSelect.value;
    const searchTerm = (searchInput.value || '').toLowerCase();
    const categoryTerm = (categoryFilter.value || '').toLowerCase();
    const cards = Array.from(ingredientsGrid.getElementsByClassName('ingredient-card-wrapper'));

    cards.sort((a, b) => {
      const nameA = (a.dataset.name || '').toLowerCase();
      const nameB = (b.dataset.name || '').toLowerCase();
      const priceA = parseFloat(a.dataset.price || '0');
      const priceB = parseFloat(b.dataset.price || '0');
      if (sortOption === 'az') return nameA.localeCompare(nameB);
      if (sortOption === 'za') return nameB.localeCompare(nameA);
      if (sortOption === 'lowhigh') return priceA - priceB;
      if (sortOption === 'highlow') return priceB - priceA;
      return 0;
    });

    cards.forEach(card => ingredientsGrid.appendChild(card));

    cards.forEach(card => {
      const matchesSearch = !searchTerm || (card.dataset.name || '').toLowerCase().includes(searchTerm);
      const matchesCategory = !categoryTerm || (card.dataset.category || '') === categoryTerm;
      card.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
    });
  }

  searchInput.addEventListener('input', filterAndSort);
  categoryFilter.addEventListener('change', filterAndSort);
  sortSelect.addEventListener('change', filterAndSort);
  filterAndSort();

  document.querySelectorAll('.add-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const card = this.closest('.ingredient-card');
      const quantityInput = card.querySelector('.quantity-box');
      const itemId = quantityInput.dataset.id;
      const name = card.querySelector('.ingredient-name').innerText;
      const qty = parseInt(quantityInput.value, 10);
      const price = parseFloat(quantityInput.dataset.price);
      if (!qty || qty <= 0) {
        alert('Please enter quantity!');
        return;
      }
      addSelectedItem({ id: itemId, name: name, qty: qty, price: price });
      quantityInput.value = '';
    });
  });

  document.getElementById('recipeForm').addEventListener('submit', syncSelectedInput);
</script>

<?php include 'customer_footer.php'; ?>


