<?php require_once 'config/paths.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<title>Student-Teacher Portal</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/index.css'); ?>">
</head>
<body>

<div class="container">

    <!-- LEFT PANEL -->
    <div class="left-panel">

        <!-- ICON -->
        <img src="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>" 
             alt="School Logo" 
             style="width: 150px; display: block; margin: 100px auto; border-radius: 10px; animation: float 3s ease-in-out infinite;">

        <!-- BUTTONS -->
        <a href="<?php echo BASE_URL; ?>Accesspage/student_login.php" class="btn">STUDENT</a>

        <a href="<?php echo BASE_URL; ?>Accesspage/teacher_login.php" class="btn">TEACHER</a>

    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
        <h1>
            STUDENT<br>
            RECORDS<br>
            AND<br>
            INFORMATION<br>
            SYSTEM
        </h1>
    </div>

</div>

</body>
</html>