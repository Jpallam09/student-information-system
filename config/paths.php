<?php
/**
 * ============================================================
 * PATH CONFIGURATION (PRODUCTION-READY)
 * ============================================================
 *
 * - Separates ENVIRONMENT, ROOT PATH, BASE URL
 * - Works for localhost and cloud hosting
 * - Avoids fragile relative paths
 * ============================================================
 */

/**
 * ============================================================
 * ENVIRONMENT CONFIG
 * ============================================================
 * Change this depending on where you deploy:
 * 'local' | 'production'
 */
define('APP_ENV', 'local');

/**
 * ============================================================
 * BASE URL CONFIG
 * ============================================================
 * Use this for all links in your system
 */
if (APP_ENV === 'local') {
define('BASE_URL', 'http://localhost/Student_Info/');
} else {
    define('BASE_URL', 'https://yourdomain.com/');
}

/**
 * ============================================================
 * ROOT PATH (SERVER FILE SYSTEM)
 * ============================================================
 * This is the absolute path of your project folder
 */
define('PROJECT_ROOT', dirname(__DIR__));

/**
 * ============================================================
 * DIRECTORY PATHS (SERVER-SIDE)
 * ============================================================
 */
define('CONFIG_PATH', PROJECT_ROOT . '/config/');
define('TASK_UPLOADS_DIR', PROJECT_ROOT . '/tasks/uploads/');
define('TASK_STUDENT_UPLOADS_DIR', PROJECT_ROOT . '/tasks/student_uploads/');
define('PROFILE_PICS_DIR', PROJECT_ROOT . '/profile_pics/');
define('ADMIN_LOGS_DIR', PROJECT_ROOT . '/admin/logs/');
define('IMAGES_DIR', PROJECT_ROOT . '/images/');

/**
 * ============================================================
 * WEB PATHS (BROWSER ACCESS)
 * ============================================================
 */
define('WEB_TASK_UPLOADS', BASE_URL . 'task/uploads/');
define('WEB_TASK_STUDENT_UPLOADS', BASE_URL . 'task/student_uploads/');
define('WEB_PROFILE_PICS', BASE_URL . 'profile_pics/');
define('WEB_IMAGES', BASE_URL . 'images/');

/**
 * ============================================================
 * AUTO-CREATE REQUIRED DIRECTORIES
 * ============================================================
 */
$directories = [
    TASK_UPLOADS_DIR,
    TASK_STUDENT_UPLOADS_DIR,
    PROFILE_PICS_DIR,
    ADMIN_LOGS_DIR
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log("Failed to create directory: " . $dir);
        }
    }
}


/**
 * ============================================================
 * HELPER FUNCTIONS (CLEAN USAGE)
 * ============================================================
 */

/**
 * Generate full URL for assets
 */
function asset($path)
{
    return BASE_URL . ltrim($path, '/');
}

/**
 * Safely get file system path
 */
function path($path)
{
    return PROJECT_ROOT . '/' . ltrim($path, '/');
}

/**
 * Check if directory is writable
 */
function ensureWritable($dir)
{
    if (!is_dir($dir) || !is_writable($dir)) {
        error_log("Path not writable: " . $dir);
        return false;
    }
    return true;
}

/**
 * Get current base URL (for debugging or flexibility)
 */
function base_url()
{
    return BASE_URL;
}