<?php
/**
 * ============================================================
 * TEACHER REGISTRATION MODULE
 * ============================================================
 * Responsibilities:
 *  - Display teacher registration form
 *  - Validate input
 *  - Prevent duplicate Teacher IDs
 *  - Securely hash password
 *  - Insert new teacher into database
 *  - Redirect after successful registration
 *
 * Security Measures:
 *  - Uses prepared statements (prevents SQL injection)
 *  - Uses password_hash() for secure password storage
 * ============================================================
 */

session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ===============================
       1. Collect Form Data
       =============================== */
    $teacher_id       = trim($_POST['teacher_id']);
    $first_name       = trim($_POST['first_name']);
    $middle_name      = trim($_POST['middle_name']);
    $last_name        = trim($_POST['last_name']);
    $suffix           = trim($_POST['suffix']);
    $dob              = $_POST['dob'];
    $gender           = $_POST['gender'];
    $civil_status     = $_POST['civil_status'];
    $nationality      = trim($_POST['nationality']);
    $teacher_type     = trim($_POST['teacher_type']);
    $course           = trim($_POST['course']);
    
    // Get year levels and sections as arrays, then implode to comma-separated string
    $year_levels      = isset($_POST['year_levels']) ? implode(',', $_POST['year_levels']) : '';
    $sections         = isset($_POST['sections']) ? implode(',', $_POST['sections']) : '';

    $email            = trim($_POST['email']);
    $mobile           = trim($_POST['mobile']);
    $home_address     = trim($_POST['home_address']);
    $emergency_person = trim($_POST['emergency_person']);
    $emergency_number = trim($_POST['emergency_number']);

    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    /* ===============================
       2. Validate Password Match
       =============================== */
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match');</script>";
        exit();
    }

    /* ===============================
       3. Check for duplicate Teacher ID
       =============================== */
    $checkStmt = $conn->prepare("SELECT id FROM teachers WHERE teacher_id = ?");
    $checkStmt->bind_param("s", $teacher_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        echo "<script>alert('Teacher ID already exists');</script>";
        exit();
    }

    /* ===============================
       4. Hash password securely
       =============================== */
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    /* ===============================
       5. Insert New Teacher (including year_levels and sections)
       =============================== */
    $insertStmt = $conn->prepare("
        INSERT INTO teachers (
            teacher_id, first_name, middle_name, last_name, suffix,
            dob, gender, civil_status, nationality, teacher_type, course,
            year_levels, sections,
            email, mobile, home_address, emergency_person, emergency_number,
            password
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $insertStmt->bind_param(
        "sssssssssssssssssss",
        $teacher_id, $first_name, $middle_name, $last_name, $suffix,
        $dob, $gender, $civil_status, $nationality, $teacher_type, $course,
        $year_levels, $sections,
        $email, $mobile, $home_address, $emergency_person, $emergency_number,
        $hashed_password
    );

    if ($insertStmt->execute()) {
        // Redirect to teacher login after registration
header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
        exit();
    } else {
        echo "Database Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Registration</title>
     <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
<link rel="stylesheet" href="<?php echo asset('css/register.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<div class="container">

    <!-- LEFT PANEL: Registration Form -->
    <div class="left-panel">

        <!-- BACK BUTTON -->
<a href="<?php echo BASE_URL; ?>Accesspage/teacher_login.php" class="back-arrow">↩</a>

        <div class="icon">📝</div>
        <h2>Teacher Registration</h2>
        <p>Create your account to access the portal</p>

        <form action="" method="POST" class="register-form">

            <!-- Basic Personal Information -->
            <fieldset>
                <legend>📝 Basic Personal Information</legend>
                <input type="text" name="teacher_id" placeholder="Teacher ID" required>
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="middle_name" placeholder="Middle Name">
                <input type="text" name="last_name" placeholder="Last Name" required>
                <input type="text" name="suffix" placeholder="Suffix">
                <label for="dob">Date of Birth</label>
                <input type="date" name="dob" required onchange="calculateAge(this)">

<parameter name="path">
                 <input type="number" name="age" placeholder="Age" required>

                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>

                <select name="civil_status" required>
                    <option value="">Select Civil Status</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Separated">Separated</option>
                    <option value="Divorced">Divorced</option>
                </select>

                <input type="text" name="nationality" placeholder="Nationality" required>
                <input type="text" name="teacher_type" placeholder="Teacher Type (e.g., Instructor 1)" required>

                <!-- Courses Dropdown -->
                <select name="course" required>
                    <option value="">Select Course</option>
                    <option value="BSIT">BSIT</option>
                    <option value="BSED">BSED</option>
                    <option value="BAT">BAT</option>
                    <option value="BTVTED">BTVTED</option>
                </select>
            </fieldset>

            <!-- Year Level & Section Assignment -->
            <fieldset>
                <legend>📚 Assigned Year Levels & Sections</legend>
                <p style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">Select the year levels and sections this teacher will handle:</p>
                
                <label><strong>Year Levels:</strong></label>
                <div class="checkbox-group" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="year_levels[]" value="1st Year"> 1st Year
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="year_levels[]" value="2nd Year"> 2nd Year
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="year_levels[]" value="3rd Year"> 3rd Year
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="year_levels[]" value="4th Year"> 4th Year
                    </label>
                </div>
                
                <label><strong>Sections:</strong></label>
                <div class="checkbox-group" style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="sections[]" value="A"> Section A
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="sections[]" value="B"> Section B
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="sections[]" value="C"> Section C
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="sections[]" value="D"> Section D
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="sections[]" value="E"> Section E
                    </label>
                </div>
            </fieldset>

            <!-- Contact Information -->
            <fieldset>
                <legend>📞 Contact Information</legend>
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="text" name="mobile" placeholder="Mobile Number" required>
                <textarea name="home_address" placeholder="Home Address" required></textarea>
                <input type="text" name="emergency_person" placeholder="Emergency Contact Person" required>
                <input type="text" name="emergency_number" placeholder="Emergency Contact Number" required>
            </fieldset>

            <!-- Account Credentials -->
            <fieldset>
                <legend>🔐 Account Credentials</legend>
                <input type="password" name="password" placeholder="Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </fieldset>

            <button type="submit" class="btn register-btn">REGISTER</button>

        </form>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
        <h1>Welcome to<br>Teacher<br>Information<br>Management</h1>
    </div>

    <script>
    function calculateAge(dobInput) {
        const dob = new Date(dobInput.value);
        if (isNaN(dob.getTime())) return;
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        const ageField = dobInput.parentNode.querySelector('input[name="age"]');
        if (ageField) {
            ageField.value = Math.max(0, age);
        }
    }
    </script>

</div>

</body>
</xai:function_call {

<xai:function_call name="edit_file">
<parameter name="path">
</html>