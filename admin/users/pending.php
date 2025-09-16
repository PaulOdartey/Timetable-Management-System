<?php
/**
 * Admin Pending Users Management - Pending Registration Interface
 * Timetable Management System
 * 
 * Professional interface for admin to review and manage pending user registrations
 * with bulk approval/rejection and comprehensive user details
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$userManager = new User();

// Initialize variables
$pendingUsers = [];
$departments = [];
$error_message = '';
$success_message = '';
$selectedRole = $_GET['role'] ?? '';
$selectedDepartment = $_GET['department'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'desc';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] ?? '' === 'bulk_action' && !empty($_POST['selected_users'])) {
    $selectedUsers = $_POST['selected_users'];
    $bulkAction = $_POST['bulk_action_type'];
    
    try {
        if ($bulkAction === 'approve') {
            $result = $userManager->bulkApproveUsers($selectedUsers, $userId);
            $success_message = $result['message'];
        } elseif ($bulkAction === 'reject') {
            $result = $userManager->bulkRejectUsers($selectedUsers, $userId);
            $success_message = $result['message'];
        }
    } catch (Exception $e) {
        $error_message = "Bulk action failed: " . $e->getMessage();
    }
}

try {
    // Build WHERE clause for pending users with filters
    $whereConditions = ["u.status = 'pending'"];
    $params = [];
    
    if (!empty($selectedRole)) {
        $whereConditions[] = "u.role = ?";
        $params[] = $selectedRole;
    }
    
    if (!empty($selectedDepartment)) {
        $whereConditions[] = "(s.department = ? OR f.department = ?)";
        $params[] = $selectedDepartment;
        $params[] = $selectedDepartment;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Validate sort parameters
    $allowedSortFields = ['created_at', 'username', 'email', 'role', 'full_name', 'department'];
    $allowedSortOrders = ['asc', 'desc'];
    
    if (!in_array($sortBy, $allowedSortFields)) {
        $sortBy = 'created_at';
    }
    if (!in_array($sortOrder, $allowedSortOrders)) {
        $sortOrder = 'desc';
    }
    
    // Get pending users with comprehensive information
    $pendingUsers = $db->fetchAll("
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
               END as department,
               CASE 
                   WHEN u.role = 'student' THEN s.student_number
                   WHEN u.role = 'faculty' THEN f.employee_id
                   ELSE NULL
               END as identifier,
               CASE 
                   WHEN u.role = 'student' THEN s.phone
                   WHEN u.role = 'faculty' THEN f.phone
                   ELSE NULL
               END as phone,
               CASE 
                   WHEN u.role = 'faculty' THEN f.designation
                   ELSE NULL
               END as designation,
               CASE 
                   WHEN u.role = 'student' THEN CONCAT('Year ', s.year_of_study, ', Semester ', s.semester)
                   WHEN u.role = 'faculty' THEN f.qualification
                   ELSE NULL
               END as additional_info,
               CASE 
                   WHEN u.role = 'faculty' THEN f.experience_years
                   ELSE NULL
               END as experience_years,
               DATEDIFF(NOW(), u.created_at) as days_pending
        FROM users u
        LEFT JOIN students s ON u.user_id = s.user_id
        LEFT JOIN faculty f ON u.user_id = f.user_id
        WHERE {$whereClause}
        ORDER BY {$sortBy} {$sortOrder}
    ", $params);

    // Get unique departments for filter
    $departments = $db->fetchAll("
        SELECT DISTINCT department 
        FROM (
            SELECT s.department FROM users u 
            LEFT JOIN students s ON u.user_id = s.user_id 
            WHERE u.status = 'pending' AND s.department IS NOT NULL
            UNION 
            SELECT f.department FROM users u 
            LEFT JOIN faculty f ON u.user_id = f.user_id 
            WHERE u.status = 'pending' AND f.department IS NOT NULL
        ) as departments 
        ORDER BY department ASC
    ");

    // Get pending statistics
    $pendingStats = $db->fetchRow("
        SELECT 
            COUNT(*) as total_pending,
            COUNT(CASE WHEN u.role = 'faculty' THEN 1 END) as pending_faculty,
            COUNT(CASE WHEN u.role = 'student' THEN 1 END) as pending_students,
            COUNT(CASE WHEN DATEDIFF(NOW(), u.created_at) > 7 THEN 1 END) as overdue_registrations,
            COUNT(CASE WHEN u.email_verified = 0 THEN 1 END) as unverified_emails
        FROM users u 
        WHERE u.status = 'pending'
    ");

} catch (Exception $e) {
    error_log("Pending Users Error: " . $e->getMessage());
    $error_message = "An error occurred while loading pending users.";
}

// Helper function for time ago display
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    return date('M j, Y', strtotime($datetime));
}

// Set page title
$pageTitle = "Pending Registrations";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?> - Admin Panel</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-color-alpha: rgba(99, 102, 241, 0.1);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --info-color: #3b82f6;
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border-color: #475569;
            --navbar-height: 64px;
            --sidebar-width: 280px;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border-color: #475569;
        }

        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-color: #cbd5e1;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--navbar-height) + 2rem);
        }

        body.sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Page Header - Sticky under navbar */
        .page-header {
            position: sticky;
            top: var(--navbar-height);
            z-index: 998;
            margin-bottom: 1rem;
        }

        .header-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        [data-theme="dark"] .header-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
            border-color: var(--border-color);
        }

        .header-text {
            display: flex;
            flex-direction: column;
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        /* Back button styling */
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .back-icon {
            font-size: 1rem;
            font-weight: bold;
        }

        /* Glass card styling */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] .glass-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .glass-card {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            padding: 1.5rem;
            border-radius: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .stat-card.urgent::before {
            background: linear-gradient(90deg, var(--error-color), var(--warning-color));
        }

        .stat-card.warning::before {
            background: linear-gradient(90deg, var(--warning-color), var(--info-color));
        }

        /* Filters and Controls */
        .controls-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        [data-theme="dark"] .controls-section {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .controls-section {
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .filter-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        [data-theme="dark"] .filter-select {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        [data-theme="light"] .filter-select {
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        /* Bulk Actions */
        .bulk-actions {
            background: var(--primary-color-alpha);
            border: 1px solid var(--primary-color);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: none;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .bulk-actions.show {
            display: flex;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* User Cards */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .user-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        [data-theme="dark"] .user-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .user-card {
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .user-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .user-info {
            flex-grow: 1;
            margin-left: 1rem;
        }

        .user-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .user-email {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .user-role {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .user-role.faculty {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
        }

        .user-role.student {
            background: rgba(59, 130, 246, 0.2);
            color: var(--info-color);
        }

        .user-checkbox {
            position: absolute;
            top: 1rem;
            right: 1rem;
            transform: scale(1.2);
        }

        .user-details {
            margin: 1rem 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
        }

        .user-detail {
            font-size: 0.875rem;
        }

        .user-detail strong {
            color: var(--text-primary);
            display: block;
            margin-bottom: 0.25rem;
        }

        .user-detail span {
            color: var(--text-secondary);
        }

        .user-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .pending-badge {
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--warning-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .urgent-pending {
            border-left: 4px solid var(--error-color) !important;
        }

        .urgent-pending .pending-badge {
            background: var(--error-color);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .unverified-email {
            border-left: 4px solid var(--warning-color) !important;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        /* Button Styles */
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            border: none;
            cursor: pointer;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            color: white;
        }

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            color: white;
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

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-1px);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: calc(var(--navbar-height) + 1rem);
            }

            .users-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header-card {
                flex-direction: row;
                text-align: left;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .users-grid {
                grid-template-columns: 1fr;
            }

            .user-actions {
                justify-content: center;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
        }

        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        .slide-up {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Sort controls */
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sort-link {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .sort-link:hover {
            color: var(--primary-color);
        }

        .sort-link.active {
            color: var(--primary-color);
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
                <div class="header-text">
                    <h1 class="page-title">⏳ Pending Registrations</h1>
                    <p class="page-subtitle">
                        Review and manage user registrations awaiting approval
                    </p>
                </div>
                <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                    <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success glass-card fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card glass-card">
                <div class="stat-icon">📋</div>
                <div class="stat-number"><?= $pendingStats['total_pending'] ?></div>
                <div class="stat-label">Total Pending</div>
            </div>
            
            <div class="stat-card glass-card">
                <div class="stat-icon">🎓</div>
                <div class="stat-number"><?= $pendingStats['pending_faculty'] ?></div>
                <div class="stat-label">Faculty Pending</div>
            </div>
            
            <div class="stat-card glass-card">
                <div class="stat-icon">🎒</div>
                <div class="stat-number"><?= $pendingStats['pending_students'] ?></div>
                <div class="stat-label">Students Pending</div>
            </div>
            
            <div class="stat-card glass-card <?= ($pendingStats['overdue_registrations'] ?? 0) > 0 ? 'urgent' : '' ?>">
                <div class="stat-icon">⏰</div>
                <div class="stat-number"><?= $pendingStats['overdue_registrations'] ?></div>
                <div class="stat-label">Overdue (7+ days)</div>
            </div>
            
            <div class="stat-card glass-card <?= ($pendingStats['unverified_emails'] ?? 0) > 0 ? 'warning' : '' ?>">
                <div class="stat-icon">📧</div>
                <div class="stat-number"><?= $pendingStats['unverified_emails'] ?></div>
                <div class="stat-label">Unverified Emails</div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section glass-card slide-up">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 style="margin: 0; color: var(--text-primary);">
                    <i class="fas fa-filter"></i> Filters & Actions
                </h3>
                <div class="sort-controls">
                    <span style="color: var(--text-secondary); font-size: 0.875rem;">Sort by:</span>
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => $sortBy === 'created_at' && $sortOrder === 'desc' ? 'asc' : 'desc'])) ?>" 
                       class="sort-link <?= $sortBy === 'created_at' ? 'active' : '' ?>">
                        Date 
                        <?php if ($sortBy === 'created_at'): ?>
                            <i class="fas fa-sort-<?= $sortOrder === 'desc' ? 'down' : 'up' ?>"></i>
                        <?php endif; ?>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'full_name', 'order' => $sortBy === 'full_name' && $sortOrder === 'asc' ? 'desc' : 'asc'])) ?>" 
                       class="sort-link <?= $sortBy === 'full_name' ? 'active' : '' ?>">
                        Name
                        <?php if ($sortBy === 'full_name'): ?>
                            <i class="fas fa-sort-<?= $sortOrder === 'desc' ? 'down' : 'up' ?>"></i>
                        <?php endif; ?>
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'role', 'order' => $sortBy === 'role' && $sortOrder === 'asc' ? 'desc' : 'asc'])) ?>" 
                       class="sort-link <?= $sortBy === 'role' ? 'active' : '' ?>">
                        Role
                        <?php if ($sortBy === 'role'): ?>
                            <i class="fas fa-sort-<?= $sortOrder === 'desc' ? 'down' : 'up' ?>"></i>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Role</label>
                    <select class="filter-select" id="roleFilter" onchange="handleRoleFilter()">
                        <option value="">All Roles</option>
                        <option value="faculty" <?= $selectedRole == 'faculty' ? 'selected' : '' ?>>Faculty</option>
                        <option value="student" <?= $selectedRole == 'student' ? 'selected' : '' ?>>Student</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Department</label>
                    <select class="filter-select" id="departmentFilter" onchange="handleDepartmentFilter()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept['department']) ?>" <?= $selectedDepartment == $dept['department'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['department']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button class="btn-action btn-outline" onclick="clearFilters()">
                        🔄 Clear Filters
                    </button>
                </div>
                
                <div class="filter-group">
                    <button class="btn-action btn-primary" onclick="selectAll()">
                        <i class="fas fa-check-square"></i> Select All
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions (shown when users are selected) -->
        <form method="post" id="bulkActionForm">
            <div class="bulk-actions" id="bulkActions">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span><strong id="selectedCount">0</strong> users selected</span>
                    
                    <select name="bulk_action_type" class="filter-select" required>
                        <option value="">Choose Action</option>
                        <option value="approve">✅ Approve Selected</option>
                        <option value="reject">❌ Reject Selected</option>
                    </select>
                    
                    <input type="hidden" name="action" value="bulk_action">
                    
                    <button type="submit" class="btn-action btn-primary" onclick="return confirmBulkAction()">
                        Execute Action
                    </button>
                    
                    <button type="button" class="btn-action btn-outline" onclick="clearSelection()">
                        Clear Selection
                    </button>
                </div>
            </div>
        </form>

        <!-- Pending Users Grid -->
        <?php if (empty($pendingUsers)): ?>
            <div class="empty-state glass-card slide-up">
                <i class="fas fa-inbox"></i>
                <h3>No Pending Registrations</h3>
                <p>All user registrations have been processed. Great work!</p>
                <a href="index.php" class="btn-action btn-primary">
                    <i class="fas fa-users"></i> View All Users
                </a>
            </div>
        <?php else: ?>
            <div class="users-grid slide-up">
                <?php foreach ($pendingUsers as $user): ?>
                    <div class="user-card <?= $user['days_pending'] > 7 ? 'urgent-pending' : '' ?> <?= !$user['email_verified'] ? 'unverified-email' : '' ?>">
                        <?php if ($user['days_pending'] > 7): ?>
                            <div class="pending-badge">
                                <?= $user['days_pending'] ?> days pending
                            </div>
                        <?php endif; ?>

                        <div class="user-card-header">
                            <div class="d-flex align-items-start">
                                <div class="user-avatar">
                                    <?php 
                                    $name = $user['full_name'] ?? $user['username'];
                                    $initials = '';
                                    $nameParts = explode(' ', $name);
                                    foreach (array_slice($nameParts, 0, 2) as $part) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                    }
                                    echo $initials ?: strtoupper(substr($user['username'], 0, 2));
                                    ?>
                                </div>
                                <div class="user-info">
                                    <div class="user-name">
                                        <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>
                                        <?php if (!$user['email_verified']): ?>
                                            <i class="fas fa-exclamation-triangle text-warning" title="Email not verified"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                    <span class="user-role <?= strtolower($user['role']) ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </div>
                            </div>
                            <input type="checkbox" class="user-checkbox" name="selected_users[]" 
                                   value="<?= $user['user_id'] ?>" onchange="updateBulkActions()">
                        </div>

                        <div class="user-details">
                            <?php if ($user['department']): ?>
                                <div class="user-detail">
                                    <strong>Department</strong>
                                    <span><?= htmlspecialchars($user['department']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($user['identifier']): ?>
                                <div class="user-detail">
                                    <strong><?= $user['role'] === 'student' ? 'Student No.' : 'Employee ID' ?></strong>
                                    <span><?= htmlspecialchars($user['identifier']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($user['phone']): ?>
                                <div class="user-detail">
                                    <strong>Phone</strong>
                                    <span><?= htmlspecialchars($user['phone']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($user['designation']): ?>
                                <div class="user-detail">
                                    <strong>Designation</strong>
                                    <span><?= htmlspecialchars($user['designation']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($user['additional_info']): ?>
                                <div class="user-detail">
                                    <strong><?= $user['role'] === 'student' ? 'Academic Info' : 'Qualification' ?></strong>
                                    <span><?= htmlspecialchars($user['additional_info']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($user['experience_years']): ?>
                                <div class="user-detail">
                                    <strong>Experience</strong>
                                    <span><?= $user['experience_years'] ?> years</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="user-detail">
                                <strong>Registered</strong>
                                <span><?= timeAgo($user['created_at']) ?></span>
                            </div>
                            
                            <div class="user-detail">
                                <strong>Email Status</strong>
                                <span class="<?= $user['email_verified'] ? 'text-success' : 'text-warning' ?>">
                                    <?= $user['email_verified'] ? '✅ Verified' : '⚠️ Unverified' ?>
                                </span>
                            </div>
                        </div>

                        <div class="user-actions">
                            <a href="approve-reject.php?action=approve&id=<?= $user['user_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn-action btn-success" 
                               onclick="return confirm('Approve registration for <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>?')">
                                ✅ Approve
                            </a>
                            
                            <a href="approve-reject.php?action=reject&id=<?= $user['user_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn-action btn-danger" 
                               onclick="return confirm('Reject registration for <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?>?')">
                                ❌ Reject
                            </a>
                            
                            <button class="btn-action btn-primary" onclick="viewUserDetails(<?= $user['user_id'] ?>)">
                                👁️ Details
                            </button>
                            
                            <?php if (!$user['email_verified']): ?>
                                <button class="btn-action btn-warning" onclick="resendVerification(<?= $user['user_id'] ?>)">
                                    📧 Resend Email
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($pendingUsers)): ?>
            <!-- Quick Stats Summary -->
            <div class="glass-card fade-in" style="margin-top: 2rem; padding: 1rem; text-align: center;">
                <p style="color: var(--text-secondary); margin: 0;">
                    <strong><?= count($pendingUsers) ?></strong> pending registrations displayed
                    <?php if (!empty($selectedRole) || !empty($selectedDepartment)): ?>
                        (filtered results)
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </main>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="userDetailsModalLabel" style="color: var(--text-primary);">
                        <i class="fas fa-user"></i> User Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="userDetailsContent">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn-action btn-outline" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Handle sidebar toggle events
            handleSidebarToggle();
            
            // Initialize bulk actions
            updateBulkActions();
        });

        /**
         * Apply current theme from localStorage
         */
        function applyCurrentTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            
            // Update theme toggle icon if it exists
            const themeIcon = document.querySelector('#themeToggle i');
            if (themeIcon) {
                themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        /**
         * Handle sidebar toggle
         */
        function handleSidebarToggle() {
            const toggleBtn = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (toggleBtn && sidebar && mainContent) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    document.body.classList.toggle('sidebar-collapsed');
                });
            }
        }

        /**
         * Handle role filter
         */
        function handleRoleFilter() {
            const roleFilter = document.getElementById('roleFilter');
            const currentUrl = new URL(window.location);
            
            if (roleFilter.value) {
                currentUrl.searchParams.set('role', roleFilter.value);
            } else {
                currentUrl.searchParams.delete('role');
            }
            
            window.location.href = currentUrl.toString();
        }

        /**
         * Handle department filter
         */
        function handleDepartmentFilter() {
            const departmentFilter = document.getElementById('departmentFilter');
            const currentUrl = new URL(window.location);
            
            if (departmentFilter.value) {
                currentUrl.searchParams.set('department', departmentFilter.value);
            } else {
                currentUrl.searchParams.delete('department');
            }
            
            window.location.href = currentUrl.toString();
        }

        /**
         * Clear all filters
         */
        function clearFilters() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.delete('role');
            currentUrl.searchParams.delete('department');
            currentUrl.searchParams.delete('sort');
            currentUrl.searchParams.delete('order');
            
            window.location.href = currentUrl.pathname;
        }

        /**
         * Select all checkboxes
         */
        function selectAll() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            
            updateBulkActions();
        }

        /**
         * Update bulk actions visibility and count
         */
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const actionSelect = document.querySelector('select[name="bulk_action_type"]');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = checkboxes.length;
                actionSelect.setAttribute('required', '');
                
                // Update hidden inputs for selected users
                const existingInputs = document.querySelectorAll('input[name="selected_users[]"]');
                existingInputs.forEach(input => {
                    if (input.type === 'hidden') {
                        input.remove();
                    }
                });
                
                checkboxes.forEach(checkbox => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'selected_users[]';
                    hiddenInput.value = checkbox.value;
                    document.getElementById('bulkActionForm').appendChild(hiddenInput);
                });
            } else {
                bulkActions.classList.remove('show');
                actionSelect.removeAttribute('required');
                actionSelect.value = '';
                
                // Remove hidden inputs
                const existingInputs = document.querySelectorAll('input[name="selected_users[]"][type="hidden"]');
                existingInputs.forEach(input => input.remove());
            }
        }

        /**
         * Clear selection
         */
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkActions();
        }

        /**
         * Confirm bulk action
         */
        function confirmBulkAction() {
            const actionSelect = document.querySelector('select[name="bulk_action_type"]');
            const selectedCount = document.getElementById('selectedCount').textContent;
            
            if (!actionSelect.value) {
                alert('Please select an action to perform.');
                return false;
            }
            
            const actionText = actionSelect.options[actionSelect.selectedIndex].text;
            const message = `Are you sure you want to ${actionText.toLowerCase()} ${selectedCount} user(s)?\n\nThis action cannot be undone.`;
            
            return confirm(message);
        }

        /**
         * View user details in modal
         */
        async function viewUserDetails(userId) {
            const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
            const modalContent = document.getElementById('userDetailsContent');
            
            // Show loading state
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading user details...</p>
                </div>
            `;
            
            modal.show();

            try {
                const response = await fetch(`../../includes/api/user-details.php?id=${userId}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();

                if (data.success) {
                    modalContent.innerHTML = generateUserDetailsHTML(data.user);
                } else {
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>Error:</strong> ${data.message || 'Failed to load user details'}
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading user details:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Error:</strong> Failed to load user details. Please check your connection and try again.
                    </div>
                `;
            }
        }

        /**
         * Generate user details HTML
         */
        function generateUserDetailsHTML(user) {
            const verificationBadge = user.email_verified 
                ? '<span class="badge bg-success">✅ Verified</span>'
                : '<span class="badge bg-warning">⚠️ Unverified</span>';
                
            const daysPending = Math.floor((new Date() - new Date(user.created_at)) / (1000 * 60 * 60 * 24));
            const urgentBadge = daysPending > 7 
                ? '<span class="badge bg-danger">🚨 Urgent</span>'
                : '';

            return `
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        <div class="user-avatar mx-auto" style="width: 80px; height: 80px; font-size: 2rem;">
                            ${user.full_name ? user.full_name.split(' ').map(n => n[0]).join('').toUpperCase() : user.username.substring(0, 2).toUpperCase()}
                        </div>
                        <h5 class="mt-2" style="color: var(--text-primary);">${user.full_name || user.username}</h5>
                        <span class="badge bg-${user.role === 'faculty' ? 'success' : 'info'}">${user.role.toUpperCase()}</span>
                        ${verificationBadge}
                        ${urgentBadge}
                    </div>
                    <div class="col-md-8">
                        <table class="table table-borderless">
                            <tr><th style="color: var(--text-primary);">Email:</th><td style="color: var(--text-secondary);">${user.email}</td></tr>
                            <tr><th style="color: var(--text-primary);">Username:</th><td style="color: var(--text-secondary);">${user.username}</td></tr>
                            ${user.department ? `<tr><th style="color: var(--text-primary);">Department:</th><td style="color: var(--text-secondary);">${user.department}</td></tr>` : ''}
                            ${user.identifier ? `<tr><th style="color: var(--text-primary);">${user.role === 'student' ? 'Student Number' : 'Employee ID'}:</th><td style="color: var(--text-secondary);">${user.identifier}</td></tr>` : ''}
                            ${user.phone ? `<tr><th style="color: var(--text-primary);">Phone:</th><td style="color: var(--text-secondary);">${user.phone}</td></tr>` : ''}
                            ${user.designation ? `<tr><th style="color: var(--text-primary);">Designation:</th><td style="color: var(--text-secondary);">${user.designation}</td></tr>` : ''}
                            <tr><th style="color: var(--text-primary);">Registered:</th><td style="color: var(--text-secondary);">${new Date(user.created_at).toLocaleDateString()} (${daysPending} days ago)</td></tr>
                            <tr><th style="color: var(--text-primary);">Status:</th><td><span class="badge bg-warning">PENDING APPROVAL</span></td></tr>
                        </table>
                    </div>
                </div>
            `;
        }

        /**
         * Resend verification email
         */
        async function resendVerification(userId) {
            if (!confirm('Resend verification email to this user?')) {
                return;
            }

            try {
                const response = await fetch('../../includes/api/resend-verification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Verification email sent successfully!');
                } else {
                    alert('Failed to send verification email: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error resending verification:', error);
                alert('Failed to send verification email. Please try again.');
            }
        }

        /**
         * Theme toggle functionality (if needed)
         */
        function toggleTheme() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update icon
            const themeIcon = document.querySelector('#themeToggle i');
            if (themeIcon) {
                themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        // Make functions available globally
        window.handleRoleFilter = handleRoleFilter;
        window.handleDepartmentFilter = handleDepartmentFilter;
        window.clearFilters = clearFilters;
        window.selectAll = selectAll;
        window.updateBulkActions = updateBulkActions;
        window.clearSelection = clearSelection;
        window.viewUserDetails = viewUserDetails;
        window.resendVerification = resendVerification;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>