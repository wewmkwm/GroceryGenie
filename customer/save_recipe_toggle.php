<?php
// customer/save_recipe_toggle.php

session_start();
if (!isset($_SESSION['customer_id'])) {
    header('Location: customer_login.php');
    exit;
}

$recipeId = isset($_POST['recipe_id']) ? intval($_POST['recipe_id']) : 0;
if ($recipeId <= 0) {
    header('Location: saved_recipes.php');
    exit;
}

require_once __DIR__ . '/../db_connect.php';

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS saved_recipes (
    customer_id INT NOT NULL,
    recipe_id INT NOT NULL,
    saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id, recipe_id),
    CONSTRAINT fk_saved_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_recipe FOREIGN KEY (recipe_id) REFERENCES recipe(recipe_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$action = $_POST['action'] ?? 'save';
$customerId = intval($_SESSION['customer_id']);

if ($action === 'remove') {
    if ($stmt = $conn->prepare("DELETE FROM saved_recipes WHERE customer_id = ? AND recipe_id = ?")) {
        $stmt->bind_param('ii', $customerId, $recipeId);
        $stmt->execute();
        $stmt->close();
    }
} else {
    if ($stmt = $conn->prepare("INSERT IGNORE INTO saved_recipes (customer_id, recipe_id) VALUES (?, ?)")) {
        $stmt->bind_param('ii', $customerId, $recipeId);
        $stmt->execute();
        $stmt->close();
    }
}

$redirect = $_POST['redirect'] ?? '';
if (!$redirect) {
    $redirect = $_SERVER['HTTP_REFERER'] ?? 'saved_recipes.php';
}

header('Location: ' . $redirect);
exit;
