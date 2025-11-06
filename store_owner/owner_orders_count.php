<?php
// store_owner/owner_orders_count.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['store_owner_id'])) {
    echo json_encode(['orders' => 0, 'alerts' => 0]);
    exit();
}

require_once __DIR__ . '/../db_connect.php';

$owner_id = (int)$_SESSION['store_owner_id'];

@$conn->query("CREATE TABLE IF NOT EXISTS store_owner_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  item_id INT NOT NULL,
  message TEXT,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_store_owner_notifications_owner FOREIGN KEY (owner_id) REFERENCES store_owners(owner_id) ON DELETE CASCADE,
  CONSTRAINT fk_store_owner_notifications_item FOREIGN KEY (item_id) REFERENCES groceryitem(item_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$newOrders = 0;
$lowStock = 0;

if ($stmt = $conn->prepare("SELECT COUNT(DISTINCT o.order_id)
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.order_id
    JOIN groceryitem gi ON gi.item_id = oi.item_id
    WHERE gi.owner_id = ? AND o.order_status IN ('Pending','Preparing')")) {
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $stmt->bind_result($newOrders);
    $stmt->fetch();
    $stmt->close();
}

if ($stmt = $conn->prepare('SELECT COUNT(*) FROM store_owner_notifications WHERE owner_id = ? AND is_read = 0')) {
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $stmt->bind_result($lowStock);
    $stmt->fetch();
    $stmt->close();
}

echo json_encode([
    'orders' => (int)$newOrders,
    'alerts' => (int)$lowStock,
]);
