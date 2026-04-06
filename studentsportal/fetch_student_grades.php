<?php
session_start();

require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$student_id = $_SESSION['student_id'];

/* =========================
   FETCH STUDENT INFO (SAFE)
========================= */
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    echo json_encode(['error' => 'Student not found']);
    exit();
}

/* =========================
   GPA
========================= */
$gpa = $student['gpa'] ?? 0;

/* =========================
   FETCH COURSE ID FIRST (FIX)
========================= */
$course_stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$course_stmt->bind_param("s", $student['course']);
$course_stmt->execute();
$course_result = $course_stmt->get_result();
$course_id = 0;
if ($course_result->num_rows > 0) {
    $course_row = $course_result->fetch_assoc();
    $course_id = $course_row['id'];
}
$course_stmt->close();

/* =========================
   SUBJECTS + GRADES (FIXED)
========================= */
$stmt = $conn->prepare("
    SELECT s.id AS subject_id, s.code AS subject_code, s.subject_name, s.credits,
           s.instructor AS teacher_name,
           g.quiz, g.homework, g.activities, g.prelim, g.midterm, g.final, g.lab, g.letter_grade
    FROM subjects s
    LEFT JOIN grades g ON g.subject_id = s.id AND g.student_id = ?
    WHERE s.course_id = ? 
      AND s.year_level = ? 
      AND (s.section = ? OR s.section IS NULL OR s.section = '')
    ORDER BY s.subject_name ASC
");
$year_level = $student['year_level'];
$section = $student['section'];
$stmt->bind_param("iiss", $student_id, $course_id, $year_level, $section);
$stmt->execute();
$subjects_result = $stmt->get_result();

/* =========================
   ASSIGNMENTS
========================= */
$stmt = $conn->prepare("
    SELECT a.title, s.code AS subject_code, a.type, a.due_date, a.max_score, a.status
    FROM assignments a
    INNER JOIN subjects s ON s.id = a.subject_id
    WHERE a.student_id = ?
    ORDER BY a.due_date ASC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();
$assignments_result = $stmt->get_result();

/* =========================
   FORMAT DATA
========================= */
$subjects = [];
while ($row = $subjects_result->fetch_assoc()) {
    $subjects[] = $row;
}

$assignments = [];
while ($row = $assignments_result->fetch_assoc()) {
    $assignments[] = $row;
}

/* =========================
   OUTPUT JSON
========================= */
echo json_encode([
    'gpa' => $gpa,
    'subjects' => $subjects,
    'assignments' => $assignments
]);