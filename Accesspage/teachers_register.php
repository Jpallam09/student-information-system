<?php
/**
 * ============================================================
 * TEACHER REGISTRATION MODULE
 * ============================================================
 */

session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $teacher_id       = trim($_POST['teacher_id']);
    $first_name       = trim($_POST['first_name']);
    $middle_name      = trim($_POST['middle_name']);
    $last_name        = trim($_POST['last_name']);
    $suffix           = trim($_POST['suffix']);
    $dob              = $_POST['dob'];
    $age              = $_POST['age'];
    $gender           = $_POST['gender'];
    $civil_status     = $_POST['civil_status'];
    $nationality      = trim($_POST['nationality']);
    $teacher_type     = trim($_POST['teacher_type']);
    $course           = trim($_POST['course']);
    
    $year_levels      = isset($_POST['year_levels']) ? implode(',', $_POST['year_levels']) : '';
    $sections         = isset($_POST['sections']) ? implode(',', $_POST['sections']) : '';

    $email            = trim($_POST['email']);
    $mobile           = trim($_POST['mobile']);
    $home_address     = trim($_POST['home_address']);
    $emergency_person = trim($_POST['emergency_person']);
    $emergency_number = trim($_POST['emergency_number']);

    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error_msg = "Passwords do not match.";
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM teachers WHERE teacher_id = ?");
        $checkStmt->bind_param("s", $teacher_id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error_msg = "Teacher ID already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $conn->prepare("
                INSERT INTO teachers (
                    teacher_id, first_name, middle_name, last_name, suffix,
                    dob, age, gender, civil_status, nationality, teacher_type, course,
                    year_levels, sections,
                    email, mobile, home_address, emergency_person, emergency_number,
                    password
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insertStmt->bind_param(
                "sssssisssssssssssss",
                $teacher_id, $first_name, $middle_name, $last_name, $suffix,
                $dob, $age, $gender, $civil_status, $nationality, $teacher_type, $course,
                $year_levels, $sections,
                $email, $mobile, $home_address, $emergency_person, $emergency_number,
                $hashed_password
            );

            if ($insertStmt->execute()) {
                header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
                exit();
            } else {
                $error_msg = "Database Error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration | Teacher Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/register.css') ?>">
    <link rel="icon" href="<?= asset('images/622685015_925666030131412_6886851389087569993_n.jpg') ?>">
</head>
<body>
<div class="container">
    <div class="right-panel">
        <a href="<?= BASE_URL ?>Accesspage/teacher_login.php" class="back-arrow" title="Back">↩</a>
        <h1>Teacher<br>Management<br>System</h1>
    </div>
    <div class="left-panel">
        <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
        <h2>Teacher Registration</h2>
        <p>Complete your profile to access the teacher portal</p>

        <?php if (isset($error_msg)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="register-form">
            <fieldset>
                <legend><i class="fas fa-user"></i> Basic Personal Information</legend>
                <input type="text" name="teacher_id" placeholder="Teacher ID (e.g., 26-T001)" pattern="[0-9]{2}-[A-Z]?[0-9]{3,4}" title="Format: YY-DeptNum (e.g., 26-T001)" maxlength="8" required>
                <div class="form-row">
                    <input type="text" name="first_name" placeholder="First Name *" required oninput="this.value = this.value.toUpperCase()">
                    <input type="text" name="middle_name" placeholder="Middle Name" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div class="form-row">
                    <input type="text" name="last_name" placeholder="Last Name *" required oninput="this.value = this.value.toUpperCase()">
                    <input type="text" name="suffix" placeholder="Suffix (Jr., Sr.)">
                </div>
                <div class="form-row">
                    <div>
                        <label>Date of Birth</label>
                        <input type="date" name="dob" required onchange="calculateAge(this)">
                    </div>
                    <input type="number" name="age" placeholder="Age *" required>
                </div>
                <div class="form-row">
                    <select name="gender" required><option value="">Gender *</option><option value="Male">Male</option><option value="Female">Female</option></select>
                    <select name="civil_status" required><option value="">Civil Status *</option><option value="Single">Single</option><option value="Married">Married</option><option value="Widowed">Widowed</option></select>
                </div>
                <div class="form-row">
                    <input type="text" name="nationality" placeholder="Nationality *" required>
                    <input type="text" name="teacher_type" placeholder="Type (e.g., Instructor I)" required>
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-graduation-cap"></i> Academic Assignment</legend>
                <select name="course" required><option value="">Primary Course *</option><option value="BSIT">BSIT</option><option value="BSED">BSED</option><option value="BAT">BAT</option><option value="BTVTED">BTVTED</option></select>
                
                <div class="checkbox-group">
                    <label><strong>Year Levels:</strong></label>
                    <label><input type="checkbox" name="year_levels[]" value="1st Year"> 1st Year</label>
                    <label><input type="checkbox" name="year_levels[]" value="2nd Year"> 2nd Year</label>
                    <label><input type="checkbox" name="year_levels[]" value="3rd Year"> 3rd Year</label>
                    <label><input type="checkbox" name="year_levels[]" value="4th Year"> 4th Year</label>
                </div>
                <div class="checkbox-group">
                    <label><strong>Sections:</strong></label>
                    <label><input type="checkbox" name="sections[]" value="A"> A</label>
                    <label><input type="checkbox" name="sections[]" value="B"> B</label>
                    <label><input type="checkbox" name="sections[]" value="C"> C</label>
                    <label><input type="checkbox" name="sections[]" value="D"> D</label>
                    <label><input type="checkbox" name="sections[]" value="E"> E</label>
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-address-book"></i> Contact Information</legend>
                <div class="form-row">
                    <input type="email" name="email" placeholder="Email Address *" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}">
                    <input type="text" name="mobile" placeholder="Mobile (09171234567)" maxlength="11" pattern="\d{11}" required oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                </div>
                <textarea name="home_address" placeholder="Complete Home Address *" required></textarea>
                <div class="form-row">
                    <input type="text" name="emergency_person" placeholder="Emergency Contact Person *" required>
                    <input type="text" name="emergency_number" placeholder="Emergency Number (09171234567)" maxlength="11" pattern="\d{11}" required oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-key"></i> Account Credentials</legend>
                <div class="password-wrapper">
                    <input type="password" name="password" id="t_password" placeholder="Password *" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePassword('t_password', this)"></i>
                </div>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="t_confirm_password" placeholder="Confirm Password *" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePassword('t_confirm_password', this)"></i>
                </div>
            </fieldset>

            <button type="submit" class="btn register-btn"><i class="fas fa-user-plus"></i> Register Teacher</button>
        </form>
    </div>
</div>

<script>
function calculateAge(dobInput) {
    const dob = new Date(dobInput.value);
    if (isNaN(dob.getTime())) return;
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) age--;
    const ageField = dobInput.closest('.form-row').querySelector('input[name="age"]') || document.querySelector('input[name="age"]');
    if (ageField) ageField.value = Math.max(0, age);
}

function togglePassword(fieldId, icon) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
</body>
</html>
