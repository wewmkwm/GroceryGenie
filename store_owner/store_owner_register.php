<?php
// store_owner/store_owner_register.php

require_once __DIR__ . '/../db_connect.php';

$message = '';

if (!isset($_SESSION)) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $storeName = trim($_POST['store_name'] ?? '');
    $storeAddress = trim($_POST['store_address'] ?? '');
    $businessReg = trim($_POST['business_reg_no'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Passwords do not match.</strong> Please try again.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    } else {
        if ($check = $conn->prepare('SELECT owner_id FROM store_owners WHERE email = ? LIMIT 1')) {
            $check->bind_param('s', $email);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><strong>Email already registered.</strong> Try logging in instead.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                if ($insert = $conn->prepare('INSERT INTO store_owners (name, email, password, phone_number, store_name, store_address, business_reg_no, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, "pending", NOW())')) {
                    $insert->bind_param('sssssss', $name, $email, $hash, $phone, $storeName, $storeAddress, $businessReg);
                    if ($insert->execute()) {
                        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Application received!</strong> We\'ll review your details shortly. <a class="alert-link" href="store_owner_login.php">Log in</a> once approved.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    } else {
                        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Unable to complete registration. Please try again.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
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
  <title>Become a GroceryGenie Store Owner</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --gg-primary: #14b8a6;
      --gg-primary-dark: #0ea5e9;
      --gg-secondary: #0f172a;
      --gg-muted: #64748b;
    }
    body {
      min-height: 100vh;
      margin: 0;
      font-family: 'Poppins', sans-serif;
      color: var(--gg-secondary);
      background:
        radial-gradient(circle at top, rgba(34, 211, 238, 0.32), transparent 50%),
        radial-gradient(circle at bottom, rgba(45, 212, 191, 0.35), transparent 55%),
        linear-gradient(135deg, #0f172a, #0ea5e9 55%, #38bdf8);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 1.2rem;
    }
    .owner-register-wrapper {
      background: rgba(255, 255, 255, 0.18);
      border-radius: 30px;
      box-shadow: 0 30px 75px rgba(15, 23, 42, 0.35);
      overflow: hidden;
      max-width: 1080px;
      width: 100%;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      backdrop-filter: blur(18px);
    }
    .owner-story {
      position: relative;
      padding: 3.2rem 3rem;
      color: rgba(255, 255, 255, 0.95);
      background: linear-gradient(135deg, rgba(14, 165, 233, 0.3), rgba(20, 184, 166, 0.16));
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .owner-story::after {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at top right, rgba(14, 165, 233, 0.35), transparent 60%);
      pointer-events: none;
    }
    .owner-story h1 {
      font-size: 2.4rem;
      font-weight: 700;
      margin-bottom: 1.2rem;
    }
    .owner-story p {
      margin-bottom: 1.6rem;
      color: rgba(229, 243, 255, 0.92);
    }
    .owner-story ul {
      list-style: none;
      padding: 0;
      margin: 2rem 0 0;
      display: grid;
      gap: 1rem;
    }
    .owner-story li {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.15rem;
      border-radius: 20px;
      background: rgba(15, 23, 42, 0.28);
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(5px);
    }
    .owner-story li i {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.22);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #cffafe;
      font-size: 1.2rem;
    }
    .owner-form {
      background: #ffffff;
      padding: 3.2rem 3rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .owner-logo {
      display: inline-flex;
      align-items: center;
      gap: 0.7rem;
      font-size: 1.55rem;
      font-weight: 700;
      color: var(--gg-secondary);
      margin-bottom: 2rem;
    }
    .owner-logo span {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: rgba(14, 165, 233, 0.15);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--gg-primary-dark);
    }
    .owner-form h2 {
      font-weight: 700;
      margin-bottom: 0.7rem;
    }
    .owner-form p {
      color: var(--gg-muted);
      margin-bottom: 2.2rem;
    }
    .form-floating > .form-control, .form-floating > .form-select, .form-floating > textarea {
      border-radius: 14px;
      border: 1px solid rgba(15, 23, 42, 0.12);
      padding: 1.1rem 1.1rem 0.35rem;
      background: rgba(15, 23, 42, 0.02);
    }
    .form-floating > textarea.form-control {
      min-height: 120px;
    }
    .form-floating > .form-control:focus, .form-floating > textarea:focus {
      border-color: rgba(14, 165, 233, 0.85);
      box-shadow: 0 0 0 0.2rem rgba(14, 165, 233, 0.2);
    }
    .form-floating label {
      color: var(--gg-muted);
    }
    .owner-btn {
      border-radius: 16px;
      border: none;
      padding: 0.9rem;
      font-weight: 600;
      background: linear-gradient(135deg, var(--gg-primary), var(--gg-primary-dark));
      color: #0f172a;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .owner-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 36px rgba(14, 165, 233, 0.32);
      color: #0f172a;
    }
    .owner-footer {
      margin-top: 1.5rem;
      font-size: 0.95rem;
    }
    .owner-footer a {
      color: var(--gg-primary-dark);
      font-weight: 600;
      text-decoration: none;
    }
    .owner-footer a:hover {
      text-decoration: underline;
    }
    @media (max-width: 992px) {
      .owner-register-wrapper {
        grid-template-columns: minmax(0, 1fr);
        max-width: 540px;
      }
      .owner-story {
        display: none;
      }
      body {
        padding: 1.8rem 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="owner-register-wrapper">
    <aside class="owner-story">
      <div>
        <h1>Grow your grocery business with GroceryGenie.</h1>
        <p>Join our network to reach new customers, manage inventory pain-free, and track performance with smart dashboards.</p>
        <ul>
          <li><i class="fas fa-chart-line"></i><div><strong>Real-time sales analytics</strong><br><span class="small">Monitor sales velocity, top products, and customer trends.</span></div></li>
          <li><i class="fas fa-bell"></i><div><strong>Automation alerts</strong><br><span class="small">Get notified when items hit low stock or orders need attention.</span></div></li>
          <li><i class="fas fa-handshake"></i><div><strong>Dedicated partner support</strong><br><span class="small">We help you set up listings, promotions, and fulfillment flows.</span></div></li>
        </ul>
      </div>
      <div class="small opacity-80"><i class="fas fa-shield-alt me-2"></i>Business details are securely stored for compliance only.</div>
    </aside>
    <section class="owner-form">
      <div class="owner-logo">
        <span><i class="fas fa-store"></i></span>
        GroceryGenie for Stores
      </div>
      <h2>Partner registration</h2>
      <p>Provide a few details so our team can verify and approve your storefront.</p>
      <?php echo $message; ?>
      <form method="POST" class="needs-validation" novalidate>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="ownerNameInput" name="name" placeholder="Full name" required>
              <label for="ownerNameInput"><i class="fas fa-user me-2 text-muted"></i>Full name</label>
              <div class="invalid-feedback">Please enter your full name.</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="email" class="form-control" id="ownerEmailInput" name="email" placeholder="name@store.com" required>
              <label for="ownerEmailInput"><i class="fas fa-envelope me-2 text-muted"></i>Email address</label>
              <div class="invalid-feedback">Provide a valid email address.</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="tel" class="form-control" id="ownerPhoneInput" name="phone_number" placeholder="Phone number" required pattern="[\d\s\-\+\(\)]{6,}">
              <label for="ownerPhoneInput"><i class="fas fa-phone me-2 text-muted"></i>Phone number</label>
              <div class="invalid-feedback">Enter a valid phone number.</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="storeNameInput" name="store_name" placeholder="Store name" required>
              <label for="storeNameInput"><i class="fas fa-store me-2 text-muted"></i>Store name</label>
              <div class="invalid-feedback">Your store name is required.</div>
            </div>
          </div>
          <div class="col-12">
            <div class="form-floating">
              <textarea class="form-control" id="addressInput" name="store_address" placeholder="Store address" required></textarea>
              <label for="addressInput"><i class="fas fa-map-marker-alt me-2 text-muted"></i>Store address</label>
              <div class="invalid-feedback">Please provide the store address.</div>
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-floating">
              <input type="text" class="form-control" id="businessRegInput" name="business_reg_no" placeholder="Business registration number">
              <label for="businessRegInput"><i class="fas fa-briefcase me-2 text-muted"></i>Business registration no.</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="password" class="form-control" id="passwordInput" name="password" placeholder="Password" minlength="6" required>
              <label for="passwordInput"><i class="fas fa-lock me-2 text-muted"></i>Password</label>
              <div class="invalid-feedback">Password must be at least 6 characters.</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="password" class="form-control" id="confirmInput" name="confirm_password" placeholder="Confirm password" minlength="6" required>
              <label for="confirmInput"><i class="fas fa-lock me-2 text-muted"></i>Confirm password</label>
              <div class="invalid-feedback">Please confirm your password.</div>
            </div>
          </div>
        </div>
        <button type="submit" class="btn owner-btn w-100 mt-3"><i class="fas fa-user-plus me-2"></i>Submit application</button>
      </form>
      <div class="owner-footer text-center">
        Already a partner?
        <a href="store_owner_login.php">Log in here</a>
      </div>
    </section>
  </div>

  <script>
    (function () {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      const password = document.getElementById('passwordInput');
      const confirm = document.getElementById('confirmInput');

      const validatePasswords = () => {
        if (password && confirm) {
          if (confirm.value !== password.value) {
            confirm.setCustomValidity('Passwords do not match');
          } else {
            confirm.setCustomValidity('');
          }
        }
      };

      password?.addEventListener('input', validatePasswords);
      confirm?.addEventListener('input', validatePasswords);

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
