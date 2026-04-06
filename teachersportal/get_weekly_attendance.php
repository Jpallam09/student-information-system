<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

// ✅ Check login
if (!isset($_SESSION['teacher_id'])) {
    echo json_encode(['labels'=>[], 'data'=>[]]);
    exit();
}

// ✅ Get selected course
$selected_course = $_SESSION['teacher_course'] ?? '';
if (empty($selected_course)) {
    echo json_encode(['labels'=>[], 'data'=>[]]);
    exit();
}

// ✅ Filters from dashboard modal
$year_level = $_GET['year_level'] ?? '';
$section = $_GET['section'] ?? '';

// ✅ Escape inputs (SECURITY)
$selected_course = mysqli_real_escape_string($conn, $selected_course);

$year_filter = '';
$section_filter = '';

if (!empty($year_level)) {
    $year_level = mysqli_real_escape_string($conn, $year_level);
    $year_filter = " AND year_level='$year_level'";
}

if (!empty($section)) {
    $section = mysqli_real_escape_string($conn, $section);
    $section_filter = " AND section='$section'";
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$attendance_data = [];

// ✅ Get all student IDs
$students_query = mysqli_query($conn, "
    SELECT id FROM students
    WHERE course='$selected_course' $year_filter $section_filter
");

$student_ids = [];

while ($row = mysqli_fetch_assoc($students_query)) {
    $student_ids[] = (int)$row['id']; // force integer
}

// ✅ If no students found
if (empty($student_ids)) {
    echo json_encode([
        'labels' => $days,
        'data' => array_fill(0, count($days), 0)
    ]);
    exit();
}

$total_students = count($student_ids);

// Convert IDs to safe string
$ids = implode(',', $student_ids);

// ✅ Calculate attendance per day
foreach ($days as $day) {
    $present_query = mysqli_query($conn, "
        SELECT COUNT(*) AS present_count
        FROM attendance
        WHERE student_id IN ($ids)
        AND status='present'
        AND DAYNAME(date)='$day'
    ");

    $present_row = mysqli_fetch_assoc($present_query);
    $present_count = (int)$present_row['present_count'];

    $attendance_percent = round(($present_count / $total_students) * 100, 2);
    $attendance_data[] = $attendance_percent;
}

// ✅ Output JSON
echo json_encode([
    'labels' => $days,
    'data' => $attendance_data
]);