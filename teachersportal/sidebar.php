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

// ── Unread task submissions count (teacher POV) ───────────────────────────
// Uses t.teacher_id — same column tasks.php uses — so the count always matches.
$unread_submissions_count = 0;
if (!$is_admin && isset($_SESSION['teacher_id'])) {
    global $conn;
    $teacher_id = $_SESSION['teacher_id'];

    $tsq = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM task_submissions ts
        JOIN tasks t ON ts.task_id = t.id
        WHERE t.teacher_id = ?
          AND ts.teacher_read = 0
    ");
    $tsq->bind_param("i", $teacher_id);
    $tsq->execute();
    $unread_submissions_count = (int)($tsq->get_result()->fetch_assoc()['cnt'] ?? 0);
}
// ──────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ── Notification badge ── */
        .notif-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e53e3e;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            margin-left: auto;
            line-height: 1;
            flex-shrink: 0;
        }
        /* Make sidebar links flex so badge sits on the right */
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar a .fas {
            width: 16px;
            flex-shrink: 0;
        }
        .sidebar a span.link-label {
            flex: 1;
        }
    </style>
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

        <a href="<?= BASE_URL ?>teachersportal/dashboard.php" class="<?= $current == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i>
            <span class="link-label">Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>teachersportal/students.php" class="<?= $current == 'students.php' ? 'active' : '' ?>">
            <i class="fas fa-user-graduate"></i>
            <span class="link-label">Students</span>
        </a>

        <?php if ($is_admin): ?>
        <a href="<?= BASE_URL ?>teachersportal/teachers_list.php" class="<?= $current == 'teachers_list.php' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard-teacher"></i>
            <span class="link-label">Teachers List</span>
        </a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>teachersportal/grades.php" class="<?= $current == 'grades.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span class="link-label">Grades</span>
        </a>

        <?php if (!$is_admin): ?>
        <a href="<?= BASE_URL ?>teachersportal/attendance.php" class="<?= $current == 'attendance.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i>
            <span class="link-label">Attendance</span>
        </a>

        <!-- Tasks with unread submissions badge -->
        <a href="<?= BASE_URL ?>teachersportal/tasks.php" class="<?= $current == 'tasks.php' ? 'active' : '' ?>">
            <i class="fas fa-tasks"></i>
            <span class="link-label">Tasks</span>
            <?php if ($unread_submissions_count > 0): ?>
                <span class="notif-badge">
                    <?php echo $unread_submissions_count > 99 ? '99+' : $unread_submissions_count; ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>teachersportal/subjects.php" class="<?= $current == 'subjects.php' ? 'active' : '' ?>">
            <i class="fas fa-book"></i>
            <span class="link-label">Subjects &amp; Classes</span>
        </a>

        <a href="<?= BASE_URL ?>teachersportal/schedule.php" class="<?= $current == 'schedule.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i>
            <span class="link-label">Schedules</span>
        </a>

        <a href="<?= BASE_URL ?>teachersportal/announcements.php" class="<?= $current == 'announcements.php' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i>
            <span class="link-label">Announcements</span>
        </a>

        <?php if ($is_admin): ?>
        <a href="<?= BASE_URL ?>admin/manage_school_year.php" class="<?= $current == 'manage_school_year.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i>
            <span class="link-label">Manage School Year</span>
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

        window.onclick = function(event) {
            if (event.target === modal) closeModal();
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') closeModal();
        });

        // Hamburger Menu Toggle
        const hamburgerBtn   = document.getElementById('hamburgerBtn');
        const sidebar        = document.getElementById('sidebar');
        const mobileBackdrop = document.getElementById('mobileBackdrop');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            hamburgerBtn.classList.toggle('active');
            hamburgerBtn.classList.toggle('hidden');
            mobileBackdrop.classList.toggle('active');
        }

        hamburgerBtn.addEventListener('click', toggleSidebar);
        mobileBackdrop.addEventListener('click', toggleSidebar);

        window.addEventListener('resize', function() {
            if (window.innerWidth > 576) {
                sidebar.classList.remove('active');
                hamburgerBtn.classList.remove('active');
                mobileBackdrop.classList.remove('active');
            }
        });

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