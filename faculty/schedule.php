<?php
/**
 * Faculty Schedule Page
 * Timetable Management System
 * 
 * Complete weekly timetable view for faculty members
 * Shows all scheduled classes in a grid format with sticky header
 */

// Start session and security checks
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Ensure user is logged in and has faculty role
User::requireLogin();
User::requireRole('faculty');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables
$scheduleData = [
    'faculty_info' => [],
    'weekly_schedule' => [],
    'schedule_summary' => [],
    'time_slots' => [],
    'subjects' => []
];

// Get current academic year and semester - use consistent database format
$currentYear = '2025-2026';  // Always use full format for consistency
$currentSemester = 1;

try {
    // Get faculty info
    $facultyInfo = $db->fetchRow("
        SELECT f.*, u.email, u.username
        FROM faculty f 
        JOIN users u ON f.user_id = u.user_id 
        WHERE f.user_id = ?
    ", [$userId]);

    if ($facultyInfo) {
        $scheduleData['faculty_info'] = $facultyInfo;
        $facultyId = $facultyInfo['faculty_id'];
    } else {
        throw new Exception('Faculty information not found');
    }

    // Get complete weekly schedule with academic year normalization
    $weeklySchedule = $db->fetchAll("
        SELECT 
            t.*,
            s.subject_code,
            s.subject_name,
            s.credits,
            c.room_number,
            c.building,
            c.capacity,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.slot_name,
            COUNT(e.enrollment_id) as enrolled_students
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
            AND t.section = e.section 
            AND t.semester = e.semester 
            AND (t.academic_year = e.academic_year OR 
                 (t.academic_year = '2025-2026' AND e.academic_year IN ('2025-26', '2025-2026')) OR
                 (t.academic_year = '2025-26' AND e.academic_year IN ('2025-26', '2025-2026')))
            AND e.status = 'enrolled'
        WHERE t.faculty_id = ? 
            AND (t.academic_year = ? OR t.academic_year = '2025-26')
            AND t.semester = ? 
            AND t.is_active = 1
        GROUP BY t.timetable_id
        ORDER BY 
            FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            ts.start_time
    ", [$facultyId, $currentYear, $currentSemester]);

    $scheduleData['weekly_schedule'] = $weeklySchedule;

    // Get all available time slots for the grid
    $timeSlots = $db->fetchAll("
        SELECT DISTINCT slot_id, day_of_week, start_time, end_time, slot_name
        FROM time_slots 
        WHERE is_active = 1 
        ORDER BY 
            FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            start_time
    ");
    $scheduleData['time_slots'] = $timeSlots;

    // Get faculty's assigned subjects
    $subjects = $db->fetchAll("
        SELECT s.*, fs.max_students, fs.assigned_date
        FROM subjects s
        JOIN faculty_subjects fs ON s.subject_id = fs.subject_id
        WHERE fs.faculty_id = ? AND fs.is_active = 1
        ORDER BY s.subject_code
    ", [$facultyId]);
    $scheduleData['subjects'] = $subjects;

    // Generate schedule summary with correct student counting and academic year normalization
    $summary = [
        'total_classes' => count($weeklySchedule),
        'total_subjects' => count($subjects),
        'total_students' => 0, // Will calculate separately
        'different_rooms' => count(array_unique(array_column($weeklySchedule, 'classroom_id'))),
        'weekly_hours' => 0
    ];

    // Calculate total students using same logic as dashboard with academic year normalization
    $totalStudents = $db->fetchRow("
        SELECT COUNT(DISTINCT e.student_id) as count
        FROM enrollments e
        JOIN faculty_subjects fs ON e.subject_id = fs.subject_id
        WHERE fs.faculty_id = ? 
        AND fs.is_active = 1 
        AND e.status = 'enrolled'
        AND (e.academic_year = '2025-2026' OR e.academic_year = '2025-26')
        AND e.semester = 1
    ", [$facultyId]);
    
    $summary['total_students'] = $totalStudents['count'] ?? 0;

    // Calculate weekly hours
    foreach ($weeklySchedule as $class) {
        $start = new DateTime($class['start_time']);
        $end = new DateTime($class['end_time']);
        $diff = $end->diff($start);
        $summary['weekly_hours'] += ($diff->h + ($diff->i / 60));
    }

    $scheduleData['schedule_summary'] = $summary;

} catch (Exception $e) {
    error_log("Faculty Schedule Error: " . $e->getMessage());
    $error_message = "Unable to load schedule data. Please try again later.";
}

// Set page title
$pageTitle = "My Schedule";
$currentPage = "schedule";

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
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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

        /* Dark mode border overrides */
        [data-theme="dark"] .schedule-grid {
            background: var(--border-color);
        }

        [data-theme="dark"] .list-class {
            border: 2px solid var(--border-color);
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

        .faculty-avatar {
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

        .stat-icon.students {
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
            background: #cbd5e1; /* stronger light-mode border */
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
        }

        .class-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
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

        /* Empty State */
        .empty-schedule {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-schedule i {
            font-size: 4rem;
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
            border: 2px solid #cbd5e1; /* stronger light-mode border */
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
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
                display: none !important;
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

            .view-toggles {
                display: none;
            }

            .sticky-header-text h3 {
                font-size: 1rem;
            }

            .sticky-header-text p {
                font-size: 0.8rem;
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

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }

        .slide-up {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
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
                        <h1 class="page-title">📅 My Schedule</h1>
                    </div>
                    <div class="d-flex gap-2">
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
                    <div class="stat-icon classes"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-number"><?= $scheduleData['schedule_summary']['total_classes'] ?></div>
                    <div class="stat-label">Total Classes</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon subjects"><i class="fas fa-book"></i></div>
                    <div class="stat-number"><?= $scheduleData['schedule_summary']['total_subjects'] ?></div>
                    <div class="stat-label">Subjects</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon students"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?= $scheduleData['schedule_summary']['total_students'] ?></div>
                    <div class="stat-label">Total Students</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon hours"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?= number_format($scheduleData['schedule_summary']['weekly_hours'], 1) ?></div>
                    <div class="stat-label">Weekly Hours</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Schedule Display -->
        <div class="schedule-container">
            <div class="schedule-header">
                <h2 class="schedule-title"><i class="fas fa-calendar-week"></i> Weekly Timetable</h2>
                <div class="view-toggles">
                    <button class="toggle-btn active" onclick="switchView('grid')">
                        <i class="fas fa-th"></i> Grid View
                    </button>
                    <button class="toggle-btn" onclick="switchView('list')">
                        <i class="fas fa-list"></i> List View
                    </button>
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
                            <?= formatTime($slot['start_time']) ?><br>
                            <small><?= formatTime($slot['end_time']) ?></small>
                        </div>
                        
                        <?php 
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        foreach ($days as $day): 
                        ?>
                            <div class="schedule-cell">
                                <?php if (isset($scheduleGrid[$day][$timeKey])): 
                                    $class = $scheduleGrid[$day][$timeKey];
                                ?>
                                    <div class="class-item" onclick="showClassDetails(<?= htmlspecialchars(json_encode($class)) ?>)">
                                        <div class="class-students"><?= $class['enrolled_students'] ?></div>
                                        <div class="class-code"><?= htmlspecialchars($class['subject_code']) ?></div>
                                        <div class="class-name"><?= htmlspecialchars($class['subject_name']) ?></div>
                                        <div class="class-room">
                                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($class['room_number']) ?>
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
                            <h3 class="day-title"><i class="fas fa-calendar-day"></i> <?= $day ?></h3>
                            <?php foreach ($dayClasses as $class): ?>
                                <div class="list-class" onclick="showClassDetails(<?= htmlspecialchars(json_encode($class)) ?>)">
                                    <div class="list-class-header">
                                        <div class="list-class-title">
                                            <?= htmlspecialchars($class['subject_code']) ?> - <?= htmlspecialchars($class['subject_name']) ?>
                                        </div>
                                        <div class="list-class-time">
                                            <?= formatTime($class['start_time']) ?> - <?= formatTime($class['end_time']) ?>
                                        </div>
                                    </div>
                                    <div class="list-class-details">
                                        <div class="detail-item">
                                            <i class="fas fa-layer-group detail-icon"></i>
                                            Section <?= htmlspecialchars($class['section']) ?>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-building detail-icon"></i>
                                            <?= htmlspecialchars($class['room_number']) ?> - <?= htmlspecialchars($class['building']) ?>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-users detail-icon"></i>
                                            <?= $class['enrolled_students'] ?> Students Enrolled
                                        </div>
                                        <div class="detail-item">
                                            <i class="fas fa-award detail-icon"></i>
                                            <?= $class['credits'] ?> Credits
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-schedule">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Classes Scheduled</h3>
                    <p>You don't have any classes scheduled for this semester.</p>
                    <p style="margin-top: 0.5rem;">Contact the admin to set up your teaching schedule.</p>
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
                    <h5 class="modal-title" style="color: var(--text-primary);"><i class="fas fa-info-circle"></i> Class Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody" style="color: var(--text-primary);">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="viewStudentsBtn">
                        <i class="fas fa-users"></i> View Students
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize schedule functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Apply theme
            applyCurrentTheme();

            // Initialize tooltips
            initializeTooltips();

            // Handle responsive view switching
            handleResponsiveView();

            // Listen for sidebar toggle events
            handleSidebarToggle();

            // Add animation delays for stats cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
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
                const gridToggle = document.querySelector('.toggle-btn[onclick*="grid"]');
                if (gridToggle) gridToggle.classList.add('active');

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
                const listToggle = document.querySelector('.toggle-btn[onclick*="list"]');
                if (listToggle) listToggle.classList.add('active');

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
            const viewStudentsBtn = document.getElementById('viewStudentsBtn');

            // Format class details
            const startTime = formatTime(classData.start_time);
            const endTime = formatTime(classData.end_time);

            modalBody.innerHTML = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">
                            <i class="fas fa-book"></i> Subject Information
                        </h6>
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
                            <strong>Section:</strong><br>
                            <span style="color: var(--text-secondary);">Section ${classData.section}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">
                            <i class="fas fa-calendar"></i> Schedule Information
                        </h6>
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
                            <span style="color: var(--text-secondary);">${classData.capacity} students</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">
                            <i class="fas fa-users"></i> Enrollment Information
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color);">${classData.enrolled_students}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Enrolled Students</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-color);">${classData.capacity - classData.enrolled_students}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Available Seats</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--warning-color);">${Math.round((classData.enrolled_students / classData.capacity) * 100)}%</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Utilization</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Set up view students button
            viewStudentsBtn.href = `students.php?subject=${classData.subject_id}&section=${classData.section}`;

            modal.show();
        }

        // Format time helper function
        function formatTime(timeString) {
            const time = new Date('1970-01-01T' + timeString);
            return time.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
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
                window.location.href = '../exports/export_faculty_schedule.php';
            }

            // G for Grid view
            if (e.key === 'g' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                switchView('grid');
            }

            // L for List view
            if (e.key === 'l' && !e.ctrlKey && !e.metaKey && !e.altKey) {
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
        const classItems = document.querySelectorAll('.class-item, .list-class');
        classItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });

            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Loading indicator for dynamic content
        function showLoading() {
            const loadingHtml = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading schedule data...</p>
                </div>
            `;
            return loadingHtml;
        }

        // Error handling for network requests
        function handleError(error) {
            console.error('Schedule Error:', error);
            return `
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Error:</strong> Unable to load data. Please refresh the page.
                </div>
            `;
        }

        // Auto-refresh functionality (optional)
        function enableAutoRefresh(intervalMinutes = 30) {
            setInterval(() => {
                // Only refresh if user is active and page is visible
                if (!document.hidden && Date.now() - lastUserActivity < 300000) { // 5 minutes
                    location.reload();
                }
            }, intervalMinutes * 60000);
        }

        // Track user activity for auto-refresh
        let lastUserActivity = Date.now();
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, () => {
                lastUserActivity = Date.now();
            }, { passive: true });
        });

        // Initialize auto-refresh (disabled by default)
        // enableAutoRefresh(30); // Uncomment to enable 30-minute auto-refresh

        // Export functionality
        function exportSchedule(format = 'pdf') {
            const exportUrl = format === 'pdf' 
                ? '../exports/export_faculty_schedule.php'
                : '../exports/export_faculty_schedule.php?format=excel';
            
            // Show loading state
            const exportBtn = document.querySelector('.action-button');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            exportBtn.disabled = true;
            
            // Simulate export delay and reset button
            setTimeout(() => {
                window.open(exportUrl, '_blank');
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 1000);
        }

        // Enhanced error boundaries
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
            // Could show user-friendly error message here
        });

        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled promise rejection:', e.reason);
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            // Log performance metrics
            if (window.performance && window.performance.timing) {
                const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
                console.log(`Page loaded in ${loadTime}ms`);
                
                // Report slow loads (> 3 seconds)
                if (loadTime > 3000) {
                    console.warn('Slow page load detected:', loadTime + 'ms');
                }
            }
        });

        // Accessibility improvements
        function enhanceAccessibility() {
            // Add proper ARIA labels to interactive elements
            const classItems = document.querySelectorAll('.class-item, .list-class');
            classItems.forEach((item, index) => {
                item.setAttribute('role', 'button');
                item.setAttribute('tabindex', '0');
                item.setAttribute('aria-label', `View details for class ${index + 1}`);
                
                // Add keyboard support
                item.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        item.click();
                    }
                });
            });

            // Enhance modal accessibility
            const modal = document.getElementById('classDetailsModal');
            if (modal) {
                modal.addEventListener('shown.bs.modal', function() {
                    const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                    if (firstFocusable) firstFocusable.focus();
                });
            }
        }

        // Initialize accessibility enhancements
        enhanceAccessibility();

        // Service Worker registration (for offline support - optional)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('../sw.js')
                    .then(registration => console.log('SW registered'))
                    .catch(registrationError => console.log('SW registration failed'));
            });
        }

        // Notification API integration (optional)
        function requestNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        console.log('Notification permission granted');
                    }
                });
            }
        }

        // Schedule reminder notifications (optional)
        function scheduleReminders() {
            const now = new Date();
            const scheduleData = <?= json_encode($scheduleData['weekly_schedule']) ?>;
            
            scheduleData.forEach(classItem => {
                const classTime = new Date(now.toDateString() + ' ' + classItem.start_time);
                const reminderTime = new Date(classTime.getTime() - (15 * 60 * 1000)); // 15 minutes before
                
                if (reminderTime > now && reminderTime.toDateString() === now.toDateString()) {
                    const timeUntilReminder = reminderTime.getTime() - now.getTime();
                    
                    setTimeout(() => {
                        if (Notification.permission === 'granted') {
                            new Notification(`Class Reminder: ${classItem.subject_code}`, {
                                body: `${classItem.subject_name} starts in 15 minutes at ${classItem.room_number}`,
                                icon: '/favicon.ico',
                                tag: 'class-reminder'
                            });
                        }
                    }, timeUntilReminder);
                }
            });
        }

        // Initialize notifications (optional)
        // requestNotificationPermission();
        // scheduleReminders();

        // Debug information (development only)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log('Faculty Schedule Debug Info:', {
                facultyInfo: <?= json_encode($scheduleData['faculty_info']) ?>,
                weeklySchedule: <?= json_encode($scheduleData['weekly_schedule']) ?>,
                scheduleSummary: <?= json_encode($scheduleData['schedule_summary']) ?>,
                currentYear: '<?= $currentYear ?>',
                currentSemester: <?= $currentSemester ?>
            });
        }
    </script>
</body>
</html> 