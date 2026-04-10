<?php
/**
 * addsubject.php (PRODUCTION-READY)
 * Teachers can add a new subject for their selected course.
 */

session_start();

require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

// ================== SESSION CHECK ==================
if (!isset($_SESSION['teacher_id'])) {
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

// ================== COURSE ==================
$course = $_SESSION['teacher_course'] ?? '';

if (empty($course)) {
    die("No course selected. Cannot add subject.");
}

// ================== GET COURSE ID (SAFE) ==================
$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$stmt->bind_param("s", $course);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("Selected course not found in database.");
}

$course_row = $result->fetch_assoc();
$course_id = $course_row['id'];

// ================== FETCH TEACHERS ==================
$stmt = $conn->prepare("
    SELECT id, teacher_id, 
    CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name, ' ', IFNULL(suffix, '')) AS full_name, 
    year_levels, sections 
    FROM teachers 
    WHERE course = ?
");
$stmt->bind_param("s", $course);
$stmt->execute();
$teachers_result = $stmt->get_result();

$teachers = [];
while ($t = $teachers_result->fetch_assoc()) {
    $teachers[] = $t;
}

// ================== MESSAGE ==================
$message = '';
$message_type = '';

// ================== FORM HANDLING ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code = strtoupper(trim($_POST['code']));
    $subject_name = strtoupper(trim($_POST['subject_name']));
    $year_level = trim($_POST['year_level']);
    $section = strtoupper(trim($_POST['section']));
    $description = trim($_POST['description']);
    $room = strtoupper(trim($_POST['room']));
    $day = trim($_POST['day']);
    $time_start = $_POST['time_start'];
    $time_end = $_POST['time_end'];
    $instructor = trim($_POST['instructor']);
    $subject_type = trim($_POST['subject_type']);

    // ================== VALIDATION ==================
    if (!preg_match('/^[A-E]$/', $section)) {
        $message = "Invalid section! Only A to E is allowed.";
        $message_type = 'error';
    } else {

        // ================== INSERT (SAFE) ==================
        $stmt = $conn->prepare("
            INSERT INTO subjects 
            (course_id, course, code, subject_name, subject_type, year_level, section, description, room, day, time_start, time_end, instructor)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "issssssssssss",
            $course_id,
            $course,
            $code,
            $subject_name,
            $subject_type,
            $year_level,
            $section,
            $description,
            $room,
            $day,
            $time_start,
            $time_end,
            $instructor
        );

        if ($stmt->execute()) {
            header("Location: " . BASE_URL . "teachersportal/subjects.php?msg=added");
            exit();
        } else {
            $message = "Error adding subject.";
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subject | Teacher Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/teachersaccess.css') ?>">
</head>
<body>

<div class="container">

    <!-- RIGHT PANEL — Dark brand panel -->
    <div class="right-panel">
        <a href="<?= BASE_URL ?>teachersportal/subjects.php" class="back-arrow" title="Back">↩</a>
        <h1>Subject<br>Management</h1>
    </div>

    <!-- LEFT PANEL — Form panel -->
    <div class="left-panel">
        <div class="icon"><i class="fas fa-book-open"></i></div>
        <h2>Add Subject</h2>
        <p>Create a new subject for: <strong><?= htmlspecialchars($course) ?></strong></p>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="register-form">

            <fieldset>
                <legend><i class="fas fa-book-open"></i> Subject Information</legend>

                <div class="form-row">
                    <div>
                        <label>Subject Code</label>
                        <input type="text" name="code" required oninput="this.value=this.value.toUpperCase()">
                    </div>
                    <div>
                        <label>Subject Name</label>
                        <input type="text" name="subject_name" required oninput="this.value=this.value.toUpperCase()">
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label>Year Level</label>
                        <select name="year_level" id="year_level" required>
                            <option value="">Select Year Level</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div>
                        <label>Section</label>
                        <input type="text" name="section" id="section" maxlength="1" value="A" required
                               oninput="this.value=this.value.toUpperCase().replace(/[^A-E]/g,'');">
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label>Subject Type</label>
                        <select name="subject_type" required>
                            <option value="">Select Type</option>
                            <option value="Major">Major Subject</option>
                            <option value="Minor">Minor Subject</option>
                        </select>
                    </div>
                    <div>
                        <label>Room</label>
                        <input type="text" name="room" required oninput="this.value=this.value.toUpperCase()">
                    </div>
                </div>

                <div>
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
            </fieldset>

            <fieldset>
                <legend><i class="fas fa-chalkboard-teacher"></i> Schedule & Instructor</legend>

                <div class="form-row">
                    <div>
                        <label>Instructor</label>
                        <select name="instructor" id="instructor" required>
                            <option value="">Select Instructor</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= htmlspecialchars($teacher['teacher_id']) ?>">
                                    <?= htmlspecialchars($teacher['full_name']) ?> (<?= htmlspecialchars($teacher['teacher_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Day</label>
                        <select name="day" required>
                            <option value="">Select Day</option>
                            <option>Monday</option>
                            <option>Tuesday</option>
                            <option>Wednesday</option>
                            <option>Thursday</option>
                            <option>Friday</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label>Start Time</label>
                        <input type="time" name="time_start" required>
                    </div>
                    <div>
                        <label>End Time</label>
                        <input type="time" name="time_end" required>
                    </div>
                </div>
            </fieldset>

            <div style="display:flex; gap:12px; margin-top:8px;">
                <button type="submit" class="btn register-btn" style="flex:1;">
                    <i class="fas fa-plus"></i> Add Subject
                </button>
                <a href="<?= BASE_URL ?>teachersportal/subjects.php"
                   class="btn"
                   style="flex:1; background: var(--slate-500); color:white; text-decoration:none; margin-top:8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>

        </form>
    </div>

</div>

<script>
const allTeachers = <?= json_encode($teachers) ?>;

function filterInstructors() {
    const yearLevel = document.getElementById('year_level').value;
    const section = document.getElementById('section').value.toUpperCase();
    const instructor = document.getElementById('instructor');

    instructor.innerHTML = '<option value="">Select Instructor</option>';

    let filtered = allTeachers;

    if (yearLevel && section) {
        filtered = allTeachers.filter(t => {
            return t.year_levels.includes(yearLevel) && t.sections.toUpperCase().includes(section);
        });
    }

    filtered.forEach(t => {
        let opt = document.createElement('option');
        opt.value = t.teacher_id;
        opt.textContent = `${t.full_name} (${t.teacher_id})`;
        instructor.appendChild(opt);
    });
}

document.getElementById('year_level').addEventListener('change', filterInstructors);
document.getElementById('section').addEventListener('input', filterInstructors);
</script>

</body>
</html>