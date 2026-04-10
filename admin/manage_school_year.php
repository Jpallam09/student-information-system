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
    file_put_contents('delete_log.txt', date('Y-m-d H:i:s') . " - POST DATA: " . json_encode($_POST) . "", FILE_APPEND | LOCK_EX);
    
    if (isset($_POST['delete_year']) && !empty($_POST['delete_year'])) {
        $year_id = (int)$_POST['delete_year'];
        // Check if active
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
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/teacherportal.css'); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include PROJECT_ROOT . '/teachersportal/sidebar.php'; ?>

<div class="content">
    <!-- Header -->
    <div class="announcement-header">
        <div class="header-left">
            <div>
                <h1><i class="fas fa-calendar-alt"></i> Manage School Year</h1>
                <p class="header-subtitle">Control active semesters and academic calendar</p>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success_msg): ?>
        <div class="alert alert-success">
    <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error">
    <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Current Active School Year Card -->
    <?php 
    $active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM school_years WHERE is_active=1"));
    ?>
    <div class="card modern-active-card">
        <div class="card-header emerald-gradient">
            <div class="status-indicator"></div>
            <h3><i class="fas fa-crown"></i> Currently Active</h3>
        </div>
        <div class="card-body">
            <?php if ($active): ?>
                <div class="active-display">
                    <i class="fas fa-check-circle" style="color: var(--accent-emerald); margin-right: 12px; font-size: 1.5rem;"></i>
                    <strong><?= htmlspecialchars($active['school_year'] . ' - ' . $active['semester'] . ' Semester') ?></strong>
                </div>
            <?php else: ?>
                <p style="padding: 20px; text-align: center; color: var(--text-muted);">
                    <i class="fas fa-info-circle"></i> No active school year set. 
                    <a href="#add-form" style="color: var(--primary-blue);">Add one now</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- All School Years Table -->
    <form id="yearsForm" method="POST">
        <div class="section-box">
            <h2><i class="fas fa-list"></i> All School Years</h2>
            <div class="table-container modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th class="sortable">School Year</th>
                            <th class="sortable">Semester</th>
                            <th class="sortable">Status</th>
                            <th>Action</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($school_years as $year): ?>
                        <tr data-id="<?= $year['id'] ?>">
                            <td data-label="School Year"><?= htmlspecialchars($year['school_year']) ?></td>
                            <td data-label="Semester"><?= $year['semester'] ?> Semester</td>
                            <td data-label="Status">
                                <?= $year['is_active'] ? '<span class="badge-active"><i class="fas fa-check-circle"></i> Active</span>' : '<span class="badge-inactive"><i class="fas fa-circle"></i> Inactive</span>' ?>
                            </td>
                            <td data-label="Action">
                                <button type="submit" name="set_active" value="<?= $year['id'] ?>" class="btn <?= $year['is_active'] ? 'btn-success' : 'btn-primary' ?>" title="<?= $year['is_active'] ? 'Currently Active' : 'Make Active' ?>">
                                    <i class="fas fa-<?= $year['is_active'] ? 'check-circle' : 'play' ?>"></i>
                                    <?= $year['is_active'] ? 'Active' : 'Set Active' ?>
                                </button>
                            </td>
                            <td data-label="Delete">
                                <button type="button" class="btn btn-danger delete-schedule" data-id="<?= $year['id'] ?>" <?= $year['is_active'] ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
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

    <!-- Add New School Year Form -->
    <div class="section-box" id="add-form">
        <h2><i class="fas fa-plus-circle"></i> Add New School Year</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> School Year</label>
                    <input type="text" name="school_year" pattern="^\d{4}-\d{4}$" maxlength="9" 
                           oninput="this.value = this.value.replace(/[^0-9-]/g, '')" 
                           required placeholder="e.g., 2024-2025" class="input-field">
                    <small style="color: var(--text-muted);">Format: YYYY-YYYY (e.g., 2024-2025)</small>
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
                    Undo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content small">
        <span class="close-modal">&times;</span>
        <h2><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h2>
        <p>Are you sure you want to delete this school year and semester?</p>
        <p style="color: var(--accent-rose); font-size: 0.9rem;"><strong>This action cannot be undone.</strong></p>
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
    // Table search functionality
    const searchInput = document.getElementById('yearSearch');
    const table = document.querySelector('.modern-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    let sortDirection = {};

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // Sortable headers
    table.querySelectorAll('th.sortable').forEach((th, index) => {
        th.addEventListener('click', () => {
            const dir = sortDirection[index] === 'asc' ? 'desc' : 'asc';
            sortDirection[index] = dir;

            // Remove previous sort classes
            table.querySelectorAll('th').forEach(h => {
                h.classList.remove('sort-asc', 'sort-desc');
            });

            th.classList.add(`sort-${dir}`);

            rows.sort((a, b) => {
                let aVal = a.cells[index].textContent.trim();
                let bVal = b.cells[index].textContent.trim();

                if (index === 2) { // Status column
                    aVal = aVal.includes('Active') ? 1 : 0;
                    bVal = bVal.includes('Active') ? 1 : 0;
                    if (dir === 'asc') {
                        return aVal - bVal;
                    } else {
                        return bVal - aVal;
                    }
                }

                if (dir === 'asc') {
                    return aVal.localeCompare(bVal);
                } else {
                    return bVal.localeCompare(aVal);
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // Auto-hide messages after 5 seconds
    const messages = document.querySelectorAll('.alert');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.animation = 'slideOutMessage 0.4s ease-out forwards';
            setTimeout(() => msg.remove(), 400);
        }, 5000);
    });

    // Close alert messages with X button
    document.querySelectorAll('.alert-close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            this.parentElement.style.animation = 'slideOutMessage 0.4s ease-out forwards';
            setTimeout(() => this.parentElement.remove(), 400);
        });
    });

    // Delete modal functionality
    let deleteModal = document.getElementById("deleteModal");
    let deleteId = null;

    // Open delete modal
    document.querySelectorAll(".delete-schedule").forEach(function(btn){
        btn.addEventListener("click", function(e){
            e.preventDefault();
            if (this.disabled) return;
            deleteId = this.dataset.id;
            deleteModal.style.display = "flex";
        });
    });

    // Close modal functions
    function closeModal() {
        deleteModal.style.display = "none";
        deleteId = null;
    }

    document.querySelectorAll(".close-modal, #cancelDeleteBtn").forEach(function(el){
        el.addEventListener("click", closeModal);
    });

    // Confirm delete - submit form
    document.getElementById("confirmDeleteBtn").addEventListener("click", function(){
        if (deleteId) {
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_year';
            input.value = deleteId;
            document.getElementById('yearsForm').appendChild(input);
            document.getElementById('yearsForm').submit();
        }
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == deleteModal) {
            closeModal();
        }
    };

    // Add animation for slide out
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideOutMessage {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
});
</script>

</body>
</html>