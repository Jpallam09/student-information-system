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

// Fetch existing task (must belong to this teacher)
$fetch = $conn->prepare("
    SELECT t.*, s.subject_name 
    FROM tasks t 
    JOIN subjects s ON t.subject_id = s.id 
    WHERE t.id = ? AND t.teacher_id = ?
");
$fetch->bind_param('ii', $task_id, $teacher_id);
$fetch->execute();
$task = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$task) {
    header("Location: " . BASE_URL . "teachersportal/tasks.php");
    exit();
}

$error   = null;
$success = null;
$upload_dir = PROJECT_ROOT . '/tasks/uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] . ' 23:59:59' : null;

    if (empty($title) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } else {
        $new_attachment      = $task['attachment'];
        $new_original_fname  = $task['original_filename'];

        // Handle optional new file upload
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
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_ext           = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                $new_original_fname = $_FILES['file']['name'];
                $new_attachment     = 'task_' . $teacher_id . '_' . time() . '.' . $file_ext;
                $target_path        = $upload_dir . $new_attachment;

                if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
                    // Delete old file if there was one
                    if ($task['attachment'] && file_exists($upload_dir . $task['attachment'])) {
                        unlink($upload_dir . $task['attachment']);
                    }
                } else {
                    $error = 'File upload failed.';
                }
            } else {
                $error = 'Invalid file type or too large (10MB max, PDF/Word/Images).';
            }
        }

        // Handle "remove attachment" checkbox
        if (!empty($_POST['remove_attachment']) && $task['attachment']) {
            if (file_exists($upload_dir . $task['attachment'])) {
                unlink($upload_dir . $task['attachment']);
            }
            $new_attachment     = null;
            $new_original_fname = null;
        }

        if (empty($error)) {
            $update = $conn->prepare("
                UPDATE tasks 
                SET title = ?, description = ?, due_date = ?, attachment = ?, original_filename = ?
                WHERE id = ? AND teacher_id = ?
            ");
            $update->bind_param('sssssii',
                $title,
                $description,
                $due_date,
                $new_attachment,
                $new_original_fname,
                $task_id,
                $teacher_id
            );

            if ($update->execute()) {
                $success = 'Task updated successfully!';
                // Refresh task data
                $task['title']             = $title;
                $task['description']       = $description;
                $task['due_date']          = $due_date;
                $task['attachment']        = $new_attachment;
                $task['original_filename'] = $new_original_fname;
            } else {
                $error = 'Database update failed.';
            }
            $update->close();
        }
    }
}

// Icon map
$type_icons = [
    'activities' => 'fa-file-alt',
    'homework'   => 'fa-book',
    'laboratory' => 'fa-flask',
];
$icon = $type_icons[$task['task_type']] ?? 'fa-tasks';
$due_val = $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task – <?= htmlspecialchars($task['title']) ?></title>
    <link rel="stylesheet" href="<?= asset('css/teachersaccess.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
    <div class="left-panel">
        <a href="<?= BASE_URL ?>teachersportal/tasks.php" class="back-arrow">↩</a>

        <div class="icon"><i class="fas <?= $icon ?>"></i></div>
        <h2>Edit Task</h2>
        <p>Editing <strong><?= ucfirst(htmlspecialchars($task['task_type'])) ?></strong> for <strong><?= htmlspecialchars($task['subject_name']) ?></strong></p>

        <?php if ($success): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="register-form">
            <fieldset>
                <legend><i class="fas <?= $icon ?>"></i> Task Details</legend>

                <label for="title">Title <span style="color:red">*</span></label>
                <input type="text" id="title" name="title"
                       value="<?= htmlspecialchars($_POST['title'] ?? $task['title']) ?>" required>

                <label for="description">Description <span style="color:red">*</span></label>
                <textarea id="description" name="description" required><?= htmlspecialchars($_POST['description'] ?? $task['description']) ?></textarea>

                <label>Current Attachment</label>
                <?php if ($task['attachment']): ?>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px; padding:8px 12px; background:#f0f4ff; border-radius:6px;">
                        <i class="fas fa-file" style="color:#3b82f6"></i>
                        <a href="<?= BASE_URL ?>tasks/uploads/<?= htmlspecialchars($task['attachment']) ?>"
                           target="_blank" style="flex:1; color:#3b82f6; font-size:0.9em; word-break:break-all;">
                            <?= htmlspecialchars($task['original_filename'] ?? $task['attachment']) ?>
                        </a>
                        <label style="font-size:0.85em; color:#ef4444; cursor:pointer; white-space:nowrap;">
                            <input type="checkbox" name="remove_attachment" value="1"
                                   style="margin-right:4px; accent-color:#ef4444;">
                        </label>
                    </div>
                <?php else: ?>
                    <p style="color:#9ca3af; font-size:0.9em; margin-bottom:8px;">No attachment</p>
                <?php endif; ?>

                <label for="file">Replace / Add Attachment</label>
                <input type="file" id="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                <small style="color:var(--text-muted); font-size:12px; display:block; margin-top:4px;">
                    Max 10MB. PDF, Word, Images. Optional.
                </small>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-calendar-alt"></i> Schedule</legend>
                <label for="due_date">Due Date</label>
                <input type="date" id="due_date" name="due_date"
                       value="<?= htmlspecialchars($_POST['due_date'] ?? $due_val) ?>">
                <small style="color:var(--text-muted); font-size:12px; display:block; margin-top:4px;">
                    Leave blank for no deadline.
                </small>
            </fieldset>

            <button type="submit" class="btn register-btn">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
    </div>

    <div class="right-panel">
        <h1>
            Edit<br>
            <?= ucfirst(htmlspecialchars($task['task_type'])) ?><br>
            Task<br>
            for<br>
            <strong><?= htmlspecialchars($task['subject_name']) ?></strong>
        </h1>
    </div>
</div>
</body>
</html>