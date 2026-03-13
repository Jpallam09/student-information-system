<?php
session_start();
include '../config/database.php';

// ✅ Check if student is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: ../Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// ✅ Fetch student info (optional if needed)
$student_query = mysqli_query($conn,"SELECT * FROM students WHERE id='$student_id'");
$student = mysqli_fetch_assoc($student_query);

// ✅ Fetch attendance records from database
$attendance_query = mysqli_query($conn, "SELECT * FROM attendance WHERE student_id='$student_id' ORDER BY date DESC");

// Initialize counters
$totalDays = 0;
$present = 0;
$absent = 0;
$recentDays = [];

while($row = mysqli_fetch_assoc($attendance_query)){
    $totalDays++;
    if($row['status'] == 'present'){
        $present++;
    } elseif($row['status'] == 'absent'){
        $absent++;
    }

    // Collect last 10 days
    if(count($recentDays) < 10){
        $recentDays[] = [
            'date' => $row['date'],
            'status' => $row['status']
        ];
    }
}

// Avoid division by zero
$attendanceRate = $totalDays ? round(($present / $totalDays) * 100) : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Attendance</title>
    <link rel="stylesheet" href="../css/studentportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include 'students_sidebar.php'; ?>

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
            <?php foreach($recentDays as $day):
                $color = $day['status']=='present'?'green':'red';
                $icon = $day['status']=='present'?'check-circle':'times-circle';
            ?>
            <div class="attendance-row">
                <div class="attendance-left">
                    <i class="fas fa-<?php echo $icon; ?> text-<?php echo $color; ?>"></i>
                    <p><?php echo date('l, F j, Y', strtotime($day['date'])); ?></p>
                </div>
                <span class="badge badge-<?php echo $color; ?>">
                    <?php echo ucfirst($day['status']); ?>
                </span>
            </div>
            <?php endforeach; ?>
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