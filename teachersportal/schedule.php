<?php
/* ============================================================
   schedule.php — PHP logic preserved exactly.
   HTML/UI unified with students.php modern style.
   ============================================================ */
session_start();
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

if(!isset($_SESSION['teacher_id'])){
    header("Location: " . BASE_URL . "Accesspage/teacher_login.php");
    exit();
}

require_once PROJECT_ROOT . '/config/teacher_filter.php';

$admin_types = ['Seeder','Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
$teacher_year_filter = '';
$teacher_section_filter = '';
if (!$is_admin) {
    $y_params = []; $y_types = '';
    $teacher_year_filter = getCombinedYearFilter('year_level', $y_params, $y_types);
    $s_params = []; $s_types = '';
    $teacher_section_filter = getCombinedSectionFilter('section', $s_params, $s_types);
}

$back_url = BASE_URL . "Accesspage/teacher_login.php";
if (isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], ['Seeder','Administrator'])) {
    $back_url = BASE_URL . "teachersportal/chooseSub.php";
}

$selected_course = $_SESSION['teacher_course'] ?? '';
if(empty($selected_course)){
    echo "Course not assigned to this teacher. Contact admin.";
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

$allowed_courses = ['BSIT','BSED','BAT','BTVTED'];
if(!in_array(strtoupper($selected_course), $allowed_courses)){
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="' . BASE_URL . 'teachersportal/chooseSub.php">← Go Back</a>';
    exit();
}

$course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$selected_course'");
if(!$course_result || mysqli_num_rows($course_result) == 0){
    die("Selected course not found in database.");
}
$course_row = mysqli_fetch_assoc($course_result);
$course_id = $course_row['id'];

/* ── POST: Save schedule ── */
if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['save_schedule']) && $is_admin){
    $id = $_POST['id'];
    $subject = mysqli_real_escape_string($conn,$_POST['subject']);
    $year_level = mysqli_real_escape_string($conn,$_POST['year_level']);
    $section = mysqli_real_escape_string($conn,$_POST['section']);
    $day = mysqli_real_escape_string($conn,$_POST['day']);
    $time_start = $_POST['time_start'];
    $time_end = $_POST['time_end'];
    $room = mysqli_real_escape_string($conn,$_POST['room']);

    if(strpos($id,'sub')===0){
        $sub_id = intval(str_replace('sub','',$id));
        $result = mysqli_query($conn, "UPDATE subjects SET subject_name='$subject',year_level='$year_level',section='$section',day='$day',time_start='$time_start',time_end='$time_end',room='$room' WHERE id='$sub_id'");
        if ($result && mysqli_affected_rows($conn) > 0) { $_SESSION['success_message'] = 'Schedule updated successfully!'; }
        else { $_SESSION['error_message'] = 'Failed to update schedule: ' . mysqli_error($conn); }
    } else {
        $sched_id = intval($id);
        $result = mysqli_query($conn, "UPDATE schedules SET subject='$subject',year_level='$year_level',section='$section',day='$day',time_start='$time_start',time_end='$time_end',room='$room' WHERE id='$sched_id'");
        if ($result && mysqli_affected_rows($conn) > 0) { $_SESSION['success_message'] = 'Schedule updated successfully!'; }
        else { $_SESSION['error_message'] = 'Failed to update schedule: ' . mysqli_error($conn); }
    }
    header("Location: " . BASE_URL . "teachersportal/schedule.php");
    exit();
}

/* ── GET: Delete ── */
if(isset($_GET['delete']) && $is_admin){
    $id = $_GET['delete'];
    if(strpos($id,'sub')===0){
        $sub_id = intval(str_replace('sub','',$id));
        $result = mysqli_query($conn,"DELETE FROM subjects WHERE id='$sub_id'");
        if ($result && mysqli_affected_rows($conn) > 0) { $_SESSION['success_message'] = 'Schedule deleted successfully!'; }
        else { $_SESSION['error_message'] = 'Failed to delete schedule or no permission.'; }
    } else {
        $sched_id = intval($id);
        $result = mysqli_query($conn,"DELETE FROM schedules WHERE id='$sched_id'");
        if ($result && mysqli_affected_rows($conn) > 0) { $_SESSION['success_message'] = 'Schedule deleted successfully!'; }
        else { $_SESSION['error_message'] = 'Failed to delete schedule or no permission.'; }
    }
    header("Location: " . BASE_URL . "teachersportal/schedule.php");
    exit();
}

/* ── Fetch schedules ── */
$schedules_query = mysqli_query($conn, "
    SELECT * FROM schedules
    WHERE course='$selected_course' $teacher_year_filter $teacher_section_filter
    ORDER BY FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday'), time_start
") or die(mysqli_error($conn));

$schedules = [];
while($row = mysqli_fetch_assoc($schedules_query)){
    $schedules[$row['day']][] = $row;
}

$subjects_query = mysqli_query($conn, "
    SELECT * FROM subjects 
    WHERE course_id='$course_id' $teacher_year_filter $teacher_section_filter
") or die(mysqli_error($conn));

while($row = mysqli_fetch_assoc($subjects_query)){
    $schedules[$row['day']][] = [
        'id'         => 'sub'.$row['id'],
        'code'       => $row['code'],
        'subject'    => $row['subject_name'],
        'year_level' => $row['year_level'],
        'section'    => $row['section'] ?? '',
        'day'        => $row['day'],
        'time_start' => $row['time_start'],
        'time_end'   => $row['time_end'],
        'room'       => $row['room'],
    ];
}

$year_order = ['1st Year' => 1, '2nd Year' => 2, '3rd Year' => 3, '4th Year' => 4];
foreach ($schedules as $day => &$day_schedules) {
    usort($day_schedules, function($a, $b) use ($year_order) {
        $year_a = $year_order[$a['year_level']] ?? 99;
        $year_b = $year_order[$b['year_level']] ?? 99;
        if ($year_a !== $year_b) return $year_a - $year_b;
        return strcmp($a['section'], $b['section']);
    });
}
unset($day_schedules);

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$year_colors = [
    '1st Year' => ['bg'=>'#eff6ff','accent'=>'#3b82f6','dark'=>'#1d4ed8'],
    '2nd Year' => ['bg'=>'#ecfdf5','accent'=>'#10b981','dark'=>'#059669'],
    '3rd Year' => ['bg'=>'#fffbeb','accent'=>'#f59e0b','dark'=>'#d97706'],
    '4th Year' => ['bg'=>'#fef2f2','accent'=>'#ef4444','dark'=>'#dc2626'],
];

$total_classes = 0;
foreach ($schedules as $day_scheds) { $total_classes += count($day_scheds); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($selected_course) ?> Schedule</title>
    <link rel="icon" href="<?= asset('images/622685015_925666030131412_6886851389087569993_n.jpg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/teacherportal.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include PROJECT_ROOT . '/teachersportal/sidebar.php'; ?>

<div class="content">

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- ── PAGE HEADER ── -->
    <div class="page-header-bar">
        <div>
            <div class="page-header-eyebrow"><?= htmlspecialchars($selected_course) ?> Portal</div>
            <h1 class="page-header-title">Class Schedules</h1>
        </div>
        <?php if ($total_classes > 0): ?>
            <span class="result-count"><i class="fas fa-calendar-alt"></i> <?= $total_classes ?> class<?= $total_classes !== 1 ? 'es' : '' ?></span>
        <?php endif; ?>
    </div>

    <!-- ── YEAR LEVEL LEGEND ── -->
    <div class="year-legend">
        <?php foreach ($year_colors as $label => $clr): ?>
            <span class="year-legend-item" style="color:<?= $clr['dark'] ?>;background:<?= $clr['bg'] ?>;border-color:<?= $clr['accent'] ?>33;">
                <span class="year-legend-dot" style="background:<?= $clr['accent'] ?>;"></span>
                <?= $label ?>
            </span>
        <?php endforeach; ?>
    </div>

    <!-- ── DAYS ── -->
    <?php foreach($days as $day): ?>
    <div class="day-section">
        <div class="day-label">
            <span class="day-label-text"><?= $day ?></span>
            <div class="day-label-line"></div>
            <?php if (isset($schedules[$day])): ?>
                <span class="day-label-count"><?= count($schedules[$day]) ?> class<?= count($schedules[$day]) !== 1 ? 'es' : '' ?></span>
            <?php endif; ?>
        </div>

        <?php if (isset($schedules[$day]) && count($schedules[$day]) > 0): ?>
        <div class="cards">
            <?php foreach($schedules[$day] as $sched):
                $clr = $year_colors[$sched['year_level']] ?? ['bg'=>'#f9fafb','accent'=>'#6b7280','dark'=>'#374151'];
            ?>
            <div class="sched-card card-clickable"
                 data-id="<?= $sched['id'] ?>"
                 data-subject="<?= htmlspecialchars($sched['subject'],ENT_QUOTES) ?>"
                 data-year="<?= $sched['year_level'] ?>"
                 data-section="<?= htmlspecialchars($sched['section'],ENT_QUOTES) ?>"
                 data-day="<?= $sched['day'] ?>"
                 data-start="<?= $sched['time_start'] ?>"
                 data-end="<?= $sched['time_end'] ?>"
                 data-room="<?= htmlspecialchars($sched['room'],ENT_QUOTES) ?>">

                <div class="sched-card-stripe" style="background:linear-gradient(90deg,<?= $clr['accent'] ?>,<?= $clr['dark'] ?>);"></div>

                <div class="sched-card-body">
                    <!-- Admin actions -->
                    <?php if ($is_admin): ?>
                    <div class="sched-card-actions">
                        <a href="#" class="edit-schedule" title="Edit"><i class="fas fa-pencil-alt"></i></a>
                        <a href="#" class="delete-schedule" title="Delete"><i class="fas fa-trash"></i></a>
                    </div>
                    <?php endif; ?>

                    <div class="sched-card-year"
                         style="background:<?= $clr['accent'] ?>20;color:<?= $clr['dark'] ?>;border:1px solid <?= $clr['accent'] ?>44;">
                        <i class="fas fa-circle" style="font-size:7px;"></i>
                        <?= $sched['year_level'] ?>
                    </div>

                    <h3 class="sched-card-title">
                        <?= htmlspecialchars((!empty($sched['code']) ? $sched['code'].' — ' : '') . $sched['subject']) ?>
                    </h3>

                    <div class="sched-info">
                        <div class="sched-info-row">
                            <i class="fas fa-users" style="color:<?= $clr['accent'] ?>;"></i>
                            Section <?= htmlspecialchars($sched['section']) ?>
                        </div>
                        <div class="sched-info-row">
                            <i class="fas fa-clock" style="color:<?= $clr['accent'] ?>;"></i>
                            <?= date("h:i A", strtotime($sched['time_start'])) ?> – <?= date("h:i A", strtotime($sched['time_end'])) ?>
                        </div>
                        <div class="sched-info-row">
                            <i class="fas fa-door-open" style="color:<?= $clr['accent'] ?>;"></i>
                            Room <?= htmlspecialchars($sched['room']) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="day-empty">
            <i class="fas fa-calendar-xmark"></i>
            No classes scheduled for <?= $day ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- ── EDIT MODAL ── -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fas fa-edit"></i> Edit Schedule</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_subject">Subject</label>
                    <input type="text" name="subject" id="edit_subject" class="input-field" required
                           oninput="this.value=this.value.toUpperCase()">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_year_level">Year Level</label>
                        <select name="year_level" id="edit_year_level" class="input-field" required>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_section">Section</label>
                        <input type="text" name="section" id="edit_section" class="input-field" required
                               maxlength="1" pattern="[A-E]" title="A–E"
                               oninput="this.value=this.value.toUpperCase().replace(/[^A-E]/g,'')">
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_day">Day</label>
                    <select name="day" id="edit_day" class="input-field" required>
                        <?php foreach ($days as $d): ?>
                            <option><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_time_start">Start Time</label>
                        <input type="time" name="time_start" id="edit_time_start" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_time_end">End Time</label>
                        <input type="time" name="time_end" id="edit_time_end" class="input-field" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_room">Room</label>
                    <input type="text" name="room" id="edit_room" class="input-field" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel"
                            onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                    <button type="submit" name="save_schedule" class="btn-add">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── DELETE MODAL ── -->
    <div id="deleteModal" class="modal">
        <div class="modal-content small">
            <span class="close close-delete-modal">&times;</span>
            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h2>
            <p>Are you sure you want to delete this schedule? This action cannot be undone.</p>
            <div class="modal-actions">
                <button id="cancelDeleteBtn" class="btn-cancel">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn-danger">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>
    </div>

</div><!-- /.content -->

<script>
document.addEventListener("DOMContentLoaded", function(){
    var editModal   = document.getElementById("editModal");
    var deleteModal = document.getElementById("deleteModal");
    let deleteUrl   = '';

    document.querySelectorAll(".edit-schedule").forEach(function(btn){
        btn.addEventListener("click", function(e){
            e.preventDefault();
            var card = btn.closest(".sched-card");
            document.getElementById("edit_id").value         = card.dataset.id;
            document.getElementById("edit_subject").value    = card.dataset.subject;
            document.getElementById("edit_year_level").value = card.dataset.year;
            document.getElementById("edit_section").value    = card.dataset.section;
            document.getElementById("edit_day").value        = card.dataset.day;
            document.getElementById("edit_time_start").value = card.dataset.start;
            document.getElementById("edit_time_end").value   = card.dataset.end;
            document.getElementById("edit_room").value       = card.dataset.room;
            editModal.style.display = "flex";
        });
    });

    document.querySelectorAll(".delete-schedule").forEach(function(btn){
        btn.addEventListener("click", function(e){
            e.preventDefault();
            var card = btn.closest(".sched-card");
            deleteUrl = '?delete=' + card.dataset.id;
            deleteModal.style.display = "flex";
        });
    });

    document.querySelectorAll(".close").forEach(function(span){
        span.addEventListener("click", function(){
            editModal.style.display   = "none";
            deleteModal.style.display = "none";
        });
    });

    document.getElementById("cancelDeleteBtn").addEventListener("click", function(){
        deleteModal.style.display = "none";
    });

    document.getElementById("confirmDeleteBtn").addEventListener("click", function(e){
        e.preventDefault();
        if(deleteUrl){ window.location.href = deleteUrl; }
    });

    window.onclick = function(event){
        if(event.target == editModal)   editModal.style.display   = "none";
        if(event.target == deleteModal) deleteModal.style.display = "none";
    };

    setTimeout(function(){
        document.querySelectorAll('.alert').forEach(function(a){
            a.style.transition = 'opacity 0.3s ease-out';
            a.style.opacity    = '0';
            setTimeout(function(){ a.remove(); }, 300);
        });
    }, 5000);

    document.addEventListener('click', function(e){
        if(e.target.classList.contains('alert-close')){
            var a = e.target.closest('.alert');
            a.style.transition = 'opacity 0.3s ease-out';
            a.style.opacity    = '0';
            setTimeout(function(){ a.remove(); }, 300);
        }
    });
});

</script>
</body>
</html>