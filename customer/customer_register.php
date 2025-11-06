<?php
// customer/customer_register.php

require_once __DIR__ . '/../db_connect.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Oops!</strong> Passwords do not match. Please try again.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    } else {
        if ($check = $conn->prepare('SELECT customer_id FROM customers WHERE email = ? LIMIT 1')) {
            $check->bind_param('s', $email);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><strong>Email already registered.</strong> Try logging in instead.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                if ($insert = $conn->prepare('INSERT INTO customers (name, email, password, phone_number, created_at) VALUES (?, ?, ?, ?, NOW())')) {
                    $insert->bind_param('ssss', $name, $email, $hash, $phone);
                    if ($insert->execute()) {
                        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Welcome aboard!</strong> Registration successful. <a class="alert-link" href="customer_login.php">Log in now</a>.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    } else {
                        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Something went wrong. Please try again.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    }
                    $insert->close();
                }
            }
            $check->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Join GroceryGenie</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --gg-primary: #ff8a4c;
      --gg-primary-dark: #ff6a00;
      --gg-secondary: #0f172a;
      --gg-muted: #64748b;
    }
    body {
      min-height: 100vh;
      margin: 0;
      font-family: 'Poppins', sans-serif;
      color: var(--gg-secondary);
      background:
        radial-gradient(circle at top, rgba(255, 214, 170, 0.3), transparent 55%),
        radial-gradient(circle at bottom, rgba(164, 197, 255, 0.26), transparent 48%),
        linear-gradient(135deg, #ff9f62, #ff6a00 55%, #ff3d71);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 1.2rem;
    }
    .register-shell {
      background: rgba(255, 255, 255, 0.18);
      border-radius: 28px;
      box-shadow: 0 30px 70px rgba(15, 23, 42, 0.3);
      overflow: hidden;
      max-width: 980px;
      width: 100%;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      backdrop-filter: blur(16px);
    }
    .register-story {
      padding: 3rem 3rem;
      position: relative;
      color: rgba(255, 255, 255, 0.95);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      background: linear-gradient(140deg, rgba(15, 23, 42, 0.32), rgba(15, 23, 42, 0.12));
    }
    .register-story::after {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at bottom left, rgba(15, 18, 64, 0.35), transparent 60%);
      pointer-events: none;
    }
    .register-story h1 {
      font-size: 2.25rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }
    .register-story p {
      margin-bottom: 1.5rem;
      color: rgba(255, 255, 255, 0.92);
    }
    .register-story ul {
      list-style: none;
      padding: 0;
      margin: 2rem 0 0;
      display: grid;
      gap: 1rem;
    }
    .register-story li {
      display: flex;
      align-items: center;
      gap: 0.9rem;
      padding: 0.8rem 1rem;
      background: rgba(255, 255, 255, 0.18);
      border-radius: 18px;
      backdrop-filter: blur(5px);
      border: 1px solid rgba(255, 255, 255, 0.18);
    }
    .register-story li i {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.24);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #ffe6d3;
    }
    .register-form {
      background: #ffffff;
      padding: 3.2rem 3rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .register-logo {
      display: inline-flex;
      align-items: center;
      gap: 0.65rem;
      font-size: 1.55rem;
      font-weight: 700;
      color: var(--gg-secondary);
      margin-bottom: 2.1rem;
    }
    .register-logo span {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      background: rgba(255, 138, 76, 0.16);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--gg-primary-dark);
    }
    .register-form h2 {
      font-weight: 700;
      margin-bottom: 0.6rem;
    }
    .register-form p {
      color: var(--gg-muted);
      margin-bottom: 2rem;
    }
    .form-floating > .form-control {
      border-radius: 14px;
      border: 1px solid rgba(15, 23, 42, 0.12);
      padding: 1.1rem 1.1rem 0.35rem;
      background: rgba(15, 23, 42, 0.02);
    }
    .form-floating > .form-control:focus {
      border-color: rgba(255, 138, 76, 0.9);
      box-shadow: 0 0 0 0.2rem rgba(255, 138, 76, 0.25);
    }
    .form-floating label {
      color: var(--gg-muted);
    }
    .btn-register {
      border-radius: 16px;
      border: none;
      padding: 0.9rem;
      font-weight: 600;
      letter-spacing: 0.02em;
      background: linear-gradient(135deg, var(--gg-primary), var(--gg-primary-dark));
      color: #0f172a;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-register:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 36px rgba(255, 106, 0, 0.35);
      color: #0f172a;
    }
    .register-footer {
      margin-top: 1.6rem;
      font-size: 0.95rem;
    }
    .register-footer a {
      color: var(--gg-primary-dark);
      font-weight: 600;
      text-decoration: none;
    }
    .register-footer a:hover {
      text-decoration: underline;
    }
    .forgot-note {
      font-size: 0.9rem;
      color: rgba(15, 23, 42, 0.7);
    }
    .forgot-note a {
      font-weight: 600;
      color: var(--gg-primary-dark);
      text-decoration: none;
    }
    .forgot-note a:hover {
      text-decoration: underline;
    }
    @media (max-width: 992px) {
      .register-shell {
        grid-template-columns: minmax(0, 1fr);
        max-width: 520px;
      }
      .register-story {
        display: none;
      }
      body {
        padding: 1.8rem 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="register-shell">
    <aside class="register-story">
      <div>
        <h1>Curate your perfect grocery run.</h1>
        <p>Save recipes, auto-build shopping lists, and keep your pantry synced with GroceryGenie.</p>
        <ul>
          <li><i class="fas fa-heart"></i><div><strong>Remember favorites</strong><br><span class="small">Bookmark recipes you love and revisit anytime.</span></div></li>
          <li><i class="fas fa-basket-shopping"></i><div><strong>Smart shopping carts</strong><br><span class="small">Move ingredients straight to checkout—no manual entry.</span></div></li>
          <li><i class="fas fa-bell"></i><div><strong>Status updates</strong><br><span class="small">Get alerts for order progress and special offers.</span></div></li>
        </ul>
      </div>
      <div class="small opacity-75"><i class="fas fa-lock me-2"></i>Your data is encrypted and never sold.</div>
    </aside>
    <section class="register-form">
      <div class="register-logo">
        <span><i class="fas fa-seedling"></i></span>
        GroceryGenie
      </div>
      <h2>Create your account</h2>
      <p>Join the community, plan smarter meals, and check out in minutes.</p>
      <?php echo $message; ?>
      <form method="POST" class="needs-validation" novalidate>
        <div class="form-floating mb-3">
          <input type="text" class="form-control" id="nameInput" name="name" placeholder="Full name" required>
          <label for="nameInput"><i class="fas fa-user me-2 text-muted"></i>Full name</label>
          <div class="invalid-feedback">Please enter your full name.</div>
        </div>
        <div class="form-floating mb-3">
          <input type="email" class="form-control" id="emailInput" name="email" placeholder="name@example.com" required>
          <label for="emailInput"><i class="fas fa-envelope me-2 text-muted"></i>Email address</label>
          <div class="invalid-feedback">Provide a valid email address.</div>
        </div>
        <div class="form-floating mb-3">
          <input type="tel" class="form-control" id="phoneInput" name="phone_number" placeholder="Phone number" required pattern="[\d\s\-\+\(\)]{6,}">
          <label for="phoneInput"><i class="fas fa-phone me-2 text-muted"></i>Phone number</label>
          <div class="invalid-feedback">Enter a valid phone number.</div>
        </div>
        <div class="form-floating mb-3">
          <input type="password" class="form-control" id="passwordInput" name="password" placeholder="Password" minlength="6" required>
          <label for="passwordInput"><i class="fas fa-lock me-2 text-muted"></i>Password</label>
          <div class="invalid-feedback">Password should be at least 6 characters.</div>
        </div>
        <div class="form-floating mb-3">
          <input type="password" class="form-control" id="confirmInput" name="confirm_password" placeholder="Confirm password" minlength="6" required>
          <label for="confirmInput"><i class="fas fa-lock me-2 text-muted"></i>Confirm password</label>
          <div class="invalid-feedback">Please confirm your password.</div>
        </div>
        <button type="submit" class="btn btn-register w-100 mt-2"><i class="fas fa-user-plus me-2"></i>Sign up</button>
      </form>
      <div class="register-footer text-center">
        Already have an account?
        <a href="customer_login.php">Log in</a>
      </div>
      <div class="forgot-note text-center mt-3">
        Forgot your password? <a href="customer_forgot_password.php">Reset it here</a>
      </div>
    </section>
  </div>

  <script>
    (function () {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      const passwordInput = document.getElementById('passwordInput');
      const confirmInput = document.getElementById('confirmInput');

      const validatePasswords = () => {
        if (passwordInput && confirmInput) {
          if (confirmInput.value !== passwordInput.value) {
            confirmInput.setCustomValidity('Passwords do not match');
          } else {
            confirmInput.setCustomValidity('');
          }
        }
      };

      passwordInput?.addEventListener('input', validatePasswords);
      confirmInput?.addEventListener('input', validatePasswords);

      Array.from(forms).forEach((form) => {
        form.addEventListener('submit', (event) => {
          validatePasswords();
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    })();
  </script>
</body>
</html>
