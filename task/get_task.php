<?php
/**
 * TASK RETRIEVAL HANDLER
 * Fetches tasks from database for specific subjects and categories
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
include_once __DIR__ . '/../config/database.php';

// Check if connection exists
if (!isset($conn) || !$conn) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed'
    ]);
    exit();
}

// Use mysqli
mysqli_set_charset($conn, 'utf8mb4');

// Check if getting single task by ID
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $taskId = intval($_GET['id']);
    
    $result = mysqli_query($conn, "SELECT * FROM tasks WHERE id = $taskId");
    
    if ($result && mysqli_num_rows($result) > 0) {
        $task = mysqli_fetch_assoc($result);
        // Format due_date for better display
        if ($task['due_date']) {
            $task['due_date_formatted'] = date('M j, Y g:i A', strtotime($task['due_date']));
            $task['due_date_raw'] = $task['due_date'];
            // Check if overdue
            $task['is_overdue'] = (strtotime($task['due_date']) < time());
        }
        echo json_encode([
            'success' => true, 
            'task' => $task
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Task not found'
        ]);
    }
    exit();
}

// Check if getting tasks by subject_id and type
if (isset($_GET['subject_id']) && !empty($_GET['subject_id']) && isset($_GET['type']) && !empty($_GET['type'])) {
    $subjectId = intval($_GET['subject_id']);
    $taskType = mysqli_real_escape_string($conn, $_GET['type']);
    
    // Validate task type
    $allowedTypes = ['activities', 'homework', 'laboratory'];
    if (!in_array($taskType, $allowedTypes)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid task type'
        ]);
        exit();
    }
    
// Get tasks for specific subject and type with submission counts
    $result = mysqli_query($conn, "
        SELECT t.*, s.subject_name, s.code, s.year_level, s.section,
               SUM(CASE WHEN ts.teacher_read = 0 THEN 1 ELSE 0 END) as unread_count
        FROM tasks t
        JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN task_submissions ts ON t.id = ts.task_id
        WHERE t.subject_id = $subjectId 
        AND t.task_type = '$taskType' 
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    
    $tasks = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $tasks[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks
    ]);
    exit();
}

// Legacy support: Get tasks by type only (for backward compatibility)
if (isset($_GET['type']) && !empty($_GET['type'])) {
    $taskType = mysqli_real_escape_string($conn, $_GET['type']);
    
    // Validate task type
    $allowedTypes = ['activities', 'homework', 'laboratory'];
    if (!in_array($taskType, $allowedTypes)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid task type'
        ]);
        exit();
    }
    
// Get all tasks by type with submission counts
    $result = mysqli_query($conn, "
        SELECT t.*, s.subject_name, s.code, s.year_level, s.section,
               SUM(CASE WHEN ts.teacher_read = 0 THEN 1 ELSE 0 END) as unread_count
        FROM tasks t
        JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN task_submissions ts ON t.id = ts.task_id
        WHERE t.task_type = '$taskType' 
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    
    $tasks = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $tasks[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks
    ]);
    exit();
}

// If no parameters provided
echo json_encode([
    'success' => false, 
    'message' => 'No parameters provided. Specify ?subject_id= and ?type= or ?type= or ?id='
]);
?>

