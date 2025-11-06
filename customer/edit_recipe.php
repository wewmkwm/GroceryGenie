<?php
// customer/edit_recipe.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    header('Location: customer_login.php');
    exit();
}

require_once __DIR__ . '/../db_connect.php';

$customerId = (int)$_SESSION['customer_id'];
$recipeId = isset($_GET['recipe_id']) ? (int)$_GET['recipe_id'] : 0;

if ($recipeId <= 0) {
    header('Location: customer_profile.php');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM recipe WHERE recipe_id = ? AND created_by = ? LIMIT 1");
if (!$stmt) {
    header('Location: customer_profile.php');
    exit();
}
$stmt->bind_param('ii', $recipeId, $customerId);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$recipe || !in_array($recipe['status'], ['needs_revision', 'rejected'], true)) {
    header('Location: customer_profile.php');
    exit();
}

// Fetch existing ingredients
$ingredientsExisting = [];
if ($stmtIng = $conn->prepare("SELECT ri.item_id, ri.quantity, gi.item_name, gi.price_per_unit 
                                FROM recipe_ingredients ri 
                                LEFT JOIN groceryitem gi ON gi.item_id = ri.item_id 
                                WHERE ri.recipe_id = ?")) {
    $stmtIng->bind_param('i', $recipeId);
    $stmtIng->execute();
    $resIng = $stmtIng->get_result();
    while ($row = $resIng->fetch_assoc()) {
        $ingredientsExisting[] = [
            'id' => (int)$row['item_id'],
            'name' => $row['item_name'],
            'qty' => (int)$row['quantity'],
            'price' => (float)$row['price_per_unit']
        ];
    }
    $stmtIng->close();
}

$fallbackSteps = '';
if (array_key_exists('steps', $recipe)) {
    $fetchedSteps = $recipe['steps'];
    if (is_string($fetchedSteps) && trim($fetchedSteps) !== '') {
        $fallbackSteps = $fetchedSteps;
    }
}
if ($fallbackSteps === '' && !empty($recipe['recipe_description'])) {
    $fallbackSteps = $recipe['recipe_description'];
}

$recipeData = [
    'recipe_name' => $recipe['recipe_name'],
    'recipe_description' => $recipe['recipe_description'],
    'recipe_category' => $recipe['recipe_category'] ?? '',
    'dietary_tag' => $recipe['dietary_tag'] ?? '',
    'steps' => $fallbackSteps,
    'bring_your_own' => $recipe['bring_your_own'] ?? '',
    'recipe_image' => $recipe['recipe_image'] ?? null,
];

$_SESSION['recipe_data'] = $recipeData;
$_SESSION['recipe_edit'] = [
    'recipe_id' => $recipeId,
    'ingredients' => $ingredientsExisting,
    'original_image' => $recipe['recipe_image'] ?? null
];

header('Location: create_recipe.php?edit=1');
exit();
