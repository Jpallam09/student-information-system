<?php
session_start();
include '../config/database.php';
include '../config/teacher_filter.php';

// ================== CHECK LOGIN ==================
if(!isset($_SESSION['teacher_id'])){
    header("Location: ../Accesspage/teacher_login.php");
    exit();
}

// ================== ADMIN CHECK - REQUIRED ==================
$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

if(!$is_admin){
    header("Location: dashboard.php");
    exit();
}

// ================== DYNAMIC BACK ARROW LOGIC ==================
$back_url = "../Accesspage/teacher_login.php";
if($is_admin){
    $back_url = "../teachersportal/chooseSub.php";
}

// ================== SET COURSE FROM SESSION ==================
$selected_course = $_SESSION['teacher_course'] ?? '';
if(empty($selected_course)){
    echo "Course not assigned. Contact admin.";
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// ================== BUILD TEACHER FILTER (for non-admins) ==================
$teacher_year_filter = '';
$teacher_section_filter = '';
// Admin teachers list - show all regular teachers for course, no personal filter
$teacher_year_filter = '';
$teacher_section_filter = '';

// Get teacher's assigned year levels for dropdown (split year_levels)
$teacher_year_levels_query = mysqli_query($conn, "SELECT DISTINCT TRIM(SUBSTRING_INDEX(TRIM(year_levels), ',', 1)) AS yl FROM teachers WHERE course='$selected_course' ORDER BY yl");
$teacher_year_levels = ['1st Year','2nd Year','3rd Year','4th Year'];
while($row = mysqli_fetch_assoc($teacher_year_levels_query)){
    if(!empty($row['yl'])) $teacher_year_levels[] = $row['yl'];
}
$teacher_year_levels = array_unique($teacher_year_levels);

// YEAR LEVEL FILTER
$selected_year = $_GET['year_level'] ?? '';
$year_filter = $selected_year ? " AND FIND_IN_SET('$selected_year', year_levels)" : "";

// Search filter
$search = $_GET['search'] ?? '';
$search_filter = '';
if(!empty($search)){
    $search_esc = mysqli_real_escape_string($conn, $search);
    $search_filter = " AND (teacher_id LIKE '%$search_esc%' OR first_name LIKE '%$search_esc%' OR last_name LIKE '%$search_esc%' OR CONCAT(first_name, ' ', last_name) LIKE '%$search_esc%')";
}

// ================== FETCH REGULAR TEACHERS ==================
$query = "SELECT id, teacher_id, first_name, middle_name, last_name, suffix, year_levels, sections, email, mobile, teacher_type
          FROM teachers 
          WHERE course = '$selected_course' 
          AND teacher_type NOT IN ('Administrator', 'Seeder')
          $teacher_year_filter $year_filter $search_filter
          ORDER BY last_name, first_name";

$result = mysqli_query($conn, $query) or die(mysqli_error($conn));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teachers List - <?= htmlspecialchars($selected_course) ?></title>
    <link rel="stylesheet" href="../css/teacherportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
    <h1>Teachers List - <?= htmlspecialchars($selected_course) ?></h1>
    <p>Registered regular teachers for this course</p>

    <div class="top-bar">
        <?php if ($is_admin): ?>
        <h3>Faculty Directory ➔</h3>
        <a href="../Accesspage/teachers_register.php?from=admin"><button>Add Teacher</button></a>
        <?php endif; ?>
    </div>

    <!-- FILTERS + SEARCH -->
    <div class="filter-group">
        <form method="GET" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
            <!-- Year Level Filter -->
            <select name="year_level" onchange="this.form.submit()">
                <option value="">All Year Levels</option>
                <?php foreach($teacher_year_levels as $yl): ?>
                <option value="<?= htmlspecialchars($yl) ?>" <?= ($selected_year==$yl)?'selected':'' ?>><?= htmlspecialchars($yl) ?></option>
                <?php endforeach; ?>
            </select>
            
            <!-- Search -->
            <input type="text" name="search" placeholder="Search by name or ID..." value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit"><i class="fas fa-search"></i> Search</button>
            
            <?php if($selected_year || $search): ?>
            <a href="teachers_list.php" class="refresh-btn" title="Clear Filters"><i class="fas fa-rotate-right"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- TEACHERS TABLE -->
    <div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Teacher ID</th>
                <th>Full Name</th>
                <th>Year Levels</th>
                <th>Sections</th>
                <th>Email</th>
                <th>Mobile</th>
                <th>Type</th>
            </tr>
        </thead>
        <tbody>
            <?php if(mysqli_num_rows($result) > 0): ?>
                <?php while($teacher = mysqli_fetch_assoc($result)): 
                    $full_name = trim($teacher['first_name'] . ' ' . ($teacher['middle_name']??'') . ' ' . $teacher['last_name'] . ' ' . ($teacher['suffix']??''));
                ?>
                <tr>
                    <td><?= htmlspecialchars($teacher['teacher_id']) ?></td>
                    <td><?= htmlspecialchars($full_name) ?></td>
                    <td><?= htmlspecialchars($teacher['year_levels'] ?: 'N/A') ?></td>
                    <td><?= htmlspecialchars($teacher['sections'] ?: 'N/A') ?></td>
                    <td><?= htmlspecialchars($teacher['email']) ?></td>
                    <td><?= htmlspecialchars($teacher['mobile']) ?></td>
                    <td><span class="badge"><?= htmlspecialchars($teacher['teacher_type']) ?></span></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7">No regular teachers found for <?= htmlspecialchars($selected_course) ?>.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

</div>

</body>
</html>

