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
$task_id = intval($_GET['id'] ?? 0);

if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID. Received: ' . ($_GET['id'] ?? 'null')]);
    exit();
}

// Simplified query - just get the task and subject info
$query = "
    SELECT t.*, COALESCE(s.subject_name, 'Unknown Subject') as subject_name, s.code
    FROM tasks t
    LEFT JOIN subjects s ON t.subject_id = s.id
    WHERE t.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $row['due_date_formatted'] = $row['due_date'] ? date('M j, Y', strtotime($row['due_date'])) : null;
    $row['is_overdue'] = (!empty($row['due_date']) && strtotime($row['due_date']) < time()) ? 1 : 0;
    $row['created_at_formatted'] = date('M j, Y', strtotime($row['created_at']));
    
    echo json_encode([
        'success' => true,
        'task' => $row
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => "Task with ID $task_id not found"
    ]);
}

$stmt->close();
mysqli_close($conn);
?>