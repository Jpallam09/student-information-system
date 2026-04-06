<?php
/**
 * ============================================================
 * DATABASE CONFIG (PRODUCTION-READY)
 * ============================================================
 */

/**
 * Load credentials from environment (BEST PRACTICE)
 * You can also hardcode for local only
 */
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'studentinfo';

/**
 * ============================================================
 * CREATE CONNECTION
 * ============================================================
 */
$conn = mysqli_connect($host, $user, $pass, $db);

/**
 * ============================================================
 * ERROR HANDLING
 * ============================================================
 */
if (!$conn) {
    // Log error instead of exposing details to users
    error_log("Database Connection Failed: " . mysqli_connect_error());

    // Show generic message
    die("Database connection error. Please contact the administrator.");
}

/**
 * ============================================================
 * SET CHARACTER SET (IMPORTANT)
 * ============================================================
 */
if (!mysqli_set_charset($conn, "utf8mb4")) {
    error_log("Charset Error: " . mysqli_error($conn));
}