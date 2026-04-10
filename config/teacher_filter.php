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
            $level = ucwords(strtolower($level));
            if (!preg_match('/\\bYear$/i', $level)) {
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

// Manual year level filter (GET)
function getYearLevelFilter($column, &$params, &$types) {
    if (!empty($_GET['year_level'])) {
        $allowed_years = ['1st Year','2nd Year','3rd Year','4th Year'];
        $year = $_GET['year_level'];
        if (!in_array($year, $allowed_years)) {
            return '';
        }
        $params[] = $year;
        $types .= 's';
        return " AND $column = ? ";
    }
    return '';
}

// Manual section filter (GET)
function getSectionFilter($column, &$params, &$types) {
    if (!empty($_GET['section'])) {
        $section = $_GET['section'];
        if (!preg_match('/^[A-Za-z0-9\\-]+$/', $section)) {
            return '';
        }
        $params[] = $section;
        $types .= 's';
        return " AND $column = ? ";
    }
    return '';
}

// AUTO filter: teacher's year levels (literal IN)
function getAutoTeacherYearFilter($column) {
    $admin_types = ['Seeder', 'Administrator'];
    $is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
    if ($is_admin || empty($years = getTeacherYearLevels())) {
        return '';
    }
    $quoted_years = [];
    foreach ($years as $year) {
        $quoted_years[] = "'" . addslashes($year) . "'";
    }
    return " AND $column IN (" . implode(',', $quoted_years) . ") ";
}

// COMBINED year filter (auto + manual)
function getCombinedYearFilter($column, &$params, &$types) {
    $filter = getAutoTeacherYearFilter($column);
    $filter .= getYearLevelFilter($column, $params, $types);
    return $filter;
}

// AUTO section filter
function getAutoTeacherSectionFilter($column) {
    $admin_types = ['Seeder', 'Administrator'];
    $is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
    if ($is_admin || empty($sections = getTeacherSections())) {
        return '';
    }
    $quoted_sections = [];
    foreach ($sections as $sec) {
        $quoted_sections[] = "'" . addslashes($sec) . "'";
    }
    return " AND $column IN (" . implode(',', $quoted_sections) . ") ";
}

// COMBINED section filter
function getCombinedSectionFilter($column, &$params, &$types) {
    $filter = getAutoTeacherSectionFilter($column);
    $filter .= getSectionFilter($column, $params, $types);
    return $filter;
}

// Dropdown years for teachers (assigned or all)
function getTeacherDropdownYears() {
    $admin_types = ['Seeder', 'Administrator'];
    $is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
    if ($is_admin || empty($years = getTeacherYearLevels())) {
        return ['1st Year','2nd Year','3rd Year','4th Year'];
    }
    return $years;
}

// Dropdown sections for teachers (assigned or allow "All")
function getTeacherDropdownSections() {
    $admin_types = ['Seeder', 'Administrator'];
    $is_admin = isset($_SESSION['teacher_type']) && in_array($_SESSION['teacher_type'], $admin_types);
    if ($is_admin) {
        return ['A','B','C','D','E'];
    }
    $sections = getTeacherSections();
    if (empty($sections)) {
        return [''];  // Allow "All" selection even if no specific sections assigned
    }
    return $sections;
}

// Legacy
function getTeacherFilter($yearColumn = 'year_level', $sectionColumn = 'section') {
    $params = [];
    $types = '';
    $filter = getYearLevelFilter($yearColumn, $params, $types);
    $filter .= getSectionFilter($sectionColumn, $params, $types);
    return $filter;
}

// Has assignments
function hasTeacherAssignments() {
    $year_levels = getTeacherYearLevels();
    $sections = getTeacherSections();
    return !empty($year_levels) || !empty($sections);
}

// Display string
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
?>
