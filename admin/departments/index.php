<?php
/**
 * Department Management Index Page
 * Timetable Management System
 * 
 * Professional department management interface with modern glassmorphism design
 * Allows admins to manage departments, view statistics, and perform bulk operations
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Department.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$departmentManager = new Department();

// Initialize variables
$departments = [];
$departmentStats = [];
$error_message = '';
$success_message = '';
$selectedStatus = $_GET['status'] ?? '';
$selectedSort = $_GET['sort'] ?? 'name';
$selectedOrder = $_GET['order'] ?? 'asc';
$searchTerm = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;

// Handle bulk actions
if (($_POST['action'] ?? '') === 'bulk_action' && !empty($_POST['selected_departments'])) {
    $selectedDepartments = $_POST['selected_departments'];
    $bulkAction = $_POST['bulk_action_type'] ?? '';

    try {
        $ok = 0; $fail = 0; $failMsgs = [];
        if ($bulkAction === 'activate' || $bulkAction === 'deactivate') {
            $newStatus = $bulkAction === 'activate' ? 1 : 0;
            foreach ($selectedDepartments as $departmentId) {
                $result = $departmentManager->updateDepartment($departmentId, ['is_active' => $newStatus], $userId);
                if (!empty($result['success'])) { $ok++; } else { $fail++; $failMsgs[] = $result['message'] ?? 'Unknown error'; }
            }
            if ($fail === 0) {
                $success_message = $bulkAction === 'activate' ? "Selected departments have been activated successfully." : "Selected departments have been deactivated successfully.";
            } else if ($ok > 0) {
                $error_message = "Some updates failed ($fail). " . implode(' | ', array_slice($failMsgs, 0, 3));
            } else {
                $error_message = "Bulk action failed for all selected departments. " . implode(' | ', array_slice($failMsgs, 0, 3));
            }
        } elseif ($bulkAction === 'delete') {
            foreach ($selectedDepartments as $departmentId) {
                $result = $departmentManager->deleteDepartment($departmentId, $userId);
                if (!empty($result['success'])) { $ok++; } else { $fail++; $failMsgs[] = $result['message'] ?? 'Unknown error'; }
            }
            if ($fail === 0) {
                $success_message = "Selected departments have been deleted successfully.";
            } else if ($ok > 0) {
                $error_message = "Some deletions failed ($fail). " . implode(' | ', array_slice($failMsgs, 0, 3));
            } else {
                $error_message = "Bulk deletion failed for all selected departments. " . implode(' | ', array_slice($failMsgs, 0, 3));
            }
        }
        // PRG: set flash and redirect to avoid form resubmission
        $qs = [];
        if ($selectedStatus !== '') $qs[] = 'status=' . urlencode($selectedStatus);
        if ($selectedSort !== '')   $qs[] = 'sort=' . urlencode($selectedSort);
        if ($selectedOrder !== '')  $qs[] = 'order=' . urlencode($selectedOrder);
        if ($searchTerm !== '')     $qs[] = 'search=' . urlencode($searchTerm);
        if (!empty($page))          $qs[] = 'page=' . (int)$page;
        if (!empty($success_message)) {
            flash_set('success', $success_message);
        } elseif (!empty($error_message)) {
            flash_set('error', $error_message);
        }
        $redirectUrl = 'index.php' . (count($qs) ? ('?' . implode('&', $qs)) : '');
        header('Location: ' . $redirectUrl);
        exit();
    } catch (Exception $e) {
        flash_set('error', 'Bulk action failed: ' . $e->getMessage());
        header('Location: index.php');
        exit();
    }
}

// Handle individual quick actions
if (($_POST['action'] ?? '') === 'quick_action') {
    $departmentId = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    $quickAction = $_POST['quick_action_type'] ?? '';

    try {
        if ($quickAction === 'toggle_status') {
            $department = $departmentManager->getDepartmentById($departmentId);
            if ($department) {
                $newStatus = $department['is_active'] ? 0 : 1;
                $result = $departmentManager->updateDepartment($departmentId, ['is_active' => $newStatus], $userId);
                if (!empty($result['success'])) {
                    $success_message = $result['message'] ?? 'Department status updated successfully.';
                } else {
                    $error_message = $result['message'] ?? 'Failed to update department status.';
                }
            } else {
                $error_message = 'Department not found.';
            }
        } elseif ($quickAction === 'delete') {
            $result = $departmentManager->deleteDepartment($departmentId, $userId);
            if (!empty($result['success'])) {
                $success_message = $result['message'] ?? 'Department deleted successfully.';
            } else {
                $error_message = $result['message'] ?? 'Failed to delete department.';
            }
        }
        // PRG redirect
        $qs = [];
        if ($selectedStatus !== '') $qs[] = 'status=' . urlencode($selectedStatus);
        if ($selectedSort !== '')   $qs[] = 'sort=' . urlencode($selectedSort);
        if ($selectedOrder !== '')  $qs[] = 'order=' . urlencode($selectedOrder);
        if ($searchTerm !== '')     $qs[] = 'search=' . urlencode($searchTerm);
        if (!empty($page))          $qs[] = 'page=' . (int)$page;
        if (!empty($success_message)) {
            flash_set('success', $success_message);
        } elseif (!empty($error_message)) {
            flash_set('error', $error_message);
        }
        $redirectUrl = 'index.php' . (count($qs) ? ('?' . implode('&', $qs)) : '');
        header('Location: ' . $redirectUrl);
        exit();
    } catch (Exception $e) {
        flash_set('error', 'Action failed: ' . $e->getMessage());
        header('Location: index.php');
        exit();
    }
}

try {
    // Get overall department statistics for header
    $departmentStats = $departmentManager->getOverallDepartmentStatistics();
    
    // Build filters
    $filters = [];
    if (!empty($selectedStatus)) {
        $filters['status'] = $selectedStatus;
    }
    if (!empty($searchTerm)) {
        $filters['search'] = $searchTerm;
    }
    
    // Set sorting options
    $validSortFields = ['department_name', 'department_code', 'created_at', 'total_users', 'active_faculty', 'active_students'];
    $sortField = in_array($selectedSort, $validSortFields) ? $selectedSort : 'department_name';
    $sortOrder = $selectedOrder === 'desc' ? 'desc' : 'asc';
    
    // Get departments with pagination
    $result = $departmentManager->getAllDepartments($filters, $page, $perPage, $sortField, $sortOrder);
    $departments = $result['departments'] ?? [];
    $pagination = $result['pagination'] ?? [
        'current_page' => 1,
        'per_page' => $perPage,
        'total' => 0,
        'total_pages' => 0,
        'has_previous' => false,
        'has_next' => false
    ];

} catch (Exception $e) {
    error_log("Department Management Error: " . $e->getMessage());
    $error_message = "Unable to load departments data. Please try again later.";
    $departments = [];
    $departmentStats = [];
    $pagination = [
        'current_page' => 1,
        'per_page' => $perPage,
        'total' => 0,
        'total_pages' => 0,
        'has_previous' => false,
        'has_next' => false
    ];
}

// Handle flash messages (session preferred, with URL fallback)
$flashMessage = null;
if (isset($_SESSION['flash']['success'])) {
    $flashMessage = ['type' => 'success', 'message' => $_SESSION['flash']['success']];
    unset($_SESSION['flash']['success']);
} elseif (isset($_SESSION['flash']['error'])) {
    $flashMessage = ['type' => 'error', 'message' => $_SESSION['flash']['error']];
    unset($_SESSION['flash']['error']);
} elseif (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
} elseif (isset($_GET['message'])) {
    if (isset($_GET['success'])) {
        $flashMessage = ['type' => 'success', 'message' => $_GET['message']];
    } elseif (isset($_GET['error'])) {
        $flashMessage = ['type' => 'error', 'message' => $_GET['message']];
    } else {
        // Default handling for backward compatibility
        if (isset($_GET['created'])) {
            $flashMessage = ['type' => 'success', 'message' => 'Department created successfully.'];
        } elseif (isset($_GET['updated'])) {
            $flashMessage = ['type' => 'success', 'message' => 'Department updated successfully.'];
        } elseif (isset($_GET['deleted'])) {
            $flashMessage = ['type' => 'success', 'message' => 'Department deleted successfully.'];
        }
    }
}

// Set page title
$pageTitle = "Department Management";
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
        .stat-icon.active { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.inactive { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.users { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }

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



        /* Search and Filters */
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

        /* Departments Container */
        .departments-container {
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
        .departments-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--bg-primary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        .departments-table {
            width: 100%;
            margin: 0;
        }

        .departments-table thead {
            background: rgba(255, 255, 255, 0.3);
        }

        .departments-table th {
            padding: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .departments-table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
        }

        .departments-table tbody tr {
            transition: all 0.3s ease;
        }

        .departments-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Dark mode table styles */
        [data-theme="dark"] .departments-container {
            background: rgba(0, 0, 0, 0.3) !important;
            border: 1px solid var(--glass-border) !important;
        }

        [data-theme="dark"] .departments-table {
            background-color: transparent !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .departments-table thead {
            background: rgba(30, 41, 59, 0.9) !important;
        }

        [data-theme="dark"] .departments-table thead th {
            background-color: rgba(30, 41, 59, 0.9) !important;
            color: var(--text-primary) !important;
            border-bottom-color: var(--border-color) !important;
        }

        [data-theme="dark"] .departments-table tbody tr {
            background-color: transparent !important;
            border-bottom: 1px solid var(--border-color) !important;
        }

        [data-theme="dark"] .departments-table tbody tr:hover {
            background-color: rgba(30, 41, 59, 0.7) !important;
        }

        [data-theme="dark"] .departments-table tbody tr:nth-child(even) {
            background-color: rgba(30, 41, 59, 0.3) !important;
        }

        [data-theme="dark"] .departments-table tbody tr:nth-child(even):hover {
            background-color: rgba(30, 41, 59, 0.7) !important;
        }

        [data-theme="dark"] .departments-table tbody td {
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
            background-color: transparent !important;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.active { background: rgba(16, 185, 129, 0.2); color: #047857; }
        .status-badge.inactive { background: rgba(239, 68, 68, 0.2); color: #991b1b; }

        /* Department Info */
        .department-info h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .department-info p {
            margin: 0.25rem 0 0 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .department-stats {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .stat-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(0, 0, 0, 0.06);
            padding: 0.25rem 0.5rem;
            border-radius: 999px;
            line-height: 1;
        }

        .stat-chip .bi { font-size: 0.9rem; opacity: 0.9; }

        .stat-chip.users { background: rgba(59, 130, 246, 0.12); border-color: rgba(59, 130, 246, 0.25); color: #1e3a8a; }
        .stat-chip.faculty { background: rgba(139, 92, 246, 0.12); border-color: rgba(139, 92, 246, 0.25); color: #4c1d95; }
        .stat-chip.students { background: rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.25); color: #065f46; }

        [data-theme="dark"] .stat-chip {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--glass-border);
            color: var(--text-primary);
        }

        /* Action Buttons in Table */
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
        .department-card {
            display: none;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .department-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .department-card-avatar {
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

        .department-card-info h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .department-card-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0.25rem 0;
        }

        .department-card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .department-card-detail {
            font-size: 0.875rem;
        }

        .department-card-detail strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .department-card-detail span {
            color: var(--text-secondary);
        }

        .department-card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Dark mode cards */
        [data-theme="dark"] .department-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
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

            .departments-table {
                font-size: 0.875rem;
            }

            .departments-table th,
            .departments-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* Mobile: switch table to cards */
        @media (max-width: 768px) {
            .departments-table {
                display: none !important;
            }
            .department-card {
                display: block !important;
                /* Stronger, more visible borders on mobile */
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
                /* Add left margin for mobile spacing */
                margin-left: 1rem;
                margin-right: 1rem;
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .department-card {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
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

        /* Sortable Headers */
        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .sortable:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .sortable::after {
            content: '↕️';
            position: absolute;
            right: 0.5rem;
            opacity: 0.5;
            font-size: 0.75rem;
        }

        .sortable.asc::after {
            content: '↑';
            opacity: 1;
        }

        .sortable.desc::after {
            content: '↓';
            opacity: 1;
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
                        <h1 class="page-title">🏢 Department Management</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="create.php" class="btn-action btn-primary">
                            ➕ Add Department
                        </a>
                        <a href="resources.php" class="btn-action btn-primary">
                           🏢 Manage Resources
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
                <div class="stat-number"><?= $departmentStats['total'] ?? 0 ?></div>
                <div class="stat-label">Total Departments</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon active">✅</div>
                <div class="stat-number"><?= $departmentStats['active'] ?? 0 ?></div>
                <div class="stat-label">Active</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon inactive">⚠️</div>
                <div class="stat-number"><?= $departmentStats['inactive'] ?? 0 ?></div>
                <div class="stat-label">Inactive</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon users">👥</div>
                <div class="stat-number"><?= $departmentStats['total_users'] ?? 0 ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters glass-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search departments..." id="searchInput" value="<?= htmlspecialchars($searchTerm) ?>">
                    <i class="bi bi-search search-icon"></i>
                </div>
                
                <div class="filter-controls">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="filter-select" id="statusFilter" onchange="handleStatusFilter()">
                            <option value="">All Status</option>
                            <option value="active" <?= $selectedStatus == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $selectedStatus == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Sort By</label>
                        <select class="filter-select" id="sortFilter" onchange="handleSortFilter()">
                            <option value="department_name" <?= $selectedSort == 'department_name' ? 'selected' : '' ?>>Name</option>
                            <option value="department_code" <?= $selectedSort == 'department_code' ? 'selected' : '' ?>>Code</option>
                            <option value="total_users" <?= $selectedSort == 'total_users' ? 'selected' : '' ?>>Total Users</option>
                            <option value="created_at" <?= $selectedSort == 'created_at' ? 'selected' : '' ?>>Created Date</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Order</label>
                        <select class="filter-select" id="orderFilter" onchange="handleOrderFilter()">
                            <option value="asc" <?= $selectedOrder == 'asc' ? 'selected' : '' ?>>Ascending</option>
                            <option value="desc" <?= $selectedOrder == 'desc' ? 'selected' : '' ?>>Descending</option>
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

        <!-- Departments Table -->
        <div class="departments-container glass-card">
            <?php if (empty($departments)): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 3H5C3.89 3 3 3.89 3 5V19C3 20.11 3.89 21 5 21H19C20.11 21 21 20.11 21 19V5C21 3.89 20.11 3 19 3ZM19 19H5V5H19V19ZM17 12H15V14H17V12ZM11 12H9V14H11V12ZM7 12H5V14H7V12Z" fill="currentColor"/>
                    </svg>
                    <h4>No Departments Found</h4>
                    <p>No departments match your current filters. Try adjusting your search criteria or create a new department.</p>
                    <a href="create.php" class="btn-action btn-primary">
                        ➕ Create First Department
                    </a>
                </div>
            <?php else: ?>
                <!-- Table -->
                <div class="table-responsive-custom">
                    <table class="departments-table table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th class="sortable <?= $selectedSort === 'department_name' ? $selectedOrder : '' ?>" onclick="handleSort('department_name')">
                                    Department
                                </th>
                                <th class="sortable <?= $selectedSort === 'department_code' ? $selectedOrder : '' ?>" onclick="handleSort('department_code')" style="width: 100px;">
                                    Code
                                </th>
                                <th style="width: 120px;">Statistics</th>
                                <th style="width: 120px;">Head</th>
                                <th class="sortable <?= $selectedSort === 'created_at' ? $selectedOrder : '' ?>" onclick="handleSort('created_at')" style="width: 120px;">
                                    Created
                                </th>
                                <th style="width: 80px;">Status</th>
                                <th style="width: 160px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $department): ?>
                                <tr id="department-<?= $department['department_id'] ?>" data-department-id="<?= $department['department_id'] ?>"
                                    data-name="<?= strtolower(htmlspecialchars($department['department_name'])) ?>"
                                    data-code="<?= strtolower(htmlspecialchars($department['department_code'])) ?>"
                                    data-status="<?= $department['is_active'] ? 'active' : 'inactive' ?>"
                                    data-total-users="<?= (int)($department['total_users'] ?? 0) ?>"
                                    data-created="<?= strtotime($department['created_at']) ?>">
                                    <td>
                                        <input type="checkbox" name="selected_departments[]" 
                                               value="<?= $department['department_id'] ?>" 
                                               form="bulkActionForm"
                                               onchange="updateBulkActions()">
                                    </td>
                                    
                                    <td>
                                        <div class="department-info">
                                            <h6><?= htmlspecialchars($department['department_name']) ?></h6>
                                            <?php if (!empty($department['description'])): ?>
                                                <p><?= htmlspecialchars(substr($department['description'], 0, 80)) ?><?= strlen($department['description']) > 80 ? '...' : '' ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($department['department_code']) ?></span>
                                    </td>
                                    
                                    <td>
                                        <div class="department-stats">
                                            <span class="stat-chip users" title="Total Users">
                                                <i class="bi bi-people-fill"></i>
                                                <span><?= $department['total_users'] ?? 0 ?></span>
                                            </span>
                                            <span class="stat-chip faculty" title="Active Faculty">
                                                <i class="bi bi-person-badge"></i>
                                                <span><?= $department['active_faculty'] ?? 0 ?></span>
                                            </span>
                                            <span class="stat-chip students" title="Active Students">
                                                <i class="bi bi-mortarboard-fill"></i>
                                                <span><?= $department['active_students'] ?? 0 ?></span>
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <?php if (!empty($department['head_name'])): ?>
                                            <div class="department-head">
                                                <small class="fw-medium">
                                                    <?= htmlspecialchars($department['head_name']) ?>
                                                </small>
                                                <?php if (!empty($department['head_employee_id'])): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($department['head_employee_id']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-muted">No Head Assigned</small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <small class="text-muted">
                                            <?= timeAgo($department['created_at']) ?>
                                        </small>
                                    </td>
                                    
                                    <td>
                                        <span class="status-badge <?= $department['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $department['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-action btn-primary" 
                                                    onclick="viewDepartment(<?= $department['department_id'] ?>)"
                                                    title="View Details">
                                                👁️
                                            </button>
                                            
                                            <a href="edit.php?id=<?= $department['department_id'] ?>" 
                                               class="btn-action btn-outline" 
                                               title="Edit Department">
                                                ✏️
                                            </a>
                                            
                                            <?php if ($department['is_active']): ?>
                                                <a href="activate-deactivate.php?action=deactivate&id=<?= $department['department_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                   class="btn-action btn-outline"
                                                   title="Deactivate Department"
                                                   onclick="return confirm('Are you sure you want to deactivate this department?')">
                                                    ⏸️
                                                </a>
                                            <?php else: ?>
                                                <a href="activate-deactivate.php?action=activate&id=<?= $department['department_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                   class="btn-action btn-primary"
                                                   title="Activate Department"
                                                   onclick="return confirm('Are you sure you want to activate this department?')">
                                                    ▶️
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="delete.php?id=<?= $department['department_id'] ?>" 
                                               class="btn-action btn-danger" 
                                               title="Delete Department"
                                               onclick="return confirm('Are you sure you want to delete this department? This action cannot be undone and will affect all associated users.')">
                                                🗑️
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile Card View -->
                    <div class="departments-cards">
                        <?php foreach ($departments as $department): ?>
                            <div class="department-card" data-department-id="<?= $department['department_id'] ?>"
                                 data-name="<?= strtolower(htmlspecialchars($department['department_name'])) ?>"
                                 data-code="<?= strtolower(htmlspecialchars($department['department_code'])) ?>"
                                 data-status="<?= $department['is_active'] ? 'active' : 'inactive' ?>"
                                 data-total-users="<?= (int)($department['total_users'] ?? 0) ?>"
                                 data-created="<?= strtotime($department['created_at']) ?>">
                                <div class="department-card-header">
                                    <div class="department-card-avatar">
                                        <?= strtoupper(substr($department['department_code'], 0, 2)) ?>
                                    </div>
                                    <div class="department-card-info">
                                        <h6><?= htmlspecialchars($department['department_name']) ?></h6>
                                        <div class="department-card-meta">
                                            <span class="status-badge <?= $department['is_active'] ? 'active' : 'inactive' ?>"><?= $department['is_active'] ? 'Active' : 'Inactive' ?></span>
                                            <span class="badge bg-primary ms-2"><?= htmlspecialchars($department['department_code']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="department-card-details">
                                    <div class="department-card-detail">
                                        <strong>Total Users:</strong>
                                        <span><?= $department['total_users'] ?? 0 ?></span>
                                    </div>
                                    <div class="department-card-detail">
                                        <strong>Faculty:</strong>
                                        <span><?= $department['active_faculty'] ?? 0 ?></span>
                                    </div>
                                    <div class="department-card-detail">
                                        <strong>Students:</strong>
                                        <span><?= $department['active_students'] ?? 0 ?></span>
                                    </div>
                                    <div class="department-card-detail">
                                        <strong>Department Head:</strong>
                                        <span><?= htmlspecialchars($department['head_name'] ?? 'Not Assigned') ?></span>
                                    </div>
                                    <div class="department-card-detail">
                                        <strong>Created:</strong>
                                        <span><?= timeAgo($department['created_at']) ?></span>
                                    </div>
                                    <?php if (!empty($department['description'])): ?>
                                        <div class="department-card-detail" style="grid-column: 1/-1;">
                                            <strong>Description:</strong>
                                            <span><?= htmlspecialchars($department['description']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="department-card-actions d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn-action btn-outline" onclick="viewDepartment(<?= (int)$department['department_id'] ?>)">View</button>
                                    <a href="edit.php?id=<?= (int)$department['department_id'] ?>" class="btn-action btn-outline">Edit</a>
                                    <?php if ($department['is_active']): ?>
                                        <a href="activate-deactivate.php?action=deactivate&id=<?= $department['department_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                           class="btn-action btn-outline"
                                           onclick="return confirm('Are you sure you want to deactivate this department?')">
                                            ⏸️ Deactivate
                                        </a>
                                    <?php else: ?>
                                        <a href="activate-deactivate.php?action=activate&id=<?= $department['department_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                           class="btn-action btn-primary"
                                           onclick="return confirm('Are you sure you want to activate this department?')">
                                            ▶️ Activate
                                        </a>
                                    <?php endif; ?>
                                    <a href="delete.php?id=<?= (int)$department['department_id'] ?>" 
                                       class="btn-action btn-danger" 
                                       title="Delete Department"
                                       onclick="return confirm('Are you sure you want to delete this department? This action cannot be undone and will affect all associated users.')">
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
                        <nav aria-label="Departments pagination">
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
                                of <?= $pagination['total'] ?> departments
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Department Detail Modal -->
    <div class="modal fade" id="departmentModal" tabindex="-1" aria-labelledby="departmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="departmentModalLabel">Department Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="departmentModalBody">
                    <!-- Content will be loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="editDepartmentBtn" class="btn btn-primary">Edit Department</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>

        // Sidebar JS handlers removed to prevent unintended expansion on single link clicks.

        // Instant client-side filtering and sorting
        function initializeSearchAndFilters() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const sortFilter = document.getElementById('sortFilter');
            const orderFilter = document.getElementById('orderFilter');

            let hasScrolledToResults = false;
            function scrollToResultsOnce() {
                if (hasScrolledToResults) return;
                
                // Only scroll if there are active filters or search terms
                const searchTerm = searchInput?.value?.trim() || '';
                const statusVal = statusFilter?.value || '';
                const sortVal = sortFilter?.value || 'department_name';
                const orderVal = orderFilter?.value || 'asc';
                
                const hasActiveFilters = !!searchTerm || !!statusVal || (sortVal !== 'department_name') || (orderVal !== 'asc');
                
                if (!hasActiveFilters) return; // Don't scroll on normal page load
                
                const container = document.querySelector('.departments-container') || document.querySelector('.departments-cards');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    hasScrolledToResults = true;
                }
            }

            function applyFilterAndSort() {
                const term = (searchInput?.value || '').toLowerCase();
                const statusVal = (statusFilter?.value || '').toLowerCase();
                const sortVal = (sortFilter?.value || 'department_name');
                const orderVal = (orderFilter?.value || 'asc');

                const tbody = document.querySelector('.departments-table tbody');
                const rows = Array.from(document.querySelectorAll('.departments-table tbody tr'));
                const cardsContainer = document.querySelector('.departments-cards');
                const cards = Array.from(document.querySelectorAll('.departments-cards .department-card'));

                const matches = (el) => {
                    // derive searchable fields from dataset or inner text
                    const name = el.dataset.name || '';
                    const code = el.dataset.code || '';
                    const descEl = el.querySelector?.('.department-info p') || el.querySelector?.('.department-card-detail span');
                    const desc = descEl ? (descEl.textContent || '').toLowerCase() : '';
                    const matchesTerm = !term || name.includes(term) || code.includes(term) || desc.includes(term);
                    const matchesStatus = !statusVal || (el.dataset.status || '') === statusVal;
                    return matchesTerm && matchesStatus;
                };

                // Filter visibility
                rows.forEach(r => { r.style.display = matches(r) ? '' : 'none'; });
                cards.forEach(c => { c.style.display = matches(c) ? '' : 'none'; });

                // Sort visible items
                const comparator = (a, b) => {
                    let av, bv;
                    switch (sortVal) {
                        case 'department_code':
                            av = a.dataset.code || ''; bv = b.dataset.code || ''; break;
                        case 'total_users':
                            av = parseInt(a.dataset.totalUsers || a.dataset['total-users'] || '0');
                            bv = parseInt(b.dataset.totalUsers || b.dataset['total-users'] || '0');
                            break;
                        case 'created_at':
                            av = parseInt(a.dataset.created || '0');
                            bv = parseInt(b.dataset.created || '0');
                            break;
                        case 'department_name':
                        default:
                            av = a.dataset.name || ''; bv = b.dataset.name || ''; break;
                    }
                    if (typeof av === 'string') av = av.toString();
                    if (typeof bv === 'string') bv = bv.toString();
                    let res;
                    if (typeof av === 'number' && typeof bv === 'number') res = av - bv; else res = av.localeCompare(bv);
                    return orderVal === 'desc' ? -res : res;
                };

                const visibleRows = rows.filter(r => r.style.display !== 'none');
                const visibleCards = cards.filter(c => c.style.display !== 'none');

                visibleRows.sort(comparator);
                visibleCards.sort(comparator);

                // Re-append in sorted order
                if (tbody) visibleRows.forEach(r => tbody.appendChild(r));
                if (cardsContainer) visibleCards.forEach(c => cardsContainer.appendChild(c));

                // Hide alerts while actively filtering/searching
                const isFiltering = !!term || !!statusVal || (sortVal !== 'department_name') || (orderVal !== 'asc');
                document.querySelectorAll('.alert').forEach(a => { a.style.display = isFiltering ? 'none' : ''; });

                scrollToResultsOnce();
            }

            // Bind events
            if (searchInput) searchInput.addEventListener('input', applyFilterAndSort);
            if (statusFilter) statusFilter.addEventListener('change', applyFilterAndSort);
            if (sortFilter) sortFilter.addEventListener('change', applyFilterAndSort);
            if (orderFilter) orderFilter.addEventListener('change', applyFilterAndSort);

            // Initial pass for any preselected values
            applyFilterAndSort();
        }

        // Filter functions
        // Deprecated URL-based handlers kept as no-ops
        function handleStatusFilter() { /* handled by initializeSearchAndFilters */ }
        function handleSortFilter() { /* handled by initializeSearchAndFilters */ }
        function handleOrderFilter() { /* handled by initializeSearchAndFilters */ }

        function handleSort(field) {
            const currentSort = '<?= $selectedSort ?>';
            const currentOrder = '<?= $selectedOrder ?>';
            
            let newOrder = 'asc';
            if (field === currentSort && currentOrder === 'asc') {
                newOrder = 'desc';
            }
            
            const url = new URL(window.location);
            url.searchParams.set('sort', field);
            url.searchParams.set('order', newOrder);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function clearFilters() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const sortFilter = document.getElementById('sortFilter');
            const orderFilter = document.getElementById('orderFilter');
            if (searchInput) searchInput.value = '';
            if (statusFilter) statusFilter.value = '';
            if (sortFilter) sortFilter.value = 'department_name';
            if (orderFilter) orderFilter.value = 'asc';
            if (typeof initializeSearchAndFilters === 'function') initializeSearchAndFilters();
        }

        function updateURL(param, value) {
            const url = new URL(window.location);
            if (value) {
                url.searchParams.set(param, value);
            } else {
                url.searchParams.delete(param);
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        // Select all functionality
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('input[name="selected_departments[]"]');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        // Update bulk actions visibility
        function updateBulkActions() {
            const selectedCheckboxes = document.querySelectorAll('input[name="selected_departments[]"]:checked');
            const bulkActions = document.getElementById('bulkActions');
            
            if (selectedCheckboxes.length > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
            }
        }

        // Clear selection
        function clearSelection() {
            const checkboxes = document.querySelectorAll('input[name="selected_departments[]"]');
            const selectAll = document.getElementById('selectAll');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAll.checked = false;
            
            updateBulkActions();
        }

        // Bulk action confirmation
        function confirmBulkAction() {
            const selectedCheckboxes = document.querySelectorAll('input[name="selected_departments[]"]:checked');
            const actionSelect = document.querySelector('select[name="bulk_action_type"]');
            
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one department.');
                return false;
            }
            
            if (!actionSelect.value) {
                alert('Please select an action.');
                return false;
            }
            
            const actionText = actionSelect.options[actionSelect.selectedIndex].text;
            const confirmMessage = `Are you sure you want to ${actionText.toLowerCase()} ${selectedCheckboxes.length} department(s)?`;
            
            if (actionSelect.value === 'delete') {
                return confirm(confirmMessage + '\n\nThis action cannot be undone and will affect all associated users.');
            }
            
            return confirm(confirmMessage);
        }

        // Quick action confirmation
        function confirmQuickAction(action) {
            if (action.includes('delete')) {
                return confirm(`Are you sure you want to ${action} this department? This action cannot be undone and will affect all associated users.`);
            }
            return confirm(`Are you sure you want to ${action} this department?`);
        }

        // View department details
        function viewDepartment(departmentId) {
            // Show loading state
            document.getElementById('departmentModalBody').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('departmentModal'));
            modal.show();
            
            // Update edit button
            document.getElementById('editDepartmentBtn').href = `edit.php?id=${departmentId}`;
            
            // Fetch department details via AJAX (with timeout and credentials)
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000); // 15s timeout

            fetch(`/timetable-management/includes/api/department-detail.php?id=${departmentId}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal
                })
                .then(async response => {
                    clearTimeout(timeoutId);
                    let data;
                    try {
                        data = await response.json();
                    } catch (e) {
                        throw new Error(`Invalid JSON response (status ${response.status})`);
                    }
                    if (!response.ok) {
                        const msg = (data && (data.error || data.message)) ? data.error || data.message : `Request failed with status ${response.status}`;
                        throw new Error(msg);
                    }
                    return data;
                })
                .then(data => {
                    if (data.success) {
                        const dept = data.department;
                        document.getElementById('departmentModalBody').innerHTML = `
                            <div class="department-details">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h4>${dept.department_name}</h4>
                                        <div class="mb-3">
                                            <span class="badge bg-primary">${dept.department_code}</span>
                                            <span class="status-badge ${dept.is_active ? 'active' : 'inactive'} ms-2">${dept.is_active ? 'Active' : 'Inactive'}</span>
                                        </div>
                                        ${dept.description ? `<div class="description-content p-3 bg-light rounded mb-3">${dept.description}</div>` : ''}
                                        
                                        <h5>Statistics</h5>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="stat-item">
                                                    <strong>Total Users:</strong> ${dept.total_users || 0}
                                                </div>
                                                <div class="stat-item">
                                                    <strong>Active Faculty:</strong> ${dept.active_faculty || 0}
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="stat-item">
                                                    <strong>Active Students:</strong> ${dept.active_students || 0}
                                                </div>
                                                <div class="stat-item">
                                                    <strong>Total Subjects:</strong> ${dept.total_subjects || 0}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="department-meta">
                                            <p><strong>Department Head:</strong><br>${dept.department_head_name || 'Not Assigned'}</p>
                                            ${dept.head_employee_id ? `<p><strong>Employee ID:</strong><br>${dept.head_employee_id}</p>` : ''}
                                            <p><strong>Established:</strong><br>${dept.established_date ? new Date(dept.established_date).toLocaleDateString() : 'Not Set'}</p>
                                            <p><strong>Created:</strong><br>${new Date(dept.created_at).toLocaleString()}</p>
                                            ${dept.contact_email ? `<p><strong>Contact Email:</strong><br>${dept.contact_email}</p>` : ''}
                                            ${dept.contact_phone ? `<p><strong>Contact Phone:</strong><br>${dept.contact_phone}</p>` : ''}
                                            ${dept.building_location ? `<p><strong>Location:</strong><br>${dept.building_location}</p>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        const msg = data.error || data.message || 'Failed to load department details.';
                        document.getElementById('departmentModalBody').innerHTML = `<div class="alert alert-danger">${msg}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('departmentModalBody').innerHTML = `<div class="alert alert-danger">Error loading department details: ${error.message}</div>`;
                });
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            applyCurrentTheme();
            
            // Sidebar handlers removed to prevent unintended expansion

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

            // Initialize instant filters and sorting
            initializeSearchAndFilters();
        });

        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
            
            if (theme === 'dark') {
                applyDarkModeTableStyles();
            } else {
                removeDarkModeTableStyles();
            }
        }

        function applyDarkModeTableStyles() {
            const table = document.querySelector('.departments-table');
            const container = document.querySelector('.departments-container');
            
            if (container) {
                container.style.setProperty('background', 'rgba(0, 0, 0, 0.3)', 'important');
                container.style.setProperty('border', '1px solid rgba(255, 255, 255, 0.1)', 'important');
            }
            
            if (table) {
                table.style.setProperty('background-color', 'transparent', 'important');
                table.style.setProperty('color', '#ffffff', 'important');
                
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
                
                const tbody = table.querySelector('tbody');
                if (tbody) {
                    const rows = tbody.querySelectorAll('tr');
                    rows.forEach((row, index) => {
                        row.style.setProperty('background-color', index % 2 === 1 ? 'rgba(30, 41, 59, 0.3)' : 'transparent', 'important');
                        row.style.setProperty('border-bottom', '1px solid #404040', 'important');
                        
                        const cells = row.querySelectorAll('td');
                        cells.forEach(cell => {
                            cell.style.setProperty('color', '#ffffff', 'important');
                            cell.style.setProperty('border-color', '#404040', 'important');
                            
                            const textElements = cell.querySelectorAll('h6, p, span:not(.status-badge):not(.badge), strong, small');
                            textElements.forEach(element => {
                                if (!element.classList.contains('status-badge') && 
                                    !element.classList.contains('badge')) {
                                    element.style.setProperty('color', '#ffffff', 'important');
                                }
                            });
                        });
                        
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
            const table = document.querySelector('.departments-table');
            const container = document.querySelector('.departments-container');
            
            if (container) {
                container.style.removeProperty('background');
                container.style.removeProperty('border');
            }
            
            if (table) {
                table.style.removeProperty('background-color');
                table.style.removeProperty('color');
                
                const allElements = table.querySelectorAll('*');
                allElements.forEach(element => {
                    element.style.removeProperty('background-color');
                    element.style.removeProperty('color');
                    element.style.removeProperty('border-color');
                    element.style.removeProperty('border-bottom');
                });
            }
        }

        // Listen for theme changes
        window.addEventListener('themeChanged', function(event) {
            applyCurrentTheme();
        });

        // Auto-scroll to updated department entry after activate/deactivate
        function autoScrollToUpdatedEntry() {
            const urlParams = new URLSearchParams(window.location.search);
            const updatedId = urlParams.get('updated_id');
            const updated = urlParams.get('updated');
            
            console.log('Auto-scroll check:', { updated, updatedId });
            
            if (updated === '1' && updatedId) {
                console.log('Looking for department entry with ID:', updatedId);
                
                // Try to find the updated entry in both desktop and mobile views
                const desktopRow = document.querySelector(`tr[data-department-id="${updatedId}"]`);
                const mobileCard = document.querySelector(`.department-card[data-department-id="${updatedId}"]`);
                
                console.log('Found elements:', { desktopRow, mobileCard });
                
                const targetElement = desktopRow || mobileCard;
                
                if (targetElement) {
                    console.log('Scrolling to element:', targetElement);
                    
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
                } else {
                    console.log('Target element not found for ID:', updatedId);
                    // Log all available data-department-id attributes for debugging
                    const allRows = document.querySelectorAll('[data-department-id]');
                    console.log('Available department IDs:', Array.from(allRows).map(el => el.getAttribute('data-department-id')));
                }
            }
        }

        // Run auto-scroll on page load
        document.addEventListener('DOMContentLoaded', autoScrollToUpdatedEntry);

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F for search focus
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            
            // Escape to clear search
            if (e.key === 'Escape') {
                document.getElementById('searchInput').value = '';
                document.getElementById('searchInput').dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>
</html>

<?php
/**
 * Helper function to build query string for pagination
 */
function buildQueryString() {
    $params = [];
    
    if (!empty($_GET['status'])) {
        $params[] = 'status=' . urlencode($_GET['status']);
    }
    if (!empty($_GET['sort'])) {
        $params[] = 'sort=' . urlencode($_GET['sort']);
    }
    if (!empty($_GET['order'])) {
        $params[] = 'order=' . urlencode($_GET['order']);
    }
    if (!empty($_GET['search'])) {
        $params[] = 'search=' . urlencode($_GET['search']);
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