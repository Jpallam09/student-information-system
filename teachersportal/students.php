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
if($is_admin){ $back_url = BASE_URL . "teachersportal/chooseSub.php"; }

// ================== SET COURSE ==================
$selected_course = $_SESSION['teacher_course'] ?? '';
if(empty($selected_course)){
    echo "Course not assigned to this teacher. Contact admin.";
    exit();
}

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

$selected_year    = $_GET['year_level'] ?? '';
$selected_section = $_GET['section'] ?? '';
$search = trim($_GET['search'] ?? '');

// ================== SECTION QUERY ==================
$sections = [];
$params_sec = [$selected_course];
$types_sec = "s";

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
while($row = $result_sec->fetch_assoc()){ $sections[] = $row['section']; }
$stmt->close();

// ================== STUDENTS QUERY ==================
$query = "SELECT id, student_id, first_name, last_name, section, year_level 
          FROM students 
          WHERE course=? $teacher_year_filter $teacher_section_filter";

$params = [$selected_course];
$types = "s";

if (!$is_admin) {
    if (!empty($y_params)) { $params = array_merge($params, $y_params); $types .= $y_types; }
    if (!empty($s_params)) { $params = array_merge($params, $s_params); $types .= $s_types; }
}

if ($selected_year)    { $query .= " AND year_level=?"; $params[] = $selected_year;    $types .= "s"; }
if ($selected_section) { $query .= " AND section=?";    $params[] = $selected_section; $types .= "s"; }

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
$student_count = $result->num_rows;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students — <?= htmlspecialchars($selected_course) ?></title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">

    <!-- PAGE HEADER -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><?= htmlspecialchars($selected_course) ?> Portal</div>
            <h1 class="page-header-title">Student Directory</h1>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <?php if($student_count > 0): ?>
                <span class="result-count"><i class="fas fa-users"></i> <?= $student_count ?> student<?= $student_count !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>Accesspage/register.php?from=teacher">
                <button type="button" style="border-radius:50px;">
                    <i class="fas fa-user-plus"></i> Add Student
                </button>
            </a>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-group">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;width:100%;">
            <select name="year_level" onchange="this.form.submit()">
                <option value="">All Year Levels</option>
                <?php foreach($teacher_year_levels as $yl): ?>
                    <option value="<?= htmlspecialchars($yl) ?>" <?= ($selected_year==$yl)?'selected':'' ?>><?= htmlspecialchars($yl) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="section" onchange="this.form.submit()">
                <option value="">All Sections</option>
                <?php foreach($sections as $sec): ?>
                    <option value="<?= htmlspecialchars($sec) ?>" <?= ($selected_section==$sec)?'selected':'' ?>><?= htmlspecialchars($sec) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search name, ID, or section…" value="<?= htmlspecialchars($search) ?>">
            </div>

            <button type="submit"><i class="fas fa-search"></i> Search</button>

            <?php if($selected_year || $selected_section || $search): ?>
                <a href="students.php" class="refresh-btn" title="Clear Filters">
                    <i class="fas fa-rotate-right"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- STUDENTS TABLE -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th style="text-align:left;">Name</th>
                    <th>Year Level</th>
                    <th>Section</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if($student_count > 0){
                    // Re-execute query to iterate
                    $stmt2 = $conn->prepare($query);
                    $stmt2->bind_param($types, ...$params);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    while($student = mysqli_fetch_assoc($result2)){
                        echo "<tr>
                            <td class='student-id-cell'>".htmlspecialchars($student['student_id'])."</td>
                            <td class='student-name'>".htmlspecialchars($student['first_name'].' '.$student['last_name'])."</td>
                            <td>".htmlspecialchars($student['year_level'])."</td>
                            <td><span class='badge-blue'>".htmlspecialchars($student['section'])."</span></td>
                            <td>
                                <a href='".BASE_URL."teachers_access/teachers_accessto_student.php?id=".urlencode($student['id'])."' 
                                   class='view-student-btn' title='View Student Profile'>
                                    <i class='fas fa-user-graduate'></i>
                                </a>
                            </td>
                        </tr>";
                    }
                    $stmt2->close();
                } else {
                    echo "<tr><td colspan='5' style='padding:0;border:none;'></td></tr>";
                }
                ?>
            </tbody>
        </table>

        <?php if($student_count === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
            <h3>No Students Found</h3>
            <p>Try adjusting your filters or search query.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>