<?php
require_once dirname(__DIR__) . '/config/paths.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

// Get current page filename
$current = basename($_SERVER['PHP_SELF']);

// ── Unread announcement count ──────────────────────────────────────────────
$unread_count = 0;
if (isset($_SESSION['student_id'])) {
    global $conn;
    $sid = $_SESSION['student_id'];

    $s = $conn->prepare("SELECT course, year_level, section FROM students WHERE id = ?");
    $s->bind_param("i", $sid);
    $s->execute();
    $sdata = $s->get_result()->fetch_assoc();

    if ($sdata) {
        $scourse = strtoupper(trim($sdata['course']));
        $syear   = $sdata['year_level'];
        $ssec    = $sdata['section'];

        $uq = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM announcements a
            JOIN teachers t ON a.teacher_id = t.id
            WHERE (
                (UPPER(TRIM(a.course_id)) = ?
                AND ((a.year_level = ? OR a.year_level = 'All')
                AND (a.section = ? OR a.section = 'All')))
                OR (t.teacher_type IN ('Seeder', 'Administrator'))
            )
            AND a.id NOT IN (
                SELECT announcement_id
                FROM student_seen_announcements
                WHERE student_id = ?
            )
        ");
        $uq->bind_param("sssi", $scourse, $syear, $ssec, $sid);
        $uq->execute();
        $unread_count = $uq->get_result()->fetch_assoc()['cnt'] ?? 0;
    }
}
// ──────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/studentportal.css'); ?>">
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
        .menu a {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .menu a .fa,
        .menu a .fas {
            width: 16px;
            flex-shrink: 0;
        }
        .menu a span.link-label {
            flex: 1;
        }
    </style>
</head>
<body>

    <!-- Hamburger Menu Button (Mobile) -->
    <button class="hamburger-btn" id="hamburgerBtn" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Mobile Backdrop -->
    <div class="mobile-backdrop" id="mobileBackdrop"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">

        <a href="#" class="back-arrow logout-btn" onclick="showLogoutConfirmation(event)">
            <i class="fas fa-sign-out-alt"></i>
        </a>

        <div class="sidebar-header">
            <img src="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>"
                 alt="School Logo"
                 style="width: 70px; display: block; margin: 40px auto 15px auto; border-radius: 5px; animation: float 3s ease-in-out infinite;">
            <h2 style="margin-top: 5px;"><i class="fas fa-graduation-cap"></i> Student's Portal</h2>
            <p class="sidebar-sub">
                <?php
                include_once PROJECT_ROOT . '/config/current_school_year.php';
                $active_year = getActiveSchoolYear($conn) ?? 'Academic Year Not Set';
                $active_sem  = getActiveSemester($conn) ?? '';

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

            <a href="<?php echo BASE_URL; ?>studentsportal/students_dashboard.php"
               class="<?= $current == 'students_dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i>
                <span class="link-label">Dashboard</span>
            </a>

            <a href="<?php echo BASE_URL; ?>studentsportal/students_profile.php"
               class="<?= $current == 'students_profile.php' ? 'active' : '' ?>">
                <i class="fas fa-user"></i>
                <span class="link-label">My Profile</span>
            </a>

            <a href="<?php echo BASE_URL; ?>studentsportal/students_grades.php"
               class="<?= $current == 'students_grades.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i>
                <span class="link-label">My Grades</span>
            </a>

            <a href="<?php echo BASE_URL; ?>studentsportal/students_classSchedule.php"
               class="<?= $current == 'students_classSchedule.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i>
                <span class="link-label">My Class Schedules</span>
            </a>

            <a href="<?php echo BASE_URL; ?>studentsportal/students_subjects&teachers.php"
               class="<?= $current == 'students_subjects&teachers.php' ? 'active' : '' ?>">
                <i class="fas fa-book"></i>
                <span class="link-label">Subjects &amp; Teachers</span>
            </a>

            <a href="<?php echo BASE_URL; ?>studentsportal/students_attendance.php"
               class="<?= $current == 'students_attendance.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i>
                <span class="link-label">My Attendance</span>
            </a>

            <!-- Announcements with unread badge -->
            <a href="<?php echo BASE_URL; ?>studentsportal/students_announcements.php"
               class="<?= $current == 'students_announcements.php' ? 'active' : '' ?>">
                <i class="fas fa-bullhorn"></i>
                <span class="link-label">Announcements</span>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge">
                        <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="<?php echo BASE_URL; ?>studentsportal/students_tasks.php"
               class="<?= $current == 'students_tasks.php' ? 'active' : '' ?>">
                <i class="fas fa-tasks"></i>
                <span class="link-label">My Tasks</span>
            </a>

            <a href="<?php echo BASE_URL; ?>studentsportal/assessment.php"
               class="<?= $current == 'assessment.php' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span class="link-label">Assessment</span>
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
            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        function confirmLogout() {
            window.location.href = '<?php echo BASE_URL; ?>studentsportal/student_logout_process.php';
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