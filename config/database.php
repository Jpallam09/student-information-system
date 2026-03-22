<?php
$host = "localhost";
$user = "studentapp";  // DEDICATED USER - CREATE BELOW
$pass = "StudentAppSecure2024!";  // CHANGE THIS
$db   = "studentinfo";

// Try dedicated user first, fallback to root (XAMPP default)
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    error_log("Dedicated DB user failed, trying root fallback");
    $user = "root";
    $pass = "";
    $conn = mysqli_connect($host, $user, $pass, $db);
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error() . "\nCreate 'studentapp' user or fix creds.");
}

// RECOMMENDED: Create dedicated user in phpMyAdmin/MySQL:
// GRANT ALL ON studentinfo.* TO 'studentapp'@'localhost' IDENTIFIED BY 'StudentAppSecure2024!';
// FLUSH PRIVILEGES;
?>
