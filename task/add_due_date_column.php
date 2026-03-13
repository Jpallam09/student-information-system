<?php
/**
 * Add due_date column to tasks table if it doesn't exist
 */
include_once __DIR__ . '/../config/database.php';

// Check if column exists
$result = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE 'due_date'");

if (mysqli_num_rows($result) == 0) {
    // Column doesn't exist, add it
    $alter = mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN due_date DATETIME NULL");
    
    if ($alter) {
        echo json_encode(['success' => true, 'message' => 'due_date column added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding column: ' . mysqli_error($conn)]);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'due_date column already exists']);
}
?>
</parameter>
</create_file>
