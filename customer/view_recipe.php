<?php
// customer/view_recipe.php
session_start();
$conn = new mysqli("localhost", "root", "", "grocerygenie");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$recipe_id = $_GET['id'] ?? 0;
if ($recipe_id == 0) {
    die("<p class='text-danger'>Invalid recipe.</p>");
}

// Fetch recipe details
# check for bring_your_own column
$hasByoColumn = false;
if ($colCheck = $conn->query("SHOW COLUMNS FROM recipe LIKE 'bring_your_own'")) {
    if ($colCheck->num_rows > 0) {
        $hasByoColumn = true;
    }
    $colCheck->close();
}

$selectCols = "r.recipe_id, r.recipe_name, r.recipe_description, r.dietary_tag, r.price_range, r.total_cost, r.status, r.recipe_image, r.created_at";
if ($hasByoColumn) {
    $selectCols .= ", r.bring_your_own";
}
$selectCols .= ", c.name AS creator_name";

$sql = "SELECT $selectCols
        FROM recipe r
        JOIN customers c ON r.created_by = c.customer_id
        WHERE r.recipe_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$result = $stmt->get_result();
$recipe = $result->fetch_assoc();

if (!$recipe) {
    die("<p class='text-danger'>Recipe not found.</p>");
}
$recipe['bring_your_own'] = ($hasByoColumn && isset($recipe['bring_your_own'])) ? $recipe['bring_your_own'] : '';

// Saved recipe status
$savedRecipe = false;
if (isset($_SESSION['customer_id'])) {
    $conn->query("CREATE TABLE IF NOT EXISTS saved_recipes (
        customer_id INT NOT NULL,
        recipe_id INT NOT NULL,
        saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (customer_id, recipe_id),
        CONSTRAINT fk_saved_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
        CONSTRAINT fk_saved_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(recipe_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    if ($stmtSaved = $conn->prepare("SELECT 1 FROM saved_recipes WHERE customer_id = ? AND recipe_id = ? LIMIT 1")) {
        $cidSaved = intval($_SESSION['customer_id']);
        $stmtSaved->bind_param("ii", $cidSaved, $recipe_id);
        $stmtSaved->execute();
        $savedRecipe = $stmtSaved->get_result()->num_rows > 0;
        $stmtSaved->close();
    }
}

// Fetch ingredients
$sqlIng = "SELECT ri.quantity, g.item_name, g.unit, g.price_per_unit 
           FROM recipe_ingredients ri
           JOIN groceryitem g ON ri.item_id = g.item_id
           WHERE ri.recipe_id=?";
$stmtIng = $conn->prepare($sqlIng);
$stmtIng->bind_param("i", $recipe_id);
$stmtIng->execute();
$ingredients = $stmtIng->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($recipe['recipe_name']); ?> - Recipe Details</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <a href="customer_profile.php" class="btn btn-secondary mb-3">⬅ Back</a>

  <div class="card shadow">
    <div class="card-header bg-primary text-white">
      <h3><?php echo htmlspecialchars($recipe['recipe_name']); ?></h3>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <?php if (!empty($recipe['recipe_image'])) { ?>
              <img src="../uploads/recipes/<?php echo htmlspecialchars($recipe['recipe_image']); ?>" 
                   class="img-fluid rounded mb-3">
          <?php } else { ?>
              <img src="https://via.placeholder.com/300x200?text=No+Image" 
                   class="img-fluid rounded mb-3">
          <?php } ?>
        </div>
        <div class="col-md-8">
          <?php if (isset($_SESSION['customer_id'])): ?>
            <form method="post" action="save_recipe_toggle.php" class="mb-3">
              <input type="hidden" name="recipe_id" value="<?php echo (int)$recipe_id; ?>">
              <input type="hidden" name="action" value="<?php echo $savedRecipe ? 'remove' : 'save'; ?>">
              <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
              <button type="submit" class="btn btn-sm <?php echo $savedRecipe ? 'btn-danger' : 'btn-outline-primary'; ?>">
                <i class="fas <?php echo $savedRecipe ? 'fa-heart-broken' : 'fa-heart'; ?>"></i>
                <?php echo $savedRecipe ? 'Remove from Saved' : 'Save Recipe'; ?>
              </button>
            </form>
          <?php endif; ?>
          <p><?php echo nl2br(htmlspecialchars($recipe['recipe_description'])); ?></p>
          <?php if ($hasByoColumn && !empty($recipe['bring_your_own'])): ?>
            <div class="alert alert-warning border-0 bg-warning bg-opacity-25 text-dark">
              <strong>Bring Your Own:</strong><br>
              <?php echo nl2br(htmlspecialchars($recipe['bring_your_own'])); ?>
            </div>
          <?php endif; ?>
          <p><strong>Dietary Tag:</strong> <?php echo htmlspecialchars($recipe['dietary_tag']); ?></p>
          <p><strong>Price Range:</strong> <?php echo htmlspecialchars($recipe['price_range']); ?></p>
          <p><strong>Total Cost:</strong> RM <?php echo number_format($recipe['total_cost'], 2); ?></p>
          <p><strong>Status:</strong> <?php echo ucfirst($recipe['status']); ?></p>
          <p><strong>Created By:</strong> <?php echo htmlspecialchars($recipe['creator_name']); ?></p>
          <p><strong>Created At:</strong> <?php echo htmlspecialchars($recipe['created_at']); ?></p>
        </div>
      </div>

      <hr>
      <h5>🛒 Ingredients</h5>
      <ul>
        <?php while ($row = $ingredients->fetch_assoc()) { ?>
          <li>
            <?php echo htmlspecialchars($row['item_name']); ?> 
            (<?php echo $row['quantity']; ?> <?php echo htmlspecialchars($row['unit']); ?>) 
            - RM <?php echo number_format($row['price_per_unit'], 2); ?>
          </li>
        <?php } ?>
      </ul>
    </div>
  </div>
</div>
</body>
</html>
