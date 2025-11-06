<?php
// admin/admin_header.php — shared admin navigation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../db_connect.php';

set_role('admin');
require_role('admin');

$defaultProfile = '../assets/img/admin_icon.png';
$profile_pic = $defaultProfile;
if (!file_exists($profile_pic)) {
    $profile_pic = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f464.svg';
}

$pendingRecipes = 0;
if (isset($conn) && $conn instanceof mysqli) {
    if ($result = $conn->query("SELECT COUNT(*) AS total_pending FROM recipe WHERE status = 'pending'")) {
        $row = $result->fetch_assoc();
        $pendingRecipes = (int)($row['total_pending'] ?? 0);
        $result->free();
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GroceryGenie - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/customer-theme.css">
  <script>
    (function () {
      try {
        var d = document.documentElement;
        var mode = localStorage.getItem('themeMode') || 'auto';
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var isDark = (mode === 'dark') || (mode === 'auto' && prefersDark);
        d.classList.toggle('theme-dark', isDark);
        if (localStorage.getItem('hc') === '1') d.classList.add('hc'); else d.classList.remove('hc');
        if (localStorage.getItem('fs') === 'lg') d.classList.add('fs-lg'); else d.classList.remove('fs-lg');
        if (localStorage.getItem('rm') === '1') d.classList.add('rm'); else d.classList.remove('rm');
      } catch (e) {}
    })();
  </script>
  <style>
    .gg-admin-nav .navbar-brand span {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 42px;
      height: 42px;
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.14);
      color: #ffcf99;
      box-shadow: inset 0 0 10px rgba(255, 194, 122, 0.22);
    }
    .gg-admin-nav .navbar-brand {
      font-weight: 700;
      font-size: 1.6rem;
      letter-spacing: 0.015em;
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .gg-admin-nav .nav-link {
      position: relative;
    }
    .gg-admin-nav .pending-badge {
      position: absolute;
      top: -6px;
      right: -10px;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      background: #ef4444;
      color: #fff;
      font-size: 0.7rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 18px rgba(239, 68, 68, 0.4);
    }
    .gg-admin-nav .nav-link .gg-nav-underline {
      content: "";
      position: absolute;
      height: 3px;
      width: 0;
      background: linear-gradient(120deg, rgba(255, 214, 130, 0.95), rgba(255, 138, 76, 0.95));
      bottom: -6px;
      left: 0;
      border-radius: 999px;
      transition: width 0.25s ease;
    }
    .gg-admin-nav .nav-link:hover .gg-nav-underline,
    .gg-admin-nav .nav-link.active .gg-nav-underline {
      width: 100%;
    }
    .gg-admin-profile {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.35rem 0.75rem;
      border-radius: var(--gg-radius-sm, 10px);
      transition: background 0.2s ease, transform 0.2s ease;
    }
    .gg-admin-profile:hover {
      background: rgba(255, 255, 255, 0.12);
      transform: translateY(-1px);
    }
    .gg-admin-avatar {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      object-fit: cover;
      border: 2px solid rgba(255, 255, 255, 0.65);
      box-shadow: 0 10px 20px rgba(15, 23, 42, 0.35);
    }
    .gg-admin-meta {
      display: flex;
      flex-direction: column;
      font-size: 0.85rem;
      color: rgba(255, 255, 255, 0.8);
      line-height: 1.2;
    }
    .gg-admin-meta strong {
      font-weight: 600;
      color: #fff;
    }
    .theme-dark .gg-admin-profile:hover {
      background: rgba(148, 163, 184, 0.15);
    }
  </style>
</head>
<body class="gg-body d-flex flex-column">
  <nav class="navbar navbar-expand-lg gg-navbar gg-admin-nav sticky-top">
    <div class="container-fluid">
      <a class="navbar-brand" href="admin_home.php">
        <span><i class="fas fa-user-shield"></i></span>
        Admin Console
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="adminNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'admin_home.php' ? 'active' : ''; ?>" href="admin_home.php">
              <i class="fas fa-home me-1"></i>Dashboard
              <span class="gg-nav-underline"></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'admin_manage_recipes.php' ? 'active' : ''; ?>" href="admin_manage_recipes.php">
              <i class="fas fa-utensils me-1"></i>Manage Recipes
              <span class="gg-nav-underline"></span>
              <?php if ($pendingRecipes > 0): ?>
                <span class="pending-badge" title="<?php echo $pendingRecipes; ?> recipe(s) awaiting review"><?php echo $pendingRecipes > 99 ? '99+' : $pendingRecipes; ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'admin_manage_users.php' ? 'active' : ''; ?>" href="admin_manage_users.php">
              <i class="fas fa-users me-1"></i>Manage Users
              <span class="gg-nav-underline"></span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'admin_reports.php' ? 'active' : ''; ?>" href="admin_reports.php">
              <i class="fas fa-chart-line me-1"></i>Reports
              <span class="gg-nav-underline"></span>
            </a>
          </li>
        </ul>
        <div class="dropdown">
          <a href="#" role="button" id="adminProfileMenu" data-bs-toggle="dropdown" aria-expanded="false" class="gg-admin-profile">
            <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="gg-admin-avatar">
            <span class="gg-admin-meta">
              <strong><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator'); ?></strong>
              <small>System Admin</small>
            </span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminProfileMenu">
            <li><a class="dropdown-item text-danger" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </div>
  </nav>

  <main class="container mt-4 flex-grow-1" id="main-content">
