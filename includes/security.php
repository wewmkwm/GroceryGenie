<?php
// includes/security.php
if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF helpers
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): bool {
    $t = $_POST['csrf_token'] ?? '';
    return is_string($t) && hash_equals($_SESSION['csrf_token'] ?? '', $t);
}

function verify_csrf_token(?string $token): bool {
    if (!is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Rate limiting for login attempts
function ensure_login_attempts_table(mysqli $conn): void {
    @ $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_email_time (email, attempted_at),
        KEY idx_ip_time (ip, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function record_login_attempt(mysqli $conn, string $email, string $ip, bool $success): void {
    ensure_login_attempts_table($conn);
    if ($st = $conn->prepare('INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, ?)')) {
        $s = $success ? 1 : 0;
        $st->bind_param('ssi', $email, $ip, $s);
        $st->execute();
        $st->close();
    }
}

function is_rate_limited(mysqli $conn, string $email, string $ip, int $max = 5, int $windowSeconds = 900): bool {
    ensure_login_attempts_table($conn);
    $sql = 'SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip = ?) AND success = 0 AND attempted_at > (NOW() - INTERVAL ? SECOND)';
    if ($st = $conn->prepare($sql)) {
        $st->bind_param('ssi', $email, $ip, $windowSeconds);
        $st->execute();
        $st->bind_result($cnt);
        $st->fetch();
        $st->close();
        return ((int)$cnt) >= $max;
    }
    return false;
}
