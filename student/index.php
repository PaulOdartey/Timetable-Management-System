<?php
/**
 * Student Dashboard - Main Index Page
 * Timetable Management System
 * 
 * Professional dashboard for students with modern glassmorphism design
 * Displays personal schedule, enrolled subjects, academic progress, and class information
 */

// Start session and security checks
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../includes/profile-image-helper.php';

// Ensure user is logged in and has student role
User::requireLogin();
User::requireRole('student');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables for dashboard data
$dashboardData = [
    'personal_info' => [],
    'academic_stats' => [],
    'class_schedule' => [],
    'enrolled_subjects' => [],
    'recent_activities' => [],
    'upcoming_classes' => [],
    'notifications' => [],
    'academic_progress' => []
];

try {
    // Get student personal information
    $studentInfo = $db->fetchRow("
        SELECT s.*, u.email, u.username, u.last_login, u.created_at as account_created
        FROM students s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.user_id = ?
    ", [$userId]);

    if ($studentInfo) {
        $dashboardData['personal_info'] = $studentInfo;
        $studentId = $studentInfo['student_id'];
    } else {
        throw new Exception("Student profile not found");
    }

    // Get academic statistics
    $academicStats = $db->fetchRow("
        SELECT 
            COUNT(DISTINCT e.subject_id) as enrolled_subjects,
            COUNT(DISTINCT t.timetable_id) as total_classes,
            COUNT(DISTINCT t.faculty_id) as total_faculty,
            COUNT(DISTINCT t.classroom_id) as classrooms_attending,
            SUM(s.credits) as total_credits,
            AVG(TIMESTAMPDIFF(MINUTE, ts.start_time, ts.end_time)) as avg_class_duration
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN timetables t ON e.subject_id = t.subject_id 
            AND e.section = t.section 
            AND e.academic_year = t.academic_year 
            AND e.semester = t.semester
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE e.student_id = ? 
        AND e.status = 'enrolled'
        AND t.is_active = 1
        AND e.academic_year = '2025-2026'
        AND e.semester = 1
    ", [$studentId]);
    $dashboardData['academic_stats'] = $academicStats ?: [];

    // Get class schedule for current week
    $classSchedule = $db->fetchAll("
        SELECT 
            s.subject_code,
            s.subject_name,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
            c.room_number,
            c.building,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.slot_name,
            e.section,
            s.credits
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN timetables t ON e.subject_id = t.subject_id 
            AND e.section = t.section 
            AND e.academic_year = t.academic_year 
            AND e.semester = t.semester
        JOIN faculty f ON t.faculty_id = f.faculty_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE e.student_id = ? 
        AND e.status = 'enrolled'
        AND t.is_active = 1
        AND e.academic_year = '2025-2026'
        AND e.semester = 1
        ORDER BY 
            FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            ts.start_time
        LIMIT 15
    ", [$studentId]);
    $dashboardData['class_schedule'] = $classSchedule;

    // Get enrolled subjects with details
    $enrolledSubjects = $db->fetchAll("
        SELECT 
            s.*,
            e.enrollment_date,
            e.section,
            e.status as enrollment_status,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
            f.designation as faculty_designation,
            COUNT(DISTINCT t.timetable_id) as weekly_classes,
            GROUP_CONCAT(DISTINCT CONCAT(ts.day_of_week, ' ', DATE_FORMAT(ts.start_time, '%H:%i')) SEPARATOR ', ') as class_times
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        LEFT JOIN timetables t ON e.subject_id = t.subject_id 
            AND e.section = t.section 
            AND e.academic_year = t.academic_year 
            AND e.semester = t.semester
            AND t.is_active = 1
        LEFT JOIN faculty f ON t.faculty_id = f.faculty_id
        LEFT JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE e.student_id = ? 
        AND e.status = 'enrolled'
        AND e.academic_year = '2025-2026'
        AND e.semester = 1
        GROUP BY s.subject_id, e.enrollment_id
        ORDER BY s.subject_name
        LIMIT 8
    ", [$studentId]);
    $dashboardData['enrolled_subjects'] = $enrolledSubjects;

    // Get upcoming classes (next 3 days)
    $upcomingClasses = $db->fetchAll("
        SELECT 
            s.subject_code,
            s.subject_name,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
            c.room_number,
            c.building,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            e.section
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN timetables t ON e.subject_id = t.subject_id 
            AND e.section = t.section 
            AND e.academic_year = t.academic_year 
            AND e.semester = t.semester
        JOIN faculty f ON t.faculty_id = f.faculty_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE e.student_id = ? 
        AND e.status = 'enrolled'
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
        ORDER BY 
            FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            ts.start_time
        LIMIT 5
    ", [$studentId]);
    $dashboardData['upcoming_classes'] = $upcomingClasses;

    // Get recent activities (audit logs related to student)
    $recentActivities = $db->fetchAll("
        SELECT 
            al.*,
            CASE 
                WHEN al.action LIKE '%ENROLLMENT%' THEN 'Course Enrollment'
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

    // Get recent notifications for student
    $recentNotifications = $db->fetchAll("
        SELECT *
        FROM notifications
        WHERE (target_role = 'student' OR target_role = 'all' OR target_user_id = ?)
        AND is_active = 1
        ORDER BY created_at DESC
        LIMIT 5
    ", [$userId]);
    $dashboardData['notifications'] = $recentNotifications;

    // Get academic progress data
    $academicProgress = $db->fetchRow("
        SELECT 
            s.year_of_study,
            s.semester as current_semester,
            COUNT(DISTINCT e.subject_id) as subjects_this_semester,
            SUM(sub.credits) as credits_this_semester,
            (SELECT COUNT(DISTINCT subject_id) FROM subjects WHERE year_level <= s.year_of_study AND is_active = 1) as total_available_subjects
        FROM students s
        LEFT JOIN enrollments e ON s.student_id = e.student_id 
            AND e.status = 'enrolled' 
            AND e.academic_year = '2025-2026' 
            AND e.semester = s.semester
        LEFT JOIN subjects sub ON e.subject_id = sub.subject_id
        WHERE s.student_id = ?
        GROUP BY s.student_id
    ", [$studentId]);
    $dashboardData['academic_progress'] = $academicProgress ?: [];

} catch (Exception $e) {
    error_log("Student Dashboard Error: " . $e->getMessage());
    $error_message = "Unable to load dashboard data. Please try again later.";
}

// Set page title and current page for navigation
$pageTitle = "Student Dashboard";
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

        /* Dark Mode: make the whole page truly dark */
        [data-theme="dark"] body {
            background: linear-gradient(135deg, #0f172a 0%, #0b1220 100%);
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

        /* Dark Mode: override welcome card styles */
        [data-theme="dark"] .welcome-card {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.45) 0%, rgba(0, 0, 0, 0.25) 100%);
            border: 1px solid var(--glass-border);
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

        .student-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .student-avatar {
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

        .student-details h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .student-meta {
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon.faculty {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-icon.credits {
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

        /* Class Schedule Item */
        .class-item {
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

        /* Dark Mode: override class item styles */
        [data-theme="dark"] .class-item {
            background: rgba(255, 255, 255, 0.06);
            border: 2px solid rgba(59, 130, 246, 0.25);
        }

        [data-theme="dark"] .class-item:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .class-time {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            min-width: 100px;
            text-align: center;
        }

        .class-details {
            flex: 1;
            margin-left: 1rem;
            min-width: 0;
        }

        .class-details h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .class-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .class-location {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
        }

        .room-info {
            background: var(--primary-color-alpha);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8125rem;
            font-weight: 600;
        }

        /* Dark Mode: override room info styles */
        [data-theme="dark"] .room-info {
            background: rgba(59, 130, 246, 0.15);
            color: var(--text-primary);
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

        /* Dark Mode: override subject item styles */
        [data-theme="dark"] .subject-item {
            background: rgba(255, 255, 255, 0.06);
            border: 2px solid rgba(59, 130, 246, 0.25);
        }

        [data-theme="dark"] .subject-item:hover {
            background: rgba(255, 255, 255, 0.12);
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

        /* Progress Card */
        .progress-card {
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 2px solid rgba(59, 130, 246, 0.25);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.08);
        }

        /* Dark Mode: override progress card styles */
        [data-theme="dark"] .progress-card {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .progress-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .progress-label {
            font-weight: 600;
            color: var(--text-primary);
        }

        .progress-value {
            font-weight: 700;
            color: var(--primary-color);
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(59, 130, 246, 0.2);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        /* Dark Mode: override progress bar styles */
        [data-theme="dark"] .progress-bar {
            background: rgba(255, 255, 255, 0.12);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        /* Dark Mode: override progress fill styles */
        [data-theme="dark"] .progress-fill {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
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

        /* Dark Mode: override activity item styles */
        [data-theme="dark"] .activity-item {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
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

        .activity-icon.enrollment {
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
            border: 2px solid rgba(59, 130, 246, 0.35);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.08);
        }

        /* Dark Mode: override notification item styles */
        [data-theme="dark"] .notification-item {
            background: rgba(255, 255, 255, 0.06);
            border: 2px solid rgba(59, 130, 246, 0.25);
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

        /* Dark Mode: override action button styles */
        [data-theme="dark"] .action-btn {
            background: var(--glass-bg);
            border-color: var(--glass-border);
            color: var(--text-primary);
        }

        [data-theme="dark"] .action-btn:hover {
            background: rgba(255, 255, 255, 0.10);
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
            .student-info {
                text-align: center;
            }

            .stat-number {
                font-size: 2rem;
            }

            .class-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .class-location {
                align-self: flex-end;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            /* Stronger, more visible borders on mobile list-like cards */
            .class-item,
            .subject-item,
            .activity-item,
            .notification-item {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .class-item,
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
                        <?= htmlspecialchars($dashboardData['personal_info']['first_name'] ?? 'Student') ?>! 🎒
                    </h1>
                    <p class="welcome-subtitle">
                          Welcome to your academic dashboard. view your schedule, and stay updated with your courses.
                        <span class="live-clock"></span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon subjects">
                    📚
                </div>
                <div class="stat-number"><?= $dashboardData['academic_stats']['enrolled_subjects'] ?? 0 ?></div>
                <div class="stat-label">Enrolled Subjects</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon classes">
                    🕐
                </div>
                <div class="stat-number"><?= $dashboardData['academic_stats']['total_classes'] ?? 0 ?></div>
                <div class="stat-label">Weekly Classes</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon faculty">
                    👨‍🏫
                </div>
                <div class="stat-number"><?= $dashboardData['academic_stats']['total_faculty'] ?? 0 ?></div>
                <div class="stat-label">Instructors</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon credits">
                    🏆
                </div>
                <div class="stat-number"><?= $dashboardData['academic_stats']['total_credits'] ?? 0 ?></div>
                <div class="stat-label">Credit Hours</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Main Section -->
            <div class="management-section">
                <!-- Class Schedule Overview -->
                <div class="management-card glass-card">
                    <h3 class="section-title">
                        📅 My Class Schedule
                        <span style="font-size: 0.875rem; font-weight: normal; color: var(--text-secondary);">
                            (Current Semester)
                        </span>
                    </h3>

                    <?php if (!empty($dashboardData['class_schedule'])): ?>
                        <?php foreach (array_slice($dashboardData['class_schedule'], 0, 6) as $class): ?>
                            <div class="class-item">
                                <div class="class-time">
                                    <?= substr($class['day_of_week'], 0, 3) ?><br>
                                    <?= date('g:i A', strtotime($class['start_time'])) ?>
                                </div>
                                <div class="class-details">
                                    <h6><?= htmlspecialchars($class['subject_code'] . ' - ' . $class['subject_name']) ?></h6>
                                    <div class="class-meta">
                                        <span>👨‍🏫 <?= htmlspecialchars($class['faculty_name']) ?></span>
                                        • <span>Section <?= htmlspecialchars($class['section']) ?></span>
                                        • <span><?= date('g:i A', strtotime($class['start_time'])) ?> - <?= date('g:i A', strtotime($class['end_time'])) ?></span>
                                    </div>
                                </div>
                                <div class="class-location">
                                    <div class="room-info">
                                        📍 <?= htmlspecialchars($class['room_number']) ?>
                                    </div>
                                    <small style="color: var(--text-tertiary);"><?= htmlspecialchars($class['building']) ?></small>
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
                            <p style="font-size: 0.875rem;">Your class schedule will appear here once you're enrolled in subjects.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Enrolled Subjects -->
                <div class="management-card glass-card">
                    <h3 class="section-title">📖 My Subjects</h3>

                    <?php if (!empty($dashboardData['enrolled_subjects'])): ?>
                        <?php foreach (array_slice($dashboardData['enrolled_subjects'], 0, 4) as $subject): ?>
                            <div class="subject-item">
                                <div class="subject-avatar">
                                    <?= strtoupper(substr($subject['subject_code'], 0, 2)) ?>
                                </div>
                                <div class="subject-details">
                                    <h6><?= htmlspecialchars($subject['subject_name']) ?></h6>
                                    <div class="subject-meta">
                                        <span><?= htmlspecialchars($subject['subject_code']) ?></span>
                                        • <span><?= $subject['credits'] ?> Credits</span>
                                        • <span>Section <?= htmlspecialchars($subject['section']) ?></span>
                                    </div>
                                    <?php if (!empty($subject['faculty_name'])): ?>
                                        <div class="subject-meta">
                                            <span>👨‍🏫 <?= htmlspecialchars($subject['faculty_name']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="subject-stats">
                                        <div class="subject-stat">
                                            <div class="subject-stat-number"><?= $subject['weekly_classes'] ?></div>
                                            <div class="subject-stat-label">Classes/Week</div>
                                        </div>
                                        <div class="subject-stat">
                                            <div class="subject-stat-number"><?= $subject['credits'] ?></div>
                                            <div class="subject-stat-label">Credits</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($dashboardData['enrolled_subjects']) > 4): ?>
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
                            <p>No subjects enrolled yet</p>
                            <p style="font-size: 0.875rem;">Contact your academic advisor for course enrollment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar Section -->
            <div class="sidebar-section">
                <!-- Academic Progress -->
                <div class="management-card glass-card">
                    <h3 class="section-title">📊 Academic Progress</h3>
                    
                    <div class="progress-card">
                        <div class="progress-item">
                            <span class="progress-label">Current Year</span>
                            <span class="progress-value">Year <?= $dashboardData['academic_progress']['year_of_study'] ?? 1 ?></span>
                        </div>
                        <div class="progress-item">
                            <span class="progress-label">Current Semester</span>
                            <span class="progress-value">Semester <?= $dashboardData['academic_progress']['current_semester'] ?? 1 ?></span>
                        </div>
                        <div class="progress-item">
                            <span class="progress-label">Subjects This Semester</span>
                            <span class="progress-value"><?= $dashboardData['academic_progress']['subjects_this_semester'] ?? 0 ?></span>
                        </div>
                        <div class="progress-item">
                            <span class="progress-label">Credit Hours</span>
                            <span class="progress-value"><?= $dashboardData['academic_progress']['credits_this_semester'] ?? 0 ?></span>
                        </div>
                        
                        <?php 
                        $totalSubjects = $dashboardData['academic_progress']['total_available_subjects'] ?? 1;
                        $completedSubjects = $dashboardData['academic_progress']['subjects_this_semester'] ?? 0;
                        $progressPercentage = min(100, ($completedSubjects / max(1, $totalSubjects)) * 100);
                        ?>
                        
                        <div class="progress-item">
                            <span class="progress-label">Academic Progress</span>
                            <span class="progress-value"><?= round($progressPercentage) ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $progressPercentage ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Classes -->
                <div class="management-card glass-card">
                    <h3 class="section-title">⏰ Upcoming Classes</h3>
                    
                    <?php if (!empty($dashboardData['upcoming_classes'])): ?>
                        <?php foreach ($dashboardData['upcoming_classes'] as $class): ?>
                            <div class="class-item">
                                <div class="class-time">
                                    <?= date('g:i A', strtotime($class['start_time'])) ?>
                                </div>
                                <div class="class-details">
                                    <h6><?= htmlspecialchars($class['subject_code']) ?></h6>
                                    <div class="class-meta">
                                        <span><?= substr($class['day_of_week'], 0, 3) ?></span>
                                        • <span><?= htmlspecialchars($class['room_number']) ?></span>
                                        • <span><?= htmlspecialchars($class['faculty_name']) ?></span>
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
                                    strpos($activity['activity_type'], 'Enrollment') !== false ? 'enrollment' : 
                                    (strpos($activity['activity_type'], 'Profile') !== false ? 'profile' : 'login') 
                                ?>">
                                    <?php 
                                    if (strpos($activity['activity_type'], 'Enrollment') !== false) {
                                        echo '📝';
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
            <a href="timetable.php" class="action-btn glass-card">
                <div class="action-icon">📅</div>
                <div>
                    <div style="font-weight: 600;">My Timetable</div>
                    <small style="color: var(--text-secondary);">View complete class timetable</small>
                </div>
            </a>

            <a href="subjects.php" class="action-btn glass-card">
                <div class="action-icon">📚</div>
                <div>
                    <div style="font-weight: 600;">My Subjects</div>
                    <small style="color: var(--text-secondary);">View enrolled courses</small>
                </div>
            </a>

            <a href="progress.php" class="action-btn glass-card">
                <div class="action-icon">📊</div>
                <div>
                    <div style="font-weight: 600;">Academic Progress</div>
                    <small style="color: var(--text-secondary);">Track your performance</small>
                </div>
            </a>

            <a href="export.php" class="action-btn glass-card">
                <div class="action-icon">📄</div>
                <div>
                    <div style="font-weight: 600;">Export</div>
                    <small style="color: var(--text-secondary);">Export schedules</small>
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

            // Handle sidebar toggle events (without sticky header)
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
                }, 180000); // Refresh every 3 minutes for students
            }

            function stopAutoRefresh() {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                }
            }

            // Update dashboard data via AJAX
            async function updateDashboardData() {
                try {
                    const response = await fetch('../includes/api/student-dashboard.php');
                    
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
                        
                        // Update progress bar
                        updateProgressBar(data.academicProgress);
                    }
                } catch (error) {
                    console.error('Failed to update dashboard:', error);
                }
            }

            // Update stat cards with animation
            function updateStatCards(stats) {
                if (!stats) return;
                
                const statMappings = {
                    enrolled_subjects: '.stat-number:nth-of-type(1)',
                    total_classes: '.stat-number:nth-of-type(2)', 
                    total_faculty: '.stat-number:nth-of-type(3)',
                    total_credits: '.stat-number:nth-of-type(4)'
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

            // Update progress bar
            function updateProgressBar(progressData) {
                if (!progressData) return;
                
                const progressFill = document.querySelector('.progress-fill');
                if (progressFill && progressData.percentage !== undefined) {
                    progressFill.style.width = progressData.percentage + '%';
                }
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
                    background: rgba(59, 130, 246, 0.1);
                    border-radius: 20px;
                    font-weight: 600;
                    color: var(--primary-color);
                `;
                welcomeSubtitle.appendChild(clockSpan);
                updateClock();
                setInterval(updateClock, 1000);
            }
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

        // Apply current theme
        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Listen for theme changes
        window.addEventListener('themeChanged', function(event) {
            applyCurrentTheme();
        });

        // Removed global keyboard shortcuts for navigation on the student dashboard

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

        // Performance monitoring for student dashboard
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

        // Academic progress visualization
        const progressElements = document.querySelectorAll('.progress-fill');
        progressElements.forEach(progress => {
            const width = progress.style.width;
            progress.style.width = '0%';
            setTimeout(() => {
                progress.style.width = width;
            }, 500);
        });

        // Interactive class schedule features
        document.querySelectorAll('.class-item').forEach(item => {
            item.addEventListener('click', function() {
                // Add subtle click feedback
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });

        // Subject card interactions
        document.querySelectorAll('.subject-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                const avatar = this.querySelector('.subject-avatar');
                if (avatar) {
                    avatar.style.transform = 'rotate(5deg) scale(1.1)';
                }
            });

            item.addEventListener('mouseleave', function() {
                const avatar = this.querySelector('.subject-avatar');
                if (avatar) {
                    avatar.style.transform = 'rotate(0deg) scale(1)';
                }
            });
        });

        // Enhanced notification interactions
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                // Mark as read visual feedback
                this.style.opacity = '0.7';
                this.style.transform = 'translateX(10px)';
                
                // Could integrate with backend to mark as read
                setTimeout(() => {
                    this.style.opacity = '';
                    this.style.transform = '';
                }, 200);
            });
        });

        // Academic statistics counter animation
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            
            counters.forEach(counter => {
                const target = parseInt(counter.textContent);
                const increment = target / 30; // Animate over 30 frames
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current);
                }, 50);
            });
        }

        // Start counter animation after page load
        setTimeout(animateCounters, 1000);

        // Smart study schedule recommendations
        function generateStudyRecommendations() {
            const now = new Date();
            const currentHour = now.getHours();
            const dayOfWeek = now.getDay();
            
            // This could be expanded with actual schedule analysis
            if (currentHour >= 9 && currentHour <= 17 && dayOfWeek >= 1 && dayOfWeek <= 5) {
                // During academic hours on weekdays
                console.log('Academic hours detected - showing study recommendations');
            }
        }

        // Mobile-specific optimizations
        if (window.innerWidth <= 768) {
            // Optimize touch interactions for mobile
            document.querySelectorAll('.action-btn').forEach(btn => {
                btn.style.minHeight = '48px'; // Ensure touch-friendly size
            });

            // Reduce animation complexity on mobile for better performance
            document.querySelectorAll('.glass-card').forEach(card => {
                card.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });

                card.addEventListener('touchend', function() {
                    this.style.transform = '';
                });
            });
        }

        // Academic calendar integration (placeholder for future enhancement)
        function checkUpcomingDeadlines() {
            // This could integrate with academic calendar
            const upcomingClasses = document.querySelectorAll('.class-item');
            
            upcomingClasses.forEach(classItem => {
                // Add priority indicators based on time until class
                const timeElement = classItem.querySelector('.class-time');
                if (timeElement) {
                    // Could add visual indicators for classes starting soon
                }
            });
        }

        // Initialize additional features
        setTimeout(() => {
            generateStudyRecommendations();
            checkUpcomingDeadlines();
        }, 2000);

        // Enhanced error handling for AJAX requests
        window.addEventListener('online', function() {
            console.log('Connection restored - resuming dashboard updates');
            if (!refreshInterval) {
                startAutoRefresh();
            }
        });

        window.addEventListener('offline', function() {
            console.log('Connection lost - pausing dashboard updates');
            stopAutoRefresh();
        });

        // Add smooth scrolling behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Performance monitoring
        if ('performance' in window && 'measureUserAgentSpecificMemory' in performance) {
            // Monitor memory usage for large dashboards
            setInterval(() => {
                if (performance.memory && performance.memory.usedJSHeapSize > 50 * 1024 * 1024) {
                    console.warn('High memory usage detected - consider optimizing dashboard');
                }
            }, 60000);
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
