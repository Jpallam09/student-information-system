<?php
session_start();
include_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$submission_id = intval($_POST['submission_id'] ?? 0);

if ($submission_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission ID']);
    exit();
}

// Mark specific submission as read by this teacher
$sql = "UPDATE task_submissions SET teacher_read = 1 WHERE id = ? AND teacher_read = 0";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $submission_id);

if (mysqli_stmt_execute($stmt)) {
    $affected = mysqli_stmt_affected_rows($stmt);
    if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Submission marked as read']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Already read']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
?>

