<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* ================= STUDENT INFO ================= */
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Student not found.");
}

/* ================= ATTENDANCE ================= */
$stmt = $conn->prepare("
    SELECT * 
    FROM attendance 
    WHERE student_id = ? 
    ORDER BY date DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance_query = $stmt->get_result();

if (!$attendance_query) {
    die("Error fetching attendance.");
}

/* ================= PROCESS DATA ================= */
$totalDays = 0;
$present = 0;
$absent = 0;
$recentDays = [];

/* ---- SAFE PROCESSING ---- */
while ($row = $attendance_query->fetch_assoc()) {

    // ✅ Skip invalid or empty dates (PREVENT strtotime crash)
    if (empty($row['date']) || !strtotime($row['date'])) {
        continue;
    }

    $totalDays++;

    if (isset($row['status'])) {
        if ($row['status'] === 'present') {
            $present++;
        } elseif ($row['status'] === 'absent') {
            $absent++;
        }
    }

    // Get last 10 records only
    if (count($recentDays) < 10) {
        $recentDays[] = $row;
    }
}

/* ---- SAFE PERCENTAGE ---- */
$attendanceRate = ($totalDays > 0) 
    ? round(($present / $totalDays) * 100) 
    : 0;

?>
<!DOCTYPE html>
<html>
<head>
    <title>My Attendance</title>
     <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/studentportal.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h2 class="page-title">My Attendance</h2>
        <p class="page-subtitle">Track your attendance record</p>
    </div>

    <!-- STATS -->
    <div class="stats-grid auto-grid">

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Attendance Rate</span>
                <i class="fas fa-chart-line stat-icon text-green"></i>
            </div>
            <div class="stat-value"><?php echo $attendanceRate; ?>%</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $attendanceRate; ?>%"></div>
            </div>
            <p class="stat-meta">Your current attendance standing</p>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Days Present</span>
                <i class="fas fa-check-circle stat-icon text-green"></i>
            </div>
            <div class="stat-value"><?php echo $present; ?></div>
            <p class="stat-meta">Out of <?php echo $totalDays; ?> school days</p>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Days Absent</span>
                <i class="fas fa-times-circle stat-icon text-red"></i>
            </div>
            <div class="stat-value"><?php echo $absent; ?></div>
            <p class="stat-meta">Recorded absences</p>
        </div>

    </div>

    <!-- RECENT ATTENDANCE -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Attendance</h3>
            <p class="card-description">Your last 10 school days</p>
        </div>

        <div class="card-content">

            <?php if (!empty($recentDays)): ?>

                <?php foreach($recentDays as $day):

                    // ✅ Final safety check before displaying date
                    if (empty($day['date']) || !strtotime($day['date'])) {
                        continue;
                    }

                    $status = isset($day['status']) ? $day['status'] : 'unknown';

                    $color = ($status === 'present') ? 'green' : 'red';
                    $icon = ($status === 'present') ? 'check-circle' : 'times-circle';
                ?>

                <div class="attendance-row">
                    <div class="attendance-left">
                        <i class="fas fa-<?php echo $icon; ?> text-<?php echo $color; ?>"></i>
                        <p><?php echo date('l, F j, Y', strtotime($day['date'])); ?></p>
                    </div>
                    <span class="badge badge-<?php echo $color; ?>">
                        <?php echo ucfirst($status); ?>
                    </span>
                </div>

                <?php endforeach; ?>

            <?php else: ?>
                <p>No attendance records found.</p>
            <?php endif; ?>

        </div>
    </div>

    <!-- POLICY -->
    <div class="card policy-card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-calendar-alt"></i> Attendance Policy
            </h3>
        </div>

        <div class="card-content">
            <ul class="policy-list">
                <li>Minimum 75% attendance required for final exams</li>
                <li>3+ Consecutive absences automatic ABSENT</li>
                <li>Late arrivals may be marked absent</li>
                <li>Perfect attendance awards for 95%+ rate</li>
            </ul>
        </div>
    </div>

</div>
</body>
</html>