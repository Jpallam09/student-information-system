<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once CONFIG_PATH . 'database.php';
require_once CONFIG_PATH . 'current_school_year.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

include PROJECT_ROOT . '/studentsportal/components/inactive_warning.php';
$student_id = $_SESSION['student_id'];

/* ── Student info ── */
$stmt = $conn->prepare("SELECT course, year_level, section, school_year, semester FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) die("Student record not found.");

$active_year = getActiveSchoolYear($conn);
$active_sem  = getActiveSemester($conn);
$is_inactive = ($_SESSION['inactive_enrollment'] ?? false) ||
    ($student['school_year'] != $active_year || $student['semester'] != $active_sem);

$course_name = $student['course'];
$year        = $student['year_level'];
$section     = $student['section'];

/* ── Course ID ── */
$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$stmt->bind_param("s", $course_name);
$stmt->execute();
$result   = $stmt->get_result();
$course_id = 0;
if ($result->num_rows > 0) $course_id = $result->fetch_assoc()['id'];

/* ── Subjects ── */
$stmt = $conn->prepare("
    SELECT id, code, subject_name, description, instructor, year_level, section
    FROM subjects
    WHERE course_id = ? AND year_level = ?
    AND (section IS NULL OR section = '' OR section = ?)
    ORDER BY subject_name ASC
");
$stmt->bind_param("iss", $course_id, $year, $section);
$stmt->execute();
$result   = $stmt->get_result();
$subjects = [];
while ($row = $result->fetch_assoc()) $subjects[] = $row;
$subject_ids = array_column($subjects, 'id');

/* ── Submitted task IDs ── */
$submitted_task_ids = [];
$sub_stmt = $conn->prepare("SELECT DISTINCT task_id FROM task_submissions WHERE student_id = ?");
$sub_stmt->bind_param("i", $student_id);
$sub_stmt->execute();
$sub_result = $sub_stmt->get_result();
while ($sub_row = $sub_result->fetch_assoc()) $submitted_task_ids[] = (int)$sub_row['task_id'];

/* ── Tasks by type ── */
$tasks = ['activities' => [], 'homework' => [], 'laboratory' => []];
if (!empty($subject_ids)) {
    $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
    foreach (array_keys($tasks) as $type) {
        $types = str_repeat('i', count($subject_ids)) . 's';
        $stmt  = $conn->prepare("
            SELECT t.*, s.subject_name, s.code
            FROM tasks t JOIN subjects s ON t.subject_id = s.id
            WHERE t.subject_id IN ($placeholders) AND t.task_type = ?
            ORDER BY t.created_at DESC
        ");
        $params = array_merge($subject_ids, [$type]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['is_submitted'] = in_array((int)$row['id'], $submitted_task_ids);
            $row['is_overdue']   = !empty($row['due_date']) && strtotime($row['due_date']) < time();
            $tasks[$type][]      = $row;
        }
    }
}

/* ── Overall stats ── */
$total_stmt = $conn->prepare("
    SELECT COUNT(*) as total FROM tasks t
    JOIN subjects s ON t.subject_id = s.id
    WHERE s.course_id = ? AND s.year_level = ?
    AND (s.section = ? OR s.section = '')
");
$total_stmt->bind_param("iss", $course_id, $year, $section);
$total_stmt->execute();
$total_tasks = (int)$total_stmt->get_result()->fetch_assoc()['total'];

$submitted_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT ts.task_id) as submitted
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    JOIN subjects s ON t.subject_id = s.id
    WHERE ts.student_id = ? AND s.course_id = ? AND s.year_level = ?
    AND (s.section = ? OR s.section = '')
");
$submitted_stmt->bind_param("iiss", $student_id, $course_id, $year, $section);
$submitted_stmt->execute();
$total_submitted = (int)$submitted_stmt->get_result()->fetch_assoc()['submitted'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?= asset('css/studentportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <!-- ── Page Header ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><i class="fas fa-tasks"></i> Task Center</div>
            <h1 class="page-header-title">My Tasks</h1>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <?php if($is_inactive): ?>
                <span class="result-count" style="background:rgba(245,158,11,0.1);color:#d97706;border-color:rgba(245,158,11,0.3);">
                    <i class="fas fa-archive"></i> Past Term Records
                </span>
            <?php endif; ?>
            <span class="result-count">
                <i class="fas fa-calendar-alt"></i>
                <?php echo htmlspecialchars($active_year . ' · ' . $active_sem . ' Sem'); ?>
            </span>
        </div>
    </div>

    <!-- ── Info strip ── -->
    <div style="margin-bottom:24px;padding:12px 20px;background:rgba(255,255,255,0.92);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.6);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);font-size:0.82rem;color:var(--text-secondary);display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <i class="fas fa-info-circle" style="color:var(--primary-blue);"></i>
        <span><strong><?php echo htmlspecialchars($course_name); ?></strong></span>
        <span style="color:var(--border-medium);">·</span>
        <span>Year <strong><?php echo htmlspecialchars($year); ?></strong></span>
        <span style="color:var(--border-medium);">·</span>
        <span>Section <strong><?php echo htmlspecialchars($section); ?></strong></span>
        <?php if($is_inactive): ?>
            <span style="color:var(--border-medium);">·</span>
            <em style="color:#a16207;">Enrolled: <?php echo htmlspecialchars($student['school_year'] . ' ' . $student['semester'] . ' Sem'); ?></em>
        <?php endif; ?>
    </div>

    <!-- ── Stats ── -->
    <div class="stats-grid" style="margin-bottom:28px;">
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Total Tasks</span><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-value"><?php echo $total_tasks; ?></div>
            <p class="stat-meta">Assigned to you</p>
        </div>
        <div class="stat-card present-card">
            <div class="stat-header"><span class="stat-label">Submitted</span><i class="fas fa-check-circle"></i></div>
            <div class="stat-value" style="color:var(--accent-emerald);"><?php echo $total_submitted; ?></div>
            <p class="stat-meta">Completed tasks</p>
            <?php if($total_tasks > 0): ?>
            <div class="progress-bar"><div class="progress-fill" style="width:<?php echo round(($total_submitted/$total_tasks)*100); ?>%;background:var(--accent-emerald);"></div></div>
            <?php endif; ?>
        </div>
        <div class="stat-card absent-card">
            <div class="stat-header"><span class="stat-label">Pending</span><i class="fas fa-clock"></i></div>
            <div class="stat-value" style="color:var(--accent-rose);"><?php echo max(0, $total_tasks - $total_submitted); ?></div>
            <p class="stat-meta">Not yet submitted</p>
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

    <!-- ── Activities ── -->
    <?php if (count($tasks['activities']) > 0): ?>
    <div class="task-type-section" id="section-activities">
        <div class="task-type-header activities" onclick="toggleSection('activities')">
            <h3><i class="fas fa-running"></i> Activities</h3>
            <span class="task-count"><?php echo count($tasks['activities']); ?></span>
            <i class="fas fa-chevron-down" style="margin-left:auto;transition:transform 0.3s;"></i>
        </div>
        <div class="task-type-content" id="section-activities-content" style="display:none;">
            <?php foreach ($tasks['activities'] as $task): ?>
                <?= renderTaskItem($task, $student_id, BASE_URL); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Homework ── -->
    <?php if (count($tasks['homework']) > 0): ?>
    <div class="task-type-section" id="section-homework">
        <div class="task-type-header homework" onclick="toggleSection('homework')">
            <h3><i class="fas fa-book"></i> Homework</h3>
            <span class="task-count"><?php echo count($tasks['homework']); ?></span>
            <i class="fas fa-chevron-down" style="margin-left:auto;transition:transform 0.3s;"></i>
        </div>
        <div class="task-type-content" id="section-homework-content" style="display:none;">
            <?php foreach ($tasks['homework'] as $task): ?>
                <?= renderTaskItem($task, $student_id, BASE_URL); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Laboratory ── -->
    <?php if (count($tasks['laboratory']) > 0): ?>
    <div class="task-type-section" id="section-laboratory">
        <div class="task-type-header laboratory" onclick="toggleSection('laboratory')">
            <h3><i class="fas fa-flask"></i> Laboratory</h3>
            <span class="task-count"><?php echo count($tasks['laboratory']); ?></span>
            <i class="fas fa-chevron-down" style="margin-left:auto;transition:transform 0.3s;"></i>
        </div>
        <div class="task-type-content" id="section-laboratory-content" style="display:none;">
            <?php foreach ($tasks['laboratory'] as $task): ?>
                <?= renderTaskItem($task, $student_id, BASE_URL); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    /* ── Inline task item template (avoids separate file dependency) ── */
    // NOTE: Replace the three include loops above with the inline version below
    // if you don't want a separate partial file.
    ?>

    <?php endif; ?>

</div><!-- /main-content -->

<!-- ═══════════════ MODALS ═══════════════ -->

<!-- View Task -->
<div id="viewTaskModal" class="task-modal">
    <div class="task-modal-content">
        <div class="task-modal-header">
            <h2 id="viewTaskTitle"><i class="fas fa-eye"></i> Task Details</h2>
            <button type="button" class="task-modal-close" onclick="closeViewModal()">×</button>
        </div>
        <div class="task-modal-body">
            <div class="task-detail-row"><span class="task-detail-label">Subject</span><span class="task-detail-value" id="viewTaskSubject"></span></div>
            <div class="task-detail-row"><span class="task-detail-label">Type</span><span class="task-detail-value" id="viewTaskType"></span></div>
            <div class="task-detail-row"><span class="task-detail-label">Description</span><p class="task-detail-value" id="viewTaskDescription" style="margin-top:5px;line-height:1.6;"></p></div>
            <div class="task-detail-row" id="viewAttachmentRow" style="display:none;">
                <span class="task-detail-label">Attachment</span>
                <a href="#" id="viewAttachmentLink" class="task-attachment-link" target="_blank"><i class="fas fa-paperclip"></i> <span id="viewAttachmentName"></span></a>
            </div>
            <div class="task-detail-row"><span class="task-detail-label">Date Posted</span><span class="task-detail-value" id="viewTaskDate"></span></div>
            <div class="task-detail-row" id="viewDueDateRow" style="display:none;">
                <span class="task-detail-label">Due Date</span><span class="task-detail-value" id="viewTaskDueDate" style="font-weight:600;"></span>
            </div>
        </div>
    </div>
</div>

<!-- View My Submission -->
<div id="viewSubmissionModal" class="task-modal">
    <div class="task-modal-content">
        <div class="task-modal-header" style="background:linear-gradient(135deg,#10B981,#059669);">
            <h2><i class="fas fa-check-circle"></i> My Submission</h2>
            <button type="button" class="task-modal-close" onclick="closeViewSubmissionModal()">×</button>
        </div>
        <div class="task-modal-body">
            <input type="hidden" id="viewSubmissionTaskId">
            <div class="task-detail-row"><span class="task-detail-label">Task</span><span class="task-detail-value" id="submissionTaskTitle" style="font-weight:600;"></span></div>
            <div class="task-detail-row">
                <span class="task-detail-label">Submitted File</span>
                <a href="#" id="submissionFileLink" class="task-attachment-link" target="_blank"><i class="fas fa-file-download"></i> <span id="submissionFileName"></span></a>
            </div>
            <div class="task-detail-row"><span class="task-detail-label">Notes</span><p class="task-detail-value" id="submissionNotes" style="margin-top:5px;line-height:1.6;"></p></div>
            <div class="task-detail-row"><span class="task-detail-label">Submitted On</span><span class="task-detail-value" id="submissionDate"></span></div>
            <div id="teacherReadStatus" style="margin-top:15px;padding:12px 16px;border-radius:50px;font-weight:600;font-size:0.85rem;display:flex;align-items:center;gap:8px;"></div>
            <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn-task" onclick="openEditSubmissionModal()" style="background:#f59e0b;color:white;border-radius:50px;"><i class="fas fa-edit"></i> Edit</button>
                <button type="button" class="btn-task" onclick="openDeleteSubmissionModal()" style="background:#ef4444;color:white;border-radius:50px;"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Submission -->
<div id="editSubmissionModal" class="task-modal">
    <div class="task-modal-content">
        <div class="task-modal-header" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
            <h2><i class="fas fa-edit"></i> Edit Submission</h2>
            <button type="button" class="task-modal-close" onclick="closeEditSubmissionModal()">×</button>
        </div>
        <div class="task-modal-body">
            <form id="editSubmissionForm">
                <input type="hidden" id="editSubmissionTaskId" name="task_id">
                <div class="task-detail-row"><span class="task-detail-label">Task</span><span class="task-detail-value" id="editSubmissionTaskTitle" style="font-weight:600;"></span></div>
                <div class="task-detail-row">
                    <span class="task-detail-label">Current File</span>
                    <a href="#" id="editCurrentFileLink" class="task-attachment-link" target="_blank"><i class="fas fa-file-download"></i> <span id="editCurrentFileName"></span></a>
                </div>
                <div class="form-group">
                    <label for="editSubmissionFile">Replace File (Optional)</label>
                    <input type="file" id="editSubmissionFile" name="submission_file" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar">
                    <small style="color:var(--text-muted);">Leave empty to keep current file</small>
                </div>
                <div class="form-group">
                    <label for="editSubmissionNotes">Notes</label>
                    <textarea id="editSubmissionNotes" name="submission_notes" rows="3" placeholder="Update your notes..."></textarea>
                </div>
                <button type="submit" class="btn-submit-task" style="background:#f59e0b;"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation -->
<div id="deleteSubmissionModal" class="task-modal">
    <div class="task-modal-content" style="max-width:420px;">
        <div class="task-modal-header" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h2>
            <button type="button" class="task-modal-close" onclick="closeDeleteSubmissionModal()">×</button>
        </div>
        <div class="task-modal-body" style="text-align:center;">
            <p style="font-size:1rem;margin-bottom:16px;color:var(--text-secondary);">Are you sure you want to delete your submission for:</p>
            <p id="deleteSubmissionTaskTitle" style="font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:1.1rem;color:var(--slate-700);margin-bottom:16px;"></p>
            <p style="color:#ef4444;margin-bottom:24px;font-size:0.85rem;"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone!</p>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button type="button" onclick="closeDeleteSubmissionModal()" class="btn-cancel">Cancel</button>
                <button type="button" onclick="confirmDeleteSubmission()" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Submit Task -->
<div id="submitTaskModal" class="task-modal">
    <div class="task-modal-content">
        <div class="task-modal-header" style="background:linear-gradient(135deg,#10B981,#059669);">
            <h2><i class="fas fa-upload"></i> Submit Task</h2>
            <button type="button" class="task-modal-close" onclick="closeSubmitModal()">×</button>
        </div>
        <div class="task-modal-body">
            <div class="task-detail-row"><span class="task-detail-label">Task</span><span class="task-detail-value" id="submitTaskTitle" style="font-weight:700;font-size:1rem;"></span></div>
            <div class="task-detail-row"><span class="task-detail-label">Subject</span><span class="task-detail-value" id="submitTaskSubject"></span></div>
            <form id="submitTaskForm" style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border-light);">
                <input type="hidden" id="submitTaskId" name="task_id">
                <div class="form-group">
                    <label for="submissionFile">Your Submission (File)</label>
                    <input type="file" id="submissionFile" name="submission_file" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip,.rar" required>
                </div>
                <div class="form-group">
                    <label for="submissionNotes">Notes (Optional)</label>
                    <textarea id="submissionNotes" name="submission_notes" rows="3" placeholder="Add any notes for your submission..."></textarea>
                </div>
                <button type="submit" class="btn-submit-task"><i class="fas fa-upload"></i> Submit Task</button>
            </form>
        </div>
    </div>
</div>

<script>
let studentEventSource = null;
let studentPollInterval = null;
let currentTaskId = null;
let currentSubmissionData = null;
const sseStudentUrl = '<?= BASE_URL ?>tasks/sse_student.php';

/* ── Inline task cards rendering (replaces PHP include partials) ── */
document.addEventListener('DOMContentLoaded', function(){
    // Render inline task items for each section
    const sections = ['activities','homework','laboratory'];
    sections.forEach(type => {
        const content = document.getElementById('section-' + type + '-content');
        if(!content) return;
        content.querySelectorAll('.task-item').forEach(item => {
            const taskId = item.dataset.taskId;
            const isOverdue = item.dataset.isOverdue === 'true';
            const submitBtn = item.querySelector('.btn-submit');
            if(isOverdue && submitBtn){
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-ban"></i> Overdue';
                submitBtn.style.background = 'rgba(239,68,68,0.15)';
                submitBtn.style.color = '#ef4444';
                submitBtn.style.cursor = 'not-allowed';
                item.dataset.overdueLocked = 'true';
            }
        });
    });
    connectStudentRealtime();
    loadInitialTeacherReadStatus();
});

function updateTeacherReadStatus(taskId, teacherRead) {
    const el = document.querySelector(`.teacher-read-status[data-task-id="${taskId}"]`);
    if(!el) return;
    el.dataset.teacherRead = teacherRead ? '1' : '0';
    if(teacherRead == 1){
        el.innerHTML = '<i class="fas fa-eye"></i> Teacher Viewed';
        el.style.background = 'rgba(16,185,129,0.12)';
        el.style.color = '#059669';
    } else {
        el.innerHTML = '<i class="fas fa-clock"></i> Submitted';
        el.style.background = '';
        el.style.color = '';
    }
}

function loadInitialTeacherReadStatus() {
    document.querySelectorAll('.teacher-read-status').forEach(badge => {
        const taskId = badge.dataset.taskId;
        fetch(`<?= BASE_URL ?>tasks/get_submissions.php?task_id=${taskId}`)
            .then(r => r.json())
            .then(data => {
                if(data.success && data.submissions){
                    const sub = data.submissions.find(s => parseInt(s.student_id) === <?php echo $student_id; ?>);
                    if(sub && sub.teacher_read == 1) updateTeacherReadStatus(taskId, 1);
                }
            }).catch(() => {});
    });
}

function connectStudentRealtime() {
    if(studentEventSource) studentEventSource.close();
    try {
        studentEventSource = new EventSource(sseStudentUrl);
        studentEventSource.onmessage = function(e){
            try {
                JSON.parse(e.data).forEach(u => {
                    updateTeacherReadStatus(u.task_id, 1);
                    showStudentNotification(`"${u.task_title}" viewed by teacher!`);
                });
            } catch(err){}
        };
        studentEventSource.onerror = function(){
            if(studentEventSource){ studentEventSource.close(); studentEventSource = null; }
            startStudentPolling();
        };
    } catch(e){ startStudentPolling(); }
}

function startStudentPolling() {
    if(studentPollInterval) clearInterval(studentPollInterval);
    studentPollInterval = setInterval(() => {
        document.querySelectorAll('.teacher-read-status[data-teacher-read="0"]').forEach(badge => {
            const taskId = badge.dataset.taskId;
            fetch(`<?= BASE_URL ?>tasks/get_submissions.php?task_id=${taskId}`)
                .then(r => r.json())
                .then(data => {
                    if(data.success && data.submissions){
                        const sub = data.submissions.find(s => parseInt(s.student_id) === <?php echo $student_id; ?>);
                        if(sub && sub.teacher_read == 1) updateTeacherReadStatus(taskId, 1);
                    }
                }).catch(() => {});
        });
    }, 30000);
}

function showStudentNotification(message, type='success') {
    const toast = document.createElement('div');
    toast.className = `notification notification-${type}`;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;transform:translateX(400px);transition:transform 0.3s;';
    toast.innerHTML = `<i class="fas fa-${type==='error'?'exclamation-circle':'eye'}"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.style.transform = 'translateX(0)', 100);
    setTimeout(() => { toast.style.transform = 'translateX(400px)'; setTimeout(() => toast.remove(), 400); }, 6000);
}

function toggleSection(type) {
    const content = document.getElementById('section-' + type + '-content');
    const icon    = document.querySelector('#section-' + type + ' .fa-chevron-down');
    if(content.style.display === 'none' || content.style.display === ''){
        content.style.display = 'block';
        content.style.maxHeight = '0'; content.style.overflow = 'hidden';
        content.style.transition = 'max-height 0.4s ease, opacity 0.4s ease';
        content.style.opacity = '0';
        void content.offsetWidth;
        content.style.maxHeight = content.scrollHeight + 100 + 'px';
        content.style.opacity = '1';
        setTimeout(() => content.style.overflow = 'visible', 400);
        if(icon) icon.style.transform = 'rotate(180deg)';
    } else {
        content.style.maxHeight = content.scrollHeight + 'px';
        content.style.transition = 'max-height 0.3s ease, opacity 0.3s ease';
        content.style.opacity = '0';
        setTimeout(() => { content.style.display = 'none'; content.style.overflow = 'hidden'; }, 300);
        if(icon) icon.style.transform = 'rotate(0deg)';
    }
}

function viewTask(taskId) {
    fetch('<?= BASE_URL ?>tasks/get_task.php?id=' + taskId)
        .then(r => r.json())
        .then(data => {
            if(data.success && data.task){
                const t = data.task;
                document.getElementById('viewTaskTitle').innerHTML = '<i class="fas fa-eye"></i> ' + t.title;
                document.getElementById('viewTaskSubject').textContent = t.subject_name || 'N/A';
                document.getElementById('viewTaskType').textContent = t.task_type ? t.task_type.charAt(0).toUpperCase() + t.task_type.slice(1) : 'N/A';
                document.getElementById('viewTaskDescription').textContent = t.description || 'No description';
                document.getElementById('viewTaskDate').textContent = t.created_at_formatted || (t.created_at ? new Date(t.created_at).toLocaleDateString() : 'N/A');
                if(t.due_date){
                    document.getElementById('viewDueDateRow').style.display = 'flex';
                    document.getElementById('viewTaskDueDate').innerHTML = t.is_overdue
                        ? `<span style="color:#e74c3c;"><i class="fas fa-exclamation-triangle"></i> ${t.due_date_formatted} (Overdue)</span>`
                        : `<span style="color:#27ae60;"><i class="fas fa-clock"></i> ${t.due_date_formatted}</span>`;
                } else { document.getElementById('viewDueDateRow').style.display = 'none'; }
                if(t.attachment){
                    document.getElementById('viewAttachmentRow').style.display = 'block';
                    document.getElementById('viewAttachmentLink').href = '<?= BASE_URL ?>tasks/uploads/' + t.attachment;
                    document.getElementById('viewAttachmentName').textContent = t.original_filename || t.attachment;
                } else { document.getElementById('viewAttachmentRow').style.display = 'none'; }
                document.getElementById('viewTaskModal').classList.add('show');
            } else { alert('Error loading task details: ' + (data.message || 'Unknown error')); }
        }).catch(() => alert('Error loading task details'));
}

function closeViewModal() { document.getElementById('viewTaskModal').classList.remove('show'); }

function viewMySubmission(taskId) {
    const modal    = document.getElementById('viewSubmissionModal');
    const modalBody = modal.querySelector('.task-modal-body');
    const savedContent = modalBody.innerHTML;
    modalBody.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:var(--primary-blue);"></i><p style="margin-top:12px;color:var(--text-muted);">Loading…</p></div>';
    modal.classList.add('show');

    fetch('<?= BASE_URL ?>tasks/get_submissions.php?task_id=' + taskId)
        .then(r => r.json())
        .then(data => {
            if(data.success && data.submissions && data.submissions.length > 0){
                const studentId = <?php echo $student_id; ?>;
                const sub = data.submissions.find(s => parseInt(s.student_id) === studentId);
                if(sub){
                    currentSubmissionData = {
                        taskId, taskTitle: data.task.title,
                        filePath: sub.file_path, originalFilename: sub.original_filename,
                        notes: sub.notes, teacherRead: sub.teacher_read == 1
                    };
                    modalBody.innerHTML = savedContent;
                    document.getElementById('viewSubmissionTaskId').value = taskId;
                    document.getElementById('submissionTaskTitle').textContent = data.task.title;
                    document.getElementById('submissionDate').textContent = sub.submitted_at_formatted || new Date(sub.submitted_at).toLocaleString();
                    let notes = sub.notes;
                    if(!notes || notes === 'undefined' || notes === 'null') notes = 'No notes provided';
                    document.getElementById('submissionNotes').textContent = notes;
                    const fileLink = document.getElementById('submissionFileLink');
                    if(sub.file_path && sub.file_path !== 'undefined'){
                        fileLink.style.display = 'inline-flex';
                        fileLink.href = '<?= BASE_URL ?>tasks/student_uploads/' + sub.file_path;
                        document.getElementById('submissionFileName').textContent = sub.original_filename || sub.file_path;
                    } else { fileLink.style.display = 'none'; }
                    const ts = document.getElementById('teacherReadStatus');
                    if(sub.teacher_read == 1){
                        ts.innerHTML = '<i class="fas fa-eye" style="color:#10b981;"></i> Teacher has viewed your submission';
                        ts.style.cssText = 'color:#10b981;background:rgba(16,185,129,0.1);padding:10px 16px;border-radius:50px;font-size:0.85rem;';
                    } else {
                        ts.innerHTML = '<i class="fas fa-clock" style="color:#f59e0b;"></i> Awaiting teacher review';
                        ts.style.cssText = 'color:#d97706;background:rgba(245,158,11,0.1);padding:10px 16px;border-radius:50px;font-size:0.85rem;';
                    }
                } else {
                    modalBody.innerHTML = `<div style="text-align:center;padding:40px;"><i class="fas fa-exclamation-triangle" style="font-size:48px;color:#f59e0b;"></i><p style="margin-top:20px;">No submission found for you.</p></div>`;
                }
            } else {
                modalBody.innerHTML = `<div style="text-align:center;padding:40px;"><i class="fas fa-file-alt" style="font-size:48px;color:#9ca3af;"></i><p style="margin-top:20px;">No submission found for this task.</p></div>`;
            }
        }).catch(err => {
            modalBody.innerHTML = `<div style="text-align:center;padding:40px;"><i class="fas fa-exclamation-triangle" style="font-size:48px;color:#ef4444;"></i><p style="margin-top:20px;">Error loading: ${err.message}</p></div>`;
        });
}

function closeViewSubmissionModal() { document.getElementById('viewSubmissionModal').classList.remove('show'); }

function openSubmitModal(taskId, taskTitle, subjectName) {
    const taskItem = document.querySelector(`.task-item[data-task-id="${taskId}"]`);
    if(taskItem && taskItem.dataset.isOverdue === 'true'){ alert('This task is overdue. You cannot submit after the due date.'); return; }
    currentTaskId = taskId;
    document.getElementById('submitTaskId').value = taskId;
    document.getElementById('submitTaskTitle').textContent = taskTitle;
    document.getElementById('submitTaskSubject').textContent = subjectName;
    document.getElementById('submitTaskForm').reset();
    document.getElementById('submitTaskModal').classList.add('show');
}

function closeSubmitModal() { document.getElementById('submitTaskModal').classList.remove('show'); currentTaskId = null; }

document.getElementById('submitTaskForm').addEventListener('submit', function(e){
    e.preventDefault();
    const fileInput = document.getElementById('submissionFile');
    if(!fileInput.files.length){ showStudentNotification('Please select a file to submit', 'error'); return; }
    const formData = new FormData();
    formData.append('task_id', document.getElementById('submitTaskId').value);
    formData.append('submission_file', fileInput.files[0]);
    formData.append('submission_notes', document.getElementById('submissionNotes').value);
    const btn = e.target.querySelector('.btn-submit-task');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
    btn.disabled = true;
    fetch('<?= BASE_URL ?>tasks/student_submit_task', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success){ showStudentNotification('Task submitted successfully! ✅'); closeSubmitModal(); setTimeout(() => location.reload(), 2000); }
            else { alert('Error: ' + data.message); }
        }).catch(() => alert('An error occurred. Please try again.'))
        .finally(() => { btn.innerHTML = orig; btn.disabled = false; });
});

function openEditSubmissionModal() {
    if(!currentSubmissionData) return;
    const taskItem = document.querySelector(`.task-item[data-task-id="${currentSubmissionData.taskId}"]`);
    if(taskItem && taskItem.dataset.overdueLocked === 'true'){ showStudentNotification('This task is overdue. You cannot edit your submission.', 'error'); return; }
    closeViewSubmissionModal();
    document.getElementById('editSubmissionTaskId').value = currentSubmissionData.taskId;
    document.getElementById('editSubmissionTaskTitle').textContent = currentSubmissionData.taskTitle;
    document.getElementById('editSubmissionNotes').value = currentSubmissionData.notes || '';
    if(currentSubmissionData.filePath && currentSubmissionData.filePath !== 'undefined'){
        document.getElementById('editCurrentFileLink').style.display = 'inline-flex';
        document.getElementById('editCurrentFileLink').href = '<?= BASE_URL ?>tasks/student_uploads/' + currentSubmissionData.filePath;
        document.getElementById('editCurrentFileName').textContent = currentSubmissionData.originalFilename || currentSubmissionData.filePath;
    } else { document.getElementById('editCurrentFileLink').style.display = 'none'; }
    document.getElementById('editSubmissionFile').value = '';
    document.getElementById('editSubmissionModal').classList.add('show');
}

function closeEditSubmissionModal() { document.getElementById('editSubmissionModal').classList.remove('show'); }

document.getElementById('editSubmissionForm').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData();
    formData.append('task_id', document.getElementById('editSubmissionTaskId').value);
    formData.append('submission_notes', document.getElementById('editSubmissionNotes').value);
    const fileInput = document.getElementById('editSubmissionFile');
    if(fileInput.files.length > 0) formData.append('submission_file', fileInput.files[0]);
    const btn = e.target.querySelector('.btn-submit-task');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    btn.disabled = true;
    fetch('<?= BASE_URL ?>tasks/update_submission', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if(data.success){ showStudentNotification(data.message + ' ✅'); setTimeout(() => location.reload(), 1500); }
            else { showStudentNotification('Error: ' + data.message, 'error'); }
        }).catch(() => alert('An error occurred.'))
        .finally(() => { btn.innerHTML = orig; btn.disabled = false; });
});

function openDeleteSubmissionModal() {
    if(!currentSubmissionData) return;
    const taskItem = document.querySelector(`.task-item[data-task-id="${currentSubmissionData.taskId}"]`);
    if(taskItem && taskItem.dataset.overdueLocked === 'true'){ showStudentNotification('This task is overdue. You cannot delete your submission.', 'error'); return; }
    document.getElementById('deleteSubmissionTaskTitle').textContent = currentSubmissionData.taskTitle;
    document.getElementById('deleteSubmissionModal').classList.add('show');
}

function closeDeleteSubmissionModal() { document.getElementById('deleteSubmissionModal').classList.remove('show'); }

function confirmDeleteSubmission() {
    if(!currentSubmissionData) return;
    fetch('<?= BASE_URL ?>tasks/delete_submission', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: currentSubmissionData.taskId })
    }).then(r => r.json())
      .then(data => {
          if(data.success){ showStudentNotification('Submission deleted.', 'error'); closeDeleteSubmissionModal(); setTimeout(() => location.reload(), 2000); }
          else { showStudentNotification('Error: ' + data.message, 'error'); }
      }).catch(() => alert('An error occurred.'));
}

/* Backdrop + ESC close */
document.addEventListener('click', e => { if(e.target.classList.contains('task-modal')) e.target.classList.remove('show'); });
document.addEventListener('keydown', e => {
    if(e.key === 'Escape'){
        ['viewTaskModal','viewSubmissionModal','submitTaskModal','editSubmissionModal','deleteSubmissionModal']
            .forEach(id => document.getElementById(id)?.classList.remove('show'));
    }
});

window.addEventListener('beforeunload', () => {
    if(studentEventSource) studentEventSource.close();
    if(studentPollInterval) clearInterval(studentPollInterval);
});
</script>

<?php
/* ────────────────────────────────────────────────────────────────
   Inline task-item PHP function — replaces the partial include
   ──────────────────────────────────────────────────────────────── */
function renderTaskItem($task, $student_id, $base_url) {
    $id           = $task['id'];
    $isSubmitted  = $task['is_submitted'];
    $isOverdue    = $task['is_overdue'];
    $title        = htmlspecialchars($task['title']);
    $subjectName  = htmlspecialchars($task['subject_name']);
    $description  = htmlspecialchars($task['description'] ?? '');
    $postedDate   = date('M j, Y', strtotime($task['created_at']));
    $dueDate      = $task['due_date'] ?? '';

    ob_start(); ?>
    <div class="task-item"
         data-task-id="<?= $id ?>"
         data-is-overdue="<?= $isOverdue ? 'true' : 'false' ?>"
         data-due-date="<?= htmlspecialchars($dueDate) ?>"
         data-subject-id="<?= $task['subject_id'] ?>">

        <div class="task-item-header">
            <div style="flex:1;">
                <h4 class="task-title"><?= $title ?></h4>
                <span class="task-subject"><i class="fas fa-book"></i> <?= $subjectName ?></span>
            </div>
            <?php if($isSubmitted): ?>
                <span class="badge-green teacher-read-status" data-task-id="<?= $id ?>" data-teacher-read="0"
                      style="display:inline-flex;align-items:center;gap:5px;">
                    <i class="fas fa-clock"></i> Submitted
                </span>
            <?php elseif($isOverdue): ?>
                <span class="badge-red" style="display:inline-flex;align-items:center;gap:5px;">
                    <i class="fas fa-exclamation-triangle"></i> Overdue
                </span>
            <?php else: ?>
                <span class="badge-yellow" style="display:inline-flex;align-items:center;gap:5px;">
                    <i class="fas fa-clock"></i> Pending
                </span>
            <?php endif; ?>
        </div>

        <?php if($description): ?>
            <p class="task-description"><?= $description ?></p>
        <?php endif; ?>

        <div class="task-meta">
            <div style="display:flex;flex-direction:column;gap:4px;">
                <span class="task-date"><i class="fas fa-calendar"></i> Posted: <?= $postedDate ?></span>
                <?php if($dueDate): ?>
                    <span class="task-date" style="color:<?= $isOverdue ? '#ef4444' : 'var(--text-muted)'; ?>;">
                        <i class="fas fa-flag"></i> Due: <?= date('M j, Y', strtotime($dueDate)); ?>
                        <?php if($isOverdue): ?> <strong>(Overdue)</strong><?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="task-actions">
                <button class="btn-task btn-view" onclick="viewTask(<?= $id ?>)">
                    <i class="fas fa-eye"></i> View
                </button>
                <?php if($isSubmitted): ?>
                    <button class="btn-task btn-submitted" onclick="viewMySubmission(<?= $id ?>)">
                        <i class="fas fa-check"></i> View Submission
                    </button>
                <?php else: ?>
                    <button class="btn-task btn-submit <?= $isOverdue ? 'overdue-btn' : '' ?>"
                            onclick="openSubmitModal(<?= $id ?>, '<?= addslashes($task['title']) ?>', '<?= addslashes($task['subject_name']) ?>')"
                            <?= $isOverdue ? 'disabled title="Task overdue"' : '' ?>>
                        <i class="fas fa-upload"></i> <?= $isOverdue ? 'Overdue' : 'Submit' ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}
?>

<?php
// Re-render task sections using the inline function
// (This block outputs AFTER </div></html> intentionally — 
//  move the task section rendering above the </div> in production)
?>
</body>
</html>