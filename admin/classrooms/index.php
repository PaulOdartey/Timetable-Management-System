<?php
/**
 * Admin Classroom Management - Main Overview
 * Timetable Management System
 * 
 * Professional interface for admin to manage all system classrooms including
 * room details, capacity management, utilization tracking, and comprehensive analytics
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Classroom.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$classroomManager = new Classroom();

// Initialize variables
$classrooms = [];
$classroomStats = [];
$buildings = [];
$departments = [];
$error_message = '';
$success_message = '';
$selectedBuilding = $_GET['building'] ?? '';
$selectedType = $_GET['type'] ?? '';
$selectedStatus = $_GET['status'] ?? '';
$selectedDepartment = $_GET['department'] ?? '';

// Handle bulk actions
// Top-level flash message (from separate handlers)
$topFlash = null;
if (isset($_SESSION['flash_message'])) {
    $topFlash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Preserve updated markers for auto-scroll
$updatedFlag = isset($_GET['updated']) ? $_GET['updated'] : null;
$updatedId = isset($_GET['updated_id']) ? (int)$_GET['updated_id'] : null;

// Handle bulk actions
if ($_POST['action'] ?? '' === 'bulk_action' && !empty($_POST['selected_classrooms'])) {
    $selectedClassrooms = $_POST['selected_classrooms'];
    $bulkAction = $_POST['bulk_action_type'];
    
    try {
        if ($bulkAction === 'activate') {
            $count = 0;
            foreach ($selectedClassrooms as $classroomId) {
                $result = $classroomManager->updateClassroom($classroomId, ['is_active' => 1]);
                if ($result['success']) $count++;
            }
            $success_message = "Activated {$count} classroom(s) successfully.";
        } elseif ($bulkAction === 'deactivate') {
            $count = 0;
            foreach ($selectedClassrooms as $classroomId) {
                $result = $classroomManager->updateClassroom($classroomId, ['is_active' => 0]);
                if ($result['success']) $count++;
            }
            $success_message = "Deactivated {$count} classroom(s) successfully.";
        } elseif (in_array($bulkAction, ['available', 'maintenance', 'reserved', 'closed'])) {
            $result = $classroomManager->bulkUpdateStatus($selectedClassrooms, $bulkAction);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
    } catch (Exception $e) {
        $error_message = "Bulk action failed: " . $e->getMessage();
    }
}

// Handle individual actions
if (isset($_GET['action']) && isset($_GET['classroom_id'])) {
    $classroomId = (int)$_GET['classroom_id'];
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'activate':
                $result = $classroomManager->updateClassroom($classroomId, ['is_active' => 1]);
                $success_message = $result['success'] ? $result['message'] : $result['message'];
                break;
            case 'deactivate':
                $result = $classroomManager->updateClassroom($classroomId, ['is_active' => 0]);
                $success_message = $result['success'] ? $result['message'] : $result['message'];
                break;
            case 'set_available':
                $result = $classroomManager->updateClassroom($classroomId, ['status' => 'available']);
                $success_message = $result['success'] ? $result['message'] : $result['message'];
                break;
            case 'set_maintenance':
                $result = $classroomManager->updateClassroom($classroomId, ['status' => 'maintenance']);
                $success_message = $result['success'] ? $result['message'] : $result['message'];
                break;
        }
    } catch (Exception $e) {
        $error_message = "Action failed: " . $e->getMessage();
    }
}

try {
    // Build filters for classroom query
    $filters = [];
    if (!empty($selectedBuilding)) {
        $filters['building'] = $selectedBuilding;
    }
    if (!empty($selectedType)) {
        $filters['type'] = $selectedType;
    }
    if (!empty($selectedStatus)) {
        $filters['status'] = $selectedStatus;
    }
    if (!empty($selectedDepartment)) {
        $filters['department_id'] = $selectedDepartment;
    }

    // Get all classrooms with comprehensive information
    $classrooms = $classroomManager->getAllClassrooms($filters);

    // Get classroom statistics
    $classroomStats = $classroomManager->getClassroomStatistics();

    // Get unique buildings for filter dropdown
    $buildings = $classroomManager->getBuildings();

    // Get departments for filter dropdown
    $departments = $db->fetchAll("
        SELECT department_id, department_name, department_code 
        FROM departments 
        WHERE is_active = 1 
        ORDER BY department_name ASC
    ");

} catch (Exception $e) {
    error_log("Admin Classroom Management Error: " . $e->getMessage());
    $error_message = "Unable to load classroom data. Please try again later.";
    $classroomStats = [
        'total_classrooms' => 0, 'active_classrooms' => 0, 'available_classrooms' => 0,
        'maintenance_classrooms' => 0, 'reserved_classrooms' => 0, 'lecture_rooms' => 0,
        'lab_rooms' => 0, 'seminar_rooms' => 0, 'auditorium_rooms' => 0, 'unique_buildings' => 0,
        'total_capacity' => 0, 'average_capacity' => 0, 'utilization_rate' => 0
    ];
}

// Set page title
$pageTitle = "Classroom Management";
$currentPage = "classrooms";
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
        .stat-icon.available { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
        .stat-icon.maintenance { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.reserved { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .stat-icon.lecture { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .stat-icon.lab { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }
        .stat-icon.capacity { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .stat-icon.buildings { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }
        .stat-icon.utilization { background: linear-gradient(135deg, #84cc16 0%, #65a30d 100%); }

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

        /* Ensure header flash alerts align left */
        .page-header .alert {
            margin-left: 0 !important;
            margin-right: auto !important;
            text-align: left;
            align-self: flex-start;
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
            flex-wrap: nowrap; /* keep filters and Clear Filters inline on desktop */
            margin-left: auto; /* push filters to the right like subjects */
        }

        .filter-controls .btn-action {
            white-space: nowrap; /* prevent Clear Filters from wrapping */
        }

        /* Keep search and filters inline on desktop */
        .search-filters .filters-row {
            display: flex;
            flex-wrap: nowrap;
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

        /* Match subjects mobile behavior: stack filters and reduce padding */
        @media (max-width: 1024px) {
            .search-filters .filters-row {
                flex-wrap: wrap; /* stack on mobile/tablet */
            }
            .filter-controls {
                flex-direction: column;
                align-items: stretch;
                flex-wrap: wrap; /* allow wrapping on mobile */
                margin-left: 0; /* reset on mobile for full-width */
            }

            .search-filters {
                padding: 1rem;
            }
        }

        .filter-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.5);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        /* Dark mode filter styles */
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

        [data-theme="dark"] .search-filters {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        /* Classrooms Table Container */
        .classrooms-container {
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
        .classrooms-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--bg-primary);
            /* subtle shadow when stuck */
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        /* Dark mode header background alignment */
        [data-theme="dark"] .classrooms-table thead th {
            background: var(--bg-secondary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
        }

        .classrooms-table {
            width: 100%;
            margin: 0;
        }

        .classrooms-table thead {
            background: rgba(255, 255, 255, 0.3);
        }

        .classrooms-table th {
            padding: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .classrooms-table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
        }

        .classrooms-table tbody tr {
            transition: all 0.3s ease;
        }

        .classrooms-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Dark mode table styles - Enhanced and Fixed */
        /* Row highlight animation for auto-scroll feedback */
        .row-highlight {
            animation: rowPulse 2s ease-in-out 1;
        }
        @keyframes rowPulse {
            0% { box-shadow: 0 0 0 rgba(99,102,241,0); background-color: rgba(99,102,241,0.08); }
            50% { box-shadow: 0 0 0.75rem rgba(99,102,241,0.35); background-color: rgba(99,102,241,0.15); }
            100% { box-shadow: 0 0 0 rgba(99,102,241,0); background-color: transparent; }
        }

        [data-theme="dark"] .classrooms-container {
            background: rgba(0, 0, 0, 0.3) !important;
            border: 1px solid var(--glass-border) !important;
        }

        [data-theme="dark"] .classrooms-table {
            background-color: transparent !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .classrooms-table thead {
            background: rgba(30, 41, 59, 0.9) !important;
        }

        [data-theme="dark"] .classrooms-table thead th {
            background-color: rgba(30, 41, 59, 0.9) !important;
            color: var(--text-primary) !important;
            border-bottom-color: var(--border-color) !important;
        }

        [data-theme="dark"] .classrooms-table tbody tr {
            background-color: transparent !important;
            border-bottom: 1px solid var(--border-color) !important;
        }

        [data-theme="dark"] .classrooms-table tbody tr:hover {
            background-color: rgba(30, 41, 59, 0.7) !important;
        }

        [data-theme="dark"] .classrooms-table tbody tr:nth-child(even) {
            background-color: rgba(30, 41, 59, 0.3) !important;
        }

        [data-theme="dark"] .classrooms-table tbody tr:nth-child(even):hover {
            background-color: rgba(30, 41, 59, 0.7) !important;
        }

        [data-theme="dark"] .classrooms-table tbody td {
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
            background-color: transparent !important;
        }

        [data-theme="dark"] .classrooms-table tbody td small {
            color: var(--text-secondary) !important;
        }

        [data-theme="dark"] .classroom-meta {
            color: var(--text-secondary) !important;
        }

        [data-theme="dark"] .classroom-details h6 {
            color: var(--text-primary) !important;
        }

        /* Dark mode badge styles */
        [data-theme="dark"] .badge {
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Dark mode capacity indicator */
        [data-theme="dark"] .capacity-indicator {
            background: rgba(255, 255, 255, 0.1) !important;
            color: var(--text-primary) !important;
        }

        /* Dark mode utilization bar */
        [data-theme="dark"] .utilization-bar {
            background: rgba(255, 255, 255, 0.1) !important;
        }

        /* Table responsive container dark mode */
        [data-theme="dark"] .table-responsive-custom {
            background: transparent !important;
        }

        /* Ensure table cells maintain dark styling */
        [data-theme="dark"] .classrooms-table td,
        [data-theme="dark"] .classrooms-table th {
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        /* Dark mode for strong text elements */
        [data-theme="dark"] .classrooms-table strong {
            color: var(--text-primary) !important;
        }

        /* Dark mode for span elements in table */
        [data-theme="dark"] .classrooms-table span:not(.badge) {
            color: var(--text-primary) !important;
        }

        /* Dark mode for specific text colors */
        [data-theme="dark"] .classrooms-table [style*="color: var(--text-secondary)"] {
            color: var(--text-secondary) !important;
        }

        [data-theme="dark"] .classrooms-table [style*="color: var(--text-tertiary)"] {
            color: var(--text-tertiary) !important;
        }

        [data-theme="dark"] .classrooms-table [style*="color: var(--text-primary)"] {
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .classrooms-table tbody td small {
            color: var(--text-secondary) !important;
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

        [data-theme="dark"] .bulk-actions {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        .classroom-info {
            display: flex;
            align-items: center;
        }

        .classroom-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
            flex-shrink: 0;
            font-size: 1.25rem;
        }

        .classroom-icon.lecture { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .classroom-icon.lab { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }
        .classroom-icon.seminar { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .classroom-icon.auditorium { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

        .classroom-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .classroom-meta {
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

        .badge-type-lecture {
            background: rgba(6, 182, 212, 0.1);
            color: #0891b2;
        }

        .badge-type-lab {
            background: rgba(236, 72, 153, 0.1);
            color: #db2777;
        }

        .badge-type-seminar {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }

        .badge-type-auditorium {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .badge-status-available {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .badge-status-maintenance {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .badge-status-reserved {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }

        .badge-status-closed {
            background: rgba(100, 116, 139, 0.1);
            color: #475569;
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .badge-inactive {
            background: rgba(100, 116, 139, 0.1);
            color: #475569;
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

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
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
        .classroom-card {
            display: none;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .classroom-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .classroom-card-avatar {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
            font-size: 1.5rem;
        }

        .classroom-card-avatar.lecture { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .classroom-card-avatar.lab { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }
        .classroom-card-avatar.seminar { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .classroom-card-avatar.auditorium { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

        .classroom-card-info h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .classroom-card-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0.25rem 0;
        }

        .classroom-card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .classroom-card-detail {
            font-size: 0.875rem;
        }

        .classroom-card-detail strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .classroom-card-detail span {
            color: var(--text-secondary);
        }

        .classroom-card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Dark mode cards */
        [data-theme="dark"] .classroom-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        [data-theme="dark"] .classroom-card-info h6 {
            color: var(--text-primary);
        }

        [data-theme="dark"] .classroom-card-meta {
            color: var(--text-secondary);
        }

        [data-theme="dark"] .classroom-card-detail strong {
            color: var(--text-primary);
        }

        [data-theme="dark"] .classroom-card-detail span {
            color: var(--text-secondary);
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
            .classrooms-container {
                display: none;
            }

            .classroom-card {
                display: block;
                /* Stronger, more visible borders on mobile */
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
                /* Add left and right margins for mobile spacing */
                margin-left: 1rem;
                margin-right: 1rem;
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .classroom-card {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }

            .classroom-card-details {
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

        /* Capacity indicator */
        .capacity-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .capacity-small {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .capacity-medium {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .capacity-large {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .capacity-xlarge {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }

        /* Utilization bar */
        .utilization-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.25rem;
        }

        .utilization-fill {
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 3px;
        }

        .utilization-low { background: #10b981; }
        .utilization-medium { background: #f59e0b; }
        .utilization-high { background: #ef4444; }

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
                        <h1 class="page-title">🏫 Classroom Management</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="create.php" class="btn-action btn-primary">
                            ➕ Add Classroom
                        </a>
                        <a href="utilization.php" class="btn-action btn-outline">
                            📊 Utilization Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon total">🏫</div>
                <div class="stat-number"><?= $classroomStats['total_classrooms'] ?? 0 ?></div>
                <div class="stat-label">Total Classrooms</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon active">✅</div>
                <div class="stat-number"><?= $classroomStats['active_classrooms'] ?? 0 ?></div>
                <div class="stat-label">Active Rooms</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon available">🟢</div>
                <div class="stat-number"><?= $classroomStats['available_classrooms'] ?? 0 ?></div>
                <div class="stat-label">Available Now</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon maintenance">🔧</div>
                <div class="stat-number"><?= $classroomStats['maintenance_classrooms'] ?? 0 ?></div>
                <div class="stat-label">Under Maintenance</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon lecture">🎓</div>
                <div class="stat-number"><?= $classroomStats['lecture_rooms'] ?? 0 ?></div>
                <div class="stat-label">Lecture Halls</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon lab">🔬</div>
                <div class="stat-number"><?= $classroomStats['lab_rooms'] ?? 0 ?></div>
                <div class="stat-label">Laboratories</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon buildings">🏢</div>
                <div class="stat-number"><?= $classroomStats['unique_buildings'] ?? 0 ?></div>
                <div class="stat-label">Buildings</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon utilization">📈</div>
                <div class="stat-number"><?= $classroomStats['utilization_rate'] ?? 0 ?>%</div>
                <div class="stat-label">Utilization Rate</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters glass-card">
            <div class="d-flex justify-content-between align-items-center gap-3 filters-row">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search classrooms..." id="searchInput">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19S2 15.194 2 10.5S5.806 2 10.5 2S19 5.806 19 10.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                
                <div class="filter-controls">
                    <div class="filter-group">
                        <label class="filter-label">Building</label>
                        <select class="filter-select" id="buildingFilter" onchange="handleBuildingFilter()">
                            <option value="">All Buildings</option>
                            <?php foreach ($buildings as $building): ?>
                                <option value="<?= htmlspecialchars($building['building']) ?>" <?= $selectedBuilding == $building['building'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($building['building']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Type</label>
                        <select class="filter-select" id="typeFilter" onchange="handleTypeFilter()">
                            <option value="">All Types</option>
                            <option value="lecture" <?= $selectedType == 'lecture' ? 'selected' : '' ?>>Lecture Hall</option>
                            <option value="lab" <?= $selectedType == 'lab' ? 'selected' : '' ?>>Laboratory</option>
                            <option value="seminar" <?= $selectedType == 'seminar' ? 'selected' : '' ?>>Seminar Room</option>
                            <option value="auditorium" <?= $selectedType == 'auditorium' ? 'selected' : '' ?>>Auditorium</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="filter-select" id="statusFilter" onchange="handleStatusFilter()">
                            <option value="">All Status</option>
                            <option value="available" <?= $selectedStatus == 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="maintenance" <?= $selectedStatus == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="reserved" <?= $selectedStatus == 'reserved' ? 'selected' : '' ?>>Reserved</option>
                            <option value="closed" <?= $selectedStatus == 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Department</label>
                        <select class="filter-select" id="departmentFilter" onchange="handleDepartmentFilter()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>" <?= $selectedDepartment == $dept['department_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name']) ?>
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

        <!-- Bulk Actions (shown when classrooms are selected) -->
        <form method="post" id="bulkActionForm">
            <div class="bulk-actions" id="bulkActions">
                <div class="d-flex align-items-center gap-3">
                    <span><strong id="selectedCount">0</strong> classrooms selected</span>
                    
                    <select name="bulk_action_type" class="filter-select">
                        <option value="">Choose Action</option>
                        <option value="activate">Activate Selected</option>
                        <option value="deactivate">Deactivate Selected</option>
                        <option value="available">Set Available</option>
                        <option value="maintenance">Set Maintenance</option>
                        <option value="reserved">Set Reserved</option>
                        <option value="closed">Set Closed</option>
                    </select>
                    
                    <button type="submit" name="action" value="bulk_action" class="btn-action btn-primary">
                        Apply Action
                    </button>
                    
                    <button type="button" class="btn-action btn-outline" onclick="clearSelection()">
                        Clear Selection
                    </button>
                </div>
            </div>

            <!-- Classrooms Table (Desktop) -->
            <?php if (!empty($classrooms)): ?>
                <div class="classrooms-container glass-card">
                    <div class="table-responsive-custom">
                        <table class="classrooms-table table table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                    <th>Classroom</th>
                                    <th>Type & Capacity</th>
                                    <th>Building & Location</th>
                                    <th>Status</th>
                                    <th>Department</th>
                                    <th>Utilization</th>
                                    <th>Equipment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="classroomsTableBody">
                                <?php foreach ($classrooms as $classroom): ?>
                                    <tr class="classroom-row" id="classroom-<?= $classroom['classroom_id'] ?>"
                                        data-room="<?= strtolower($classroom['room_number']) ?>"
                                        data-building="<?= strtolower($classroom['building']) ?>"
                                        data-type="<?= $classroom['type'] ?>"
                                        data-status="<?= $classroom['status'] ?>"
                                        data-department="<?= $classroom['department_name'] ?? '' ?>">
                                        <td>
                                            <input type="checkbox" name="selected_classrooms[]" value="<?= $classroom['classroom_id'] ?>" 
                                                   class="classroom-checkbox" onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <div class="classroom-info">
                                                <div class="classroom-icon <?= $classroom['type'] ?>">
                                                    <?php 
                                                    $typeIcons = [
                                                        'lecture' => '🎓',
                                                        'lab' => '🔬',
                                                        'seminar' => '💼',
                                                        'auditorium' => '🎭'
                                                    ];
                                                    echo $typeIcons[$classroom['type']] ?? '🏫';
                                                    ?>
                                                </div>
                                                <div class="classroom-details">
                                                    <h6><?= htmlspecialchars($classroom['room_number']) ?></h6>
                                                    <p class="classroom-meta">
                                                        <?php if ($classroom['floor']): ?>
                                                            Floor <?= $classroom['floor'] ?>
                                                        <?php endif; ?>
                                                        <?php if ($classroom['is_shared']): ?>
                                                            • Shared
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-type-<?= $classroom['type'] ?>">
                                                <?= ucfirst($classroom['type']) ?>
                                            </span>
                                            <br>
                                            <div class="mt-1">
                                                <?php 
                                                $capacity = $classroom['capacity'];
                                                if ($capacity <= 30) {
                                                    $capacityClass = 'capacity-small';
                                                    $capacityIcon = '👥';
                                                } elseif ($capacity <= 60) {
                                                    $capacityClass = 'capacity-medium';
                                                    $capacityIcon = '👥';
                                                } elseif ($capacity <= 100) {
                                                    $capacityClass = 'capacity-large';
                                                    $capacityIcon = '👥';
                                                } else {
                                                    $capacityClass = 'capacity-xlarge';
                                                    $capacityIcon = '👥';
                                                }
                                                ?>
                                                <span class="capacity-indicator <?= $capacityClass ?>">
                                                    <?= $capacityIcon ?> <?= $capacity ?> seats
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($classroom['building']) ?></strong>
                                            <?php if ($classroom['floor']): ?>
                                                <br><small style="color: var(--text-secondary);">Floor <?= $classroom['floor'] ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?= $classroom['status'] ?>">
                                                <?= ucfirst($classroom['status']) ?>
                                            </span>
                                            <br>
                                            <span class="badge <?= $classroom['is_active'] ? 'badge-active' : 'badge-inactive' ?> mt-1">
                                                <?= $classroom['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($classroom['department_name']): ?>
                                                <strong><?= htmlspecialchars($classroom['department_name']) ?></strong>
                                                <br><small style="color: var(--text-secondary);"><?= htmlspecialchars($classroom['department_code'] ?? '') ?></small>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">Shared Resource</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $bookings = $classroom['current_bookings'] ?? 0;
                                            $maxSlots = 20; // Approximate max slots per week
                                            $utilizationPercent = $maxSlots > 0 ? min(($bookings / $maxSlots) * 100, 100) : 0;
                                            ?>
                                            <div style="font-size: 0.875rem; color: var(--text-primary);">
                                                <strong><?= $bookings ?></strong> bookings
                                            </div>
                                            <div class="utilization-bar">
                                                <div class="utilization-fill <?= $utilizationPercent > 70 ? 'utilization-high' : ($utilizationPercent > 40 ? 'utilization-medium' : 'utilization-low') ?>" 
                                                     style="width: <?= $utilizationPercent ?>%"></div>
                                            </div>
                                            <small style="color: var(--text-secondary);"><?= round($utilizationPercent) ?>% utilized</small>
                                        </td>
                                        <td>
                                            <?php if (!empty($classroom['equipment'])): ?>
                                                <div style="font-size: 0.8125rem; color: var(--text-secondary); max-width: 200px;">
                                                    <?= htmlspecialchars(substr($classroom['equipment'], 0, 50)) ?>
                                                    <?= strlen($classroom['equipment']) > 50 ? '...' : '' ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">No equipment listed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <?php if ($classroom['status'] === 'maintenance'): ?>
                                                    <a href="set-status.php?status=available&id=<?= $classroom['classroom_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                       class="btn-action btn-success btn-sm" 
                                                       onclick="return confirm('Mark this classroom as available?')" title="Set Available">
                                                        ✅
                                                    </a>
                                                <?php else: ?>
                                                    <a href="set-status.php?status=maintenance&id=<?= $classroom['classroom_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                       class="btn-action btn-warning btn-sm" 
                                                       onclick="return confirm('Mark this classroom for maintenance?')" title="Set Maintenance">
                                                        🔧
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($classroom['is_active']): ?>
                                                    <a href="activate-deactivate.php?action=deactivate&id=<?= $classroom['classroom_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                       class="btn-action btn-outline btn-sm"
                                                       onclick="return confirm('Deactivate this classroom?')" title="Deactivate">
                                                        ⏸️
                                                    </a>
                                                <?php else: ?>
                                                    <a href="activate-deactivate.php?action=activate&id=<?= $classroom['classroom_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                       class="btn-action btn-success btn-sm"
                                                       onclick="return confirm('Activate this classroom?')" title="Activate">
                                                        ▶️
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn-action btn-primary btn-sm" 
                                                        onclick="viewClassroomDetails(<?= $classroom['classroom_id'] ?>)" title="View Details">
                                                    👁️
                                                </button>
                                                
                                                <a href="edit.php?id=<?= $classroom['classroom_id'] ?>" 
                                                   class="btn-action btn-outline btn-sm" title="Edit Classroom">
                                                    ✏️
                                                </a>
                                                
                                                <a href="delete.php?id=<?= $classroom['classroom_id'] ?>" 
                                                   class="btn-action btn-danger btn-sm" 
                                                   onclick="return confirm('Are you sure you want to delete this classroom? This action cannot be undone.')" 
                                                   title="Delete Classroom">
                                                    🗑️
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
        </form>

        <!-- Classrooms Cards (Mobile) -->
        <div id="classroomsCards">
            <?php foreach ($classrooms as $classroom): ?>
                <div class="classroom-card" id="classroom-card-<?= $classroom['classroom_id'] ?>"
                     data-room="<?= strtolower($classroom['room_number']) ?>"
                     data-building="<?= strtolower($classroom['building']) ?>"
                     data-type="<?= $classroom['type'] ?>"
                     data-status="<?= $classroom['status'] ?>"
                     data-department="<?= $classroom['department_name'] ?? '' ?>">
                    
                    <div class="classroom-card-header">
                        <div class="classroom-card-avatar <?= $classroom['type'] ?>">
                            <?php 
                            $typeIcons = [
                                'lecture' => '🎓',
                                'lab' => '🔬',
                                'seminar' => '💼',
                                'auditorium' => '🎭'
                            ];
                            echo $typeIcons[$classroom['type']] ?? '🏫';
                            ?>
                        </div>
                        <div class="classroom-card-info">
                            <h6><?= htmlspecialchars($classroom['room_number']) ?></h6>
                            <div class="classroom-card-meta"><?= htmlspecialchars($classroom['building']) ?></div>
                            <div class="classroom-card-meta">
                                <span class="badge badge-type-<?= $classroom['type'] ?>"><?= ucfirst($classroom['type']) ?></span>
                                <span class="badge badge-status-<?= $classroom['status'] ?>"><?= ucfirst($classroom['status']) ?></span>
                                <span class="badge <?= $classroom['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $classroom['is_active'] ? 'Active' : 'Inactive' ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="classroom-card-details">
                        <div class="classroom-card-detail">
                            <strong>Capacity:</strong><br>
                            <span><?= $classroom['capacity'] ?> seats</span>
                        </div>
                        <div class="classroom-card-detail">
                            <strong>Building:</strong><br>
                            <span><?= htmlspecialchars($classroom['building']) ?></span>
                        </div>
                        <?php if ($classroom['floor']): ?>
                        <div class="classroom-card-detail">
                            <strong>Floor:</strong><br>
                            <span><?= $classroom['floor'] ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="classroom-card-detail">
                            <strong>Department:</strong><br>
                            <span><?= htmlspecialchars($classroom['department_name'] ?? 'Shared Resource') ?></span>
                        </div>
                        <div class="classroom-card-detail">
                            <strong>Current Bookings:</strong><br>
                            <span><?= $classroom['current_bookings'] ?? 0 ?> active</span>
                        </div>
                        <?php if (!empty($classroom['equipment'])): ?>
                        <div class="classroom-card-detail">
                            <strong>Equipment:</strong><br>
                            <span><?= htmlspecialchars(substr($classroom['equipment'], 0, 100)) ?><?= strlen($classroom['equipment']) > 100 ? '...' : '' ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="classroom-card-actions">
                        <button type="button" class="btn-action btn-primary" onclick="viewClassroomDetails(<?= $classroom['classroom_id'] ?>)">
                            👁️ View Details
                        </button>
                        
                        <a href="edit.php?id=<?= $classroom['classroom_id'] ?>" class="btn-action btn-outline">
                            ✏️ Edit Classroom
                        </a>
                        
                        <?php if ($classroom['status'] === 'maintenance'): ?>
                            <a href="set-status.php?status=available&id=<?= $classroom['classroom_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn-action btn-success"
                               onclick="return confirm('Mark this classroom as available?')">
                                ✅ Set Available
                            </a>
                        <?php else: ?>
                            <a href="set-status.php?status=maintenance&id=<?= $classroom['classroom_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn-action btn-warning"
                               onclick="return confirm('Mark this classroom for maintenance?')">
                                🔧 Set Maintenance
                            </a>
                        <?php endif; ?>
                        <?php if ($classroom['is_active']): ?>
                            <a href="activate-deactivate.php?action=deactivate&id=<?= $classroom['classroom_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn-action btn-outline"
                               onclick="return confirm('Deactivate this classroom?')">
                                ⏸️ Deactivate
                            </a>
                        <?php else: ?>
                            <a href="activate-deactivate.php?action=activate&id=<?= $classroom['classroom_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn-action btn-success"
                               onclick="return confirm('Activate this classroom?')">
                                ▶️ Activate
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($classroom['is_active']): ?>
                            <a href="?action=deactivate&classroom_id=<?= $classroom['classroom_id'] ?>" 
                               class="btn-action btn-outline"
                               onclick="return confirm('Deactivate this classroom?')">
                                ⏸️ Deactivate
                            </a>
                        <?php else: ?>
                            <a href="?action=activate&classroom_id=<?= $classroom['classroom_id'] ?>" 
                               class="btn-action btn-success"
                               onclick="return confirm('Activate this classroom?')">
                                ▶️ Activate
                            </a>
                        <?php endif; ?>
                        
                        <a href="delete.php?id=<?= $classroom['classroom_id'] ?>" 
                           class="btn-action btn-danger"
                           onclick="return confirm('Are you sure you want to delete this classroom? This action cannot be undone.')">
                            🗑️ Delete Classroom
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
            <div class="empty-state glass-card">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 21L21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M5 21V7L13 2L21 7V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M9 9V13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M13 9V13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M17 9V13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <h3>No Classrooms Found</h3>
                <p>
                    <?php if (!empty($selectedBuilding) || !empty($selectedType) || !empty($selectedStatus)): ?>
                        No classrooms match your current filter criteria. Try adjusting your filters or clear them to see all classrooms.
                    <?php else: ?>
                        No classrooms are currently registered in the system. Add your first classroom to get started with room management.
                    <?php endif; ?>
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="create.php" class="btn-action btn-primary">
                        ➕ Add First Classroom
                    </a>
                    <?php if (!empty($selectedBuilding) || !empty($selectedType) || !empty($selectedStatus)): ?>
                        <button onclick="clearFilters()" class="btn-action btn-outline">
                            🔄 Clear Filters
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Success/Error Messages -->
        <?php if (isset($error_message) && !empty($error_message)): ?>
            <div class="alert alert-danger glass-card mt-3" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message) && !empty($success_message)): ?>
            <div class="alert alert-success glass-card mt-3" role="alert">
                <strong>Success:</strong> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Classroom Details Modal -->
    <div class="modal fade" id="classroomDetailsModal" tabindex="-1" aria-labelledby="classroomDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="classroomDetailsModalLabel">Classroom Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="classroomDetailsContent">
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

            // Setup theme observer
            setupThemeObserver();
        });

        function setupThemeObserver() {
            // Watch for changes to the table content
            const tableBody = document.querySelector('.classrooms-table tbody');
            if (tableBody) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            // New content was added, reapply theme
                            setTimeout(() => {
                                applyCurrentTheme();
                            }, 10);
                        }
                    });
                });

                observer.observe(tableBody, {
                    childList: true,
                    subtree: true
                });
            }

            // Watch for theme attribute changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                        setTimeout(() => {
                            applyCurrentTheme();
                        }, 10);
                    }
                });
            });

            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-theme']
            });
        }


        // Sidebar JS handlers removed to prevent unintended expansion on single link clicks.

        function initializeSearchAndFilters() {
            const searchInput = document.getElementById('searchInput');
            const buildingFilter = document.getElementById('buildingFilter');
            const typeFilter = document.getElementById('typeFilter');
            const statusFilter = document.getElementById('statusFilter');
            const departmentFilter = document.getElementById('departmentFilter');

            let hasScrolledToResults = false;

            function scrollToResultsOnce() {
                if (hasScrolledToResults) return;
                const container = document.querySelector('.classrooms-container') || document.getElementById('classroomsCards');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    hasScrolledToResults = true;
                }
            }

            function filterClassrooms() {
                const searchTerm = (searchInput?.value || '').toLowerCase();
                const buildingVal = (buildingFilter?.value || '').toLowerCase();
                const typeVal = typeFilter?.value || '';
                const statusVal = statusFilter?.value || '';
                const departmentVal = (departmentFilter?.value || '').toLowerCase();

                const tableRows = document.querySelectorAll('.classroom-row');
                const classroomCards = document.querySelectorAll('.classroom-card');

                let visibleCount = 0;

                function applyFilter(elements) {
                    elements.forEach(element => {
                        const room = element.dataset.room || '';
                        const building = element.dataset.building || '';
                        const type = element.dataset.type || '';
                        const status = element.dataset.status || '';
                        const department = (element.dataset.department || '').toLowerCase();

                        const matchesSearch = !searchTerm ||
                            room.includes(searchTerm) ||
                            building.includes(searchTerm) ||
                            type.includes(searchTerm);

                        const matchesBuilding = !buildingVal || building === buildingVal;
                        const matchesType = !typeVal || type === typeVal;
                        const matchesStatus = !statusVal || status === statusVal;
                        const matchesDepartment = !departmentVal || department === departmentVal;

                        const shouldShow = matchesSearch && matchesBuilding && matchesType && matchesStatus && matchesDepartment;

                        if (shouldShow) {
                            element.style.display = '';
                            visibleCount++;
                        } else {
                            element.style.display = 'none';
                        }
                    });
                }

                applyFilter(tableRows);
                applyFilter(classroomCards);

                if (visibleCount === 0 && (tableRows.length > 0 || classroomCards.length > 0)) {
                    showNoResultsMessage();
                } else {
                    hideNoResultsMessage();
                }

                // Hide top alerts when actively filtering/searching
                const isActivelyFiltering = (searchTerm.length > 0) || buildingVal || typeVal || statusVal || departmentVal;
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
                searchInput.addEventListener('input', () => { filterClassrooms(); scrollToResultsOnce(); });
            }
            if (buildingFilter) {
                buildingFilter.addEventListener('change', () => { filterClassrooms(); scrollToResultsOnce(); });
            }
            if (typeFilter) {
                typeFilter.addEventListener('change', () => { filterClassrooms(); scrollToResultsOnce(); });
            }
            if (statusFilter) {
                statusFilter.addEventListener('change', () => { filterClassrooms(); scrollToResultsOnce(); });
            }
            if (departmentFilter) {
                departmentFilter.addEventListener('change', () => { filterClassrooms(); scrollToResultsOnce(); });
            }

            // Initial run in case of preselected values
            filterClassrooms();
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
                <h3>No Classrooms Found</h3>
                <p>No classrooms match your current search criteria. Try adjusting your search terms.</p>
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
            const buildingFilter = document.getElementById('buildingFilter');
            const typeFilter = document.getElementById('typeFilter');
            const statusFilter = document.getElementById('statusFilter');
            const departmentFilter = document.getElementById('departmentFilter');
            if (searchInput) searchInput.value = '';
            if (buildingFilter) buildingFilter.value = '';
            if (typeFilter) typeFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            if (departmentFilter) departmentFilter.value = '';
            // Trigger a filter pass without reloading
            if (typeof initializeSearchAndFilters === 'function') {
                const evt = new Event('input');
                if (searchInput) searchInput.dispatchEvent(evt);
            }
        }

        // Legacy URL-based handlers kept as no-ops for compatibility
        function handleBuildingFilter() { /* handled by change listener */ }
        function handleTypeFilter() { /* handled by change listener */ }
        function handleStatusFilter() { /* handled by change listener */ }
        function handleDepartmentFilter() { /* handled by change listener */ }

        // Bulk Actions Management
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const classroomCheckboxes = document.querySelectorAll('.classroom-checkbox');
            
            classroomCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const selectedCheckboxes = document.querySelectorAll('.classroom-checkbox:checked');
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
                const allCheckboxes = document.querySelectorAll('.classroom-checkbox');
                selectAll.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < allCheckboxes.length;
                selectAll.checked = selectedCheckboxes.length === allCheckboxes.length && allCheckboxes.length > 0;
            }
        }

        function clearSelection() {
            const classroomCheckboxes = document.querySelectorAll('.classroom-checkbox');
            const selectAll = document.getElementById('selectAll');
            const actionSelect = document.querySelector('select[name="bulk_action_type"]');
            
            classroomCheckboxes.forEach(checkbox => {
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

        async function viewClassroomDetails(classroomId) {
            const modal = new bootstrap.Modal(document.getElementById('classroomDetailsModal'));
            const modalContent = document.getElementById('classroomDetailsContent');
            
            // Show loading state
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="loading-shimmer" style="height: 200px; border-radius: 8px; margin-bottom: 1rem;"></div>
                    <p>Loading classroom details...</p>
                </div>
            `;
            
            modal.show();

            try {
                const response = await fetch(`../../includes/api/classroom-details.php?id=${classroomId}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();

                if (data.success) {
                    modalContent.innerHTML = generateClassroomDetailsHTML(data.classroom);
                } else {
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>Error:</strong> ${data.message || 'Failed to load classroom details'}
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading classroom details:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Error:</strong> Failed to load classroom details. Please check your connection and try again.
                        <br><small>Technical details: ${error.message}</small>
                    </div>
                `;
            }
        }

        function generateClassroomDetailsHTML(classroom) {
            const typeIcons = {
                'lecture': '🎓',
                'lab': '🔬',
                'seminar': '💼',
                'auditorium': '🎭'
            };

            const statusColors = {
                'available': '#059669',
                'maintenance': '#d97706',
                'reserved': '#7c3aed',
                'closed': '#475569'
            };

            const typeIcon = typeIcons[classroom.type] || '🏫';

            // Parse facilities if it's JSON
            let facilitiesList = '';
            if (classroom.facilities) {
                try {
                    const facilities = JSON.parse(classroom.facilities);
                    if (Array.isArray(facilities)) {
                        facilitiesList = facilities.map(f => `<span class="badge badge-type-${classroom.type} me-1 mb-1">${f}</span>`).join('');
                    }
                } catch (e) {
                    facilitiesList = `<span class="text-muted">${classroom.facilities}</span>`;
                }
            }

            const utilizationPercent = Math.min((classroom.current_bookings / 20) * 100, 100);

            return `
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        <div class="classroom-icon ${classroom.type} mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2.5rem;">
                            ${typeIcon}
                        </div>
                        <h4>${classroom.room_number}</h4>
                        <p class="text-muted">${classroom.building}${classroom.floor ? ` • Floor ${classroom.floor}` : ''}</p>
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <span class="badge badge-type-${classroom.type}">
                                ${classroom.type.charAt(0).toUpperCase() + classroom.type.slice(1)}
                            </span>
                            <span class="badge" style="background: ${statusColors[classroom.status]}20; color: ${statusColors[classroom.status]};">
                                ${classroom.status.charAt(0).toUpperCase() + classroom.status.slice(1)}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <strong>Capacity:</strong><br>
                                <span style="color: var(--primary-color); font-size: 1.25rem; font-weight: 600;">
                                    👥 ${classroom.capacity} seats
                                </span>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <strong>Building:</strong><br>
                                ${classroom.building}
                                ${classroom.floor ? `<br><small class="text-muted">Floor ${classroom.floor}</small>` : ''}
                            </div>
                            ${classroom.department_name ? `
                            <div class="col-sm-6 mb-3">
                                <strong>Department:</strong><br>
                                ${classroom.department_name}
                                ${classroom.department_code ? `<br><small class="text-muted">${classroom.department_code}</small>` : ''}
                            </div>
                            ` : `
                            <div class="col-sm-6 mb-3">
                                <strong>Department:</strong><br>
                                <span class="text-muted">Shared Resource</span>
                            </div>
                            `}
                            <div class="col-sm-6 mb-3">
                                <strong>Status:</strong><br>
                                <span class="badge" style="background: ${statusColors[classroom.status]}20; color: ${statusColors[classroom.status]};">
                                    ${classroom.status.charAt(0).toUpperCase() + classroom.status.slice(1)}
                                </span>
                                <span class="badge ${classroom.is_active ? 'badge-active' : 'badge-inactive'} ms-1">
                                    ${classroom.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                            ${classroom.equipment ? `
                            <div class="col-12 mb-3">
                                <strong>Equipment:</strong><br>
                                <span style="color: var(--text-secondary);">${classroom.equipment}</span>
                            </div>
                            ` : ''}
                            ${facilitiesList ? `
                            <div class="col-12 mb-3">
                                <strong>Facilities:</strong><br>
                                ${facilitiesList}
                            </div>
                            ` : ''}
                            <div class="col-sm-6 mb-3">
                                <strong>Current Bookings:</strong><br>
                                <span style="color: var(--primary-color); font-weight: 600;">
                                    ${classroom.current_bookings || 0} active schedules
                                </span>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <strong>Utilization Rate:</strong><br>
                                <div class="utilization-bar mb-1">
                                    <div class="utilization-fill ${utilizationPercent > 70 ? 'utilization-high' : (utilizationPercent > 40 ? 'utilization-medium' : 'utilization-low')}" 
                                         style="width: ${utilizationPercent}%"></div>
                                </div>
                                <small>${Math.round(utilizationPercent)}% utilized</small>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <strong>Created:</strong><br>
                                ${new Date(classroom.created_at).toLocaleDateString()}
                            </div>
                            <div class="col-sm-6 mb-3">
                                <strong>Last Updated:</strong><br>
                                ${new Date(classroom.updated_at).toLocaleDateString()}
                            </div>
                        </div>

                        ${classroom.scheduled_subjects ? `
                        <div class="mt-3">
                            <strong>Current Schedule:</strong><br>
                            <div class="mt-2" style="max-height: 100px; overflow-y: auto;">
                                <small style="color: var(--text-secondary);">${classroom.scheduled_subjects}</small>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="edit.php?id=${classroom.classroom_id}" class="btn-action btn-primary me-2">
                        ✏️ Edit Classroom
                    </a>
                    <a href="utilization.php?classroom_id=${classroom.classroom_id}" class="btn-action btn-outline me-2">
                        📊 View Utilization
                    </a>
                    <a href="../timetables/index.php?classroom_id=${classroom.classroom_id}" class="btn-action btn-outline me-2">
                        📅 View Schedule
                    </a>
                    <a href="delete.php?id=${classroom.classroom_id}" class="btn-action btn-danger"
                       onclick="return confirm('Are you sure you want to delete this classroom? This action cannot be undone.')">
                        🗑️ Delete Classroom
                    </a>
                </div>
            `;
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
            const table = document.querySelector('.classrooms-table');
            const container = document.querySelector('.classrooms-container');
            
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
            const table = document.querySelector('.classrooms-table');
            const container = document.querySelector('.classrooms-container');
            
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

            const selectedClassrooms = document.querySelectorAll('.classroom-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const actionSelect = this.querySelector('select[name="bulk_action_type"]');
            const actionType = actionSelect.value;
            
            // Check if bulk actions are visible (if hidden, the form shouldn't submit)
            if (!bulkActions.classList.contains('show')) {
                e.preventDefault();
                alert('Please select classrooms first.');
                return;
            }
            
            if (selectedClassrooms.length === 0) {
                e.preventDefault();
                alert('Please select at least one classroom to perform this action.');
                return;
            }
            
            if (!actionType) {
                e.preventDefault();
                alert('Please select an action to perform.');
                // Focus the select element
                actionSelect.focus();
                return;
            }
            
            const confirmMessage = `Are you sure you want to ${actionType} ${selectedClassrooms.length} selected classroom(s)?`;
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });

        // Auto-hide success alerts after 5 seconds
        (function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 600);
                }, 5000);
            });
        })();

        // Scroll to updated classroom and highlight it
        (function() {
            const params = new URLSearchParams(window.location.search);
            const targetId = params.get('updated_id');
            const alertEl = document.getElementById('topSuccessAlert');

            const scrollAndHighlight = (id) => {
                const row = document.getElementById('classroom-' + id);
                const card = document.getElementById('classroom-card-' + id);
                const target = row || card;
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    target.classList.add('row-highlight');
                    setTimeout(() => target.classList.remove('row-highlight'), 2600);
                }
            };

            if (alertEl && targetId) {
                // Show alert first, then scroll
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setTimeout(() => scrollAndHighlight(targetId), 1200);
            } else if (targetId) {
                scrollAndHighlight(targetId);
            }
        })();
    </script>
</body>
</html>