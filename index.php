<?php require_once 'config/paths.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student-Teacher Portal | SRIS</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('css/index.css'); ?>">
</head>
<body>

<div class="container">

    <!-- LEFT PANEL — now WHITE with title/features -->
    <div class="left-panel">

        <!-- BACKGROUND ACCENT -->
        <div class="left-accent"></div>

        <div class="left-content">

            <!-- EYEBROW -->
            <div class="eyebrow">
                <span class="eyebrow__line"></span>
                <span class="eyebrow__text">Isabela State University</span>
            </div>

            <!-- MAIN TITLE -->
            <h1 class="main-title">
                Student<br>
                Records &amp;<br>
                <span class="main-title__accent">Information</span><br>
                System
            </h1>

            <!-- SUBTITLE -->
            <p class="subtitle">
                Streamlined academic records management for students and educators — fast, secure, and always up to date.
            </p>

            <!-- FEATURE PILLS -->
            <div class="features">
                <div class="feature-pill">
                    <span class="feature-pill__dot"></span>
                    Secure Access
                </div>
                <div class="feature-pill">
                    <span class="feature-pill__dot"></span>
                    Real-time Data
                </div>
                <div class="feature-pill">
                    <span class="feature-pill__dot"></span>
                    Easy Records
                </div>
            </div>

        </div>

    </div>

    <!-- RIGHT PANEL — now DARK with logo/buttons -->
    <div class="right-panel">

        <!-- DECORATIVE CIRCLES -->
        <div class="deco-circle deco-circle--top"></div>
        <div class="deco-circle deco-circle--bottom"></div>

        <!-- SCHOOL LOGO -->
        <div class="logo-wrap">
            <img src="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>"
                 alt="School Logo">
        </div>

        <!-- DIVIDER -->
        <div class="panel-divider"></div>

        <!-- PORTAL LABEL -->
        <p class="portal-label">Select Your Portal</p>

        <!-- BUTTONS -->
        <div class="btn-group">
            <a href="<?php echo BASE_URL; ?>Accesspage/student_login.php" class="btn btn--student">
                <span class="btn__label">Student</span>
                <span class="btn__arrow">→</span>
            </a>

            <a href="<?php echo BASE_URL; ?>Accesspage/teacher_login.php" class="btn btn--teacher">
                <span class="btn__label">Teacher</span>
                <span class="btn__arrow">→</span>
            </a>
        </div>

    </div>

</div>

</body>
</html>