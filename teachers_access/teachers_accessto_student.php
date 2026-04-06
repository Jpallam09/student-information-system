<?php
session_start();

/* ============================================================
   PRODUCTION PATH + DATABASE
   ============================================================ */
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/current_school_year.php';

/* ============================================================
   ACTIVE SCHOOL YEAR / SEM
   ============================================================ */
$active_year = getActiveSchoolYear($conn) ?? '';
$active_sem  = getActiveSemester($conn) ?? '';

/* ============================================================
   AUTH CHECK
   ============================================================ */
if (!isset($_SESSION['teacher_id'])) {
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

/* ============================================================
   ADMIN CHECK
   ============================================================ */
$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

/* ============================================================
   GET STUDENT ID
   ============================================================ */
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "No student selected.";
    exit();
}

$student_id = intval($_GET['id']);

/* ============================================================
   FETCH STUDENT (PREPARED STATEMENT)
   ============================================================ */
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Student not found.";
    exit();
}

$student = $result->fetch_assoc();

/* ============================================================
   MESSAGES
   ============================================================ */
$success_msg = $error_msg = $no_change_msg = "";

/* ============================================================
   DELETE STUDENT
   ============================================================ */
if (isset($_POST['delete_student'])) {
    $conn->begin_transaction();

    try {
        $stmt1 = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");
        $stmt1->bind_param("i", $student_id);
        $stmt1->execute();

        $stmt2 = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt2->bind_param("i", $student_id);
        $stmt2->execute();

        $conn->commit();

        header("Location: " . BASE_URL . "teachersportal/students.php?msg=deleted");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error deleting student: " . $e->getMessage();
    }
}

/* ============================================================
   UPDATE STUDENT
   ============================================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_student'])) {

    $fields = [
        'first_name','middle_name','last_name','suffix','dob','gender',
        'civil_status','nationality','course','year_level','section',
        'school_year','semester','email','mobile','home_address',
        'emergency_person','emergency_number','age','place_of_birth',
        'religion','student_type','status','last_school_attended',
        'last_school_address','zip_code','father_name','mother_name',
        'guardian_name','parent_contact','parent_occupation',
        'parent_employer','blood_type','medical_conditions','allergies'
    ];

    $new_data = [];
    foreach ($fields as $field) {
        $new_data[$field] = $_POST[$field] ?? '';
    }

    /* Check changes */
    $changed = false;
    foreach ($new_data as $key => $value) {
        if ($student[$key] != $value) {
            $changed = true;
            break;
        }
    }

    if ($changed) {

        $set_parts = [];
        $types = "";
        $values = [];

        foreach ($new_data as $key => $value) {
            $set_parts[] = "$key = ?";
            $types .= "s";
            $values[] = $value;
        }

        $values[] = $student_id;
        $types .= "i";

        $sql = "UPDATE students SET " . implode(", ", $set_parts) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);

        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            $success_msg = "Student information updated successfully.";

            // refresh data
            $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();

        } else {
            $error_msg = "Database error: " . $conn->error;
        }

    } else {
        $no_change_msg = "No changes detected.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student Info | Teacher Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/teachersaccess.css') ?>">
</head>
<body>

<div class="container">
    <div class="left-panel">
       <a href="<?= BASE_URL ?>teachersportal/students.php" class="back-arrow">↩</a>
        <div class="icon"><i class="fas fa-user-edit"></i></div>
        <?php if ($is_admin): ?>
        <h2>Edit Student</h2>
        <p>Update the student's details below. All fields marked are required.</p>
        <?php else: ?>
        <h2>View Student</h2>
        <p>You are in read-only mode. Only administrators can edit or delete student information.</p>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($no_change_msg): ?>
            <div class="message info">
                <i class="fas fa-info-circle"></i> <?= htmlspecialchars($no_change_msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="register-form">
            <fieldset>
                <legend><i class="fas fa-user"></i> Basic Personal Information</legend>
                <div class="form-row">
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" placeholder="First Name *" required oninput="this.value = this.value.toUpperCase()">
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($student['middle_name']); ?>" placeholder="Middle Name" required oninput="this.value = this.value.toUpperCase()">
                </div>
                <div class="form-row">
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" placeholder="Last Name *" required oninput="this.value = this.value.toUpperCase()">
                    <input type="text" name="suffix" value="<?php echo htmlspecialchars($student['suffix']); ?>" placeholder="Suffix">
                </div>
                <div class="form-row">
                    <label for="dob">Date of Birth</label>
                    <input type="date" name="dob" value="<?php echo htmlspecialchars($student['dob']); ?>" required onchange="calculateAge(this)">
                    <input type="number" name="age" value="<?php echo htmlspecialchars($student['age']); ?>" placeholder="Age *" required>
                </div>

                <div class="form-row">
                    <input type="text" name="place_of_birth" value="<?php echo htmlspecialchars($student['place_of_birth']); ?>" placeholder="Place of Birth" oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">

                    <select name="gender" required>
                        <option value="">Gender *</option>
                        <option value="Male" <?php echo ($student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="form-row">
                    <select name="civil_status" required>
                        <option value="">Civil Status *</option>
                        <option value="Single" <?php echo ($student['civil_status'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                        <option value="Married" <?php echo ($student['civil_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                        <option value="Widowed" <?php echo ($student['civil_status'] == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                    </select>
                    <select name="nationality" required>
                        <option value="">Nationality *</option>
                        <option value="Filipino" <?php echo ($student['nationality'] == 'Filipino') ? 'selected' : ''; ?>>Filipino</option>
                        <option value="Other" <?php echo ($student['nationality'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-row">
                    <input type="text" name="religion" value="<?php echo htmlspecialchars($student['religion']); ?>" placeholder="Religion">
                    <select name="student_type" required>
                        <option value="">Student Type *</option>
                        <option value="New" <?php echo ($student['student_type'] == 'New') ? 'selected' : ''; ?>>New</option>
                        <option value="Transferee" <?php echo ($student['student_type'] == 'Transferee') ? 'selected' : ''; ?>>Transferee</option>
                        <option value="Continuing" <?php echo ($student['student_type'] == 'Continuing') ? 'selected' : ''; ?>>Continuing</option>
                    </select>
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-graduation-cap"></i> Academic Information</legend>
                <div class="form-row">
                    <select name="course" required>
                        <option value="">Course *</option>
                        <option value="BSIT" <?php echo ($student['course'] == 'BSIT') ? 'selected' : ''; ?>>BSIT</option>
                        <option value="BSED" <?php echo ($student['course'] == 'BSED') ? 'selected' : ''; ?>>BSED</option>
                        <option value="BAT" <?php echo ($student['course'] == 'BAT') ? 'selected' : ''; ?>>BAT</option>
                        <option value="BTVTED" <?php echo ($student['course'] == 'BTVTED') ? 'selected' : ''; ?>>BTVTED</option>
                    </select>
                    <select name="year_level" required>
                        <option value="">Year Level *</option>
                        <option value="1st Year" <?php echo ($student['year_level'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                        <option value="2nd Year" <?php echo ($student['year_level'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                        <option value="3rd Year" <?php echo ($student['year_level'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                        <option value="4th Year" <?php echo ($student['year_level'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                    </select>
                </div>
                <div class="form-row">
                    <select name="section" required>
                        <option value="">Section *</option>
                        <option value="A" <?php echo ($student['section'] == 'A') ? 'selected' : ''; ?>>A</option>
                        <option value="B" <?php echo ($student['section'] == 'B') ? 'selected' : ''; ?>>B</option>
                        <option value="C" <?php echo ($student['section'] == 'C') ? 'selected' : ''; ?>>C</option>
                        <option value="D" <?php echo ($student['section'] == 'D') ? 'selected' : ''; ?>>D</option>
                    </select>
                    <input type="text" name="school_year" value="<?php echo htmlspecialchars($student['school_year'] ?? $active_year); ?>" placeholder="School Year * (Active: <?= htmlspecialchars($active_year) ?>)" title="Active year auto-suggested">
                </div>
                <div class="form-row">
                    <select name="semester" required>
                        <option value="1st" <?= (($student['semester'] ?? $active_sem) == '1st') ? 'selected' : '' ?> <?= ($active_sem == '1st') ? '(Active)' : '' ?>>1st SEM</option>
                        <option value="2nd" <?= (($student['semester'] ?? $active_sem) == '2nd') ? 'selected' : '' ?> <?= ($active_sem == '2nd') ? '(Active)' : '' ?>>2nd SEM</option>
                    </select>
                    <select name="status" required>
                        <option value="">Status *</option>
                        <option value="Regular" <?php echo ($student['status'] == 'Regular') ? 'selected' : ''; ?>>Regular</option>
                        <option value="Irregular" <?php echo ($student['status'] == 'Irregular') ? 'selected' : ''; ?>>Irregular</option>
                    </select>
                </div>
                <div class="form-row">
                    <input type="text" name="last_school_attended" value="<?php echo htmlspecialchars($student['last_school_attended']); ?>" placeholder="Last School Attended" oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">

                    <input type="text" name="last_school_address" value="<?php echo htmlspecialchars($student['last_school_address']); ?>" placeholder="Last School Address" oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-address-book"></i> Contact Information</legend>
                <div class="form-row">
                    <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" placeholder="Email Address (e.g., example@gmail.com)" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" required>
                    <input type="text" name="mobile" value="<?php echo htmlspecialchars($student['mobile']); ?>" placeholder="Mobile Number (11-digit Philippine number, e.g., 09171234567)" maxlength="11" pattern="\d{11}" required oninput="this.value = this.value.replace(/[^0-9]/g,'');">
                </div>
                <div class="form-row">
                    <textarea name="home_address" placeholder="Home Address" required><?php echo htmlspecialchars($student['home_address']); ?></textarea>
                </div>
                <div class="form-row">
                    <input type="text" name="zip_code" value="<?php echo htmlspecialchars($student['zip_code']); ?>" placeholder="Zip Code (e.g., 3315)" maxlength="4" pattern="\d{4}" required oninput="this.value = this.value.replace(/[^0-9]/g,'');">
                    <input type="text" name="emergency_person" value="<?php echo htmlspecialchars($student['emergency_person']); ?>" placeholder="Emergency Contact Person"oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">
                </div>
                <div class="form-row">
                    <input type="text" name="emergency_number" value="<?php echo htmlspecialchars($student['emergency_number']); ?>" placeholder="Mobile Number (11-digit Philippine number, e.g., 09171234567)" maxlength="11" pattern="\d{11}" required oninput="this.value = this.value.replace(/[^0-9]/g,'');">
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-users"></i> Parent / Guardian Information</legend>
                <div class="form-row">
                    <input type="text" name="father_name" value="<?php echo htmlspecialchars($student['father_name']); ?>" placeholder="Father's Name"oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">
                    <input type="text" name="mother_name" value="<?php echo htmlspecialchars($student['mother_name']); ?>" placeholder="Mother's Name"oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">
                </div>
                <div class="form-row">
                    <input type="text" name="guardian_name" value="<?php echo htmlspecialchars($student['guardian_name']); ?>" placeholder="Guardian Name"oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">
                    <input type="text" name="parent_contact" value="<?php echo htmlspecialchars($student['parent_contact']); ?>" placeholder="Mobile Number (11-digit Philippine number, e.g., 09171234567)" maxlength="11" pattern="\d{11}" required oninput="this.value = this.value.replace(/[^0-9]/g,'');">
                </div>
                <div c
                lass="form-row">
                    <input type="text" name="parent_occupation" value="<?php echo htmlspecialchars($student['parent_occupation']); ?>" placeholder="Parent Occupation"oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">
                    <input type="text" name="parent_employer" value="<?php echo htmlspecialchars($student['parent_employer']); ?>" placeholder="Parent Employer"oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-heartbeat"></i> Health Information</legend>
                <div class="form-row">
                    <input type="text" name="blood_type" value="<?php echo htmlspecialchars($student['blood_type']); ?>" placeholder="Blood Type (A+, O-, B+, etc.)"maxlength="3" oninput="this.value = this.value.toUpperCase().replace(/[^ABO+-]/g,'')"
                </div>
                <div class="form-row">
                    <textarea name="medical_conditions" placeholder="Medical Conditions"><?php echo htmlspecialchars($student['medical_conditions']); ?></textarea>
                </div>
                <div class="form-row">
                    <textarea name="allergies" placeholder="Allergies"><?php echo htmlspecialchars($student['allergies']); ?></textarea>
                </div>
            </fieldset>

            <div style="display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap;">
                <button type="submit" name="update_student" class="btn register-btn" style="flex: 1; min-width: 150px;">
                    <i class="fas fa-save"></i> Update Student
                </button>
                <button type="button" id="showDeleteModal" class="btn register-btn" style="flex: 1; min-width: 150px; background: var(--accent-rose);">
                    <i class="fas fa-trash-alt"></i> Delete Student
                </button>
                <a href="<?= BASE_URL ?>teachersportal/students.php" class="btn register-btn" style="flex: 1; min-width: 150px; background: var(--slate-500); text-decoration: none;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <div class="right-panel">
        <h1>Student<br>Management<br>System</h1>
    </div>

<!-- Delete Confirmation Modal - Matched with announcement.php style -->
<div id="deleteModal" class="modal">
    <div class="modal-content small">
        <span class="close-delete-modal close">&times;</span>
        <h2><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Confirm Delete</h2>
        <p>Are you sure you want to delete this student? This action cannot be undone.</p>
        <div class="modal-actions">
            <button id="cancelDeleteBtn" class="btn-outline">Cancel</button>
            <form method="POST" style="display: inline;" id="deleteForm">
                <button type="submit" name="delete_student" class="btn-danger">Delete</button>
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
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    const ageField = dobInput.parentNode.querySelector('input[name="age"]');
    if (ageField) {
        ageField.value = Math.max(0, age);
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const deleteModal = document.getElementById('deleteModal');

    const showDeleteModalBtn = document.getElementById('showDeleteModal');
    const closeDeleteModalBtn = document.querySelector('.close-delete-modal');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    
    // If not admin, make form read-only
    if (!isAdmin) {
        // Disable all input fields
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(function(input) {
            if (input.type === 'text' || input.type === 'email' || input.type === 'number' || input.type === 'date' || input.type === 'hidden') {
                input.setAttribute('readonly', 'readonly');
            } else if (input.tagName === 'SELECT' || input.tagName === 'TEXTAREA') {
                input.setAttribute('disabled', 'disabled');
            }
        });
        
        // Hide Update and Delete buttons, keep only Cancel button
        const updateBtn = document.querySelector('button[name="update_student"]');
        const deleteBtn = document.getElementById('showDeleteModal');
        if (updateBtn) updateBtn.style.display = 'none';
        if (deleteBtn) deleteBtn.style.display = 'none';
        
        // Update Cancel button to be more prominent (as back button)
     
const cancelBtn = document.querySelector(
    'a[href="<?= BASE_URL ?>teachersportal/students.php"]'
);
        if (cancelBtn) {
            cancelBtn.style.display = 'flex';
            cancelBtn.style.justifyContent = 'center';
        }
    }
    
    if (showDeleteModalBtn && isAdmin) {
        showDeleteModalBtn.addEventListener('click', function(e) {
            e.preventDefault();
            deleteModal.style.display = 'flex';
        });
    }
    
    function closeModal() {
        deleteModal.style.display = 'none';
    }
    
    if (closeDeleteModalBtn) closeDeleteModalBtn.addEventListener('click', closeModal);
    if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', closeModal);
    
    window.addEventListener('click', function(event) {
        if (event.target == deleteModal) {
            closeModal();
        }
    });
    
    // Escape key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && deleteModal.style.display === 'flex') {
            closeModal();
        }
    });
});
</script>

</body>
</html>
