<?php
session_start();
include '../config/database.php';

if(!isset($_SESSION['student_id'])){
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$student_id = $_SESSION['student_id'];

// Fetch student info
$student_query = mysqli_query($conn, "SELECT * FROM students WHERE id='$student_id'");
$student = mysqli_fetch_assoc($student_query);

// GPA
$gpa = $student['gpa'] ?? 0;

// Subjects with grades and teachers
$subjects_query = mysqli_query($conn,
    "SELECT s.id AS subject_id, s.code AS subject_code, s.subject_name, s.credits,
            t.first_name AS teacher_first, t.last_name AS teacher_last,
            g.quiz, g.homework, g.activities, g.prelim, g.midterm, g.final, g.lab, g.letter_grade
     FROM subjects s
     LEFT JOIN teachers t ON t.id = s.teacher_id
     LEFT JOIN grades g ON g.subject_id = s.id AND g.student_id = '$student_id'
     WHERE s.course='{$student['course']}' AND s.year_level='{$student['year_level']}'
     ORDER BY s.subject_name ASC"
);

// Pending assignments
$assignments_query = mysqli_query($conn,
    "SELECT a.title, s.code AS subject_code, a.type, a.due_date, a.max_score, a.status
     FROM assignments a
     INNER JOIN subjects s ON s.id = a.subject_id
     WHERE a.student_id='$student_id'
     ORDER BY a.due_date ASC"
);

// Prepare arrays
$subjects = [];
while($row = mysqli_fetch_assoc($subjects_query)){
    $subjects[] = $row;
}

$assignments = [];
while($row = mysqli_fetch_assoc($assignments_query)){
    $assignments[] = $row;
}

// Return JSON
echo json_encode([
    'gpa' => $gpa,
    'subjects' => $subjects,
    'assignments' => $assignments
]);