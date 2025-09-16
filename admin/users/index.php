?<?php
/**
 * Admin Users Management - User Management Interface
 * Timetable Management System
 * 
 * Professional interface for admin to manage all system users including
 * pending registrations, active users, and comprehensive user analytics
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
$users = [];
$userStats = [];
$departments = [];
$error_message = '';
$success_message = '';
$selectedRole = $_GET['role'] ?? '';
$selectedStatus = $_GET['status'] ?? '';
$selectedDepartment = $_GET['department'] ?? '';

// Handle bulk actions
if (($_POST['action'] ?? '') === 'bulk_action' && !empty($_POST['selected_users'])) {
    $selectedUsers = $_POST['selected_users'];
    $bulkAction = $_POST['bulk_action_type'] ?? '';
    try {
        if ($bulkAction === 'approve') {
            $result = $userManager->bulkApproveUsers($selectedUsers, $userId);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => $result['message'] ?? 'Selected users approved successfully.'];
        } elseif ($bulkAction === 'reject') {
            $result = $userManager->bulkRejectUsers($selectedUsers, $userId);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => $result['message'] ?? 'Selected users rejected successfully.'];
        } elseif ($bulkAction === 'activate') {
            foreach ($selectedUsers as $targetUserId) {
                $userManager->changeUserStatus((int)$targetUserId, 'active', $userId);
            }
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Selected users have been activated successfully.'];
        } elseif ($bulkAction === 'deactivate') {
            foreach ($selectedUsers as $targetUserId) {
                $userManager->changeUserStatus((int)$targetUserId, 'inactive', $userId);
            }
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Selected users have been deactivated successfully.'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid bulk action.'];
        }
    } catch (Exception $e) {
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Bulk action failed: ' . $e->getMessage()];
    }
    // Preserve current filters on redirect
    $queryParams = [];
    if (!empty($selectedRole)) { $queryParams['role'] = $selectedRole; }
    if (!empty($selectedStatus)) { $queryParams['status'] = $selectedStatus; }
    if (!empty($selectedDepartment)) { $queryParams['department'] = $selectedDepartment; }
    $redirectUrl = 'index.php' . (empty($queryParams) ? '' : ('?' . http_build_query($queryParams)));
    header('Location: ' . $redirectUrl);
    exit;
}

// Individual actions are now handled by separate handlers:
// - activate-deactivate.php for activate/deactivate actions
// - approve-reject.php for approve/reject actions

try {
    // Build filters for user query
    $filters = [];
    if (!empty($selectedRole)) {
        $filters['role'] = $selectedRole;
    }
    if (!empty($selectedStatus)) {
        $filters['status'] = $selectedStatus;
    }
    if (!empty($selectedDepartment)) {
        $filters['department'] = $selectedDepartment;
    }

    // Build WHERE clause with proper parameter binding
    $whereConditions = [];
    $params = [];
    
    if (!empty($selectedRole)) {
        $whereConditions[] = "u.role = ?";
        $params[] = $selectedRole;
    }
    
    if (!empty($selectedStatus)) {
        $whereConditions[] = "u.status = ?";
        $params[] = $selectedStatus;
    }
    
    if (!empty($selectedDepartment)) {
        $whereConditions[] = "(s.department = ? OR f.department = ? OR a.department = ?)";
        $params[] = $selectedDepartment;
        $params[] = $selectedDepartment;
        $params[] = $selectedDepartment;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get all users with comprehensive information
    $users = $db->fetchAll("
        SELECT u.*, 
               CASE 
                   WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                   WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                   WHEN u.role = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                   ELSE u.username
               END as full_name,
               CASE 
                   WHEN u.role = 'student' THEN s.department
                   WHEN u.role = 'faculty' THEN f.department
                   WHEN u.role = 'admin' THEN a.department
                   ELSE NULL
               END as department,
               CASE 
                   WHEN u.role = 'student' THEN s.student_number
                   WHEN u.role = 'faculty' THEN f.employee_id
                   WHEN u.role = 'admin' THEN a.employee_id
                   ELSE NULL
               END as identifier,
               CASE 
                   WHEN u.role = 'student' THEN s.phone
                   WHEN u.role = 'faculty' THEN f.phone
                   WHEN u.role = 'admin' THEN a.phone
                   ELSE NULL
               END as phone,
               CASE 
                   WHEN u.role = 'faculty' THEN f.designation
                   WHEN u.role = 'admin' THEN a.designation
                   ELSE NULL
               END as designation,
               CASE 
                   WHEN u.role = 'student' THEN CONCAT('Year ', s.year_of_study)
                   ELSE NULL
               END as academic_info
        FROM users u
        LEFT JOIN students s ON u.user_id = s.user_id
        LEFT JOIN faculty f ON u.user_id = f.user_id
        LEFT JOIN admin_profiles a ON u.user_id = a.user_id
        {$whereClause}
        ORDER BY u.created_at DESC
    ", $params);

    // Get user statistics
    $userStats = $db->fetchRow("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_approvals,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_users,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
            COUNT(CASE WHEN role = 'faculty' THEN 1 END) as faculty_count,
            COUNT(CASE WHEN role = 'student' THEN 1 END) as student_count,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_registrations
        FROM users
    ");

    // Get unique departments for filter dropdown
    $departments = $db->fetchAll("
        SELECT DISTINCT department 
        FROM (
            SELECT department FROM students WHERE department IS NOT NULL
            UNION 
            SELECT department FROM faculty WHERE department IS NOT NULL
            UNION
            SELECT department FROM admin_profiles WHERE department IS NOT NULL
        ) as all_departments 
        ORDER BY department ASC
    ");

} catch (Exception $e) {
    error_log("Admin Users Management Error: " . $e->getMessage());
    $error_message = "Unable to load users data. Please try again later.";
    $userStats = [
        'total_users' => 0, 'pending_approvals' => 0, 'active_users' => 0, 
        'inactive_users' => 0, 'rejected_users' => 0, 'admin_count' => 0, 
        'faculty_count' => 0, 'student_count' => 0, 'recent_registrations' => 0
    ];
}

// Set page title
$pageTitle = "User Management";
$currentPage = "users";
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
            --sidebar-collapsed-width: 70px;
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
        .stat-icon.pending { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.active { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.recent { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .stat-icon.faculty { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .stat-icon.students { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }
        .stat-icon.admins { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .stat-icon.rejected { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }

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

        /* Search and Filters - Exactly like students.php */
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

        /* Users Table Container */
        .users-container {
            background: transparent;
            backdrop-filter: none;
            border: none;
            border-radius: 0;
            overflow: visible;
        }

        .table-responsive-custom {
            max-height: 65vh;
            overflow-y: auto;
            /* Prevent horizontal scroll; allow content to wrap */
            overflow-x: hidden;
        }

        /* Sticky table header */
        .users-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            padding: 0;
            font-size: 0.9rem;
            background: transparent;
            box-shadow: none;
            border: none;
            border-radius: 0;
        }

        .users-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1) !important;
        }

        /* Dark mode header background alignment */
        [data-theme="dark"] .users-table thead th {
            background: rgba(30, 41, 59, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
        }

        .users-table {
            width: 100%;
            margin: 0;
            table-layout: fixed; /* Make columns share width and wrap content */
            border: none;
            border-radius: 0;
        }

        .users-table thead {
            background: rgba(255, 255, 255, 0.7);
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(8px);
        }

        .users-table th {
            padding: 0.5rem 0.6rem; /* compact header */
            font-weight: 600;
            color: var(--text-primary);
            border: none;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            border-right: none;
            font-size: 0.75rem; /* smaller header text */
            text-transform: uppercase;
            letter-spacing: 0.4px;
            line-height: 1.2;
            white-space: normal; /* allow header text to wrap */
            word-break: break-word;
        }

        .users-table td {
            padding: 0.6rem 0.6rem; /* compact cells */
            border: none;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            border-right: none;
            vertical-align: middle;
            white-space: normal; /* wrap long text like emails, departments */
            word-break: break-word;
        }

        /* Compact badges */
        .badge {
            padding: 0.2rem 0.5rem;
            border-radius: 16px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Column widths and alignment for better fit */
        .users-table th:nth-child(1),
        .users-table td:nth-child(1) { /* checkbox */
            width: 44px;
            text-align: center;
            white-space: nowrap;
        }

        .users-table th:nth-child(3),
        .users-table td:nth-child(3) { /* Role */
            width: 90px;
            text-align: center;
        }

        .users-table th:nth-child(4),
        .users-table td:nth-child(4) { /* Status */
            width: 100px;
            text-align: center;
        }

        .users-table th:nth-child(5),
        .users-table td:nth-child(5) { /* Department */
            width: 140px;
        }

        .users-table th:nth-child(6),
        .users-table td:nth-child(6) { /* Identifier */
            width: 120px;
        }

        .users-table th:nth-child(7),
        .users-table td:nth-child(7) { /* Contact */
            width: 150px;
        }

        .users-table th:nth-child(8),
        .users-table td:nth-child(8) { /* Registration */
            width: 120px;
            white-space: nowrap;
        }

        .users-table th:nth-child(9),
        .users-table td:nth-child(9) { /* Actions */
            width: 130px;
            text-align: center;
        }

        .users-table tbody tr {
            transition: all 0.3s ease;
        }

        .users-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.3);
            border-left: 3px solid rgba(59, 130, 246, 0.5);
        }

        /* Dark mode table styles - Enhanced and Fixed */
        [data-theme="dark"] .users-container {
            background: rgba(0, 0, 0, 0.3) !important;
            border: 1px solid var(--glass-border) !important;
        }

        [data-theme="dark"] .users-table {
            background-color: transparent !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .users-table thead {
            background: rgba(30, 41, 59, 0.9) !important;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        [data-theme="dark"] .users-table thead th {
            background-color: rgba(30, 41, 59, 0.9) !important;
            color: var(--text-primary) !important;
            border-bottom: 2px solid rgba(59, 130, 246, 0.4) !important;
            border-right: 1px solid rgba(59, 130, 246, 0.2) !important;
        }

        [data-theme="dark"] .users-table tbody tr {
            background-color: transparent !important;
            border-bottom: 2px solid rgba(59, 130, 246, 0.3) !important;
        }

        [data-theme="dark"] .users-table tbody tr:hover {
            background-color: rgba(30, 41, 59, 0.7) !important;
        }

        [data-theme="dark"] .users-table tbody tr:nth-child(even) {
            background-color: rgba(30, 41, 59, 0.3) !important;
        }

        [data-theme="dark"] .users-table tbody tr:nth-child(even):hover {
            background-color: rgba(30, 41, 59, 0.7) !important;
        }

        [data-theme="dark"] .users-table tbody td {
            color: var(--text-primary) !important;
            border-bottom: 2px solid rgba(59, 130, 246, 0.3) !important;
            border-right: 1px solid rgba(59, 130, 246, 0.1) !important;
            background-color: transparent !important;
        }

        [data-theme="dark"] .users-table tbody td small {
            color: var(--text-secondary) !important;
        }

        [data-theme="dark"] .user-meta {
            color: var(--text-secondary) !important;
        }

        [data-theme="dark"] .user-details h6 {
            color: var(--text-primary) !important;
        }

        /* CRITICAL: Dark mode dropdown styles */
        .filter-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.5);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        [data-theme="dark"] .filter-select {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .filter-select:focus {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
        }

        [data-theme="dark"] .filter-select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        /* Bulk actions dropdown dark mode */
        [data-theme="dark"] .bulk-actions select {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .bulk-actions select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        /* Search input dark mode */
        [data-theme="dark"] .search-input {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .search-input:focus {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
        }

        [data-theme="dark"] .search-input::placeholder {
            color: var(--text-tertiary);
        }

        /* Dark mode search filters container */
        [data-theme="dark"] .search-filters {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        /* Dark mode bulk actions */
        [data-theme="dark"] .bulk-actions {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-meta {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-role-admin {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .badge-role-faculty {
            background: rgba(6, 182, 212, 0.1);
            color: #0891b2;
        }

        .badge-role-student {
            background: rgba(236, 72, 153, 0.1);
            color: #db2777;
        }

        .badge-status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .badge-status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .badge-status-inactive {
            background: rgba(100, 116, 139, 0.1);
            color: #475569;
        }

        .badge-status-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
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


        /* Mobile Cards for responsive design */
        .user-card {
            display: none;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        /* Mobile blue border accent for cards (match departments mobile style) */
        @media (max-width: 768px) {
            .user-card {
                border-color: rgba(59, 130, 246, 0.6) !important; /* deeper blue */
                border-width: 2px !important; /* thicker border */
                /* left accent bar similar to departments */
                box-shadow: inset 4px 0 0 0 #3b82f6, 0 0 0 1px rgba(59, 130, 246, 0.15);
            }
        }

        .user-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .user-card-avatar {
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

        .user-card-info h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-card-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0.25rem 0;
        }

        .user-card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .user-card-detail {
            font-size: 0.875rem;
        }

        .user-card-detail strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .user-card-detail span {
            color: var(--text-secondary);
        }

        .user-card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Dark mode cards */
        [data-theme="dark"] .user-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        [data-theme="dark"] .user-card-info h6 {
            color: var(--text-primary);
        }

        [data-theme="dark"] .user-card-meta {
            color: var(--text-secondary);
        }

        [data-theme="dark"] .user-card-detail strong {
            color: var(--text-primary);
        }

        [data-theme="dark"] .user-card-detail span {
            color: var(--text-secondary);
        }

        /* Modal Dark Mode Styles */
        [data-theme="dark"] .modal-content {
            background: var(--bg-secondary) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .modal-header {
            border-bottom: 1px solid var(--border-color) !important;
            background: var(--bg-secondary) !important;
        }

        [data-theme="dark"] .modal-title {
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .modal-body {
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .btn-close {
            filter: invert(1);
        }

        /* Light mode modal - ensure white background */
        [data-theme="light"] .modal-content,
        .modal-content {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            color: #1e293b !important;
        }

        [data-theme="light"] .modal-header,
        .modal-header {
            border-bottom: 1px solid #e2e8f0 !important;
            background: #ffffff !important;
        }

        [data-theme="light"] .modal-title,
        .modal-title {
            color: #1e293b !important;
        }

        [data-theme="light"] .modal-body,
        .modal-body {
            background: #ffffff !important;
            color: #1e293b !important;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-filters {
                padding: 1rem;
            }

        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            /* Hide table and show cards on mobile */
            .users-container {
                display: none;
            }

            .user-card {
                display: block;
                /* Add left and right margins for mobile spacing */
                margin-left: 1rem;
                margin-right: 1rem;
            }

            .user-card-details {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 480px) {
        }

        /* Responsive Table */
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive-custom::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive-custom::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .table-responsive-custom::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
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
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Row highlighting for post-action feedback */
        .row-highlight {
            background: rgba(102, 126, 234, 0.2) !important;
            animation: highlightPulse 2.6s ease-out;
        }

        @keyframes highlightPulse {
            0% { background: rgba(102, 126, 234, 0.4) !important; }
            50% { background: rgba(102, 126, 234, 0.2) !important; }
            100% { background: rgba(255, 255, 255, 0.1) !important; }
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

        /* Loading states */
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
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../../includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <?php 
    // Flash success at top
    $flashMessage = null;
    if (isset($_SESSION['flash_message'])) {
        $flashMessage = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
    }
    // Determine if filters are active (used to suppress flash in header during filtering)
    $hasActiveFilters = !empty($selectedRole) || !empty($selectedStatus) || !empty($selectedDepartment);
    ?>

    

    <!-- Main Content -->
    <main class="main-content">
    <?php if ($flashMessage && !$hasActiveFilters && $flashMessage['type'] === 'success'): ?>
        <div class="alert alert-success glass-card mt-3" id="topSuccessAlert" role="alert">
            <strong>✅ Success!</strong> <?= htmlspecialchars($flashMessage['message']) ?>
        </div>
    <?php endif; ?>
    <?php if ($flashMessage && !$hasActiveFilters && $flashMessage['type'] === 'error'): ?>
        <div class="alert alert-danger glass-card mt-3" id="topErrorAlert" role="alert">
            <strong>❌ Error!</strong> <?= htmlspecialchars($flashMessage['message']) ?>
        </div>
    <?php endif; ?>
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-card glass-card fade-in">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">👥 User Management</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="create.php" class="btn-action btn-primary">
                            ➕ Create User
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon total">👥</div>
                <div class="stat-number"><?= $userStats['total_users'] ?? 0 ?></div>
                <div class="stat-label">Total Users</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon pending">⏳</div>
                <div class="stat-number"><?= $userStats['pending_approvals'] ?? 0 ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon active">✅</div>
                <div class="stat-number"><?= $userStats['active_users'] ?? 0 ?></div>
                <div class="stat-label">Active Users</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon recent">📈</div>
                <div class="stat-number"><?= $userStats['recent_registrations'] ?? 0 ?></div>
                <div class="stat-label">Recent (7 days)</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon faculty">🎓</div>
                <div class="stat-number"><?= $userStats['faculty_count'] ?? 0 ?></div>
                <div class="stat-label">Faculty Members</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon students">📚</div>
                <div class="stat-number"><?= $userStats['student_count'] ?? 0 ?></div>
                <div class="stat-label">Students</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon admins">👑</div>
                <div class="stat-number"><?= $userStats['admin_count'] ?? 0 ?></div>
                <div class="stat-label">Administrators</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon rejected">❌</div>
                <div class="stat-number"><?= $userStats['rejected_users'] ?? 0 ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters glass-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search users..." id="searchInput">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19S2 15.194 2 10.5S5.806 2 10.5 2S19 5.806 19 10.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                
                <div class="filter-controls">
                    <div class="filter-group">
                        <label class="filter-label">Role</label>
                        <select class="filter-select" id="roleFilter" onchange="handleRoleFilter()">
                            <option value="">All Roles</option>
                            <option value="admin" <?= $selectedRole == 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="faculty" <?= $selectedRole == 'faculty' ? 'selected' : '' ?>>Faculty</option>
                            <option value="student" <?= $selectedRole == 'student' ? 'selected' : '' ?>>Student</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="filter-select" id="statusFilter" onchange="handleStatusFilter()">
                            <option value="">All Status</option>
                            <option value="active" <?= $selectedStatus == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="pending" <?= $selectedStatus == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="inactive" <?= $selectedStatus == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="rejected" <?= $selectedStatus == 'rejected' ? 'selected' : '' ?>>Rejected</option>
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
                    
                    <button class="btn-action btn-outline" onclick="clearFilters()">
                        🔄 Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions (shown when users are selected) -->
        <form method="post" id="bulkActionForm">
            <div class="bulk-actions" id="bulkActions">
                <div class="d-flex align-items-center gap-3">
                    <span><strong id="selectedCount">0</strong> users selected</span>
                    
                    <select name="bulk_action_type" class="filter-select" required>
                        <option value="">Choose Action</option>
                        <option value="approve">Approve Selected</option>
                        <option value="reject">Reject Selected</option>
                        <option value="activate">Activate Selected</option>
                        <option value="deactivate">Deactivate Selected</option>
                    </select>
                    
                    <button type="submit" name="action" value="bulk_action" class="btn-action btn-primary">
                        Apply Action
                    </button>
                    
                    <button type="button" class="btn-action btn-outline" onclick="clearSelection()">
                        Clear Selection
                    </button>
                </div>
            </div>

            <!-- Users Table (Desktop) -->
            <?php if (!empty($users)): ?>
                <div class="users-container glass-card">
                    <div class="table-responsive-custom">
                        <table class="users-table table table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Department</th>
                                    <th>Identifier</th>
                                    <th>Contact</th>
                                    <th>Registration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <?php foreach ($users as $user): ?>
                                    <tr class="user-row" id="user-<?= $user['user_id'] ?>"
                                        data-user-id="<?= $user['user_id'] ?>"
                                        data-name="<?= strtolower($user['full_name'] ?? $user['username']) ?>"
                                        data-email="<?= strtolower($user['email']) ?>"
                                        data-username="<?= strtolower($user['username']) ?>"
                                        data-role="<?= $user['role'] ?>"
                                        data-status="<?= $user['status'] ?>"
                                        data-department="<?= $user['department'] ?? '' ?>">
                                        <td>
                                            <input type="checkbox" name="selected_users[]" value="<?= $user['user_id'] ?>" 
                                                   class="user-checkbox" onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <div class="user-info">
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
                                                <div class="user-details">
                                                    <h6><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></h6>
                                                    <p class="user-meta"><?= htmlspecialchars($user['email']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-role-<?= $user['role'] ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?= $user['status'] ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($user['department'] ?? 'Not specified') ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($user['identifier'])): ?>
                                                <strong><?= htmlspecialchars($user['identifier']) ?></strong>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($user['phone'])): ?>
                                                <a href="tel:<?= htmlspecialchars($user['phone']) ?>" class="text-decoration-none" style="color: var(--primary-color);">
                                                    📞 <?= htmlspecialchars($user['phone']) ?>
                                                </a><br>
                                            <?php endif; ?>
                                            <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="text-decoration-none" style="color: var(--primary-color); font-size: 0.8125rem;">
                                                ✉️ Email
                                            </a>
                                        </td>
                                        <td>
                                            <small style="color: var(--text-secondary);">
                                                <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                            </small>
                                            <?php if (!empty($user['last_login'])): ?>
                                                <br><small style="color: var(--text-tertiary);">
                                                    Last: <?= timeAgo($user['last_login']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <?php if ($user['status'] === 'pending'): ?>
                                                    <a href="approve-reject.php?action=approve&id=<?= $user['user_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                       class="btn-action btn-success btn-sm" 
                                                       onclick="return confirm('Approve this user registration?')" title="Approve">
                                                        ✅
                                                    </a>
                                                    <a href="approve-reject.php?action=reject&id=<?= $user['user_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                       class="btn-action btn-danger btn-sm" 
                                                       onclick="return confirm('Reject this user registration?')" title="Reject">
                                                        ❌
                                                    </a>
                                                <?php elseif ($user['status'] === 'active'): ?>
                                                    <a href="activate-deactivate.php?action=deactivate&id=<?= $user['user_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                       class="btn-action btn-outline btn-sm" 
                                                       onclick="return confirm('Deactivate this user?')" title="Deactivate">
                                                        ⏸️
                                                    </a>
                                                <?php elseif ($user['status'] === 'inactive'): ?>
                                                    <a href="activate-deactivate.php?action=activate&id=<?= $user['user_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                       class="btn-action btn-success btn-sm" 
                                                       onclick="return confirm('Activate this user?')" title="Activate">
                                                        ▶️
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <button class="btn-action btn-primary btn-sm" 
                                                        type="button" onclick="viewUserDetails(<?= $user['user_id'] ?>)" title="View Details">
                                                    👁️
                                                </button>
                                                
                                                <a href="edit.php?id=<?= $user['user_id'] ?>" 
                                                   class="btn-action btn-outline btn-sm" title="Edit User">
                                                    ✏️
                                                </a>
                                                
                                                <?php if ($user['role'] !== 'admin' || $user['user_id'] != $userId): ?>
                                                    <a href="delete.php?id=<?= $user['user_id'] ?>" 
                                                       class="btn-action btn-danger btn-sm" 
                                                       title="Delete User">
                                                        🗑️
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
        </form>

        <!-- Users Cards (Mobile) -->
        <div id="usersCards">
            <?php foreach ($users as $user): ?>
                <div class="user-card" id="user-card-<?= $user['user_id'] ?>"
                     data-name="<?= strtolower($user['full_name'] ?? $user['username']) ?>"
                     data-email="<?= strtolower($user['email']) ?>"
                     data-username="<?= strtolower($user['username']) ?>"
                     data-role="<?= $user['role'] ?>"
                     data-status="<?= $user['status'] ?>"
                     data-department="<?= $user['department'] ?? '' ?>">
                    
                    <div class="user-card-header">
                        <div class="user-card-avatar">
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
                        <div class="user-card-info">
                            <h6><?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></h6>
                            <div class="user-card-meta"><?= htmlspecialchars($user['email']) ?></div>
                            <div class="user-card-meta">
                                <span class="badge badge-role-<?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
                                <span class="badge badge-status-<?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="user-card-details">
                        <div class="user-card-detail">
                            <strong>Department:</strong><br>
                            <span><?= htmlspecialchars($user['department'] ?? 'Not specified') ?></span>
                        </div>
                        <div class="user-card-detail">
                            <strong>Identifier:</strong><br>
                            <span><?= htmlspecialchars($user['identifier'] ?? 'Not set') ?></span>
                        </div>
                        <?php if (!empty($user['phone'])): ?>
                            <div class="user-card-detail">
                                <strong>Phone:</strong><br>
                                <span><?= htmlspecialchars($user['phone']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="user-card-detail">
                            <strong>Registered:</strong><br>
                            <span><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($user['last_login'])): ?>
                            <div class="user-card-detail">
                                <strong>Last Login:</strong><br>
                                <span><?= timeAgo($user['last_login']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($user['designation'])): ?>
                            <div class="user-card-detail">
                                <strong>Designation:</strong><br>
                                <span><?= htmlspecialchars($user['designation']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($user['academic_info'])): ?>
                            <div class="user-card-detail">
                                <strong>Academic Level:</strong><br>
                                <span><?= htmlspecialchars($user['academic_info']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="user-card-actions">
                        <?php if ($user['status'] === 'pending'): ?>
                            <a href="approve-reject.php?action=approve&id=<?= $user['user_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn-action btn-success" 
                               onclick="return confirm('Approve this user registration?')">
                                ✅ Approve
                            </a>
                            <a href="approve-reject.php?action=reject&id=<?= $user['user_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn-action btn-danger" 
                               onclick="return confirm('Reject this user registration?')">
                                ❌ Reject
                            </a>
                        <?php elseif ($user['status'] === 'active'): ?>
                            <a href="activate-deactivate.php?action=deactivate&id=<?= $user['user_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn-action btn-outline" 
                               onclick="return confirm('Deactivate this user?')">
                                ⏸️ Deactivate
                            </a>
                        <?php elseif ($user['status'] === 'inactive'): ?>
                            <a href="activate-deactivate.php?action=activate&id=<?= $user['user_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn-action btn-success" 
                               onclick="return confirm('Activate this user?')">
                                ▶️ Activate
                            </a>
                        <?php endif; ?>
                        
                        <button class="btn-action btn-primary" onclick="viewUserDetails(<?= $user['user_id'] ?>)">
                            👁️ View Details
                        </button>
                        
                        <a href="edit.php?id=<?= $user['user_id'] ?>" class="btn-action btn-outline">
                            ✏️ Edit User
                        </a>
                        
                        <?php if ($user['role'] !== 'admin' || $user['user_id'] != $userId): ?>
                            <a href="delete.php?id=<?= $user['user_id'] ?>" 
                               class="btn-action btn-danger">
                                🗑️ Delete User
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="btn-action btn-outline">
                                ✉️ Send Email
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['phone'])): ?>
                            <a href="tel:<?= htmlspecialchars($user['phone']) ?>" class="btn-action btn-outline">
                                📞 Call
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
            <div class="empty-state glass-card">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2"/>
                    <path d="M9 11C11.2091 11 13 9.20914 13 7C13 4.79086 11.2091 3 9 3C6.79086 3 5 4.79086 5 7C5 9.20914 6.79086 11 9 11Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M23 21V19C22.9993 18.1137 22.7044 17.2528 22.1614 16.5523C21.6184 15.8519 20.8581 15.3516 20 15.13" stroke="currentColor" stroke-width="2"/>
                    <path d="M16 3.13C16.8604 3.35031 17.623 3.85071 18.1676 4.55232C18.7122 5.25392 19.0078 6.11683 19.0078 7.005C19.0078 7.89317 18.7122 8.75608 18.1676 9.45768C17.623 10.1593 16.8604 10.6597 16 10.88" stroke="currentColor" stroke-width="2"/>
                </svg>
                <h3>No Users Found</h3>
                <p>
                    <?php if (!empty($selectedRole) || !empty($selectedStatus) || !empty($selectedDepartment)): ?>
                        No users match your current filter criteria. Try adjusting your filters or clear them to see all users.
                    <?php else: ?>
                        No users are currently registered in the system. Users will appear here as they register and are approved.
                    <?php endif; ?>
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="create.php" class="btn-action btn-primary">
                        ➕ Create First User
                    </a>
                    <?php if (!empty($selectedRole) || !empty($selectedStatus) || !empty($selectedDepartment)): ?>
                        <button onclick="clearFilters()" class="btn-action btn-outline">
                            🔄 Clear Filters
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="alert alert-danger glass-card mt-3" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        
    </main>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="userDetailsModalLabel">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- Content will be loaded via JavaScript -->
                    <div class="text-center">
                        <div class="loading-shimmer" style="height: 200px; border-radius: 8px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize search and filters
            initializeSearchAndFilters();
            
            
            // Apply current theme
            applyCurrentTheme();
            
            // Add animation delays
            const animatedElements = document.querySelectorAll('.slide-up');
            animatedElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            // Sidebar handlers removed to prevent unintended expansion

            // Initialize bulk actions
            updateBulkActions();

            // Handle post-action feedback
            handlePostActionFeedback();

            // Auto-hide alerts
            autoHideAlerts();

            // Hide flash alerts when filters are active on initial load
            hideAlertsWhenFiltering();
        });

        // Handle post-action feedback (row highlighting and scrolling)
        function handlePostActionFeedback() {
            const urlParams = new URLSearchParams(window.location.search);
            const targetId = urlParams.get('created_id') || urlParams.get('updated_id');
            
            if (targetId) {
                setTimeout(() => {
                    const targetRow = document.getElementById(`user-${targetId}`);
                    const targetCard = document.getElementById(`user-card-${targetId}`);
                    
                    if (targetRow) {
                        // Scroll to the row
                        targetRow.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        
                        // Add highlight class
                        targetRow.classList.add('row-highlight');
                        
                        // Remove highlight after animation
                        setTimeout(() => {
                            targetRow.classList.remove('row-highlight');
                        }, 2600);
                    } else if (targetCard) {
                        // Mobile card version
                        targetCard.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        
                        targetCard.classList.add('row-highlight');
                        setTimeout(() => {
                            targetCard.classList.remove('row-highlight');
                        }, 2600);
                    }
                }, 100);
            }
        }

        // Auto-hide success alerts
        function autoHideAlerts() {
            const successAlerts = document.querySelectorAll('.alert-success');
            successAlerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'all 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        }


        // Sidebar JS handlers removed to prevent unintended expansion on single link clicks.

        function initializeSearchAndFilters() {
            const searchInput = document.getElementById('searchInput');
            const roleFilter = document.getElementById('roleFilter');
            const statusFilter = document.getElementById('statusFilter');
            const departmentFilter = document.getElementById('departmentFilter');

            let hasScrolledToResults = false;

            function scrollToResultsOnce() {
                if (hasScrolledToResults) return;
                const container = document.querySelector('.users-container') || document.getElementById('usersCards');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    hasScrolledToResults = true;
                }
            }

            function filterUsers() {
                const searchTerm = (searchInput?.value || '').toLowerCase();
                const roleVal = roleFilter?.value || '';
                const statusVal = statusFilter?.value || '';
                const deptVal = departmentFilter?.value || '';

                const tableRows = document.querySelectorAll('.user-row');
                const userCards = document.querySelectorAll('.user-card');

                let visibleCount = 0;

                function applyFilter(elements) {
                    elements.forEach(element => {
                        const name = element.dataset.name || '';
                        const email = element.dataset.email || '';
                        const username = element.dataset.username || '';
                        const role = element.dataset.role || '';
                        const status = element.dataset.status || '';
                        const department = element.dataset.department || '';

                        const matchesSearch = !searchTerm ||
                            name.includes(searchTerm) ||
                            email.includes(searchTerm) ||
                            username.includes(searchTerm);

                        const matchesRole = !roleVal || role === roleVal;
                        const matchesStatus = !statusVal || status === statusVal;
                        const matchesDept = !deptVal || department === deptVal;

                        const shouldShow = matchesSearch && matchesRole && matchesStatus && matchesDept;

                        if (shouldShow) {
                            element.style.display = '';
                            visibleCount++;
                        } else {
                            element.style.display = 'none';
                        }
                    });
                }

                applyFilter(tableRows);
                applyFilter(userCards);

                if (visibleCount === 0 && (tableRows.length > 0 || userCards.length > 0)) {
                    showNoResultsMessage();
                } else {
                    hideNoResultsMessage();
                }

                // Hide top alerts when actively filtering/searching
                const isActivelyFiltering = (searchTerm.length > 0) || roleVal || statusVal || deptVal;
                const topAlert = document.getElementById('topSuccessAlert');
                const topError = document.getElementById('topErrorAlert');
                if (isActivelyFiltering) {
                    if (topAlert) topAlert.style.display = 'none';
                    if (topError) topError.style.display = 'none';
                } else {
                    if (topAlert) topAlert.style.display = '';
                    if (topError) topError.style.display = '';
                }
            }

            // Event listeners - all client-side, no page reloads
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    filterUsers();
                    scrollToResultsOnce();
                });
            }
            if (roleFilter) {
                roleFilter.addEventListener('change', () => {
                    filterUsers();
                    scrollToResultsOnce();
                });
            }
            if (statusFilter) {
                statusFilter.addEventListener('change', () => {
                    filterUsers();
                    scrollToResultsOnce();
                });
            }
            if (departmentFilter) {
                departmentFilter.addEventListener('change', () => {
                    filterUsers();
                    scrollToResultsOnce();
                });
            }

            // Initial run in case filters are preselected from server side
            filterUsers();
        }

        // Hide alerts if URL has filters (role/status/department)
        function hideAlertsWhenFiltering() {
            const url = new URL(window.location);
            const hasFilters = url.searchParams.has('role') || url.searchParams.has('status') || url.searchParams.has('department');
            if (hasFilters) {
                const topAlert = document.getElementById('topSuccessAlert');
                const topError = document.getElementById('topErrorAlert');
                if (topAlert) topAlert.style.display = 'none';
                if (topError) topError.style.display = 'none';
            }
        }

        function showNoResultsMessage() {
            hideNoResultsMessage(); // Remove existing message first
            
            const noResultsDiv = document.createElement('div');
            noResultsDiv.id = 'noResultsMessage';
            noResultsDiv.className = 'empty-state glass-card';
            noResultsDiv.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19S2 15.194 2 10.5S5.806 2 10.5 2S19 5.806 19 10.5Z" stroke="currentColor" stroke-width="2"/>
                </svg>
                <h3>No Users Found</h3>
                <p>No users match your current search criteria. Try adjusting your search terms.</p>
            `;
            
            document.querySelector('.main-content').appendChild(noResultsDiv);
        }

        function hideNoResultsMessage() {
            const existingMessage = document.getElementById('noResultsMessage');
            if (existingMessage) {
                existingMessage.remove();
            }
        }

        function clearFilters() {
            const searchInput = document.getElementById('searchInput');
            const roleFilter = document.getElementById('roleFilter');
            const statusFilter = document.getElementById('statusFilter');
            const departmentFilter = document.getElementById('departmentFilter');
            if (searchInput) searchInput.value = '';
            if (roleFilter) roleFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            if (departmentFilter) departmentFilter.value = '';
            // Re-run client-side filter
            if (typeof initializeSearchAndFilters === 'function') {
                // If already initialized, just trigger filter pass
                const event = new Event('input');
                if (searchInput) searchInput.dispatchEvent(event);
            }
        }

        // Deprecated: URL-based filtering replaced by client-side filtering
        function handleRoleFilter() { /* no-op retained for compatibility */ }
        function handleStatusFilter() { /* no-op retained for compatibility */ }
        function handleDepartmentFilter() { /* no-op retained for compatibility */ }

        // Bulk Actions Management
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const selectedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');
            const actionSelect = document.querySelector('select[name="bulk_action_type"]');
            
            if (selectedCount) {
                selectedCount.textContent = selectedCheckboxes.length;
            }
            
            if (bulkActions) {
                if (selectedCheckboxes.length > 0) {
                    bulkActions.classList.add('show');
                    // Add required attribute when visible
                    if (actionSelect) actionSelect.setAttribute('required', 'required');
                } else {
                    bulkActions.classList.remove('show');
                    // Remove required attribute when hidden to prevent validation errors
                    if (actionSelect) {
                        actionSelect.removeAttribute('required');
                        actionSelect.value = ''; // Reset selection
                    }
                }
            }
            
            // Update select all checkbox state
            if (selectAll) {
                const allCheckboxes = document.querySelectorAll('.user-checkbox');
                selectAll.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < allCheckboxes.length;
                selectAll.checked = selectedCheckboxes.length === allCheckboxes.length && allCheckboxes.length > 0;
            }
        }

        function clearSelection() {
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            const selectAll = document.getElementById('selectAll');
            const actionSelect = document.querySelector('select[name="bulk_action_type"]');
            
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            
            // Reset the action select dropdown
            if (actionSelect) {
                actionSelect.value = '';
                actionSelect.removeAttribute('required');
            }
            
            updateBulkActions();
        }

        async function viewUserDetails(userId) {
            const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
            const modalContent = document.getElementById('userDetailsContent');
            
            // Show loading state
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="loading-shimmer" style="height: 200px; border-radius: 8px; margin-bottom: 1rem;"></div>
                    <p>Loading user details...</p>
                </div>
            `;
            
            modal.show();

            try {
                const response = await fetch(`../../includes/api/user-details.php?id=${userId}`);
                let data = null;
                try {
                    data = await response.json();
                } catch (e) {
                    // Non-JSON response
                    data = null;
                }

                if (!response.ok) {
                    const backendMsg = data && (data.message || data.error)
                        ? `${data.message || ''}${data.error ? ' - ' + data.error : ''}`.trim()
                        : `HTTP error ${response.status}`;
                    throw new Error(backendMsg);
                }
                
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
                        <strong>Error:</strong> Failed to load user details. Please try again.
                        <br><small>Details: ${error.message}</small>
                    </div>
                `;
            }
        }

        function generateUserDetailsHTML(user) {
            const roleIcon = {
                'admin': '👑',
                'faculty': '🎓',
                'student': '📚'
            };

            const statusColor = {
                'active': '#059669',
                'pending': '#d97706',
                'inactive': '#475569',
                'rejected': '#dc2626'
            };

            // Generate initials for avatar
            let initials = '';
            if (user.full_name && user.full_name !== user.username) {
                const nameParts = user.full_name.split(' ');
                initials = nameParts.slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase();
            } else {
                initials = user.username.substring(0, 2).toUpperCase();
            }

            return `
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        <div class="user-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                            ${initials}
                        </div>
                        <h4>${user.full_name || user.username}</h4>
                        <p class="text-muted">${user.identifier || 'No ID set'}</p>
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <span class="badge" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                                ${roleIcon[user.role] || '👤'} ${user.role.charAt(0).toUpperCase() + user.role.slice(1)}
                            </span>
                            <span class="badge" style="background: ${statusColor[user.status]}20; color: ${statusColor[user.status]};">
                                ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <strong>Email:</strong><br>
                                <a href="mailto:${user.email}" class="text-decoration-none" style="color: var(--primary-color);">
                                    ${user.email}
                                </a>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <strong>Username:</strong><br>
                                ${user.username}
                            </div>
                            ${user.department ? `
                            <div class="col-sm-6 mb-3">
                                <strong>Department:</strong><br>
                                ${user.department}
                            </div>
                            ` : ''}
                            ${user.phone ? `
                            <div class="col-sm-6 mb-3">
                                <strong>Phone:</strong><br>
                                <a href="tel:${user.phone}" class="text-decoration-none" style="color: var(--primary-color);">
                                    ${user.phone}
                                </a>
                            </div>
                            ` : ''}
                            ${user.designation ? `
                            <div class="col-sm-6 mb-3">
                                <strong>Designation:</strong><br>
                                ${user.designation}
                            </div>
                            ` : ''}
                            ${user.academic_info ? `
                            <div class="col-sm-6 mb-3">
                                <strong>Academic Level:</strong><br>
                                ${user.academic_info}
                            </div>
                            ` : ''}
                            ${user.qualification ? `
                            <div class="col-sm-6 mb-3">
                                <strong>Qualification:</strong><br>
                                ${user.qualification}
                            </div>
                            ` : ''}
                            ${user.specialization ? `
                            <div class="col-sm-6 mb-3">
                                <strong>Specialization:</strong><br>
                                ${user.specialization}
                            </div>
                            ` : ''}
                            ${user.office_location ? `
                            <div class="col-sm-6 mb-3">
                                <strong>Office Location:</strong><br>
                                ${user.office_location}
                            </div>
                            ` : ''}
                            ${user.experience_years ? `
                            <div class="col-sm-6 mb-3">
                                <strong>Experience:</strong><br>
                                ${user.experience_years} years
                            </div>
                            ` : ''}
                            <div class="col-sm-6 mb-3">
                                <strong>Registration Date:</strong><br>
                                ${new Date(user.created_at).toLocaleDateString()}
                            </div>
                            <div class="col-sm-6 mb-3">
                                <strong>Last Login:</strong><br>
                                ${user.last_login ? timeAgoJS(user.last_login) : 'Never'}
                            </div>
                            <div class="col-sm-6 mb-3">
                                <strong>Email Verified:</strong><br>
                                <span style="color: ${user.email_verified ? '#059669' : '#d97706'};">
                                    ${user.email_verified ? '✅ Verified' : '⏳ Pending'}
                                </span>
                            </div>
                            ${user.approved_at ? `
                            <div class="col-sm-6 mb-3">
                                <strong>Approved Date:</strong><br>
                                ${new Date(user.approved_at).toLocaleDateString()}
                            </div>
                            ` : ''}
                        </div>

                        ${user.assigned_subjects > 0 ? `
                        <div class="mt-3">
                            <strong>Faculty Statistics:</strong><br>
                            <div class="d-flex gap-3 mt-2">
                                <span class="badge" style="background: rgba(6, 182, 212, 0.1); color: #0891b2;">
                                    📚 ${user.assigned_subjects} Subjects
                                </span>
                                ${user.active_schedules > 0 ? `
                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
                                    📅 ${user.active_schedules} Classes
                                </span>
                                ` : ''}
                                ${user.total_students > 0 ? `
                                <span class="badge" style="background: rgba(236, 72, 153, 0.1); color: #db2777;">
                                    👥 ${user.total_students} Students
                                </span>
                                ` : ''}
                            </div>
                        </div>
                        ` : ''}

                        ${user.enrolled_subjects > 0 ? `
                        <div class="mt-3">
                            <strong>Student Statistics:</strong><br>
                            <div class="d-flex gap-3 mt-2">
                                <span class="badge" style="background: rgba(236, 72, 153, 0.1); color: #db2777;">
                                    📚 ${user.enrolled_subjects} Subjects
                                </span>
                                ${user.scheduled_classes > 0 ? `
                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
                                    📅 ${user.scheduled_classes} Classes
                                </span>
                                ` : ''}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="edit.php?id=${user.user_id}" class="btn-action btn-primary me-2">
                        ✏️ Edit User
                    </a>
                    <a href="mailto:${user.email}" class="btn-action btn-outline me-2">
                        ✉️ Send Email
                    </a>
                    ${user.phone ? `
                    <a href="tel:${user.phone}" class="btn-action btn-outline me-2">
                        📞 Call User
                    </a>
                    ` : ''}
                    ${(user.role !== 'admin') ? `
                    <a href="delete.php?id=${user.user_id}" class="btn-action btn-danger">
                        🗑️ Delete User
                    </a>
                    ` : ''}
                </div>
            `;
        }

        function timeAgoJS(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const seconds = Math.floor((now - date) / 1000);

            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
            if (seconds < 2592000) return Math.floor(seconds / 86400) + ' days ago';
            
            return date.toLocaleDateString();
        }

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
            const table = document.querySelector('.users-table');
            const container = document.querySelector('.users-container');
            
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
                            const textElements = cell.querySelectorAll('h6, p, span:not(.badge), strong, small');
                            textElements.forEach(element => {
                                if (!element.classList.contains('badge')) {
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
            const table = document.querySelector('.users-table');
            const container = document.querySelector('.users-container');
            
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
                    element.style.removeProperty('background');
                    element.style.removeProperty('border-bottom');
                });
            }
        }

        // Listen for theme changes
        window.addEventListener('themeChanged', function(event) {
            applyCurrentTheme();
        });

        // Also listen for storage changes (when theme is changed in another tab)
        window.addEventListener('storage', function(event) {
            if (event.key === 'theme') {
                applyCurrentTheme();
            }
        });

        // Force theme application on page load and after DOM changes
        document.addEventListener('DOMContentLoaded', function() {
            // Small delay to ensure all elements are rendered
            setTimeout(() => {
                applyCurrentTheme();
            }, 100);
        });

        // Handle bulk action form submission
        document.getElementById('bulkActionForm').addEventListener('submit', function(e) {
            // Only run validations for the Bulk Action submit button
            const submitter = e.submitter;
            if (!submitter || submitter.name !== 'action' || submitter.value !== 'bulk_action') {
                return; // Ignore submissions not triggered by the bulk action button
            }

            const selectedUsers = document.querySelectorAll('.user-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const actionSelect = this.querySelector('select[name="bulk_action_type"]');
            const actionType = actionSelect.value;
            
            // Check if bulk actions are visible (if hidden, the form shouldn't submit)
            if (!bulkActions.classList.contains('show')) {
                e.preventDefault();
                alert('Please select users first.');
                return;
            }
            
            if (selectedUsers.length === 0) {
                e.preventDefault();
                alert('Please select at least one user to perform this action.');
                return;
            }
            
            if (!actionType) {
                e.preventDefault();
                alert('Please select an action to perform.');
                // Focus the select element
                actionSelect.focus();
                return;
            }
            
            const confirmMessage = `Are you sure you want to ${actionType} ${selectedUsers.length} selected user(s)?`;
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });

        // Auto-hide top success alert after 5 seconds
        (function() {
            const alertEl = document.getElementById('topSuccessAlert');
            if (alertEl) {
                setTimeout(() => {
                    alertEl.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alertEl.style.opacity = '0';
                    alertEl.style.transform = 'translateY(-10px)';
                    setTimeout(() => alertEl.remove(), 600);
                }, 5000);
            }
        })();

        // Scroll to show success message first, then highlight updated user
        (function() {
            const params = new URLSearchParams(window.location.search);
            const targetId = params.get('created_id') || params.get('updated_id') || params.get('deleted_id');
            
            // If there's a flash message, scroll to top first to show it
            const alertEl = document.getElementById('topSuccessAlert');
            if (alertEl) {
                // Scroll to top to show success message
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                // If there's a target user, scroll to it after showing the message
                if (targetId) {
                    setTimeout(() => {
                        const row = document.getElementById('user-' + targetId);
                        if (row) {
                            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            row.classList.add('row-highlight');
                            setTimeout(() => row.classList.remove('row-highlight'), 2600);
                        }
                    }, 1500); // Wait 1.5 seconds to show message first
                }
            } else if (targetId) {
                // No flash message, just scroll to the target row
                const row = document.getElementById('user-' + targetId);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('row-highlight');
                    setTimeout(() => row.classList.remove('row-highlight'), 2600);
                } else if (params.get('deleted_id')) {
                    // If deleted row no longer exists, scroll to below the sticky header
                    const sticky = document.getElementById('stickyHeader');
                    if (sticky) {
                        sticky.scrollIntoView({ behavior: 'smooth', block: 'end' });
                    }
                }
            }
        })();
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