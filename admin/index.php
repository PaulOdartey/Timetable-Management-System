<?php
/**
 * Admin Dashboard - Main Index Page
 * Timetable Management System
 * 
 * Professional dashboard for administrators with modern glassmorphism design
 * Displays system overview, user management, timetable stats, and quick actions
 */

// Start session and security checks
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../includes/profile-image-helper.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables for dashboard data
$dashboardData = [
    'personal_info' => [],
    'system_stats' => [],
    'user_stats' => [],
    'timetable_stats' => [],
    'recent_activities' => [],
    'pending_registrations' => [],
    'recent_notifications' => [],
    'department_overview' => []
];

try {
    // Get admin personal information
    $adminInfo = $db->fetchRow("
        SELECT a.*, u.email, u.username, u.last_login, u.created_at as account_created
        FROM admin_profiles a 
        JOIN users u ON a.user_id = u.user_id 
        WHERE a.user_id = ?
    ", [$userId]);

    if ($adminInfo) {
        $dashboardData['personal_info'] = $adminInfo;
    }

    // Get system statistics
    $systemStats = $db->fetchRow("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE status = 'active') as total_active_users,
            (SELECT COUNT(*) FROM users WHERE status = 'pending' AND email_verified = 1) as pending_users,
            (SELECT COUNT(*) FROM subjects WHERE is_active = 1) as total_subjects,
            (SELECT COUNT(*) FROM classrooms WHERE is_active = 1) as total_classrooms,
            (SELECT COUNT(*) FROM timetables WHERE is_active = 1) as active_timetables,
            (SELECT COUNT(*) FROM departments WHERE is_active = 1) as total_departments
    ");
    $dashboardData['system_stats'] = $systemStats ?: [];

    // Get user statistics by role
    $userStats = $db->fetchAll("
        SELECT 
            role,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_login_count
        FROM users 
        GROUP BY role
        ORDER BY count DESC
    ");
    $dashboardData['user_stats'] = $userStats;

    // Get timetable statistics
    $timetableStats = $db->fetchRow("
        SELECT 
            COUNT(*) as total_schedules,
            COUNT(DISTINCT subject_id) as scheduled_subjects,
            COUNT(DISTINCT faculty_id) as active_faculty,
            COUNT(DISTINCT classroom_id) as utilized_classrooms,
            AVG(TIMESTAMPDIFF(MINUTE, ts.start_time, ts.end_time)) as avg_class_duration
        FROM timetables t
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE t.is_active = 1
        AND t.academic_year = '2025-2026'
        AND t.semester = 1
    ");
    $dashboardData['timetable_stats'] = $timetableStats ?: [];

    // Get pending registrations
    $pendingRegistrations = $db->fetchAll("
        SELECT u.*, 
               CASE 
                   WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                   WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                   ELSE u.username
               END as full_name,
               CASE 
                   WHEN u.role = 'student' THEN s.department
                   WHEN u.role = 'faculty' THEN f.department
                   ELSE NULL
               END as department
        FROM users u
        LEFT JOIN students s ON u.user_id = s.user_id
        LEFT JOIN faculty f ON u.user_id = f.user_id
        WHERE u.status = 'pending'
        AND u.email_verified = 1
        ORDER BY u.created_at ASC
        LIMIT 10
    ");
    $dashboardData['pending_registrations'] = $pendingRegistrations;

    // Get recent activities (audit logs)
    $recentActivities = $db->fetchAll("
        SELECT 
            al.*,
            u.username,
            CASE 
                WHEN u.role = 'admin' THEN CONCAT(ap.first_name, ' ', ap.last_name)
                WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                ELSE u.username
            END as user_name
        FROM audit_logs al
        JOIN users u ON al.user_id = u.user_id
        LEFT JOIN admin_profiles ap ON u.user_id = ap.user_id
        LEFT JOIN faculty f ON u.user_id = f.user_id
        LEFT JOIN students s ON u.user_id = s.user_id
        WHERE al.action IN ('CREATE_USER', 'UPDATE_PROFILE', 'TIMETABLE_CREATE', 'TIMETABLE_UPDATE', 'TIMETABLE_DELETE')
        ORDER BY al.timestamp DESC
        LIMIT 10
    ");
    $dashboardData['recent_activities'] = $recentActivities;

    // Get recent notifications
    $recentNotifications = $db->fetchAll("
        SELECT *
        FROM notifications
        WHERE (target_role = 'admin' OR target_role = 'all' OR target_user_id = ?)
        AND is_active = 1
        ORDER BY created_at DESC
        LIMIT 5
    ", [$userId]);
    $dashboardData['recent_notifications'] = $recentNotifications;

    // Get department overview
    $departmentOverview = $db->fetchAll("
        SELECT 
            d.department_name,
            d.department_code,
            COUNT(DISTINCT CASE WHEN u.role = 'faculty' AND u.status = 'active' THEN u.user_id END) as faculty_count,
            COUNT(DISTINCT CASE WHEN u.role = 'student' AND u.status = 'active' THEN u.user_id END) as student_count,
            COUNT(DISTINCT s.subject_id) as subject_count,
            COUNT(DISTINCT c.classroom_id) as classroom_count
        FROM departments d
        LEFT JOIN users u ON d.department_id = u.department_id
        LEFT JOIN subjects s ON d.department_id = s.department_id AND s.is_active = 1
        LEFT JOIN classrooms c ON d.department_id = c.department_id AND c.is_active = 1
        WHERE d.is_active = 1
        GROUP BY d.department_id
        ORDER BY d.department_name
        LIMIT 8
    ");
    $dashboardData['department_overview'] = $departmentOverview;

} catch (Exception $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    $error_message = "Unable to load dashboard data. Please try again later.";
}

// Set page title and current page for navigation
$pageTitle = "Admin Dashboard";
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
        /* CSS Variables for consistent theming */
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
            --sidebar-collapsed-width: 70px;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .admin-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .admin-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .admin-details h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .admin-meta {
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

        .stat-icon.users {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon.subjects {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon.classrooms {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-icon.timetables {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .stat-icon.pending {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .stat-icon.departments {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
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

        .pending-user-item {
            display: flex;
            align-items: center;
            justify-content: between;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 2px solid rgba(59, 130, 246, 0.4);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
            transition: all 0.3s ease;
        }

        .pending-user-item:hover {
            background: rgba(255, 255, 255, 0.7);
            border: 2px solid rgba(59, 130, 246, 0.7);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
            transform: translateX(4px);
        }

        .pending-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .pending-user-details {
            flex: 1;
            min-width: 0;
        }

        .pending-user-details h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .pending-user-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .pending-user-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .btn-approve {
            padding: 0.375rem 0.75rem;
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-approve:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-reject {
            padding: 0.375rem 0.75rem;
            background: var(--error-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-reject:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        /* Activity Feed */
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 2px solid rgba(59, 130, 246, 0.4);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: rgba(255, 255, 255, 0.7);
            border: 2px solid rgba(59, 130, 246, 0.7);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
            transform: translateY(-2px);
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

        .activity-icon.create {
            background: var(--success-color);
            color: white;
        }

        .activity-icon.update {
            background: var(--primary-color);
            color: white;
        }

        .activity-icon.delete {
            background: var(--error-color);
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
            border: 2px solid rgba(59, 130, 246, 0.4);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background: rgba(255, 255, 255, 0.7);
            border: 2px solid rgba(59, 130, 246, 0.7);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
            transform: translateY(-2px);
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

        /* Department Overview */
        .department-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .department-card {
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.5);
            border: 2px solid rgba(59, 130, 246, 0.4);
            border-radius: 16px;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
            transition: all 0.3s ease;
        }

        .department-card:hover {
            background: rgba(255, 255, 255, 0.7);
            border: 2px solid rgba(59, 130, 246, 0.7);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
            transform: translateY(-2px);
        }

        .department-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .department-avatar {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
        }

        .department-info h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .department-code {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .department-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .department-stat {
            text-align: center;
        }

        .department-stat-number {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .department-stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
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
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .department-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {

            .admin-info {
                text-align: center;
            }

            .stat-number {
                font-size: 2rem;
            }

            .pending-user-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .pending-user-actions {
                width: 100%;
                justify-content: center;
            }


            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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

        /* Toast notification styles */
        .toast-notification {
            font-weight: 500;
            font-size: 0.875rem;
            max-width: 300px;
            word-wrap: break-word;
        }

        /* Enhanced loading states */
        .loading-shimmer {
            background: linear-gradient(90deg, 
                rgba(255, 255, 255, 0.1) 25%, 
                rgba(255, 255, 255, 0.3) 50%, 
                rgba(255, 255, 255, 0.1) 75%
            );
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
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

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            :root {
                --text-primary: #000000;
                --text-secondary: #333333;
                --border-color: #666666;
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Dashboard Update Animations */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        /* Critical Alerts Styling */
        .critical-alerts-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            max-width: 350px;
        }

        .alert {
            margin-bottom: 8px;
            animation: slideInRight 0.3s ease-out;
        }

        .alert-warning {
            border-left-color: #f59e0b !important;
        }

        .alert-error {
            border-left-color: #ef4444 !important;
        }

        .alert-info {
            border-left-color: #3b82f6 !important;
        }

        /* Stat card update animation */
        .stat-number {
            transition: all 0.3s ease;
        }

        .stat-number.updating {
            animation: pulse 0.6s ease-in-out;
            color: var(--primary-color);
        }

        /* Mobile responsive alerts */
        @media (max-width: 768px) {
            .critical-alerts-container {
                right: 10px;
                left: 10px;
                max-width: none;
                top: 70px;
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
                       <?= htmlspecialchars($dashboardData['personal_info']['first_name'] ?? 'Administrator') ?>!🚀 
                    </h1>
                    <p class="welcome-subtitle">
                        Welcome to the admin dashboard. Monitor system performance and manage your timetable system efficiently.
                        <span class="live-clock"></span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon users">
                    👥
                </div>
                <div class="stat-number"><?= $dashboardData['system_stats']['total_active_users'] ?? 0 ?></div>
                <div class="stat-label">Active Users</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon pending">
                    ⏳
                </div>
                <div class="stat-number"><?= $dashboardData['system_stats']['pending_users'] ?? 0 ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon subjects">
                    📚
                </div>
                <div class="stat-number"><?= $dashboardData['system_stats']['total_subjects'] ?? 0 ?></div>
                <div class="stat-label">Total Subjects</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon classrooms">
                    🏢
                </div>
                <div class="stat-number"><?= $dashboardData['system_stats']['total_classrooms'] ?? 0 ?></div>
                <div class="stat-label">Classrooms</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon timetables">
                    📅
                </div>
                <div class="stat-number"><?= $dashboardData['system_stats']['active_timetables'] ?? 0 ?></div>
                <div class="stat-label">Active Schedules</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon departments">
                    🏛️
                </div>
                <div class="stat-number"><?= $dashboardData['system_stats']['total_departments'] ?? 0 ?></div>
                <div class="stat-label">Departments</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Management Section -->
            <div class="management-section">
                <!-- Pending Registrations -->
                <div class="management-card glass-card">
                    <h3 class="section-title">
                        ⏳ Pending Registrations
                        <span style="font-size: 0.875rem; font-weight: normal; color: var(--text-secondary);">
                            (<?= count($dashboardData['pending_registrations']) ?> awaiting approval)
                        </span>
                    </h3>

                    <?php if (!empty($dashboardData['pending_registrations'])): ?>
                        <?php foreach (array_slice($dashboardData['pending_registrations'], 0, 5) as $user): ?>
                            <div class="pending-user-item">
                                <div class="pending-user-avatar">
                                    <?php if (!empty($user['full_name'])): ?>
                                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                    <?php else: ?>
                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="pending-user-details">
                                    <h6><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></h6>
                                    <div class="pending-user-meta">
                                        <span><?= ucfirst($user['role']) ?></span> • 
                                        <span><?= htmlspecialchars($user['email']) ?></span>
                                        <?php if (!empty($user['department'])): ?>
                                            • <span><?= htmlspecialchars($user['department']) ?></span>
                                        <?php endif; ?>
                                        <br><small>Registered: <?= timeAgo($user['created_at']) ?></small>
                                    </div>
                                </div>
                                <div class="pending-user-actions">
                                    <button class="btn-approve" onclick="approveUser(<?= $user['user_id'] ?>)" title="Approve">
                                        ✓
                                    </button>
                                    <button class="btn-reject" onclick="rejectUser(<?= $user['user_id'] ?>)" title="Reject">
                                        ✗
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($dashboardData['pending_registrations']) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="users/pending.php" class="action-btn" style="display: inline-flex; padding: 0.5rem 1rem;">
                                    <span>View All <?= count($dashboardData['pending_registrations']) ?> Pending</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 48px; height: 48px;">
                                <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <p>No pending registrations! 🎉</p>
                            <p style="font-size: 0.875rem;">All user registrations have been processed.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="management-card glass-card">
                    <h3 class="section-title">📈 Recent Activities</h3>

                    <?php if (!empty($dashboardData['recent_activities'])): ?>
                        <?php foreach (array_slice($dashboardData['recent_activities'], 0, 5) as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?= strpos($activity['action'], 'CREATE') !== false ? 'create' : (strpos($activity['action'], 'DELETE') !== false ? 'delete' : 'update') ?>">
                                    <?php if (strpos($activity['action'], 'CREATE') !== false): ?>
                                        ➕
                                    <?php elseif (strpos($activity['action'], 'DELETE') !== false): ?>
                                        🗑️
                                    <?php else: ?>
                                        ✏️
                                    <?php endif; ?>
                                </div>
                                <div class="activity-content">
                                    <h6><?= htmlspecialchars($activity['user_name'] ?: $activity['username']) ?></h6>
                                    <div class="activity-message">
                                        <?php
                                        $actionText = str_replace('_', ' ', strtolower($activity['action']));
                                        $actionText = ucwords($actionText);
                                        echo htmlspecialchars($activity['description'] ?: $actionText);
                                        ?>
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
            </div>

            <!-- Sidebar Section -->
            <div class="sidebar-section">
                <!-- User Statistics -->
                <div class="management-card glass-card">
                    <h3 class="section-title">👥 User Distribution</h3>
                    
                    <?php if (!empty($dashboardData['user_stats'])): ?>
                        <div style="margin-top: 1rem;">
                            <canvas id="userStatsChart" width="300" height="200"></canvas>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <?php foreach ($dashboardData['user_stats'] as $stat): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                    <span style="font-weight: 500; text-transform: capitalize;"><?= htmlspecialchars($stat['role']) ?>s</span>
                                    <span style="font-weight: 600; color: var(--primary-color);"><?= $stat['active_count'] ?>/<?= $stat['count'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Notifications -->
                <div class="management-card glass-card">
                    <h3 class="section-title">🔔 System Notifications</h3>

                    <?php if (!empty($dashboardData['recent_notifications'])): ?>
                        <?php foreach ($dashboardData['recent_notifications'] as $notification): ?>
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

        <!-- Department Overview -->
        <?php if (!empty($dashboardData['department_overview'])): ?>
            <div class="management-card glass-card">
                <h3 class="section-title">🏛️ Department Overview</h3>
                
                <div class="department-grid">
                    <?php foreach ($dashboardData['department_overview'] as $dept): ?>
                        <div class="department-card">
                            <div class="department-header">
                                <div class="department-avatar">
                                    <?= strtoupper(substr($dept['department_code'], 0, 2)) ?>
                                </div>
                                <div class="department-info">
                                    <h6><?= htmlspecialchars($dept['department_name']) ?></h6>
                                    <div class="department-code"><?= htmlspecialchars($dept['department_code']) ?></div>
                                </div>
                            </div>
                            
                            <div class="department-stats">
                                <div class="department-stat">
                                    <div class="department-stat-number"><?= $dept['faculty_count'] ?></div>
                                    <div class="department-stat-label">Faculty</div>
                                </div>
                                <div class="department-stat">
                                    <div class="department-stat-number"><?= $dept['student_count'] ?></div>
                                    <div class="department-stat-label">Students</div>
                                </div>
                                <div class="department-stat">
                                    <div class="department-stat-number"><?= $dept['subject_count'] ?></div>
                                    <div class="department-stat-label">Subjects</div>
                                </div>
                                <div class="department-stat">
                                    <div class="department-stat-number"><?= $dept['classroom_count'] ?></div>
                                    <div class="department-stat-label">Classrooms</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="users/" class="action-btn glass-card">
                <div class="action-icon">👥</div>
                <div>
                    <div style="font-weight: 600;">User Management</div>
                    <small style="color: var(--text-secondary);">Manage all system users</small>
                </div>
            </a>

            <a href="timetable/" class="action-btn glass-card">
                <div class="action-icon">📅</div>
                <div>
                    <div style="font-weight: 600;">Timetable Management</div>
                    <small style="color: var(--text-secondary);">Create & manage schedules</small>
                </div>
            </a>

            <a href="subjects/" class="action-btn glass-card">
                <div class="action-icon">📚</div>
                <div>
                    <div style="font-weight: 600;">Subjects & Courses</div>
                    <small style="color: var(--text-secondary);">Manage academic subjects</small>
                </div>
            </a>

            <a href="classrooms/" class="action-btn glass-card">
                <div class="action-icon">🏢</div>
                <div>
                    <div style="font-weight: 600;">Classroom Management</div>
                    <small style="color: var(--text-secondary);">Manage facilities</small>
                </div>
            </a>

            <a href="reports/" class="action-btn glass-card">
                <div class="action-icon">📊</div>
                <div>
                    <div style="font-weight: 600;">Reports & Analytics</div>
                    <small style="color: var(--text-secondary);">System insights</small>
                </div>
            </a>

            <a href="settings/" class="action-btn glass-card">
                <div class="action-icon">⚙️</div>
                <div>
                    <div style="font-weight: 600;">System Settings</div>
                    <small style="color: var(--text-secondary);">Configure system</small>
                </div>
            </a>

            <a href="notifications/" class="action-btn glass-card">
                <div class="action-icon">🔔</div>
                <div>
                    <div style="font-weight: 600;">Notifications</div>
                    <small style="color: var(--text-secondary);">System announcements</small>
                </div>
            </a>

            <a href="../backup/" class="action-btn glass-card">
                <div class="action-icon">💾</div>
                <div>
                    <div style="font-weight: 600;">Backup & Maintenance</div>
                    <small style="color: var(--text-secondary);">System maintenance</small>
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

            // Sidebar handlers removed to prevent unintended expansion

            // Initialize charts
            initializeUserStatsChart();

            // Auto-refresh functionality for real-time updates
            let refreshInterval;

            function startAutoRefresh() {
                refreshInterval = setInterval(async () => {
                    try {
                        await updateDashboardData();
                    } catch (error) {
                        console.error('Auto-refresh failed:', error);
                    }
                }, 60000); // Refresh every 60 seconds for admin
            }

            function stopAutoRefresh() {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                }
            }

            // Update dashboard data via AJAX
            async function updateDashboardData() {
                try {
                    const response = await fetch('../includes/api/dashboard.php');
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update stats if needed
                        updateStatCards(data.stats);
                        
                        // Update pending count
                        if (data.pendingCount !== undefined) {
                            updatePendingCount(data.pendingCount);
                        }
                        
                        // Show toast notification for new items
                        if (data.hasNewPendingUsers) {
                            showNotificationToast('New user registrations pending approval!');
                        }
                    }
                } catch (error) {
                    console.error('Failed to update dashboard:', error);
                }
            }

            // Update stat cards with animation
            function updateStatCards(stats) {
                if (!stats) return;
                
                // Update each stat with animation
                const statMappings = {
                    total_active_users: 'users',
                    pending_users: 'pending',
                    total_subjects: 'subjects',
                    active_timetables: 'timetables'
                };

                Object.entries(stats).forEach(([key, value]) => {
                    const mappedKey = statMappings[key] || key;
                    const statElement = document.querySelector(`[data-stat="${mappedKey}"]`);
                    if (statElement && statElement.textContent !== value.toString()) {
                        animateNumberChange(statElement, parseInt(statElement.textContent) || 0, value);
                        
                        // Add pulse animation if count increased
                        if (value > parseInt(statElement.textContent)) {
                            statElement.parentElement.style.animation = 'pulse 0.5s ease';
                            setTimeout(() => {
                                statElement.parentElement.style.animation = '';
                            }, 500);
                        }
                    }
                });
            }

            // Update pending count specifically
            function updatePendingCount(count) {
                const pendingElements = document.querySelectorAll('[data-stat="pending"]');
                pendingElements.forEach(element => {
                    if (element.textContent !== count.toString()) {
                        // Animate the change
                        element.style.transform = 'scale(1.1)';
                        element.style.color = 'var(--accent-color)';
                        
                        setTimeout(() => {
                            element.textContent = count;
                            element.style.transform = 'scale(1)';
                            element.style.color = '';
                        }, 150);
                    }
                });
            }

            // Animate number changes
            function animateNumberChange(element, from, to) {
                const duration = 1000;
                const stepTime = 50;
                const steps = duration / stepTime;
                const increment = (to - from) / steps;
                let current = from;

                const timer = setInterval(() => {
                    current += increment;
                    if ((increment > 0 && current >= to) || (increment < 0 && current <= to)) {
                        current = to;
                        clearInterval(timer);
                    }
                    element.textContent = Math.round(current);
                }, stepTime);
            }

            // Show toast notifications
            function showNotificationToast(message) {
                // Create toast element
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
                    background: rgba(102, 126, 234, 0.1);
                    border-radius: 20px;
                    font-weight: 600;
                    color: var(--primary-color);
                `;
                welcomeSubtitle.appendChild(clockSpan);
                updateClock();
                setInterval(updateClock, 1000);
            }
        });

        // Initialize User Statistics Chart
        function initializeUserStatsChart() {
            const ctx = document.getElementById('userStatsChart');
            if (!ctx) return;

            const userStats = <?= json_encode($dashboardData['user_stats']) ?>;
            
            const data = {
                labels: userStats.map(stat => stat.role.charAt(0).toUpperCase() + stat.role.slice(1) + 's'),
                datasets: [{
                    data: userStats.map(stat => stat.active_count),
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',  // Blue for admin
                        'rgba(16, 185, 129, 0.8)',  // Green for faculty
                        'rgba(245, 158, 11, 0.8)',  // Orange for students
                    ],
                    borderColor: [
                        'rgba(59, 130, 246, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)',
                    ],
                    borderWidth: 2
                }]
            };

            new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = userStats.reduce((sum, stat) => sum + stat.active_count, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '70%',
                    elements: {
                        arc: {
                            borderWidth: 2,
                            hoverBorderWidth: 3
                        }
                    }
                }
            });
        }



        // User Management Functions
        async function approveUser(userId) {
            if (!confirm('Are you sure you want to approve this user?')) {
                return;
            }

            try {
                const response = await fetch('../includes/api/user-management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'approve',
                        user_id: userId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotificationToast('User approved successfully!');
                    // Remove the user from the pending list
                    const userItem = document.querySelector(`[onclick="approveUser(${userId})"]`).closest('.pending-user-item');
                    userItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    userItem.style.opacity = '0';
                    userItem.style.transform = 'translateX(-20px)';
                    
                    setTimeout(() => {
                        userItem.remove();
                        // Update counter
                        updatePendingCounter();
                    }, 300);
                } else {
                    alert('Error: ' + (data.message || 'Failed to approve user'));
                }
            } catch (error) {
                console.error('Error approving user:', error);
                alert('Failed to approve user. Please try again.');
            }
        }

        async function rejectUser(userId) {
            if (!confirm('Are you sure you want to reject this user? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('../includes/api/user-management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'reject',
                        user_id: userId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotificationToast('User registration rejected.');
                    // Remove the user from the pending list
                    const userItem = document.querySelector(`[onclick="rejectUser(${userId})"]`).closest('.pending-user-item');
                    userItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    userItem.style.opacity = '0';
                    userItem.style.transform = 'translateX(-20px)';
                    
                    setTimeout(() => {
                        userItem.remove();
                        // Update counter
                        updatePendingCounter();
                    }, 300);
                } else {
                    alert('Error: ' + (data.message || 'Failed to reject user'));
                }
            } catch (error) {
                console.error('Error rejecting user:', error);
                alert('Failed to reject user. Please try again.');
            }
        }

        function updatePendingCounter() {
            const pendingItems = document.querySelectorAll('.pending-user-item');
            const counterElement = document.querySelector('.section-title span');
            if (counterElement) {
                counterElement.textContent = `(${pendingItems.length} awaiting approval)`;
            }
            
            
            // Show empty state if no pending users
            if (pendingItems.length === 0) {
                const managementCard = document.querySelector('.pending-user-item').closest('.management-card');
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 48px; height: 48px;">
                        <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <p>No pending registrations! 🎉</p>
                    <p style="font-size: 0.875rem;">All user registrations have been processed.</p>
                `;
                
                // Remove all pending items container and add empty state
                const pendingContainer = document.querySelector('.pending-user-item').parentElement;
                pendingContainer.innerHTML = '';
                pendingContainer.appendChild(emptyState);
            }
        }

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + U for Users
            if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
                e.preventDefault();
                window.location.href = 'users/';
            }

            // Ctrl/Cmd + T for Timetables
            if ((e.ctrlKey || e.metaKey) && e.key === 't') {
                e.preventDefault();
                window.location.href = 'timetable/';
            }

            // Ctrl/Cmd + S for Settings
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                window.location.href = 'settings/';
            }

            // Ctrl/Cmd + R for Reports
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                window.location.href = 'reports/';
            }
        });

        // Apply current theme
        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        }

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

        // Enhanced performance monitoring
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

        // Real-time Dashboard Updates
        let dashboardUpdateInterval;
        let lastUpdateTime = 0;

        async function updateDashboardData() {
            try {
                const response = await fetch('../includes/api/dashboard.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    // Update stats cards with animation
                    updateStatsCards(data.stats);
                    
                    // Update notifications
                    updateNotifications(data.notifications, data.hasNewNotifications);
                    
                    // Update critical alerts
                    updateCriticalAlerts(data.criticalAlerts);
                    
                    // Update recent activities
                    updateRecentActivities(data.recentActivities);
                    
                    lastUpdateTime = Date.now();
                    console.log('Dashboard updated successfully');
                } else {
                    console.error('Dashboard API error:', data.error);
                }
            } catch (error) {
                console.error('Failed to update dashboard:', error);
            }
        }

        function updateStatsCards(stats) {
            if (!stats) return;
            
            // Update each stat with animation
            const statMappings = {
                'total_active_users': '.stat-number[data-stat="users"]',
                'pending_users': '.stat-number[data-stat="pending"]',
                'total_subjects': '.stat-number[data-stat="subjects"]',
                'total_classrooms': '.stat-number[data-stat="classrooms"]',
                'active_timetables': '.stat-number[data-stat="timetables"]',
                'total_departments': '.stat-number[data-stat="departments"]'
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

        function updateNotifications(notifications, hasNew) {
            if (hasNew) {
                showNotificationAlert('New notifications received!');
            }
            
            // Update notification badge if exists
            const badge = document.querySelector('.notification-badge');
            if (badge && notifications) {
                const unreadCount = notifications.filter(n => n.is_read == 0).length;
                if (unreadCount > 0) {
                    badge.textContent = unreadCount;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        function updateCriticalAlerts(alerts) {
            if (!alerts || alerts.length === 0) return;
            
            const alertsContainer = document.getElementById('criticalAlerts') || createAlertsContainer();
            
            alerts.forEach(alert => {
                if (!document.querySelector(`[data-alert-id="${alert.type}-${alert.message.substring(0, 20)}"]`)) {
                    showCriticalAlert(alert, alertsContainer);
                }
            });
        }

        function createAlertsContainer() {
            const container = document.createElement('div');
            container.id = 'criticalAlerts';
            container.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 1000;
                max-width: 400px;
            `;
            document.body.appendChild(container);
            return container;
        }

        function showCriticalAlert(alert, container) {
            const alertElement = document.createElement('div');
            alertElement.className = `alert alert-${alert.type} alert-dismissible fade show`;
            alertElement.setAttribute('data-alert-id', `${alert.type}-${alert.message.substring(0, 20)}`);
            alertElement.style.cssText = `
                margin-bottom: 10px;
                animation: slideInRight 0.3s ease-out;
                backdrop-filter: blur(10px);
                background: rgba(255, 255, 255, 0.95);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 12px;
            `;
            
            alertElement.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <strong>${alert.type === 'warning' ? '⚠️' : 'ℹ️'}</strong>
                        ${alert.message}
                    </div>
                    ${alert.action ? `<a href="${alert.action}" class="btn btn-sm btn-outline-primary ms-2">View</a>` : ''}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            container.appendChild(alertElement);
            
            // Auto-dismiss after 10 seconds
            setTimeout(() => {
                if (alertElement.parentNode) {
                    alertElement.classList.remove('show');
                    setTimeout(() => alertElement.remove(), 300);
                }
            }, 10000);
        }

        function updateRecentActivities(activities) {
            if (!activities || activities.length === 0) return;
            
            const activitiesContainer = document.querySelector('.recent-activities-list');
            if (activitiesContainer) {
                // Update with new activities if different
                const currentCount = activitiesContainer.children.length;
                if (activities.length !== currentCount) {
                    // Could update the activities list here if needed
                    console.log('Activities updated:', activities.length);
                }
            }
        }

        function showNotificationAlert(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-info alert-dismissible fade show';
            alert.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1050;
                max-width: 300px;
                animation: slideInRight 0.3s ease-out;
            `;
            alert.innerHTML = `
                <div class="d-flex align-items-center">
                    <span>🔔 ${message}</span>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.appendChild(alert);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }

        // Start real-time updates
        function startDashboardUpdates() {
            // Initial update
            updateDashboardData();
            
            // Set up periodic updates (every 30 seconds)
            dashboardUpdateInterval = setInterval(updateDashboardData, 30000);
            
            // Pause updates when page is not visible
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    if (dashboardUpdateInterval) {
                        clearInterval(dashboardUpdateInterval);
                        dashboardUpdateInterval = null;
                    }
                } else {
                    if (!dashboardUpdateInterval) {
                        updateDashboardData(); // Immediate update when page becomes visible
                        dashboardUpdateInterval = setInterval(updateDashboardData, 30000);
                    }
                }
            });
        }

        // Removed sidebar JS functions to test if they cause expansion issue

        // Initialize dashboard updates when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Start dashboard updates
            startDashboardUpdates();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (dashboardUpdateInterval) {
                clearInterval(dashboardUpdateInterval);
            }
        });
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