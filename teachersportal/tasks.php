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
$is_admin    = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

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

if (empty($selected_course)) { echo "Course not selected."; exit(); }

$allowed_courses = ['BSIT', 'BSED', 'BAT', 'BTVTED'];
if (!in_array(strtoupper($selected_course), $allowed_courses)) { echo "<p>Invalid course.</p>"; exit(); }

$course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$selected_course'");
if (!$course_result || mysqli_num_rows($course_result) == 0) { die("Course not found in DB."); }
$course_row = mysqli_fetch_assoc($course_result);
$course_id  = $course_row['id'];

// ── Summary stats ──────────────────────────────────────────────
$stats_sql = "SELECT
    COUNT(DISTINCT t.id)  AS total_tasks,
    COUNT(DISTINCT s.id)  AS total_subjects,
    COUNT(DISTINCT ts.id) AS total_submissions,
    SUM(CASE WHEN ts.teacher_read = 0 THEN 1 ELSE 0 END) AS unread_submissions
FROM subjects s
LEFT JOIN tasks t  ON t.subject_id = s.id AND t.teacher_id = '$teacher_id'
LEFT JOIN task_submissions ts ON ts.task_id = t.id
WHERE s.course_id = '$course_id'
$subject_year_filter
$subject_section_filter";

$stats_result = mysqli_query($conn, $stats_sql);
$stats               = $stats_result ? mysqli_fetch_assoc($stats_result) : [];
$total_tasks         = $stats['total_tasks']        ?? 0;
$total_subjects      = $stats['total_subjects']     ?? 0;
$total_submissions   = $stats['total_submissions']  ?? 0;
$unread_submissions  = $stats['unread_submissions'] ?? 0;

// ── Subjects with unread count ─────────────────────────────────
$subjects_query = mysqli_query($conn,
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

// ── Teacher full name ──────────────────────────────────────────
$teacher_query = mysqli_query($conn,
    "SELECT CONCAT(first_name,' ',IFNULL(middle_name,''),' ',last_name,' ',IFNULL(suffix,'')) AS full_name
     FROM teachers WHERE id=" . intval($teacher_id)
);
$teacher_row       = mysqli_fetch_assoc($teacher_query);
$teacher_full_name = trim($teacher_row['full_name'] ?? ($_SESSION['teacher_name'] ?? 'User'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tasks — <?= htmlspecialchars($selected_course) ?></title>
<link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/tasks.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- Top-right logo -->
<div class="top-right-logo">
    <img src="<?= asset('images/622685015_925666030131412_6886851389087569993_n.jpg') ?>"
         alt="Isabela State University seal">
</div>

<?php include PROJECT_ROOT . '/teachersportal/sidebar.php'; ?>

<!-- ── CONTENT: no .container wrapper — matches dashboard.php exactly ── -->
<div class="content">

    <!-- Page title -->
    <h1 class="dash-title">
        <i class="fas fa-tasks"></i>
        Tasks — <?= htmlspecialchars($selected_course) ?>
    </h1>

    <!-- Welcome banner — identical structure to dashboard.php -->
    <div class="dashboard-welcome">
        <div class="dashboard-welcome-icon">
            <i class="fas fa-tasks"></i>
        </div>
        <div class="dashboard-welcome-text">
            <div class="eyebrow"><span></span> <?= date('l, F j, Y') ?></div>
            <h2>
                Task Management
                <?php if ($is_admin): ?>
                    <strong>Administrator View</strong>
                <?php else: ?>
                    <strong><?= htmlspecialchars($teacher_full_name) ?></strong>
                <?php endif; ?>
            </h2>
        </div>
        <div class="welcome-course-badge">
            <i class="fas fa-graduation-cap" style="margin-right:6px;"></i>
            <?= htmlspecialchars($selected_course) ?>
        </div>
    </div>

    <!-- Stat cards — .cards already defined in teacherportal.css as auto-fit grid -->
    <div class="cards">
        <div class="card">
            <h3><i class="fas fa-list-check"></i> Created Tasks</h3>
            <h1><?= $total_tasks ?></h1>
            <p>Across all subjects</p>
        </div>
        <div class="card">
            <h3><i class="fas fa-paper-plane"></i> Total Submissions</h3>
            <h1><?= $total_submissions ?></h1>
            <p>From students</p>
        </div>
        <div class="card">
            <h3><i class="fas fa-bell"></i> Unread Submissions</h3>
            <h1 style="color: var(--accent-amber);"><?= $unread_submissions ?></h1>
            <p>Waiting for review</p>
        </div>
        <div class="card">
            <h3><i class="fas fa-book-open"></i> Active Subjects</h3>
            <h1><?= $total_subjects ?></h1>
            <p>With tasks this term</p>
        </div>
    </div>

    <!-- Quick guide -->
    <div class="section-box">
        <h2><i class="fas fa-circle-info"></i> Quick Guide</h2>
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
                <div class="guide-text"><strong>Mark as Read</strong> — clears the bell badge after reviewing</div>
            </div>
        </div>
    </div>

    <!-- Subject cards -->
    <div class="section-box">
        <div class="top-bar">
            <h2><i class="fas fa-book"></i> Your Task Subjects</h2>
        </div>

        <?php if (mysqli_num_rows($subjects_query) > 0): ?>
            <div class="subject-cards-grid">
                <?php while ($subject = mysqli_fetch_assoc($subjects_query)): ?>
                    <div class="card">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                            <div style="flex:1; min-width:0;">
                                <h3>
                                    <i class="fas fa-book-open"></i>
                                    <?= htmlspecialchars($subject['code']) ?>
                                </h3>
                                <p style="font-family:'Playfair Display',Georgia,serif; font-size:1rem; font-weight:700; color:var(--slate-800); margin:2px 0 6px; letter-spacing:-0.01em;">
                                    <?= htmlspecialchars($subject['subject_name']) ?>
                                </p>
                                <p style="margin:0 0 8px; color:var(--slate-500); font-size:0.83rem;">
                                    <?= htmlspecialchars($subject['year_level']) ?> &middot; Section <?= htmlspecialchars($subject['section']) ?>
                                </p>
                                <?php if (!empty($subject['description'])): ?>
                                    <p style="margin:0 0 8px; font-size:0.82rem; color:var(--slate-600);"><?= htmlspecialchars($subject['description']) ?></p>
                                <?php endif; ?>
                                <span class="badge <?= $subject['subject_type'] === 'MINOR' ? 'badge-minor' : 'badge-major' ?>">
                                    <?= strtoupper($subject['subject_type']) ?>
                                </span>
                            </div>

                            <!-- Bell icon -->
                            <span class="notification-icon"
                                  data-subject-id="<?= $subject['id'] ?>"
                                  data-subject-name="<?= htmlspecialchars($subject['subject_name']) ?>"
                                  title="View submissions"
                                  role="button"
                                  tabindex="0"
                                  style="margin-left:14px;">
                                <i class="fas fa-bell"></i>
                                <?php if (!empty($subject['unread_count']) && $subject['unread_count'] > 0): ?>
                                    <span class="notification-count"><?= $subject['unread_count'] ?></span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="task-buttons">
                            <a href="<?= base_url() ?>teachers_access/submit_act.php?subject_id=<?= $subject['id'] ?>" class="task-btn act">
                                <i class="fas fa-file-upload"></i> Activity
                            </a>
                            <a href="<?= base_url() ?>teachers_access/submit_hmwork.php?subject_id=<?= $subject['id'] ?>" class="task-btn hm">
                                <i class="fas fa-book"></i> Homework
                            </a>
                            <a href="<?= base_url() ?>teachers_access/submit_lab.php?subject_id=<?= $subject['id'] ?>" class="task-btn lab">
                                <i class="fas fa-flask"></i> Laboratory
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-book-open"></i></div>
                <h3>No subjects found</h3>
                <p>Add subjects first via <a href="subjects.php" style="color:var(--primary-blue);font-weight:600;">Subjects &amp; Classes</a></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Created Tasks table -->
    <div class="section-box">
        <div class="top-bar" style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:1rem; align-items:center;">
            <h2><i class="fas fa-list-check"></i> Created Tasks</h2>

            <form method="GET" class="tasks-search-form">
                <input type="text"
                       name="search"
                       placeholder="Search by title, subject, or type…"
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
                <?php if ($search !== ''): ?>
                    <a href="tasks.php" class="refresh-btn" title="Clear search">
                        <i class="fas fa-rotate-right"></i>
                    </a>
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
            $like          = "%{$search}%";
            $tasks_params  = array_merge($tasks_params, [$like, $like, $like, $like]);
            $tasks_types  .= 'ssss';
        }

        $stmt = $conn->prepare(
            "SELECT t.*, s.code, s.subject_name
             FROM tasks t
             JOIN subjects s ON t.subject_id = s.id
             $tasks_filter
             ORDER BY t.created_at DESC"
        );
        if (!$stmt) { die('DB error: ' . mysqli_error($conn)); }
        $stmt->bind_param($tasks_types, ...$tasks_params);
        $stmt->execute();
        $tasks_query = $stmt->get_result();
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
                    <?php while ($task = $tasks_query->fetch_assoc()):
                        $type_class = $task['task_type'] === 'activities' ? 'badge-act'
                                    : ($task['task_type'] === 'homework'  ? 'badge-hm' : 'badge-lab');

                        $task_file  = $task['attachment']
                            ? '<a href="' . BASE_URL . 'tasks/uploads/' . htmlspecialchars($task['attachment']) . '" target="_blank" class="task-file-link">
                                   <i class="fas fa-file"></i> ' . htmlspecialchars($task['original_filename'] ?? basename($task['attachment'])) . '
                               </a>'
                            : '<span class="no-file">No file</span>';

                        $created = date('M j, Y', strtotime($task['created_at']));
                        $due     = $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'No deadline';
                    ?>
                        <tr>
                            <td data-label="Subject"><?= htmlspecialchars($task['code']) ?> — <?= htmlspecialchars($task['subject_name']) ?></td>
                            <td data-label="Type">
                                <span class="badge <?= $type_class ?>"><?= ucfirst(htmlspecialchars($task['task_type'])) ?></span>
                            </td>
                            <td data-label="Title" style="font-weight:600; color:var(--slate-800);"><?= htmlspecialchars($task['title']) ?></td>
                            <td data-label="Description"><?= strlen($task['description']) > 50 ? substr(htmlspecialchars($task['description']), 0, 50) . '…' : htmlspecialchars($task['description']) ?></td>
                            <td data-label="File"><?= $task_file ?></td>
                            <td data-label="Created"><?= $created ?></td>
                            <td data-label="Due"><?= $due ?></td>
                            <td data-label="Action">
                                <div class="action-buttons" style="display:flex; gap:8px;">
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
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-clipboard-list"></i></div>
                                <h3>No tasks yet</h3>
                                <p>Create tasks using the subject cards above.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif;
                $stmt->close(); ?>
                </tbody>
            </table>
        </div>
    </div><!-- /.section-box created tasks -->

</div><!-- /.content -->


<!-- ── Submissions Modal ── -->
<div id="submissionsModal" class="submissions-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-bell"></i> Submissions</h2>
            <button class="close" id="closeModal" aria-label="Close">&times;</button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<!-- ── Delete Task Modal ── -->
<div id="deleteTaskModal" class="modal">
    <div class="modal-content small">
        <span class="close-modal">&times;</span>
        <h2>
            <i class="fas fa-exclamation-triangle" style="color:var(--accent-rose);"></i>
            Confirm Delete
        </h2>
        <p>Are you sure you want to permanently delete this task? This action cannot be undone.</p>
        <div class="modal-actions" style="justify-content:center; margin-top:20px;">
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
document.addEventListener('DOMContentLoaded', function () {
    const modal      = document.getElementById('submissionsModal');
    const closeBtn   = document.getElementById('closeModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody  = document.getElementById('modalBody');

    const closeSubmModal = () => {
        modal.classList.remove('show');
        modalBody.innerHTML = '';
        if (currentSubjectId) refreshBellCount(currentSubjectId);
    };

    document.querySelectorAll('.notification-icon').forEach(bell => {
        ['click','keydown'].forEach(evt => {
            bell.addEventListener(evt, function (e) {
                if (evt === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault(); e.stopPropagation();
                const sid  = this.dataset.subjectId;
                const name = this.dataset.subjectName;
                if (!sid) { showNotification('Invalid subject', 'error'); return; }
                currentSubjectId = sid;
                modalTitle.innerHTML = `<i class="fas fa-bell"></i> Submissions — ${escapeHtml(name)}`;
                modal.classList.add('show');
                loadSubmissions(sid);
            });
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeSubmModal);
    window.addEventListener('click',   e => { if (e.target === modal) closeSubmModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSubmModal(); });
});

async function loadSubmissions(subjectId) {
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = `
        <div style="text-align:center;padding:48px 20px">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--slate-400)"></i>
            <p style="margin-top:12px;color:var(--slate-500)">Loading submissions…</p>
        </div>`;

    try {
        const res  = await fetch(`${BASE_URL}tasks/get_subject_submissions.php?subject_id=${subjectId}`);
        const data = await res.json();

        if (data.success && data.submissions?.length) {
            let html = '';
            data.submissions.forEach(student => {
                html += `
                <div class="student-group">
                    <div class="student-header">
                        <i class="fas fa-user-graduate"></i>
                        ${escapeHtml(student.student_name)}
                        &middot; Year ${student.year_level}
                        &middot; Section ${escapeHtml(student.section)}
                    </div>
                    <div class="student-submissions">`;

                student.submissions.forEach(sub => {
                    const validFile = sub.file_path && !['undefined','null',''].includes(sub.file_path);
                    const fileLink  = validFile
                        ? `<a href="${BASE_URL}tasks/student_uploads/${sub.file_path}" class="file-link" target="_blank">
                               <i class="fas fa-file-download"></i> ${escapeHtml(sub.original_filename)}
                           </a>`
                        : `<span style="color:var(--slate-400)"><i class="fas fa-ban"></i> No file attached</span>`;

                    const readBadge = sub.teacher_read
                        ? `<span class="read-badge read-yes"><i class="fas fa-check-circle"></i> Read</span>`
                        : `<button class="read-badge read-no" onclick="markAsRead(${sub.task_id},${student.student_id},this)">
                               <i class="fas fa-eye"></i> Mark as Read
                           </button>`;

                    const icon  = sub.task_type === 'Activities' ? 'fa-tasks' : (sub.task_type === 'Homework' ? 'fa-book' : 'fa-flask');
                    const color = sub.task_type === 'Activities' ? 'var(--primary-blue)' : (sub.task_type === 'Homework' ? 'var(--accent-amber)' : 'var(--accent-emerald)');
                    const notes = (sub.notes?.trim() && !['undefined','null'].includes(sub.notes))
                        ? `<div class="submission-notes"><i class="fas fa-comment"></i> ${escapeHtml(sub.notes)}</div>` : '';

                    html += `
                    <div class="student-submission">
                        <div class="submission-icon" style="background:${color}20;color:${color}">
                            <i class="fas ${icon}"></i>
                        </div>
                        <div class="submission-details">
                            <div class="submission-title">
                                ${escapeHtml(sub.task_title)}
                                <span class="submission-type" style="color:${color}">(${sub.task_type})</span>
                            </div>
                            <div class="submission-meta"><i class="fas fa-calendar-alt"></i> ${sub.submitted_at}</div>
                            <div>${fileLink}</div>
                            ${notes}
                        </div>
                        <div class="submission-status">${readBadge}</div>
                    </div>`;
                });
                html += '</div></div>';
            });
            modalBody.innerHTML = html;
        } else {
            modalBody.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                <h3>No submissions yet</h3>
                <p>Students have not submitted any work for this subject.</p>
            </div>`;
        }
    } catch (err) {
        modalBody.innerHTML = `
        <div class="empty-state" style="color:var(--accent-rose)">
            <div class="empty-state-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h3>Error loading submissions</h3>
        </div>`;
    }
}

function markAsRead(taskId, studentId, el) {
    el.disabled = true;
    const orig  = el.innerHTML;
    el.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';

    fetch(`${BASE_URL}tasks/mark_submission_read.php?task_id=${taskId}&student_id=${studentId}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                el.outerHTML = '<span class="read-badge read-yes"><i class="fas fa-check-circle"></i> Read</span>';
                showNotification('Marked as read', 'success');
                if (currentSubjectId) refreshBellCount(currentSubjectId);
                setTimeout(() => { if (currentSubjectId) loadSubmissions(currentSubjectId); }, 1000);
            } else {
                el.disabled  = false;
                el.innerHTML = orig;
                showNotification('Error: ' + d.message, 'error');
            }
        })
        .catch(() => { el.disabled = false; el.innerHTML = orig; showNotification('Request failed', 'error'); });
}

async function refreshBellCount(subjectId) {
    try {
        const res  = await fetch(`${BASE_URL}tasks/get_unread_count.php?subject_id=${subjectId}`);
        const data = await res.json();
        if (!data.success) return;
        const bell     = document.querySelector(`.notification-icon[data-subject-id="${subjectId}"]`);
        if (!bell) return;
        const existing = bell.querySelector('.notification-count');
        if (data.count > 0) {
            if (existing) { existing.textContent = data.count; }
            else {
                const sp = document.createElement('span');
                sp.className   = 'notification-count';
                sp.textContent = data.count;
                bell.appendChild(sp);
            }
        } else { existing?.remove(); }
    } catch (e) {}
}

function showNotification(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = `toast-notification toast-${type}`;
    t.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => (t.style.transform = 'translateX(0)'), 100);
    setTimeout(() => { t.style.transform = 'translateX(420px)'; setTimeout(() => t.remove(), 400); }, 3000);
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g,
        m => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' })[m]);
}

/* ── Delete modal ── */
let deleteUrl = null;
const delModal  = document.getElementById('deleteTaskModal');
const confirmEl = document.getElementById('confirmDeleteTask');
const cancelEl  = document.getElementById('cancelDeleteTask');
const closeEl   = delModal?.querySelector('.close-modal');

const openDel  = url => { deleteUrl = url; delModal?.classList.add('show'); };
const closeDel = ()  => { deleteUrl = null; delModal?.classList.remove('show'); };

document.addEventListener('click', e => {
    const btn = e.target.closest('.action-btn.delete');
    if (btn) { e.preventDefault(); openDel(btn.closest('a')?.href); }
});
confirmEl?.addEventListener('click', () => { if (deleteUrl) location.href = deleteUrl; });
cancelEl?.addEventListener('click',  closeDel);
closeEl?.addEventListener('click',   closeDel);
delModal?.addEventListener('click',  e => { if (e.target === delModal) closeDel(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && delModal?.classList.contains('show')) closeDel(); });
</script>
</body>
</html>