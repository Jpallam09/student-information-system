<?php
/**
 * GET TASK SUBMISSIONS
 * Fetches all submissions for a specific task or for a subject/task_type combination
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

include_once __DIR__ . '/../config/database.php';

if (!isset($conn) || !$conn) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed'
    ]);
    exit();
}

mysqli_set_charset($conn, 'utf8mb4');

// Check if task_id is provided OR (subject_id and task_type) is provided
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : null;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : null;
$task_type = isset($_GET['task_type']) ? $_GET['task_type'] : null;

// Get submissions by task_id
if ($task_id) {
    // Get task info first
    $task_query = mysqli_query($conn, "SELECT * FROM tasks WHERE id = $task_id");
    if (!$task_query || mysqli_num_rows($task_query) == 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Task not found'
        ]);
        exit();
    }

    $task = mysqli_fetch_assoc($task_query);

    // Get all submissions for this task with student info
    $submissions_query = mysqli_query($conn, "
        SELECT 
            ts.id as submission_id,
            ts.task_id,
            ts.student_id,
            ts.file_path,
            ts.original_filename,
            ts.notes,
            ts.submitted_at,
            ts.teacher_read,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            s.email,
            s.course,
            s.year_level,
            s.section
        FROM task_submissions ts
        JOIN students s ON ts.student_id = s.id
        WHERE ts.task_id = $task_id
        ORDER BY ts.submitted_at DESC
    ");

    $submissions = [];
    if ($submissions_query) {
        while ($row = mysqli_fetch_assoc($submissions_query)) {
            $submissions[] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'task' => $task,
        'submissions' => $submissions,
        'total_submissions' => count($submissions)
    ]);
}
// Get submissions by subject_id and task_type
elseif ($subject_id && $task_type) {
    // Validate task_type
    $allowed_types = ['activities', 'homework', 'laboratory'];
    if (!in_array($task_type, $allowed_types)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid task type'
        ]);
        exit();
    }

    // Get all tasks for this subject and type
    $tasks_query = mysqli_query($conn, "
        SELECT id, title, task_type, subject_id 
        FROM tasks 
        WHERE subject_id = $subject_id AND task_type = '$task_type'
    ");
    
    $task_ids = [];
    $tasks = [];
    if ($tasks_query) {
        while ($row = mysqli_fetch_assoc($tasks_query)) {
            $task_ids[] = $row['id'];
            $tasks[] = $row;
        }
    }

    if (empty($task_ids)) {
        echo json_encode([
            'success' => true,
            'task_type' => $task_type,
            'tasks' => [],
            'submissions' => [],
            'total_submissions' => 0
        ]);
        exit();
    }

    $task_ids_str = implode(',', $task_ids);

    // Get all submissions for these tasks with student info
    $submissions_query = mysqli_query($conn, "
        SELECT 
            ts.id as submission_id,
            ts.task_id,
            ts.student_id,
            ts.file_path,
            ts.original_filename,
            ts.notes,
            ts.submitted_at,
            ts.teacher_read,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            s.email,
            s.course,
            s.year_level,
            s.section,
            t.title as task_title,
            t.task_type
        FROM task_submissions ts
        JOIN students s ON ts.student_id = s.id
        JOIN tasks t ON ts.task_id = t.id
        WHERE ts.task_id IN ($task_ids_str)
        ORDER BY ts.submitted_at DESC
    ");

    $submissions = [];
    if ($submissions_query) {
        while ($row = mysqli_fetch_assoc($submissions_query)) {
            $submissions[] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'task_type' => $task_type,
        'subject_id' => $subject_id,
        'tasks' => $tasks,
        'submissions' => $submissions,
        'total_submissions' => count($submissions)
    ]);
}
else {
    echo json_encode([
        'success' => false, 
        'message' => 'Either task_id or subject_id+task_type is required'
    ]);
}
?>

