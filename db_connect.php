<?php
// db_connect.php — centralized mysqli connection (utf8mb4)

if (!isset($conn) || !($conn instanceof mysqli)) {
    $DB_HOST = 'localhost';
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_NAME = 'grocerygenie';

    // Optional: enable mysqli exceptions for easier debugging
    // mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        // Avoid leaking internal details in production
        die('Database connection failed. Please try again later.');
    }

    // Ensure proper character set
    $conn->set_charset('utf8mb4');
}
?>
