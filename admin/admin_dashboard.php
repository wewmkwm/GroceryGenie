<?php
// admin/admin_dashboard.php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="alert alert-success shadow-sm">
    👋 Welcome, <b><?php echo $_SESSION['admin_name']; ?></b>
  </div>
  <a href="admin_manage_storeowners.php" class="btn btn-primary">Manage Store Owners</a>
  <a href="admin_logout.php" class="btn btn-danger">Logout</a>
</div>

</body>
</html>
