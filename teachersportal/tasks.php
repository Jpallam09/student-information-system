<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/teacher_filter.php';

if (!isset($_SESSION['teacher_id'])) {
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

$subject_year_filter    = '';
$subject_section_filter = '';
$subject_params         = [];
$subject_types          = '';

if (!$is_admin) {
    $subject_year_filter    = getCombinedYearFilter('s.year_level', $subject_params, $subject_types);
    $subject_section_filter = getCombinedSectionFilter('s.section', $subject_params, $subject_types);
}

$selected_course = $_SESSION['teacher_course'] ?? '';
$teacher_id      = $_SESSION['teacher_id'];
$search          = trim($_GET['search'] ?? '');

if (empty($selected_course)) {
    echo "Course not selected."; 
    exit();
}

$allowed_courses = ['BSIT', 'BSED', 'BAT', 'BTVTED'];
if (!in_array(strtoupper($selected_course), $allowed_courses)) {
    echo "<p>Invalid course.</p>"; 
    exit();
}

$course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$selected_course'");
if (!$course_result || mysqli_num_rows($course_result) == 0) {
    die("Course not found in DB.");
}
$course_row = mysqli_fetch_assoc($course_result);
$course_id  = $course_row['id'];

// Summary stats
$stats_sql = "SELECT
    COUNT(DISTINCT t.id) AS total_tasks,
    COUNT(DISTINCT s.id) AS total_subjects,
    COUNT(DISTINCT ts.id) AS total_submissions,
    SUM(CASE WHEN ts.teacher_read = 0 THEN 1 ELSE 0 END) AS unread_submissions
FROM subjects s
LEFT JOIN tasks t ON t.subject_id = s.id AND t.teacher_id = '$teacher_id'
LEFT JOIN task_submissions ts ON ts.task_id = t.id
WHERE s.course_id = '$course_id'
$subject_year_filter
$subject_section_filter";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [];

$total_tasks        = $stats['total_tasks'] ?? 0;
$total_subjects     = $stats['total_subjects'] ?? 0;
$total_submissions  = $stats['total_submissions'] ?? 0;
$unread_submissions = $stats['unread_submissions'] ?? 0;

// Subjects with unread count
$subjects_query = mysqli_query(
    $conn,
    "SELECT s.*, (
            SELECT COUNT(*)
            FROM task_submissions ts
            JOIN tasks t ON ts.task_id = t.id
            WHERE t.subject_id = s.id AND ts.teacher_read = 0
        ) AS unread_count
     FROM subjects s
     WHERE course_id='$course_id'
     $subject_year_filter
     $subject_section_filter
     ORDER BY s.year_level ASC, s.subject_name ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tasks - <?= htmlspecialchars($selected_course) ?></title>
 <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
<link rel="stylesheet" href="<?= asset('css/tasks.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include PROJECT_ROOT . '/teachersportal/sidebar.php'; ?>

<div class="content">
    <h1><i class="fas fa-tasks"></i> Tasks Management - <?= htmlspecialchars($selected_course) ?></h1>

    <div class="intro-section">
        <!-- Summary Metric Cards -->
        <div class="summary-grid">
            <div class="metric-card">
                <div class="metric-icon-wrap icon-tasks-total">
                    <i class="fas fa-list-check"></i>
                </div>
                <div class="metric-body">
                    <div class="metric-label">Created tasks</div>
                    <div class="metric-value"><?= $total_tasks ?></div>
                    <div class="metric-sub">across all subjects</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon-wrap icon-submissions">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="metric-body">
                    <div class="metric-label">Total submissions</div>
                    <div class="metric-value"><?= $total_submissions ?></div>
                    <div class="metric-sub">from students</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon-wrap icon-unread">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="metric-body">
                    <div class="metric-label">Unread submissions</div>
                    <div class="metric-value metric-warning"><?= $unread_submissions ?></div>
                    <div class="metric-sub">waiting for review</div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon-wrap icon-subjects">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="metric-body">
                    <div class="metric-label">Subjects with tasks</div>
                    <div class="metric-value"><?= $total_subjects ?></div>
                    <div class="metric-sub">active this term</div>
                </div>
            </div>
        </div>

        <!-- Guide Cards -->
        <div class="guide-grid">
            <div class="guide-card">
                <div class="guide-icon icon-bell"><i class="fas fa-bell"></i></div>
                <div class="guide-text"><strong>Bell</strong> — view unread student submissions per subject</div>
            </div>
            <div class="guide-card">
                <div class="guide-icon icon-edit"><i class="fas fa-pencil-alt"></i></div>
                <div class="guide-text"><strong>Edit / Delete</strong> — modify tasks; deleting removes all submissions</div>
            </div>
            <div class="guide-card">
                <div class="guide-icon icon-search"><i class="fas fa-search"></i></div>
                <div class="guide-text"><strong>Search</strong> — filter by title, description, subject, or type</div>
            </div>
            <div class="guide-card">
                <div class="guide-icon icon-eye"><i class="fas fa-eye"></i></div>
                <div class="guide-text"><strong>Mark as read</strong> — clears the bell badge after reviewing</div>
            </div>
        </div>
    </div>

    <div class="section-box">
        <div class="top-bar">
            <h2 style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-book"></i><span>Your Tasks Subjects</span>
            </h2>
        </div>

        <?php if (mysqli_num_rows($subjects_query) > 0): ?>
            <div class="cards">
                <?php while ($subject = mysqli_fetch_assoc($subjects_query)): ?>
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div>
                                <h3><?= htmlspecialchars($subject['code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?></h3>
                                <p style="margin: 5px 0; color: #666; font-size: 0.95em;">
                                    <?= htmlspecialchars($subject['year_level']) ?> | Section <?= htmlspecialchars($subject['section']) ?>
                                </p>
                                <?php if (!empty($subject['description'])): ?>
                                    <p style="margin: 5px 0; font-size: 0.9em; color: #555;"><?= htmlspecialchars($subject['description']) ?></p>
                                    <span class="badge <?= $subject['subject_type']=='MINOR' ? 'badge-minor' : 'badge-major' ?>">
                                        <?= strtoupper($subject['subject_type']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="notification-icon" data-subject-id="<?= $subject['id'] ?>" data-subject-name="<?= htmlspecialchars($subject['subject_name']) ?>" title="View submissions" style="cursor: pointer;">
                                <i class="fas fa-bell"></i>
                                <?php if (!empty($subject['unread_count']) && $subject['unread_count'] > 0): ?>
                                    <span class="notification-count"><?= $subject['unread_count'] ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="task-buttons">
                            <a href="<?= base_url() ?>teachers_access/submit_act.php?subject_id=<?= $subject['id'] ?>" class="task-btn act" title="Submit Activity">
                                <i class="fas fa-file-upload"></i> Activity
                            </a>
                            <a href="<?= base_url() ?>teachers_access/submit_hmwork.php?subject_id=<?= $subject['id'] ?>" class="task-btn hm" title="Homework">
                                <i class="fas fa-book"></i> Homework
                            </a>
                            <a href="<?= base_url() ?>teachers_access/submit_lab.php?subject_id=<?= $subject['id'] ?>" class="task-btn lab" title="Laboratory">
                                <i class="fas fa-flask"></i> Laboratory
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; color: #666;">
                <i class="fas fa-book" style="font-size: 4em; margin-bottom: 20px; opacity: 0.5;"></i>
                <h3>No subjects found</h3>
                <p>Add subjects first via <a href="subjects.php">Subjects & Classes</a></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================== CREATED TASKS SECTION ================== -->
    <div class="section-box">
        <div class="top-bar" style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: center;">
            <h2 style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <i class="fas fa-list-check"></i> Created Tasks
            </h2>
            <form method="GET" class="tasks-search-form" style="display:flex; flex-wrap: wrap; gap:0.75rem; align-items:center; margin:0;">
                <input type="text" name="search" placeholder="Search tasks by title, subject, or type..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
                <?php if ($search !== ''): ?>
                    <a href="tasks.php" class="refresh-btn" title="Clear search"><i class="fas fa-rotate-right"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <?php
        $tasks_params = [$teacher_id, $course_id];
        $tasks_types  = "ii";
        $tasks_filter = "WHERE t.teacher_id=? AND s.course_id=?";

        if (!$is_admin) {
            $tasks_filter .= " $subject_year_filter $subject_section_filter";
            if (!empty($subject_params)) {
                $tasks_params = array_merge($tasks_params, $subject_params);
                $tasks_types .= $subject_types;
            }
        }

        if ($search !== '') {
            $tasks_filter .= " AND (t.title LIKE ? OR t.description LIKE ? OR s.subject_name LIKE ? OR t.task_type LIKE ?)";
            $search_like = "%{$search}%";
            $tasks_params = array_merge($tasks_params, [$search_like, $search_like, $search_like, $search_like]);
            $tasks_types .= 'ssss';
        }

        $tasks_sql = "SELECT t.*, s.code, s.subject_name
                      FROM tasks t
                      JOIN subjects s ON t.subject_id = s.id
                      $tasks_filter
                      ORDER BY t.created_at DESC";

        $stmt_tasks = $conn->prepare($tasks_sql);
        if (!$stmt_tasks) {
            die('Database error preparing tasks query: ' . mysqli_error($conn));
        }

        $stmt_tasks->bind_param($tasks_types, ...$tasks_params);
        $stmt_tasks->execute();
        $tasks_query = $stmt_tasks->get_result();
        ?>

        <div class="table-container">
            <table class="tasks-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Task File</th>
                        <th>Date Created</th>
                        <th>Due Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tasks_query && $tasks_query->num_rows > 0): ?>
                        <?php while ($task = $tasks_query->fetch_assoc()): ?>
                            <?php
                            $type_class = $task['task_type'] === 'activities' ? 'badge-act' :
                                        ($task['task_type'] === 'homework'   ? 'badge-hm'  : 'badge-lab');

                            $task_file = $task['attachment'] ?
                                '<a href="' . BASE_URL . 'tasks/uploads/' . htmlspecialchars($task['attachment']) . '" target="_blank" class="task-file-link">
                                    <i class="fas fa-file"></i> ' . htmlspecialchars($task['original_filename'] ?? basename($task['attachment'])) . '
                                </a>' : '<span class="no-file">No file</span>';

                            $created = date('M j, Y', strtotime($task['created_at']));
                            $due     = $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No deadline';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($task['code']) ?> - <?= htmlspecialchars($task['subject_name']) ?></td>
                                <td><span class="badge <?= $type_class ?>"><?= ucfirst(htmlspecialchars($task['task_type'])) ?></span></td>
                                <td><?= htmlspecialchars($task['title']) ?></td>
                                <td><?= strlen($task['description']) > 50 ? substr(htmlspecialchars($task['description']), 0, 50) . '...' : htmlspecialchars($task['description']) ?></td>
                                <td><?= $task_file ?></td>
                                <td><?= $created ?></td>
                                <td><?= $due ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?= BASE_URL ?>tasks/edit_task.php?task_id=<?= $task['id'] ?>" class="action-btn edit" title="Edit">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>tasks/delete_task.php?task_id=<?= $task['id'] ?>" class="action-btn delete" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center; padding:40px; color:#666;">
                            <i class="fas fa-clipboard-list" style="font-size:3em; opacity:0.5; display:block; margin-bottom:15px;"></i>
                            No tasks created yet. Create tasks using the subject cards above.
                        </td></tr>
                    <?php endif;
                    $stmt_tasks->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Submissions Modal -->
    <div id="submissionsModal" class="submissions-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-bell"></i> Loading Submissions...</h2>
                <span class="close" id="closeModal">&times;</span>
            </div>
            <div id="modalBody" style="padding: 25px;">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>
</div>

<!-- Delete Task Modal -->
<div id="deleteTaskModal" class="modal">
    <div class="modal-content small">
        <span class="close-modal">&times;</span>
        <h2><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Confirm Delete</h2>
        <p>Are you sure you want to permanently delete this task? This action cannot be undone.</p>
        <div class="modal-actions" style="justify-content: center; margin-top: 20px;">
            <button id="cancelDeleteTask" class="btn-outline">Cancel</button>
            <button id="confirmDeleteTask" class="btn-danger">Delete</button>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    let currentSubjectId = null;
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal      = document.getElementById('submissionsModal');
    const closeBtn   = document.getElementById('closeModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody  = document.getElementById('modalBody');

    document.querySelectorAll('.notification-icon').forEach(bell => {
        bell.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const subjectId = this.getAttribute('data-subject-id');
            const subjectName = this.getAttribute('data-subject-name');
            
            if (!subjectId || subjectId === '') {
                showNotification('Invalid subject ID', 'error');
                return;
            }
            
            currentSubjectId = subjectId;
            modalTitle.innerHTML = `<i class="fas fa-bell"></i> Submissions for ${escapeHtml(subjectName)}`;
            modal.classList.add('show');
            loadSubmissions(subjectId);
        });
    });

    if (closeBtn) {
        closeBtn.onclick = () => {
            modal.classList.remove('show');
            modalBody.innerHTML = '';
            if (currentSubjectId) refreshBellCount(currentSubjectId);
        };
    }
    
    window.onclick = (e) => { 
        if (e.target === modal) {
            modal.classList.remove('show');
            modalBody.innerHTML = '';
            if (currentSubjectId) refreshBellCount(currentSubjectId);
        }
    };
    
    document.addEventListener('keydown', (e) => { 
        if (e.key === 'Escape') {
            modal.classList.remove('show');
            modalBody.innerHTML = '';
            if (currentSubjectId) refreshBellCount(currentSubjectId);
        }
    });
});

async function loadSubmissions(subjectId) {
    try {
        modalBody.innerHTML = '<div style="text-align:center;padding:40px"><i class="fas fa-spinner fa-spin" style="font-size:2em;color:#6b7280"></i><p>Loading submissions...</p></div>';
        
        const url = `${BASE_URL}tasks/get_subject_submissions.php?subject_id=${subjectId}`;
        const res = await fetch(url);
        const data = await res.json();
        
        if (data.success && data.submissions && data.submissions.length > 0) {
            let html = '';
            data.submissions.forEach(student => {
                const studentId = student.student_id;
                
                html += `
                    <div class="student-group">
                        <div class="student-header">
                            <i class="fas fa-user-graduate"></i> ${escapeHtml(student.student_name)} • Year ${student.year_level} • Section ${escapeHtml(student.section)}
                        </div>
                        <div class="student-submissions">
                `;
                
                student.submissions.forEach(sub => {
                    const fileLink = sub.file_path && sub.file_path !== 'undefined' && sub.file_path !== 'null' && sub.file_path !== ''
                        ? `<a href="${BASE_URL}tasks/student_uploads/${sub.file_path}" class="file-link" target="_blank"><i class="fas fa-file-download"></i> ${escapeHtml(sub.original_filename)}</a>`
                        : '<span style="color:#6b7280"><i class="fas fa-ban"></i> No file attached</span>';
                    
                    const readBadge = sub.teacher_read
                        ? '<span class="read-badge read-yes"><i class="fas fa-check-circle"></i> Read</span>'
                        : `<button class="read-badge read-no" onclick="markSubmissionAsReadDirect(${sub.task_id}, ${studentId}, this)" style="border:none; cursor:pointer;"><i class="fas fa-eye"></i> Mark as Read</button>`;
                    
                    const typeIcon  = sub.task_type === 'Activities' ? 'fa-tasks' : (sub.task_type === 'Homework' ? 'fa-book' : 'fa-flask');
                    const typeColor = sub.task_type === 'Activities' ? '#3b82f6' : (sub.task_type === 'Homework' ? '#f59e0b' : '#10b981');
                    
                    const notesHtml = sub.notes && sub.notes.trim() !== '' && sub.notes !== 'undefined' && sub.notes !== 'null'
                        ? `<div class="submission-notes"><i class="fas fa-comment"></i> ${escapeHtml(sub.notes)}</div>`
                        : '';
                    
                    html += `
                        <div class="student-submission">
                            <div class="submission-icon" style="background: ${typeColor}20; color: ${typeColor}">
                                <i class="fas ${typeIcon}"></i>
                            </div>
                            <div class="submission-details">
                                <div class="submission-title">${escapeHtml(sub.task_title)} <span class="submission-type" style="color: ${typeColor}">(${sub.task_type})</span></div>
                                <div class="submission-meta">
                                    <i class="fas fa-calendar-alt"></i> Submitted: ${sub.submitted_at}
                                </div>
                                <div class="submission-file">${fileLink}</div>
                                ${notesHtml}
                            </div>
                            <div class="submission-status">
                                ${readBadge}
                            </div>
                        </div>
                    `;
                });
                html += '    </div></div>';
            });
            modalBody.innerHTML = html;
        } else {
            modalBody.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-inbox empty-icon"></i>
                    <h3>No submissions yet</h3>
                    <p>Students have not submitted any work for this subject.</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
        modalBody.innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444"><i class="fas fa-exclamation-triangle" style="font-size:2em"></i><p>Error loading submissions.</p></div>';
    }
}

function markSubmissionAsReadDirect(taskId, studentId, element) {
    if (!taskId || !studentId || taskId <= 0 || studentId <= 0) {
        showNotification('Invalid task or student ID: ' + taskId + ', ' + studentId, 'error');
        return;
    }
    
    element.disabled = true;
    var originalText = element.innerHTML;
    element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    fetch(BASE_URL + 'tasks/mark_submission_read.php?task_id=' + taskId + '&student_id=' + studentId)
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            element.innerHTML = '<i class="fas fa-check-circle"></i> Read';
            element.classList.remove('read-no');
            element.classList.add('read-yes');
            element.disabled = false;
            element.onclick = null;
            showNotification('Submission marked as read', 'success');
            if (currentSubjectId) refreshBellCount(currentSubjectId);
            setTimeout(function() {
                if (currentSubjectId) loadSubmissions(currentSubjectId);
            }, 1000);
        } else {
            element.disabled = false;
            element.innerHTML = originalText;
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(function(error) {
        element.disabled = false;
        element.innerHTML = originalText;
        showNotification('Error marking submission as read: ' + error, 'error');
    });
}

async function refreshBellCount(subjectId) {
    try {
        const response = await fetch(`${BASE_URL}tasks/get_unread_count.php?subject_id=${subjectId}`);
        const data = await response.json();
        
        if (data.success) {
            const bellIcon = document.querySelector(`.notification-icon[data-subject-id="${subjectId}"]`);
            if (bellIcon) {
                const existingCount = bellIcon.querySelector('.notification-count');
                if (data.count > 0) {
                    if (existingCount) {
                        existingCount.textContent = data.count;
                    } else {
                        const newCountSpan = document.createElement('span');
                        newCountSpan.className = 'notification-count';
                        newCountSpan.textContent = data.count;
                        bellIcon.appendChild(newCountSpan);
                    }
                } else if (existingCount) {
                    existingCount.remove();
                }
            }
        }
    } catch (error) {
        console.error('Error refreshing count:', error);
    }
}

function showNotification(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = type === 'error'
        ? `<i class="fas fa-exclamation-circle"></i> ${message}`
        : `<i class="fas fa-check-circle"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.style.transform = 'translateX(0)', 100);
    setTimeout(() => {
        toast.style.transform = 'translateX(400px)';
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Delete task modal
let deleteTaskUrl = null;
const deleteModal    = document.getElementById('deleteTaskModal');
const confirmDeleteBtn = document.getElementById('confirmDeleteTask');
const cancelDeleteBtn  = document.getElementById('cancelDeleteTask');
const closeDeleteBtn   = deleteModal ? deleteModal.querySelector('.close-modal') : null;

document.addEventListener('click', function(e) {
    const deleteButton = e.target.closest('.action-btn.delete');
    if (deleteButton) {
        e.preventDefault();
        const link = deleteButton.closest('a');
        deleteTaskUrl = link ? link.href : null;
        if (deleteModal) deleteModal.classList.add('show');
    }
});

if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', function() {
        if (deleteTaskUrl) window.location.href = deleteTaskUrl;
    });
}

if (cancelDeleteBtn) {
    cancelDeleteBtn.addEventListener('click', function() {
        if (deleteModal) deleteModal.classList.remove('show');
        deleteTaskUrl = null;
    });
}

if (closeDeleteBtn) {
    closeDeleteBtn.addEventListener('click', function() {
        if (deleteModal) deleteModal.classList.remove('show');
        deleteTaskUrl = null;
    });
}

if (deleteModal) {
    deleteModal.addEventListener('click', function(event) {
        if (event.target === deleteModal) {
            deleteModal.classList.remove('show');
            deleteTaskUrl = null;
        }
    });
}
</script>
</body>
</html>