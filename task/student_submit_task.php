<?php
/**
 * STUDENT TASK SUBMISSION HANDLER
 * Handles student task submissions with file uploads
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/paths.php';

// Start session to get student ID
session_start();

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit tasks']);
    exit();
}

$student_id = $_SESSION['student_id'];

// Get form data
$taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$notes = isset($_POST['submission_notes']) ? trim($_POST['submission_notes']) : '';

// Validate task ID
if ($taskId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

// Check if task exists and not overdue
$taskCheck = mysqli_query($conn, "SELECT id, due_date FROM tasks WHERE id = $taskId");
if (!$taskCheck || mysqli_num_rows($taskCheck) == 0) {
    echo json_encode(['success' => false, 'message' => 'Task not found']);
    exit();
}
$task = mysqli_fetch_assoc($taskCheck);
if (!empty($task['due_date']) && strtotime($task['due_date']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Task is overdue. Submissions are no longer accepted.']);
    exit();
}

// Create submissions table if not exists
$createTable = "
CREATE TABLE IF NOT EXISTS task_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255),
    original_filename VARCHAR(255),
    notes TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task_student (task_id, student_id),
    UNIQUE KEY unique_submission (task_id, student_id)
)";
mysqli_query($conn, $createTable);

// Handle file upload
$filePath = null;
$originalFileName = null;

if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    
    if ($_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error = $uploadErrors[$_FILES['submission_file']['error']] ?? 'Unknown upload error';
        echo json_encode(['success' => false, 'message' => 'Upload error: ' . $error]);
        exit();
    }
    
    // SECURITY: Validate file type (docs, images, PDFs only - no executables)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $_FILES['submission_file']['tmp_name']);
    finfo_close($finfo);
    
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain'
    ];
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, PDF, DOC/DOCX, XLS/XLSX, TXT']);
        exit();
    }
    
    // Size limit 10MB
    if ($_FILES['submission_file']['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large. Max 10MB']);
        exit();
    }
    
$uploadDir = TASK_STUDENT_UPLOADS_DIR;
    
    // Create uploads directory if it doesn't exist
if (!ensureWritable($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Upload directory not writable: ' . $uploadDir]);
        exit();
    }
    
    $originalFileName = $_FILES['submission_file']['name'];
    $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
    // Double-check extension
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
    if (!in_array($fileExtension, $allowed_exts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file extension']);
        exit();
    }
    
    $fileName = time() . '_student' . $student_id . '_' . uniqid() . '.' . $fileExtension;
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $targetPath)) {
        $filePath = $fileName;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        exit();
    }
}

// Check if student already submitted
$checkExisting = mysqli_query($conn, "
    SELECT id FROM task_submissions 
    WHERE task_id = $taskId AND student_id = $student_id
");

if ($checkExisting && mysqli_num_rows($checkExisting) > 0) {
    // Update existing submission
    if ($filePath) {
        $sql = "UPDATE task_submissions SET 
                file_path = ?, 
                original_filename = ?, 
                notes = ?,
                submitted_at = NOW()
                WHERE task_id = $taskId AND student_id = $student_id";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $filePath, $originalFileName, $notes);
    } else {
        $sql = "UPDATE task_submissions SET 
                notes = ?,
                submitted_at = NOW()
                WHERE task_id = $taskId AND student_id = $student_id";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $notes);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true,
            'message' => 'Submission updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating submission: ' . mysqli_error($conn)
        ]);
    }
} else {
    // Insert new submission
    $sql = "INSERT INTO task_submissions (task_id, student_id, file_path, original_filename, notes, submitted_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iisss", $taskId, $student_id, $filePath, $originalFileName, $notes);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true,
            'message' => 'Task submitted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error submitting task: ' . mysqli_error($conn)
        ]);
    }
}
?>

