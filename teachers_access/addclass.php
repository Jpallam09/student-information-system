<?php
/**
 * addclass.php (PRODUCTION-READY)
 * Teachers can add a new class/section for a selected course.
 */

session_start();

require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

// ================== SESSION CHECK ==================
if (!isset($_SESSION['teacher_id'])) {
    header("Location: " . BASE_URL . "Accesspage/admin_login.php");
    exit();
}

$message = '';
$message_type = '';

// ================== GET SELECTED COURSE ==================
$selected_course = $_SESSION['teacher_course'] ?? '';

if (empty($selected_course)) {
    die("No course selected. Please go back and choose a course.");
}

// ================== FETCH COURSE ID (SAFE) ==================
$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$stmt->bind_param("s", $selected_course);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("Selected course does not exist in the database. Cannot add class.");
}

$course_row = $result->fetch_assoc();
$course_id = $course_row['id'];

// ================== FORM SUBMISSION ==================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $section = strtoupper(trim($_POST['section']));
    $year_level = trim($_POST['year_level']);

    // ================== VALIDATION ==================
    if (!preg_match('/^[A-E]$/', $section)) {
        $message = "Invalid section! Only A to E is allowed.";
        $message_type = 'error';
    } elseif (empty($year_level)) {
        $message = "Year level is required!";
        $message_type = 'error';
    } else {

        // ================== CHECK DUPLICATE ==================
        $stmt = $conn->prepare("SELECT id FROM classes WHERE course_id = ? AND section = ? AND year_level = ?");
        $stmt->bind_param("iss", $course_id, $section, $year_level);
        $stmt->execute();
        $check = $stmt->get_result();

        if ($check->num_rows > 0) {
            $message = "This class/section already exists!";
            $message_type = 'error';
        } else {

            // ================== INSERT ==================
            $stmt = $conn->prepare("INSERT INTO classes (course_id, section, year_level) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $course_id, $section, $year_level);

            if ($stmt->execute()) {
                $message = "Class added successfully!";
                $message_type = 'success';
            } else {
                $message = "Error adding class.";
                $message_type = 'error';
            }
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
 <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
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
        <h1>Class<br>Management</h1>
    </div>

    <!-- LEFT PANEL — Form panel -->
    <div class="left-panel">
        <div class="icon"><i class="fas fa-school"></i></div>
        <h2>Add Class</h2>
        <p>Create a new section for: <strong><?= htmlspecialchars($selected_course) ?></strong></p>

        <?php if (!empty($message)): ?>
            <div class="message <?= $message_type ?>">
                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="register-form">
            <fieldset>
                <legend><i class="fas fa-layer-group"></i> Class Information</legend>

                <div class="form-row">
                    <div>
                        <label for="section">Section</label>
                        <input type="text"
                               id="section"
                               name="section"
                               placeholder="e.g., A"
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
                </div>
            </fieldset>

            <div style="display: flex; gap: 12px; margin-top: 8px;">
                <button type="submit" class="btn register-btn" style="flex: 1;">
                    <i class="fas fa-plus"></i> Add Class
                </button>
                <a href="<?= BASE_URL ?>teachersportal/subjects.php"
                   class="btn"
                   style="flex: 1; background: var(--slate-500); color: white; text-decoration: none; margin-top: 8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

</div>
</body>
</html>