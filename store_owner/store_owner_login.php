  <?php
  // store_owner/store_owner_login.php

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
          if ($stmt = $conn->prepare("SELECT owner_id, name, email, password, status FROM store_owners WHERE email = ? LIMIT 1")) {
              $stmt->bind_param("s", $email);
              $stmt->execute();
              $result = $stmt->get_result();

              if ($result && $result->num_rows === 1) {
                  $row = $result->fetch_assoc();
                  if (password_verify($password, $row['password'])) {
                      if ($row['status'] === 'approved') {
                          $_SESSION['store_owner_id'] = $row['owner_id'];
                          $_SESSION['store_owner_name'] = $row['name'];
                          $_SESSION['role'] = 'store_owner';

                          header("Location: store_owner_dashboard.php");
                          exit();
                      }
                      $message = "<div class='alert alert-warning text-center fw-semibold'>Your account is awaiting admin approval.</div>";
                  } else {
                      $message = "<div class='alert alert-danger text-center fw-semibold'>Incorrect password. Please try again.</div>";
                  }
              } else {
                  $message = "<div class='alert alert-danger text-center fw-semibold'>No store owner account found with that email.</div>";
              }
              $stmt->close();
          }
      } else {
          $message = "<div class='alert alert-warning text-center fw-semibold'>Please provide both email and password.</div>";
      }
  }
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Store Owner Login • GroceryGenie</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
      :root {
        --gg-primary: #22d3ee;
        --gg-primary-dark: #0ea5e9;
        --gg-accent: #34d399;
        --gg-surface: #ffffff;
        --gg-dark: #0f172a;
        --gg-muted: #64748b;
      }
      body {
        font-family: 'Poppins', sans-serif;
        background:
          radial-gradient(circle at top, rgba(45, 212, 191, 0.35), transparent 50%),
          radial-gradient(circle at bottom, rgba(14, 165, 233, 0.32), transparent 55%),
          linear-gradient(135deg, #0f172a, #0ea5e9 55%, #38bdf8);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.5rem;
        color: #fff;
      }
      .owner-auth-container {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border-radius: 28px;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(16px);
        box-shadow: 0 28px 90px rgba(15, 23, 42, 0.55);
        max-width: 960px;
        width: 100%;
        position: relative;
      }
      .owner-auth-hero {
        position: relative;
        padding: 3rem 3.25rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        color: rgba(255, 255, 255, 0.95);
        background: linear-gradient(140deg, rgba(14, 165, 233, 0.25), rgba(45, 212, 191, 0.08));
      }
      .owner-auth-hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(22, 189, 202, 0.4), transparent 60%);
        pointer-events: none;
      }
      .owner-auth-hero h1 {
        font-size: 2.25rem;
        font-weight: 700;
        margin-bottom: 1.1rem;
      }
      .owner-metrics {
        display: grid;
        gap: 1rem;
        margin-top: 2.4rem;
      }
      .owner-metric {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.35rem;
        border-radius: 18px;
        background: rgba(15, 23, 42, 0.35);
        backdrop-filter: blur(6px);
        border: 1px solid rgba(255, 255, 255, 0.12);
      }
      .owner-metric i {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.18);
        color: #a5f3fc;
        font-size: 1.2rem;
      }
      .owner-auth-form {
        background: var(--gg-surface);
        color: var(--gg-dark);
        padding: 3rem 3rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
      }
      .owner-logo {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        font-size: 1.45rem;
        font-weight: 700;
        color: var(--gg-dark);
        margin-bottom: 2.25rem;
      }
      .owner-logo span {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background: rgba(14, 165, 233, 0.16);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #0284c7;
      }
      .owner-auth-form h2 {
        font-weight: 700;
        margin-bottom: 0.7rem;
      }
      .owner-auth-form p {
        color: var(--gg-muted);
        margin-bottom: 2.1rem;
      }
      .form-floating .form-control {
        border-radius: 14px;
        border: 1px solid rgba(15, 23, 42, 0.12);
        padding: 1.1rem 1.1rem 0.35rem;
        background: rgba(15, 23, 42, 0.02);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
      }
      .form-floating .form-control:focus {
        border-color: rgba(14, 165, 233, 0.8);
        box-shadow: 0 0 0 0.2rem rgba(14, 165, 233, 0.25);
      }
      .form-floating label {
        padding-left: 1.1rem;
        color: var(--gg-muted);
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
        box-shadow: 0 18px 40px rgba(14, 165, 233, 0.32);
        color: #0f172a;
      }
      .extra-actions {
        border-top: 1px solid rgba(15, 23, 42, 0.08);
        margin-top: 2.4rem;
        padding-top: 2rem;
        display: grid;
        gap: 0.75rem;
      }
      .extra-actions .btn {
        border-radius: 12px;
        font-weight: 600;
        padding: 0.75rem 1rem;
      }
      .register-note {
        margin-top: 1.5rem;
        font-size: 0.95rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
      .register-note a {
        color: var(--gg-primary-dark);
        font-weight: 600;
        text-decoration: none;
      }
      .register-note a:hover {
        text-decoration: underline;
      }
      @media (max-width: 992px) {
        .owner-auth-container {
          grid-template-columns: minmax(0, 1fr);
          max-width: 480px;
        }
        .owner-auth-hero {
          display: none;
        }
        body {
          padding: 1.5rem 1.2rem;
        }
      }
    </style>
  </head>
  <body>
    <div class="owner-auth-container">
      <aside class="owner-auth-hero">
        <div>
          <h1>Grow your store with confidence.</h1>
          <p>Track sales, manage inventory, and keep shoppers delighted with GroceryGenie’s tools for store owners.</p>
        </div>
        <div class="owner-metrics">
          <div class="owner-metric">
            <i class="fas fa-chart-bar"></i>
            <div>
              <strong>Live performance insights</strong>
              <span class="d-block small opacity-75">Monitor revenue, orders, and top products at a glance.</span>
            </div>
          </div>
          <div class="owner-metric">
            <i class="fas fa-boxes-stacked"></i>
            <div>
              <strong>Inventory alerts</strong>
              <span class="d-block small opacity-75">Get notified before popular items run out.</span>
            </div>
          </div>
        </div>
        <div class="small opacity-75 mt-4">
          <i class="fas fa-lock me-2"></i>We protect every login with enterprise-grade encryption.
        </div>
      </aside>

      <section class="owner-auth-form">
        <div class="owner-logo">
          <span><i class="fas fa-store"></i></span>
          GroceryGenie Store Owner
        </div>
        <h2>Sign in to your dashboard</h2>
        <p>Manage inventory, fulfill orders, and explore sales reports from one intuitive place.</p>

        <?php if (!empty($message)) echo $message; ?>

        <form method="POST" class="needs-validation" novalidate>
          <div class="form-floating mb-3">
            <input type="email" class="form-control" id="emailInput" name="email" placeholder="name@store.com" required>
            <label for="emailInput"><i class="fas fa-envelope me-2 text-muted"></i>Email address</label>
            <div class="invalid-feedback">Enter a valid email address.</div>
          </div>
          <div class="form-floating mb-3 position-relative">
            <input type="password" class="form-control" id="passwordInput" name="password" placeholder="Password" required minlength="6">
            <label for="passwordInput"><i class="fas fa-lock me-2 text-muted"></i>Password</label>
            <i class="fas fa-eye toggle-password" data-toggle="passwordInput"></i>
            <div class="invalid-feedback">Password must be at least 6 characters.</div>
          </div>
          <button type="submit" class="btn btn-login w-100">
            <i class="fas fa-arrow-right-to-bracket me-2"></i>Sign in
          </button>
        </form>

        <div class="register-note">
          <span>New partner?</span>
          <a href="store_owner_register.php"><i class="fas fa-user-plus me-1"></i>Create store account</a>
        </div>

        <div class="extra-actions">
          <a href="../customer/customer_login.php" class="btn btn-outline-secondary">
            <i class="fas fa-user me-2"></i>Customer login
          </a>
          <a href="../admin/admin_login.php" class="btn btn-outline-primary">
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
