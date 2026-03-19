<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['teacher_id']) || !in_array($_SESSION['teacher_type'], ['Administrator', 'Seeder'])) {
    header("Location: ../Accesspage/teacher_login.php");
    exit();
}

$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['set_active']) && !empty($_POST['set_active'])) {
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
    } elseif (isset($_POST['add_year'])) {
        $school_year = mysqli_real_escape_string($conn, $_POST['school_year']);
        $semester = $_POST['semester'];
        $insert = mysqli_query($conn, "INSERT INTO school_years (school_year, semester) VALUES ('$school_year', '$semester')");
        if ($insert) {
            $success_msg = 'New school year added.';
        } else {
            $error_msg = 'Error adding year: ' . mysqli_error($conn);
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
    <link rel="stylesheet" href="../css/teacherportal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include '../teachersportal/sidebar.php'; ?>

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
    <div class="card active-year-card">
        <h3><i class="fas fa-crown"></i> Currently Active</h3>
        <?php if ($active): ?>
            <div class="active-display">
                <strong><?= htmlspecialchars($active['school_year'] . ' ' . $active['semester'] . ' Semester') ?></strong>
                <form method="POST" style="display:inline;">
                    <button type="submit" name="set_active" value="<?= $active['id'] ?>" class="btn btn-secondary">Keep Active</button>
                </form>
            </div>
        <?php else: ?>
            <p>No active school year set. <a href="#add-form">Add one now</a></p>
        <?php endif; ?>
    </div>

    <!-- List All Years -->
    <div class="card">
        <h3><i class="fas fa-list"></i> All School Years</h3>
        <form method="POST">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($school_years as $year): ?>
                        <tr>
                            <td><?= htmlspecialchars($year['school_year']) ?></td>
                            <td><?= $year['semester'] ?> Sem</td>
                            <td><?= $year['is_active'] ? '<span class="badge-blue">Active</span>' : '<span class="badge-red">Inactive</span>' ?></td>
                            <td>
                                <button type="submit" name="set_active" value="<?= $year['id'] ?>" class="btn <?= $year['is_active'] ? 'btn-secondary' : 'btn-primary' ?>">
                                    <?= $year['is_active'] ? 'Active' : 'Set Active' ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <!-- Add New Year Form -->
    <div class="section-box" id="add-form">
        <h2><i class="fas fa-plus"></i> Add New School Year</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>School Year (e.g., 2025-2026)</label>
                    <input type="text" name="school_year" pattern="\d{4}-\d{4}" required placeholder="2025-2026" class="input-field">
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" class="input-field" required>
                        <option value="1st">1st Semester</option>
                        <option value="2nd">2nd Semester</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_year" class="btn-primary">Add School Year</button>
        </form>
    </div>
</div>

</body>
</html>
