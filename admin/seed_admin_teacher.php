<?php
require_once dirname(__DIR__) . '/config/paths.php';
require_once PROJECT_ROOT . '/config/database.php';

$teacher_id = "00-0000";
$first_name = "System";
$middle_name = ""; // optional
$last_name = "Administrator";
$suffix = ""; // optional
$dob = "1980-01-01";
$gender = "Female"; // or "Male"
$civil_status = "Single";
$nationality = "Filipino";
$teacher_type = "Administrator"; // this identifies the admin
$email = "admin@school.edu.ph";
$mobile = "09171234567";
$home_address = "School Campus, San Mateo, Isabela";
$emergency_person = "Maccoy Tabios"; // example emergency contact
$emergency_number = "09179876543";
$password = password_hash("Registrar123", PASSWORD_DEFAULT); // hashed password

// Check if admin already exists
$stmt = $conn->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("s", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $stmt = $conn->prepare("INSERT INTO teachers (teacher_id, first_name, middle_name, last_name, suffix, dob, gender, civil_status, nationality, teacher_type, email, mobile, home_address, emergency_person, emergency_number, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssssssss", $teacher_id, $first_name, $middle_name, $last_name, $suffix, $dob, $gender, $civil_status, $nationality, $teacher_type, $email, $mobile, $home_address, $emergency_person, $emergency_number, $password);

    if ($stmt->execute()) {
        echo "Admin teacher account created successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
} else {
    echo "Admin teacher account already exists!";
}

$conn->close();
?>