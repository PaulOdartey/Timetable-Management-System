<?php
/**
 * Faculty Timetable - Weekly Schedule View
 * Timetable Management System
 */

// Security and initialization
define('SYSTEM_ACCESS', true);
session_start();


// Include necessary files
require_once '../../classes/Database.php';
require_once '../../config/config.php';
require_once '../../classes/User.php';

// Authentication and authorization
User::requireLogin();
User::requireRole('faculty');

// Database instance
$db = Database::getInstance();
$currentUserId = User::getCurrentUserId();

// Fetch faculty information
$facultyInfo = $db->fetchRow("
    SELECT f.*, u.username, u.email
    FROM faculty f 
    JOIN users u ON f.user_id = u.user_id 
    WHERE f.user_id = ?
", [$currentUserId]);

if (!$facultyInfo) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

// Get current academic year and semester
$academicYear = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'academic_year_current'")['setting_value'] ?? '2024-25';
$currentSemester = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'semester_current'")['setting_value'] ?? '1';

// Get week parameter (default to current week)
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$currentDate = new DateTime();
$currentDate->modify($weekOffset . ' weeks');

// Calculate week start (Monday) and end (Sunday)
$weekStart = clone $currentDate;
$weekStart->modify('monday this week');
$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');

// Get all time slots
$timeSlots = $db->fetchAll("
    SELECT 
        slot_id,
        slot_name,
        start_time,
        end_time,
        day_of_week,
        is_active
    FROM time_slots 
    WHERE is_active = TRUE
    ORDER BY 
        FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
        start_time
");

// Get faculty's complete timetable for the current academic year
$facultyTimetable = $db->fetchAll("
    SELECT 
        t.timetable_id,
        t.section,
        s.subject_code,
        s.subject_name,
        s.credits,
        s.type as subject_type,
        CONCAT(c.room_number, ', ', c.building) as classroom,
        c.capacity,
        ts.slot_id,
        ts.slot_name,
        ts.start_time,
        ts.end_time,
        ts.day_of_week,
        COUNT(e.enrollment_id) as enrolled_count,
        d.department_name,
        prog.program_name
    FROM timetables t
    JOIN subjects s ON t.subject_id = s.subject_id
    JOIN classrooms c ON t.classroom_id = c.classroom_id
    JOIN time_slots ts ON t.slot_id = ts.slot_id
    JOIN departments d ON s.department_id = d.department_id
    JOIN programs prog ON s.program_id = prog.program_id
    LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
        AND t.section = e.section 
        AND t.academic_year = e.academic_year 
        AND t.semester = e.semester
        AND e.status = 'enrolled'
    WHERE t.faculty_id = ? 
        AND t.is_active = TRUE
        AND t.academic_year = ? 
        AND t.semester = ?
    GROUP BY t.timetable_id
    ORDER BY 
        FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
        ts.start_time
", [$facultyInfo['faculty_id'], $academicYear, $currentSemester]);

// Organize timetable by day and time
$weeklySchedule = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Initialize empty schedule
foreach ($days as $day) {
    $weeklySchedule[$day] = [];
}

// Fill schedule with classes
foreach ($facultyTimetable as $class) {
    $day = $class['day_of_week'];
    $timeKey = $class['start_time'] . '-' . $class['end_time'];
    
    if (!isset($weeklySchedule[$day][$timeKey])) {
        $weeklySchedule[$day][$timeKey] = [];
    }
    
    $weeklySchedule[$day][$timeKey][] = $class;
}

// Get unique time slots for the week view
$uniqueTimeSlots = [];
foreach ($timeSlots as $slot) {
    $timeKey = $slot['start_time'] . '-' . $slot['end_time'];
    if (!isset($uniqueTimeSlots[$timeKey])) {
        $uniqueTimeSlots[$timeKey] = [
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'display_time' => date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time']))
        ];
    }
}

// Sort time slots by start time
ksort($uniqueTimeSlots);

// Calculate statistics
$weeklyStats = [
    'total_classes' => count($facultyTimetable),
    'total_hours' => 0,
    'subjects_count' => count(array_unique(array_column($facultyTimetable, 'subject_code'))),
    'total_students' => array_sum(array_column($facultyTimetable, 'enrolled_count'))
];

// Calculate total teaching hours
foreach ($facultyTimetable as $class) {
    $start = new DateTime($class['start_time']);
    $end = new DateTime($class['end_time']);
    $interval = $start->diff($end);
    $weeklyStats['total_hours'] += $interval->h + ($interval->i / 60);
}

// Get today's classes
$today = date('l');
$todayClasses = [];
if (isset($weeklySchedule[$today])) {
    foreach ($weeklySchedule[$today] as $timeSlot => $classes) {
        $todayClasses = array_merge($todayClasses, $classes);
    }
}

// Sort today's classes by time
usort($todayClasses, function($a, $b) {
    return strtotime($a['start_time']) - strtotime($b['start_time']);
});

$pageTitle = 'My Timetable';
$currentPage = 'timetable';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SYSTEM_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --faculty-navbar-height: 70px;
            --faculty-sidebar-width: 280px;
            --faculty-sidebar-collapsed-width: 80px;
            --faculty-primary: #28a745;
            --faculty-secondary: #20c997;
            --faculty-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            --faculty-dark: #1e7e34;
            --faculty-light: #d4edda;
            --faculty-shadow: 0 2px 15px rgba(40, 167, 69, 0.1);
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
            --timetable-cell-height: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Include all the navbar and sidebar styles from the previous file */
        .faculty-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--faculty-navbar-height);
            background: var(--faculty-gradient);
            color: white;
            z-index: 1100;
            box-shadow: var(--faculty-shadow);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .faculty-navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 1.5rem;
            max-width: 100%;
            margin: 0 auto;
        }

        .faculty-navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .faculty-navbar-brand:hover {
            color: rgba(255, 255, 255, 0.9);
            transform: translateY(-1px);
        }

        .faculty-navbar-brand .brand-icon {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .faculty-navbar-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .faculty-nav-item {
            position: relative;
        }

        .faculty-nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            white-space: nowrap;
        }

        .faculty-nav-link:hover,
        .faculty-nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .faculty-nav-link.active::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 3px;
            background: white;
            border-radius: 2px;
        }

        .faculty-nav-icon {
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        .faculty-nav-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.4rem;
            border-radius: 10px;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: facultyPulse 2s infinite;
        }

        .faculty-nav-badge.badge-info {
            background: #17a2b8;
        }

        .faculty-navbar-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .faculty-user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            text-align: right;
        }

        .faculty-user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: white;
            margin: 0;
            line-height: 1.2;
        }

        .faculty-user-role {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
            line-height: 1.2;
        }

        .faculty-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .faculty-user-avatar:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: scale(1.05);
        }

        .faculty-navbar-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .faculty-navbar-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Main Content */
        .faculty-main-wrapper {
            margin-left: var(--faculty-sidebar-width);
            margin-top: var(--faculty-navbar-height);
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - var(--faculty-navbar-height));
            background: #f8f9fa;
        }

        .faculty-main-wrapper.sidebar-collapsed {
            margin-left: var(--faculty-sidebar-collapsed-width);
        }

        .faculty-content {
            padding: 2rem;
        }

        /* Timetable Header */
        .timetable-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .timetable-header h1 {
            color: #333;
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 2rem;
        }

        .timetable-header p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        /* Week Navigation */
        .week-navigation {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .week-info {
            text-align: center;
            flex: 1;
        }

        .week-info h3 {
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .week-info p {
            color: #666;
            margin: 0;
        }

        .week-nav-btn {
            background: var(--faculty-gradient);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .week-nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .week-nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Statistics Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.classes { background: var(--primary-gradient); }
        .stat-icon.hours { background: var(--secondary-gradient); }
        .stat-icon.subjects { background: var(--success-gradient); }
        .stat-icon.students { background: var(--warning-gradient); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Timetable Grid */
        .timetable-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .timetable-grid {
            display: grid;
            grid-template-columns: 120px repeat(7, 1fr);
            gap: 1px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            min-width: 800px;
        }

        .timetable-header-cell {
            background: var(--faculty-gradient);
            color: white;
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .timetable-time-cell {
            background: rgba(102, 126, 234, 0.1);
            padding: 1rem 0.5rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.8rem;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: var(--timetable-cell-height);
        }

        .timetable-cell {
            background: white;
            padding: 0.5rem;
            min-height: var(--timetable-cell-height);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            position: relative;
        }

        .timetable-cell.empty {
            background: #f8f9fa;
        }

        .class-block {
            background: var(--primary-gradient);
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.8rem;
            line-height: 1.3;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .class-block:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .class-block.theory {
            background: var(--primary-gradient);
        }

        .class-block.practical {
            background: var(--secondary-gradient);
        }

        .class-block.lab {
            background: var(--success-gradient);
        }

        .class-subject {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.85rem;
        }

        .class-details {
            font-size: 0.75rem;
            opacity: 0.9;
            line-height: 1.2;
        }

        .class-room {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.25rem;
        }

        .class-students {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.25rem;
        }

        /* Today's Classes Sidebar */
        .today-classes {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .section-title {
            color: #333;
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #667eea;
        }

        .today-class-item {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .today-class-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }

        .today-class-time {
            background: var(--primary-gradient);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .today-class-subject {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .today-class-details {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .action-btn {
            background: white;
            border: 2px solid var(--faculty-primary);
            color: var(--faculty-primary);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            background: var(--faculty-primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .action-btn.primary {
            background: var(--faculty-gradient);
            color: white;
            border-color: transparent;
        }

        .action-btn.primary:hover {
            background: var(--faculty-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(30, 126, 52, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        /* Dropdown Menu Styles */
        .faculty-dropdown {
            position: relative;
        }

        .faculty-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1200;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .faculty-dropdown:hover .faculty-dropdown-menu,
        .faculty-dropdown.active .faculty-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .faculty-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .faculty-dropdown-item:last-child {
            border-bottom: none;
        }

        .faculty-dropdown-item:hover {
            background: var(--faculty-light);
            color: var(--faculty-dark);
        }

        .faculty-dropdown-item.danger:hover {
            background: #f8d7da;
            color: #721c24;
        }

        /* Animation Keyframes */
        @keyframes facultyPulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* Responsive Design */
        @media (max-width: 1023px) {
            .faculty-navbar-container {
                padding: 0 1rem;
            }
            
            .faculty-nav-link .nav-text {
                display: none;
            }
            
            .faculty-nav-link {
                padding: 0.75rem;
            }
            
            .faculty-user-info {
                display: none;
            }

            .timetable-grid {
                grid-template-columns: 100px repeat(7, 1fr);
            }

            .class-block {
                font-size: 0.75rem;
                padding: 0.5rem;
            }
        }

        @media (max-width: 768px) {
            .faculty-navbar-nav {
                display: none;
            }
            
            .faculty-navbar-toggle {
                display: block;
            }
            
            .faculty-main-wrapper {
                margin-left: 0;
            }

            .faculty-content {
                padding: 1rem;
            }

            .timetable-header h1 {
                font-size: 1.5rem;
            }

            .week-navigation {
                flex-direction: column;
                gap: 1rem;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .timetable-grid {
                grid-template-columns: 80px repeat(7, 1fr);
                min-width: 600px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-btn {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .faculty-navbar-brand .brand-text {
                display: none;
            }
            
            .faculty-navbar-container {
                padding: 0 0.75rem;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .timetable-grid {
                grid-template-columns: 60px repeat(7, 1fr);
                min-width: 500px;
            }

            .class-block {
                font-size: 0.7rem;
                padding: 0.4rem;
            }
        }

        /* Print styles */
        @media print {
            .faculty-navbar,
            .action-buttons,
            .week-navigation {
                display: none !important;
            }
            
            .faculty-main-wrapper {
                margin-left: 0 !important;
                margin-top: 0 !important;
            }

            .timetable-container {
                box-shadow: none;
                border: 1px solid #000;
            }

            .class-block {
                background: #f0f0f0 !important;
                color: #000 !important;
                border: 1px solid #000;
            }
        }
    </style>
</head>
<body>
    <!-- Faculty Navbar -->
    <nav class="faculty-navbar" id="facultyNavbar" data-theme="default">
        <div class="faculty-navbar-container">
            <!-- Brand/Logo -->
            <a href="<?php echo BASE_URL; ?>faculty/" class="faculty-navbar-brand">
                <div class="brand-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <span class="brand-text">Faculty Portal</span>
            </a>

            <!-- Desktop Navigation -->
            <ul class="faculty-navbar-nav">
                <li class="faculty-nav-item">
                    <a href="<?php echo BASE_URL; ?>faculty/" class="faculty-nav-link">
                        <i class="fas fa-tachometer-alt faculty-nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                
                <li class="faculty-nav-item">
                    <a href="<?php echo BASE_URL; ?>faculty/timetable/" class="faculty-nav-link active">
                        <i class="fas fa-calendar-week faculty-nav-icon"></i>
                        <span class="nav-text">My Schedule</span>
                        <?php if ($weeklyStats['total_classes'] > 0): ?>
                            <span class="faculty-nav-badge badge-info"><?php echo $weeklyStats['total_classes']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="faculty-nav-item">
                    <a href="<?php echo BASE_URL; ?>faculty/subjects/" class="faculty-nav-link">
                        <i class="fas fa-book faculty-nav-icon"></i>
                        <span class="nav-text">My Subjects</span>
                    </a>
                </li>
                
                <li class="faculty-nav-item">
                    <a href="<?php echo BASE_URL; ?>faculty/classes/" class="faculty-nav-link">
                        <i class="fas fa-users faculty-nav-icon"></i>
                        <span class="nav-text">My Classes</span>
                    </a>
                </li>
                
                <li class="faculty-nav-item">
                    <a href="<?php echo BASE_URL; ?>faculty/notifications/" class="faculty-nav-link">
                        <i class="fas fa-bell faculty-nav-icon"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </li>
            </ul>

            <!-- User Info & Dropdown -->
            <div class="faculty-navbar-user">
                <?php if ($facultyInfo): ?>
                    <div class="faculty-user-info">
                        <p class="faculty-user-name"><?php echo htmlspecialchars($facultyInfo['first_name'] . ' ' . $facultyInfo['last_name']); ?></p>
                        <p class="faculty-user-role"><?php echo htmlspecialchars($facultyInfo['designation']); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="faculty-dropdown">
                    <div class="faculty-user-avatar" onclick="toggleFacultyDropdown()">
                        <?php 
                        if ($facultyInfo) {
                            echo strtoupper(substr($facultyInfo['first_name'], 0, 1) . substr($facultyInfo['last_name'], 0, 1));
                        } else {
                            echo 'F';
                        }
                        ?>
                    </div>
                    
                    <div class="faculty-dropdown-menu" id="facultyDropdownMenu">
                        <a href="<?php echo BASE_URL; ?>faculty/profile/" class="faculty-dropdown-item">
                            <i class="fas fa-user"></i>
                            My Profile
                        </a>
                        <a href="<?php echo BASE_URL; ?>faculty/profile/change-password.php" class="faculty-dropdown-item">
                            <i class="fas fa-key"></i>
                            Change Password
                        </a>
                        <a href="<?php echo BASE_URL; ?>faculty/profile/preferences.php" class="faculty-dropdown-item">
                            <i class="fas fa-sliders-h"></i>
                            Preferences
                        </a>
                        <a href="<?php echo BASE_URL; ?>faculty/help/" class="faculty-dropdown-item">
                            <i class="fas fa-question-circle"></i>
                            Help & Support
                        </a>
                        <div style="border-top: 1px solid rgba(0,0,0,0.1); margin: 0.5rem 0;"></div>
                        <a href="<?php echo BASE_URL; ?>auth/logout.php" class="faculty-dropdown-item danger" onclick="return confirm('Are you sure you want to logout?')">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile Toggle -->
            <button class="faculty-navbar-toggle" onclick="toggleFacultyMobileMenu()" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <div class="faculty-main-wrapper" id="facultyMainWrapper">
        <div class="faculty-content">
            <!-- Timetable Header -->
            <div class="timetable-header">
                <h1><i class="fas fa-calendar-week"></i> My Teaching Schedule</h1>
                <p>Academic Year <?php echo $academicYear; ?> • Semester <?php echo $currentSemester; ?></p>
            </div>

            <!-- Week Navigation -->
            <div class="week-navigation">
                <button class="week-nav-btn" onclick="navigateWeek(-1)">
                    <i class="fas fa-chevron-left"></i> Previous Week
                </button>
                
                <div class="week-info">
                    <h3><?php echo $weekStart->format('M j') . ' - ' . $weekEnd->format('M j, Y'); ?></h3>
                    <p>Week <?php echo $weekStart->format('W'); ?> of <?php echo $weekStart->format('Y'); ?></p>
                </div>
                
                <button class="week-nav-btn" onclick="navigateWeek(1)">
                    Next Week <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon classes">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-number"><?php echo $weeklyStats['total_classes']; ?></div>
                    <div class="stat-label">Weekly Classes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon hours">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($weeklyStats['total_hours'], 1); ?></div>
                    <div class="stat-label">Teaching Hours</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon subjects">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-number"><?php echo $weeklyStats['subjects_count']; ?></div>
                    <div class="stat-label">Subjects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon students">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-number"><?php echo $weeklyStats['total_students']; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="export.php?format=pdf&week=<?php echo $weekOffset; ?>" class="action-btn primary">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <a href="export.php?format=excel&week=<?php echo $weekOffset; ?>" class="action-btn">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
                <a href="daily.php?date=<?php echo date('Y-m-d'); ?>" class="action-btn">
                    <i class="fas fa-calendar-day"></i> Today's Details
                </a>
                <a href="monthly.php" class="action-btn">
                    <i class="fas fa-calendar-alt"></i> Monthly View
                </a>
            </div>

            <div class="row">
                <!-- Weekly Timetable Grid -->
                <div class="col-lg-8">
                    <div class="timetable-container">
                        <h2 class="section-title">
                            <i class="fas fa-table"></i>
                            Weekly Schedule
                        </h2>
                        
                        <?php if (empty($facultyTimetable)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Classes Scheduled</h3>
                                <p>You don't have any classes scheduled for this week.</p>
                            </div>
                        <?php else: ?>
                            <div class="timetable-grid">
                                <!-- Header Row -->
                                <div class="timetable-header-cell">Time</div>
                                <?php foreach ($days as $day): ?>
                                    <div class="timetable-header-cell">
                                        <?php echo $day; ?>
                                        <br>
                                        <small><?php 
                                            $dayDate = clone $weekStart;
                                            $dayDate->modify('+' . (array_search($day, $days)) . ' days');
                                            echo $dayDate->format('M j');
                                        ?></small>
                                    </div>
                                <?php endforeach; ?>
                                
                                <!-- Time Slots and Classes -->
                                <?php foreach ($uniqueTimeSlots as $timeKey => $timeSlot): ?>
                                    <div class="timetable-time-cell">
                                        <?php echo $timeSlot['display_time']; ?>
                                    </div>
                                    
                                    <?php foreach ($days as $day): ?>
                                        <div class="timetable-cell <?php echo empty($weeklySchedule[$day][$timeKey]) ? 'empty' : ''; ?>">
                                            <?php if (!empty($weeklySchedule[$day][$timeKey])): ?>
                                                <?php foreach ($weeklySchedule[$day][$timeKey] as $class): ?>
                                                    <div class="class-block <?php echo strtolower($class['subject_type']); ?>" 
                                                         onclick="showClassDetails(<?php echo htmlspecialchars(json_encode($class)); ?>)"
                                                         title="<?php echo htmlspecialchars($class['subject_name']); ?>">
                                                        <div class="class-subject">
                                                            <?php echo htmlspecialchars($class['subject_code']); ?>
                                                        </div>
                                                        <div class="class-details">
                                                            Section <?php echo htmlspecialchars($class['section']); ?>
                                                        </div>
                                                        <div class="class-room">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                            <?php echo htmlspecialchars($class['classroom']); ?>
                                                        </div>
                                                        <div class="class-students">
                                                            <i class="fas fa-users"></i>
                                                            <?php echo $class['enrolled_count']; ?> students
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Today's Classes Sidebar -->
                <div class="col-lg-4">
                    <div class="today-classes">
                        <h2 class="section-title">
                            <i class="fas fa-calendar-day"></i>
                            Today's Classes (<?php echo $today; ?>)
                        </h2>
                        
                        <?php if (empty($todayClasses)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Classes Today</h3>
                                <p>You don't have any classes scheduled for today.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($todayClasses as $class): ?>
                                <div class="today-class-item">
                                    <div class="today-class-time">
                                        <?php echo date('g:i A', strtotime($class['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($class['end_time'])); ?>
                                    </div>
                                    <div class="today-class-subject">
                                        <?php echo htmlspecialchars($class['subject_code']); ?> - <?php echo htmlspecialchars($class['subject_name']); ?>
                                    </div>
                                    <div class="today-class-details">
                                        <i class="fas fa-users"></i> Section <?php echo htmlspecialchars($class['section']); ?> (<?php echo $class['enrolled_count']; ?> students)<br>
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($class['classroom']); ?><br>
                                        <i class="fas fa-tag"></i> <?php echo ucfirst($class['subject_type']); ?> • <?php echo $class['credits']; ?> Credits<br>
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($class['department_name']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="today-classes">
                        <h2 class="section-title">
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h2>
                        
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <a href="<?php echo BASE_URL; ?>faculty/subjects/" class="action-btn" style="justify-content: center;">
                                <i class="fas fa-book"></i> View All Subjects
                            </a>
                            <a href="<?php echo BASE_URL; ?>faculty/students/" class="action-btn" style="justify-content: center;">
                                <i class="fas fa-user-graduate"></i> View Students
                            </a>
                            <a href="<?php echo BASE_URL; ?>faculty/attendance/" class="action-btn" style="justify-content: center;">
                                <i class="fas fa-check-circle"></i> Mark Attendance
                            </a>
                            <a href="<?php echo BASE_URL; ?>faculty/reports/" class="action-btn" style="justify-content: center;">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Class Details Modal -->
    <div class="modal fade" id="classDetailsModal" tabindex="-1" aria-labelledby="classDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="classDetailsModalLabel">Class Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="classDetailsContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="markAttendance()">Mark Attendance</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Faculty Navbar functionality
        function toggleFacultyDropdown() {
            const dropdown = document.querySelector('.faculty-dropdown');
            dropdown.classList.toggle('active');
            
            if (dropdown.classList.contains('active')) {
                setTimeout(() => {
                    document.addEventListener('click', closeFacultyDropdownOutside);
                }, 100);
            }
        }
        
        function closeFacultyDropdownOutside(event) {
            const dropdown = document.querySelector('.faculty-dropdown');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
                document.removeEventListener('click', closeFacultyDropdownOutside);
            }
        }
        
        function toggleFacultyMobileMenu() {
            // Mobile menu functionality
            console.log('Mobile menu toggle');
        }

        // Week navigation
        function navigateWeek(direction) {
            const currentWeek = <?php echo $weekOffset; ?>;
            const newWeek = currentWeek + direction;
            window.location.href = `?week=${newWeek}`;
        }

        // Show class details in modal
        function showClassDetails(classData) {
            const modal = new bootstrap.Modal(document.getElementById('classDetailsModal'));
            const content = document.getElementById('classDetailsContent');
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-book"></i> Subject Information</h6>
                        <p><strong>Code:</strong> ${classData.subject_code}</p>
                        <p><strong>Name:</strong> ${classData.subject_name}</p>
                        <p><strong>Type:</strong> ${classData.subject_type}</p>
                        <p><strong>Credits:</strong> ${classData.credits}</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle"></i> Class Details</h6>
                        <p><strong>Section:</strong> ${classData.section}</p>
                        <p><strong>Time:</strong> ${formatTime(classData.start_time)} - ${formatTime(classData.end_time)}</p>
                        <p><strong>Classroom:</strong> ${classData.classroom}</p>
                        <p><strong>Capacity:</strong> ${classData.capacity}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6><i class="fas fa-users"></i> Enrollment</h6>
                        <p><strong>Enrolled Students:</strong> ${classData.enrolled_count}</p>
                        <p><strong>Department:</strong> ${classData.department_name}</p>
                        <p><strong>Program:</strong> ${classData.program_name}</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-calendar"></i> Schedule Info</h6>
                        <p><strong>Day:</strong> ${classData.day_of_week}</p>
                        <p><strong>Slot:</strong> ${classData.slot_name}</p>
                        <p><strong>Academic Year:</strong> <?php echo $academicYear; ?></p>
                        <p><strong>Semester:</strong> <?php echo $currentSemester; ?></p>
                    </div>
                </div>
            `;
            
            // Store class data for attendance marking
            window.currentClassData = classData;
            
            modal.show();
        }

        // Format time helper
        function formatTime(timeString) {
            const time = new Date(`2000-01-01 ${timeString}`);
            return time.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        // Mark attendance functionality
        function markAttendance() {
            if (window.currentClassData) {
                const classData = window.currentClassData;
                const url = `<?php echo BASE_URL; ?>faculty/attendance/mark.php?timetable_id=${classData.timetable_id}&date=${new Date().toISOString().split('T')[0]}`;
                window.open(url, '_blank');
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to timetable cells
            const classBl ocks = document.querySelectorAll('.class-block');
            classBlocks.forEach(block => {
                block.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.zIndex = '10';
                });
                
                block.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.zIndex = '1';
                });
            });

            // Add keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.altKey) {
                    switch(e.key) {
                        case 'ArrowLeft':
                            e.preventDefault();
                            navigateWeek(-1);
                            break;
                        case 'ArrowRight':
                            e.preventDefault();
                            navigateWeek(1);
                            break;
                        case 't':
                            e.preventDefault();
                            window.location.href = `daily.php?date=${new Date().toISOString().split('T')[0]}`;
                            break;
                        case 'p':
                            e.preventDefault();
                            window.location.href = 'export.php?format=pdf&week=<?php echo $weekOffset; ?>';
                            break;
                    }
                }
            });

            // Auto-refresh every 5 minutes to update current time indicator
            setInterval(function() {
                updateCurrentTimeIndicator();
            }, 300000);

            // Initialize current time indicator
            updateCurrentTimeIndicator();

            console.log('Faculty timetable initialized successfully');
        });

        // Update current time indicator
        function updateCurrentTimeIndicator() {
            const now = new Date();
            const currentDay = now.toLocaleDateString('en-US', { weekday: 'long' });
            const currentTime = now.toTimeString().slice(0, 5);

            // Remove existing indicators
            document.querySelectorAll('.current-time-indicator').forEach(el => el.remove());

            // Add indicator to current time slot if it exists
            const timeSlots = document.querySelectorAll('.timetable-time-cell');
            timeSlots.forEach(cell => {
                const timeText = cell.textContent.trim();
                const timeRange = timeText.split(' - ');
                if (timeRange.length === 2) {
                    const startTime = convertTo24Hour(timeRange[0]);
                    const endTime = convertTo24Hour(timeRange[1]);
                    
                    if (currentTime >= startTime && currentTime <= endTime) {
                        const indicator = document.createElement('div');
                        indicator.className = 'current-time-indicator';
                        indicator.style.cssText = `
                            position: absolute;
                            top: 0;
                            left: 0;
                            right: 0;
                            height: 3px;
                            background: #ff4757;
                            z-index: 100;
                            animation: facultyPulse 2s infinite;
                        `;
                        cell.style.position = 'relative';
                        cell.appendChild(indicator);
                    }
                }
            });
        }

        // Convert 12-hour to 24-hour format
        function convertTo24Hour(time12h) {
            const [time, modifier] = time12h.split(' ');
            let [hours, minutes] = time.split(':');
            if (hours === '12') {
                hours = '00';
            }
            if (modifier === 'PM') {
                hours = parseInt(hours, 10) + 12;
            }
            return `${hours.toString().padStart(2, '0')}:${minutes}`;
        }

        // Print functionality
        function printTimetable() {
            window.print();
        }

        // Export functionality with loading states
        document.querySelectorAll('a[href*="export.php"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const icon = this.querySelector('i');
                const originalClass = icon.className;
                const originalText = this.innerHTML;
                
                icon.className = 'fas fa-spinner fa-spin';
                this.innerHTML = icon.outerHTML + ' Generating...';
                
                // Reset after 3 seconds
                setTimeout(() => {
                    this.innerHTML = originalText;
                }, 3000);
            });
        });

        // Expose functions globally
        window.toggleFacultyDropdown = toggleFacultyDropdown;
        window.toggleFacultyMobileMenu = toggleFacultyMobileMenu;
        window.navigateWeek = navigateWeek;
        window.showClassDetails = showClassDetails;
        window.markAttendance = markAttendance;

        console.log('Faculty timetable with enhanced features initialized successfully');
    </script>
</body>
</html>
