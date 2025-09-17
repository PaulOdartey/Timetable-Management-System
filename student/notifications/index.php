<?php
/**
 * Student Notifications Page
 * Timetable Management System
 * 
 * Complete notifications view for students
 * Shows all notifications, system announcements, and academic messages
 */

// Start session and security checks
session_start();

// Include required files (use absolute paths from project root)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/User.php';

// Ensure user is logged in and has student role
User::requireLogin();
User::requireRole('student');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables
$notificationsData = [
    'student_info' => [],
    'all_notifications' => [],
    'notifications_summary' => [],
    'notifications_by_type' => [],
    'unread_count' => 0
];

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        
        switch ($action) {
            case 'mark_read':
                if ($notificationId > 0) {
                    $db->execute("
                        UPDATE notifications 
                        SET is_read = 1, read_at = NOW() 
                        WHERE notification_id = ? 
                        AND (target_user_id = ? OR target_role = 'student' OR target_role = 'all')
                    ", [$notificationId, $userId]);
                    
                    $response = ['success' => true, 'message' => 'Notification marked as read'];
                }
                break;
                
            case 'mark_unread':
                if ($notificationId > 0) {
                    $db->execute("
                        UPDATE notifications 
                        SET is_read = 0, read_at = NULL 
                        WHERE notification_id = ? 
                        AND (target_user_id = ? OR target_role = 'student' OR target_role = 'all')
                    ", [$notificationId, $userId]);
                    
                    $response = ['success' => true, 'message' => 'Notification marked as unread'];
                }
                break;
                
            case 'mark_all_read':
                $db->execute("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE is_read = 0 
                    AND (target_user_id = ? OR target_role = 'student' OR target_role = 'all')
                    AND is_active = 1
                ", [$userId]);
                
                $response = ['success' => true, 'message' => 'All notifications marked as read'];
                break;
                
            case 'delete_notification':
                if ($notificationId > 0) {
                    // Only allow deletion of personal notifications
                    $db->execute("
                        UPDATE notifications 
                        SET is_active = 0 
                        WHERE notification_id = ? 
                        AND target_user_id = ?
                    ", [$notificationId, $userId]);
                    
                    $response = ['success' => true, 'message' => 'Notification deleted'];
                }
                break;
        }
        
        // If it's an AJAX request, return JSON response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response ?? ['success' => false, 'message' => 'Invalid action']);
            exit;
        }
        
        // Redirect to prevent form resubmission
        header('Location: notifications.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Notifications Action Error: " . $e->getMessage());
        $error_message = "Unable to process action. Please try again.";
    }
}

try {
    // Get student info
    $studentInfo = $db->fetchRow("
        SELECT s.*, u.email, u.username
        FROM students s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.user_id = ?
    ", [$userId]);

    if ($studentInfo) {
        $notificationsData['student_info'] = $studentInfo;
        $studentId = $studentInfo['student_id'];
    } else {
        throw new Exception('Student information not found');
    }

    // Get all notifications for this student
    $allNotifications = $db->fetchAll("
        SELECT 
            n.*,
            u.username as created_by_username,
            CASE 
                WHEN n.target_user_id = ? THEN 'personal'
                WHEN n.target_role = 'student' THEN 'student'
                WHEN n.target_role = 'all' THEN 'system'
                ELSE 'other'
            END as notification_scope,
            CASE 
                WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'today'
                WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'week'
                WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'month'
                ELSE 'older'
            END as time_category,
            CASE 
                WHEN n.expires_at IS NOT NULL AND n.expires_at < NOW() THEN 1
                ELSE 0
            END as is_expired,
            CASE
                WHEN n.related_table = 'enrollments' THEN 'academic'
                WHEN n.related_table = 'timetables' THEN 'schedule'
                WHEN n.type = 'urgent' OR n.type = 'error' THEN 'urgent'
                ELSE 'general'
            END as category
        FROM notifications n
        LEFT JOIN users u ON n.created_by = u.user_id
        WHERE n.is_active = 1
        AND (
            n.target_user_id = ? 
            OR n.target_role = 'student' 
            OR n.target_role = 'all'
        )
        AND (n.expires_at IS NULL OR n.expires_at > NOW())
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT 200
    ", [$userId, $userId]);

    $notificationsData['all_notifications'] = $allNotifications;

    // Group notifications by type and time
    $notificationsByType = [
        'unread' => [],
        'today' => [],
        'week' => [],
        'month' => [],
        'older' => [],
        'academic' => [],
        'schedule' => [],
        'urgent' => [],
        'general' => []
    ];
    
    $unreadCount = 0;
    $typesCounts = [
        'info' => 0,
        'success' => 0,
        'warning' => 0,
        'error' => 0,
        'urgent' => 0
    ];
    
    $scopeCounts = [
        'personal' => 0,
        'student' => 0,
        'system' => 0
    ];

    $categoryCounts = [
        'academic' => 0,
        'schedule' => 0,
        'urgent' => 0,
        'general' => 0
    ];

    foreach ($allNotifications as $notification) {
        // Count by read status
        if (!$notification['is_read']) {
            $unreadCount++;
            $notificationsByType['unread'][] = $notification;
        }
        
        // Group by time category
        $timeCategory = $notification['time_category'];
        if (isset($notificationsByType[$timeCategory])) {
            $notificationsByType[$timeCategory][] = $notification;
        }
        
        // Group by category
        $category = $notification['category'];
        if (isset($notificationsByType[$category])) {
            $notificationsByType[$category][] = $notification;
        }
        
        // Count by type
        $type = $notification['type'];
        if (isset($typesCounts[$type])) {
            $typesCounts[$type]++;
        }
        
        // Count by scope
        $scope = $notification['notification_scope'];
        if (isset($scopeCounts[$scope])) {
            $scopeCounts[$scope]++;
        }

        // Count by category
        if (isset($categoryCounts[$category])) {
            $categoryCounts[$category]++;
        }
    }

    $notificationsData['notifications_by_type'] = $notificationsByType;
    $notificationsData['unread_count'] = $unreadCount;

    // Calculate summary statistics
    $totalNotifications = count($allNotifications);
    $readNotifications = $totalNotifications - $unreadCount;
    $todayNotifications = count($notificationsByType['today']);
    $urgentNotifications = $categoryCounts['urgent'] + $typesCounts['urgent'] + $typesCounts['error'];
    $academicNotifications = $categoryCounts['academic'];

    $notificationsData['notifications_summary'] = [
        'total_notifications' => $totalNotifications,
        'unread_count' => $unreadCount,
        'read_count' => $readNotifications,
        'today_count' => $todayNotifications,
        'urgent_count' => $urgentNotifications,
        'academic_count' => $academicNotifications,
        'types_distribution' => $typesCounts,
        'scope_distribution' => $scopeCounts,
        'category_distribution' => $categoryCounts
    ];

} catch (Exception $e) {
    error_log("Student Notifications Error: " . $e->getMessage());
    $error_message = "Unable to load notifications data. Please try again later.";
}

// Set page title
$pageTitle = "My Notifications";
$currentPage = "notifications";

// Helper function to get notification type badge color
function getNotificationTypeBadge($type) {
    $badges = [
        'info' => 'bg-primary',
        'success' => 'bg-success', 
        'warning' => 'bg-warning',
        'error' => 'bg-danger',
        'urgent' => 'bg-danger'
    ];
    return $badges[$type] ?? 'bg-secondary';
}

// Helper function to get notification type icon
function getNotificationTypeIcon($type) {
    $icons = [
        'info' => '📢',
        'success' => '✅', 
        'warning' => '⚠️',
        'error' => '❌',
        'urgent' => '🚨'
    ];
    return $icons[$type] ?? '📋';
}

// Helper function to get scope badge
function getScopeBadge($scope) {
    $badges = [
        'personal' => 'bg-info',
        'student' => 'bg-primary',
        'system' => 'bg-secondary'
    ];
    return $badges[$scope] ?? 'bg-secondary';
}

// Helper function to get category badge
function getCategoryBadge($category) {
    $badges = [
        'academic' => 'bg-success',
        'schedule' => 'bg-info',
        'urgent' => 'bg-danger',
        'general' => 'bg-secondary'
    ];
    return $badges[$category] ?? 'bg-secondary';
}

// Helper function to get category icon
function getCategoryIcon($category) {
    $icons = [
        'academic' => '🎓',
        'schedule' => '📅',
        'urgent' => '🚨',
        'general' => '📋'
    ];
    return $icons[$category] ?? '📋';
}

// Helper function to format time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 604800) return floor($time/86400) . ' days ago';
    if ($time < 2419200) return floor($time/604800) . ' weeks ago';
    return date('M j, Y', strtotime($datetime));
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
            --student-primary: #10b981;
            --student-secondary: #059669;
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

        .stat-icon.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.unread {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .stat-icon.today {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon.academic {
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

        /* Notifications Container */
        .notifications-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            overflow-x: auto;
        }

        .notifications-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .notifications-title {
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

        /* Notifications Grid */
        .notifications-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .notification-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .notification-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .notification-card.unread {
            border-left: 4px solid var(--primary-color);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(255, 255, 255, 1) 100%);
        }

        .notification-card.urgent {
            border-left: 4px solid var(--error-color);
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(255, 255, 255, 1) 100%);
        }

        .notification-card.academic {
            border-left: 4px solid var(--warning-color);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(255, 255, 255, 1) 100%);
        }

        .notification-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .notification-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            line-height: 1.4;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .notification-type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .scope-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .category-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .notification-message {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .notification-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--text-tertiary);
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .notification-action-btn {
            padding: 0.25rem 0.5rem;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-action-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Notifications List View */
        .notifications-list {
            display: none;
        }

        .notifications-list.active {
            display: block;
        }

        .time-group {
            margin-bottom: 2rem;
        }

        .time-group-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
        }

        .list-notification {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .list-notification:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

       .list-notification.unread {
            border-left: 4px solid var(--primary-color);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(255, 255, 255, 1) 100%);
        }

        .list-notification-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .list-notification-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .list-notification-name {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .list-notification-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Filter and Search */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .bulk-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Empty State */
        .empty-notifications {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-notifications svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive Design */
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

            .notifications-container {
                padding: 1rem;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                min-width: unset;
            }
        }

        @media (max-width: 768px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            /* Hide the header refresh button on mobile */
            .refresh-btn {
                display: none !important;
            }

            .notifications-grid {
                display: none !important;
            }

            .notifications-list {
                display: block !important;
            }

            .toggle-btn {
                display: none;
            }

            .notification-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .list-notification-header {
                flex-direction: column;
                align-items: flex-start;
            }

            /* Mobile header actions styling */
            .header-card .d-flex {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 1.5rem;
            }

            .header-card .d-flex > div:last-child {
                display: flex;
                justify-content: center;
                gap: 0.75rem;
                flex-wrap: wrap;
            }

            .header-card .btn-action {
                flex: 1;
                min-width: 140px;
                max-width: 160px;
                justify-content: center;
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
                font-weight: 600;
                text-align: center;
            }

            .page-title {
                font-size: 2rem !important;
                text-align: center;
                margin-bottom: 0.75rem;
            }

            .page-subtitle {
                text-align: center;
                font-size: 1rem;
                margin-bottom: 0;
            }

            /* Ensure buttons are touch-friendly */
            .btn-action {
                min-height: 44px;
                touch-action: manipulation;
            }

            /* Stronger, more visible borders on mobile list view cards */
            .list-notification {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .list-notification {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }

        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .notifications-grid {
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

        /* Notification Status Indicators */
        .notification-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--student-primary);
        }

        .notification-status.read {
            background: var(--text-tertiary);
        }

        .notification-status.urgent {
            background: var(--error-color);
            animation: pulse 2s infinite;
        }

        .notification-status.academic {
            background: var(--warning-color);
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        /* Notification Animation */
        .notification-enter {
            animation: slideInRight 0.3s ease-out;
        }

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

        /* Action Buttons Styling */
        .notification-action-btn.mark-read {
            background: var(--success-color);
            color: white;
        }

        .notification-action-btn.mark-unread {
            background: var(--warning-color);
            color: white;
        }

        .notification-action-btn.delete {
            background: var(--error-color);
            color: white;
        }

        /* Tooltip Styling */
        .tooltip {
            font-size: 0.75rem;
        }

        /* Custom Scrollbar */
        .notifications-container::-webkit-scrollbar {
            width: 6px;
        }

        .notifications-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .notifications-container::-webkit-scrollbar-thumb {
            background: var(--student-primary);
            border-radius: 3px;
        }

        .notifications-container::-webkit-scrollbar-thumb:hover {
            background: var(--student-secondary);
        }

        /* Student-specific styling */
        .student-badge {
            background: linear-gradient(135deg, var(--student-primary) 0%, var(--student-secondary) 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Academic-related notifications styling */
        .academic-highlight {
            border-left: 4px solid var(--warning-color);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(255, 255, 255, 1) 100%);
        }

        /* Schedule-related notifications styling */
        .schedule-highlight {
            border-left: 4px solid #3b82f6;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(255, 255, 255, 1) 100%);
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-card glass-card fade-in">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">🔔 My Notifications</h1>
                    </div>
                    <button class="btn-action btn-success refresh-btn" onclick="location.reload()">
                        🔄 Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <?php if (!empty($notificationsData['notifications_summary'])): ?>
            <div class="stats-grid">
                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon total">📬</div>
                    <div class="stat-number"><?= $notificationsData['notifications_summary']['total_notifications'] ?></div>
                    <div class="stat-label">Total Notifications</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon unread">📩</div>
                    <div class="stat-number"><?= $notificationsData['notifications_summary']['unread_count'] ?></div>
                    <div class="stat-label">Unread</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon today">📅</div>
                    <div class="stat-number"><?= $notificationsData['notifications_summary']['today_count'] ?></div>
                    <div class="stat-label">Today</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon academic">🎓</div>
                    <div class="stat-number"><?= $notificationsData['notifications_summary']['academic_count'] ?></div>
                    <div class="stat-label">Academic</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Notifications Display -->
        <div class="notifications-container">
            <div class="notifications-header">
                <h2 class="notifications-title">All Notifications</h2>
                <div class="view-toggles">
                    <button class="toggle-btn active" onclick="switchView('grid')">Grid View</button>
                    <button class="toggle-btn" onclick="switchView('list')">List View</button>
                </div>
            </div>

            <!-- Filter and Search Bar -->
            <div class="filter-bar">
                <input type="text" id="notificationSearch" class="search-input" placeholder="Search notifications...">
                <select id="typeFilter" class="filter-select">
                    <option value="all">All Types</option>
                    <option value="info">Info</option>
                    <option value="success">Success</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                    <option value="urgent">Urgent</option>
                </select>
                <select id="categoryFilter" class="filter-select">
                    <option value="all">All Categories</option>
                    <option value="academic">Academic</option>
                    <option value="schedule">Schedule</option>
                    <option value="urgent">Urgent</option>
                    <option value="general">General</option>
                </select>
                <select id="statusFilter" class="filter-select">
                    <option value="all">All Status</option>
                    <option value="unread">Unread</option>
                    <option value="read">Read</option>
                </select>
                <select id="scopeFilter" class="filter-select">
                    <option value="all">All Scope</option>
                    <option value="personal">Personal</option>
                    <option value="student">Students</option>
                    <option value="system">System</option>
                </select>
                <div class="bulk-actions">
                    <button onclick="markSelectedAsRead()" class="btn-action btn-outline" id="markReadBtn" style="display: none;">
                        ✅ Mark Read
                    </button>
                    <button onclick="deleteSelected()" class="btn-action btn-outline" id="deleteBtn" style="display: none;">
                        🗑️ Delete
                    </button>
                </div>
            </div>

            <?php if (!empty($notificationsData['all_notifications'])): ?>
                <!-- Grid View -->
                <div class="notifications-grid" id="gridView">
                    <?php foreach ($notificationsData['all_notifications'] as $notification): ?>
                        <div class="notification-card <?= !$notification['is_read'] ? 'unread' : '' ?> <?= ($notification['category'] === 'urgent' || $notification['type'] === 'urgent' || $notification['type'] === 'error') ? 'urgent' : '' ?> <?= $notification['category'] === 'academic' ? 'academic' : '' ?>" 
                             data-id="<?= $notification['notification_id'] ?>"
                             data-type="<?= htmlspecialchars($notification['type']) ?>"
                             data-status="<?= $notification['is_read'] ? 'read' : 'unread' ?>"
                             data-scope="<?= htmlspecialchars($notification['notification_scope']) ?>"
                             data-category="<?= htmlspecialchars($notification['category']) ?>"
                             onclick="toggleNotificationSelection(this)">
                            
                            <div class="notification-status <?= $notification['is_read'] ? 'read' : 'unread' ?> <?= $notification['category'] ?>"></div>
                            
                            <div class="notification-header">
                                <div>
                                    <div class="notification-title"><?= getNotificationTypeIcon($notification['type']) ?> <?= htmlspecialchars($notification['title']) ?></div>
                                    <div class="notification-meta">
                                        <span class="notification-type-badge <?= getNotificationTypeBadge($notification['type']) ?>">
                                            <?= strtoupper($notification['type']) ?>
                                        </span>
                                        <span class="category-badge <?= getCategoryBadge($notification['category']) ?>">
                                            <?= getCategoryIcon($notification['category']) ?> <?= strtoupper($notification['category']) ?>
                                        </span>
                                        <span class="scope-badge <?= getScopeBadge($notification['notification_scope']) ?>">
                                            <?= strtoupper($notification['notification_scope']) ?>
                                        </span>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-info">NEW</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <input type="checkbox" class="notification-checkbox" style="display: none;" data-id="<?= $notification['notification_id'] ?>">
                            </div>

                            <div class="notification-message">
                                <?= htmlspecialchars(substr($notification['message'], 0, 150)) ?><?= strlen($notification['message']) > 150 ? '...' : '' ?>
                            </div>

                            <div class="notification-footer">
                                <div class="notification-time">
                                    <?= timeAgo($notification['created_at']) ?>
                                    <?php if ($notification['created_by_username']): ?>
                                        • by <?= htmlspecialchars($notification['created_by_username']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <button class="notification-action-btn mark-read" onclick="markNotificationAsRead(<?= $notification['notification_id'] ?>)" title="Mark as read">
                                            ✓
                                        </button>
                                    <?php else: ?>
                                        <button class="notification-action-btn mark-unread" onclick="markNotificationAsUnread(<?= $notification['notification_id'] ?>)" title="Mark as unread">
                                            ↺
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($notification['notification_scope'] === 'personal'): ?>
                                        <button class="notification-action-btn delete" onclick="deleteNotification(<?= $notification['notification_id'] ?>)" title="Delete">
                                            🗑️
                                        </button>
                                    <?php endif; ?>
                                    <button class="notification-action-btn" onclick="showNotificationDetails(<?= htmlspecialchars(json_encode($notification)) ?>)" title="View details">
                                        👁️
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- List View -->
                <div class="notifications-list" id="listView">
                    <?php if (!empty($notificationsData['notifications_by_type'])): ?>
                        <?php 
                        $timeGroups = [
                            'today' => '📅 Today',
                            'week' => '📆 This Week', 
                            'month' => '🗓️ This Month',
                            'older' => '📋 Older'
                        ];
                        ?>
                        
                        <?php foreach ($timeGroups as $timeKey => $timeLabel): ?>
                            <?php if (!empty($notificationsData['notifications_by_type'][$timeKey])): ?>
                                <div class="time-group">
                                    <h3 class="time-group-title">
                                        <?= $timeLabel ?> 
                                        <span style="font-weight: 400; font-size: 0.9rem;">
                                            (<?= count($notificationsData['notifications_by_type'][$timeKey]) ?> notifications)
                                        </span>
                                    </h3>
                                    
                                    <?php foreach ($notificationsData['notifications_by_type'][$timeKey] as $notification): ?>
                                        <div class="list-notification <?= !$notification['is_read'] ? 'unread' : '' ?> <?= $notification['category'] === 'academic' ? 'academic-highlight' : '' ?> <?= $notification['category'] === 'schedule' ? 'schedule-highlight' : '' ?>" 
                                             data-id="<?= $notification['notification_id'] ?>"
                                             data-type="<?= htmlspecialchars($notification['type']) ?>"
                                             data-status="<?= $notification['is_read'] ? 'read' : 'unread' ?>"
                                             data-scope="<?= htmlspecialchars($notification['notification_scope']) ?>"
                                             data-category="<?= htmlspecialchars($notification['category']) ?>">
                                            
                                            <div class="list-notification-header">
                                                <div class="list-notification-title">
                                                    <div class="list-notification-name">
                                                        <?= getNotificationTypeIcon($notification['type']) ?> <?= htmlspecialchars($notification['title']) ?>
                                                    </div>
                                                </div>
                                                <div class="list-notification-meta">
                                                    <span class="notification-type-badge <?= getNotificationTypeBadge($notification['type']) ?>">
                                                        <?= strtoupper($notification['type']) ?>
                                                    </span>
                                                    <span class="category-badge <?= getCategoryBadge($notification['category']) ?>">
                                                        <?= getCategoryIcon($notification['category']) ?> <?= strtoupper($notification['category']) ?>
                                                    </span>
                                                    <span class="scope-badge <?= getScopeBadge($notification['notification_scope']) ?>">
                                                        <?= strtoupper($notification['notification_scope']) ?>
                                                    </span>
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="badge bg-info">NEW</span>
                                                    <?php endif; ?>
                                                    <span class="badge bg-secondary">
                                                        <?= timeAgo($notification['created_at']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="notification-message" style="margin-bottom: 1rem;">
                                                <?= nl2br(htmlspecialchars($notification['message'])) ?>
                                            </div>
                                            
                                            <?php if ($notification['created_by_username']): ?>
                                                <div style="margin-bottom: 1rem; font-size: 0.875rem; color: var(--text-secondary);">
                                                    <strong>From:</strong> <?= htmlspecialchars($notification['created_by_username']) ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($notification['related_table'] && $notification['related_id']): ?>
                                                <div style="margin-bottom: 1rem; font-size: 0.875rem; color: var(--text-secondary);">
                                                    <strong>Related to:</strong> <?= ucfirst($notification['related_table']) ?> (ID: <?= $notification['related_id'] ?>)
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="notification-actions">
                                                <?php if (!$notification['is_read']): ?>
                                                    <button class="notification-action-btn mark-read" onclick="markNotificationAsRead(<?= $notification['notification_id'] ?>)">
                                                        ✓ Mark as Read
                                                    </button>
                                                <?php else: ?>
                                                    <button class="notification-action-btn mark-unread" onclick="markNotificationAsUnread(<?= $notification['notification_id'] ?>)">
                                                        ↺ Mark as Unread
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($notification['notification_scope'] === 'personal'): ?>
                                                    <button class="notification-action-btn delete" onclick="deleteNotification(<?= $notification['notification_id'] ?>)">
                                                        🗑️ Delete
                                                    </button>
                                                <?php endif; ?>
                                                <button class="notification-action-btn" onclick="showNotificationDetails(<?= htmlspecialchars(json_encode($notification)) ?>)">
                                                    👁️ View Details
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-notifications">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 8C18 6.17157 16.8284 5 15 5H9C7.17157 5 6 6.17157 6 8C6 8 6 9 6 10V12C6 14.2091 7.79086 16 10 16H14C16.2091 16 18 14.2091 18 12V10C18 9 18 8 18 8Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M6 12H4C2.89543 12 2 12.8954 2 14V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V14C22 12.8954 21.1046 12 20 12H18" stroke="currentColor" stroke-width="2"/>
                        <path d="M12 9V13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <h3>No Notifications</h3>
                    <p>You don't have any notifications at the moment.</p>
                    <p style="margin-top: 0.5rem;">You'll receive notifications about academic updates, schedule changes, enrollment status, and important announcements here.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Notification Details Modal -->
    <div class="modal fade" id="notificationDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" style="color: var(--text-primary);">Notification Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody" style="color: var(--text-primary);">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="modalMarkReadBtn">Mark as Read</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        let selectedNotifications = new Set();
        let bulkActionsVisible = false;

        // Initialize notifications functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Apply theme
            applyCurrentTheme();
            
            // Initialize tooltips
            initializeTooltips();
            
            // Handle responsive view switching
            handleResponsiveView();

            // Listen for sidebar toggle events
            handleSidebarToggle();

            // Initialize search and filters
            initializeSearchAndFilters();

       // Auto-refresh notifications every 5 minutes
            setInterval(refreshNotifications, 300000);
        });

        // Handle sidebar toggle with sticky header support
        function handleSidebarToggle() {
            window.addEventListener('sidebarToggled', function(e) {
                const body = document.body;
                
                if (e.detail && e.detail.collapsed) {
                    body.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                }
            });

            const sidebar = document.querySelector('.tms-sidebar');
            if (sidebar) {
                if (sidebar.classList.contains('collapsed')) {
                    document.body.classList.add('sidebar-collapsed');
                }
                
                if (window.innerWidth <= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                }
            }

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

        // View switching functionality
        function switchView(viewType) {
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const toggleBtns = document.querySelectorAll('.toggle-btn');
            // No sticky header buttons on this page

            toggleBtns.forEach(btn => btn.classList.remove('active'));

            if (viewType === 'grid') {
                gridView.style.display = 'grid';
                listView.style.display = 'none';
                const gridToggle = document.querySelector('.toggle-btn[onclick*="grid"]');
                if (gridToggle) gridToggle.classList.add('active');
                
                // No sticky header buttons to update

            } else {
                gridView.style.display = 'none';
                listView.style.display = 'block';
                listView.classList.add('active');
                const listToggle = document.querySelector('.toggle-btn[onclick*="list"]');
                if (listToggle) listToggle.classList.add('active');
                
                // No sticky header buttons to update
            }
        }

        // Notification selection for bulk actions
        function toggleNotificationSelection(card) {
            const checkbox = card.querySelector('.notification-checkbox');
            const notificationId = card.dataset.id;
            
            if (event.target.closest('.notification-actions') || event.target.closest('.notification-action-btn')) {
                return; // Don't select when clicking action buttons
            }
            
            checkbox.style.display = checkbox.style.display === 'none' ? 'block' : 'none';
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                selectedNotifications.add(notificationId);
                card.style.border = '2px solid var(--student-primary)';
            } else {
                selectedNotifications.delete(notificationId);
                card.style.border = '';
            }
            
            updateBulkActions();
        }

        // Update bulk action buttons visibility
        function updateBulkActions() {
            const markReadBtn = document.getElementById('markReadBtn');
            const deleteBtn = document.getElementById('deleteBtn');
            
            if (selectedNotifications.size > 0) {
                markReadBtn.style.display = 'block';
                deleteBtn.style.display = 'block';
                bulkActionsVisible = true;
            } else {
                markReadBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
                bulkActionsVisible = false;
            }
        }

        // Mark notification as read
        async function markNotificationAsRead(notificationId) {
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=mark_read&notification_id=${notificationId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update UI
                    const cards = document.querySelectorAll(`[data-id="${notificationId}"]`);
                    cards.forEach(card => {
                        card.classList.remove('unread');
                        card.dataset.status = 'read';
                        const status = card.querySelector('.notification-status');
                        if (status) status.classList.add('read');
                        
                        // Update action button
                        const markReadBtn = card.querySelector('.mark-read');
                        if (markReadBtn) {
                            markReadBtn.className = 'notification-action-btn mark-unread';
                            markReadBtn.onclick = () => markNotificationAsUnread(notificationId);
                            markReadBtn.textContent = markReadBtn.textContent.includes('Mark') ? '↺ Mark as Unread' : '↺';
                            markReadBtn.title = 'Mark as unread';
                        }
                    });
                    
                    // Update counters
                    updateNotificationCounters(-1);
                    showToast('Notification marked as read', 'success');
                } else {
                    showToast('Failed to mark notification as read', 'error');
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
                showToast('Error updating notification', 'error');
            }
        }

        // Mark notification as unread
        async function markNotificationAsUnread(notificationId) {
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=mark_unread&notification_id=${notificationId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update UI
                    const cards = document.querySelectorAll(`[data-id="${notificationId}"]`);
                    cards.forEach(card => {
                        card.classList.add('unread');
                        card.dataset.status = 'unread';
                        const status = card.querySelector('.notification-status');
                        if (status) status.classList.remove('read');
                        
                        // Update action button
                        const markUnreadBtn = card.querySelector('.mark-unread');
                        if (markUnreadBtn) {
                            markUnreadBtn.className = 'notification-action-btn mark-read';
                            markUnreadBtn.onclick = () => markNotificationAsRead(notificationId);
                            markUnreadBtn.textContent = markUnreadBtn.textContent.includes('Mark') ? '✓ Mark as Read' : '✓';
                            markUnreadBtn.title = 'Mark as read';
                        }
                    });
                    
                    // Update counters
                    updateNotificationCounters(1);
                    showToast('Notification marked as unread', 'success');
                } else {
                    showToast('Failed to mark notification as unread', 'error');
                }
            } catch (error) {
                console.error('Error marking notification as unread:', error);
                showToast('Error updating notification', 'error');
            }
        }

        // Delete notification
        async function deleteNotification(notificationId) {
            if (!confirm('Are you sure you want to delete this notification?')) {
                return;
            }
            
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=delete_notification&notification_id=${notificationId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Remove from UI
                    const cards = document.querySelectorAll(`[data-id="${notificationId}"]`);
                    cards.forEach(card => {
                        card.style.animation = 'slideOutRight 0.3s ease-in';
                        setTimeout(() => card.remove(), 300);
                    });
                    
                    // Update counters
                    updateNotificationCounters(0, -1);
                    showToast('Notification deleted', 'success');
                } else {
                    showToast('Failed to delete notification', 'error');
                }
            } catch (error) {
                console.error('Error deleting notification:', error);
                showToast('Error deleting notification', 'error');
            }
        }

        // Mark all notifications as read
        async function markAllAsRead() {
            if (!confirm('Mark all notifications as read?')) {
                return;
            }
            
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=mark_all_read'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update all unread notifications in UI
                    const unreadCards = document.querySelectorAll('[data-status="unread"]');
                    unreadCards.forEach(card => {
                        card.classList.remove('unread');
                        card.dataset.status = 'read';
                        const status = card.querySelector('.notification-status');
                        if (status) status.classList.add('read');
                        
                        // Update action buttons
                        const markReadBtn = card.querySelector('.mark-read');
                        if (markReadBtn) {
                            markReadBtn.className = 'notification-action-btn mark-unread';
                            const notificationId = card.dataset.id;
                            markReadBtn.onclick = () => markNotificationAsUnread(notificationId);
                            markReadBtn.textContent = markReadBtn.textContent.includes('Mark') ? '↺ Mark as Unread' : '↺';
                            markReadBtn.title = 'Mark as unread';
                        }
                    });
                    
                    // Reset counters
                    updateAllNotificationCounters();
                    showToast('All notifications marked as read', 'success');
                } else {
                    showToast('Failed to mark all notifications as read', 'error');
                }
            } catch (error) {
                console.error('Error marking all as read:', error);
                showToast('Error updating notifications', 'error');
            }
        }

        // Show notification details modal
        function showNotificationDetails(notificationData) {
            const modal = new bootstrap.Modal(document.getElementById('notificationDetailsModal'));
            const modalBody = document.getElementById('modalBody');
            const modalMarkReadBtn = document.getElementById('modalMarkReadBtn');

            // Format notification details
            modalBody.innerHTML = `
                <div class="row g-4">
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span style="font-size: 1.5rem;">${getNotificationTypeIcon(notificationData.type)}</span>
                            <h4 style="margin: 0; color: var(--text-primary);">${escapeHtml(notificationData.title)}</h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 style="color: var(--student-primary); font-weight: 600; margin-bottom: 1rem;">📋 Notification Details</h6>
                        <div class="mb-3">
                            <strong>Type:</strong><br>
                            <span class="badge ${getNotificationTypeBadge(notificationData.type)}">${notificationData.type.toUpperCase()}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Category:</strong><br>
                            <span class="badge ${getCategoryBadge(notificationData.category)}">${getCategoryIcon(notificationData.category)} ${notificationData.category.toUpperCase()}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Scope:</strong><br>
                            <span class="badge ${getScopeBadge(notificationData.notification_scope)}">${notificationData.notification_scope.toUpperCase()}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Status:</strong><br>
                            <span class="badge ${notificationData.is_read ? 'bg-secondary' : 'bg-primary'}">${notificationData.is_read ? 'READ' : 'UNREAD'}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Created:</strong><br>
                            <span style="color: var(--text-secondary);">${new Date(notificationData.created_at).toLocaleString()}</span>
                        </div>
                        ${notificationData.read_at ? `
                        <div class="mb-3">
                            <strong>Read:</strong><br>
                            <span style="color: var(--text-secondary);">${new Date(notificationData.read_at).toLocaleString()}</span>
                        </div>
                        ` : ''}
                    </div>
                    <div class="col-md-6">
                        <h6 style="color: var(--student-primary); font-weight: 600; margin-bottom: 1rem;">📊 Additional Information</h6>
                        ${notificationData.created_by_username ? `
                        <div class="mb-3">
                            <strong>From:</strong><br>
                            <span style="color: var(--text-secondary);">${escapeHtml(notificationData.created_by_username)}</span>
                        </div>
                        ` : ''}
                        ${notificationData.expires_at ? `
                        <div class="mb-3">
                            <strong>Expires:</strong><br>
                            <span style="color: var(--text-secondary);">${new Date(notificationData.expires_at).toLocaleString()}</span>
                        </div>
                        ` : ''}
                        ${notificationData.related_table ? `
                        <div class="mb-3">
                            <strong>Related to:</strong><br>
                            <span style="color: var(--text-secondary);">${notificationData.related_table.charAt(0).toUpperCase() + notificationData.related_table.slice(1)} ${notificationData.related_id ? '(ID: ' + notificationData.related_id + ')' : ''}</span>
                        </div>
                        ` : ''}
                        <div class="mb-3">
                            <strong>Priority:</strong><br>
                            <span class="badge ${notificationData.priority === 'urgent' ? 'bg-danger' : notificationData.priority === 'high' ? 'bg-warning' : 'bg-secondary'}">${(notificationData.priority || 'normal').toUpperCase()}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Time Category:</strong><br>
                            <span class="student-badge">${notificationData.time_category.toUpperCase()}</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <h6 style="color: var(--student-primary); font-weight: 600; margin-bottom: 1rem;">💬 Message</h6>
                        <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 12px; line-height: 1.6;">
                            ${escapeHtml(notificationData.message).replace(/\n/g, '<br>')}
                        </div>
                    </div>
                </div>
            `;

            // Set up modal action button
            if (!notificationData.is_read) {
                modalMarkReadBtn.textContent = 'Mark as Read';
                modalMarkReadBtn.className = 'btn btn-primary';
                modalMarkReadBtn.onclick = () => {
                    markNotificationAsRead(notificationData.notification_id);
                    modal.hide();
                };
            } else {
                modalMarkReadBtn.textContent = 'Mark as Unread';
                modalMarkReadBtn.className = 'btn btn-warning';
                modalMarkReadBtn.onclick = () => {
                    markNotificationAsUnread(notificationData.notification_id);
                    modal.hide();
                };
            }

            modal.show();
        }

        // Initialize search and filters
        function initializeSearchAndFilters() {
            const searchInput = document.getElementById('notificationSearch');
            const typeFilter = document.getElementById('typeFilter');
            const categoryFilter = document.getElementById('categoryFilter');
            const statusFilter = document.getElementById('statusFilter');
            const scopeFilter = document.getElementById('scopeFilter');

            function applyFilters() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedType = typeFilter.value;
                const selectedCategory = categoryFilter.value;
                const selectedStatus = statusFilter.value;
                const selectedScope = scopeFilter.value;

                const notifications = document.querySelectorAll('.notification-card, .list-notification');
                
                notifications.forEach(notification => {
                    let visible = true;

                    // Search filter
                    if (searchTerm) {
                        const notificationText = notification.textContent.toLowerCase();
                        visible = visible && notificationText.includes(searchTerm);
                    }

                    // Type filter
                    if (selectedType !== 'all') {
                        const notificationType = notification.getAttribute('data-type');
                        visible = visible && (notificationType === selectedType);
                    }

                    // Category filter
                    if (selectedCategory !== 'all') {
                        const notificationCategory = notification.getAttribute('data-category');
                        visible = visible && (notificationCategory === selectedCategory);
                    }

                    // Status filter
                    if (selectedStatus !== 'all') {
                        const notificationStatus = notification.getAttribute('data-status');
                        visible = visible && (notificationStatus === selectedStatus);
                    }

                    // Scope filter
                    if (selectedScope !== 'all') {
                        const notificationScope = notification.getAttribute('data-scope');
                        visible = visible && (notificationScope === selectedScope);
                    }

                    notification.style.display = visible ? '' : 'none';
                });

                // Hide empty time groups in list view
                const timeGroups = document.querySelectorAll('.time-group');
                timeGroups.forEach(group => {
                    const visibleNotifications = group.querySelectorAll('.list-notification:not([style*="display: none"])');
                    group.style.display = visibleNotifications.length > 0 ? '' : 'none';
                });
            }

            if (searchInput) searchInput.addEventListener('input', applyFilters);
            if (typeFilter) typeFilter.addEventListener('change', applyFilters);
            if (categoryFilter) categoryFilter.addEventListener('change', applyFilters);
            if (statusFilter) statusFilter.addEventListener('change', applyFilters);
            if (scopeFilter) scopeFilter.addEventListener('change', applyFilters);
        }

        // Refresh notifications
        function refreshNotifications() {
            window.location.reload();
        }

        // Update notification counters
        function updateNotificationCounters(unreadChange = 0, totalChange = 0) {
            const stickyStats = document.querySelectorAll('.sticky-stat-number');
            const statCards = document.querySelectorAll('.stat-number');
            
            // Update unread count
            if (unreadChange !== 0) {
                const currentUnread = parseInt(stickyStats[1].textContent);
                const newUnread = Math.max(0, currentUnread + unreadChange);
                stickyStats[1].textContent = newUnread;
                if (statCards[1]) statCards[1].textContent = newUnread;
            }
            
            // Update total count
            if (totalChange !== 0) {
                const currentTotal = parseInt(stickyStats[0].textContent);
                const newTotal = Math.max(0, currentTotal + totalChange);
                stickyStats[0].textContent = newTotal;
                if (statCards[0]) statCards[0].textContent = newTotal;
            }
        }

        // Update all notification counters (for mark all as read)
        function updateAllNotificationCounters() {
            const stickyStats = document.querySelectorAll('.sticky-stat-number');
            const statCards = document.querySelectorAll('.stat-number');
            
            // Set unread count to 0
            stickyStats[1].textContent = '0';
            if (statCards[1]) statCards[1].textContent = '0';
        }

        // Helper functions
        function getNotificationTypeIcon(type) {
            const icons = {
                'info': '📢',
                'success': '✅', 
                'warning': '⚠️',
                'error': '❌',
                'urgent': '🚨'
            };
            return icons[type] || '📋';
        }

        function getNotificationTypeBadge(type) {
            const badges = {
                'info': 'bg-primary',
                'success': 'bg-success', 
                'warning': 'bg-warning',
                'error': 'bg-danger',
                'urgent': 'bg-danger'
            };
            return badges[type] || 'bg-secondary';
        }

        function getScopeBadge(scope) {
            const badges = {
                'personal': 'bg-info',
                'student': 'bg-primary',
                'system': 'bg-secondary'
            };
            return badges[scope] || 'bg-secondary';
        }

        function getCategoryBadge(category) {
            const badges = {
                'academic': 'bg-success',
                'schedule': 'bg-info',
                'urgent': 'bg-danger',
                'general': 'bg-secondary'
            };
            return badges[category] || 'bg-secondary';
        }

        function getCategoryIcon(category) {
            const icons = {
                'academic': '🎓',
                'schedule': '📅',
                'urgent': '🚨',
                'general': '📋'
            };
            return icons[category] || '📋';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Toast notifications
        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(toast);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
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

            checkScreenSize();
            window.addEventListener('resize', checkScreenSize);
        }

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + R for Refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                refreshNotifications();
            }

            // Ctrl/Cmd + A for Mark All Read
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea')) {
                e.preventDefault();
                markAllAsRead();
            }

            // G for Grid view
            if (e.key === 'g' && !e.ctrlKey && !e.metaKey && !e.target.matches('input, textarea')) {
                switchView('grid');
            }

            // L for List view
            if (e.key === 'l' && !e.ctrlKey && !e.metaKey && !e.target.matches('input, textarea')) {
                switchView('list');
            }

            // F for Focus search
            if (e.key === 'f' && !e.ctrlKey && !e.metaKey && !e.target.matches('input, textarea')) {
                e.preventDefault();
                const searchInput = document.getElementById('notificationSearch');
                if (searchInput) searchInput.focus();
            }

            // Escape to clear selection
            if (e.key === 'Escape') {
                clearSelection();
            }
        });

        // Clear selection
        function clearSelection() {
            selectedNotifications.clear();
            const checkboxes = document.querySelectorAll('.notification-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
                checkbox.style.display = 'none';
                checkbox.closest('.notification-card, .list-notification').style.border = '';
            });
            updateBulkActions();
        }

        // Bulk actions
        function markSelectedAsRead() {
            selectedNotifications.forEach(id => markNotificationAsRead(id));
            clearSelection();
        }

        function deleteSelected() {
            if (confirm(`Delete ${selectedNotifications.size} selected notifications?`)) {
                selectedNotifications.forEach(id => deleteNotification(id));
                clearSelection();
            }
        }

        // Listen for theme changes
        window.addEventListener('themeChanged', function(event) {
            applyCurrentTheme();
        });

        // Add smooth scrolling
        window.addEventListener('load', function() {
            document.documentElement.style.scrollBehavior = 'smooth';
        });

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
        document.querySelectorAll('.stat-card, .notification-card, .list-notification').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Add slideOutRight animation
        const style = document.createElement('style');
        style.textContent = `
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
        `;
        document.head.appendChild(style);

        // Student-specific notification handling
        function handleAcademicNotification(notificationData) {
            // Special handling for academic notifications
            if (notificationData.category === 'academic') {
                // Could add special visual effects or actions for academic notifications
                console.log('Academic notification received:', notificationData.title);
            }
        }

        function handleScheduleNotification(notificationData) {
            // Special handling for schedule notifications
            if (notificationData.category === 'schedule') {
                // Could add special visual effects or actions for schedule notifications
                console.log('Schedule notification received:', notificationData.title);
            }
        }

        // Academic progress tracking
        function trackNotificationInteraction(notificationId, action) {
            // Track how students interact with notifications for analytics
            const interaction = {
                notification_id: notificationId,
                action: action,
                timestamp: new Date().toISOString()
            };
            
            // Store in session storage for later submission
            const interactions = JSON.parse(sessionStorage.getItem('notification_interactions') || '[]');
            interactions.push(interaction);
            sessionStorage.setItem('notification_interactions', JSON.stringify(interactions));
        }

        // Filter notifications by academic relevance
        function filterAcademicNotifications() {
            const notifications = document.querySelectorAll('.notification-card, .list-notification');
            notifications.forEach(notification => {
                const category = notification.getAttribute('data-category');
                notification.style.display = (category === 'academic' || category === 'schedule') ? '' : 'none';
            });
        }

        // Priority notification handling
        function handleUrgentNotifications() {
            const urgentNotifications = document.querySelectorAll('[data-category="urgent"], [data-type="urgent"], [data-type="error"]');
            urgentNotifications.forEach(notification => {
                if (!notification.classList.contains('unread')) return;
                
                // Add special styling for urgent notifications
                notification.style.animation = 'pulse 2s infinite';
                notification.style.borderColor = 'var(--error-color)';
            });
        }

        // Initialize urgent notification handling
        document.addEventListener('DOMContentLoaded', function() {
            handleUrgentNotifications();
        });
    </script>
</body>
</html>

<?php
/**
 * Helper function for formatting notification priority for students
 * @param string $priority
 * @return string
 */
function formatStudentNotificationPriority($priority) {
    $priorities = [
        'low' => '🔽 Low Priority',
        'normal' => '📋 Normal',
        'high' => '🔼 Important',
        'urgent' => '🚨 Urgent - Action Required'
    ];
    return $priorities[$priority] ?? '📋 Normal';
}

/**
 * Helper function to check if notification is academic-related
 * @param string $related_table
 * @param string $type
 * @return bool
 */
function isAcademicNotification($related_table, $type) {
    $academic_tables = ['enrollments', 'subjects', 'grades', 'assignments'];
    return in_array($related_table, $academic_tables) || $type === 'academic';
}

/**
 * Helper function to check if notification is schedule-related
 * @param string $related_table
 * @param string $message
 * @return bool
 */
function isScheduleNotification($related_table, $message) {
    return $related_table === 'timetables' || 
           stripos($message, 'schedule') !== false ||
           stripos($message, 'timetable') !== false ||
           stripos($message, 'class') !== false;
}

/**
 * Helper function to get student-specific notification context
 * @param array $notification
 * @param int $studentId
 * @return array
 */
function getStudentNotificationContext($notification, $studentId) {
    $context = [
        'is_academic' => isAcademicNotification($notification['related_table'], $notification['type']),
        'is_schedule' => isScheduleNotification($notification['related_table'], $notification['message']),
        'urgency_level' => $notification['type'] === 'urgent' || $notification['type'] === 'error' ? 'high' : 'normal',
        'requires_action' => in_array($notification['type'], ['urgent', 'error', 'warning']),
        'category_icon' => getCategoryIcon($notification['category'] ?? 'general')
    ];
    
    return $context;
}

/**
 * Helper function to format academic notification for students
 * @param array $notification
 * @return string
 */
function formatAcademicNotification($notification) {
    $formatted = $notification['message'];
    
    // Add academic context if available
    if ($notification['related_table'] === 'enrollments') {
        $formatted = "📚 Enrollment Update: " . $formatted;
    } elseif ($notification['related_table'] === 'subjects') {
        $formatted = "📖 Subject Information: " . $formatted;
    } elseif ($notification['related_table'] === 'timetables') {
        $formatted = "📅 Schedule Update: " . $formatted;
    }
    
    return $formatted;
}
?>    