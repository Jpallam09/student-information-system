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
    $zip_code         = trim($_POST['zip_code']) ?: 'NA';

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

// Count the parameters: 37 placeholders
// Type definition: 
// - 's' for string (36 of them for most fields)
// - 'i' for integer (age is integer)
// So: 36 's' + 1 'i' = 37 characters
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
<title>Student Registration</title>
 <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
<link rel="stylesheet" href="<?php echo asset('css/register.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<div class="container">
<div class="left-panel">

<a href="<?php echo ($from === 'teacher') ? BASE_URL . 'teachersportal/students.php'   : BASE_URL . 'Accesspage/student_login.php'; ?>" class="back-arrow">↩</a>

<div class="icon">📝</div>
<h2>Student Registration</h2>
<p>Create your account to access the portal</p>

<form action="register.php<?php echo ($from === 'teacher') ? '?from=teacher' : ''; ?>" method="POST" class="register-form">

<fieldset>
<legend>📝 Basic Personal Information</legend>
                <!-- First Name -->
                <input type="text" 
                name="first_name" 
                value="<?= htmlspecialchars($student['first_name'] ?? '') ?>" 
                placeholder="First Name (e.g., Juan)" 
                required
                oninput="this.value = this.value.toUpperCase()">

                <!-- Middle Name -->
                <input type="text" 
                name="middle_name" 
                value="<?= htmlspecialchars($student['middle_name'] ?? '') ?>"  
                placeholder="Middle Name (e.g., Malittay)"
                oninput="this.value = this.value.toUpperCase()">

                <!-- Last Name -->
                <input type="text" 
                name="last_name" 
                value="<?= htmlspecialchars($student['last_name'] ?? '') ?>" 
                placeholder="Last Name (e.g., Danilla)" 
                required
                oninput="this.value = this.value.toUpperCase()">
                
                <select name="suffix">
                <option value="">Select Suffix</option>
                <option value="Jr.">Jr.</option>
                <option value="Sr.">Sr.</option>
                </select>
                
                <label for="dob">Date of Birth</label>
<input type="date" name="dob" placeholder="Date of Birth" title="Enter your date of birth" required onchange="calculateAge(this)">
                <input type="number" name="age" placeholder="Age" required>

                
                <!-- Place of birth restricted to real PH barangay, municipality, province -->
                <input type="text"
                name="place_of_birth"
                value="<?= htmlspecialchars($student['place_of_birth'] ?? '') ?>"
                placeholder="Place of Birth (Barangay, Municipality, Province)"
                title="Enter your birthplace in format: Barangay, Municipality, Province"
                oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">

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


<select name="nationality" required>
<option value="">Select Nationality</option>
<option value="Filipino">Filipino</option>
<option value="American">American</option>
<option value="Canadian">Canadian</option>
<option value="British">British</option>
<option value="Australian">Australian</option>
<option value="Japanese">Japanese</option>
<option value="Chinese">Chinese</option>
<option value="Other">Other</option>
</select>

<!-- Religion fixed -->
<input type="text" 
name="religion" 
value="<?= htmlspecialchars($student['religion'] ?? 'Roman Catholic') ?>"
placeholder="Religion (e.g., Roman Catholic)"
required
oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g,'').replace(/\b\w/g,c => c.toUpperCase());">

<select name="student_type" required>
<option value="">Select Student Type</option>
<option value="New">New</option>
<option value="Transferee">Transferee</option>
<option value="Continuing">Continuing</option>
<option value="Returnee">Returnee</option>
<option value="Cross-enrollee">Cross-enrollee</option>
</select>
</fieldset>

<fieldset>
<legend>🎓 Academic Information</legend>
<select name="course" required>
<option value="">Select Course</option>
<option value="BSIT">BSIT</option>
<option value="BSED">BSED</option>
<option value="BAT">BAT</option>
<option value="BTVTED">BTVTED</option>
</select>

<select name="year_level" required>
<option value="">Select Year Level</option>
<option value="1st Year">1st Year</option>
<option value="2nd Year">2nd Year</option>
<option value="3rd Year">3rd Year</option>
<option value="4th Year">4th Year</option>
</select>

<select name="section" required>
<option value="">Select Section</option>
<option value="A">A</option>
<option value="B">B</option>
<option value="C">C</option>
<option value="D">D</option>
</select>

<input type="text" name="school_year" value="<?= htmlspecialchars($active_year) ?>" placeholder="Auto-filled: <?= htmlspecialchars($active_year) ?>" pattern="\d{4}-\d{4}" title="Active School Year from Admin" maxlength="9" readonly required>

<select name="semester" disabled required>
<option value="<?= htmlspecialchars($active_sem) ?>" selected><?= htmlspecialchars($active_sem) ?> SEM (Active)</option>
</select>

<select name="status" required>
    <option value="">Select Active Status</option>
    <option value="Regular">Regular</option>
    <option value="Irregular">Irregular</option>
    <option value="Probation">Probation</option>
    <option value="Graduated">Graduated</option>
    <option value="Dropped">Dropped</option>
    <option value="Transferred">Transferred</option>
</select>

<input type="text" 
name="last_school_attended" 
value="<?= htmlspecialchars($student['last_school_attended'] ?? '') ?>" 
placeholder="Last School Attended (Senior High School/High School)"
oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());"
title="Specify last school attended with level (Elementary, High School, etc.)">

<input type="text" 
name="last_school_address" 
value="<?= htmlspecialchars($student['last_school_address'] ?? '') ?>" 
placeholder="Last School Address"
oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">

<fieldset>
<legend>📞 Contact Information</legend>
<input type="email" name="email" placeholder="Email Address (e.g., example@gmail.com)" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" required>
<input type="text" 
name="mobile" 
placeholder="Mobile Number (11-digit Philippine number, e.g., 09171234567)" 
maxlength="11" 
pattern="\d{11}" 
required
oninput="this.value = this.value.replace(/[^0-9]/g,'');">

<textarea 
name="home_address" 
placeholder="Home Address (Barangay, Municipality, Province)" 
required
oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })"
><?= htmlspecialchars($student['home_address'] ?? '') ?></textarea>

<input type="text" 
name="zip_code" 
placeholder="Zip Code (e.g., 3315)" 
maxlength="4" 
pattern="\d{4}" 
required
oninput="this.value = this.value.replace(/[^0-9]/g,'');">

<input type="text" 
name="emergency_person" 
placeholder="Emergency Contact Person" 
required
oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g,'').replace(/\b\w/g, c => c.toUpperCase());">

<input type="text" 
name="emergency_number" 
placeholder="Emergency Contact Number (e.g., 09758685522)" 
maxlength="11" 
pattern="\d{11}" 
required
oninput="this.value = this.value.replace(/[^0-9]/g,'');">
</fieldset>

<fieldset>
<legend>👨‍👩‍👧 Parent / Guardian Information</legend>
<input type="text" name="father_name" placeholder="Father's Name (or NA)" required>
<input type="text" name="mother_name" placeholder="Mother's Name (or NA)" required>
<input type="text" name="guardian_name" placeholder="Guardian Name (or NA)">
<input type="text" 
name="parent_contact" 
placeholder="Parent Contact Number (11-digit)" 
maxlength="11" 
pattern="\d{11}" 
oninput="this.value = this.value.replace(/[^0-9]/g,'');">
<input type="text" name="parent_occupation" placeholder="Parent Occupation (or NA)">
<input type="text" name="parent_employer" placeholder="Parent Employer (or NA)">
</fieldset>

<fieldset>
<legend>🏥 Health Information</legend>
<input 
type="text" 
name="blood_type"
value="<?= htmlspecialchars($student['blood_type'] ?? '') ?>" 
placeholder="Blood Type (A+, O-, B+, etc.)"
maxlength="3"
oninput="this.value = this.value.toUpperCase().replace(/[^ABO+-]/g,'')"
pattern="^(A|B|AB|O)[+-]$"
title="Enter a valid blood type (A+, A-, B+, B-, AB+, AB-, O+, O-)"
required
>
<textarea name="medical_conditions" placeholder="Medical Conditions (e.g., Asthma, Diabetes; NA if none)"></textarea>
<textarea name="allergies" placeholder="Allergies (e.g., Peanuts, Pollen; NA if none)"></textarea>
</fieldset>

<fieldset>
<legend>🔐 Account Credentials</legend>
<input type="text" name="student_id" placeholder="School ID (e.g., 25-0001)" pattern="[0-9]{2}-[0-9]{4}" title="Format: 2 digits, dash, 4 digits (e.g., 25-0001)" maxlength="7" required>
<div class="password-wrapper">
    <input type="password" name="password" id="password" placeholder="Password" required>
    <i class="fa-regular fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
</div>

<div class="password-wrapper">
    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
    <i class="fa-regular fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
</div>
</fieldset>

<button type="submit" class="btn register-btn">
<?php echo ($from === 'teacher') ? 'ADD NEW STUDENT' : 'REGISTER'; ?>
</button>

</form>
</div>

<script>
function formatAddress(input) {
    let parts = input.value.split(',');

    parts = parts.map(part => {
        part = part.trim().toLowerCase();
        return part.charAt(0).toUpperCase() + part.slice(1);
    });

    input.value = parts.join(', ');
}
</script>


<script>
// Toggle password visibility with icon change
function togglePassword(fieldId, icon) {
    const field = document.getElementById(fieldId);
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

<div class="right-panel">

<h1>
Welcome to<br>
Student<br>
Information<br>
Management
</h1>
</div>
</div>
</body>
</html>