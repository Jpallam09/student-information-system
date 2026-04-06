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

$query = "SELECT COUNT(*) as unread_count
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    WHERE t.subject_id = ? AND ts.teacher_read = 0";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'count' => (int)$row['unread_count']
]);

$stmt->close();
mysqli_close($conn);
?>