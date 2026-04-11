<?php
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: " . BASE_URL . "Accesspage/student_login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

/* ================= STUDENT INFO ================= */
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
if (!$student) die("Student not found.");

/* ================= ATTENDANCE ================= */
$stmt = $conn->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance_query = $stmt->get_result();
if (!$attendance_query) die("Error fetching attendance.");

/* ================= PROCESS DATA ================= */
$totalDays = 0; $present = 0; $absent = 0; $late = 0;
$recentDays = []; $allDays = [];

while ($row = $attendance_query->fetch_assoc()) {
    if (empty($row['date']) || !strtotime($row['date'])) continue;
    $totalDays++;
    $status = $row['status'] ?? '';
    if ($status === 'present') $present++;
    elseif ($status === 'absent') $absent++;
    elseif ($status === 'late') $late++;
    $allDays[] = $row;
}

$recentDays = array_slice($allDays, 0, 10);
$attendanceRate = ($totalDays > 0) ? round(($present / $totalDays) * 100) : 0;

// Monthly breakdown (last 3 months)
$monthlyData = [];
foreach ($allDays as $day) {
    $monthKey = date('M Y', strtotime($day['date']));
    if (!isset($monthlyData[$monthKey])) $monthlyData[$monthKey] = ['present'=>0,'absent'=>0,'late'=>0,'total'=>0];
    $monthlyData[$monthKey]['total']++;
    $s = $day['status'] ?? '';
    if ($s === 'present') $monthlyData[$monthKey]['present']++;
    elseif ($s === 'absent') $monthlyData[$monthKey]['absent']++;
    elseif ($s === 'late') $monthlyData[$monthKey]['late']++;
}
$monthlyData = array_slice($monthlyData, 0, 3, true);

// Determine standing
$standing = $attendanceRate >= 95 ? ['label'=>'Excellent','class'=>'badge-green','icon'=>'fa-star'] :
           ($attendanceRate >= 85 ? ['label'=>'Good','class'=>'badge-blue','icon'=>'fa-thumbs-up'] :
           ($attendanceRate >= 75 ? ['label'=>'Satisfactory','class'=>'badge-yellow','icon'=>'fa-check'] :
                                    ['label'=>'At Risk','class'=>'badge-red','icon'=>'fa-exclamation-triangle']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/studentportal.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .attendance-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 24px; border-bottom: 1px solid var(--border-light); transition: background var(--transition-fast); }
        .attendance-row:last-child { border-bottom: none; }
        .attendance-row:hover { background: var(--slate-50); }
        .att-date-info { display: flex; align-items: center; gap: 12px; }
        .att-date-icon { width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .att-date-icon.present { background: rgba(16,185,129,0.12); color: var(--accent-emerald); }
        .att-date-icon.absent  { background: rgba(244,63,94,0.1); color: var(--accent-rose); }
        .att-date-icon.late    { background: rgba(245,158,11,0.12); color: var(--accent-amber); }
        .att-date-text { font-size: 0.88rem; font-weight: 500; color: var(--slate-800); }
        .att-date-sub  { font-size: 0.75rem; color: var(--text-muted); margin-top: 1px; }
        .rate-ring-wrap { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 24px; }
        .rate-ring { position: relative; width: 120px; height: 120px; }
        .rate-ring svg { transform: rotate(-90deg); }
        .rate-ring-val { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .rate-ring-num { font-family: 'Playfair Display', Georgia, serif; font-size: 1.6rem; font-weight: 700; color: var(--slate-800); line-height: 1; }
        .rate-ring-label { font-size: 10px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); margin-top: 2px; }
        .monthly-bar-wrap { padding: 4px 0; }
        .monthly-row { margin-bottom: 14px; }
        .monthly-row-header { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 0.82rem; }
        .monthly-row-label { font-weight: 600; color: var(--slate-700); }
        .monthly-row-val { color: var(--text-muted); }
        .monthly-bar { height: 8px; background: var(--slate-200); border-radius: 4px; overflow: hidden; display: flex; gap: 1px; }
        .monthly-bar-fill { height: 100%; border-radius: 4px; }
        .policy-item { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px dashed var(--border-light); font-size: 0.85rem; color: var(--text-secondary); }
        .policy-item:last-child { border-bottom: none; }
        .policy-item i { color: var(--primary-blue); font-size: 12px; margin-top: 3px; flex-shrink: 0; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .two-col { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <!-- ── Page Header ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><i class="fas fa-calendar-check"></i> Attendance</div>
            <h1 class="page-header-title">My Attendance Record</h1>
        </div>
        <span class="<?= $standing['class'] ?> result-count" style="font-size:0.8rem;">
            <i class="fas <?= $standing['icon'] ?>"></i> <?= $standing['label'] ?> Standing
        </span>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stats-grid" style="margin-bottom:28px;">
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Attendance Rate</span><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?= $attendanceRate ?>%</div>
            <div class="progress-bar" style="margin-top:10px;"><div class="progress-fill" style="width:<?= $attendanceRate ?>%"></div></div>
            <p class="stat-meta" style="margin-top:6px;">Overall standing</p>
        </div>
        <div class="stat-card present-card">
            <div class="stat-header"><span class="stat-label">Days Present</span><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= $present ?></div>
            <p class="stat-meta"><i class="fas fa-calendar"></i> Out of <?= $totalDays ?> school days</p>
        </div>
        <div class="stat-card absent-card">
            <div class="stat-header"><span class="stat-label">Days Absent</span><i class="fas fa-times-circle"></i></div>
            <div class="stat-value"><?= $absent ?></div>
            <p class="stat-meta"><i class="fas fa-calendar-times"></i> Recorded absences</p>
        </div>
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Days Late</span><i class="fas fa-clock" style="color:var(--accent-amber);"></i></div>
            <div class="stat-value" style="color:var(--accent-amber);"><?= $late ?></div>
            <p class="stat-meta">Tardiness records</p>
        </div>
    </div>

    <div class="two-col">

        <!-- ── Left: Rate Ring + Monthly ── -->
        <div>
            <div class="section-box" style="margin-top:0;padding:0;overflow:hidden;">
                <div style="padding:18px 24px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;gap:10px;">
                    <h2 style="margin:0;font-size:1.05rem;"><i class="fas fa-chart-pie"></i> Attendance Overview</h2>
                </div>
                <div class="rate-ring-wrap">
                    <div class="rate-ring">
                        <svg width="120" height="120" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="50" fill="none" stroke="var(--slate-200)" stroke-width="12"/>
                            <circle cx="60" cy="60" r="50" fill="none"
                                stroke="<?= $attendanceRate >= 75 ? 'var(--accent-emerald)' : 'var(--accent-rose)' ?>"
                                stroke-width="12"
                                stroke-dasharray="<?= round(314.16 * $attendanceRate / 100) ?> 314.16"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="rate-ring-val">
                            <div class="rate-ring-num"><?= $attendanceRate ?>%</div>
                            <div class="rate-ring-label">Rate</div>
                        </div>
                    </div>
                    <div style="display:flex;gap:16px;flex-wrap:wrap;justify-content:center;">
                        <div style="text-align:center;">
                            <div style="font-size:1.2rem;font-weight:700;color:var(--accent-emerald);"><?= $present ?></div>
                            <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);">Present</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:1.2rem;font-weight:700;color:var(--accent-rose);"><?= $absent ?></div>
                            <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);">Absent</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:1.2rem;font-weight:700;color:var(--accent-amber);"><?= $late ?></div>
                            <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);">Late</div>
                        </div>
                    </div>
                </div>

                <?php if(!empty($monthlyData)): ?>
                <div style="padding:0 24px 20px;">
                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:14px;">Monthly Breakdown</div>
                    <div class="monthly-bar-wrap">
                        <?php foreach($monthlyData as $month => $data):
                            $rate = $data['total'] > 0 ? round(($data['present']/$data['total'])*100) : 0;
                            $presentPct = $data['total'] > 0 ? round(($data['present']/$data['total'])*100) : 0;
                            $absentPct  = $data['total'] > 0 ? round(($data['absent']/$data['total'])*100) : 0;
                            $latePct    = $data['total'] > 0 ? round(($data['late']/$data['total'])*100) : 0;
                        ?>
                        <div class="monthly-row">
                            <div class="monthly-row-header">
                                <span class="monthly-row-label"><?= $month ?></span>
                                <span class="monthly-row-val"><?= $rate ?>% · <?= $data['total'] ?> days</span>
                            </div>
                            <div class="monthly-bar">
                                <div class="monthly-bar-fill" style="width:<?= $presentPct ?>%;background:var(--accent-emerald);"></div>
                                <div class="monthly-bar-fill" style="width:<?= $latePct ?>%;background:var(--accent-amber);"></div>
                                <div class="monthly-bar-fill" style="width:<?= $absentPct ?>%;background:var(--accent-rose);"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Attendance Policy -->
            <div class="section-box" style="margin-top:20px;padding:0;overflow:hidden;">
                <div style="padding:18px 24px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;gap:10px;">
                    <h2 style="margin:0;font-size:1.05rem;"><i class="fas fa-book-open"></i> Attendance Policy</h2>
                </div>
                <div style="padding:16px 24px;">
                    <div class="policy-item"><i class="fas fa-circle-check"></i> Minimum <strong>75%</strong> attendance required to sit for final examinations.</div>
                    <div class="policy-item"><i class="fas fa-circle-check"></i> Three or more <strong>consecutive absences</strong> will be flagged automatically.</div>
                    <div class="policy-item"><i class="fas fa-circle-check"></i> Late arrivals beyond 15 minutes may be marked <strong>absent</strong>.</div>
                    <div class="policy-item"><i class="fas fa-circle-check"></i> Students with <strong>95%+</strong> rate receive perfect attendance recognition.</div>
                    <?php if($attendanceRate < 75): ?>
                    <div class="alert alert-danger" style="margin-top:12px;">
                        <i class="fas fa-exclamation-triangle alert-icon"></i>
                        <div>
                            <p class="alert-title">Warning: Below Required Rate</p>
                            <p class="alert-text">Your attendance is below the 75% minimum. Please consult your adviser.</p>
                        </div>
                    </div>
                    <?php elseif($attendanceRate >= 95): ?>
                    <div class="alert alert-success" style="margin-top:12px;">
                        <i class="fas fa-star alert-icon"></i>
                        <div>
                            <p class="alert-title">Perfect Attendance Eligible</p>
                            <p class="alert-text">You qualify for perfect attendance recognition this semester!</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Right: Recent Records ── -->
        <div>
            <div class="section-box" style="margin-top:0;padding:0;overflow:hidden;">
                <div style="padding:18px 24px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                    <h2 style="margin:0;font-size:1.05rem;"><i class="fas fa-list-check"></i> Recent Records</h2>
                    <span class="result-count"><i class="fas fa-history"></i> Last <?= count($recentDays) ?> days</span>
                </div>
                <?php if(!empty($recentDays)): ?>
                    <?php foreach($recentDays as $day):
                        if(empty($day['date'])||!strtotime($day['date'])) continue;
                        $status = $day['status'] ?? 'unknown';
                        $iconCls = $status === 'present' ? 'fa-check' : ($status === 'late' ? 'fa-clock' : 'fa-times');
                        $badgeCls = $status === 'present' ? 'badge-green' : ($status === 'late' ? 'badge-yellow' : 'badge-red');
                    ?>
                    <div class="attendance-row">
                        <div class="att-date-info">
                            <div class="att-date-icon <?= $status ?>">
                                <i class="fas <?= $iconCls ?>"></i>
                            </div>
                            <div>
                                <div class="att-date-text"><?= date('l', strtotime($day['date'])) ?></div>
                                <div class="att-date-sub"><?= date('F j, Y', strtotime($day['date'])) ?></div>
                            </div>
                        </div>
                        <span class="<?= $badgeCls ?>"><?= ucfirst($status) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:48px 20px;color:var(--text-muted);">
                        <i class="fas fa-calendar-xmark" style="font-size:2rem;display:block;margin-bottom:10px;opacity:0.35;"></i>
                        <p style="font-size:0.88rem;">No attendance records found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.two-col -->

</div>
</body>
</html>