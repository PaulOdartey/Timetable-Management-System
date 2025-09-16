<?php
/**
 * Faculty Dashboard - Main Index Page
 * Timetable Management System
 * 
 * Professional dashboard for faculty members with modern glassmorphism design
 * Displays personal schedule, assigned subjects, student counts, and teaching activities
 */

// Start session and security checks
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../includes/profile-image-helper.php';

// Ensure user is logged in and has faculty role
User::requireLogin();
User::requireRole('faculty');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables for dashboard data
$dashboardData = [
    'personal_info' => [],
    'teaching_stats' => [],
    'schedule_overview' => [],
    'assigned_subjects' => [],
    'recent_activities' => [],
    'student_enrollments' => [],
    'notifications' => [],
    'upcoming_classes' => []
];

try {
    // Get faculty personal information
    $facultyInfo = $db->fetchRow("
        SELECT f.*, u.email, u.username, u.last_login, u.created_at as account_created
        FROM faculty f 
        JOIN users u ON f.user_id = u.user_id 
        WHERE f.user_id = ?
    ", [$userId]);

    if ($facultyInfo) {
        $dashboardData['personal_info'] = $facultyInfo;
        $facultyId = $facultyInfo['faculty_id'];
    } else {
        throw new Exception("Faculty profile not found");
    }

    // Get teaching statistics
    $teachingStats = $db->fetchRow("
        SELECT 
            COUNT(DISTINCT t.subject_id) as subjects_teaching,
            COUNT(DISTINCT t.timetable_id) as total_classes,
            COUNT(DISTINCT t.classroom_id) as classrooms_used,
            COUNT(DISTINCT e.student_id) as total_students,
            AVG(TIMESTAMPDIFF(MINUTE, ts.start_time, ts.end_time)) as avg_class_duration
        FROM timetables t
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
            AND t.section = e.section 
            AND t.academic_year = e.academic_year 
            AND t.semester = e.semester
            AND e.status = 'enrolled'
        WHERE t.faculty_id = ? 
        AND t.is_active = 1
        AND t.academic_year = '2025-2026'
        AND t.semester = 1
    ", [$facultyId]);
    $dashboardData['teaching_stats'] = $teachingStats ?: [];

    // Get schedule overview for current week
    $scheduleOverview = $db->fetchAll("
        SELECT 
            s.subject_code,
            s.subject_name,
            c.room_number,
            c.building,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            t.section,
            COUNT(e.student_id) as enrolled_students
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
            AND t.section = e.section 
            AND t.academic_year = e.academic_year 
            AND t.semester = e.semester
            AND e.status = 'enrolled'
        WHERE t.faculty_id = ? 
        AND t.is_active = 1
        AND t.academic_year = '2025-2026'
        AND t.semester = 1
        GROUP BY t.timetable_id
        ORDER BY 
            FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            ts.start_time
        LIMIT 10
    ", [$facultyId]);
    $dashboardData['schedule_overview'] = $scheduleOverview;

    // Get assigned subjects with details
    $assignedSubjects = $db->fetchAll("
        SELECT 
            s.*,
            fs.assigned_date,
            COUNT(DISTINCT t.timetable_id) as scheduled_classes,
            COUNT(DISTINCT e.student_id) as enrolled_students,
            MAX(t.created_at) as last_scheduled
        FROM faculty_subjects fs
        JOIN subjects s ON fs.subject_id = s.subject_id
        LEFT JOIN timetables t ON s.subject_id = t.subject_id AND t.faculty_id = ?
        LEFT JOIN enrollments e ON s.subject_id = e.subject_id 
            AND e.academic_year = '2025-2026' 
            AND e.semester = 1
            AND e.status = 'enrolled'
        WHERE fs.faculty_id = ? 
        AND fs.is_active = 1
        AND s.is_active = 1
        GROUP BY s.subject_id
        ORDER BY s.subject_name
        LIMIT 8
    ", [$facultyId, $facultyId]);
    $dashboardData['assigned_subjects'] = $assignedSubjects;

    // Get recent activities (audit logs related to faculty)
    $recentActivities = $db->fetchAll("
        SELECT 
            al.*,
            CASE 
                WHEN al.action LIKE '%TIMETABLE%' THEN 'Schedule Update'
                WHEN al.action LIKE '%PROFILE%' THEN 'Profile Update'
                WHEN al.action LIKE '%LOGIN%' THEN 'System Access'
                ELSE al.action
            END as activity_type
        FROM audit_logs al
        WHERE al.user_id = ?
        ORDER BY al.timestamp DESC
        LIMIT 8
    ", [$userId]);
    $dashboardData['recent_activities'] = $recentActivities;

    // Get upcoming classes (next 3 days)
    $upcomingClasses = $db->fetchAll("
        SELECT 
            s.subject_code,
            s.subject_name,
            c.room_number,
            c.building,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            t.section,
            COUNT(e.student_id) as enrolled_students
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
            AND t.section = e.section 
            AND t.academic_year = e.academic_year 
            AND t.semester = e.semester
            AND e.status = 'enrolled'
        WHERE t.faculty_id = ? 
        AND t.is_active = 1
        AND ts.day_of_week IN (
            CASE DAYOFWEEK(NOW())
                WHEN 1 THEN 'Monday'    -- Sunday -> Monday
                WHEN 2 THEN 'Tuesday'   -- Monday -> Tuesday  
                WHEN 3 THEN 'Wednesday' -- Tuesday -> Wednesday
                WHEN 4 THEN 'Thursday'  -- Wednesday -> Thursday
                WHEN 5 THEN 'Friday'    -- Thursday -> Friday
                WHEN 6 THEN 'Saturday'  -- Friday -> Saturday
                WHEN 7 THEN 'Monday'    -- Saturday -> Monday
            END,
            CASE DAYOFWEEK(NOW())
                WHEN 1 THEN 'Tuesday'
                WHEN 2 THEN 'Wednesday'
                WHEN 3 THEN 'Thursday'
                WHEN 4 THEN 'Friday'
                WHEN 5 THEN 'Saturday'
                WHEN 6 THEN 'Monday'
                WHEN 7 THEN 'Tuesday'
            END
        )
        GROUP BY t.timetable_id
        ORDER BY 
            FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            ts.start_time
        LIMIT 5
    ", [$facultyId]);
    $dashboardData['upcoming_classes'] = $upcomingClasses;

    // Get recent notifications for faculty
    $recentNotifications = $db->fetchAll("
        SELECT *
        FROM notifications
        WHERE (target_role = 'faculty' OR target_role = 'all' OR target_user_id = ?)
        AND is_active = 1
        ORDER BY created_at DESC
        LIMIT 5
    ", [$userId]);
    $dashboardData['notifications'] = $recentNotifications;

} catch (Exception $e) {
    error_log("Faculty Dashboard Error: " . $e->getMessage());
    $error_message = "Unable to load dashboard data. Please try again later.";
}

// Set page title and current page for navigation
$pageTitle = "Faculty Dashboard";
$currentPage = "dashboard";
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
   
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-tertiary: #94a3b8;
            --border-color: #e2e8f0;
            --accent-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --primary-color: #3b82f6;
            --primary-color-alpha: rgba(59, 130, 246, 0.1);
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --navbar-height: 64px;
        }

        /* Dark theme variables */
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

        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            min-height: 100vh;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        /* Main Content Container with responsive sidebar support */
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

        /* Glassmorphism Card Effects */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
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

        .welcome-card {
            padding: 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .faculty-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .faculty-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .faculty-details h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .faculty-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            padding: 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.subjects {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }

        .stat-icon.classes {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon.students {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-icon.classrooms {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Management Sections */
        .management-section {
            display: grid;
            gap: 1.5rem;
        }

        .management-card {
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Schedule Item */
        .schedule-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 2px solid rgba(59, 130, 246, 0.4);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
            transition: all 0.3s ease;
        }

        .schedule-item:hover {
            background: rgba(255, 255, 255, 0.7);
            border: 2px solid rgba(59, 130, 246, 0.7);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
            transform: translateX(4px);
        }

        .schedule-time {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            min-width: 100px;
            text-align: center;
        }

        .schedule-details {
            flex: 1;
            margin-left: 1rem;
            min-width: 0;
        }

        .schedule-details h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .schedule-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .schedule-stats {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
        }

        .student-count {
            background: var(--primary-color-alpha);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8125rem;
            font-weight: 600;
        }

        /* Subject Cards */
        .subject-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 2px solid rgba(59, 130, 246, 0.4);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
            transition: all 0.3s ease;
        }

        .subject-item:hover {
            background: rgba(255, 255, 255, 0.7);
            border: 2px solid rgba(59, 130, 246, 0.7);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
            transform: translateY(-2px);
        }

        .subject-avatar {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .subject-details {
            flex: 1;
            min-width: 0;
        }

        .subject-details h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .subject-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .subject-stats {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .subject-stat {
            text-align: center;
        }

        .subject-stat-number {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .subject-stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* Activity Feed */
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
            font-size: 1rem;
        }

        .activity-icon.schedule {
            background: var(--success-color);
            color: white;
        }

        .activity-icon.profile {
            background: var(--primary-color);
            color: white;
        }

        .activity-icon.login {
            background: var(--warning-color);
            color: white;
        }

        .activity-content h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .activity-message {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .activity-time {
            color: var(--text-tertiary);
            font-size: 0.75rem;
        }

        /* Notification Card */
        .notification-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .notification-icon.info {
            background: var(--primary-color);
            color: white;
        }

        .notification-icon.warning {
            background: var(--warning-color);
            color: white;
        }

        .notification-icon.error {
            background: var(--error-color);
            color: white;
        }

        .notification-content h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .notification-message {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .notification-time {
            color: var(--text-tertiary);
            font-size: 0.75rem;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.8);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: var(--text-primary);
        }

        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color);
            color: white;
            font-size: 1.125rem;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Animation Classes */
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
            
            .content-grid {
                grid-template-columns: 1fr;
            }

            .welcome-title {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .faculty-info {
                text-align: center;
            }

            .stat-number {
                font-size: 2rem;
            }

            .schedule-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .schedule-stats {
                align-self: flex-end;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            /* Stronger, more visible borders on mobile list-like cards */
            .schedule-item,
            .subject-item,
            .activity-item,
            .notification-item {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .schedule-item,
            [data-theme="dark"] .subject-item,
            [data-theme="dark"] .activity-item,
            [data-theme="dark"] .notification-item {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
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
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        /* Enhanced focus states for accessibility */
        .glass-card:focus,
        .action-btn:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Print styles */
        @media print {
            body { background: white !important; }
            .glass-card { 
                background: white !important;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
            .quick-actions { display: none; }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
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
            <div class="welcome-card glass-card fade-in">
                <div class="welcome-content">
                    <h1 class="welcome-title">
                        Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, 
                        Dr. <?= htmlspecialchars($dashboardData['personal_info']['first_name'] ?? 'Faculty') ?>! 🎓
                    </h1>
                    <p class="welcome-subtitle">
                        Welcome to your teaching dashboard. Manage your classes, view schedules, and track student progress.
                        <span class="live-clock"></span>
                    </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon subjects">
                    📚
                </div>
                <div class="stat-number"><?= $dashboardData['teaching_stats']['subjects_teaching'] ?? 0 ?></div>
                <div class="stat-label">Subjects Teaching</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon classes">
                    🕐
                </div>
                <div class="stat-number"><?= $dashboardData['teaching_stats']['total_classes'] ?? 0 ?></div>
                <div class="stat-label">Weekly Classes</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon students">
                    👥
                </div>
                <div class="stat-number"><?= $dashboardData['teaching_stats']['total_students'] ?? 0 ?></div>
                <div class="stat-label">Total Students</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon classrooms">
                    🏢
                </div>
                <div class="stat-number"><?= $dashboardData['teaching_stats']['classrooms_used'] ?? 0 ?></div>
                <div class="stat-label">Classrooms Used</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Main Section -->
            <div class="management-section">
                <!-- Teaching Schedule Overview -->
                <div class="management-card glass-card">
                    <h3 class="section-title">
                        📅 Weekly Teaching Schedule
                        <span style="font-size: 0.875rem; font-weight: normal; color: var(--text-secondary);">
                            (Current Semester)
                        </span>
                    </h3>

                    <?php if (!empty($dashboardData['schedule_overview'])): ?>
                        <?php foreach (array_slice($dashboardData['schedule_overview'], 0, 6) as $class): ?>
                            <div class="schedule-item">
                                <div class="schedule-time">
                                    <?= substr($class['day_of_week'], 0, 3) ?><br>
                                    <?= date('g:i A', strtotime($class['start_time'])) ?>
                                </div>
                                <div class="schedule-details">
                                    <h6><?= htmlspecialchars($class['subject_code'] . ' - ' . $class['subject_name']) ?></h6>
                                    <div class="schedule-meta">
                                        <span>📍 <?= htmlspecialchars($class['room_number'] . ', ' . $class['building']) ?></span>
                                        • <span>Section <?= htmlspecialchars($class['section']) ?></span>
                                        • <span><?= date('g:i A', strtotime($class['start_time'])) ?> - <?= date('g:i A', strtotime($class['end_time'])) ?></span>
                                    </div>
                                </div>
                                <div class="schedule-stats">
                                    <div class="student-count">
                                        <?= $class['enrolled_students'] ?> students
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="schedule/" class="action-btn" style="display: inline-flex; padding: 0.5rem 1rem;">
                                <span>View Complete Schedule</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 48px; height: 48px;">
                                <path d="M8 2V5M16 2V5M3.5 9.09H20.5M21 8.5V17C21 20 19.5 21.5 16 21.5H8C4.5 21.5 3 20 3 17V8.5C3 5.5 4.5 4 8 4H16C19.5 4 21 5.5 21 8.5Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <p>No classes scheduled yet</p>
                            <p style="font-size: 0.875rem;">Your teaching schedule will appear here once assigned.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Assigned Subjects -->
                <div class="management-card glass-card">
                    <h3 class="section-title">📖 My Subjects</h3>

                    <?php if (!empty($dashboardData['assigned_subjects'])): ?>
                        <?php foreach (array_slice($dashboardData['assigned_subjects'], 0, 4) as $subject): ?>
                            <div class="subject-item">
                                <div class="subject-avatar">
                                    <?= strtoupper(substr($subject['subject_code'], 0, 2)) ?>
                                </div>
                                <div class="subject-details">
                                    <h6><?= htmlspecialchars($subject['subject_name']) ?></h6>
                                    <div class="subject-meta">
                                        <span><?= htmlspecialchars($subject['subject_code']) ?></span>
                                        • <span><?= $subject['credits'] ?> Credits</span>
                                        • <span><?= htmlspecialchars($subject['department']) ?></span>
                                    </div>
                                    <div class="subject-stats">
                                        <div class="subject-stat">
                                            <div class="subject-stat-number"><?= $subject['scheduled_classes'] ?></div>
                                            <div class="subject-stat-label">Classes</div>
                                        </div>
                                        <div class="subject-stat">
                                            <div class="subject-stat-number"><?= $subject['enrolled_students'] ?></div>
                                            <div class="subject-stat-label">Students</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($dashboardData['assigned_subjects']) > 4): ?>
                            <div class="text-center mt-3">
                                <a href="subjects/" class="action-btn" style="display: inline-flex; padding: 0.5rem 1rem;">
                                    <span>View All Subjects</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 48px; height: 48px;">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" stroke="currentColor" stroke-width="2"/>
                                <path d="M6.5 2H20V22H6.5A2.5 2.5 0 0 1 4 19.5V4.5A2.5 2.5 0 0 1 6.5 2Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <p>No subjects assigned yet</p>
                            <p style="font-size: 0.875rem;">Contact administration for subject assignments.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar Section -->
            <div class="sidebar-section">
                <!-- Upcoming Classes -->
                <div class="management-card glass-card">
                    <h3 class="section-title">⏰ Upcoming Classes</h3>
                    
                    <?php if (!empty($dashboardData['upcoming_classes'])): ?>
                        <?php foreach ($dashboardData['upcoming_classes'] as $class): ?>
                            <div class="schedule-item">
                                <div class="schedule-time">
                                    <?= date('g:i A', strtotime($class['start_time'])) ?>
                                </div>
                                <div class="schedule-details">
                                    <h6><?= htmlspecialchars($class['subject_code']) ?></h6>
                                    <div class="schedule-meta">
                                        <span><?= substr($class['day_of_week'], 0, 3) ?></span>
                                        • <span><?= htmlspecialchars($class['room_number']) ?></span>
                                        • <span><?= $class['enrolled_students'] ?> students</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 48px; height: 48px;">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <p>No upcoming classes</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="management-card glass-card">
                    <h3 class="section-title">📈 Recent Activities</h3>

                    <?php if (!empty($dashboardData['recent_activities'])): ?>
                        <?php foreach (array_slice($dashboardData['recent_activities'], 0, 5) as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?= 
                                    strpos($activity['activity_type'], 'Schedule') !== false ? 'schedule' : 
                                    (strpos($activity['activity_type'], 'Profile') !== false ? 'profile' : 'login') 
                                ?>">
                                    <?php 
                                    if (strpos($activity['activity_type'], 'Schedule') !== false) {
                                        echo '📅';
                                    } elseif (strpos($activity['activity_type'], 'Profile') !== false) {
                                        echo '👤';
                                    } else {
                                        echo '🔐';
                                    }
                                    ?>
                                </div>
                                <div class="activity-content">
                                    <h6><?= htmlspecialchars($activity['activity_type']) ?></h6>
                                    <div class="activity-message">
                                        <?= htmlspecialchars($activity['description'] ?: ucwords(str_replace('_', ' ', strtolower($activity['action'])))) ?>
                                    </div>
                                    <div class="activity-time">
                                        <?= timeAgo($activity['timestamp']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 48px; height: 48px;">
                                <path d="M9 17H7V10H9V17ZM13 17H11V7H13V17ZM17 17H15V13H17V17Z" fill="currentColor"/>
                            </svg>
                            <p>No recent activities</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Notifications -->
                <div class="management-card glass-card">
                    <h3 class="section-title">🔔 Notifications</h3>

                    <?php if (!empty($dashboardData['notifications'])): ?>
                        <?php foreach ($dashboardData['notifications'] as $notification): ?>
                            <div class="notification-item">
                                <div class="notification-icon <?= $notification['type'] ?>">
                                    <?php
                                    switch($notification['type']) {
                                        case 'warning': echo '⚠️'; break;
                                        case 'error': echo '🚨'; break;
                                        case 'success': echo '✅'; break;
                                        default: echo 'ℹ️';
                                    }
                                    ?>
                                </div>
                                <div class="notification-content">
                                    <h6><?= htmlspecialchars($notification['title']) ?></h6>
                                    <div class="notification-message">
                                        <?= htmlspecialchars(substr($notification['message'], 0, 100)) ?><?= strlen($notification['message']) > 100 ? '...' : '' ?>
                                    </div>
                                    <div class="notification-time">
                                        <?= timeAgo($notification['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 48px; height: 48px;">
                                <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <p>No new notifications</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="schedule.php" class="action-btn glass-card">
                <div class="action-icon">📅</div>
                <div>
                    <div style="font-weight: 600;">My Schedule</div>
                    <small style="color: var(--text-secondary);">View complete teaching schedule</small>
                </div>
            </a>

            <a href="subjects.php" class="action-btn glass-card">
                <div class="action-icon">📚</div>
                <div>
                    <div style="font-weight: 600;">My Subjects</div>
                    <small style="color: var(--text-secondary);">Manage assigned subjects</small>
                </div>
            </a>

            <a href="students.php" class="action-btn glass-card">
                <div class="action-icon">👥</div>
                <div>
                    <div style="font-weight: 600;">My Students</div>
                    <small style="color: var(--text-secondary);">View enrolled students</small>
                </div>
            </a>

            <a href="export.php" class="action-btn glass-card">
                <div class="action-icon">📊</div>
                <div>
                    <div style="font-weight: 600;">Reports</div>
                    <small style="color: var(--text-secondary);">Export schedules & data</small>
                </div>
            </a>

            <a href="profile.php" class="action-btn glass-card">
                <div class="action-icon">⚙️</div>
                <div>
                    <div style="font-weight: 600;">Profile Settings</div>
                    <small style="color: var(--text-secondary);">Update personal information</small>
                </div>
            </a>

            <a href="notifications.php" class="action-btn glass-card">
                <div class="action-icon">🔔</div>
                <div>
                    <div style="font-weight: 600;">All Notifications</div>
                    <small style="color: var(--text-secondary);">View all announcements</small>
                </div>
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize dashboard functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Update clock immediately and then every second
            updateClock();
            setInterval(updateClock, 1000);
            
            // Apply current theme
            applyCurrentTheme();
            
            // Add animation delays for staggered effect
            const animatedElements = document.querySelectorAll('.slide-up');
            animatedElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            // Handle sidebar toggle events
            handleSidebarToggle();

            // Auto-refresh functionality for real-time updates
            let refreshInterval;

            function startAutoRefresh() {
                refreshInterval = setInterval(async () => {
                    try {
                        await updateDashboardData();
                    } catch (error) {
                        console.error('Auto-refresh failed:', error);
                    }
                }, 120000); // Refresh every 2 minutes for faculty
            }

            function stopAutoRefresh() {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                }
            }

            // Update dashboard data via AJAX
            async function updateDashboardData() {
                try {
                    const response = await fetch('../includes/api/faculty-dashboard.php');
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update stats if changed
                        updateStatCards(data.stats);
                        
                        // Update notifications
                        if (data.hasNewNotifications) {
                            showNotificationToast('New notifications received!');
                        }
                    }
                } catch (error) {
                    console.error('Failed to update dashboard:', error);
                }
            }

            // Update stat cards with animation
            function updateStatCards(stats) {
                if (!stats) return;
                
                const statMappings = {
                    subjects_teaching: '.stat-number:nth-of-type(1)',
                    total_classes: '.stat-number:nth-of-type(2)', 
                    total_students: '.stat-number:nth-of-type(3)',
                    classrooms_used: '.stat-number:nth-of-type(4)'
                };

                Object.entries(statMappings).forEach(([key, selector]) => {
                    const element = document.querySelector(selector);
                    if (element && stats[key] !== undefined) {
                        const currentValue = parseInt(element.textContent);
                        const newValue = parseInt(stats[key]);
                        
                        if (currentValue !== newValue) {
                            // Animate the change
                            element.style.transform = 'scale(1.1)';
                            element.style.color = 'var(--accent-color)';
                            
                            setTimeout(() => {
                                element.textContent = newValue;
                                element.style.transform = 'scale(1)';
                                element.style.color = '';
                            }, 150);
                        }
                    }
                });
            }

            // Show toast notifications
            function showNotificationToast(message) {
                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: var(--glass-bg);
                    backdrop-filter: blur(20px);
                    border: 1px solid var(--glass-border);
                    border-radius: 12px;
                    padding: 1rem 1.5rem;
                    color: var(--text-primary);
                    box-shadow: var(--shadow-lg);
                    z-index: 1000;
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                `;
                toast.textContent = message;

                document.body.appendChild(toast);

                // Animate in
                setTimeout(() => {
                    toast.style.transform = 'translateX(0)';
                }, 100);

                // Remove after 5 seconds
                setTimeout(() => {
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        document.body.removeChild(toast);
                    }, 300);
                }, 5000);
            }

            // Page visibility API for pause/resume auto-refresh
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopAutoRefresh();
                } else {
                    startAutoRefresh();
                }
            });

            // Start auto-refresh
            startAutoRefresh();

            // Clock functionality
            function updateClock() {
                const now = new Date();
                const timeString = now.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const clockElement = document.querySelector('.live-clock');
                if (clockElement) {
                    clockElement.textContent = timeString;
                }
            }

            // Create and add live clock to welcome section
            const welcomeSubtitle = document.querySelector('.welcome-subtitle');
            if (welcomeSubtitle) {
                const clockSpan = document.createElement('span');
                clockSpan.className = 'live-clock';
                clockSpan.style.cssText = `
                    display: inline-block;
                    margin-left: 1rem;
                    padding: 0.25rem 0.75rem;
                    background: rgba(16, 185, 129, 0.1);
                    border-radius: 20px;
                    font-weight: 600;
                    color: var(--primary-color);
                `;
                welcomeSubtitle.appendChild(clockSpan);
                updateClock();
                setInterval(updateClock, 1000);
            }

            // Apply current theme
            function applyCurrentTheme() {
                const theme = localStorage.getItem('theme') || 'light';
                document.documentElement.setAttribute('data-theme', theme);
            }

            // Listen for theme changes
            window.addEventListener('themeChanged', function(event) {
                applyCurrentTheme();
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

            // Performance monitoring for faculty dashboard
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.1 });

            // Observe elements for animations
            document.querySelectorAll('.slide-up, .fade-in').forEach(element => {
                observer.observe(element);
            });

        }); // End of DOMContentLoaded
        
        // Sidebar toggle handling (no sticky header)
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
                if (sidebar.classList.contains('collapsed')) {
                    document.body.classList.add('sidebar-collapsed');
                }
                if (window.innerWidth <= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                }
            }

            // Handle window resize for responsive behavior
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                } else {
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

    </script>
</body>
</html>

<?php
/**
 * Helper function for time ago display
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>