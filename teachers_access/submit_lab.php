<?php
session_start();
if (!isset($_SESSION['teacher_id'])) {
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';
$task_type = 'laboratory';  // Matches enum: 'laboratory'
$task_title = 'Laboratory Submission';
$upload_dir = TASK_UPLOADS_DIR; 
$subject_id = intval($_GET['subject_id'] ?? 0);
$teacher_id = $_SESSION['teacher_id'];

if ($subject_id <= 0) {
    die("Invalid subject ID.");
}

// Validate that the subject belongs to the teacher's course
$teacher_course = $_SESSION['teacher_course'] ?? '';
if (empty($teacher_course)) {
    die("Teacher course not set.");
}

$subject_query = mysqli_prepare($conn, "SELECT course_id FROM subjects WHERE id = ?");
mysqli_stmt_bind_param($subject_query, 'i', $subject_id);
mysqli_stmt_execute($subject_query);
$subject_result = mysqli_stmt_get_result($subject_query);
if (mysqli_num_rows($subject_result) == 0) {
    die("Subject not found.");
}
$subject_row = mysqli_fetch_assoc($subject_result);
$subject_course_id = $subject_row['course_id'];

// Get the course name
$course_query = mysqli_prepare($conn, "SELECT course_name FROM courses WHERE id = ?");
mysqli_stmt_bind_param($course_query, 'i', $subject_course_id);
mysqli_stmt_execute($course_query);
$course_result = mysqli_stmt_get_result($course_query);
if (mysqli_num_rows($course_result) == 0) {
    die("Course not found.");
}
$course_row = mysqli_fetch_assoc($course_result);
$subject_course_name = $course_row['course_name'];

if (strtoupper($subject_course_name) !== strtoupper($teacher_course)) {
    die("Access denied: Subject does not belong to your course.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] . ' 23:59:59' : null;
    $attachment = null;
    $original_filename = null;
    $error = null;

    // Create upload dir if not exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Handle optional file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'image/gif'
        ];
        if (in_array($_FILES['file']['type'], $allowed_types) && $_FILES['file']['size'] <= 10 * 1024 * 1024) {
            $file_ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $original_filename = $_FILES['file']['name'];
            $attachment = 'task_' . $teacher_id . '_' . time() . '.' . $file_ext;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $attachment)) {
                $error = 'File upload failed.';
            }
        } else {
            $error = 'Invalid file type or too large (10MB max).';
        }
    }

    if (empty($error) && !empty($title) && !empty($description)) {
        $query = "INSERT INTO tasks 
                    (task_type, subject_id, teacher_id, title, description, attachment, original_filename, due_date, created_at) 
                  VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'siisssss',
            $task_type,
            $subject_id,
            $teacher_id,
            $title,
            $description,
            $attachment,
            $original_filename,
            $due_date
        );
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Laboratory task created successfully!';
           header('Refresh: 2; url=' . BASE_URL . 'teachersportal/tasks.php');
        } else {
            $error = 'Database insert failed: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        if (empty($error)) $error = 'Please fill all required fields.';
    }
}

// Fetch subject name
$subject_name = 'Selected Subject';
if ($subject_id > 0) {
    $q = "SELECT subject_name FROM subjects WHERE id = ?";
    $s = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($s, 'i', $subject_id);
    mysqli_stmt_execute($s);
    $res = mysqli_stmt_get_result($s);
    if ($row = mysqli_fetch_assoc($res)) {
        $subject_name = $row['subject_name'];
    }
    mysqli_stmt_close($s);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $task_title ?> - <?= htmlspecialchars($subject_name) ?></title>
       <link rel="stylesheet" href="<?= asset('css/teachersaccess.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="left-panel">
           <a href="<?= BASE_URL ?>teachersportal/tasks.php" class="back-arrow">↩</a>

            <div class="icon"><i class="fas fa-flask"></i></div>
            <h2><?= $task_title ?></h2>
            <p>Create and submit laboratory tasks for <strong><?= htmlspecialchars($subject_name) ?></strong></p>

            <?php if (isset($success)): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="register-form">
                <fieldset>
                    <legend><i class="fas fa-flask"></i> Laboratory Details</legend>

                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>

                    <label for="description">Description</label>
                    <textarea id="description" name="description" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

                    <label for="file">Attachment File</label>
                    <input type="file" id="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                    <small style="color: var(--text-muted); font-size: 12px; display: block; margin-top: 4px;">Max 10MB. PDF, Word, Images. Optional.</small>
                </fieldset>

                <fieldset>
                    <legend><i class="fas fa-calendar-alt"></i> Schedule</legend>
                    <div class="form-row">
                        <div>
                            <label for="date_submitted">Date Submitted</label>
                            <input type="date" id="date_submitted" name="date_submitted" value="<?= $_POST['date_submitted'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div>
                            <label for="due_date">Due Date</label>
                            <input type="date" id="due_date" name="due_date" value="<?= $_POST['due_date'] ?? '' ?>" required>
                        </div>
                    </div>
                </fieldset>

                <button type="submit" class="btn register-btn">
                    <i class="fas fa-paper-plane"></i> Submit Laboratory
                </button>
            </form>
        </div>

        <div class="right-panel">
            <h1>
                Submit<br>
                Laboratory<br>
                for<br>
                <strong><?= htmlspecialchars($subject_name) ?></strong>
            </h1>
        </div>
    </div>
</body>
</html>