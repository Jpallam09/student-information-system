<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/paths.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit();
}

$student_id = intval($_SESSION['student_id']);
$task_id = intval($_POST['task_id'] ?? 0);
$notes = trim($_POST['submission_notes'] ?? '');

if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

// Check if student has existing submission
$check_query = "SELECT id FROM task_submissions WHERE task_id = ? AND student_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param('ii', $task_id, $student_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No existing submission found']);
    exit();
}

// Handle optional new file
$upload_dir = PROJECT_ROOT . '/tasks/student_uploads/';
$file_path = null;
$original_filename = null;
$old_file_path = null;

if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
    // Get old file path first
    $old_query = "SELECT file_path FROM task_submissions WHERE task_id = ? AND student_id = ?";
    $old_stmt = $conn->prepare($old_query);
    $old_stmt->bind_param('ii', $task_id, $student_id);
    $old_stmt->execute();
    $old_result = $old_stmt->get_result();
    if ($old_row = $old_result->fetch_assoc()) {
        $old_file_path = $old_row['file_path'];
    }
    
    // Validate/upload new file
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'image/gif', 'text/plain'];
    $max_size = 10 * 1024 * 1024;
    
    if (in_array($_FILES['submission_file']['type'], $allowed_types) && $_FILES['submission_file']['size'] <= $max_size) {
        $file_ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
        $original_filename = $_FILES['submission_file']['name'];
        $file_path = 'student_' . $student_id . '_' . time() . '.' . $file_ext;
        $target_path = $upload_dir . $file_path;
        
        if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target_path)) {
            // Delete old file
            if ($old_file_path && file_exists($upload_dir . $old_file_path)) {
                unlink($upload_dir . $old_file_path);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'File upload failed']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type/size']);
        exit();
    }
}

$file_path_param = $file_path ?: $old_file_path;
$original_filename_param = $original_filename ?: null;

// Update submission
$update_query = "
    UPDATE task_submissions 
    SET file_path = ?, original_filename = ?, notes = ?, submitted_at = CURRENT_TIMESTAMP, teacher_read = 0
    WHERE task_id = ? AND student_id = ?
";
$stmt = $conn->prepare($update_query);
$stmt->bind_param('sssii', $file_path_param, $original_filename_param, $notes, $task_id, $student_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Submission updated successfully!'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

mysqli_close($conn);
?>

