<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

session_start();
include '../config/database.php';

$student_id = $_SESSION['student_id'] ?? 0;
if (!$student_id) {
    exit();
}

function sendStudentUpdates($conn, $student_id) {
    // Get submissions that were just marked as read by teacher
    $query = "SELECT ts.task_id, ts.file_path, ts.teacher_read, t.title, t.task_type
              FROM task_submissions ts 
              JOIN tasks t ON ts.task_id = t.id 
              WHERE ts.student_id = $student_id 
              AND ts.teacher_read = 1
              ORDER BY ts.updated_at DESC LIMIT 10";
    
    $result = mysqli_query($conn, $query);
    $updates = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $updates[] = [
            'task_id' => $row['task_id'],
            'task_title' => $row['title'],
            'task_type' => $row['task_type'],
            'teacher_read' => true,
            'message' => 'Teacher viewed your submission!'
        ];
    }
    
    echo "data: " . json_encode($updates) . "\n\n";
    ob_flush();
    flush();
}

while (true) {
    sendStudentUpdates($conn, $student_id);
    sleep(3); // Check every 3 seconds
}

ob_end_flush();
?>

