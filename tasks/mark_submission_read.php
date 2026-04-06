<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json');

// Allow any logged in teacher to mark as read
if (!isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get the raw input
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

// Debug logging
error_log("Raw input: " . $raw_input);
error_log("Decoded data: " . print_r($data, true));

$task_id = 0;
$student_id = 0;

// Try to get parameters from JSON
if ($data && isset($data['task_id'])) {
    $task_id = intval($data['task_id']);
    $student_id = intval($data['student_id']);
} 
// Try from POST
elseif (isset($_POST['task_id'])) {
    $task_id = intval($_POST['task_id']);
    $student_id = intval($_POST['student_id']);
} 
// Try from GET
elseif (isset($_GET['task_id'])) {
    $task_id = intval($_GET['task_id']);
    $student_id = intval($_GET['student_id']);
}

// Validate parameters
if ($task_id <= 0 || $student_id <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid parameters',
        'task_id' => $task_id,
        'student_id' => $student_id,
        'received_data' => $data
    ]);
    exit();
}

// Direct update without verification
$query = "UPDATE task_submissions SET teacher_read = 1 WHERE task_id = ? AND student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $task_id, $student_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Submission marked as read'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No submission found to update',
            'task_id' => $task_id,
            'student_id' => $student_id
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $stmt->error
    ]);
}

$stmt->close();
mysqli_close($conn);
?>