<?php
// store_owner/store_owner_header.php (header partial)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/permissions.php';
set_role('store_owner');
require_role('store_owner');

$conn = new mysqli('localhost','root','','grocerygenie');

$default_profile_pic = '../assets/img/default_profile.png';
$profile_pic = $default_profile_pic;

$lowStockCount = 0;
$newOrdersCount = 0;
if (isset($_SESSION['store_owner_id'])) {
  @$conn->query("CREATE TABLE IF NOT EXISTS store_owner_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    item_id INT NOT NULL,
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_store_owner_notifications_owner FOREIGN KEY (owner_id) REFERENCES store_owners(owner_id) ON DELETE CASCADE,
    CONSTRAINT fk_store_owner_notifications_item FOREIGN KEY (item_id) REFERENCES groceryitem(item_id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
  $owner_id = (int)$_SESSION['store_owner_id'];

  $lowStockThreshold = 5;
  if ($stmtLowItems = $conn->prepare('SELECT item_id, item_name, quantity FROM groceryitem WHERE owner_id = ? AND quantity <= ?')) {
    $stmtLowItems->bind_param('ii', $owner_id, $lowStockThreshold);
    $stmtLowItems->execute();
    $resultLow = $stmtLowItems->get_result();
    $lowItems = $resultLow ? $resultLow->fetch_all(MYSQLI_ASSOC) : [];
    if ($resultLow) { $resultLow->free(); }
    $stmtLowItems->close();

    foreach ($lowItems as $lowItem) {
      $itemId = (int)$lowItem['item_id'];
      $notifMessage = sprintf('Low stock alert: %s has only %d unit(s) remaining.', $lowItem['item_name'], (int)$lowItem['quantity']);

      $notifId = null;
      $isRead = 0;
      $hasExisting = false;

      if ($stmtExisting = $conn->prepare('SELECT id, is_read FROM store_owner_notifications WHERE owner_id = ? AND item_id = ? ORDER BY created_at DESC LIMIT 1')) {
        $stmtExisting->bind_param('ii', $owner_id, $itemId);
        $stmtExisting->execute();
        $stmtExisting->bind_result($notifId, $isRead);
        $hasExisting = $stmtExisting->fetch();
        $stmtExisting->close();
      }

      if (!$hasExisting) {
        if ($stmtInsertLow = $conn->prepare('INSERT INTO store_owner_notifications (owner_id, item_id, message) VALUES (?, ?, ?)')) {
          $stmtInsertLow->bind_param('iis', $owner_id, $itemId, $notifMessage);
          $stmtInsertLow->execute();
          $stmtInsertLow->close();
        }
      }
    }
  }

  if ($stmtNotifCount = $conn->prepare('SELECT COUNT(*) FROM store_owner_notifications WHERE owner_id = ? AND is_read = 0')) {
    $stmtNotifCount->bind_param('i', $_SESSION['store_owner_id']);
    $stmtNotifCount->execute();
    $stmtNotifCount->bind_result($lowStockCount);
    $stmtNotifCount->fetch();
    $stmtNotifCount->close();
  }
  if ($stmtOrderCount = $conn->prepare('SELECT COUNT(DISTINCT o.order_id) FROM orders o JOIN order_items oi ON oi.order_id = o.order_id JOIN groceryitem gi ON gi.item_id = oi.item_id WHERE gi.owner_id = ? AND o.order_status IN (\'Pending\', \'Preparing\')')) {
    $stmtOrderCount->bind_param('i', $owner_id);
    $stmtOrderCount->execute();
    $stmtOrderCount->bind_result($newOrdersCount);
    $stmtOrderCount->fetch();
    $stmtOrderCount->close();
  }
  $check = $conn->query("SHOW COLUMNS FROM store_owners LIKE 'profile_pic'");
  if ($check && $check->num_rows > 0) {
    $res = $conn->query("SELECT profile_pic FROM store_owners WHERE owner_id='$owner_id' LIMIT 1");
    if ($res && $res->num_rows > 0) {
      $row = $res->fetch_assoc();
      if (!empty($row['profile_pic'])) { $profile_pic = 'uploads/' . $row['profile_pic']; }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>GroceryGenie - Store Owner</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/customer-theme.css">
  <style>
    .gg-orders-link {
      position: relative;
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
      <a class="navbar-brand" href="store_owner_dashboard.php">
        <span><i class="fas fa-store"></i></span>
        GroceryGenie
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav me-auto">
<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
          <li class="nav-item"><a class="nav-link <?php echo ($current_page === 'store_owner_dashboard.php') ? 'active' : ''; ?>" href="store_owner_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link <?php echo ($current_page === 'store_owner_sales_report.php') ? 'active' : ''; ?>" href="store_owner_sales_report.php"><i class="fas fa-chart-line"></i> Sales Report</a></li>
          <li class="nav-item"><a class="nav-link <?php echo ($current_page === 'store_owner_add_item.php') ? 'active' : ''; ?>" href="store_owner_add_item.php"><i class="fas fa-plus-circle"></i> Add Item</a></li>
          <li class="nav-item">
            <a class="nav-link gg-orders-link <?php echo ($current_page === 'store_owner_orders.php') ? 'active' : ''; ?>" href="store_owner_orders.php">
              <i class="fas fa-shopping-cart"></i> Orders
              <?php if ($newOrdersCount > 0): ?>
                <span class="gg-owner-notification-badge ms-1"><?php echo $newOrdersCount; ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link position-relative <?php echo ($current_page === 'store_owner_notifications.php') ? 'active' : ''; ?>" href="store_owner_notifications.php">
              <i class="fas fa-bell"></i> Alerts
              <?php if ($lowStockCount > 0): ?>
                <span class="gg-notification-badge"><?php echo $lowStockCount; ?></span>
              <?php endif; ?>
            </a>
          </li>
        </ul>
        <form class="d-flex align-items-center me-3" method="GET" action="store_owner_search.php" onsubmit="return window.ggOwnerSearch && window.ggOwnerSearch.validate(this);">
          <input class="form-control form-control-sm rounded-pill px-3" type="search" name="q" placeholder="Search inventory..." aria-label="Search">
        </form>
        <div class="dropdown">
          <a href="#" role="button" id="storeOwnerMenu" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="gg-profile-thumb">
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="storeOwnerMenu">
            <?php if (isset($_SESSION['store_owner_id'])) { ?>
              <li><a class="dropdown-item" href="store_owner_profile.php"><i class="fas fa-user"></i> Profile</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="store_owner_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php } else { ?>
              <li><a class="dropdown-item" href="store_owner_login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
            <?php } ?>
          </ul>
        </div>
      </div>
    </div>
  </nav>

  <script>
    window.ggOwnerSearch = {
      validate(form) {
        var input = form.querySelector('input[name="q"]');
        if (!input) { return true; }
        var value = (input.value || '').trim();
        if (!value) {
          input.classList.add('is-invalid');
          input.focus();
          setTimeout(function(){ input.classList.remove('is-invalid'); }, 1500);
          return false;
        }
        input.value = value;
        return true;
      }
    };
  </script>
  <?php if (isset($_SESSION['store_owner_id'])): ?>
  <script>
    (function () {
      const ordersLink = document.querySelector('.nav-link[href="store_owner_orders.php"]');
      const alertsLink = document.querySelector('.nav-link[href="store_owner_notifications.php"]');
      if (!ordersLink || !alertsLink) return;

      const ensureBadge = (link, className) => {
        let badge = link.querySelector('.' + className);
        if (!badge) {
          badge = document.createElement('span');
          badge.className = className + ' d-none';
          link.appendChild(badge);
        }
        return badge;
      };

      const ordersBadge = ensureBadge(ordersLink, 'gg-owner-notification-badge');
      const alertsBadge = ensureBadge(alertsLink, 'gg-notification-badge');

      const updateBadge = (badge, value) => {
        if (!badge) return;
        if (value > 0) {
          badge.textContent = value;
          badge.classList.remove('d-none');
        } else {
          badge.textContent = '';
          badge.classList.add('d-none');
        }
      };

      const pollCounts = async () => {
        try {
          const response = await fetch('owner_orders_count.php', { cache: 'no-store' });
          if (!response.ok) return;
          const data = await response.json();
          updateBadge(ordersBadge, Number(data.orders) || 0);
          updateBadge(alertsBadge, Number(data.alerts) || 0);
        } catch (e) {}
      };

      pollCounts();
      setInterval(pollCounts, 15000);
    })();
  </script>
  <?php endif; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const profileToggle = document.getElementById('storeOwnerMenu');
      const profileMenu = document.querySelector('[aria-labelledby="storeOwnerMenu"]');
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
          setupBootstrapDropdown();
        });

        setTimeout(() => {
          setupBootstrapDropdown();
        }, 500);
      }
    });
  </script>

  <!-- Main Content Wrapper -->
  <main class="container mt-4 flex-grow-1" id="main-content">
