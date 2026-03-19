<?php
/**
 * Global School Year Helper
 * Returns active school year/semester info
 */

function getActiveSchoolYear($conn) {
    $result = mysqli_query($conn, "
        SELECT school_year FROM school_years WHERE is_active = 1 LIMIT 1
    ");
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['school_year'];
    }
    return null;
}

function getActiveSemester($conn) {
    $result = mysqli_query($conn, "
        SELECT semester FROM school_years WHERE is_active = 1 LIMIT 1
    ");
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['semester'];
    }
    return null;
}

function getActiveSchoolYearId($conn) {
    $result = mysqli_query($conn, "
        SELECT id FROM school_years WHERE is_active = 1 LIMIT 1
    ");
    if ($row = mysqli_fetch_assoc($result)) {
        return (int)$row['id'];
    }
    return null;
}

function getAllSchoolYears($conn) {
    $result = mysqli_query($conn, "SELECT * FROM school_years ORDER BY school_year DESC, semester ASC");
    $years = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $years[] = $row;
    }
    return $years;
}
?>

