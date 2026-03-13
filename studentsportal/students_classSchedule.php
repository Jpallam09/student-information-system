<?php
session_start();
include '../config/database.php';

// --- SESSION CHECK ---
if(!isset($_SESSION['student_id'])){
    header("Location: ../Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// --- FETCH STUDENT INFO ---
$student_query = mysqli_query($conn, "
    SELECT course, year_level, section
    FROM students 
    WHERE id='$student_id'
") or die(mysqli_error($conn));

if(mysqli_num_rows($student_query) == 0){
    die("Student not found.");
}

$student = mysqli_fetch_assoc($student_query);
$student_course  = $student['course'];
$student_year    = $student['year_level'];
$student_section = $student['section'];

// --- GET COURSE ID ---
$course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$student_course'");
if(!$course_result || mysqli_num_rows($course_result) == 0){
    die("Course not found in database.");
}
$course_row = mysqli_fetch_assoc($course_result);
$course_id = $course_row['id'];

// --- FETCH MANUAL SCHEDULES ---
$schedule_query = mysqli_query($conn, "
    SELECT * 
    FROM schedules
    WHERE course='$student_course'
      AND year_level='$student_year'
      AND section='$student_section'
    ORDER BY FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday'), time_start
") or die(mysqli_error($conn));

$schedules = [];
while($row = mysqli_fetch_assoc($schedule_query)){
    $schedules[$row['day']][] = [
        'id' => $row['id'],
        'subject' => $row['subject'],
        'year_level' => $row['year_level'],
        'section' => $row['section'],
        'day' => $row['day'],
        'time_start' => $row['time_start'],
        'time_end' => $row['time_end'],
        'room' => $row['room'],
        'type' => 'Manual'
    ];
}

// --- FETCH SUBJECTS ADDED VIA addsubject.php ---
// Fixed: use course_id instead of course name
$subjects_query = mysqli_query($conn, "
    SELECT * 
    FROM subjects 
    WHERE course_id='$course_id'
      AND year_level='$student_year'
      AND (section IS NULL OR section='' OR section='$student_section')
") or die(mysqli_error($conn));

while($row = mysqli_fetch_assoc($subjects_query)){
    $schedules[$row['day']][] = [
        'id' => 'sub'.$row['id'],
        'subject' => $row['subject_name'],
        'year_level' => $row['year_level'],
        'section' => $row['section'] ?? '',
        'day' => $row['day'],
        'time_start' => $row['time_start'],
        'time_end' => $row['time_end'],
        'room' => $row['room'],
        'type' => 'Subject'
    ];
}

// --- CONFIG ---
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$currentDay = date('l');
$currentTime = date('H:i:s');

// --- STATS ---
$classes_today = isset($schedules[$currentDay]) ? count($schedules[$currentDay]) : 0;
$total_classes = 0;
$next_class = null;

foreach($schedules as $day => $day_classes){
    $total_classes += count($day_classes);
}

// --- NEXT CLASS LOGIC ---
if(isset($schedules[$currentDay])){
    foreach($schedules[$currentDay] as $class){
        if($currentTime >= $class['time_start'] && $currentTime <= $class['time_end']){
            $class['status'] = "Ongoing";
            $next_class = $class;
            break;
        }
        if($class['time_start'] > $currentTime){
            $class['status'] = "Upcoming";
            $next_class = $class;
            break;
        }
    }
}

// Check next days if none today
if(!$next_class){
    $currentIndex = array_search($currentDay,$days);
    for($i=$currentIndex+1;$i<count($days);$i++){
        $nextDay = $days[$i];
        if(isset($schedules[$nextDay])){
            $next_class = $schedules[$nextDay][0];
            $next_class['status'] = "Upcoming";
            break;
        }
    }
}

// Loop back to Monday if still none
if(!$next_class){
    foreach($days as $day){
        if(isset($schedules[$day])){
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
    <link rel="stylesheet" href="../css/studentportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include 'students_sidebar.php'; ?>

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
                <h3><?= $day ?> <?php if($isToday) echo '<span class="badge badge-blue">Today</span>'; ?></h3>
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