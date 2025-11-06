<?php
// store_owner/store_owner_delete_item.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['store_owner_id'])) {
    header('Location: store_owner_login.php');
    exit();
}

$owner_id = (int)$_SESSION['store_owner_id'];
$item_id  = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

$redirectBase = 'store_owner_dashboard.php';
$messageParam = '';

if ($item_id <= 0) {
    $messageParam = '&message=' . urlencode('Invalid item selected for deletion.');
    header("Location: {$redirectBase}?status=error{$messageParam}");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'grocerygenie');
if ($conn->connect_error) {
    $messageParam = '&message=' . urlencode('Unable to connect to the database.');
    header("Location: {$redirectBase}?status=error{$messageParam}");
    exit();
}

$conn->begin_transaction();

try {
    $stmtCheck = $conn->prepare('SELECT item_id FROM groceryitem WHERE item_id = ? AND owner_id = ? LIMIT 1');
    if (!$stmtCheck) {
        throw new Exception('Failed to prepare ownership query.');
    }
    $stmtCheck->bind_param('ii', $item_id, $owner_id);
    $stmtCheck->execute();
    $stmtCheck->store_result();

    if ($stmtCheck->num_rows === 0) {
        throw new Exception('You do not have permission to delete this item.');
    }
    $stmtCheck->close();

    $stmtDeleteOrderItems = $conn->prepare('DELETE oi FROM order_items oi JOIN groceryitem gi ON gi.item_id = oi.item_id WHERE gi.item_id = ? AND gi.owner_id = ?');
    if ($stmtDeleteOrderItems) {
        $stmtDeleteOrderItems->bind_param('ii', $item_id, $owner_id);
        $stmtDeleteOrderItems->execute();
        $stmtDeleteOrderItems->close();
    }

    $imagePath = null;
    $stmtImage = $conn->prepare('SELECT item_image FROM groceryitem WHERE item_id = ? LIMIT 1');
    if ($stmtImage) {
        $stmtImage->bind_param('i', $item_id);
        $stmtImage->execute();
        $stmtImage->bind_result($imagePath);
        $stmtImage->fetch();
        $stmtImage->close();
    }

    $stmtDelete = $conn->prepare('DELETE FROM groceryitem WHERE item_id = ? AND owner_id = ?');
    if (!$stmtDelete) {
        throw new Exception('Failed to prepare delete statement.');
    }
    $stmtDelete->bind_param('ii', $item_id, $owner_id);
    if (!$stmtDelete->execute()) {
        throw new Exception('Unable to delete the item. Please try again.');
    }
    $stmtDelete->close();

    $conn->commit();

    if (!empty($imagePath)) {
        $imageFullPath = __DIR__ . '/../uploads/items/' . $imagePath;
        if (is_file($imageFullPath)) {
            @unlink($imageFullPath);
        }
    }

    $messageParam = '&message=' . urlencode('Item removed from your catalogue.');
    header("Location: {$redirectBase}?status=success{$messageParam}");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $messageParam = '&message=' . urlencode($e->getMessage());
    header("Location: {$redirectBase}?status=error{$messageParam}");
    exit();
}
