<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* ============================================================
   FETCH STUDENT INFO
============================================================ */
$stmt = $conn->prepare("SELECT course, year_level, section FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) die("Student not found.");

$student_course  = $student['course'];
$student_year    = $student['year_level'];
$student_section = $student['section'];

/* ============================================================
   GET COURSE ID
============================================================ */
$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$stmt->bind_param("s", $student_course);
$stmt->execute();
$course_result = $stmt->get_result();
if (!$course_result || $course_result->num_rows == 0) die("Course not found in database.");
$course_row = $course_result->fetch_assoc();
$course_id  = $course_row['id'];

/* ============================================================
   FETCH MANUAL SCHEDULES
============================================================ */
$stmt = $conn->prepare("
    SELECT * FROM schedules
    WHERE course = ? AND year_level = ? AND section = ?
    ORDER BY FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday'), time_start
");
$stmt->bind_param("sss", $student_course, $student_year, $student_section);
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[$row['day']][] = ['id'=>$row['id'],'subject'=>$row['subject'],'year_level'=>$row['year_level'],'section'=>$row['section'],'day'=>$row['day'],'time_start'=>$row['time_start'],'time_end'=>$row['time_end'],'room'=>$row['room'],'type'=>'Manual'];
}

/* ============================================================
   FETCH SUBJECTS
============================================================ */
$stmt = $conn->prepare("
    SELECT * FROM subjects
    WHERE course_id = ? AND year_level = ? AND (section IS NULL OR section = '' OR section = ?)
");
$stmt->bind_param("iss", $course_id, $student_year, $student_section);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $schedules[$row['day']][] = ['id'=>'sub'.$row['id'],'subject'=>$row['subject_name'],'year_level'=>$row['year_level'],'section'=>$row['section']??'','day'=>$row['day'],'time_start'=>$row['time_start'],'time_end'=>$row['time_end'],'room'=>$row['room'],'type'=>'Subject'];
}

/* ============================================================
   TIME CONFIG
============================================================ */
date_default_timezone_set('Asia/Manila');
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$currentDay  = date('l');
$currentTime = date('H:i:s');
$current = strtotime($currentTime) ?: time();

/* ============================================================
   STATS
============================================================ */
$classes_today = isset($schedules[$currentDay]) ? count($schedules[$currentDay]) : 0;
$total_classes = array_sum(array_map('count', $schedules));

/* ============================================================
   NEXT CLASS LOGIC
============================================================ */
$next_class = null;

if (isset($schedules[$currentDay])) {
    foreach ($schedules[$currentDay] as $class) {
        if (empty($class['time_start']) || empty($class['time_end'])) continue;
        $start = strtotime($class['time_start']);
        $end   = strtotime($class['time_end']);
        if ($start === false || $end === false) continue;
        if ($current >= $start && $current <= $end) { $class['status'] = "Ongoing"; $next_class = $class; break; }
        if ($start > $current) { $class['status'] = "Upcoming"; $next_class = $class; break; }
    }
}

if (!$next_class) {
    $currentIndex = array_search($currentDay, $days);
    if ($currentIndex === false) $currentIndex = 0;
    for ($i = $currentIndex + 1; $i < count($days); $i++) {
        if (!empty($schedules[$days[$i]])) { $next_class = $schedules[$days[$i]][0]; $next_class['status'] = "Upcoming"; break; }
    }
}

if (!$next_class) {
    foreach ($days as $day) {
        if (!empty($schedules[$day])) { $next_class = $schedules[$day][0]; $next_class['status'] = "Upcoming"; break; }
    }
}

// Color map for days
$dayColors = ['Monday'=>'#2563eb','Tuesday'=>'#8b5cf6','Wednesday'=>'#10b981','Thursday'=>'#f59e0b','Friday'=>'#f43f5e'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedule</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/studentportal.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .schedule-day-card { background: rgba(255,255,255,0.92); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.6); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 16px; transition: all var(--transition-slow); }
        .schedule-day-card:hover { box-shadow: var(--shadow-lg); }
        .schedule-day-header { padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-light); }
        .schedule-day-title { display: flex; align-items: center; gap: 10px; }
        .day-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .day-name { font-family: 'Playfair Display', Georgia, serif; font-size: 1.05rem; font-weight: 700; color: var(--slate-800); }
        .schedule-day-body { padding: 16px 24px; display: flex; flex-direction: column; gap: 10px; }
        .schedule-item { display: flex; align-items: center; gap: 16px; background: var(--slate-50); border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 14px 18px; transition: all var(--transition-base); position: relative; overflow: hidden; }
        .schedule-item::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
        .schedule-item:hover { border-color: rgba(37,99,235,0.25); box-shadow: var(--shadow-sm); transform: translateX(3px); }
        .schedule-item.is-ongoing { background: rgba(16,185,129,0.06); border-color: rgba(16,185,129,0.3); }
        .schedule-item.is-done { opacity: 0.55; }
        .sched-time { font-size: 0.78rem; font-weight: 700; color: var(--text-muted); min-width: 110px; text-align: center; }
        .sched-time-block { display: flex; flex-direction: column; align-items: center; gap: 2px; }
        .sched-time-start { font-size: 0.95rem; font-weight: 700; color: var(--slate-800); }
        .sched-time-sep { font-size: 0.7rem; color: var(--text-muted); }
        .sched-time-end { font-size: 0.88rem; color: var(--text-secondary); }
        .sched-divider { width: 1px; height: 40px; background: var(--border-light); flex-shrink: 0; }
        .sched-info { flex: 1; }
        .sched-subject { font-family: 'Playfair Display', Georgia, serif; font-size: 0.98rem; font-weight: 700; color: var(--slate-800); margin-bottom: 3px; }
        .sched-meta { font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; gap: 10px; }
        .sched-meta i { font-size: 10px; }
        .no-sched { text-align: center; padding: 32px; color: var(--text-muted); font-size: 0.88rem; }
        .no-sched i { font-size: 1.8rem; display: block; margin-bottom: 8px; opacity: 0.3; }
        .today-highlight { border: 2px solid rgba(37,99,235,0.3) !important; box-shadow: 0 0 0 4px rgba(37,99,235,0.06), var(--shadow-md) !important; }
    </style>
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <!-- ── Page Header ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><i class="fas fa-calendar-week"></i> My Classes</div>
            <h1 class="page-header-title">Class Schedule</h1>
        </div>
        <span class="result-count">
            <i class="fas fa-graduation-cap"></i>
            <?= htmlspecialchars($student_course) ?> · <?= htmlspecialchars($student_year) ?> · §<?= htmlspecialchars($student_section) ?>
        </span>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stats-grid" style="margin-bottom:28px;">
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Today's Classes</span><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?= $classes_today ?></div>
            <p class="stat-meta"><i class="fas fa-calendar-day"></i> <?= $currentDay ?></p>
        </div>
        <div class="stat-card present-card">
            <div class="stat-header"><span class="stat-label">Weekly Total</span><i class="fas fa-book-open"></i></div>
            <div class="stat-value"><?= $total_classes ?></div>
            <p class="stat-meta"><i class="fas fa-calendar-week"></i> Per week</p>
        </div>
        <div class="stat-card" style="--accent-col: var(--accent-violet);">
            <div class="stat-header"><span class="stat-label">Next Class</span><i class="fas fa-bell" style="color:var(--accent-violet);"></i></div>
            <?php if($next_class): ?>
                <div class="stat-value" style="font-size:1.15rem;"><?= htmlspecialchars($next_class['subject']) ?></div>
                <p class="stat-meta"><i class="fas fa-clock"></i> <?= date("h:i A", strtotime($next_class['time_start'])) ?> &mdash; <?= $next_class['day'] ?></p>
                <p class="stat-meta" style="margin-top:4px;">
                    <span style="color:<?= $next_class['status']==='Ongoing' ? 'var(--accent-emerald)' : 'var(--primary-blue)' ?>;font-weight:700;">
                        <?= $next_class['status'] === 'Ongoing' ? '🟢 Ongoing' : '⏰ Upcoming' ?>
                    </span>
                </p>
            <?php else: ?>
                <div class="stat-value" style="font-size:1rem;color:var(--text-muted);">None</div>
                <p class="stat-meta">No upcoming classes</p>
            <?php endif; ?>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Schedule Days</span><i class="fas fa-layer-group"></i></div>
            <div class="stat-value"><?= count(array_filter($schedules, fn($d)=>!empty($d))) ?></div>
            <p class="stat-meta">Active school days</p>
        </div>
    </div>

    <!-- ── Weekly Schedule ── -->
    <?php foreach($days as $day):
        $isToday = ($day === $currentDay);
        $dayClasses = $schedules[$day] ?? [];
        $color = $dayColors[$day] ?? '#2563eb';
    ?>
    <div class="schedule-day-card <?= $isToday ? 'today-highlight' : '' ?>">
        <div class="schedule-day-header">
            <div class="schedule-day-title">
                <div class="day-dot" style="background:<?= $color ?>;box-shadow:0 0 0 3px <?= $color ?>22;"></div>
                <span class="day-name"><?= $day ?></span>
                <?php if($isToday): ?><span class="badge badge-blue">Today</span><?php endif; ?>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:0.8rem;color:var(--text-muted);"><?= count($dayClasses) ?> <?= count($dayClasses)===1?'class':'classes' ?></span>
            </div>
        </div>

        <div class="schedule-day-body">
            <?php if(empty($dayClasses)): ?>
                <div class="no-sched"><i class="fas fa-moon"></i> No classes scheduled this day</div>
            <?php else:
                foreach($dayClasses as $class):
                    if(empty($class['time_start'])||empty($class['time_end'])) continue;
                    $start = strtotime($class['time_start']);
                    $end   = strtotime($class['time_end']);
                    $isOngoing  = $isToday && $current >= $start && $current <= $end;
                    $isDone     = $isToday && $current > $end;
                    $statusCls  = $isOngoing ? 'is-ongoing' : ($isDone ? 'is-done' : '');
            ?>
                <div class="schedule-item <?= $statusCls ?>" style="<?= 'border-left-color:'.$color.';' ?>">
                    <div class="before-accent" style="position:absolute;left:0;top:0;bottom:0;width:4px;background:<?= $color ?>;"></div>
                    <div class="sched-time-block" style="padding-left:8px;">
                        <div class="sched-time-start"><?= date("h:i A", $start) ?></div>
                        <div class="sched-time-sep">to</div>
                        <div class="sched-time-end"><?= date("h:i A", $end) ?></div>
                    </div>
                    <div class="sched-divider"></div>
                    <div class="sched-info">
                        <div class="sched-subject"><?= htmlspecialchars($class['subject']) ?></div>
                        <div class="sched-meta">
                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($class['room'] ?: 'TBA') ?></span>
                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($class['type']) ?></span>
                        </div>
                    </div>
                    <div>
                        <?php if($isOngoing): ?>
                            <span class="badge badge-green">🟢 Ongoing</span>
                        <?php elseif($isDone): ?>
                            <span class="badge badge-gray">Done</span>
                        <?php elseif($isToday && $start > $current): ?>
                            <span class="badge badge-blue">Upcoming</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>
</body>
</html>