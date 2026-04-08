<?php
/**
 * ============================================================
 * STUDENT LOGIN MODULE
 * ============================================================
 * Responsibilities:
 *  - Authenticate student credentials
 *  - Verify password securely
 *  - Start student session
 *  - Redirect to student dashboard
 *
 * Security:
 *  - Uses prepared statements (prevents SQL injection)
 *  - Uses password_verify() for hashed password validation
 * ============================================================
 */

session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/current_school_year.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ================================
    // 1. Collect input safely
    // ================================
    $student_id = trim($_POST['student_id']);
    $password   = $_POST['password'];

    // ================================
    // 2. Prepare secure query
    // ================================
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // ================================
    // 3. Check if student exists
    // ================================
    if ($result->num_rows === 1) {

        $student = $result->fetch_assoc();

        // ================================
        // 4. Verify hashed password
        // ================================
        if (password_verify($password, $student['password'])) {

            // ================================
            // 5. Set secure session variables
            // ================================
            $_SESSION['student_id']     = $student['id'];
            $_SESSION['selected_course']= $student['course'];
            $_SESSION['year_level']     = $student['year_level'];
            $_SESSION['section']        = $student['section'];
            
            // NEW: Check inactive enrollment status
            $active_year = getActiveSchoolYear($conn);
            $active_sem = getActiveSemester($conn);
            $_SESSION['inactive_enrollment'] = 
                ($student['school_year'] != $active_year || $student['semester'] != $active_sem);

            // Redirect to student dashboard
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

<!-- HTML login form (unchanged) -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Portal Login</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
<link rel="stylesheet" href="<?php echo asset('css/student.css'); ?>">
</head>
<body>
<div class="container">

    <div class="left-panel">
  <a href="<?php echo BASE_URL; ?>index.php" class="back-arrow">↩</a>
        <div class="icon">👤</div>
        <h2>STUDENT PORTAL</h2>
        <p>Please enter your details</p>

        <!-- Display error if exists -->
        <?php if(isset($error)) { echo "<p style='color:red;'>$error</p>"; } ?>

        <form action="" method="POST">
            <div class="input-group">
                <label>Student ID</label>
                <input type="text" name="student_id" placeholder="School ID (e.g., 25-0001)" pattern="[0-9]{2}-[0-9]{4}" title="Format: 2 digits, dash, 4 digits (e.g., 25-0001)" maxlength="7" required>
            </div>

            <div class="input-group password-group">
                <label>Password</label>
                <input type="password" name="password" id="password" required>
                <span class="toggle-password" id="togglePassword">👁</span>
            </div>

            <button type="submit" class="btn">Log In</button>

           <p style="margin-top:10px;">Don't have an account?  <a href="<?php echo BASE_URL; ?>Accesspage/register.php">Register Here</a></p>
        </form>
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
        password.type = "text"; toggle.textContent = "🙈"; 
    } else {
        password.type = "password"; toggle.textContent = "👁";
    }
});
</script>
</body>
</html>