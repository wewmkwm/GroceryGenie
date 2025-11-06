<?php
// customer/customer_header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connect.php';

// Default profile picture
$default_profile_pic = '../assets/img/default_profile.png';
$profile_pic = $default_profile_pic;

/**
 * Convert a stored profile picture reference into a URL usable from customer pages.
 */
function customer_nav_profile_image_url(?string $stored, string $fallback): string
{
    if (empty($stored)) {
        return $fallback;
    }

    $stored = trim($stored);

    if (preg_match('/^https?:\/\//i', $stored)) {
        return $stored;
    }

    if (str_starts_with($stored, '../') || str_starts_with($stored, './') || str_starts_with($stored, '/')) {
        return $stored;
    }

    $normalized = ltrim($stored, './');
    if (str_starts_with($normalized, 'uploads/')) {
        return '../' . $normalized;
    }

    if (str_starts_with($normalized, 'customer/uploads/')) {
        return '../' . substr($normalized, strlen('customer/'));
    }

    return '../uploads/customers/' . $normalized;
}

// If customer logged in, fetch profile picture
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];
    $sql = "SELECT profile_pic FROM customers WHERE customer_id=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!empty($row['profile_pic'])) {
            $profile_pic = customer_nav_profile_image_url($row['profile_pic'], $default_profile_pic);
        }
    }
    $stmt->close();
}

// Unread notifications count
$notif_count = 0;
if (isset($_SESSION['customer_id'])) {
    @$conn->query("CREATE TABLE IF NOT EXISTS notifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      customer_id INT NOT NULL,
      order_id INT NOT NULL,
      type VARCHAR(50) DEFAULT 'order',
      message TEXT,
      is_read TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if ($stc = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE customer_id=? AND is_read=0')) {
        $stc->bind_param('i', $_SESSION['customer_id']);
        $stc->execute();
        $stc->bind_result($notif_count);
        $stc->fetch();
        $stc->close();
    }
}

// Detect current page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GroceryGenie - Customer</title>
  <meta name="theme-color" content="#000000">
  <link rel="manifest" href="../manifest.webmanifest">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/customer-theme.css">
  <style>
    .gg-navbar .navbar-brand {
      font-size: 1.45rem;
      letter-spacing: 0.015em;
    }
    .gg-navbar .navbar-brand span {
      width: 36px;
      height: 36px;
    }
    .gg-navbar .nav-link {
      font-size: 0.92rem;
      padding: 0.35rem 0.75rem;
    }
    @media (max-width: 575.98px) {
      .gg-navbar .navbar-brand {
        font-size: 1.3rem;
      }
      .gg-navbar .nav-link {
        font-size: 0.95rem;
      }
    }
  </style>
  <script>
    (function(){
      try{
        var d=document.documentElement;
        var mode = localStorage.getItem('themeMode') || 'auto';
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var isDark = (mode==='dark') || (mode==='auto' && prefersDark);
        if(isDark) d.classList.add('theme-dark'); else d.classList.remove('theme-dark');
        if(localStorage.getItem('hc')==='1') d.classList.add('hc');
        if(localStorage.getItem('fs')==='lg') d.classList.add('fs-lg');
        if(localStorage.getItem('rm')==='1') d.classList.add('rm');
      }catch(e){}
    })();
  </script>
</head>
<body class="gg-body d-flex flex-column">
  <nav class="navbar navbar-expand-lg gg-navbar sticky-top">
    <div class="container-fluid">
      <a class="navbar-brand" href="customer_home.php">
        <span><i class="fas fa-seedling"></i></span>
        GroceryGenie
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'all_recipes.php') ? 'active' : ''; ?>" href="all_recipes.php"><i class="fas fa-utensils"></i> All Recipes</a></li>
          <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'explore_recipes.php') ? 'active' : ''; ?>" href="explore_recipes.php"><i class="fas fa-compass"></i> Explore</a></li>
          <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'create_recipe.php') ? 'active' : ''; ?>" 
              href="<?php echo isset($_SESSION['customer_id']) ? 'create_recipe.php' : '#'; ?>" 
              <?php if (!isset($_SESSION['customer_id'])) { ?>
                onclick="alert('Please log in to create a recipe.'); return false;"
              <?php } ?>>
              <i class="fas fa-plus-circle"></i> Create Recipe
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'shopping.php') ? 'active' : ''; ?>" 
              href="<?php echo isset($_SESSION['customer_id']) ? 'shopping.php' : '#'; ?>" 
              <?php if (!isset($_SESSION['customer_id'])) { ?>
                onclick="alert('Please log in to access shopping.'); return false;"
              <?php } ?>>
              <i class="fas fa-shopping-cart"></i> Shopping
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'meal_planner.php') ? 'active' : ''; ?>" 
               href="<?php echo isset($_SESSION['customer_id']) ? 'meal_planner.php' : '#'; ?>"
               <?php if (!isset($_SESSION['customer_id'])) { ?>
                 onclick="alert('Please log in to use Meal Planner.'); return false;"
               <?php } ?>>
               <i class="fas fa-calendar-week"></i> Meal Planner
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'pantry.php') ? 'active' : ''; ?>" 
               href="<?php echo isset($_SESSION['customer_id']) ? 'pantry.php' : '#'; ?>"
               <?php if (!isset($_SESSION['customer_id'])) { ?>
                 onclick="alert('Please log in to use Pantry.'); return false;"
               <?php } ?>>
               <i class="fas fa-warehouse"></i> Pantry
            </a>
          </li>
        </ul>

        <div class="me-3">
          <a id="customerNotifLink" class="gg-btn-outline position-relative" href="notifications.php" title="Notifications">
            <i class="fas fa-bell"></i>
            <span id="customerNotifBadge" class="gg-notification-badge <?php echo ($notif_count > 0) ? '' : 'd-none'; ?>"><?php echo (int)$notif_count; ?></span>
          </a>
        </div>
        <div class="dropdown">
          <a href="#" role="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="gg-profile-thumb">
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
            <?php if (isset($_SESSION['customer_id'])) { ?>
              <li><a class="dropdown-item" href="customer_profile.php"><i class="fas fa-user"></i> Profile</a></li>
              <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
              <li><a class="dropdown-item" href="friends.php"><i class="fas fa-user-friends"></i> Friends</a></li>
              <li><a class="dropdown-item" href="my_orders.php"><i class="fas fa-receipt"></i> My Orders</a></li>
              <li><a class="dropdown-item" href="saved_recipes.php"><i class="fas fa-heart"></i> Saved Recipes</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php } else { ?>
              <li><a class="dropdown-item" href="customer_login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
              <li><a class="dropdown-item" href="customer_register.php"><i class="fas fa-user-plus"></i> Register</a></li>
            <?php } ?>
          </ul>
        </div>
      </div>
    </div>
  </nav>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const profileToggle = document.getElementById('profileMenu');
      const profileMenu = document.querySelector('[aria-labelledby="profileMenu"]');
      if (!profileToggle || !profileMenu) return;

      let manualEnabled = false;
      let manualOpen = false;

      const manualSetState = (isOpen) => {
        manualOpen = isOpen;
        profileMenu.classList.toggle('show', isOpen);
        profileMenu.style.display = isOpen ? 'block' : 'none';
        profileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      };

      const manualToggleHandler = (e) => {
        e.preventDefault();
        e.stopPropagation();
        manualSetState(!manualOpen);
      };

      const manualDocumentHandler = (e) => {
        if (profileMenu.contains(e.target) || profileToggle.contains(e.target)) return;
        manualSetState(false);
      };

      const stopPropagationHandler = (e) => e.stopPropagation();

      const enableManual = () => {
        if (manualEnabled) return;
        manualEnabled = true;
        manualSetState(false);

        profileToggle.addEventListener('click', manualToggleHandler);
        document.addEventListener('click', manualDocumentHandler);
        profileMenu.addEventListener('click', stopPropagationHandler);
      };

      const disableManual = () => {
        if (!manualEnabled) return;
        manualEnabled = false;
        profileToggle.removeEventListener('click', manualToggleHandler);
        document.removeEventListener('click', manualDocumentHandler);
        profileMenu.removeEventListener('click', stopPropagationHandler);
        manualSetState(false);
      };

      const setupBootstrapDropdown = () => {
        if (!(window.bootstrap && window.bootstrap.Dropdown)) return false;
        disableManual();
        bootstrap.Dropdown.getOrCreateInstance(profileToggle);
        return true;
      };

      if (!setupBootstrapDropdown()) {
        enableManual();

        window.addEventListener('load', () => {
          if (setupBootstrapDropdown()) {
            // Bootstrap is now available; no extra action required.
          }
        });

        setTimeout(() => {
          setupBootstrapDropdown();
        }, 500);
      }
    });
  </script>

  <script>
    // Register service worker for PWA
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        navigator.serviceWorker.register('../service-worker.js').catch(function(){});
      });
    }
  </script>
  <?php if (isset($_SESSION['customer_id'])): ?>
  <script>
    (function () {
      const badge = document.getElementById('customerNotifBadge');
      if (!badge) return;

      const updateBadge = (count) => {
        const value = Number(count) || 0;
        if (value > 0) {
          badge.textContent = value;
          badge.classList.remove('d-none');
        } else {
          badge.textContent = '';
          badge.classList.add('d-none');
        }
      };

      const fetchNotifications = async () => {
        try {
          const response = await fetch('notifications_count.php', { cache: 'no-store' });
          if (!response.ok) return;
          const data = await response.json();
          updateBadge(data.count);
        } catch (e) {
          // Fail silently; badge will update on next poll
        }
      };

      fetchNotifications();
      setInterval(fetchNotifications, 10000);
    })();
  </script>
  <?php endif; ?>
  <main class="container mt-4 flex-grow-1" id="main-content">


