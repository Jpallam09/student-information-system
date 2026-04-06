<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Load paths FIRST (cloud-safe)
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

session_start();

// Validate session
$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    exit();
}

function sendStudentUpdates($conn, $student_id) {

    // ✅ Prepared statement (SAFE)
    $stmt = $conn->prepare("
        SELECT ts.task_id, ts.file_path, ts.teacher_read, t.title, t.task_type
        FROM task_submissions ts
        JOIN tasks t ON ts.task_id = t.id
        WHERE ts.student_id = ?
        AND ts.teacher_read = 1
        ORDER BY ts.updated_at DESC
        LIMIT 10
    ");

    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $updates = [];

    while ($row = $result->fetch_assoc()) {
        $updates[] = [
            'task_id' => $row['task_id'],
            'task_title' => $row['title'],
            'task_type' => $row['task_type'],
            'teacher_read' => true,
            'message' => 'Teacher viewed your submission!'
        ];
    }

    // ✅ Safe JSON output
    echo "data: " . json_encode($updates) . "\n\n";

    // Flush output (important for SSE)
    @ob_flush();
    @flush();
}

while (true) {

    // Prevent server overload (VERY IMPORTANT)
    set_time_limit(0);

    sendStudentUpdates($conn, $student_id);

    // Sleep to reduce CPU usage
    sleep(3);
}

?>