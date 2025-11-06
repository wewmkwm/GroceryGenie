<?php
// customer/notifications_count.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

require_once __DIR__ . '/../db_connect.php';

@$conn->query("CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  order_id INT NOT NULL,
  type VARCHAR(50) DEFAULT 'order',
  message TEXT,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$count = 0;
if ($stmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE customer_id = ? AND is_read = 0')) {
    $stmt->bind_param('i', $_SESSION['customer_id']);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
}

echo json_encode(['count' => (int)$count]);
