<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/paths.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$task_id = intval($data['task_id'] ?? 0);
$student_id = intval($_SESSION['student_id'] ?? 0);

if ($task_id <= 0 || $student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Delete submission (only own)
$query = "DELETE FROM task_submissions WHERE task_id = ? AND student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $task_id, $student_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Submission deleted successfully!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No submission found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}

mysqli_close($conn);
?>

