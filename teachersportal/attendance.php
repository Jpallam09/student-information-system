<?php
session_start();
include '../config/database.php';
include '../config/teacher_filter.php';

// ================== BUILD TEACHER FILTER ==================
$teacher_year_filter = '';
$teacher_section_filter = '';
$admin_types = ['Seeder','Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
if (!$is_admin) {
    $teacher_year_filter = getYearLevelFilter('year_level');
$teacher_section_filter = getSectionFilter('section');
}

$teacher_year_levels = !$is_admin ? getTeacherYearLevels() : ['1st Year','2nd Year','3rd Year','4th Year'];

// ================== CHECK LOGIN ==================

if(!isset($_SESSION['teacher_id'])){
    header("Location: ../Accesspage/teacher_login.php");
    exit();
}

// Block admin access
if ($is_admin) {
    echo "<div style='text-align:center; margin-top:50px; font-family:Arial,sans-serif;'>";
    echo "<h2 style='color:#dc3545;'><i class='fas fa-exclamation-triangle'></i> Access Denied</h2>";
    echo "<p>Attendance Management is only available for regular teachers.</p>";
    echo "<p>Admin users cannot access this page.</p>";
    echo "<a href='dashboard.php' style='display:inline-block; margin-top:20px; padding:10px 20px; background:#0A91AB; color:white; text-decoration:none; border-radius:5px;'>Go Back to Dashboard</a>";
    echo "</div>";
    exit();
}

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
$allowed_courses = ['BSIT','BSED','BAT','BTVTED'];
if(!in_array(strtoupper($selected_course), $allowed_courses)){
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="chooseSub.php">← Go Back</a>';
    exit();
}

// YEAR LEVEL FILTER
$selected_year = $_GET['year_level'] ?? '';
$year_filter = $selected_year ? " AND year_level='$selected_year'" : "";

// SECTION FILTER
$selected_section = $_GET['section'] ?? '';

// Fetch unique sections
$sections = [];
$section_query = mysqli_query($conn,"SELECT DISTINCT section FROM students WHERE course='$selected_course' $teacher_year_filter $year_filter ORDER BY section");
while($row = mysqli_fetch_assoc($section_query)){
    $sections[] = $row['section'];
}

$section_filter = $selected_section ? " AND section='$selected_section'" : "";

// Date selection
$date = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d');

// Save attendance
$message = '';
if(isset($_POST['save_attendance'])){
    foreach($_POST['status'] as $student_id => $status){
        $check = mysqli_query($conn,"SELECT id FROM attendance WHERE student_id='$student_id' AND `date`='$date'");
        if(mysqli_num_rows($check)>0){
            mysqli_query($conn,"UPDATE attendance SET status='$status' WHERE student_id='$student_id' AND `date`='$date'");
        } else {
            mysqli_query($conn,"INSERT INTO attendance(student_id,`date`,status) VALUES('$student_id','$date','$status')");
        }
    }
    $message = "Attendance saved for $date";
}

// Fetch students
$students = mysqli_query($conn,"SELECT id, student_id, first_name, last_name, section, year_level 
                               FROM students 
                               WHERE course='$selected_course' $teacher_year_filter $teacher_section_filter $year_filter $section_filter
                               ORDER BY section, last_name");

// Fetch current attendance
$current_attendance = [];
$att_query = mysqli_query($conn,"SELECT student_id, status FROM attendance WHERE `date`='$date'");
while($row = mysqli_fetch_assoc($att_query)){
    $current_attendance[$row['student_id']] = $row['status'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?= htmlspecialchars($selected_course) ?></title>
    <link rel="stylesheet" href="../css/teacherportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
    <h1><i class="fas fa-calendar-check"></i> <?= htmlspecialchars($selected_course) ?> Attendance</h1>

    <?php if($message): ?>
        <div class="message success" id="flashMessage">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
        </div>
        <script>
            setTimeout(() => {
                const flash = document.getElementById('flashMessage');
                if(flash) {
                    flash.style.transition = 'opacity 0.5s';
                    flash.style.opacity = '0';
                    setTimeout(() => flash.remove(), 500);
                }
            }, 3000);
        </script>
    <?php endif; ?>

    <!-- Year Level & Section Filter -->
    <div class="filter-group">
        <form method="GET" style="display:flex; gap:1rem; align-items:center;">
            <select name="year_level" onchange="this.form.submit()">
                <option value="">All Year Levels</option>
                <?php foreach($teacher_year_levels as $year): ?>
                    <option value="<?= htmlspecialchars($year) ?>" <?= ($selected_year == $year)?'selected':'' ?>><?= htmlspecialchars($year) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="section" onchange="this.form.submit()">
                <option value="">All Sections</option>
                <?php foreach($sections as $sec): ?>
                    <option value="<?= $sec ?>" <?= ($selected_section == $sec) ? 'selected' : '' ?>><?= $sec ?></option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" onchange="this.form.submit()">

            <?php if($selected_year || $selected_section): ?>
                <a href="attendance.php" class="refresh-btn" title="Clear Filters">↺</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Attendance Table -->
    <div class="table-container">
        <form method="POST">
            <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Year Level</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(mysqli_num_rows($students) > 0): ?>
                    <?php while($student = mysqli_fetch_assoc($students)): 
                        $status = $current_attendance[$student['id']] ?? 'present';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($student['student_id']) ?></td>
                            <td><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></td>
                            <td><?= htmlspecialchars($student['year_level']) ?></td>
                            <td><?= htmlspecialchars($student['section']) ?></td>
                            <td>
                                <div class="attendance-buttons" data-student="<?= $student['id'] ?>">
                                    <button type="button" class="status-btn <?= $status=='present'?'active':'' ?>" data-value="present">Present</button>
                                    <button type="button" class="status-btn <?= $status=='absent'?'active':'' ?>" data-value="absent">Absent</button>
                                    <input type="hidden" name="status[<?= $student['id'] ?>]" value="<?= $status ?>">
                                </div>
                            </td>
                            <td style="text-align:center; color:#065471; font-weight:600;">
                                <?= date("M d, Y", strtotime($date)) ?>
                                <?php if($date == date('Y-m-d')): ?>
                                    <span class="badge" style="background-color:#10b981; color:white; padding:2px 6px; border-radius:4px; margin-left:0.3rem;">Today</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem;">
                            <i class="fas fa-users" style="font-size: 2rem; color: var(--text-muted); margin-bottom: 0.5rem;"></i>
                            <p>No students found for the selected filters.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if(mysqli_num_rows($students) > 0): ?>
                <div style="margin-top: 1.5rem; text-align: right;">
                    <button type="submit" name="save_attendance" class="btn">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.attendance-buttons').forEach(container => {
    const buttons = container.querySelectorAll('.status-btn');
    const hiddenInput = container.querySelector('input[type="hidden"]');

    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            hiddenInput.value = btn.dataset.value;
        });
    });
});
</script>
</body>
</html>