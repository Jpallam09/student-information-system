<?php
session_start();

require_once dirname(__DIR__) . '/config/paths.php';
require_once CONFIG_PATH . 'database.php';

/* -----------------------------
   STUDENT AUTHENTICATION
------------------------------ */
if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* -----------------------------
   GET STUDENT INFO (SECURE)
------------------------------ */
$stmt = $conn->prepare("SELECT course, year_level, section FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Student not found.");
}

$course_name = $student['course'];
$year        = $student['year_level'];
$section     = $student['section'];

/* -----------------------------
   GET COURSE ID (SECURE)
------------------------------ */
$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$stmt->bind_param("s", $course_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Course not found in database.");
}

$course_row = $result->fetch_assoc();
$course_id = $course_row['id'];

/* -----------------------------
   FETCH SUBJECTS WITH SCHEDULE
------------------------------ */
$stmt = $conn->prepare("
    SELECT 
        s.code, 
        s.subject_name, 
        s.description, 
        s.room, 
        s.day, 
        s.time_start, 
        s.time_end, 
 s.instructor, 
        s.subject_type,
        s.section,
        CONCAT(
            t.first_name, ' ', 
            IFNULL(t.middle_name, ''), ' ', 
            t.last_name, ' ', 
            IFNULL(t.suffix, '')
        ) AS instructor_name,
        t.email AS instructor_email
    FROM subjects s
    LEFT JOIN teachers t ON s.instructor = t.teacher_id
    WHERE s.course_id = ?
      AND s.year_level = ?
      AND (s.section IS NULL OR s.section = '' OR s.section = ?)
    ORDER BY s.subject_name ASC
");

$stmt->bind_param("iss", $course_id, $year, $section);
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subjects & Teachers</title>
<link rel="stylesheet" href="<?= asset('css/studentportal.css') ?>">
<style>
.badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; color: white; }
.badge-major { background-color: #3b82f6; }
.badge-minor { background-color: #6b7280; }
</style>
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <h2>Subjects & Teachers</h2>
    <p>
        <?= htmlspecialchars($course_name) ?> - 
        <?= htmlspecialchars($year) ?> - 
        Section <?= htmlspecialchars($section) ?>
    </p>

    <?php if (count($subjects) > 0): ?>
        <?php foreach ($subjects as $subject): ?>
            <div class="card">

                <!-- Subject Name -->
                <h3><?= htmlspecialchars($subject['subject_name']) ?></h3>

<!-- Subject Code -->
                <p>
                    <span class="label">Subject Code:</span> 
                    <?= htmlspecialchars($subject['code']) ?>
                </p>

                <!-- Type -->
                <p>
                    <span class="label">Type:</span> 
                    <span class="badge <?= $subject['subject_type']=='MINOR' ? 'badge-minor' : 'badge-major' ?>"><?= strtoupper($subject['subject_type']) ?></span>
                </p>

                <!-- Description -->
                <p>
                    <span class="label">Description:</span> 
                    <?= htmlspecialchars($subject['description'] ?: '-') ?>
                </p>

                <!-- Room -->
                <p>
                    <span class="label">Room:</span> 
                    <?= htmlspecialchars($subject['room'] ?: '-') ?>
                </p>

                <!-- Schedule -->
                <p>
                    <span class="label">Schedule:</span> 
                    <?= htmlspecialchars($subject['day'] ?: '-') ?>
                    <span class="schedule-time">
                        <?= $subject['time_start'] ? date("h:i A", strtotime($subject['time_start'])) : '-' ?> - 
                        <?= $subject['time_end'] ? date("h:i A", strtotime($subject['time_end'])) : '-' ?>
                    </span>
                </p>

                <!-- Instructor -->
                <p>
                    <span class="label">Instructor:</span> 
                    <?= htmlspecialchars(trim($subject['instructor_name']) ?: $subject['instructor']) ?>
                </p>

                <!-- Email -->
                <p>
                    <span class="label">Email:</span> 
                    <?= htmlspecialchars($subject['instructor_email'] ?: '-') ?>
                </p>

            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No subjects found for your course, year level, or section.</p>
    <?php endif; ?>

</div>

</body>
</html>