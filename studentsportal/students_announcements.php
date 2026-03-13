<?php
session_start();
include '../config/database.php';

// Redirect if student not logged in
if(!isset($_SESSION['student_id'])){
    header("Location: ../Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Get student info
$studentQuery = mysqli_query($conn, "SELECT course, year_level, section FROM students WHERE id='$student_id'");
$studentData = mysqli_fetch_assoc($studentQuery);

$student_course = strtoupper(trim($studentData['course']));
$student_year = $studentData['year_level'];
$student_section = $studentData['section'];

if(empty($student_course)){
    echo "<p>No course assigned to your account.</p>";
    exit();
}

// Fetch announcements for this student
// Show announcements that match student's course AND (year_level/section match OR announcement is from admin)
$annQuery = mysqli_query($conn, "
    SELECT a.*, CONCAT(t.first_name,' ',IFNULL(t.middle_name,''),' ',t.last_name,' ',IFNULL(t.suffix,'')) AS teacher_name
    FROM announcements a
    JOIN teachers t ON a.teacher_id = t.id
    WHERE (
        (UPPER(TRIM(a.course_id))='$student_course' AND ((a.year_level='$student_year' OR a.year_level='All') AND (a.section='$student_section' OR a.section='All')))
        OR 
        (t.teacher_type IN ('Seeder', 'Administrator'))
    )
    ORDER BY a.pinned DESC, a.created_at DESC
");

// Separate pinned and regular
$pinnedAnnouncements = [];
$regularAnnouncements = [];
while($row = mysqli_fetch_assoc($annQuery)){
    if(!isset($row['priority']) || empty($row['priority'])) $row['priority'] = 'medium';
    if($row['pinned']){
        $pinnedAnnouncements[] = $row;
    } else {
        $regularAnnouncements[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Announcements</title>
<link rel="stylesheet" href="../css/studentportal.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'students_sidebar.php'; ?>

<div class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h2 class="page-title">Announcements</h2>
        <p class="page-subtitle">Stay updated with school and class notices</p>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid auto-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Announcements</span>
                <i class="fas fa-bell stat-icon"></i>
            </div>
            <div class="stat-value"><?= count($pinnedAnnouncements) + count($regularAnnouncements) ?></div>
            <p class="stat-meta">This course</p>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Pinned Items</span>
                <i class="fas fa-thumbtack stat-icon text-blue"></i>
            </div>
            <div class="stat-value"><?= count($pinnedAnnouncements) ?></div>
            <p class="stat-meta">Important notices</p>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">High Priority</span>
                <i class="fas fa-exclamation-circle stat-icon text-red"></i>
            </div>
            <div class="stat-value">
                <?php
                $highCount = 0;
                foreach(array_merge($pinnedAnnouncements,$regularAnnouncements) as $a){
                    if($a['priority']=='high') $highCount++;
                }
                echo $highCount;
                ?>
            </div>
            <p class="stat-meta">Requires attention</p>
        </div>
    </div>

    <!-- PINNED ANNOUNCEMENTS -->
    <?php if(count($pinnedAnnouncements) > 0): ?>
    <div class="announcement-section">
        <div class="section-title">
            <i class="fas fa-thumbtack text-blue"></i>
            <h3>Pinned Announcements</h3>
        </div>

        <?php foreach($pinnedAnnouncements as $announcement):
            $badgeClass = $announcement['priority']=='high'?'badge-red':($announcement['priority']=='medium'?'badge-yellow':'badge-blue');
        ?>
        <div class="card pinned-card">
            <div class="card-header">
                <div class="announcement-title">
                    <h3><?= htmlspecialchars($announcement['title']); ?></h3>
                    <span class="badge <?= $badgeClass; ?>"><?= ucfirst($announcement['priority']); ?></span>
                    <span class="badge badge-gray"><?= htmlspecialchars($announcement['section']); ?></span>
                </div>
                <p class="announcement-meta">
                    Teacher: <?= htmlspecialchars($announcement['teacher_name']); ?> |
                    <i class="fas fa-calendar-alt"></i>
                    <?= date('F j, Y h:i A', strtotime($announcement['created_at'])); ?>
                </p>
            </div>
            <div class="card-content">
                <p><?= htmlspecialchars($announcement['content']); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- REGULAR ANNOUNCEMENTS -->
    <div class="announcement-section">
        <h3 class="section-title-simple">
            <?= count($pinnedAnnouncements) > 0 ? "All Announcements" : "Recent Announcements"; ?>
        </h3>

        <?php foreach($regularAnnouncements as $announcement):
            $badgeClass = $announcement['priority']=='high'?'badge-red':($announcement['priority']=='medium'?'badge-yellow':'badge-blue');
        ?>
        <div class="card">
            <div class="card-header">
                <div class="announcement-title">
                    <h3><?= htmlspecialchars($announcement['title']); ?></h3>
                    <span class="badge <?= $badgeClass; ?>"><?= ucfirst($announcement['priority']); ?></span>
                    <span class="badge badge-gray"><?= htmlspecialchars($announcement['section']); ?></span>
                </div>
                <p class="announcement-meta">
                    Teacher: <?= htmlspecialchars($announcement['teacher_name']); ?> |
                    <i class="fas fa-calendar-alt"></i>
                    <?= date('F j, Y h:i A', strtotime($announcement['created_at'])); ?>
                </p>
            </div>
            <div class="card-content">
                <p><?= htmlspecialchars($announcement['content']); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>
</body>
</html>