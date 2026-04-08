<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once CONFIG_PATH . 'database.php';
require_once CONFIG_PATH . 'teacher_filter.php';

// ================== CHECK LOGIN ==================
if (!isset($_SESSION['teacher_id'])) {
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

// ================== ADMIN CHECK ==================
$admin_types = ['Seeder', 'Administrator'];
$is_admin    = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

// Block admin access
if ($is_admin) {
    echo "<div style='text-align:center; margin-top:50px; font-family:Arial,sans-serif;'>";
    echo "<h2 style='color:#dc3545;'><i class='fas fa-exclamation-triangle'></i> Access Denied</h2>";
    echo "<p>Attendance Management is only available for regular teachers.</p>";
    echo "<a href='" . BASE_URL . "teachersportal/dashboard.php' style='display:inline-block;margin-top:20px;padding:10px 20px;background:#0A91AB;color:white;text-decoration:none;border-radius:5px;'>Go Back to Dashboard</a>";
    echo "</div>";
    exit();
}

// ================== BUILD TEACHER FILTERS ==================
// getCombinedYearFilter applies BOTH the session-based auto filter (assigned year
// levels from $_SESSION['teacher_year_levels']) AND the optional manual GET filter.
// This is the key fix: the old code used only getYearLevelFilter() which only
// read $_GET['year_level'] and ignored the session assignment entirely.
$y_params = []; $y_types = '';
$s_params = []; $s_types = '';
$teacher_year_filter    = getCombinedYearFilter('year_level', $y_params, $y_types);
$teacher_section_filter = getCombinedSectionFilter('section',  $s_params, $s_types);

// Dropdown year list — only the years the teacher is assigned to
$teacher_year_levels = getTeacherDropdownYears();

// ================== COURSE FROM SESSION ==================
$selected_course = $_SESSION['teacher_course'] ?? '';
if (empty($selected_course)) {
    echo "Course not assigned to this teacher. Contact admin.";
    exit();
}

$teacher_id      = $_SESSION['teacher_id'];
$allowed_courses = ['BSIT','BSED','BAT','BTVTED'];
if (!in_array(strtoupper($selected_course), $allowed_courses)) {
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="' . BASE_URL . 'teachersportal/chooseSub.php">← Go Back</a>';
    exit();
}

// ================== MANUAL FILTER SELECTIONS (GET) ==================
$selected_year    = $_GET['year_level'] ?? '';
$selected_section = $_GET['section']    ?? '';
$date             = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d');

// ================== FETCH SECTIONS ==================
// Build the section dropdown from students who pass the year filter.
// We use a plain query here because $teacher_year_filter may contain
// literal IN() values (not bound params) from getAutoTeacherYearFilter().
$safe_course = mysqli_real_escape_string($conn, $selected_course);
$sec_where   = "WHERE course = '$safe_course' $teacher_year_filter";
if ($selected_year) {
    $safe_year = mysqli_real_escape_string($conn, $selected_year);
    $sec_where .= " AND year_level = '$safe_year'";
}
$sec_result = mysqli_query($conn, "SELECT DISTINCT section FROM students $sec_where ORDER BY section");
$sections   = [];
while ($row = mysqli_fetch_assoc($sec_result)) {
    $sections[] = $row['section'];
}

// ================== SAVE ATTENDANCE ==================
$message = '';
if (isset($_POST['save_attendance'])) {
    foreach ($_POST['status'] as $raw_student_id => $status) {
        $student_id = intval($raw_student_id);

        $stmt_check = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND `date` = ?");
        $stmt_check->bind_param("is", $student_id, $date);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        if ($check_result->num_rows > 0) {
            $stmt_update = $conn->prepare("UPDATE attendance SET status = ? WHERE student_id = ? AND `date` = ?");
            $stmt_update->bind_param("sis", $status, $student_id, $date);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO attendance (student_id, `date`, status) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("iss", $student_id, $date, $status);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
    $message = "Attendance saved for $date";
}

// ================== FETCH STUDENTS ==================
// Build a plain query so the literal IN() clauses from getCombinedYearFilter /
// getCombinedSectionFilter are injected correctly (they are already escaped
// internally by addslashes() inside teacher_filter.php).
$stu_where = "WHERE course = '$safe_course' $teacher_year_filter $teacher_section_filter";
if ($selected_year) {
    $safe_year = mysqli_real_escape_string($conn, $selected_year);
    $stu_where .= " AND year_level = '$safe_year'";
}
if ($selected_section) {
    $safe_section = mysqli_real_escape_string($conn, $selected_section);
    $stu_where   .= " AND section = '$safe_section'";
}

$students_result = mysqli_query($conn,
    "SELECT id, student_id, first_name, last_name, section, year_level
     FROM students $stu_where
     ORDER BY section, last_name"
);

// ================== FETCH CURRENT ATTENDANCE ==================
$current_attendance = [];
$stmt_att = $conn->prepare("SELECT student_id, status FROM attendance WHERE `date` = ?");
$stmt_att->bind_param("s", $date);
$stmt_att->execute();
$att_result = $stmt_att->get_result();
while ($row = $att_result->fetch_assoc()) {
    $current_attendance[$row['student_id']] = $row['status'];
}
$stmt_att->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?= htmlspecialchars($selected_course) ?></title>
     <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include path('teachersportal/sidebar.php'); ?>

<div class="content">
    <h1><i class="fas fa-calendar-check"></i> <?= htmlspecialchars($selected_course) ?> Attendance</h1>

    <?php if ($message): ?>
        <div class="message success" id="flashMessage">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
        </div>
        <script>
            setTimeout(() => {
                const flash = document.getElementById('flashMessage');
                if (flash) {
                    flash.style.transition = 'opacity 0.5s';
                    flash.style.opacity = '0';
                    setTimeout(() => flash.remove(), 500);
                }
            }, 3000);
        </script>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filter-group">
        <form method="GET" style="display:flex; gap:1rem; align-items:center;">
            <select name="year_level" onchange="this.form.submit()">
                <option value="">All Year Levels</option>
                <?php foreach ($teacher_year_levels as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= $selected_year == $year ? 'selected' : '' ?>>
                        <?= htmlspecialchars($year) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="section" onchange="this.form.submit()">
                <option value="">All Sections</option>
                <?php foreach ($sections as $sec): ?>
                    <option value="<?= htmlspecialchars($sec) ?>" <?= $selected_section == $sec ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sec) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">

            <?php if ($selected_year || $selected_section): ?>
                <a href="attendance.php" class="refresh-btn" title="Clear Filters">↺</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Attendance Table -->
    <div class="table-container">
        <form method="POST">
            <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Year Level</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                    <?php while ($student = mysqli_fetch_assoc($students_result)):
                        $status = $current_attendance[$student['id']] ?? 'present';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($student['student_id']) ?></td>
                            <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                            <td><?= htmlspecialchars($student['year_level']) ?></td>
                            <td><?= htmlspecialchars($student['section']) ?></td>
                            <td>
                                <div class="attendance-buttons" data-student="<?= $student['id'] ?>">
                                    <button type="button" class="status-btn <?= $status == 'present' ? 'active' : '' ?>" data-value="present">Present</button>
                                    <button type="button" class="status-btn <?= $status == 'absent'  ? 'active' : '' ?>" data-value="absent">Absent</button>
                                    <input type="hidden" name="status[<?= $student['id'] ?>]" value="<?= $status ?>">
                                </div>
                            </td>
                            <td style="text-align:center; color:#065471; font-weight:600;">
                                <?= date("M d, Y", strtotime($date)) ?>
                                <?php if ($date == date('Y-m-d')): ?>
                                    <span class="badge" style="background:#10b981;color:white;padding:2px 6px;border-radius:4px;margin-left:0.3rem;">Today</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2rem;">
                            <i class="fas fa-users" style="font-size:2rem; color:var(--text-muted); display:block; margin-bottom:0.5rem;"></i>
                            <p>No students found for the selected filters.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                <div style="margin-top:1.5rem; text-align:right;">
                    <button type="submit" name="save_attendance" class="btn">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.attendance-buttons').forEach(container => {
    const buttons     = container.querySelectorAll('.status-btn');
    const hiddenInput = container.querySelector('input[type="hidden"]');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            hiddenInput.value = btn.dataset.value;
        });
    });
});
</script>
</body>
</html>