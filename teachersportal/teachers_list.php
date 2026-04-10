<?php
/* ============================================================
   teachers_list.php — PHP logic preserved exactly.
   HTML/UI unified with students.php modern style.
   ============================================================ */
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once CONFIG_PATH . 'database.php';
require_once CONFIG_PATH . 'teacher_filter.php';

if(!isset($_SESSION['teacher_id'])){
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

$admin_types = ['Seeder', 'Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);

if(!$is_admin){
    header("Location: " . BASE_URL . "teachersportal/dashboard.php");
    exit();
}

$back_url = BASE_URL . "Accesspage/teacher_login.php";
if($is_admin){ $back_url = BASE_URL . "teachersportal/chooseSub.php"; }

$selected_course = $_SESSION['teacher_course'] ?? '';
if(empty($selected_course)){ echo "Course not assigned. Contact admin."; exit(); }

$teacher_id = $_SESSION['teacher_id'];
$safe_course = mysqli_real_escape_string($conn, $selected_course);

$teacher_year_filter = '';
$teacher_section_filter = '';

$teacher_year_levels_query = mysqli_query($conn,
    "SELECT DISTINCT TRIM(SUBSTRING_INDEX(TRIM(year_levels), ',', 1)) AS yl
     FROM teachers WHERE course='$safe_course' ORDER BY yl");
$teacher_year_levels = ['1st Year','2nd Year','3rd Year','4th Year'];
while($row = mysqli_fetch_assoc($teacher_year_levels_query)){
    if(!empty($row['yl'])) $teacher_year_levels[] = $row['yl'];
}
$teacher_year_levels = array_unique($teacher_year_levels);

$selected_year = $_GET['year_level'] ?? '';
$year_filter = $selected_year ? " AND FIND_IN_SET('$selected_year', year_levels)" : "";

$search = $_GET['search'] ?? '';
$search_filter = '';
if(!empty($search)){
    $search_esc = mysqli_real_escape_string($conn, $search);
    $search_filter = " AND (teacher_id LIKE '%$search_esc%' OR first_name LIKE '%$search_esc%' OR last_name LIKE '%$search_esc%' OR CONCAT(first_name, ' ', last_name) LIKE '%$search_esc%')";
}

$query = "SELECT id, teacher_id, first_name, middle_name, last_name, suffix, year_levels, sections, email, mobile, teacher_type
          FROM teachers 
          WHERE course = '$safe_course' 
          AND teacher_type NOT IN ('Administrator', 'Seeder')
          $teacher_year_filter $year_filter $search_filter
          ORDER BY last_name, first_name";

$result = mysqli_query($conn, $query) or die(mysqli_error($conn));
$teacher_count = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers List — <?= htmlspecialchars($selected_course) ?></title>
    <link rel="icon" href="<?= asset('images/622685015_925666030131412_6886851389087569993_n.jpg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include path('teachersportal/sidebar.php'); ?>

<div class="content">

    <!-- ── PAGE HEADER ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><?= htmlspecialchars($selected_course) ?> Portal</div>
            <h1 class="page-header-title">Faculty Directory</h1>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <?php if ($teacher_count > 0): ?>
                <span class="result-count">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <?= $teacher_count ?> teacher<?= $teacher_count !== 1 ? 's' : '' ?>
                </span>
            <?php endif; ?>
            <?php if ($is_admin): ?>
                <a href="<?= BASE_URL ?>Accesspage/teachers_register.php?from=admin">
                    <button type="button" style="border-radius:50px;">
                        <i class="fas fa-user-plus"></i> Add Teacher
                    </button>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── FILTERS ── -->
    <div class="filter-group">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;width:100%;">
            <select name="year_level" onchange="this.form.submit()">
                <option value="">All Year Levels</option>
                <?php foreach($teacher_year_levels as $yl): ?>
                    <option value="<?= htmlspecialchars($yl) ?>" <?= ($selected_year==$yl)?'selected':'' ?>>
                        <?= htmlspecialchars($yl) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name or ID…" value="<?= htmlspecialchars($search) ?>">
            </div>

            <button type="submit"><i class="fas fa-search"></i> Search</button>

            <?php if($selected_year || $search): ?>
                <a href="teachers_list.php" class="refresh-btn" title="Clear Filters">
                    <i class="fas fa-rotate-right"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── TABLE ── -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Teacher ID</th>
                    <th style="text-align:left;">Full Name</th>
                    <th>Year Levels</th>
                    <th>Sections</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
            <?php if($teacher_count > 0):
                mysqli_data_seek($result, 0);
                while($teacher = mysqli_fetch_assoc($result)):
                    $full_name = trim(
                        $teacher['first_name'] . ' ' .
                        ($teacher['middle_name'] ?? '') . ' ' .
                        $teacher['last_name'] . ' ' .
                        ($teacher['suffix'] ?? '')
                    );
                    $initials = strtoupper(
                        substr($teacher['first_name'], 0, 1) .
                        substr($teacher['last_name'], 0, 1)
                    );
            ?>
                <tr>
                    <td class="teacher-id-cell"><?= htmlspecialchars($teacher['teacher_id']) ?></td>
                    <td>
                        <div class="name-cell">
                            <div class="teacher-avatar"><?= htmlspecialchars($initials) ?></div>
                            <span class="teacher-name"><?= htmlspecialchars($full_name) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($teacher['year_levels'] ?: 'N/A') ?></td>
                    <td><?= htmlspecialchars($teacher['sections'] ?: 'N/A') ?></td>
                    <td>
                        <a href="mailto:<?= htmlspecialchars($teacher['email']) ?>"
                           style="color:var(--primary-blue);text-decoration:none;">
                            <?= htmlspecialchars($teacher['email']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($teacher['mobile']) ?></td>
                    <td>
                        <span class="type-pill">
                            <i class="fas fa-circle" style="font-size:6px;"></i>
                            <?= htmlspecialchars($teacher['teacher_type']) ?>
                        </span>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="7" style="padding:0;border:none;"></td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if ($teacher_count === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
            <h3>No Teachers Found</h3>
            <p>Try adjusting your filters or search query.</p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.content -->
</body>
</html>