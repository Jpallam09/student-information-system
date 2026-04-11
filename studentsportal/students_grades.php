<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once CONFIG_PATH . 'database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_id = intval($_SESSION['student_id']);

$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) { session_destroy(); header("Location: " . BASE_URL . "Accesspage/student_login.php"); exit(); }

$gpa             = $student['gpa'] ?? 0;
$course_name     = $student['course'];
$student_section = $student['section'];
$year_level      = $student['year_level'];

$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$stmt->bind_param("s", $course_name);
$stmt->execute();
$course_row = $stmt->get_result()->fetch_assoc();
$course_id  = $course_row['id'] ?? 0;

$stmt = $conn->prepare("
    SELECT s.code AS subject_code, s.subject_name,
           g.quiz, g.homework, g.activities, g.prelim, g.midterm, g.final, g.lab, g.letter_grade,
           CONCAT(t.first_name,' ',t.last_name) AS teacher_name
    FROM subjects s
    LEFT JOIN grades g ON g.subject_id = s.id AND g.student_id = ?
    LEFT JOIN teachers t ON t.course = ?
        AND FIND_IN_SET(?, t.year_levels) > 0
        AND FIND_IN_SET(?, t.sections) > 0
    WHERE s.course_id = ? AND s.year_level = ? AND s.section = ?
    ORDER BY s.subject_name ASC
");
$stmt->bind_param("isssiis",
    $student_id, $course_name, $year_level, $student_section,
    $course_id, $year_level, $student_section
);
$stmt->execute();
$subjects_result = $stmt->get_result();

// Collect for summary
$grades_list = [];
$rows = [];
while($sub = $subjects_result->fetch_assoc()){
    $rows[] = $sub;
    if(!empty($sub['letter_grade']) && is_numeric($sub['letter_grade']))
        $grades_list[] = floatval($sub['letter_grade']);
}
$dynamic_gpa = count($grades_list) > 0 ? round(array_sum($grades_list)/count($grades_list), 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades — Student Portal</title>
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
            <div class="page-header-eyebrow"><i class="fas fa-star"></i> Academic Performance</div>
            <h1 class="page-header-title">Grades &amp; Assessments</h1>
        </div>
        <span class="result-count">
            <i class="fas fa-graduation-cap"></i>
            <?php echo htmlspecialchars($course_name); ?> · Yr <?php echo htmlspecialchars($year_level); ?> · §<?php echo htmlspecialchars($student_section); ?>
        </span>
    </div>

    <!-- ── GPA Stat Cards ── -->
    <div class="stats-grid" style="margin-bottom:28px;">
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Current GPA</span><i class="fas fa-award"></i></div>
            <div class="stat-value"><?php echo $dynamic_gpa > 0 ? $dynamic_gpa : ($gpa > 0 ? $gpa : 'N/A'); ?></div>
            <p class="stat-meta">Out of 4.0</p>
            <div class="progress-bar"><div class="progress-fill" style="width:<?php echo min((($dynamic_gpa ?: $gpa)/4)*100,100); ?>%"></div></div>
        </div>

        <div class="stat-card present-card">
            <div class="stat-header"><span class="stat-label">Graded Subjects</span><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?php echo count($grades_list); ?></div>
            <p class="stat-meta">of <?php echo count($rows); ?> total subjects</p>
        </div>

        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Highest Grade</span><i class="fas fa-trophy"></i></div>
            <div class="stat-value" style="color:var(--accent-emerald);"><?php echo !empty($grades_list) ? min($grades_list) : '—'; ?></div>
            <p class="stat-meta">Best performing subject</p>
        </div>
    </div>

    <!-- ── Grades Table ── -->
    <div class="card" style="cursor:default;padding:0;overflow:hidden;">
        <div style="padding:24px 28px 16px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <div class="page-header-eyebrow" style="margin-bottom:4px;"><i class="fas fa-table"></i> Subject Breakdown</div>
                <h3 class="card-title">Grades as Entered by Your Teachers</h3>
            </div>
        </div>

        <div class="table-container" style="border-radius:0;box-shadow:none;border:none;margin:0;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th style="text-align:left;min-width:160px;">Subject</th>
                        <th style="text-align:left;min-width:120px;">Instructor</th>
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
                    <?php foreach($rows as $sub):
                        $grade = $sub['letter_grade'] ?? '-';
                        $g = floatval($grade);
                        if(is_numeric($grade)){
                            if($g <= 1.5)      $badge = 'badge-green';
                            elseif($g <= 2.0)  $badge = 'badge-blue';
                            elseif($g <= 2.5)  $badge = 'badge-yellow';
                            else               $badge = 'badge-red';
                        } else {
                            $badge = '';
                        }
                        $teacher = !empty(trim($sub['teacher_name'])) ? htmlspecialchars(trim($sub['teacher_name'])) : '<em style="color:var(--text-muted);">Not assigned</em>';

                        $cells = ['quiz','homework','activities','prelim','midterm','final','lab'];
                        $has_any = false;
                        foreach($cells as $c) if($sub[$c] !== null && $sub[$c] !== '') { $has_any = true; break; }
                    ?>
                    <tr>
                        <td style="text-align:left;">
                            <div style="font-weight:700;color:var(--slate-800);font-size:0.9rem;"><?php echo htmlspecialchars($sub['subject_name']); ?></div>
                            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;font-family:monospace;"><?php echo htmlspecialchars($sub['subject_code']); ?></div>
                        </td>
                        <td style="text-align:left;"><?php echo $teacher; ?></td>
                        <?php foreach($cells as $c): ?>
                            <td><?php echo ($sub[$c] !== null && $sub[$c] !== '') ? htmlspecialchars($sub[$c]) : '<span style="color:var(--text-muted);">—</span>'; ?></td>
                        <?php endforeach; ?>
                        <td>
                            <?php if($badge && $grade !== '-'): ?>
                                <span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($grade); ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if(empty($rows)): ?>
                    <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text-muted);">
                        <i class="fas fa-clipboard" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.4;"></i>
                        No subjects found for your course and section.
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- GPA Summary bar -->
        <?php if($dynamic_gpa > 0): ?>
        <div style="padding:16px 28px;border-top:1px solid var(--border-light);display:flex;align-items:center;justify-content:flex-end;gap:12px;background:var(--slate-50);">
            <span style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);">Computed GPA</span>
            <span style="font-family:'Playfair Display',Georgia,serif;font-size:1.5rem;font-weight:700;color:var(--primary-blue);"><?php echo $dynamic_gpa; ?></span>
            <span style="font-size:0.8rem;color:var(--text-muted);">/ 4.0</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Grade Legend ── -->
    <div style="margin-top:20px;padding:16px 24px;background:rgba(255,255,255,0.92);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.6);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);">
        <p style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:10px;">Grade Scale</p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <span class="badge-green">1.0 – 1.5 · Excellent</span>
            <span class="badge-blue">1.75 – 2.0 · Good</span>
            <span class="badge-yellow">2.25 – 2.5 · Satisfactory</span>
            <span class="badge-red">2.75+ · Needs Improvement</span>
        </div>
    </div>

</div>
</body>
</html>