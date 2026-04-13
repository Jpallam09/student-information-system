<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => true,
    'cookie_samesite' => 'Strict'
]);

require_once dirname(__DIR__) . '/config/paths.php';
require_once CONFIG_PATH . 'database.php';
require_once CONFIG_PATH . 'current_school_year.php';
require_once CONFIG_PATH . 'fee_structure.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_id = intval($_SESSION['student_id']);

// ================= FETCH STUDENT =================
$stmt = $conn->prepare("
    SELECT first_name, middle_name, last_name, student_id, course, year_level, section, school_year, semester, status
    FROM students WHERE id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    session_destroy();
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_name = trim($student['first_name'].' '.($student['middle_name'] ? $student['middle_name'].' ' : '').$student['last_name']);
$status      = $student['status']      ?? 'N/A';
$school_year = $student['school_year'] ?? 'N/A';
$semester    = $student['semester']    ?? 'N/A';
$course_name = htmlspecialchars($student['course']);
$year_level  = htmlspecialchars($student['year_level']);
$section     = htmlspecialchars($student['section'] ?? 'N/A');

// ================= GET COURSE ID =================
$stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
$stmt->bind_param("s", $student['course']);
$stmt->execute();
$course_result = $stmt->get_result();
$course_id = $course_result->num_rows > 0 ? $course_result->fetch_assoc()['id'] : 0;

// ================= FETCH SUBJECTS =================
$stmt = $conn->prepare("
    SELECT s.code, s.subject_name, s.description, s.room, s.day, s.time_start, s.time_end,
           s.instructor, s.section, s.subject_type,
           CONCAT(t.first_name,' ',IFNULL(t.middle_name,''),' ',t.last_name,IFNULL(t.suffix,'')) AS instructor_name
    FROM subjects s
    LEFT JOIN teachers t ON s.instructor = t.teacher_id
    WHERE s.course_id = ? AND s.year_level = ? AND (s.section = ? OR s.section = '' OR s.section IS NULL)
");
$stmt->bind_param("iss", $course_id, $year_level, $section);
$stmt->execute();
$subjects_result = $stmt->get_result();

$subjects = []; $total_units = 0;
while ($row = $subjects_result->fetch_assoc()) {
    $row['units'] = isset($row['units']) ? (int)$row['units'] : 3;
    $total_units += $row['units'];
    $subjects[] = $row;
}

$major_count = 0;
foreach ($subjects as $sub) {
    if (!empty($sub['subject_type']) && strtolower($sub['subject_type']) === 'major') $major_count++;
}

// ================= FEES =================
$fees       = getFeeStructure($conn, $student['course'], $major_count);
$assessment = calculateAssessment($fees, $total_units);
$total_tuition = $assessment['tuition'];
$total_amount  = $assessment['total_amount'];
$down_payment  = $assessment['down_payment'];
$balance       = $assessment['balance'];

// ================= LOGO AS BASE64 =================
$logo_filename = '622685015_925666030131412_6886851389087569993_n.jpg';
$logo_path     = IMAGES_DIR . $logo_filename;
$logo_base64 = '';
$logo_mime   = 'JPEG';
if (file_exists($logo_path)) {
    $logo_base64 = base64_encode(file_get_contents($logo_path));
    $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
    $logo_mime = ($ext === 'png') ? 'PNG' : 'JPEG';
}

// ================= EXPAND COURSE ACRONYM =================
function expandCourseAcronym(string $course): string {
    $map = ['BSIT'=>'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY','BSCS'=>'BACHELOR OF SCIENCE IN COMPUTER SCIENCE','BSIS'=>'BACHELOR OF SCIENCE IN INFORMATION SYSTEMS','BSECE'=>'BACHELOR OF SCIENCE IN ELECTRONICS AND COMMUNICATIONS ENGINEERING','BSEE'=>'BACHELOR OF SCIENCE IN ELECTRICAL ENGINEERING','BSCE'=>'BACHELOR OF SCIENCE IN CIVIL ENGINEERING','BSME'=>'BACHELOR OF SCIENCE IN MECHANICAL ENGINEERING','BSEd'=>'BACHELOR OF SCIENCE IN EDUCATION','BSED'=>'BACHELOR OF SCIENCE IN EDUCATION','BSBA'=>'BACHELOR OF SCIENCE IN BUSINESS ADMINISTRATION','BSACCT'=>'BACHELOR OF SCIENCE IN ACCOUNTANCY','BSN'=>'BACHELOR OF SCIENCE IN NURSING','BSHM'=>'BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT','BSTM'=>'BACHELOR OF SCIENCE IN TOURISM MANAGEMENT','BSAG'=>'BACHELOR OF SCIENCE IN AGRICULTURE','BSCRIM'=>'BACHELOR OF SCIENCE IN CRIMINOLOGY','AB'=>'BACHELOR OF ARTS','BEEd'=>'BACHELOR OF ELEMENTARY EDUCATION','BEED'=>'BACHELOR OF ELEMENTARY EDUCATION','BSPsych'=>'BACHELOR OF SCIENCE IN PSYCHOLOGY'];
    foreach ($map as $acronym => $full) { if (stripos($course, $acronym) !== false) return str_ireplace($acronym, $full, $course); }
    return strtoupper($course);
}
$course_full_name = expandCourseAcronym($student['course']);

// ================= SUBJECTS JSON =================
$subjects_js = [];
foreach ($subjects as $s) {
    $time_start = !empty($s['time_start']) ? date('h:i A', strtotime($s['time_start'])) : '';
    $time_end   = !empty($s['time_end'])   ? date('h:i A', strtotime($s['time_end']))   : '';
    $sched      = trim(($s['day']??'').' '.$time_start.($time_end?'-'.$time_end:''));
    $instr      = trim($s['instructor_name']??$s['instructor']??'TBA');
    $instr      = preg_replace('/\s+/',' ',$instr)?:'TBA';
    $subjects_js[] = ['code'=>$s['code']??'','name'=>$s['subject_name']??'','desc'=>$s['description']??'','units'=>(int)($s['units']??3),'sched'=>$sched,'instr'=>$instr,'section'=>$s['section']??''];
}
$subjects_json = json_encode($subjects_js, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT);

$MISC_ITEMS = [
    ['Library Fee',100],['Medical and Dental Fee',50],['Athletics Fee',50],
    ['Registration Fee',50],['SBO/SSC/SSCF',60],['School Paper / University Organ Fee',50],
    ['Guidance Fee',20],['Socio Cultural Fee',25],['Internet Fee',40],
    ['Journal Fee',50],['Student Development Fee',200],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Form – <?= htmlspecialchars($student_name) ?></title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/studentportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        /* ═══════════════════════════════════════════
           ASSESSMENT PAGE — Unified with studentportal.css
           Inherits all CSS variables from the portal
        ═══════════════════════════════════════════ */

        /* ── Wrap ── */
        .assessment-wrap {
            max-width: 1020px;
            margin: 0 auto;
        }

        /* ── Document card ── */
        .assessment-doc {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 28px;
        }

        /* ── Document header (matches dashboard-welcome) ── */
        .assessment-doc-header {
            background: linear-gradient(160deg, #1e3a5f 0%, #0f1f3d 60%, #0a1628 100%);
            padding: 36px 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .assessment-doc-header::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 220px; height: 220px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.05);
            pointer-events: none;
        }
        .assessment-doc-header::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -40px;
            width: 260px; height: 260px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.04);
            pointer-events: none;
        }
        .assessment-logo {
            width: 82px; height: 82px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.2);
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
            animation: sidebarLogoFloat 4s ease-in-out infinite;
        }
        @keyframes sidebarLogoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        .assessment-school-name {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.45rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
            letter-spacing: -0.01em;
        }
        .assessment-school-addr {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.45);
            margin-bottom: 18px;
            position: relative;
            z-index: 1;
        }
        .assessment-doc-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 50px;
            padding: 8px 22px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.85);
            position: relative;
            z-index: 1;
        }

        /* ── Sections ── */
        .assessment-section {
            padding: 28px 32px;
            border-bottom: 1px solid var(--border-light);
        }
        .assessment-section:last-child { border-bottom: none; }

        /* ── Section title (matches section-heading style) ── */
        .assessment-section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--slate-800);
            margin-bottom: 20px;
        }
        .assessment-section-title .section-icon {
            width: 36px; height: 36px;
            border-radius: var(--radius-md);
            background: rgba(37,99,235,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-blue);
            font-size: 14px;
            flex-shrink: 0;
        }

        /* ── Info table ── */
        .info-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .info-table td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }
        .info-table td:first-child {
            font-weight: 600;
            color: var(--slate-700);
            width: 38%;
            background: var(--slate-50);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .info-table td:last-child { color: var(--text-secondary); }
        .info-table tr:last-child td { border-bottom: none; }

        /* ── Subjects table ── */
        .subjects-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
        .subjects-table th {
            background: linear-gradient(135deg, #1e3a5f 0%, #0f1f3d 100%);
            color: rgba(255,255,255,0.85);
            font-weight: 600;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 12px 14px;
            text-align: left;
            white-space: nowrap;
        }
        .subjects-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .subjects-table tbody tr:nth-child(even) { background: rgba(248,250,252,0.7); }
        .subjects-table tbody tr:hover { background: rgba(37,99,235,0.04); transition: background 0.15s ease; }
        .subjects-table tbody tr:last-child td { border-bottom: none; }
        .subjects-table .code-cell {
            font-weight: 700;
            color: var(--slate-800);
            font-family: monospace;
            font-size: 0.88rem;
        }
        .subjects-table .name-cell { font-weight: 600; color: var(--slate-800); }
        .subjects-table .units-cell {
            text-align: center;
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 1rem;
        }
        .subjects-table tfoot td {
            background: rgba(37,99,235,0.07);
            font-weight: 700;
            color: var(--slate-800);
            font-size: 0.9rem;
            padding: 13px 14px;
        }
        .subjects-table tfoot .units-cell {
            color: var(--primary-blue);
            font-size: 1.1rem;
        }

        /* ── Fee table ── */
        .fee-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .fee-table td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .fee-table tr:last-child td { border-bottom: none; }
        .fee-table .fee-label { font-weight: 500; color: var(--slate-700); }
        .fee-table .fee-amount {
            text-align: right;
            font-weight: 700;
            color: var(--slate-800);
            width: 150px;
        }
        .fee-table .fee-total td {
            background: rgba(30,58,95,0.06);
            font-weight: 700;
            font-size: 1rem;
            border-top: 2px solid var(--border-medium);
        }
        .fee-table .fee-total .fee-label {
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .fee-table .fee-total .fee-amount {
            color: var(--accent-rose);
            font-size: 1.2rem;
        }

        /* ── Misc dropdown ── */
        .misc-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary-blue);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            transition: color 0.15s;
        }
        .misc-toggle:hover { color: var(--primary-dark); }
        .misc-toggle i { font-size: 10px; transition: transform 0.2s; }
        .misc-toggle.open i { transform: rotate(180deg); }

        .misc-dropdown {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            padding: 8px 0;
            margin-top: 10px;
            display: none;
        }
        .misc-dropdown-item {
            padding: 9px 18px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            transition: background 0.12s;
        }
        .misc-dropdown-item:hover { background: var(--slate-50); }
        .misc-dropdown-item span:last-child { font-weight: 600; color: var(--slate-700); }
        .misc-dropdown-total {
            padding: 11px 18px;
            font-weight: 700;
            color: var(--slate-800);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border-light);
            margin-top: 4px;
            font-size: 0.88rem;
        }

        /* ── Signature grid ── */
        .sig-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .sig-box {
            border: 1px solid var(--border-medium);
            border-radius: var(--radius-lg);
            padding: 22px;
            min-height: 130px;
            display: flex;
            flex-direction: column;
            background: var(--slate-50);
        }
        .sig-label {
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--slate-700);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sig-label i { color: var(--primary-blue); font-size: 12px; }
        .sig-line {
            flex: 1;
            border-bottom: 1px solid var(--border-medium);
            margin-bottom: 10px;
        }
        .sig-sub { font-size: 0.72rem; color: var(--text-muted); }

        /* ── Actions bar ── */
        .assessment-actions {
            padding: 22px 32px;
            background: var(--slate-50);
            border-top: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .assessment-footer {
            text-align: center;
            padding: 16px 32px;
            border-top: 1px solid var(--border-light);
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* ── No subjects state ── */
        .no-subjects-state {
            text-align: center;
            padding: 52px 32px;
            color: var(--text-muted);
        }
        .no-subjects-state .empty-state-icon {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: var(--slate-100);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 1.8rem;
            color: var(--text-muted);
        }
        .no-subjects-state h3 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.05rem;
            color: var(--slate-700);
            margin-bottom: 6px;
        }
        .no-subjects-state p { font-size: 0.875rem; color: var(--text-muted); }

        /* ── Download button spinner ── */
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .spinner {
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }

        /* ── Fee breakdown row highlight ── */
        .fee-row-tuition td { border-left: 3px solid var(--primary-blue); }
        .fee-row-misc td    { border-left: 3px solid var(--accent-violet); }
        .fee-row-lab td     { border-left: 3px solid var(--accent-emerald); }

        /* ── Type badge for subjects ── */
        .badge-major { background: rgba(37,99,235,0.1); color: var(--primary-blue); padding: 3px 9px; border-radius: 50px; font-weight: 700; font-size: 10px; letter-spacing: 0.05em; text-transform: uppercase; }
        .badge-minor { background: rgba(100,116,139,0.12); color: var(--slate-600); padding: 3px 9px; border-radius: 50px; font-weight: 700; font-size: 10px; letter-spacing: 0.05em; text-transform: uppercase; }

        @media print {
            .sidebar, .assessment-actions, .misc-dropdown { display: none !important; }
            .main-content { margin-left: 0; width: 100%; }
            .assessment-doc { box-shadow: none; border: 2px solid #333; }
        }
        @media (max-width: 700px) {
            .assessment-section { padding: 20px 16px; }
            .sig-grid { grid-template-columns: 1fr; }
            .subjects-table { font-size: 0.75rem; }
            .subjects-table th, .subjects-table td { padding: 9px 8px; }
        }
    </style>
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">
    <div class="assessment-wrap">

        <!-- ══ Page Header ══ -->
        <div class="page-header-bar" style="margin-bottom:24px;">
            <div>
                <div class="page-header-eyebrow">
                    <i class="fas fa-file-invoice-dollar"></i> Finance
                </div>
                <h1 class="page-header-title">Enrollment Assessment Form</h1>
            </div>
            <span class="result-count">
                <i class="fas fa-calendar-alt"></i>
                <?= htmlspecialchars($school_year) ?> · <?= htmlspecialchars($semester) ?> Sem
            </span>
        </div>

        <!-- ══ Welcome Banner (matches dashboard-welcome pattern) ══ -->
        <div class="dashboard-welcome" style="margin-bottom:28px;">
            <div class="dashboard-welcome-icon">
                <i class="fas fa-file-invoice" style="color:rgba(255,255,255,0.9);"></i>
            </div>
            <div class="dashboard-welcome-text">
                <div class="eyebrow"><span></span> Official Document</div>
                <h2>
                    <?= htmlspecialchars($student_name) ?>
                    <strong>Enrollment Assessment — <?= htmlspecialchars($school_year) ?> · <?= htmlspecialchars($semester) ?> Semester</strong>
                </h2>
            </div>
            <div class="welcome-course-badge">
                <?= htmlspecialchars($student['student_id']) ?>
            </div>
        </div>

        <!-- ══ Stat Cards ══ -->
        <div class="stats-grid" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Units</span>
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-value"><?= $total_units ?></div>
                <p class="stat-meta"><?= count($subjects) ?> enrolled subjects</p>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Tuition Fee</span>
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-value" style="font-size:1.5rem;">₱<?= number_format($total_tuition, 2) ?></div>
                <p class="stat-meta">@ ₱<?= number_format($fees['tuition_per_unit']) ?>/unit</p>
            </div>
            <div class="stat-card absent-card">
                <div class="stat-header">
                    <span class="stat-label">Total Due</span>
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-value" style="font-size:1.5rem;">₱<?= number_format($total_amount, 2) ?></div>
                <p class="stat-meta">Including all fees</p>
            </div>
            <div class="stat-card present-card">
                <div class="stat-header">
                    <span class="stat-label">Status</span>
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value" style="font-size:1.1rem;"><?= htmlspecialchars($status) ?></div>
                <p class="stat-meta"><?= $course_name ?> · Yr <?= $year_level ?></p>
            </div>
        </div>

        <!-- ══ Assessment Document ══ -->
        <div class="assessment-doc">

            <!-- Document Header -->
            <div class="assessment-doc-header">
                <img src="<?= WEB_IMAGES ?>622685015_925666030131412_6886851389087569993_n.jpg"
                     alt="ISU Logo" class="assessment-logo">
                <div class="assessment-school-name">Isabela State University – San Mateo Campus</div>
                <div class="assessment-school-addr">
                    San Mateo, Isabela &nbsp;·&nbsp; (078) 686-0085 &nbsp;·&nbsp; sanmateo@isu.edu.ph
                </div>
                <div class="assessment-doc-title">
                    <i class="fas fa-file-invoice"></i> Enrollment Assessment Form
                </div>
            </div>

            <!-- ── 1. Student Information ── -->
            <div class="assessment-section">
                <div class="assessment-section-title">
                    <div class="section-icon"><i class="fas fa-user-graduate"></i></div>
                    Student Information
                </div>
                <div class="table-container" style="margin-top:0;">
                    <table class="info-table">
                        <tr>
                            <td>Complete Name</td>
                            <td><strong style="color:var(--slate-800);font-size:0.95rem;"><?= htmlspecialchars($student_name) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Student Number</td>
                            <td><span style="font-family:monospace;font-weight:600;color:var(--primary-blue);"><?= htmlspecialchars($student['student_id']) ?></span></td>
                        </tr>
                        <tr>
                            <td>Course / Program</td>
                            <td><?= $course_name ?></td>
                        </tr>
                        <tr>
                            <td>Year Level / Section</td>
                            <td>Year <?= $year_level ?> — Section <?= $section ?></td>
                        </tr>
                        <tr>
                            <td>Academic Status</td>
                            <td>
                                <?php
                                    $sc = $status==='Regular' ? 'badge-green' : ($status==='Irregular' ? 'badge-yellow' : ($status==='Probation' ? 'badge-red' : 'badge-blue'));
                                ?>
                                <span class="<?= $sc ?>"><?= htmlspecialchars($status) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>School Year / Semester</td>
                            <td><?= htmlspecialchars($school_year) ?> &nbsp;·&nbsp; <?= htmlspecialchars($semester) ?> Semester</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ── 2. Enrolled Subjects ── -->
            <div class="assessment-section">
                <div class="assessment-section-title">
                    <div class="section-icon"><i class="fas fa-book"></i></div>
                    Enrolled Subjects
                    <span class="badge badge-blue" style="margin-left:4px;"><?= $total_units ?> Units</span>
                </div>

                <?php if (empty($subjects)): ?>
                    <div class="no-subjects-state">
                        <div class="empty-state-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <h3>No Subjects Found</h3>
                        <p>No subjects match your current enrollment criteria. Please contact your registrar.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container" style="margin-top:0;">
                        <table class="subjects-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Subject Name</th>
                                    <th>Description</th>
                                    <th style="text-align:center;">Units</th>
                                    <th>Schedule</th>
                                    <th>Room</th>
                                    <th>Type</th>
                                    <th>Instructor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $sub): ?>
                                <tr>
                                    <td class="code-cell"><?= htmlspecialchars($sub['code']) ?></td>
                                    <td class="name-cell"><?= htmlspecialchars($sub['subject_name']) ?></td>
                                    <td style="font-size:0.8rem;"><?= htmlspecialchars($sub['description'] ?? '—') ?></td>
                                    <td class="units-cell"><?= $sub['units'] ?></td>
                                    <td>
                                        <div style="font-weight:600;color:var(--slate-800);font-size:0.82rem;"><?= htmlspecialchars($sub['day'] ?? '') ?></div>
                                        <div style="font-size:0.74rem;color:var(--text-muted);">
                                            <?= !empty($sub['time_start']) ? date('h:i A', strtotime($sub['time_start'])) : '' ?>
                                            <?= !empty($sub['time_end'])   ? '– ' . date('h:i A', strtotime($sub['time_end'])) : '' ?>
                                        </div>
                                    </td>
                                    <td style="font-size:0.8rem;"><?= htmlspecialchars($sub['room'] ?: 'TBA') ?></td>
                                    <td>
                                        <?php if (!empty($sub['subject_type'])): ?>
                                            <span class="<?= strtolower($sub['subject_type']) === 'major' ? 'badge-major' : 'badge-minor' ?>">
                                                <?= htmlspecialchars($sub['subject_type']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);font-size:0.78rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.82rem;">
                                        <?= htmlspecialchars(trim(preg_replace('/\s+/', ' ', $sub['instructor_name'] ?? '')) ?: ($sub['instructor'] ?? 'TBA')) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="font-weight:700;color:var(--slate-800);">Total Enrolled Units</td>
                                    <td class="units-cell"><?= $total_units ?></td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── 3. Assessment of Fees ── -->
            <div class="assessment-section">
                <div class="assessment-section-title">
                    <div class="section-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    Assessment of Fees
                </div>
                <div class="table-container" style="margin-top:0;">
                    <table class="fee-table">
                        <tr class="fee-row-tuition">
                            <td class="fee-label">
                                <div style="font-weight:600;color:var(--slate-800);margin-bottom:2px;">Tuition Fee</div>
                                <div style="font-size:0.78rem;color:var(--text-muted);"><?= $total_units ?> units × ₱<?= number_format($fees['tuition_per_unit']) ?>/unit</div>
                            </td>
                            <td class="fee-amount">₱<?= number_format($total_tuition, 2) ?></td>
                        </tr>
                        <tr class="fee-row-misc">
                            <td class="fee-label">
                                <span class="misc-toggle" id="miscToggle" onclick="toggleMiscDropdown()">
                                    <i class="fas fa-chevron-down"></i> Miscellaneous Fees
                                </span>
                                <div id="misc-dropdown" class="misc-dropdown">
                                    <?php
                                    $hardSum = array_sum(array_column($MISC_ITEMS, 1));
                                    $scale   = $fees['misc_fee'] / $hardSum;
                                    foreach ($MISC_ITEMS as [$label, $raw]):
                                        $amt = number_format(round($raw * $scale, 2), 2);
                                    ?>
                                    <div class="misc-dropdown-item">
                                        <span><?= $label ?></span>
                                        <span>₱<?= $amt ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="misc-dropdown-total">
                                        <span>Subtotal</span>
                                        <span>₱<?= number_format($fees['misc_fee'], 2) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="fee-amount">₱<?= number_format($fees['misc_fee'], 2) ?></td>
                        </tr>
                        <tr class="fee-row-lab">
                            <td class="fee-label">
                                <div style="font-weight:600;color:var(--slate-800);margin-bottom:2px;">Laboratory Fees</div>
                                <div style="font-size:0.78rem;color:var(--text-muted);">
                                    ₱<?= number_format($major_count > 0 ? $fees['lab_fee']/$major_count : 0) ?>/major subject × <?= $major_count ?> subject<?= $major_count !== 1 ? 's' : '' ?>
                                </div>
                            </td>
                            <td class="fee-amount">₱<?= number_format($fees['lab_fee'], 2) ?></td>
                        </tr>
                        <tr class="fee-total">
                            <td class="fee-label">
                                <i class="fas fa-receipt" style="color:var(--accent-rose);"></i>
                                Total Amount Due
                            </td>
                            <td class="fee-amount">₱<?= number_format($total_amount, 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ── 4. Validation & Signatures ── -->
            <div class="assessment-section">
                <div class="assessment-section-title">
                    <div class="section-icon"><i class="fas fa-signature"></i></div>
                    Validation &amp; Approval
                </div>
                <div class="sig-grid">
                    <div class="sig-box">
                        <div class="sig-label"><i class="fas fa-stamp"></i> Registrar</div>
                        <div class="sig-line"></div>
                        <div class="sig-sub">Signature over Printed Name &amp; Date</div>
                    </div>
                    <div class="sig-box">
                        <div class="sig-label"><i class="fas fa-cash-register"></i> Cashier</div>
                        <div style="font-size:0.82rem;color:var(--text-secondary);flex:1;padding-bottom:10px;">
                            <div style="margin-bottom:10px;display:flex;align-items:center;gap:8px;">
                                <span style="white-space:nowrap;font-weight:600;">Amount Paid: ₱</span>
                                <span style="flex:1;border-bottom:1px solid var(--border-medium);display:inline-block;height:18px;"></span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-weight:600;white-space:nowrap;">OR #:</span>
                                <span style="width:72px;border-bottom:1px solid var(--border-medium);display:inline-block;height:18px;"></span>
                                <span style="font-weight:600;white-space:nowrap;margin-left:8px;">Date:</span>
                                <span style="flex:1;border-bottom:1px solid var(--border-medium);display:inline-block;height:18px;"></span>
                            </div>
                        </div>
                        <div class="sig-sub">Official Receipt Details</div>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="assessment-actions no-print">
                <p style="font-size:0.78rem;color:var(--text-muted);margin:0;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-info-circle" style="color:var(--primary-blue);"></i>
                    Computer-generated · Official for enrollment purposes · <?= date('M d, Y H:i:s') ?>
                </p>
                <button class="btn btn-add" id="pdfBtn" onclick="downloadPDF()">
                    <i class="fas fa-file-pdf"></i> Download Official PDF
                </button>
            </div>

            <!-- Footer -->
            <div class="assessment-footer">
                <strong>Isabela State University – San Mateo Campus</strong><br>
                San Mateo, Isabela &nbsp;·&nbsp; (078) 686-0085 &nbsp;·&nbsp; sanmateo@isu.edu.ph
            </div>

        </div><!-- /.assessment-doc -->
    </div><!-- /.assessment-wrap -->
</div><!-- /.main-content -->

<!-- ══════════════════════════════════════════════
     jsPDF LOGIC — All original logic preserved
══════════════════════════════════════════════ -->
<script>
const PDF_DATA = {
    studentName    : <?= json_encode(strtoupper($student_name)) ?>,
    studentId      : <?= json_encode($student['student_id']) ?>,
    course         : <?= json_encode($course_full_name) ?>,
    yearLevel      : <?= json_encode($student['year_level']) ?>,
    section        : <?= json_encode($student['section'] ?? 'N/A') ?>,
    status         : <?= json_encode($status) ?>,
    schoolYear     : <?= json_encode($school_year) ?>,
    semester       : <?= json_encode($semester) ?>,
    totalUnits     : <?= (int)$total_units ?>,
    tuitionPerUnit : <?= (float)$fees['tuition_per_unit'] ?>,
    totalTuition   : <?= (float)$total_tuition ?>,
    miscFee        : <?= (float)$fees['misc_fee'] ?>,
    labFeePerSubj  : <?= $major_count > 0 ? (float)($fees['lab_fee'] / $major_count) : 0 ?>,
    majorCount     : <?= (int)$major_count ?>,
    labFeeTotal    : <?= (float)$fees['lab_fee'] ?>,
    totalAmount    : <?= (float)$total_amount ?>,
    downPayment    : <?= (float)$down_payment ?>,
    balance        : <?= (float)$balance ?>,
    subjects       : <?= $subjects_json ?>,
    generatedAt    : <?= json_encode(date('m/d/Y h:i A')) ?>,
    logoBase64     : <?= json_encode($logo_base64) ?>,
    logoMime       : <?= json_encode($logo_mime) ?>,
};

const MISC_ITEMS = [
    ['Library Fee',100],['Medical and Dental Fee',50],['Athletics Fee',50],
    ['Registration Fee',50],['SBO/SSC/SSCF',60],['School Paper/University Organ Fee',50],
    ['Guidance Fee',20],['Socio Cultural Fee',25],['Internet Fee',40],
    ['Journal Fee',50],['Student Development Fee',200],
];

function fmt(n)  { return n.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function truncate(str,maxLen) { if(!str) return ''; return str.length>maxLen?str.substring(0,maxLen-1)+'…':str; }

async function downloadPDF() {
    const btn = document.getElementById('pdfBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Generating PDF…';
    try { await buildPDF(); }
    catch(e) { console.error(e); alert('PDF generation failed.\n'+e.message); }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-file-pdf"></i> Download Official PDF';
}

async function buildPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({orientation:'portrait',unit:'mm',format:'a4'});
    const PW=210,ML=15,MR=195,CW=180;
    let y=12;
    const C={blue:[30,80,160],blueDark:[20,60,130],white:[255,255,255],black:[0,0,0],gray:[100,100,100],grayLt:[220,220,220],rowEven:[245,248,255],rowOdd:[255,255,255],yellow:[255,243,200],red:[200,0,0]};
    function setFont(size,style='normal',color=C.black){doc.setFontSize(size);doc.setFont('helvetica',style);doc.setTextColor(...color);}
    function txt(s,x,yy,size=9,style='normal',color=C.black,align='left'){setFont(size,style,color);doc.text(String(s??''),x,yy,{align});}
    function hline(x1,yy,x2,lw=0.2,color=C.grayLt){doc.setDrawColor(...color);doc.setLineWidth(lw);doc.line(x1,yy,x2,yy);}
    function fillRect(x,yy,w,h,fc){doc.setFillColor(...fc);doc.rect(x,yy,w,h,'F');}
    function strokeRect(x,yy,w,h,lw=0.2,color=C.grayLt){doc.setDrawColor(...color);doc.setLineWidth(lw);doc.rect(x,yy,w,h,'S');}
    function headerCell(label,x,yy,w,h=5.5){fillRect(x,yy,w,h,C.blue);strokeRect(x,yy,w,h,0.15,C.blueDark);txt(label,x+w/2,yy+h*0.67,7,'bold',C.white,'center');}
    function dataCell(s,x,yy,w,h,size=8,style='normal',align='left',color=C.black){const pad=align==='right'?w-1.5:1.5;const xPos=align==='center'?x+w/2:x+pad;txt(truncate(String(s??''),Math.floor(w/1.8)),xPos,yy+h*0.65,size,style,color,align);}

    const LSIZE=18,LX=ML;
    if(PDF_DATA.logoBase64&&PDF_DATA.logoBase64.length>0){
        const imgSrc=`data:image/${PDF_DATA.logoMime==='PNG'?'png':'jpeg'};base64,${PDF_DATA.logoBase64}`;
        doc.addImage(imgSrc,PDF_DATA.logoMime,LX,y,LSIZE,LSIZE);
    }
    const cx=(ML+LSIZE+2+MR)/2;
    txt('ISABELA STATE UNIVERSITY',cx,y+4,13,'bold',C.blue,'center');
    txt('San Mateo, Isabela',cx,y+9,8,'normal',C.gray,'center');
    txt(`Control No.: ${PDF_DATA.studentId}`,MR,y+4,7.5,'normal',C.black,'right');
    txt(`${PDF_DATA.semester} Semester, SY ${PDF_DATA.schoolYear}`,MR,y+9,7.5,'normal',C.black,'right');
    y+=20;
    txt(PDF_DATA.course,PW/2,y,8,'bold',C.black,'center');
    y+=3; hline(ML,y,MR,0.6,C.blue); y+=5;

    fillRect(ML,y-3.5,CW,6,[240,244,252]);strokeRect(ML,y-3.5,CW,6,0.25,C.blue);
    txt('ID No:',ML+1,y,7.5,'bold');txt(PDF_DATA.studentId,ML+13,y,7.5);
    txt('Name:',ML+46,y,7.5,'bold');txt(`${PDF_DATA.studentName} (${PDF_DATA.section})`,ML+57,y,7.5);
    txt('Status:',MR-48,y,7.5,'bold');txt(PDF_DATA.status,MR-33,y,7.5);
    y+=7;

    const subjectCols=[{label:'CODE',w:18},{label:'SUBJECT',w:36},{label:'DESCRIPTION',w:44},{label:'UNITS',w:11},{label:'SCHEDULE',w:34},{label:'SECTION',w:18},{label:'INSTRUCTOR',w:CW-18-36-44-11-34-18}];
    let colX=[ML];
    subjectCols.forEach((c,i)=>{if(i<subjectCols.length-1)colX.push(colX[i]+c.w);});
    const ROW_H=6;
    subjectCols.forEach((c,i)=>headerCell(c.label,colX[i],y,c.w,ROW_H));
    y+=ROW_H;
    PDF_DATA.subjects.forEach((s,idx)=>{
        const bg=idx%2===0?C.rowOdd:C.rowEven;
        fillRect(ML,y,CW,ROW_H,bg);strokeRect(ML,y,CW,ROW_H,0.12,C.grayLt);
        subjectCols.forEach((_,i)=>{if(i>0)doc.setDrawColor(...C.grayLt);doc.setLineWidth(0.15);if(i>0)doc.line(colX[i],y,colX[i],y+ROW_H);});
        dataCell(s.code,colX[0],y,subjectCols[0].w,ROW_H,7.5,'bold');
        dataCell(s.name,colX[1],y,subjectCols[1].w,ROW_H,7.5);
        dataCell(s.desc,colX[2],y,subjectCols[2].w,ROW_H,7);
        dataCell(s.units,colX[3],y,subjectCols[3].w,ROW_H,7.5,'bold','center');
        dataCell(s.sched,colX[4],y,subjectCols[4].w,ROW_H,6.5);
        dataCell(s.section||PDF_DATA.section,colX[5],y,subjectCols[5].w,ROW_H,7);
        dataCell(s.instr,colX[6],y,subjectCols[6].w,ROW_H,7);
        y+=ROW_H;
    });
    fillRect(ML,y,CW,ROW_H,[230,238,255]);strokeRect(ML,y,CW,ROW_H,0.25,C.blue);
    txt('Total Units:',colX[2]+1.5,y+ROW_H*0.65,8,'bold',C.black);
    txt(String(PDF_DATA.totalUnits),colX[3]+subjectCols[3].w/2,y+ROW_H*0.65,9,'bold',C.blue,'center');
    y+=ROW_H+6;

    txt('ASSESSMENT OF FEES:',ML,y,10,'bold',C.black); y+=5;
    const AMT_X=MR,SUB_X=MR-32;
    txt('Tuition Fees:',ML,y,8,'bold'); y+=4;
    txt(`TUITION FEE: ${PDF_DATA.totalUnits} x ${fmt(PDF_DATA.tuitionPerUnit)}/unit`,ML+4,y,8);
    txt(fmt(PDF_DATA.totalTuition),AMT_X,y,8,'bold',C.black,'right'); y+=5;
    txt('Laboratory Fees:',ML,y,8,'bold'); y+=4;
    txt(`COMPUTER LABORATORY: ${PDF_DATA.majorCount} x ${fmt(PDF_DATA.labFeePerSubj)}/subject`,ML+4,y,8);
    txt(fmt(PDF_DATA.labFeeTotal),AMT_X,y,8,'bold',C.black,'right'); y+=5;
    txt('Miscellaneous Fees:',ML,y,8,'bold'); y+=4;
    const hardSum=MISC_ITEMS.reduce((a,b)=>a+b[1],0);
    const scale=PDF_DATA.miscFee/hardSum;
    MISC_ITEMS.forEach(([label,raw])=>{
        const amt=parseFloat((raw*scale).toFixed(2));
        txt(label,ML+4,y,8);txt(fmt(amt),SUB_X,y,8,'normal',C.black,'right');y+=4;
    });
    txt(fmt(PDF_DATA.miscFee),AMT_X,y-4,8,'bold',C.black,'right');
    y+=2; hline(ML,y,MR,0.4,[150,150,150]); y+=4;
    fillRect(ML,y-3.5,CW,7,[255,243,200]);strokeRect(ML,y-3.5,CW,7,0.35,[180,140,0]);
    txt('Total Assessment:',ML+2,y+0.5,9,'bold',C.black);
    txt(fmt(PDF_DATA.totalAmount),AMT_X,y+0.5,10,'bold',C.red,'right');
    y+=9;
    hline(ML,y,MR,0.5,[100,100,100]); y+=4;
    txt('*This is computer generated, signature is not required.',ML,y,7,'italic',C.gray);
    txt(PDF_DATA.generatedAt,MR,y,7,'normal',C.gray,'right');

    const filename=`Assessment_${PDF_DATA.studentName.replace(/\s+/g,'_')}_${new Date().toISOString().slice(0,10)}.pdf`;
    doc.save(filename);
}

// ── Misc dropdown ──
let miscOpen = false;
function toggleMiscDropdown() {
    const dd     = document.getElementById('misc-dropdown');
    const toggle = document.getElementById('miscToggle');
    miscOpen = !miscOpen;
    dd.style.display = miscOpen ? 'block' : 'none';
    toggle.classList.toggle('open', miscOpen);
}
document.addEventListener('click', function(e) {
    const dd     = document.getElementById('misc-dropdown');
    const toggle = document.getElementById('miscToggle');
    if (dd && toggle && !toggle.contains(e.target) && !dd.contains(e.target)) {
        dd.style.display = 'none';
        toggle.classList.remove('open');
        miscOpen = false;
    }
});
</script>
</body>
</html>