<?php
session_start();

// ✅ If teacher is not logged in, redirect to login
if(!isset($_SESSION['teacher_id'])){
    header("Location: ../Accesspage/teacher_login.php");
    exit();
}

// ✅ When course is clicked
if(isset($_GET['course'])){
    // 👈 Set the session variable dashboard.php expects
    $_SESSION['teacher_course'] = $_GET['course']; 
    header("Location: ../teachersportal/dashboard.php"); // Go to dashboard for that course
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Choose Program</title>
<link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="container">
    <div class="left-panel">
        <a href="../Accesspage/teacher_login.php" class="back-arrow">↩</a>
         <img src="../images/622685015_925666030131412_6886851389087569993_n.jpg" alt="School Logo" style="width: 100px; display: block; margin: 80px auto 15px auto; border-radius: 5px; animation: float 3s ease-in-out infinite;">
        <h2>Select Your Program</h2>
        <a href="chooseSub.php?course=BSIT" class="btn">BSIT</a>
        <a href="chooseSub.php?course=BSED" class="btn">BSED</a>
        <a href="chooseSub.php?course=BAT" class="btn">BAT</a>
        <a href="chooseSub.php?course=BTVTED" class="btn">BTVTED</a>
    </div>
    <div class="right-panel">
        <h1>Choose Your Program<br>to Continue</h1>
    </div>
</div>
</body>
</html>