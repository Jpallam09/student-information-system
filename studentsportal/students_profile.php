<?php
session_start();
include '../config/database.php';

// ✅ Check if student is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: ../Accesspage/student_login.php");
    exit();
}

// ✅ Get student ID
$student_id = $_SESSION['student_id'];
if(isset($_GET['id'])){
    $student_id = $_GET['id'];
}

// ✅ Fetch student info
$sql = "SELECT * FROM students WHERE id='$student_id'";
$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) == 0){
    die("Student not found.");
}

$student = mysqli_fetch_assoc($result);

// ✅ Handle profile update
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])){
    $first_name = mysqli_real_escape_string($conn,$_POST['first_name'] ?? '');
    $middle_name = mysqli_real_escape_string($conn,$_POST['middle_name'] ?? '');
    $last_name = mysqli_real_escape_string($conn,$_POST['last_name'] ?? '');
    $suffix = mysqli_real_escape_string($conn,$_POST['suffix'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $age = mysqli_real_escape_string($conn,$_POST['age'] ?? '');
    $place_of_birth = mysqli_real_escape_string($conn,$_POST['place_of_birth'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $civil_status = mysqli_real_escape_string($conn,$_POST['civil_status'] ?? '');
    $nationality = mysqli_real_escape_string($conn,$_POST['nationality'] ?? '');
    $religion = mysqli_real_escape_string($conn,$_POST['religion'] ?? '');
    $student_type = mysqli_real_escape_string($conn,$_POST['student_type'] ?? '');
    $email = mysqli_real_escape_string($conn,$_POST['email'] ?? '');
    $mobile = mysqli_real_escape_string($conn,$_POST['mobile'] ?? '');
    $home_address = mysqli_real_escape_string($conn,$_POST['home_address'] ?? '');
    $zip_code = mysqli_real_escape_string($conn,$_POST['zip_code'] ?? '');
    $status = mysqli_real_escape_string($conn,$_POST['status'] ?? '');

    $father_name = mysqli_real_escape_string($conn,$_POST['father_name'] ?? '');
    $mother_name = mysqli_real_escape_string($conn,$_POST['mother_name'] ?? '');
    $guardian_name = mysqli_real_escape_string($conn,$_POST['guardian_name'] ?? '');
    $parent_contact = mysqli_real_escape_string($conn,$_POST['emergency_number'] ?? '');
    $parent_occupation = mysqli_real_escape_string($conn,$_POST['parent_occupation'] ?? '');
    $parent_employer = mysqli_real_escape_string($conn,$_POST['parent_employer'] ?? '');

    $last_school_attended = mysqli_real_escape_string($conn,$_POST['last_school_attended'] ?? '');
    $last_school_address = mysqli_real_escape_string($conn,$_POST['last_school_address'] ?? '');

    $blood_type = mysqli_real_escape_string($conn,$_POST['blood_type'] ?? '');
    $medical_conditions = mysqli_real_escape_string($conn,$_POST['medical_conditions'] ?? '');
    $allergies = mysqli_real_escape_string($conn,$_POST['allergies'] ?? '');

    $update = "UPDATE students SET
        first_name='$first_name',
        middle_name='$middle_name',
        last_name='$last_name',
        suffix='$suffix',
        dob='$dob',
        age='$age',
        place_of_birth='$place_of_birth',
        gender='$gender',
        civil_status='$civil_status',
        nationality='$nationality',
        religion='$religion',
        student_type='$student_type',
        email='$email',
        mobile='$mobile',
        home_address='$home_address',
        zip_code='$zip_code',
        status='$status',
        father_name='$father_name',
        mother_name='$mother_name',
        guardian_name='$guardian_name',
        parent_contact='$parent_contact',
        parent_occupation='$parent_occupation',
        parent_employer='$parent_employer',
        last_school_attended='$last_school_attended',
        last_school_address='$last_school_address',
        blood_type='$blood_type',
        medical_conditions='$medical_conditions',
        allergies='$allergies'
        WHERE id='$student_id'";

    if(mysqli_query($conn, $update)){
        $_SESSION['profile_updated'] = true;

        // ✅ Refetch updated student info so modal shows updated values immediately
        $result = mysqli_query($conn, "SELECT * FROM students WHERE id='$student_id'");
        if(mysqli_num_rows($result) > 0){
            $student = mysqli_fetch_assoc($result);
        }

        // Keep redirect if you want page refresh
        // header("Location: students_profile.php?id=$student_id");
        // exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Profile</title>
    <link rel="stylesheet" href="../css/studentportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php if(isset($_SESSION['profile_updated']) && $_SESSION['profile_updated']): ?>
    <div class="notification success" id="profileNotification">
        PROFILE UPDATED
    </div>
    <?php unset($_SESSION['profile_updated']); ?>

    <script>
        // Auto-hide the notification after 3 seconds
        setTimeout(() => {
            const notification = document.getElementById('profileNotification');
            if(notification){
                notification.style.transition = 'opacity 0.5s';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500); // Remove from DOM
            }
        }, 3000);
    </script>
<?php endif; ?>

<?php include 'students_sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <h2 class="page-title">Student Profile</h2>
        <p class="page-subtitle">Manage your information and academic details</p>
    </div>

    <div class="profile-grid">
        <div class="profile-card">
            <div class="profile-avatar">
                <img src="../images/default-profile.png" alt="Profile Picture">
            </div>
            <h3 class="profile-name"><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></h3>
            <p class="profile-role"><?= htmlspecialchars(($student['course'] ?? '').' - '.($student['year_level'] ?? '').'') ?></p>

            <div class="profile-info">
                <p><i class="fas fa-id-card"></i> <?= htmlspecialchars($student['student_id'] ?? '') ?></p>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($student['email'] ?? '') ?></p>
                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($student['mobile'] ?? '') ?></p>
            </div>

            <button class="btn-primary" id="openProfileModal"><i class="fas fa-edit"></i> Edit Profile</button>
        </div>

        <div class="profile-details">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-graduate"></i> Student Details</h3>
                </div>
                <div class="card-content info-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">

                    <!-- PERSONAL INFORMATION -->
                    <h3>📝 Basic Personal Information</h3>
                    <div class="info-item">
                        <label><i class="fas fa-id-badge"></i> Full Name</label>
                        <p><?= htmlspecialchars(($student['first_name'] ?? '').' '.($student['middle_name'] ?? '').' '.($student['last_name'] ?? '').' '.($student['suffix'] ?? '')) ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-venus-mars"></i> Gender</label>
                        <p><?= htmlspecialchars($student['gender'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-calendar-alt"></i> Date of Birth</label>
                        <p><?= htmlspecialchars($student['dob'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-child"></i> Age</label>
                        <p><?= htmlspecialchars($student['age'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-map-marker-alt"></i> Place of Birth</label>
                        <p><?= htmlspecialchars($student['place_of_birth'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-heart"></i> Civil Status</label>
                        <p><?= htmlspecialchars($student['civil_status'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-flag"></i> Nationality</label>
                        <p><?= htmlspecialchars($student['nationality'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-praying-hands"></i> Religion</label>
                        <p><?= htmlspecialchars($student['religion'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-home"></i> Address</label>
                        <p><?= htmlspecialchars($student['home_address'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-map-pin"></i> ZIP Code</label>
                        <p><?= htmlspecialchars($student['zip_code'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-user-check"></i> Student Type</label>
                        <p><?= htmlspecialchars($student['student_type'] ?? '') ?></p>
                    </div>

                    <!-- PARENT / GUARDIAN INFORMATION -->
                    <h3>👪 Parent / Guardian Information</h3>
                    <div class="info-item">
                        <label><i class="fas fa-male"></i> Father</label>
                        <p><?= htmlspecialchars($student['father_name'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-female"></i> Mother</label>
                        <p><?= htmlspecialchars($student['mother_name'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-user-tie"></i> Guardian</label>
                        <p><?= htmlspecialchars($student['guardian_name'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-phone"></i> Parent Contact</label>
                        <p><?= htmlspecialchars($student['parent_contact'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-briefcase"></i> Parent Occupation</label>
                        <p><?= htmlspecialchars($student['parent_occupation'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-building"></i> Parent Employer</label>
                        <p><?= htmlspecialchars($student['parent_employer'] ?? '') ?></p>
                    </div>

                    <!-- ACADEMIC INFORMATION -->
                    <h3>🎓 Academic Information</h3>
                    <div class="info-item">
                        <label><i class="fas fa-book"></i> Program</label>
                        <p><?= htmlspecialchars($student['course'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-layer-group"></i> Year Level</label>
                        <p><?= htmlspecialchars($student['year_level'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-users"></i> Section</label>
                        <p><?= htmlspecialchars($student['section'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-school"></i> School Year</label>
                        <p><?= htmlspecialchars($student['school_year'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-clock"></i> Semester</label>
                        <p><?= htmlspecialchars($student['semester'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-check-circle"></i> Academic Status</label>
                        <p><?= htmlspecialchars($student['status'] ?? '') ?></p>
                    </div>

                    <!-- LAST SCHOOL -->
                    <h3>🏫 Last School Information</h3>
                    <div class="info-item">
                        <label><i class="fas fa-school"></i> Last School Attended</label>
                        <p><?= htmlspecialchars($student['last_school_attended'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-map-marker-alt"></i> Last School Address</label>
                        <p><?= htmlspecialchars($student['last_school_address'] ?? '') ?></p>
                    </div>

                    <!-- HEALTH INFORMATION -->
                    <h3>💉 Health Information</h3>
                    <div class="info-item">
                        <label><i class="fas fa-tint"></i> Blood Type</label>
                        <p><?= htmlspecialchars($student['blood_type'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-notes-medical"></i> Medical Conditions</label>
                        <p><?= htmlspecialchars($student['medical_conditions'] ?? '') ?></p>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-allergies"></i> Allergies</label>
                        <p><?= htmlspecialchars($student['allergies'] ?? '') ?></p>
                    </div>

                </div>
            </div>
        </div>

    <!-- EDIT PROFILE MODAL -->
<div id="editProfileModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2 class="modal-title"><i class="fas fa-user-edit"></i> Edit Profile</h2>
        <form method="POST" class="edit-profile-form">

            <div class="form-grid">

                <!-- 📝 Basic Personal Information -->
                <h3>📝 Basic Personal Information</h3>
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
                
                <!-- DOB with hint -->
                <input type="date" name="dob" value="<?= htmlspecialchars($student['dob'] ?? '') ?>" placeholder="Date of Birth" title="Enter your date of birth (YYYY-MM-DD)" required>

                <input type="number" name="age" value="<?= htmlspecialchars($student['age'] ?? '') ?>" placeholder="Age" min="1" max="120" required>

                <!-- Place of birth restricted to real PH barangay, municipality, province -->
                <input type="text"
                name="place_of_birth"
                value="<?= htmlspecialchars($student['place_of_birth'] ?? '') ?>"
                placeholder="Place of Birth (Barangay, Municipality, Province)"
                title="Enter your birthplace in format: Barangay, Municipality, Province"
                oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })">

                <select name="gender" required>
                    <option value="Male" <?= ($student['gender'] ?? '')=='Male'?'selected':'' ?>>Male</option>
                    <option value="Female" <?= ($student['gender'] ?? '')=='Female'?'selected':'' ?>>Female</option>
                </select>

                <input type="text" name="civil_status" value="<?= htmlspecialchars($student['civil_status'] ?? '') ?>" placeholder="Civil Status" title="Single, Married, etc.">

                <!-- Nationality fixed -->
                <select name="nationality" required>
    <?php 
    $nationalities = ['Filipino','American','Canadian','British','Australian','Japanese','Chinese','Other'];
    foreach($nationalities as $nat): ?>
        <option value="<?= $nat ?>" <?= ($student['nationality'] ?? '')==$nat?'selected':'' ?>><?= $nat ?></option>
    <?php endforeach; ?>
</select>

                <!-- Religion fixed -->
                                <input type="text" 
                name="religion" 
                value="<?= htmlspecialchars($student['religion'] ?? 'Roman Catholic') ?>"
                placeholder="Religion (e.g., Roman Catholic)"
                required
                oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g,'').replace(/\b\w/g,c => c.toUpperCase());">

                <select name="student_type" required>
                    <option value="New" <?= ($student['student_type'] ?? '')=='New'?'selected':'' ?>>New</option>
                    <option value="Transferee" <?= ($student['student_type'] ?? '')=='Transferee'?'selected':'' ?>>Transferee</option>
                    <option value="Continuing" <?= ($student['student_type'] ?? '')=='Continuing'?'selected':'' ?>>Continuing</option>
                    <option value="Returnee" <?= ($student['student_type'] ?? '')=='Returnee'?'selected':'' ?>>Returnee</option>
                    <option value="Cross-enrollee" <?= ($student['student_type'] ?? '')=='Cross-enrollee'?'selected':'' ?>>Cross-enrollee</option>
                </select>

                <!-- Email with validation -->
                <input type="email" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" placeholder="Email (example@gmail.com)" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" required>

                <!-- Mobile -->
                <input type="text" 
                    name="mobile" 
                    value="<?= htmlspecialchars($student['mobile'] ?? '') ?>"
                    placeholder="Mobile Number (11-digit Philippine number, e.g., 09171234567)" 
                    maxlength="11" 
                    pattern="\d{11}" 
                    required
                    oninput="this.value = this.value.replace(/[^0-9]/g,'');">

                <!-- Home address -->
                <textarea 
                name="home_address" 
                placeholder="Home Address (Barangay, Municipality, Province)" 
                required
                oninput="this.value = this.value.toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); })"
                ><?= htmlspecialchars($student['home_address'] ?? '') ?></textarea>

                <input type="text" 
                name="zip_code" 
                value="<?= htmlspecialchars($student['zip_code'] ?? '') ?>"
                placeholder="Zip Code (e.g., 3315)" 
                maxlength="4" 
                pattern="\d{4}" 
                required
                oninput="this.value = this.value.replace(/[^0-9]/g,'');">

<select name="status" required>
    <option value="" disabled selected>Select Active Status</option>
    <option value="Regular" <?= ($student['status'] ?? '')=='Regular' ? 'selected' : '' ?>>Regular</option>
    <option value="Irregular" <?= ($student['status'] ?? '')=='Irregular' ? 'selected' : '' ?>>Irregular</option>
    <option value="Probation" <?= ($student['status'] ?? '')=='Probation' ? 'selected' : '' ?>>Probation</option>
    <option value="Graduated" <?= ($student['status'] ?? '')=='Graduated' ? 'selected' : '' ?>>Graduated</option>
    <option value="Dropped" <?= ($student['status'] ?? '')=='Dropped' ? 'selected' : '' ?>>Dropped</option>
    <option value="Transferred" <?= ($student['status'] ?? '')=='Transferred' ? 'selected' : '' ?>>Transferred</option>
</select>
                <!-- 👪 Parent / Guardian Information -->
                <h3>👪 Parent / Guardian Information</h3>
                <!-- Parent / Guardian Info -->
                <input type="text" name="father_name" 
                value="<?= htmlspecialchars($student['father_name'] ?? 'NA') ?>" 
                placeholder="Father's Name"
                oninput="this.value = this.value.toUpperCase()">

                <input type="text" name="mother_name" 
                value="<?= htmlspecialchars($student['mother_name'] ?? 'NA') ?>" 
                placeholder="Mother's Name"
                oninput="this.value = this.value.toUpperCase()">

                <input type="text" name="guardian_name" 
                value="<?= htmlspecialchars($student['guardian_name'] ?? 'NA') ?>" 
                placeholder="Guardian Name"
                oninput="this.value = this.value.toUpperCase()">

                <input type="text" name="parent_occupation" 
                value="<?= htmlspecialchars($student['parent_occupation'] ?? 'NA') ?>" 
                placeholder="Parent Occupation"
                oninput="this.value = this.value.toUpperCase()">

                <input type="text" name="parent_employer" 
                value="<?= htmlspecialchars($student['parent_employer'] ?? 'NA') ?>" 
                placeholder="Name of company where your parent works"
                oninput="this.value = this.value.toUpperCase()">
                <!-- Emergency contact -->
                  <!-- Parent Emergency Contact -->
                <input type="text" 
                    name="emergency_number" 
                    value="<?= htmlspecialchars($student['parent_contact'] ?? '') ?>"
                    placeholder="Parent Emergency Contact Number (e.g., 09758685522)" 
                    maxlength="11" 
                    pattern="\d{11}" 
                    required
                    oninput="this.value = this.value.replace(/[^0-9]/g,'');">

                <!-- 🎓 Academic Information -->
                <h3>🎓 Academic Information</h3>
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

                <!-- 💉 Health Information -->
                <h3>💉 Health Information</h3>
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
                <textarea name="medical_conditions" placeholder="Medical Conditions (e.g., Asthma, Diabetes, NA)"><?= htmlspecialchars($student['medical_conditions'] ?? 'NA') ?></textarea>
                <textarea name="allergies" placeholder="Allergies (e.g., Penicillin, Peanuts, NA)"><?= htmlspecialchars($student['allergies'] ?? 'NA') ?></textarea>

            </div>

            <div class="form-buttons">
                <button type="submit" name="update_profile" class="btn-primary"><i class="fas fa-save"></i> Update</button>
                <button type="button" class="btn-secondary" id="closeProfileModal">Cancel</button>
            </div>
        </form>
    </div>
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
// Improved Modal JS
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editProfileModal');
    const openBtn = document.getElementById('openProfileModal');
    const closeBtn = document.getElementById('closeProfileModal');
    const closeIcon = document.querySelector('#editProfileModal .close-modal');

    if (!modal || !openBtn) return; // Exit if elements don't exist

    function openModal() { 
        modal.classList.add('show'); 
        modal.style.display = 'flex'; 
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
    
    function closeModal() { 
        modal.classList.remove('show'); 
        modal.style.display = 'none'; 
        document.body.style.overflow = ''; // Restore scrolling
    }

    openBtn.addEventListener('click', openModal);
    
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (closeIcon) closeIcon.addEventListener('click', closeModal);
    
    // Close when clicking outside modal
    modal.addEventListener('click', function(e) { 
        if(e.target === modal) closeModal(); 
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) { 
        if(e.key === 'Escape' && modal.classList.contains('show')) {
            closeModal(); 
        }
    });

    // Prevent modal from closing when clicking inside modal content
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});

// Notification
<?php if(isset($_SESSION['profile_updated']) && $_SESSION['profile_updated']): ?>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function(){
        const notification = document.getElementById('profileNotification');
        if(notification) { 
            notification.style.display = 'flex'; 
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }
    }, 100);
});
<?php unset($_SESSION['profile_updated']); ?>
<?php endif; ?>
</script>
</body>
</html>