<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

// ✅ Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

// ✅ Get student ID
$student_id = $_SESSION['student_id'];
if (isset($_GET['id'])) {
    $student_id = (int) $_GET['id'];
}

/* =========================
   FETCH STUDENT (SAFE)
========================= */
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Student not found.");
}

$student = $result->fetch_assoc();

/* =========================
   PROFILE PICTURE UPLOAD
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_picture']) && isset($_FILES['profile_picture'])) {

    $uploadDir = PROFILE_PICS_DIR;

    if (!ensureWritable($uploadDir)) {
        $_SESSION['upload_error'] = 'Profile pics directory not writable';
        header("Location: " . BASE_URL . "studentsportal/students_profile.php?id=" . $student_id);
        exit();
    }

    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    $maxSize = 2 * 1024 * 1024;

    $file = $_FILES['profile_picture'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['upload_error'] = 'Upload error occurred.';
        header("Location: " . BASE_URL . "studentsportal/students_profile.php?id=" . $student_id);
        exit();
    }

    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $fileType = $finfo->file($fileTmpName);
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileType, $allowedTypes) || !in_array($fileExt, ['jpg', 'jpeg', 'png'])) {
        $_SESSION['upload_error'] = 'Invalid file type.';
        header("Location: " . BASE_URL . "studentsportal/students_profile.php?id=" . $student_id);
        exit();
    }

    if ($fileSize > $maxSize) {
        $_SESSION['upload_error'] = 'File too large (max 2MB).';
        header("Location: " . BASE_URL . "studentsportal/students_profile.php?id=" . $student_id);
        exit();
    }

    $newFileName = 'student_' . $student_id . '_' . time() . '.' . $fileExt;
    $uploadPath = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        $stmt = $conn->prepare("UPDATE students SET profile_picture = ?, profile_image = ? WHERE id = ?");
        $stmt->bind_param("ssi", $newFileName, $newFileName, $student_id);
        if ($stmt->execute()) {
            $_SESSION['profile_pic_updated'] = true;
        } else {
            $_SESSION['upload_error'] = 'DB update failed.';
            unlink($uploadPath);
        }
    } else {
        $_SESSION['upload_error'] = 'Upload failed.';
    }

    header("Location: " . BASE_URL . "studentsportal/students_profile.php?id=" . $student_id);
    exit();
}

/* =========================
   UPDATE PROFILE (SAFE)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    $stmt = $conn->prepare("
        UPDATE students SET
        first_name=?, middle_name=?, last_name=?, suffix=?, dob=?, age=?,
        place_of_birth=?, gender=?, civil_status=?, nationality=?, religion=?,
        student_type=?, email=?, mobile=?, home_address=?, zip_code=?, status=?,
        father_name=?, mother_name=?, guardian_name=?, parent_contact=?,
        parent_occupation=?, parent_employer=?, last_school_attended=?,
        last_school_address=?, blood_type=?, medical_conditions=?, allergies=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "ssssssssssssssssssssssssssssi",
        $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'],
        $_POST['suffix'], $_POST['dob'], $_POST['age'], $_POST['place_of_birth'],
        $_POST['gender'], $_POST['civil_status'], $_POST['nationality'],
        $_POST['religion'], $_POST['student_type'], $_POST['email'],
        $_POST['mobile'], $_POST['home_address'], $_POST['zip_code'],
        $_POST['status'], $_POST['father_name'], $_POST['mother_name'],
        $_POST['guardian_name'], $_POST['emergency_number'],
        $_POST['parent_occupation'], $_POST['parent_employer'],
        $_POST['last_school_attended'], $_POST['last_school_address'],
        $_POST['blood_type'], $_POST['medical_conditions'], $_POST['allergies'],
        $student_id
    );

    if ($stmt->execute()) {
        $_SESSION['profile_updated'] = true;
    } else {
        echo "Error updating profile.";
    }

    header("Location: " . BASE_URL . "studentsportal/students_profile.php?id=" . $student_id);
    exit();
}

// Profile pic src logic
$profilePic = $student['profile_picture'] ?? null;
$defaultPic = BASE_URL . 'images/default-profile.png';
$serverPicPath = $profilePic ? PROFILE_PICS_DIR . basename($profilePic) : '';
$uploadedPicUrl = $profilePic ? WEB_PROFILE_PICS . basename($profilePic) : '';
$picSrc = ($profilePic && file_exists($serverPicPath)) ? $uploadedPicUrl : $defaultPic;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/studentportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .profile-layout { display: grid; grid-template-columns: 300px 1fr; gap: 24px; align-items: start; }
        .profile-sidebar-card { background: rgba(255,255,255,0.92); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.6); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm); position: sticky; top: 24px; }
        .profile-sidebar-header { background: linear-gradient(160deg, #1e3a5f 0%, #0f1f3d 100%); padding: 32px 20px 24px; text-align: center; position: relative; }
        .profile-sidebar-header::before { content: ''; position: absolute; top: -40px; right: -40px; width: 160px; height: 160px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.05); pointer-events: none; }
        .profile-avatar-wrap { width: 88px; height: 88px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.25); overflow: hidden; margin: 0 auto 14px; background: rgba(255,255,255,0.1); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
        .profile-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .profile-name { font-family: 'Playfair Display', Georgia, serif; font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 4px; }
        .profile-course-tag { font-size: 10px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,0.45); }
        .profile-sidebar-body { padding: 20px; }
        .profile-info-row { display: flex; align-items: center; gap: 10px; font-size: 0.83rem; color: var(--text-secondary); padding: 6px 0; border-bottom: 1px dashed var(--border-light); }
        .profile-info-row:last-of-type { border-bottom: none; }
        .profile-info-row i { color: var(--primary-blue); font-size: 11px; width: 14px; flex-shrink: 0; }
        .profile-info-row span { word-break: break-all; }
        .profile-actions { padding: 0 20px 20px; display: flex; flex-direction: column; gap: 8px; }
        .section-card { background: rgba(255,255,255,0.92); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.6); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm); }
        .section-card-header { padding: 16px 24px; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; gap: 10px; }
        .section-icon { width: 34px; height: 34px; border-radius: 50%; background: rgba(37,99,235,0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .section-icon i { color: var(--primary-blue); font-size: 13px; }
        .section-card-title { font-family: 'Playfair Display', Georgia, serif; font-size: 1rem; font-weight: 700; color: var(--slate-800); }
        .info-grid-2 { padding: 20px 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .info-field label { font-size: 10px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 3px; }
        .info-field p { font-size: 0.88rem; color: var(--text-secondary); margin: 0; word-break: break-word; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid-2 h3 { grid-column: 1 / -1; font-family: 'Playfair Display', Georgia, serif; font-size: 0.95rem; font-weight: 700; color: var(--slate-700); padding: 8px 0 4px; border-bottom: 1px solid var(--border-light); }
        .form-grid-2 input, .form-grid-2 select, .form-grid-2 textarea { margin: 0; }
        .form-grid-2 textarea { grid-column: 1 / -1; }
        .modal-form-wrap { padding: 4px 0; }
        @media (max-width: 900px) { .profile-layout { grid-template-columns: 1fr; } .profile-sidebar-card { position: static; } }
        @media (max-width: 576px) { .info-grid-2 { grid-template-columns: 1fr; } .form-grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div id="notification-container">
    <?php if(isset($_SESSION['profile_updated']) && $_SESSION['profile_updated']): ?>
    <div class="notification notification-success auto-fade"><i class="fas fa-check-circle"></i> Profile updated successfully!</div>
    <?php unset($_SESSION['profile_updated']); ?>
    <?php endif; ?>
    <?php if(isset($_SESSION['profile_pic_updated']) && $_SESSION['profile_pic_updated']): ?>
    <div class="notification notification-success auto-fade"><i class="fas fa-check-circle"></i> Profile picture updated!</div>
    <?php unset($_SESSION['profile_pic_updated']); ?>
    <?php endif; ?>
    <?php if(isset($_SESSION['upload_error'])): ?>
    <div class="notification notification-error auto-fade"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['upload_error']) ?></div>
    <?php unset($_SESSION['upload_error']); ?>
    <?php endif; ?>
</div>

<div class="main-content">

    <!-- ── Page Header ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><i class="fas fa-user"></i> My Account</div>
            <h1 class="page-header-title">Student Profile</h1>
        </div>
        <span class="result-count">
            <i class="fas fa-id-card"></i>
            <?= htmlspecialchars($student['student_id'] ?? '') ?>
        </span>
    </div>

    <div class="profile-layout">

        <!-- ── Left: Profile Sidebar Card ── -->
        <div class="profile-sidebar-card">
            <div class="profile-sidebar-header">
                <div class="profile-avatar-wrap">
                    <img src="<?= htmlspecialchars($picSrc) ?>" alt="Profile Picture">
                </div>
                <div class="profile-name"><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></div>
                <div class="profile-course-tag"><?= htmlspecialchars(($student['course'] ?? '').' · Yr '.($student['year_level'] ?? '').' · §'.($student['section'] ?? '')) ?></div>
            </div>
            <div class="profile-sidebar-body">
                <div class="profile-info-row"><i class="fas fa-id-badge"></i><span><?= htmlspecialchars($student['student_id'] ?? '') ?></span></div>
                <div class="profile-info-row"><i class="fas fa-envelope"></i><span><?= htmlspecialchars($student['email'] ?? '') ?></span></div>
                <div class="profile-info-row"><i class="fas fa-phone"></i><span><?= htmlspecialchars($student['mobile'] ?? '') ?></span></div>
                <div class="profile-info-row"><i class="fas fa-circle-dot"></i>
                    <span>
                        <?php
                        $s = $student['status'] ?? '';
                        $bc = $s === 'Regular' ? 'badge-green' : ($s === 'Irregular' ? 'badge-yellow' : ($s === 'Probation' ? 'badge-red' : 'badge-gray'));
                        echo '<span class="'.$bc.'">'.$s.'</span>';
                        ?>
                    </span>
                </div>
                <div class="profile-info-row"><i class="fas fa-calendar-alt"></i><span><?= htmlspecialchars(($student['school_year'] ?? '').' · '.($student['semester'] ?? '').' Sem') ?></span></div>
            </div>
            <div class="profile-actions">
                <button onclick="document.getElementById('editProfileModal').style.display='flex'" class="btn" style="justify-content:center;">
                    <i class="fas fa-pencil"></i> Edit Profile
                </button>
                <button onclick="document.getElementById('picUploadModal').style.display='flex'" class="btn btn-outline" style="justify-content:center;">
                    <i class="fas fa-camera"></i> Change Photo
                </button>
            </div>
        </div>

        <!-- ── Right: Details ── -->
        <div style="display:flex;flex-direction:column;gap:20px;">

            <?php
            $infoSections = [
                [
                    'icon' => 'fa-user',
                    'title' => 'Personal Information',
                    'fields' => [
                        ['Full Name', trim(($student['first_name']??'').' '.($student['middle_name']??'').' '.($student['last_name']??'').' '.($student['suffix']??''))],
                        ['Gender', $student['gender']??''],
                        ['Date of Birth', $student['dob']??''],
                        ['Age', $student['age']??''],
                        ['Place of Birth', $student['place_of_birth']??''],
                        ['Civil Status', $student['civil_status']??''],
                        ['Nationality', $student['nationality']??''],
                        ['Religion', $student['religion']??''],
                        ['Address', $student['home_address']??''],
                        ['ZIP Code', $student['zip_code']??''],
                        ['Student Type', $student['student_type']??''],
                    ]
                ],
                [
                    'icon' => 'fa-users',
                    'title' => 'Parent & Guardian Information',
                    'fields' => [
                        ['Father', $student['father_name']??''],
                        ['Mother', $student['mother_name']??''],
                        ['Guardian', $student['guardian_name']??''],
                        ['Contact Number', $student['parent_contact']??''],
                        ['Occupation', $student['parent_occupation']??''],
                        ['Employer', $student['parent_employer']??''],
                    ]
                ],
                [
                    'icon' => 'fa-graduation-cap',
                    'title' => 'Academic Information',
                    'fields' => [
                        ['Program', $student['course']??''],
                        ['Year Level', $student['year_level']??''],
                        ['Section', $student['section']??''],
                        ['School Year', $student['school_year']??''],
                        ['Semester', $student['semester']??''],
                        ['Academic Status', $student['status']??''],
                    ]
                ],
                [
                    'icon' => 'fa-school',
                    'title' => 'Last School Attended',
                    'fields' => [
                        ['School Name', $student['last_school_attended']??''],
                        ['School Address', $student['last_school_address']??''],
                    ]
                ],
                [
                    'icon' => 'fa-heart-pulse',
                    'title' => 'Health Information',
                    'fields' => [
                        ['Blood Type', $student['blood_type']??''],
                        ['Medical Conditions', $student['medical_conditions']??''],
                        ['Allergies', $student['allergies']??''],
                    ]
                ],
            ];
            foreach($infoSections as $sec): ?>
            <div class="section-card">
                <div class="section-card-header">
                    <div class="section-icon"><i class="fas <?= $sec['icon'] ?>"></i></div>
                    <span class="section-card-title"><?= $sec['title'] ?></span>
                </div>
                <div class="info-grid-2">
                    <?php foreach($sec['fields'] as [$label, $val]): ?>
                    <div class="info-field">
                        <label><?= $label ?></label>
                        <p><?= htmlspecialchars($val ?: '—') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div><!-- /.profile-layout -->


    <!-- ── EDIT PROFILE MODAL ── -->
    <div id="editProfileModal" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:700px;">
            <span class="close-modal" onclick="document.getElementById('editProfileModal').style.display='none';document.body.style.overflow=''">&times;</span>
            <h2><i class="fas fa-user-edit"></i> Edit Profile</h2>
            <form method="POST" class="modal-form-wrap">
                <div class="form-grid-2">

                    <h3>📝 Basic Personal Information</h3>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($student['first_name'] ?? '') ?>" placeholder="First Name" required oninput="this.value=this.value.toUpperCase()">
                    <input type="text" name="middle_name" value="<?= htmlspecialchars($student['middle_name'] ?? '') ?>" placeholder="Middle Name" oninput="this.value=this.value.toUpperCase()">
                    <input type="text" name="last_name" value="<?= htmlspecialchars($student['last_name'] ?? '') ?>" placeholder="Last Name" required oninput="this.value=this.value.toUpperCase()">
                    <select name="suffix">
                        <option value="">Select Suffix</option>
                        <option value="Jr." <?= ($student['suffix']??'')=='Jr.'?'selected':'' ?>>Jr.</option>
                        <option value="Sr." <?= ($student['suffix']??'')=='Sr.'?'selected':'' ?>>Sr.</option>
                    </select>
                    <input type="date" name="dob" value="<?= htmlspecialchars($student['dob'] ?? '') ?>" required onchange="calculateAge(this)">
                    <input type="number" name="age" value="<?= htmlspecialchars($student['age'] ?? '') ?>" placeholder="Age" min="1" max="120" required>
                    <input type="text" name="place_of_birth" value="<?= htmlspecialchars($student['place_of_birth'] ?? '') ?>" placeholder="Place of Birth">
                    <select name="gender" required>
                        <option value="Male" <?= ($student['gender']??'')=='Male'?'selected':'' ?>>Male</option>
                        <option value="Female" <?= ($student['gender']??'')=='Female'?'selected':'' ?>>Female</option>
                    </select>
                    <input type="text" name="civil_status" value="<?= htmlspecialchars($student['civil_status'] ?? '') ?>" placeholder="Civil Status">
                    <select name="nationality" required>
                        <?php foreach(['Filipino','American','Canadian','British','Australian','Japanese','Chinese','Other'] as $nat): ?>
                        <option value="<?= $nat ?>" <?= ($student['nationality']??'')==$nat?'selected':'' ?>><?= $nat ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="religion" value="<?= htmlspecialchars($student['religion'] ?? '') ?>" placeholder="Religion" required>
                    <select name="student_type" required>
                        <?php foreach(['New','Transferee','Continuing','Returnee','Cross-enrollee'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($student['student_type']??'')==$t?'selected':'' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="email" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" placeholder="Email" required>
                    <input type="text" name="mobile" value="<?= htmlspecialchars($student['mobile'] ?? '') ?>" placeholder="Mobile (09XXXXXXXXX)" maxlength="11" pattern="\d{11}" required oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    <textarea name="home_address" placeholder="Home Address" required><?= htmlspecialchars($student['home_address'] ?? '') ?></textarea>
                    <input type="text" name="zip_code" value="<?= htmlspecialchars($student['zip_code'] ?? '') ?>" placeholder="ZIP Code" maxlength="4" pattern="\d{4}" required oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                    <select name="status" required>
                        <option value="" disabled>Select Status</option>
                        <?php foreach(['Regular','Irregular','Probation','Graduated','Dropped','Transferred'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($student['status']??'')==$st?'selected':'' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div></div>

                    <h3>👪 Parent / Guardian Information</h3>
                    <input type="text" name="father_name" value="<?= htmlspecialchars($student['father_name'] ?? 'NA') ?>" placeholder="Father's Name" oninput="this.value=this.value.toUpperCase()">
                    <input type="text" name="mother_name" value="<?= htmlspecialchars($student['mother_name'] ?? 'NA') ?>" placeholder="Mother's Name" oninput="this.value=this.value.toUpperCase()">
                    <input type="text" name="guardian_name" value="<?= htmlspecialchars($student['guardian_name'] ?? 'NA') ?>" placeholder="Guardian Name" oninput="this.value=this.value.toUpperCase()">
                    <input type="text" name="parent_occupation" value="<?= htmlspecialchars($student['parent_occupation'] ?? 'NA') ?>" placeholder="Parent Occupation" oninput="this.value=this.value.toUpperCase()">
                    <input type="text" name="parent_employer" value="<?= htmlspecialchars($student['parent_employer'] ?? 'NA') ?>" placeholder="Parent Employer" oninput="this.value=this.value.toUpperCase()">
                    <input type="text" name="emergency_number" value="<?= htmlspecialchars($student['parent_contact'] ?? '') ?>" placeholder="Emergency Contact" maxlength="11" pattern="\d{11}" required oninput="this.value=this.value.replace(/[^0-9]/g,'')">

                    <h3>🏫 Last School Information</h3>
                    <input type="text" name="last_school_attended" value="<?= htmlspecialchars($student['last_school_attended'] ?? '') ?>" placeholder="Last School Attended">
                    <input type="text" name="last_school_address" value="<?= htmlspecialchars($student['last_school_address'] ?? '') ?>" placeholder="Last School Address">

                    <h3>💉 Health Information</h3>
                    <input type="text" name="blood_type" value="<?= htmlspecialchars($student['blood_type'] ?? '') ?>" placeholder="Blood Type (A+, O-, B+...)" maxlength="3" oninput="this.value=this.value.toUpperCase().replace(/[^ABO+-]/g,'')" pattern="^(A|B|AB|O)[+-]$" required>
                    <div></div>
                    <textarea name="medical_conditions" placeholder="Medical Conditions (or NA)"><?= htmlspecialchars($student['medical_conditions'] ?? 'NA') ?></textarea>
                    <textarea name="allergies" placeholder="Allergies (or NA)"><?= htmlspecialchars($student['allergies'] ?? 'NA') ?></textarea>

                </div>

                <div class="modal-actions" style="margin-top:24px;">
                    <button type="submit" name="update_profile" class="btn"><i class="fas fa-save"></i> Save Changes</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('editProfileModal').style.display='none';document.body.style.overflow=''">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── PROFILE PICTURE MODAL ── -->
    <div id="picUploadModal" class="modal">
        <div class="modal-content" style="max-width:440px;text-align:center;">
            <span class="close-modal" onclick="document.getElementById('picUploadModal').style.display='none';document.body.style.overflow=''">&times;</span>
            <h2><i class="fas fa-camera"></i> Change Profile Picture</h2>
            <div style="margin-bottom:20px;">
                <img id="currentPicPreview" src="<?= htmlspecialchars($picSrc) ?>" alt="Current" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:3px solid var(--accent-emerald);box-shadow:var(--shadow-md);">
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <input type="file" name="profile_picture" id="profilePicInput" accept="image/jpeg,image/jpg,image/png" required>
                    <p style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;">JPG or PNG, max 2MB</p>
                </div>
                <div class="modal-actions">
                    <button type="submit" name="update_profile_picture" class="btn"><i class="fas fa-upload"></i> Upload</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('picUploadModal').style.display='none';document.body.style.overflow=''">Cancel</button>
                </div>
            </form>
        </div>
    </div>

</div><!-- /.main-content -->

<script>
function calculateAge(dobInput) {
    const dob = new Date(dobInput.value);
    if (isNaN(dob.getTime())) return;
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
    const ageField = document.querySelector('input[name="age"]');
    if (ageField) ageField.value = Math.max(0, age);
}

document.addEventListener('DOMContentLoaded', function () {
    // ESC closes modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(m => { m.style.display = 'none'; document.body.style.overflow = ''; });
        }
    });
    // Click outside modal closes it
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }
        });
    });
    // Profile pic live preview
    const picInput = document.getElementById('profilePicInput');
    if (picInput) {
        picInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = ev => { document.getElementById('currentPicPreview').src = ev.target.result; };
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>
</body>
</html>v