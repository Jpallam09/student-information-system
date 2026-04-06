<?php
session_start();

// Use absolute project path (BEST)
require_once dirname(__DIR__) . '/config/paths.php';

// Clear session
$_SESSION = [];
session_destroy();

// Delete cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect using BASE_URL
header("Location: " . BASE_URL . "Accesspage/student_login.php");
exit();