<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

// ================== CHECK LOGIN ==================
if(!isset($_SESSION['teacher_id'])){
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

require_once PROJECT_ROOT . '/config/teacher_filter.php';

// ================== BUILD TEACHER FILTER ==================
$teacher_year_filter = '';
$teacher_section_filter = '';
$admin_types = ['Seeder','Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
if (!$is_admin) {
    $y_params = []; $y_types = '';
    $teacher_year_filter = getCombinedYearFilter('year_level', $y_params, $y_types);
    $s_params = []; $s_types = '';
    $teacher_section_filter = getCombinedSectionFilter('section', $s_params, $s_types);
}

$teacher_year_levels = !$is_admin ? getTeacherYearLevels() : ['1st Year','2nd Year','3rd Year','4th Year'];

$back_url = BASE_URL . "Accesspage/teacher_login.php";
if (isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], ['Seeder','Administrator'])) {
    $back_url = BASE_URL . "teachersportal/chooseSub.php";
}

// ================== SET COURSE FROM SESSION ==================
$selected_course = $_SESSION['teacher_course'] ?? '';
if(empty($selected_course)){ echo "Course not assigned to this teacher. Contact admin."; exit(); }

$teacher_id = $_SESSION['teacher_id'];

$allowed_courses = ['BSIT','BSED','BAT','BTVTED'];
if(!in_array(strtoupper($selected_course), $allowed_courses)){
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="' . BASE_URL . 'teachersportal/chooseSub.php">← Go Back</a>';
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
} else { die("Course not found in database."); }
$stmt->close();

// ================== FILTER VARIABLES ==================
$selected_year    = $_GET['year_level'] ?? '';
$selected_section = $_GET['section'] ?? '';

// ================== FETCH AVAILABLE SECTIONS ==================
$section_sql_base = "SELECT DISTINCT section FROM students WHERE course=? $teacher_year_filter";
$params_sec = [$selected_course]; $types_sec = "s";

if (!$is_admin && !empty($y_params)) {
    $params_sec = array_merge($params_sec, $y_params);
    $types_sec .= $y_types;
}
if(!empty($selected_year)){ $section_sql_base .= " AND year_level=?"; $params_sec[] = $selected_year; $types_sec .= "s"; }
$section_sql_base .= " ORDER BY section ASC";

$stmt = $conn->prepare($section_sql_base);
$stmt->bind_param($types_sec, ...$params_sec);
$stmt->execute();
$sections_result = $stmt->get_result();
$available_sections = [];
while($row = $sections_result->fetch_assoc()){ $available_sections[] = $row['section']; }
$stmt->close();

// ================== SAVE GRADES (teachers only) ==================
if(isset($_POST['save']) && !$is_admin){
    $expanded_students_post = $_POST['expanded_students'][0] ?? '';
    $expanded_students_post = $expanded_students_post ? explode(',', $expanded_students_post) : [];

    if(!empty($_POST['student_id']) && is_array($_POST['student_id'])){
        foreach($_POST['student_id'] as $index => $raw_student_id){
            $student_id = intval($raw_student_id);
            $subject_id = intval($_POST['subject_id'][$index]);
            $quiz       = floatval($_POST['quiz'][$index]);
            $homework   = floatval($_POST['homework'][$index]);
            $activities = floatval($_POST['activities'][$index]);
            $prelim     = floatval($_POST['prelim'][$index]);
            $midterm    = floatval($_POST['midterm'][$index]);
            $final      = floatval($_POST['final'][$index]);
            $lab        = floatval($_POST['lab'][$index]);

            $percentage = ($quiz*0.10)+($homework*0.10)+($activities*0.10)+
                          ($prelim*0.20)+($midterm*0.20)+($final*0.30)+($lab*0.20);

            if($percentage>=60) $grade="1.0"; elseif($percentage>=55) $grade="1.25";
            elseif($percentage>=50) $grade="1.5"; elseif($percentage>=45) $grade="1.75";
            elseif($percentage>=40) $grade="2.0"; elseif($percentage>=35) $grade="2.25";
            elseif($percentage>=30) $grade="2.5"; elseif($percentage>=25) $grade="2.75";
            elseif($percentage>=20) $grade="3.0"; else $grade="5.0";

            $stmt_check = $conn->prepare("SELECT id FROM grades WHERE student_id=? AND subject_id=?");
            $stmt_check->bind_param("ii", $student_id, $subject_id);
            $stmt_check->execute();
            $check_result = $stmt_check->get_result();

            if($check_result->num_rows > 0){
                $stmt_update = $conn->prepare("UPDATE grades SET quiz=?,homework=?,activities=?,prelim=?,midterm=?,final=?,lab=?,percentage=?,letter_grade=? WHERE student_id=? AND subject_id=?");
                $stmt_update->bind_param("dddddddissi", $quiz,$homework,$activities,$prelim,$midterm,$final,$lab,$percentage,$grade,$student_id,$subject_id);
                $stmt_update->execute(); $stmt_update->close();
            } else {
                $stmt_insert = $conn->prepare("INSERT INTO grades (student_id,subject_id,quiz,homework,activities,prelim,midterm,final,lab,percentage,letter_grade) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt_insert->bind_param("iidddddddds", $student_id,$subject_id,$quiz,$homework,$activities,$prelim,$midterm,$final,$lab,$percentage,$grade);
                $stmt_insert->execute(); $stmt_insert->close();
            }
            $stmt_check->close();
        }
        $message = "Grades saved successfully!";

        $unique_students = array_unique($_POST['student_id']);
        foreach($unique_students as $stu_id){
            $stu_id = intval($stu_id);
            $stmt_gpa_calc = $conn->prepare("SELECT quiz,homework,activities,prelim,midterm,final,lab FROM grades WHERE student_id=?");
            $stmt_gpa_calc->bind_param("i", $stu_id);
            $stmt_gpa_calc->execute();
            $gpa_res = $stmt_gpa_calc->get_result();
            $gpa_sum = 0; $gpa_count = $gpa_res->num_rows;
            while($g = $gpa_res->fetch_assoc()){
                $total = ($g['quiz']*0.10)+($g['homework']*0.10)+($g['activities']*0.10)+
                         ($g['prelim']*0.20)+($g['midterm']*0.20)+($g['final']*0.30)+($g['lab']*0.20);
                if($total>=60) $gp=1.0; elseif($total>=55) $gp=1.25; elseif($total>=50) $gp=1.5;
                elseif($total>=45) $gp=1.75; elseif($total>=40) $gp=2.0; elseif($total>=35) $gp=2.25;
                elseif($total>=30) $gp=2.5; elseif($total>=25) $gp=2.75; elseif($total>=20) $gp=3.0;
                else $gp=5.0;
                $gpa_sum += $gp;
            }
            $stmt_gpa_calc->close();
            $final_gpa = $gpa_count > 0 ? round($gpa_sum/$gpa_count,2) : 0.0;
            $stmt_update_gpa = $conn->prepare("UPDATE students SET gpa=? WHERE id=?");
            $stmt_update_gpa->bind_param("di", $final_gpa, $stu_id);
            $stmt_update_gpa->execute(); $stmt_update_gpa->close();
        }
    } else {
        $message = "No subjects found for the selected students. Nothing to save.";
    }
}

// ================== FETCH STUDENTS ==================
$students_base_sql = "SELECT * FROM students WHERE course=? $teacher_year_filter $teacher_section_filter";
$params_stu = [$selected_course]; $types_stu = "s";

if (!$is_admin) {
    if (!empty($y_params)) { $params_stu = array_merge($params_stu, $y_params); $types_stu .= $y_types; }
    if (!empty($s_params)) { $params_stu = array_merge($params_stu, $s_params); $types_stu .= $s_types; }
}

if($selected_year)    { $students_base_sql .= " AND year_level=?"; $params_stu[] = $selected_year;    $types_stu .= "s"; }
if($selected_section) { $students_base_sql .= " AND section=?";    $params_stu[] = $selected_section; $types_stu .= "s"; }
$students_base_sql .= " ORDER BY section ASC, last_name ASC";

$stmt_stu = $conn->prepare($students_base_sql);
$stmt_stu->bind_param($types_stu, ...$params_stu);
$stmt_stu->execute();
$students_query = $stmt_stu->get_result();
$stmt_stu->close();

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
<title>Grades — <?= htmlspecialchars($selected_course) ?></title>
<link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include PROJECT_ROOT . '/teachersportal/sidebar.php'; ?>

<div class="content">

    <!-- PAGE HEADER -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><?= htmlspecialchars($selected_course) ?> Portal</div>
            <h1 class="page-header-title">
                Grades Management
                <?php if($is_admin): ?>
                    <span class="admin-view-badge"><i class="fas fa-eye"></i> View Only</span>
                <?php endif; ?>
            </h1>
        </div>
    </div>

    <?php if(isset($message) && $message): ?>
    <div class="message success" id="flashMessage">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
    </div>
    <script>
        setTimeout(() => {
            const flash = document.getElementById('flashMessage');
            if(flash){ flash.style.transition='opacity 0.5s'; flash.style.opacity='0'; setTimeout(()=>flash.remove(),500); }
        }, 3000);
    </script>
    <?php endif; ?>

    <!-- FILTERS -->
    <div class="filter-group">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;width:100%;">
            <select name="year_level" onchange="this.form.submit()" style="border-radius:50px;">
                <option value="">All Year Levels</option>
                <?php foreach($teacher_year_levels as $yl): ?>
                    <option value="<?= htmlspecialchars($yl) ?>" <?= ($selected_year==$yl)?'selected':'' ?>><?= htmlspecialchars($yl) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="section" onchange="this.form.submit()" style="border-radius:50px;">
                <option value="">All Sections</option>
                <?php foreach($available_sections as $sec): ?>
                    <option value="<?= $sec ?>" <?= ($selected_section==$sec)?'selected':'' ?>><?= $sec ?></option>
                <?php endforeach; ?>
            </select>
            <?php if($selected_year || $selected_section): ?>
                <a href="grades.php" class="refresh-btn" title="Clear Filters">
                    <i class="fas fa-rotate-right"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- GRADES TABLE -->
    <div class="table-container">
        <form method="POST">
            <input type="hidden" name="expanded_students[]" id="expanded_students_input" value="<?= implode(',', $keep_open_ids) ?>">
            <table>
                <thead>
                    <tr>
                        <th style="text-align:left;padding-left:20px;">Student ID</th>
                        <th style="text-align:left;">Name</th>
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
                        <td style="font-family:monospace;font-size:0.87rem;color:var(--text-muted);text-align:left;padding-left:20px;"><?= htmlspecialchars($student['id']) ?></td>
                        <td style="font-weight:600;color:var(--slate-800);text-align:left;"><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></td>
                        <td><?= htmlspecialchars($student['year_level']) ?></td>
                        <td><span class="badge-blue"><?= htmlspecialchars($student['section']) ?></span></td>
                        <td>
                            <button type="button" class="btn-view-grades" id="btn-student<?= $student['id'] ?>" onclick="toggleDetails('student<?= $student['id'] ?>')">
                                <i class="fas fa-clipboard-list" id="icon-student<?= $student['id'] ?>"></i>
                                <span id="text-student<?= $student['id'] ?>">View Grades</span>
                            </button>
                        </td>
                    </tr>

                    <!-- EXPANDED GRADES ROW -->
                    <tr id="student<?= $student['id'] ?>" class="student-details" style="display:<?= in_array($student['id'], $keep_open_ids) ? 'table-row' : 'none' ?>;">
                        <td colspan="5" style="padding:0;border:none;">
                            <div style="background:var(--slate-50);padding:20px 24px;margin:0;border-top:1px solid var(--border-light);border-bottom:1px solid var(--border-light);">

                                <!-- Student info mini-header -->
                                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1e3a5f,#2563eb);display:flex;align-items:center;justify-content:center;color:white;font-size:14px;font-weight:700;flex-shrink:0;">
                                        <?= strtoupper(substr($student['first_name'],0,1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:700;color:var(--slate-800);font-size:0.95rem;"><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></div>
                                        <div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($student['year_level']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($student['section']) ?></div>
                                    </div>
                                </div>

                                <div class="table-container" style="margin-bottom:0;box-shadow:none;border-radius:var(--radius-lg);overflow:hidden;border:1px solid var(--border-light);">
                                    <table class="inner-table">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;">Subject</th>
                                                <th>Quiz<br><small>10%</small></th>
                                                <th>HW<br><small>10%</small></th>
                                                <th>Activities<br><small>10%</small></th>
                                                <th>Prelim<br><small>20%</small></th>
                                                <th>Midterm<br><small>20%</small></th>
                                                <th>Final<br><small>30%</small></th>
                                                <th>Lab<br><small>20%</small></th>
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
                                                $grade_row    = $grade_result->fetch_assoc();
                                                $stmt_grade->close();
                                                $letter_grade = $grade_row['letter_grade'] ?? '-';
                                        ?>
                                                <tr>
                                                    <td style="text-align:left;font-weight:600;color:var(--slate-800);">
                                                        <?= htmlspecialchars($subject['code']) ?>
                                                    </td>

                                                    <?php if(!$is_admin): ?>
                                                        <input type="hidden" name="student_id[]" value="<?= $student['id'] ?>">
                                                        <input type="hidden" name="subject_id[]" value="<?= $subject['id'] ?>">
                                                        <td><input class="grade-input" type="number" name="quiz[]"       value="<?= $grade_row['quiz']       ?? 0 ?>" min="0" max="20"></td>
                                                        <td><input class="grade-input" type="number" name="homework[]"   value="<?= $grade_row['homework']   ?? 0 ?>" min="0" max="50"></td>
                                                        <td><input class="grade-input" type="number" name="activities[]" value="<?= $grade_row['activities'] ?? 0 ?>" min="0" max="50"></td>
                                                        <td><input class="grade-input" type="number" name="prelim[]"     value="<?= $grade_row['prelim']     ?? 0 ?>" min="0" max="60"></td>
                                                        <td><input class="grade-input" type="number" name="midterm[]"    value="<?= $grade_row['midterm']    ?? 0 ?>" min="0" max="60"></td>
                                                        <td><input class="grade-input" type="number" name="final[]"      value="<?= $grade_row['final']      ?? 0 ?>" min="0" max="60"></td>
                                                        <td><input class="grade-input" type="number" name="lab[]"        value="<?= $grade_row['lab']        ?? 0 ?>" min="0" max="60"></td>
                                                    <?php else: ?>
                                                        <td><input class="readonly-grade" type="text" value="<?= $grade_row['quiz']       ?? '—' ?>" readonly></td>
                                                        <td><input class="readonly-grade" type="text" value="<?= $grade_row['homework']   ?? '—' ?>" readonly></td>
                                                        <td><input class="readonly-grade" type="text" value="<?= $grade_row['activities'] ?? '—' ?>" readonly></td>
                                                        <td><input class="readonly-grade" type="text" value="<?= $grade_row['prelim']     ?? '—' ?>" readonly></td>
                                                        <td><input class="readonly-grade" type="text" value="<?= $grade_row['midterm']    ?? '—' ?>" readonly></td>
                                                        <td><input class="readonly-grade" type="text" value="<?= $grade_row['final']      ?? '—' ?>" readonly></td>
                                                        <td><input class="readonly-grade" type="text" value="<?= $grade_row['lab']        ?? '—' ?>" readonly></td>
                                                    <?php endif; ?>

                                                    <td>
                                                        <?php if($letter_grade != '-'): ?>
                                                            <?php
                                                            if($letter_grade <= 1.5)      $badge_class = 'badge-green';
                                                            elseif($letter_grade <= 2.0)  $badge_class = 'badge-blue';
                                                            elseif($letter_grade <= 2.5)  $badge_class = 'badge-yellow';
                                                            else                          $badge_class = 'badge-red';
                                                            ?>
                                                            <span class="<?= $badge_class ?>"><?= $letter_grade ?></span>
                                                        <?php else: ?> — <?php endif; ?>
                                                    </td>
                                                </tr>
                                        <?php
                                            endwhile;
                                        } else {
                                            echo "<tr><td colspan='9' style='text-align:center;color:var(--accent-rose);padding:20px;'>No subjects assigned for this student.</td></tr>";
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- GPA Summary -->
                                <?php
                                $stmt_gpa = $conn->prepare("SELECT quiz,homework,activities,prelim,midterm,final,lab FROM grades WHERE student_id=?");
                                $stmt_gpa->bind_param("i", $student['id']);
                                $stmt_gpa->execute();
                                $gpa_result = $stmt_gpa->get_result();
                                $total_score_sum = 0; $subject_count = $gpa_result->num_rows;
                                while($g_row = $gpa_result->fetch_assoc()){
                                    $total_grade = ($g_row['quiz']*0.10)+($g_row['homework']*0.10)+($g_row['activities']*0.10)+
                                                   ($g_row['prelim']*0.20)+($g_row['midterm']*0.20)+($g_row['final']*0.30)+($g_row['lab']*0.20);
                                    if($total_grade>=60) $gpa_pts=1.0; elseif($total_grade>=55) $gpa_pts=1.25;
                                    elseif($total_grade>=50) $gpa_pts=1.5; elseif($total_grade>=45) $gpa_pts=1.75;
                                    elseif($total_grade>=40) $gpa_pts=2.0; elseif($total_grade>=35) $gpa_pts=2.25;
                                    elseif($total_grade>=30) $gpa_pts=2.5; elseif($total_grade>=25) $gpa_pts=2.75;
                                    elseif($total_grade>=20) $gpa_pts=3.0; else $gpa_pts=5.0;
                                    $total_score_sum += $gpa_pts;
                                }
                                $stmt_gpa->close();
                                $display_gpa = ($subject_count > 0) ? round($total_score_sum/$subject_count,2) : 0;
                                $gpa_badge = $display_gpa <= 1.5 ? 'badge-green' : ($display_gpa <= 2.5 ? 'badge-blue' : ($display_gpa < 5.0 ? 'badge-yellow' : 'badge-red'));
                                ?>
                                <div class="gpa-summary-bar">
                                    <span class="gpa-label">Cumulative GPA</span>
                                    <span class="<?= $gpa_badge ?>" style="font-size:1rem;padding:6px 18px;border-radius:50px;font-weight:700;"><?= $display_gpa ?></span>
                                </div>

                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>

            <?php if(!$is_admin && mysqli_num_rows($students_query) > 0): ?>
            <div style="margin-top:24px;text-align:right;padding:0 4px;">
                <button type="submit" name="save" style="padding:12px 32px;font-size:13px;border-radius:50px;box-shadow:0 6px 20px rgba(37,99,235,0.35);">
                    <i class="fas fa-save"></i> Save All Grades
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

</div>

<script>
function toggleDetails(id) {
    const row           = document.getElementById(id);
    const expandedInput = document.getElementById('expanded_students_input');
    const icon          = document.getElementById('icon-' + id);
    const text          = document.getElementById('text-' + id);
    const btn           = document.getElementById('btn-' + id);
    let expanded        = expandedInput.value ? expandedInput.value.split(',') : [];

    if(row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
        expanded.push(id.replace('student',''));
        icon.className  = 'fas fa-chevron-up';
        text.textContent = 'Hide Grades';
        btn.classList.add('btn-view-grades-open');
    } else {
        row.style.display = 'none';
        expanded = expanded.filter(s => s !== id.replace('student',''));
        icon.className  = 'fas fa-clipboard-list';
        text.textContent = 'View Grades';
        btn.classList.remove('btn-view-grades-open');
    }
    expandedInput.value = expanded.join(',');
}

document.querySelectorAll('button[onclick^="toggleDetails"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const currentId = this.getAttribute('onclick').match(/'([^']+)'/)[1];
        document.querySelectorAll('.student-details').forEach(row => {
            if(row.id !== currentId && row.style.display === 'table-row') {
                row.style.display = 'none';
                const btnEl = document.getElementById('btn-' + row.id);
                const iconEl = document.getElementById('icon-' + row.id);
                const textEl = document.getElementById('text-' + row.id);
                if(btnEl) {
                    iconEl.className  = 'fas fa-clipboard-list';
                    textEl.textContent = 'View Grades';
                    btnEl.classList.remove('btn-view-grades-open');
                }
            }
        });
    });
});
</script>
</body>
</html>