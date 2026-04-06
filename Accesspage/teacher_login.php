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
                <input type="text" name="teacher_id" required>
            </div>

            <div class="input-group password-group">
                <label>Password</label>
                <input type="password" name="password" id="password" required>
                <span class="toggle-password" id="togglePassword">👁</span>
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
const toggle = document.getElementById("togglePassword");
const password = document.getElementById("password");

toggle.addEventListener("click", function () {
    if (password.type === "password") {
        password.type = "text";
        toggle.textContent = "🙈"; 
    } else {
        password.type = "password";
        toggle.textContent = "👁";
    }
});
</script>

</body>
</html>