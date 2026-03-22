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

// ================== DYNAMIC BACK ARROW LOGIC ==================
$back_url = "../Accesspage/teacher_login.php";
$admin_types = ['Seeder','Administrator'];
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

// ================== GET COURSE ID ==================
$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name=?");
$stmt->bind_param("s", $selected_course);
$stmt->execute();
$course_result = $stmt->get_result();
if($course_result && $course_result->num_rows > 0){
    $course_row = $course_result->fetch_assoc();
    $course_id = $course_row['id'];
}else{
    die("Course not found in database.");
}
$stmt->close();

// ================== FILTER VARIABLES ==================
$selected_year = $_GET['year_level'] ?? '';
$selected_section = $_GET['section'] ?? '';

// ================== FETCH AVAILABLE SECTIONS ================== (PREPARED)
$section_sql_base = "SELECT DISTINCT section FROM students WHERE course=? $teacher_year_filter";
$params_sec = [$selected_course];
$types_sec = "s";

if(!empty($selected_year)){
    $section_sql_base .= " AND year_level=?";
    $params_sec[] = $selected_year;
    $types_sec .= "s";
}
$section_sql_base .= " ORDER BY section ASC";

$stmt = $conn->prepare($section_sql_base);
$stmt->bind_param($types_sec, ...$params_sec);
$stmt->execute();
$sections_result = $stmt->get_result();
$available_sections = [];
while($row = $sections_result->fetch_assoc()){
    $available_sections[] = $row['section'];
}
$stmt->close();

// ================== SAVE GRADES ================== (LOOP PREPARED)
if(isset($_POST['save'])){

    $expanded_students_post = $_POST['expanded_students'][0] ?? '';
    $expanded_students_post = $expanded_students_post ? explode(',', $expanded_students_post) : [];

    if(!empty($_POST['student_id']) && is_array($_POST['student_id'])){
        foreach($_POST['student_id'] as $index => $raw_student_id){

            $student_id = intval($raw_student_id);
            $subject_id = intval($_POST['subject_id'][$index]);

            $quiz = floatval($_POST['quiz'][$index]);
            $homework = floatval($_POST['homework'][$index]);
            $activities = floatval($_POST['activities'][$index]);
            $prelim = floatval($_POST['prelim'][$index]);
            $midterm = floatval($_POST['midterm'][$index]);
            $final = floatval($_POST['final'][$index]);
            $lab = floatval($_POST['lab'][$index]);

            $percentage = 
                ($quiz * 0.10) + ($homework * 0.10) + ($activities * 0.10) +
                ($prelim * 0.20) + ($midterm * 0.20) + ($final * 0.30) + ($lab * 0.20);

            if($percentage >= 60) $grade="1.0";
            elseif($percentage >= 55) $grade="1.25";
            elseif($percentage >= 50) $grade="1.5";
            elseif($percentage >= 45) $grade="1.75";
            elseif($percentage >= 40) $grade="2.0";
            elseif($percentage >= 35) $grade="2.25";
            elseif($percentage >= 30) $grade="2.5";
            elseif($percentage >= 25) $grade="2.75";
            elseif($percentage >= 20) $grade="3.0";
            else $grade="5.0";

            // Check if grade exists (PREPARED)
            $stmt_check = $conn->prepare("SELECT id FROM grades WHERE student_id=? AND subject_id=?");
            $stmt_check->bind_param("ii", $student_id, $subject_id);
            $stmt_check->execute();
            $check_result = $stmt_check->get_result();

            if($check_result->num_rows > 0){

                $stmt_update = $conn->prepare("UPDATE grades SET 
                    quiz=?, homework=?, activities=?, prelim=?, midterm=?, final=?, lab=?, percentage=?, letter_grade=?
                    WHERE student_id=? AND subject_id=?");
                $stmt_update->bind_param("dddddddiss", $quiz, $homework, $activities, $prelim, $midterm, $final, $lab, $percentage, $grade, $student_id, $subject_id);
                $stmt_update->execute();
                $stmt_update->close();

            } else {

                $stmt_insert = $conn->prepare("INSERT INTO grades
                    (student_id, subject_id, quiz, homework, activities, prelim, midterm, final, lab, percentage, letter_grade)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_insert->bind_param("iidddddddds", $student_id, $subject_id, $quiz, $homework, $activities, $prelim, $midterm, $final, $lab, $percentage, $grade);
                $stmt_insert->execute();
                $stmt_insert->close();
            }
            $stmt_check->close();
        }

        $message = "Grades and GPA successfully saved!";

    } else {
        $message = "No subjects found for the selected students. Nothing to save.";
    }
}

// ================== FETCH STUDENTS ================== (PREPARED)
$students_base_sql = "SELECT * FROM students WHERE course=? $teacher_year_filter $teacher_section_filter";
$params_stu = [$selected_course];
$types_stu = "s";

if ($selected_year) {
    $students_base_sql .= " AND year_level=?";
    $params_stu[] = $selected_year;
    $types_stu .= "s";
}
if ($selected_section) {
    $students_base_sql .= " AND section=?";
    $params_stu[] = $selected_section;
    $types_stu .= "s";
}
$students_base_sql .= " ORDER BY section ASC, last_name ASC";

$stmt_stu = $conn->prepare($students_base_sql);
$stmt_stu->bind_param($types_stu, ...$params_stu);
$stmt_stu->execute();
$students_query = $stmt_stu->get_result();
$stmt_stu->close();


// Keep expanded students after saving
$keep_open_ids = [];
if(isset($_POST['expanded_students'][0]) && !empty($_POST['expanded_students'][0])){
    $keep_open_ids = explode(',', $_POST['expanded_students'][0]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grades - <?= htmlspecialchars($selected_course) ?></title>
<link rel="stylesheet" href="../css/teacherportal.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
    <h1><i class="fas fa-chart-line"></i> <?= htmlspecialchars($selected_course) ?> Grades Management</h1>

   <?php if(isset($message) && $message): ?>
    <div class="message success" id="flashMessage">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
    </div>

    <script>
        setTimeout(() => {
            const flash = document.getElementById('flashMessage');
            if(flash){
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
                <?php foreach($teacher_year_levels as $yl): ?>
                <option value="<?= htmlspecialchars($yl) ?>" <?= ($selected_year==$yl)?'selected':'' ?>><?= htmlspecialchars($yl) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="section" onchange="this.form.submit()">

                <option value="">All Sections</option>
                <?php foreach($available_sections as $sec): ?>
                    <option value="<?= $sec ?>" <?= ($selected_section==$sec)?'selected':'' ?>><?= $sec ?></option>
                <?php endforeach; ?>
            </select>

            <?php if($selected_year || $selected_section): ?>
                <a href="grades.php" class="refresh-btn" title="Clear Filters">↺</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Grades Table -->
    <div class="table-container">
        <form method="POST">
            <input type="hidden" name="expanded_students[]" id="expanded_students_input" value="<?= implode(',', $keep_open_ids) ?>">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Year Level</th>
                        <th>Section</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                mysqli_data_seek($students_query, 0);
                while($student = mysqli_fetch_assoc($students_query)): 
                ?>
                    <tr>
                        <td><?= htmlspecialchars($student['id']) ?></td>
                        <td><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></td>
                        <td><?= htmlspecialchars($student['year_level']) ?></td>
                        <td><?= htmlspecialchars($student['section']) ?></td>
                        <td>
                            <button type="button" class="btn-view-grades" id="btn-student<?= $student['id'] ?>" onclick="toggleDetails('student<?= $student['id'] ?>')">
                                <i class="fas fa-clipboard-list"></i> <span id="text-student<?= $student['id'] ?>">View Grades</span>
                            </button>
                        </td>
                    </tr>

                    <!-- Student Grades Details -->
                    <tr id="student<?= $student['id'] ?>" class="student-details" style="display: <?= in_array($student['id'], $keep_open_ids) ? 'table-row' : 'none' ?>;">
                        <td colspan="5">
                            <div style="background: var(--off-white); padding: 1.5rem; border-radius: var(--border-radius-md);">
                                <div class="table-container" style="margin-bottom: 1rem;">
                                    <table style="margin: 0;">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Quiz (10%)</th>
                                                <th>Homework (10%)</th>
                                                <th>Activities (10%)</th>
                                                <th>Prelim (20%)</th>
                                                <th>Midterm (20%)</th>
                                                <th>Final (30%)</th>
                                                <th>Lab (20%)</th>
                                                <th>Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
$stmt_sub = $conn->prepare("SELECT * FROM subjects WHERE course_id=? AND year_level=? AND section=? ORDER BY subject_name ASC");
$stmt_sub->bind_param("iss", $course_id, $student['year_level'], $student['section']);
$stmt_sub->execute();
$subjects_result = $stmt_sub->get_result();
if($subjects_result && $subjects_result->num_rows > 0){
    while($subject = $subjects_result->fetch_assoc()):
$stmt_grade = $conn->prepare("SELECT * FROM grades WHERE student_id=? AND subject_id=?");
$stmt_grade->bind_param("ii", $student['id'], $subject['id']);
$stmt_grade->execute();
$grade_result = $stmt_grade->get_result();
$grade_row = $grade_result->fetch_assoc();
$stmt_grade->close();
                                                $letter_grade = $grade_row['letter_grade'] ?? '-';
                                        ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($subject['code']) ?></strong></td>
                                                    <input type="hidden" name="student_id[]" value="<?= $student['id'] ?>">
                                                    <input type="hidden" name="subject_id[]" value="<?= $subject['id'] ?>">
                                                    <td><input type="number" name="quiz[]" value="<?= $grade_row['quiz'] ?? 0 ?>" min="0" max="20" style="width: 70px;"></td>
                                                    <td><input type="number" name="homework[]" value="<?= $grade_row['homework'] ?? 0 ?>" min="0" max="50" style="width: 70px;"></td>
                                                    <td><input type="number" name="activities[]" value="<?= $grade_row['activities'] ?? 0 ?>" min="0" max="50" style="width: 70px;"></td>
                                                    <td><input type="number" name="prelim[]" value="<?= $grade_row['prelim'] ?? 0 ?>" min="0" max="60" style="width: 70px;"></td>
                                                    <td><input type="number" name="midterm[]" value="<?= $grade_row['midterm'] ?? 0 ?>" min="0" max="60" style="width: 70px;"></td>
                                                    <td><input type="number" name="final[]" value="<?= $grade_row['final'] ?? 0 ?>" min="0" max="60" style="width: 70px;"></td>
                                                    <td><input type="number" name="lab[]" value="<?= $grade_row['lab'] ?? 0 ?>" min="0" max="60" style="width: 70px;"></td>
                                                    <td>
                                                        <?php if($letter_grade != '-'): ?>
                                                            <?php
                                                            $badge_class = 'badge-';
                                                            if($letter_grade <= 1.5) $badge_class .= 'green';
                                                            elseif($letter_grade <= 2.0) $badge_class .= 'blue';
                                                            elseif($letter_grade <= 2.5) $badge_class .= 'yellow';
                                                            else $badge_class .= 'red';
                                                            ?>
                                                            <span class="<?= $badge_class ?>"><?= $letter_grade ?></span>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                        <?php
                                            endwhile;
                                        } else {
                                            echo "<tr><td colspan='9' style='text-align:center; color: #d00;'>No subjects assigned for this student.</td></tr>";
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- GPA Calculation -->
                                <?php
                                $grades_for_gpa = mysqli_query($conn, "SELECT quiz, homework, activities, prelim, midterm, final, lab FROM grades WHERE student_id='{$student['id']}'");
                                $total_score_sum = 0;
                                $subject_count = mysqli_num_rows($grades_for_gpa);

                                while($g_row = mysqli_fetch_assoc($grades_for_gpa)){
                                    $total_grade = ($g_row['quiz']*0.10)+($g_row['homework']*0.10)+($g_row['activities']*0.10)+($g_row['prelim']*0.20)+($g_row['midterm']*0.20)+($g_row['final']*0.30)+($g_row['lab']*0.20);
                                    if($total_grade>=60) $gpa_points=1.0;
                                    elseif($total_grade>=55) $gpa_points=1.25;
                                    elseif($total_grade>=50) $gpa_points=1.5;
                                    elseif($total_grade>=45) $gpa_points=1.75;
                                    elseif($total_grade>=40) $gpa_points=2.0;
                                    elseif($total_grade>=35) $gpa_points=2.25;
                                    elseif($total_grade>=30) $gpa_points=2.5;
                                    elseif($total_grade>=25) $gpa_points=2.75;
                                    elseif($total_grade>=20) $gpa_points=3.0;
                                    else $gpa_points=5.0;
                                    $total_score_sum += $gpa_points;
                                }

                                $display_gpa = ($subject_count>0) ? round($total_score_sum/$subject_count,2) : 0;
                                ?>

                                <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                                    <p style="font-weight: 600; padding: 0.5rem 1rem; background: var(--pure-white); border-radius: var(--border-radius-md);">
                                        GPA: <span class="badge-green"><?= $display_gpa ?></span>
                                    </p>
                                </div>

                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            
            <?php if(mysqli_num_rows($students_query) > 0): ?>
                <div style="margin-top: 2rem; text-align: right;">
                    <button type="submit" name="save" class="btn">
                        <i class="fas fa-save"></i> Save All Grades
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
function toggleDetails(id) {
    const row = document.getElementById(id);
    const expandedInput = document.getElementById('expanded_students_input');
    const btn = document.getElementById('btn-' + id);
    const icon = btn.querySelector('i');
    const text = btn.querySelector('span');
    let expanded = expandedInput.value ? expandedInput.value.split(',') : [];
    
    if(row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
        expanded.push(id.replace('student',''));
        // Update to "Hide Grades" when open
        icon.className = 'fas fa-chevron-up';
        text.textContent = 'Hide Grades';
        btn.classList.add('btn-view-grades-open');
    } else {
        row.style.display = 'none';
        expanded = expanded.filter(s => s !== id.replace('student',''));
        // Update back to "View Grades" when closed
        icon.className = 'fas fa-clipboard-list';
        text.textContent = 'View Grades';
        btn.classList.remove('btn-view-grades-open');
    }
    
    expandedInput.value = expanded.join(',');
}

// Close other open rows when opening a new one
document.querySelectorAll('button[onclick^="toggleDetails"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const currentId = this.getAttribute('onclick').match(/'([^']+)'/)[1];
        document.querySelectorAll('.student-details').forEach(row => {
            if(row.id !== currentId && row.style.display === 'table-row') {
                row.style.display = 'none';
                // Reset button state for closed rows
                const btnId = 'btn-' + row.id;
                const btn = document.getElementById(btnId);
                if(btn) {
                    const icon = btn.querySelector('i');
                    const text = btn.querySelector('span');
                    icon.className = 'fas fa-clipboard-list';
                    text.textContent = 'View Grades';
                    btn.classList.remove('btn-view-grades-open');
                }
            }
        });
    });
});
</script>
</body>
</html>