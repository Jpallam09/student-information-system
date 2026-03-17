<?php
/**
 * addsubject.php
 * 
 * Teachers can add a new subject for their selected course.
 */

session_start();
include '../config/database.php';

if(!isset($_SESSION['teacher_id'])){
    header("Location: ../Accesspage/teacher_login.php");
    exit();
}

$course = $_SESSION['teacher_course'] ?? '';
if(empty($course)){
    echo "No course selected. Cannot add subject.";
    exit();
}

$course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$course'");
if(!$course_result || mysqli_num_rows($course_result) == 0){
    die("Selected course not found in database.");
}
$course_row = mysqli_fetch_assoc($course_result);
$course_id = $course_row['id'];

// Fetch teachers registered for this course (with year_levels and sections)
$teachers_query = mysqli_query($conn, "SELECT id, teacher_id, CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name, ' ', IFNULL(suffix, '')) AS full_name, year_levels, sections FROM teachers WHERE course='$course'");
$teachers = [];
while($t = mysqli_fetch_assoc($teachers_query)) {
    $teachers[] = $t;
}

$message = '';
$message_type = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $code = mysqli_real_escape_string($conn, $_POST['code']);
    $subject_name = mysqli_real_escape_string($conn, $_POST['subject_name']);
    $year_level = mysqli_real_escape_string($conn, $_POST['year_level']);
    $section = mysqli_real_escape_string($conn, $_POST['section']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $room = mysqli_real_escape_string($conn, $_POST['room']);
    $day = mysqli_real_escape_string($conn, $_POST['day']);
    $time_start = mysqli_real_escape_string($conn, $_POST['time_start']);
    $time_end = mysqli_real_escape_string($conn, $_POST['time_end']);
    $instructor = mysqli_real_escape_string($conn, $_POST['instructor']);

    $sql = "INSERT INTO subjects 
            (course_id, code, subject_name, year_level, section, description, room, day, time_start, time_end, instructor) 
            VALUES 
            ('$course_id', '$code', '$subject_name', '$year_level', '$section', '$description', '$room', '$day', '$time_start', '$time_end', '$instructor')";

    if(mysqli_query($conn, $sql)){
        header("Location: ../teachersportal/subjects.php?msg=added");
        exit();
    } else {
        $message = "Error adding subject: " . mysqli_error($conn);
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subject | Teacher Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../css/teachersaccess.css">
</head>
<body>

<div class="container">
    <div class="left-panel">
        <a href="../teachersportal/subjects.php" class="back-arrow">↩</a>
        <div class="icon"><i class="fas fa-book-open"></i></div>
        <h2>Add Subject</h2>
        <p>Create a new subject for: <strong><?= htmlspecialchars($course) ?></strong></p>

        <?php if(!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas <?= $message_type == 'error' ? 'fa-exclamation-circle' : 'fa-info-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="register-form">
            <fieldset>
                <legend>Subject Information</legend>
                <div class="form-row">
                    <div>
                        <label for="code">Subject Code</label>
                        <input type="text" id="code" name="code" placeholder="e.g., CS101" required oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div>
                        <label for="subject_name">Subject Name</label>
                        <input type="text" id="subject_name" name="subject_name" placeholder="e.g., Computer Science" oninput="this.value = this.value.toUpperCase()" required>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="year_level">Year Level</label>
                        <select name="year_level" id="year_level" required>
                            <option value="">Select Year Level</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div>
                        <label for="section">Section</label>
                        <input type="text" id="section" name="section" placeholder="e.g., A" value="A" maxlength="1" required oninput="this.value = this.value.toUpperCase().replace(/[^A-E]/g,'');">
                    </div>
                </div>
                <div class="form-row">
                    <div style="grid-column: span 2;">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" placeholder="Subject description (optional)" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="room">Room</label>
                        <input type="text" id="room" name="room" placeholder="e.g., Room 101" oninput="this.value = this.value.toUpperCase()"required>
                    </div>
                    <div>
                        <label for="instructor">Instructor</label>
                        <select id="instructor" name="instructor" required>
                            <option value="">Select Instructor for <?= htmlspecialchars($course) ?></option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?= htmlspecialchars($teacher['teacher_id']) ?>"><?= htmlspecialchars($teacher['full_name']) ?> (<?= htmlspecialchars($teacher['teacher_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="day">Day</label>
                        <select name="day" id="day" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="time_start">Start Time</label>
                        <input type="time" name="time_start" id="time_start" required>
                    </div>
                    <div>
                        <label for="time_end">End Time</label>
                        <input type="time" name="time_end" id="time_end" required>
                    </div>
                </div>
            </fieldset>
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="submit" class="btn register-btn" style="flex: 1;">
                    <i class="fas fa-plus"></i> Add Subject
                </button>
                <a href="../teachersportal/subjects.php" class="btn" style="flex: 1; background: var(--slate-500); color: white; text-decoration: none;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <div class="right-panel">
        <h1>Subject<br>Management</h1>
    </div>
</div>

<script>
const allTeachers = <?= json_encode($teachers) ?>;

function filterInstructors() {
    const yearLevel = document.getElementById('year_level').value.trim();
    const section = document.getElementById('section').value.trim().toUpperCase();
    const instructorSelect = document.getElementById('instructor');
    
    // Clear existing options except first
    instructorSelect.innerHTML = '<option value="">Select Instructor for <?= htmlspecialchars($course) ?></option>';
    
    let filteredTeachers = allTeachers;
    
    if (yearLevel && section) {
        filteredTeachers = allTeachers.filter(teacher => {
            const teacherYears = teacher.year_levels.split(',').map(y => y.trim());
            const teacherSections = teacher.sections.split(',').map(s => s.trim().toUpperCase());
            return teacherYears.includes(yearLevel) && teacherSections.includes(section);
        });
    }
    
    // Add filtered options
    filteredTeachers.forEach(teacher => {
        const option = document.createElement('option');
        option.value = teacher.teacher_id;
        option.textContent = `${teacher.full_name} (${teacher.teacher_id})`;
        instructorSelect.appendChild(option);
    });
    
    // Show/hide message
    if (yearLevel && section && filteredTeachers.length === 0) {
        const noMatchOption = document.createElement('option');
        noMatchOption.value = '';
        noMatchOption.textContent = 'No instructors assigned to this year/section';
        noMatchOption.disabled = true;
        instructorSelect.appendChild(noMatchOption);
    }
}

// Event listeners
document.getElementById('year_level').addEventListener('change', filterInstructors);
document.getElementById('section').addEventListener('input', filterInstructors);
</script>

</body>
</html>

