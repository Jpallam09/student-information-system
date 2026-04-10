<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/current_school_year.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id = trim($_POST['student_id']);
    $password   = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $student = $result->fetch_assoc();

        if (password_verify($password, $student['password'])) {
            $_SESSION['student_id']      = $student['id'];
            $_SESSION['selected_course'] = $student['course'];
            $_SESSION['year_level']      = $student['year_level'];
            $_SESSION['section']         = $student['section'];

            $active_year = getActiveSchoolYear($conn);
            $active_sem  = getActiveSemester($conn);
            $_SESSION['inactive_enrollment'] =
                ($student['school_year'] != $active_year || $student['semester'] != $active_sem);

            header("Location: " . BASE_URL . "studentsportal/students_dashboard.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Student not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Login | SRIS</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/student.css'); ?>">
</head>
<body class="student-login">
<div class="container">

    <!-- LEFT PANEL — white, title & features -->
    <div class="left-panel">
        <a href="<?php echo BASE_URL; ?>index.php" class="back-arrow">↩</a>
        <div class="left-accent"></div>
        <div class="left-content">
            <div class="eyebrow">
                <span class="eyebrow__line"></span>
                <span class="eyebrow__text">Isabela State University</span>
            </div>
            <h1 class="main-title">
                Student<br>
                Records &amp;<br>
                <span class="main-title__accent">Information</span><br>
                System
            </h1>
            <p class="subtitle">
                Streamlined academic records management for students and educators — fast, secure, and always up to date.
            </p>
            <div class="features">
                <div class="feature-pill"><span class="feature-pill__dot"></span>Secure Access</div>
                <div class="feature-pill"><span class="feature-pill__dot"></span>Real-time Data</div>
                <div class="feature-pill"><span class="feature-pill__dot"></span>Easy Records</div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL — dark, logo & form -->
    <div class="right-panel">

        <div class="deco-circle deco-circle--top"></div>
        <div class="deco-circle deco-circle--bottom"></div>

        <div class="logo-wrap">
            <img src="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>" alt="School Logo">
        </div>

        <div class="panel-divider"></div>
        <p class="portal-label">STUDENT PORTAL</p>

        <?php if (isset($error)) { ?>
            <p class="error-msg"><?php echo $error; ?></p>
        <?php } ?>

        <form action="" method="POST">
            <div class="input-group">
                <label>Student ID</label>
                <input type="text" name="student_id" placeholder="School ID (e.g., 25-0001)"
                       pattern="[0-9]{2}-[0-9]{4}" title="Format: 2 digits, dash, 4 digits (e.g., 25-0001)"
                       maxlength="7" required>
            </div>
            <div class="input-group password-group">
                <label>Password</label>
                <input type="password" name="password" id="password" required>
                <span class="toggle-password" id="togglePassword">
                    <i class="fas fa-eye" id="toggleIcon"></i>
                </span>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn">
                    <span class="btn__label">Log In</span>
                    <span class="btn__arrow">→</span>
                </button>
            </div>
        </form>

        <p class="register-text">
            Don't have an account?
            <a href="<?php echo BASE_URL; ?>Accesspage/register.php" class="register-link">Register Here</a>
        </p>
    </div>

</div>
<script>
document.getElementById("togglePassword").addEventListener("click", function () {
    const field = document.getElementById("password");
    const icon  = document.getElementById("toggleIcon");
    if (field.type === "password") {
        field.type = "text";
        icon.classList.replace("fa-eye", "fa-eye-slash");
    } else {
        field.type = "password";
        icon.classList.replace("fa-eye-slash", "fa-eye");
    }
});
</script>
</body>
</html>