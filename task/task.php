    <?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => false,
    'cookie_samesite' => 'Strict'
]);

include_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../config/teacher_filter.php';

if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../Accesspage/teacher_login.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$selected_course = $_SESSION['teacher_course'] ?? '';

// Check if user is admin - admin cannot access task page
$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

if ($is_admin) {
    echo "<div style='text-align:center; margin-top:50px; font-family:Arial,sans-serif;'>";
    echo "<h2 style='color:#dc3545;'><i class='fas fa-exclamation-triangle'></i> Access Denied</h2>";
    echo "<p>Task Management is only available for regular teachers.</p>";
    echo "<p>Admin users cannot access this page.</p>";
    echo "<a href='../teachersportal/dashboard.php' style='display:inline-block; margin-top:20px; padding:10px 20px; background:#0A91AB; color:white; text-decoration:none; border-radius:5px;'>Go Back to Dashboard</a>";
    echo "</div>";
    exit();
}

$course_id = null;
if (!empty($selected_course)) {
    $course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$selected_course'");
    if ($course_result && mysqli_num_rows($course_result) > 0) {
        $course_row = mysqli_fetch_assoc($course_result);
        $course_id = $course_row['id'];
    }
}

$subjects = [];
if ($course_id) {
$subjects_query = mysqli_query($conn, "SELECT * FROM subjects WHERE course_id = '$course_id'" . getTeacherFilter() . " ORDER BY year_level ASC, subject_name ASC");
    if ($subjects_query) {
        while ($row = mysqli_fetch_assoc($subjects_query)) {
            $subjects[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager | <?= htmlspecialchars($selected_course) ?></title>
<link rel="stylesheet" href="../css/teacherportal.css">
    <style>
        /* Submission Notification Styles */
        .submission-badge {
            background: #ef4444;
            color: white;
            border-radius: 12px;
            padding: 4px 8px;
            font-size: 0.75rem;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-left: 10px;
            animation: pulse 2s infinite;
        }
        
        .task-list-item.has-submissions {
            border-left: 4px solid #ef4444;
            position: relative;
        }
        
        .task-list-item.has-submissions::before {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
            animation: bounce 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .task-section-info {
            display: flex;
            gap: 6px;
            margin: 4px 0;
            flex-wrap: wrap;
        }
        
        .task-section-info .year-badge,
        .task-section-info .section-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            background: var(--slate-200);
            color: var(--slate-700);
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>
<body>
    <?php 
    $back_url = "../Accesspage/teacher_login.php";
    $admin_types = ['Seeder', 'Administrator'];
    if (isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types)) {
        $back_url = "../teachersportal/chooseSub.php";
    }
    include '../teachersportal/sidebar.php'; 
    ?>
    
    <div style="position: fixed; top: 15px; right: 20px; z-index: 9999;">
        <img src="../images/task.jpg.png" alt="Logo" style="width: 50px; border-radius: 5px;">
    </div>

    <div class="content">
        <h1><i class="fas fa-tasks"></i> Task Management</h1>
        <p>Select a subject to manage its activities, homework, and laboratory tasks</p>
            
        <?php if (empty($subjects)): ?>
            <div class="no-subjects-message">
                <i class="fas fa-book-open"></i>
                <h3>No Subjects Found</h3>
                <p>You don't have any subjects assigned to this course.</p>
            </div>
        <?php else: ?>
            <div class="subjects-grid">
                <?php foreach ($subjects as $subject): ?>
                    <div class="subject-card" id="subject-card-<?= $subject['id'] ?>">
                        <div class="subject-card-header">
                            <h3><i class="fas fa-book"></i> <?= htmlspecialchars($subject['subject_name']) ?></h3>
                            <span class="year-badge"><?= htmlspecialchars($subject['year_level']) ?></span>
                            <span class="section-badge"><?= htmlspecialchars($subject['section']) ?></span>
                        </div>
                        <div class="subject-card-body">
                            <p><?= htmlspecialchars($subject['description'] ?? 'No description available') ?></p>
                        </div>
                        <div class="subject-card-footer">
                            <button type="button" class="view-tasks-btn" onclick="openTaskModal(<?= $subject['id'] ?>, '<?= htmlspecialchars($subject['subject_name']) ?>', '<?= htmlspecialchars($subject['year_level']) ?>', '<?= htmlspecialchars($subject['section']) ?>', this)">
                                <i class="fas fa-tasks"></i> Manage Tasks
                            </button>
                        </div>
                        
                        <!-- Embedded Task Modal for this subject -->
                        <div id="taskModal-<?= $subject['id'] ?>" class="subject-task-modal">
                            <div class="subject-task-modal-content">
                                <div class="subject-task-modal-header">
                                    <h2 id="taskModalTitle-<?= $subject['id'] ?>"><i class="fas fa-book"></i> <?= htmlspecialchars($subject['subject_name']) ?> - <?= htmlspecialchars($subject['year_level']) ?> Section <?= htmlspecialchars($subject['section']) ?> | Tasks</h2>
                                    <button type="button" class="subject-task-modal-close" onclick="closeTaskModal(<?= $subject['id'] ?>)">&times;</button>
                                </div>
                                <div class="subject-task-modal-body">
                                    <!-- Activities Section -->
                                    <div class="task-type-section">
                                        <div class="task-type-header" onclick="toggleTaskSection(<?= $subject['id'] ?>, 'activities')">
                                            <h4><i class="fas fa-running"></i> Activities</h4>
                                            <i class="fas fa-chevron-down toggle-icon"></i>
                                        </div>
                                        <div class="task-type-content" id="section-<?= $subject['id'] ?>-activities">
                                            <div style="display: flex; justify-content: flex-end; margin-bottom: 12px; gap: 8px;">
                                                <button type="button" class="add-task-btn" onclick="openAddTaskForm(<?= $subject['id'] ?>, 'activities')">
                                                    <i class="fas fa-plus"></i> Add Activity
                                                </button>
                                                <button type="button" class="add-task-btn" style="background: var(--accent-emerald);" onclick="viewSubmissions(<?= $subject['id'] ?>, 'activities')">
                                                    <i class="fas fa-users"></i> View Students Submissions
                                                </button>
                                            </div>
                                            <div id="tasks-list-<?= $subject['id'] ?>-activities"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Homework Section -->
                                    <div class="task-type-section">
                                        <div class="task-type-header" onclick="toggleTaskSection(<?= $subject['id'] ?>, 'homework')">
                                            <h4><i class="fas fa-book"></i> Homework</h4>
                                            <i class="fas fa-chevron-down toggle-icon"></i>
                                        </div>
                                        <div class="task-type-content" id="section-<?= $subject['id'] ?>-homework">
    <div style="display: flex; justify-content: flex-end; margin-bottom: 12px; gap: 8px;">
        <button type="button" class="add-task-btn" onclick="openAddTaskForm(<?= $subject['id'] ?>, 'homework')">
            <i class="fas fa-plus"></i> Add Homework
        </button>
        <button type="button" class="add-task-btn" style="background: var(--accent-emerald);" onclick="viewSubmissions(<?= $subject['id'] ?>, 'homework')">
            <i class="fas fa-users"></i> View Students Submissions
        </button>
    </div>
                                            <div id="tasks-list-<?= $subject['id'] ?>-homework"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Laboratory Section -->
                                    <div class="task-type-section">
                                        <div class="task-type-header" onclick="toggleTaskSection(<?= $subject['id'] ?>, 'laboratory')">
                                            <h4><i class="fas fa-flask"></i> Laboratory</h4>
                                            <i class="fas fa-chevron-down toggle-icon"></i>
                                        </div>
                                        <div class="task-type-content" id="section-<?= $subject['id'] ?>-laboratory">
    <div style="display: flex; justify-content: flex-end; margin-bottom: 12px; gap: 8px;">
        <button type="button" class="add-task-btn" onclick="openAddTaskForm(<?= $subject['id'] ?>, 'laboratory')">
            <i class="fas fa-plus"></i> Add Lab
        </button>
        <button type="button" class="add-task-btn" style="background: var(--accent-emerald);" onclick="viewSubmissions(<?= $subject['id'] ?>, 'laboratory')">
            <i class="fas fa-users"></i> View StudentsSubmissions
        </button>
    </div>
                                            <div id="tasks-list-<?= $subject['id'] ?>-laboratory"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Add/Edit Task Form Modal (embedded in each card) -->
                        <div id="taskFormModal-<?= $subject['id'] ?>" class="subject-form-modal">
                            <div class="subject-form-modal-content">
                                <span class="subject-form-modal-close" onclick="closeTaskFormModal(<?= $subject['id'] ?>)">&times;</span>
                                <h2 id="taskFormTitle-<?= $subject['id'] ?>">Add New Task</h2>
                                <form id="taskForm-<?= $subject['id'] ?>">
                                    <input type="hidden" id="formTaskType-<?= $subject['id'] ?>">
                                    <input type="hidden" id="formSubjectId-<?= $subject['id'] ?>" value="<?= $subject['id'] ?>">
                                    <input type="hidden" id="formTaskId-<?= $subject['id'] ?>">
                                    <div class="form-group">
                                        <label>Subject:</label>
                                        <input type="text" id="formSubjectName-<?= $subject['id'] ?>" value="<?= htmlspecialchars($subject['subject_name']) ?>" readonly style="background: var(--slate-100);">
                                    </div>
                                    <div class="form-group">
                                        <label>Task Title:</label>
                                        <input type="text" id="formTaskTitleInput-<?= $subject['id'] ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Description:</label>
                                        <textarea id="formTaskDescription-<?= $subject['id'] ?>" rows="4" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Attachment (optional):</label>
                                        <input type="file" id="formTaskAttachment-<?= $subject['id'] ?>" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip">
                                    </div>
                                    <div class="form-group">
                                        <label>Due Date (optional):</label>
                                        <input type="datetime-local" id="formDueDate-<?= $subject['id'] ?>">
                                    </div>
                                    <button type="submit" class="btn-add">SUBMIT TASK</button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- View Submissions Modal (embedded in each card) -->
                        <div id="viewSubmissionsModal-<?= $subject['id'] ?>" class="subject-form-modal" style="backdrop-filter: none; -webkit-backdrop-filter: none; background: rgba(0,0,0,0.5);">
                            <div class="subject-form-modal-content" style="max-width: 700px; transform: none !important;">
                                <span class="subject-form-modal-close" onclick="closeViewSubmissionsModal(<?= $subject['id'] ?>)">&times;</span>
                                <h2><i class="fas fa-users"></i>  Submissions</h2>
                                <div id="submissionsList-<?= $subject['id'] ?>" style="max-height: 50vh; overflow-y: auto;"></div>
                            </div>
                        </div>
                        
                        <!-- Delete Confirmation Modal (embedded in each card) -->
                        <div id="deleteModal-<?= $subject['id'] ?>" class="subject-form-modal">
                            <div class="subject-form-modal-content small">
                                <span class="subject-form-modal-close" onclick="closeDeleteModal(<?= $subject['id'] ?>)">&times;</span>
                                <h2 style="color: var(--accent-rose);"><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h2>
                                <p>Are you sure you want to delete this task?</p>
                                <p style="color: var(--text-muted); font-size: 0.9rem;">This action cannot be undone.</p>
                                <div class="delete-actions">
                                    <button type="button" onclick="closeDeleteModal(<?= $subject['id'] ?>)" class="cancel-btn">Cancel</button>
                                    <button type="button" onclick="confirmDelete(<?= $subject['id'] ?>)" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Task Modal -->
    <div id="taskModal" class="task-modal">
        <div class="task-modal-content">
            <div class="task-modal-header">
<h2 id="taskModalTitle"><i class="fas fa-book"></i> Tasks</h2>
    <!-- Note: Dynamic title set via JS -->
                <button type="button" class="task-modal-close" onclick="closeTaskModal()">&times;</button>
            </div>
            <div class="task-modal-body">
                <!-- Activities Section -->
                <div class="task-type-section">
                    <div class="task-type-header" onclick="toggleTaskSection('activities')">
                        <h4><i class="fas fa-running"></i> Activities</h4>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="task-type-content" id="section-activities">
                        <div style="display: flex; justify-content: flex-end; margin-bottom: 12px; gap: 8px;">
                            <button type="button" class="add-task-btn" onclick="openAddTaskForm('activities')">
                                <i class="fas fa-plus"></i> Add Activity
                            </button>
                            <button type="button" class="add-task-btn" style="background: var(--accent-emerald);" onclick="viewSubmissions(currentSubjectId, 'activities', event)">
                                <i class="fas fa-users"></i> View Submissions
                            </button>
                        </div>
                        <div id="tasks-list-activities"></div>
                    </div>
                </div>
                
                <!-- Homework Section -->
                <div class="task-type-section">
                    <div class="task-type-header" onclick="toggleTaskSection('homework')">
                        <h4><i class="fas fa-book"></i> Homework</h4>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="task-type-content" id="section-homework">
<div style="display: flex; justify-content: flex-end; margin-bottom: 12px; gap: 8px;">
                            <button type="button" class="add-task-btn" onclick="openAddTaskForm('homework')">
                                <i class="fas fa-plus"></i> Add Homework
                            </button>
                            <button type="button" class="add-task-btn" style="background: var(--accent-emerald);" onclick="viewSubmissions(currentSubjectId, 'homework')">
                                <i class="fas fa-users"></i> View Submissions
                            </button>
                        </div>
                        <div id="tasks-list-homework"></div>
                    </div>
                </div>
                
                <!-- Laboratory Section -->
                <div class="task-type-section">
                    <div class="task-type-header" onclick="toggleTaskSection('laboratory')">
                        <h4><i class="fas fa-flask"></i> Laboratory</h4>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="task-type-content" id="section-laboratory">
                        <div style="display: flex; justify-content: flex-end; margin-bottom: 12px; gap: 8px;">
                            <button type="button" class="add-task-btn" onclick="openAddTaskForm('laboratory')">
                                <i class="fas fa-plus"></i> Add Lab
                            </button>
                            <button type="button" class="add-task-btn" style="background: var(--accent-emerald);" onclick="viewSubmissions(currentSubjectId, 'laboratory')">
                                <i class="fas fa-users"></i> View Submissions
                            </button>
                        </div>
                        <div id="tasks-list-laboratory"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Task Form Modal -->
    <div id="taskFormModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeTaskFormModal()">&times;</span>
            <h2 id="taskFormTitle">Add New Task</h2>
            <form id="taskForm">
                <input type="hidden" id="formTaskType">
                <input type="hidden" id="formSubjectId">
                <input type="hidden" id="formTaskId">
                <div class="form-group">
                    <label>Subject:</label>
                    <input type="text" id="formSubjectName" readonly style="background: var(--slate-100);">
                </div>
                <div class="form-group">
                    <label>Task Title:</label>
                    <input type="text" id="formTaskTitleInput" required>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <textarea id="formTaskDescription" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label>Attachment (optional):</label>
                    <input type="file" id="formTaskAttachment" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.zip">
                </div>
                <div class="form-group">
                    <label>Due Date (optional):</label>
                    <input type="datetime-local" id="formDueDate">
                </div>
                <button type="submit" class="btn-add">SUBMIT TASK</button>
            </form>
        </div>
    </div>

    <!-- View Submissions Modal -->
    <div id="viewSubmissionsModal" class="modal" style="backdrop-filter: none; -webkit-backdrop-filter: none; background: rgba(15, 23, 42, 0.6);">
        <div class="modal-content" style="max-width: 800px; max-height: 80vh; transform: none !important;">
            <span class="close-modal" onclick="closeViewSubmissionsModal()">&times;</span>
            <h2><i class="fas fa-users"></i>Students View Submissions</h2>
            <div id="submissionsList" style="max-height: 60vh; overflow-y: auto;"></div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content small">
            <span class="close-modal" onclick="closeDeleteModal()">&times;</span>
            <h2 style="color: var(--accent-rose);"><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h2>
            <p>Are you sure you want to delete this task?</p>
            <p style="color: var(--text-muted); font-size: 0.9rem;">This action cannot be undone.</p>
            <div class="delete-actions">
                <button type="button" onclick="closeDeleteModal()" class="cancel-btn">Cancel</button>
                <button type="button" onclick="confirmDelete()" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>
    </div>

    <script>
    // Move functions to top to prevent "not defined" on onclick
    function openTaskModal(subjectId, subjectName, yearLevel, section) {
        currentSubjectId = subjectId;
        currentSubjectName = subjectName;
        document.getElementById('taskModalTitle').innerHTML = '<i class="fas fa-book"></i> ' + subjectName + ' - ' + yearLevel + ' Section ' + section + ' | Tasks';
        document.getElementById('taskModal').classList.add('show');
        
        resetTaskSections();
        
        loadTasksForSubject('activities');
        loadTasksForSubject('homework');
        loadTasksForSubject('laboratory');
    }
    
    function closeTaskModal() {
        document.getElementById('taskModal').classList.remove('show');
        currentSubjectId = null;
        currentSubjectName = '';
    }
    
    var currentSubjectId = null;

    var currentSubjectName = '';
    var deleteTaskId = null;
    var subjectIds = <?php echo json_encode(array_column($subjects, 'id')); ?>;
    
    function openTaskModal(subjectId, subjectName, yearLevel, section) {
        currentSubjectId = subjectId;
        currentSubjectName = subjectName;
        document.getElementById('taskModalTitle').innerHTML = '<i class="fas fa-book"></i> ' + subjectName + ' - ' + yearLevel + ' Section ' + section + ' | Tasks';
        document.getElementById('taskModal').classList.add('show');
        
        resetTaskSections();
        
        loadTasksForSubject('activities');
        loadTasksForSubject('homework');
        loadTasksForSubject('laboratory');
    }
    
    function closeTaskModal() {
        document.getElementById('taskModal').classList.remove('show');
        currentSubjectId = null;
        currentSubjectName = '';
    }
    
    function toggleTaskSection(type) {
        var header = document.querySelector('#section-' + type).previousElementSibling;
        var content = document.getElementById('section-' + type);
        
        header.classList.toggle('collapsed');
        content.classList.toggle('collapsed');
    }
    
    function resetTaskSections() {
        // Close all task type sections by adding 'collapsed' class to all task-type-content and task-type-header
        document.querySelectorAll('.task-type-content').forEach(function(content) {
            content.classList.add('collapsed');
        });
        document.querySelectorAll('.task-type-header').forEach(function(header) {
            header.classList.add('collapsed');
        });
    }
    
    function loadTasksForSubject(taskType) {
        var container = document.getElementById('tasks-list-' + taskType);
        if (!container || !currentSubjectId) return;
        
        container.innerHTML = '<div class="no-tasks-msg"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div>';
        
        fetch('get_task.php?subject_id=' + currentSubjectId + '&type=' + taskType)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.tasks && data.tasks.length > 0) {
                    var html = '';
                    data.tasks.forEach(function(task) {
                        var date = new Date(task.created_at).toLocaleDateString();
                        var sectionInfo = (task.year_level || task.section) ? '<div class="task-section-info"><span class="year-badge">' + (task.year_level || '') + '</span><span class="section-badge">' + (task.section || '') + '</span></div>' : '';
                        
                        // Submission badge - FIXED: Declare variable
                        var submissionBadge = '';
                        if (task.unread_count > 0) {
                            submissionBadge = '<span class="submission-badge" title="' + task.unread_count + ' unread submission(s)"><i class="fas fa-bell"></i> ' + task.unread_count + '</span>';
                        }
                        
                        html += '<div class="task-list-item' + (task.unread_count > 0 ? ' has-submissions' : '') + '" data-task-id="' + task.id + '">' +

                            '<h6>' + task.title + submissionBadge + '</h6>' +
                            sectionInfo +
                            '<p class="task-desc">' + task.description + '</p>' +
                            '<div class="task-list-footer">' +
                                '<span class="task-date"><i class="fas fa-calendar"></i> ' + date + '</span>' +
                                '<div class="task-actions">' +
                                    '<button type="button" class="action-btn view-btn" onclick="viewTask(' + task.id + ')" title="View"><i class="fas fa-eye"></i></button>' +
                                    '<button type="button" class="action-btn edit-btn" onclick="editTask(' + task.id + ')" title="Edit"><i class="fas fa-edit"></i></button>' +
                                    '<button type="button" class="action-btn delete-btn" onclick="deleteTask(' + task.id + ')" title="Delete"><i class="fas fa-trash"></i></button>' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="no-tasks-msg"><i class="fas fa-clipboard"></i>No tasks yet</div>';
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                container.innerHTML = '<div class="no-tasks-msg">Error loading tasks</div>';
            });
    }
    
    function openAddTaskForm(taskType) {
        document.getElementById('formTaskType').value = taskType;
        document.getElementById('formSubjectId').value = currentSubjectId;
        document.getElementById('formSubjectName').value = currentSubjectName;
        document.getElementById('formTaskId').value = '';
        document.getElementById('formTaskTitleInput').value = '';
        document.getElementById('formTaskDescription').value = '';
        document.getElementById('taskFormTitle').innerText = 'Add New ' + taskType.charAt(0).toUpperCase() + taskType.slice(1);
        document.getElementById('taskFormModal').classList.add('show');
    }
    
    function closeTaskFormModal() {
        document.getElementById('taskFormModal').classList.remove('show');
    }
    
    document.getElementById('taskForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData();
        formData.append('taskType', document.getElementById('formTaskType').value);
        formData.append('subjectId', document.getElementById('formSubjectId').value);
        formData.append('taskTitle', document.getElementById('formTaskTitleInput').value);
        formData.append('taskDescription', document.getElementById('formTaskDescription').value);
        
        var dueDate = document.getElementById('formDueDate').value;
        if (dueDate) {
            formData.append('dueDate', dueDate);
        }
        
        var fileInput = document.getElementById('formTaskAttachment');
        if (fileInput.files.length > 0) {
            formData.append('taskAttachment', fileInput.files[0]);
        }
        
        var taskId = document.getElementById('formTaskId').value;
        if (taskId) {
            formData.append('taskId', taskId);
        }
        
        fetch('submit_task.php', { method: 'POST', body: formData })
            .then(function(response) { return response.text(); })
            .then(function(text) {
                console.log('Raw response:', text);
                try {
                    var data = JSON.parse(text);
                    if (data.success) {
                        alert('Task submitted successfully!');
                        closeTaskFormModal();
                        loadTasksForSubject(document.getElementById('formTaskType').value);
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                } catch(e) {
                    alert('Server error: ' + text.substring(0, 200));
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                alert('An error occurred. Please check console for details.');
            });
    });
    
    function viewTask(id) {
        fetch('get_task.php?id=' + id)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.task) {
                    var t = data.task;
                    alert('Title: ' + t.title + '\n\nDescription: ' + t.description + '\n\nType: ' + t.task_type);
                }
            });
    }
    
    function editTask(id) {
        fetch('get_task.php?id=' + id)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.task) {
                    var t = data.task;
                    document.getElementById('formTaskType').value = t.task_type;
                    document.getElementById('formSubjectId').value = currentSubjectId;
                    document.getElementById('formSubjectName').value = currentSubjectName;
                    document.getElementById('formTaskId').value = t.id;
                    document.getElementById('formTaskTitleInput').value = t.title;
                    document.getElementById('formTaskDescription').value = t.description;
                    document.getElementById('taskFormTitle').innerText = 'Edit ' + t.task_type.charAt(0).toUpperCase() + t.task_type.slice(1) + ' Task';
                    document.getElementById('taskFormModal').classList.add('show');
                }
            });
    }
    
    function deleteTask(id) {
        deleteTaskId = id;
        document.getElementById('deleteModal').classList.add('show');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
        deleteTaskId = null;
    }
    
    function confirmDelete() {
        if (!deleteTaskId) return;
        
        fetch('delete_task.php?id=' + deleteTaskId)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    alert('Task deleted!');
                    closeDeleteModal();
                    loadTasksForSubject('activities');
                    loadTasksForSubject('homework');
                    loadTasksForSubject('laboratory');
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
    
    function viewSubmissions(subjectId, taskType, event) {
        // Prevent event propagation to avoid modal flickering
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Always use the global modal (outside subject cards)
        var container = document.getElementById('submissionsList');
        var modal = document.getElementById('viewSubmissionsModal');
        
        if (!container) {
            console.error('Container not found: submissionsList');
            return;
        }
        
        // Check if modal is already open to prevent double-triggering
        if (modal && modal.classList.contains('show')) {
            return;
        }
        
        container.innerHTML = '<div class="no-tasks-msg"><i class="fas fa-spinner fa-spin"></i><br>Loading submissions...</div>';
        
        if (modal) {
            modal.classList.add('show');
        }
        
        var fetchUrl = 'get_submissions.php?subject_id=' + subjectId + '&task_type=' + taskType;
        
        fetch(fetchUrl)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.submissions && data.submissions.length > 0) {
                    var html = '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
                    html += '<tr style="background: var(--slate-100);">';
                    html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light); font-size: 0.85rem;">Student Name</th>';
                    html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light); font-size: 0.85rem;">Section</th>';
                    html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light); font-size: 0.85rem;">Year</th>';
                    html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light); font-size: 0.85rem;">Task</th>';
                    html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light); font-size: 0.85rem;">Submitted</th>';
                    html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light); font-size: 0.85rem;">File</th>';
                    html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light); font-size: 0.85rem;">Notes</th>';
                    html += '</tr>';
                    
                    data.submissions.forEach(function(sub) {
                        var submittedDate = new Date(sub.submitted_at).toLocaleString();
                        var filePathEscaped = sub.file_path ? sub.file_path.replace(/'/g, "\\'") : '';
                        var fileLink = sub.file_path ? '<button class="view-submission-btn" data-submission-id="' + sub.submission_id + '" data-file-path="' + filePathEscaped + '" style="color: var(--primary-blue); font-size: 0.85rem; background: none; border: none; cursor: pointer; padding: 0; text-decoration: underline;"><i class="fas fa-file"></i> View</button>' : 'N/A';
                        html += '<tr style="border-bottom: 1px solid var(--border-light);">'; 
                        html += '<td style="padding: 10px; font-size: 0.9rem;"><strong>' + (sub.student_name || 'Unknown') + '</strong></td>';
                        html += '<td style="padding: 10px; font-size: 0.85rem;">' + (sub.section || '-') + '</td>';
                        html += '<td style="padding: 10px; font-size: 0.85rem;">' + (sub.year_level || '-') + '</td>';
                        html += '<td style="padding: 10px; font-size: 0.85rem;">' + (sub.task_title || 'Unknown') + '</td>';
                        html += '<td style="padding: 10px; font-size: 0.85rem;">' + submittedDate + '</td>';
                        html += '<td style="padding: 10px;">' + fileLink + '</td>';
                        html += '<td style="padding: 10px; font-size: 0.85rem;">' + (sub.notes || '-') + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</table>';
                    html += '<div style="margin-top: 15px; padding: 10px; background: var(--slate-50); border-radius: 6px; font-size: 0.85rem; color: var(--text-secondary);">';
                    html += '<i class="fas fa-info-circle"></i> Total Submissions: <strong>' + data.total_submissions + '</strong>';
                    html += '</div>';
                    container.innerHTML = html;
                    
                    // Add event delegation for submission buttons
                    container.querySelector('table').addEventListener('click', function(e) {
                        if (e.target.closest('.view-submission-btn')) {
                            const btn = e.target.closest('.view-submission-btn');
                            const submissionId = btn.dataset.submissionId;
                            const filePath = btn.dataset.filePath;
                            markAndViewSubmission(submissionId, filePath);
                        }
                    });
                } else {
                    container.innerHTML = '<div class="no-tasks-msg"><i class="fas fa-inbox"></i>No submissions yet</div>';
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                container.innerHTML = '<div class="no-tasks-msg">Error loading submissions</div>';
            });
    }
    
    function closeViewSubmissionsModal(subjectId) {
        var modal = document.getElementById('viewSubmissionsModal');
        if (modal) {
            modal.classList.remove('show');
        }
    }
    
async function markAndViewSubmission(submissionId, filePath) {
        if (!filePath || !submissionId) return;
        try {
            const formData = new FormData();
            formData.append('submission_id', submissionId);
            await fetch('mark_submission_read.php', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Mark read error:', error);
        }
        window.open('student_uploads/' + filePath, '_blank');
        // Refresh task badges
        if (currentSubjectId) {
            loadTasksForSubject('activities');
            loadTasksForSubject('homework');
            loadTasksForSubject('laboratory');
        }
    }
    
    // Close modals when clicking on the backdrop
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('task-modal')) {
            closeTaskModal();
        }
        if (event.target.id === 'viewSubmissionsModal') {
            closeViewSubmissionsModal();
        }
    });

    // ==================== REAL-TIME UPDATES ====================
    let eventSource = null;
    let pollInterval = null;

    function connectRealtimeUpdates() {
        if (eventSource) eventSource.close();
        
        eventSource = new EventSource('sse_unread.php');
        
        eventSource.onmessage = function(event) {
            const unreadData = JSON.parse(event.data);
            updateUnreadBadges(unreadData);
            checkNewSubmissions(unreadData);
        };
        
        eventSource.onerror = function() {
            console.log('SSE connection lost, starting polling fallback...');
            startPollingFallback();
        };
    }

    function updateUnreadBadges(unreadData) {
        // Update all task badges across subjects and task modals
        Object.keys(unreadData).forEach(key => {
            const [subjectId, taskType] = key.split('_');
            const badgeSelector = `[data-subject-id="${subjectId}"][data-task-type="${taskType}"] .submission-badge, .task-list-item[data-task-id] .submission-badge`;
            const taskItems = document.querySelectorAll(`#tasks-list-${subjectId}-${taskType} .task-list-item, #tasks-list-${taskType} .task-list-item`);
            
            taskItems.forEach(taskItem => {
                const taskId = taskItem.dataset.taskId;
                if (taskId) {
                    // Update specific task badge if exists, else general section badge
                    const badge = taskItem.querySelector('.submission-badge');
                    if (badge && unreadData[key] > 0) {
                        badge.textContent = ` ${unreadData[key]}`;
                        badge.title = unreadData[key] + ' unread submission(s)';
                        taskItem.classList.add('has-submissions');
                    } else if (badge) {
                        badge.remove();
                        taskItem.classList.remove('has-submissions');
                    }
                }
            });
        });
    }

    function checkNewSubmissions(unreadData) {
        // Show notification for new submissions
        Object.keys(unreadData).forEach(key => {
            if (unreadData[key] > 0) {
                const [subjectId, taskType] = key.split('_');
                showNotification(`${taskType.charAt(0).toUpperCase() + taskType.slice(1)}: ${unreadData[key]} new submission(s)!`, 'success');
            }
        });
    }

    function showNotification(message, type = 'info') {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 10000;
            background: ${type === 'success' ? '#10b981' : '#3b82f6'}; color: white;
            padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            font-weight: 500; max-width: 300px; transform: translateX(400px); transition: transform 0.3s;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.style.transform = 'translateX(0)', 100);
        setTimeout(() => {
            toast.style.transform = 'translateX(400px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function startPollingFallback() {
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(() => {
            fetch('get_task.php?subject_id=' + (currentSubjectId || '') + '&type=all&unread_only=1')
                .then(r => r.json())
                .then(data => updateUnreadBadges(data.unread_summary || {}));
        }, 30000); // 30s fallback
    }

    // Auto-connect on page load
    window.addEventListener('load', connectRealtimeUpdates);
    window.addEventListener('beforeunload', () => {
        if (eventSource) eventSource.close();
        if (pollInterval) clearInterval(pollInterval);
    });

    // Reconnect when task modal opens
    document.addEventListener('click', function(e) {
        if (e.target.matches('.view-tasks-btn')) {
            setTimeout(connectRealtimeUpdates, 500);
        }
    });
    
    </script>
</body>
</html>
