<?php
// customer/save_recipe.php

include 'db_connect.php';
session_start();
require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/security.php';

// Ensure user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    die('Security validation failed. Please go back and try again.');
}

// Sanitize input safely
$recipe_name        = $_POST['recipe_name']        ?? null;
$recipe_description = $_POST['recipe_description'] ?? null;
$dietary_tag        = $_POST['dietary_tag']        ?? null;
$price_range        = $_POST['price_range']        ?? null;
$recipe_category    = $_POST['recipe_category']    ?? null;
$bring_your_own     = trim($_POST['bring_your_own'] ?? '');
$ingredients        = $_POST['ingredients']        ?? null;
$steps              = $_POST['steps']              ?? null;

// Handle file upload
$recipe_image = null;
if (!empty($_FILES['recipe_image']['name'])) {
    $uploadResult = gg_secure_upload(
        $_FILES['recipe_image'],
        dirname(__DIR__) . '/uploads/recipes/'
    );

    if ($uploadResult['success']) {
        $recipe_image = "uploads/recipes/" . $uploadResult['filename'];
    } else {
        die("Image upload failed: " . htmlspecialchars($uploadResult['message']));
    }
}

// Validate required fields
if (!$recipe_name || !$recipe_description) {
    die("Missing required fields. Please go back and fill in all required information.");
}

// Determine if bring_your_own column exists
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

if ($hasByoColumn) {
    $stmt = $conn->prepare("
        INSERT INTO recipe 
        (recipe_name, recipe_title, recipe_description, dietary_tag, price_range, recipe_category, ingredients, steps, recipe_image, bring_your_own, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "ssssssssssi",
        $recipe_name,
        $recipe_name,
        $recipe_description,
        $dietary_tag,
        $price_range,
        $recipe_category,
        $ingredients,
        $steps,
        $recipe_image,
        $bring_your_own,
        $customer_id
    );
} else {
    $stmt = $conn->prepare("
        INSERT INTO recipe 
        (recipe_name, recipe_title, recipe_description, dietary_tag, price_range, recipe_category, ingredients, steps, recipe_image, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "sssssssssi",
        $recipe_name,
        $recipe_name,
        $recipe_description,
        $dietary_tag,
        $price_range,
        $recipe_category,
        $ingredients,
        $steps,
        $recipe_image,
        $customer_id
    );
}

if ($stmt->execute()) {
    header("Location: finish_create_recipe.php");
    exit;
} else {
    echo "Error: " . $stmt->error;
}
?>
