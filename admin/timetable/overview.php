<?php
/**
 * Admin Timetable View - Timetable Viewing Interface
 * Timetable Management System
 * 
 * Professional interface for admin to view and manage timetables
 * with comprehensive filtering, search, and multiple view modes
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Timetable.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$timetableManager = new Timetable();

// Initialize variables
$error_message = '';
$success_message = '';
$timetables = [];
$totalCount = 0;
$filters = [];
$departments = [];
$academicYears = [];
$semesters = [];
$subjects = [];
$faculty = [];
$classrooms = [];

// Get filter parameters
$currentAcademicYear = $_GET['academic_year'] ?? '2025-2026';
$currentSemester = $_GET['semester'] ?? 1;
$departmentFilter = $_GET['department'] ?? '';
$facultyFilter = $_GET['faculty'] ?? '';
$subjectFilter = $_GET['subject'] ?? '';
$classroomFilter = $_GET['classroom'] ?? '';
$dayFilter = $_GET['day'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$viewMode = $_GET['view'] ?? 'list';
$sortBy = $_GET['sort'] ?? 'day_time';
$sortOrder = $_GET['order'] ?? 'ASC';

// Pagination parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

try {
    // Get filter options
    $departments = $db->fetchAll("
        SELECT DISTINCT department 
        FROM subjects 
        WHERE is_active = 1 
        ORDER BY department ASC
    ");
    
    $academicYears = $db->fetchAll("
        SELECT DISTINCT academic_year 
        FROM timetables 
        ORDER BY academic_year DESC
    ");
    
    $semesters = $db->fetchAll("
        SELECT DISTINCT semester 
        FROM timetables 
        ORDER BY semester ASC
    ");
    
    // Build filter conditions
    $whereConditions = ['t.is_active = 1'];
    $params = [];
    
    if ($currentAcademicYear) {
        $whereConditions[] = 't.academic_year = ?';
        $params[] = $currentAcademicYear;
    }
    
    if ($currentSemester) {
        $whereConditions[] = 't.semester = ?';
        $params[] = $currentSemester;
    }
    
    if ($departmentFilter) {
        $whereConditions[] = 's.department = ?';
        $params[] = $departmentFilter;
    }
    
    if ($facultyFilter) {
        $whereConditions[] = 't.faculty_id = ?';
        $params[] = $facultyFilter;
    }
    
    if ($subjectFilter) {
        $whereConditions[] = 't.subject_id = ?';
        $params[] = $subjectFilter;
    }
    
    if ($classroomFilter) {
        $whereConditions[] = 't.classroom_id = ?';
        $params[] = $classroomFilter;
    }
    
    if ($dayFilter) {
        $whereConditions[] = 'ts.day_of_week = ?';
        $params[] = $dayFilter;
    }
    
    if ($searchQuery) {
        $whereConditions[] = '(s.subject_code LIKE ? OR s.subject_name LIKE ? OR 
                              CONCAT(f.first_name, " ", f.last_name) LIKE ? OR 
                              c.room_number LIKE ? OR c.building LIKE ?)';
        $searchTerm = '%' . $searchQuery . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Determine sort clause
    $sortClause = '';
    switch ($sortBy) {
        case 'subject':
            $sortClause = 's.subject_code ' . $sortOrder;
            break;
        case 'faculty':
            $sortClause = 'faculty_name ' . $sortOrder;
            break;
        case 'classroom':
            $sortClause = 'c.room_number ' . $sortOrder;
            break;
        case 'day_time':
        default:
            $sortClause = 'FIELD(ts.day_of_week, "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday") ' . $sortOrder . ', ts.start_time ' . $sortOrder;
            break;
    }
    
    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(DISTINCT t.timetable_id)
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN faculty f ON t.faculty_id = f.faculty_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE $whereClause
    ";
    
    $totalCount = $db->fetchColumn($countQuery, $params);
    
    // Get timetable data
    $query = "
        SELECT 
            t.timetable_id,
            t.section,
            t.semester,
            t.academic_year,
            t.max_students,
            t.notes,
            t.created_at,
            t.is_active,
            s.subject_id,
            s.subject_code,
            s.subject_name,
            s.credits,
            s.type as subject_type,
            s.department as subject_department,
            f.faculty_id,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
            f.employee_id,
            f.designation,
            c.classroom_id,
            c.room_number,
            c.building,
            c.capacity as room_capacity,
            c.type as room_type,
            ts.slot_id,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.slot_name,
            CONCAT(ts.start_time, ' - ', ts.end_time) as time_range,
            (SELECT COUNT(*) FROM enrollments e 
             WHERE e.subject_id = t.subject_id 
             AND e.section = t.section 
             AND e.academic_year = t.academic_year 
             AND e.semester = t.semester 
             AND e.status = 'enrolled') as enrolled_students
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN faculty f ON t.faculty_id = f.faculty_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE $whereClause
        ORDER BY $sortClause
        LIMIT $limit OFFSET $offset
    ";
    
    $timetables = $db->fetchAll($query, $params);
    
    // Get filter dropdown options based on current filters
    if ($currentAcademicYear && $currentSemester) {
        $subjects = $db->fetchAll("
            SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name
            FROM subjects s
            JOIN timetables t ON s.subject_id = t.subject_id
            WHERE t.academic_year = ? AND t.semester = ? AND t.is_active = 1
            ORDER BY s.subject_code
        ", [$currentAcademicYear, $currentSemester]);
        
        $faculty = $db->fetchAll("
            SELECT DISTINCT f.faculty_id, CONCAT(f.first_name, ' ', f.last_name) as faculty_name, f.employee_id
            FROM faculty f
            JOIN timetables t ON f.faculty_id = t.faculty_id
            WHERE t.academic_year = ? AND t.semester = ? AND t.is_active = 1
            ORDER BY faculty_name
        ", [$currentAcademicYear, $currentSemester]);
        
        $classrooms = $db->fetchAll("
            SELECT DISTINCT c.classroom_id, c.room_number, c.building
            FROM classrooms c
            JOIN timetables t ON c.classroom_id = t.classroom_id
            WHERE t.academic_year = ? AND t.semester = ? AND t.is_active = 1
            ORDER BY c.building, c.room_number
        ", [$currentAcademicYear, $currentSemester]);
    }
    
    // Check for success message from other operations
    if (isset($_GET['success'])) {
        $success_message = urldecode($_GET['success']);
    }
    
} catch (Exception $e) {
    error_log("Timetable View Error: " . $e->getMessage());
    $error_message = "An error occurred while loading timetable data.";
}

// Calculate pagination info
$totalPages = ceil($totalCount / $limit);
$startRecord = $totalCount > 0 ? $offset + 1 : 0;
$endRecord = min($offset + $limit, $totalCount);

// Set page title
$pageTitle = "View Timetables";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?> - Admin Panel</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-color-alpha: rgba(99, 102, 241, 0.1);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --info-color: #3b82f6;
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border-color: #475569;
            --navbar-height: 64px;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border-color: #475569;
        }

        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-color: #cbd5e1;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--navbar-height) + 2rem);
        }

        body.sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Page Header - Sticky under navbar */
        .page-header {
            position: sticky;
            top: var(--navbar-height);
            z-index: 998;
            margin-bottom: 1rem;
            margin-top: 1rem;

        }

        .header-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        [data-theme="dark"] .header-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
            border-color: var(--border-color);
        }

        .header-text {
            display: flex;
            flex-direction: column;
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        /* Back button styling */
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .back-icon {
            font-size: 1rem;
            font-weight: bold;
        }

        /* Glass card styling */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] .glass-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .glass-card {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Filter Section */
        .filters-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .filters-section {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .filters-section {
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        .filters-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .filters-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .filter-toggle {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: var(--text-primary);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] .filter-toggle {
            border-color: var(--border-color);
        }

        [data-theme="light"] .filter-toggle {
            border-color: var(--border-color);
        }

        .filters-content {
            display: none;
        }

        .filters-content.show {
            display: block;
        }

        /* Stats Bar */
        .stats-bar {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        [data-theme="dark"] .stats-bar {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .stats-bar {
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        .stats-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .view-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .view-toggle {
            display: flex;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            overflow: hidden;
        }

        [data-theme="dark"] .view-toggle {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .view-toggle {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .view-btn {
            background: transparent;
            border: none;
            padding: 0.5rem 1rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .view-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .view-btn:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
            background: rgba(255, 255, 255, 0.15);
        }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background: var(--bg-secondary);
        }

        [data-theme="light"] .form-control,
        [data-theme="light"] .form-select {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        /* Search Bar */
        .search-container {
            position: relative;
            max-width: 400px;
        }

        .search-input {
            padding-left: 2.5rem;
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            pointer-events: none;
        }

        /* Timetable List View */
        .timetable-list {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            overflow: hidden;
        }

        [data-theme="dark"] .timetable-list {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .timetable-list {
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        .timetable-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        /* Mobile: make list items look like cards with thick blue border */
        @media (max-width: 768px) {
            .timetable-item {
                border: 3px solid rgba(59, 130, 246, 0.8); /* blue-500 */
                border-radius: 16px;
                margin: 0 0 1rem 0;
                background: rgba(255, 255, 255, 0.08);
            }
        }

        [data-theme="dark"] .timetable-item {
            border-bottom-color: var(--border-color);
        }

        /* Dark mode: stronger mobile border contrast */
        @media (max-width: 768px) {
            [data-theme="dark"] .timetable-item {
                border: 3px solid rgba(59, 130, 246, 0.9);
                background: rgba(0, 0, 0, 0.25);
            }
        }

        [data-theme="light"] .timetable-item {
            border-bottom-color: var(--border-color);
        }

        .timetable-item:last-child {
            border-bottom: none;
        }

        .timetable-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        [data-theme="dark"] .timetable-item:hover {
            background: var(--bg-tertiary);
        }

        [data-theme="light"] .timetable-item:hover {
            background: var(--bg-secondary);
        }

        .item-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .item-actions {
            display: flex;
            gap: 0.5rem;
        }

        .item-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 0.25rem;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 0.875rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        /* Subject Type Badges */
        .subject-type {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .subject-type.theory {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .subject-type.practical {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .subject-type.lab {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        /* Day of Week Badges */
        .day-badge {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            text-align: center;
            min-width: 80px;
        }

        .day-badge.Monday { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .day-badge.Tuesday { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .day-badge.Wednesday { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .day-badge.Thursday { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .day-badge.Friday { background: rgba(147, 51, 234, 0.1); color: #9333ea; border: 1px solid rgba(147, 51, 234, 0.3); }
        .day-badge.Saturday { background: rgba(99, 102, 241, 0.1); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.3); }

        /* Schedule Grid View */
        .timetable-grid {
            display: grid;
            grid-template-columns: 120px repeat(6, 1fr);
            gap: 1px;
            background: var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            min-width: 900px;
        }

        .schedule-grid-header {
            background: var(--bg-tertiary);
            padding: 1rem 0.75rem;
            font-weight: 600;
            text-align: center;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        [data-theme="dark"] .schedule-grid-header {
            background: var(--bg-tertiary);
        }

        [data-theme="light"] .schedule-grid-header {
            background: var(--bg-tertiary);
        }

        .time-header {
            background: var(--primary-color);
            color: white;
        }

        .day-header {
            background: var(--bg-secondary);
        }

        [data-theme="dark"] .day-header {
            background: var(--bg-secondary);
        }

        [data-theme="light"] .day-header {
            background: var(--bg-secondary);
        }

        .time-slot {
            background: var(--primary-color);
            color: white;
            padding: 1rem 0.75rem;
            font-size: 0.8rem;
            text-align: center;
            font-weight: 500;
        }

        .schedule-cell {
            background: var(--bg-primary);
            padding: 0.5rem;
            min-height: 80px;
            position: relative;
            transition: all 0.2s ease;
        }

        [data-theme="dark"] .schedule-cell {
            background: var(--bg-primary);
        }

        [data-theme="light"] .schedule-cell {
            background: var(--bg-primary);
        }

        .schedule-cell:hover {
            background: var(--bg-secondary);
        }

        .class-item {
            background: linear-gradient(135deg, var(--primary-color) 0%, rgba(102, 126, 234, 0.8) 100%);
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            height: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .class-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .class-code {
            font-weight: 700;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }

        .class-name {
            font-size: 0.7rem;
            opacity: 0.9;
            margin-bottom: 0.25rem;
            line-height: 1.2;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .class-room {
            font-size: 0.65rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .class-faculty {
            font-size: 0.65rem;
            opacity: 0.8;
            margin-bottom: 0.25rem;
        }

        .class-students {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.125rem 0.375rem;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        .class-section {
            position: absolute;
            bottom: 0.25rem;
            left: 0.25rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.125rem 0.375rem;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        /* Action Buttons */
        .btn-action {
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-1px);
            color: white;
        }

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }

        [data-theme="dark"] .btn-outline {
            border-color: var(--border-color);
        }

        [data-theme="light"] .btn-outline {
            border-color: var(--border-color);
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
        }

        [data-theme="dark"] .pagination-container {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .pagination-container {
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        .pagination-info {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .pagination-nav {
            display: flex;
            gap: 0.5rem;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .pagination-btn:hover:not(.disabled) {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
        }

        .pagination-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        [data-theme="dark"] .pagination-btn {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .pagination-btn {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        /* Loading State */
        .loading-spinner {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
        }

        .spinner {
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: calc(var(--navbar-height) + 1rem);
            }

            .item-details {
                grid-template-columns: 1fr;
            }

            .timetable-grid {
                min-width: 700px;
                font-size: 0.8rem;
            }

            .class-item {
                padding: 0.5rem;
            }

            .class-name {
                font-size: 0.65rem;
            }

            .class-room, .class-faculty {
                font-size: 0.6rem;
            }
        }

        @media (max-width: 768px) {
            /* Keep compact header inline on mobile */
            .header-card {
                flex-direction: row;
                text-align: left;
                gap: 1rem;
            }

            /* Force list view on mobile - hide grid view completely */
            .view-toggle .view-btn[data-view="grid"] {
                display: none;
            }

            .timetable-grid {
                display: none !important;
            }

            /* Ensure list view is always shown on mobile */
            .timetable-list {
                display: block !important;
            }

            .filters-section {
                padding: 1rem;
            }

            .filters-content .row {
                --bs-gutter-x: 0.5rem;
            }

            .filters-content .col-md-3,
            .filters-content .col-md-4,
            .filters-content .col-md-6 {
                margin-bottom: 1rem;
            }

            .stats-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .view-controls {
                justify-content: space-between;
            }

            .timetable-item {
                padding: 1rem;
            }

            .item-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .item-actions {
                align-self: stretch;
                justify-content: space-between;
            }

            .pagination-container {
                flex-direction: column;
                gap: 1rem;
            }

            .pagination-nav {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .timetable-grid {
                grid-template-columns: 80px repeat(6, 1fr);
                min-width: 600px;
            }

            .schedule-grid-header,
            .time-slot {
                padding: 0.5rem 0.25rem;
                font-size: 0.75rem;
            }

            .class-item {
                padding: 0.375rem;
            }

            .class-code {
                font-size: 0.7rem;
            }

            .class-name {
                font-size: 0.6rem;
                -webkit-line-clamp: 1;
            }

            .class-room,
            .class-faculty {
                font-size: 0.55rem;
            }

            .class-students,
            .class-section {
                font-size: 0.55rem;
                padding: 0.1rem 0.3rem;
            }
        }

        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-up {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Print styles for grid view */
        @media print {
            .timetable-grid {
                grid-template-columns: 100px repeat(6, 1fr);
                font-size: 0.7rem;
            }

            .class-item {
                background: white !important;
                color: black !important;
                border: 1px solid #ccc;
            }

            .item-actions {
                display: none !important;
            }

            .view-controls,
            .filters-section,
            .pagination-container {
                display: none !important;
            }
        }rem;
            }

            .item-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .item-actions {
                align-self: stretch;
                justify-content: space-between;
            }

            .pagination-container {
                flex-direction: column;
                gap: 1rem;
            }

            .pagination-nav {
                flex-wrap: wrap;
                justify-content: center;
            }
        }rem;
            }

            .item-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .item-actions {
                align-self: stretch;
                justify-content: space-between;
            }

            .pagination-container {
                flex-direction: column;
                gap: 1rem;
            }

            .pagination-nav {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-up {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Modal Dark Mode Styles */
        [data-theme="dark"] .modal-content {
            background: var(--bg-secondary) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .modal-header {
            border-bottom: 1px solid var(--border-color) !important;
            background: var(--bg-secondary) !important;
        }

        [data-theme="dark"] .modal-title {
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .modal-body {
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .modal-footer {
            background: var(--bg-secondary) !important;
            border-top: 1px solid var(--border-color) !important;
        }

        [data-theme="dark"] .btn-close {
            filter: invert(1);
        }

        /* Light mode modal - ensure white background */
        [data-theme="light"] .modal-content,
        .modal-content {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            color: #1e293b !important;
        }

        [data-theme="light"] .modal-header,
        .modal-header {
            border-bottom: 1px solid #e2e8f0 !important;
            background: #ffffff !important;
        }

        [data-theme="light"] .modal-title,
        .modal-title {
            color: #1e293b !important;
        }

        [data-theme="light"] .modal-body,
        .modal-body {
            background: #ffffff !important;
            color: #1e293b !important;
        }

        [data-theme="light"] .modal-footer,
        .modal-footer {
            background: #ffffff !important;
            border-top: 1px solid #e2e8f0 !important;
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../../includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-card glass-card fade-in">
                <div class="header-text">
                    <h1 class="page-title">📅 View Timetables</h1>
                </div>
                <div class="d-flex gap-2">
                    <a href="create.php" class="btn-action btn-primary btn-sm">
                        <i class="fas fa-plus"></i> <span class="d-none d-sm-inline">Create</span>
                    </a>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success glass-card fade-in">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card fade-in">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filters-section fade-in">
            <div class="filters-header">
                <h3 class="filters-title">🔍 Search & Filters</h3>
                <button type="button" class="filter-toggle" onclick="toggleFilters()">
                    <i class="fas fa-filter"></i> <span id="filterToggleText">Show Filters</span>
                </button>
            </div>
            
            <div class="filters-content" id="filtersContent">
                <form method="GET" id="filtersForm">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Search</label>
                            <div class="search-container">
                                <input type="text" class="form-control search-input" name="search" 
                                       value="<?= htmlspecialchars($searchQuery) ?>" 
                                       placeholder="Search subjects, faculty, rooms...">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Academic Year</label>
                            <select class="form-select" name="academic_year" onchange="this.form.submit()">
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= htmlspecialchars($year['academic_year']) ?>" 
                                            <?= $currentAcademicYear === $year['academic_year'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['academic_year']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Semester</label>
                            <select class="form-select" name="semester" onchange="this.form.submit()">
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?= $sem['semester'] ?>" 
                                            <?= $currentSemester == $sem['semester'] ? 'selected' : '' ?>>
                                        Semester <?= $sem['semester'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['department']) ?>" 
                                            <?= $departmentFilter === $dept['department'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Subject</label>
                            <select class="form-select" name="subject">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $subj): ?>
                                    <option value="<?= $subj['subject_id'] ?>" 
                                            <?= $subjectFilter == $subj['subject_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subj['subject_code']) ?> - <?= htmlspecialchars($subj['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Faculty</label>
                            <select class="form-select" name="faculty">
                                <option value="">All Faculty</option>
                                <?php foreach ($faculty as $fac): ?>
                                    <option value="<?= $fac['faculty_id'] ?>" 
                                            <?= $facultyFilter == $fac['faculty_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($fac['faculty_name']) ?> (<?= htmlspecialchars($fac['employee_id']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Classroom</label>
                            <select class="form-select" name="classroom">
                                <option value="">All Classrooms</option>
                                <?php foreach ($classrooms as $room): ?>
                                    <option value="<?= $room['classroom_id'] ?>" 
                                            <?= $classroomFilter == $room['classroom_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($room['room_number']) ?> - <?= htmlspecialchars($room['building']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Day of Week</label>
                            <select class="form-select" name="day">
                                <option value="">All Days</option>
                                <?php $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; ?>
                                <?php foreach ($days as $day): ?>
                                    <option value="<?= $day ?>" <?= $dayFilter === $day ? 'selected' : '' ?>>
                                        <?= $day ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort By</label>
                            <select class="form-select" name="sort">
                                <option value="day_time" <?= $sortBy === 'day_time' ? 'selected' : '' ?>>Day & Time</option>
                                <option value="subject" <?= $sortBy === 'subject' ? 'selected' : '' ?>>Subject</option>
                                <option value="faculty" <?= $sortBy === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                                <option value="classroom" <?= $sortBy === 'classroom' ? 'selected' : '' ?>>Classroom</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Order</label>
                            <select class="form-select" name="order">
                                <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                                <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn-action btn-primary flex-fill">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="view.php" class="btn-action btn-outline">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar fade-in">
            <div class="stats-info">
                <i class="fas fa-calendar-alt"></i>
                <span>Showing <?= $startRecord ?>-<?= $endRecord ?> of <?= $totalCount ?> timetable entries</span>
                <?php if ($currentAcademicYear && $currentSemester): ?>
                    <span class="mx-2">•</span>
                    <i class="fas fa-graduation-cap"></i>
                    <span><?= htmlspecialchars($currentAcademicYear) ?>, Semester <?= $currentSemester ?></span>
                <?php endif; ?>
            </div>
            
            <div class="view-controls">
                <div class="view-toggle">
                    <button class="view-btn <?= $viewMode === 'list' ? 'active' : '' ?>" 
                            data-view="list" onclick="changeView('list')">
                        <i class="fas fa-list"></i> List
                    </button>
                    <button class="view-btn <?= $viewMode === 'grid' ? 'active' : '' ?>" 
                            data-view="grid" onclick="changeView('grid')">
                        <i class="fas fa-th-large"></i> Grid
                    </button>
                </div>
            </div>
        </div>

        <!-- Timetable Content -->
        <?php if (empty($timetables)): ?>
            <!-- Empty State -->
            <div class="glass-card slide-up">
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Timetables Found</h3>
                    <p>No timetable entries match your current filters. Try adjusting your search criteria or create a new timetable entry.</p>
                    <a href="create.php" class="btn-action btn-primary">
                        <i class="fas fa-plus"></i> Create New Timetable
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- List View -->
            <div class="timetable-list slide-up" id="listView" style="display: <?= $viewMode === 'list' ? 'block' : 'none' ?>">
                <?php foreach ($timetables as $timetable): ?>
                    <div class="timetable-item">
                        <div class="item-header">
                            <h4 class="item-title">
                                <?= htmlspecialchars($timetable['subject_code']) ?> - <?= htmlspecialchars($timetable['subject_name']) ?>
                                <span class="subject-type <?= $timetable['subject_type'] ?>">
                                    <?= ucfirst($timetable['subject_type']) ?>
                                </span>
                            </h4>
                            <div class="item-actions">
                                <a href="edit.php?id=<?= $timetable['timetable_id'] ?>" 
                                   class="btn-action btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="delete.php?id=<?= $timetable['timetable_id'] ?>" 
                                   class="btn-action btn-danger" title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this timetable entry?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                        
                        <div class="item-details">
                            <div class="detail-group">
                                <span class="detail-label">Schedule</span>
                                <div class="detail-value d-flex align-items-center gap-2">
                                    <span class="day-badge <?= $timetable['day_of_week'] ?>"><?= $timetable['day_of_week'] ?></span>
                                    <span><?= date('g:i A', strtotime($timetable['start_time'])) ?> - <?= date('g:i A', strtotime($timetable['end_time'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="detail-group">
                                <span class="detail-label">Faculty</span>
                                <span class="detail-value">
                                    <?= htmlspecialchars($timetable['faculty_name']) ?>
                                    <small class="text-muted">(<?= htmlspecialchars($timetable['employee_id']) ?>)</small>
                                </span>
                            </div>
                            
                            <div class="detail-group">
                                <span class="detail-label">Classroom</span>
                                <span class="detail-value">
                                    <?= htmlspecialchars($timetable['room_number']) ?>
                                    <small class="text-muted"><?= htmlspecialchars($timetable['building']) ?></small>
                                </span>
                            </div>
                            
                            <div class="detail-group">
                                <span class="detail-label">Section & Enrollment</span>
                                <span class="detail-value">
                                    Section <?= htmlspecialchars($timetable['section']) ?>
                                    <small class="text-muted">(<?= $timetable['enrolled_students'] ?> students)</small>
                                </span>
                            </div>
                            
                            <div class="detail-group">
                                <span class="detail-label">Department</span>
                                <span class="detail-value"><?= htmlspecialchars($timetable['subject_department']) ?></span>
                            </div>
                            
                            <div class="detail-group">
                                <span class="detail-label">Credits</span>
                                <span class="detail-value"><?= $timetable['credits'] ?> Credit<?= $timetable['credits'] > 1 ? 's' : '' ?></span>
                            </div>
                        </div>
                        
                        <?php if ($timetable['notes']): ?>
                            <div class="mt-3">
                                <span class="detail-label">Notes</span>
                                <div class="detail-value"><?= htmlspecialchars($timetable['notes']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Grid View -->
            <div class="timetable-grid slide-up" id="gridView" style="display: <?= $viewMode === 'grid' ? 'grid' : 'none' ?>;">
                <!-- Headers -->
                <div class="schedule-grid-header time-header">Time</div>
                <div class="schedule-grid-header day-header">Monday</div>
                <div class="schedule-grid-header day-header">Tuesday</div>
                <div class="schedule-grid-header day-header">Wednesday</div>
                <div class="schedule-grid-header day-header">Thursday</div>
                <div class="schedule-grid-header day-header">Friday</div>
                <div class="schedule-grid-header day-header">Saturday</div>

                <!-- Time slots and classes -->
                <?php 
                // Get unique time slots from timetables
                $uniqueTimeSlots = [];
                foreach ($timetables as $timetable) {
                    $timeKey = $timetable['start_time'] . '-' . $timetable['end_time'];
                    if (!isset($uniqueTimeSlots[$timeKey])) {
                        $uniqueTimeSlots[$timeKey] = [
                            'start_time' => $timetable['start_time'],
                            'end_time' => $timetable['end_time'],
                            'slot_name' => $timetable['slot_name']
                        ];
                    }
                }
                
                // Sort by start time
                uasort($uniqueTimeSlots, function($a, $b) {
                    return strcmp($a['start_time'], $b['start_time']);
                });
                
                // Organize timetables by day and time
                $scheduleGrid = [];
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                foreach ($days as $day) {
                    $scheduleGrid[$day] = [];
                }
                
                foreach ($timetables as $timetable) {
                    $day = $timetable['day_of_week'];
                    $timeKey = $timetable['start_time'] . '-' . $timetable['end_time'];
                    $scheduleGrid[$day][$timeKey] = $timetable;
                }
                
                // Helper function for time formatting
                function formatTimeGrid($time) {
                    return date('h:i A', strtotime($time));
                }
                ?>
                
                <?php if (!empty($uniqueTimeSlots)): ?>
                    <?php foreach ($uniqueTimeSlots as $timeKey => $slot): ?>
                        <div class="time-slot">
                            <?= formatTimeGrid($slot['start_time']) ?><br>
                            <small><?= formatTimeGrid($slot['end_time']) ?></small>
                        </div>
                        
                        <?php foreach ($days as $day): ?>
                            <div class="schedule-cell">
                                <?php if (isset($scheduleGrid[$day][$timeKey])): 
                                    $class = $scheduleGrid[$day][$timeKey];
                                ?>
                                    <div class="class-item" onclick="showClassDetailsModal(<?= htmlspecialchars(json_encode($class)) ?>)">
                                        <div class="class-students"><?= $class['enrolled_students'] ?></div>
                                        <div class="class-section">Sec <?= htmlspecialchars($class['section']) ?></div>
                                        
                                        <div class="class-code"><?= htmlspecialchars($class['subject_code']) ?></div>
                                        <div class="class-name"><?= htmlspecialchars($class['subject_name']) ?></div>
                                        <div class="class-faculty"><?= htmlspecialchars($class['faculty_name']) ?></div>
                                        <div class="class-room">
                                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($class['room_number']) ?>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <div class="item-actions" onclick="event.stopPropagation();">
                                                <a href="edit.php?id=<?= $class['timetable_id'] ?>" 
                                                   class="btn-action btn-warning" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?= $class['timetable_id'] ?>" 
                                                   class="btn-action btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;" title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this timetable entry?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Empty grid state -->
                    <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-secondary);">
                        <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>No timetable entries found for the current filters.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?= $startRecord ?>-<?= $endRecord ?> of <?= $totalCount ?> entries
                    </div>
                    
                    <div class="pagination-nav">
                        <?php
                        $currentUrl = $_SERVER['REQUEST_URI'];
                        $urlParts = parse_url($currentUrl);
                        parse_str($urlParts['query'] ?? '', $queryParams);
                        
                        // Previous button
                        if ($page > 1):
                            $queryParams['page'] = $page - 1;
                            $prevUrl = '?' . http_build_query($queryParams);
                        ?>
                            <a href="<?= htmlspecialchars($prevUrl) ?>" class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                <i class="fas fa-chevron-left"></i> Previous
                            </span>
                        <?php endif; ?>
                        
                        <?php
                        // Page numbers
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1):
                            $queryParams['page'] = 1;
                            $firstUrl = '?' . http_build_query($queryParams);
                        ?>
                            <a href="<?= htmlspecialchars($firstUrl) ?>" class="pagination-btn">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="pagination-btn disabled">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): 
                            $queryParams['page'] = $i;
                            $pageUrl = '?' . http_build_query($queryParams);
                        ?>
                            <a href="<?= htmlspecialchars($pageUrl) ?>" 
                               class="pagination-btn <?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php
                        if ($endPage < $totalPages):
                            if ($endPage < $totalPages - 1): ?>
                                <span class="pagination-btn disabled">...</span>
                            <?php endif; ?>
                            <?php
                            $queryParams['page'] = $totalPages;
                            $lastUrl = '?' . http_build_query($queryParams);
                            ?>
                            <a href="<?= htmlspecialchars($lastUrl) ?>" class="pagination-btn"><?= $totalPages ?></a>
                        <?php endif; ?>
                        
                        <?php
                        // Next button
                        if ($page < $totalPages):
                            $queryParams['page'] = $page + 1;
                            $nextUrl = '?' . http_build_query($queryParams);
                        ?>
                            <a href="<?= htmlspecialchars($nextUrl) ?>" class="pagination-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                Next <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Handle sidebar toggle events
            handleSidebarToggle();
            
            // Initialize view mode from URL or localStorage
            initializeViewMode();
            
            // Auto-submit form on filter changes
            initializeAutoSubmit();
            
            // Initialize search functionality
            initializeSearch();
        });

        /**
         * Apply current theme from localStorage
         */
        function applyCurrentTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            
            // Update theme toggle icon if it exists
            const themeIcon = document.querySelector('#themeToggle i');
            if (themeIcon) {
                themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        /**
         * Handle sidebar toggle
         */
        function handleSidebarToggle() {
            const toggleBtn = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (toggleBtn && sidebar && mainContent) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    document.body.classList.toggle('sidebar-collapsed');
                });
            }
        }

        /**
         * Initialize view mode
         */
        function initializeViewMode() {
            const urlParams = new URLSearchParams(window.location.search);
            const viewParam = urlParams.get('view');
            const savedView = localStorage.getItem('timetable_view_mode') || 'list';
            
            // Use URL parameter if available, otherwise use saved preference
            const currentView = viewParam || savedView;
            
            // On mobile, force list view
            if (window.innerWidth <= 768) {
                setViewMode('list', false);
            } else {
                setViewMode(currentView, false);
            }
        }

        /**
         * Change view mode
         */
        function changeView(viewMode) {
            setViewMode(viewMode, true);
        }

        /**
         * Set view mode
         */
        function setViewMode(viewMode, updateUrl = true) {
            const listView = document.getElementById('listView');
            const gridView = document.getElementById('gridView');
            const listBtn = document.querySelector('.view-btn[data-view="list"]');
            const gridBtn = document.querySelector('.view-btn[data-view="grid"]');
            
            // On mobile, only allow list view
            if (window.innerWidth <= 768 && viewMode === 'grid') {
                viewMode = 'list';
            }
            
            // Update view display
            if (viewMode === 'grid') {
                listView.style.display = 'none';
                gridView.style.display = 'grid';
                listBtn.classList.remove('active');
                gridBtn.classList.add('active');
            } else {
                listView.style.display = 'block';
                gridView.style.display = 'none';
                listBtn.classList.add('active');
                gridBtn.classList.remove('active');
            }
            
            // Save preference
            localStorage.setItem('timetable_view_mode', viewMode);
            
            // Update URL if requested
            if (updateUrl) {
                const url = new URL(window.location);
                url.searchParams.set('view', viewMode);
                window.history.pushState({}, '', url);
            }
        }

        /**
         * Toggle filters visibility
         */
        function toggleFilters() {
            const filtersContent = document.getElementById('filtersContent');
            const toggleText = document.getElementById('filterToggleText');
            const isVisible = filtersContent.classList.contains('show');
            
            if (isVisible) {
                filtersContent.classList.remove('show');
                toggleText.textContent = 'Show Filters';
            } else {
                filtersContent.classList.add('show');
                toggleText.textContent = 'Hide Filters';
            }
            
            // Save preference
            localStorage.setItem('timetable_filters_visible', !isVisible);
        }

        /**
         * Initialize auto-submit for filter changes
         */
        function initializeAutoSubmit() {
            const form = document.getElementById('filtersForm');
            const autoSubmitElements = form.querySelectorAll('select[name="academic_year"], select[name="semester"]');
            
            autoSubmitElements.forEach(element => {
                element.addEventListener('change', function() {
                    // Show loading state
                    showLoading();
                    form.submit();
                });
            });
        }

        /**
         * Initialize search functionality
         */
        function initializeSearch() {
            const searchInput = document.querySelector('input[name="search"]');
            let searchTimeout;
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (this.value.length >= 3 || this.value.length === 0) {
                            document.getElementById('filtersForm').submit();
                        }
                    }, 500);
                });
            }
        }

        /**
         * Show loading state
         */
        function showLoading() {
            const statsBar = document.querySelector('.stats-bar .stats-info');
            if (statsBar) {
                statsBar.innerHTML = `
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <span>Loading...</span>
                    </div>
                `;
            }
        }

        /**
         * Refresh page with current filters
         */
        function refreshPage() {
            showLoading();
            window.location.reload();
        }

        /**
         * Export current timetable view
         */
        function exportTimetables() {
            const url = new URL(window.location);
            url.searchParams.set('export', 'pdf');
            window.open(url.toString(), '_blank');
        }

        /**
         * Handle window resize for responsive behavior
         */
        window.addEventListener('resize', function() {
            // Force list view on mobile
            if (window.innerWidth <= 768) {
                setViewMode('list', false);
            }
        });

        /**
         * Initialize filters visibility from saved preference
         */
        document.addEventListener('DOMContentLoaded', function() {
            const filtersVisible = localStorage.getItem('timetable_filters_visible');
            if (filtersVisible === 'true') {
                toggleFilters();
            }
        });

        /**
         * Theme toggle functionality
         */
        function toggleTheme() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update icon
            const themeIcon = document.querySelector('#themeToggle i');
            if (themeIcon) {
                themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        /**
         * Show class details modal for grid view
         */
        function showClassDetailsModal(classData) {
            const modalContent = `
                <div class="modal fade" id="classDetailsModal" tabindex="-1" aria-labelledby="classDetailsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                                <h5 class="modal-title" id="classDetailsModalLabel">
                                    <i class="fas fa-info-circle"></i> Timetable Details
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <h6 style="color: var(--primary-color); margin-bottom: 1rem;">
                                            <i class="fas fa-book"></i> Subject Information
                                        </h6>
                                        <div class="mb-3">
                                            <strong>Subject:</strong><br>
                                            <span style="color: var(--text-secondary);">${classData.subject_code} - ${classData.subject_name}</span>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Type:</strong><br>
                                            <span class="subject-type ${classData.subject_type}">${classData.subject_type.charAt(0).toUpperCase() + classData.subject_type.slice(1)}</span>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Credits:</strong><br>
                                            <span style="color: var(--text-secondary);">${classData.credits} Credit${classData.credits > 1 ? 's' : ''}</span>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Department:</strong><br>
                                            <span style="color: var(--text-secondary);">${classData.subject_department}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 style="color: var(--primary-color); margin-bottom: 1rem;">
                                            <i class="fas fa-calendar"></i> Schedule Information
                                        </h6>
                                        <div class="mb-3">
                                            <strong>Day & Time:</strong><br>
                                            <span class="day-badge ${classData.day_of_week}">${classData.day_of_week}</span><br>
                                            <span style="color: var(--text-secondary);">${formatModalTime(classData.start_time)} - ${formatModalTime(classData.end_time)}</span>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Faculty:</strong><br>
                                            <span style="color: var(--text-secondary);">${classData.faculty_name}</span><br>
                                            <small>(${classData.employee_id})</small>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Classroom:</strong><br>
                                            <span style="color: var(--text-secondary);">${classData.room_number} - ${classData.building}</span><br>
                                            <small>Capacity: ${classData.room_capacity} students</small>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Section:</strong><br>
                                            <span style="color: var(--text-secondary);">Section ${classData.section}</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <h6 style="color: var(--primary-color); margin-bottom: 1rem;">
                                            <i class="fas fa-users"></i> Enrollment Information
                                        </h6>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <div style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; text-align: center;">
                                                    <div style="font-size: 1.5rem; font-weight: 600; color: var(--primary-color);">${classData.enrolled_students}</div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Enrolled</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; text-align: center;">
                                                    <div style="font-size: 1.5rem; font-weight: 600; color: var(--success-color);">${classData.room_capacity - classData.enrolled_students}</div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Available</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; text-align: center;">
                                                    <div style="font-size: 1.5rem; font-weight: 600; color: var(--warning-color);">${Math.round((classData.enrolled_students / classData.room_capacity) * 100)}%</div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Utilization</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px; text-align: center;">
                                                    <div style="font-size: 1.5rem; font-weight: 600; color: var(--info-color);">${classData.semester}</div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Semester</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    ${classData.notes ? `
                                    <div class="col-12">
                                        <h6 style="color: var(--primary-color); margin-bottom: 1rem;">
                                            <i class="fas fa-sticky-note"></i> Notes
                                        </h6>
                                        <div style="background: var(--bg-tertiary); padding: 1rem; border-radius: 8px;">
                                            ${classData.notes}
                                        </div>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                                <button type="button" class="btn-action btn-outline" data-bs-dismiss="modal">Close</button>
                                <a href="edit.php?id=${classData.timetable_id}" class="btn-action btn-warning">
                                    <i class="fas fa-edit"></i> Edit Entry
                                </a>
                                <a href="delete.php?id=${classData.timetable_id}" class="btn-action btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this timetable entry?')">
                                    <i class="fas fa-trash"></i> Delete Entry
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('classDetailsModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalContent);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('classDetailsModal'));
            modal.show();
        }

        /**
         * Format time for modal display
         */
        function formatModalTime(timeString) {
            const time = new Date('1970-01-01T' + timeString);
            return time.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }

        /**
         * Quick filter shortcuts
         */
        function quickFilter(type, value) {
            const form = document.getElementById('filtersForm');
            const input = form.querySelector(`[name="${type}"]`);
            
            if (input) {
                input.value = value;
                form.submit();
            }
        }

        /**
         * Print current view
         */
        function printTimetables() {
            window.print();
        }

        // Make functions available globally
        window.changeView = changeView;
        window.toggleFilters = toggleFilters;
        window.refreshPage = refreshPage;
        window.exportTimetables = exportTimetables;
        window.toggleTheme = toggleTheme;
        window.quickFilter = quickFilter;
        window.printTimetables = printTimetables;
        window.showClassDetailsModal = showClassDetailsModal;
        window.formatModalTime = formatModalTime;

        // Enhanced view switching with grid support
        function changeView(viewMode) {
            setViewMode(viewMode, true);
        }

        function setViewMode(viewMode, updateUrl = true) {
            const listView = document.getElementById('listView');
            const gridView = document.getElementById('gridView');
            const listBtn = document.querySelector('.view-btn[data-view="list"]');
            const gridBtn = document.querySelector('.view-btn[data-view="grid"]');
            
            // On mobile, only allow list view
            if (window.innerWidth <= 768 && viewMode === 'grid') {
                viewMode = 'list';
            }
            
            // Update view display
            if (viewMode === 'grid') {
                if (listView) listView.style.display = 'none';
                if (gridView) gridView.style.display = 'grid';
                if (listBtn) listBtn.classList.remove('active');
                if (gridBtn) gridBtn.classList.add('active');
            } else {
                if (listView) listView.style.display = 'block';
                if (gridView) gridView.style.display = 'none';
                if (listBtn) listBtn.classList.add('active');
                if (gridBtn) gridBtn.classList.remove('active');
            }
            
            // Save preference
            localStorage.setItem('timetable_view_mode', viewMode);
            
            // Update URL if requested
            if (updateUrl) {
                const url = new URL(window.location);
                url.searchParams.set('view', viewMode);
                window.history.pushState({}, '', url);
            }
        }

        // Initialize view mode on load
        document.addEventListener('DOMContentLoaded', function() {
            // Handle responsive view switching
            function checkScreenSize() {
                if (window.innerWidth <= 768) {
                    setViewMode('list', false);
                } else {
                    const urlParams = new URLSearchParams(window.location.search);
                    const viewParam = urlParams.get('view');
                    const savedView = localStorage.getItem('timetable_view_mode') || 'list';
                    setViewMode(viewParam || savedView, false);
                }
            }

            // Check on load and resize
            checkScreenSize();
            window.addEventListener('resize', checkScreenSize);
        });

        // Print styles
        const printStyles = `
            <style media="print">
                .sidebar, .navbar, .filters-section, .pagination-container, 
                .item-actions, .view-controls, .btn-action { display: none !important; }
                .main-content { margin-left: 0 !important; padding: 1rem !important; }
                .timetable-item { page-break-inside: avoid; }
                .glass-card { background: white !important; border: 1px solid #000 !important; }
                .page-header { position: static !important; }
                body { background: white !important; color: black !important; }
                .timetable-grid { 
                    background: white !important;
                    border: 2px solid #000 !important;
                }
                .schedule-grid-header, .time-slot {
                    background: #f0f0f0 !important;
                    color: #000 !important;
                    border: 1px solid #ccc !important;
                }
                .schedule-cell {
                    border: 1px solid #ddd !important;
                }
                .class-item {
                    background: #f8f9fa !important;
                    color: #000 !important;
                    border: 1px solid #ccc !important;
                    box-shadow: none !important;
                }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', printStyles);
    </script>
</body>
</html>