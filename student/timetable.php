<?php
/**
 * Student Timetable Page
 * Timetable Management System
 * 
 * Complete weekly timetable view for students
 * Shows all enrolled classes in a grid format with sticky header
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
$scheduleData = [
    'student_info' => [],
    'weekly_schedule' => [],
    'schedule_summary' => [],
    'time_slots' => [],
    'enrollments' => []
];

// Get current academic year and semester - use consistent database format
$currentYear = '2025-2026';  // Always use full format for consistency
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
        $scheduleData['student_info'] = $studentInfo;
        $studentId = $studentInfo['student_id'];
    } else {
        throw new Exception('Student information not found');
    }

    // Get complete weekly schedule with academic year normalization
    $weeklySchedule = $db->fetchAll("
        SELECT 
            t.*,
            s.subject_code,
            s.subject_name,
            s.credits,
            s.type as subject_type,
            c.room_number,
            c.building,
            c.capacity,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.slot_name,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
            f.designation as faculty_designation,
            e.enrollment_date,
            e.status as enrollment_status
        FROM enrollments e
        JOIN timetables t ON e.subject_id = t.subject_id 
            AND e.section = t.section 
            AND e.semester = t.semester 
            AND (e.academic_year = t.academic_year OR 
                 (e.academic_year = '2025-2026' AND t.academic_year IN ('2025-26', '2025-2026')) OR
                 (e.academic_year = '2025-26' AND t.academic_year IN ('2025-26', '2025-2026')))
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN faculty f ON t.faculty_id = f.faculty_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE e.student_id = ? 
            AND (e.academic_year = ? OR e.academic_year = '2025-26')
            AND e.semester = ? 
            AND e.status = 'enrolled'
            AND t.is_active = 1
        ORDER BY ts.day_of_week, ts.start_time
    ", [$studentId, $currentYear, $currentSemester]);

    $scheduleData['weekly_schedule'] = $weeklySchedule;

    // Get all available time slots for the grid
    $timeSlots = $db->fetchAll("
        SELECT DISTINCT slot_id, day_of_week, start_time, end_time, slot_name
        FROM time_slots 
        WHERE is_active = 1 
        ORDER BY day_of_week, start_time
    ");
    $scheduleData['time_slots'] = $timeSlots;

    // Get student's current enrollments
    $enrollments = $db->fetchAll("
        SELECT e.*, s.subject_code, s.subject_name, s.credits, s.type,
               CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
               f.designation as faculty_designation
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        LEFT JOIN faculty_subjects fs ON s.subject_id = fs.subject_id AND fs.is_active = 1
        LEFT JOIN faculty f ON fs.faculty_id = f.faculty_id
        WHERE e.student_id = ? 
        AND e.status = 'enrolled'
        AND (e.academic_year = ? OR e.academic_year = '2025-26')
        AND e.semester = ?
        ORDER BY s.subject_code
    ", [$studentId, $currentYear, $currentSemester]);
    $scheduleData['enrollments'] = $enrollments;

    // Generate schedule summary
    $summary = [
        'total_classes' => count($weeklySchedule),
        'total_subjects' => count($enrollments),
        'total_credits' => array_sum(array_column($enrollments, 'credits')),
        'different_rooms' => count(array_unique(array_column($weeklySchedule, 'classroom_id'))),
        'weekly_hours' => 0
    ];

    // Calculate weekly hours
    foreach ($weeklySchedule as $class) {
        $start = new DateTime($class['start_time']);
        $end = new DateTime($class['end_time']);
        $diff = $end->diff($start);
        $summary['weekly_hours'] += ($diff->h + ($diff->i / 60));
    }

    $scheduleData['schedule_summary'] = $summary;

} catch (Exception $e) {
    error_log("Student Timetable Error: " . $e->getMessage());
    $error_message = "Unable to load timetable data. Please try again later.";
}

// Set page title
$pageTitle = "My Timetable";
$currentPage = "timetable";

// Surface handler errors via query string
if (isset($_GET['error']) && $_GET['error'] !== '') {
    $error_message = $_GET['error'];
}

// Helper function to organize schedule by day and time
function organizeScheduleGrid($schedule, $timeSlots) {
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $grid = [];
    
    // Initialize grid
    foreach ($days as $day) {
        $grid[$day] = [];
    }
    
    // Fill grid with classes
    foreach ($schedule as $class) {
        $day = $class['day_of_week'];
        $timeKey = $class['start_time'] . '-' . $class['end_time'];
        $grid[$day][$timeKey] = $class;
    }
    
    return $grid;
}

// Helper function to get unique time slots
function getUniqueTimeSlots($schedule) {
    $slots = [];
    foreach ($schedule as $class) {
        $key = $class['start_time'] . '-' . $class['end_time'];
        if (!isset($slots[$key])) {
            $slots[$key] = [
                'start_time' => $class['start_time'],
                'end_time' => $class['end_time'],
                'slot_name' => $class['slot_name']
            ];
        }
    }
    
    // Sort by start time
    uasort($slots, function($a, $b) {
        return strcmp($a['start_time'], $b['start_time']);
    });
    
    return $slots;
}

$scheduleGrid = organizeScheduleGrid($scheduleData['weekly_schedule'], $scheduleData['time_slots']);
$uniqueTimeSlots = getUniqueTimeSlots($scheduleData['weekly_schedule']);
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

        /* Ensure header buttons stay small like in subjects page */
        .page-header .btn-action {
            padding: 0.375rem 0.75rem !important;
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-action {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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

        .stat-icon.classes {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.subjects {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon.credits {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon.hours {
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

        /* Schedule Grid */
        .schedule-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            overflow-x: auto;
        }

        .schedule-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .schedule-title {
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

        .schedule-grid {
            display: grid;
            grid-template-columns: 120px repeat(6, 1fr);
            gap: 1px;
            background: var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            min-width: 900px;
        }

        .grid-header {
            background: var(--bg-tertiary);
            padding: 1rem 0.75rem;
            font-weight: 600;
            text-align: center;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .time-header {
            background: var(--primary-color);
            color: white;
        }

        .day-header {
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
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .class-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .class-item.theory {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .class-item.practical {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .class-item.lab {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
            margin-top: 0.25rem;
        }

        .class-credits {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.125rem 0.375rem;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
        }

        /* Empty State */
        .empty-schedule {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-schedule svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* List View */
        .schedule-list {
            display: none;
        }

        .schedule-list.active {
            display: block;
        }

        .list-day {
            margin-bottom: 2rem;
        }

        .day-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .list-class {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }

        .list-class.theory {
            border-left-color: #667eea;
        }

        .list-class.practical {
            border-left-color: #10b981;
        }

        .list-class.lab {
            border-left-color: #f59e0b;
        }

        .list-class:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .list-class-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .list-class-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .list-class-time {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .list-class-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
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

        .subject-type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .subject-type-badge.theory {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .subject-type-badge.practical {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .subject-type-badge.lab {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        /* Timetable Header */
        .timetable-header {
            padding: 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .student-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .header-info h1 {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .header-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            font-weight: 500;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .action-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            cursor: pointer;
        }

        .action-button:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .action-button.secondary {
            background: rgba(255, 255, 255, 0.8);
            color: var(--text-primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .action-button.secondary:hover {
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
        }

        /* Enhanced Responsive Design */
        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 0px;
                --sidebar-collapsed-width: 0px;
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

            .schedule-container {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .schedule-grid {
                display: none;
            }

            .schedule-list {
                display: block !important;
            }

            /* Stronger, more visible borders on mobile list view cards */
            .list-class {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .list-class {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }

            .toggle-btn {
                display: none;
            }

            
        }

        @media (max-width: 480px) {
            

            .stats-grid {
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

        /* Print Styles */
        @media print {
            body { background: white !important; }
            .glass-card { 
                background: white !important;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
            .header-actions { display: none; }
            .view-toggles { display: none; }
        }

        /* Dark mode component overrides */
        [data-theme="dark"] body {
            background: linear-gradient(135deg, #0f172a 0%, #111827 100%);
        }

        [data-theme="dark"] .glass-card,
        [data-theme="dark"] .schedule-container {
            background: rgba(17, 17, 17, 0.6);
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
        }

        [data-theme="dark"] .sticky-btn-secondary,
        [data-theme="dark"] .btn-secondary,
        [data-theme="dark"] .action-button.secondary {
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        [data-theme="dark"] .view-toggles {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        [data-theme="dark"] .toggle-btn {
            color: var(--text-secondary);
        }

        [data-theme="dark"] .timetable-header {
            background: linear-gradient(135deg, rgba(23, 23, 23, 0.9) 0%, rgba(23, 23, 23, 0.7) 100%);
            border-color: rgba(255, 255, 255, 0.08);
        }

        [data-theme="dark"] .schedule-grid {
            background: rgba(255, 255, 255, 0.06);
        }

        [data-theme="dark"] .grid-header.day-header {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        [data-theme="dark"] .schedule-cell {
            background: var(--bg-primary);
        }

        [data-theme="dark"] .schedule-cell:hover {
            background: var(--bg-secondary);
        }

        [data-theme="dark"] .academic-info,
        [data-theme="dark"] .meta-item {
            background: rgba(102, 126, 234, 0.12);
            color: var(--text-primary);
        }

        /* List view cards */
        [data-theme="dark"] .list-class {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        [data-theme="dark"] .list-class:hover {
            box-shadow: var(--shadow-md);
        }

        /* Subject type badges in list view */
        [data-theme="dark"] .subject-type-badge.theory {
            background: rgba(102, 126, 234, 0.15);
            color: #9aa8ff;
        }
        [data-theme="dark"] .subject-type-badge.practical {
            background: rgba(16, 185, 129, 0.15);
            color: #7de9c2;
        }
        [data-theme="dark"] .subject-type-badge.lab {
            background: rgba(245, 158, 11, 0.15);
            color: #ffcf7a;
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
                        <h1 class="page-title">📅My Timetable</h1>
                    </div>
                    <div class="d-flex gap-2 header-actions">
                    <a href="subjects.php" class="btn-action btn-outline">
                            📚 View Subjects
                        </a>
                        <a href="export.php" class="btn-action btn-primary">
                            📄 Export List
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <?php if (!empty($scheduleData['schedule_summary'])): ?>
            <div class="stats-grid">
                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon classes">📚</div>
                    <div class="stat-number"><?= $scheduleData['schedule_summary']['total_classes'] ?></div>
                    <div class="stat-label">Total Classes</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon subjects">📖</div>
                    <div class="stat-number"><?= $scheduleData['schedule_summary']['total_subjects'] ?></div>
                    <div class="stat-label">Subjects</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon credits">🎯</div>
                    <div class="stat-number"><?= $scheduleData['schedule_summary']['total_credits'] ?></div>
                    <div class="stat-label">Total Credits</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon hours">⏰</div>
                    <div class="stat-number"><?= number_format($scheduleData['schedule_summary']['weekly_hours'], 1) ?></div>
                    <div class="stat-label">Weekly Hours</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Schedule Display -->
        <div class="schedule-container">
            <div class="schedule-header">
                <h2 class="schedule-title">Weekly Timetable</h2>
                <div class="view-toggles">
                    <button class="toggle-btn active" onclick="switchView('grid')">Grid View</button>
                    <button class="toggle-btn" onclick="switchView('list')">List View</button>
                </div>
            </div>

            <?php if (!empty($scheduleData['weekly_schedule'])): ?>
                <!-- Grid View -->
                <div class="schedule-grid" id="gridView">
                    <!-- Headers -->
                    <div class="grid-header time-header">Time</div>
                    <div class="grid-header day-header">Monday</div>
                    <div class="grid-header day-header">Tuesday</div>
                    <div class="grid-header day-header">Wednesday</div>
                    <div class="grid-header day-header">Thursday</div>
                    <div class="grid-header day-header">Friday</div>
                    <div class="grid-header day-header">Saturday</div>

                    <!-- Time slots and classes -->
                    <?php foreach ($uniqueTimeSlots as $timeKey => $slot): ?>
                        <div class="time-slot">
                            <?= date('h:i A', strtotime($slot['start_time'])) ?><br>
                            <small><?= date('h:i A', strtotime($slot['end_time'])) ?></small>
                        </div>
                        
                        <?php 
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        foreach ($days as $day): 
                        ?>
                            <div class="schedule-cell">
                                <?php if (isset($scheduleGrid[$day][$timeKey])): 
                                    $class = $scheduleGrid[$day][$timeKey];
                                ?>
                                    <div class="class-item <?= strtolower($class['subject_type']) ?>" onclick="showClassDetails(<?= htmlspecialchars(json_encode($class)) ?>)">
                                        <div class="class-credits"><?= $class['credits'] ?></div>
                                        <div class="class-code"><?= htmlspecialchars($class['subject_code']) ?></div>
                                        <div class="class-name"><?= htmlspecialchars($class['subject_name']) ?></div>
                                        <div class="class-room">
                                            🏢 <?= htmlspecialchars($class['room_number']) ?>
                                        </div>
                                        <div class="class-faculty">
                                            👨‍🏫 <?= htmlspecialchars($class['faculty_name']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>

                <!-- List View -->
                <div class="schedule-list" id="listView">
                    <?php 
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    foreach ($days as $day): 
                        $dayClasses = array_filter($scheduleData['weekly_schedule'], function($class) use ($day) {
                            return $class['day_of_week'] === $day;
                        });
                        
                        if (!empty($dayClasses)):
                    ?>
                        <div class="list-day">
                            <h3 class="day-title"><?= $day ?></h3>
                            <?php foreach ($dayClasses as $class): ?>
                                <div class="list-class <?= strtolower($class['subject_type']) ?>">
                                    <div class="list-class-header">
                                        <div class="list-class-title">
                                            <?= htmlspecialchars($class['subject_code']) ?> - <?= htmlspecialchars($class['subject_name']) ?>
                                            <span class="subject-type-badge <?= strtolower($class['subject_type']) ?>">
                                                <?= ucfirst($class['subject_type']) ?>
                                            </span>
                                        </div>
                                        <div class="list-class-time">
                                            <?= date('h:i A', strtotime($class['start_time'])) ?> - <?= date('h:i A', strtotime($class['end_time'])) ?>
                                        </div>
                                    </div>
                                    <div class="list-class-details">
                                        <div class="detail-item">
                                            <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M19 21V5C19 3.89543 18.1046 3 17 3H7C5.89543 3 5 3.89543 5 5V21L12 18L19 21Z" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                            Section <?= htmlspecialchars($class['section']) ?>
                                        </div>
                                        <div class="detail-item">
                                            <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M3 21H21M3 10H21M3 7L12 3L21 7V20H3V7Z" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                            <?= htmlspecialchars($class['room_number']) ?> - <?= htmlspecialchars($class['building']) ?>
                                        </div>
                                        <div class="detail-item">
                                            <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7Z" stroke="currentColor" stroke-width="2"/>
                                                <path d="M12 14C8.13401 14 5 17.134 5 21H19C19 17.134 15.866 14 12 14Z" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                            <?= htmlspecialchars($class['faculty_name']) ?>
                                        </div>
                                        <div class="detail-item">
                                            <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                            <?= $class['credits'] ?> Credits
                                        </div>
                                        <div class="detail-item">
                                            <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                            <?= htmlspecialchars($class['faculty_designation']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-schedule">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M16 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                        <path d="M3 10H21" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <h3>No Classes Scheduled</h3>
                    <p>You don't have any classes scheduled for this semester.</p>
                    <p style="margin-top: 0.5rem;">Contact the admin to enroll in subjects.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Class Details Modal -->
    <div class="modal fade" id="classDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" style="color: var(--text-primary);">Class Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody" style="color: var(--text-primary);">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="viewSubjectBtn">View Subject Details</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize timetable functionality
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
        // Enhanced sidebar toggle handling (no sticky header)
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
            const stickyGridBtn = document.getElementById('stickyGridBtn');
            const stickyListBtn = document.getElementById('stickyListBtn');

            // Remove active class from all buttons
            toggleBtns.forEach(btn => btn.classList.remove('active'));

            if (viewType === 'grid') {
                gridView.style.display = 'grid';
                listView.style.display = 'none';
                document.querySelector('.toggle-btn[onclick*="grid"]').classList.add('active');
                
                // Update sticky header buttons
                if (stickyGridBtn) {
                    stickyGridBtn.classList.remove('sticky-btn-secondary');
                    stickyGridBtn.classList.add('sticky-btn-primary');
                }
                if (stickyListBtn) {
                    stickyListBtn.classList.remove('sticky-btn-primary');
                    stickyListBtn.classList.add('sticky-btn-secondary');
                }
            } else {
                gridView.style.display = 'none';
                listView.style.display = 'block';
                document.querySelector('.toggle-btn[onclick*="list"]').classList.add('active');
                
                // Update sticky header buttons
                if (stickyListBtn) {
                    stickyListBtn.classList.remove('sticky-btn-secondary');
                    stickyListBtn.classList.add('sticky-btn-primary');
                }
                if (stickyGridBtn) {
                    stickyGridBtn.classList.remove('sticky-btn-primary');
                    stickyGridBtn.classList.add('sticky-btn-secondary');
                }
            }
        }

        // Show class details modal
        function showClassDetails(classData) {
            const modal = new bootstrap.Modal(document.getElementById('classDetailsModal'));
            const modalBody = document.getElementById('modalBody');
            const viewSubjectBtn = document.getElementById('viewSubjectBtn');

            // Format class details
            const startTime = new Date('1970-01-01T' + classData.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            const endTime = new Date('1970-01-01T' + classData.end_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

            modalBody.innerHTML = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">📚 Subject Information</h6>
                        <div class="mb-3">
                            <strong>Subject Code:</strong><br>
                            <span style="color: var(--text-secondary);">${classData.subject_code}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Subject Name:</strong><br>
                            <span style="color: var(--text-secondary);">${classData.subject_name}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Credits:</strong><br>
                            <span style="color: var(--text-secondary);">${classData.credits} Credits</span>
                        </div>
                        <div class="mb-3">
                            <strong>Type:</strong><br>
                            <span class="subject-type-badge ${classData.subject_type && classData.subject_type.toLowerCase ? classData.subject_type.toLowerCase() : ''}">${classData.subject_type}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Section:</strong><br>
                            <span style="color: var(--text-secondary);">Section ${classData.section}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">📅 Schedule Information</h6>
                        <div class="mb-3">
                            <strong>Day:</strong><br>
                            <span style="color: var(--text-secondary);">${classData.day_of_week}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Time:</strong><br>
                            <span style="color: var(--text-secondary);">${startTime} - ${endTime}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Classroom:</strong><br>
                            <span style="color: var(--text-secondary);">${classData.room_number} - ${classData.building}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Capacity:</strong><br>
                            <span style="color: var(--text-secondary);">${classData.capacity}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Enrollment Status:</strong><br>
                            <span style="color: var(--success-color); text-transform: capitalize;">${classData.enrollment_status}</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">👨‍🏫 Faculty Information</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px;">
                                    <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.5rem;">Faculty Name</div>
                                    <div style="color: var(--text-secondary);">${classData.faculty_name}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px;">
                                    <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.5rem;">Designation</div>
                                    <div style="color: var(--text-secondary);">${classData.faculty_designation}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">📋 Enrollment Details</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-color);">✓</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Enrolled</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color);">${classData.credits}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Credit Hours</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--warning-color);">${classData.section}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Section</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Set up view subject button
            viewSubjectBtn.href = `subjects.php?subject=${classData.subject_id}`;

            modal.show();
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
                window.location.href = 'export.php';
            }

            // G for Grid view
            if (e.key === 'g' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                switchView('grid');
            }

            // L for List view
            if (e.key === 'l' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                switchView('list');
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

        // Performance optimization: Lazy load class details
        const classItems = document.querySelectorAll('.class-item');
        classItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Add loading animation for better UX
        function showLoading() {
            const container = document.querySelector('.schedule-container');
            if (container) {
                container.style.opacity = '0.6';
                container.style.pointerEvents = 'none';
            }
        }

        function hideLoading() {
            const container = document.querySelector('.schedule-container');
            if (container) {
                container.style.opacity = '1';
                container.style.pointerEvents = 'auto';
            }
        }

        // Handle print functionality with better formatting
        window.addEventListener('beforeprint', function() {
            // Hide unnecessary elements for print
            const elementsToHide = document.querySelectorAll('.header-actions, .view-toggles');
            elementsToHide.forEach(el => el.style.display = 'none');
            
            // Ensure grid view is shown for print
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            if (gridView && listView) {
                gridView.style.display = 'grid';
                listView.style.display = 'none';
            }
        });

        window.addEventListener('afterprint', function() {
            // Restore hidden elements after print
            const elementsToShow = document.querySelectorAll('.header-actions, .view-toggles');
            elementsToShow.forEach(el => el.style.display = '');
            
            // Restore original view
            handleResponsiveView();
        });

        // Add accessibility improvements
        document.addEventListener('keydown', function(e) {
            // ESC key to close modal
            if (e.key === 'Escape') {
                const modal = bootstrap.Modal.getInstance(document.getElementById('classDetailsModal'));
                if (modal) {
                    modal.hide();
                }
            }
        });

        // Auto-refresh functionality (optional)
        function enableAutoRefresh() {
            // Refresh every 5 minutes to get updated data
            setInterval(function() {
                if (document.visibilityState === 'visible') {
                    // Only refresh if page is visible
                    location.reload();
                }
            }, 300000); // 5 minutes
        }

        // Uncomment to enable auto-refresh
        // enableAutoRefresh();

        // Add visual feedback for user interactions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('class-item') || e.target.closest('.class-item')) {
                const item = e.target.classList.contains('class-item') ? e.target : e.target.closest('.class-item');
                item.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    item.style.transform = '';
                }, 150);
            }
        });

        // Initialize intersection observer for animations
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

        // Observe all stat cards for animation
        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Add error handling for network issues
        window.addEventListener('online', function() {
            console.log('Connection restored');
            // Could show a notification that connection is back
        });

        window.addEventListener('offline', function() {
            console.log('Connection lost');
            // Could show a notification about offline mode
        });
    </script>
</body>
</html>

<?php
/**
 * Helper function for time formatting
 * @param string $time
 * @return string
 */
function formatTime($time) {
    return date('h:i A', strtotime($time));
}

/**
 * Helper function for day abbreviation
 * @param string $day
 * @return string
 */
function getDayAbbr($day) {
    $abbrs = [
        'Monday' => 'MON',
        'Tuesday' => 'TUE', 
        'Wednesday' => 'WED',
        'Thursday' => 'THU',
        'Friday' => 'FRI',
        'Saturday' => 'SAT'
    ];
    return $abbrs[$day] ?? $day;
}

/**
 * Helper function to get subject type color
 * @param string $type
 * @return string
 */
function getSubjectTypeColor($type) {
    $colors = [
        'theory' => '#667eea',
        'practical' => '#10b981',
        'lab' => '#f59e0b'
    ];
    return $colors[strtolower($type)] ?? '#667eea';
}

/**
 * Helper function to calculate class duration
 * @param string $startTime
 * @param string $endTime
 * @return float
 */
function calculateClassDuration($startTime, $endTime) {
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);
    $diff = $end->diff($start);
    return $diff->h + ($diff->i / 60);
}
?>