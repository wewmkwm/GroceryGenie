<?php
// api/chat_action.php
// Simple chat endpoint: send and fetch messages between friends
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db_connect.php';

function out($ok, $message = '', $extra = []) {
  echo json_encode(array_merge(['ok' => (bool)$ok, 'message' => $message], $extra));
  exit;
}

if (!isset($_SESSION['customer_id'])) out(false, 'Please log in.');
$me = (int)$_SESSION['customer_id'];

// Ensure messages table exists
@$conn->query("CREATE TABLE IF NOT EXISTS messages (
  message_id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  content TEXT NOT NULL,
  recipe_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  KEY idx_pair_time (sender_id, receiver_id, created_at),
  KEY idx_receiver_time (receiver_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$col = $conn->query("SHOW COLUMNS FROM messages LIKE 'recipe_id'");
if ($col && $col->num_rows === 0) { @ $conn->query("ALTER TABLE messages ADD COLUMN recipe_id INT NULL AFTER content"); }
if ($col) { $col->close(); }

$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? trim($input['action']) : '';
$peer_id = isset($input['peer_id']) ? (int)$input['peer_id'] : 0;
if ($peer_id <= 0) out(false, 'Invalid peer.');

// Only allow chatting if friends (accepted)
$st = $conn->prepare('SELECT 1 FROM friends WHERE ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?)) AND status="accepted" LIMIT 1');
$st->bind_param('iiii', $me, $peer_id, $peer_id, $me);
$st->execute();
$st->store_result();
$allowed = $st->num_rows > 0; $st->close();
if (!$allowed) out(false, 'You can only chat with accepted friends.');

if ($action === 'send') {
  $content = trim((string)($input['content'] ?? ''));
  $recipeId = isset($input['recipe_id']) ? (int)$input['recipe_id'] : 0;
  $recipeName = '';
  if ($recipeId > 0) {
    $check = $conn->prepare('SELECT recipe_name FROM recipe WHERE recipe_id = ? AND status = "approved" LIMIT 1');
    if ($check) {
      $check->bind_param('i', $recipeId);
      $check->execute();
      $check->bind_result($recipeName);
      if (!$check->fetch()) {
        $check->close();
        out(false, 'Recipe not found.');
      }
      $check->close();
    }
    if ($content === '') {
      $content = 'Check out this recipe: ' . $recipeName;
    }
  }
  if ($content === '') out(false, 'Message cannot be empty.');
  if (mb_strlen($content) > 4000) $content = mb_substr($content, 0, 4000);
  $st = $conn->prepare('INSERT INTO messages (sender_id, receiver_id, content, recipe_id) VALUES (?,?,?,?)');
  $recipeParam = $recipeId > 0 ? $recipeId : null;
  $st->bind_param('iisi', $me, $peer_id, $content, $recipeParam);
  $ok = $st->execute();
  $msg_id = $st->insert_id;
  $st->close();
  if ($ok) {
    // Create a chat notification for the receiver (silent attempts to ensure schema)
    @$conn->query("CREATE TABLE IF NOT EXISTS notifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      customer_id INT NOT NULL,
      order_id INT NOT NULL,
      type VARCHAR(50) DEFAULT 'order',
      message TEXT,
      is_read TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Add chat_user_id column if missing
    $col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'chat_user_id'");
    if ($col && $col->num_rows === 0) { @ $conn->query("ALTER TABLE notifications ADD COLUMN chat_user_id INT NULL AFTER order_id"); }
    if ($col) { $col->close(); }
    // Build snippet
    $snippet = mb_substr($content, 0, 140);
    if ($insN = $conn->prepare('INSERT INTO notifications (customer_id, order_id, chat_user_id, type, message, is_read) VALUES (?, 0, ?, "chat", ?, 0)')) {
      $insN->bind_param('iis', $peer_id, $me, $snippet);
      $insN->execute();
      $insN->close();
    }
  }
  out($ok, $ok ? 'Sent' : 'Failed', ['message_id' => (int)$msg_id, 'recipe_id' => $recipeId, 'recipe_name' => $recipeName]);
}

if ($action === 'fetch') {
  $after_id = isset($input['after_id']) ? (int)$input['after_id'] : 0;
  $st = $conn->prepare('SELECT m.message_id, m.sender_id, m.receiver_id, m.content, m.recipe_id, m.created_at, r.recipe_name
                        FROM messages m
                        LEFT JOIN recipe r ON r.recipe_id = m.recipe_id
                        WHERE ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
                          AND m.message_id > ?
                        ORDER BY m.message_id ASC LIMIT 100');
  $st->bind_param('iiiii', $me, $peer_id, $peer_id, $me, $after_id);
  $st->execute();
  $res = $st->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) {
    $rows[] = [
      'message_id' => (int)$r['message_id'],
      'sender_id' => (int)$r['sender_id'],
      'receiver_id' => (int)$r['receiver_id'],
      'content' => $r['content'],
      'recipe_id' => isset($r['recipe_id']) ? (int)$r['recipe_id'] : 0,
      'recipe_name' => $r['recipe_name'],
      'created_at' => $r['created_at'],
    ];
  }
  $st->close();
  out(true, '', ['messages' => $rows]);
}

out(false, 'Unknown action.');
