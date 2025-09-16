<?php
/**
 * Admin Notification Management - Notification Management Interface
 * Timetable Management System
 * 
 * Professional interface for admin to manage all system notifications including
 * creation, viewing, editing, and comprehensive notification analytics
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../classes/User.php';
require_once '../../classes/Notification.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$notificationManager = new Notification();

// Initialize variables
$notifications = [];
$notificationStats = [];
$error_message = '';
$success_message = '';

// Get flash messages
$flash_success = flash_get('success');
$flash_error = flash_get('error');
if ($flash_success) $success_message = $flash_success;
if ($flash_error) $error_message = $flash_error;
$selectedType = $_GET['type'] ?? '';
$selectedRole = $_GET['target_role'] ?? '';
$selectedPriority = $_GET['priority'] ?? '';
$selectedStatus = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;

// Handle bulk actions
if ($_POST['action'] ?? '' === 'bulk_action' && !empty($_POST['selected_notifications'])) {
    $selectedNotifications = $_POST['selected_notifications'];
    $bulkAction = $_POST['bulk_action_type'] ?? null; // Prevent undefined index warning
    
    try {
        if ($bulkAction === 'delete') {
            foreach ($selectedNotifications as $notificationId) {
                $notificationManager->deleteNotification($notificationId, $userId);
            }
            $success_message = "Selected notifications have been deleted successfully.";
        } elseif ($bulkAction === 'activate') {
            foreach ($selectedNotifications as $notificationId) {
                $notificationManager->updateNotification($notificationId, ['is_active' => 1], $userId);
            }
            $success_message = "Selected notifications have been activated successfully.";
        } elseif ($bulkAction === 'deactivate') {
            foreach ($selectedNotifications as $notificationId) {
                $notificationManager->updateNotification($notificationId, ['is_active' => 0], $userId);
            }
            $success_message = "Selected notifications have been deactivated successfully.";
        } elseif ($bulkAction === null || $bulkAction === '') {
            // No bulk action selected; provide gentle feedback without warnings
            $error_message = $error_message ?: 'Please select a bulk action to perform.';
        }
    } catch (Exception $e) {
        $error_message = "Bulk action failed: " . $e->getMessage();
    }
}

// Handle individual quick actions
if ($_POST['action'] ?? '' === 'quick_action') {
    $notificationId = (int)($_POST['notification_id'] ?? 0); // Prevent undefined index warning
    $quickAction = $_POST['quick_action_type'] ?? null; // Prevent undefined index warning
    
    try {
        if ($quickAction === 'toggle_status') {
            $notification = $notificationManager->getNotificationById($notificationId);
            if ($notification) {
                $newStatus = $notification['is_active'] ? 0 : 1;
                $result = $notificationManager->updateNotification($notificationId, ['is_active' => $newStatus], $userId);
                $success_message = $result['message'];
            }
        } elseif ($quickAction === 'delete') {
            $result = $notificationManager->deleteNotification($notificationId, $userId);
            $success_message = $result['message'];
        } elseif ($quickAction === null || $quickAction === '') {
            $error_message = $error_message ?: 'No quick action specified.';
        }
    } catch (Exception $e) {
        $error_message = "Action failed: " . $e->getMessage();
    }
}

try {
    // Get notification statistics
    $notificationStats = $notificationManager->getNotificationStatistics();
    
    // Build filters
    $filters = [];
    if (!empty($selectedType)) {
        $filters['type'] = $selectedType;
    }
    if (!empty($selectedRole)) {
        $filters['target_role'] = $selectedRole;
    }
    if (!empty($selectedPriority)) {
        $filters['priority'] = $selectedPriority;
    }
    
    // Add status filter logic
    if ($selectedStatus === 'active') {
        // Will be handled by the getAllNotifications method (only gets active by default)
    } elseif ($selectedStatus === 'expired') {
        $filters['date_to'] = date('Y-m-d', strtotime('-1 day')); // Expired notifications
    }
    
    // Get notifications with pagination
    $result = $notificationManager->getAllNotifications($filters, $page, $perPage);
    $notifications = $result['notifications'];
    $pagination = $result['pagination'];

} catch (Exception $e) {
    error_log("Admin Notification Management Error: " . $e->getMessage());
    $error_message = "Unable to load notifications data. Please try again later.";
    $notifications = [];
    $notificationStats = [];
    $pagination = [
        'current_page' => 1,
        'per_page' => $perPage,
        'total' => 0,
        'total_pages' => 0,
        'has_previous' => false,
        'has_next' => false
    ];
}

// Handle flash messages from URL parameters
$flashMessage = null;
if (isset($_GET['message'])) {
    if (isset($_GET['success'])) {
        $flashMessage = ['type' => 'success', 'message' => $_GET['message']];
    } elseif (isset($_GET['error'])) {
        $flashMessage = ['type' => 'error', 'message' => $_GET['message']];
    } else {
        // Default handling for backward compatibility
        if (isset($_GET['created'])) {
            $flashMessage = ['type' => 'success', 'message' => 'Notification created successfully.'];
        } elseif (isset($_GET['updated'])) {
            $flashMessage = ['type' => 'success', 'message' => 'Notification updated successfully.'];
        } elseif (isset($_GET['deleted'])) {
            $flashMessage = ['type' => 'success', 'message' => 'Notification deleted successfully.'];
        }
    }
}

// Set page title
$pageTitle = "Notification Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?> - Admin Dashboard</title>
    
    <!-- CSS Files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-color-alpha: rgba(59, 130, 246, 0.1);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.12);
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --navbar-height: 64px;
        }

        body {
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
            margin-bottom: 1.5rem;
        }

        /* Statistics Cards */
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

        .stat-icon.total { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .stat-icon.unread { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.read { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.urgent { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.875rem;
        }


        /* Search and Filters - Exactly like users index */
        .search-filters {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .search-box {
            position: relative;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.5);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .filter-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-select, .bulk-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.5);
            color: var(--text-primary);
            font-size: 0.875rem;
            min-width: 120px;
        }

        /* Action Buttons */
        .btn-action {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
        }

        /* Bulk Actions */
        .bulk-actions {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: none;
        }

        .bulk-actions.show {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Notifications Container - Exactly like users container */
        .notifications-container {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            overflow: hidden;
        }

        .table-responsive-custom {
            max-height: 65vh;
            overflow-y: auto;
            overflow-x: auto;
        }

        /* Sticky table header */
        .notifications-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--bg-primary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        /* Dark mode header background alignment */
        [data-theme="dark"] .notifications-table thead th {
            background: var(--bg-secondary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
        }

        .notifications-table {
            width: 100%;
            margin: 0;
        }

        .notifications-table thead {
            background: rgba(255, 255, 255, 0.3);
        }

        .notifications-table th {
            padding: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notifications-table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
        }

        /* Column widths and alignment to mirror users table */
        .notifications-table th:nth-child(1),
        .notifications-table td:nth-child(1) { /* checkbox */
            width: 44px;
            text-align: center;
            white-space: nowrap;
        }

        .notifications-table th:nth-child(9),
        .notifications-table td:nth-child(9) { /* Actions */
            width: 130px;
            text-align: center;
            white-space: nowrap;
        }

        /* Title & Message cell - professional layout */
        .notification-content { display: grid; grid-template-rows: auto auto auto; gap: 0.25rem; }
        .notif-title { display: flex; align-items: center; gap: 0.5rem; }
        .notif-type-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex: 0 0 8px; }
        .notif-type-dot.type-info { background: #3b82f6; }
        .notif-type-dot.type-success { background: #10b981; }
        .notif-type-dot.type-warning { background: #f59e0b; }
        .notif-type-dot.type-error { background: #ef4444; }
        .notif-type-dot.type-urgent { background: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.15); }
        .notif-icon { font-size: 1rem; line-height: 1; display: inline-flex; align-items: center; }
        .notif-icon.type-info { color: #3b82f6; }
        .notif-icon.type-success { color: #10b981; }
        .notif-icon.type-warning { color: #f59e0b; }
        .notif-icon.type-error { color: #ef4444; }
        .notif-icon.type-urgent { color: #dc2626; }
        .title-link { font-weight: 700; color: var(--text-primary); text-decoration: none; }
        .title-link:hover { text-decoration: underline; }
        .notif-preview { color: var(--text-secondary); font-size: 0.875rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .notif-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .chip { font-size: 0.75rem; padding: 0.2rem 0.5rem; border-radius: 999px; background: rgba(0,0,0,0.06); color: var(--text-secondary); border: 1px solid rgba(0,0,0,0.08); }
        .chip i { margin-right: 0.25rem; }
        .chip-expiring { background: rgba(245, 158, 11, 0.12); color: #92400e; border-color: rgba(245,158,11,0.25); }
        .chip-time { background: rgba(59,130,246,0.12); color: #1d4ed8; border-color: rgba(59,130,246,0.25); }

        [data-theme="dark"] .title-link { color: var(--text-primary); }
        [data-theme="dark"] .chip { background: rgba(255,255,255,0.06); border-color: var(--border-color); color: var(--text-secondary); }
        [data-theme="dark"] .chip-expiring { background: rgba(245, 158, 11, 0.18); color: #fbbf24; border-color: rgba(245,158,11,0.35); }
        [data-theme="dark"] .chip-time { background: rgba(59,130,246,0.18); color: #93c5fd; border-color: rgba(59,130,246,0.35); }

        .notifications-table tbody tr {
            transition: all 0.3s ease;
        }

        .notifications-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Dark mode table styles - Enhanced and Fixed */
        [data-theme="dark"] .notifications-container {
            background: rgba(0, 0, 0, 0.3) !important;
            border: 1px solid var(--glass-border) !important;
        }

        [data-theme="dark"] .notifications-table {
            background-color: transparent !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .notifications-table thead {
            background: rgba(30, 41, 59, 0.9) !important;
        }

        [data-theme="dark"] .notifications-table thead th {
            background-color: rgba(30, 41, 59, 0.9) !important;
            color: var(--text-primary) !important;
            border-bottom-color: var(--border-color) !important;
        }

        [data-theme="dark"] .notifications-table tbody tr {
            background-color: transparent !important;
            border-bottom: 1px solid var(--border-color) !important;
        }

        [data-theme="dark"] .notifications-table tbody tr:hover {
            background-color: rgba(30, 41, 59, 0.7) !important;
        }

        [data-theme="dark"] .notifications-table tbody tr:nth-child(even) {
            background-color: rgba(30, 41, 59, 0.3) !important;
        }

        [data-theme="dark"] .notifications-table tbody tr:nth-child(even):hover {
            background-color: rgba(30, 41, 59, 0.7) !important;
        }

        [data-theme="dark"] .notifications-table tbody td {
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
            background-color: transparent !important;
        }

        [data-theme="dark"] .notifications-table tbody td small {
            color: var(--text-secondary) !important;
        }

        [data-theme="dark"] .notification-content h6 {
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .notification-content p {
            color: var(--text-secondary) !important;
        }

        [data-theme="dark"] .creator-info {
            color: var(--text-primary) !important;
        }

        /* Notification Type Badges */
        .type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .type-badge.info { background: rgba(59, 130, 246, 0.2); color: #1d4ed8; }
        .type-badge.success { background: rgba(16, 185, 129, 0.2); color: #047857; }
        .type-badge.warning { background: rgba(245, 158, 11, 0.2); color: #92400e; }
        .type-badge.error { background: rgba(239, 68, 68, 0.2); color: #991b1b; }
        .type-badge.urgent { background: rgba(239, 68, 68, 0.3); color: #7f1d1d; }

        /* Priority Badges */
        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-badge.low { background: rgba(107, 114, 128, 0.2); color: #374151; }
        .priority-badge.normal { background: rgba(139, 92, 246, 0.2); color: #5b21b6; }
        .priority-badge.high { background: rgba(249, 115, 22, 0.2); color: #9a3412; }
        .priority-badge.urgent { background: rgba(239, 68, 68, 0.3); color: #7f1d1d; }

        /* Target Role Badges */
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-badge.all { background: rgba(107, 114, 128, 0.2); color: #374151; }
        .role-badge.admin { background: rgba(239, 68, 68, 0.2); color: #991b1b; }
        .role-badge.faculty { background: rgba(6, 182, 212, 0.2); color: #0e7490; }
        .role-badge.student { background: rgba(236, 72, 153, 0.2); color: #be185d; }

        /* Status Indicators */
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-indicator.active { background: var(--success-color); }
        .status-indicator.inactive { background: var(--error-color); }

        /* Action Buttons in Table - Match users index exactly */
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
            cursor: pointer;
            margin-right: 0.25rem;
        }

        .btn-action.btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-action.btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
            color: white;
        }

        .btn-action.btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-action.btn-outline:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
        }

        .btn-action.btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-action.btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        /* Mobile Cards for responsive design */
        .notification-card {
            display: none;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        /* Mobile: add thick blue border to cards */
        @media (max-width: 768px) {
            .notification-card {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8); /* blue-500 */
            }
        }

        .notification-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .notification-card-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        /* Ensure icon inside mobile avatar is visible and sized nicely */
        .notification-card-avatar i {
            font-size: 1.25rem;
            line-height: 1;
        }

        .notification-card-info h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .notification-card-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0.25rem 0;
        }

        .notification-card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .notification-card-detail {
            font-size: 0.875rem;
        }

        .notification-card-detail strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .notification-card-detail span {
            color: var(--text-secondary);
        }

        .notification-card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Dark mode cards */
        [data-theme="dark"] .notification-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        /* Dark mode: slightly stronger border contrast on mobile */
        @media (max-width: 768px) {
            [data-theme="dark"] .notification-card {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }
        }

        [data-theme="dark"] .notification-card-info h6 {
            color: var(--text-primary);
        }

        [data-theme="dark"] .notification-card-meta {
            color: var(--text-secondary);
        }

        [data-theme="dark"] .notification-card-detail strong {
            color: var(--text-primary);
        }

        [data-theme="dark"] .notification-card-detail span {
            color: var(--text-secondary);
        }
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 1.75rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .notifications-table {
                font-size: 0.875rem;
            }

            .notifications-table th,
            .notifications-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* Mobile: switch table to cards */
        @media (max-width: 768px) {
            .notifications-table {
                display: none !important;
            }
            .notification-card {
                display: block !important;
                /* Add left and right margins for mobile spacing */
                margin-left: 1rem;
                margin-right: 1rem;
            }
        }

        /* Alert Styling */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border-left: 4px solid #ef4444;
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
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">📢 Notification Management</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="create.php" class="btn-action btn-primary">
                            ➕ Create Notification
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($flashMessage && $flashMessage['type'] === 'success'): ?>
            <div class="alert alert-success glass-card" id="topSuccessAlert" role="alert">
                <strong>✅ Success!</strong> <?= htmlspecialchars($flashMessage['message']) ?>
            </div>
        <?php endif; ?>
        <?php if ($flashMessage && $flashMessage['type'] === 'error'): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <strong>❌ Error!</strong> <?= htmlspecialchars($flashMessage['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Additional success/error messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success glass-card" role="alert">
                <strong>✅ Success!</strong> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <strong>❌ Error!</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon total">📊</div>
                <div class="stat-number"><?= $notificationStats['total'] ?? 0 ?></div>
                <div class="stat-label">Total Notifications</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon unread">📬</div>
                <div class="stat-number"><?= $notificationStats['unread_count'] ?? 0 ?></div>
                <div class="stat-label">Unread</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon read">📭</div>
                <div class="stat-number"><?= $notificationStats['read_count'] ?? 0 ?></div>
                <div class="stat-label">Read</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon urgent">🚨</div>
                <div class="stat-number">
                    <?php
                    $urgentCount = 0;
                    if (!empty($notificationStats['by_priority'])) {
                        foreach ($notificationStats['by_priority'] as $priority) {
                            if ($priority['priority'] === 'urgent') {
                                $urgentCount = $priority['count'];
                                break;
                            }
                        }
                    }
                    echo $urgentCount;
                    ?>
                </div>
                <div class="stat-label">Urgent</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters glass-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search notifications..." id="searchInput">
                    <i class="bi bi-search search-icon"></i>
                </div>
                
                <div class="filter-controls">
                    <div class="filter-group">
                        <label class="filter-label">Type</label>
                        <select class="filter-select" id="typeFilter" onchange="handleTypeFilter()">
                            <option value="">All Types</option>
                            <option value="info" <?= $selectedType == 'info' ? 'selected' : '' ?>>Info</option>
                            <option value="success" <?= $selectedType == 'success' ? 'selected' : '' ?>>Success</option>
                            <option value="warning" <?= $selectedType == 'warning' ? 'selected' : '' ?>>Warning</option>
                            <option value="error" <?= $selectedType == 'error' ? 'selected' : '' ?>>Error</option>
                            <option value="urgent" <?= $selectedType == 'urgent' ? 'selected' : '' ?>>Urgent</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Target Role</label>
                        <select class="filter-select" id="roleFilter" onchange="handleRoleFilter()">
                            <option value="">All Roles</option>
                            <option value="all" <?= $selectedRole == 'all' ? 'selected' : '' ?>>All Users</option>
                            <option value="admin" <?= $selectedRole == 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="faculty" <?= $selectedRole == 'faculty' ? 'selected' : '' ?>>Faculty</option>
                            <option value="student" <?= $selectedRole == 'student' ? 'selected' : '' ?>>Student</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Priority</label>
                        <select class="filter-select" id="priorityFilter" onchange="handlePriorityFilter()">
                            <option value="">All Priorities</option>
                            <option value="low" <?= $selectedPriority == 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="normal" <?= $selectedPriority == 'normal' ? 'selected' : '' ?>>Normal</option>
                            <option value="high" <?= $selectedPriority == 'high' ? 'selected' : '' ?>>High</option>
                            <option value="urgent" <?= $selectedPriority == 'urgent' ? 'selected' : '' ?>>Urgent</option>
                        </select>
                    </div>
                    
                    <button class="btn-action btn-outline" onclick="clearFilters()">
                        🔄 Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions">
            <form method="POST" id="bulkActionForm" class="d-flex align-items-center gap-2 w-100">
                <input type="hidden" name="action" value="bulk_action">
                <span class="fw-bold">With selected:</span>
                <select name="bulk_action_type" class="bulk-select" required>
                    <option value="">Choose Action</option>
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmBulkAction()">
                    Apply
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                    Clear Selection
                </button>
            </form>
        </div>

        <!-- Notifications Table -->
        <div class="notifications-container glass-card">
            <?php if (empty($notifications)): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 22C13.1 22 14 21.1 14 20H10C10 21.1 10.9 22 12 22ZM18 16V11C18 7.93 16.36 5.36 13.5 4.68V4C13.5 3.17 12.83 2.5 12 2.5S10.5 3.17 10.5 4V4.68C7.63 5.36 6 7.92 6 11V16L4 18V19H20V18L18 16Z" fill="currentColor"/>
                    </svg>
                    <h4>No Notifications Found</h4>
                    <p>No notifications match your current filters. Try adjusting your search criteria or create a new notification.</p>
                    <a href="create.php" class="btn-action btn-primary">
                        ➕ Create First Notification
                    </a>
                </div>
            <?php else: ?>
                <!-- Table -->
                <div class="table-responsive-custom">
                    <table class="notifications-table table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Title & Message</th>
                                <th style="width: 100px;">Type</th>
                                <th style="width: 100px;">Priority</th>
                                <th style="width: 100px;">Target</th>
                                <th style="width: 120px;">Creator</th>
                                <th style="width: 120px;">Created</th>
                                <th style="width: 80px;">Status</th>
                                <th style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $notification): ?>
                                <tr id="notification-<?= $notification['notification_id'] ?>"
                                    data-notification-id="<?= $notification['notification_id'] ?>"
                                    data-type="<?= htmlspecialchars($notification['type']) ?>"
                                    data-priority="<?= htmlspecialchars($notification['priority']) ?>"
                                    data-role="<?= $notification['target_user_id'] ? 'individual' : htmlspecialchars($notification['target_role']) ?>">
                                    <td>
                                        <input type="checkbox" name="selected_notifications[]" 
                                               value="<?= $notification['notification_id'] ?>" 
                                               form="bulkActionForm"
                                               onchange="updateBulkActions()">
                                    </td>
                                    
                                    <td>
                                        <?php 
                                            $msg = $notification['message'] ?? '';
                                            $msgShort = mb_substr($msg, 0, 140);
                                            $msgShort .= (mb_strlen($msg) > 140) ? '…' : '';
                                        ?>
                                        <div class="notification-content">
                                            <div class="notif-title">
                                                <span class="notif-type-dot type-<?= htmlspecialchars($notification['type']) ?>"></span>
                                                <i class="bi bi-bell-fill notif-icon type-<?= htmlspecialchars($notification['type']) ?>" aria-hidden="true"></i>
                                                <a class="title-link" href="edit.php?id=<?= (int)$notification['notification_id'] ?>" title="View / Edit">
                                                    <?= htmlspecialchars($notification['title']) ?>
                                                </a>
                                            </div>
                                            <div class="notif-preview">
                                                <?= htmlspecialchars($msgShort) ?>
                                            </div>
                                            <div class="notif-meta">
                                                <span class="chip chip-time"><i class="bi bi-calendar3"></i><?= timeAgo($notification['created_at']) ?></span>
                                                <?php if (!empty($notification['expires_at'])): ?>
                                                    <span class="chip chip-expiring"><i class="bi bi-clock"></i>Expires: <?= date('M j, Y g:i A', strtotime($notification['expires_at'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <span class="type-badge <?= $notification['type'] ?>">
                                            <?= ucfirst($notification['type']) ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <span class="priority-badge <?= $notification['priority'] ?>">
                                            <?= ucfirst($notification['priority']) ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <span class="role-badge <?= $notification['target_role'] ?>">
                                            <?php if ($notification['target_user_id']): ?>
                                                <i class="bi bi-person"></i> Individual
                                            <?php else: ?>
                                                <?= ucfirst($notification['target_role']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <div class="creator-info">
                                            <small class="fw-medium">
                                                <?= htmlspecialchars($notification['creator_full_name'] ?? $notification['creator_name']) ?>
                                            </small>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <small class="text-muted">
                                            <?= timeAgo($notification['created_at']) ?>
                                        </small>
                                    </td>
                                    
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="status-indicator <?= $notification['is_active'] ? 'active' : 'inactive' ?>"></span>
                                            <small><?= $notification['is_active'] ? 'Active' : 'Inactive' ?></small>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center flex-wrap table-actions">
                                            <button class="btn-action btn-primary btn-sm" 
                                                    onclick="viewNotification(<?= (int)$notification['notification_id'] ?>)"
                                                    title="View Details">👁️</button>

                                            <a href="edit.php?id=<?= (int)$notification['notification_id'] ?>" 
                                               class="btn-action btn-outline btn-sm" 
                                               title="Edit Notification">✏️</a>

                                            <?php if (!empty($notification['is_active'])): ?>
                                                <a href="activate-deactivate.php?action=deactivate&id=<?= (int)$notification['notification_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                   class="btn-action btn-outline btn-sm" 
                                                   onclick="return confirm('Deactivate this notification?')" title="Deactivate">⏸️</a>
                                            <?php else: ?>
                                                <a href="activate-deactivate.php?action=activate&id=<?= (int)$notification['notification_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                   class="btn-action btn-success btn-sm" 
                                                   onclick="return confirm('Activate this notification?')" title="Activate">▶️</a>
                                            <?php endif; ?>

                                            <a href="delete.php?id=<?= (int)$notification['notification_id'] ?>" 
                                               class="btn-action btn-danger btn-sm" 
                                               title="Delete Notification"
                                               onclick="return confirm('Are you sure you want to delete this notification? This action cannot be undone.')">🗑️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile Card View -->
                    <div class="notifications-cards">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-card" data-notification-id="<?= $notification['notification_id'] ?>">
                                <div class="notification-card-header">
                                    <div class="notification-card-avatar"><i class="bi bi-bell-fill" aria-hidden="true"></i></div>
                                    <div class="notification-card-info">
                                        <h6><?= htmlspecialchars($notification['title']) ?></h6>
                                        <div class="notification-card-meta">
                                            <span class="type-badge <?= htmlspecialchars($notification['type']) ?>"><?= htmlspecialchars(ucfirst($notification['type'])) ?></span>
                                            <span class="priority-badge <?= htmlspecialchars($notification['priority']) ?> ms-2"><?= htmlspecialchars(ucfirst($notification['priority'])) ?></span>
                                            <span class="role-badge <?= htmlspecialchars($notification['target_role']) ?> ms-2"><?= htmlspecialchars(ucfirst($notification['target_role'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="notification-card-details">
                                    <div class="notification-card-detail">
                                        <strong>Message:</strong>
                                        <?php 
                                            $msg = $notification['message'] ?? '';
                                            $msgShort = mb_substr($msg, 0, 120);
                                            $msgShort .= (mb_strlen($msg) > 120) ? '…' : '';
                                        ?>
                                        <span><?= htmlspecialchars($msgShort) ?></span>
                                    </div>
                                    <div class="notification-card-detail">
                                        <strong>Status:</strong>
                                        <span><?= $notification['is_active'] ? 'Active' : 'Inactive' ?></span>
                                    </div>
                                    <div class="notification-card-detail">
                                        <strong>Creator:</strong>
                                        <span class="creator-name">
                                            <?= htmlspecialchars($notification['creator_name'] ?? ($notification['created_by_name'] ?? 'System')) ?>
                                        </span>
                                    </div>
                                    <div class="notification-card-detail">
                                        <strong>Created:</strong>
                                        <span><?= timeAgo($notification['created_at']) ?></span>
                                    </div>
                                    <?php if (!empty($notification['expires_at'])): ?>
                                        <div class="notification-card-detail">
                                            <strong>Expires:</strong>
                                            <span><?= date('M j, Y g:i A', strtotime($notification['expires_at'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-card-actions d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn-action btn-outline" onclick="viewNotification(<?= (int)$notification['notification_id'] ?>)"> View</button>
                                    <a href="edit.php?id=<?= (int)$notification['notification_id'] ?>" class="btn-action btn-outline"> Edit</a>
                                    <?php if ($notification['is_active']): ?>
                                        <a href="activate-deactivate.php?action=deactivate&id=<?= $notification['notification_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                           class="btn-action btn-outline" 
                                           onclick="return confirm('Deactivate this notification?')">
                                            ⏸️ Deactivate
                                        </a>
                                    <?php else: ?>
                                        <a href="activate-deactivate.php?action=activate&id=<?= $notification['notification_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                           class="btn-action btn-primary" 
                                           onclick="return confirm('Activate this notification?')">
                                            ▶️ Activate
                                        </a>
                                    <?php endif; ?>
                                    <a href="delete.php?id=<?= (int)$notification['notification_id'] ?>" 
                                       class="btn-action btn-danger" 
                                       title="Delete Notification"
                                       onclick="return confirm('Are you sure you want to delete this notification? This action cannot be undone.')">
                                        🗑️
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination-container">
                        <nav aria-label="Notifications pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($pagination['has_previous']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $pagination['current_page'] - 1 ?><?= buildQueryString() ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $pagination['current_page'] - 2);
                                $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <li class="page-item <?= $i == $pagination['current_page'] ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?><?= buildQueryString() ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($pagination['has_next']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $pagination['current_page'] + 1 ?><?= buildQueryString() ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        
                        <div class="text-center mt-2">
                            <small class="text-muted">
                                Showing <?= ($pagination['current_page'] - 1) * $pagination['per_page'] + 1 ?> 
                                to <?= min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) ?> 
                                of <?= $pagination['total'] ?> notifications
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Notification Detail Modal -->
    <div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notificationModalLabel">Notification Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="notificationModalBody">
                    <!-- Content will be loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="editNotificationBtn" class="btn btn-primary">Edit Notification</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>

        // Sidebar JS handlers removed to prevent unintended expansion on single link clicks.
        // Page relies on static CSS margins.

        // Client-side search and filters (no page reload)
        const searchInputEl = document.getElementById('searchInput');
        const typeFilterEl = document.getElementById('typeFilter');
        const roleFilterEl = document.getElementById('roleFilter');
        const priorityFilterEl = document.getElementById('priorityFilter');

        function getTextContentSafe(el, selector) {
            const node = el.querySelector(selector);
            return node ? node.textContent.toLowerCase() : '';
        }

        function setFilterParamInURL(param, value) {
            const url = new URL(window.location);
            if (value) url.searchParams.set(param, value); else url.searchParams.delete(param);
            url.searchParams.delete('page');
            history.replaceState(null, '', url);
        }

        function applyClientFilters() {
            const q = (searchInputEl.value || '').trim().toLowerCase();
            const minLen = 3;
            const typeVal = (typeFilterEl?.value || '').toLowerCase();
            const roleVal = (roleFilterEl?.value || '').toLowerCase();
            const priorityVal = (priorityFilterEl?.value || '').toLowerCase();

            const rows = document.querySelectorAll('.notifications-table tbody tr');
            const cards = document.querySelectorAll('.notification-card');

            rows.forEach(row => {
                const rowType = (row.getAttribute('data-type') || '').toLowerCase();
                const rowRole = (row.getAttribute('data-role') || '').toLowerCase();
                const rowPriority = (row.getAttribute('data-priority') || '').toLowerCase();

                // Text match
                let textMatch = true;
                if (q.length >= minLen) {
                    const title = getTextContentSafe(row, '.title-link');
                    const preview = getTextContentSafe(row, '.notif-preview');
                    const creator = getTextContentSafe(row, '.creator-info');
                    const typeTxt = getTextContentSafe(row, '.type-badge');
                    const priorityTxt = getTextContentSafe(row, '.priority-badge');
                    const roleTxt = getTextContentSafe(row, '.role-badge');
                    const createdTxt = getTextContentSafe(row, 'td small.text-muted');
                    const statusTxt = getTextContentSafe(row, 'td .d-flex small');
                    textMatch = [title, preview, creator, typeTxt, priorityTxt, roleTxt, createdTxt, statusTxt]
                        .some(v => v && v.includes(q));
                }

                // Type/Role/Priority match
                const typeMatch = !typeVal || rowType === typeVal;
                const roleMatch = !roleVal || rowRole === roleVal;
                const priorityMatch = !priorityVal || rowPriority === priorityVal;

                row.style.display = (textMatch && typeMatch && roleMatch && priorityMatch) ? '' : 'none';
            });

            // Cards (mobile view)
            cards.forEach(card => {
                let title = getTextContentSafe(card, '.notification-card-info h6');
                let message = '';
                const msgNode = card.querySelector('.notification-card-detail span');
                if (msgNode) message = msgNode.textContent.toLowerCase();
                let creator = getTextContentSafe(card, '.creator-name');

                const badges = {
                    type: getTextContentSafe(card, '.type-badge'),
                    priority: getTextContentSafe(card, '.priority-badge'),
                    role: getTextContentSafe(card, '.role-badge')
                };

                let textMatch = true;
                if (q.length >= minLen) {
                    const typeTxt = getTextContentSafe(card, '.type-badge');
                    const priorityTxt = getTextContentSafe(card, '.priority-badge');
                    const roleTxt = getTextContentSafe(card, '.role-badge');
                    textMatch = [title, message, creator, typeTxt, priorityTxt, roleTxt]
                        .some(v => v && v.includes(q));
                }

                const typeMatch = !typeVal || badges.type === typeVal;
                const roleMatch = !roleVal || badges.role === roleVal;
                const priorityMatch = !priorityVal || badges.priority === priorityVal;

                card.style.display = (textMatch && typeMatch && roleMatch && priorityMatch) ? '' : 'none';
            });
        }

        // Wire up events
        if (searchInputEl) {
            searchInputEl.addEventListener('input', applyClientFilters);
        }
        function handleTypeFilter() {
            const v = typeFilterEl.value; setFilterParamInURL('type', v); applyClientFilters();
        }
        function handleRoleFilter() {
            const v = roleFilterEl.value; setFilterParamInURL('target_role', v); applyClientFilters();
        }
        function handlePriorityFilter() {
            const v = priorityFilterEl.value; setFilterParamInURL('priority', v); applyClientFilters();
        }
        function clearFilters() {
            if (typeFilterEl) typeFilterEl.value = '';
            if (roleFilterEl) roleFilterEl.value = '';
            if (priorityFilterEl) priorityFilterEl.value = '';
            if (searchInputEl) searchInputEl.value = '';
            setFilterParamInURL('type', '');
            setFilterParamInURL('target_role', '');
            setFilterParamInURL('priority', '');
            applyClientFilters();
        }

        // Select all functionality
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('input[name="selected_notifications[]"]');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        // Update bulk actions visibility
        function updateBulkActions() {
            const selectedCheckboxes = document.querySelectorAll('input[name="selected_notifications[]"]:checked');
            const bulkActions = document.getElementById('bulkActions');
            
            if (selectedCheckboxes.length > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
            }
        }

        // Clear selection
        function clearSelection() {
            const checkboxes = document.querySelectorAll('input[name="selected_notifications[]"]');
            const selectAll = document.getElementById('selectAll');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAll.checked = false;
            
            updateBulkActions();
        }

        // Bulk action confirmation
        function confirmBulkAction() {
            const selectedCheckboxes = document.querySelectorAll('input[name="selected_notifications[]"]:checked');
            const actionSelect = document.querySelector('select[name="bulk_action_type"]');
            
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one notification.');
                return false;
            }
            
            if (!actionSelect.value) {
                alert('Please select an action.');
                return false;
            }
            
            const actionText = actionSelect.options[actionSelect.selectedIndex].text;
            return confirm(`Are you sure you want to ${actionText.toLowerCase()} ${selectedCheckboxes.length} notification(s)?`);
        }

        // Quick action confirmation
        function confirmQuickAction(action) {
            return confirm(`Are you sure you want to ${action} this notification?`);
        }

        // View notification details
        function viewNotification(notificationId) {
            // Show loading state
            document.getElementById('notificationModalBody').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('notificationModal'));
            modal.show();
            
            // Update edit button
            document.getElementById('editNotificationBtn').href = `edit.php?id=${notificationId}`;
            
            // Fetch notification details via AJAX
            fetch(`/timetable-management/includes/api/get_notification.php?id=${notificationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const notification = data.notification;
                        document.getElementById('notificationModalBody').innerHTML = `
                            <div class="notification-details">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h4>${notification.title}</h4>
                                        <div class="mb-3">
                                            <span class="type-badge ${notification.type}">${notification.type}</span>
                                            <span class="priority-badge ${notification.priority} ms-2">${notification.priority}</span>
                                            <span class="role-badge ${notification.target_role} ms-2">${notification.target_role}</span>
                                        </div>
                                        <div class="message-content p-3 bg-light rounded">
                                            ${notification.message.replace(/\n/g, '<br>')}
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="notification-meta">
                                            <p><strong>Creator:</strong><br>${notification.creator_full_name || notification.creator_name}</p>
                                            <p><strong>Created:</strong><br>${new Date(notification.created_at).toLocaleString()}</p>
                                            <p><strong>Status:</strong><br>${notification.is_active ? 'Active' : 'Inactive'}</p>
                                            ${notification.expires_at ? `<p><strong>Expires:</strong><br>${new Date(notification.expires_at).toLocaleString()}</p>` : ''}
                                            ${notification.related_table ? `<p><strong>Related:</strong><br>${notification.related_table} #${notification.related_id}</p>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        document.getElementById('notificationModalBody').innerHTML = '<div class="alert alert-danger">Failed to load notification details.</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('notificationModalBody').innerHTML = '<div class="alert alert-danger">Error loading notification details.</div>';
                });
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            applyCurrentTheme();

            // Auto-hide success alerts after 5 seconds
            setTimeout(function() {
                const successAlert = document.getElementById('topSuccessAlert');
                if (successAlert) {
                    const bsAlert = new bootstrap.Alert(successAlert);
                    bsAlert.close();
                }
            }, 5000);

            // Update bulk actions on page load
            updateBulkActions();

            // Apply current filters (from preselected selects or URL params) without reloading
            if (typeof applyClientFilters === 'function') {
                applyClientFilters();
            }
        });

        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
            
            // Force table styling update for dark mode
            if (theme === 'dark') {
                applyDarkModeTableStyles();
            } else {
                removeDarkModeTableStyles();
            }
        }

        function applyDarkModeTableStyles() {
            // Force dark mode styles on table elements
            const table = document.querySelector('.notifications-table');
            const container = document.querySelector('.notifications-container');
            
            if (container) {
                container.style.setProperty('background', 'rgba(0, 0, 0, 0.3)', 'important');
                container.style.setProperty('border', '1px solid rgba(255, 255, 255, 0.1)', 'important');
            }
            
            if (table) {
                // Apply dark styles to table
                table.style.setProperty('background-color', 'transparent', 'important');
                table.style.setProperty('color', '#ffffff', 'important');
                
                // Apply to thead
                const thead = table.querySelector('thead');
                if (thead) {
                    thead.style.setProperty('background', 'rgba(30, 41, 59, 0.9)', 'important');
                    const thElements = thead.querySelectorAll('th');
                    thElements.forEach(th => {
                        th.style.setProperty('background-color', 'rgba(30, 41, 59, 0.9)', 'important');
                        th.style.setProperty('color', '#ffffff', 'important');
                        th.style.setProperty('border-bottom-color', '#404040', 'important');
                    });
                }
                
                // Apply to tbody rows
                const tbody = table.querySelector('tbody');
                if (tbody) {
                    const rows = tbody.querySelectorAll('tr');
                    rows.forEach((row, index) => {
                        row.style.setProperty('background-color', index % 2 === 1 ? 'rgba(30, 41, 59, 0.3)' : 'transparent', 'important');
                        row.style.setProperty('border-bottom', '1px solid #404040', 'important');
                        
                        // Apply to cells
                        const cells = row.querySelectorAll('td');
                        cells.forEach(cell => {
                            cell.style.setProperty('color', '#ffffff', 'important');
                            cell.style.setProperty('border-color', '#404040', 'important');
                            
                            // Apply to text elements within cells
                            const textElements = cell.querySelectorAll('h6, p, span:not(.type-badge):not(.priority-badge):not(.role-badge), strong, small');
                            textElements.forEach(element => {
                                if (!element.classList.contains('type-badge') && 
                                    !element.classList.contains('priority-badge') && 
                                    !element.classList.contains('role-badge')) {
                                    element.style.setProperty('color', '#ffffff', 'important');
                                }
                            });
                        });
                        
                        // Add hover effect
                        row.addEventListener('mouseenter', function() {
                            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                                this.style.setProperty('background-color', 'rgba(30, 41, 59, 0.7)', 'important');
                            }
                        });
                        
                        row.addEventListener('mouseleave', function() {
                            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                                this.style.setProperty('background-color', index % 2 === 1 ? 'rgba(30, 41, 59, 0.3)' : 'transparent', 'important');
                            }
                        });
                    });
                }
            }
        }

        function removeDarkModeTableStyles() {
            // Remove inline dark mode styles for light mode
            const table = document.querySelector('.notifications-table');
            const container = document.querySelector('.notifications-container');
            
            if (container) {
                container.style.removeProperty('background');
                container.style.removeProperty('border');
            }
            
            if (table) {
                table.style.removeProperty('background-color');
                table.style.removeProperty('color');
                
                // Remove from all child elements
                const allElements = table.querySelectorAll('*');
                allElements.forEach(element => {
                    element.style.removeProperty('background-color');
                    element.style.removeProperty('color');
                    element.style.removeProperty('border-color');
                    element.style.removeProperty('border-bottom');
                });
            }
        }

        // Auto-scroll to updated notification entry after activate/deactivate
        function autoScrollToUpdatedEntry() {
            const urlParams = new URLSearchParams(window.location.search);
            const updatedId = urlParams.get('updated_id');
            const updated = urlParams.get('updated');
            
            if (updated === '1' && updatedId) {
                // Try to find the updated entry in both desktop and mobile views
                const desktopRow = document.querySelector(`tr[data-notification-id="${updatedId}"]`);
                const mobileCard = document.querySelector(`.notification-card[data-notification-id="${updatedId}"]`);
                
                const targetElement = desktopRow || mobileCard;
                
                if (targetElement) {
                    // Smooth scroll to the element
                    setTimeout(() => {
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        
                        // Add temporary highlight effect
                        targetElement.style.transition = 'all 0.3s ease';
                        targetElement.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
                        targetElement.style.transform = 'scale(1.02)';
                        
                        // Remove highlight after 2 seconds
                        setTimeout(() => {
                            targetElement.style.backgroundColor = '';
                            targetElement.style.transform = '';
                        }, 2000);
                    }, 500); // Small delay to ensure page is fully rendered
                    
                    // Clean up URL parameters
                    const newUrl = new URL(window.location);
                    newUrl.searchParams.delete('updated');
                    newUrl.searchParams.delete('updated_id');
                    window.history.replaceState({}, '', newUrl);
                }
            }
        }

        // Run auto-scroll on page load
        document.addEventListener('DOMContentLoaded', autoScrollToUpdatedEntry);
    </script>
</body>
</html>

<?php
/**
 * Helper function to build query string for pagination
 */
function buildQueryString() {
    $params = [];
    
    if (!empty($_GET['type'])) {
        $params[] = 'type=' . urlencode($_GET['type']);
    }
    if (!empty($_GET['target_role'])) {
        $params[] = 'target_role=' . urlencode($_GET['target_role']);
    }
    if (!empty($_GET['priority'])) {
        $params[] = 'priority=' . urlencode($_GET['priority']);
    }
    if (!empty($_GET['status'])) {
        $params[] = 'status=' . urlencode($_GET['status']);
    }
    
    return !empty($params) ? '&' . implode('&', $params) : '';
}

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