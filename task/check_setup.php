<?php
/**
 * Database Setup Script
 * Adds subject_id column to tasks table if it doesn't exist
 */

include_once __DIR__ . '/../config/database.php';

echo "<h2>Checking and setting up database...</h2>";

// Check if tasks table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'tasks'");
if (mysqli_num_rows($result) == 0) {
    echo "<p style='color:red;'>Tasks table does not exist!</p>";
    exit();
}

// Check if subject_id column exists
$result = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE 'subject_id'");
if (mysqli_num_rows($result) == 0) {
    // Add subject_id column
    $sql = "ALTER TABLE tasks ADD COLUMN subject_id INT DEFAULT NULL AFTER task_type";
    if (mysqli_query($conn, $sql)) {
        echo "<p style='color:green;'>✓ Added subject_id column to tasks table</p>";
    } else {
        echo "<p style='color:red;'>Error adding subject_id: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p style='color:green;'>✓ subject_id column already exists</p>";
}

// Display current table structure
echo "<h3>Current tasks table structure:</h3>";
$result = mysqli_query($conn, "DESCRIBE tasks");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Database setup complete!</h3>";
echo "<p>You can now use the Task Management system with subjects.</p>";
?>
