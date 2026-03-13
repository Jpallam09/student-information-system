<?php
/**
 * STUDENT SUBMISSION DELETE HANDLER
 * Allows students to delete their submitted task
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include_once __DIR__ . '/../config/database.php';

// Start session to get student ID
session_start();

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to delete submissions']);
    exit();
}

$student_id = $_SESSION['student_id'];

// Get task ID from POST or JSON
$taskId = 0;

if (isset($_POST['task_id']) && !empty($_POST['task_id'])) {
    $taskId = intval($_POST['task_id']);
} else {
    // Try JSON POST input
    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = intval($input['task_id'] ?? 0);
}

if ($taskId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Task ID is required']);
    exit();
}

// Check if submission exists for this student
$checkExisting = mysqli_query($conn, "
    SELECT id, file_path FROM task_submissions 
    WHERE task_id = $taskId AND student_id = $student_id
");

if (!$checkExisting || mysqli_num_rows($checkExisting) == 0) {
    echo json_encode(['success' => false, 'message' => 'No submission found']);
    exit();
}

$submission = mysqli_fetch_assoc($checkExisting);

// Delete the file if exists
if ($submission['file_path']) {
    $filePath = __DIR__ . '/student_uploads/' . $submission['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

// Delete from database
$deleteResult = mysqli_query($conn, "
    DELETE FROM task_submissions 
    WHERE task_id = $taskId AND student_id = $student_id
");

if ($deleteResult) {
    echo json_encode(['success' => true, 'message' => 'Submission deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting submission: ' . mysqli_error($conn)]);
}
?>

