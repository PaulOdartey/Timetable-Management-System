<?php
/**
 * Student Subjects Page
 * Timetable Management System
 * 
 * Complete subjects view for students
 * Shows all enrolled subjects with details and faculty information
 */

// Start session and security checks
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Ensure user is logged in and has student role
User::requireLogin();
User::requireRole('student');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables
$subjectsData = [
    'student_info' => [],
    'enrolled_subjects' => [],
    'subjects_summary' => [],
    'subjects_by_semester' => [],
    'available_subjects' => []
];

// Get current academic year and semester
$currentYear = '2025-2026';
$currentSemester = 1;

try {
    // Get student info
    $studentInfo = $db->fetchRow("
        SELECT s.*, u.email, u.username
        FROM students s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.user_id = ?
    ", [$userId]);

    if ($studentInfo) {
        $subjectsData['student_info'] = $studentInfo;
        $studentId = $studentInfo['student_id'];
    } else {
        throw new Exception('Student information not found');
    }

    // Get enrolled subjects with detailed information
    $enrolledSubjects = $db->fetchAll("
        SELECT 
            s.*,
            e.enrollment_id,
            e.section,
            e.enrollment_date,
            e.status as enrollment_status,
            d.department_name,
            d.department_code,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
            f.employee_id,
            f.designation,
            c.room_number,
            c.building,
            c.capacity,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.slot_name,
            COUNT(DISTINCT e2.student_id) as classmates_count,
            GROUP_CONCAT(DISTINCT CONCAT(ts.day_of_week, ' ', 
                DATE_FORMAT(ts.start_time, '%h:%i %p'), '-', 
                DATE_FORMAT(ts.end_time, '%h:%i %p'), ' (', c.room_number, ')') 
                ORDER BY ts.day_of_week, ts.start_time SEPARATOR '; ') as class_schedule
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        LEFT JOIN departments d ON s.department_id = d.department_id
        LEFT JOIN timetables t ON s.subject_id = t.subject_id 
            AND t.section = e.section 
            AND (t.academic_year = e.academic_year OR 
                 (t.academic_year = '2025-2026' AND e.academic_year IN ('2025-26', '2025-2026')) OR
                 (t.academic_year = '2025-26' AND e.academic_year IN ('2025-26', '2025-2026')))
            AND t.semester = e.semester 
            AND t.is_active = 1
        LEFT JOIN faculty f ON t.faculty_id = f.faculty_id
        LEFT JOIN classrooms c ON t.classroom_id = c.classroom_id
        LEFT JOIN time_slots ts ON t.slot_id = ts.slot_id
        LEFT JOIN enrollments e2 ON s.subject_id = e2.subject_id 
            AND e2.section = e.section 
            AND e2.semester = e.semester 
            AND e2.academic_year = e.academic_year 
            AND e2.status = 'enrolled'
        WHERE e.student_id = ? 
            AND (e.academic_year = '2025-2026' OR e.academic_year = '2025-26')
            AND e.semester = ? 
            AND e.status = 'enrolled'
            AND s.is_active = 1
        GROUP BY s.subject_id, e.enrollment_id
        ORDER BY s.semester ASC, s.year_level ASC, s.subject_code ASC
    ", [$studentId, $currentSemester]);

    $subjectsData['enrolled_subjects'] = $enrolledSubjects;

    // Group subjects by semester for better organization
    $subjectsBySemester = [];
    foreach ($enrolledSubjects as $subject) {
        $sem = $subject['semester'];
        if (!isset($subjectsBySemester[$sem])) {
            $subjectsBySemester[$sem] = [];
        }
        $subjectsBySemester[$sem][] = $subject;
    }
    $subjectsData['subjects_by_semester'] = $subjectsBySemester;

    // Calculate summary statistics
    $totalSubjects = count($enrolledSubjects);
    $totalCredits = array_sum(array_column($enrolledSubjects, 'credits'));
    $totalDurationHours = array_sum(array_column($enrolledSubjects, 'duration_hours'));
    $completedSubjects = count(array_filter($enrolledSubjects, function($s) { 
        return $s['enrollment_status'] === 'completed'; 
    }));
    
    // Get unique semesters enrolled
    $semestersEnrolled = count(array_unique(array_column($enrolledSubjects, 'semester')));
    
    // Get departments enrolled in
    $departmentsEnrolled = count(array_unique(array_filter(array_column($enrolledSubjects, 'department_name'))));

    // Calculate average classmates
    $avgClassmates = $totalSubjects > 0 ? round(array_sum(array_column($enrolledSubjects, 'classmates_count')) / $totalSubjects) : 0;

    $subjectsData['subjects_summary'] = [
        'total_subjects' => $totalSubjects,
        'total_credits' => $totalCredits,
        'total_hours' => $totalDurationHours,
        'completed_subjects' => $completedSubjects,
        'semesters_enrolled' => $semestersEnrolled,
        'departments_enrolled' => $departmentsEnrolled,
        'avg_classmates' => $avgClassmates
    ];

    // Get available subjects for enrollment (optional - for future enhancement)
    $availableSubjects = $db->fetchAll("
        SELECT s.*, d.department_name,
               COUNT(e.enrollment_id) as current_enrollment
        FROM subjects s
        LEFT JOIN departments d ON s.department_id = d.department_id
        LEFT JOIN enrollments e ON s.subject_id = e.subject_id 
            AND e.status = 'enrolled'
            AND (e.academic_year = '2025-2026' OR e.academic_year = '2025-26')
            AND e.semester = ?
        WHERE s.is_active = 1
            AND s.semester = ?
            AND s.year_level <= ?
            AND s.subject_id NOT IN (
                SELECT subject_id FROM enrollments 
                WHERE student_id = ? 
                AND status IN ('enrolled', 'completed')
                AND (academic_year = '2025-2026' OR academic_year = '2025-26')
            )
        GROUP BY s.subject_id
        ORDER BY s.subject_code
        LIMIT 10
    ", [$currentSemester, $currentSemester, $studentInfo['year_of_study'], $studentId]);

    $subjectsData['available_subjects'] = $availableSubjects;

} catch (Exception $e) {
    error_log("Student Subjects Error: " . $e->getMessage());
    $error_message = "Unable to load subjects data. Please try again later.";
}

// Set page title
$pageTitle = "My Subjects";
$currentPage = "subjects";

// Helper function to get subject type badge color
function getSubjectTypeBadge($type) {
    $badges = [
        'theory' => 'bg-primary',
        'practical' => 'bg-success', 
        'lab' => 'bg-warning'
    ];
    return $badges[$type] ?? 'bg-secondary';
}

// Helper function to get enrollment status badge
function getEnrollmentStatusBadge($status) {
    $badges = [
        'enrolled' => 'bg-success',
        'completed' => 'bg-info',
        'dropped' => 'bg-danger',
        'failed' => 'bg-dark'
    ];
    return $badges[$status] ?? 'bg-secondary';
}

// Helper function to get year level suffix
function getYearSuffix($year) {
    $suffixes = [1 => 'st', 2 => 'nd', 3 => 'rd'];
    return $suffixes[$year] ?? 'th';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $pageTitle ?> - <?= SYSTEM_NAME ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-tertiary: #94a3b8;
            --border-color: #e2e8f0;
            --accent-color: #667eea;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --primary-color: #667eea;
            --primary-color-alpha: rgba(102, 126, 234, 0.1);
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --navbar-height: 64px;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --bg-tertiary: #404040;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --text-tertiary: #808080;
            --border-color: #404040;
            --glass-bg: rgba(0, 0, 0, 0.25);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* Sidebar collapsed state */
        body.sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            margin-top: 1rem;
        }

        .header-card {
            padding: 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 0;
        }

        .btn-action {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            border: none;
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

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
        }

        /* Summary Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            padding: 1.5rem;
            text-align: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .stat-icon.subjects {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.credits {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon.hours {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon.classmates {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Subjects Container */
        .subjects-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            overflow-x: auto;
        }

        .subjects-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .subjects-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .view-toggles {
            display: flex;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.25rem;
        }

        .toggle-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: none;
            color: var(--text-secondary);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .toggle-btn.active {
            background: var(--primary-color);
            color: white;
        }

        /* Subjects Grid */
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .subject-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .subject-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .subject-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
        }

        .subject-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .subject-code {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .subject-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.4;
        }

        .subject-badges {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .subject-type-badge, .enrollment-status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            text-align: center;
        }

        .subject-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .detail-icon {
            width: 16px;
            height: 16px;
            color: var(--primary-color);
        }

        .faculty-info {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .faculty-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .faculty-designation {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .subject-stats {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .stat-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.125rem;
        }

        .stat-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .schedule-info {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 12px;
            margin-top: 1rem;
        }

        .schedule-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .schedule-list {
            font-size: 0.75rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Subjects List View */
        .subjects-list {
            display: none;
        }

        .subjects-list.active {
            display: block;
        }

        .semester-group {
            margin-bottom: 2rem;
        }

        .semester-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
        }

        .list-subject {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .list-subject:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .list-subject-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .list-subject-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .list-subject-code {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .list-subject-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .list-subject-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .list-subject-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        /* Empty State */
        .empty-subjects {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-subjects svg {
            width: 64px;
            }

            .subject-badges {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }

            .subject-type-badge, .enrollment-status-badge {
                padding: 0.25rem 0.75rem;
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                text-align: center;
            }

            .subject-details {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
                margin-bottom: 1rem;
            }

            .detail-item {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--text-secondary);
                font-size: 0.875rem;
            }

            .detail-icon {
                width: 16px;
                height: 16px;
                color: var(--primary-color);
            }

            .faculty-info {
                background: var(--bg-secondary);
                padding: 1rem;
                border-radius: 12px;
                margin-bottom: 1rem;
            }

            .faculty-name {
                font-size: 0.875rem;
                font-weight: 600;
                color: var(--text-primary);
                margin-bottom: 0.25rem;
            }

            .faculty-designation {
                font-size: 0.75rem;
                color: var(--text-secondary);
            }

            .subject-stats {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding-top: 1rem;
                border-top: 1px solid var(--border-color);
            }

            .stat-group {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .stat-item {
                text-align: center;
            }

            .stat-value {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--primary-color);
                margin-bottom: 0.125rem;
            }

            .stat-text {
                font-size: 0.75rem;
                color: var(--text-secondary);
                font-weight: 500;
            }

            .schedule-info {
                background: var(--bg-secondary);
                padding: 1rem;
                border-radius: 12px;
                margin-top: 1rem;
            }

            .schedule-title {
                font-size: 0.875rem;
                font-weight: 600;
                color: var(--text-primary);
                margin-bottom: 0.5rem;
            }

            .schedule-list {
                font-size: 0.75rem;
                color: var(--text-secondary);
                line-height: 1.4;
            }

            /* Subjects List View */
            .subjects-list {
                display: none;
            }

            .subjects-list.active {
                display: block;
            }

            .semester-group {
                margin-bottom: 2rem;
            }

            .semester-title {
                font-size: 1.25rem;
                font-weight: 600;
                color: var(--text-primary);
                margin-bottom: 1rem;
                padding: 1rem;
                background: var(--bg-secondary);
                border-radius: 12px;
                border-left: 4px solid var(--primary-color);
            }

            .list-subject {
                background: var(--bg-primary);
                border: 1px solid var(--border-color);
                border-radius: 12px;
                padding: 1.5rem;
                margin-bottom: 1rem;
                transition: all 0.3s ease;
            }

            .list-subject:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-md);
            }

            .list-subject-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 1rem;
                flex-wrap: wrap;
                gap: 1rem;
            }

            .list-subject-title {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .list-subject-code {
                font-size: 1.125rem;
                font-weight: 700;
                color: var(--primary-color);
            }

            .list-subject-name {
                font-size: 1rem;
                font-weight: 600;
                color: var(--text-primary);
            }

            .list-subject-meta {
                display: flex;
                align-items: center;
                gap: 1rem;
                flex-wrap: wrap;
            }

            .list-subject-details {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }

            /* Empty State */
            .empty-subjects {
                text-align: center;
                padding: 4rem 2rem;
                color: var(--text-secondary);
            }

            .empty-subjects svg {
                width: 64px;
                height: 64px;
                margin-bottom: 1rem;
                opacity: 0.5;
            }

            /* Enhanced Responsive Design */
            @media screen and (max-width: 1024px) {
                :root {
                    --sidebar-width: 0px;
                    --sidebar-collapsed-width: 0px;
                }
                
                body {
                    overflow-x: hidden;
                }
                
                .main-content {
                    margin-left: 0;
                    padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-content h1 {
                font-size: 2rem;
            }

            .subjects-container {
                padding: 1rem;
            }
        }

        @media screen and (max-width: 768px) {
            * {
                box-sizing: border-box;
            }
            
            body {
                overflow-x: hidden;
                width: 100vw;
            }
            
            .main-content {
                width: 100%;
                max-width: 100vw;
                padding: 0.75rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .subjects-grid {
                display: none !important;
            }

            .subjects-list {
                display: block !important;
            }

            .toggle-btn {
                display: none;
            }

            .subject-details {
                grid-template-columns: 1fr;
            }

            .list-subject-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .list-subject-details {
                grid-template-columns: 1fr;
            }

            /* Stronger, more visible borders on mobile list view cards */
            .list-subject {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .list-subject {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }
        }

        @media screen and (max-width: 480px) {
            body {
                font-size: 14px;
            }
            
            .main-content {
                padding: 0.5rem;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .subjects-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading States */
        .loading-skeleton {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.3) 25%, rgba(255, 255, 255, 0.5) 50%, rgba(255, 255, 255, 0.3) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

     
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>


    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-card glass-card fade-in">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">📚 My Subjects</h1>
                    </div>
                    <div class="d-flex gap-2">
                    
                        <a href="timetable.php" class="btn-action btn-outline">
                            📅 View Schedule
                        </a>
                        <a href="export.php" class="btn-action btn-primary">
                            📄 Export List
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <?php if (!empty($subjectsData['subjects_summary'])): ?>
            <div class="stats-grid">
                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon subjects">📚</div>
                    <div class="stat-number"><?= $subjectsData['subjects_summary']['total_subjects'] ?></div>
                    <div class="stat-label">Enrolled Subjects</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon credits">⭐</div>
                    <div class="stat-number"><?= $subjectsData['subjects_summary']['total_credits'] ?></div>
                    <div class="stat-label">Total Credits</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon hours">⏰</div>
                    <div class="stat-number"><?= $subjectsData['subjects_summary']['total_hours'] ?></div>
                    <div class="stat-label">Study Hours</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon classmates">👥</div>
                    <div class="stat-number"><?= $subjectsData['subjects_summary']['avg_classmates'] ?></div>
                    <div class="stat-label">Avg Classmates</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Subjects Display -->
        <div class="subjects-container">
            <div class="subjects-header">
                <h2 class="subjects-title">Enrolled Subjects</h2>
                <div class="view-toggles">
                    <button class="toggle-btn active" onclick="switchView('grid')">Grid View</button>
                    <button class="toggle-btn" onclick="switchView('list')">List View</button>
                </div>
            </div>

            <?php if (!empty($subjectsData['enrolled_subjects'])): ?>
                <!-- Grid View -->
                <div class="subjects-grid" id="gridView">
                    <?php foreach ($subjectsData['enrolled_subjects'] as $subject): ?>
                        <div class="subject-card" onclick="showSubjectDetails(<?= htmlspecialchars(json_encode($subject)) ?>)">
                            <div class="subject-header">
                                <div>
                                    <div class="subject-code"><?= htmlspecialchars($subject['subject_code']) ?></div>
                                    <div class="subject-name"><?= htmlspecialchars($subject['subject_name']) ?></div>
                                </div>
                                <div class="subject-badges">
                                    <span class="subject-type-badge <?= getSubjectTypeBadge($subject['type']) ?>">
                                        <?= strtoupper($subject['type']) ?>
                                    </span>
                                    <span class="enrollment-status-badge <?= getEnrollmentStatusBadge($subject['enrollment_status']) ?>">
                                        <?= strtoupper($subject['enrollment_status']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="subject-details">
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    <?= $subject['credits'] ?> Credits
                                </div>
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M8 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M16 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                        <path d="M3 10H21" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    Semester <?= $subject['semester'] ?>
                                </div>
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M22 12H18L15 21L9 3L6 12H2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    Year <?= $subject['year_level'] ?><?= getYearSuffix($subject['year_level']) ?>
                                </div>
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M19 21V5C19 3.89543 18.1046 3 17 3H7C5.89543 3 5 3.89543 5 5V21L12 18L19 21Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    Section <?= $subject['section'] ?>
                                </div>
                            </div>

                            <?php if ($subject['faculty_name']): ?>
                                <div class="faculty-info">
                                    <div class="faculty-name">👨‍🏫 <?= htmlspecialchars($subject['faculty_name']) ?></div>
                                    <div class="faculty-designation"><?= htmlspecialchars($subject['designation'] ?? 'Faculty') ?></div>
                                </div>
                            <?php endif; ?>

                            <div class="subject-stats">
                                <div class="stat-group">
                                    <div class="stat-item">
                                        <div class="stat-value"><?= $subject['duration_hours'] ?></div>
                                        <div class="stat-text">Hours</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?= $subject['classmates_count'] - 1 ?></div>
                                        <div class="stat-text">Classmates</div>
                                    </div>
                                    <?php if ($subject['capacity']): ?>
                                        <div class="stat-item">
                                            <div class="stat-value"><?= $subject['capacity'] ?></div>
                                            <div class="stat-text">Capacity</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($subject['class_schedule']): ?>
                                <div class="schedule-info">
                                    <div class="schedule-title">📅 Class Schedule</div>
                                    <div class="schedule-list"><?= htmlspecialchars($subject['class_schedule']) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- List View -->
                <div class="subjects-list" id="listView">
                    <?php if (!empty($subjectsData['subjects_by_semester'])): ?>
                        <?php foreach ($subjectsData['subjects_by_semester'] as $semester => $subjects): ?>
                            <div class="semester-group">
                                <h3 class="semester-title">📚 Semester <?= $semester ?> Subjects</h3>
                                <?php foreach ($subjects as $subject): ?>
                                    <div class="list-subject">
                                        <div class="list-subject-header">
                                            <div class="list-subject-title">
                                                <div class="list-subject-code"><?= htmlspecialchars($subject['subject_code']) ?></div>
                                                <div class="list-subject-name"><?= htmlspecialchars($subject['subject_name']) ?></div>
                                            </div>
                                            <div class="list-subject-meta">
                                                <span class="subject-type-badge <?= getSubjectTypeBadge($subject['type']) ?>">
                                                    <?= strtoupper($subject['type']) ?>
                                                </span>
                                                <span class="enrollment-status-badge <?= getEnrollmentStatusBadge($subject['enrollment_status']) ?>">
                                                    <?= strtoupper($subject['enrollment_status']) ?>
                                                </span>
                                                <span class="badge bg-info">
                                                    Year <?= $subject['year_level'] ?><?= getYearSuffix($subject['year_level']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="list-subject-details">
                                            <div class="detail-item">
                                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                <?= $subject['credits'] ?> Credits
                                            </div>
                                            <div class="detail-item">
                                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M19 21V5C19 3.89543 18.1046 3 17 3H7C5.89543 3 5 3.89543 5 5V21L12 18L19 21Z" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                Section <?= $subject['section'] ?>
                                            </div>
                                            <div class="detail-item">
                                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2"/>
                                                    <path d="M9 11C11.2091 11 13 9.20914 13 7C13 4.79086 11.2091 3 9 3C6.79086 3 5 4.79086 5 7C5 9.20914 7.79086 11 9 11Z" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                <?= $subject['classmates_count'] - 1 ?> Classmates
                                            </div>
                                            <?php if ($subject['faculty_name']): ?>
                                                <div class="detail-item">
                                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2"/>
                                                        <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                                                    </svg>
                                                    Prof. <?= htmlspecialchars($subject['faculty_name']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="detail-item">
                                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                <?= $subject['duration_hours'] ?> Duration Hours
                                            </div>
                                            <div class="detail-item">
                                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M8 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    <path d="M16 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                                    <path d="M3 10H21" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                Enrolled: <?= date('M j, Y', strtotime($subject['enrollment_date'])) ?>
                                            </div>
                                        </div>
                                        <?php if ($subject['class_schedule']): ?>
                                            <div class="schedule-info">
                                                <div class="schedule-title">📅 Class Schedule</div>
                                                <div class="schedule-list"><?= htmlspecialchars($subject['class_schedule']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($subject['description']): ?>
                                            <div class="schedule-info">
                                                <div class="schedule-title">📝 Description</div>
                                                <div class="schedule-list"><?= htmlspecialchars($subject['description']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($subjectsData['enrolled_subjects'] as $subject): ?>
                            <div class="list-subject">
                                <div class="list-subject-header">
                                    <div class="list-subject-title">
                                        <div class="list-subject-code"><?= htmlspecialchars($subject['subject_code']) ?></div>
                                        <div class="list-subject-name"><?= htmlspecialchars($subject['subject_name']) ?></div>
                                    </div>
                                    <div class="list-subject-meta">
                                        <span class="subject-type-badge <?= getSubjectTypeBadge($subject['type']) ?>">
                                            <?= strtoupper($subject['type']) ?>
                                        </span>
                                        <span class="enrollment-status-badge <?= getEnrollmentStatusBadge($subject['enrollment_status']) ?>">
                                            <?= strtoupper($subject['enrollment_status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="list-subject-details">
                                    <div class="detail-item">
                                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12 C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        <?= $subject['credits'] ?> Credits
                                    </div>
                                    <div class="detail-item">
                                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2"/>
                                            <path d="M9 11C11.2091 11 13 9.20914 13 7C13 4.79086 11.2091 3 9 3C6.79086 3 5 4.79086 5 7C5 9.20914 7.79086 11 9 11Z" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        <?= $subject['classmates_count'] - 1 ?> Classmates
                                    </div>
                                    <div class="detail-item">
                                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M3 21H21M3 10H21M3 7L12 3L21 7V20H3V7Z" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        Section <?= $subject['section'] ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-subjects">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 19.5C4 18.837 4.26339 18.2011 4.73223 17.7322C5.20107 17.2634 5.83696 17 6.5 17H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M6.5 2H20V22H6.5C5.83696 22 5.20107 21.7366 4.73223 21.2678C4.26339 20.7989 4 20.163 4 19.5V4.5C4 3.83696 4.26339 3.20107 4.73223 2.73223C5.20107 2.26339 5.83696 2 6.5 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 7H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M9 11H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <h3>No Subjects Enrolled</h3>
                    <p>You are not enrolled in any subjects for this semester.</p>
                    <p style="margin-top: 0.5rem;">Contact the admin for enrollment assistance.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Subject Details Modal -->
    <div class="modal fade" id="subjectDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" style="color: var(--text-primary);">Subject Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody" style="color: var(--text-primary);">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="viewClassmatesBtn">View Classmates</a>
                    <a href="#" class="btn btn-info" id="viewScheduleBtn">View Schedule</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize subjects functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Apply theme
            applyCurrentTheme();
            
            // Initialize tooltips
            initializeTooltips();
            
            // Handle responsive view switching
            handleResponsiveView();

            // Listen for sidebar toggle events
            handleSidebarToggle();
        });

        // Enhanced sidebar toggle handling with proper sticky header support
        function handleSidebarToggle() {
            // Listen for sidebar collapse/expand events
            window.addEventListener('sidebarToggled', function(e) {
                const body = document.body;
                
                if (e.detail && e.detail.collapsed) {
                    body.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                }
            });

            // Check for existing sidebar state on load
            const sidebar = document.querySelector('.tms-sidebar');
            if (sidebar) {
                // Check if sidebar is collapsed
                if (sidebar.classList.contains('collapsed')) {
                    document.body.classList.add('sidebar-collapsed');
                }
                
                // For mobile, always treat as collapsed
                if (window.innerWidth <= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                }
            }

            // Handle window resize for responsive behavior
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 1024) {
                    // Mobile: always collapsed state for sticky header
                    document.body.classList.add('sidebar-collapsed');
                } else {
                    // Desktop: check actual sidebar state
                    const sidebar = document.querySelector('.tms-sidebar');
                    if (sidebar) {
                        if (sidebar.classList.contains('collapsed')) {
                            document.body.classList.add('sidebar-collapsed');
                        } else {
                            document.body.classList.remove('sidebar-collapsed');
                        }
                    }
                }
            });
        }

        // View switching functionality
        function switchView(viewType) {
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const toggleBtns = document.querySelectorAll('.toggle-btn');
            
            // Remove active class from all buttons
            toggleBtns.forEach(btn => btn.classList.remove('active'));

            if (viewType === 'grid') {
                gridView.style.display = 'grid';
                listView.style.display = 'none';
                document.querySelector('.toggle-btn[onclick*="grid"]').classList.add('active');
                
            } else {
                gridView.style.display = 'none';
                listView.style.display = 'block';
                document.querySelector('.toggle-btn[onclick*="list"]').classList.add('active');
                
            }
        }

        // Show subject details modal
        function showSubjectDetails(subjectData) {
            const modal = new bootstrap.Modal(document.getElementById('subjectDetailsModal'));
            const modalBody = document.getElementById('modalBody');
            const viewClassmatesBtn = document.getElementById('viewClassmatesBtn');
            const viewScheduleBtn = document.getElementById('viewScheduleBtn');

            // Format subject details
            modalBody.innerHTML = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">📚 Subject Information</h6>
                        <div class="mb-3">
                            <strong>Subject Code:</strong><br>
                            <span style="color: var(--text-secondary);">${subjectData.subject_code}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Subject Name:</strong><br>
                            <span style="color: var(--text-secondary);">${subjectData.subject_name}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Type:</strong><br>
                            <span class="badge ${getSubjectTypeBadge(subjectData.type)}">${subjectData.type.toUpperCase()}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Credits:</strong><br>
                            <span style="color: var(--text-secondary);">${subjectData.credits} Credits</span>
                        </div>
                        <div class="mb-3">
                            <strong>Duration:</strong><br>
                            <span style="color: var(--text-secondary);">${subjectData.duration_hours} Hours</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">🎓 Enrollment Information</h6>
                        <div class="mb-3">
                            <strong>Enrollment Status:</strong><br>
                            <span class="badge ${getEnrollmentStatusBadge(subjectData.enrollment_status)}">${subjectData.enrollment_status.toUpperCase()}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Section:</strong><br>
                            <span style="color: var(--text-secondary);">Section ${subjectData.section}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Semester:</strong><br>
                            <span style="color: var(--text-secondary);">Semester ${subjectData.semester}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Year Level:</strong><br>
                            <span style="color: var(--text-secondary);">Year ${subjectData.year_level}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Enrollment Date:</strong><br>
                            <span style="color: var(--text-secondary);">${new Date(subjectData.enrollment_date).toLocaleDateString()}</span>
                        </div>
                    </div>
                    ${subjectData.faculty_name ? `
                    <div class="col-12">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">👨‍🏫 Faculty Information</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">${subjectData.faculty_name}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">${subjectData.designation || 'Faculty'}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">${subjectData.employee_id || 'N/A'}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Employee ID</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.125rem; font-weight: 600; color: var(--text-primary);">${subjectData.room_number || 'TBA'}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Classroom</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    <div class="col-12">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">📊 Class Statistics</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color);">${subjectData.classmates_count}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Total Students</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-color);">${subjectData.classmates_count - 1}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Your Classmates</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--warning-color);">${subjectData.capacity || 'N/A'}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Class Capacity</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ${subjectData.class_schedule ? `
                    <div class="col-12">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">📅 Class Schedule</h6>
                        <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px;">
                            <div style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;">
                                ${subjectData.class_schedule.split(';').map(schedule => `<div>• ${schedule.trim()}</div>`).join('')}
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    ${subjectData.description ? `
                    <div class="col-12">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">📝 Description</h6>
                        <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px;">
                            <div style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;">
                                ${subjectData.description}
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    ${subjectData.prerequisites ? `
                    <div class="col-12">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">📋 Prerequisites</h6>
                        <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px;">
                            <div style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;">
                                ${subjectData.prerequisites}
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;

            // Set up action buttons
            viewClassmatesBtn.href = `classmates.php?subject=${subjectData.subject_id}&section=${subjectData.section}`;
            viewScheduleBtn.href = `schedule.php#subject-${subjectData.subject_id}`;

            modal.show();
        }

        // Helper function for badge classes
        function getSubjectTypeBadge(type) {
            const badges = {
                'theory': 'bg-primary',
                'practical': 'bg-success', 
                'lab': 'bg-warning'
            };
            return badges[type] || 'bg-secondary';
        }

        // Helper function for enrollment status badge
        function getEnrollmentStatusBadge(status) {
            const badges = {
                'enrolled': 'bg-success',
                'completed': 'bg-info',
                'dropped': 'bg-danger',
                'failed': 'bg-dark'
            };
            return badges[status] || 'bg-secondary';
        }

        // Apply current theme
        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Initialize tooltips
        function initializeTooltips() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Handle responsive view switching
        function handleResponsiveView() {
            function checkScreenSize() {
                if (window.innerWidth <= 768) {
                    switchView('list');
                } else {
                    switchView('grid');
                }
            }

            // Check on load
            checkScreenSize();

            // Check on resize
            window.addEventListener('resize', checkScreenSize);
        }

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + P for Print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }

            // Ctrl/Cmd + E for Export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'export-subjects.php';
            }

            // G for Grid view
            if (e.key === 'g' && !e.ctrlKey && !e.metaKey) {
                switchView('grid');
            }

            // L for List view
            if (e.key === 'l' && !e.ctrlKey && !e.metaKey) {
                switchView('list');
            }

            // S for Schedule
            if (e.key === 's' && !e.ctrlKey && !e.metaKey) {
                window.location.href = 'schedule.php';
            }
        });

        // Listen for theme changes
        window.addEventListener('themeChanged', function(event) {
            applyCurrentTheme();
        });

        // Add smooth scrolling
        window.addEventListener('load', function() {
            document.documentElement.style.scrollBehavior = 'smooth';
        });

        // Enhanced hover effects for cards
        const cards = document.querySelectorAll('.glass-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Performance optimization: Lazy load subject details
        const subjectCards = document.querySelectorAll('.subject-card');
        subjectCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Search functionality
        function searchSubjects(query) {
            const subjects = document.querySelectorAll('.subject-card, .list-subject');
            query = query.toLowerCase();

            subjects.forEach(subject => {
                const code = subject.querySelector('.subject-code, .list-subject-code')?.textContent.toLowerCase() || '';
                const name = subject.querySelector('.subject-name, .list-subject-name')?.textContent.toLowerCase() || '';
                const visible = code.includes(query) || name.includes(query);
                
                subject.style.display = visible ? '' : 'none';
            });
        }

        // Add search functionality if search input exists
        const searchInput = document.querySelector('#subjectSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                searchSubjects(this.value);
            });
        }

        // Intersection Observer for animation
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all cards
        document.querySelectorAll('.stat-card, .subject-card, .list-subject').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Filter subjects by type
        function filterByType(type) {
            const subjects = document.querySelectorAll('.subject-card, .list-subject');
            
            subjects.forEach(subject => {
                const badge = subject.querySelector('.subject-type-badge');
                const subjectType = badge ? badge.textContent.toLowerCase() : '';
                const visible = type === 'all' || subjectType === type.toLowerCase();
                
                subject.style.display = visible ? '' : 'none';
            });
        }

        // Filter subjects by enrollment status
        function filterByStatus(status) {
            const subjects = document.querySelectorAll('.subject-card, .list-subject');
            
            subjects.forEach(subject => {
                const badge = subject.querySelector('.enrollment-status-badge');
                const enrollmentStatus = badge ? badge.textContent.toLowerCase() : '';
                const visible = status === 'all' || enrollmentStatus === status.toLowerCase();
                
                subject.style.display = visible ? '' : 'none';
            });
        }

        // Add filter event listeners if filter elements exist
        document.addEventListener('change', function(e) {
            if (e.target.id === 'typeFilter') {
                filterByType(e.target.value);
            } else if (e.target.id === 'statusFilter') {
                filterByStatus(e.target.value);
            }
        });
    </script>
</body>
</html>

<?php
/**
 * Helper function for formatting enrollment date
 * @param string $date
 * @return string
 */
function formatEnrollmentDate($date) {
    return date('M j, Y', strtotime($date));
}

/**
 * Helper function to get GPA calculation
 * @param array $subjects
 * @return float
 */
function calculateGPA($subjects) {
    $totalPoints = 0;
    $totalCredits = 0;
    
    foreach ($subjects as $subject) {
        if ($subject['enrollment_status'] === 'completed') {
            // This would require a grades table in a real system
            $totalPoints += $subject['credits'] * 4.0; // Assuming all A's for demo
            $totalCredits += $subject['credits'];
        }
    }
    
    return $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.0;
}

/**
 * Helper function to check enrollment eligibility
 * @param string $prerequisites
 * @param array $completedSubjects
 * @return bool
 */
function checkEnrollmentEligibility($prerequisites, $completedSubjects) {
    if (empty(trim($prerequisites))) return true;
    
    // This would require more complex logic in a real system
    // to parse prerequisites and check against completed subjects
    return true;
}
?>