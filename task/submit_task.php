<?php
/**
 * TASK SUBMISSION HANDLER
 * Handles task creation and updates with subject association
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/paths.php';

// Get form data
$taskType = $_POST['taskType'] ?? '';
$subjectId = $_POST['subjectId'] ?? '';
$title = $_POST['taskTitle'] ?? '';
$description = $_POST['taskDescription'] ?? '';
$taskId = $_POST['taskId'] ?? '';
$dueDate = $_POST['dueDate'] ?? '';

// Debug: Log received data
error_log("Received taskType: $taskType, subjectId: $subjectId, title: $title");

// Validate input
if (empty($taskType)) {
    echo json_encode(['success' => false, 'message' => 'Task type is required']);
    exit();
}

if (empty($subjectId)) {
    echo json_encode(['success' => false, 'message' => 'Subject ID is required']);
    exit();
}

if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Task title is required']);
    exit();
}

if (empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Task description is required']);
    exit();
}

// Process due date
$dueDateFormatted = null;
if (!empty($dueDate)) {
    $dueDateFormatted = date('Y-m-d H:i:s', strtotime($dueDate));
}

// Validate subject_id
$subjectId = intval($subjectId);
if ($subjectId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid subject selected']);
    exit();
}

// Check if subject exists
$subjectCheck = mysqli_query($conn, "SELECT id FROM subjects WHERE id = $subjectId");
if (!$subjectCheck || mysqli_num_rows($subjectCheck) == 0) {
    echo json_encode(['success' => false, 'message' => 'Subject not found']);
    exit();
}

// Handle file upload
$attachmentPath = null;
$originalFileName = null;

if (isset($_FILES['taskAttachment']) && $_FILES['taskAttachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    
    if ($_FILES['taskAttachment']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error = $uploadErrors[$_FILES['taskAttachment']['error']] ?? 'Unknown upload error';
        echo json_encode(['success' => false, 'message' => 'Upload error: ' . $error]);
        exit();
    }
    
$uploadDir = TASK_UPLOADS_DIR;
    
    // Create uploads directory if it doesn't exist
if (!ensureWritable($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Upload directory not writable: ' . $uploadDir]);
        exit();
    }
    
    $originalFileName = $_FILES['taskAttachment']['name'];
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['taskAttachment']['tmp_name'], $targetPath)) {
        $attachmentPath = $fileName;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        exit();
    }
}

// Determine if this is an update or insert
if (!empty($taskId)) {
// UPDATE existing task
    $taskId = intval($taskId);
    
    // Build update query
    $sql = "UPDATE tasks SET 
            task_type = ?, 
            title = ?, 
            description = ?";
    
    $params = [$taskType, $title, $description];
    $types = "sss";
    
    // Add due date if provided
    if ($dueDateFormatted) {
        $sql .= ", due_date = ?";
        $params[] = $dueDateFormatted;
        $types .= "s";
    }
    
    // Add attachment if uploaded
    if ($attachmentPath) {
        $sql .= ", attachment = ?, original_filename = ?";
        $params[] = $attachmentPath;
        $params[] = $originalFileName;
        $types .= "ss";
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $taskId;
    $types .= "i";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        // Fetch the updated task
        $result = mysqli_query($conn, "SELECT * FROM tasks WHERE id = $taskId");
        $task = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'success' => true,
            'message' => 'Task updated successfully',
            'task' => $task
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Error updating task: ' . mysqli_error($conn)
        ]);
    }
} else {
// INSERT new task
    $sql = "INSERT INTO tasks (task_type, subject_id, title, description, attachment, original_filename, due_date, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sisssss", $taskType, $subjectId, $title, $description, $attachmentPath, $originalFileName, $dueDateFormatted);
    
    if (mysqli_stmt_execute($stmt)) {
        $newTaskId = mysqli_insert_id($conn);
        
        if ($newTaskId > 0) {
            // Fetch the created task
            $result = mysqli_query($conn, "SELECT * FROM tasks WHERE id = $newTaskId");
            $task = mysqli_fetch_assoc($result);
            
            echo json_encode([
                'success' => true,
                'message' => 'Task submitted successfully',
                'task' => $task
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Task inserted but failed to get ID'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Error submitting task: ' . mysqli_error($conn)
        ]);
    }
}
?>

