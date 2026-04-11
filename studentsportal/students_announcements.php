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
$stmt = $conn->prepare("SELECT course, year_level, section FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$studentData = $result->fetch_assoc();
if (!$studentData) die("Student not found.");

$student_course  = strtoupper(trim($studentData['course']));
$student_year    = $studentData['year_level'];
$student_section = $studentData['section'];

if (empty($student_course)) { echo "<p>No course assigned to your account.</p>"; exit(); }

/* ================= ANNOUNCEMENTS ================= */
$stmt = $conn->prepare("
    SELECT a.*,
    CONCAT(t.first_name,' ',IFNULL(t.middle_name,''),' ',t.last_name,' ',IFNULL(t.suffix,'')) AS teacher_name
    FROM announcements a
    JOIN teachers t ON a.teacher_id = t.id
    WHERE (
        (t.teacher_type IN ('Seeder', 'Administrator') AND UPPER(TRIM(a.course_id)) = ?)
        OR (UPPER(TRIM(a.course_id)) = ? AND (a.year_level = ? OR a.year_level = 'All') AND (a.section = ? OR a.section = 'All'))
    )
    ORDER BY a.pinned DESC, a.created_at DESC
");
$stmt->bind_param("ssss", $student_course, $student_course, $student_year, $student_section);
$stmt->execute();
$annQuery = $stmt->get_result();

/* ================= PROCESS DATA ================= */
$pinnedAnnouncements = []; $regularAnnouncements = [];

while ($row = $annQuery->fetch_assoc()) {
    $row['priority'] = $row['priority'] ?? 'medium';
    $row['pinned']   = $row['pinned']   ?? 0;
    if (empty($row['created_at']) || !strtotime($row['created_at'])) $row['created_at'] = date('Y-m-d H:i:s');
    if ($row['pinned']) $pinnedAnnouncements[] = $row;
    else $regularAnnouncements[] = $row;
}

/* ================= MARK AS SEEN ================= */
$all_ids = array_merge(array_column($pinnedAnnouncements, 'id'), array_column($regularAnnouncements, 'id'));
if (!empty($all_ids)) {
    $placeholders = implode(',', array_fill(0, count($all_ids), '?'));
    $mark = $conn->prepare("INSERT IGNORE INTO student_seen_announcements (student_id, announcement_id) SELECT ?, id FROM announcements WHERE id IN ($placeholders)");
    $params = array_merge([$student_id], $all_ids);
    $mark->bind_param('i'.str_repeat('i', count($all_ids)), ...$params);
    $mark->execute();
}

$totalCount = count($pinnedAnnouncements) + count($regularAnnouncements);
$highCount = 0;
foreach(array_merge($pinnedAnnouncements, $regularAnnouncements) as $a) {
    if(($a['priority']??'') === 'high') $highCount++;
}

// Helper: priority config
function prioConfig($priority) {
    return match($priority) {
        'high'   => ['class'=>'badge-red',   'alert'=>'alert-danger',  'icon'=>'fa-circle-exclamation', 'label'=>'High Priority'],
        'low'    => ['class'=>'badge-blue',  'alert'=>'alert-info',    'icon'=>'fa-circle-info',         'label'=>'Low Priority'],
        default  => ['class'=>'badge-yellow','alert'=>'alert-warning', 'icon'=>'fa-triangle-exclamation','label'=>'Medium Priority'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements</title>
    <link rel="icon" href="<?php echo asset('images/622685015_925666030131412_6886851389087569993_n.jpg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/studentportal.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .ann-card { background: rgba(255,255,255,0.92); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.6); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm); transition: all var(--transition-slow); margin-bottom: 14px; }
        .ann-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .ann-card.pinned-card { border-color: rgba(245,158,11,0.35); box-shadow: 0 0 0 3px rgba(245,158,11,0.08), var(--shadow-sm); }
        .ann-card-top { padding: 20px 24px 0; }
        .ann-card-meta { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .ann-teacher { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; }
        .ann-teacher-avatar { width: 28px; height: 28px; border-radius: 50%; background: rgba(37,99,235,0.12); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: var(--primary-blue); flex-shrink: 0; }
        .ann-badges { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .ann-title { font-family: 'Playfair Display', Georgia, serif; font-size: 1.1rem; font-weight: 700; color: var(--slate-800); margin-bottom: 10px; line-height: 1.35; }
        .ann-content { font-size: 0.88rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 0; }
        .ann-card-footer { padding: 12px 24px; border-top: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; background: var(--slate-50); }
        .ann-footer-meta { display: flex; align-items: center; gap: 14px; font-size: 0.75rem; color: var(--text-muted); }
        .ann-footer-meta i { font-size: 10px; margin-right: 3px; }
        .ann-expand-btn { font-size: 0.75rem; font-weight: 600; color: var(--primary-blue); background: none; border: none; cursor: pointer; padding: 0; letter-spacing: .03em; }
        .ann-expand-btn:hover { text-decoration: underline; }
        .ann-body-text { padding: 0 24px 20px; }
        .section-eyebrow { display: flex; align-items: center; gap: 10px; margin: 28px 0 14px; }
        .section-eyebrow-line { flex: 1; height: 1px; background: var(--border-light); }
        .section-eyebrow-label { font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--text-muted); white-space: nowrap; }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 12px; opacity: 0.3; }
        .empty-state p { font-size: 0.9rem; }
    </style>
</head>
<body>

<?php include PROJECT_ROOT . '/studentsportal/students_sidebar.php'; ?>

<div class="main-content">

    <!-- ── Page Header ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><i class="fas fa-bullhorn"></i> Notices</div>
            <h1 class="page-header-title">Announcements</h1>
        </div>
        <span class="result-count">
            <i class="fas fa-bell"></i> <?= $totalCount ?> announcement<?= $totalCount !== 1 ? 's' : '' ?>
        </span>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stats-grid" style="margin-bottom:28px;">
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Total</span><i class="fas fa-layer-group"></i></div>
            <div class="stat-value"><?= $totalCount ?></div>
            <p class="stat-meta">All announcements</p>
        </div>
       <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Pinned</span><i class="fas fa-thumbtack" style="color:var(--accent-amber);"></i></div>
            <div class="stat-value" style="color:var(--accent-amber);"><?= count($pinnedAnnouncements) ?></div>
            <p class="stat-meta">Important notices</p>
        </div>
        <div class="stat-card absent-card">
            <div class="stat-header"><span class="stat-label">High Priority</span><i class="fas fa-circle-exclamation"></i></div>
            <div class="stat-value"><?= $highCount ?></div>
            <p class="stat-meta">Urgent items</p>
        </div>
        <div class="stat-card present-card">
            <div class="stat-header"><span class="stat-label">Regular</span><i class="fas fa-comment-dots"></i></div>
            <div class="stat-value"><?= count($regularAnnouncements) ?></div>
            <p class="stat-meta">General announcements</p>
        </div>
    </div>

    <!-- ── Pinned Announcements ── -->
    <?php if(!empty($pinnedAnnouncements)): ?>
        <div class="section-eyebrow">
            <div class="section-eyebrow-label"><i class="fas fa-thumbtack" style="color:var(--accent-amber);margin-right:4px;"></i> Pinned Announcements</div>
            <div class="section-eyebrow-line"></div>
        </div>

        <?php foreach($pinnedAnnouncements as $ann):
            $prio = prioConfig($ann['priority']);
            $createdAt = strtotime($ann['created_at']) ? date('F j, Y \a\t g:i A', strtotime($ann['created_at'])) : 'N/A';
            $shortContent = strlen($ann['content']) > 220 ? substr($ann['content'], 0, 220) . '…' : $ann['content'];
            $needsExpand = strlen($ann['content']) > 220;
            $annId = 'ann-'.$ann['id'];
            $teacherInitials = strtoupper(substr(trim($ann['teacher_name']),0,2));
        ?>
        <div class="ann-card pinned-card">
            <div class="ann-card-top">
                <div class="ann-card-meta">
                    <div class="ann-teacher">
                        <div class="ann-teacher-avatar"><?= $teacherInitials ?></div>
                        <?= htmlspecialchars(trim($ann['teacher_name'])) ?>
                    </div>
                    <div class="ann-badges">
                        <span class="badge <?= $prio['class'] ?>"><i class="fas <?= $prio['icon'] ?>" style="font-size:9px;margin-right:3px;"></i> <?= $prio['label'] ?></span>
                        <span class="badge badge-yellow"><i class="fas fa-thumbtack" style="font-size:9px;margin-right:3px;"></i> Pinned</span>
                    </div>
                </div>
                <div class="ann-title"><?= htmlspecialchars($ann['title']) ?></div>
            </div>
            <div class="ann-body-text">
                <div id="<?= $annId ?>-short" class="ann-content"><?= htmlspecialchars($shortContent) ?></div>
                <?php if($needsExpand): ?>
                <div id="<?= $annId ?>-full" class="ann-content" style="display:none;"><?= htmlspecialchars($ann['content']) ?></div>
                <?php endif; ?>
            </div>
            <div class="ann-card-footer">
                <div class="ann-footer-meta">
                    <span><i class="fas fa-calendar"></i><?= $createdAt ?></span>
                    <?php if(!empty($ann['year_level']) && $ann['year_level'] !== 'All'): ?>
                    <span><i class="fas fa-users"></i>Year <?= htmlspecialchars($ann['year_level']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if($needsExpand): ?>
                <button class="ann-expand-btn" onclick="toggleAnn('<?= $annId ?>', this)">Read more <i class="fas fa-chevron-down" style="font-size:9px;"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ── Regular Announcements ── -->
    <div class="section-eyebrow">
        <div class="section-eyebrow-label"><i class="fas fa-comment-dots" style="margin-right:4px;"></i> <?= !empty($pinnedAnnouncements) ? 'All Announcements' : 'Recent Announcements' ?></div>
        <div class="section-eyebrow-line"></div>
    </div>

    <?php if(empty($regularAnnouncements)): ?>
        <div class="ann-card">
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No announcements at the moment.<br>Check back later for updates from your teachers.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach($regularAnnouncements as $ann):
            $prio = prioConfig($ann['priority']);
            $createdAt = strtotime($ann['created_at']) ? date('F j, Y \a\t g:i A', strtotime($ann['created_at'])) : 'N/A';
            $shortContent = strlen($ann['content']) > 220 ? substr($ann['content'], 0, 220) . '…' : $ann['content'];
            $needsExpand = strlen($ann['content']) > 220;
            $annId = 'ann-'.$ann['id'];
            $teacherInitials = strtoupper(substr(trim($ann['teacher_name']),0,2));
        ?>
        <div class="ann-card">
            <div class="ann-card-top">
                <div class="ann-card-meta">
                    <div class="ann-teacher">
                        <div class="ann-teacher-avatar"><?= $teacherInitials ?></div>
                        <?= htmlspecialchars(trim($ann['teacher_name'])) ?>
                    </div>
                    <div class="ann-badges">
                        <span class="badge <?= $prio['class'] ?>"><i class="fas <?= $prio['icon'] ?>" style="font-size:9px;margin-right:3px;"></i> <?= $prio['label'] ?></span>
                    </div>
                </div>
                <div class="ann-title"><?= htmlspecialchars($ann['title']) ?></div>
            </div>
            <div class="ann-body-text">
                <div id="<?= $annId ?>-short" class="ann-content"><?= htmlspecialchars($shortContent) ?></div>
                <?php if($needsExpand): ?>
                <div id="<?= $annId ?>-full" class="ann-content" style="display:none;"><?= htmlspecialchars($ann['content']) ?></div>
                <?php endif; ?>
            </div>
            <div class="ann-card-footer">
                <div class="ann-footer-meta">
                    <span><i class="fas fa-calendar"></i><?= $createdAt ?></span>
                    <?php if(!empty($ann['year_level']) && $ann['year_level'] !== 'All'): ?>
                    <span><i class="fas fa-users"></i>Year <?= htmlspecialchars($ann['year_level']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if($needsExpand): ?>
                <button class="ann-expand-btn" onclick="toggleAnn('<?= $annId ?>', this)">Read more <i class="fas fa-chevron-down" style="font-size:9px;"></i></button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
function toggleAnn(id, btn) {
    const shortEl = document.getElementById(id + '-short');
    const fullEl  = document.getElementById(id + '-full');
    if (!fullEl) return;
    const expanded = fullEl.style.display !== 'none';
    shortEl.style.display = expanded ? '' : 'none';
    fullEl.style.display  = expanded ? 'none' : '';
    btn.innerHTML = expanded
        ? 'Read more <i class="fas fa-chevron-down" style="font-size:9px;"></i>'
        : 'Show less <i class="fas fa-chevron-up" style="font-size:9px;"></i>';
}
</script>
</body>
</html>