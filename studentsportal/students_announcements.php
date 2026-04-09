<?php
session_start();

require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

// Redirect if student not logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* ================= STUDENT INFO ================= */
$stmt = $conn->prepare("SELECT course, year_level, section FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$studentData = $result->fetch_assoc();

if (!$studentData) {
    die("Student not found.");
}

$student_course  = strtoupper(trim($studentData['course']));
$student_year    = $studentData['year_level'];
$student_section = $studentData['section'];

if (empty($student_course)) {
    echo "<p>No course assigned to your account.</p>";
    exit();
}

/* ================= ANNOUNCEMENTS ================= */
$stmt = $conn->prepare("
    SELECT a.*,
    CONCAT(t.first_name,' ',IFNULL(t.middle_name,''),' ',t.last_name,' ',IFNULL(t.suffix,'')) AS teacher_name
    FROM announcements a
    JOIN teachers t ON a.teacher_id = t.id
    WHERE (
        (t.teacher_type IN ('Seeder', 'Administrator') AND UPPER(TRIM(a.course_id)) = ?)
        OR (
            UPPER(TRIM(a.course_id)) = ?
            AND (a.year_level = ? OR a.year_level = 'All')
            AND (a.section = ? OR a.section = 'All')
        )
    )
    ORDER BY a.pinned DESC, a.created_at DESC
");
$stmt->bind_param("ssss", $student_course, $student_course, $student_year, $student_section);
$stmt->execute();
$annQuery = $stmt->get_result();

/* ================= PROCESS DATA ================= */
$pinnedAnnouncements  = [];
$regularAnnouncements = [];

while ($row = $annQuery->fetch_assoc()) {
    $row['priority'] = $row['priority'] ?? 'medium';
    $row['pinned']   = $row['pinned']   ?? 0;

    if (empty($row['created_at']) || !strtotime($row['created_at'])) {
        $row['created_at'] = date('Y-m-d H:i:s');
    }

    if ($row['pinned']) {
        $pinnedAnnouncements[] = $row;
    } else {
        $regularAnnouncements[] = $row;
    }
}

/* ================= MARK ALL AS SEEN ================= */
$all_ids = array_merge(
    array_column($pinnedAnnouncements,  'id'),
    array_column($regularAnnouncements, 'id')
);

if (!empty($all_ids)) {
    $placeholders = implode(',', array_fill(0, count($all_ids), '?'));
    $types        = str_repeat('i', count($all_ids));

    $mark = $conn->prepare("
        INSERT IGNORE INTO student_seen_announcements (student_id, announcement_id)
        SELECT ?, id FROM announcements WHERE id IN ($placeholders)
    ");

    $params     = array_merge([$student_id], $all_ids);
    $bind_types = 'i' . $types;
    $mark->bind_param($bind_types, ...$params);
    $mark->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Announcements</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/studentportal.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h2 class="page-title">Announcements</h2>
        <p class="page-subtitle">Stay updated with school and class notices</p>
    </div>

    <!-- STATS -->
    <div class="stats-grid auto-grid">

        <div class="stat-card">
            <span>Total Announcements</span>
            <div class="stat-value">
                <?php echo count($pinnedAnnouncements) + count($regularAnnouncements); ?>
            </div>
        </div>

        <div class="stat-card">
            <span>Pinned</span>
            <div class="stat-value"><?php echo count($pinnedAnnouncements); ?></div>
        </div>

        <div class="stat-card">
            <span>High Priority</span>
            <div class="stat-value">
                <?php
                $highCount = 0;
                foreach (array_merge($pinnedAnnouncements, $regularAnnouncements) as $a) {
                    if (($a['priority'] ?? '') === 'high') $highCount++;
                }
                echo $highCount;
                ?>
            </div>
        </div>

    </div>

    <!-- PINNED -->
    <?php if (!empty($pinnedAnnouncements)): ?>
    <h3>📌 Pinned Announcements</h3>

    <?php foreach ($pinnedAnnouncements as $announcement):
        $priority   = $announcement['priority'] ?? 'medium';
        $badgeClass = $priority === 'high' ? 'badge-red' : ($priority === 'medium' ? 'badge-yellow' : 'badge-blue');
        $createdAt  = strtotime($announcement['created_at'])
            ? date('F j, Y h:i A', strtotime($announcement['created_at']))
            : 'N/A';
    ?>
    <div class="card">
        <h3><?php echo htmlspecialchars($announcement['title'] ?? ''); ?></h3>
        <span class="badge <?php echo $badgeClass; ?>">
            <?php echo ucfirst($priority); ?>
        </span>
        <p>
            Teacher: <?php echo htmlspecialchars($announcement['teacher_name'] ?? ''); ?>
            | <?php echo $createdAt; ?>
        </p>
        <p><?php echo htmlspecialchars($announcement['content'] ?? ''); ?></p>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- REGULAR -->
    <h3><?php echo !empty($pinnedAnnouncements) ? "All Announcements" : "Recent Announcements"; ?></h3>

    <?php if (empty($regularAnnouncements)): ?>
        <p style="color: var(--text-muted, #888);">No announcements at the moment.</p>
    <?php endif; ?>

    <?php foreach ($regularAnnouncements as $announcement):
        $priority   = $announcement['priority'] ?? 'medium';
        $badgeClass = $priority === 'high' ? 'badge-red' : ($priority === 'medium' ? 'badge-yellow' : 'badge-blue');
        $createdAt  = strtotime($announcement['created_at'])
            ? date('F j, Y h:i A', strtotime($announcement['created_at']))
            : 'N/A';
    ?>
    <div class="card">
        <h3><?php echo htmlspecialchars($announcement['title'] ?? ''); ?></h3>
        <span class="badge <?php echo $badgeClass; ?>">
            <?php echo ucfirst($priority); ?>
        </span>
        <p>
            Teacher: <?php echo htmlspecialchars($announcement['teacher_name'] ?? ''); ?>
            | <?php echo $createdAt; ?>
        </p>
        <p><?php echo htmlspecialchars($announcement['content'] ?? ''); ?></p>
    </div>
    <?php endforeach; ?>

</div>

</body>
</html>