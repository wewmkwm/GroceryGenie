<?php
// api/friend_action.php
// JSON endpoint for friend actions
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../db_connect.php';

function respond($ok, $message = '', $extra = []) {
    echo json_encode(array_merge(['ok' => (bool)$ok, 'message' => $message], $extra));
    exit;
}

if (!isset($_SESSION['customer_id'])) {
    respond(false, 'Please log in.');
}

$me = (int)$_SESSION['customer_id'];

// Ensure table exists (idempotent)
$conn->query("CREATE TABLE IF NOT EXISTS friends (
  id INT NOT NULL AUTO_INCREMENT,
  requester_id INT NOT NULL,
  addressee_id INT NOT NULL,
  status ENUM('pending','accepted','rejected','cancelled','blocked') NOT NULL DEFAULT 'pending',
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_direct (requester_id, addressee_id),
  KEY idx_addressee_status (addressee_id, status),
  KEY idx_requester_status (requester_id, status),
  CONSTRAINT fk_fr_req FOREIGN KEY (requester_id) REFERENCES customers(customer_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_fr_add FOREIGN KEY (addressee_id) REFERENCES customers(customer_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? trim($input['action']) : '';
$target = isset($input['user_id']) ? (int)$input['user_id'] : 0;

if (!$action || $target <= 0) {
    respond(false, 'Invalid request.');
}
if ($target === $me) {
    respond(false, 'You cannot perform this action on yourself.');
}

// Ensure target exists
$st = $conn->prepare('SELECT customer_id FROM customers WHERE customer_id = ? LIMIT 1');
$st->bind_param('i', $target);
$st->execute();
$st->store_result();
if ($st->num_rows === 0) {
    $st->close();
    respond(false, 'User not found.');
}
$st->close();

// Helper: check existing links
function get_link($conn, $a, $b) {
    $sql = 'SELECT id, requester_id, addressee_id, status FROM friends WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?) LIMIT 1';
    $st = $conn->prepare($sql);
    $st->bind_param('iiii', $a, $b, $b, $a);
    $st->execute();
    $res = $st->get_result();
    $row = $res->fetch_assoc();
    $st->close();
    return $row ?: null;
}

$link = get_link($conn, $me, $target);

switch ($action) {
    case 'send': {
        if ($link) {
            if ($link['status'] === 'accepted') respond(false, 'You are already friends.');
            if ((int)$link['requester_id'] === $me) {
                if ($link['status'] === 'pending') respond(true, 'Request already sent.');
                // allow resending if was rejected/cancelled -> update to pending
                $st = $conn->prepare('UPDATE friends SET status="pending" WHERE id=?');
                $st->bind_param('i', $link['id']);
                $st->execute();
                $st->close();
                respond(true, 'Friend request re-sent.');
            } else {
                // reverse exists
                if ($link['status'] === 'pending') {
                    // auto-accept when both want it
                    $st = $conn->prepare('UPDATE friends SET status="accepted" WHERE id=?');
                    $st->bind_param('i', $link['id']);
                    $st->execute();
                    $st->close();
                    respond(true, 'Friend request accepted.');
                }
                respond(false, 'Request already exists.');
            }
        }
        $st = $conn->prepare('INSERT INTO friends (requester_id, addressee_id, status) VALUES (?,?,"pending")');
        $st->bind_param('ii', $me, $target);
        if ($st->execute()) {
            $st->close();
            respond(true, 'Friend request sent.');
        }
        $err = $st->error; $st->close();
        respond(false, 'Failed to send request: ' . $err);
    }
    case 'accept': {
        if (!$link) respond(false, 'No friend request found.');
        if ((int)$link['addressee_id'] !== $me || $link['status'] !== 'pending') respond(false, 'Cannot accept this request.');
        $st = $conn->prepare('UPDATE friends SET status="accepted" WHERE id=?');
        $st->bind_param('i', $link['id']);
        $ok = $st->execute();
        $st->close();
        respond($ok, $ok ? 'Friend request accepted.' : 'Failed to accept.');
    }
    case 'reject': {
        if (!$link) respond(false, 'No friend request found.');
        if ((int)$link['addressee_id'] !== $me || $link['status'] !== 'pending') respond(false, 'Cannot reject this request.');
        $st = $conn->prepare('DELETE FROM friends WHERE id=?');
        $st->bind_param('i', $link['id']);
        $ok = $st->execute();
        $st->close();
        respond($ok, $ok ? 'Request rejected.' : 'Failed to reject.');
    }
    case 'cancel': {
        if (!$link) respond(false, 'No outgoing request to cancel.');
        if ((int)$link['requester_id'] !== $me || $link['status'] !== 'pending') respond(false, 'Cannot cancel.');
        $st = $conn->prepare('DELETE FROM friends WHERE id=?');
        $st->bind_param('i', $link['id']);
        $ok = $st->execute();
        $st->close();
        respond($ok, $ok ? 'Request cancelled.' : 'Failed to cancel.');
    }
    case 'unfriend': {
        if (!$link || $link['status'] !== 'accepted') respond(false, 'You are not friends.');
        $st = $conn->prepare('DELETE FROM friends WHERE id=?');
        $st->bind_param('i', $link['id']);
        $ok = $st->execute();
        $st->close();
        respond($ok, $ok ? 'Unfriended.' : 'Failed to unfriend.');
    }
    case 'block': {
        if ($link) {
            if ((int)$link['requester_id'] === $me) {
                $st = $conn->prepare('UPDATE friends SET status="blocked" WHERE id=?');
                $st->bind_param('i', $link['id']);
                $ok = $st->execute();
                $st->close();
                respond($ok, $ok ? 'Blocked.' : 'Failed to block.');
            } else {
                // reverse row: create my directed block if direct doesn't exist
                $st = $conn->prepare('INSERT IGNORE INTO friends (requester_id, addressee_id, status) VALUES (?,?,"blocked")');
                $st->bind_param('ii', $me, $target);
                $ok = $st->execute();
                $st->close();
                respond($ok, $ok ? 'Blocked.' : 'Failed to block.');
            }
        } else {
            $st = $conn->prepare('INSERT INTO friends (requester_id, addressee_id, status) VALUES (?,?,"blocked")');
            $st->bind_param('ii', $me, $target);
            $ok = $st->execute();
            $st->close();
            respond($ok, $ok ? 'Blocked.' : 'Failed to block.');
        }
    }
    case 'unblock': {
        if (!$link) respond(false, 'No block found.');
        if ((int)$link['requester_id'] !== $me || $link['status'] !== 'blocked') respond(false, 'Cannot unblock.');
        $st = $conn->prepare('DELETE FROM friends WHERE id=?');
        $st->bind_param('i', $link['id']);
        $ok = $st->execute();
        $st->close();
        respond($ok, $ok ? 'Unblocked.' : 'Failed to unblock.');
    }
    default:
        respond(false, 'Unknown action.');
}

