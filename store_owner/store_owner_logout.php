<?php
// store_owner/store_owner_logout.php
session_start();

// ✅ Clear all session variables
$_SESSION = [];

// ✅ Destroy the session
session_destroy();

// ✅ Redirect to login page
header("Location: store_owner_login.php");
exit();
