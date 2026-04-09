<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once CONFIG_PATH . 'database.php';
require_once CONFIG_PATH . 'teacher_filter.php';

// ================== CHECK LOGIN ==================
if(!isset($_SESSION['teacher_id'])){
   header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
exit();
}

// ================== DYNAMIC BACK ARROW LOGIC ==================
$back_url = BASE_URL . "Accesspage/teacher_login.php";

if(isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], ['Seeder','Administrator'])){
    $back_url = BASE_URL . "teachersportal/chooseSub.php";
}

// ================== SET COURSE FROM SESSION ==================
$selected_course = $_SESSION['teacher_course'] ?? '';
if(empty($selected_course)){
    echo "Course not assigned to this teacher. Contact admin.";
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

$allowed_courses = ['BSIT','BSED','BAT','BTVTED'];
if(!in_array(strtoupper($selected_course), $allowed_courses)){
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="' . BASE_URL . 'teachersportal/chooseSub.php">← Go Back</a>';
    exit();
}

// ================== CHECK IF USER IS ADMIN ==================
$admin_types = ['Seeder','Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

// ================== ADD / EDIT ANNOUNCEMENT ==================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_announcement'])){
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $year_level = mysqli_real_escape_string($conn, $_POST['year_level']);
    $section = mysqli_real_escape_string($conn, $_POST['section']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'medium');
    $post_date = !empty($_POST['announcement_date']) ? $_POST['announcement_date'] : date('Y-m-d H:i:s');

    $course_db = $selected_course;

    if(!empty($_POST['announcement_id'])){
        $id = intval($_POST['announcement_id']);
        if ($is_admin) {
            // Admin can edit any announcement
            $sql = "UPDATE announcements 
                    SET title='$title', content='$content', year_level='$year_level', section='$section', priority='$priority', created_at='$post_date'
                    WHERE id='$id'";
        } else {
            // Regular teacher can only edit their OWN announcements
            $sql = "UPDATE announcements 
                    SET title='$title', content='$content', year_level='$year_level', section='$section', priority='$priority', created_at='$post_date'
                    WHERE id='$id' AND teacher_id='$teacher_id'";
        }
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_affected_rows($conn) > 0) {
            $_SESSION['success_message'] = 'Announcement updated successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update announcement: ' . mysqli_error($conn);
        }
    } else {
        $sql = "INSERT INTO announcements (teacher_id, course_id, title, content, year_level, section, priority, created_at)
                VALUES ('$teacher_id', '$course_db', '$title', '$content', '$year_level', '$section', '$priority', '$post_date')";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_affected_rows($conn) > 0) {
            $_SESSION['success_message'] = 'Announcement added successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to add announcement: ' . mysqli_error($conn);
        }
    }

    header("Location: " . BASE_URL . "teachersportal/announcements.php");
    exit();
}

// ================== DELETE ==================
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    if ($is_admin) {
        // Admin can delete any announcement
        $result = mysqli_query($conn, "DELETE FROM announcements WHERE id='$id'");
        if ($result && mysqli_affected_rows($conn) > 0) {
            $_SESSION['success_message'] = 'Announcement deleted successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete announcement or no permission.';
        }
    } else {
        // Regular teacher can only delete their OWN announcements
        $result = mysqli_query($conn, "DELETE FROM announcements WHERE id='$id' AND teacher_id='$teacher_id'");
        if ($result && mysqli_affected_rows($conn) > 0) {
            $_SESSION['success_message'] = 'Announcement deleted successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete announcement or no permission.';
        }
    }
    header("Location: " . BASE_URL . "teachersportal/announcements.php");
    exit();
}

// ================== PIN/UNPIN ==================
if(isset($_GET['toggle_pin'])){
    $id = intval($_GET['toggle_pin']);
    if ($is_admin) {
        // Admin can toggle pin for any announcement
        $res = mysqli_query($conn, "SELECT pinned FROM announcements WHERE id='$id'");
        $data = mysqli_fetch_assoc($res);
        $newPin = $data['pinned'] ? 0 : 1;
        mysqli_query($conn, "UPDATE announcements SET pinned='$newPin' WHERE id='$id'");
    } else {
        // Regular teacher can only toggle pin for their OWN announcements
        $res = mysqli_query($conn, "SELECT pinned FROM announcements WHERE id='$id' AND teacher_id='$teacher_id'");
        $data = mysqli_fetch_assoc($res);
        $newPin = $data ? ($data['pinned'] ? 0 : 1) : 0;
        mysqli_query($conn, "UPDATE announcements SET pinned='$newPin' WHERE id='$id' AND teacher_id='$teacher_id'");
    }
    echo json_encode(['success'=>true, 'pinned'=>$newPin]);
    exit();
}

// ================== FETCH ANNOUNCEMENTS ==================
// Admin sees ALL announcements.
// Regular teacher sees ONLY their own announcements.

$teacher_year_filter    = '';
$teacher_section_filter = '';
if (!$is_admin) {
    $y_params = []; $y_types = '';
    $teacher_year_filter = getYearLevelFilter('year_level', $y_params, $y_types);
    $s_params = []; $s_types = '';
    $teacher_section_filter = getSectionFilter('section', $s_params, $s_types);
}

if ($is_admin) {
    // Admin: see everything, pinned
    $pinnedQuery = mysqli_query($conn, "
        SELECT a.*, 
               CONCAT(t.first_name,' ', IFNULL(t.middle_name,''),' ', t.last_name,' ', IFNULL(t.suffix,'')) AS teacher_name,
               a.course_id AS announcement_course
        FROM announcements a
        JOIN teachers t ON a.teacher_id = t.id
        WHERE a.course_id = '$selected_course' 
          AND t.teacher_type IN ('Seeder','Administrator')
          AND a.pinned = 1
        ORDER BY a.created_at DESC
    ");

    // Admin: see everything, recent
    $recentQuery = mysqli_query($conn, "
        SELECT a.*, 
               CONCAT(t.first_name,' ', IFNULL(t.middle_name,''),' ', t.last_name,' ', IFNULL(t.suffix,'')) AS teacher_name,
               a.course_id AS announcement_course
        FROM announcements a
        JOIN teachers t ON a.teacher_id = t.id
        WHERE a.course_id = '$selected_course' 
          AND t.teacher_type IN ('Seeder','Administrator')
          AND a.pinned = 0
        ORDER BY a.created_at DESC
    ");
} else {
    // Regular teacher: ONLY their own announcements (teacher_id filter added)
    $pinnedQuery = mysqli_query($conn, "
        SELECT a.*, 
               CONCAT(t.first_name,' ', IFNULL(t.middle_name,''),' ', t.last_name,' ', IFNULL(t.suffix,'')) AS teacher_name
        FROM announcements a
        JOIN teachers t ON a.teacher_id = t.id
        WHERE a.course_id = '$selected_course'
          AND a.teacher_id = '$teacher_id'
          AND a.pinned = 1
        ORDER BY a.created_at DESC
    ");

    $recentQuery = mysqli_query($conn, "
        SELECT a.*, 
               CONCAT(t.first_name,' ', IFNULL(t.middle_name,''),' ', t.last_name,' ', IFNULL(t.suffix,'')) AS teacher_name
        FROM announcements a
        JOIN teachers t ON a.teacher_id = t.id
        WHERE a.course_id = '$selected_course'
          AND a.teacher_id = '$teacher_id'
          AND a.pinned = 0
        ORDER BY a.created_at DESC
    ");
}

// ================== FETCH SECTIONS ==================
$sections = [];
if ($is_admin) {
    $sectionsQuery = mysqli_query($conn, "
        SELECT DISTINCT section FROM students 
        WHERE UPPER(TRIM(course)) = '$selected_course' 
          AND section IS NOT NULL AND section != '' 
        ORDER BY section ASC
    ");
} else {
    $sectionsQuery = mysqli_query($conn, "
        SELECT DISTINCT s.section FROM students s 
        JOIN teachers t ON 1=1 
        WHERE UPPER(TRIM(s.course)) = '$selected_course' 
          AND s.section IS NOT NULL AND s.section != '' 
          AND s.year_level IN ('" . implode("','", array_map(function($y){ return mysqli_real_escape_string($GLOBALS['conn'], trim($y)); }, getTeacherYearLevels())) . "') 
        ORDER BY s.section ASC
    ");
}
while($row = mysqli_fetch_assoc($sectionsQuery)){
    $sections[] = $row['section'];
}

$yearLevels     = $is_admin ? ['1st Year','2nd Year','3rd Year','4th Year'] : getTeacherYearLevels();
$priorityLevels = ['low','medium','high'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - <?= htmlspecialchars($selected_course) ?></title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="../css/teacherportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include path('teachersportal/sidebar.php'); ?>

<div class="content">

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success" id="successAlert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-error" id="errorAlert">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <div class="announcement-header">
        <?php if ($is_admin): ?>
            <h1><i class="fas fa-bullhorn"></i> All Announcements</h1>
        <?php else: ?>
            <h1><i class="fas fa-bullhorn"></i> My Announcements — <?= htmlspecialchars($selected_course) ?></h1>
        <?php endif; ?>
        <button id="openModalBtn" class="btn"><i class="fas fa-plus"></i> New Announcement</button>
    </div>

    <?php if(mysqli_num_rows($pinnedQuery) > 0): ?>
        <h2><i class="fas fa-thumbtack"></i> Pinned Announcements</h2>
        <div class="cards">
        <?php while($row = mysqli_fetch_assoc($pinnedQuery)): ?>
            <div class="card"
                 data-id="<?= $row['id'] ?>"
                 data-title="<?= htmlspecialchars($row['title']) ?>"
                 data-content="<?= htmlspecialchars($row['content']) ?>"
                 data-year_level="<?= htmlspecialchars($row['year_level']) ?>"
                 data-section="<?= htmlspecialchars($row['section']) ?>"
                 data-priority="<?= $row['priority'] ?>"
                 data-date="<?= $row['created_at'] ?>">
                <i class="fas fa-thumbtack pinned-icon"></i>
                <h3>
                    <?= htmlspecialchars($row['title']) ?>
                    <span class="priority-badge"><?= ucfirst($row['priority']) ?></span>
                </h3>
                <p><?= htmlspecialchars($row['content']) ?></p>
                <small><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></small>
                <div class="card-actions">
                    <a href="#" class="edit-announcement" title="Edit"><i class="fas fa-pencil"></i></a>
                    <a href="#" class="toggle-pin" data-id="<?= $row['id'] ?>" title="Unpin"><i class="fas fa-thumbtack"></i></a>
                    <a href="#" class="delete-announcement" data-id="<?= $row['id'] ?>" title="Delete"><i class="fas fa-trash"></i></a>
                </div>
                <p class="announcement-meta">
                    <?php if ($is_admin): ?>
                        <i class="fas fa-book"></i> <?= htmlspecialchars($row['announcement_course']) ?> |
                    <?php endif; ?>
                    <i class="fas fa-user"></i> <?= htmlspecialchars($row['teacher_name']) ?> |
                    <i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($row['year_level']) ?> |
                    <i class="fas fa-users"></i> Section <?= htmlspecialchars($row['section']) ?>
                </p>
            </div>
        <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <h2>
        <?php if ($is_admin): ?>
            Recent Announcements
        <?php else: ?>
            My Recent Announcements
        <?php endif; ?>
    </h2>
    <div class="cards">
        <?php if(mysqli_num_rows($recentQuery) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($recentQuery)): ?>
                <div class="card"
                     data-id="<?= $row['id'] ?>"
                     data-title="<?= htmlspecialchars($row['title']) ?>"
                     data-content="<?= htmlspecialchars($row['content']) ?>"
                     data-year_level="<?= htmlspecialchars($row['year_level']) ?>"
                     data-section="<?= htmlspecialchars($row['section']) ?>"
                     data-priority="<?= $row['priority'] ?>"
                     data-date="<?= $row['created_at'] ?>">
                    <h3>
                        <?= htmlspecialchars($row['title']) ?>
                        <span class="priority-badge"><?= ucfirst($row['priority']) ?></span>
                    </h3>
                    <p><?= htmlspecialchars($row['content']) ?></p>
                    <small><?= date('M d, Y h:i A', strtotime($row['created_at'])) ?></small>
                    <div class="card-actions">
                        <a href="#" class="edit-announcement" title="Edit"><i class="fas fa-pencil"></i></a>
                        <a href="#" class="toggle-pin" data-id="<?= $row['id'] ?>" title="Pin"><i class="fas fa-thumbtack"></i></a>
                        <a href="#" class="delete-announcement" data-id="<?= $row['id'] ?>" title="Delete"><i class="fas fa-trash"></i></a>
                    </div>
                    <p class="announcement-meta">
                        <?php if ($is_admin): ?>
                            <i class="fas fa-book"></i> <?= htmlspecialchars($row['announcement_course']) ?> |
                        <?php endif; ?>
                        <i class="fas fa-user"></i> <?= htmlspecialchars($row['teacher_name']) ?> |
                        <i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($row['year_level']) ?> |
                        <i class="fas fa-users"></i> Section <?= htmlspecialchars($row['section']) ?>
                    </p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--text-muted);">
                <i class="fas fa-info-circle"></i> 
                <?= $is_admin ? 'No recent announcements.' : 'You have not posted any announcements yet.' ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Announcement Modal -->
<div id="announcementModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2 id="modalTitle">Add New Announcement</h2>
        <form method="POST">
            <input type="hidden" name="announcement_id" id="announcement_id">

            <div class="form-group">
                <label for="announcement_title">Title</label>
                <input type="text" name="title" id="announcement_title" required
                       oninput="this.value = this.value.toUpperCase();">
            </div>

            <div class="form-group">
                <label for="announcement_content">Content</label>
                <textarea name="content" id="announcement_content" rows="5" required
                          oninput="formatSentenceCase(this)"></textarea>
            </div>

            <?php if (!$is_admin): ?>
            <div class="form-group">
                <label for="announcement_year_level">Year Level</label>
                <select name="year_level" id="announcement_year_level" required>
                    <option value="">Select Year Level</option>
                    <?php foreach($yearLevels as $year): ?>
                        <option value="<?= $year ?>"><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="announcement_section">Section</label>
                <select name="section" id="announcement_section" required>
                    <option value="">Select Section</option>
                    <?php foreach($sections as $sec): ?>
                        <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="announcement_priority">Priority</label>
                <select name="announcement_priority" id="announcement_priority" required>
                    <?php foreach($priorityLevels as $pri): ?>
                        <option value="<?= $pri ?>"><?= ucfirst($pri) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="announcement_date">Date &amp; Time to Post</label>
                <input type="datetime-local" name="announcement_date" id="announcement_date">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-outline"
                        onclick="document.getElementById('announcementModal').style.display='none'">Cancel</button>
                <button type="submit" name="save_announcement" class="btn" id="modalSubmitBtn">Save Announcement</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content small">
        <span class="close-delete-modal close">&times;</span>
        <h2><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Confirm Delete</h2>
        <p>Are you sure you want to delete this announcement? This action cannot be undone.</p>
        <div class="modal-actions">
            <button id="cancelDeleteBtn" class="btn-outline">Cancel</button>
            <a href="#" id="confirmDeleteBtn" class="btn-danger">Delete</a>
        </div>
    </div>
</div>

<script>
function formatSentenceCase(el) {
    let text = el.value.trimStart();
    if(text.length === 0) return;
    el.value = text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
}
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const modal          = document.getElementById('announcementModal');
    const deleteModal    = document.getElementById('deleteModal');
    const openBtn        = document.getElementById('openModalBtn');
    const closeBtn       = document.querySelector('.close-modal');
    const closeDeleteBtn = document.querySelector('.close-delete-modal');
    const cancelDeleteBtn  = document.getElementById('cancelDeleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    let deleteUrl = '';

    openBtn.onclick = () => {
        document.getElementById('announcement_id').value      = '';
        document.getElementById('announcement_title').value   = '';
        document.getElementById('announcement_content').value = '';
        document.getElementById('announcement_priority').value = 'medium';
        document.getElementById('announcement_date').value    = '';

        const yearLevel = document.getElementById('announcement_year_level');
        const section   = document.getElementById('announcement_section');
        if (yearLevel) yearLevel.value = '';
        if (section)   section.value   = '';

        document.getElementById('modalTitle').textContent      = 'Add New Announcement';
        document.getElementById('modalSubmitBtn').textContent  = 'Save Announcement';
        modal.style.display = 'flex';
    };

    closeBtn.onclick = () => modal.style.display = 'none';
    if (closeDeleteBtn)  closeDeleteBtn.onclick  = () => deleteModal.style.display = 'none';
    if (cancelDeleteBtn) cancelDeleteBtn.onclick = () => deleteModal.style.display = 'none';

    window.onclick = (e) => {
        if (e.target == modal)       modal.style.display       = 'none';
        if (e.target == deleteModal) deleteModal.style.display = 'none';
    };

    document.querySelectorAll('.edit-announcement').forEach(icon => {
        icon.addEventListener('click', e => {
            e.preventDefault();
            const card = icon.closest('.card');
            document.getElementById('announcement_id').value      = card.dataset.id;
            document.getElementById('announcement_title').value   = card.dataset.title;
            document.getElementById('announcement_content').value = card.dataset.content;
            document.getElementById('announcement_priority').value = card.dataset.priority ?? 'medium';

            const yearLevelEl = document.getElementById('announcement_year_level');
            if (yearLevelEl) yearLevelEl.value = card.dataset.year_level;

            const sectionEl = document.getElementById('announcement_section');
            if (sectionEl) sectionEl.value = card.dataset.section;

            const date = new Date(card.dataset.date);
            document.getElementById('announcement_date').value = date.toISOString().slice(0, 16);

            document.getElementById('modalTitle').textContent     = 'Edit Announcement';
            document.getElementById('modalSubmitBtn').textContent = 'Update Announcement';
            modal.style.display = 'flex';
        });
    });

    document.querySelectorAll('.delete-announcement').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            deleteUrl = '?delete=' + link.dataset.id;
            deleteModal.style.display = 'flex';
        });
    });

    confirmDeleteBtn.onclick = e => {
        e.preventDefault();
        if (deleteUrl) window.location.href = deleteUrl;
    };

    document.querySelectorAll('.toggle-pin').forEach(btn => {
        btn.addEventListener('click', async e => {
            e.preventDefault();
            const id = btn.dataset.id;
            try {
                const res  = await fetch(`?toggle_pin=${id}`);
                const data = await res.json();
                if (data.success) location.reload();
            } catch(err) {
                alert('Failed to toggle pin');
            }
        });
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.style.transition = 'opacity 0.3s ease-out';
            alert.style.opacity    = '0';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);

    // Manual close alert
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('alert-close')) {
            const alert = e.target.closest('.alert');
            alert.style.transition = 'opacity 0.3s ease-out';
            alert.style.opacity    = '0';
            setTimeout(() => alert.remove(), 300);
        }
    });
});
</script>
</body>
</html>