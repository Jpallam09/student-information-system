<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

session_start();
include_once __DIR__ . '/../config/database.php';

$teacher_id = $_SESSION['teacher_id'] ?? 0;
if (!$teacher_id) {
    exit();
}

$last_event_id = $_SERVER['HTTP_LAST_EVENT_ID'] ?? 0;
$course_id = null;
if (isset($_SESSION['teacher_course']) && !empty($_SESSION['teacher_course'])) {
    $course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='" . mysqli_real_escape_string($conn, $_SESSION['teacher_course']) . "'");
    if ($course_result && $row = mysqli_fetch_assoc($course_result)) {
        $course_id = $row['id'];
    }
}

if (!$course_id) {
    exit();
}

function sendUnreadCounts($conn, $teacher_id, $course_id) {
    $subjects_query = mysqli_query($conn, "SELECT id FROM subjects WHERE course_id = '$course_id'");
    $unread_data = [];
    
    while ($subject = mysqli_fetch_assoc($subjects_query)) {
        $types = ['activities', 'homework', 'laboratory'];
        foreach ($types as $type) {
            $count_query = "SELECT COUNT(*) as unread_count 
                          FROM task_submissions ts 
                          JOIN tasks t ON ts.task_id = t.id 
                          WHERE t.subject_id = '{$subject['id']}' 
                          AND t.task_type = '$type' 
                          AND ts.teacher_read = 0 
                          AND t.teacher_id = '$teacher_id'";
            $count_result = mysqli_query($conn, $count_query);
            $count = mysqli_fetch_assoc($count_result);
            $unread_data["{$subject['id']}_$type"] = (int)$count['unread_count'];
        }
    }
    
    echo "data: " . json_encode($unread_data) . "\n\n";
    ob_flush();
    flush();
}

while (true) {
    sendUnreadCounts($conn, $teacher_id, $course_id);
    sleep(5); // Poll every 5 seconds
}
ob_end_flush();
?>


