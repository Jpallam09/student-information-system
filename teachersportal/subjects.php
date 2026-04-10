<?php
/* ============================================================
   subjects.php — PHP logic preserved exactly.
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
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

$sy_params = []; $sy_types = '';
$ss_params = []; $ss_types = '';
$cy_params = []; $cy_types = '';
$cs_params = []; $cs_types = '';
$subject_year_filter = $subject_section_filter = $class_year_filter = $class_section_filter = '';

if (!$is_admin) {
    $subject_year_filter    = getCombinedYearFilter('s.year_level', $sy_params, $sy_types);
    $subject_section_filter = getCombinedSectionFilter('s.section',  $ss_params, $ss_types);
    $class_year_filter      = getCombinedYearFilter('c.year_level', $cy_params, $cy_types);
    $class_section_filter   = getCombinedSectionFilter('c.section',  $cs_params, $cs_types);
}

/* ── DELETE class ── */
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    if ($is_admin) {
        $stmt_del = $conn->prepare("DELETE FROM classes WHERE id=?");
        $stmt_del->bind_param("i", $delete_id);
        $stmt_del->execute();
        $_SESSION[$stmt_del->affected_rows > 0 ? 'success_message' : 'error_message'] =
            $stmt_del->affected_rows > 0 ? 'Class deleted successfully!' : 'Failed to delete class or no permission.';
        $stmt_del->close();
    }
    header("Location: " . BASE_URL . "teachersportal/subjects.php");
    exit();
}

if (isset($_POST['delete_class_id'])) {
    $delete_id = intval($_POST['delete_class_id']);
    $stmt_del = $conn->prepare("DELETE FROM classes WHERE id=?");
    $stmt_del->bind_param("i", $delete_id);
    $stmt_del->execute();
    $_SESSION[$stmt_del->affected_rows > 0 ? 'success_message' : 'error_message'] =
        $stmt_del->affected_rows > 0 ? 'Class deleted successfully!' : 'Failed to delete class or no permission.';
    $stmt_del->close();
    header("Location: " . BASE_URL . "teachersportal/subjects.php");
    exit();
}

/* ── UPDATE class ── */
if (isset($_POST['update_class_id'])) {
    $update_id       = intval($_POST['update_class_id']);
    $section_post    = $_POST['section'];
    $year_level_post = $_POST['year_level'];
    $stmt_upd = $conn->prepare("UPDATE classes SET section=?, year_level=? WHERE id=?");
    $stmt_upd->bind_param("ssi", $section_post, $year_level_post, $update_id);
    $stmt_upd->execute();
    $_SESSION[$stmt_upd->affected_rows > 0 ? 'success_message' : 'error_message'] =
        $stmt_upd->affected_rows > 0 ? 'Class updated successfully!' : 'Failed to update class: ' . $conn->error;
    $stmt_upd->close();
    header("Location: subjects.php");
    exit();
}

/* ── AJAX: list students ── */
if (isset($_GET['action']) && $_GET['action'] == 'list_students'
    && isset($_GET['class_id'], $_GET['section'], $_GET['year_level'])) {
    $selected_course = $_SESSION['teacher_course'] ?? '';
    if (empty($selected_course)) { header('Content-Type: application/json'); echo json_encode([]); exit(); }
    $list_y_params = []; $list_y_types = '';
    $list_s_params = []; $list_s_types = '';
    $list_year_filter = $list_section_filter = '';
    if (!$is_admin) {
        $list_year_filter    = getCombinedYearFilter('year_level', $list_y_params, $list_y_types);
        $list_section_filter = getCombinedSectionFilter('section', $list_s_params, $list_s_types);
    }
    $list_section    = $_GET['section'];
    $list_year_level = $_GET['year_level'];
    $list_sql = "SELECT CONCAT(first_name,' ',last_name) AS full_name, student_id
                 FROM students
                 WHERE course=? $list_year_filter $list_section_filter
                   AND section=? AND year_level=?
                 ORDER BY last_name ASC, first_name ASC";
    $list_params = [$selected_course];
    $list_types  = "s";
    if (!$is_admin) {
        if (!empty($list_y_params)) { $list_params = array_merge($list_params, $list_y_params); $list_types .= $list_y_types; }
        if (!empty($list_s_params)) { $list_params = array_merge($list_params, $list_s_params); $list_types .= $list_s_types; }
    }
    $list_params[] = $list_section;    $list_types .= "s";
    $list_params[] = $list_year_level; $list_types .= "s";
    $stmt_list = $conn->prepare($list_sql);
    $stmt_list->bind_param($list_types, ...$list_params);
    $stmt_list->execute();
    $students_arr = [];
    $list_result = $stmt_list->get_result();
    while ($row = $list_result->fetch_assoc()) { $students_arr[] = $row; }
    $stmt_list->close();
    header('Content-Type: application/json');
    echo json_encode($students_arr);
    exit();
}

/* ── Session / course ── */
$back_url = BASE_URL . "Accesspage/teacher_login.php";
if ($is_admin) $back_url = BASE_URL . "teachersportal/chooseSub.php";

$selected_course = $_SESSION['teacher_course'] ?? '';
if (empty($selected_course)) { echo "Course not assigned to this teacher. Contact admin."; exit(); }

$teacher_id      = $_SESSION['teacher_id'];
$allowed_courses = ['BSIT', 'BSED', 'BAT', 'BTVTED'];
if (!in_array(strtoupper($selected_course), $allowed_courses)) {
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="' . BASE_URL . 'teachersportal/chooseSub.php">← Go Back</a>';
    exit();
}

$stmt_cid = $conn->prepare("SELECT id FROM courses WHERE course_name=?");
$stmt_cid->bind_param("s", $selected_course);
$stmt_cid->execute();
$cid_result = $stmt_cid->get_result();
if (!$cid_result || $cid_result->num_rows == 0) die("Selected course does not exist in the database.");
$course_row = $cid_result->fetch_assoc();
$course_id  = $course_row['id'];
$stmt_cid->close();

$year_level     = $_GET['year_level']     ?? '';
$section_filter = $_GET['section_filter'] ?? '';

$year_colors = [
    '1st Year' => ['bg'=>'#eff6ff','accent'=>'#3b82f6','dark'=>'#1d4ed8'],
    '2nd Year' => ['bg'=>'#ecfdf5','accent'=>'#10b981','dark'=>'#059669'],
    '3rd Year' => ['bg'=>'#fffbeb','accent'=>'#f59e0b','dark'=>'#d97706'],
    '4th Year' => ['bg'=>'#fef2f2','accent'=>'#ef4444','dark'=>'#dc2626'],
];

/* ── Subjects ── */
$subj_sql    = "SELECT * FROM subjects s WHERE s.course_id=? $subject_year_filter $subject_section_filter";
$subj_params = [$course_id]; $subj_types = "i";
if (!$is_admin) {
    if (!empty($sy_params)) { $subj_params = array_merge($subj_params, $sy_params); $subj_types .= $sy_types; }
    if (!empty($ss_params)) { $subj_params = array_merge($subj_params, $ss_params); $subj_types .= $ss_types; }
}
if ($year_level)     { $subj_sql .= " AND s.year_level=?"; $subj_params[] = $year_level;     $subj_types .= "s"; }
if ($section_filter) { $subj_sql .= " AND s.section=?";    $subj_params[] = $section_filter; $subj_types .= "s"; }
$subj_sql .= " ORDER BY s.year_level ASC, s.subject_name ASC";
$stmt_subj = $conn->prepare($subj_sql);
$stmt_subj->bind_param($subj_types, ...$subj_params);
$stmt_subj->execute();
$subjects_query = $stmt_subj->get_result();
$stmt_subj->close();

/* ── Classes ── */
$class_sql = "SELECT c.id, c.section, c.year_level, COUNT(s.id) AS student_count
              FROM classes c
              LEFT JOIN students s ON s.section=c.section AND s.year_level=c.year_level AND s.course=?
              WHERE c.course_id=? $class_year_filter $class_section_filter";
$class_params = [$selected_course, $course_id]; $class_types = "si";
if (!$is_admin) {
    if (!empty($cy_params)) { $class_params = array_merge($class_params, $cy_params); $class_types .= $cy_types; }
    if (!empty($cs_params)) { $class_params = array_merge($class_params, $cs_params); $class_types .= $cs_types; }
}
if ($year_level)     { $class_sql .= " AND c.year_level=?"; $class_params[] = $year_level;     $class_types .= "s"; }
if ($section_filter) { $class_sql .= " AND c.section=?";    $class_params[] = $section_filter; $class_types .= "s"; }
$class_sql .= " GROUP BY c.id, c.section, c.year_level ORDER BY c.year_level ASC, c.section ASC";
$stmt_class = $conn->prepare($class_sql);
$stmt_class->bind_param($class_types, ...$class_params);
$stmt_class->execute();
$classes_query = $stmt_class->get_result();
$stmt_class->close();

$teacher_dropdown_years = getTeacherDropdownYears();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects & Classes — <?= htmlspecialchars($selected_course) ?></title>
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
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- ── PAGE HEADER ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><?= htmlspecialchars($selected_course) ?> Portal</div>
            <h1 class="page-header-title">Subjects &amp; Classes</h1>
        </div>
    </div>

    <!-- ── FILTERS ── -->
    <div class="filter-group">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;width:100%;">
            <select name="year_level" onchange="this.form.submit()">
                <?php if ($is_admin): ?><option value="">All Year Levels</option><?php endif; ?>
                <?php foreach ($teacher_dropdown_years as $yl): ?>
                    <option value="<?= htmlspecialchars($yl) ?>" <?= $year_level==$yl?'selected':'' ?>><?= htmlspecialchars($yl) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="section_filter" onchange="this.form.submit()">
                <?php if ($is_admin): ?><option value="">All Sections</option><?php endif; ?>
                <?php
                $dropdown_sections = $is_admin ? ['A','B','C','D','E'] : getTeacherDropdownSections();
                foreach ($dropdown_sections as $sec):
                ?>
                    <option value="<?= htmlspecialchars($sec) ?>" <?= $section_filter==$sec?'selected':'' ?>><?= htmlspecialchars($sec) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($year_level || $section_filter): ?>
                <a href="subjects.php" class="refresh-btn"><i class="fas fa-rotate-right"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ══════════════════════════════════
         SUBJECT MANAGEMENT
    ══════════════════════════════════ -->
    <div class="section-box">
        <div class="section-heading">
            <div class="section-heading-left">
                <div class="section-heading-icon"><i class="fas fa-book"></i></div>
                <div>
                    <h2 class="section-heading-title">Subject Management</h2>
                    <p class="section-heading-sub">All enrolled subjects for <?= htmlspecialchars($selected_course) ?></p>
                </div>
            </div>
            <?php if ($is_admin): ?>
                <a href="<?= BASE_URL ?>teachers_access/addsubject.php">
                    <button style="border-radius:50px;"><i class="fas fa-plus"></i> Add Subject</button>
                </a>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Subject Name</th>
                        <th>Year Level</th>
                        <th>Section</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($subjects_query && $subjects_query->num_rows > 0): ?>
                    <?php while ($subject = $subjects_query->fetch_assoc()): ?>
                        <tr>
                            <td style="font-family:monospace;font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($subject['code']) ?></td>
                            <td style="font-weight:600;color:var(--slate-800);text-align:left;"><?= htmlspecialchars($subject['subject_name']) ?></td>
                            <td><?= htmlspecialchars($subject['year_level']) ?></td>
                            <td><span class="badge-blue"><?= htmlspecialchars($subject['section']) ?></span></td>
                            <td>
                                <span class="<?= $subject['subject_type']=='MINOR'?'badge-minor':'badge-major' ?>">
                                    <?= strtoupper($subject['subject_type']) ?>
                                </span>
                            </td>
                            <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($subject['description']) ?></td>
                            <td>
                                <button onclick="openModal('<?= htmlspecialchars(addslashes($subject['subject_name'])) ?>','<?= htmlspecialchars(addslashes($subject['description'])) ?>')"
                                        style="border-radius:50px;padding:7px 14px;font-size:11px;">
                                    <i class="fas fa-info-circle"></i> Details
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="padding:0;border:none;"></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if (!$subjects_query || $subjects_query->num_rows === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-book-open"></i></div>
                <h3>No Subjects Found</h3>
                <p>Try adjusting your filters<?= $is_admin ? ' or add a subject' : '' ?>.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════
         CLASS MANAGEMENT
    ══════════════════════════════════ -->
    <div class="section-box">
        <div class="section-heading">
            <div class="section-heading-left">
                <div class="section-heading-icon"><i class="fas fa-layer-group"></i></div>
                <div>
                    <h2 class="section-heading-title">Class Management</h2>
                    <p class="section-heading-sub">
                        <?= $is_admin ? 'Manage all classes and sections' : 'Click a card to view enrolled students' ?>
                    </p>
                </div>
            </div>
            <?php if ($is_admin): ?>
                <a href="<?= BASE_URL ?>teachers_access/addclass.php">
                    <button style="border-radius:50px;"><i class="fas fa-plus"></i> Add Class</button>
                </a>
            <?php endif; ?>
        </div>

        <!-- Legend -->
        <div class="year-legend">
            <?php foreach ($year_colors as $label => $clr): ?>
                <span class="year-legend-item" style="color:<?= $clr['dark'] ?>;background:<?= $clr['bg'] ?>;border-color:<?= $clr['accent'] ?>33;">
                    <span class="year-legend-dot" style="background:<?= $clr['accent'] ?>;"></span>
                    <?= $label ?>
                </span>
            <?php endforeach; ?>
        </div>

        <div class="cards">
        <?php if ($classes_query && $classes_query->num_rows > 0):
            while ($class = $classes_query->fetch_assoc()):
                $stmt_sc = $conn->prepare("SELECT COUNT(*) AS count FROM subjects WHERE year_level=? AND section=? AND course_id=?");
                $stmt_sc->bind_param("ssi", $class['year_level'], $class['section'], $course_id);
                $stmt_sc->execute();
                $subject_count = $stmt_sc->get_result()->fetch_assoc()['count'];
                $stmt_sc->close();
                $clr = $year_colors[$class['year_level']] ?? ['bg'=>'#f9fafb','accent'=>'#6b7280','dark'=>'#374151'];
        ?>
            <div class="card class-card <?= !$is_admin?'clickable-card':'' ?>"
                 data-id="<?= $class['id'] ?>"
                 data-section="<?= htmlspecialchars($class['section'],ENT_QUOTES) ?>"
                 data-year="<?= htmlspecialchars($class['year_level'],ENT_QUOTES) ?>"
                 style="background:<?= $clr['bg'] ?>;border-color:<?= $clr['accent'] ?>33;"
                 <?php if (!$is_admin): ?>
                     onclick="openStudentModal(<?= $class['id'] ?>,'<?= addslashes($class['section']) ?>','<?= addslashes($class['year_level']) ?>')"
                 <?php endif; ?>>

                <div class="class-card-stripe"
                     style="background:linear-gradient(90deg,<?= $clr['accent'] ?>,<?= $clr['dark'] ?>);"></div>

                <div class="class-card-body">
                    <?php if ($is_admin): ?>
                    <div class="card-actions" style="display:flex;gap:6px;">
                        <a href="#" class="edit-class" title="Edit" onclick="event.stopPropagation();">
                            <i class="fas fa-pencil-alt"></i>
                        </a>
                        <a href="#" class="delete-class" title="Delete" onclick="event.stopPropagation();">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="class-card-year"
                         style="background:<?= $clr['accent'] ?>20;color:<?= $clr['dark'] ?>;border:1px solid <?= $clr['accent'] ?>44;">
                        <i class="fas fa-circle" style="font-size:7px;"></i>
                        <?= htmlspecialchars($class['year_level']) ?>
                    </div>

                    <h3>Section <?= htmlspecialchars($class['section']) ?></h3>

                    <div class="class-card-stats">
                        <div class="class-stat" style="color:<?= $clr['dark'] ?>;">
                            <i class="fas fa-user-graduate"></i>
                            <?= $class['student_count'] ?> Students
                        </div>
                        <div class="class-stat" style="color:<?= $clr['dark'] ?>;">
                            <i class="fas fa-book"></i>
                            <?= $subject_count ?> Subjects
                        </div>
                        <?php if (!$is_admin): ?>
                        <div class="class-stat" style="color:<?= $clr['accent'] ?>;margin-left:auto;">
                            <i class="fas fa-eye"></i> View Students
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; else: ?>
            <!-- empty handled below -->
        <?php endif; ?>
        </div>

        <?php if (!$classes_query || $classes_query->num_rows === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-chalkboard"></i></div>
            <h3>No Classes Found</h3>
            <p>Try adjusting your filters<?= $is_admin ? ' or add a class' : '' ?>.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── EDIT CLASS MODAL ── -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fas fa-edit"></i> Edit Class</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="update_class_id" id="edit_id">
                <div class="form-group">
                    <label for="edit_section">Section</label>
                    <input type="text" name="section" id="edit_section" class="input-field" required maxlength="1" pattern="[A-E]">
                </div>
                <div class="form-group">
                    <label for="edit_year_level">Year Level</label>
                    <select name="year_level" id="edit_year_level" class="input-field" required>
                        <?php foreach ($teacher_dropdown_years as $yl): ?>
                            <option value="<?= htmlspecialchars($yl) ?>"><?= htmlspecialchars($yl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── DELETE MODAL ── -->
    <div id="deleteModal" class="modal">
        <div class="modal-content small">
            <span class="close close-delete-modal">&times;</span>
            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h2>
            <p>Are you sure you want to delete this class? This action cannot be undone.</p>
            <div class="modal-actions">
                <button id="cancelDeleteBtn" class="btn-cancel">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn-danger"><i class="fas fa-trash"></i> Delete</a>
            </div>
        </div>
    </div>

    <!-- ── SUBJECT DETAILS MODAL ── -->
    <div id="subjectDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="subjectModalTitle"><i class="fas fa-info-circle"></i> Subject Details</h2>
            <div id="subjectModalContent" style="margin-top:20px;line-height:1.6;"></div>
            <div class="modal-actions" style="margin-top:20px;">
                <button type="button" class="btn-cancel" onclick="closeSubjectModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- ── STUDENTS LIST MODAL ── -->
    <div id="studentListModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="studentModalTitle"><i class="fas fa-users"></i> Students in Class</h2>
            <div class="table-container" style="margin-top:16px;">
                <table id="studentsTable">
                    <thead><tr><th>#</th><th style="text-align:left;">Student Name</th><th>Student ID</th></tr></thead>
                    <tbody id="studentsTableBody">
                        <tr><td colspan="3">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-actions" style="margin-top:20px;">
                <button type="button" class="btn-cancel" onclick="closeStudentModal()">Close</button>
            </div>
        </div>
    </div>

</div><!-- /.content -->

<script>
document.addEventListener("DOMContentLoaded", function () {
    var editModal        = document.getElementById("editModal");
    var deleteModal      = document.getElementById("deleteModal");
    var studentListModal = document.getElementById("studentListModal");

    /* Edit */
    document.querySelectorAll(".edit-class").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            var card = btn.closest(".card");
            document.getElementById("edit_id").value         = card.dataset.id;
            document.getElementById("edit_section").value    = card.dataset.section;
            document.getElementById("edit_year_level").value = card.dataset.year;
            editModal.style.display = "flex";
        });
    });
    document.getElementById("cancelEditBtn").addEventListener("click", function () { editModal.style.display = "none"; });

    /* Delete */
    document.querySelectorAll(".delete-class").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            var card = btn.closest(".card");
            document.getElementById("confirmDeleteBtn").href = '?delete=' + card.dataset.id;
            deleteModal.style.display = "flex";
        });
    });
    document.getElementById("cancelDeleteBtn").addEventListener("click", function () { deleteModal.style.display = "none"; });

    /* Universal close */
    document.querySelectorAll(".close").forEach(function (span) {
        span.addEventListener("click", function () {
            document.querySelectorAll(".modal").forEach(function (m) { m.style.display = "none"; });
        });
    });
    window.addEventListener("click", function (e) {
        [editModal, deleteModal, studentListModal, document.getElementById("subjectDetailsModal")]
            .forEach(function (m) { if (e.target === m) m.style.display = "none"; });
    });

    /* Subject details */
    window.openModal = function (name, desc) {
        document.getElementById("subjectModalTitle").innerHTML = '<i class="fas fa-info-circle"></i> ' + name;
        document.getElementById("subjectModalContent").innerHTML = "<p><strong>Description:</strong></p><p>" + desc.replace(/\n/g, "<br>") + "</p>";
        document.getElementById("subjectDetailsModal").style.display = "flex";
    };
    window.closeSubjectModal = function () { document.getElementById("subjectDetailsModal").style.display = "none"; };

    /* Students list */
    window.openStudentModal = function (id, section, year_level) {
        document.getElementById("studentModalTitle").innerHTML =
            '<i class="fas fa-users"></i> Section ' + section + ' — ' + year_level;
        var tbody = document.getElementById("studentsTableBody");
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">Loading…</td></tr>';
        studentListModal.style.display = "flex";
        fetch("subjects.php?action=list_students&class_id=" + id +
              "&section=" + encodeURIComponent(section) +
              "&year_level=" + encodeURIComponent(year_level))
            .then(function (r) { return r.json(); })
            .then(function (students) {
                tbody.innerHTML = "";
                if (!students.length) {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#666;">No students enrolled</td></tr>';
                } else {
                    students.forEach(function (s, i) {
                        var row = tbody.insertRow();
                        row.insertCell(0).textContent = i + 1;
                        var nameCell = row.insertCell(1);
                        nameCell.textContent = s.full_name || "N/A";
                        nameCell.style.textAlign = "left";
                        nameCell.style.fontWeight = "600";
                        row.insertCell(2).textContent = s.student_id || "N/A";
                    });
                }
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#dc3545;">Error loading students</td></tr>';
            });
    };
    window.closeStudentModal = function () { studentListModal.style.display = "none"; };

    /* Auto-dismiss alerts */
    setTimeout(function () {
        document.querySelectorAll(".alert").forEach(function (a) {
            a.style.transition = "opacity 0.3s ease-out";
            a.style.opacity = "0";
            setTimeout(function () { a.remove(); }, 300);
        });
    }, 5000);
    document.addEventListener("click", function (e) {
        if (e.target.classList.contains("alert-close")) {
            var a = e.target.closest(".alert");
            a.style.transition = "opacity 0.3s ease-out";
            a.style.opacity = "0";
            setTimeout(function () { a.remove(); }, 300);
        }
    });
});
</script>
</body>
</html>