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

// ================== SET BACK ARROW LOGIC ==================
$back_url = BASE_URL . "Accesspage/teacher_login.php";
if(isset($_SESSION['teacher_type']) && $_SESSION['teacher_type'] === "Administrator"){
    $back_url = BASE_URL . "teachersportal/chooseSub.php";
}

// ================== SET COURSE FROM SESSION ==================
$selected_course = $_SESSION['teacher_course'] ?? '';
if(empty($selected_course)){
    echo "Course not assigned to this teacher. Contact admin.";
    exit();
}

// ================== BUILD TEACHER FILTER ==================
$teacher_year_filter = '';
$teacher_section_filter = '';
if (!$is_admin) {
    $y_params = []; $y_types = '';
    $teacher_year_filter = getCombinedYearFilter('year_level', $y_params, $y_types);
    $s_params = []; $s_types = '';
    $teacher_section_filter = getCombinedSectionFilter('section', $s_params, $s_types);
}

// ================== TOTAL STUDENTS ==================
$total_students_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM students WHERE course='$selected_course' $teacher_year_filter $teacher_section_filter");
$total_students_row = mysqli_fetch_assoc($total_students_query);
$total_students = $total_students_row['total'];

// ================== ACTIVE SECTIONS ==================
$active_sections_query = mysqli_query($conn, "SELECT COUNT(DISTINCT section) AS total FROM students WHERE course='$selected_course' $teacher_year_filter $teacher_section_filter");
$active_sections_row = mysqli_fetch_assoc($active_sections_query);
$active_sections = $active_sections_row['total'];

// ================== AVERAGE ATTENDANCE ==================
$avg_attendance_query = mysqli_query($conn, "
    SELECT AVG(CASE WHEN attendance.status='present' THEN 1 ELSE 0 END) AS avg_att
    FROM attendance
    JOIN students ON students.id = attendance.student_id
    WHERE students.course='$selected_course'
    $teacher_year_filter
    $teacher_section_filter
");
$avg_attendance_row = mysqli_fetch_assoc($avg_attendance_query);
$avg_attendance = round(($avg_attendance_row['avg_att'] ?? 0)*100,2);

// ================== CLASS AVERAGE ==================
$class_avg_query = mysqli_query($conn, "
    SELECT AVG(percentage) AS class_avg
    FROM grades
    JOIN students ON students.id = grades.student_id
    WHERE students.course='$selected_course'
    $teacher_year_filter
    $teacher_section_filter
");
$class_avg_row = mysqli_fetch_assoc($class_avg_query);
$class_avg = round($class_avg_row['class_avg'],2);

// ================== ATTENDANCE BY YEAR LEVEL ==================
$teacher_year_levels = !$is_admin ? getTeacherYearLevels() : ['1st Year','2nd Year','3rd Year','4th Year'];
$year_levels = $teacher_year_levels;
$attendance_by_year = [];

foreach($teacher_year_levels as $year) {
    $year_query = mysqli_query($conn, "
        SELECT 
            COUNT(CASE WHEN attendance.status='present' THEN 1 END) as total_present,
            COUNT(*) as total_records
        FROM attendance
        JOIN students ON students.id = attendance.student_id
        WHERE students.course='$selected_course'
        AND students.year_level='$year'
        AND YEAR(attendance.date) = YEAR(CURDATE())
        $teacher_section_filter
    ");
    $row = mysqli_fetch_assoc($year_query);
    $attendance_by_year[$year] = ($row['total_records']>0) ? round(($row['total_present']/$row['total_records'])*100,2) : 0;
}

// ================== ANNOUNCEMENT STATISTICS ==================
$announcement_stats = [];
foreach($teacher_year_levels as $year) {
    $announce_query = mysqli_query($conn, "
        SELECT COUNT(*) as total 
        FROM announcements 
        WHERE course_id='$selected_course' 
        AND year_level='$year'
    ");
    $row = mysqli_fetch_assoc($announce_query);
    $announcement_stats[$year] = $row['total'];
}

// ================== GRADE DISTRIBUTION ==================
$grades = ['1.0'=>0,'1.25'=>0,'1.5'=>0,'1.75'=>0,'2.0'=>0,'2.25'=>0,'2.5'=>0,'2.75'=>0,'3.0'=>0,'4.0'=>0,'5.0'=>0];
$grade_query = mysqli_query($conn, "
    SELECT grades.letter_grade 
    FROM grades 
    JOIN students ON students.id = grades.student_id
    WHERE students.course='$selected_course'
    $teacher_year_filter
    $teacher_section_filter
");
while($row = mysqli_fetch_assoc($grade_query)){
    $g = $row['letter_grade'];
    if(isset($grades[$g])) $grades[$g]++;
}

// ================== YEAR LEVELS & SECTIONS ==================
$sections_query = mysqli_query($conn, "SELECT DISTINCT section FROM students WHERE course='$selected_course' $teacher_year_filter ORDER BY section ASC");
$sections = [];
while($row = mysqli_fetch_assoc($sections_query)){
    $sections[] = $row['section'];
}

// ================== RANKING STUDENTS (GPA 1.0-1.5) ==================
$ranking_students = [];
$student_query = mysqli_query($conn, "SELECT * FROM students WHERE course='$selected_course' $teacher_year_filter $teacher_section_filter ORDER BY last_name ASC");

while($student = mysqli_fetch_assoc($student_query)){
    $student_id = $student['id'];
    $grades_query = mysqli_query($conn, "SELECT quiz, homework, activities, prelim, midterm, final, lab FROM grades WHERE student_id='$student_id'");
    $total_subjects = mysqli_num_rows($grades_query);
    $gpa_sum = 0;
    while($grade_row = mysqli_fetch_assoc($grades_query)){
        $total_grade = 
            ($grade_row['quiz']*0.10)+
            ($grade_row['homework']*0.10)+
            ($grade_row['activities']*0.10)+
            ($grade_row['prelim']*0.20)+
            ($grade_row['midterm']*0.20)+
            ($grade_row['final']*0.30)+
            ($grade_row['lab']*0.20);

        if($total_grade >= 60) $gpa_points = 1.0;
        elseif($total_grade >= 55) $gpa_points = 1.25;
        elseif($total_grade >= 50) $gpa_points = 1.5;
        elseif($total_grade >= 45) $gpa_points = 1.75;
        elseif($total_grade >= 40) $gpa_points = 2.0;
        elseif($total_grade >= 35) $gpa_points = 2.25;
        elseif($total_grade >= 30) $gpa_points = 2.5;
        elseif($total_grade >= 25) $gpa_points = 2.75;
        elseif($total_grade >= 20) $gpa_points = 3.0;
        else $gpa_points = 5.0;

        $gpa_sum += $gpa_points;
    }
    $avg_gpa = ($total_subjects>0) ? round($gpa_sum/$total_subjects,2) : 0;
    if($avg_gpa>=1.0 && $avg_gpa<=1.5){
        $student['gpa'] = $avg_gpa;
        $ranking_students[] = $student;
    }
}
usort($ranking_students, function($a,$b){ return $a['gpa'] <=> $b['gpa']; });

// ================== FETCH TEACHER FULL NAME ==================
$teacher_query = mysqli_query($conn, "
    SELECT CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name, ' ', IFNULL(suffix, '')) AS full_name
    FROM teachers 
    WHERE id = " . intval($_SESSION['teacher_id']) . "
");
$teacher_row = mysqli_fetch_assoc($teacher_query);
$teacher_full_name = trim($teacher_row['full_name'] ?? ($_SESSION['teacher_name'] ?? 'User'));

// ================== FETCH ATTENDANCE DATA FOR MODAL ==================
$attendance_students_query = mysqli_query($conn, "
    SELECT s.id, s.student_id, s.first_name, s.last_name, s.year_level, s.section,
           COUNT(CASE WHEN a.status='present' THEN 1 END) as total_present,
           COUNT(a.id) as total_records
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id AND YEAR(a.date) = YEAR(CURDATE())
    WHERE s.course='$selected_course' $teacher_year_filter $teacher_section_filter
    GROUP BY s.id
    ORDER BY s.year_level, s.section, s.last_name
");

$attendance_students = [];
while($row = mysqli_fetch_assoc($attendance_students_query)){
    $row['attendance_percentage'] = ($row['total_records']>0) ? round(($row['total_present']/$row['total_records'])*100,2) : 0;
    $attendance_students[] = $row;
}

// ================== TEACHER ASSIGNMENTS DISPLAY ==================
$teacher_assignment_display = getTeacherAssignmentDisplay();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — <?= htmlspecialchars($selected_course) ?></title>
<link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- Top Right Logo -->
<div class="top-right-logo">
    <img src="<?= asset('images/622685015_925666030131412_6886851389087569993_n.jpg') ?>" 
         alt="Isabela State University seal">
</div>

<?php include PROJECT_ROOT . '/teachersportal/sidebar.php'; ?>

<div class="content">

    <!-- PAGE TITLE -->
    <h1 class="dash-title">
        <i class="fas fa-chart-pie"></i>
        <?= htmlspecialchars($selected_course) ?> Dashboard
    </h1>

    <!-- WELCOME BANNER -->
    <div class="dashboard-welcome">
        <div class="dashboard-welcome-icon">
            <?php if ($is_admin): ?>
                <i class="fas fa-shield-halved"></i>
            <?php else: ?>
                <i class="fas fa-chalkboard-teacher"></i>
            <?php endif; ?>
        </div>
        <div class="dashboard-welcome-text">
            <div class="eyebrow"><span></span> <?= date('l, F j, Y') ?></div>
            <h2>
                <?php if ($is_admin): ?>
                    Welcome back, System Administrator
                <?php else: ?>
                    Welcome back
                    <strong><?= htmlspecialchars($teacher_full_name) ?></strong>
                <?php endif; ?>
            </h2>
        </div>
        <div class="welcome-course-badge">
            <i class="fas fa-graduation-cap" style="margin-right:6px;"></i>
            <?= htmlspecialchars($selected_course) ?>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="cards">
        <div class="card">
            <h3><i class="fas fa-users"></i> Total Students</h3>
            <h1><?= $total_students ?></h1>
            <p>Enrolled this period</p>
        </div>
        <div class="card">
            <h3><i class="fas fa-layer-group"></i> Active Sections</h3>
            <h1><?= $active_sections ?></h1>
            <p>Across all year levels</p>
        </div>
        <div class="card">
            <h3><i class="fas fa-check-circle"></i> Avg. Attendance</h3>
            <h1><?= $avg_attendance ?>%</h1>
            <p>Year-to-date average</p>
        </div>
        <div class="card">
            <h3><i class="fas fa-star"></i> Class Average</h3>
            <h1><?= $class_avg ?></h1>
            <p>GPA across all subjects</p>
        </div>
        <div class="card clickable-card" data-type="ranking" title="View ranking students">
            <h3><i class="fas fa-medal"></i> Top Students</h3>
            <h1><?= count($ranking_students) ?></h1>
            <p>GPA 1.0 – 1.5 achievers</p>
            <span class="card-click-hint">Click to view →</span>
        </div>
        <div class="card clickable-card" data-type="attendance" title="View attendance overview">
            <h3><i class="fas fa-calendar-check"></i> Attendance Overview</h3>
            <h1>View</h1>
            <p>Per student breakdown</p>
            <span class="card-click-hint">Click to view →</span>
        </div>

        <?php if (!$is_admin): ?>
        <div class="card">
            <h3><i class="fas fa-layer-group"></i> Your Assignment</h3>
            <h1 style="font-size:1.1rem; color: var(--primary-blue); margin-top:6px;"><?= htmlspecialchars($teacher_assignment_display) ?></h1>
            <p>Year level &amp; section</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ATTENDANCE BY YEAR LEVEL -->
    <div class="section-box">
        <h2><i class="fas fa-chart-bar"></i> Attendance by Year Level</h2>
        <div class="attendance-cards">
            <?php foreach($year_levels as $year): ?>
            <div class="attendance-year-card" data-year="<?= htmlspecialchars($year) ?>">
                <h3><i class="fas fa-users"></i> <?= htmlspecialchars($year) ?></h3>
                <h1><?= $attendance_by_year[$year] ?>%</h1>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $attendance_by_year[$year] ?>%;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ANNOUNCEMENT STATISTICS -->
    <div class="section-box">
        <h2><i class="fas fa-bullhorn"></i> Announcement Statistics</h2>
        <div class="announcement-cards">
            <?php 
            $total_announcements = array_sum($announcement_stats);
            foreach($year_levels as $year): 
                $count = $announcement_stats[$year];
                $percentage = ($total_announcements > 0) ? round(($count / $total_announcements) * 100, 1) : 0;
            ?>
            <div class="announcement-stat-card">
                <h4><?= htmlspecialchars($year) ?></h4>
                <div class="count"><?= $count ?></div>
                <div class="percentage"><?= $percentage ?>% of total</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $percentage ?>%;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- GRADE DISTRIBUTION -->
    <div class="charts-container">
        <div class="section-box">
            <h2><i class="fas fa-chart-pie"></i> Grade Distribution</h2>
            <canvas id="gradeChart" height="100"></canvas>
        </div>
    </div>

</div><!-- /.content -->


<!-- =================== RANKING STUDENTS MODAL =================== -->
<div id="studentModal" class="modal">
    <div class="modal-content" style="max-width:100%;width:100%;height:100vh;margin:0;border-radius:0;max-height:100vh;">
        <span class="close">&times;</span>
        <h2 id="modalTitle"><i class="fas fa-medal"></i> Top Ranking Students</h2>

        <div class="modal-filter-row">
            <div>
                <label for="yearFilter">Year Level</label>
                <select id="yearFilter">
                    <option value="">All Years</option>
                    <?php foreach($year_levels as $year): ?>
                        <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="sectionFilter">Section</label>
                <select id="sectionFilter">
                    <option value="">All Sections</option>
                    <?php foreach($sections as $sec): ?>
                        <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <table id="modalTable">
            <thead>
                <tr><th>Rank</th><th>Name</th><th>Year Level</th><th>Section</th><th>GPA</th></tr>
            </thead>
            <tbody>
                <?php foreach($ranking_students as $index=>$student): ?>
                <tr data-year="<?= htmlspecialchars(trim($student['year_level'])) ?>" 
                    data-section="<?= htmlspecialchars(trim($student['section'])) ?>" 
                    data-gpa="<?= $student['gpa'] ?>">
                    <td>
                        <?php if($index === 0): ?>
                            <span class="badge-yellow" style="padding:4px 12px;border-radius:50px;font-weight:700;">🥇 #1</span>
                        <?php elseif($index === 1): ?>
                            <span class="badge-blue" style="padding:4px 12px;border-radius:50px;font-weight:700;">🥈 #2</span>
                        <?php elseif($index === 2): ?>
                            <span style="background:rgba(180,83,9,0.12);color:#92400e;padding:4px 12px;border-radius:50px;font-weight:700;display:inline-flex;align-items:center;">🥉 #3</span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-weight:600;">#<?= $index+1 ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:600;color:var(--slate-800);"><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></td>
                    <td><?= htmlspecialchars($student['year_level']) ?></td>
                    <td><?= htmlspecialchars($student['section']) ?></td>
                    <td><span class="badge-green" style="font-size:0.85rem;"><?= $student['gpa'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- =================== ATTENDANCE OVERVIEW MODAL =================== -->
<div id="attendanceModal" class="modal">
    <div class="modal-content" style="max-width:100%;width:100%;height:100vh;margin:0;border-radius:0;max-height:100vh;">
        <span class="close">&times;</span>
        <h2><i class="fas fa-calendar-check"></i> Attendance Overview</h2>

        <div class="stats-container" style="margin-bottom:24px;">
            <div class="stat-box">
                <h3>Overall Attendance</h3>
                <div class="stat-number"><?= $avg_attendance ?>%</div>
            </div>
            <div class="stat-box">
                <h3>Total Students</h3>
                <div class="stat-number"><?= $total_students ?></div>
            </div>
            <div class="stat-box">
                <h3>Active Sections</h3>
                <div class="stat-number"><?= $active_sections ?></div>
            </div>
        </div>

        <div class="modal-filter-row">
            <div>
                <label for="attendanceYearFilter">Year Level</label>
                <select id="attendanceYearFilter">
                    <option value="">All Years</option>
                    <?php foreach($year_levels as $year): ?>
                        <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="attendanceSectionFilter">Section</label>
                <select id="attendanceSectionFilter">
                    <option value="">All Sections</option>
                    <?php foreach($sections as $sec): ?>
                        <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <table id="attendanceModalTable">
            <thead>
                <tr>
                    <th>Student ID</th><th>Name</th><th>Year Level</th>
                    <th>Section</th><th>Attendance %</th><th>Present / Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($attendance_students as $student): ?>
                <tr data-year="<?= htmlspecialchars($student['year_level']) ?>" 
                    data-section="<?= htmlspecialchars($student['section']) ?>">
                    <td style="font-family:monospace;font-size:0.85rem;"><?= htmlspecialchars($student['student_id']) ?></td>
                    <td style="font-weight:600;color:var(--slate-800);"><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></td>
                    <td><?= htmlspecialchars($student['year_level']) ?></td>
                    <td><?= htmlspecialchars($student['section']) ?></td>
                    <td>
                        <?php
                        $pct = $student['attendance_percentage'];
                        $cls = $pct >= 80 ? 'badge-green' : ($pct >= 60 ? 'badge-yellow' : 'badge-red');
                        ?>
                        <span class="<?= $cls ?>" style="font-size:0.82rem;"><?= $pct ?>%</span>
                        <div class="progress-bar" style="margin-top:5px;">
                            <div class="progress-fill" style="width:<?= $pct ?>%;"></div>
                        </div>
                    </td>
                    <td><?= $student['total_present'] ?> / <?= $student['total_records'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- =================== YEAR LEVEL ATTENDANCE MODAL =================== -->
<div id="yearAttendanceModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="yearModalTitle"><i class="fas fa-users"></i> Year Level Attendance</h2>

        <div class="modal-filter-row">
            <div>
                <label for="yearAttendanceSectionFilter">Section</label>
                <select id="yearAttendanceSectionFilter">
                    <option value="">All Sections</option>
                    <?php foreach($sections as $sec): ?>
                        <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <table id="yearAttendanceTable">
            <thead>
                <tr><th>Student ID</th><th>Name</th><th>Section</th><th>Attendance %</th><th>Present / Total</th></tr>
            </thead>
            <tbody id="yearAttendanceTableBody"></tbody>
        </table>
    </div>
</div>


<script>
// Grade Distribution Chart
new Chart(document.getElementById('gradeChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($grades)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($grades)) ?>,
            backgroundColor: [
                '#2563eb','#1d4ed8','#3b82f6','#60a5fa','#93c5fd',
                '#f59e0b','#f97316','#ef4444','#8b5cf6','#06b6d4','#94a3b8'
            ],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    font: { family: "'DM Sans', sans-serif", size: 13 },
                    padding: 16,
                    boxWidth: 12,
                    boxHeight: 12,
                    borderRadius: 6,
                    usePointStyle: true
                }
            }
        },
        cutout: '60%'
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const rankingModal        = document.getElementById('studentModal');
    const attendanceModal     = document.getElementById('attendanceModal');
    const yearAttendanceModal = document.getElementById('yearAttendanceModal');
    const closeButtons        = document.querySelectorAll('.close');
    const attendanceStudents  = <?= json_encode($attendance_students) ?>;

    // Open modals via clickable cards
    document.querySelectorAll('.clickable-card').forEach(card => {
        card.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            if (type === 'ranking')    rankingModal.style.display    = 'flex';
            if (type === 'attendance') attendanceModal.style.display = 'flex';
        });
    });

    // Year attendance card click
    document.querySelectorAll('.attendance-year-card').forEach(card => {
        card.addEventListener('click', function() {
            const year = this.getAttribute('data-year');
            showYearAttendance(year);
        });
    });

    function showYearAttendance(year) {
        document.getElementById('yearModalTitle').innerHTML = '<i class="fas fa-users"></i> ' + year + ' — Attendance';
        const yearStudents = attendanceStudents.filter(s => s.year_level === year);
        const tbody = document.getElementById('yearAttendanceTableBody');
        tbody.innerHTML = '';
        yearStudents.forEach(student => {
            const pct = student.attendance_percentage;
            const cls = pct >= 80 ? 'badge-green' : (pct >= 60 ? 'badge-yellow' : 'badge-red');
            const row = document.createElement('tr');
            row.setAttribute('data-section', student.section);
            row.innerHTML = `
                <td style="font-family:monospace;font-size:0.85rem;">${student.student_id}</td>
                <td style="font-weight:600;color:var(--slate-800);">${student.first_name} ${student.last_name}</td>
                <td>${student.section}</td>
                <td>
                    <span class="${cls}" style="font-size:0.82rem;">${pct}%</span>
                    <div class="progress-bar" style="margin-top:5px;">
                        <div class="progress-fill" style="width:${pct}%;"></div>
                    </div>
                </td>
                <td>${student.total_present}/${student.total_records}</td>
            `;
            tbody.appendChild(row);
        });
        yearAttendanceModal.style.display = 'flex';
    }

    // Close modals
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            rankingModal.style.display        = 'none';
            attendanceModal.style.display     = 'none';
            yearAttendanceModal.style.display = 'none';
        });
    });

    window.addEventListener('click', function(event) {
        if (event.target === rankingModal)        rankingModal.style.display        = 'none';
        if (event.target === attendanceModal)     attendanceModal.style.display     = 'none';
        if (event.target === yearAttendanceModal) yearAttendanceModal.style.display = 'none';
    });

    // Ranking filter
    const yearFilter    = document.getElementById('yearFilter');
    const sectionFilter = document.getElementById('sectionFilter');
    if (yearFilter)    yearFilter.addEventListener('change', filterRankingTable);
    if (sectionFilter) sectionFilter.addEventListener('change', filterRankingTable);

    function filterRankingTable() {
        const selectedYear    = yearFilter    ? yearFilter.value    : '';
        const selectedSection = sectionFilter ? sectionFilter.value : '';
        const rows = document.querySelectorAll('#modalTable tbody tr');
        let visibleCount = 1;
        rows.forEach(row => {
            const yearMatch    = !selectedYear    || row.getAttribute('data-year')    === selectedYear;
            const sectionMatch = !selectedSection || row.getAttribute('data-section') === selectedSection;
            row.style.display  = yearMatch && sectionMatch ? '' : 'none';
        });
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const rankCell = row.querySelector('td:first-child');
                if (rankCell) {
                    rankCell.innerHTML = `<span style="color:var(--text-muted);font-weight:600;">#${visibleCount++}</span>`;
                }
            }
        });
    }

    // Attendance modal filter
    const attendanceYearFilter    = document.getElementById('attendanceYearFilter');
    const attendanceSectionFilter = document.getElementById('attendanceSectionFilter');
    if (attendanceYearFilter)    attendanceYearFilter.addEventListener('change', filterAttendanceTable);
    if (attendanceSectionFilter) attendanceSectionFilter.addEventListener('change', filterAttendanceTable);

    function filterAttendanceTable() {
        const selectedYear    = attendanceYearFilter    ? attendanceYearFilter.value    : '';
        const selectedSection = attendanceSectionFilter ? attendanceSectionFilter.value : '';
        document.querySelectorAll('#attendanceModalTable tbody tr').forEach(row => {
            const yearMatch    = !selectedYear    || row.getAttribute('data-year')    === selectedYear;
            const sectionMatch = !selectedSection || row.getAttribute('data-section') === selectedSection;
            row.style.display  = yearMatch && sectionMatch ? '' : 'none';
        });
    }

    // Year attendance section filter
    const yearAttendanceSectionFilter = document.getElementById('yearAttendanceSectionFilter');
    if (yearAttendanceSectionFilter) {
        yearAttendanceSectionFilter.addEventListener('change', function() {
            const selectedSection = this.value;
            document.querySelectorAll('#yearAttendanceTable tbody tr').forEach(row => {
                row.style.display = !selectedSection || row.getAttribute('data-section') === selectedSection ? '' : 'none';
            });
        });
    }
});
</script>
</body>
</html>