<?php
require_once dirname(__DIR__) . '/config/paths.php';
session_start();

// ✅ If teacher is not logged in, redirect to login
if(!isset($_SESSION['teacher_id'])){
   header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

// ✅ ADMIN-ONLY ACCESS for chooseSub.php
if($_SESSION['teacher_type'] !== "Administrator"){
 header("Location: " . BASE_URL . "teachersportal/dashboard.php");
exit();
}

// Handle course selection
$allowed_courses = ['BSIT', 'BSED', 'BAT', 'BTVTED'];

if(isset($_GET['course']) && in_array($_GET['course'], $allowed_courses)){
    $_SESSION['teacher_course'] = $_GET['course'];
    header("Location: " . BASE_URL . "teachersportal/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Choose Program</title>
 <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body>
<div class="container">
    <div class="left-panel">
        <a href="<?= BASE_URL ?>Accesspage/teacher_login.php" class="back-arrow">↩</a>
       <img src="<?= asset('images/622685015_925666030131412_6886851389087569993_n.jpg') ?>" alt="School Logo" style="width: 100px; display: block; margin: 80px auto 15px auto; border-radius: 5px; animation: float 3s ease-in-out infinite;">
        <h2>Select Your Program</h2>
        <a href="<?= BASE_URL ?>teachersportal/chooseSub.php?course=BSIT" class="btn">BSIT</a>
        <a href="<?= BASE_URL ?>teachersportal/chooseSub.php?course=BSED" class="btn">BSED</a>
        <a href="<?= BASE_URL ?>teachersportal/chooseSub.php?course=BAT" class="btn">BAT</a>
        <a href="<?= BASE_URL ?>teachersportal/chooseSub.php?course=BTVTED" class="btn">BTVTED</a>
    </div>
    <div class="right-panel">
        <h1>Choose Your Program<br>to Continue</h1>
    </div>
</div>
</body>
</html>