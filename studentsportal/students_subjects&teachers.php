<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once CONFIG_PATH . 'database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_id = intval($_SESSION['student_id']);

$stmt = $conn->prepare("SELECT course, year_level, section FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) die("Student not found.");

$course_name = $student['course'];
$year        = $student['year_level'];
$section     = $student['section'];

$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$stmt->bind_param("s", $course_name);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("Course not found.");
$course_id = $result->fetch_assoc()['id'];

$stmt = $conn->prepare("
    SELECT s.code, s.subject_name, s.description, s.room, s.day,
           s.time_start, s.time_end, s.instructor, s.subject_type, s.section,
           CONCAT(t.first_name,' ',IFNULL(t.middle_name,''),' ',t.last_name,' ',IFNULL(t.suffix,'')) AS instructor_name,
           t.email AS instructor_email
    FROM subjects s
    LEFT JOIN teachers t ON s.instructor = t.teacher_id
    WHERE s.course_id = ? AND s.year_level = ?
    AND (s.section IS NULL OR s.section = '' OR s.section = ?)
    ORDER BY s.subject_name ASC
");
$stmt->bind_param("iss", $course_id, $year, $section);
$stmt->execute();
$subjects_result = $stmt->get_result();

$subjects = [];
while($row = $subjects_result->fetch_assoc()) $subjects[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects &amp; Teachers</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?= asset('css/studentportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <!-- ── Page Header ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><i class="fas fa-book"></i> Enrolled Subjects</div>
            <h1 class="page-header-title">Subjects &amp; Teachers</h1>
        </div>
        <span class="result-count">
            <i class="fas fa-layer-group"></i>
            <?php echo count($subjects); ?> subjects
        </span>
    </div>

    <!-- ── Course info strip ── -->
    <div style="margin-bottom:24px;padding:14px 20px;background:rgba(255,255,255,0.92);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.6);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <span style="display:inline-flex;align-items:center;gap:6px;font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);">
            <i class="fas fa-graduation-cap" style="color:var(--primary-blue);"></i>
            <?php echo htmlspecialchars($course_name); ?>
        </span>
        <span style="width:1px;height:16px;background:var(--border-medium);"></span>
        <span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);">Year <?php echo htmlspecialchars($year); ?></span>
        <span style="width:1px;height:16px;background:var(--border-medium);"></span>
        <span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);">Section <?php echo htmlspecialchars($section); ?></span>
    </div>

    <?php if (count($subjects) === 0): ?>
        <div class="no-tasks">
            <i class="fas fa-book-open"></i>
            <p>No subjects found for your course, year level, or section.</p>
        </div>
    <?php else: ?>

    <!-- ── Subject Cards ── -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;">
        <?php foreach ($subjects as $subject): ?>
        <div class="subject-card-enhanced">

            <!-- Card header (dark navy gradient like teacher portal) -->
            <div class="subject-card-header">
                <div style="flex:1;">
                    <h3><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                    <span style="font-size:0.72rem;opacity:0.7;letter-spacing:0.05em;"><?php echo htmlspecialchars($subject['code']); ?></span>
                </div>
                <span class="<?php echo $subject['subject_type'] === 'MINOR' ? 'badge-minor' : 'badge-major'; ?>"
                      style="align-self:flex-start;white-space:nowrap;">
                    <?php echo strtoupper($subject['subject_type'] ?? 'MAJOR'); ?>
                </span>
            </div>

            <!-- Card body -->
            <div class="subject-card-body">
                <?php if(!empty($subject['description'])): ?>
                <div class="subject-card-row">
                    <i class="fas fa-info-circle"></i>
                    <div><strong>Description</strong><br><?php echo htmlspecialchars($subject['description']); ?></div>
                </div>
                <?php endif; ?>

                <div class="subject-card-row">
                    <i class="fas fa-door-open"></i>
                    <div><strong>Room</strong><br><?php echo htmlspecialchars($subject['room'] ?: '—'); ?></div>
                </div>

                <div class="subject-card-row">
                    <i class="fas fa-calendar-week"></i>
                    <div>
                        <strong>Schedule</strong><br>
                        <?php echo htmlspecialchars($subject['day'] ?: '—'); ?>
                        <?php if($subject['time_start'] && $subject['time_end']): ?>
                            &nbsp;·&nbsp;
                            <span style="font-weight:600;color:var(--primary-blue);">
                                <?php echo date("h:i A", strtotime($subject['time_start'])); ?> –
                                <?php echo date("h:i A", strtotime($subject['time_end'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="subject-card-row" style="margin-bottom:0;">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <div>
                        <strong>Instructor</strong><br>
                        <?php
                        $name = trim($subject['instructor_name'] ?? '');
                        echo htmlspecialchars($name ?: $subject['instructor'] ?: '—');
                        ?>
                        <?php if(!empty($subject['instructor_email'])): ?>
                            <br>
                            <a href="mailto:<?php echo htmlspecialchars($subject['instructor_email']); ?>"
                               style="font-size:0.82rem;color:var(--primary-blue);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-top:2px;">
                                <i class="fas fa-envelope" style="font-size:11px;"></i>
                                <?php echo htmlspecialchars($subject['instructor_email']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

</body>
</html>