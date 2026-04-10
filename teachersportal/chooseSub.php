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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body>
<div class="container">
    <a href="<?= BASE_URL ?>Accesspage/teacher_login.php" class="back-arrow">↩</a>
    <div class="right-panel">
        <div class="right-content">
            <div class="eyebrow">
                <span class="eyebrow__line"></span>
                <span class="eyebrow__text">Administrator</span>
            </div>
            <h1 class="main-title">
                Choose Your<br>
                <span class="main-title__accent">Program</span><br>
                to Continue
            </h1>
            <p class="subtitle">
                Select the academic program you manage to access your personalized admin dashboard and tools.
            </p>
            <div class="features">
                <div class="feature-pill">
                    <span class="feature-pill__dot"></span>
                    Admin Access
                </div>
                <div class="feature-pill">
                    <span class="feature-pill__dot"></span>
                    Program Tools
                </div>
                <div class="feature-pill">
                    <span class="feature-pill__dot"></span>
                    Secure Setup
                </div>
            </div>
        </div>
    </div>
    <div class="left-panel">
       <div class="logo-wrap">
           <img src="<?= asset('images/622685015_925666030131412_6886851389087569993_n.jpg') ?>" alt="School Logo">
       </div>
        <div class="panel-divider"></div>
        <p class="portal-label">Select Your Program</p>
        <div class="btn-group">
            <a href="<?= BASE_URL ?>teachersportal/chooseSub.php?course=BSIT" class="btn btn--primary">
                <span class="btn__label">BSIT</span>
                <span class="btn__arrow">→</span>
            </a>
            <a href="<?= BASE_URL ?>teachersportal/chooseSub.php?course=BSED" class="btn btn--primary">
                <span class="btn__label">BSED</span>
                <span class="btn__arrow">→</span>
            </a>
            <a href="<?= BASE_URL ?>teachersportal/chooseSub.php?course=BAT" class="btn btn--primary">
                <span class="btn__label">BAT</span>
                <span class="btn__arrow">→</span>
            </a>
            <a href="<?= BASE_URL ?>teachersportal/chooseSub.php?course=BTVTED" class="btn btn--primary">
                <span class="btn__label">BTVTED</span>
                <span class="btn__arrow">→</span>
            </a>
        </div>
    </div>
</div>
</body>
</html>
