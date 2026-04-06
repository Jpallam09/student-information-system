<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/paths.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = intval($_SESSION['student_id']);
$task_id = intval($_GET['task_id'] ?? 0);

if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

// Check if connection exists
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get task details - using simple query first
$task_query = "SELECT * FROM tasks WHERE id = ?";
$task_stmt = $conn->prepare($task_query);
if (!$task_stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
    exit();
}

$task_stmt->bind_param('i', $task_id);
$task_stmt->execute();
$task_result = $task_stmt->get_result();
$task = $task_result->fetch_assoc();

if (!$task) {
    echo json_encode(['success' => false, 'message' => "Task ID $task_id not found in tasks table"]);
    $task_stmt->close();
    exit();
}

// Get subject name if subject_id exists
$subject_name = 'Unknown Subject';
if (!empty($task['subject_id'])) {
    $subject_query = "SELECT subject_name FROM subjects WHERE id = ?";
    $subject_stmt = $conn->prepare($subject_query);
    if ($subject_stmt) {
        $subject_stmt->bind_param('i', $task['subject_id']);
        $subject_stmt->execute();
        $subject_result = $subject_stmt->get_result();
        if ($subject_row = $subject_result->fetch_assoc()) {
            $subject_name = $subject_row['subject_name'];
        }
        $subject_stmt->close();
    }
}
$task['subject_name'] = $subject_name;

// Get submissions for this task
$query = "SELECT * FROM task_submissions WHERE task_id = ? ORDER BY submitted_at DESC";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare submission query: ' . $conn->error]);
    $task_stmt->close();
    exit();
}

$stmt->bind_param('i', $task_id);
$stmt->execute();
$result = $stmt->get_result();

$submissions = [];
while ($row = $result->fetch_assoc()) {
    $row['submitted_at_formatted'] = date('M j, Y g:i A', strtotime($row['submitted_at']));
    $row['teacher_read'] = isset($row['teacher_read']) ? (int)$row['teacher_read'] : 0;
    $submissions[] = $row;
}

// Return success response
echo json_encode([
    'success' => true,
    'task' => $task,
    'submissions' => $submissions
]);

$task_stmt->close();
$stmt->close();
mysqli_close($conn);
?>