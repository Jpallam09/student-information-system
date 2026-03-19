<?php
/**
 * Teacher Filter Helper
 * This file provides functions to filter queries based on teacher's assigned year levels and sections
 */

// Get teacher's assigned year levels as array
function getTeacherYearLevels() {
    $year_levels_str = $_SESSION['teacher_year_levels'] ?? '';
    if (empty($year_levels_str)) {
        return [];
    }
    $levels = explode(',', $year_levels_str);
    $normalized = [];
    foreach ($levels as $level) {
        $level = trim($level);
        if (!empty($level)) {
            // Normalize: "2nd year" -> "2nd Year", "4thyear" -> "4th Year"
            $level = ucwords(strtolower($level));
            if (!preg_match('/\bYear$/i', $level)) {
                $level .= ' Year';
            }
            $normalized[] = $level;
        }
    }
    return $normalized;
}

// Get teacher's assigned sections as array
function getTeacherSections() {
    $sections_str = $_SESSION['teacher_sections'] ?? '';
    if (empty($sections_str)) {
        return [];
    }
    $sections = explode(',', $sections_str);
    $normalized = [];
    foreach ($sections as $sec) {
        $sec = trim($sec);
        if (!empty($sec)) {
            $normalized[] = $sec;
        }
    }
    return $normalized;
}

// Build SQL WHERE clause for year level filtering
function getYearLevelFilter($column = 'year_level') {
    $year_levels = getTeacherYearLevels();
    if (empty($year_levels)) {
        return ''; // No restriction - show all
    }
    
    $escaped = array_map(function($y) use ($column) {
        global $conn;
        return "'" . mysqli_real_escape_string($conn, trim($y)) . "'";
    }, $year_levels);
    
    return " AND $column IN (" . implode(',', $escaped) . ")";
}

// Build SQL WHERE clause for section filtering
function getSectionFilter($column = 'section') {
    $sections = getTeacherSections();
    if (empty($sections)) {
        return ''; // No restriction - show all
    }
    
    $escaped = array_map(function($s) use ($column) {
        global $conn;
        $s = trim($s);
        return "'" . mysqli_real_escape_string($conn, $s) . "'";
    }, $sections);
    
    return " AND $column IN (" . implode(',', $escaped) . ")";
}

// Get combined filter for both year level and section
function getTeacherFilter($yearColumn = 'year_level', $sectionColumn = 'section') {
    $filter = getYearLevelFilter($yearColumn);
    $filter .= getSectionFilter($sectionColumn);
    return $filter;
}

// Check if teacher has any assignments
function hasTeacherAssignments() {
    $year_levels = getTeacherYearLevels();
    $sections = getTeacherSections();
    return !empty($year_levels) || !empty($sections);
}

// Get teacher's assignments display string
function getTeacherAssignmentDisplay() {
    $year_levels = getTeacherYearLevels();
    $sections = getTeacherSections();
    
    $display = [];
    if (!empty($year_levels)) {
        $display[] = "Year Levels: " . implode(', ', $year_levels);
    }
    if (!empty($sections)) {
        $display[] = "Sections: " . implode(', ', $sections);
    }
    
    return empty($display) ? "All" : implode(' | ', $display);
}

