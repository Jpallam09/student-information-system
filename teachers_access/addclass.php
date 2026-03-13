<?php
/**
 * addclass.php
 * 
 * Teachers can add a new class/section for a selected course.
 */

session_start();
include '../config/database.php';

// SESSION CHECK
if(!isset($_SESSION['teacher_id'])){
    header("Location: ../Accesspage/admin_login.php");
    exit();
}

$message = '';
$message_type = '';

$selected_course = $_SESSION['teacher_course'] ?? '';
if(empty($selected_course)){
    die("No course selected. Please go back and choose a course.");
}

$course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$selected_course'");
if(!$course_result || mysqli_num_rows($course_result) == 0){
    die("Selected course does not exist in the database. Cannot add class.");
}
$course_row = mysqli_fetch_assoc($course_result);
$course_id = $course_row['id'];

// FORM SUBMISSION
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $section = mysqli_real_escape_string($conn, $_POST['section']);
    $year_level = mysqli_real_escape_string($conn, $_POST['year_level']);

    $check = mysqli_query($conn, "SELECT * FROM classes WHERE course_id='$course_id' AND section='$section' AND year_level='$year_level'");
    if(mysqli_num_rows($check) > 0){
        $message = "This class/section already exists!";
        $message_type = 'error';
    } else {
        $insert = mysqli_query($conn, "INSERT INTO classes (course_id, section, year_level) VALUES ('$course_id', '$section', '$year_level')");
        if($insert){
            $message = "Class added successfully!";
            $message_type = 'success';
        } else {
            $message = "Error adding class: " . mysqli_error($conn);
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
    <title>Add Class | Teacher Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../css/teachersaccess.css">
</head>
<body>

<div class="container">
    <div class="left-panel">
        <a href="../teachersportal/subjects.php" class="back-arrow">↩</a>
        <div class="icon"><i class="fas fa-school"></i></div>
        <h2>Add Class</h2>
        <p>Create a new section for: <strong><?= htmlspecialchars($selected_course) ?></strong></p>

        <?php if(!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="register-form">
            <fieldset>
                <legend>Class Information</legend>

                <div class="form-row">
                    <div>
                        <label for="section">Section</label>
                        <input type="text" 
                               id="section" 
                               name="section" 
                               placeholder="e.g., A" 
                               value="A"             
                               maxlength="1"           
                               pattern="[A-E]"         
                               title="Please enter a letter from A to E"
                               required
                               oninput="this.value = this.value.toUpperCase().replace(/[^A-E]/g,'');">
                    </div>

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
            </fieldset>

            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="submit" class="btn register-btn" style="flex: 1;">
                    <i class="fas fa-plus"></i> Add Class
                </button>
                <a href="../teachersportal/subjects.php" class="btn" style="flex: 1; background: var(--slate-500); color: white; text-decoration: none;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <div class="right-panel">
        <h1>Class<br>Management</h1>
    </div>

</body>
</html>
