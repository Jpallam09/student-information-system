<?php
/**
 * ============================================================
 * STUDENT REGISTRATION MODULE
 * ============================================================
 */

session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';
include_once PROJECT_ROOT . '/config/current_school_year.php';
$active_year = getActiveSchoolYear($conn) ?? '';
$active_sem = getActiveSemester($conn) ?? '';

$from = isset($_GET['from']) ? $_GET['from'] : 'student';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id       = trim($_POST['student_id']);
    $first_name       = trim($_POST['first_name']);
    $middle_name      = trim($_POST['middle_name']);
    $last_name        = trim($_POST['last_name']);
    $suffix           = trim($_POST['suffix']);
    $dob              = $_POST['dob'];
    $age              = $_POST['age'];
    $place_of_birth   = trim($_POST['place_of_birth']);
    $gender           = $_POST['gender'];
    $civil_status     = $_POST['civil_status'];
    $nationality      = trim($_POST['nationality']);
    $religion         = trim($_POST['religion']);
    $student_type     = $_POST['student_type'];

    $course           = $_POST['course'];
    $year_level       = $_POST['year_level'];
    $section          = $_POST['section'];
    $school_year      = trim($_POST['school_year']);
    $semester         = $_POST['semester'];
    $status           = $_POST['status'];

    $email            = trim($_POST['email']);
    $mobile           = trim($_POST['mobile']);
    $home_address     = trim($_POST['home_address']);
    $zip_code         = $_POST['zip_code'] ?: 'NA';

    $emergency_person = trim($_POST['emergency_person']);
    $emergency_number = trim($_POST['emergency_number']);

    $father_name      = trim($_POST['father_name']) ?: 'NA';
    $mother_name      = trim($_POST['mother_name']) ?: 'NA';
    $guardian_name    = trim($_POST['guardian_name']) ?: 'NA';
    $parent_contact   = trim($_POST['parent_contact']) ?: 'NA';
    $parent_occupation= trim($_POST['parent_occupation']) ?: 'NA';
    $parent_employer  = trim($_POST['parent_employer']) ?: 'NA';

    $last_school_attended = trim($_POST['last_school_attended']);
    $last_school_address  = trim($_POST['last_school_address']);

    $blood_type       = trim($_POST['blood_type']);
    $medical_conditions = trim($_POST['medical_conditions']) ?: 'NA';
    $allergies        = trim($_POST['allergies']) ?: 'NA';

    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match');</script>";
        exit();
    }

    $checkStmt = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
    $checkStmt->bind_param("s", $student_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        echo "<script>alert('Student ID already exists');</script>";
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

   // ================== INSERT STUDENT WITH STATUS ==================
$insertStmt = $conn->prepare("
    INSERT INTO students (
        student_id, first_name, middle_name, last_name, suffix,
        dob, age, place_of_birth, gender, civil_status, nationality, religion, student_type,
        course, year_level, section, school_year, semester,
        email, mobile, home_address, zip_code,
        emergency_person, emergency_number,
        father_name, mother_name, guardian_name, parent_contact, parent_occupation, parent_employer,
        last_school_attended, last_school_address,
        blood_type, medical_conditions, allergies,
        password, status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$insertStmt->bind_param(
    "ssssssisssssssssssssssssssssssssssssss",
    $student_id, $first_name, $middle_name, $last_name, $suffix,
    $dob, $age, $place_of_birth, $gender, $civil_status, $nationality, $religion, $student_type,
    $course, $year_level, $section, $school_year, $semester,
    $email, $mobile, $home_address, $zip_code,
    $emergency_person, $emergency_number,
    $father_name, $mother_name, $guardian_name, $parent_contact, $parent_occupation, $parent_employer,
    $last_school_attended, $last_school_address,
    $blood_type, $medical_conditions, $allergies,
    $hashed_password, $status
);
    if ($insertStmt->execute()) {
        if ($from === 'teacher') {
header("Location: " . BASE_URL . "teachersportal/students.php");
        } else {
header("Location: " . BASE_URL . "Accesspage/student_login.php");
        }
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Registration | Student Info System</title>
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
        <a href="<?= ($from === 'teacher') ? BASE_URL . 'teachersportal/students.php' : BASE_URL . 'Accesspage/student_login.php' ?>" class="back-arrow" title="Back">↩</a>
        <h1>Student<br>Management<br>System</h1>
    </div>
    <div class="left-panel">
        <div class="icon"><i class="fas fa-user-plus"></i></div>
        <h2>Student Registration</h2>
        <p>Complete all fields to create your account</p>
        <form action="register.php<?= ($from === 'teacher') ? '?from=teacher' : '' ?>" method="POST" class="register-form">
            <fieldset>
                <legend><i class="fas fa-user"></i> Basic Personal Information</legend>
                <div class="form-row">
                    <input type="text" name="first_name" placeholder="First Name *" required oninput="this.value = this.value.toUpperCase()">
                    <input type="text" name="middle_name" placeholder="Middle Name" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div class="form-row">
                    <input type="text" name="last_name" placeholder="Last Name *" required oninput="this.value = this.value.toUpperCase()">
                    <input type="text" name="suffix" placeholder="Suffix">
                </div>
                <div class="form-row">
                    <div>
                        <label>Date of Birth</label>
                        <input type="date" name="dob" required onchange="calculateAge(this)">
                    </div>
                    <input type="number" name="age" placeholder="Age *" required>
                </div>
                <div class="form-row">
                    <input type="text" name="place_of_birth" placeholder="Place of Birth" oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, l => l.toUpperCase())">
                    <select name="gender" required><option value="">Gender *</option><option value="Male">Male</option><option value="Female">Female</option></select>
                </div>
                <div class="form-row">
                    <select name="civil_status" required><option value="">Civil Status *</option><option value="Single">Single</option><option value="Married">Married</option><option value="Widowed">Widowed"></option></select>
                    <select name="nationality" required><option value="">Nationality *</option><option value="Filipino">Filipino</option><option value="Other">Other</option></select>
                </div>
                <div class="form-row">
                    <input type="text" name="religion" placeholder="Religion">
                    <select name="student_type" required><option value="">Student Type *</option><option value="New">New</option><option value="Transferee">Transferee</option><option value="Continuing">Continuing</option></select>
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-graduation-cap"></i> Academic Information</legend>
                <div class="form-row">
                    <select name="course" required><option value="">Course *</option><option value="BSIT">BSIT</option><option value="BSED">BSED</option><option value="BAT">BAT</option><option value="BTVTED">BTVTED</option></select>
                    <select name="year_level" required><option value="">Year Level *</option><option value="1st Year">1st Year</option><option value="2nd Year">2nd Year</option><option value="3rd Year">3rd Year</option><option value="4th Year">4th Year</option></select>
                </div>
                <div class="form-row">
                    <select name="section" required><option value="">Section *</option><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D"></option></select>
                    <input type="text" name="school_year" value="<?= htmlspecialchars($active_year) ?>" placeholder="School Year * (<?= htmlspecialchars($active_year) ?>)" readonly>
                </div>
                <div class="form-row">
                    <select name="semester" required><option value="1st" <?= $active_sem=='1st'?'selected':'' ?>>1st Semester</option><option value="2nd" <?= $active_sem=='2nd'?'selected':'' ?>>2nd Semester</option></select>
                    <select name="status" required><option value="">Status *</option><option value="Regular">Regular</option><option value="Irregular">Irregular</option></select>
                </div>
                <div class="form-row">
                    <input type="text" name="last_school_attended" placeholder="Last School Attended">
                    <input type="text" name="last_school_address" placeholder="Last School Address">
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-address-book"></i> Contact Information</legend>
                <div class="form-row">
                    <input type="email" name="email" placeholder="Email *" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}" required>
                    <input type="text" name="mobile" placeholder="Mobile (09171234567)" maxlength="11" pattern="\\d{11}" required oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                </div>
                <textarea name="home_address" placeholder="Complete Home Address *" required></textarea>
                <div class="form-row">
                    <input type="text" name="zip_code" placeholder="Zip Code (3315)" maxlength="4" pattern="\\d{4}" required oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    <input type="text" name="emergency_person" placeholder="Emergency Contact">
                </div>
                <input type="text" name="emergency_number" placeholder="Emergency Number (09171234567)" maxlength="11" pattern="\\d{11}" required oninput="this.value=this.value.replace(/[^0-9]/g,'')">
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-users"></i> Parent/Guardian Information</legend>
                <div class="form-row">
                    <input type="text" name="father_name" placeholder="Father's Name">
                    <input type="text" name="mother_name" placeholder="Mother's Name">
                </div>
                <div class="form-row">
                    <input type="text" name="guardian_name" placeholder="Guardian Name">
                    <input type="text" name="parent_contact" placeholder="Parent Contact (09171234567)" maxlength="11" pattern="\\d{11}" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                </div>
                <div class="form-row">
                    <input type="text" name="parent_occupation" placeholder="Parent Occupation">
                    <input type="text" name="parent_employer" placeholder="Parent Employer">
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-heartbeat"></i> Health Information</legend>
                <input type="text" name="blood_type" placeholder="Blood Type (A+, O-, etc.)" maxlength="3" pattern="^[ABO][+-]$" oninput="this.value=this.value.toUpperCase().replace(/[^ABO+-]/g,'')">
                <textarea name="medical_conditions" placeholder="Medical Conditions (or None)"></textarea>
                <textarea name="allergies" placeholder="Allergies (or None)"></textarea>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-key"></i> Account Credentials</legend>
                <input type="text" name="student_id" placeholder="Student ID (25-0001)" pattern="[0-9]{2}-[0-9]{4}" maxlength="7" required>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" placeholder="Password *" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                </div>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password *" required>
                    <i class="fa-regular fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                </div>
            </fieldset>

            <button type="submit" class="btn register-btn"><?= ($from === 'teacher') ? 'Add Student' : 'Register Account' ?></button>
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
    const ageField = dobInput.closest('.form-row').querySelector('input[name="age"]');
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
