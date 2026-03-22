<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/paths.php';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get form data
$taskId = $_POST['taskId'] ?? '';
$taskType = $_POST['taskType'] ?? '';
$title = $_POST['taskTitle'] ?? '';
$description = $_POST['taskDescription'] ?? '';

if (empty($taskId) || empty($taskType) || empty($title) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Handle file upload
$attachmentPath = null;
$originalFileName = null;

if (isset($_FILES['taskAttachment']) && $_FILES['taskAttachment']['error'] !== UPLOAD_ERR_NO_FILE) {
$uploadDir = TASK_UPLOADS_DIR;

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
    }
}

// Update database
try {
    if ($attachmentPath) {
        // Update with new attachment
        $sql = "UPDATE tasks SET task_type = :task_type, title = :title, description = :description, 
                attachment = :attachment, original_filename = :original_filename WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':task_type' => $taskType,
            ':title' => $title,
            ':description' => $description,
            ':attachment' => $attachmentPath,
            ':original_filename' => $originalFileName,
            ':id' => $taskId
        ]);
    } else {
        // Update without changing attachment
        $sql = "UPDATE tasks SET task_type = :task_type, title = :title, description = :description WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':task_type' => $taskType,
            ':title' => $title,
            ':description' => $description,
            ':id' => $taskId
        ]);
    }
    
    // Get updated task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
    $stmt->execute([':id' => $taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Task updated successfully',
        'task' => $task
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>