<?php
session_start();
include_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$task_id = intval($_GET['task_id'] ?? 0);
$teacher_id = $_SESSION['teacher_id'];

if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

// Mark ALL submissions for this task as read by this teacher
$sql = "UPDATE task_submissions SET teacher_read = 1 WHERE task_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $task_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Task marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
?>
