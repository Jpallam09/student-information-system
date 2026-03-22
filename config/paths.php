<?php
/**
 * Centralized Path Definitions for Production Safety
 * Auto-detects script location, uses DIRECTORY_SEPARATOR
 */

// Base project root (relative to config/)
define('PROJECT_ROOT', realpath(__DIR__ . '/../'));

// Upload directories (script-relative + normalized separators)
define('TASK_UPLOADS_DIR', __DIR__ . '/../task/uploads/');
define('TASK_STUDENT_UPLOADS_DIR', __DIR__ . '/../task/student_uploads/');
define('PROFILE_PICS_DIR', __DIR__ . '/../profile_pics/');
define('ADMIN_LOGS_DIR', __DIR__ . '/../admin/');

// Web-accessible paths (for JS/HTML links - relative to docroot)
define('WEB_TASK_STUDENT_UPLOADS', '/Student Info/task/student_uploads/'); // Adjust for prod docroot
define('WEB_PROFILE_PICS', '/Student Info/profile_pics/');

// Ensure directories exist + safe permissions
$uploadDirs = [
    TASK_UPLOADS_DIR,
    TASK_STUDENT_UPLOADS_DIR,
    PROFILE_PICS_DIR
];
foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function ensureWritable($dir) {
    if (!is_dir($dir) || !is_writable($dir)) {
        error_log("Path not writable: $dir");
        return false;
    }
    return true;
}
?>

