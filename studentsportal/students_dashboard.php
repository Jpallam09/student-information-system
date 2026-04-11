<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Strict'
]);

require_once dirname(__DIR__) . '/config/paths.php';
require_once CONFIG_PATH . 'database.php';
require_once CONFIG_PATH . 'current_school_year.php';

if(!isset($_SESSION['student_id'])){
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_id = intval($_SESSION['student_id']);
$is_inactive = ($_SESSION['inactive_enrollment'] ?? false);
$active_year = getActiveSchoolYear($conn);
$active_sem  = getActiveSemester($conn);

/* ── Student info ── */
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    session_destroy();
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
$course_name  = $student['course'];
$year_level   = $student['year_level'];
$section      = $student['section'];

/* ── Course ID ── */
$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$stmt->bind_param("s", $course_name);
$stmt->execute();
$course_row = $stmt->get_result()->fetch_assoc();
$course_id  = $course_row['id'] ?? 0;

/* ── GPA ── */
$gpa = 0; $gpa_change = 0;
$stmt = $conn->prepare("
    SELECT g.letter_grade FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = ? AND s.course_id = ? AND s.year_level = ?
    AND (s.section = ? OR s.section IS NULL OR s.section = '')
");
$stmt->bind_param("iiss", $student_id, $course_id, $year_level, $section);
$stmt->execute();
$grades_array = [];
$r = $stmt->get_result();
while($g = $r->fetch_assoc()){
    if($g['letter_grade'] && is_numeric($g['letter_grade']))
        $grades_array[] = floatval($g['letter_grade']);
}
if(count($grades_array) > 0){
    $gpa        = round(array_sum($grades_array) / count($grades_array), 2);
    $gpa_change = round((max($grades_array) - min($grades_array)) / 10, 1);
}

/* ── Attendance ── */
$stmt = $conn->prepare("SELECT status FROM attendance WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$att_result = $stmt->get_result();
$total_classes = 0; $present_classes = 0;
while($att = $att_result->fetch_assoc()){
    $total_classes++;
    if($att['status'] == 'present') $present_classes++;
}
$attendance_rate = $total_classes > 0 ? round(($present_classes / $total_classes) * 100) : 0;

/* ── Subject count ── */
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM subjects WHERE course_id = ? AND year_level = ? AND (section = ? OR section IS NULL OR section = '')");
$stmt->bind_param("iss", $course_id, $year_level, $section);
$stmt->execute();
$subjects_count = $stmt->get_result()->fetch_assoc()['total'];

/* ── Upcoming tasks ── */
$upcoming_tasks = [];
$stmt = $conn->prepare("SELECT id FROM subjects WHERE course_id = ? AND year_level = ? AND (section = ? OR section IS NULL OR section = '')");
$stmt->bind_param("iss", $course_id, $year_level, $section);
$stmt->execute();
$subject_ids_raw = $stmt->get_result();
$task_ids = [];
while($row = $subject_ids_raw->fetch_assoc()) $task_ids[] = $row['id'];

if(!empty($task_ids)){
    $task_ids_str = implode(',', array_map('intval', $task_ids));
    $tasks_query = mysqli_query($conn, "
        SELECT t.*, s.subject_name FROM tasks t
        JOIN subjects s ON t.subject_id = s.id
        WHERE t.subject_id IN ($task_ids_str)
        AND (t.due_date IS NOT NULL AND t.due_date != '')
        AND t.due_date >= CURDATE()
        ORDER BY t.due_date ASC LIMIT 5
    ");
    if($tasks_query) while($task = mysqli_fetch_assoc($tasks_query)) $upcoming_tasks[] = $task;
}

/* ── Achievements ── */
$achievements = [];
$q = mysqli_query($conn, "
    SELECT t.title, ts.submitted_at, s.subject_name
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    JOIN subjects s ON t.subject_id = s.id
    WHERE ts.student_id = '$student_id'
    ORDER BY ts.submitted_at DESC LIMIT 3
");
if($q) while($ach = mysqli_fetch_assoc($q)) $achievements[] = $ach;

/* ── Chart data ── */
$chart_labels = []; $chart_data = [];
$cq = mysqli_query($conn, "
    SELECT s.subject_name, g.prelim, g.midterm, g.final
    FROM grades g JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = '$student_id' AND s.course_id = '$course_id'
    AND s.year_level = '$year_level' ORDER BY s.subject_name ASC LIMIT 5
");
if($cq) while($ch = mysqli_fetch_assoc($cq)){
    $chart_labels[] = $ch['subject_name'];
    $scores = array_filter([$ch['prelim'], $ch['midterm'], $ch['final']], fn($v) => $v !== null && $v !== '');
    $chart_data[] = !empty($scores) ? round(array_sum($scores) / count($scores)) : 0;
}

/* ── Announcements ── */
$announcements = [];
$stmt = $conn->prepare("
    SELECT a.*, CONCAT(t.first_name,' ',IFNULL(t.middle_name,''),' ',t.last_name,' ',IFNULL(t.suffix,'')) AS teacher_name
    FROM announcements a JOIN teachers t ON a.teacher_id = t.id
    WHERE (
        (t.teacher_type IN ('Seeder','Administrator') AND UPPER(TRIM(a.course_id)) = ?)
        OR (UPPER(TRIM(a.course_id)) = ? AND (a.year_level = ? OR a.year_level = 'All') AND (a.section = ? OR a.section = 'All'))
    )
    ORDER BY a.pinned DESC, a.created_at DESC LIMIT 3
");
$cb = strtoupper(trim($course_name));
$stmt->bind_param("ssss", $cb, $cb, $year_level, $section);
$stmt->execute();
$r2 = $stmt->get_result();
while($ann = $r2->fetch_assoc()) $announcements[] = $ann;
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
    </style>
</head>
<body>

<!-- Fixed top-right logo -->
<div style="position:fixed;top:15px;right:20px;z-index:9999;">
    <img src="<?php echo WEB_IMAGES; ?>622685015_925666030131412_6886851389087569993_n.jpg" alt="Logo"
         style="width:46px;height:46px;border-radius:50%;border:2px solid rgba(37,99,235,0.25);box-shadow:0 4px 14px rgba(0,0,0,0.12);animation:float 3s ease-in-out infinite;object-fit:cover;">
</div>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <?php if ($is_inactive): ?>
        <?php include PROJECT_ROOT . '/studentsportal/components/inactive_warning.php'; ?>
    <?php endif; ?>

    <!-- ── Welcome Banner ── -->
    <div class="dashboard-welcome">
        <div class="dashboard-welcome-icon">
            <i class="fas fa-graduation-cap" style="color:rgba(255,255,255,0.9);"></i>
        </div>
        <div class="dashboard-welcome-text">
            <div class="eyebrow"><span></span> Student Dashboard</div>
            <h2>
                Welcome back, <?php echo $student_name; ?>!
                <strong>
                    <?php if($is_inactive): ?>📅 Past Term — View Only<?php else: ?>Here's your academic overview for today<?php endif; ?>
                </strong>
            </h2>
        </div>
        <div class="welcome-course-badge">
            <?php echo htmlspecialchars($course_name); ?> · Yr <?php echo htmlspecialchars($year_level); ?> · §<?php echo htmlspecialchars($section); ?>
        </div>
    </div>

    <!-- ── Page Header (eyebrow pattern) ── -->
    <div class="page-header-bar" style="margin-bottom:20px;">
        <div>
            <div class="page-header-eyebrow"><i class="fas fa-chart-line"></i> Academic Overview</div>
            <h1 class="page-header-title">Your Progress at a Glance</h1>
        </div>
        <span class="result-count">
            <i class="fas fa-calendar-alt"></i>
            <?php echo htmlspecialchars($active_year . ' · ' . $active_sem . ' Sem'); ?>
        </span>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Overall GPA</span><i class="fas fa-award"></i></div>
            <div class="stat-value"><?php echo $gpa > 0 ? $gpa : 'N/A'; ?></div>
            <p class="stat-meta"><?php echo $gpa > 0 ? ($gpa_change > 0 ? '+' . $gpa_change : $gpa_change) . ' spread' : 'No grades yet'; ?></p>
            <div class="progress-bar"><div class="progress-fill" style="width:<?php echo min(($gpa / 4) * 100, 100); ?>%"></div></div>
        </div>

        <div class="stat-card present-card">
            <div class="stat-header"><span class="stat-label">Attendance</span><i class="fas fa-calendar-check"></i></div>
            <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
            <p class="stat-meta"><?php echo "$present_classes of $total_classes"; ?> classes attended</p>
            <div class="progress-bar"><div class="progress-fill" style="width:<?php echo $attendance_rate; ?>;background:var(--accent-emerald);"></div></div>
        </div>

        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Active Subjects</span><i class="fas fa-book-open"></i></div>
            <div class="stat-value"><?php echo $subjects_count; ?></div>
            <p class="stat-meta">This semester</p>
        </div>

        <div class="stat-card" style="--before-color:var(--accent-violet);">
            <div class="stat-header"><span class="stat-label">Course</span><i class="fas fa-graduation-cap" style="color:var(--accent-violet);"></i></div>
            <div class="stat-value" style="font-size:1.2rem;"><?php echo htmlspecialchars($course_name); ?></div>
            <p class="stat-meta">Year <?php echo htmlspecialchars($year_level); ?> · Section <?php echo htmlspecialchars($section); ?></p>
        </div>
    </div>

    <!-- ── Charts ── -->
    <div class="chart-grid">
        <div class="card" style="cursor:default;">
            <div class="card-header">
                <h3 class="card-title">Subject Performance</h3>
                <p class="card-description">Average scores across your subjects</p>
            </div>
            <div class="card-content">
                <canvas id="performanceChart" height="200"></canvas>
            </div>
        </div>
        <div class="card" style="cursor:default;">
            <div class="card-header">
                <h3 class="card-title">GPA Trend</h3>
                <p class="card-description">Academic progress over semesters</p>
            </div>
            <div class="card-content">
                <canvas id="gpaChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- ── Deadlines & Achievements ── -->
    <div class="two-col-grid">
        <div class="card" style="cursor:default;">
            <div class="card-header">
                <h3 class="card-title">Upcoming Deadlines</h3>
                <p class="card-description">Tasks due soon</p>
            </div>
            <div class="card-content alert-list" style="border-top:none;margin-top:0;padding-top:0;">
                <?php if(count($upcoming_tasks) > 0): ?>
                    <?php foreach($upcoming_tasks as $task):
                        $days = ceil((strtotime($task['due_date']) - time()) / 86400);
                        $cls  = $days <= 1 ? 'alert-danger' : ($days <= 3 ? 'alert-warning' : 'alert-info');
                        $txt  = $days <= 0 ? 'Due today' : ($days == 1 ? 'Due tomorrow' : "Due in $days days");
                        $icon = $days <= 1 ? 'fa-fire' : ($days <= 3 ? 'fa-clock' : 'fa-calendar');
                    ?>
                    <div class="alert <?php echo $cls; ?>">
                        <i class="fas <?php echo $icon; ?> alert-icon"></i>
                        <div>
                            <p class="alert-title"><?php echo htmlspecialchars($task['title']); ?></p>
                            <p class="alert-text"><?php echo $txt; ?> · <?php echo htmlspecialchars($task['subject_name']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle alert-icon"></i>
                        <div>
                            <p class="alert-title">All caught up!</p>
                            <p class="alert-text">No upcoming deadlines right now.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="cursor:default;">
            <div class="card-header">
                <h3 class="card-title">Recent Submissions</h3>
                <p class="card-description">Your latest completed tasks</p>
            </div>
            <div class="card-content alert-list" style="border-top:none;margin-top:0;padding-top:0;">
                <?php if(count($achievements) > 0): ?>
                    <?php foreach($achievements as $ach): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-trophy alert-icon"></i>
                        <div>
                            <p class="alert-title"><?php echo htmlspecialchars($ach['title']); ?></p>
                            <p class="alert-text"><?php echo htmlspecialchars($ach['subject_name']); ?> · <?php echo date('M j, Y', strtotime($ach['submitted_at'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-purple">
                        <i class="fas fa-star alert-icon"></i>
                        <div>
                            <p class="alert-title">No submissions yet</p>
                            <p class="alert-text">Submit tasks to see them here!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Announcements ── -->
    <?php if(!empty($announcements)): ?>
    <div class="page-header-bar" style="margin-top:8px;margin-bottom:16px;">
        <div>
            <div class="page-header-eyebrow"><i class="fas fa-bullhorn"></i> Announcements</div>
            <h2 class="page-header-title" style="font-size:1.3rem;">Latest from Your Teachers</h2>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:24px;">
        <?php foreach($announcements as $ann): ?>
        <div class="card" style="cursor:default;">
            <?php if($ann['pinned']): ?>
                <span style="position:absolute;top:14px;right:14px;"><i class="fas fa-thumbtack" style="color:var(--accent-amber);font-size:14px;"></i></span>
            <?php endif; ?>
            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-user-tie" style="color:var(--primary-blue);"></i>
                <?php echo htmlspecialchars(trim($ann['teacher_name'])); ?>
            </div>
            <h4 style="font-family:'Playfair Display',Georgia,serif;font-size:1rem;font-weight:700;color:var(--slate-800);margin:0 0 8px;line-height:1.35;">
                <?php echo htmlspecialchars($ann['title']); ?>
            </h4>
           <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.6;margin:0 0 12px;display:-webkit-box;-webkit-line-clamp:3;line-clamp:3; -webkit-box-orient:vertical;overflow:hidden;">
                <?php echo htmlspecialchars($ann['content']); ?>
            </p>
            <div class="announcement-meta">
                <span><i class="fas fa-calendar"></i><?php echo date('M j, Y', strtotime($ann['created_at'])); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div><!-- /main-content -->

<script>
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
new Chart(performanceCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels ?: ['No Data']); ?>,
        datasets: [{
            label: 'Avg Score',
            data: <?php echo json_encode($chart_data ?: [0]); ?>,
            backgroundColor: 'rgba(37,99,235,0.75)',
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } }
});

const gpaCtx = document.getElementById('gpaChart').getContext('2d');
const gpaVal = <?php echo $gpa; ?>;
new Chart(gpaCtx, {
    type: 'line',
    data: {
        labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Current'],
        datasets: [{
            label: 'GPA',
            data: [
                Math.max(0, gpaVal - 0.3),
                Math.max(0, gpaVal - 0.2),
                Math.max(0, gpaVal - 0.1),
                gpaVal
            ],
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,0.08)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#2563eb',
            pointRadius: 5
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } }
});
</script>
</body>
</html>sss