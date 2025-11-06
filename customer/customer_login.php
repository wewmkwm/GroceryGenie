<?php
// customer/customer_login.php

session_start();

$conn = new mysqli("localhost", "root", "", "grocerygenie");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email !== '' && $password !== '') {
        if ($stmt = $conn->prepare("SELECT customer_id, name, password FROM customers WHERE email = ? LIMIT 1")) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $row = $result->fetch_assoc();

                if (password_verify($password, $row['password'])) {
                    $_SESSION['customer_id'] = $row['customer_id'];
                    $_SESSION['customer_name'] = $row['name'];
                    $_SESSION['role'] = 'customer';

                    $checkCol = $conn->query("SHOW COLUMNS FROM customers LIKE 'last_login_at'");
                    if ($checkCol && $checkCol->num_rows === 0) {
                        @$conn->query("ALTER TABLE customers ADD COLUMN last_login_at DATETIME NULL AFTER created_at");
                    }
                    $checkIp = $conn->query("SHOW COLUMNS FROM customers LIKE 'last_login_ip'");
                    if ($checkIp && $checkIp->num_rows === 0) {
                        @$conn->query("ALTER TABLE customers ADD COLUMN last_login_ip VARCHAR(45) NULL AFTER last_login_at");
                    }

                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    if ($upd = $conn->prepare("UPDATE customers SET last_login_at = NOW(), last_login_ip = ? WHERE customer_id = ?")) {
                        $upd->bind_param("si", $ip, $row['customer_id']);
                        $upd->execute();
                        $upd->close();
                    }

                    header("Location: customer_home.php");
                    exit();
                }
                $message = "<div class='alert alert-danger text-center fw-semibold'>Incorrect password. Please try again.</div>";
            } else {
                $message = "<div class='alert alert-danger text-center fw-semibold'>No account found with that email.</div>";
            }
            $stmt->close();
        }
    } else {
        $message = "<div class='alert alert-warning text-center fw-semibold'>Enter both email and password to continue.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Customer Login • GroceryGenie</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --gg-primary: #ff8a4c;
      --gg-primary-dark: #ff6a00;
      --gg-surface: #ffffff;
      --gg-dark: #0f172a;
      --gg-muted: #64748b;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background:
        radial-gradient(circle at top, rgba(255, 214, 170, 0.35), transparent 50%),
        radial-gradient(circle at bottom, rgba(146, 197, 255, 0.35), transparent 50%),
        linear-gradient(135deg, #ff8a4c, #ff6a00 52%, #ff3d71);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 1.5rem;
      color: #fff;
    }
    .auth-shell {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      border-radius: 28px;
      overflow: hidden;
      background: rgba(255, 255, 255, 0.12);
      backdrop-filter: blur(18px);
      box-shadow: 0 28px 80px rgba(15, 23, 42, 0.45);
      max-width: 980px;
      width: 100%;
      position: relative;
    }
    .auth-side {
      position: relative;
      padding: 3rem 3.25rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      color: rgba(255, 255, 255, 0.95);
      background: linear-gradient(135deg, rgba(15, 23, 42, 0.2), rgba(15, 23, 42, 0.05));
    }
    .auth-side::after {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at bottom left, rgba(15, 18, 64, 0.35), transparent 55%);
      pointer-events: none;
    }
    .auth-side h1 {
      font-size: 2.3rem;
      font-weight: 700;
      margin-bottom: 1.2rem;
    }
    .auth-side .selling-points {
      display: grid;
      gap: 1rem;
      margin-top: 2.4rem;
    }
    .auth-point {
      display: flex;
      align-items: center;
      gap: 0.95rem;
      padding: 0.95rem 1.2rem;
      border-radius: 18px;
      background: rgba(15, 23, 42, 0.3);
      backdrop-filter: blur(4px);
      border: 1px solid rgba(255, 255, 255, 0.12);
    }
    .auth-point i {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.18);
      color: #ffe3c2;
      font-size: 1.2rem;
    }
    .auth-form-panel {
      background: var(--gg-surface);
      color: var(--gg-dark);
      padding: 3rem 3rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .auth-logo {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      font-size: 1.45rem;
      font-weight: 700;
      color: var(--gg-dark);
      margin-bottom: 2.25rem;
    }
    .auth-logo span {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      background: rgba(255, 138, 76, 0.14);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--gg-primary-dark);
    }
    .auth-form-panel h2 {
      font-weight: 700;
      margin-bottom: 0.75rem;
    }
    .auth-form-panel p {
      color: var(--gg-muted);
      margin-bottom: 2.2rem;
    }
    .form-floating .form-control {
      border-radius: 14px;
      border: 1px solid rgba(15, 23, 42, 0.12);
      padding: 1.1rem 1.1rem 0.35rem;
      background: rgba(15, 23, 42, 0.02);
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-floating .form-control:focus {
      border-color: rgba(255, 138, 76, 0.85);
      box-shadow: 0 0 0 0.2rem rgba(255, 138, 76, 0.25);
    }
    .form-floating label {
      padding-left: 1.1rem;
      color: var(--gg-muted);
    }
    .forgot-link {
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--gg-primary-dark);
      text-decoration: none;
    }
    .forgot-link:hover {
      text-decoration: underline;
      color: var(--gg-primary-dark);
    }
    .toggle-password {
      position: absolute;
      right: 0.9rem;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: rgba(15, 23, 42, 0.45);
    }
    .btn-login {
      background: linear-gradient(135deg, var(--gg-primary), var(--gg-primary-dark));
      border: none;
      border-radius: 16px;
      padding: 0.9rem;
      font-weight: 600;
      letter-spacing: 0.02em;
      color: #0f172a;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 40px rgba(255, 138, 76, 0.35);
      color: #0f172a;
    }
    .form-footer {
      margin-top: 1.5rem;
      font-size: 0.95rem;
    }
    .form-footer a {
      color: var(--gg-primary-dark);
      font-weight: 600;
      text-decoration: none;
    }
    .form-footer a:hover {
      text-decoration: underline;
    }
    .alt-actions {
      border-top: 1px solid rgba(15, 23, 42, 0.08);
      margin-top: 2.4rem;
      padding-top: 2rem;
      display: grid;
      gap: 0.75rem;
    }
    .alt-actions .btn {
      border-radius: 12px;
      font-weight: 600;
      padding: 0.75rem 1rem;
    }
    @media (max-width: 992px) {
      .auth-shell {
        grid-template-columns: minmax(0, 1fr);
        max-width: 480px;
      }
      .auth-side {
        display: none;
      }
      body {
        padding: 1.5rem 1.2rem;
      }
    }
  </style>
</head>
<body>
  <div class="auth-shell">
    <aside class="auth-side">
      <div>
        <h1>Welcome back to GroceryGenie.</h1>
        <p>Pick up where you left off — your saved recipes, carts, and personalized grocery lists are waiting.</p>
      </div>
      <div class="selling-points">
        <div class="auth-point">
          <i class="fas fa-heart"></i>
          <div>
            <strong>Save your favourites</strong>
            <span class="d-block small opacity-75">Quickly revisit recipes and shopping plans.</span>
          </div>
        </div>
        <div class="auth-point">
          <i class="fas fa-basket-shopping"></i>
          <div>
            <strong>Smart grocery lists</strong>
            <span class="d-block small opacity-75">Auto-build carts from any recipe.</span>
          </div>
        </div>
        <div class="auth-point">
          <i class="fas fa-bell"></i>
          <div>
            <strong>Real-time updates</strong>
            <span class="d-block small opacity-75">Track orders and delivery status instantly.</span>
          </div>
        </div>
      </div>
      <div class="small opacity-75 mt-4">
        <i class="fas fa-shield-alt me-2"></i>Your credentials are encrypted and kept private.
      </div>
    </aside>

    <section class="auth-form-panel">
      <div class="auth-logo">
        <span><i class="fas fa-seedling"></i></span>
        GroceryGenie
      </div>
      <h2>Sign in to continue</h2>
      <p>Access your personalised recipe feed, grocery bundles, and seamless checkout experience.</p>

      <?php if (!empty($message)) echo $message; ?>

      <form method="POST" class="needs-validation" novalidate>
        <div class="form-floating mb-3">
          <input type="email" class="form-control" id="emailInput" name="email" placeholder="name@example.com" required>
          <label for="emailInput">Email address</label>
          <div class="invalid-feedback">Please enter a valid email address.</div>
        </div>
        <div class="form-floating mb-3 position-relative">
          <input type="password" class="form-control" id="passwordInput" name="password" placeholder="Password" required minlength="6">
          <label for="passwordInput">Password</label>
          <i class="fas fa-eye toggle-password" data-toggle="passwordInput"></i>
          <div class="invalid-feedback">Password must be at least 6 characters.</div>
        </div>
        <div class="text-end mb-3">
          <a href="customer_forgot_password.php" class="forgot-link">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-login w-100">
          <i class="fas fa-arrow-right-to-bracket me-2"></i>Sign in
        </button>
      </form>

      <div class="form-footer d-flex justify-content-between align-items-center">
        <span>New to GroceryGenie?</span>
        <a href="customer_register.php"><i class="fas fa-user-plus me-1"></i>Create account</a>
      </div>

      <div class="alt-actions">
        <a href="../store_owner/store_owner_login.php" class="btn btn-outline-primary">
          <i class="fas fa-store me-2"></i>Store owner login
        </a>
        <a href="../admin/admin_login.php" class="btn btn-outline-secondary">
          <i class="fas fa-user-shield me-2"></i>Admin login
        </a>
      </div>
    </section>
  </div>

  <script>
    (function () {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });

      document.querySelectorAll('.toggle-password').forEach(toggle => {
        toggle.addEventListener('click', function () {
          const targetId = this.getAttribute('data-toggle');
          const input = document.getElementById(targetId);
          if (!input) return;
          const isPassword = input.getAttribute('type') === 'password';
          input.setAttribute('type', isPassword ? 'text' : 'password');
          this.classList.toggle('fa-eye');
          this.classList.toggle('fa-eye-slash');
        });
      });
    })();
  </script>
</body>
</html>
