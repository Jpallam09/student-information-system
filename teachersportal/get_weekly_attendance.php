<?php
session_start();
include '../config/database.php';

// ✅ Check login
if(!isset($_SESSION['teacher_id'])){
    echo json_encode(['labels'=>[], 'data'=>[]]);
    exit();
}

// ✅ Get selected course
$selected_course = $_SESSION['selected_course'] ?? '';
if(empty($selected_course)){
    echo json_encode(['labels'=>[], 'data'=>[]]);
    exit();
}

// ✅ Filters from dashboard modal
$year_level = $_GET['year_level'] ?? '';
$section = $_GET['section'] ?? '';

$year_filter = $year_level ? " AND year_level='$year_level'" : "";
$section_filter = $section ? " AND section='$section'" : "";

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$attendance_data = [];

// ✅ Get all student IDs for the selected course + filters
$students_query = mysqli_query($conn, "
    SELECT id FROM students
    WHERE course='$selected_course' $year_filter $section_filter
");
$student_ids = [];
while($row = mysqli_fetch_assoc($students_query)){
    $student_ids[] = $row['id'];
}

$total_students = count($student_ids);

// ✅ Calculate attendance % for each day
foreach($days as $day){
    if($total_students === 0){
        $attendance_data[] = 0;
        continue;
    }

    $ids = implode(',', $student_ids); // safe because IDs are integers

    $present_query = mysqli_query($conn, "
        SELECT COUNT(*) AS present_count
        FROM attendance
        WHERE student_id IN ($ids) AND status='present' AND DAYNAME(date)='$day'
    ");
    $present_row = mysqli_fetch_assoc($present_query);
    $present_count = (int)$present_row['present_count'];

    $attendance_percent = round(($present_count / $total_students) * 100, 2);
    $attendance_data[] = $attendance_percent;
}

// ✅ Return JSON
echo json_encode([
    'labels' => $days,
    'data' => $attendance_data
]);