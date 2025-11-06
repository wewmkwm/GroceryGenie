<?php
// admin/admin_login.php

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
        if ($stmt = $conn->prepare("SELECT admin_id, name, password FROM admins WHERE email = ? LIMIT 1")) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $row = $result->fetch_assoc();
                if (password_verify($password, $row['password'])) {
                    $_SESSION['admin_id'] = $row['admin_id'];
                    $_SESSION['admin_name'] = $row['name'];
                    $_SESSION['role'] = 'admin';

                    header("Location: admin_home.php");
                    exit();
                }
                $message = "<div class='alert alert-danger text-center fw-semibold'>Invalid password.</div>";
            } else {
                $message = "<div class='alert alert-danger text-center fw-semibold'>No admin account found with that email.</div>";
            }
            $stmt->close();
        }
    } else {
        $message = "<div class='alert alert-warning text-center fw-semibold'>Please enter both email and password.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login • GroceryGenie</title>
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
        radial-gradient(circle at top, rgba(255, 214, 170, 0.4), transparent 55%),
        radial-gradient(circle at bottom, rgba(164, 197, 255, 0.28), transparent 50%),
        linear-gradient(135deg, #0f172a, #1e293b 52%, #111827);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
      color: #fff;
    }
    .auth-wrapper {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0;
      background: rgba(15, 23, 42, 0.4);
      border-radius: 24px;
      box-shadow: 0 30px 80px rgba(15, 23, 42, 0.55);
      overflow: hidden;
      max-width: 980px;
      width: 100%;
    }
    .auth-hero {
      position: relative;
      background: linear-gradient(140deg, rgba(255, 138, 76, 0.15), rgba(255, 214, 130, 0.05));
      padding: 3rem 2.8rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      color: rgba(255, 255, 255, 0.92);
    }
    .auth-hero::after {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at top right, rgba(255, 169, 100, 0.35), transparent 60%);
      pointer-events: none;
    }
    .auth-hero h1 {
      font-size: 2.3rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }
    .hero-metrics {
      display: grid;
      gap: 1rem;
      margin-top: 2.5rem;
    }
    .hero-metric {
      background: rgba(15, 23, 42, 0.35);
      border-radius: 18px;
      padding: 1rem 1.35rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      backdrop-filter: blur(6px);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .hero-metric i {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 138, 76, 0.22);
      color: #ffb86c;
      font-size: 1.2rem;
    }
    .auth-panel {
      background: var(--gg-surface);
      padding: 3rem 2.75rem;
      color: var(--gg-dark);
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
      margin-bottom: 2.2rem;
    }
    .auth-logo span {
      width: 40px;
      height: 40px;
      border-radius: 14px;
      background: rgba(255, 138, 76, 0.2);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--gg-primary-dark);
    }
    .auth-panel h2 {
      font-weight: 700;
      margin-bottom: 0.6rem;
    }
    .auth-panel p {
      color: var(--gg-muted);
      margin-bottom: 2rem;
    }
    .form-floating .form-control {
      border-radius: 14px;
      border: 1px solid rgba(15, 23, 42, 0.12);
      padding: 1.1rem 1.1rem 0.35rem;
      background: rgba(15, 23, 42, 0.02);
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-floating .form-control:focus {
      border-color: rgba(255, 138, 76, 0.8);
      box-shadow: 0 0 0 0.2rem rgba(255, 138, 76, 0.25);
    }
    .form-floating label {
      padding-left: 1.1rem;
      color: var(--gg-muted);
    }
    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear {
      display: none;
    }
    input[type="password"]::-webkit-credentials-auto-fill-button {
      visibility: hidden;
      display: none;
      pointer-events: none;
    }
    .toggle-password {
      position: absolute;
      right: 0.85rem;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: rgba(15, 23, 42, 0.45);
    }
    .btn-login {
      background: linear-gradient(135deg, var(--gg-primary), var(--gg-primary-dark));
      border: none;
      border-radius: 14px;
      padding: 0.85rem;
      font-weight: 600;
      letter-spacing: 0.02em;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      color: #0f172a;
    }
    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 35px rgba(255, 138, 76, 0.38);
      color: #0f172a;
    }
    .alt-links {
      display: grid;
      gap: 0.75rem;
      margin-top: 2rem;
    }
    .alt-links .btn {
      border-radius: 12px;
      font-weight: 600;
      padding: 0.7rem 1rem;
    }
    .security-note {
      font-size: 0.82rem;
      color: rgba(255, 255, 255, 0.65);
      margin-top: auto;
    }
    @media (max-width: 992px) {
      .auth-wrapper {
        grid-template-columns: minmax(0, 1fr);
        max-width: 480px;
      }
      .auth-hero {
        display: none;
      }
      body {
        padding: 1.5rem 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-hero">
      <div>
        <h1>Welcome back, Admin.</h1>
        <p>Monitor performance, approve store owners, and keep recipes thriving — all from the GroceryGenie console.</p>
      </div>
      <div class="hero-metrics">
        <div class="hero-metric">
          <i class="fas fa-chart-line"></i>
          <div>
            <strong>Realtime dashboards</strong>
            <span class="d-block small opacity-75">Track orders, revenue, and recipe performance.</span>
          </div>
        </div>
        <div class="hero-metric">
          <i class="fas fa-user-shield"></i>
          <div>
            <strong>Role-based control</strong>
            <span class="d-block small opacity-75">Manage customers &amp; store owners securely.</span>
          </div>
        </div>
        <div class="hero-metric">
          <i class="fas fa-bell"></i>
          <div>
            <strong>Smart notifications</strong>
            <span class="d-block small opacity-75">Stay ahead with critical system alerts.</span>
          </div>
        </div>
      </div>
      <div class="security-note">
        <i class="fas fa-lock me-2"></i> Two-factor authentication available in account settings.
      </div>
    </div>

    <div class="auth-panel">
      <div class="auth-logo">
        <span><i class="fas fa-seedling"></i></span>
        GroceryGenie Admin
      </div>
      <h2>Sign in to your dashboard</h2>
      <p>Enter your credentials to continue. Your session will stay secure on this device.</p>

      <?php if (!empty($message)) echo $message; ?>

      <form method="POST" class="needs-validation" novalidate>
        <div class="form-floating mb-3">
          <input type="email" class="form-control" id="emailInput" placeholder="name@domain.com" name="email" required>
          <label for="emailInput"><i class="fas fa-envelope me-2 text-muted"></i>Email address</label>
          <div class="invalid-feedback">Please enter a valid email.</div>
        </div>
        <div class="form-floating mb-3 position-relative">
          <input type="password" class="form-control" id="passwordInput" placeholder="Password" name="password" required minlength="6">
          <label for="passwordInput"><i class="fas fa-lock me-2 text-muted"></i>Password</label>
          <i class="fas fa-eye toggle-password" data-toggle="passwordInput"></i>
          <div class="invalid-feedback">Your password must be at least 6 characters.</div>
        </div>
        <button type="submit" class="btn btn-login w-100 mt-2">
          <i class="fas fa-arrow-right-to-bracket me-2"></i>Sign in
        </button>
      </form>

      <div class="alt-links">
        <a href="../customer/customer_login.php" class="btn btn-outline-secondary">
          <i class="fas fa-user me-2"></i>Customer login
        </a>
        <a href="../store_owner/store_owner_login.php" class="btn btn-outline-primary">
          <i class="fas fa-store me-2"></i>Store owner login
        </a>
      </div>
    </div>
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
