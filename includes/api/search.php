<?php
/**
 * Faculty Notifications Page
 * Timetable Management System
 * 
 * Professional notifications page for faculty members with modern glassmorphism design
 * Displays all notifications with filtering, marking as read, and real-time updates
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
$notifications = [];
$stats = [
    'total' => 0,
    'unread' => 0,
    'today' => 0,
    'this_week' => 0
];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'mark_read':
                $notificationId = (int)$_POST['notification_id'];
                $result = $db->execute("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE notification_id = ? AND (target_user_id = ? OR target_role IN ('faculty', 'all'))
                ", [$notificationId, $userId]);
                
                echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
                exit;
                
            case 'mark_all_read':
                $result = $db->execute("
                    UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE is_read = 0 AND (target_user_id = ? OR target_role IN ('faculty', 'all'))
                ", [$userId]);
                
                echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
                exit;
                
            case 'delete_notification':
                $notificationId = (int)$_POST['notification_id'];
                $result = $db->execute("
                    UPDATE notifications 
                    SET is_active = 0 
                    WHERE notification_id = ? AND (target_user_id = ? OR target_role IN ('faculty', 'all'))
                ", [$notificationId, $userId]);
                
                echo json_encode(['success' => true, 'message' => 'Notification deleted']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause based on filters
$whereConditions = ["(target_user_id = ? OR target_role IN ('faculty', 'all'))", "is_active = 1"];
$params = [$userId];

if ($filter === 'unread') {
    $whereConditions[] = "is_read = 0";
} elseif ($filter === 'read') {
    $whereConditions[] = "is_read = 1";
} elseif ($filter === 'today') {
    $whereConditions[] = "DATE(created_at) = CURDATE()";
} elseif ($filter === 'week') {
    $whereConditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
} elseif ($filter === 'urgent') {
    $whereConditions[] = "priority = 'urgent'";
}

if (!empty($search)) {
    $whereConditions[] = "(title LIKE ? OR message LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereClause = implode(" AND ", $whereConditions);

try {
    // Get notifications with pagination
    $notifications = $db->fetchAll("
        SELECT 
            n.*,
            CASE 
                WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'just now'
                WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN CONCAT(TIMESTAMPDIFF(HOUR, n.created_at, NOW()), ' hours ago')
                WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK) THEN CONCAT(TIMESTAMPDIFF(DAY, n.created_at, NOW()), ' days ago')
                ELSE DATE_FORMAT(n.created_at, '%M %d, %Y')
            END as time_ago,
            u.username as created_by_name
        FROM notifications n
        LEFT JOIN users u ON n.created_by = u.user_id
        WHERE {$whereClause}
        ORDER BY n.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ", $params);

    // Get total count for pagination
    $totalCount = $db->fetchColumn("
        SELECT COUNT(*) 
        FROM notifications n
        WHERE {$whereClause}
    ", $params);

    // Get statistics
    $stats['total'] = $db->fetchColumn("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE (target_user_id = ? OR target_role IN ('faculty', 'all')) AND is_active = 1
    ", [$userId]);

    $stats['unread'] = $db->fetchColumn("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE (target_user_id = ? OR target_role IN ('faculty', 'all')) AND is_active = 1 AND is_read = 0
    ", [$userId]);

    $stats['today'] = $db->fetchColumn("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE (target_user_id = ? OR target_role IN ('faculty', 'all')) AND is_active = 1 AND DATE(created_at) = CURDATE()
    ", [$userId]);

    $stats['this_week'] = $db->fetchColumn("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE (target_user_id = ? OR target_role IN ('faculty', 'all')) AND is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)
    ", [$userId]);

} catch (Exception $e) {
    error_log("Faculty Notifications Error: " . $e->getMessage());
    $error_message = "Unable to load notifications. Please try again later.";
}

// Calculate pagination
$totalPages = ceil($totalCount / $limit);

// Set page title and current page for navigation
$pageTitle = "Notifications";
$currentPage = "notifications";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SYSTEM_NAME ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom Styles matching index.php glassmorphism design -->
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon.week {
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
            font-size: 0.875rem;
        }

        /* Filters and Controls */
        .controls-section {
            margin-bottom: 2rem;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.75rem 1.5rem;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 25px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .filter-tab:hover {
            background: rgba(255, 255, 255, 0.4);
            color: var(--text-primary);
            transform: translateY(-1px);
        }

        .filter-tab.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .search-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            background: var(--glass-bg);
            color: var(--text-primary);
            backdrop-filter: blur(10px);
        }

        .search-input::placeholder {
            color: var(--text-tertiary);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: color-mix(in srgb, var(--primary-color) 90%, black);
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
        }

        /* Notifications List */
        .notifications-container {
            display: grid;
            gap: 1rem;
        }

        .notification-card {
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .notification-card:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .notification-card.unread {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(255, 255, 255, 0.3) 100%);
            border-left: 4px solid var(--primary-color);
        }

        .notification-card.read {
            background: var(--glass-bg);
            opacity: 0.8;
        }

        .notification-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .notification-icon.info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .notification-icon.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .notification-icon.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .notification-icon.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .notification-icon.urgent {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            animation: pulse 2s infinite;
        }

        .notification-details h5 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .notification-time {
            color: var(--text-tertiary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .notification-action {
            width: 32px;
            height: 32px;
            border: none;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-secondary);
        }

        .notification-action:hover {
            background: rgba(255, 255, 255, 0.4);
            color: var(--text-primary);
            transform: scale(1.1);
        }

        .notification-message {
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .notification-priority {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-low {
            background: rgba(34, 197, 94, 0.1);
            color: #059669;
        }

        .priority-normal {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }

        .priority-high {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .priority-urgent {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            animation: pulse 2s infinite;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            gap: 1rem;
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .pagination a:hover {
            background: rgba(255, 255, 255, 0.4);
            transform: translateY(-1px);
        }

        .pagination .current {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
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

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        /* Animations */
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

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Loading States */
        .loading-skeleton {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.3) 25%, rgba(255, 255, 255, 0.5) 50%, rgba(255, 255, 255, 0.3) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 8px;
            height: 1rem;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
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

            .welcome-title {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .search-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                min-width: auto;
                width: 100%;
            }

            .filter-tabs {
                justify-content: center;
            }

            .notification-header {
                flex-direction: column;
                gap: 1rem;
            }

            .notification-meta {
                width: 100%;
            }

            .notification-actions {
                align-self: stretch;
                justify-content: flex-end;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-tabs {
                grid-template-columns: repeat(2, 1fr);
                display: grid;
                gap: 0.5rem;
            }

            .filter-tab {
                text-align: center;
                padding: 0.5rem;
                font-size: 0.875rem;
            }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-lg);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            border-left: 4px solid var(--error-color);
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
                    <h1 class="welcome-title">📱 Notifications</h1>
                    <p class="welcome-subtitle">
                        Stay updated with important announcements, schedule changes, and system notifications.
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon total">
                    📊
                </div>
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Notifications</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon unread">
                    🔴
                </div>
                <div class="stat-number"><?= $stats['unread'] ?></div>
                <div class="stat-label">Unread</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon today">
                    📅
                </div>
                <div class="stat-number"><?= $stats['today'] ?></div>
                <div class="stat-label">Today</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon week">
                    📆
                </div>
                <div class="stat-number"><?= $stats['this_week'] ?></div>
                <div class="stat-label">This Week</div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section glass-card">
            <div style="padding: 1.5rem;">
                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <a href="?filter=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                       class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                        All
                    </a>
                    <a href="?filter=unread<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                       class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">
                        Unread (<?= $stats['unread'] ?>)
                    </a>
                    <a href="?filter=read<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                       class="filter-tab <?= $filter === 'read' ? 'active' : '' ?>">
                        Read
                    </a>
                    <a href="?filter=today<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                       class="filter-tab <?= $filter === 'today' ? 'active' : '' ?>">
                        Today
                    </a>
                    <a href="?filter=week<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                       class="filter-tab <?= $filter === 'week' ? 'active' : '' ?>">
                        This Week
                    </a>
                    <a href="?filter=urgent<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                       class="filter-tab <?= $filter === 'urgent' ? 'active' : '' ?>">
                        Urgent
                    </a>
                </div>

                <!-- Search and Actions -->
                <form method="GET" class="search-controls">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                    <input type="text" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search notifications..." 
                           class="search-input">
                    <button type="submit" class="btn btn-primary">
                        🔍 Search
                    </button>
                    <?php if ($stats['unread'] > 0): ?>
                        <button type="button" class="btn btn-outline-primary" onclick="markAllAsRead()">
                            ✓ Mark All Read
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="notifications-container">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $index => $notification): ?>
                    <div class="notification-card glass-card <?= $notification['is_read'] ? 'read' : 'unread' ?> fade-in" 
                         data-notification-id="<?= $notification['notification_id'] ?>"
                         style="animation-delay: <?= $index * 0.1 ?>s">
                        
                        <div class="notification-header">
                            <div class="notification-meta">
                                <div class="notification-icon <?= $notification['type'] ?>">
                                    <?php
                                    $icons = [
                                        'info' => 'ℹ️',
                                        'success' => '✅',
                                        'warning' => '⚠️',
                                        'error' => '❌',
                                        'urgent' => '🚨'
                                    ];
                                    echo $icons[$notification['type']] ?? 'ℹ️';
                                    ?>
                                </div>
                                <div class="notification-details">
                                    <h5><?= htmlspecialchars($notification['title']) ?></h5>
                                    <div class="notification-time"><?= $notification['time_ago'] ?></div>
                                </div>
                            </div>
                            
                            <div class="notification-actions">
                                <?php if (!$notification['is_read']): ?>
                                    <button class="notification-action" 
                                            onclick="markAsRead(<?= $notification['notification_id'] ?>)"
                                            title="Mark as read">
                                        ✓
                                    </button>
                                <?php endif; ?>
                                <button class="notification-action" 
                                        onclick="deleteNotification(<?= $notification['notification_id'] ?>)"
                                        title="Delete notification">
                                    🗑️
                                </button>
                            </div>
                        </div>

                        <div class="notification-message">
                            <?= nl2br(htmlspecialchars($notification['message'])) ?>
                        </div>

                        <?php if ($notification['priority'] !== 'normal'): ?>
                            <div style="margin-top: 1rem;">
                                <span class="notification-priority priority-<?= $notification['priority'] ?>">
                                    <?= ucfirst($notification['priority']) ?> Priority
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if (!$notification['is_read']): ?>
                            <div style="position: absolute; top: 1rem; right: 4rem; width: 8px; height: 8px; background: var(--primary-color); border-radius: 50%;"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&filter=<?= urlencode($filter) ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                    ← Previous
                                </a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $i ?>&filter=<?= urlencode($filter) ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&filter=<?= urlencode($filter) ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                    Next →
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div style="color: var(--text-secondary); font-size: 0.875rem;">
                            Showing <?= (($page - 1) * $limit) + 1 ?> to <?= min($page * $limit, $totalCount) ?> of <?= $totalCount ?> notifications
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state glass-card">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M13.73 21C13.5542 21.3031 13.3019 21.5556 12.9988 21.7314C12.6956 21.9072 12.3522 21.999 12 21.999C11.6478 21.999 11.3044 21.9072 11.0012 21.7314C10.6981 21.5556 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <h3>
                        <?php if (!empty($search)): ?>
                            No notifications found
                        <?php elseif ($filter === 'unread'): ?>
                            No unread notifications
                        <?php else: ?>
                            No notifications yet
                        <?php endif; ?>
                    </h3>
                    <p>
                        <?php if (!empty($search)): ?>
                            Try adjusting your search terms or filter settings.
                        <?php elseif ($filter === 'unread'): ?>
                            You're all caught up! 🎉
                        <?php else: ?>
                            Notifications will appear here when you receive them.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search) || $filter !== 'all'): ?>
                        <a href="notifications.php" class="btn btn-primary" style="margin-top: 1rem;">
                            View All Notifications
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize notifications functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Add animation delays for staggered effect
            const animatedElements = document.querySelectorAll('.fade-in');
            animatedElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.05}s`;
            });

            // Auto-refresh notifications every 30 seconds
            setInterval(checkForNewNotifications, 30000);

            // Mark notification as read when clicked (not on action buttons)
            document.querySelectorAll('.notification-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicking on action buttons
                    if (e.target.closest('.notification-actions')) return;
                    
                    const notificationId = this.dataset.notificationId;
                    if (this.classList.contains('unread')) {
                        markAsRead(notificationId, false);
                    }
                });
            });

            // Handle keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + A to mark all as read
                if ((e.ctrlKey || e.metaKey) && e.key === 'a' && document.querySelectorAll('.notification-card.unread').length > 0) {
                    e.preventDefault();
                    markAllAsRead();
                }

                // R to refresh
                if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
                    e.preventDefault();
                    window.location.reload();
                }
            });
        });

        // Mark single notification as read
        async function markAsRead(notificationId, showToast = true) {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=mark_read&notification_id=${notificationId}`
                });

                const data = await response.json();
                
                if (data.success) {
                    // Update UI
                    const card = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (card) {
                        card.classList.remove('unread');
                        card.classList.add('read');
                        
                        // Remove unread indicator
                        const indicator = card.querySelector('div[style*="position: absolute"]');
                        if (indicator) indicator.remove();
                        
                        // Remove mark as read button
                        const markReadBtn = card.querySelector('.notification-action[title="Mark as read"]');
                        if (markReadBtn) markReadBtn.remove();
                    }
                    
                    // Update stats
                    updateUnreadCount();
                    
                    if (showToast) {
                        showToast('Notification marked as read', 'success');
                    }
                } else {
                    showToast('Failed to mark notification as read', 'error');
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
                showToast('Network error occurred', 'error');
            }
        }

        // Mark all notifications as read
        async function markAllAsRead() {
            const unreadCount = document.querySelectorAll('.notification-card.unread').length;
            
            if (unreadCount === 0) {
                showToast('No unread notifications', 'info');
                return;
            }

            if (!confirm(`Mark all ${unreadCount} notifications as read?`)) {
                return;
            }

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=mark_all_read'
                });

                const data = await response.json();
                
                if (data.success) {
                    // Update UI
                    document.querySelectorAll('.notification-card.unread').forEach(card => {
                        card.classList.remove('unread');
                        card.classList.add('read');
                        
                        // Remove unread indicator
                        const indicator = card.querySelector('div[style*="position: absolute"]');
                        if (indicator) indicator.remove();
                        
                        // Remove mark as read button
                        const markReadBtn = card.querySelector('.notification-action[title="Mark as read"]');
                        if (markReadBtn) markReadBtn.remove();
                    });
                    
                    // Update stats and hide mark all button
                    updateUnreadCount();
                    const markAllBtn = document.querySelector('button[onclick="markAllAsRead()"]');
                    if (markAllBtn) markAllBtn.style.display = 'none';
                    
                    showToast(`All ${unreadCount} notifications marked as read`, 'success');
                } else {
                    showToast('Failed to mark all notifications as read', 'error');
                }
            } catch (error) {
                console.error('Error marking all notifications as read:', error);
                showToast('Network error occurred', 'error');
            }
        }

        // Delete notification
        async function deleteNotification(notificationId) {
            if (!confirm('Are you sure you want to delete this notification?')) {
                return;
            }

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_notification&notification_id=${notificationId}`
                });

                const data = await response.json();
                
                if (data.success) {
                    // Remove from UI with animation
                    const card = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (card) {
                        card.style.transform = 'translateX(100%)';
                        card.style.opacity = '0';
                        setTimeout(() => {
                            card.remove();
                            
                            // Check if no notifications left
                            if (document.querySelectorAll('.notification-card').length === 0) {
                                window.location.reload();
                            }
                        }, 300);
                    }
                    
                    updateUnreadCount();
                    showToast('Notification deleted', 'success');
                } else {
                    showToast('Failed to delete notification', 'error');
                }
            } catch (error) {
                console.error('Error deleting notification:', error);
                showToast('Network error occurred', 'error');
            }
        }

        // Update unread count in stats and navigation
        function updateUnreadCount() {
            const unreadCards = document.querySelectorAll('.notification-card.unread');
            const unreadCount = unreadCards.length;
            
            // Update stat card
            const unreadStatNumber = document.querySelector('.stat-icon.unread').parentElement.querySelector('.stat-number');
            if (unreadStatNumber) {
                unreadStatNumber.textContent = unreadCount;
                
                // Add animation
                unreadStatNumber.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    unreadStatNumber.style.transform = 'scale(1)';
                }, 200);
            }
            
            // Update filter tab
            const unreadTab = document.querySelector('.filter-tab[href*="filter=unread"]');
            if (unreadTab) {
                unreadTab.textContent = `Unread (${unreadCount})`;
            }
            
            // Update navbar notification badge
            const navBadge = document.querySelector('.notification-badge');
            if (navBadge) {
                if (unreadCount > 0) {
                    navBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                    navBadge.style.display = 'flex';
                } else {
                    navBadge.style.display = 'none';
                }
            }
        }

        // Check for new notifications
        async function checkForNewNotifications() {
            try {
                const response = await fetch('../includes/api/check-notifications.php');
                const data = await response.json();
                
                if (data.success && data.hasNew) {
                    // Show notification about new notifications
                    showToast(`${data.newCount} new notification(s) received`, 'info');
                    
                    // Optionally reload if on first page and no filters
                    const urlParams = new URLSearchParams(window.location.search);
                    if (!urlParams.get('page') && urlParams.get('filter') !== 'read') {
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
                }
            } catch (error) {
                console.error('Error checking for new notifications:', error);
            }
        }

        // Show toast notification
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 1.2rem;">×</button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Show with animation
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
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

        // Enhanced hover effects
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.glass-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('no-hover')) {
                        this.style.transform = 'translateY(-4px) scale(1.01)';
                    }
                });

                card.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('no-hover')) {
                        this.style.transform = 'translateY(0) scale(1)';
                    }
                });
            });
        });

        // Auto-scroll to unread notifications on page load
        window.addEventListener('load', function() {
            const firstUnread = document.querySelector('.notification-card.unread');
            if (firstUnread && window.location.hash === '') {
                firstUnread.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        // Real-time updates using Server-Sent Events (if implemented)
        if (typeof EventSource !== 'undefined') {
            const eventSource = new EventSource('../includes/api/notification-stream.php');
            
            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);
                
                if (data.type === 'new_notification') {
                    showToast('New notification received!', 'info');
                    
                    // If on first page, add the new notification to the top
                    const urlParams = new URLSearchParams(window.location.search);
                    if ((!urlParams.get('page') || urlParams.get('page') === '1') && 
                        (urlParams.get('filter') === 'all' || !urlParams.get('filter'))) {
                        // Reload to show new notification
                        setTimeout(() => window.location.reload(), 1000);
                    }
                }
            };
            
            eventSource.onerror = function() {
                console.log('Notification stream connection error');
                eventSource.close();
            };
        }
    </script>
</body>
</html>