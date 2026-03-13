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
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="../images/622685015_925666030131412_6886851389087569993_n.jpg" alt="School Logo" style="width: 80px; display: block; margin: 40px auto 15px auto;border-radius: 5px; animation: float 3s ease-in-out infinite;">
<h2 style="margin-top: 5px;"><i class="fas fa-graduation-cap"></i>Student's Portal</h2>

        </div>

        <div class="menu">
            <a href="students_dashboard.php" class="<?php echo ($current_page == 'students_dashboard.php') ? 'active' : ''; ?>">My Dashboard🏠</a>
            <a href="students_profile.php" class="<?php echo ($current_page == 'students_profile.php') ? 'active' : ''; ?>">My Profile👤</a>
            <a href="students_grades.php" class="<?php echo ($current_page == 'students_grades.php') ? 'active' : ''; ?>">My Grades📊</a>
            <a href="students_classSchedule.php" class="<?php echo ($current_page == 'students_classSchedule.php') ? 'active' : ''; ?>">My Class Schedules🗓</a>
            <a href="students_subjects&teachers.php" class="<?php echo ($current_page == 'students_subjects&teachers.php') ? 'active' : ''; ?>">Subjects&Teachers📚</a>
            <a href="students_attendance.php" class="<?php echo ($current_page == 'students_attendance.php') ? 'active' : ''; ?>">My Attendance📋</a>
            <a href="students_announcements.php" class="<?php echo ($current_page == 'students_announcements.php') ? 'active' : ''; ?>">Announcements 📢</a>
            <a href="students_tasks.php" class="<?php echo ($current_page == 'students_tasks.php') ? 'active' : ''; ?>">My Tasks📝</a>
            <a href="#" class="logout <?php echo ($current_page == 'logout') ? 'active' : ''; ?>" onclick="showLogoutConfirmation(event)">Logout🚪?</a>
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