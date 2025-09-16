<?php
/**
 * Admin Subjects Management - Subjects Management Interface
 * Timetable Management System
 * 
 * Professional interface for admin to manage all system subjects including
 * CRUD operations, faculty assignments, and comprehensive subject analytics
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Subject.php';
require_once '../../includes/functions.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$subjectManager = new Subject();

// Initialize variables
$subjects = [];
$subjectStats = [];
$departments = [];
$error_message = '';
$success_message = '';
$selectedDepartment = $_GET['department'] ?? '';
$selectedType = $_GET['type'] ?? '';
$selectedYearLevel = $_GET['year_level'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Handle bulk actions
if ($_POST['action'] ?? '' === 'bulk_action' && !empty($_POST['selected_subjects'])) {
    $selectedSubjects = $_POST['selected_subjects'];
    $bulkAction = $_POST['bulk_action_type'];
    
    try {
        $result = $subjectManager->bulkAction($selectedSubjects, $bulkAction, $userId);
        if ($result['success']) {
            flash_set('success', $result['message']);
        } else {
            flash_set('error', $result['message']);
        }
        
        // Preserve filters in redirect
        $redirectParams = [];
        if (!empty($selectedDepartment)) $redirectParams['department'] = $selectedDepartment;
        if (!empty($selectedType)) $redirectParams['type'] = $selectedType;
        if (!empty($selectedYearLevel)) $redirectParams['year_level'] = $selectedYearLevel;
        if (!empty($searchTerm)) $redirectParams['search'] = $searchTerm;
        
        $redirectUrl = 'index.php' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');
        header("Location: {$redirectUrl}");
        exit;
        
    } catch (Exception $e) {
        $error_message = "Bulk action failed: " . $e->getMessage();
    }
}

// Handle individual actions
if (isset($_GET['action']) && isset($_GET['subject_id'])) {
    $targetSubjectId = (int)$_GET['subject_id'];
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'activate':
                $result = $subjectManager->updateSubject($targetSubjectId, ['is_active' => 1]);
                if ($result['success']) {
                    $_SESSION['flash_message'] = [
                        'type' => 'success',
                        'message' => 'Subject activated successfully!'
                    ];
                }
                break;
            case 'deactivate':
                $result = $subjectManager->updateSubject($targetSubjectId, ['is_active' => 0]);
                if ($result['success']) {
                    $_SESSION['flash_message'] = [
                        'type' => 'success',
                        'message' => 'Subject deactivated successfully!'
                    ];
                }
                break;
        }
        
        // Preserve filters in redirect
        $redirectParams = [];
        if (!empty($selectedDepartment)) $redirectParams['department'] = $selectedDepartment;
        if (!empty($selectedType)) $redirectParams['type'] = $selectedType;
        if (!empty($selectedYearLevel)) $redirectParams['year_level'] = $selectedYearLevel;
        if (!empty($searchTerm)) $redirectParams['search'] = $searchTerm;
        
        if (isset($result) && $result['success']) {
            $redirectParams['updated'] = '1';
            $redirectParams['updated_id'] = $targetSubjectId;
        }
        
        $redirectUrl = 'index.php' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');
        header("Location: {$redirectUrl}");
        exit;
        
    } catch (Exception $e) {
        $error_message = "Action failed: " . $e->getMessage();
    }
}

try {
    // Build filters for subject query
    $filters = [];
    if (!empty($selectedDepartment)) {
        $filters['department'] = $selectedDepartment;
    }
    if (!empty($selectedType)) {
        $filters['type'] = $selectedType;
    }
    if (!empty($selectedYearLevel)) {
        $filters['year_level'] = $selectedYearLevel;
    }

    // Get all subjects with comprehensive information
    $subjects = $subjectManager->getAllSubjects($filters, $searchTerm);

    // Get subjects statistics
    $subjectStats = $subjectManager->getSubjectsStatistics();

    // Get unique departments for filter dropdown
    $departments = $subjectManager->getDepartments();

} catch (Exception $e) {
    error_log("Admin Subjects Management Error: " . $e->getMessage());
    $error_message = "Unable to load subjects data. Please try again later.";
    $subjectStats = [
        'total_subjects' => 0, 'theory_subjects' => 0, 'practical_subjects' => 0, 
        'lab_subjects' => 0, 'year_1_subjects' => 0, 'year_2_subjects' => 0, 
        'year_3_subjects' => 0, 'year_4_subjects' => 0, 'avg_credits' => 0,
        'avg_duration' => 0
    ];
}

// Handle flash messages (use centralized helpers)
$flash = null;
$successFlash = flash_get('success');
$errorFlash = flash_get('error');
$infoFlash = flash_get('info');
if (!empty($successFlash)) {
    $flash = ['type' => 'success', 'message' => $successFlash];
} elseif (!empty($errorFlash)) {
    $flash = ['type' => 'error', 'message' => $errorFlash];
} elseif (!empty($infoFlash)) {
    $flash = ['type' => 'info', 'message' => $infoFlash];
} else {
    // Fallback for URL parameters (no generic 'updated' message to avoid ambiguity)
    if (isset($_GET['created']) && $_GET['created'] == '1') {
        $flash = ['type' => 'success', 'message' => 'Subject created successfully!'];
    } elseif (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
        $flash = ['type' => 'success', 'message' => 'Subject deleted successfully!'];
    }
}

// Set page title
$pageTitle = "Subjects Management";
$currentPage = "subjects";
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
        /* Use the same CSS variables and base styles from users index */
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

        /* Statistics Cards - EXACTLY like users index */
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
        .stat-icon.theory { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.practical { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.lab { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .stat-icon.year1 { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .stat-icon.year2 { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }
        .stat-icon.year3 { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .stat-icon.year4 { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }

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

        /* Search and Filters - EXACTLY like users index */
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

        /* Subjects Table Container - EXACTLY like users index */
        .timeslots-container {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        /* Scroll container for table */
        .timeslots-container .table-responsive {
            max-height: 65vh;
            overflow-y: auto;
            overflow-x: hidden; /* prevent side-by-side horizontal scroll */
        }

        .timeslots-table {
            width: 100%;
            margin: 0;
            table-layout: fixed; /* match users table for consistent wrapping */
        }

        /* Sticky header + compact header styling (match users table) */
        .timeslots-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--bg-primary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            padding: 0.5rem 0.6rem; /* compact header */
            font-weight: 600;
            color: var(--text-primary);
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.75rem; /* smaller header text */
            text-transform: uppercase;
            letter-spacing: 0.4px;
            line-height: 1.2;
            white-space: normal; /* allow header text to wrap */
            word-break: break-word;
        }

        /* Table header sublabels */
        .th-sub {
            display: block;
            margin-top: 2px;
            font-size: 0.72rem;
            line-height: 1.1;
            color: var(--text-tertiary);
            font-weight: 500;
            white-space: normal;
        }

        /* Allow wrapping in cells to avoid horizontal scrolling */
        .timeslots-table th,
        .timeslots-table td {
            white-space: normal;
            word-break: break-word;
        }

        .timeslots-table td {
            padding: 0.6rem 0.6rem; /* compact cells to match users */
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
            white-space: normal; /* wrap long text */
            word-break: break-word;
        }

        .timeslots-table tbody tr {
            transition: all 0.3s ease;
        }

        .timeslots-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Dark Mode Styling */
        [data-theme="dark"] .timeslots-container {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
        }

        [data-theme="dark"] .timeslots-table {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        [data-theme="dark"] .timeslots-table thead th {
            background: var(--bg-secondary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .timeslots-table tbody tr {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .timeslots-table tbody tr:hover {
            background: var(--bg-tertiary);
        }

        [data-theme="dark"] .timeslots-table tbody td {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        /* Column widths and alignment (mirror users table where applicable) */
        .timeslots-table th:nth-child(1),
        .timeslots-table td:nth-child(1) { /* checkbox */
            width: 44px;
            text-align: center;
            white-space: nowrap;
        }

        .timeslots-table th:nth-child(4),
        .timeslots-table td:nth-child(4) { /* Type */
            width: 100px;
            text-align: center;
        }

        .timeslots-table th:nth-child(5),
        .timeslots-table td:nth-child(5) { /* Year & Sem */
            width: 120px;
            white-space: nowrap;
        }

        .timeslots-table th:nth-child(6),
        .timeslots-table td:nth-child(6) { /* Credits & Duration */
            width: 140px;
        }

        .timeslots-table th:nth-child(9),
        .timeslots-table td:nth-child(9) { /* Actions */
            width: 130px;
            text-align: center;
        }

        [data-theme="dark"] .status-badge {
            color: white;
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
        
        /* Dark mode styles for subjects table - EXACTLY like users table */
        [data-theme="dark"] .subjects-table {
            background-color: #1a202c !important;
            color: #e2e8f0 !important;
            border-color: #2d3748 !important;
        }
        
        [data-theme="dark"] .subjects-table thead {
            background-color: #2d3748 !important;
        }
        
        [data-theme="dark"] .subjects-table thead th {
            color: #e2e8f0 !important;
            border-bottom-color: #4a5568 !important;
            background-color: #2d3748 !important;
        }
        
        [data-theme="dark"] .subjects-table tbody tr {
            border-bottom: 1px solid #2d3748 !important;
            background-color: #1a202c !important;
        }
        
        [data-theme="dark"] .subjects-table tbody tr:hover {
            background-color: #2d3748 !important;
        }
        
        [data-theme="dark"] .subjects-table tbody tr:nth-child(even) {
            background-color: #1a202c !important;
        }
        
        [data-theme="dark"] .subjects-table tbody tr:nth-child(even):hover {
            background-color: #2d3748 !important;
        }
        
        [data-theme="dark"] .subjects-table tbody td {
            color: #e2e8f0 !important;
            border-color: #2d3748 !important;
            background-color: transparent !important;
        }
        
        [data-theme="dark"] .subjects-table tbody td small {
            color: #a0aec0 !important;
        }
        
        [data-theme="dark"] .status-badge {
            color: white !important;
        }
        
        [data-theme="dark"] .subjects-container {
            background-color: #1a202c !important;
            border-color: #2d3748 !important;
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

        .subject-avatar {
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

        .subject-info {
            display: flex;
            align-items: center;
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

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-type-theory {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .badge-type-practical {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .badge-type-lab {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }

        .badge-year {
            background: rgba(6, 182, 212, 0.1);
            color: #0891b2;
        }

        .badge-credits {
            background: rgba(236, 72, 153, 0.1);
            color: #db2777;
        }

        /* Compact meta badges container */
        .meta-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.25rem;
        }
        .meta-inline {
            display: flex;
            gap: 0.5rem;
            align-items: baseline;
            flex-wrap: wrap;
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

        /* Mobile Cards for responsive design */
        .subject-card {
            display: none;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .subject-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .subject-card-avatar {
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

        .subject-card-info h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .subject-card-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0.25rem 0;
        }

        .subject-card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .subject-card-detail {
            font-size: 0.875rem;
        }

        .subject-card-detail strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .subject-card-detail span {
            color: var(--text-secondary);
        }

        .subject-card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
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
            .timeslots-container {
                display: none;
            }

            .subject-card {
                display: block;
                /* Stronger, more visible borders on mobile */
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8); /* increased contrast */
                /* Add left and right margins for mobile spacing */
                margin-left: 1rem;
                margin-right: 1rem;
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .subject-card {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }

            .subject-card-details {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 480px) {
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
        <?php if (!empty($flash)): ?>
            <div class="sticky-header show" id="topSuccessAlert" role="alert">
                <div class="sticky-header-content">
                    <div class="sticky-header-info">
                        <div class="sticky-header-avatar">✅</div>
                        <div class="sticky-header-text">
                            <h3><?= ($flash['type'] ?? '') === 'success' ? 'Success' : ((($flash['type'] ?? '') === 'error') ? 'Error' : 'Notice') ?></h3>
                            <p><?= htmlspecialchars($flash['message'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-card glass-card fade-in">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">📚 Subjects Management</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="create.php" class="btn-action btn-primary">
                            ➕ Add Subject
                        </a>
                        <a href="assign-faculty.php" class="btn-action btn-success">
                            👨‍🏫 Assign Faculty
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon total">📚</div>
                <div class="stat-number"><?= $subjectStats['total_subjects'] ?? 0 ?></div>
                <div class="stat-label">Total Subjects</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon theory">📖</div>
                <div class="stat-number"><?= $subjectStats['theory_subjects'] ?? 0 ?></div>
                <div class="stat-label">Theory Subjects</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon practical">🛠️</div>
                <div class="stat-number"><?= $subjectStats['practical_subjects'] ?? 0 ?></div>
                <div class="stat-label">Practical Subjects</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon lab">🧪</div>
                <div class="stat-number"><?= $subjectStats['lab_subjects'] ?? 0 ?></div>
                <div class="stat-label">Laboratory</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon year1">1️⃣</div>
                <div class="stat-number"><?= $subjectStats['year_1_subjects'] ?? 0 ?></div>
                <div class="stat-label">Year 1 Subjects</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon year2">2️⃣</div>
                <div class="stat-number"><?= $subjectStats['year_2_subjects'] ?? 0 ?></div>
                <div class="stat-label">Year 2 Subjects</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon year3">3️⃣</div>
                <div class="stat-number"><?= $subjectStats['year_3_subjects'] ?? 0 ?></div>
                <div class="stat-label">Year 3 Subjects</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon year4">4️⃣</div>
                <div class="stat-number"><?= $subjectStats['year_4_subjects'] ?? 0 ?></div>
                <div class="stat-label">Year 4 Subjects</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters glass-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search subjects..." 
                           value="<?= htmlspecialchars($searchTerm) ?>" id="searchInput">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19S2 15.194 2 10.5S5.806 2 10.5 2S19 5.806 19 10.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                
                <div class="filter-controls">
                    <div class="filter-group">
                        <label class="filter-label">Department</label>
                        <select class="filter-select" id="departmentFilter" onchange="handleDepartmentFilter()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['department']) ?>" 
                                        <?= $selectedDepartment == $dept['department'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Type</label>
                        <select class="filter-select" id="typeFilter" onchange="handleTypeFilter()">
                            <option value="">All Types</option>
                            <option value="theory" <?= $selectedType == 'theory' ? 'selected' : '' ?>>Theory</option>
                            <option value="practical" <?= $selectedType == 'practical' ? 'selected' : '' ?>>Practical</option>
                            <option value="lab" <?= $selectedType == 'lab' ? 'selected' : '' ?>>Laboratory</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Year Level</label>
                        <select class="filter-select" id="yearLevelFilter" onchange="handleYearLevelFilter()">
                            <option value="">All Years</option>
                            <option value="1" <?= $selectedYearLevel == '1' ? 'selected' : '' ?>>Year 1</option>
                            <option value="2" <?= $selectedYearLevel == '2' ? 'selected' : '' ?>>Year 2</option>
                            <option value="3" <?= $selectedYearLevel == '3' ? 'selected' : '' ?>>Year 3</option>
                            <option value="4" <?= $selectedYearLevel == '4' ? 'selected' : '' ?>>Year 4</option>
                        </select>
                    </div>
                    
                    <button class="btn-action btn-outline" onclick="clearFilters()">
                        🔄 Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions (shown when subjects are selected) -->
        <form method="post" id="bulkActionForm">
            <div class="bulk-actions" id="bulkActions">
                <div class="d-flex align-items-center gap-3">
                    <span><strong id="selectedCount">0</strong> subjects selected</span>
                    
                    <select name="bulk_action_type" class="filter-select">
                        <option value="">Choose Action</option>
                        <option value="activate">Activate Selected</option>
                        <option value="deactivate">Deactivate Selected</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                    
                    <button type="submit" name="action" value="bulk_action" class="btn-action btn-primary">
                        Apply Action
                    </button>
                    
                    <button type="button" class="btn-action btn-outline" onclick="clearSelection()">
                        Clear Selection
                    </button>
                </div>
            </div>

            <!-- Subjects Table (Desktop) -->
            <?php if (!empty($subjects)): ?>
                <div class="timeslots-container glass-card">
                    <div class="table-responsive">
                        <table class="timeslots-table table table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                    <th>Subject</th>
                                    <th>Department</th>
                                    <th>Type</th>
                                    <th>Year & Sem</th>
                                    <th>Credits & Duration</th>
                                    <th>Faculty & Students</th>
                                    <th>Classrooms</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="subjectsTableBody">
                                <?php foreach ($subjects as $subject): ?>
                                    <tr class="subject-row" id="subject-<?= $subject['subject_id'] ?>"
                                        data-subject-id="<?= $subject['subject_id'] ?>"
                                        data-code="<?= strtolower($subject['subject_code']) ?>"
                                        data-name="<?= strtolower($subject['subject_name']) ?>"
                                        data-department="<?= strtolower($subject['department'] ?? '') ?>"
                                        data-type="<?= $subject['type'] ?>"
                                        data-year="<?= $subject['year_level'] ?>">
                                        <td>
                                            <input type="checkbox" name="selected_subjects[]" value="<?= $subject['subject_id'] ?>" 
                                                   class="subject-checkbox" onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <div class="subject-info">
                                                <div class="subject-avatar">
                                                    <?= strtoupper(substr($subject['subject_code'], 0, 2)) ?>
                                                </div>
                                                <div class="subject-details">
                                                    <h6><?= htmlspecialchars($subject['subject_code']) ?></h6>
                                                    <p class="subject-meta"><?= htmlspecialchars($subject['subject_name']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($subject['department'] ?? 'Not specified') ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-type-<?= $subject['type'] ?>">
                                                <?= ucfirst($subject['type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-year">Year <?= $subject['year_level'] ?></span>
                                            <br>
                                            <strong>Sem <?= $subject['semester'] ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-credits"><?= $subject['credits'] ?> Cr</span>
                                            <br>
                                            <strong><?= $subject['duration_hours'] ?> hrs</strong>
                                        </td>
                                        <td>
                                            <?php if ($subject['assigned_faculty_count'] > 0): ?>
                                                <strong style="color: var(--success-color);">👨‍🏫 <?= $subject['assigned_faculty_count'] ?></strong>
                                            <?php else: ?>
                                                <span style="color: var(--warning-color);">👨‍🏫 0</span>
                                            <?php endif; ?>
                                            <br>
                                            <?php if ($subject['enrolled_students_count'] > 0): ?>
                                                <strong style="color: var(--primary-color);">👥 <?= $subject['enrolled_students_count'] ?></strong>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">👥 0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($subject['scheduled_classes_count'] > 0): ?>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <strong style="color: var(--success-color);">🏫 <?= $subject['scheduled_classes_count'] ?></strong>
                                                    <small style="color: var(--text-secondary);">scheduled</small>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-tertiary);">🏫 No classes scheduled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <?php if ($subject['is_active']): ?>
                                                    <a href="activate-deactivate.php?action=deactivate&id=<?= $subject['subject_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                       class="btn-action btn-warning btn-sm" 
                                                       onclick="return confirm('Are you sure you want to deactivate this subject?')" title="Deactivate Subject">
                                                        ⏸️
                                                    </a>
                                                <?php else: ?>
                                                    <a href="activate-deactivate.php?action=activate&id=<?= $subject['subject_id'] ?>&return=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                                       class="btn-action btn-success btn-sm" 
                                                       onclick="return confirm('Are you sure you want to activate this subject?')" title="Activate Subject">
                                                        ▶️
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn-action btn-primary btn-sm" 
                                                        onclick="viewSubjectDetails(<?= $subject['subject_id'] ?>)" title="View Details">
                                                    👁️
                                                </button>
                                                
                                                <a href="edit.php?id=<?= $subject['subject_id'] ?>" 
                                                   class="btn-action btn-outline btn-sm" title="Edit Subject">
                                                    ✏️
                                                </a>
                                                
                                                <a href="assign-faculty.php?subject_id=<?= $subject['subject_id'] ?>" 
                                                   class="btn-action btn-success btn-sm" title="Assign Faculty">
                                                    👨‍🏫
                                                </a>
                                                
                                                <a href="delete.php?id=<?= $subject['subject_id'] ?>" 
                                                   class="btn-action btn-danger btn-sm" 
                                                   onclick="return confirm('Are you sure you want to delete this subject? This action cannot be undone.')" 
                                                   title="Delete Subject">
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

        <!-- Subjects Cards (Mobile) -->
        <div id="subjectsCards">
            <?php foreach ($subjects as $subject): ?>
                <div class="subject-card" id="subject-card-<?= $subject['subject_id'] ?>"
                     data-code="<?= strtolower($subject['subject_code']) ?>"
                     data-name="<?= strtolower($subject['subject_name']) ?>"
                     data-department="<?= strtolower($subject['department'] ?? '') ?>"
                     data-type="<?= $subject['type'] ?>"
                     data-year="<?= $subject['year_level'] ?>">
                    
                    <div class="subject-card-header">
                        <div class="subject-card-avatar">
                            <?= strtoupper(substr($subject['subject_code'], 0, 2)) ?>
                        </div>
                        <div class="subject-card-info">
                            <h6><?= htmlspecialchars($subject['subject_code']) ?></h6>
                            <div class="subject-card-meta"><?= htmlspecialchars($subject['subject_name']) ?></div>
                            <div class="subject-card-meta">
                                <span class="badge badge-type-<?= $subject['type'] ?>"><?= ucfirst($subject['type']) ?></span>
                                <span class="badge badge-year">Year <?= $subject['year_level'] ?></span>
                                <span class="badge badge-credits"><?= $subject['credits'] ?> Credits</span>
                            </div>
                        </div>
                    </div>

                    <div class="subject-card-details">
                        <div class="subject-card-detail">
                            <strong>Department:</strong><br>
                            <span><?= htmlspecialchars($subject['department'] ?? 'Not specified') ?></span>
                        </div>
                        <div class="subject-card-detail">
                            <strong>Semester:</strong><br>
                            <span><?= $subject['semester'] ?></span>
                        </div>
                        <div class="subject-card-detail">
                            <strong>Duration:</strong><br>
                            <span><?= $subject['duration_hours'] ?> hours</span>
                        </div>
                        <div class="subject-card-detail">
                            <strong>Assigned Faculty:</strong><br>
                            <span><?= $subject['assigned_faculty_count'] ?> faculty members</span>
                        </div>
                        <div class="subject-card-detail">
                            <strong>Enrolled Students:</strong><br>
                            <span><?= $subject['enrolled_students_count'] ?> students</span>
                        </div>
                        <div class="subject-card-detail">
                            <strong>Scheduled Classes:</strong><br>
                            <span><?= $subject['scheduled_classes_count'] ?> classes</span>
                        </div>
                    </div>

                    <div class="subject-card-actions">
                        <button type="button" class="btn-action btn-primary" onclick="viewSubjectDetails(<?= $subject['subject_id'] ?>)">
                            👁️ View Details
                        </button>
                        
                        <a href="edit.php?id=<?= $subject['subject_id'] ?>" class="btn-action btn-outline">
                            ✏️ Edit Subject
                        </a>
                        
                        <a href="assign-faculty.php?subject_id=<?= $subject['subject_id'] ?>" class="btn-action btn-success">
                            👨‍🏫 Assign Faculty
                        </a>
                        
                        <a href="delete.php?id=<?= $subject['subject_id'] ?>" 
                           class="btn-action btn-danger js-delete-subject"
                           data-delete-href="delete.php?id=<?= $subject['subject_id'] ?>">
                            🗑️ Delete Subject
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
            <div class="empty-state glass-card">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h3>No Subjects Found</h3>
                <p>
                    <?php if (!empty($selectedDepartment) || !empty($selectedType) || !empty($selectedYearLevel) || !empty($searchTerm)): ?>
                        No subjects match your current filter criteria. Try adjusting your filters or clear them to see all subjects.
                    <?php else: ?>
                        No subjects are currently registered in the system. Subjects will appear here as they are created.
                    <?php endif; ?>
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="create.php" class="btn-action btn-primary">
                        ➕ Create First Subject
                    </a>
                    <?php if (!empty($selectedDepartment) || !empty($selectedType) || !empty($selectedYearLevel) || !empty($searchTerm)): ?>
                        <button onclick="clearFilters()" class="btn-action btn-outline">
                            🔄 Clear Filters
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= ($flash['type'] ?? '') === 'success' ? 'success' : ((($flash['type'] ?? '') === 'error') ? 'danger' : 'info') ?> glass-card mt-3 d-none" role="alert">
                <!-- Fallback alert kept hidden; sticky header above is primary display -->
                <strong><?= ($flash['type'] ?? '') === 'success' ? 'Success:' : ((($flash['type'] ?? '') === 'error') ? 'Error:' : 'Notice:') ?></strong>
                <?= htmlspecialchars($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>

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

    <!-- Subject Details Modal -->
    <div class="modal fade" id="subjectDetailsModal" tabindex="-1" aria-labelledby="subjectDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="subjectDetailsModalLabel">Subject Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="subjectDetailsContent">
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
        // Auto-hide top success/error banner after 5 seconds
        (function() {
            const topAlert = document.getElementById('topSuccessAlert');
            if (topAlert) {
                setTimeout(() => {
                    topAlert.style.transition = 'transform 0.4s ease, opacity 0.4s ease';
                    topAlert.style.opacity = '0';
                    topAlert.style.transform = 'translateY(-100%)';
                    setTimeout(() => { topAlert.remove(); }, 450);
                }, 5000);
            }
        })();

        // Highlight updated row and scroll into view if present
        (function() {
            const params = new URLSearchParams(window.location.search);
            const updatedId = params.get('updated_id');
            if (updatedId) {
                const row = document.getElementById('subject-' + updatedId);
                if (row) {
                    row.classList.add('row-highlight');
                    // Ensure it is visible within the scrollable container
                    const container = row.closest('.table-responsive');
                    if (container) {
                        const top = row.offsetTop - 120; // account for sticky header height
                        container.scrollTo({ top, behavior: 'smooth' });
                    } else {
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    setTimeout(() => row.classList.remove('row-highlight'), 2600);
                }
            }
        })();
        // Accessibility: prevent focused descendant when modal gets aria-hidden
        document.addEventListener('DOMContentLoaded', function () {
            const subjectModalEl = document.getElementById('subjectDetailsModal');
            if (subjectModalEl) {
                // Ensure modal is focusable on show and not inert
                subjectModalEl.addEventListener('show.bs.modal', function () {
                    subjectModalEl.removeAttribute('inert');
                    // Focus the modal container for better a11y
                    setTimeout(() => {
                        try { subjectModalEl.focus({ preventScroll: true }); } catch (e) { /* noop */ }
                    }, 0);
                });

                subjectModalEl.addEventListener('hide.bs.modal', function () {
                    // Prevent focus while the modal is transitioning to hidden
                    subjectModalEl.setAttribute('inert', '');
                    const ae = document.activeElement;
                    if (ae && subjectModalEl.contains(ae)) {
                        // Remove focus from any element inside the modal before it becomes hidden
                        ae.blur();
                    }
                    // Restore focus to the trigger if available
                    if (window.__lastSubjectDetailsTrigger && document.contains(window.__lastSubjectDetailsTrigger)) {
                        try { window.__lastSubjectDetailsTrigger.focus({ preventScroll: true }); } catch (e) { /* noop */ }
                    }
                });

                // Safety: after fully hidden ensure no focused element remains inside hidden modal
                subjectModalEl.addEventListener('hidden.bs.modal', function () {
                    if (subjectModalEl.contains(document.activeElement)) {
                        try { document.activeElement.blur(); } catch (e) { /* noop */ }
                    }
                });

                // If user clicks a dismiss control, blur it on next tick to avoid retaining focus
                subjectModalEl.addEventListener('click', function (e) {
                    const dismissEl = e.target.closest('[data-bs-dismiss="modal"], .btn-close');
                    if (dismissEl && subjectModalEl.contains(dismissEl)) {
                        setTimeout(() => {
                            if (subjectModalEl.contains(document.activeElement)) {
                                try { document.activeElement.blur(); } catch (e) { /* noop */ }
                            }
                        }, 0);
                    }
                });
            }
        });
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

            // Handle row highlighting and scrolling for post-action feedback
            handlePostActionFeedback();

            // Auto-hide success alerts
            autoHideAlerts();
        });

        // Handle post-action feedback (row highlighting and scrolling)
        function handlePostActionFeedback() {
            const urlParams = new URLSearchParams(window.location.search);
            const targetId = urlParams.get('created_id') || urlParams.get('updated_id');
            
            if (targetId) {
                setTimeout(() => {
                    const targetRow = document.getElementById(`subject-${targetId}`);
                    const targetCard = document.getElementById(`subject-card-${targetId}`);
                    
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
            const departmentFilter = document.getElementById('departmentFilter');
            const typeFilter = document.getElementById('typeFilter');
            const yearLevelFilter = document.getElementById('yearLevelFilter');

            let hasScrolledToResults = false;

            function scrollToResultsOnce() {
                if (hasScrolledToResults) return;
                const container = document.querySelector('.timeslots-container') || document.getElementById('subjectsCards');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    hasScrolledToResults = true;
                }
            }

            function filterSubjects() {
                const searchTerm = (searchInput?.value || '').toLowerCase();
                const deptVal = departmentFilter?.value.toLowerCase() || '';
                const typeVal = typeFilter?.value || '';
                const yearVal = yearLevelFilter?.value || '';

                const tableRows = document.querySelectorAll('.subject-row');
                const subjectCards = document.querySelectorAll('.subject-card');

                let visibleCount = 0;

                function applyFilter(elements) {
                    elements.forEach(element => {
                        const code = element.dataset.code || '';
                        const name = element.dataset.name || '';
                        const department = element.dataset.department || '';
                        const type = element.dataset.type || '';
                        const year = element.dataset.year || '';

                        const matchesSearch = !searchTerm ||
                            code.includes(searchTerm) ||
                            name.includes(searchTerm) ||
                            department.includes(searchTerm);

                        const matchesDept = !deptVal || department === deptVal;
                        const matchesType = !typeVal || type === typeVal;
                        const matchesYear = !yearVal || year === yearVal;

                        const shouldShow = matchesSearch && matchesDept && matchesType && matchesYear;

                        if (shouldShow) {
                            element.style.display = '';
                            visibleCount++;
                        } else {
                            element.style.display = 'none';
                        }
                    });
                }

                applyFilter(tableRows);
                applyFilter(subjectCards);

                if (visibleCount === 0 && (tableRows.length > 0 || subjectCards.length > 0)) {
                    showNoResultsMessage();
                } else {
                    hideNoResultsMessage();
                }

                // Hide top sticky alert when actively filtering/searching
                const isActivelyFiltering = (searchTerm.length > 0) || deptVal || typeVal || yearVal;
                const topAlert = document.getElementById('topSuccessAlert');
                if (topAlert) topAlert.style.display = isActivelyFiltering ? 'none' : '';
            }

            // Event listeners - all client-side, no page reloads
            if (searchInput) {
                searchInput.addEventListener('input', () => { filterSubjects(); scrollToResultsOnce(); });
            }
            if (departmentFilter) {
                departmentFilter.addEventListener('change', () => { filterSubjects(); scrollToResultsOnce(); });
            }
            if (typeFilter) {
                typeFilter.addEventListener('change', () => { filterSubjects(); scrollToResultsOnce(); });
            }
            if (yearLevelFilter) {
                yearLevelFilter.addEventListener('change', () => { filterSubjects(); scrollToResultsOnce(); });
            }

            // Initial run
            filterSubjects();
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
                <h3>No Subjects Found</h3>
                <p>No subjects match your current search criteria. Try adjusting your search terms.</p>
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
            const departmentFilter = document.getElementById('departmentFilter');
            const typeFilter = document.getElementById('typeFilter');
            const yearLevelFilter = document.getElementById('yearLevelFilter');
            if (searchInput) searchInput.value = '';
            if (departmentFilter) departmentFilter.value = '';
            if (typeFilter) typeFilter.value = '';
            if (yearLevelFilter) yearLevelFilter.value = '';
            // Trigger filter pass without reload
            if (typeof initializeSearchAndFilters === 'function') {
                const evt = new Event('input');
                if (searchInput) searchInput.dispatchEvent(evt);
            }
        }

        // Replace URL-based filtering with client-side behavior
        function handleDepartmentFilter() { /* no-op, handled by change listener */ }
        function handleTypeFilter() { /* no-op, handled by change listener */ }
        function handleYearLevelFilter() { /* no-op, handled by change listener */ }

        // Bulk Actions Management
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const subjectCheckboxes = document.querySelectorAll('.subject-checkbox');
            
            subjectCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const selectedCheckboxes = document.querySelectorAll('.subject-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');
            const bulkActionSelect = document.querySelector('select[name="bulk_action_type"]');
            
            if (selectedCount) {
                selectedCount.textContent = selectedCheckboxes.length;
            }
            
            if (bulkActions) {
                if (selectedCheckboxes.length > 0) {
                    bulkActions.classList.add('show');
                    // Add required attribute when visible
                    if (bulkActionSelect) {
                        bulkActionSelect.setAttribute('required', 'required');
                    }
                } else {
                    bulkActions.classList.remove('show');
                    // Remove required attribute when hidden and reset value
                    if (bulkActionSelect) {
                        bulkActionSelect.removeAttribute('required');
                        bulkActionSelect.value = '';
                    }
                }
            }
            
            // Update select all checkbox state
            if (selectAll) {
                const allCheckboxes = document.querySelectorAll('.subject-checkbox');
                selectAll.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < allCheckboxes.length;
                selectAll.checked = selectedCheckboxes.length === allCheckboxes.length && allCheckboxes.length > 0;
            }
        }

        function clearSelection() {
            const subjectCheckboxes = document.querySelectorAll('.subject-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            subjectCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            
            updateBulkActions();
        }

        async function viewSubjectDetails(subjectId) {
            // Remember the element that triggered opening for focus restoration
            window.__lastSubjectDetailsTrigger = document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
            const modal = new bootstrap.Modal(document.getElementById('subjectDetailsModal'));
            const modalContent = document.getElementById('subjectDetailsContent');
            
            // Show loading state
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="loading-shimmer" style="height: 200px; border-radius: 8px; margin-bottom: 1rem;"></div>
                    <p>Loading subject details...</p>
                </div>
            `;
            
            modal.show();

            try {
                // Check if API file exists first with a more specific path
                const apiPath = window.location.pathname.includes('/admin/subjects/') 
                    ? '../../includes/api/subject-details.php' 
                    : '../includes/api/subject-details.php';
                
                const response = await fetch(`${apiPath}?id=${subjectId}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const textResponse = await response.text();
                    console.error('Non-JSON response:', textResponse);
                    throw new Error('Server returned non-JSON response. Please check if the API endpoint exists.');
                }
                
                const data = await response.json();

                if (data.success) {
                    modalContent.innerHTML = generateSubjectDetailsHTML(data.subject);
                } else {
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>Error:</strong> ${data.message || 'Failed to load subject details'}
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading subject details:', error);
                
                // Show fallback content with basic subject info from the table
                const subjectRow = document.getElementById(`subject-${subjectId}`);
                if (subjectRow) {
                    const subjectCode = subjectRow.querySelector('.subject-details h6')?.textContent || 'Unknown';
                    const subjectName = subjectRow.querySelector('.subject-meta')?.textContent || 'Unknown';
                    
                    modalContent.innerHTML = `
                        <div class="alert alert-warning">
                            <strong>API Unavailable:</strong> Showing basic information only.
                        </div>
                        <div class="text-center mb-4">
                            <div class="subject-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                                ${subjectCode.substring(0, 2).toUpperCase()}
                            </div>
                            <h4>${subjectCode}</h4>
                            <p class="text-muted">${subjectName}</p>
                        </div>
                        <div class="text-center">
                            <p>For full details, please ensure the API endpoint is available.</p>
                            <div class="mt-3">
                                <a href="edit.php?id=${subjectId}" class="btn-action btn-primary me-2">
                                    ✏️ Edit Subject
                                </a>
                                <a href="assign-faculty.php?subject_id=${subjectId}" class="btn-action btn-success me-2">
                                    👨‍🏫 Assign Faculty
                                </a>
                            </div>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">Technical details: ${error.message}</small>
                        </div>
                    `;
                } else {
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>Error:</strong> Failed to load subject details.
                            <br><small>Technical details: ${error.message}</small>
                            <br><small>Please check that the API endpoint exists at: includes/api/subject-details.php</small>
                        </div>
                    `;
                }
            }
        }

        function generateSubjectDetailsHTML(subject) {
            const typeIcon = {
                'theory': '📖',
                'practical': '🛠️',
                'lab': '🧪'
            };

            const typeColor = {
                'theory': '#059669',
                'practical': '#d97706',
                'lab': '#7c3aed'
            };

            return `
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        <div class="subject-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                            ${subject.subject_code.substring(0, 2).toUpperCase()}
                        </div>
                        <h4>${subject.subject_code}</h4>
                        <p class="text-muted">${subject.subject_name}</p>
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <span class="badge" style="background: ${typeColor[subject.type]}20; color: ${typeColor[subject.type]};">
                                ${typeIcon[subject.type] || '📚'} ${subject.type.charAt(0).toUpperCase() + subject.type.slice(1)}
                            </span>
                            <span class="badge" style="background: rgba(6, 182, 212, 0.1); color: #0891b2;">
                                Year ${subject.year_level}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <strong>Department:</strong><br>
                                ${subject.department || 'Not specified'}
                            </div>
                            <div class="col-sm-6 mb-3">
                                <strong>Credits:</strong><br>
                                ${subject.credits} Credits
                            </div>
                            <div class="col-sm-6 mb-3">
                                <strong>Duration:</strong><br>
                                ${subject.duration_hours} Hours
                            </div>
                            <div class="col-sm-6 mb-3">
                                <strong>Semester:</strong><br>
                                Semester ${subject.semester}
                            </div>
                            ${subject.prerequisites ? `
                            <div class="col-12 mb-3">
                                <strong>Prerequisites:</strong><br>
                                ${subject.prerequisites}
                            </div>
                            ` : ''}
                            ${subject.description ? `
                            <div class="col-12 mb-3">
                                <strong>Description:</strong><br>
                                ${subject.description}
                            </div>
                            ` : ''}
                        </div>

                        <div class="mt-3">
                            <strong>Statistics:</strong><br>
                            <div class="d-flex gap-3 mt-2">
                                <span class="badge" style="background: rgba(6, 182, 212, 0.1); color: #0891b2;">
                                    👨‍🏫 ${subject.assigned_faculty_count || 0} Faculty
                                </span>
                                <span class="badge" style="background: rgba(236, 72, 153, 0.1); color: #db2777;">
                                    👥 ${subject.enrolled_students_count || 0} Students
                                </span>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
                                    📅 ${subject.scheduled_classes_count || 0} Classes
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="edit.php?id=${subject.subject_id}" class="btn-action btn-primary me-2">
                        ✏️ Edit Subject
                    </a>
                    <a href="assign-faculty.php?subject_id=${subject.subject_id}" class="btn-action btn-success me-2">
                        👨‍🏫 Assign Faculty
                    </a>
                    <a href="delete.php?id=${subject.subject_id}" class="btn-action btn-danger"
                       onclick="return confirm('Are you sure you want to delete this subject? This action cannot be undone.')">
                        🗑️ Delete Subject
                    </a>
                </div>
            `;
        }

        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Listen for theme changes
        window.addEventListener('themeChanged', function(event) {
            applyCurrentTheme();
        });

        // Handle bulk action form submission
        document.getElementById('bulkActionForm').addEventListener('submit', function(e) {
            const selectedSubjects = document.querySelectorAll('.subject-checkbox:checked');
            const actionType = this.querySelector('select[name="bulk_action_type"]').value;
            
            if (selectedSubjects.length === 0) {
                e.preventDefault();
                alert('Please select at least one subject to perform this action.');
                return;
            }
            
            if (!actionType) {
                e.preventDefault();
                alert('Please select an action to perform.');
                return;
            }
            
            const confirmMessage = `Are you sure you want to ${actionType} ${selectedSubjects.length} selected subject(s)?`;
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });

        // Auto-scroll functionality for subjects
        function autoScrollToUpdatedSubject() {
            const urlParams = new URLSearchParams(window.location.search);
            const updated = urlParams.get('updated');
            const updatedId = urlParams.get('updated_id');
            
            console.log('Auto-scroll check:', { updated, updatedId });
            
            if (updated === '1' && updatedId) {
                console.log('Looking for subject with ID:', updatedId);
                
                // Find the subject row by data-subject-id attribute
                const subjectRow = document.querySelector(`tr[data-subject-id="${updatedId}"]`);
                
                if (subjectRow) {
                    console.log('Found subject row:', subjectRow);
                    
                    // Scroll to the subject with smooth behavior
                    setTimeout(() => {
                        subjectRow.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        
                        // Add highlight effect
                        subjectRow.style.background = 'rgba(16, 185, 129, 0.2)';
                        subjectRow.style.transform = 'scale(1.02)';
                        subjectRow.style.transition = 'all 0.3s ease';
                        
                        // Remove highlight after 2 seconds
                        setTimeout(() => {
                            subjectRow.style.background = '';
                            subjectRow.style.transform = '';
                        }, 2000);
                        
                    }, 500);
                    
                    // Clean up URL parameters
                    const newUrl = window.location.pathname + 
                        (window.location.search.replace(/[?&](updated|updated_id)=[^&]*/g, '').replace(/^&/, '?') || '');
                    window.history.replaceState({}, '', newUrl);
                    
                } else {
                    console.log('Subject row not found for ID:', updatedId);
                }
            }
        }

        // Run auto-scroll when page loads
        document.addEventListener('DOMContentLoaded', autoScrollToUpdatedSubject);
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