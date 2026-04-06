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

// Verify student can access task
// Determine student course_id from either the student row or the course name stored in the student record.
$studentCourseStmt = $conn->prepare("SELECT course_id, course, year_level, section FROM students WHERE id = ?");
$studentCourseStmt->bind_param('i', $student_id);
$studentCourseStmt->execute();
$studentCourseResult = $studentCourseStmt->get_result();
$studentCourse = $studentCourseResult->fetch_assoc();

$studentCourseId = $studentCourse['course_id'];
if (empty($studentCourseId) && !empty($studentCourse['course'])) {
    $courseLookup = $conn->prepare("SELECT id FROM courses WHERE course_name = ? LIMIT 1");
    $courseLookup->bind_param('s', $studentCourse['course']);
    $courseLookup->execute();
    $courseLookupResult = $courseLookup->get_result();
    $courseRow = $courseLookupResult->fetch_assoc();
    if ($courseRow) {
        $studentCourseId = $courseRow['id'];
    }
}

if (empty($studentCourseId)) {
    echo json_encode(['success' => false, 'message' => 'Access denied to this task']);
    exit();
}

$access_query = "
    SELECT t.id FROM tasks t
    JOIN subjects s ON t.subject_id = s.id
    JOIN students st ON st.id = ?
    WHERE t.id = ? AND s.course_id = ?
      AND s.year_level = ?
      AND (s.section IS NULL OR s.section = '' OR s.section = ?)
";
$access_stmt = $conn->prepare($access_query);
$access_stmt->bind_param('iisss', $student_id, $task_id, $studentCourseId, $studentCourse['year_level'], $studentCourse['section']);
$access_stmt->execute();
if ($access_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Access denied to this task']);
    exit();
}

// Handle file upload
$upload_dir = PROJECT_ROOT . '/tasks/student_uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_path = null;
$original_filename = null;

if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'image/gif', 'text/plain'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if (!in_array($_FILES['submission_file']['type'], $allowed_types) || $_FILES['submission_file']['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type or size too large (10MB max, PDF/Word/Images/TXT)']);
        exit();
    }
    
    $file_ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
    $original_filename = $_FILES['submission_file']['name'];
    $file_path = 'student_' . $student_id . '_' . time() . '.' . $file_ext;
    $target_path = $upload_dir . $file_path;
    
    if (!move_uploaded_file($_FILES['submission_file']['tmp_name'], $target_path)) {
        echo json_encode(['success' => false, 'message' => 'File upload failed']);
        exit();
    }
}

// Insert or update submission (upsert on unique task_id+student_id)
$upsert_query = "
    INSERT INTO task_submissions (task_id, student_id, file_path, original_filename, notes, teacher_read) 
    VALUES (?, ?, ?, ?, ?, 0)
    ON DUPLICATE KEY UPDATE
    file_path = VALUES(file_path),
    original_filename = VALUES(original_filename), 
    notes = VALUES(notes),
    submitted_at = CURRENT_TIMESTAMP,
    teacher_read = 0
";
$stmt = $conn->prepare($upsert_query);
$stmt->bind_param('iisss', $task_id, $student_id, $file_path, $original_filename, $notes);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Task submitted successfully!',
        'submission_id' => $conn->insert_id
    ]);
} else {
    // Cleanup file on DB fail
    if ($file_path && file_exists($target_path)) {
        unlink($target_path);
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

mysqli_close($conn);
?>

