<?php
// session_start();
// if(!isset($_SESSION['student_id'])) {
//     header("Location: ../Accesspage/student_login.php");
//     exit();
// }

// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../css/studentportal.css">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
           <a href="#" class="back-arrow logout-btn" onclick="showLogoutConfirmation(event)">
        <i class="fas fa-sign-out-alt"></i>
    </a>
        <div class="sidebar-header">
            <img src="../images/622685015_925666030131412_6886851389087569993_n.jpg" alt="School Logo" style="width: 80px; display: block; margin: 40px auto 15px auto;border-radius: 5px; animation: float 3s ease-in-out infinite;">
<h2 style="margin-top: 5px;"><i class="fas fa-graduation-cap"></i>Student's Portal</h2>
<p class="sidebar-sub">
    <?php 
    include_once '../config/current_school_year.php'; 
    $active_year = getActiveSchoolYear($conn) ?? 'Academic Year Not Set'; 
    $active_sem = getActiveSemester($conn) ?? ''; 
    
    // Show enrolled vs active if inactive
    if (isset($_SESSION['student_id']) && isset($_SESSION['inactive_enrollment']) && $_SESSION['inactive_enrollment']) {
        $student_q = mysqli_query($conn, "SELECT school_year, semester FROM students WHERE id = {$_SESSION['student_id']}");
        if ($student_row = mysqli_fetch_assoc($student_q)) {
            echo 'Enrolled: ' . htmlspecialchars($student_row['school_year'] . ' ' . $student_row['semester'] . ' Sem') . ' | ';
        }
    }
    ?>
    Active: <?php echo htmlspecialchars($active_year); ?> - <?php echo htmlspecialchars($active_sem); ?>
</p>

        </div>

        <div class="menu">
            <a href="/STUDENT%20INFO/studentsportal/students_dashboard.php" class="<?= $current=='students_dashboard.php'?'active':'' ?>">
        <i class="fas fa-th-large"></i> Dashboard
    </a>
            <a href="/STUDENT%20INFO/studentsportal/students_profile.php" class="<?= $current=='students_profile.php'?'active':'' ?>">
        <i class="fas fa-user"></i> My Profile
    </a>
            <a href="/STUDENT%20INFO/studentsportal/students_grades.php" class="<?= $current=='students_grades.php'?'active':'' ?>">
        <i class="fas fa-chart-line"></i> My Grades
    </a>
            <a href="/STUDENT%20INFO/studentsportal/students_classSchedule.php" class="<?= $current=='students_classSchedule.php'?'active':'' ?>">
        <i class="fas fa-calendar-alt"></i> My Class Schedules
    </a>
            <a href="/STUDENT%20INFO/studentsportal/students_subjects&teachers.php" class="<?= $current=='students_subjects&teachers.php'?'active':'' ?>">
        <i class="fas fa-book"></i> Subjects & Teachers
    </a>
            <a href="/STUDENT%20INFO/studentsportal/students_attendance.php" class="<?= $current=='students_attendance.php'?'active':'' ?>">
        <i class="fas fa-calendar-check"></i> My Attendance
    </a>
            <a href="/STUDENT%20INFO/studentsportal/students_announcements.php" class="<?= $current=='students_announcements.php'?'active':'' ?>">
        <i class="fas fa-bullhorn"></i> Announcements
    </a>
            <a href="/STUDENT%20INFO/studentsportal/students_tasks.php" class="<?= $current=='students_tasks.php'?'active':'' ?>">
        <i class="fas fa-tasks"></i> My Tasks
    </a>
        </div>
    </div>

    <!-- Logout Modal -->
    <div id="logoutModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to logout?</p>
            <div class="modal-buttons">
                <button class="btn-logout" onclick="confirmLogout()">Yes, Logout</button>
                <button class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('logoutModal');

       function showLogoutConfirmation(event) {
    event.preventDefault();
    event.stopPropagation();
    modal.classList.add('show'); // just add class
}

function closeModal() {
    modal.classList.remove('show'); // remove class
    // no need for setTimeout or display manipulation
}

function confirmLogout() {
    window.location.href = '../Accesspage/student_login.php';
}

        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>