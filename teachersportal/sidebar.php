<?php
$current = basename($_SERVER['PHP_SELF']);

// Default back_url if not set
if (!isset($back_url)) {
    $back_url = "../Accesspage/teacher_login.php";
}

// Check if user is admin
$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
?>
<div class="sidebar">
    <a href="<?= $back_url ?>" class="back-arrow">
        <i class="fas fa-arrow-left"></i>
    </a>
       <img src="../images/622685015_925666030131412_6886851389087569993_n.jpg" alt="School Logo" style="width: 80px; display: block; margin: 40px auto 15px auto;border-radius: 5px; animation: float 3s ease-in-out infinite;">
    <?php if ($is_admin): ?>
    <h2 style="margin-top: 5px;"><i class="fas fa-user-shield"></i> Admin's Portal</h2>
    <?php else: ?>
    <h2 style="margin-top: 5px;"><i class="fas fa-chalkboard-teacher"></i> Teacher's Portal</h2>
    <?php endif; ?>
    <p class="sidebar-sub" style="margin-top: 5px;">Academic Year 2025-2026</p>


    <a href="/STUDENT%20INFO/teachersportal/dashboard.php" class="<?= $current=='dashboard.php'?'active':'' ?>">
        <i class="fas fa-th-large"></i> Dashboard
    </a>

    <a href="/STUDENT%20INFO/teachersportal/students.php" class="<?= $current=='students.php'?'active':'' ?>">
        <i class="fas fa-user-graduate"></i> Students
    </a>

    <a href="/STUDENT%20INFO/teachersportal/grades.php" class="<?= $current=='grades.php'?'active':'' ?>">
        <i class="fas fa-chart-line"></i> Grades
    </a>

    <?php if(!$is_admin): ?>
    <a href="/STUDENT%20INFO/teachersportal/attendance.php" class="<?= $current=='attendance.php'?'active':'' ?>">
        <i class="fas fa-calendar-check"></i> Attendance
    </a>

    <a href="/STUDENT%20INFO/task/task.php" class="<?= $current=='task.php'?'active':'' ?>">
        <i class="fas fa-tasks"></i> Tasks
    </a>
    <?php endif; ?>

    <a href="/STUDENT%20INFO/teachersportal/subjects.php" class="<?= $current=='subjects.php'?'active':'' ?>">
        <i class="fas fa-book"></i> Subjects & Classes
    </a>

    <a href="/STUDENT%20INFO/teachersportal/schedule.php" class="<?= $current=='schedule.php'?'active':'' ?>">
        <i class="fas fa-calendar-alt"></i> Schedules
    </a>

    <a href="/STUDENT%20INFO/teachersportal/announcements.php" class="<?= $current=='announcements.php'?'active':'' ?>">
        <i class="fas fa-bullhorn"></i> Announcements
    </a>
</div>
