<?php
/**
 * TASK DELETE HANDLER
 * Deletes tasks from database
 */
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Support both GET and POST requests
$taskId = 0;

// Try GET request first
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $taskId = intval($_GET['id']);
} else {
    // Try JSON POST input
    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = intval($input['id'] ?? 0);
}

if ($taskId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Task ID is required']);
    exit();
}

include_once __DIR__ . '/../config/database.php';

// Get attachment filename before deleting
$result = mysqli_query($conn, "SELECT attachment FROM tasks WHERE id = $taskId");
$task = null;
if ($result && mysqli_num_rows($result) > 0) {
    $task = mysqli_fetch_assoc($result);
}

// Delete from database
$deleteResult = mysqli_query($conn, "DELETE FROM tasks WHERE id = $taskId");

if ($deleteResult) {
    // Delete attachment file if exists
    if ($task && $task['attachment']) {
        $filePath = __DIR__ . '/uploads/' . $task['attachment'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting task: ' . mysqli_error($conn)]);
}
?>

