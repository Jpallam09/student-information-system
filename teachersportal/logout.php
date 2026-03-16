<?php
session_start();
session_destroy();
header("Location: ../Accesspage/teacher_login.php");
exit();
?>

