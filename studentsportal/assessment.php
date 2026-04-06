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
    SELECT 
        first_name, middle_name, last_name,
        student_id, course, year_level, section,
        school_year, semester, status
    FROM students 
    WHERE id = ?
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

// ================= DEFINE VARIABLES =================
$student_name = trim(
    $student['first_name'] . ' ' .
    ($student['middle_name'] ? $student['middle_name'] . ' ' : '') .
    $student['last_name']
);

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
    SELECT 
        s.code, s.subject_name, s.description,
        s.room, s.day, s.time_start, s.time_end,
        s.instructor, s.section, s.subject_type,
        CONCAT(
            t.first_name, ' ',
            IFNULL(t.middle_name, ''), ' ',
            t.last_name,
            IFNULL(t.suffix, '')
        ) AS instructor_name
    FROM subjects s
    LEFT JOIN teachers t ON s.instructor = t.teacher_id
    WHERE s.course_id = ?
      AND s.year_level = ?
      AND (s.section = ? OR s.section = '' OR s.section IS NULL)
");
$stmt->bind_param("iss", $course_id, $year_level, $section);
$stmt->execute();
$subjects_result = $stmt->get_result();

$subjects    = [];
$total_units = 0;

while ($row = $subjects_result->fetch_assoc()) {
    $row['units'] = isset($row['units']) ? (int)$row['units'] : 3;
    $total_units += $row['units'];
    $subjects[] = $row;
}

// ================= COUNT MAJOR SUBJECTS =================
$major_count = 0;
foreach ($subjects as $sub) {
    if (!empty($sub['subject_type']) && strtolower($sub['subject_type']) === 'major') {
        $major_count++;
    }
}

// ================= FEES =================
$fees       = getFeeStructure($conn, $student['course'], $major_count);
$assessment = calculateAssessment($fees, $total_units);

$total_tuition = $assessment['tuition'];
$total_amount  = $assessment['total_amount'];
$down_payment  = $assessment['down_payment'];
$balance       = $assessment['balance'];

// ================= LOGO AS BASE64 FOR PDF =================
// IMAGES_DIR is defined in your paths.php as: PROJECT_ROOT . '/images/'
// WEB_IMAGES points to the same folder via browser URL
$logo_filename = '622685015_925666030131412_6886851389087569993_n.jpg';
$logo_path     = IMAGES_DIR . $logo_filename; // e.g. /var/www/Student_Info/images/622...jpg

$logo_base64 = '';
$logo_mime   = 'JPEG';
if (file_exists($logo_path)) {
    $logo_base64 = base64_encode(file_get_contents($logo_path));
    $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
    $logo_mime = ($ext === 'png') ? 'PNG' : 'JPEG';
}

// ================= EXPAND COURSE ACRONYM FOR PDF =================
function expandCourseAcronym(string $course): string {
    $map = [
        'BSIT'   => 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY',
        'BSCS'   => 'BACHELOR OF SCIENCE IN COMPUTER SCIENCE',
        'BSIS'   => 'BACHELOR OF SCIENCE IN INFORMATION SYSTEMS',
        'BSECE'  => 'BACHELOR OF SCIENCE IN ELECTRONICS AND COMMUNICATIONS ENGINEERING',
        'BSEE'   => 'BACHELOR OF SCIENCE IN ELECTRICAL ENGINEERING',
        'BSCE'   => 'BACHELOR OF SCIENCE IN CIVIL ENGINEERING',
        'BSME'   => 'BACHELOR OF SCIENCE IN MECHANICAL ENGINEERING',
        'BSEd'   => 'BACHELOR OF SCIENCE IN EDUCATION',
        'BSED'   => 'BACHELOR OF SCIENCE IN EDUCATION',
        'BSBA'   => 'BACHELOR OF SCIENCE IN BUSINESS ADMINISTRATION',
        'BSACCT' => 'BACHELOR OF SCIENCE IN ACCOUNTANCY',
        'BSN'    => 'BACHELOR OF SCIENCE IN NURSING',
        'BSHM'   => 'BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT',
        'BSTM'   => 'BACHELOR OF SCIENCE IN TOURISM MANAGEMENT',
        'BSAG'   => 'BACHELOR OF SCIENCE IN AGRICULTURE',
        'BSCRIM' => 'BACHELOR OF SCIENCE IN CRIMINOLOGY',
        'AB'     => 'BACHELOR OF ARTS',
        'BEEd'   => 'BACHELOR OF ELEMENTARY EDUCATION',
        'BEED'   => 'BACHELOR OF ELEMENTARY EDUCATION',
        'BSPsych'=> 'BACHELOR OF SCIENCE IN PSYCHOLOGY',
    ];
    // Try to match any known acronym at the start of the course string
    foreach ($map as $acronym => $full) {
        if (stripos($course, $acronym) !== false) {
            return str_ireplace($acronym, $full, $course);
        }
    }
    return strtoupper($course); // fallback: just uppercase whatever is stored
}

$course_full_name = expandCourseAcronym($student['course']);

// ================= BUILD subjects JSON for JS =================
$subjects_js = [];
foreach ($subjects as $s) {
    $time_start = !empty($s['time_start']) ? date('h:i A', strtotime($s['time_start'])) : '';
    $time_end   = !empty($s['time_end'])   ? date('h:i A', strtotime($s['time_end']))   : '';
    $sched      = trim(($s['day'] ?? '') . ' ' . $time_start . ($time_end ? '-' . $time_end : ''));
    $instr      = trim($s['instructor_name'] ?? $s['instructor'] ?? 'TBA');
    // Remove extra spaces from instructor CONCAT
    $instr = preg_replace('/\s+/', ' ', $instr) ?: 'TBA';

    $subjects_js[] = [
        'code'  => $s['code']         ?? '',
        'name'  => $s['subject_name'] ?? '',
        'desc'  => $s['description']  ?? '',
        'units' => (int)($s['units']  ?? 3),
        'sched' => $sched,
        'instr' => $instr,
        'section' => $s['section']    ?? '',
    ];
}
$subjects_json = json_encode($subjects_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Form – <?= htmlspecialchars($student_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/studentportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- jsPDF only – no html2canvas needed -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">
    <div class="assessment-form">

        <!-- ── HEADER ──────────────────────────────────────────────────────── -->
        <div class="assessment-card">
            <div class="header">
                <img src="<?= WEB_IMAGES ?>622685015_925666030131412_6886851389087569993_n.jpg" alt="ISU Logo" class="logo">
                <div class="school-name">ISABELA STATE UNIVERSITY – San Mateo Campus</div>
                <div class="school-address">San Mateo, Isabela &nbsp;|&nbsp; Tel: (078) 686-0085 &nbsp;|&nbsp; sanmateo@isu.edu.ph</div>
                <div class="title">ENROLLMENT ASSESSMENT FORM</div>
            </div>

            <!-- 1. Student Information -->
            <div class="section">
                <h3 class="section-title"><i class="fas fa-user-graduate"></i> STUDENT INFORMATION</h3>
                <div class="table-container">
                    <table>
                        <tr><th style="width:35%">Field</th><th>Details</th></tr>
                        <tr><td><strong>Complete Name</strong></td><td><?= htmlspecialchars($student_name) ?></td></tr>
                        <tr><td><strong>Student No.</strong></td><td><?= htmlspecialchars($student['student_id']) ?></td></tr>
                        <tr><td><strong>Course / Program</strong></td><td><?= $course_name ?></td></tr>
                        <tr><td><strong>Year Level / Section</strong></td><td><?= $year_level ?> – <?= $section ?></td></tr>
                        <tr><td><strong>Student Type (Academic Status)</strong></td><td><?= htmlspecialchars($status) ?></td></tr>
                        <tr><td><strong>School Year / Semester</strong></td><td><?= htmlspecialchars($school_year) ?> / <?= htmlspecialchars($semester) ?> Sem</td></tr>
                    </table>
                </div>
            </div>

            <!-- 2. Enrolled Subjects -->
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-book"></i> ENROLLED SUBJECTS
                    <span style="font-size:16px;color:var(--success-green);font-weight:500">(Total: <?= $total_units ?> Units)</span>
                </h3>
                <?php if (empty($subjects)): ?>
                    <div class="no-subjects">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div style="font-size:18px;margin-bottom:10px;font-weight:500">No Subjects Available</div>
                        <div>No subjects found for your current enrollment criteria.</div>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <tr>
                                <th>Code</th><th>Subject Name</th><th>Description</th>
                                <th style="width:80px">Units</th><th>Schedule</th><th>Instructor</th>
                            </tr>
                            <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($sub['code']) ?></strong></td>
                                <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                                <td><?= htmlspecialchars($sub['description'] ?? 'No description') ?></td>
                                <td style="text-align:center;font-weight:600"><?= $sub['units'] ?></td>
                                <td>
                                    <div style="font-weight:500"><?= htmlspecialchars($sub['day']) ?></div>
                                    <div><?= date('h:i A', strtotime($sub['time_start'])) ?> – <?= date('h:i A', strtotime($sub['time_end'])) ?></div>
                                </td>
                                <td><?= htmlspecialchars(trim(preg_replace('/\s+/',' ',$sub['instructor_name'])) ?: ($sub['instructor'] ?? 'TBA')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3. Assessment Fees -->
            <div class="section">
                <h3 class="section-title"><i class="fas fa-file-invoice-dollar"></i> ASSESSMENT FEES</h3>
                <div class="table-container">
                    <table>
                        <tr><th style="width:70%">Description</th><th style="width:30%">Amount (₱)</th></tr>
                        <tr>
                            <td>Tuition Fee (<?= $total_units ?> units @ ₱<?= number_format($fees['tuition_per_unit']) ?>/unit)</td>
                            <td class="amount">₱<?= number_format($total_tuition, 2) ?></td>
                        </tr>
                        <tr class="misc-fees-row">
                            <td style="position:relative">
                                <span class="clickable-fee" onclick="toggleMiscDropdown()">
                                    Miscellaneous Fees <i class="fas fa-chevron-down"></i>
                                </span>
                                <div id="misc-dropdown" class="misc-dropdown" style="display:none">
                                    <div>Library Fee — ₱100.00</div>
                                    <div>Medical and Dental Fee — ₱50.00</div>
                                    <div>Athletics Fee — ₱50.00</div>
                                    <div>Registration Fee — ₱50.00</div>
                                    <div>SBO/SSC/SSCF — ₱60.00</div>
                                    <div>School Paper / University Organ Fee — ₱50.00</div>
                                    <div>Guidance Fee — ₱20.00</div>
                                    <div>Socio Cultural Fee — ₱25.00</div>
                                    <div>Internet Fee — ₱40.00</div>
                                    <div>Journal Fee — ₱50.00</div>
                                    <div>Student Development Fee — ₱200.00</div>
                                    <div class="total-row">Total: ₱<?= number_format($fees['misc_fee'], 2) ?></div>
                                </div>
                            </td>
                            <td class="amount">₱<?= number_format($fees['misc_fee'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Laboratory Fees (₱<?= number_format($major_count > 0 ? $fees['lab_fee'] / $major_count : 0) ?> per Major Subject × <?= $major_count ?>)</td>
                            <td class="amount">₱<?= number_format($fees['lab_fee'], 2) ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>TOTAL AMOUNT DUE</strong></td>
                            <td class="amount">₱<?= number_format($total_amount, 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- 4. Validation & Approval -->
            <div class="section">
                <h3 class="section-title"><i class="fas fa-signature"></i> VALIDATION &amp; APPROVAL</h3>
                <table class="signature-table">
                    <tr>
                        <td style="width:50%">
                            <div style="font-weight:700;font-size:16px;margin-bottom:50px;color:var(--slate-800)">REGISTRAR</div>
                            <hr style="border-color:var(--slate-300)">
                            <div style="font-size:13px;color:var(--slate-600)">Signature over Printed Name &amp; Date</div>
                        </td>
                        <td style="width:50%">
                            <div style="font-weight:700;font-size:16px;margin-bottom:20px;color:var(--slate-800)">CASHIER</div>
                            <hr style="border-color:var(--slate-300)">
                            <div style="font-size:13px;color:var(--slate-600)">
                                Amount Paid: ₱<span style="border-bottom:1px solid var(--slate-400);display:inline-block;min-width:100px">&nbsp;</span><br>
                                OR #: <span style="border-bottom:1px solid var(--slate-400);display:inline-block;min-width:80px">&nbsp;</span>
                                &nbsp;Date: <span style="border-bottom:1px solid var(--slate-400);display:inline-block;min-width:80px">&nbsp;</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons no-print">
                <button class="btn btn-pdf" id="pdfBtn" onclick="downloadPDF()">
                    <i class="fas fa-file-pdf"></i> Download Official PDF
                </button>
            </div>

            <!-- Footer -->
            <div style="text-align:center;margin-top:30px;padding-top:15px;border-top:2px solid #ccc;font-size:9pt;color:#666">
                <strong>ISABELA STATE UNIVERSITY – San Mateo Campus</strong><br>
                San Mateo, Isabela &nbsp;|&nbsp; (078) 686-0085 &nbsp;|&nbsp; sanmateo@isu.edu.ph<br>
                <em>Computer-generated – Official for Enrollment Purposes &nbsp;|&nbsp; Generated: <?= date('M d, Y H:i:s') ?></em>
            </div>
        </div><!-- /.assessment-card -->
    </div><!-- /.assessment-form -->
</div><!-- /.main-content -->

<!-- ══════════════════════════════════════════════════════════════════════
     PDF GENERATION  –  Pure jsPDF (no screenshot, crisp vector text)
     Matches the layout of the official ISU assessment form.
═══════════════════════════════════════════════════════════════════════ -->
<script>
// ── PHP data injected into JS ──────────────────────────────────────────
const PDF_DATA = {
    studentName : <?= json_encode(strtoupper($student_name)) ?>,
    studentId   : <?= json_encode($student['student_id']) ?>,
    course      : <?= json_encode($course_full_name) ?>,
    yearLevel   : <?= json_encode($student['year_level']) ?>,
    section     : <?= json_encode($student['section'] ?? 'N/A') ?>,
    status      : <?= json_encode($status) ?>,
    schoolYear  : <?= json_encode($school_year) ?>,
    semester    : <?= json_encode($semester) ?>,
    totalUnits  : <?= (int)$total_units ?>,
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
    ['Library Fee',                        100],
    ['Medical and Dental Fee',              50],
    ['Athletics Fee',                       50],
    ['Registration Fee',                    50],
    ['SBO/SSC/SSCF',                        60],
    ['School Paper/University Organ Fee',   50],
    ['Guidance Fee',                        20],
    ['Socio Cultural Fee',                  25],
    ['Internet Fee',                        40],
    ['Journal Fee',                         50],
    ['Student Development Fee',            200],
];

// ── Helpers ────────────────────────────────────────────────────────────
function fmt(n)  { return n.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function peso(n) { return fmt(n); }

function truncate(str, maxLen) {
    if (!str) return '';
    return str.length > maxLen ? str.substring(0, maxLen - 1) + '…' : str;
}

// ── Main PDF builder ───────────────────────────────────────────────────
async function downloadPDF() {
    const btn = document.getElementById('pdfBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Generating PDF…';

    try {
        await buildPDF();
    } catch(e) {
        console.error(e);
        alert('PDF generation failed. Please try again.\n' + e.message);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-file-pdf"></i> Download Official PDF';
}

async function buildPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation:'portrait', unit:'mm', format:'a4' });

    const PW = 210, PH = 297;
    const ML = 15, MR = PW - 15;          // left / right margin
    const CW = MR - ML;                   // content width
    let   y  = 12;

    // ── colour palette ─────────────────────────────────────────────────
    const C = {
        blue    : [30,  80, 160],
        blueDark: [20,  60, 130],
        white   : [255,255,255],
        black   : [  0,  0,   0],
        gray    : [100,100,100],
        grayLt  : [220,220,220],
        rowEven : [245,248,255],
        rowOdd  : [255,255,255],
        yellow  : [255,243,200],
        red     : [200,  0,  0],
        green   : [ 16,185,129],
    };

    // ── drawing primitives ─────────────────────────────────────────────
    function setFont(size, style='normal', color=C.black) {
        doc.setFontSize(size);
        doc.setFont('helvetica', style);
        doc.setTextColor(...color);
    }

    function txt(s, x, yy, size=9, style='normal', color=C.black, align='left') {
        setFont(size, style, color);
        doc.text(String(s ?? ''), x, yy, {align});
    }

    function hline(x1, yy, x2, lw=0.2, color=C.grayLt) {
        doc.setDrawColor(...color);
        doc.setLineWidth(lw);
        doc.line(x1, yy, x2, yy);
    }

    function vline(x, y1, y2, lw=0.15, color=C.grayLt) {
        doc.setDrawColor(...color);
        doc.setLineWidth(lw);
        doc.line(x, y1, x, y2);
    }

    function fillRect(x, yy, w, h, fillColor) {
        doc.setFillColor(...fillColor);
        doc.rect(x, yy, w, h, 'F');
    }

    function strokeRect(x, yy, w, h, lw=0.2, color=C.grayLt) {
        doc.setDrawColor(...color);
        doc.setLineWidth(lw);
        doc.rect(x, yy, w, h, 'S');
    }

    // Draws a header cell (blue bg, white text, centered)
    function headerCell(label, x, yy, w, h=5.5) {
        fillRect(x, yy, w, h, C.blue);
        strokeRect(x, yy, w, h, 0.15, C.blueDark);
        txt(label, x + w/2, yy + h*0.67, 7, 'bold', C.white, 'center');
    }

    // Draws a data cell (text clipped to width)
    function dataCell(s, x, yy, w, h, size=8, style='normal', align='left', color=C.black) {
        const pad = align === 'right' ? w - 1.5 : 1.5;
        const xPos = align === 'center' ? x + w/2 : x + pad;
        txt(truncate(String(s ?? ''), Math.floor(w/1.8)), xPos, yy + h*0.65, size, style, color, align);
    }

    // ══════════════════════════════════════════════════════════════════
    // SECTION 1 – HEADER
    // ══════════════════════════════════════════════════════════════════

    // ── ISU LOGO ──────────────────────────────────────────────────────
    const LX   = ML;        // top-left X of logo image
    const LSIZE = 18;       // width & height in mm (square crop)
    const LCX  = ML + LSIZE / 2;  // centre X (for text alignment reference)

    if (PDF_DATA.logoBase64 && PDF_DATA.logoBase64.length > 0) {
        // Real logo image embedded from server
        const imgSrc = `data:image/${PDF_DATA.logoMime === 'PNG' ? 'png' : 'jpeg'};base64,${PDF_DATA.logoBase64}`;
        doc.addImage(imgSrc, PDF_DATA.logoMime, LX, y, LSIZE, LSIZE);
    } else {
        // ── Fallback drawn seal (if image file not found on server) ──
        const cx2 = LCX, cy2 = y + LSIZE / 2, R0 = 9;
        doc.setFillColor(0, 100, 60);   doc.circle(cx2, cy2, R0, 'F');
        doc.setFillColor(255,255,255);  doc.circle(cx2, cy2, R0 - 1.1, 'F');
        doc.setFillColor(212,175, 55);  doc.circle(cx2, cy2, R0 - 1.5, 'F');
        doc.setFillColor(20, 60, 130);
        doc.rect(cx2 - 3.75, cy2 - 5.8, 7.5, 6.3, 'F');
        doc.triangle(cx2 - 3.75, cy2 + 0.5, cx2 + 3.75, cy2 + 0.5, cx2, cy2 + 3.2, 'F');
        doc.setFillColor(255,180,  0);  doc.ellipse(cx2, cy2 - 4.6, 1.1, 1.8, 'F');
        doc.setFillColor(255,255,255);  doc.rect(cx2 - 2.2, cy2 + 0.2, 4.4, 1.5, 'F');
        doc.setFillColor(0,100,60);
        doc.ellipse(cx2 - 3.8, cy2 + 1.5, 1.0, 2.2, 'F');
        doc.ellipse(cx2 + 3.8, cy2 + 1.5, 1.0, 2.2, 'F');
        doc.setTextColor(255,255,255); doc.setFontSize(3.8); doc.setFont('helvetica','bold');
        doc.text('ISABELA STATE UNIVERSITY', cx2, cy2 - R0 + 0.9, {align:'center'});
        doc.text('SAN MATEO CAMPUS',         cx2, cy2 + R0 - 0.4, {align:'center'});
        doc.setTextColor(0,0,0);
    }

    // School name & address (centered in remaining space after logo)
    const cx = (ML + LSIZE + 2 + MR) / 2;
    txt('ISABELA STATE UNIVERSITY', cx, y + 4,  13, 'bold',   C.blue, 'center');
    txt('San Mateo, Isabela',       cx, y + 9,   8, 'normal', C.gray, 'center');

    // Top-right: Control / semester
    txt(`Control No.: ${PDF_DATA.studentId}`,                          MR, y + 4,  7.5, 'normal', C.black, 'right');
    txt(`${PDF_DATA.semester} Semester, SY ${PDF_DATA.schoolYear}`,   MR, y + 9,  7.5, 'normal', C.black, 'right');

    y += 20;
    txt(PDF_DATA.course, PW/2, y, 8, 'bold', C.black, 'center');

    y += 3;
    hline(ML, y, MR, 0.6, C.blue);
    y += 5;

    // ══════════════════════════════════════════════════════════════════
    // SECTION 2 – STUDENT INFO BAR
    // ══════════════════════════════════════════════════════════════════
    fillRect(ML, y - 3.5, CW, 6, [240, 244, 252]);
    strokeRect(ML, y - 3.5, CW, 6, 0.25, C.blue);

    txt('ID No:',   ML + 1,      y, 7.5, 'bold');
    txt(PDF_DATA.studentId,      ML + 13,     y, 7.5);
    txt('Name:',   ML + 46,      y, 7.5, 'bold');
    txt(`${PDF_DATA.studentName} (${PDF_DATA.section})`, ML + 57, y, 7.5);
    txt('Status:',  MR - 48,     y, 7.5, 'bold');
    txt(PDF_DATA.status,         MR - 33,     y, 7.5);

    y += 7;

    // ══════════════════════════════════════════════════════════════════
    // SECTION 3 – SUBJECTS TABLE
    // ══════════════════════════════════════════════════════════════════
    // Column definitions: [label, relativeWidth]
    const subjectCols = [
        { label:'CODE',       w: 18 },
        { label:'SUBJECT',    w: 36 },
        { label:'DESCRIPTION',w: 44 },
        { label:'UNITS',      w: 11 },
        { label:'SCHEDULE',   w: 34 },
        { label:'SECTION',    w: 18 },
        { label:'INSTRUCTOR', w: CW - 18 - 36 - 44 - 11 - 34 - 18 },
    ];
    // Compute absolute x positions
    let colX = [ML];
    subjectCols.forEach((c, i) => {
        if (i < subjectCols.length - 1) colX.push(colX[i] + c.w);
    });

    const ROW_H = 6;

    // Header row
    subjectCols.forEach((c, i) => headerCell(c.label, colX[i], y, c.w, ROW_H));
    y += ROW_H;

    // Data rows
    PDF_DATA.subjects.forEach((s, idx) => {
        const bg = idx % 2 === 0 ? C.rowOdd : C.rowEven;
        fillRect(ML, y, CW, ROW_H, bg);
        strokeRect(ML, y, CW, ROW_H, 0.12, C.grayLt);
        subjectCols.forEach((_, i) => { if (i > 0) vline(colX[i], y, y + ROW_H); });

        dataCell(s.code,  colX[0], y, subjectCols[0].w, ROW_H, 7.5, 'bold');
        dataCell(s.name,  colX[1], y, subjectCols[1].w, ROW_H, 7.5);
        dataCell(s.desc,  colX[2], y, subjectCols[2].w, ROW_H, 7);
        dataCell(s.units, colX[3], y, subjectCols[3].w, ROW_H, 7.5, 'bold', 'center');
        dataCell(s.sched, colX[4], y, subjectCols[4].w, ROW_H, 6.5);
        dataCell(s.section || PDF_DATA.section, colX[5], y, subjectCols[5].w, ROW_H, 7);
        dataCell(s.instr, colX[6], y, subjectCols[6].w, ROW_H, 7);
        y += ROW_H;
    });

    // Total units footer row
    fillRect(ML, y, CW, ROW_H, [230, 238, 255]);
    strokeRect(ML, y, CW, ROW_H, 0.25, C.blue);
    txt('Total Units:', colX[2] + 1.5, y + ROW_H * 0.65, 8, 'bold', C.black);
    txt(String(PDF_DATA.totalUnits), colX[3] + subjectCols[3].w / 2, y + ROW_H * 0.65, 9, 'bold', C.blue, 'center');
    y += ROW_H + 6;

    // ══════════════════════════════════════════════════════════════════
    // SECTION 4 – ASSESSMENT OF FEES
    // ══════════════════════════════════════════════════════════════════
    txt('ASSESSMENT OF FEES:', ML, y, 10, 'bold', C.black);
    y += 5;

    const AMT_X = MR;       // right-align amounts here
    const SUB_X = MR - 32;  // right-align sub-totals / column 2

    // ── Tuition ──────────────────────────────────────────────────────
    txt('Tuition Fees:', ML, y, 8, 'bold');
    y += 4;
    txt(`TUITION FEE: ${PDF_DATA.totalUnits} x ${fmt(PDF_DATA.tuitionPerUnit)}/unit`, ML + 4, y, 8);
    txt(fmt(PDF_DATA.totalTuition), SUB_X, y, 8, 'normal', C.black, 'right');
    txt(fmt(PDF_DATA.totalTuition), AMT_X, y, 8, 'bold',   C.black, 'right');
    y += 5;

    // ── Laboratory ───────────────────────────────────────────────────
    txt('Laboratory Fees:', ML, y, 8, 'bold');
    y += 4;
    txt(`COMPUTER LABORATORY: ${PDF_DATA.majorCount} x ${fmt(PDF_DATA.labFeePerSubj)}/subject`, ML + 4, y, 8);
    txt(fmt(PDF_DATA.labFeeTotal), SUB_X, y, 8, 'normal', C.black, 'right');
    txt(fmt(PDF_DATA.labFeeTotal), AMT_X, y, 8, 'bold',   C.black, 'right');
    y += 5;

    // ── Miscellaneous ────────────────────────────────────────────────
    txt('Miscellaneous Fees:', ML, y, 8, 'bold');
    y += 4;

    // Scale items proportionally to actual misc total from DB
    const hardSum = MISC_ITEMS.reduce((a, b) => a + b[1], 0);
    const scale   = PDF_DATA.miscFee / hardSum;

    MISC_ITEMS.forEach(([label, raw]) => {
        const amt = parseFloat((raw * scale).toFixed(2));
        txt(label, ML + 4, y, 8);
        txt(fmt(amt), SUB_X, y, 8, 'normal', C.black, 'right');
        y += 4;
    });
    // Misc sub-total (bold, right column)
    txt(fmt(PDF_DATA.miscFee), AMT_X, y - 4, 8, 'bold', C.black, 'right');

    y += 2;
    hline(ML, y, MR, 0.4, [150,150,150]);
    y += 4;

    // ── Total Assessment ─────────────────────────────────────────────
    fillRect(ML, y - 3.5, CW, 7, C.yellow);
    strokeRect(ML, y - 3.5, CW, 7, 0.35, [180,140,0]);
    txt('Total Assessment:', ML + 2, y + 0.5, 9, 'bold', C.black);
    txt(fmt(PDF_DATA.totalAmount), AMT_X, y + 0.5, 10, 'bold', C.red, 'right');
    y += 9;

    // ══════════════════════════════════════════════════════════════════
    // SECTION 5 – FOOTER
    // ══════════════════════════════════════════════════════════════════
    hline(ML, y, MR, 0.5, [100,100,100]);
    y += 4;
    txt('*This is computer generated, signature is not required.', ML, y, 7, 'italic', C.gray);
    txt(PDF_DATA.generatedAt, MR, y, 7, 'normal', C.gray, 'right');

    // ── Save ──────────────────────────────────────────────────────────
    const filename = `Assessment_${PDF_DATA.studentName.replace(/\s+/g,'_')}_${new Date().toISOString().slice(0,10)}.pdf`;
    doc.save(filename);
}

// ── Misc dropdown toggle ───────────────────────────────────────────────
let miscOpen = false;

function toggleMiscDropdown() {
    const dd  = document.getElementById('misc-dropdown');
    const btn = dd.parentElement.querySelector('.clickable-fee');
    miscOpen = !miscOpen;
    dd.style.display  = miscOpen ? 'block' : 'none';
    btn.classList.toggle('open', miscOpen);
}

document.addEventListener('click', function(e) {
    const dd  = document.getElementById('misc-dropdown');
    const btn = dd ? dd.parentElement.querySelector('.clickable-fee') : null;
    if (dd && btn && !btn.contains(e.target) && !dd.contains(e.target)) {
        dd.style.display = 'none';
        btn.classList.remove('open');
        miscOpen = false;
    }
});
</script>
</body>
</html>