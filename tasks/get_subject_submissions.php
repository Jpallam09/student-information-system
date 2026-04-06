<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$subject_id = intval($_GET['subject_id'] ?? 0);
if ($subject_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid subject ID']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_type = $_SESSION['teacher_type'] ?? '';
$is_admin = in_array($teacher_type, ['Seeder', 'Administrator']);

// For non-admin users, verify they have access to this subject
if (!$is_admin) {
    $verify_query = "SELECT id FROM subjects WHERE id = ? AND (teacher_id = ? OR teacher_id IS NULL)";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param('ii', $subject_id, $teacher_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Access denied'
        ]);
        exit();
    }
    $verify_stmt->close();
}

// Main query to get submissions - using correct task_id
$query = "
    SELECT 
        s.id as student_id,
        CONCAT(
            COALESCE(s.first_name, ''),
            ' ',
            COALESCE(s.last_name, '')
        ) as student_name,
        s.year_level,
        s.section,
        ts.id as submission_id,
        ts.task_id,
        t.title as task_title,
        t.task_type,
        ts.file_path,
        ts.original_filename,
        ts.notes,
        ts.submitted_at,
        ts.teacher_read
    FROM task_submissions ts
    INNER JOIN tasks t ON ts.task_id = t.id
    INNER JOIN students s ON ts.student_id = s.id
    WHERE t.subject_id = ?
    ORDER BY s.last_name ASC, s.first_name ASC, ts.submitted_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$submissions = [];
while ($row = $result->fetch_assoc()) {
    $student_id_key = $row['student_id'];
    
    if (!isset($submissions[$student_id_key])) {
        $submissions[$student_id_key] = [
            'student_id' => (int)$row['student_id'],
            'student_name' => trim($row['student_name']),
            'year_level' => $row['year_level'],
            'section' => $row['section'],
            'submissions' => []
        ];
    }
    
    // Handle notes
    $notes = $row['notes'];
    if ($notes === 'undefined' || $notes === 'null' || $notes === null || $notes === '') {
        $notes = 'No notes provided';
    }
    
    $submissions[$student_id_key]['submissions'][] = [
        'task_id' => (int)$row['task_id'],  // This should be 129 for your data
        'task_title' => htmlspecialchars($row['task_title']),
        'task_type' => ucfirst($row['task_type']),
        'submitted_at' => date('M j, Y g:i A', strtotime($row['submitted_at'])),
        'file_path' => $row['file_path'],
        'original_filename' => htmlspecialchars($row['original_filename'] ?: 'No file'),
        'notes' => htmlspecialchars($notes),
        'teacher_read' => (int)$row['teacher_read'] == 1
    ];
}

// Convert to indexed array
$submissions_array = array_values($submissions);

echo json_encode([
    'success' => true,
    'submissions' => $submissions_array,
    'count' => count($submissions_array),
    'subject_id' => $subject_id
]);

$stmt->close();
mysqli_close($conn);
?>