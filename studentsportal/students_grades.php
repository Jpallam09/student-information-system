<?php
session_start();
include '../config/database.php';

// Ensure student is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: ../Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Fetch student info
$student_query = mysqli_query($conn, "SELECT * FROM students WHERE id='$student_id'");
$student = mysqli_fetch_assoc($student_query);

// GPA
$gpa = $student['gpa'] ?? 0;

/* =========================
   FIX: GET COURSE ID
========================= */
$course_name = $student['course'];
$course_result = mysqli_query($conn,"SELECT id FROM courses WHERE course_name='$course_name'");
$course_row = mysqli_fetch_assoc($course_result);
$course_id = $course_row['id'];

$student_section = $student['section'];

/* =========================
   FETCH SUBJECTS + GRADES
========================= */
$subjects_query = mysqli_query($conn,
    "SELECT s.code AS subject_code, 
            s.subject_name,
            g.quiz, g.homework, g.activities, 
            g.prelim, g.midterm, g.final, 
            g.lab, g.letter_grade
     FROM subjects s
     LEFT JOIN grades g 
        ON g.subject_id = s.id 
        AND g.student_id = '$student_id'
     WHERE s.course_id='$course_id'
     AND s.year_level='{$student['year_level']}'
     AND s.section='$student_section'
     ORDER BY s.subject_name ASC"
);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Grades & Assessments - Student Portal</title>
    <link rel="stylesheet" href="../css/studentportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include 'students_sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h2 class="page-title">Grades & Assessments</h2>
        <p class="page-subtitle">View your academic performance</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Current GPA</span>
                <i class="fas fa-award stat-icon"></i>
            </div>
            <div class="stat-value"><?= $gpa ?></div>
            <p class="stat-meta">Out of 4.0</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width:<?= ($gpa/4)*100 ?>%"></div>
            </div>
        </div>
    </div>

    <!-- SUBJECT GRADES TABLE -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Subject Grades</h3>
            <p class="card-description">Grades as entered by your teachers</p>
        </div>
        <div class="card-content">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th>Quiz</th>
                        <th>Homework</th>
                        <th>Activities</th>
                        <th>Prelim</th>
                        <th>Midterm</th>
                        <th>Final</th>
                        <th>Lab</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($sub = mysqli_fetch_assoc($subjects_query)): 
                        $grade = $sub['letter_grade'] ?? '-';
                        if(in_array($grade, ["1.0","1.25","1.5"])) $badge="badge-green";
                        elseif(in_array($grade, ["1.75","2.0"])) $badge="badge-blue";
                        elseif(in_array($grade, ["2.25","2.5"])) $badge="badge-yellow";
                        elseif($grade=="-") $badge="";
                        else $badge="badge-red";
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                        <td>Teacher Not Assigned</td>
                        <td><?= $sub['quiz'] ?? '-' ?></td>
                        <td><?= $sub['homework'] ?? '-' ?></td>
                        <td><?= $sub['activities'] ?? '-' ?></td>
                        <td><?= $sub['prelim'] ?? '-' ?></td>
                        <td><?= $sub['midterm'] ?? '-' ?></td>
                        <td><?= $sub['final'] ?? '-' ?></td>
                        <td><?= $sub['lab'] ?? '-' ?></td>
                        <td><?= $badge ? "<span class='$badge'>$grade</span>" : '-' ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>