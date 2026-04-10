<?php
/* ============================================================
   manage_school_year.php — PHP logic preserved exactly.
   HTML/UI unified with students.php modern style.
   ============================================================ */
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

if (!isset($_SESSION['teacher_id']) || !in_array($_SESSION['teacher_type'], ['Administrator', 'Seeder'])) {
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents('delete_log.txt', date('Y-m-d H:i:s') . " - POST DATA: " . json_encode($_POST) . "", FILE_APPEND | LOCK_EX);

    if (isset($_POST['delete_year']) && !empty($_POST['delete_year'])) {
        $year_id = (int)$_POST['delete_year'];
        $active_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT is_active FROM school_years WHERE id = $year_id"));
        if ($active_check && $active_check['is_active']) {
            $error_msg = 'Cannot delete active school year. Set another active first.';
        } else {
            $delete = mysqli_query($conn, "DELETE FROM school_years WHERE id = $year_id");
            $affected = mysqli_affected_rows($conn);
            file_put_contents('delete_log.txt', date('Y-m-d H:i:s') . " - Delete attempt id: $year_id, affected: $affected, error: " . mysqli_error($conn) . "", FILE_APPEND | LOCK_EX);
            if ($delete && $affected > 0) {
                $success_msg = 'School year deleted successfully.';
            } else {
                $error_msg = 'No school year found or already deleted. (Affected: ' . $affected . ')';
            }
        }
    } elseif (isset($_POST['add_year']) && !empty($_POST['school_year'])) {
        $school_year = mysqli_real_escape_string($conn, $_POST['school_year']);
        $semester = $_POST['semester'];
        $insert = mysqli_query($conn, "INSERT INTO school_years (school_year, semester) VALUES ('$school_year', '$semester')");
        if ($insert) { $success_msg = 'New school year added.'; }
        else { $error_msg = 'Error adding year (duplicate?): ' . mysqli_error($conn); }
    } elseif (isset($_POST['set_active']) && !empty($_POST['set_active'])) {
        $year_id = (int)$_POST['set_active'];
        mysqli_query($conn, "UPDATE school_years SET is_active = 0");
        $update = mysqli_query($conn, "UPDATE school_years SET is_active = 1 WHERE id = $year_id");
        if ($update) { $success_msg = 'Active school year updated successfully.'; }
        else { $error_msg = 'Error updating active year.'; }
    }
}

$school_years = [];
$result = mysqli_query($conn, "SELECT * FROM school_years ORDER BY school_year DESC, semester ASC");
while ($row = mysqli_fetch_assoc($result)) {
    $school_years[] = $row;
}

$active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM school_years WHERE is_active=1"));
$total_years = count($school_years);
$inactive_years = count(array_filter($school_years, fn($y) => !$y['is_active']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage School Year | Admin</title>
    <link rel="icon" href="<?= asset('images/622685015_925666030131412_6886851389087569993_n.jpg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include PROJECT_ROOT . '/teachersportal/sidebar.php'; ?>

<div class="content">

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- ── PAGE HEADER ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow">Admin Panel</div>
            <h1 class="page-header-title">Manage School Year</h1>
        </div>
        <?php if ($total_years > 0): ?>
            <span class="result-count">
                <i class="fas fa-calendar-alt"></i>
                <?= $total_years ?> school year<?= $total_years !== 1 ? 's' : '' ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- ── STATS ROW ── -->
    <div class="stats-row">
        <div class="mini-stat">
            <div class="mini-stat-label"><i class="fas fa-calendar-alt" style="margin-right:4px;color:var(--primary-blue);"></i> Total</div>
            <div class="mini-stat-value"><?= $total_years ?></div>
        </div>
        <div class="mini-stat active-stat">
            <div class="mini-stat-label"><i class="fas fa-check-circle" style="margin-right:4px;color:var(--accent-emerald);"></i> Active</div>
            <div class="mini-stat-value"><?= $active ? 1 : 0 ?></div>
        </div>
        <div class="mini-stat inactive-stat">
            <div class="mini-stat-label"><i class="fas fa-circle" style="margin-right:4px;color:var(--slate-400);"></i> Inactive</div>
            <div class="mini-stat-value"><?= $inactive_years ?></div>
        </div>
    </div>

    <!-- ── ACTIVE YEAR BANNER ── -->
    <?php if ($active): ?>
    <div class="active-year-card">
        <div class="active-year-icon"><i class="fas fa-crown"></i></div>
        <div>
            <div class="active-year-label"><i class="fas fa-circle" style="font-size:6px;margin-right:5px;"></i> Currently Active</div>
            <div class="active-year-value">
                <?= htmlspecialchars($active['school_year'] . ' — ' . $active['semester'] . ' Semester') ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="active-year-none">
        <i class="fas fa-info-circle" style="font-size:1.2rem;flex-shrink:0;"></i>
        No active school year is set. <a href="#add-form" style="color:var(--primary-blue);font-weight:600;margin-left:4px;">Add one below →</a>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════
         ALL SCHOOL YEARS TABLE
    ══════════════════════════════════ -->
    <form id="yearsForm" method="POST">
        <div class="section-box">
            <div class="section-heading">
                <div class="section-heading-left">
                    <div class="section-heading-icon"><i class="fas fa-list"></i></div>
                    <div>
                        <h2 class="section-heading-title">All School Years</h2>
                        <p class="section-heading-sub">Click "Set Active" to switch the current academic period</p>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>School Year</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Set Active</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($school_years as $year): ?>
                        <tr data-id="<?= $year['id'] ?>">
                            <td style="font-family:monospace;font-size:0.9rem;font-weight:600;color:var(--slate-800);">
                                <?= htmlspecialchars($year['school_year']) ?>
                            </td>
                            <td><?= $year['semester'] ?> Semester</td>
                            <td>
                                <?php if ($year['is_active']): ?>
                                    <span class="badge-active"><i class="fas fa-check-circle"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive"><i class="fas fa-circle"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="submit" name="set_active" value="<?= $year['id'] ?>"
                                        class="btn-set-active <?= $year['is_active']?'is-active':'' ?>"
                                        title="<?= $year['is_active']?'Currently Active':'Make Active' ?>">
                                    <i class="fas fa-<?= $year['is_active']?'check-circle':'play' ?>"></i>
                                    <?= $year['is_active'] ? 'Active' : 'Set Active' ?>
                                </button>
                            </td>
                            <td>
                                <button type="button"
                                        class="btn-tbl-delete delete-schedule"
                                        data-id="<?= $year['id'] ?>"
                                        <?= $year['is_active'] ? 'disabled' : '' ?>>
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <!-- ══════════════════════════════════
         ADD NEW SCHOOL YEAR
    ══════════════════════════════════ -->
    <div class="section-box add-form-box" id="add-form">
        <div class="section-heading">
            <div class="section-heading-left">
                <div class="section-heading-icon"><i class="fas fa-plus-circle"></i></div>
                <div>
                    <h2 class="section-heading-title">Add New School Year</h2>
                    <p class="section-heading-sub">Format: YYYY-YYYY (e.g., 2024-2025)</p>
                </div>
            </div>
        </div>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> School Year</label>
                    <input type="text" name="school_year"
                           pattern="^\d{4}-\d{4}$" maxlength="9"
                           oninput="this.value=this.value.replace(/[^0-9-]/g,'')"
                           required placeholder="e.g., 2024-2025" class="input-field">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-layer-group"></i> Semester</label>
                    <select name="semester" class="input-field" required>
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                    </select>
                </div>
            </div>
            <div class="action-buttons">
                <button type="submit" name="add_year" class="btn-add">
                    <i class="fas fa-save"></i> Add School Year
                </button>
                <button type="reset" class="btn-cancel">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

</div><!-- /.content -->

<!-- ── DELETE MODAL ── -->
<div id="deleteModal" class="modal">
    <div class="modal-content small">
        <span class="close-modal">&times;</span>
        <h2><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h2>
        <p>Are you sure you want to delete this school year and semester?</p>
        <p style="color:var(--accent-rose);font-size:0.9rem;"><strong>This action cannot be undone.</strong></p>
        <div class="modal-actions">
            <button id="cancelDeleteBtn" class="btn-cancel">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button id="confirmDeleteBtn" class="btn-danger">
                <i class="fas fa-trash-alt"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var deleteModal = document.getElementById("deleteModal");
    var deleteId    = null;

    /* Open delete modal */
    document.querySelectorAll(".delete-schedule").forEach(function(btn){
        btn.addEventListener("click", function(e){
            e.preventDefault();
            if (this.disabled) return;
            deleteId = this.dataset.id;
            deleteModal.style.display = "flex";
        });
    });

    /* Close helpers */
    function closeModal() { deleteModal.style.display = "none"; deleteId = null; }

    document.querySelectorAll(".close-modal, #cancelDeleteBtn").forEach(function(el){
        el.addEventListener("click", closeModal);
    });

    /* Confirm delete */
    document.getElementById("confirmDeleteBtn").addEventListener("click", function(){
        if (deleteId) {
            var input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'delete_year';
            input.value = deleteId;
            document.getElementById('yearsForm').appendChild(input);
            document.getElementById('yearsForm').submit();
        }
    });

    /* Close on outside click */
    window.onclick = function(event) {
        if (event.target == deleteModal) closeModal();
    };

    /* Auto-dismiss alerts */
    setTimeout(function(){
        document.querySelectorAll('.alert').forEach(function(a){
            a.style.transition = 'opacity 0.3s ease-out';
            a.style.opacity = '0';
            setTimeout(function(){ a.remove(); }, 300);
        });
    }, 5000);

    document.querySelectorAll('.alert-close').forEach(function(btn){
        btn.addEventListener('click', function(){
            var a = this.parentElement;
            a.style.transition = 'opacity 0.3s ease-out';
            a.style.opacity = '0';
            setTimeout(function(){ a.remove(); }, 300);
        });
    });
});
</script>
</body>
</html>