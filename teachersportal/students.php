<?php
session_start();
include '../config/database.php';
include '../config/teacher_filter.php';

// ================== CHECK LOGIN ==================
if(!isset($_SESSION['teacher_id'])){
    header("Location: ../Accesspage/teacher_login.php");
    exit();
}

// ================== CHECK IF ADMIN ==================
$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

// ================== DYNAMIC BACK ARROW LOGIC ==================
$back_url = "../Accesspage/teacher_login.php";
if(isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types)){
    $back_url = "../teachersportal/chooseSub.php";
}

// ================== SET COURSE FROM SESSION ==================
$selected_course = $_SESSION['teacher_course'] ?? '';
if(empty($selected_course)){
    echo "Course not assigned to this teacher. Contact admin.";
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// Allowed courses
$allowed_courses = ['BSIT','BSED','BAT','BTVTED'];
if(!in_array(strtoupper($selected_course), $allowed_courses)){
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="chooseSub.php">← Go Back</a>';
    exit();
}

// ================== BUILD TEACHER FILTER ==================
$teacher_year_filter = '';
$teacher_section_filter = '';
if (!$is_admin) {
    $teacher_year_filter = getYearLevelFilter('year_level');
    $teacher_section_filter = getSectionFilter('section');
}

// Get teacher's assigned year levels for dropdown
$teacher_year_levels = !$is_admin ? getTeacherYearLevels() : ['1st Year','2nd Year','3rd Year','4th Year'];

// YEAR LEVEL FILTER (from URL)
$selected_year = $_GET['year_level'] ?? '';
$year_filter = $selected_year ? " AND year_level='$selected_year'" : "";

// SECTION FILTER
$selected_section = $_GET['section'] ?? '';

// Fetch unique sections for the selected course and year
$sections = [];
$section_query = mysqli_query($conn,"SELECT DISTINCT section FROM students WHERE course='$selected_course' $teacher_year_filter $year_filter ORDER BY section");
while($row = mysqli_fetch_assoc($section_query)){
    $sections[] = $row['section'];
}

$section_filter = $selected_section ? " AND section='$selected_section'" : "";

// Search
$search = '';
if(isset($_GET['search'])){
    $search = mysqli_real_escape_string($conn,$_GET['search']);
}

// Fetch students with teacher filters + optional year and section filter
$query = "SELECT id, student_id, first_name, last_name, section, year_level 
          FROM students 
          WHERE course='$selected_course' $teacher_year_filter $teacher_section_filter $year_filter $section_filter";

if($search != ''){
    $query .= " AND (student_id LIKE '%$search%' 
                     OR first_name LIKE '%$search%' 
                     OR last_name LIKE '%$search%' 
                     OR CONCAT(first_name,' ',last_name) LIKE '%$search%' 
                     OR section LIKE '%$search%')";
}

$result = mysqli_query($conn,$query);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Students - <?= htmlspecialchars($selected_course) ?></title>
    <link rel="stylesheet" href="../css/teacherportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
    <h1><?= htmlspecialchars($selected_course) ?> Students</h1>

    <div class="top-bar">
        <h3>Register Student Directory ➔</h3>
        <a href="../Accesspage/register.php?from=teacher"><button>Add Student</button></a>
    </div>

    <!-- YEAR LEVEL + SECTION DROPDOWN + SEARCH -->
    <div class="filter-group">
        <form method="GET" style="display:flex; gap:1rem; align-items:center; flex-wrap: wrap;">
            <!-- Year Level -->
            <select name="year_level" onchange="this.form.submit()">
                <option value="">All Years</option>
                <?php foreach($teacher_year_levels as $yl): ?>
                <option value="<?= htmlspecialchars($yl) ?>" <?= ($selected_year==$yl)?'selected':'' ?>><?= htmlspecialchars($yl) ?></option>
                <?php endforeach; ?>
            </select>
            
            <!-- Section -->
            <select name="section" onchange="this.form.submit()">
                <option value="">All Sections</option>
                <?php foreach($sections as $sec): ?>
                <option value="<?= htmlspecialchars($sec) ?>" <?= ($selected_section==$sec)?'selected':'' ?>><?= htmlspecialchars($sec) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Search -->
            <input type="text" name="search" placeholder="Search by name, ID, or section..." value="<?= htmlspecialchars($search) ?>">

            <!-- Submit -->
            <button type="submit"><i class="fas fa-search"></i> Search</button>
            
            <!-- Clear/Refresh -->
            <?php if($selected_year || $selected_section || $search): ?>
                <a href="students.php" class="refresh-btn" title="Clear Filters"><i class="fas fa-rotate-right"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <table>
        <tr>
            <th>Student ID</th>
            <th>Name</th>
            <th>Year Level</th>
            <th>Section</th>
            <th>Action</th>
        </tr>
        <?php
        if(mysqli_num_rows($result) > 0){
            while($student = mysqli_fetch_assoc($result)){
                echo "<tr>
                    <td>".htmlspecialchars($student['student_id'])."</td>
                    <td>".htmlspecialchars($student['first_name'].' '.$student['last_name'])."</td>
                    <td>".htmlspecialchars($student['year_level'])."</td>
                    <td>".htmlspecialchars($student['section'])."</td>
                    <td>
                        <a href='../teachers_access/teachers_accessto_student.php?id=".urlencode($student['id'])."' class='view-student-btn' title='View Student'><i class='fas fa-user-graduate'></i></a>
                    </td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No students found.</td></tr>";
        }
        ?>
    </table>
</div>
</body>
</html>

