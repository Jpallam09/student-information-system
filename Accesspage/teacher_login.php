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
    <title>Teacher Login</title>
     <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="<?php echo asset('css/student.css'); ?>">

</head>
<body>

<div class="container">

    <div class="left-panel">
   <a href="<?php echo BASE_URL; ?>index.php" class="back-arrow">↩</a>
        <div class="icon">👤</div>
        <h2>TEACHER LOGIN</h2>
        <p>Please enter your details</p>

        <!-- Display error -->
        <?php if(isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>

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
            <button type="submit" class="btn">Log In</button>
        </form>

        <p style="margin-top:10px;">Don't have an account? <a href="<?php echo BASE_URL; ?>Accesspage/teachers_register.php">Register Here</a></p>
    </div>

    <div class="right-panel">
        <h1>Welcome to<br>Student<br>Records<br>and<br>Information<br>System</h1>
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