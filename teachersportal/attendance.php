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

if ($is_admin) {
    echo "<div style='text-align:center;margin-top:50px;font-family:DM Sans,sans-serif;'>";
    echo "<h2 style='color:#f43f5e;'><i class='fas fa-exclamation-triangle'></i> Access Denied</h2>";
    echo "<p>Attendance Management is only available for regular teachers.</p>";
    echo "<a href='" . BASE_URL . "teachersportal/dashboard.php' style='display:inline-block;margin-top:20px;padding:10px 24px;background:#2563eb;color:white;text-decoration:none;border-radius:50px;font-weight:700;font-size:13px;letter-spacing:0.06em;text-transform:uppercase;'>Go Back to Dashboard</a>";
    echo "</div>";
    exit();
}

// ================== BUILD TEACHER FILTERS ==================
$y_params = []; $y_types = '';
$s_params = []; $s_types = '';
$teacher_year_filter    = getCombinedYearFilter('year_level', $y_params, $y_types);
$teacher_section_filter = getCombinedSectionFilter('section',  $s_params, $s_types);
$teacher_year_levels    = getTeacherDropdownYears();

// ================== COURSE FROM SESSION ==================
$selected_course = $_SESSION['teacher_course'] ?? '';
if (empty($selected_course)) { echo "Course not assigned to this teacher. Contact admin."; exit(); }

$teacher_id      = $_SESSION['teacher_id'];
$allowed_courses = ['BSIT','BSED','BAT','BTVTED'];
if (!in_array(strtoupper($selected_course), $allowed_courses)) {
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="' . BASE_URL . 'teachersportal/chooseSub.php">← Go Back</a>';
    exit();
}

// ================== MANUAL FILTER SELECTIONS ==================
$selected_year    = $_GET['year_level'] ?? '';
$selected_section = $_GET['section']    ?? '';
$date             = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d');

// ================== FETCH SECTIONS ==================
$safe_course = mysqli_real_escape_string($conn, $selected_course);
$sec_query   = "SELECT DISTINCT section FROM students WHERE course = ? " . getAutoTeacherYearFilter('year_level');

if ($selected_year) {
    $safe_year = mysqli_real_escape_string($conn, $selected_year);
    $sec_query .= " AND year_level = ?";
    $stmt_sec   = $conn->prepare($sec_query);
    $stmt_sec->bind_param("ss", $safe_course, $safe_year);
} else {
    $stmt_sec = $conn->prepare($sec_query);
    $stmt_sec->bind_param("s", $safe_course);
}
$stmt_sec->execute();
$sec_result = $stmt_sec->get_result();
$stmt_sec->close();

$sections = [];
while ($row = $sec_result->fetch_assoc()) { $sections[] = $row['section']; }

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
            $stmt_update->execute(); $stmt_update->close();
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO attendance (student_id, `date`, status) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("iss", $student_id, $date, $status);
            $stmt_insert->execute(); $stmt_insert->close();
        }
        $stmt_check->close();
    }
    $message = "Attendance recorded for " . date("F j, Y", strtotime($date));
}

// ================== FETCH STUDENTS ==================
$safe_year    = $selected_year    ? mysqli_real_escape_string($conn, $selected_year)    : '';
$safe_section = $selected_section ? mysqli_real_escape_string($conn, $selected_section) : '';

$stu_base = "SELECT id, student_id, first_name, last_name, section, year_level FROM students 
             WHERE course = ? " . getAutoTeacherYearFilter('year_level') . getAutoTeacherSectionFilter('section');

$params = [$safe_course];
$types  = 's';

if ($selected_year)    { $stu_base .= " AND year_level = ?"; $params[] = $safe_year;    $types .= 's'; }
if ($selected_section) { $stu_base .= " AND section = ?";   $params[] = $safe_section; $types .= 's'; }
$stu_base .= " ORDER BY section, last_name";

$stmt_stu = $conn->prepare($stu_base);
$stmt_stu->bind_param($types, ...$params);
$stmt_stu->execute();
$students_result = $stmt_stu->get_result();
$stmt_stu->close();

// ================== FETCH CURRENT ATTENDANCE ==================
$current_attendance = [];
$stmt_att = $conn->prepare("SELECT student_id, status FROM attendance WHERE `date` = ?");
$stmt_att->bind_param("s", $date);
$stmt_att->execute();
$att_result = $stmt_att->get_result();
while ($row = $att_result->fetch_assoc()) { $current_attendance[$row['student_id']] = $row['status']; }
$stmt_att->close();

// Count totals for summary
$total_students_count = $students_result->num_rows;
$present_count  = 0;
$absent_count   = 0;
foreach ($current_attendance as $sid => $status) {
    if ($status === 'present') $present_count++;
    else $absent_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance — <?= htmlspecialchars($selected_course) ?></title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include path('teachersportal/sidebar.php'); ?>

<div class="content">

    <!-- PAGE HEADER -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><?= htmlspecialchars($selected_course) ?> Portal</div>
            <h1 class="page-header-title">Attendance</h1>
        </div>
        <div class="<?= ($date == date('Y-m-d')) ? 'date-banner today' : 'date-banner' ?>">
            <i class="fas fa-calendar-day"></i>
            <?= date("l, F j Y", strtotime($date)) ?>
            <?php if ($date == date('Y-m-d')): ?>&nbsp;· Today<?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="message success" id="flashMessage">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
    </div>
    <script>
        setTimeout(() => {
            const flash = document.getElementById('flashMessage');
            if (flash) { flash.style.transition='opacity 0.5s'; flash.style.opacity='0'; setTimeout(()=>flash.remove(),500); }
        }, 3000);
    </script>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <div class="attendance-summary">
        <div class="att-mini-card total">
            <span class="label"><i class="fas fa-users" style="margin-right:4px;"></i>Total</span>
            <span class="value"><?= $total_students_count ?></span>
        </div>
        <div class="att-mini-card present">
            <span class="label"><i class="fas fa-check-circle" style="margin-right:4px;"></i>Present</span>
            <span class="value"><?= $present_count ?></span>
        </div>
        <div class="att-mini-card absent">
            <span class="label"><i class="fas fa-times-circle" style="margin-right:4px;"></i>Absent</span>
            <span class="value"><?= $absent_count ?></span>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-group">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;width:100%;">
            <select name="year_level" onchange="this.form.submit()" style="border-radius:50px;">
                <option value="">All Year Levels</option>
                <?php foreach ($teacher_year_levels as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= $selected_year==$year?'selected':'' ?>>
                        <?= htmlspecialchars($year) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="section" onchange="this.form.submit()" style="border-radius:50px;">
                <option value="">All Sections</option>
                <?php foreach ($sections as $sec): ?>
                    <option value="<?= htmlspecialchars($sec) ?>" <?= $selected_section==$sec?'selected':'' ?>>
                        <?= htmlspecialchars($sec) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date" class="date-picker" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">

            <?php if ($selected_year || $selected_section): ?>
                <a href="attendance.php" class="refresh-btn" title="Clear Filters">
                    <i class="fas fa-rotate-right"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ATTENDANCE TABLE -->
    <div class="table-container">
        <form method="POST">
            <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">

            <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
            <!-- Quick mark all -->
            <div class="quick-actions" style="padding:16px 20px 0;">
                <span style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-bolt"></i> Quick Mark:
                </span>
                <button type="button" class="quick-btn present-btn" onclick="markAll('present')">
                    <i class="fas fa-check"></i> All Present
                </button>
                <button type="button" class="quick-btn absent-btn" onclick="markAll('absent')">
                    <i class="fas fa-times"></i> All Absent
                </button>
            </div>
            <?php endif; ?>

            <table style="<?= ($students_result && mysqli_num_rows($students_result) > 0) ? '' : '' ?>">
                <thead>
                    <tr>
                        <th style="text-align:left;padding-left:20px;">Student ID</th>
                        <th style="text-align:left;">Name</th>
                        <th>Year Level</th>
                        <th>Section</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
                    <?php while ($student = mysqli_fetch_assoc($students_result)):
                        $status = $current_attendance[$student['id']] ?? 'present';
                    ?>
                        <tr>
                            <td class="att-id" style="padding-left:20px;"><?= htmlspecialchars($student['student_id']) ?></td>
                            <td class="att-name"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                            <td><?= htmlspecialchars($student['year_level']) ?></td>
                            <td><span class="badge-blue"><?= htmlspecialchars($student['section']) ?></span></td>
                            <td>
                                <div class="attendance-buttons" data-student="<?= $student['id'] ?>">
                                    <button type="button" class="status-btn <?= $status == 'present' ? 'active' : '' ?>" data-value="present">
                                        <i class="fas fa-check"></i> Present
                                    </button>
                                    <button type="button" class="status-btn <?= $status == 'absent' ? 'active' : '' ?>" data-value="absent">
                                        <i class="fas fa-times"></i> Absent
                                    </button>
                                    <input type="hidden" name="status[<?= $student['id'] ?>]" value="<?= $status ?>">
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="padding:0;border:none;"></td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if (!($students_result && mysqli_num_rows($students_result) > 0)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-users-slash"></i></div>
                <h3>No Students Found</h3>
                <p>Adjust your filters to find students for this attendance session.</p>
            </div>
            <?php endif; ?>

            <?php if ($students_result && mysqli_num_rows($students_result) > 0): ?>
            <div style="padding:20px;text-align:right;">
                <button type="submit" name="save_attendance" style="padding:12px 32px;font-size:13px;border-radius:50px;box-shadow:0 6px 20px rgba(37,99,235,0.35);">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

</div>

<script>
// Toggle present/absent buttons
document.querySelectorAll('.attendance-buttons').forEach(container => {
    const buttons     = container.querySelectorAll('.status-btn');
    const hiddenInput = container.querySelector('input[type="hidden"]');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            hiddenInput.value = btn.dataset.value;
            updateSummary();
        });
    });
});

// Quick mark all
function markAll(status) {
    document.querySelectorAll('.attendance-buttons').forEach(container => {
        const buttons     = container.querySelectorAll('.status-btn');
        const hiddenInput = container.querySelector('input[type="hidden"]');
        buttons.forEach(b => b.classList.remove('active'));
        const targetBtn = container.querySelector(`[data-value="${status}"]`);
        if (targetBtn) targetBtn.classList.add('active');
        hiddenInput.value = status;
    });
    updateSummary();
}

// Live summary update
function updateSummary() {
    const all     = document.querySelectorAll('.attendance-buttons input[type="hidden"]');
    let present   = 0, absent = 0;
    all.forEach(inp => { if (inp.value === 'present') present++; else absent++; });
    const pEl = document.querySelector('.att-mini-card.present .value');
    const aEl = document.querySelector('.att-mini-card.absent  .value');
    if (pEl) pEl.textContent = present;
    if (aEl) aEl.textContent = absent;
}
</script>
</body>
</html>