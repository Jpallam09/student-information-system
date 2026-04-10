<?php
/* ============================================================
   announcements.php — PHP logic preserved exactly.
   HTML/UI unified with students.php modern style.
   ============================================================ */
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
$teacher_id  = $_SESSION['teacher_id'];
$selected_course = $_SESSION['teacher_course'] ?? '';

/* ── Handle POST: add / edit / delete / pin ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
if ($action === 'add' || $action === 'edit') {
    $title    = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
    $content = mysqli_real_escape_string($conn, $_POST['content'] ?? '');
    $priority = mysqli_real_escape_string($conn, $_POST['priority'] ?? 'normal');
    $audience = mysqli_real_escape_string($conn, $_POST['audience'] ?? 'all');

            if ($action === 'add') {
                mysqli_query($conn,
                    "INSERT INTO announcements (title, content, priority, audience, teacher_id, course, created_at)
                     VALUES ('$title',$content','$priority','$audience',$teacher_id,'$selected_course',NOW())"
                );
                $_SESSION['success_message'] = 'Announcement posted successfully!';
            } else {
                $id = (int)$_POST['announcement_id'];
                mysqli_query($conn,
                    "UPDATE announcements SET title='$title', content='$content',
                     priority='$priority', audience='$audience' WHERE id=$id"
                );
                $_SESSION['success_message'] = 'Announcement updated successfully!';
            }
        }

        if ($action === 'delete') {
            $id = (int)$_POST['announcement_id'];
            mysqli_query($conn, "DELETE FROM announcements WHERE id=$id");
            $_SESSION['success_message'] = 'Announcement deleted.';
        }

        if ($action === 'toggle_pin') {
            $id  = (int)$_POST['announcement_id'];
            $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT is_pinned FROM announcements WHERE id=$id"));
            $new = $row['is_pinned'] ? 0 : 1;
            mysqli_query($conn, "UPDATE announcements SET is_pinned=$new WHERE id=$id");
            $_SESSION['success_message'] = $new ? 'Announcement pinned.' : 'Announcement unpinned.';
        }
    }
    header("Location: announcements.php");
    exit();
}

/* ── Filters ── */
$filter_priority = $_GET['priority'] ?? '';
$filter_audience = $_GET['audience'] ?? '';
$search          = trim($_GET['search'] ?? '');

$where = "WHERE course='$selected_course'";
if ($filter_priority) $where .= " AND priority='" . mysqli_real_escape_string($conn, $filter_priority) . "'";
if ($filter_audience) $where .= " AND audience='" . mysqli_real_escape_string($conn, $filter_audience) . "'";
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (title LIKE '%$s%' OR content LIKE '%$s%')";
}

$announcements_result = mysqli_query($conn,
    "SELECT a.*, CONCAT(t.first_name,' ',t.last_name) AS teacher_name
     FROM announcements a
     LEFT JOIN teachers t ON t.id = a.teacher_id
     $where
     ORDER BY is_pinned DESC, created_at DESC"
);
$announcements = [];
while ($row = mysqli_fetch_assoc($announcements_result)) {
    $announcements[] = $row;
}

/* ── Stats ── */
$total     = count($announcements);
$pinned    = count(array_filter($announcements, fn($a) => $a['is_pinned']));
$high_prio = count(array_filter($announcements, fn($a) => $a['priority'] === 'high'));

$priority_map = [
    'high'   => ['label' => 'High',   'class' => 'badge-red'],
    'medium' => ['label' => 'Medium', 'class' => 'badge-yellow'],
    'normal' => ['label' => 'Normal', 'class' => 'badge-blue'],
    'low'    => ['label' => 'Low',    'class' => 'badge-green'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements — <?= htmlspecialchars($selected_course) ?></title>
    <link rel="icon" href="<?= asset('images/622685015_925666030131412_6886851389087569993_n.jpg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include PROJECT_ROOT . '/teachersportal/sidebar.php'; ?>

<div class="content">

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- ── PAGE HEADER ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><?= htmlspecialchars($selected_course) ?> Portal</div>
            <h1 class="page-header-title">Announcements</h1>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <?php if ($total > 0): ?>
                <span class="result-count"><i class="fas fa-bullhorn"></i> <?= $total ?> announcement<?= $total !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
            <button type="button" onclick="openAddModal()" style="border-radius:50px;">
                <i class="fas fa-plus"></i> New Announcement
            </button>
        </div>
    </div>

    <!-- ── STATS ROW ── -->
    <div class="stats-row">
        <div class="mini-stat">
            <div class="mini-stat-label"><i class="fas fa-bullhorn" style="margin-right:5px;color:var(--primary-blue);"></i> Total</div>
            <div class="mini-stat-value"><?= $total ?></div>
        </div>
        <div class="mini-stat pinned">
            <div class="mini-stat-label"><i class="fas fa-thumbtack" style="margin-right:5px;color:var(--accent-amber);"></i> Pinned</div>
            <div class="mini-stat-value"><?= $pinned ?></div>
        </div>
        <div class="mini-stat urgent">
            <div class="mini-stat-label"><i class="fas fa-exclamation-circle" style="margin-right:5px;color:var(--accent-rose);"></i> High Priority</div>
            <div class="mini-stat-value"><?= $high_prio ?></div>
        </div>
    </div>

    <!-- ── FILTERS ── -->
    <div class="filter-group">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;width:100%;">
            <select name="priority" onchange="this.form.submit()">
                <option value="">All Priorities</option>
                <option value="high"   <?= $filter_priority==='high'   ?'selected':'' ?>>High</option>
                <option value="medium" <?= $filter_priority==='medium' ?'selected':'' ?>>Medium</option>
                <option value="normal" <?= $filter_priority==='normal' ?'selected':'' ?>>Normal</option>
                <option value="low"    <?= $filter_priority==='low'    ?'selected':'' ?>>Low</option>
            </select>
            <select name="audience" onchange="this.form.submit()">
                <option value="">All Audiences</option>
                <option value="all"     <?= $filter_audience==='all'     ?'selected':'' ?>>All</option>
                <option value="1st Year" <?= $filter_audience==='1st Year' ?'selected':'' ?>>1st Year</option>
                <option value="2nd Year" <?= $filter_audience==='2nd Year' ?'selected':'' ?>>2nd Year</option>
                <option value="3rd Year" <?= $filter_audience==='3rd Year' ?'selected':'' ?>>3rd Year</option>
                <option value="4th Year" <?= $filter_audience==='4th Year' ?'selected':'' ?>>4th Year</option>
            </select>
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search announcements…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit"><i class="fas fa-search"></i> Search</button>
            <?php if ($filter_priority || $filter_audience || $search): ?>
                <a href="announcements.php" class="refresh-btn" title="Clear Filters">
                    <i class="fas fa-rotate-right"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── ANNOUNCEMENTS GRID ── -->
    <?php if (count($announcements) > 0): ?>
    <div class="ann-grid">
        <?php foreach ($announcements as $ann):
            $pmap = $priority_map[$ann['priority']] ?? ['label'=>'Normal','class'=>'badge-blue'];
        ?>
        <div class="ann-card <?= $ann['is_pinned'] ? 'is-pinned' : '' ?>">
            <div class="ann-card-header">
                <?php if ($ann['is_pinned']): ?>
                    <i class="fas fa-thumbtack ann-pin-icon"></i>
                <?php endif; ?>
                <div style="flex:1;">
                    <h3 class="ann-card-title"><?= htmlspecialchars($ann['title']) ?></h3>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">
                        <span class="<?= $pmap['class'] ?>"><?= $pmap['label'] ?></span>
                        <?php if (!empty($ann['audience']) && $ann['audience'] !== 'all'): ?>
                            <span class="badge-blue"><?= htmlspecialchars($ann['audience']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <p class="ann-card-content"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>

            <div class="ann-card-footer">
                <div class="ann-meta">
                    <?php if (!empty($ann['teacher_name'])): ?>
                        <span><i class="fas fa-user"></i><?= htmlspecialchars($ann['teacher_name']) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-clock"></i><?= date('M d, Y', strtotime($ann['created_at'])) ?></span>
                </div>
                <div class="ann-actions">
                    <!-- Pin -->
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="toggle_pin">
                        <input type="hidden" name="announcement_id" value="<?= $ann['id'] ?>">
                        <button type="submit" class="ann-btn ann-btn-pin" title="<?= $ann['is_pinned']?'Unpin':'Pin' ?>">
                            <i class="fas fa-thumbtack"></i>
                        </button>
                    </form>
                    <!-- Edit -->
                    <button type="button" class="ann-btn ann-btn-edit"
                            onclick="openEditModal(<?= htmlspecialchars(json_encode($ann)) ?>)"
                            title="Edit">
                        <i class="fas fa-pencil"></i>
                    </button>
                    <!-- Delete -->
                    <button type="button" class="ann-btn ann-btn-delete"
                            onclick="openDeleteModal(<?= $ann['id'] ?>)"
                            title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="section-box">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-bullhorn"></i></div>
            <h3>No Announcements Found</h3>
            <p>Try adjusting your filters or post a new announcement.</p>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.content -->

<!-- ── ADD / EDIT MODAL ── -->
<div id="annModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAnnModal()">&times;</span>
        <h2 id="annModalTitle"><i class="fas fa-bullhorn"></i> New Announcement</h2>
        <form method="POST" id="annForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="announcement_id" id="formAnnId" value="">
            <div class="form-group">
                <label for="ann_title"><i class="fas fa-heading"></i> Title</label>
                <input type="text" name="title" id="ann_title" class="input-field" required placeholder="Announcement title…">
            </div>
            <div class="form-group">
                <label for="ann_content"><i class="fas fa-align-left"></i> Message</label>
                <textarea name="content" id="ann_content" class="input-field" required placeholder="Write your announcement here…"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="ann_priority"><i class="fas fa-flag"></i> Priority</label>
                    <select name="priority" id="ann_priority" class="input-field">
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ann_audience"><i class="fas fa-users"></i> Audience</label>
                    <select name="audience" id="ann_audience" class="input-field">
                        <option value="all">All</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAnnModal()">Cancel</button>
                <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ── DELETE CONFIRM MODAL ── -->
<div id="deleteModal" class="modal">
    <div class="modal-content small">
        <span class="close" onclick="closeDeleteModal()">&times;</span>
        <h2><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h2>
        <p>Are you sure you want to delete this announcement?</p>
        <p style="color:var(--accent-rose);font-size:0.9rem;"><strong>This action cannot be undone.</strong></p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="announcement_id" id="deleteAnnId" value="">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── Modal helpers ── */
function openAddModal() {
    document.getElementById('annModalTitle').innerHTML = '<i class="fas fa-bullhorn"></i> New Announcement';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formAnnId').value  = '';
    document.getElementById('ann_title').value    = '';
    document.getElementById('ann_content').value     = '';
    document.getElementById('ann_priority').value = 'normal';
    document.getElementById('ann_audience').value = 'all';
    document.getElementById('annModal').style.display = 'flex';
}

function openEditModal(ann) {
    document.getElementById('annModalTitle').innerHTML = '<i class="fas fa-pencil"></i> Edit Announcement';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formAnnId').value  = ann.id;
    document.getElementById('ann_title').value    = ann.title;
    document.getElementById('ann_content').value     = ann.content;
    document.getElementById('ann_priority').value = ann.priority || 'normal';
    document.getElementById('ann_audience').value = ann.audience || 'all';
    document.getElementById('annModal').style.display = 'flex';
}

function closeAnnModal() { document.getElementById('annModal').style.display = 'none'; }

function openDeleteModal(id) {
    document.getElementById('deleteAnnId').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }

/* ── Close on outside click ── */
window.addEventListener('click', function(e) {
    ['annModal','deleteModal'].forEach(function(id) {
        var m = document.getElementById(id);
        if (e.target === m) m.style.display = 'none';
    });
});

/* ── Auto-dismiss alerts ── */
setTimeout(function(){
    document.querySelectorAll('.alert').forEach(function(a){
        a.style.transition='opacity 0.3s';
        a.style.opacity='0';
        setTimeout(function(){a.remove();},300);
    });
},5000);
</script>
</body>
</html>