<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

/* ============================================================
   SESSION CHECK
============================================================ */
if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* ============================================================
   FETCH STUDENT INFO (SAFE)
============================================================ */
$stmt = $conn->prepare("
    SELECT course, year_level, section
    FROM students 
    WHERE id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Student not found.");
}

$student_course  = $student['course'];
$student_year    = $student['year_level'];
$student_section = $student['section'];

/* ============================================================
   GET COURSE ID (SAFE + PREPARED)
============================================================ */
$stmt = $conn->prepare("
    SELECT id 
    FROM courses 
    WHERE course_name = ?
");
$stmt->bind_param("s", $student_course);
$stmt->execute();
$course_result = $stmt->get_result();

if (!$course_result || $course_result->num_rows == 0) {
    die("Course not found in database.");
}

$course_row = $course_result->fetch_assoc();
$course_id  = $course_row['id'];

/* ============================================================
   FETCH MANUAL SCHEDULES
============================================================ */
$stmt = $conn->prepare("
    SELECT * 
    FROM schedules
    WHERE course = ?
      AND year_level = ?
      AND section = ?
    ORDER BY FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday'), time_start
");
$stmt->bind_param("sss", $student_course, $student_year, $student_section);
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];

while ($row = $result->fetch_assoc()) {
    $schedules[$row['day']][] = [
        'id'          => $row['id'],
        'subject'     => $row['subject'],
        'year_level'  => $row['year_level'],
        'section'     => $row['section'],
        'day'         => $row['day'],
        'time_start'  => $row['time_start'],
        'time_end'    => $row['time_end'],
        'room'        => $row['room'],
        'type'        => 'Manual'
    ];
}

/* ============================================================
   FETCH SUBJECTS (ADDED VIA SYSTEM)
============================================================ */
$stmt = $conn->prepare("
    SELECT * 
    FROM subjects 
    WHERE course_id = ?
      AND year_level = ?
      AND (section IS NULL OR section = '' OR section = ?)
");
$stmt->bind_param("iss", $course_id, $student_year, $student_section);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $schedules[$row['day']][] = [
        'id'          => 'sub' . $row['id'],
        'subject'     => $row['subject_name'],
        'year_level'  => $row['year_level'],
        'section'     => $row['section'] ?? '',
        'day'         => $row['day'],
        'time_start'  => $row['time_start'],
        'time_end'    => $row['time_end'],
        'room'        => $row['room'],
        'type'        => 'Subject'
    ];
}

/* ============================================================
   TIME CONFIG
============================================================ */
date_default_timezone_set('Asia/Manila');

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$currentDay = date('l');
$currentTime = date('H:i:s');

$current = strtotime($currentTime);
if ($current === false) {
    $current = time(); // fallback
}

/* ============================================================
   STATS
============================================================ */
$classes_today = isset($schedules[$currentDay]) ? count($schedules[$currentDay]) : 0;

$total_classes = 0;
foreach ($schedules as $day_classes) {
    $total_classes += count($day_classes);
}

/* ============================================================
   NEXT CLASS LOGIC
============================================================ */
$next_class = null;

if (isset($schedules[$currentDay])) {
    foreach ($schedules[$currentDay] as $class) {

        // Skip invalid data
        if (empty($class['time_start']) || empty($class['time_end'])) {
            continue;
        }

        $start = strtotime($class['time_start']);
        $end   = strtotime($class['time_end']);

        if ($start === false || $end === false) {
            continue;
        }

        // Ongoing
        if ($current >= $start && $current <= $end) {
            $class['status'] = "Ongoing";
            $next_class = $class;
            break;
        }

        // Upcoming
        if ($start > $current) {
            $class['status'] = "Upcoming";
            $next_class = $class;
            break;
        }
    }
}

/* ============================================================
   SEARCH NEXT DAYS
============================================================ */
if (!$next_class) {

    $currentIndex = array_search($currentDay, $days);

    if ($currentIndex === false) {
        $currentIndex = 0;
    }

    for ($i = $currentIndex + 1; $i < count($days); $i++) {
        $nextDay = $days[$i];

        if (!empty($schedules[$nextDay])) {
            $next_class = $schedules[$nextDay][0];
            $next_class['status'] = "Upcoming";
            break;
        }
    }
}

/* ============================================================
   LOOP BACK TO MONDAY
============================================================ */
if (!$next_class) {
    foreach ($days as $day) {
        if (!empty($schedules[$day])) {
            $next_class = $schedules[$day][0];
            $next_class['status'] = "Upcoming";
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Class Schedule - <?= htmlspecialchars($student_course) ?></title>
     <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
   <link rel="stylesheet" href="<?php echo asset('css/studentportal.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h2>Class Schedule</h2>
        <p><?= htmlspecialchars($student_course) ?> - <?= htmlspecialchars($student_year) ?> - Section <?= htmlspecialchars($student_section) ?></p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid auto-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span>Classes Today</span>
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?= $classes_today ?></div>
            <p><?= $currentDay ?></p>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span>Total Classes</span>
                <i class="fas fa-book-open"></i>
            </div>
            <div class="stat-value"><?= $total_classes ?></div>
            <p>Per week</p>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span>Next Class</span>
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <?php if($next_class): ?>
                <div class="stat-value"><?= htmlspecialchars($next_class['subject']) ?></div>
                <p><?= $next_class['day'] ?> • <?= date("h:i A", strtotime($next_class['time_start'])) ?> - <?= date("h:i A", strtotime($next_class['time_end'])) ?></p>
                <p style="color: <?= $next_class['status']=='Ongoing'?'green':'#065471' ?>"><?= $next_class['status'] ?></p>
            <?php else: ?>
                <div class="stat-value">No upcoming classes</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Weekly Schedule -->
    <?php foreach($days as $day): 
        $isToday = ($day === $currentDay);
    ?>
        <div class="card schedule-day <?= $isToday ? 'today-card' : '' ?>">
            <div class="card-header">
                <h3><?= htmlspecialchars($day) ?> <?php if($isToday) echo '<span class="badge badge-blue">Today</span>'; ?></h3>
                <p><?= isset($schedules[$day]) ? count($schedules[$day]) : 0 ?> classes scheduled</p>
            </div>
            <div class="card-content">
                <?php if(empty($schedules[$day])): ?>
                    <p>No classes scheduled.</p>
                <?php else: ?>
                    <div class="schedule-grid">
                        <?php foreach($schedules[$day] as $class): ?>
                            <div class="schedule-box">
                                <div class="schedule-title">
                                    <h4><?= htmlspecialchars($class['subject']) ?></h4>
                                    <p><?= htmlspecialchars($class['type']) ?></p>
                                </div>
                                <div class="schedule-info">
                                    <div><i class="fas fa-clock"></i> <?= date("h:i A", strtotime($class['time_start'])) ?> - <?= date("h:i A", strtotime($class['time_end'])) ?></div>
                                    <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($class['room']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</div>
</body>
</html>