<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/teacher_filter.php';

// ================== CHECK LOGIN ==================
if (!isset($_SESSION['teacher_id'])) {
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

// ================== ADMIN CHECK ==================
$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

// ================== BUILD TEACHER FILTER ==================
// Always initialize so variables exist even for admins
$sy_params = []; $sy_types = '';   // subject year filter params
$ss_params = []; $ss_types = '';   // subject section filter params
$cy_params = []; $cy_types = '';   // class year filter params
$cs_params = []; $cs_types = '';   // class section filter params

$subject_year_filter    = '';
$subject_section_filter = '';
$class_year_filter      = '';
$class_section_filter   = '';

if (!$is_admin) {
    $subject_year_filter    = getCombinedYearFilter('s.year_level', $sy_params, $sy_types);
    $subject_section_filter = getCombinedSectionFilter('s.section',  $ss_params, $ss_types);
    $class_year_filter      = getCombinedYearFilter('c.year_level', $cy_params, $cy_types);
    $class_section_filter   = getCombinedSectionFilter('c.section',  $cs_params, $cs_types);
}

// ================== DELETE CLASS ==================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    if ($is_admin) {
        $stmt_del = $conn->prepare("DELETE FROM classes WHERE id=?");
        $stmt_del->bind_param("i", $delete_id);
        $stmt_del->execute();
        if ($stmt_del->affected_rows > 0) {
            $_SESSION['success_message'] = 'Class deleted successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete class or no permission.';
        }
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
    if ($stmt_del->affected_rows > 0) {
        $_SESSION['success_message'] = 'Class deleted successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to delete class or no permission.';
    }
    $stmt_del->close();
    header("Location: " . BASE_URL . "teachersportal/subjects.php");
    exit();
}

// ================== UPDATE CLASS ==================
if (isset($_POST['update_class_id'])) {
    $update_id        = intval($_POST['update_class_id']);
    $section_post     = $_POST['section'];
    $year_level_post  = $_POST['year_level'];

    $stmt_upd = $conn->prepare("UPDATE classes SET section=?, year_level=? WHERE id=?");
    $stmt_upd->bind_param("ssi", $section_post, $year_level_post, $update_id);
    $stmt_upd->execute();
    if ($stmt_upd->affected_rows > 0) {
        $_SESSION['success_message'] = 'Class updated successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to update class: ' . $conn->error;
    }
    $stmt_upd->close();
    header("Location: subjects.php");
    exit();
}

// ================== LIST STUDENTS FOR CLASS (AJAX) ==================
if (isset($_GET['action']) && $_GET['action'] == 'list_students'
    && isset($_GET['class_id'], $_GET['section'], $_GET['year_level'])) {

    $selected_course = $_SESSION['teacher_course'] ?? '';
    if (empty($selected_course)) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit();
    }

    // Build filter for this AJAX block independently
    $list_y_params = []; $list_y_types = '';
    $list_s_params = []; $list_s_types = '';
    $list_year_filter    = '';
    $list_section_filter = '';
    if (!$is_admin) {
        $list_year_filter    = getCombinedYearFilter('year_level',  $list_y_params, $list_y_types);
        $list_section_filter = getCombinedSectionFilter('section',  $list_s_params, $list_s_types);
    }

    $list_section    = $_GET['section'];
    $list_year_level = $_GET['year_level'];

    $list_sql = "SELECT CONCAT(first_name,' ',last_name) AS full_name, student_id
                 FROM students
                 WHERE course=? $list_year_filter $list_section_filter
                   AND section=? AND year_level=?
                 ORDER BY last_name ASC, first_name ASC";

    $list_params  = [$selected_course];
    $list_types   = "s";

    if (!$is_admin) {
        if (!empty($list_y_params)) { $list_params = array_merge($list_params, $list_y_params); $list_types .= $list_y_types; }
        if (!empty($list_s_params)) { $list_params = array_merge($list_params, $list_s_params); $list_types .= $list_s_types; }
    }

    $list_params[] = $list_section;    $list_types .= "s";
    $list_params[] = $list_year_level; $list_types .= "s";

    $stmt_list = $conn->prepare($list_sql);
    $stmt_list->bind_param($list_types, ...$list_params);
    $stmt_list->execute();
    $list_result = $stmt_list->get_result();

    $students_arr = [];
    while ($row = $list_result->fetch_assoc()) {
        $students_arr[] = $row;
    }
    $stmt_list->close();

    header('Content-Type: application/json');
    echo json_encode($students_arr);
    exit();
}

// ================== BACK BUTTON ==================
$back_url = BASE_URL . "Accesspage/teacher_login.php";
if ($is_admin) {
    $back_url = BASE_URL . "teachersportal/chooseSub.php";
}

// ================== COURSE SESSION ==================
$selected_course = $_SESSION['teacher_course'] ?? '';
if (empty($selected_course)) {
    echo "Course not assigned to this teacher. Contact admin.";
    exit();
}

$teacher_id      = $_SESSION['teacher_id'];
$allowed_courses = ['BSIT', 'BSED', 'BAT', 'BTVTED'];
if (!in_array(strtoupper($selected_course), $allowed_courses)) {
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="' . BASE_URL . 'teachersportal/chooseSub.php">← Go Back</a>';
    exit();
}

// ================== GET COURSE ID ==================
$stmt_cid = $conn->prepare("SELECT id FROM courses WHERE course_name=?");
$stmt_cid->bind_param("s", $selected_course);
$stmt_cid->execute();
$cid_result = $stmt_cid->get_result();
if (!$cid_result || $cid_result->num_rows == 0) {
    die("Selected course does not exist in the database.");
}
$course_row = $cid_result->fetch_assoc();
$course_id  = $course_row['id'];
$stmt_cid->close();

// ================== YEAR FILTER ==================
$year_level = $_GET['year_level'] ?? '';

// ================== FETCH SUBJECTS (PREPARED) ==================
$subj_sql    = "SELECT * FROM subjects s WHERE s.course_id=? $subject_year_filter $subject_section_filter";
$subj_params = [$course_id];
$subj_types  = "i";

if (!$is_admin) {
    if (!empty($sy_params)) { $subj_params = array_merge($subj_params, $sy_params); $subj_types .= $sy_types; }
    if (!empty($ss_params)) { $subj_params = array_merge($subj_params, $ss_params); $subj_types .= $ss_types; }
}

if ($year_level) {
    $subj_sql    .= " AND s.year_level=?";
    $subj_params[] = $year_level;
    $subj_types  .= "s";
}

$subj_sql .= " ORDER BY s.year_level ASC, s.subject_name ASC";

$stmt_subj = $conn->prepare($subj_sql);
$stmt_subj->bind_param($subj_types, ...$subj_params);
$stmt_subj->execute();
$subjects_query = $stmt_subj->get_result();
$stmt_subj->close();

// ================== FETCH CLASSES (PREPARED) ==================
$class_sql    = "SELECT c.id, c.section, c.year_level,
                        COUNT(s.id) AS student_count
                 FROM classes c
                 LEFT JOIN students s
                   ON s.section=c.section
                  AND s.year_level=c.year_level
                  AND s.course=?
                 WHERE c.course_id=? $class_year_filter $class_section_filter
                 GROUP BY c.id, c.section, c.year_level
                 ORDER BY c.year_level ASC, c.section ASC";

$class_params = [$selected_course, $course_id];
$class_types  = "si";

if (!$is_admin) {
    if (!empty($cy_params)) { $class_params = array_merge($class_params, $cy_params); $class_types .= $cy_types; }
    if (!empty($cs_params)) { $class_params = array_merge($class_params, $cs_params); $class_types .= $cs_types; }
}

$stmt_class = $conn->prepare($class_sql);
$stmt_class->bind_param($class_types, ...$class_params);
$stmt_class->execute();
$classes_query = $stmt_class->get_result();
$stmt_class->close();

// Fetch dropdown years once for reuse
$teacher_dropdown_years = getTeacherDropdownYears();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subjects & Classes - <?= htmlspecialchars($selected_course) ?></title>
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; color: white; }
        .badge-major { background-color: #3b82f6; }
        .badge-minor { background-color: #6b7280; }
    </style>
</head>
<body>
<?php include PROJECT_ROOT . '/teachersportal/sidebar.php'; ?>

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

    <h1>Subjects & Classes - <?= htmlspecialchars($selected_course) ?></h1>
    <p>Manage subjects, classes, and assignments</p>

    <form method="GET" style="margin-bottom:15px;">
        <label>Select Year Level:</label>
        <select name="year_level" onchange="this.form.submit()">
            <?php if ($is_admin): ?>
                <option value="">All Years</option>
            <?php endif; ?>
            <?php foreach ($teacher_dropdown_years as $yl): ?>
                <option value="<?= htmlspecialchars($yl) ?>" <?= ($year_level == $yl) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($yl) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- SUBJECT MANAGEMENT -->
    <div class="section-box">
        <div class="top-bar">
            <h2>Subject Management</h2>
            <?php if ($is_admin): ?>
                <a href="<?= BASE_URL ?>teachers_access/addsubject.php">
                    <button><i class="fas fa-plus"></i> Add Subject</button>
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
                        <th>Type</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($subjects_query && $subjects_query->num_rows > 0): ?>
                    <?php while ($subject = $subjects_query->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($subject['code']) ?></td>
                            <td><?= htmlspecialchars($subject['subject_name']) ?></td>
                            <td><?= htmlspecialchars($subject['year_level']) ?></td>
                            <td>
                                <span class="badge <?= $subject['subject_type'] == 'MINOR' ? 'badge-minor' : 'badge-major' ?>">
                                    <?= strtoupper($subject['subject_type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($subject['description']) ?></td>
                            <td>
                                <button onclick="openModal(
                                    '<?= htmlspecialchars(addslashes($subject['subject_name'])) ?>',
                                    '<?= htmlspecialchars(addslashes($subject['description'])) ?>'
                                )">
                                    <i class="fas fa-info-circle"></i> Details
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6">No subjects found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CLASS MANAGEMENT -->
    <div class="section-box">
        <div class="top-bar">
            <div>
                <h2>Class Management</h2>
                <p style="margin-top:5px;">Manage all classes and sections</p>
                <?php if (!$is_admin): ?>
                    <p style="margin-top:5px;">Click cards to View List of Students</p>
                <?php endif; ?>
            </div>
            <?php if ($is_admin): ?>
                <a href="<?= BASE_URL ?>teachers_access/addclass.php">
                    <button><i class="fas fa-plus"></i> Add Class</button>
                </a>
            <?php endif; ?>
        </div>

        <div class="cards" style="margin-top:20px;">
        <?php if ($classes_query && $classes_query->num_rows > 0): ?>
            <?php while ($class = $classes_query->fetch_assoc()): ?>
                <?php
                // Subject count for this class (prepared)
                $stmt_sc = $conn->prepare("SELECT COUNT(*) AS count FROM subjects WHERE year_level=? AND section=? AND course_id=?");
                $stmt_sc->bind_param("ssi", $class['year_level'], $class['section'], $course_id);
                $stmt_sc->execute();
                $subject_count = $stmt_sc->get_result()->fetch_assoc()['count'];
                $stmt_sc->close();
                ?>
                <div class="card <?= !$is_admin ? 'clickable-card' : '' ?>"
                     data-id="<?= $class['id'] ?>"
                     data-section="<?= htmlspecialchars($class['section'], ENT_QUOTES) ?>"
                     data-year="<?= htmlspecialchars($class['year_level'], ENT_QUOTES) ?>"
                     <?php if (!$is_admin): ?>
                         onclick="openStudentModal(<?= $class['id'] ?>, '<?= addslashes($class['section']) ?>', '<?= addslashes($class['year_level']) ?>')"
                         style="cursor:pointer;"
                     <?php endif; ?>>

                    <h3>Section <?= htmlspecialchars($class['section']) ?></h3>
                    <p>Year Level: <?= htmlspecialchars($class['year_level']) ?></p>
                    <p><?= $class['student_count'] ?> Students | <?= $subject_count ?> Subjects</p>

                    <div class="card-actions">
                        <?php if ($is_admin): ?>
                            <a href="#" class="edit-class" title="Edit" onclick="event.stopPropagation();"><i class="fas fa-pencil-alt"></i></a>
                            <a href="#" class="delete-class" title="Delete" onclick="event.stopPropagation();"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No classes found for this course.</p>
        <?php endif; ?>
        </div>
    </div>

    <!-- Edit Class Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fas fa-edit"></i> Edit Class</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="update_class_id" id="edit_id">
                <div class="form-group">
                    <label for="edit_section">Section</label>
                    <input type="text" name="section" id="edit_section" required maxlength="1" pattern="[A-E]">
                </div>
                <div class="form-group">
                    <label for="edit_year_level">Year Level</label>
                    <select name="year_level" id="edit_year_level" required>
                        <?php foreach ($teacher_dropdown_years as $yl): ?>
                            <option value="<?= htmlspecialchars($yl) ?>"><?= htmlspecialchars($yl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-outline" id="cancelEditBtn">Cancel</button>
                    <button type="submit" class="btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content small">
            <span class="close close-delete-modal">&times;</span>
            <h2><i class="fas fa-exclamation-triangle" style="color:#dc3545;"></i> Confirm Delete</h2>
            <p>Are you sure you want to delete this class? This action cannot be undone.</p>
            <div class="modal-actions">
                <button id="cancelDeleteBtn" class="btn-outline">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn-danger">Delete</a>
            </div>
        </div>
    </div>

    <!-- Subject Details Modal -->
    <div id="subjectDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="subjectModalTitle"><i class="fas fa-info-circle"></i> Subject Details</h2>
            <div id="subjectModalContent" style="margin-top:20px; line-height:1.6;">Loading...</div>
            <div class="modal-actions" style="margin-top:20px;">
                <button type="button" class="btn-outline" onclick="closeSubjectModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Students List Modal -->
    <div id="studentListModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="studentModalTitle">Students in Class</h2>
            <table id="studentsTable" style="width:100%; margin-top:20px;">
                <thead>
                    <tr><th>#</th><th>Student Name</th><th>Student ID</th></tr>
                </thead>
                <tbody id="studentsTableBody">
                    <tr><td colspan="3">Loading...</td></tr>
                </tbody>
            </table>
            <div class="modal-actions" style="margin-top:20px;">
                <button type="button" class="btn-outline" onclick="closeStudentModal()">Close</button>
            </div>
        </div>
    </div>

</div><!-- /.content -->

<script>
document.addEventListener("DOMContentLoaded", function () {

    var editModal        = document.getElementById("editModal");
    var deleteModal      = document.getElementById("deleteModal");
    var studentListModal = document.getElementById("studentListModal");

    // ── Edit class ──────────────────────────────────────────
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

    document.getElementById("cancelEditBtn").addEventListener("click", function () {
        editModal.style.display = "none";
    });

    // ── Delete class ─────────────────────────────────────────
    document.querySelectorAll(".delete-class").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            var card = btn.closest(".card");
            document.getElementById("confirmDeleteBtn").href = '?delete=' + card.dataset.id;
            deleteModal.style.display = "flex";
        });
    });

    document.getElementById("cancelDeleteBtn").addEventListener("click", function () {
        deleteModal.style.display = "none";
    });

    // ── Universal close (×) buttons ──────────────────────────
    document.querySelectorAll(".close").forEach(function (span) {
        span.addEventListener("click", function () {
            document.querySelectorAll(".modal").forEach(function (m) {
                m.style.display = "none";
            });
        });
    });

    // ── Close on outside click ───────────────────────────────
    window.addEventListener("click", function (e) {
        [editModal, deleteModal, studentListModal,
         document.getElementById("subjectDetailsModal")].forEach(function (m) {
            if (e.target === m) m.style.display = "none";
        });
    });

    // ── Subject Details Modal ────────────────────────────────
    window.openModal = function (subjectName, description) {
        document.getElementById("subjectModalTitle").textContent = subjectName;
        document.getElementById("subjectModalContent").innerHTML =
            "<p><strong>Description:</strong></p><p>" +
            description.replace(/\n/g, "<br>") + "</p>";
        document.getElementById("subjectDetailsModal").style.display = "flex";
    };

    window.closeSubjectModal = function () {
        document.getElementById("subjectDetailsModal").style.display = "none";
    };

    // ── Student List Modal ───────────────────────────────────
    window.openStudentModal = function (id, section, year_level) {
        document.getElementById("studentModalTitle").textContent =
            "Students - Section " + section + " (" + year_level + ")";
        var tbody = document.getElementById("studentsTableBody");
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">Loading...</td></tr>';
        studentListModal.style.display = "flex";

        fetch("subjects.php?action=list_students&class_id=" + id +
              "&section=" + encodeURIComponent(section) +
              "&year_level=" + encodeURIComponent(year_level))
            .then(function (r) { return r.json(); })
            .then(function (students) {
                tbody.innerHTML = "";
                if (students.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#666;">No students enrolled</td></tr>';
                } else {
                    students.forEach(function (student, index) {
                        var row = tbody.insertRow();
                        row.insertCell(0).textContent = index + 1;
                        row.insertCell(1).textContent = student.full_name  || "N/A";
                        row.insertCell(2).textContent = student.student_id || "N/A";
                    });
                }
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#dc3545;">Error loading students</td></tr>';
            });
    };

    window.closeStudentModal = function () {
        studentListModal.style.display = "none";
    };

    // ── Auto-dismiss alerts ──────────────────────────────────
    setTimeout(function () {
        document.querySelectorAll(".alert").forEach(function (a) {
            a.style.transition = "opacity 0.3s ease-out";
            a.style.opacity    = "0";
            setTimeout(function () { a.remove(); }, 300);
        });
    }, 5000);

    document.addEventListener("click", function (e) {
        if (e.target.classList.contains("alert-close")) {
            var a = e.target.closest(".alert");
            a.style.transition = "opacity 0.3s ease-out";
            a.style.opacity    = "0";
            setTimeout(function () { a.remove(); }, 300);
        }
    });
});
</script>
</body>
</html>