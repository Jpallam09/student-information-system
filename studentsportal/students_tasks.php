<?php

session_start();

require_once dirname(__DIR__) . '/config/paths.php';
require_once CONFIG_PATH . 'database.php';
require_once CONFIG_PATH . 'current_school_year.php';

/* -----------------------------
STUDENT AUTHENTICATION
------------------------------ */
if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

include PROJECT_ROOT . '/studentsportal/components/inactive_warning.php';

$student_id = $_SESSION['student_id'];

/* -----------------------------
GET STUDENT INFO
------------------------------ */
$stmt = $conn->prepare("
    SELECT course, year_level, section, school_year, semester
    FROM students
    WHERE id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Student record not found.");
}

$active_year = getActiveSchoolYear($conn);
$active_sem = getActiveSemester($conn);

$is_inactive = ($_SESSION['inactive_enrollment'] ?? false) ||
    ($student['school_year'] != $active_year || $student['semester'] != $active_sem);

$course_name = $student['course'];
$year = $student['year_level'];
$section = $student['section'];

/* -----------------------------
GET COURSE ID
------------------------------ */
$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$stmt->bind_param("s", $course_name);
$stmt->execute();
$result = $stmt->get_result();

$course_id = 0;
if ($result->num_rows > 0) {
    $course_row = $result->fetch_assoc();
    $course_id = $course_row['id'];
}

/* -----------------------------
FETCH SUBJECTS FOR STUDENT
------------------------------ */
$stmt = $conn->prepare("
    SELECT id, code, subject_name, description, instructor, year_level, section
    FROM subjects
    WHERE course_id = ?
    AND year_level = ?
    AND (section IS NULL OR section = '' OR section = ?)
    ORDER BY subject_name ASC
");
$stmt->bind_param("iss", $course_id, $year, $section);
$stmt->execute();
$result = $stmt->get_result();

$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

// Get subject IDs for querying tasks
$subject_ids = array_column($subjects, 'id');

/* -----------------------------
FETCH TASKS FOR STUDENT'S SUBJECTS
------------------------------ */
$tasks = [
    'activities' => [],
    'homework' => [],
    'laboratory' => []
];

// Get student's submitted task IDs
$submitted_task_ids = [];
$sub_stmt = $conn->prepare("SELECT DISTINCT task_id FROM task_submissions WHERE student_id = ?");
$sub_stmt->bind_param("i", $student_id);
$sub_stmt->execute();
$sub_result = $sub_stmt->get_result();
while ($sub_row = $sub_result->fetch_assoc()) {
    $submitted_task_ids[] = (int)$sub_row['task_id'];
}

// Fetch tasks by type
if (!empty($subject_ids)) {
    $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
    $task_types = ['activities', 'homework', 'laboratory'];

    foreach ($task_types as $type) {
        $types = str_repeat('i', count($subject_ids)) . 's';
        $stmt = $conn->prepare("
            SELECT t.*, s.subject_name, s.code
            FROM tasks t
            JOIN subjects s ON t.subject_id = s.id
            WHERE t.subject_id IN ($placeholders) AND t.task_type = ?
            ORDER BY t.created_at DESC
        ");
        $params = array_merge($subject_ids, [$type]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $row['is_submitted'] = in_array((int)$row['id'], $submitted_task_ids);
            $row['is_overdue'] = !empty($row['due_date']) && strtotime($row['due_date']) < time();
            $tasks[$type][] = $row;
        }
    }
}

// Calculate overall stats
$total_stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM tasks t
    JOIN subjects s ON t.subject_id = s.id
    WHERE s.course_id = ?
    AND s.year_level = ?
    AND (s.section = ? OR s.section = '')
");
$total_stmt->bind_param("iss", $course_id, $year, $section);
$total_stmt->execute();
$total_row = $total_stmt->get_result()->fetch_assoc();
$total_tasks = (int)$total_row['total'];

$submitted_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT ts.task_id) as submitted
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    JOIN subjects s ON t.subject_id = s.id
    WHERE ts.student_id = ?
    AND s.course_id = ?
    AND s.year_level = ?
    AND (s.section = ? OR s.section = '')
");
$submitted_stmt->bind_param("iiss", $student_id, $course_id, $year, $section);
$submitted_stmt->execute();
$submitted_row = $submitted_stmt->get_result()->fetch_assoc();
$total_submitted = (int)$submitted_row['submitted'];

// Subject stats
$subject_stats = [];
foreach ($subjects as $subject) {
    $subj_id = $subject['id'];

    $subj_total = $conn->prepare("SELECT COUNT(*) as total FROM tasks WHERE subject_id = ?");
    $subj_total->bind_param("i", $subj_id);
    $subj_total->execute();
    $total_row = $subj_total->get_result()->fetch_assoc();

    $subj_submitted = $conn->prepare("
        SELECT COUNT(DISTINCT ts.task_id) as submitted
        FROM task_submissions ts
        JOIN tasks t ON ts.task_id = t.id
        WHERE ts.student_id = ? AND t.subject_id = ?
    ");
    $subj_submitted->bind_param("ii", $student_id, $subj_id);
    $subj_submitted->execute();
    $sub_row = $subj_submitted->get_result()->fetch_assoc();

    $subject_stats[$subj_id] = [
        'total' => (int)$total_row['total'],
        'submitted' => (int)$sub_row['submitted'],
        'pending' => max(0, (int)$total_row['total'] - (int)$sub_row['submitted'])
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Tasks</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?= asset('css/studentportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <div style="position: fixed; top: 15px; right: 20px; z-index: 9999;">
        <img src="<?= asset('images/task.jpg.png') ?>" alt="Logo" style="width: 50px; border-radius: 5px;">
    </div>
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <h2 class="page-title"><i class="fas fa-tasks"></i> My Tasks</h2>
        <p class="page-subtitle">View and submit your assignments, activities, and laboratory tasks</p>
    </div>

    <!-- Student Info -->
    <div style="margin-bottom: 20px; padding: 15px; background: var(--slate-100); border-radius: 8px;">
        <p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary);">
            <i class="fas fa-info-circle"></i>
            <?php if ($is_inactive): ?>
                <strong style="color: #f59e0b;">📅 Past Term Records</strong> |
            <?php endif; ?>
            Active Term: <strong><?php echo htmlspecialchars($active_year . ' ' . $active_sem . ' Sem'); ?></strong> |
            Course: <strong><?php echo htmlspecialchars($course_name); ?></strong> -
            Year <strong><?php echo htmlspecialchars($year); ?></strong> - Section <strong><?php echo htmlspecialchars($section); ?></strong>
            <?php if ($is_inactive): ?> | <em style="color: #a16207;">Enrolled: <?php echo htmlspecialchars($student['school_year'] . ' ' . $student['semester'] . ' Sem'); ?></em><?php endif; ?>
        </p>
    </div>

    <!-- Overall Stats Grid -->
    <div class="cards-container" style="margin-bottom: 30px;">
        <div class="card" style="cursor: default;">
            <h3><i class="fas fa-clipboard-list"></i> Total Tasks</h3>
            <h1><?php echo $total_tasks; ?></h1>
            <p>Assigned to you</p>
        </div>

        <div class="card" style="cursor: default;">
            <h3><i class="fas fa-check-circle"></i> Submitted</h3>
            <h1 style="color: var(--accent-emerald);"><?php echo $total_submitted; ?></h1>
            <p>Completed</p>
        </div>

        <div class="card" style="cursor: default;">
            <h3><i class="fas fa-clock"></i> Pending</h3>
            <h1 style="color: var(--accent-amber);"><?php echo max(0, $total_tasks - $total_submitted); ?></h1>
            <p>Not yet submitted</p>
        </div>
    </div>

    <?php if (empty($subjects)): ?>
        <div class="no-tasks">
            <i class="fas fa-book-open"></i>
            <p>No subjects found for your course. Please contact your administrator.</p>
        </div>
    <?php elseif ($total_tasks == 0): ?>
        <div class="no-tasks">
            <i class="fas fa-clipboard"></i>
            <p>No tasks assigned yet. Check back later!</p>
        </div>
    <?php else: ?>

        <!-- Activities Section -->
        <?php if (count($tasks['activities']) > 0): ?>
            <div class="task-type-section" id="section-activities">
                <div class="task-type-header activities" onclick="toggleSection('activities')">
                    <h3><i class="fas fa-running"></i> Activities</h3>
                    <span class="task-count"><?php echo count($tasks['activities']); ?></span>
                    <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
                </div>
                <div class="task-type-content" id="section-activities-content" style="display: none;">
                    <?php foreach ($tasks['activities'] as $task): ?>
                        <div class="task-item" data-task-id="<?php echo $task['id']; ?>" data-is-overdue="<?php echo $task['is_overdue'] ? 'true' : 'false'; ?>" data-due-date="<?php echo htmlspecialchars($task['due_date'] ?? ''); ?>" data-subject-id="<?php echo $task['subject_id']; ?>">
                            <div class="task-item-header">
                                <div style="flex: 1;">
                                    <h4 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <span class="task-subject"><i class="fas fa-book"></i> <?php echo htmlspecialchars($task['subject_name']); ?></span>
                                </div>
                                <?php if ($task['is_submitted']): ?>
                                    <span class="badge badge-green teacher-read-status" data-task-id="<?php echo $task['id']; ?>" data-teacher-read="0">
                                        <i class="fas fa-clock"></i> Submitted
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-yellow"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </div>
                            <p class="task-description"><?php echo htmlspecialchars($task['description']); ?></p>
                            <div class="task-meta">
                                <span class="task-date"><i class="fas fa-calendar"></i> Posted: <?php echo date('M j, Y', strtotime($task['created_at'])); ?></span>
                                <div class="task-actions">
                                    <button class="btn-task btn-view" onclick="viewTask(<?php echo $task['id']; ?>)"><i class="fas fa-eye"></i> View Task from Teacher</button>
                                    <?php if ($task['is_submitted']): ?>
                                        <button class="btn-task btn-submitted" onclick="viewMySubmission(<?php echo $task['id']; ?>)" style="background: var(--accent-emerald); color: white;"><i class="fas fa-check"></i> View Submission</button>
                                    <?php else: ?>
                                        <button class="btn-task btn-submit" onclick="openSubmitModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title']); ?>', '<?php echo htmlspecialchars($task['subject_name']); ?>')"><i class="fas fa-upload"></i> Submit Task to Teacher</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Homework Section -->
        <?php if (count($tasks['homework']) > 0): ?>
            <div class="task-type-section" id="section-homework">
                <div class="task-type-header homework" onclick="toggleSection('homework')">
                    <h3><i class="fas fa-book"></i> Homework</h3>
                    <span class="task-count"><?php echo count($tasks['homework']); ?></span>
                    <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
                </div>
                <div class="task-type-content" id="section-homework-content" style="display: none;">
                    <?php foreach ($tasks['homework'] as $task): ?>
                        <div class="task-item" data-task-id="<?php echo $task['id']; ?>" data-is-overdue="<?php echo $task['is_overdue'] ? 'true' : 'false'; ?>" data-due-date="<?php echo htmlspecialchars($task['due_date'] ?? ''); ?>" data-subject-id="<?php echo $task['subject_id']; ?>">
                            <div class="task-item-header">
                                <div style="flex: 1;">
                                    <h4 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <span class="task-subject"><i class="fas fa-book"></i> <?php echo htmlspecialchars($task['subject_name']); ?></span>
                                </div>
                                <?php if ($task['is_submitted']): ?>
                                    <span class="badge badge-green teacher-read-status" data-task-id="<?php echo $task['id']; ?>" data-teacher-read="0">
                                        <i class="fas fa-clock"></i> Submitted
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-yellow"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </div>
                            <p class="task-description"><?php echo htmlspecialchars($task['description']); ?></p>
                            <div class="task-meta">
                                <span class="task-date"><i class="fas fa-calendar"></i> Posted: <?php echo date('M j, Y', strtotime($task['created_at'])); ?></span>
                                <div class="task-actions">
                                    <button class="btn-task btn-view" onclick="viewTask(<?php echo $task['id']; ?>)"><i class="fas fa-eye"></i> View Task from Teacher</button>
                                    <?php if ($task['is_submitted']): ?>
                                        <button class="btn-task btn-submitted" onclick="viewMySubmission(<?php echo $task['id']; ?>)" style="background: var(--accent-emerald); color: white;"><i class="fas fa-check"></i> View Submission</button>
                                    <?php else: ?>
                                        <button class="btn-task btn-submit" onclick="openSubmitModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title']); ?>', '<?php echo htmlspecialchars($task['subject_name']); ?>')"><i class="fas fa-upload"></i> Submit</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Laboratory Section -->
        <?php if (count($tasks['laboratory']) > 0): ?>
            <div class="task-type-section" id="section-laboratory">
                <div class="task-type-header laboratory" onclick="toggleSection('laboratory')">
                    <h3><i class="fas fa-flask"></i> Laboratory</h3>
                    <span class="task-count"><?php echo count($tasks['laboratory']); ?></span>
                    <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
                </div>
                <div class="task-type-content" id="section-laboratory-content" style="display: none;">
                    <?php foreach ($tasks['laboratory'] as $task): ?>
                        <div class="task-item" data-task-id="<?php echo $task['id']; ?>" data-is-overdue="<?php echo $task['is_overdue'] ? 'true' : 'false'; ?>" data-due-date="<?php echo htmlspecialchars($task['due_date'] ?? ''); ?>" data-subject-id="<?php echo $task['subject_id']; ?>">
                            <div class="task-item-header">
                                <div style="flex: 1;">
                                    <h4 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <span class="task-subject"><i class="fas fa-book"></i> <?php echo htmlspecialchars($task['subject_name']); ?></span>
                                </div>
                                <?php if ($task['is_submitted']): ?>
                                    <span class="badge badge-green teacher-read-status" data-task-id="<?php echo $task['id']; ?>" data-teacher-read="0">
                                        <i class="fas fa-clock"></i> Submitted
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-yellow"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </div>
                            <p class="task-description"><?php echo htmlspecialchars($task['description']); ?></p>
                            <div class="task-meta">
                                <span class="task-date"><i class="fas fa-calendar"></i> Posted: <?php echo date('M j, Y', strtotime($task['created_at'])); ?></span>
                                <div class="task-actions">
                                    <button class="btn-task btn-view" onclick="viewTask(<?php echo $task['id']; ?>)"><i class="fas fa-eye"></i> View Task from Teacher</button>
                                    <?php if ($task['is_submitted']): ?>
                                        <button class="btn-task btn-submitted" onclick="viewMySubmission(<?php echo $task['id']; ?>)" style="background: var(--accent-emerald); color: white;"><i class="fas fa-check"></i> View Submission</button>
                                    <?php else: ?>
                                        <button class="btn-task btn-submit" onclick="openSubmitModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title']); ?>', '<?php echo htmlspecialchars($task['subject_name']); ?>')"><i class="fas fa-upload"></i> Submit</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<!-- View Task Modal -->
<div id="viewTaskModal" class="task-modal">
    <div class="task-modal-content">
        <div class="task-modal-header">
            <h2 id="viewTaskTitle"><i class="fas fa-eye"></i> Task Details</h2>
            <button type="button" class="task-modal-close" onclick="closeViewModal()">×</button>
        </div>
        <div class="task-modal-body">
            <div class="task-detail-row">
                <span class="task-detail-label">Subject</span>
                <span class="task-detail-value" id="viewTaskSubject"></span>
            </div>
            <div class="task-detail-row">
                <span class="task-detail-label">Type</span>
                <span class="task-detail-value" id="viewTaskType"></span>
            </div>
            <div class="task-detail-row">
                <span class="task-detail-label">Description</span>
                <p class="task-detail-value" id="viewTaskDescription" style="margin-top: 5px; line-height: 1.6;"></p>
            </div>
            <div class="task-detail-row" id="viewAttachmentRow" style="display: none;">
                <span class="task-detail-label">Attachment</span>
                <a href="#" id="viewAttachmentLink" class="task-attachment-link" target="_blank">
                    <i class="fas fa-paperclip"></i> <span id="viewAttachmentName"></span>
                </a>
            </div>
            <div class="task-detail-row">
                <span class="task-detail-label">Date Posted</span>
                <span class="task-detail-value" id="viewTaskDate"></span>
            </div>
            <div class="task-detail-row" id="viewDueDateRow" style="display: none;">
                <span class="task-detail-label">Due Date</span>
                <span class="task-detail-value" id="viewTaskDueDate" style="font-weight: 600;"></span>
            </div>
        </div>
    </div>
</div>

<!-- View My Submission Modal -->
<div id="viewSubmissionModal" class="task-modal">
    <div class="task-modal-content">
        <div class="task-modal-header" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
            <h2><i class="fas fa-check-circle"></i> My Submission</h2>
            <button type="button" class="task-modal-close" onclick="closeViewSubmissionModal()">×</button>
        </div>
        <div class="task-modal-body">
            <input type="hidden" id="viewSubmissionTaskId">
            <div class="task-detail-row">
                <span class="task-detail-label">Task</span>
                <span class="task-detail-value" id="submissionTaskTitle" style="font-weight: 600;"></span>
            </div>
            <div class="task-detail-row">
                <span class="task-detail-label">Submitted File</span>
                <a href="#" id="submissionFileLink" class="task-attachment-link" target="_blank">
                    <i class="fas fa-file-download"></i> <span id="submissionFileName"></span>
                </a>
            </div>
            <div class="task-detail-row">
                <span class="task-detail-label">Notes</span>
                <p class="task-detail-value" id="submissionNotes" style="margin-top: 5px; line-height: 1.6;"></p>
            </div>
            <div class="task-detail-row">
                <span class="task-detail-label">Submitted On</span>
                <span class="task-detail-value" id="submissionDate"></span>
            </div>
            <div id="teacherReadStatus" style="margin-top: 15px; padding: 10px; border-radius: 6px; background: var(--slate-50); font-weight: 500; display: flex; align-items: center; gap: 8px;"></div>
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-task" onclick="openEditSubmissionModal()" style="background: #f59e0b; color: white;">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button type="button" class="btn-task" onclick="openDeleteSubmissionModal()" style="background: #ef4444; color: white;">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Submission Modal -->
<div id="editSubmissionModal" class="task-modal">
    <div class="task-modal-content">
        <div class="task-modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <h2><i class="fas fa-edit"></i> Edit Submission</h2>
            <button type="button" class="task-modal-close" onclick="closeEditSubmissionModal()">×</button>
        </div>
        <div class="task-modal-body">
            <form id="editSubmissionForm">
                <input type="hidden" id="editSubmissionTaskId" name="task_id">
                <div class="task-detail-row">
                    <span class="task-detail-label">Task</span>
                    <span class="task-detail-value" id="editSubmissionTaskTitle" style="font-weight: 600;"></span>
                </div>
                <div class="task-detail-row">
                    <span class="task-detail-label">Current File</span>
                    <a href="#" id="editCurrentFileLink" class="task-attachment-link" target="_blank">
                        <i class="fas fa-file-download"></i> <span id="editCurrentFileName"></span>
                    </a>
                </div>
                <div class="form-group">
                    <label for="editSubmissionFile">Replace File (Optional)</label>
                    <input type="file" id="editSubmissionFile" name="submission_file" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar">
                    <small style="color: var(--text-muted);">Leave empty to keep current file</small>
                </div>
                <div class="form-group">
                    <label for="editSubmissionNotes">Notes</label>
                    <textarea id="editSubmissionNotes" name="submission_notes" rows="3" placeholder="Update your notes..."></textarea>
                </div>
                <button type="submit" class="btn-submit-task" style="background: #f59e0b;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Delete Submission Confirmation Modal -->
<div id="deleteSubmissionModal" class="task-modal">
    <div class="task-modal-content" style="max-width: 400px;">
        <div class="task-modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h2>
            <button type="button" class="task-modal-close" onclick="closeDeleteSubmissionModal()">×</button>
        </div>
        <div class="task-modal-body" style="text-align: center;">
            <p style="font-size: 1.1rem; margin-bottom: 20px;">Are you sure you want to delete your submission for:</p>
            <p id="deleteSubmissionTaskTitle" style="font-weight: 600; font-size: 1.2rem; color: var(--slate-700);"></p>
            <p style="color: #ef4444; margin-bottom: 20px;"><i class="fas fa-warning"></i> This action cannot be undone!</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" onclick="closeDeleteSubmissionModal()" class="btn-task" style="background: var(--slate-500); color: white;">
                    Cancel
                </button>
                <button type="button" onclick="confirmDeleteSubmission()" class="btn-task" style="background: #ef4444; color: white;">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Submit Task Modal -->
<div id="submitTaskModal" class="task-modal">
    <div class="task-modal-content">
        <div class="task-modal-header" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
            <h2><i class="fas fa-upload"></i> Submit Task</h2>
            <button type="button" class="task-modal-close" onclick="closeSubmitModal()">×</button>
        </div>
        <div class="task-modal-body">
            <div class="task-detail-row">
                <span class="task-detail-label">Task</span>
                <span class="task-detail-value" id="submitTaskTitle" style="font-weight: 600; font-size: 1rem;"></span>
            </div>
            <div class="task-detail-row">
                <span class="task-detail-label">Subject</span>
                <span class="task-detail-value" id="submitTaskSubject"></span>
            </div>

            <form id="submitTaskForm" class="submit-form">
                <input type="hidden" id="submitTaskId" name="task_id">
                <div class="form-group">
                    <label for="submissionFile">Your Submission (File)</label>
                    <input type="file" id="submissionFile" name="submission_file" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar" required>
                </div>
                <div class="form-group">
                    <label for="submissionNotes">Notes (Optional)</label>
                    <textarea id="submissionNotes" name="submission_notes" rows="3" placeholder="Add any notes for your submission..."></textarea>
                </div>
                <button type="submit" class="btn-submit-task">
                    <i class="fas fa-upload"></i> Submit Task
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Real-time teacher read status
let studentEventSource = null;
let studentPollInterval = null;
let currentTaskId = null;
let currentSubmissionData = null;

const sseStudentUrl = '<?= BASE_URL ?>tasks/sse_student.php';

// Function to update teacher read badge
function updateTeacherReadStatus(taskId, teacherRead) {
    const statusEl = document.querySelector(`.teacher-read-status[data-task-id="${taskId}"]`);
    if (statusEl) {
        statusEl.dataset.teacherRead = teacherRead ? '1' : '0';
        if (teacherRead == 1) {
            statusEl.innerHTML = '<i class="fas fa-eye"></i> Teacher Viewed';
            statusEl.style.background = '#10b981';
            statusEl.style.color = 'white';
        } else {
            statusEl.innerHTML = '<i class="fas fa-clock"></i> Submitted';
            statusEl.style.background = '';
            statusEl.style.color = '';
        }
    }
}

// Load initial teacher read status for submitted tasks
function loadInitialTeacherReadStatus() {
    const submittedBadges = document.querySelectorAll('.teacher-read-status');
    submittedBadges.forEach(badge => {
        const taskId = badge.dataset.taskId;
        fetch(`<?= BASE_URL ?>tasks/get_submissions.php?task_id=${taskId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.submissions) {
                    const studentId = <?php echo $student_id; ?>;
                    const submission = data.submissions.find(s => parseInt(s.student_id) === studentId);
                    if (submission && submission.teacher_read == 1) {
                        updateTeacherReadStatus(taskId, 1);
                    }
                }
            })
            .catch(err => console.log('Load status error:', err));
    });
}

function connectStudentRealtime() {
    if (studentEventSource) studentEventSource.close();

    try {
        studentEventSource = new EventSource(sseStudentUrl);

        studentEventSource.onopen = function() {
            console.log('SSE connection established');
        };

        studentEventSource.onmessage = function(event) {
            try {
                const updates = JSON.parse(event.data);
                updates.forEach(update => {
                    updateTeacherReadStatus(update.task_id, 1);
                    showStudentNotification(`"${update.task_title}" viewed by teacher!`);
                });
            } catch (e) {
                console.log('Error parsing SSE message:', e);
            }
        };

        studentEventSource.onerror = function(event) {
            console.log('Student SSE connection failed, using polling fallback...');
            if (studentEventSource) {
                studentEventSource.close();
                studentEventSource = null;
            }
            startStudentPolling();
        };
    } catch (error) {
        console.log('SSE not supported, using polling fallback');
        startStudentPolling();
    }
}

function startStudentPolling() {
    if (studentPollInterval) clearInterval(studentPollInterval);

    studentPollInterval = setInterval(() => {
        const badges = document.querySelectorAll('.teacher-read-status[data-teacher-read="0"]');
        badges.forEach(badge => {
            const taskId = badge.dataset.taskId;
            fetch(`<?= BASE_URL ?>tasks/get_submissions.php?task_id=${taskId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.submissions) {
                        const studentId = <?php echo $student_id; ?>;
                        const submission = data.submissions.find(s => parseInt(s.student_id) === studentId);
                        if (submission && submission.teacher_read == 1) {
                            updateTeacherReadStatus(taskId, 1);
                            showStudentNotification(`Task viewed by teacher!`);
                        }
                    }
                })
                .catch(err => console.log('Polling error:', err));
        });
    }, 30000);
}

function showStudentNotification(message, type = 'success') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed; top: 20px; right: 20px; z-index: 10000;
        background: linear-gradient(135deg, ${type === 'error' ? '#ef4444, #dc2626' : '#10b981, #059669'} 100%); color: white;
        padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        font-weight: 500; max-width: 350px; transform: translateX(400px); transition: transform 0.3s;
    `;
    toast.innerHTML = type === 'error' ? `<i class="fas fa-trash"></i> ${message}` : `<i class="fas fa-eye"></i> ${message}`;
    document.body.appendChild(toast);

    setTimeout(() => toast.style.transform = 'translateX(0)', 100);
    setTimeout(() => {
        toast.style.transform = 'translateX(400px)';
        setTimeout(() => toast.remove(), 400);
    }, 6000);
}

function toggleSection(type) {
    const content = document.getElementById('section-' + type + '-content');
    const header = document.querySelector('#section-' + type + ' .task-type-header');
    const icon = header.querySelector('.fas.fa-chevron-down');

    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        content.style.maxHeight = '0';
        content.style.overflow = 'hidden';
        content.style.transition = 'max-height 0.4s ease, opacity 0.4s ease';
        content.style.opacity = '0';

        void content.offsetWidth;

        content.style.maxHeight = content.scrollHeight + 100 + 'px';
        content.style.opacity = '1';
        content.style.overflow = 'visible';

        if (icon) {
            icon.style.transform = 'rotate(180deg)';
        }
    } else {
        content.style.maxHeight = content.scrollHeight + 'px';
        content.style.transition = 'max-height 0.3s ease, opacity 0.3s ease';
        content.style.opacity = '0';

        setTimeout(() => {
            content.style.display = 'none';
            content.style.overflow = 'hidden';
        }, 300);

        if (icon) {
            icon.style.transform = 'rotate(0deg)';
        }
    }
}

function viewTask(taskId) {
    fetch('<?= BASE_URL ?>tasks/get_task.php?id=' + taskId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.task) {
                const task = data.task;
                document.getElementById('viewTaskTitle').innerHTML = '<i class="fas fa-eye"></i> ' + task.title;
                document.getElementById('viewTaskSubject').textContent = task.subject_name || 'N/A';
                document.getElementById('viewTaskType').textContent = task.task_type ? task.task_type.charAt(0).toUpperCase() + task.task_type.slice(1) : 'N/A';
                document.getElementById('viewTaskDescription').textContent = task.description || 'No description';
                document.getElementById('viewTaskDate').textContent = task.created_at_formatted || (task.created_at ? new Date(task.created_at).toLocaleDateString() : 'N/A');

                if (task.due_date) {
                    const dueDateRow = document.getElementById('viewDueDateRow');
                    const dueDateEl = document.getElementById('viewTaskDueDate');
                    dueDateRow.style.display = 'flex';

                    if (task.is_overdue) {
                        dueDateEl.innerHTML = '<span style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> ' + task.due_date_formatted + ' (Overdue)</span>';
                    } else {
                        dueDateEl.innerHTML = '<span style="color: #27ae60;"><i class="fas fa-clock"></i> ' + task.due_date_formatted + '</span>';
                    }
                } else {
                    document.getElementById('viewDueDateRow').style.display = 'none';
                }

                if (task.attachment) {
                    document.getElementById('viewAttachmentRow').style.display = 'block';
                    document.getElementById('viewAttachmentLink').href = '<?= BASE_URL ?>tasks/uploads/' + task.attachment;
                    document.getElementById('viewAttachmentName').textContent = task.original_filename || task.attachment;
                } else {
                    document.getElementById('viewAttachmentRow').style.display = 'none';
                }

                document.getElementById('viewTaskModal').classList.add('show');
            } else {
                console.error('Task detail error response:', data);
                alert('Error loading task details: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading task details');
        });
}

function closeViewModal() {
    document.getElementById('viewTaskModal').classList.remove('show');
}

function viewMySubmission(taskId) {
    console.log('Viewing submission for task ID:', taskId);
    console.log('Student ID:', <?php echo $student_id; ?>);

    // Show loading state
    const modal = document.getElementById('viewSubmissionModal');
    const modalBody = modal.querySelector('.task-modal-body');
    const originalContent = modalBody.innerHTML;
    modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading submission...</div>';
    modal.classList.add('show');

    fetch('<?= BASE_URL ?>tasks/get_submissions.php?task_id=' + taskId)
        .then(response => response.json())
        .then(data => {
            console.log('API Response:', data);

            if (data.success && data.submissions && data.submissions.length > 0) {
                const studentId = <?php echo $student_id; ?>;
                const submission = data.submissions.find(s => parseInt(s.student_id) === studentId);

                if (submission) {
                    console.log('Found submission:', submission);

                    // Store submission data for edit/delete
                    currentSubmissionData = {
                        taskId: taskId,
                        taskTitle: data.task.title,
                        filePath: submission.file_path,
                        originalFilename: submission.original_filename,
                        notes: submission.notes,
                        teacherRead: submission.teacher_read == 1
                    };

                    // Restore original modal content
                    modalBody.innerHTML = originalContent;

                    // Populate the modal with data
                    document.getElementById('viewSubmissionTaskId').value = taskId;
                    document.getElementById('submissionTaskTitle').textContent = data.task.title;
                    document.getElementById('submissionDate').textContent = submission.submitted_at_formatted || new Date(submission.submitted_at).toLocaleString();

                    // Handle notes (fix for "undefined" string)
                    let notesText = submission.notes;
                    if (!notesText || notesText === 'undefined' || notesText === 'null') {
                        notesText = 'No notes provided';
                    }
                    document.getElementById('submissionNotes').textContent = notesText;

                    // Handle file link
                    const fileLink = document.getElementById('submissionFileLink');
                    const fileNameSpan = document.getElementById('submissionFileName');
                    if (submission.file_path && submission.file_path !== 'undefined') {
                        fileLink.style.display = 'inline-flex';
                        fileLink.href = '<?= BASE_URL ?>tasks/student_uploads/' + submission.file_path;
                        const fileName = submission.original_filename || submission.file_path;
                        fileNameSpan.textContent = fileName;
                    } else {
                        fileLink.style.display = 'none';
                        fileNameSpan.textContent = 'No file uploaded';
                    }

                    // Show teacher read status
                    const teacherStatus = document.getElementById('teacherReadStatus');
                    if (submission.teacher_read == 1) {
                        teacherStatus.innerHTML = '<i class="fas fa-eye" style="color: #10b981;"></i> Teacher has viewed your submission';
                        teacherStatus.style.color = '#10b981';
                        teacherStatus.style.backgroundColor = '#d1fae5';
                    } else {
                        teacherStatus.innerHTML = '<i class="fas fa-clock" style="color: #f59e0b;"></i> Awaiting teacher review';
                        teacherStatus.style.color = '#f59e0b';
                        teacherStatus.style.backgroundColor = '#fef3c7';
                    }

                    modal.classList.add('show');
                } else {
                    modalBody.innerHTML = `<div style="text-align: center; padding: 40px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f59e0b;"></i>
                        <p style="margin-top: 20px;">No submission found for you (Student ID: ${studentId})</p>
                        <button onclick="location.reload()" class="btn-task" style="margin-top: 20px;">Refresh</button>
                    </div>`;
                }
            } else {
                modalBody.innerHTML = `<div style="text-align: center; padding: 40px;">
                    <i class="fas fa-file-alt" style="font-size: 48px; color: #9ca3af;"></i>
                    <p style="margin-top: 20px;">No submission found for this task.</p>
                    <button onclick="closeViewSubmissionModal()" class="btn-task" style="margin-top: 20px;">Close</button>
                </div>`;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            modalBody.innerHTML = `<div style="text-align: center; padding: 40px;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444;"></i>
                <p style="margin-top: 20px;">Error loading submission: ${error.message}</p>
                <button onclick="closeViewSubmissionModal()" class="btn-task" style="margin-top: 20px;">Close</button>
            </div>`;
        });
}

function closeViewSubmissionModal() {
    document.getElementById('viewSubmissionModal').classList.remove('show');
}

function openSubmitModal(taskId, taskTitle, subjectName) {
    const taskItem = document.querySelector(`.task-item[data-task-id="${taskId}"]`);
    if (taskItem && taskItem.dataset.isOverdue === 'true') {
        alert('This task is overdue. You cannot submit after the due date.');
        return;
    }
    currentTaskId = taskId;
    document.getElementById('submitTaskId').value = taskId;
    document.getElementById('submitTaskTitle').textContent = taskTitle;
    document.getElementById('submitTaskSubject').textContent = subjectName;
    document.getElementById('submitTaskForm').reset();
    document.getElementById('submitTaskModal').classList.add('show');
}

function closeSubmitModal() {
    document.getElementById('submitTaskModal').classList.remove('show');
    currentTaskId = null;
}

document.getElementById('submitTaskForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const fileInput = document.getElementById('submissionFile');

    if (!fileInput.files.length) {
        showStudentNotification('Please select a file to submit ❌', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('task_id', document.getElementById('submitTaskId').value);
    formData.append('submission_file', fileInput.files[0]);
    formData.append('submission_notes', document.getElementById('submissionNotes').value);

    const submitBtn = e.target.querySelector('.btn-submit-task');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    submitBtn.disabled = true;

    fetch('<?= BASE_URL ?>tasks/student_submit_task', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStudentNotification('Your task has been submitted successfully! ✅');
                closeSubmitModal();
                setTimeout(() => location.reload(), 2000);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting. Please try again.');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
});

function openEditSubmissionModal() {
    if (!currentSubmissionData) {
        alert('No submission data available');
        return;
    }

    closeViewSubmissionModal();

    document.getElementById('editSubmissionTaskId').value = currentSubmissionData.taskId;
    document.getElementById('editSubmissionTaskTitle').textContent = currentSubmissionData.taskTitle;
    document.getElementById('editSubmissionNotes').value = currentSubmissionData.notes || '';

    if (currentSubmissionData.filePath && currentSubmissionData.filePath !== 'undefined') {
        document.getElementById('editCurrentFileLink').style.display = 'inline-flex';
        document.getElementById('editCurrentFileLink').href = '<?= BASE_URL ?>tasks/student_uploads/' + currentSubmissionData.filePath;
        document.getElementById('editCurrentFileName').textContent = currentSubmissionData.originalFilename || currentSubmissionData.filePath;
    } else {
        document.getElementById('editCurrentFileLink').style.display = 'none';
    }

    document.getElementById('editSubmissionFile').value = '';
    document.getElementById('editSubmissionModal').classList.add('show');
}

function closeEditSubmissionModal() {
    document.getElementById('editSubmissionModal').classList.remove('show');
}

document.getElementById('editSubmissionForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('task_id', document.getElementById('editSubmissionTaskId').value);
    formData.append('submission_notes', document.getElementById('editSubmissionNotes').value);

    const fileInput = document.getElementById('editSubmissionFile');
    if (fileInput.files.length > 0) {
        formData.append('submission_file', fileInput.files[0]);
    }

    const submitBtn = e.target.querySelector('.btn-submit-task');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;

    fetch('<?= BASE_URL ?>tasks/update_submission', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStudentNotification(data.message + ' ✅');
                setTimeout(() => location.reload(), 1500);
            } else {
                showStudentNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating. Please try again.');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
});

function openDeleteSubmissionModal() {
    if (!currentSubmissionData) {
        alert('No submission data available');
        return;
    }

    document.getElementById('deleteSubmissionTaskTitle').textContent = currentSubmissionData.taskTitle;
    document.getElementById('deleteSubmissionModal').classList.add('show');
}

function closeDeleteSubmissionModal() {
    document.getElementById('deleteSubmissionModal').classList.remove('show');
}

function confirmDeleteSubmission() {
    if (!currentSubmissionData) {
        alert('No submission data available');
        return;
    }

    fetch('<?= BASE_URL ?>tasks/delete_submission', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ task_id: currentSubmissionData.taskId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStudentNotification('Submission deleted successfully!', 'error');
                closeDeleteSubmissionModal();
                setTimeout(() => location.reload(), 2000);
            } else {
                showStudentNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting. Please try again.');
        });
}

document.addEventListener('click', function(event) {
    if (event.target.classList.contains('task-modal')) {
        event.target.classList.remove('show');
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeViewModal();
        closeViewSubmissionModal();
        closeSubmitModal();
        closeEditSubmissionModal();
        closeDeleteSubmissionModal();
    }
});

window.addEventListener('load', () => {
    document.querySelectorAll('.task-item').forEach(taskItem => {
        const isOverdue = taskItem.dataset.isOverdue === 'true';
        if (!isOverdue) return;

        // Block submit button
        const submitBtn = taskItem.querySelector('.btn-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-ban"></i> Overdue – Cannot Submit';
            submitBtn.style.backgroundColor = '#ef4444';
            submitBtn.style.opacity = '0.7';
            submitBtn.style.cursor = 'not-allowed';
            submitBtn.title = 'Task due date has passed. Cannot submit.';
        }

        // Block edit & delete on submitted tasks (the "View Submission" button stays,
        // but we override the modal buttons after open via a flag)
        taskItem.dataset.overdueLocked = 'true';
    });

    // Intercept openEditSubmissionModal / openDeleteSubmissionModal for overdue tasks
    const _origOpenEdit = window.openEditSubmissionModal;
    window.openEditSubmissionModal = function() {
        if (currentSubmissionData) {
            const taskItem = document.querySelector(`.task-item[data-task-id="${currentSubmissionData.taskId}"]`);
            if (taskItem && taskItem.dataset.overdueLocked === 'true') {
                showStudentNotification('This task is overdue. You cannot edit your submission.', 'error');
                return;
            }
        }
        if (_origOpenEdit) _origOpenEdit();
    };

    const _origOpenDelete = window.openDeleteSubmissionModal;
    window.openDeleteSubmissionModal = function() {
        if (currentSubmissionData) {
            const taskItem = document.querySelector(`.task-item[data-task-id="${currentSubmissionData.taskId}"]`);
            if (taskItem && taskItem.dataset.overdueLocked === 'true') {
                showStudentNotification('This task is overdue. You cannot delete your submission.', 'error');
                return;
            }
        }
        if (_origOpenDelete) _origOpenDelete();
    };

    connectStudentRealtime();
    loadInitialTeacherReadStatus();
});

window.addEventListener('beforeunload', () => {
    if (studentEventSource) studentEventSource.close();
    if (studentPollInterval) clearInterval(studentPollInterval);
});
</script>

</body>
</html>