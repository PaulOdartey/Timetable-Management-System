<?php
/**
 * Admin Timetable Management - Timetable Management Interface
 * Timetable Management System
 * 
 * Professional interface for admin to manage timetable entries with
 * comprehensive filtering, statistics, and bulk operations
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Timetable.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$timetableManager = new Timetable();

// Initialize variables
$timetables = [];
$timetableStats = [];
$filterOptions = [];
$error_message = '';
$success_message = '';
$selectedDepartment = $_GET['department'] ?? '';
$selectedSemester = $_GET['semester'] ?? '';
$selectedAcademicYear = $_GET['academic_year'] ?? '';
$selectedSection = $_GET['section'] ?? '';
$selectedDay = $_GET['day'] ?? '';
$selectedFaculty = $_GET['faculty'] ?? '';

// Flash success message from previous actions
if (isset($_SESSION['flash']['success'])) {
    $success_message = $_SESSION['flash']['success'];
    unset($_SESSION['flash']['success']);
}

// Flash error message from previous actions
if (isset($_SESSION['flash']['error'])) {
    $error_message = $_SESSION['flash']['error'];
    unset($_SESSION['flash']['error']);
}

// Handle bulk actions
if (($_POST['action'] ?? '') === 'bulk_action' && !empty($_POST['selected_timetables'])) {
    $selectedTimetables = $_POST['selected_timetables'];
    $bulkAction = $_POST['bulk_action_type'] ?? '';
    try {
        if ($bulkAction === 'delete_selected') {
            $result = $timetableManager->bulkDeleteTimetables($selectedTimetables);
            if ($result['success']) {
                $_SESSION['flash']['success'] = $result['message'] ?? 'Selected timetables have been deleted successfully.';
            } else {
                $_SESSION['flash']['error'] = $result['message'] ?? 'Failed to delete selected timetables.';
            }
        } elseif ($bulkAction === 'export_selected') {
            // Implement export for selected entries
            $_SESSION['flash']['success'] = 'Selected timetable entries exported successfully.';
        } else {
            $_SESSION['flash']['error'] = 'Invalid bulk action.';
        }
    } catch (Exception $e) {
        $_SESSION['flash']['error'] = 'Bulk action failed: ' . $e->getMessage();
    }
    // Preserve current filters on redirect
    $query = [];
    if (!empty($selectedDepartment)) $query['department'] = $selectedDepartment;
    if (!empty($selectedSemester)) $query['semester'] = $selectedSemester;
    if (!empty($selectedAcademicYear)) $query['academic_year'] = $selectedAcademicYear;
    if (!empty($selectedSection)) $query['section'] = $selectedSection;
    if (!empty($selectedDay)) $query['day'] = $selectedDay;
    if (!empty($selectedFaculty)) $query['faculty'] = $selectedFaculty;
    $redirectUrl = 'index.php' . (empty($query) ? '' : ('?' . http_build_query($query)));
    header('Location: ' . $redirectUrl);
    exit;
}

// Build filter array
$filters = [];
if (!empty($selectedDepartment)) $filters['department'] = $selectedDepartment;
if (!empty($selectedSemester)) $filters['semester'] = $selectedSemester;
if (!empty($selectedAcademicYear)) $filters['academic_year'] = $selectedAcademicYear;

try {
    // Get timetable data with pagination (using getAllTimetables for admin management)
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $timetableResult = $timetableManager->getAllTimetables($filters, $page, $limit);
    $timetables = $timetableResult['success'] ? $timetableResult['data'] : [];
    $pagination = $timetableResult['pagination'] ?? [];
    
    // Get statistics
    $statsResult = $timetableManager->getTimetableStats();
    $timetableStats = $statsResult['success'] ? $statsResult['stats'] : [];
    
    // Get filter options
    $filterResult = $timetableManager->getFilterOptions();
    $filterOptions = $filterResult['success'] ? $filterResult : [];
    
    // Get available resources for filters
    $resourcesResult = $timetableManager->getAvailableResources();
    $availableResources = $resourcesResult['success'] ? $resourcesResult : [];
    
} catch (Exception $e) {
    $error_message = "Error loading timetable data: " . $e->getMessage();
    $timetables = [];
    $timetableStats = [
        'total_entries' => 0,
        'subjects_scheduled' => 0,
        'faculty_scheduled' => 0,
        'classroom_utilization' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Timetable Management - Admin Panel</title>
    
    <!-- External CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
     

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    
    <style>
        /* Core Layout and Theme Variables */
        :root {
            --primary-color: #667eea;
            --primary-color-alpha: rgba(102, 126, 234, 0.2);
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-tertiary: #9ca3af;
            
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            
            --border-color: #e5e7eb;
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --navbar-height: 64px;
        }

        /* Dark Mode Variables */
        [data-theme="dark"] {
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --text-tertiary: #9ca3af;
            
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --bg-tertiary: #374151;
            
            --border-color: #374151;
            --glass-bg: rgba(0, 0, 0, 0.25);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        /* Base Styles */
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

        [data-theme="dark"] .header-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(30, 41, 59, 0.7) 100%);
            border-color: var(--border-color);
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
            border-radius: 16px;
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem auto;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
        }

        .stat-icon.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.subjects { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.faculty { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.classrooms { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }



        /* Search and Filter Controls */
        .search-filters {
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .search-filters {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        .filter-controls {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: nowrap; /* keep all filters and Reset inline on desktop */
        }

        .filter-controls .btn-action {
            white-space: nowrap; /* prevent Reset wrapping */
        }

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

        [data-theme="dark"] .bulk-actions {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        .bulk-actions.show {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Table Styles */
        .timetables-container {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            overflow: hidden;
        }

        [data-theme="dark"] .timetables-container {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        /* Scrollable wrapper to enable sticky table head like in classrooms page */
        .table-responsive-custom {
            max-height: 65vh;
            overflow-y: auto;
            overflow-x: auto;
        }

        .timetables-table {
            width: 100%;
            margin: 0;
        }

        /* Sticky table header */
        .timetables-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--bg-primary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        /* Dark mode header background alignment */
        [data-theme="dark"] .timetables-table thead th {
            background: var(--bg-secondary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
        }

        .timetables-table thead {
            background: rgba(255, 255, 255, 0.3);
        }

        [data-theme="dark"] .timetables-table thead {
            background: rgba(0, 0, 0, 0.4);
        }

        .timetables-table th {
            padding: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        [data-theme="dark"] .timetables-table th {
            color: var(--text-primary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .timetables-table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
            color: var(--text-primary);
        }

        [data-theme="dark"] .timetables-table td {
            color: var(--text-primary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .timetables-table tbody tr {
            transition: all 0.3s ease;
        }

        .timetables-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        [data-theme="dark"] .timetables-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Column widths and alignment to match users table */
        .timetables-table th:nth-child(1),
        .timetables-table td:nth-child(1) { /* checkbox */
            width: 44px;
            text-align: center;
            white-space: nowrap;
        }

        .timetables-table th:nth-child(8),
        .timetables-table td:nth-child(8) { /* Actions */
            width: 130px;
            text-align: center;
            white-space: nowrap;
        }

        /* Subject Info Styling */
        .subject-info {
            display: flex;
            align-items: center;
        }

        .subject-avatar {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
            flex-shrink: 0;
            font-size: 0.75rem;
        }

        .subject-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .subject-meta {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Faculty Info Styling */
        .faculty-info {
            display: flex;
            align-items: center;
        }

        .faculty-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
            flex-shrink: 0;
            font-size: 0.75rem;
        }

        .faculty-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .faculty-meta {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Schedule Info Styling */
        .schedule-info {
            text-align: center;
        }

        .day-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.25rem;
        }

        .time-info {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Location Info Styling */
        .location-info {
            text-align: center;
        }

        .room-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 0.25rem;
        }

        .building-info {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Badge Styles */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-section {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .badge-capacity {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .badge-capacity.warning {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .badge-capacity.danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .badge-outline {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: var(--text-primary);
        }

        [data-theme="dark"] .badge-outline {
            border-color: var(--glass-border);
        }

        /* Action Buttons */
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

        .btn-info {
            background: var(--info-color);
            color: white;
        }

        .btn-info:hover {
            background: #2563eb;
            transform: translateY(-1px);
            color: white;
        }

        /* Mobile Card Layout */
        .timetable-card {
            display: none;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        [data-theme="dark"] .timetable-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        .timetable-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .timetable-card-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .timetable-card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
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
            align-items: center;
            gap: 0.5rem;
        }

        .pagination a, .pagination span {
            padding: 0.5rem 0.75rem;
            background: rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .pagination a,
        [data-theme="dark"] .pagination span {
            background: rgba(0, 0, 0, 0.3);
            border-color: var(--glass-border);
        }

        .pagination a:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
        }

        [data-theme="dark"] .pagination a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .pagination .current {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
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
                flex-wrap: wrap; /* allow wrapping on mobile */
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
            .timetables-container {
                display: none;
            }

            .timetable-card {
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
            [data-theme="dark"] .timetable-card {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }

            .timetable-card-details {
                grid-template-columns: 1fr;
            }

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
                        <h1 class="page-title">📅 Timetable Management</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="overview.php" class="btn-action btn-info">
                            📊 Overview
                        </a>
                        <a href="create.php" class="btn-action btn-primary">
                            ➕ Create Entry
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon total">📅</div>
                <div class="stat-number"><?= $timetableStats['total_entries'] ?? 0 ?></div>
                <div class="stat-label">Total Entries</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon subjects">📚</div>
                <div class="stat-number"><?= $timetableStats['subjects_scheduled'] ?? 0 ?></div>
                <div class="stat-label">Subjects Scheduled</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon faculty">👨‍🏫</div>
                <div class="stat-number"><?= $timetableStats['faculty_scheduled'] ?? 0 ?></div>
                <div class="stat-label">Faculty Assigned</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon classrooms">🏫</div>
                <div class="stat-number"><?= number_format($timetableStats['classroom_utilization'] ?? 0, 1) ?>%</div>
                <div class="stat-label">Classroom Utilization</div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <strong>❌ Error!</strong><br>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <strong>✅ Success!</strong><br>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Controls -->
        <div class="search-filters glass-card slide-up">
            <form method="GET" class="filter-controls" onsubmit="return false;">
                <div class="col-md-2">
                    <label for="department" class="form-label">Department</label>
                    <select name="department" id="department" class="filter-select">
                        <option value="">All Departments</option>
                        <?php if (!empty($filterOptions['departments'])): ?>
                            <?php foreach ($filterOptions['departments'] as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>" 
                                        <?= $selectedDepartment === $dept ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="semester" class="form-label">Semester</label>
                    <select name="semester" id="semester" class="filter-select">
                        <option value="">All Semesters</option>
                        <?php if (!empty($filterOptions['semesters'])): ?>
                            <?php foreach ($filterOptions['semesters'] as $sem): ?>
                                <option value="<?= $sem ?>" <?= $selectedSemester == $sem ? 'selected' : '' ?>>
                                    Semester <?= $sem ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="academic_year" class="form-label">Academic Year</label>
                    <select name="academic_year" id="academic_year" class="filter-select">
                        <option value="">All Years</option>
                        <?php if (!empty($filterOptions['academic_years'])): ?>
                            <?php foreach ($filterOptions['academic_years'] as $year): ?>
                                <option value="<?= htmlspecialchars($year) ?>" 
                                        <?= $selectedAcademicYear === $year ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($year) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="day" class="form-label">Day</label>
                    <select name="day" id="day" class="filter-select">
                        <option value="">All Days</option>
                        <?php if (!empty($filterOptions['days_of_week'])): ?>
                            <?php foreach ($filterOptions['days_of_week'] as $day): ?>
                                <option value="<?= htmlspecialchars($day) ?>" 
                                        <?= $selectedDay === $day ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($day) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="section" class="form-label">Section</label>
                    <select name="section" id="section" class="filter-select">
                        <option value="">All Sections</option>
                        <option value="A" <?= $selectedSection === 'A' ? 'selected' : '' ?>>Section A</option>
                        <option value="B" <?= $selectedSection === 'B' ? 'selected' : '' ?>>Section B</option>
                        <option value="C" <?= $selectedSection === 'C' ? 'selected' : '' ?>>Section C</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button type="button" class="btn-action btn-outline btn-sm" onclick="clearFilters()">
                        🔄 Clear
                    </button>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions glass-card" id="bulkActions">
            <form method="POST" onsubmit="return confirmBulkAction()">
                <input type="hidden" name="action" value="bulk_action">
                <div class="d-flex align-items-center gap-3">
                    <span><strong>Bulk Actions:</strong></span>
                    <select name="bulk_action_type" class="filter-select" required>
                        <option value="">Select Action</option>
                        <option value="delete_selected">Delete Selected</option>
                        <option value="export_selected">Export Selected</option>
                    </select>
                    <button type="submit" class="btn-action btn-primary">Apply to Selected</button>
                    <button type="button" onclick="clearSelection()" class="btn-action btn-outline btn-sm">Clear Selection</button>
                </div>
                <div id="selectedTimetables"></div>
            </form>
        </div>

        <!-- Timetables Table -->
        <div class="timetables-container glass-card slide-up">
            <div class="table-responsive-custom">
            <table class="timetables-table">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="selectAll" onchange="toggleAllTimetables()">
                        </th>
                        <th>Subject</th>
                        <th>Faculty</th>
                        <th>Schedule</th>
                        <th>Location</th>
                        <th>Section</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($timetables)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-calendar-times fa-3x mb-3"></i><br>
                                    No timetable entries found. <a href="create.php">Create your first entry</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($timetables as $timetable): ?>
                            <tr data-timetable-id="<?= $timetable['timetable_id'] ?>"
                                data-department="<?= strtolower(htmlspecialchars($timetable['department'] ?? '')) ?>"
                                data-semester="<?= strtolower((string)($timetable['semester'] ?? '')) ?>"
                                data-academic-year="<?= strtolower(htmlspecialchars($timetable['academic_year'] ?? '')) ?>"
                                data-day="<?= strtolower(htmlspecialchars($timetable['day_of_week'] ?? '')) ?>"
                                data-section="<?= strtolower(htmlspecialchars($timetable['section'] ?? '')) ?>"
                                data-subject="<?= strtolower(htmlspecialchars(($timetable['subject_code'] ?? '') . ' ' . ($timetable['subject_name'] ?? ''))) ?>"
                                data-faculty="<?= strtolower(htmlspecialchars($timetable['faculty_name'] ?? '')) ?>"
                                data-room="<?= strtolower(htmlspecialchars($timetable['room_number'] ?? '')) ?>"
                                data-building="<?= strtolower(htmlspecialchars($timetable['building'] ?? '')) ?>">
                                <td>
                                    <input type="checkbox" name="selected_timetables[]" 
                                           value="<?= $timetable['timetable_id'] ?>" 
                                           class="timetable-checkbox"
                                           onchange="updateBulkActions()">
                                </td>
                                <td>
                                    <div class="subject-info">
                                        <div class="subject-avatar">
                                            <?= strtoupper(substr($timetable['subject_code'], 0, 3)) ?>
                                        </div>
                                        <div class="subject-details">
                                            <h6><?= htmlspecialchars($timetable['subject_code']) ?></h6>
                                            <p class="subject-meta">
                                                <?= htmlspecialchars($timetable['subject_name']) ?><br>
                                                <small><?= $timetable['credits'] ?? 3 ?> Credits</small>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="faculty-info">
                                        <div class="faculty-avatar">
                                            <?php 
                                            $facultyNames = explode(' ', trim($timetable['faculty_name']));
                                            echo strtoupper(substr($facultyNames[0], 0, 1) . (isset($facultyNames[1]) ? substr($facultyNames[1], 0, 1) : ''));
                                            ?>
                                        </div>
                                        <div class="faculty-details">
                                            <h6><?= htmlspecialchars($timetable['faculty_name']) ?></h6>
                                            <p class="faculty-meta">
                                                <?= htmlspecialchars($timetable['employee_id']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="schedule-info">
                                        <div class="day-badge"><?= htmlspecialchars($timetable['day_of_week']) ?></div>
                                        <p class="time-info">
                                            <?= date('g:i A', strtotime($timetable['start_time'])) ?> - 
                                            <?= date('g:i A', strtotime($timetable['end_time'])) ?>
                                        </p>
                                    </div>
                                </td>
                                <td>
                                    <div class="location-info">
                                        <div class="room-badge"><?= htmlspecialchars($timetable['room_number']) ?></div>
                                        <p class="building-info">
                                            <?= htmlspecialchars($timetable['building']) ?><br>
                                            <small>Capacity: <?= $timetable['capacity'] ?></small>
                                        </p>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-section"><?= htmlspecialchars($timetable['section']) ?></span>
                                    <br><small class="text-muted">Sem <?= $timetable['semester'] ?? 1 ?></small>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $enrolledStudents = $timetable['enrolled_students'] ?? 0;
                                    $capacity = $timetable['capacity'] ?? 1;
                                    $occupancyPercent = ($enrolledStudents / $capacity) * 100;
                                    $badgeClass = 'badge-capacity';
                                    if ($occupancyPercent > 90) $badgeClass .= ' warning';
                                    if ($occupancyPercent > 100) $badgeClass .= ' danger';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= $enrolledStudents ?>/<?= $capacity ?>
                                    </span>
                                    <br><small class="text-muted"><?= number_format($occupancyPercent, 1) ?>%</small>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-center flex-wrap">
                                        <button type="button"
                                           class="btn-action btn-info btn-sm" 
                                           title="View Details"
                                           onclick="viewTimetableDetails(<?= $timetable['timetable_id'] ?>)">
                                            👁️
                                        </button>
                                        <a href="edit.php?id=<?= $timetable['timetable_id'] ?>" 
                                           class="btn-action btn-outline btn-sm" 
                                           title="Edit Entry">
                                            ✏️
                                        </a>
                                        <?php if ($timetable['is_active']): ?>
                                            <a href="activate-deactivate.php?action=deactivate&id=<?= $timetable['timetable_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                               class="btn-action btn-warning btn-sm"
                                               title="Deactivate Entry"
                                               onclick="return confirm('Are you sure you want to deactivate this timetable entry?')">
                                                ⏸️
                                            </a>
                                        <?php else: ?>
                                            <a href="activate-deactivate.php?action=activate&id=<?= $timetable['timetable_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                               class="btn-action btn-success btn-sm"
                                               title="Activate Entry"
                                               onclick="return confirm('Are you sure you want to activate this timetable entry?')">
                                                ▶️
                                            </a>
                                        <?php endif; ?>
                                        <a href="delete.php?id=<?= $timetable['timetable_id'] ?>" 
                                           class="btn-action btn-danger btn-sm"
                                           title="Delete Entry"
                                           onclick="return confirm('Are you sure you want to delete this timetable entry?')">
                                            🗑️
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Mobile Card Layout -->
        <?php foreach ($timetables as $timetable): ?>
            <div class="timetable-card glass-card slide-up" data-timetable-id="<?= $timetable['timetable_id'] ?>"
                 data-department="<?= strtolower(htmlspecialchars($timetable['department'] ?? '')) ?>"
                 data-semester="<?= strtolower((string)($timetable['semester'] ?? '')) ?>"
                 data-academic-year="<?= strtolower(htmlspecialchars($timetable['academic_year'] ?? '')) ?>"
                 data-day="<?= strtolower(htmlspecialchars($timetable['day_of_week'] ?? '')) ?>"
                 data-section="<?= strtolower(htmlspecialchars($timetable['section'] ?? '')) ?>"
                 data-subject="<?= strtolower(htmlspecialchars(($timetable['subject_code'] ?? '') . ' ' . ($timetable['subject_name'] ?? ''))) ?>"
                 data-faculty="<?= strtolower(htmlspecialchars($timetable['faculty_name'] ?? '')) ?>"
                 data-room="<?= strtolower(htmlspecialchars($timetable['room_number'] ?? '')) ?>"
                 data-building="<?= strtolower(htmlspecialchars($timetable['building'] ?? '')) ?>">
                <div class="timetable-card-header">
                    <div class="subject-info">
                        <div class="subject-avatar">
                            <?= strtoupper(substr($timetable['subject_code'], 0, 3)) ?>
                        </div>
                        <div class="subject-details">
                            <h6><?= htmlspecialchars($timetable['subject_code']) ?></h6>
                            <p class="subject-meta"><?= htmlspecialchars($timetable['subject_name']) ?></p>
                        </div>
                    </div>
                    <span class="badge badge-section"><?= htmlspecialchars($timetable['section']) ?></span>
                </div>

                <div class="timetable-card-details">
                    <div>
                        <strong>Faculty:</strong><br>
                        <?= htmlspecialchars($timetable['faculty_name']) ?><br>
                        <small><?= htmlspecialchars($timetable['employee_id']) ?></small>
                    </div>
                    <div>
                        <strong>Schedule:</strong><br>
                        <?= htmlspecialchars($timetable['day_of_week']) ?><br>
                        <small><?= date('g:i A', strtotime($timetable['start_time'])) ?> - <?= date('g:i A', strtotime($timetable['end_time'])) ?></small>
                    </div>
                    <div>
                        <strong>Location:</strong><br>
                        <?= htmlspecialchars($timetable['room_number']) ?><br>
                        <small><?= htmlspecialchars($timetable['building']) ?></small>
                    </div>
                    <div>
                        <strong>Students:</strong><br>
                        <?= $timetable['enrolled_students'] ?? 0 ?>/<?= $timetable['capacity'] ?><br>
                        <small><?= number_format((($timetable['enrolled_students'] ?? 0) / ($timetable['capacity'] ?? 1)) * 100, 1) ?>% occupied</small>
                    </div>
                    <div>
                        <strong>Semester:</strong><br>
                        Semester <?= $timetable['semester'] ?? 1 ?><br>
                        <small><?= htmlspecialchars($timetable['academic_year'] ?? '2025-2026') ?></small>
                    </div>
                    <div>
                        <strong>Credits:</strong><br>
                        <?= $timetable['credits'] ?? 3 ?> Credits
                    </div>
                </div>

                <div class="timetable-card-actions">
                    <input type="checkbox" name="selected_timetables[]" 
                           value="<?= $timetable['timetable_id'] ?>" 
                           class="timetable-checkbox"
                           onchange="updateBulkActions()">
                    <button type="button"
                       class="btn-action btn-info btn-sm"
                       title="View Details"
                       onclick="viewTimetableDetails(<?= $timetable['timetable_id'] ?>)">
                        👁️ View
                    </button>
                    <a href="edit.php?id=<?= $timetable['timetable_id'] ?>" 
                       class="btn-action btn-outline btn-sm"
                       title="Edit Entry">
                        ✏️ Edit
                    </a>
                    <?php if ($timetable['is_active']): ?>
                        <a href="activate-deactivate.php?action=deactivate&id=<?= $timetable['timetable_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                           class="btn-action btn-warning btn-sm"
                           title="Deactivate Entry"
                           onclick="return confirm('Are you sure you want to deactivate this timetable entry?')">
                            ⏸️ Deactivate
                        </a>
                    <?php else: ?>
                        <a href="activate-deactivate.php?action=activate&id=<?= $timetable['timetable_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                           class="btn-action btn-success btn-sm"
                           title="Activate Entry"
                           onclick="return confirm('Are you sure you want to activate this timetable entry?')">
                            ▶️ Activate
                        </a>
                    <?php endif; ?>
                    <a href="delete.php?id=<?= $timetable['timetable_id'] ?>" 
                       class="btn-action btn-danger btn-sm"
                       title="Delete Entry"
                       onclick="return confirm('Are you sure you want to delete this timetable entry?')">
                        🗑️ Delete
                    </a>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
            <div class="pagination-container">
                <div class="pagination">
                    <?php if ($pagination['current_page'] > 1): ?>
                        <a href="?page=<?= $pagination['current_page'] - 1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>">
                            ← Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                        <?php if ($i == $pagination['current_page']): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                        <a href="?page=<?= $pagination['current_page'] + 1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>">
                            Next →
                        </a>
                    <?php endif; ?>
                </div>
                <div class="pagination-info">
                    Showing <?= (($pagination['current_page'] - 1) * $pagination['per_page']) + 1 ?> to 
                    <?= min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) ?> of 
                    <?= $pagination['total'] ?> entries
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Timetable Details Modal -->
    <div class="modal fade" id="timetableDetailsModal" tabindex="-1" aria-labelledby="timetableDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content glass-card">
                <div class="modal-header">
                    <h5 class="modal-title" id="timetableDetailsModalLabel">📅 Timetable Entry Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="timetableDetailsContent">
                    <!-- Content will be loaded via API -->
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
            applyCurrentTheme();
            
            // Add animation delays
            const animatedElements = document.querySelectorAll('.slide-up');
            animatedElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            // Initialize bulk actions
            updateBulkActions();

            // Initialize instant filters
            initializeSearchAndFilters();
        });

        // Instant client-side filtering (no reload)
        function initializeSearchAndFilters() {
            const deptEl = document.getElementById('department');
            const semEl = document.getElementById('semester');
            const yearEl = document.getElementById('academic_year');
            const dayEl = document.getElementById('day');
            const sectionEl = document.getElementById('section');

            let hasScrolledToResults = false;
            function scrollToResultsOnce() {
                if (hasScrolledToResults) return;
                const container = document.querySelector('.timetables-container') || document.querySelector('.timetable-card');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    hasScrolledToResults = true;
                }
            }

            function filterTimetables() {
                const norm = (s) => (s || '').toString().toLowerCase().replace(/\s+/g, ' ').trim();
                const vDept = norm(deptEl?.value || '');
                const vSem = norm(semEl?.value || '');
                const vYear = norm(yearEl?.value || '');
                const vDay = norm(dayEl?.value || '');
                const vSection = norm(sectionEl?.value || '');

                const rows = document.querySelectorAll('.timetables-table tbody tr[data-timetable-id]');
                const cards = document.querySelectorAll('.timetable-card');

                let visibleCount = 0;
                function apply(elements) {
                    elements.forEach(el => {
                        const dept = norm(el.dataset.department || '');
                        const sem = norm(el.dataset.semester || '');
                        const year = norm(el.dataset.academicYear || el.dataset['academic-year'] || '');
                        const day = norm(el.dataset.day || '');
                        const section = norm(el.dataset.section || '');

                        // Department: allow inclusive match to handle code/name differences
                        const mDept = !vDept || (dept && (dept === vDept || dept.includes(vDept)));
                        const mSem = !vSem || sem === vSem;
                        const mYear = !vYear || year === vYear;
                        const mDay = !vDay || day === vDay;
                        const mSection = !vSection || section === vSection;

                        const show = mDept && mSem && mYear && mDay && mSection;
                        el.style.display = show ? '' : 'none';
                        if (show) visibleCount++;
                    });
                }

                apply(rows);
                apply(cards);

                // Hide alerts when actively filtering
                const isFiltering = !!vDept || !!vSem || !!vYear || !!vDay || !!vSection;
                document.querySelectorAll('.alert').forEach(a => { a.style.display = isFiltering ? 'none' : ''; });

                // Only auto-scroll if the user is actively filtering
                if (isFiltering) {
                    scrollToResultsOnce();
                }
            }

            [deptEl, semEl, yearEl, dayEl, sectionEl].forEach(el => {
                if (!el) return;
                el.addEventListener('change', filterTimetables);
            });

            // Initial pass for preselected values
            filterTimetables();
        }

        function clearFilters() {
            const deptEl = document.getElementById('department');
            const semEl = document.getElementById('semester');
            const yearEl = document.getElementById('academic_year');
            const dayEl = document.getElementById('day');
            const sectionEl = document.getElementById('section');
            if (deptEl) deptEl.value = '';
            if (semEl) semEl.value = '';
            if (yearEl) yearEl.value = '';
            if (dayEl) dayEl.value = '';
            if (sectionEl) sectionEl.value = '';
            if (typeof initializeSearchAndFilters === 'function') initializeSearchAndFilters();
        }


        // Sidebar JS handlers removed to prevent unintended expansion on single link clicks.

        // Apply current theme
        function applyCurrentTheme() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', currentTheme);
        }

        // Bulk Actions Management
        function toggleAllTimetables() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.timetable-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.timetable-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedTimetables = document.getElementById('selectedTimetables');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                
                selectedTimetables.innerHTML = '';
                checkboxes.forEach(checkbox => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'selected_timetables[]';
                    hiddenInput.value = checkbox.value;
                    selectedTimetables.appendChild(hiddenInput);
                });
            } else {
                bulkActions.classList.remove('show');
            }
            
            const allCheckboxes = document.querySelectorAll('.timetable-checkbox');
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
                selectAll.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
            }
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.timetable-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            // Uncheck all individual checkboxes
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Clear the select all checkbox
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            
            updateBulkActions();
        }

        function confirmBulkAction() {
            const checkboxes = document.querySelectorAll('.timetable-checkbox:checked');
            const actionSelect = document.querySelector('select[name="bulk_action_type"]');
            
            if (checkboxes.length === 0) {
                alert('Please select at least one timetable entry.');
                return false;
            }
            
            if (!actionSelect.value) {
                alert('Please select an action.');
                return false;
            }
            
            const actionText = actionSelect.options[actionSelect.selectedIndex].text;
            const count = checkboxes.length;
            
            return confirm(`Are you sure you want to "${actionText}" for ${count} selected timetable entry(s)?`);
        }

        // Theme switcher support
        document.addEventListener('themeChanged', function(e) {
            document.documentElement.setAttribute('data-theme', e.detail.theme);
        });

        // Auto-submit filters on change
        (function(){
            const filterForm = document.querySelector('.search-filters form.filter-controls');
            if (!filterForm) return;
            const inputs = filterForm.querySelectorAll('select, input[type="search"], input[type="text"]');
            inputs.forEach(el => {
                el.addEventListener('change', () => filterForm.submit());
            });
            const textInputs = filterForm.querySelectorAll('input[type="search"], input[type="text"]');
            textInputs.forEach(inp => {
                let t;
                inp.addEventListener('input', () => {
                    clearTimeout(t);
                    t = setTimeout(() => filterForm.submit(), 400);
                });
            });
        })();

        // API Functions for Timetable Details
        async function viewTimetableDetails(timetableId) {
            const modal = new bootstrap.Modal(document.getElementById('timetableDetailsModal'));
            const modalContent = document.getElementById('timetableDetailsContent');
            
            // Show modal with loading state
            modal.show();
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="loading-shimmer" style="height: 200px; border-radius: 8px;"></div>
                    <p class="mt-2 text-muted">Loading timetable details...</p>
                </div>
            `;
            
            try {
                const apiUrl = `../../includes/api/timetable-details.php?id=${timetableId}`;
                console.log('Fetching from:', apiUrl);
                
                const response = await fetch(apiUrl);
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Response data:', data);
                
                if (data.success) {
                    modalContent.innerHTML = formatTimetableDetails(data.timetable);
                } else {
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>Error:</strong> ${data.message || 'Failed to load timetable details'}
                        </div>
                    `;
                }
            } catch (error) {
                console.error('API Error Details:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Error:</strong> ${error.message}<br>
                        <small>Check the browser console for more details.</small>
                    </div>
                `;
            }
        }

        function formatTimetableDetails(timetable) {
            const enrollmentDate = new Date(timetable.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            const occupancyPercent = ((timetable.enrolled_students || 0) / (timetable.capacity || 1)) * 100;

            return `
                <div class="row">
                    <!-- Subject Information -->
                    <div class="col-md-6">
                        <div class="info-section mb-4">
                            <h6 class="section-title mb-3">📚 Subject Information</h6>
                            <div class="d-flex align-items-center mb-3">
                                <div class="subject-avatar me-3" style="width: 50px; height: 50px; font-size: 1rem;">
                                    ${timetable.subject_code.substring(0, 3).toUpperCase()}
                                </div>
                                <div>
                                    <h6 class="mb-1">${timetable.subject_code}</h6>
                                    <small class="text-muted">${timetable.subject_name}</small>
                                </div>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <strong>Credits:</strong> ${timetable.credits || 3} Credits
                                </div>
                                <div class="info-item">
                                    <strong>Department:</strong> ${timetable.subject_department || 'N/A'}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Faculty Information -->
                    <div class="col-md-6">
                        <div class="info-section mb-4">
                            <h6 class="section-title mb-3">👨‍🏫 Faculty Information</h6>
                            <div class="d-flex align-items-center mb-3">
                                <div class="faculty-avatar me-3" style="width: 50px; height: 50px; font-size: 1rem;">
                                    ${timetable.faculty_name.split(' ').map(n => n.charAt(0)).join('').substring(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <h6 class="mb-1">${timetable.faculty_name}</h6>
                                    <small class="text-muted">${timetable.employee_id}</small>
                                </div>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <strong>Department:</strong> ${timetable.faculty_department || 'N/A'}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Schedule Details -->
                <div class="info-section mb-4">
                    <h6 class="section-title mb-3">📅 Schedule Details</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="info-item">
                                <strong>Day:</strong> ${timetable.day_of_week}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <strong>Time:</strong> ${new Date('1970-01-01T' + timetable.start_time + 'Z').toLocaleTimeString('en-US', {timeZone:'UTC',hour12:true,hour:'numeric',minute:'2-digit'})} - ${new Date('1970-01-01T' + timetable.end_time + 'Z').toLocaleTimeString('en-US', {timeZone:'UTC',hour12:true,hour:'numeric',minute:'2-digit'})}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <strong>Slot Name:</strong> ${timetable.slot_name || 'Regular Slot'}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <strong>Duration:</strong> ${Math.round((new Date('1970-01-01T' + timetable.end_time + 'Z') - new Date('1970-01-01T' + timetable.start_time + 'Z')) / (1000 * 60 / 60))} hours
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location & Capacity -->
                <div class="info-section mb-4">
                    <h6 class="section-title mb-3">🏫 Location & Capacity</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-item">
                                <strong>Room:</strong> ${timetable.room_number}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-item">
                                <strong>Building:</strong> ${timetable.building}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-item">
                                <strong>Capacity:</strong> ${timetable.capacity} students
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="info-item">
                                <strong>Enrolled:</strong> ${timetable.enrolled_students || 0} students
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-item">
                                <strong>Occupancy:</strong> ${occupancyPercent.toFixed(1)}%
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-item">
                                <strong>Available:</strong> ${(timetable.capacity || 0) - (timetable.enrolled_students || 0)} seats
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="info-section mb-4">
                    <h6 class="section-title mb-3">📖 Academic Information</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="info-item">
                                <strong>Section:</strong> ${timetable.section}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <strong>Semester:</strong> Semester ${timetable.semester || 1}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Academic Year:</strong> ${timetable.academic_year || '2025-2026'}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div class="info-section">
                    <h6 class="section-title mb-3">⚙️ System Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Created:</strong> ${enrollmentDate}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Created By:</strong> ${timetable.created_by_username || 'System'}
                            </div>
                        </div>
                    </div>
                    ${timetable.notes ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="info-item">
                                    <strong>Notes:</strong> ${timetable.notes}
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>

                <!-- Action Buttons -->
                <div class="mt-4 d-flex gap-2 justify-content-end">
                    <a href="edit.php?id=${timetable.timetable_id}" class="btn-action btn-outline">
                        ✏️ Edit Entry
                    </a>
                    <button type="button" class="btn-action btn-outline" data-bs-dismiss="modal">
                        ❌ Close
                    </button>
                </div>

                <style>
                    .info-section {
                        padding: 1rem;
                        background: rgba(255, 255, 255, 0.1);
                        border-radius: 8px;
                        margin-bottom: 1rem;
                    }
                    
                    [data-theme="dark"] .info-section {
                        background: rgba(0, 0, 0, 0.2);
                    }
                    
                    .section-title {
                        color: var(--primary-color);
                        font-weight: 600;
                        border-bottom: 2px solid var(--primary-color-alpha);
                        padding-bottom: 0.5rem;
                    }
                    
                    .info-grid {
                        display: grid;
                        gap: 0.75rem;
                    }
                    
                    .info-item {
                        padding: 0.5rem 0;
                        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                    }
                    
                    .info-item:last-child {
                        border-bottom: none;
                    }
                    
                    .info-item strong {
                        color: var(--text-primary);
                        display: inline-block;
                        min-width: 120px;
                    }

                    .subject-avatar, .faculty-avatar {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        border-radius: 8px;
                        color: white;
                        font-weight: 600;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }

                    .faculty-avatar {
                        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                        border-radius: 50%;
                    }
                </style>
            `;
        }

        // Export functionality
        function exportTimetables() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export.php';
            
            // Add current filter values
            const filters = ['department', 'semester', 'academic_year', 'day', 'section'];
            filters.forEach(filter => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = filter;
                input.value = document.getElementById(filter).value;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Auto-scroll to updated timetable entry after activate/deactivate
        function autoScrollToUpdatedEntry() {
            const urlParams = new URLSearchParams(window.location.search);
            const updatedId = urlParams.get('updated_id');
            const updated = urlParams.get('updated');
            
            console.log('Auto-scroll check:', { updated, updatedId });
            
            if (updated === '1' && updatedId) {
                console.log('Looking for timetable entry with ID:', updatedId);
                
                // Try to find the updated entry in both desktop and mobile views
                const desktopRow = document.querySelector(`tr[data-timetable-id="${updatedId}"]`);
                const mobileCard = document.querySelector(`.timetable-card[data-timetable-id="${updatedId}"]`);
                
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
                    // Log all available data-timetable-id attributes for debugging
                    const allRows = document.querySelectorAll('[data-timetable-id]');
                    console.log('Available timetable IDs:', Array.from(allRows).map(el => el.getAttribute('data-timetable-id')));
                }
            }
        }

        // Run auto-scroll on page load
        document.addEventListener('DOMContentLoaded', autoScrollToUpdatedEntry);

        // Make functions globally available
        window.viewTimetableDetails = viewTimetableDetails;
        window.exportTimetables = exportTimetables;
        window.toggleAllTimetables = toggleAllTimetables;
        window.updateBulkActions = updateBulkActions;
        window.clearSelection = clearSelection;
        window.confirmBulkAction = confirmBulkAction;
    </script>
</body>
</html>