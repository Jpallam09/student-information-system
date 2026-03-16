<?php
session_start();
include '../config/database.php';
include '../config/teacher_filter.php';

// ================== CHECK LOGIN ==================
if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../Accesspage/teacher_login.php");
    exit();
}

// ================== ADMIN CHECK ==================
$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

// ================== BUILD TEACHER FILTER ==================
$subject_year_filter = '';
$subject_section_filter = '';

$class_year_filter = '';
$class_section_filter = '';

if (!$is_admin) {
    $subject_year_filter = getYearLevelFilter('s.year_level');
    $subject_section_filter = getSectionFilter('s.section');

    $class_year_filter = getYearLevelFilter('c.year_level');
    $class_section_filter = getSectionFilter('c.section');
}

// ================== DELETE CLASS ==================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    if ($is_admin) {
        mysqli_query($conn, "DELETE FROM classes WHERE id='$delete_id'");
    }
    header("Location: subjects.php");
    exit();
}

if (isset($_POST['delete_class_id'])) {
    $delete_id = intval($_POST['delete_class_id']);
    mysqli_query($conn, "DELETE FROM classes WHERE id='$delete_id'");
    header("Location: subjects.php");
    exit();
}


// ================== UPDATE CLASS ==================
if (isset($_POST['update_class_id'])) {

    $update_id = intval($_POST['update_class_id']);
    $section = mysqli_real_escape_string($conn, $_POST['section']);
    $year_level_post = mysqli_real_escape_string($conn, $_POST['year_level']);

    mysqli_query(
        $conn,
        "UPDATE classes 
         SET section='$section', year_level='$year_level_post' 
         WHERE id='$update_id'"
    );

    header("Location: subjects.php");
    exit();
}

// ================== BACK BUTTON ==================
$back_url = "../Accesspage/teacher_login.php";

if ($is_admin) {
    $back_url = "../teachersportal/chooseSub.php";
}

// ================== COURSE SESSION ==================
$selected_course = $_SESSION['teacher_course'] ?? '';

if (empty($selected_course)) {
    echo "Course not assigned to this teacher. Contact admin.";
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

$allowed_courses = ['BSIT', 'BSED', 'BAT', 'BTVTED'];

if (!in_array(strtoupper($selected_course), $allowed_courses)) {
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="chooseSub.php">← Go Back</a>';
    exit();
}

// ================== GET COURSE ID ==================
$course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$selected_course'");

if (!$course_result || mysqli_num_rows($course_result) == 0) {
    die("Selected course does not exist in the database.");
}

$course_row = mysqli_fetch_assoc($course_result);
$course_id = $course_row['id'];

// ================== YEAR FILTER ==================
$year_level = $_GET['year_level'] ?? '';
$year_sql = $year_level ? " AND s.year_level='$year_level'" : "";

// ================== FETCH SUBJECTS ==================
$subjects_query = mysqli_query(
    $conn,
    "SELECT * FROM subjects s 
     WHERE course_id='$course_id'
     $subject_year_filter
     $subject_section_filter
     $year_sql
     ORDER BY s.year_level ASC, s.subject_name ASC"
);

// ================== FETCH CLASSES ==================
$classes_query = mysqli_query(
    $conn,
    "SELECT c.id, c.section, c.year_level,
            COUNT(s.id) AS student_count
     FROM classes c
     LEFT JOIN students s 
     ON s.section = c.section 
     AND s.year_level = c.year_level 
     AND s.course = '$selected_course'
     WHERE c.course_id = '$course_id'
     $class_year_filter
     $class_section_filter
     GROUP BY c.id, c.section, c.year_level
     ORDER BY c.section ASC"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<title>Subjects & Classes - <?= htmlspecialchars($selected_course) ?></title>

<link rel="stylesheet" href="../css/teacherportal.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="content">

<h1>Subjects & Classes - <?= htmlspecialchars($selected_course) ?></h1>

<p>Manage subjects, classes, and assignments</p>

<form method="GET" style="margin-bottom:15px;">

<label>Select Year Level:</label>

<select name="year_level" onchange="this.form.submit()">

<option value="">All Years</option>

<option value="1st Year" <?= ($year_level == '1st Year') ? 'selected' : '' ?>>1st Year</option>

<option value="2nd Year" <?= ($year_level == '2nd Year') ? 'selected' : '' ?>>2nd Year</option>

<option value="3rd Year" <?= ($year_level == '3rd Year') ? 'selected' : '' ?>>3rd Year</option>

<option value="4th Year" <?= ($year_level == '4th Year') ? 'selected' : '' ?>>4th Year</option>

</select>

</form>

<!-- SUBJECT MANAGEMENT -->

<div class="section-box">

<div class="top-bar">

<h2>Subject Management</h2>

<?php if ($is_admin): ?>

<a href="../teachers_access/addsubject.php">

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
<th>Description</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php

if (mysqli_num_rows($subjects_query) > 0) {

while ($subject = mysqli_fetch_assoc($subjects_query)):

?>

<tr>

<td><?= htmlspecialchars($subject['code']) ?></td>

<td><?= htmlspecialchars($subject['subject_name']) ?></td>

<td><?= htmlspecialchars($subject['year_level']) ?></td>

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

<?php endwhile;

} else {

echo "<tr><td colspan='5'>No subjects found.</td></tr>";

}

?>

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

</div>

<?php if ($is_admin): ?>

<a href="../teachers_access/addclass.php">

<button><i class="fas fa-plus"></i> Add Class</button>

</a>

<?php endif; ?>

</div>

    <div class="cards" style="margin-top:20px;">

<?php

if (mysqli_num_rows($classes_query) > 0) {

while ($class = mysqli_fetch_assoc($classes_query)):

?>

<div class="card" 
     data-id="<?= $class['id'] ?>" 
     data-section="<?= htmlspecialchars($class['section'],ENT_QUOTES) ?>" 
     data-year="<?= htmlspecialchars($class['year_level'],ENT_QUOTES) ?>">

<h3>Section <?= htmlspecialchars($class['section']) ?></h3>

<p>Year Level: <?= htmlspecialchars($class['year_level']) ?></p>


        <p><?= $class['student_count'] ?> Students</p>
        
        <?php if ($is_admin): ?>
        <div class="card-actions">
            <a href="#" class="edit-class" title="Edit"><i class="fas fa-pencil"></i></a>
            <a href="#" class="delete-class" title="Delete"><i class="fas fa-trash"></i></a>
        </div>
        <?php endif; ?>



</div>

<?php endwhile;

} else {

echo "<p>No classes found for this course.</p>";

}

?>

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
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
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
        <span class="close-delete-modal close">&times;</span>
        <h2><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Confirm Delete</h2>
        <p>Are you sure you want to delete this class? This action cannot be undone.</p>
        <div class="modal-actions">
            <button id="cancelDeleteBtn" class="btn-outline">Cancel</button>
            <a href="#" id="confirmDeleteBtn" class="btn-danger">Delete</a>
        </div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function(){

    var editModal = document.getElementById("editModal");
    var deleteModal = document.getElementById("deleteModal");
    var editForm = document.getElementById("editForm");
    let deleteUrl = '';

    // Edit class events
    document.querySelectorAll(".edit-class").forEach(function(btn){
        btn.addEventListener("click", function(e){
            e.preventDefault();
            var card = btn.closest(".card");
            document.getElementById("edit_id").value = card.dataset.id;
            document.getElementById("edit_section").value = card.dataset.section;
            document.getElementById("edit_year_level").value = card.dataset.year;
            editModal.style.display = "flex";
        });
    });

    // Delete class events
    document.querySelectorAll(".delete-class").forEach(function(btn){
        btn.addEventListener("click", function(e){
            e.preventDefault();
            var card = btn.closest(".card");
            deleteUrl = '?delete=' + card.dataset.id;
            document.getElementById("confirmDeleteBtn").href = deleteUrl;
            deleteModal.style.display = "flex";
        });
    });

    // Close modals
    document.querySelectorAll(".close").forEach(function(span){
        span.addEventListener("click", function(){ 
            editModal.style.display = "none";
            deleteModal.style.display = "none";
        });
    });

    // Delete modal specific close buttons
    document.querySelector('.close-delete-modal').addEventListener("click", function(){
        deleteModal.style.display = "none";
    });
    
    document.getElementById('cancelDeleteBtn').addEventListener("click", function(){
        deleteModal.style.display = "none";
    });


    // Close on outside click
    window.onclick = function(event) {
        if (event.target == editModal) editModal.style.display = "none";
        if (event.target == deleteModal) deleteModal.style.display = "none";
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = "none";
    }

    // Edit modal cancel button
    document.getElementById('cancelEditBtn').addEventListener("click", function(){
        editModal.style.display = "none";
    });
});
</script>


</body>

</html>
