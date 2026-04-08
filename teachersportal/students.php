<?php
session_start();

require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/config/teacher_filter.php';

// ================== CHECK LOGIN ==================
if(!isset($_SESSION['teacher_id'])){
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

// ================== CHECK IF ADMIN ==================
$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

// ================== BACK URL ==================
$back_url = BASE_URL . "Accesspage/teacher_login.php";
if($is_admin){
    $back_url = BASE_URL . "teachersportal/chooseSub.php";
}

// ================== SET COURSE ==================
$selected_course = $_SESSION['teacher_course'] ?? '';

if(empty($selected_course)){
    echo "Course not assigned to this teacher. Contact admin.";
    exit();
}

// Allowed courses
$allowed_courses = ['BSIT','BSED','BAT','BTVTED'];
if(!in_array(strtoupper($selected_course), $allowed_courses)){
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="' . BASE_URL . 'teachersportal/chooseSub.php">← Go Back</a>';
    exit();
}

// ================== FILTERS ==================
$teacher_year_filter = '';
$teacher_section_filter = '';

if (!$is_admin) {
    $y_params = []; $y_types = '';
    $teacher_year_filter = getCombinedYearFilter('year_level', $y_params, $y_types);
    $s_params = []; $s_types = '';
    $teacher_section_filter = getCombinedSectionFilter('section', $s_params, $s_types);
}

$teacher_year_levels = !$is_admin ? getTeacherYearLevels() : ['1st Year','2nd Year','3rd Year','4th Year'];

// URL filters
$selected_year = $_GET['year_level'] ?? '';
$selected_section = $_GET['section'] ?? '';
$search = trim($_GET['search'] ?? '');

// ================== SECTION QUERY ==================
$sections = [];

// Build params starting with course
$params_sec = [$selected_course];
$types_sec = "s";

// ⬇️ Add the teacher year filter params BEFORE appending selected_year
if (!$is_admin && !empty($y_params)) {
    $params_sec = array_merge($params_sec, $y_params);
    $types_sec .= $y_types;
}

$section_sql = "SELECT DISTINCT section FROM students WHERE course=? $teacher_year_filter";

if ($selected_year) {
    $section_sql .= " AND year_level=?";
    $params_sec[] = $selected_year;
    $types_sec .= "s";
}

$section_sql .= " ORDER BY section";

$stmt = $conn->prepare($section_sql);
$stmt->bind_param($types_sec, ...$params_sec);
$stmt->execute();
$result_sec = $stmt->get_result();

while($row = $result_sec->fetch_assoc()){
    $sections[] = $row['section'];
}
$stmt->close();

// ================== STUDENTS QUERY ==================
$query = "SELECT id, student_id, first_name, last_name, section, year_level 
          FROM students 
          WHERE course=? $teacher_year_filter $teacher_section_filter";

$params = [$selected_course];
$types = "s";

// ⬇️ Merge teacher filter params right after course
if (!$is_admin) {
    if (!empty($y_params)) {
        $params = array_merge($params, $y_params);
        $types .= $y_types;
    }
    if (!empty($s_params)) {
        $params = array_merge($params, $s_params);
        $types .= $s_types;
    }
}

// Then add URL filter params as before
if ($selected_year) {
    $query .= " AND year_level=?";
    $params[] = $selected_year;
    $types .= "s";
}

if ($selected_section) {
    $query .= " AND section=?";
    $params[] = $selected_section;
    $types .= "s";
}

if ($search != '') {
    $query .= " AND (student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name,' ',last_name) LIKE ? OR section LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= "sssss";
}

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Students - <?= htmlspecialchars($selected_course) ?></title>
     <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
    <h1><?= htmlspecialchars($selected_course) ?> Students</h1>

    <div class="top-bar">
        <h3>Register Student Directory ➔</h3>
       <a href="<?= BASE_URL ?>Accesspage/register.php?from=teacher"><button>Add Student</button></a>
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
                        <a href='".BASE_URL."teachers_access/teachers_accessto_student.php?id=".urlencode($student['id'])."' class='view-student-btn' title='View Student'><i class='fas fa-user-graduate'></i></a>
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

