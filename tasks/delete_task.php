<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/paths.php';

if (!isset($_SESSION['teacher_id'])) {
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

$teacher_id = intval($_SESSION['teacher_id']);
$task_id    = intval($_GET['task_id'] ?? 0);

if ($task_id <= 0) {
    header("Location: " . BASE_URL . "teachersportal/tasks.php");
    exit();
}

// Verify the task belongs to this teacher before deleting
$verify = $conn->prepare("SELECT attachment FROM tasks WHERE id = ? AND teacher_id = ?");
$verify->bind_param('ii', $task_id, $teacher_id);
$verify->execute();
$result = $verify->get_result();
$task   = $result->fetch_assoc();
$verify->close();

if (!$task) {
    // Task not found or doesn't belong to this teacher
    header("Location: " . BASE_URL . "teachersportal/tasks.php");
    exit();
}

// Delete associated student submissions' files first
$subs = $conn->prepare("SELECT file_path FROM task_submissions WHERE task_id = ?");
$subs->bind_param('i', $task_id);
$subs->execute();
$subs_result = $subs->get_result();
while ($sub = $subs_result->fetch_assoc()) {
    if ($sub['file_path']) {
        $student_file = PROJECT_ROOT . '/tasks/student_uploads/' . $sub['file_path'];
        if (file_exists($student_file)) {
            unlink($student_file);
        }
    }
}
$subs->close();

// Delete student submissions from DB
$del_subs = $conn->prepare("DELETE FROM task_submissions WHERE task_id = ?");
$del_subs->bind_param('i', $task_id);
$del_subs->execute();
$del_subs->close();

// Delete teacher's attachment file
if ($task['attachment']) {
    $attachment_path = PROJECT_ROOT . '/tasks/uploads/' . $task['attachment'];
    if (file_exists($attachment_path)) {
        unlink($attachment_path);
    }
}

// Delete the task
$del = $conn->prepare("DELETE FROM tasks WHERE id = ? AND teacher_id = ?");
$del->bind_param('ii', $task_id, $teacher_id);
$del->execute();
$del->close();

mysqli_close($conn);

header("Location: " . BASE_URL . "teachersportal/tasks.php");
exit();
?>