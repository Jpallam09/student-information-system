<?php
include_once __DIR__ . '/../config/database.php';

$sql = "ALTER TABLE task_submissions 
        ADD COLUMN teacher_read TINYINT(1) DEFAULT 0 COMMENT '1 if teacher marked as read',
        ADD INDEX idx_teacher_read (teacher_read)";

if (mysqli_query($conn, $sql)) {
    echo "✅ Added teacher_read column to task_submissions";
} else {
    echo "Already exists or Error: " . mysqli_error($conn);
}
?>

