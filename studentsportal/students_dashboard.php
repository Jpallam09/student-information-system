<?php
session_start();
include '../config/database.php';
include '../config/current_school_year.php';

// ✅ Check if student is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: ../Accesspage/student_login.php");
    exit();
}

$student_id = mysqli_real_escape_string($conn, $_SESSION['student_id']);

// Check inactive enrollment
$is_inactive = ($_SESSION['inactive_enrollment'] ?? false);
$active_year = getActiveSchoolYear($conn);
$active_sem = getActiveSemester($conn);

// ============================================
// FETCH STUDENT INFO
// ============================================
$student_query = mysqli_query($conn, "SELECT * FROM students WHERE id='$student_id'");
if (!$student_query) {
    error_log("Student query failed: " . mysqli_error($conn));
    die("Database error");
}
$student = mysqli_fetch_assoc($student_query);

if (!$student) {
    session_destroy();
    header("Location: ../Accesspage/student_login.php");
    exit();
}

$student_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
$course_name = mysqli_real_escape_string($conn, $student['course']);
$year_level = mysqli_real_escape_string($conn, $student['year_level']);
$section = mysqli_real_escape_string($conn, $student['section']);

// Get course ID for querying
$course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$course_name'");
if (!$course_result) {
    error_log("Course query failed: " . mysqli_error($conn));
    $course_id = 0;
} else {
    $course_row = mysqli_fetch_assoc($course_result);
    $course_id = $course_row['id'] ?? 0;
}

// ============================================
// CALCULATE GPA DYNAMICALLY
// ============================================
$gpa = 0;
$gpa_change = 0;

$gpa_query = mysqli_query($conn, "
    SELECT g.letter_grade 
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = '$student_id' 
    AND s.course_id = '$course_id'
    AND s.year_level = '$year_level'
    AND (s.section = '$section' OR s.section IS NULL OR s.section = '')
");
if ($gpa_query) {
    $grades_array = [];
    while($g = mysqli_fetch_assoc($gpa_query)){
        if($g['letter_grade'] && is_numeric($g['letter_grade'])){
            $grades_array[] = floatval($g['letter_grade']);
        }
    }

    if(count($grades_array) > 0){
        $gpa = round(array_sum($grades_array) / count($grades_array), 2);
        $gpa_change = round((max($grades_array) - min($grades_array)) / 10, 1);
    }
} else {
    error_log("GPA query failed: " . mysqli_error($conn));
}

// ============================================
// CALCULATE ATTENDANCE RATE
// ============================================
$attendance_query = mysqli_query($conn, "SELECT status FROM attendance WHERE student_id='$student_id'");
$attendance_rate = 0;
$total_classes = 0;
$present_classes = 0;
if ($attendance_query) {
    while($att = mysqli_fetch_assoc($attendance_query)){
        $total_classes++;
        if($att['status'] == 'present'){
            $present_classes++;
        }
    }
    $attendance_rate = $total_classes > 0 ? round(($present_classes / $total_classes) * 100) : 0;
}

// ============================================
// COUNT ACTIVE SUBJECTS
// ============================================
$subjects_count = 0;
$subjects_query = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM subjects 
    WHERE course_id = '$course_id' 
    AND year_level = '$year_level'
    AND (section = '$section' OR section IS NULL OR section = '')
");
if ($subjects_query && $subj = mysqli_fetch_assoc($subjects_query)){
    $subjects_count = $subj['total'];
}

// ============================================
// FETCH UPCOMING TASKS
// ============================================
$upcoming_tasks = [];
$task_ids = [];

$subject_ids_query = mysqli_query($conn, "
    SELECT id FROM subjects 
    WHERE course_id = '$course_id' 
    AND year_level = '$year_level'
    AND (section = '$section' OR section IS NULL OR section = '')
");
if ($subject_ids_query) {
    while($sid = mysqli_fetch_assoc($subject_ids_query)){
        $task_ids[] = $sid['id'];
    }
}

if(!empty($task_ids)){
    $task_ids_str = implode(',', array_map('intval', $task_ids));
    
    $tasks_query = mysqli_query($conn, "
        SELECT t.*, s.subject_name
        FROM tasks t
        JOIN subjects s ON t.subject_id = s.id
        WHERE t.subject_id IN ($task_ids_str)
        AND (t.due_date IS NOT NULL AND t.due_date != '')
        AND t.due_date >= CURDATE()
        ORDER BY t.due_date ASC
        LIMIT 5
    ");
    if ($tasks_query) {
        while($task = mysqli_fetch_assoc($tasks_query)){
            $upcoming_tasks[] = $task;
        }
    }
}

// ============================================
// FETCH RECENT ACHIEVEMENTS
// ============================================
$achievements = [];
$submitted_query = mysqli_query($conn, "
    SELECT t.title, t.due_date, ts.submitted_at, s.subject_name
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    JOIN subjects s ON t.subject_id = s.id
    WHERE ts.student_id = '$student_id'
    ORDER BY ts.submitted_at DESC
    LIMIT 3
");
if ($submitted_query) {
    while($ach = mysqli_fetch_assoc($submitted_query)){
        $achievements[] = $ach;
    }
}

// ============================================
// FETCH GRADES FOR CHART
// ============================================
$chart_labels = ['Math', 'Physics', 'Chemistry', 'English', 'Programming'];
$chart_data = [0, 0, 0, 0, 0];

$chart_query = mysqli_query($conn, "
    SELECT s.subject_name, g.prelim, g.midterm, g.final
    FROM grades g
    JOIN subjects s ON g.subject_id = s.id
    WHERE g.student_id = '$student_id'
    AND s.course_id = '$course_id'
    AND s.year_level = '$year_level'
    ORDER BY s.subject_name ASC
    LIMIT 5
");
if ($chart_query) {
    $chart_labels = [];
    $chart_data = [];
    while($ch = mysqli_fetch_assoc($chart_query)){
        $chart_labels[] = $ch['subject_name'];
        $scores = array_filter([$ch['prelim'], $ch['midterm'], $ch['final']], function($v){ return $v !== null && $v !== ''; });
        $chart_data[] = !empty($scores) ? round(array_sum($scores) / count($scores)) : 0;
    }
}

// ============================================
// FETCH RECENT ANNOUNCEMENTS
// ============================================
$announcements = [];
$course_esc = mysqli_real_escape_string($conn, $course_name);
$ann_query = mysqli_query($conn, "
    SELECT * FROM announcements 
    WHERE (course_id = '$course_esc' OR course_id = 'ALL')
    AND (year_level = '$year_level' OR year_level = 'All')
    AND (section = '$section' OR section = 'All' OR section = '')
    ORDER BY pinned DESC, created_at DESC
    LIMIT 3
");
if ($ann_query) {
    while($ann = mysqli_fetch_assoc($ann_query)){
        $announcements[] = $ann;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../css/studentportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- Top Right Logo -->
<div style="position: fixed; top: 15px; right: 20px; z-index: 9999;">
    <img src="../images/622685015_925666030131412_6886851389087569993_n.jpg" alt="School Logo" style="width: 50px; display: block; margin: 40px auto 15px auto; border-radius: 5px; animation: float 3s ease-in-out infinite;">
</div>

<?php include 'students_sidebar.php'; ?>

<div class="main-content">

    <!-- Inactive Warning (if applicable) -->
    <?php if ($is_inactive): ?>
        <?php include 'components/inactive_warning.php'; ?>
    <?php endif; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <h2 class="page-title">Welcome Back, <?php echo $student_name; ?>!<?php if($is_inactive): ?> <span style="color: #f59e0b; font-size: 0.8em;">(Past Term)</span><?php endif; ?></h2>
        <p class="page-subtitle">Here's your academic progress and upcoming activities<?php if($is_inactive): ?> (view-only mode)<?php endif; ?></p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Overall GPA</span>
                <i class="fas fa-award stat-icon"></i>
            </div>
            <div class="stat-value"><?php echo $gpa > 0 ? $gpa : 'N/A'; ?></div>
            <p class="stat-meta"><?php echo $gpa > 0 ? ($gpa_change > 0 ? '+' . $gpa_change : $gpa_change) . ' from grades' : 'No grades yet'; ?></p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo min(($gpa / 4) * 100, 100); ?>%"></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Attendance</span>
                <i class="fas fa-calendar-check stat-icon"></i>
            </div>
            <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
            <p class="stat-meta"><?php echo $present_classes . ' of ' . $total_classes; ?> classes</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $attendance_rate; ?>%"></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active Subjects</span>
                <i class="fas fa-book-open stat-icon"></i>
            </div>
            <div class="stat-value"><?php echo $subjects_count; ?></div>
            <p class="stat-meta">This semester</p>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Course</span>
                <i class="fas fa-graduation-cap stat-icon"></i>
            </div>
            <div class="stat-value" style="font-size: 1.2rem;"><?php echo htmlspecialchars($course_name); ?></div>
            <p class="stat-meta"><?php echo htmlspecialchars($year_level); ?> - Section <?php echo htmlspecialchars($section); ?></p>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="chart-grid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Subject Performance</h3>
                <p class="card-description">Your current scores across all subjects</p>
            </div>
            <div class="card-content">
                <canvas id="performanceChart" height="200"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">GPA Progress</h3>
                <p class="card-description">Your academic progress over time</p>
            </div>
            <div class="card-content">
                <canvas id="gpaChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Two Column Section -->
    <div class="two-col-grid">
        <!-- Deadlines -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Upcoming Deadlines</h3>
                <p class="card-description">Assignments and exams due soon</p>
            </div>
            <div class="card-content alert-list">
                <?php if(count($upcoming_tasks) > 0): ?>
                    <?php foreach($upcoming_tasks as $task): 
                        $due_date = strtotime($task['due_date']);
                        $days_until = ceil(($due_date - time()) / (60 * 60 * 24));
                        $alert_class = $days_until <= 1 ? 'alert-danger' : ($days_until <= 3 ? 'alert-warning' : 'alert-info');
                        $due_text = $days_until <= 0 ? 'Due today' : ($days_until == 1 ? 'Due tomorrow' : "Due in $days_until days");
                    ?>
                    <div class="alert <?php echo $alert_class; ?>">
                        <i class="fas fa-clock alert-icon"></i>
                        <div>
                            <p class="alert-title"><?php echo htmlspecialchars($task['title']); ?></p>
                            <p class="alert-text"><?php echo $due_text; ?> - <?php echo htmlspecialchars($task['subject_name']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle alert-icon"></i>
                        <div>
                            <p class="alert-title">No upcoming deadlines</p>
                            <p class="alert-text">You're all caught up!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Achievements -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Achievements</h3>
                <p class="card-description">Your latest accomplishments</p>
            </div>
            <div class="card-content alert-list">
                <?php if(count($achievements) > 0): ?>
                    <?php foreach($achievements as $ach): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-trophy alert-icon"></i>
                        <div>
                            <p class="alert-title"><?php echo htmlspecialchars($ach['title']); ?></p>
                            <p class="alert-text"><?php echo htmlspecialchars($ach['subject_name']); ?> - Submitted <?php echo date('M j, Y', strtotime($ach['submitted_at'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-purple">
                        <i class="fas fa-star alert-icon"></i>
                        <div>
                            <p class="alert-title">No achievements yet</p>
                            <p class="alert-text">Submit tasks to see them here!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- Charts Script -->
<script>
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
new Chart(performanceCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{ label: 'Score', data: <?php echo json_encode($chart_data); ?>, backgroundColor: '#0A91AB' }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } }
});

const gpaCtx = document.getElementById('gpaChart').getContext('2d');
new Chart(gpaCtx, {
    type: 'line',
    data: {
        labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
        datasets: [{
            label: 'GPA',
            data: [<?php echo $gpa > 0 ? $gpa - 0.3 : 0; ?>, <?php echo $gpa > 0 ? $gpa - 0.2 : 0; ?>, <?php echo $gpa > 0 ? $gpa - 0.1 : 0; ?>, <?php echo $gpa; ?>],
            borderColor: '#065471',
            fill: false
        }]
    },
    options: { responsive: true }
});
</script>

<style>
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}
</style>

</body>
</html>
