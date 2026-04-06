<?php
session_start();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Disable time limit for SSE
set_time_limit(0);

if (!isset($_SESSION['student_id'])) {
    echo "data: " . json_encode(['error' => 'Unauthorized']) . "\n\n";
    exit();
}

$student_id = $_SESSION['student_id'];
$last_check = time();

// Simple polling fallback - check for new teacher views every 5 seconds
while (true) {
    require_once dirname(__DIR__) . '/config/database.php';
    
    // Check for submissions that were viewed by teacher
    // Removed last_notified column dependency
    $query = "
        SELECT ts.task_id, t.title as task_title, ts.teacher_read, ts.updated_at
        FROM task_submissions ts
        JOIN tasks t ON ts.task_id = t.id
        WHERE ts.student_id = ? 
        AND ts.teacher_read = 1
        ORDER BY ts.updated_at DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $updates = [];
    while ($row = $result->fetch_assoc()) {
        $updates[] = [
            'task_id' => $row['task_id'],
            'task_title' => $row['task_title'],
            'teacher_read' => 1
        ];
    }
    
    if (!empty($updates)) {
        echo "data: " . json_encode($updates) . "\n\n";
        ob_flush();
        flush();
    } else {
        // Send heartbeat to keep connection alive
        echo ": heartbeat\n\n";
        ob_flush();
        flush();
    }
    
    $stmt->close();
    mysqli_close($conn);
    
    // Wait 5 seconds before next check
    sleep(5);
    
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }
}
?>