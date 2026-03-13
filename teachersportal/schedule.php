<?php
session_start();
include '../config/database.php';
include '../config/teacher_filter.php';

// ================== BUILD TEACHER FILTER ==================
$admin_types = ['Seeder','Administrator'];
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
$teacher_year_filter = '';
$teacher_section_filter = '';
if (!$is_admin) {
    $teacher_year_filter = getYearLevelFilter('year_level');
    $teacher_section_filter = getSectionFilter('section');
}


// ================== CHECK LOGIN ==================
if(!isset($_SESSION['teacher_id'])){
    header("Location: ../Accesspage/teacher_login.php");
    exit();
}

// ================== DYNAMIC BACK ARROW LOGIC ==================
// Seeder/admin accounts go to chooseSub.php; regular teachers go to login page
$back_url = "../Accesspage/teacher_login.php"; // default
$admin_types = ['Seeder','Administrator']; // list of seeded/admin accounts
$is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
if(isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types)){
    $back_url = "../teachersportal/chooseSub.php";
}

// ================== SET COURSE FROM SESSION ==================
$selected_course = $_SESSION['teacher_course'] ?? '';
if(empty($selected_course)){
    echo "Course not assigned to this teacher. Contact admin.";
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// Allowed courses
$allowed_courses = ['BSIT','BSED','BAT','BTVTED'];
if(!in_array(strtoupper($selected_course), $allowed_courses)){
    echo "<p>No course selected. Please go back and choose a course.</p>";
    echo '<a href="chooseSub.php">← Go Back</a>';
    exit();
}

// ================== GET COURSE ID ==================
// Minimal addition so subjects from addsubject.php appear
$course_result = mysqli_query($conn, "SELECT id FROM courses WHERE course_name='$selected_course'");
if(!$course_result || mysqli_num_rows($course_result) == 0){
    die("Selected course not found in database.");
}
$course_row = mysqli_fetch_assoc($course_result);
$course_id = $course_row['id'];

// --- HANDLE ADD/EDIT SCHEDULE ---
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
        // Update subject from addsubject.php
        $sub_id = intval(str_replace('sub','',$id));
        mysqli_query($conn, "
            UPDATE subjects SET
                subject_name='$subject',
                year_level='$year_level',
                section='$section',
                day='$day',
                time_start='$time_start',
                time_end='$time_end',
                room='$room'
            WHERE id='$sub_id'
        ") or die(mysqli_error($conn));
    } else {
        // Update manual schedule
        $sched_id = intval($id);
        mysqli_query($conn, "
            UPDATE schedules SET
                subject='$subject',
                year_level='$year_level',
                section='$section',
                day='$day',
                time_start='$time_start',
                time_end='$time_end',
                room='$room'
            WHERE id='$sched_id'
        ") or die(mysqli_error($conn));
    }

    header("Location: schedule.php");
    exit();
}

// --- HANDLE DELETE ---
if(isset($_GET['delete']) && $is_admin){
    $id = $_GET['delete'];

    if(strpos($id,'sub')===0){
        $sub_id = intval(str_replace('sub','',$id));
        mysqli_query($conn,"DELETE FROM subjects WHERE id='$sub_id'") or die(mysqli_error($conn));
    } else {
        $sched_id = intval($id);
        mysqli_query($conn,"DELETE FROM schedules WHERE id='$sched_id'") or die(mysqli_error($conn));
    }

    header("Location: schedule.php");
    exit();
}

// --- FETCH MANUAL SCHEDULES ---
$schedules_query = mysqli_query($conn, "
    SELECT * FROM schedules
    WHERE course='$selected_course' $teacher_year_filter $teacher_section_filter
    ORDER BY FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday'), time_start
") or die(mysqli_error($conn));


$schedules = [];
while($row = mysqli_fetch_assoc($schedules_query)){
    $schedules[$row['day']][] = $row;
}

// --- FETCH SUBJECTS ADDED VIA addsubject.php ---
// CHANGE: fetch using course_id instead of course name
$subjects_query = mysqli_query($conn, "
    SELECT * FROM subjects 
    WHERE course_id='$course_id' $teacher_year_filter $teacher_section_filter
") or die(mysqli_error($conn));

while($row = mysqli_fetch_assoc($subjects_query)){
    $schedules[$row['day']][] = [
        'id' => 'sub'.$row['id'], // differentiate from manual schedules
        'subject' => $row['subject_name'],
        'year_level' => $row['year_level'],
        'section' => $row['section'] ?? '',
        'day' => $row['day'],
        'time_start' => $row['time_start'],
        'time_end' => $row['time_end'],
        'room' => $row['room']
    ];
}

// --- CONFIG ---
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$year_colors = ['1st Year'=>'#3b82f6','2nd Year'=>'#10b981','3rd Year'=>'#f59e0b','4th Year'=>'#ef4444'];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($selected_course) ?> Schedule</title>
    <link rel="stylesheet" href="../css/teacherportal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
    <div class="announcement-header">
        <h1><?= htmlspecialchars($selected_course) ?> Class Schedules</h1>
    </div>

   <?php foreach($days as $day): ?>
    <h2><?= $day ?></h2>
    <div class="cards">
        <?php 
        // Check if there are schedules for this day
        if(isset($schedules[$day]) && count($schedules[$day]) > 0){
            foreach($schedules[$day] as $sched): 
                // Determine color: subjects from addsubject.php = blue, otherwise based on year level
                $color = $year_colors[$sched['year_level']] ?? '#000000';
        ?>
        <div class="card card-clickable" 
             data-id="<?= $sched['id'] ?>" 
             data-subject="<?= htmlspecialchars($sched['subject'],ENT_QUOTES) ?>" 
             data-year="<?= $sched['year_level'] ?>" 
             data-section="<?= htmlspecialchars($sched['section'],ENT_QUOTES) ?>" 
             data-day="<?= $sched['day'] ?>" 
             data-start="<?= $sched['time_start'] ?>" 
             data-end="<?= $sched['time_end'] ?>" 
             data-room="<?= htmlspecialchars($sched['room'],ENT_QUOTES) ?>">

            <div class="year-badge" style="background:<?= $color ?>"><?= $sched['year_level'] ?></div>
            <h3><?= htmlspecialchars($sched['subject']) ?></h3>
            <p>Section: <?= htmlspecialchars($sched['section']) ?></p>
            <p><?= date("h:i A", strtotime($sched['time_start'])) ?> - <?= date("h:i A", strtotime($sched['time_end'])) ?></p>
            <p>Room: <?= htmlspecialchars($sched['room']) ?></p>

            <div class="card-actions">
                <?php if ($is_admin): ?>
                <a href="#" class="edit-schedule" title="Edit"><i class="fas fa-edit"></i></a>
                <a href="#" class="delete-schedule" title="Delete"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php 
            endforeach;
        } else {
            // Show a placeholder if no schedules exist for this day
            echo "<p>No classes scheduled</p>";
        }
        ?>
    </div>
<?php endforeach; ?>

<!-- Edit Schedule Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Schedule</h2>
        <form method="POST" id="editForm">
            <input type="hidden" name="id" id="edit_id">
            
           <div class="form-group">
    <label for="edit_subject">Subject</label>
    <input type="text" name="subject" id="edit_subject" required 
           oninput="this.value = this.value.toUpperCase();">
</div>
            
            <div class="form-group">
                <label for="edit_year_level">Year Level</label>
                <select name="year_level" id="edit_year_level" required>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                </select>
            </div>

            <div class="form-group">
    <label for="edit_section">Section</label>
    <input 
        type="text" 
        name="section" 
        id="edit_section" 
        required 
        maxlength="1" 
        pattern="[A-E]" 
        title="Enter a single letter between A and E"
        oninput="this.value = this.value.toUpperCase().replace(/[^A-E]/g,'')"
    >
</div>
            
            <div class="form-group">
                <label for="edit_day">Day</label>
                <select name="day" id="edit_day" required>
                    <option>Monday</option>
                    <option>Tuesday</option>
                    <option>Wednesday</option>
                    <option>Thursday</option>
                    <option>Friday</option>
                </select>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="edit_time_start">Start Time</label>
                    <input type="time" name="time_start" id="edit_time_start" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_time_end">End Time</label>
                    <input type="time" name="time_end" id="edit_time_end" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit_room">Room</label>
                <input type="text" name="room" id="edit_room" required>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-outline" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                <button type="submit" name="save_schedule" class="btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content small">
        <span class="close-delete-modal close">&times;</span>
        <h2><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Confirm Delete</h2>
        <p>Are you sure you want to delete this schedule? This action cannot be undone.</p>
        <div class="modal-actions">
            <button id="cancelDeleteBtn" class="btn-outline">Cancel</button>
            <a href="#" id="confirmDeleteBtn" class="btn-danger">Delete</a>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){

    var editModal = document.getElementById("editModal");
    var deleteModal = document.getElementById("deleteModal");
    var editForm = document.getElementById("editForm");
    var closeDeleteBtn = document.querySelector('.close-delete-modal');
    var cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    var confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    let deleteUrl = '';

    // Open edit modal
    document.querySelectorAll(".edit-schedule").forEach(function(btn){
        btn.addEventListener("click", function(e){
            e.preventDefault();
            var card = btn.closest(".card");
            document.getElementById("edit_id").value = card.dataset.id;
            document.getElementById("edit_subject").value = card.dataset.subject;
            document.getElementById("edit_year_level").value = card.dataset.year;
            document.getElementById("edit_section").value = card.dataset.section;
            document.getElementById("edit_day").value = card.dataset.day;
            document.getElementById("edit_time_start").value = card.dataset.start;
            document.getElementById("edit_time_end").value = card.dataset.end;
            document.getElementById("edit_room").value = card.dataset.room;
            editModal.style.display = "flex";
        });
    });

    // Open delete modal
    document.querySelectorAll(".delete-schedule").forEach(function(btn){
        btn.addEventListener("click", function(e){
            e.preventDefault();
            var card = btn.closest(".card");
            deleteUrl = '?delete=' + card.dataset.id;
            deleteModal.style.display = "flex";
        });
    });

    // Close modals
    document.querySelectorAll(".close").forEach(function(span){
        span.addEventListener("click", function(){ 
            editModal.style.display = "none";
            deleteModal.style.display = "none";
        });
    });

    // Delete modal specific close buttons
    if(closeDeleteBtn) {
        closeDeleteBtn.addEventListener("click", function(){
            deleteModal.style.display = "none";
        });
    }

    if(cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener("click", function(){
            deleteModal.style.display = "none";
        });
    }

    // Confirm delete
    if(confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", function(e){
            e.preventDefault();
            if(deleteUrl) {
                window.location.href = deleteUrl;
            }
        });
    }

    // Close modal if click outside
    window.onclick = function(event) {
        if (event.target == editModal) editModal.style.display = "none";
        if (event.target == deleteModal) deleteModal.style.display = "none";
    }
});
</script>
</body>
</html>