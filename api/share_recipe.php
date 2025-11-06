<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/security.php';

function share_respond(bool $ok, string $message = '', array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
    exit;
}

if (!isset($_SESSION['customer_id'])) {
    share_respond(false, 'Please log in.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    share_respond(false, 'Invalid method.');
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$recipeId = isset($payload['recipe_id']) ? (int)$payload['recipe_id'] : 0;
$friendId = isset($payload['friend_id']) ? (int)$payload['friend_id'] : 0;
$note = trim($payload['note'] ?? '');
$csrfToken = $payload['csrf_token'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    share_respond(false, 'Security check failed. Please refresh and try again.');
}

if ($recipeId <= 0 || $friendId <= 0) {
    share_respond(false, 'Invalid data supplied.');
}

$senderId = (int)$_SESSION['customer_id'];
if ($senderId === $friendId) {
    share_respond(false, 'You cannot share with yourself.');
}

// Ensure recipe exists
if (!($stmt = $conn->prepare('SELECT recipe_name FROM recipe WHERE recipe_id = ? AND status = "approved" LIMIT 1'))) {
    share_respond(false, 'Server error.');
}
$stmt->bind_param('i', $recipeId);
$stmt->execute();
$stmt->bind_result($recipeName);
if (!$stmt->fetch()) {
    $stmt->close();
    share_respond(false, 'Recipe not found or unavailable.');
}
$stmt->close();

// Ensure friendship exists
if (!($stmt = $conn->prepare('SELECT 1 FROM friends WHERE status = "accepted" AND ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)) LIMIT 1'))) {
    share_respond(false, 'Server error.');
}
$stmt->bind_param('iiii', $senderId, $friendId, $friendId, $senderId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    share_respond(false, 'You can only share recipes with friends.');
}
$stmt->close();

// Create recipe_shares table if needed
@$conn->query('CREATE TABLE IF NOT EXISTS recipe_shares (
  share_id INT AUTO_INCREMENT PRIMARY KEY,
  recipe_id INT NOT NULL,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_receiver_time (receiver_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

if (!($stmt = $conn->prepare('INSERT INTO recipe_shares (recipe_id, sender_id, receiver_id, note) VALUES (?, ?, ?, ?)'))) {
    share_respond(false, 'Server error.');
}
$stmt->bind_param('iiis', $recipeId, $senderId, $friendId, $note);
if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    share_respond(false, 'Failed to share recipe: ' . $error);
}
$stmt->close();

// Ensure notifications table/columns exist
@$conn->query('CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  order_id INT NOT NULL,
  type VARCHAR(50) DEFAULT "order",
  message TEXT,
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'chat_user_id'");
if ($col && $col->num_rows === 0) { @ $conn->query("ALTER TABLE notifications ADD COLUMN chat_user_id INT NULL AFTER order_id"); }
if ($col) { $col->close(); }
$col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'recipe_id'");
if ($col && $col->num_rows === 0) { @ $conn->query("ALTER TABLE notifications ADD COLUMN recipe_id INT NULL AFTER chat_user_id"); }
if ($col) { $col->close(); }

$snippetSource = $note !== '' ? $note : $recipeName;
$snippet = mb_substr($snippetSource, 0, 140);
if ($stmt = $conn->prepare('INSERT INTO notifications (customer_id, order_id, chat_user_id, recipe_id, type, message, is_read) VALUES (?, 0, ?, ?, "recipe_share", ?, 0)')) {
    $stmt->bind_param('iiis', $friendId, $senderId, $recipeId, $snippet);
    $stmt->execute();
    $stmt->close();
}

share_respond(true, 'Recipe shared successfully.');
?>
