<?php
/**
 * ============================================================
 * TEACHER LOGIN MODULE - UPDATED
 * ============================================================
 * Responsibilities:
 *  - Authenticate teacher credentials
 *  - Verify password securely
 *  - Start teacher session
 *  - Store teacher course in session
 *  - Redirect to dashboard or chooseSub for admin
 *
 * Security:
 *  - Uses prepared statements (prevents SQL injection)
 *  - Uses password_verify() for hashed password validation
 * ============================================================
 */

session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ================================ 1. Collect Input ================================
    $teacher_id = trim($_POST['teacher_id']);
    $password   = $_POST['password'];

    // ================================ 2. Prepare Secure Query ================================
    $stmt = $conn->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
    $stmt->bind_param("s", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // ================================ 3. Validate Teacher Exists ================================
    if ($result->num_rows === 1) {

        $teacher = $result->fetch_assoc();

        // ================================ 4. Verify Password ================================
        if (password_verify($password, $teacher['password'])) {

            // ================================ 5. Set Secure Session ================================
            $_SESSION['teacher_id']     = $teacher['id'];
            $_SESSION['teacher_course']  = $teacher['course']; // ✅ store course
            $_SESSION['teacher_name']    = $teacher['first_name'] . ' ' . $teacher['last_name'];
            $_SESSION['teacher_type']    = $teacher['teacher_type']; // ✅ store type
            // Store assigned year levels and sections in session
            $_SESSION['teacher_year_levels'] = $teacher['year_levels'] ?? '';
            $_SESSION['teacher_sections']    = $teacher['sections'] ?? '';

// ================================ 6. Redirect Based on Teacher Type ================================
            if($teacher['teacher_type'] === "Administrator"){
                // Admin goes to choose program first
header("Location: " . BASE_URL . "teachersportal/chooseSub.php");
                exit();
            } else {
                // Regular teacher goes directly to dashboard with fixed course
header("Location: " . BASE_URL . "teachersportal/dashboard.php");
                exit();
            }

        } else {
            $error = "Invalid password.";
        }

    } else {
        $error = "Teacher ID not found. Please register first.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Portal Login | SRIS</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/student.css'); ?>">
</head>
<body class="teacher-login">
<div class="container">

    <div class="left-panel">
        <a href="<?php echo BASE_URL; ?>index.php" class="back-arrow">↩</a>
        
        <!-- Decorative circles -->
        <div class="deco-circle deco-circle--top"></div>
        <div class="deco-circle deco-circle--bottom"></div>
        
        <!-- Logo -->
        <div class="logo-wrap">
            <img src="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>" alt="School Logo">
        </div>
        
        <!-- Divider & Label -->
        <div class="panel-divider"></div>
        <p class="portal-label">TEACHER PORTAL</p>

        <!-- Error -->
        <?php if(isset($error)) { ?><p style="color: #fee2e2; background: rgba(236, 15, 64, 0.1); padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;"><?php echo $error; ?></p><?php } ?>

        <!-- Form -->
        <form action="" method="POST">
            <div class="input-group">
                <label>Teacher ID</label>
                <input type="text" name="teacher_id" placeholder="Teacher ID (e.g., 25-0001)" pattern="[0-9]{2}-[0-9]{4}" title="Format: 2 digits, dash, 4 digits (e.g., 25-0001)" maxlength="7" required>
            </div>

            <div class="input-group password-group">
                <label>Password</label>
                <input type="password" name="password" id="password" required>
                <span class="toggle-password" id="togglePassword"><i class="fas fa-eye" id="toggleIcon"></i></span>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn">
                    <span class="btn__label">Log In</span>
                    <span class="btn__arrow">→</span>
                </button>
            </div>
        </form>

        <p style="margin-top: 20px; color: rgba(255,255,255,0.8); text-align: center;">
            Don't have an account? <a href="<?php echo BASE_URL; ?>Accesspage/teachers_register.php" class="register-link">Register Here</a>
        </p>
    </div>

    <div class="right-panel">
        <div class="right-accent"></div>
        <div class="right-content">
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

</div>

<script>

// Toggle password visibility with icon change
function togglePassword(fieldId, iconId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    if (field.type === "password") {
        field.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        field.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

document.getElementById("togglePassword").addEventListener("click", function () {
    togglePassword("password", "toggleIcon");
});

</script>

</body>
</html>