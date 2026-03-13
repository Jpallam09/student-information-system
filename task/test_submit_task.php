<?php
/**
 * Test submit_task.php directly
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Test receiving POST data
$taskType = isset($_POST['taskType']) ? $_POST['taskType'] : '';
$subjectId = isset($_POST['subjectId']) ? $_POST['subjectId'] : '';
$title = isset($_POST['taskTitle']) ? $_POST['taskTitle'] : '';
$description = isset($_POST['taskDescription']) ? $_POST['taskDescription'] : '';

echo json_encode([
    'success' => true,
    'received' => [
        'taskType' => $taskType,
        'subjectId' => $subjectId,
        'title' => $title,
        'description' => $description
    ]
]);
?>

