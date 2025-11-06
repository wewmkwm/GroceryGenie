<?php
// customer/customer_reset_password.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connect.php';

$statusMessage = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$isTokenValid = false;
$emailForReset = '';

function fetchResetRecord(mysqli $conn, string $token): ?array {
    if ($token === '') {
        return null;
    }
    $tokenHash = hash('sha256', $token);
    if ($stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token_hash = ? LIMIT 1")) {
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $stmt->bind_result($email, $expiresAt);
        if ($stmt->fetch()) {
            $stmt->close();
            if (strtotime($expiresAt) >= time()) {
                return ['email' => $email, 'expires_at' => $expiresAt];
            }
        } else {
            $stmt->close();
        }
    }
    return null;
}

if ($token !== '') {
    $record = fetchResetRecord($conn, $token);
    if ($record) {
        $isTokenValid = true;
        $emailForReset = $record['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isTokenValid) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $statusMessage = "<div class='alert alert-warning'>Password must be at least 6 characters long.</div>";
    } elseif ($password !== $confirm) {
        $statusMessage = "<div class='alert alert-warning'>Passwords do not match. Please try again.</div>";
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        if ($upd = $conn->prepare("UPDATE customers SET password = ? WHERE email = ?")) {
            $upd->bind_param('ss', $hash, $emailForReset);
            $upd->execute();
            $upd->close();
        }
        if ($del = $conn->prepare("DELETE FROM password_resets WHERE email = ?")) {
            $del->bind_param('s', $emailForReset);
            $del->execute();
            $del->close();
        }

        $statusMessage = "<div class='alert alert-success'>Your password has been reset successfully. <a href='customer_login.php' class='alert-link'>Return to login</a>.</div>";
        $isTokenValid = false;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isTokenValid) {
    $statusMessage = "<div class='alert alert-danger'>This reset link has expired or is invalid. Please request a new one.</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password · GroceryGenie</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background:
        radial-gradient(circle at top, rgba(255, 214, 170, 0.35), transparent 55%),
        radial-gradient(circle at bottom, rgba(146, 197, 255, 0.35), transparent 48%),
        linear-gradient(135deg, #ff8a4c, #ff6a00 52%, #ff3d71);
      padding: 2rem 1rem;
    }
    .reset-card {
      backdrop-filter: blur(18px);
      background: rgba(255, 255, 255, 0.2);
      border-radius: 28px;
      box-shadow: 0 28px 80px rgba(15, 23, 42, 0.4);
      color: #0f172a;
      max-width: 520px;
      width: 100%;
      padding: 2.6rem 2.8rem;
    }
    .brand-mark {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      font-size: 1.4rem;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 1.5rem;
    }
    .brand-mark span {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(15, 23, 42, 0.12);
      color: #ff6a00;
      font-size: 1.2rem;
    }
    h1 {
      font-size: 2rem;
      font-weight: 700;
    }
    .btn-reset {
      border-radius: 999px;
      padding: 0.75rem;
      font-weight: 600;
      background: linear-gradient(135deg, #ff8a4c, #ff6a00);
      border: none;
      color: #fff;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-reset:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 32px rgba(255, 106, 0, 0.35);
      color: #fff;
    }
    .back-link a {
      font-weight: 600;
      color: #ff6a00;
      text-decoration: none;
    }
    .back-link a:hover {
      text-decoration: underline;
    }
    @media (max-width: 575.98px) {
      .reset-card {
        padding: 2rem 1.75rem;
        border-radius: 20px;
      }
    }
  </style>
</head>
<body>
  <div class="reset-card">
    <div class="brand-mark">
      <span><i class="fas fa-seedling"></i></span>
      GroceryGenie
    </div>

    <?php if (!empty($statusMessage)) echo $statusMessage; ?>

    <?php if ($isTokenValid): ?>
      <h1>Create a new password</h1>
      <p class="text-muted">Choose a secure password to protect your account.</p>

      <form method="POST" class="mt-4">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="form-floating mb-3">
          <input type="password" class="form-control" id="passwordInput" name="password" placeholder="New password" required minlength="6">
          <label for="passwordInput"><i class="fas fa-lock me-2 text-muted"></i>New password</label>
        </div>
        <div class="form-floating mb-3">
          <input type="password" class="form-control" id="confirmInput" name="confirm_password" placeholder="Confirm password" required minlength="6">
          <label for="confirmInput"><i class="fas fa-lock me-2 text-muted"></i>Confirm password</label>
        </div>
        <button type="submit" class="btn btn-reset w-100"><i class="fas fa-check me-2"></i>Reset password</button>
      </form>
    <?php else: ?>
      <h1>Reset link unavailable</h1>
      <p class="text-muted">This reset link may have expired or already been used. Request a fresh link to continue.</p>
      <div class="back-link mt-4">
        <a href="customer_forgot_password.php"><i class="fas fa-redo me-2"></i>Request new reset link</a>
      </div>
    <?php endif; ?>

    <div class="back-link mt-4">
      <a href="customer_login.php"><i class="fas fa-arrow-left me-2"></i>Back to login</a>
    </div>
  </div>
</body>
</html>
