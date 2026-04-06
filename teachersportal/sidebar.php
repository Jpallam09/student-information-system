<?php
require_once dirname(__DIR__) . '/config/paths.php';
$current = basename($_SERVER['PHP_SELF']);

// Default back_url if not set
if (!isset($back_url)) {
   $back_url = BASE_URL . "Accesspage/teacher_login.php";
}

// Check if user is admin
$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <!-- Hamburger Menu Button (Mobile) -->
    <button class="hamburger-btn" id="hamburgerBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Mobile Backdrop -->
    <div class="mobile-backdrop" id="mobileBackdrop"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
    <a href="#" class="back-arrow logout-btn" onclick="showLogoutConfirmation(event)">
        <i class="fas fa-sign-out-alt"></i>
    </a>
       <img src="<?= asset('images/622685015_925666030131412_6886851389087569993_n.jpg') ?>" 
     alt="School Logo" 
     style="width: 80px; display: block; margin: 40px auto 15px auto; border-radius: 5px;">
    <?php if ($is_admin): ?>
    <h2 style="margin-top: 5px;"><i class="fas fa-user-shield"></i> Admin's Portal</h2>
    <?php else: ?>
    <h2 style="margin-top: 5px;"><i class="fas fa-chalkboard-teacher"></i> Teacher's Portal</h2>
    <?php endif; ?>

   <a href="<?= BASE_URL ?>teachersportal/dashboard.php" class="<?= $current=='dashboard.php'?'active':'' ?>">
        <i class="fas fa-th-large"></i> Dashboard
    </a>

    <a href="<?= BASE_URL ?>teachersportal/students.php" class="<?= $current=='students.php'?'active':'' ?>">
        <i class="fas fa-user-graduate"></i> Students
    </a>

    <?php if ($is_admin): ?>
    <a href="<?= BASE_URL ?>teachersportal/teachers_list.php" class="<?= $current=='teachers_list.php'?'active':'' ?>">
        <i class="fas fa-chalkboard-teacher"></i> Teachers List
    </a>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>teachersportal/grades.php" class="<?= $current=='grades.php'?'active':'' ?>">
        <i class="fas fa-chart-line"></i> Grades
    </a>

    <?php if(!$is_admin): ?>
    <a href="<?= BASE_URL ?>teachersportal/attendance.php" class="<?= $current=='attendance.php'?'active':'' ?>">
        <i class="fas fa-calendar-check"></i> Attendance
    </a>

    <a href="<?= BASE_URL ?>teachersportal/tasks.php" class="<?= $current=='tasks.php'?'active':'' ?>">
        <i class="fas fa-tasks"></i> Tasks
    </a>
<?php endif; ?>

    <a href="<?= BASE_URL ?>teachersportal/subjects.php" class="<?= $current=='subjects.php'?'active':'' ?>">
        <i class="fas fa-book"></i> Subjects & Classes
    </a>

    <a href="<?= BASE_URL ?>teachersportal/schedule.php" class="<?= $current=='schedule.php'?'active':'' ?>">
        <i class="fas fa-calendar-alt"></i> Schedules
    </a>

    <a href="<?= BASE_URL ?>teachersportal/announcements.php" class="<?= $current=='announcements.php'?'active':'' ?>">
        <i class="fas fa-bullhorn"></i> Announcements
    </a>

    <?php if ($is_admin): ?>
    <a href="<?= BASE_URL ?>admin/manage_school_year.php" class="<?= $current=='manage_school_year.php'?'active':'' ?>">
        <i class="fas fa-calendar-alt"></i> Manage School Year
    </a>
    <?php endif; ?>
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
            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        function confirmLogout() {
            window.location.href = '<?= BASE_URL ?>teachersportal/logout.php';
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

        // Hamburger Menu Toggle
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const mobileBackdrop = document.getElementById('mobileBackdrop');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            hamburgerBtn.classList.toggle('active');
            hamburgerBtn.classList.toggle('hidden');
            mobileBackdrop.classList.toggle('active');
        }

        hamburgerBtn.addEventListener('click', toggleSidebar);
        mobileBackdrop.addEventListener('click', toggleSidebar);

        // Close sidebar on window resize (desktop)
        window.addEventListener('resize', function() {
            if (window.innerWidth > 576) {
                sidebar.classList.remove('active');
                hamburgerBtn.classList.remove('active');
                mobileBackdrop.classList.remove('active');
            }
        });

        // Auto-close sidebar on mobile link click
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 576 && e.target.closest('.sidebar a[href]')) {
                sidebar.classList.remove('active');
                hamburgerBtn.classList.remove('active');
                mobileBackdrop.classList.remove('active');
            }
        });
    </script>
</body>
</html>
