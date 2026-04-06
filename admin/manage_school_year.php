<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

if (!isset($_SESSION['teacher_id']) || !in_array($_SESSION['teacher_type'], ['Administrator', 'Seeder'])) {
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // DEBUG: Log full POST data
    file_put_contents('delete_log.txt', date('Y-m-d H:i:s') . " - POST DATA: " . json_encode($_POST) . "\n", FILE_APPEND | LOCK_EX);
    
    if (isset($_POST['delete_year']) && !empty($_POST['delete_year'])) {
        $year_id = (int)$_POST['delete_year'];
        // Check if active
        $active_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT is_active FROM school_years WHERE id = $year_id"));
        if ($active_check && $active_check['is_active']) {
            $error_msg = 'Cannot delete active school year. Set another active first.';
        } else {
            $delete = mysqli_query($conn, "DELETE FROM school_years WHERE id = $year_id");
            $affected = mysqli_affected_rows($conn);
            file_put_contents('delete_log.txt', date('Y-m-d H:i:s') . " - Delete attempt id: $year_id, affected: $affected, error: " . mysqli_error($conn) . "\n", FILE_APPEND | LOCK_EX);
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
        if ($insert) {
            $success_msg = 'New school year added.';
        } else {
            $error_msg = 'Error adding year (duplicate?): ' . mysqli_error($conn);
        }
    } elseif (isset($_POST['set_active']) && !empty($_POST['set_active'])) {
        $year_id = (int)$_POST['set_active'];
        // Deactivate all
        mysqli_query($conn, "UPDATE school_years SET is_active = 0");
        // Activate selected
        $update = mysqli_query($conn, "UPDATE school_years SET is_active = 1 WHERE id = $year_id");
        if ($update) {
            $success_msg = 'Active school year updated successfully.';
        } else {
            $error_msg = 'Error updating active year.';
        }
    }
}

$school_years = [];
$result = mysqli_query($conn, "SELECT * FROM school_years ORDER BY school_year DESC, semester ASC");
while ($row = mysqli_fetch_assoc($result)) {
    $school_years[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage School Year | Admin</title>
<link rel="stylesheet" href="<?php echo asset('css/teacherportal.css'); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include PROJECT_ROOT . '/teachersportal/sidebar.php'; ?>

<div class="content">
    <div class="announcement-header">
        <h1><i class="fas fa-calendar-alt"></i> Manage School Year & Semester</h1>
    </div>

    <?php if ($success_msg): ?>
        <div class="message success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Current Active -->
    <?php 
    $active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM school_years WHERE is_active=1"));
    ?>
    <div class="card active-year-card" style="border-left: 5px solid var(--accent-emerald); box-shadow: var(--shadow-lg);">
        <div style="background: linear-gradient(135deg, var(--accent-emerald), #059669); padding: 12px 20px; margin: -24px -24px 20px -24px; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
            <h3 style="color: white; margin: 0; display: flex; align-items: center; gap: 10px;"><i class="fas fa-crown"></i> Currently Active</h3>
        </div>
        <?php if ($active): ?>
            <div class="active-display">
                <i class="fas fa-check-circle" style="color: var(--accent-emerald); margin-right: 8px;"></i>
                <strong><?= htmlspecialchars($active['school_year'] . ' ' . $active['semester'] . ' Semester') ?></strong>
            </div>
        <?php else: ?>
            <p>No active school year set. <a href="#add-form">Add one now</a></p>
        <?php endif; ?>
    </div>

    <!-- List All Years FORM -->
    <form id="yearsForm" method="POST">
    <div class="card" style="box-shadow: var(--shadow-md);">
        <div style="background: linear-gradient(135deg, var(--primary-blue), var(--accent-violet)); padding: 16px 24px; margin: -24px -24px 20px -24px; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
            <h3 style="color: white; margin: 0; display: flex; align-items: center; gap: 10px;"><i class="fas fa-list"></i> All School Years</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Semester</th>
                        <th>Status</th>
                        <th>Action</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($school_years as $year): ?>
                    <tr data-id="<?= $year['id'] ?>">
                        <td><?= htmlspecialchars($year['school_year']) ?></td>
                        <td><?= $year['semester'] ?> Sem</td>
                        <td><?= $year['is_active'] ? '<span class="badge-active">Active</span>' : '<span class="badge-inactive">Inactive</span>' ?></td>
                        <td>
                            <button type="submit" name="set_active" value="<?= $year['id'] ?>" class="btn <?= $year['is_active'] ? 'btn-success' : 'btn-primary' ?>" title="<?= $year['is_active'] ? 'Currently Active' : 'Make Active' ?>">
                                <i class="fas fa-<?= $year['is_active'] ? 'check-circle' : 'play' ?>"></i>
                                <?= $year['is_active'] ? 'Active' : 'Set Active' ?>
                            </button>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger delete-schedule" data-id="<?= $year['id'] ?>" <?= $year['is_active'] ? 'disabled title="Cannot delete active year"' : '' ?>>
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </form>

    <!-- Add New Year Form -->
    <div class="section-box" id="add-form" style="box-shadow: var(--shadow-lg); border: 1px solid var(--border-light);">
        <div style="background: linear-gradient(135deg, var(--accent-emerald), #10b981); padding: 20px 24px; margin: -24px -24px 24px -24px; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
            <h2 style="color: white; margin: 0; display: flex; align-items: center; gap: 12px;"><i class="fas fa-plus"></i> Add New School Year</h2>
        </div>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>School Year (e.g., 1978-1979)</label>
                    <input type="text" name="school_year" pattern="^\d{4}-\d{4}$" maxlength="9" oninput="this.value = this.value.replace(/[^0-9-]/g, '')" required placeholder="1978-1979" class="input-field">
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" class="input-field" required>
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_year" class="btn-primary">
                <i class="fas fa-plus"></i> Add School Year
            </button>
        </form>
    </div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content small">
        <span class="close-delete-modal close">&times;</span>
        <h2><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Confirm Delete</h2>
        <p>Are you sure you want to delete this school year and semester? This action cannot be undone.</p>
        <div class="modal-actions">
            <button id="cancelDeleteBtn" class="btn-outline">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button id="confirmDeleteBtn" class="btn-danger">
                <i class="fas fa-trash-alt"></i> Delete
            </button>
        </div>
    </div>
</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    var deleteModal = document.getElementById("deleteModal");
    let targetRow = null;
    let deleteId = null;

    // Auto-hide messages after 5 seconds with slide out
    const messages = document.querySelectorAll('.message');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.animation = 'slideOutMessage 0.4s ease-out forwards';
            setTimeout(() => msg.remove(), 400);
        }, 5000);
    });

    // Open delete modal
    document.querySelectorAll(".delete-schedule").forEach(function(btn){
        btn.addEventListener("click", function(e){
            e.preventDefault();
            deleteId = this.dataset.id;
            targetRow = this.closest('tr');
            deleteModal.style.display = "flex";
        });
    });

    // Close modals
    document.querySelectorAll(".close").forEach(function(span){
        span.addEventListener("click", function(){ 
            deleteModal.style.display = "none";
        });
    });

    document.getElementById('cancelDeleteBtn').addEventListener("click", function(){
        deleteModal.style.display = "none";
    });

    // Confirm delete - SUBMIT FORM
    document.getElementById("confirmDeleteBtn").addEventListener("click", function(){
        if (deleteId && targetRow) {
            // Create hidden input for delete_year
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_year';
            input.value = deleteId;
            document.getElementById('yearsForm').appendChild(input);
            
            // Submit form
            document.getElementById('yearsForm').submit();
        }
    });

    // Close if click outside
    window.onclick = function(event) {
        if (event.target == deleteModal) deleteModal.style.display = "none";
    }
});
</script>

</body>
</html>

