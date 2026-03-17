<?php
session_start();
include '../config/database.php';

/* -----------------------------
   STUDENT AUTHENTICATION
------------------------------ */
if(!isset($_SESSION['student_id'])){
    header("Location: ../Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* -----------------------------
   GET STUDENT INFO
------------------------------ */
$student_query = mysqli_query($conn, "
    SELECT course, year_level, section 
    FROM students 
    WHERE id='$student_id'
") or die(mysqli_error($conn));

$student = mysqli_fetch_assoc($student_query);

$course_name = $student['course'];
$year        = $student['year_level'];
$section     = $student['section'];

/* -----------------------------
   GET COURSE ID
------------------------------ */
$course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$course_name'");
if(!$course_result || mysqli_num_rows($course_result) == 0){
    die("Course not found in database.");
}
$course_row = mysqli_fetch_assoc($course_result);
$course_id = $course_row['id'];

/* -----------------------------
   FETCH SUBJECTS WITH SCHEDULE DETAILS
------------------------------ */
/**
 * Retrieves all subjects for the student's course, year level, and section.
 * Includes subjects for the student’s section OR general subjects (no section)
 */
$subjects_query = mysqli_query($conn, "
    SELECT s.code, s.subject_name, s.description, s.room, s.day, s.time_start, s.time_end, 
           s.instructor, s.section,
           CONCAT(t.first_name, ' ', IFNULL(t.middle_name, ''), ' ', t.last_name, ' ', IFNULL(t.suffix, '')) AS instructor_name,
           t.email AS instructor_email
    FROM subjects s
    LEFT JOIN teachers t ON s.instructor = t.teacher_id
    WHERE s.course_id='$course_id'
      AND s.year_level='$year'
      AND (s.section IS NULL OR s.section='' OR s.section='$section')
    ORDER BY s.subject_name ASC
") or die(mysqli_error($conn));

$subjects = [];
while($row = mysqli_fetch_assoc($subjects_query)){
    $subjects[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subjects & Teachers</title>
    <link rel="stylesheet" href="../css/studentportal.css">
</head>
<body>

<?php include 'students_sidebar.php'; ?>

<div class="main-content">

    <h2>Subjects & Teachers</h2>
    <p><?= htmlspecialchars($course_name) ?> - <?= htmlspecialchars($year) ?> - Section <?= htmlspecialchars($section) ?></p>

    <?php if(count($subjects) > 0): ?>
        <?php foreach($subjects as $subject): ?>
            <div class="card">
                <!-- Subject Name -->
                <h3><?= htmlspecialchars($subject['subject_name']) ?></h3>

                <!-- Subject Code -->
                <p><span class="label">Subject Code:</span> <?= htmlspecialchars($subject['code']) ?></p>

                <!-- Description -->
                <p><span class="label">Description:</span> <?= htmlspecialchars($subject['description']) ?></p>

                <!-- Room -->
                <p><span class="label">Room:</span> <?= htmlspecialchars($subject['room']) ?></p>

                <!-- Day and Time -->
                <p><span class="label">Schedule:</span> 
                    <?= htmlspecialchars($subject['day']) ?> 
                    <span class="schedule-time">
                        <?= htmlspecialchars(date("h:i A", strtotime($subject['time_start']))) ?> - 
                        <?= htmlspecialchars(date("h:i A", strtotime($subject['time_end']))) ?>
                    </span>
                </p>

                <!-- Instructor -->
                <p><span class="label">Instructor:</span> <?= htmlspecialchars($subject['instructor_name'] ?: $subject['instructor']) ?></p>

                <!-- Instructor Email -->
                <p><span class="label">Email:</span> <?= htmlspecialchars($subject['instructor_email'] ?: '-') ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No subjects found for your course, year level, or section.</p>
    <?php endif; ?>

</div>

</body>
</html>