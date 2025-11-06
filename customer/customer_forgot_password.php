<?php
// customer/customer_forgot_password.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connect.php';

$statusMessage = '';
$debugResetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $statusMessage = "<div class='alert alert-warning'>Please enter a valid email address.</div>";
    } else {
        if ($stmt = $conn->prepare("SELECT customer_id, name FROM customers WHERE email = ? LIMIT 1")) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $customer = $result->fetch_assoc();

                $conn->query("
                    CREATE TABLE IF NOT EXISTS password_resets (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        email VARCHAR(191) NOT NULL,
                        token_hash CHAR(64) NOT NULL,
                        expires_at DATETIME NOT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_email (email),
                        INDEX idx_token (token_hash)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                if ($del = $conn->prepare("DELETE FROM password_resets WHERE email = ?")) {
                    $del->bind_param('s', $email);
                    $del->execute();
                    $del->close();
                }

                if ($ins = $conn->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)")) {
                    $ins->bind_param('sss', $email, $tokenHash, $expiresAt);
                    $ins->execute();
                    $ins->close();
                }

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $currentDir = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/customer/customer_forgot_password.php'), '/\\');
                $resetLink = $scheme . '://' . $host . $currentDir . '/customer_reset_password.php?token=' . urlencode($token);

                $subject = 'GroceryGenie Password Reset';
                $messageBody = "Hi {$customer['name']},\n\nWe received a request to reset your GroceryGenie password.\n"
                    . "Click the link below to choose a new password (valid for 60 minutes):\n\n{$resetLink}\n\n"
                    . "If you did not request this change, you can safely ignore this email.";
                $headers = "From: no-reply@grocerygenie.local";

                @mail($email, $subject, $messageBody, $headers);

                $statusMessage = "<div class='alert alert-success'>If an account exists for {$email}, you'll receive a reset link shortly.</div>";
                $debugResetLink = $resetLink;
            } else {
                $statusMessage = "<div class='alert alert-success'>If an account exists for {$email}, you'll receive a reset link shortly.</div>";
            }
            $stmt->close();
        } else {
            $statusMessage = "<div class='alert alert-danger'>We couldn't process your request right now. Please try again later.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password · GroceryGenie</title>
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
      background: rgba(255, 255, 255, 0.18);
      border-radius: 28px;
      box-shadow: 0 28px 80px rgba(15, 23, 42, 0.4);
      color: #0f172a;
      max-width: 520px;
      width: 100%;
      padding: 2.5rem 2.75rem;
    }
    .reset-card h1 {
      font-size: 2rem;
      font-weight: 700;
    }
    .reset-card p {
      color: rgba(15, 23, 42, 0.75);
    }
    .brand-mark {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      font-size: 1.35rem;
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
      background: rgba(15, 23, 42, 0.1);
      color: #ff6a00;
      font-size: 1.2rem;
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
    .support-note {
      font-size: 0.9rem;
      color: rgba(15, 23, 42, 0.65);
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
    <h1>Forgot your password?</h1>
    <p>Enter the email associated with your GroceryGenie account and we'll send you a secure link to reset your password.</p>

    <?php if (!empty($statusMessage)) echo $statusMessage; ?>

    <form method="POST" class="mt-4">
      <div class="form-floating mb-3">
        <input type="email" class="form-control" id="emailInput" name="email" placeholder="you@example.com" required>
        <label for="emailInput"><i class="fas fa-envelope me-2 text-muted"></i>Email address</label>
      </div>
      <button type="submit" class="btn btn-reset w-100"><i class="fas fa-paper-plane me-2"></i>Send reset link</button>
    </form>

    <?php if (!empty($debugResetLink)) : ?>
      <div class="alert alert-info mt-4">
        <div class="fw-semibold"><i class="fas fa-link me-2"></i>Reset link (development preview)</div>
        <div class="small text-break"><a href="<?php echo htmlspecialchars($debugResetLink); ?>"><?php echo htmlspecialchars($debugResetLink); ?></a></div>
      </div>
    <?php endif; ?>

    <div class="support-note mt-4">
      Didn't request a reset? You can ignore this page, or <a href="mailto:support@grocerygenie.local" class="fw-semibold text-decoration-none">contact support</a> for help.
    </div>
    <div class="back-link mt-3">
      <a href="customer_login.php"><i class="fas fa-arrow-left me-2"></i>Back to login</a>
    </div>
  </div>
</body>
</html>
