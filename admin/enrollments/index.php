<?php
/**
 * Admin Enrollment Management - Enrollment Management Interface
 * Timetable Management System
 * 
 * Professional interface for admin to manage student enrollments with
 * comprehensive filtering, statistics, and bulk operations
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Enrollment.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$enrollmentManager = new Enrollment();

// Initialize variables
$enrollments = [];
$enrollmentStats = [];
$departments = [];
$academicYears = [];
$error_message = '';
$success_message = '';
$selectedStatus = $_GET['status'] ?? '';
$selectedDepartment = $_GET['department'] ?? '';
$selectedSemester = $_GET['semester'] ?? '';
$selectedAcademicYear = $_GET['academic_year'] ?? '';
$selectedSection = $_GET['section'] ?? '';

// Flash success message from previous actions
if (isset($_SESSION['flash_message'])) {
    $success_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Handle bulk actions
if ($_POST['action'] ?? '' === 'bulk_action' && !empty($_POST['selected_enrollments'])) {
    $selectedEnrollments = $_POST['selected_enrollments'];
    $bulkAction = $_POST['bulk_action_type'];
    
    try {
        if ($bulkAction === 'mark_completed') {
            foreach ($selectedEnrollments as $enrollmentId) {
                $enrollmentManager->updateEnrollmentStatus($enrollmentId, 'completed');
            }
            $success_message = "Selected enrollments marked as completed successfully.";
        } elseif ($bulkAction === 'mark_dropped') {
            foreach ($selectedEnrollments as $enrollmentId) {
                $enrollmentManager->updateEnrollmentStatus($enrollmentId, 'dropped');
            }
            $success_message = "Selected enrollments marked as dropped successfully.";
        } elseif ($bulkAction === 'reactivate') {
            foreach ($selectedEnrollments as $enrollmentId) {
                $enrollmentManager->updateEnrollmentStatus($enrollmentId, 'enrolled');
            }
            $success_message = "Selected enrollments reactivated successfully.";
        }
    } catch (Exception $e) {
        $error_message = "Bulk action failed: " . $e->getMessage();
    }
}

// Build filter array
$filters = [];
if (!empty($selectedStatus)) $filters['status'] = $selectedStatus;
if (!empty($selectedDepartment)) $filters['department'] = $selectedDepartment;
if (!empty($selectedSemester)) $filters['semester'] = $selectedSemester;
if (!empty($selectedAcademicYear)) $filters['academic_year'] = $selectedAcademicYear;
if (!empty($selectedSection)) $filters['section'] = $selectedSection;

try {
    // Get enrollment data
    $enrollments = $enrollmentManager->getAllEnrollments($filters);
    $enrollmentStats = $enrollmentManager->getEnrollmentStats();
    $departments = $enrollmentManager->getDepartments();
    $academicYears = $enrollmentManager->getAcademicYears();
} catch (Exception $e) {
    $error_message = "Error loading enrollment data: " . $e->getMessage();
    $enrollments = [];
    $enrollmentStats = [
        'total_enrollments' => 0,
        'active_enrollments' => 0,
        'dropped_enrollments' => 0,
        'completed_enrollments' => 0,
        'recent_enrollments' => 0,
        'subjects_with_enrollments' => 0,
        'students_enrolled' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Enrollment Management - Admin Panel</title>
    
    <!-- External CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">


    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
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

        /* Page Header - Regular header that scrolls normally */
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
        .stat-icon.enrolled { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.dropped { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .stat-icon.completed { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .stat-icon.recent { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.subjects { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .stat-icon.students { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }

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
            flex-wrap: wrap;
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

        /* Bulk Actions - Match index.php design */
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
        .enrollments-container {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            overflow: hidden;
        }

        [data-theme="dark"] .enrollments-container {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        /* Scrollable wrapper to enable sticky header */
        .table-responsive-custom {
            max-height: 65vh;
            overflow-y: auto;
            overflow-x: auto;
        }

        .enrollments-table {
            width: 100%;
            margin: 0;
        }

        .enrollments-table thead {
            background: rgba(255, 255, 255, 0.3);
        }

        [data-theme="dark"] .enrollments-table thead {
            background: rgba(0, 0, 0, 0.4);
        }

        /* Sticky table header */
        .enrollments-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--bg-primary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        /* Dark mode header background alignment */
        [data-theme="dark"] .enrollments-table thead th {
            background: var(--bg-secondary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
        }

        .enrollments-table th {
            padding: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        [data-theme="dark"] .enrollments-table th {
            color: var(--text-primary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .enrollments-table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
            color: var(--text-primary);
        }

        [data-theme="dark"] .enrollments-table td {
            color: var(--text-primary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .enrollments-table tbody tr {
            transition: all 0.3s ease;
        }

        .enrollments-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        [data-theme="dark"] .enrollments-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Column widths and alignment for compact layout */
        .enrollments-table th:nth-child(1),
        .enrollments-table td:nth-child(1) { /* checkbox */
            width: 44px;
            text-align: center;
            white-space: nowrap;
        }

        .enrollments-table th:nth-child(9),
        .enrollments-table td:nth-child(9) { /* Actions */
            width: 130px;
            text-align: center;
            white-space: nowrap;
        }

        /* Student Info Styling */
        .student-info {
            display: flex;
            align-items: center;
        }

        .student-avatar {
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

        .student-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .student-meta {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Subject Info Styling */
        .subject-info h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .subject-meta {
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

        .badge-status-enrolled {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .badge-status-dropped {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .badge-status-completed {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }

        .badge-status-failed {
            background: rgba(107, 114, 128, 0.1);
            color: #4b5563;
        }

        .badge-outline {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: var(--text-primary);
        }

        [data-theme="dark"] .badge-outline {
            border-color: var(--glass-border);
        }

        .enrollments-table th.section-col,
        .enrollments-table td.section-col,
        .enrollments-table th.semester-col,
        .enrollments-table td.semester-col {
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
        }

        .badge.badge-outline {
            min-width: 48px;
        }

        /* Action Buttons - Match index.php design */
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
        .enrollment-card {
            display: none;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        [data-theme="dark"] .enrollment-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        .enrollment-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .enrollment-card-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .enrollment-card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
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
            .enrollments-container {
                display: none;
            }

            .enrollment-card {
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
            [data-theme="dark"] .enrollment-card {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }

            .enrollment-card-details {
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
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-card glass-card fade-in">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">📚 Enrollment Management</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="enroll.php" class="btn-action btn-primary">
                            ➕ Enroll Student
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon total">📚</div>
                <div class="stat-number"><?= $enrollmentStats['total_enrollments'] ?? 0 ?></div>
                <div class="stat-label">Total Enrollments</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon enrolled">✅</div>
                <div class="stat-number"><?= $enrollmentStats['active_enrollments'] ?? 0 ?></div>
                <div class="stat-label">Active Enrollments</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon dropped">❌</div>
                <div class="stat-number"><?= $enrollmentStats['dropped_enrollments'] ?? 0 ?></div>
                <div class="stat-label">Dropped</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon completed">🎓</div>
                <div class="stat-number"><?= $enrollmentStats['completed_enrollments'] ?? 0 ?></div>
                <div class="stat-label">Completed</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon recent">⏰</div>
                <div class="stat-number"><?= $enrollmentStats['recent_enrollments'] ?? 0 ?></div>
                <div class="stat-label">Recent (7 days)</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon subjects">📖</div>
                <div class="stat-number"><?= $enrollmentStats['subjects_with_enrollments'] ?? 0 ?></div>
                <div class="stat-label">Active Subjects</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon students">👥</div>
                <div class="stat-number"><?= $enrollmentStats['students_enrolled'] ?? 0 ?></div>
                <div class="stat-label">Enrolled Students</div>
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
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="enrolled" <?= $selectedStatus === 'enrolled' ? 'selected' : '' ?>>Enrolled</option>
                        <option value="dropped" <?= $selectedStatus === 'dropped' ? 'selected' : '' ?>>Dropped</option>
                        <option value="completed" <?= $selectedStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="failed" <?= $selectedStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="department" class="form-label">Department</label>
                    <select name="department" id="department" class="filter-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept['department']) ?>" 
                                    <?= $selectedDepartment === $dept['department'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['department']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="semester" class="form-label">Semester</label>
                    <select name="semester" id="semester" class="filter-select">
                        <option value="">All Semesters</option>
                        <?php for ($i = 1; $i <= 2; $i++): ?>
                            <option value="<?= $i ?>" <?= $selectedSemester == $i ? 'selected' : '' ?>>
                                Semester <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="academic_year" class="form-label">Academic Year</label>
                    <select name="academic_year" id="academic_year" class="filter-select">
                        <option value="">All Years</option>
                        <?php foreach ($academicYears as $year): ?>
                            <option value="<?= htmlspecialchars($year) ?>" 
                                    <?= $selectedAcademicYear === $year ? 'selected' : '' ?>>
                                <?= htmlspecialchars($year) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="section" class="form-label">Section</label>
                    <div class="d-flex align-items-center gap-2 flex-nowrap">
                        <select name="section" id="section" class="filter-select">
                            <option value="">All Sections</option>
                            <option value="A" <?= $selectedSection === 'A' ? 'selected' : '' ?>>Section A</option>
                            <option value="B" <?= $selectedSection === 'B' ? 'selected' : '' ?>>Section B</option>
                            <option value="C" <?= $selectedSection === 'C' ? 'selected' : '' ?>>Section C</option>
                        </select>
                        <button type="button" onclick="clearFilters()" class="btn-action btn-outline btn-sm d-inline-flex align-items-center gap-1" title="Clear filters"><span>🔄</span><span>Clear</span></button>
                    </div>
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
                        <option value="mark_completed">Mark as Completed</option>
                        <option value="mark_dropped">Mark as Dropped</option>
                        <option value="reactivate">Reactivate</option>
                    </select>
                    <button type="submit" class="btn-action btn-primary">Apply to Selected</button>
                    <button type="button" onclick="clearSelection()" class="btn-action btn-outline btn-sm">Clear Selection</button>
                </div>
                <div id="selectedEnrollments"></div>
            </form>
        </div>

        <!-- Enrollments Table -->
        <div class="enrollments-container glass-card slide-up">
            <div class="table-responsive-custom">
            <table class="enrollments-table">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="selectAll" onchange="toggleAllEnrollments()">
                        </th>
                        <th>Student</th>
                        <th>Subject</th>
                        <th class="section-col">Section</th>
                        <th class="semester-col">Semester</th>
                        <th>Academic Year</th>
                        <th>Status</th>
                        <th>Enrolled Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($enrollments)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                    No enrollments found. <a href="enroll.php">Enroll your first student</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($enrollments as $enrollment): ?>
                            <tr class="enrollment-row"
                                data-status="<?= strtolower($enrollment['status']) ?>"
                                data-department="<?= strtolower($enrollment['student_department']) ?>"
                                data-semester="<?= strtolower((string)$enrollment['semester']) ?>"
                                data-year="<?= strtolower($enrollment['academic_year']) ?>"
                                data-section="<?= strtolower(trim((string)($enrollment['section'] ?? ''))) ?>"
                                data-student="<?= strtolower($enrollment['student_first_name'] . ' ' . $enrollment['student_last_name']) ?>"
                                data-subject="<?= strtolower($enrollment['subject_code'] . ' ' . $enrollment['subject_name']) ?>">
                                <td>
                                    <input type="checkbox" name="selected_enrollments[]" 
                                           value="<?= $enrollment['enrollment_id'] ?>" 
                                           class="enrollment-checkbox"
                                           onchange="updateBulkActions()">
                                </td>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?= strtoupper(substr($enrollment['student_first_name'], 0, 1) . substr($enrollment['student_last_name'], 0, 1)) ?>
                                        </div>
                                        <div class="student-details">
                                            <h6><?= htmlspecialchars($enrollment['student_first_name'] . ' ' . $enrollment['student_last_name']) ?></h6>
                                            <p class="student-meta">
                                                <?= htmlspecialchars($enrollment['student_number']) ?> • 
                                                <?= htmlspecialchars($enrollment['student_department']) ?> • 
                                                Year <?= $enrollment['year_of_study'] ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="subject-info">
                                        <h6><?= htmlspecialchars($enrollment['subject_code']) ?></h6>
                                        <p class="subject-meta">
                                            <?= htmlspecialchars($enrollment['subject_name']) ?><br>
                                            <small><?= $enrollment['credits'] ?> Credits • <?= htmlspecialchars($enrollment['subject_department']) ?></small>
                                        </p>
                                    </div>
                                </td>
                                <td class="section-col">
                                    <?php $sec = trim((string)($enrollment['section'] ?? '')); ?>
                                    <span class="badge badge-outline"><?= $sec !== '' ? strtoupper(htmlspecialchars($sec)) : '—' ?></span>
                                </td>
                                <td class="semester-col">
                                    <span class="badge badge-outline">Sem <?= htmlspecialchars((string)($enrollment['semester'] ?? '')) ?></span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($enrollment['academic_year']) ?>
                                </td>
                                <td>
                                    <span class="badge badge-status-<?= $enrollment['status'] ?>">
                                        <?= ucfirst($enrollment['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= date('M j, Y', strtotime($enrollment['enrollment_date'])) ?><br>
                                    <small class="text-muted"><?= date('g:i A', strtotime($enrollment['enrollment_date'])) ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-center flex-wrap">
                                        <button type="button" class="btn-action btn-info btn-sm" 
                                                onclick="viewEnrollmentDetails(<?= $enrollment['enrollment_id'] ?>)" title="View Details">
                                            👁️
                                        </button>

                                        <a href="edit.php?id=<?= $enrollment['enrollment_id'] ?>" 
                                           class="btn-action btn-outline btn-sm" title="Edit Enrollment">
                                            ✏️
                                        </a>

                                        <a href="delete.php?id=<?= $enrollment['enrollment_id'] ?><?= $selectedStatus ? '&status=' . urlencode($selectedStatus) : '' ?><?= $selectedDepartment ? '&department=' . urlencode($selectedDepartment) : '' ?><?= $selectedSemester ? '&semester=' . urlencode($selectedSemester) : '' ?><?= $selectedAcademicYear ? '&academic_year=' . urlencode($selectedAcademicYear) : '' ?><?= $selectedSection ? '&section=' . urlencode($selectedSection) : '' ?>" 
                                           class="btn-action btn-danger btn-sm" 
                                           title="Delete Enrollment"
                                           onclick="return confirm('Are you sure you want to delete this enrollment?')">
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
        <?php foreach ($enrollments as $enrollment): ?>
            <div class="enrollment-card glass-card slide-up"
                 data-status="<?= strtolower($enrollment['status']) ?>"
                 data-department="<?= strtolower($enrollment['student_department']) ?>"
                 data-semester="<?= strtolower((string)$enrollment['semester']) ?>"
                 data-year="<?= strtolower($enrollment['academic_year']) ?>"
                 data-section="<?= strtolower(trim((string)($enrollment['section'] ?? ''))) ?>"
                 data-student="<?= strtolower($enrollment['student_first_name'] . ' ' . $enrollment['student_last_name']) ?>"
                 data-subject="<?= strtolower($enrollment['subject_code'] . ' ' . $enrollment['subject_name']) ?>">
                <div class="enrollment-card-header">
                    <div class="student-info">
                        <div class="student-avatar">
                            <?= strtoupper(substr($enrollment['student_first_name'], 0, 1) . substr($enrollment['student_last_name'], 0, 1)) ?>
                        </div>
                        <div class="student-details">
                            <h6><?= htmlspecialchars($enrollment['student_first_name'] . ' ' . $enrollment['student_last_name']) ?></h6>
                            <p class="student-meta"><?= htmlspecialchars($enrollment['student_number']) ?></p>
                        </div>
                    </div>
                    <span class="badge badge-status-<?= $enrollment['status'] ?>">
                        <?= ucfirst($enrollment['status']) ?>
                    </span>
                </div>

                <div class="enrollment-card-details">
                    <div>
                        <strong>Subject:</strong><br>
                        <?= htmlspecialchars($enrollment['subject_code'] . ' - ' . $enrollment['subject_name']) ?>
                    </div>
                    <div>
                        <strong>Section:</strong><br>
                        <?= htmlspecialchars($enrollment['section']) ?>
                    </div>
                    <div>
                        <strong>Semester:</strong><br>
                        Semester <?= $enrollment['semester'] ?>
                    </div>
                    <div>
                        <strong>Academic Year:</strong><br>
                        <?= htmlspecialchars($enrollment['academic_year']) ?>
                    </div>
                    <div>
                        <strong>Department:</strong><br>
                        <?= htmlspecialchars($enrollment['student_department']) ?>
                    </div>
                    <div>
                        <strong>Enrolled:</strong><br>
                        <?= date('M j, Y', strtotime($enrollment['enrollment_date'])) ?>
                    </div>
                </div>

                <div class="enrollment-card-actions">
                    <input type="checkbox" name="selected_enrollments[]" 
                           value="<?= $enrollment['enrollment_id'] ?>" 
                           class="enrollment-checkbox"
                           onchange="updateBulkActions()">
                    <button onclick="viewEnrollmentDetails(<?= $enrollment['enrollment_id'] ?>)" 
                            class="btn-action btn-info btn-sm"
                            title="View Details">
                        👁️ View
                    </button>
                    <a href="edit.php?id=<?= $enrollment['enrollment_id'] ?>" 
                       class="btn-action btn-outline btn-sm"
                       title="Edit Enrollment">
                        ✏️ Edit
                    </a>
                    <a href="delete.php?id=<?= $enrollment['enrollment_id'] ?>" 
                       class="btn-action btn-danger btn-sm"
                       title="Delete Enrollment"
                       onclick="return confirm('Are you sure you want to delete this enrollment?')">
                        🗑️ Delete
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </main>

    <!-- Enrollment Details Modal -->
    <div class="modal fade" id="enrollmentDetailsModal" tabindex="-1" aria-labelledby="enrollmentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content glass-card">
                <div class="modal-header">
                    <h5 class="modal-title" id="enrollmentDetailsModalLabel">📚 Enrollment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="enrollmentDetailsContent">
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

            // Initialize instant client-side search/filters
            if (typeof initializeSearchAndFilters === 'function') {
                initializeSearchAndFilters();
            }
        });


        // Sidebar JS handlers removed to prevent unintended expansion on single link clicks.

        // Apply current theme
        function applyCurrentTheme() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', currentTheme);
        }

        // Bulk Actions Management
        function toggleAllEnrollments() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.enrollment-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.enrollment-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedEnrollments = document.getElementById('selectedEnrollments');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                
                // Update hidden inputs for selected enrollments
                selectedEnrollments.innerHTML = '';
                checkboxes.forEach(checkbox => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'selected_enrollments[]';
                    hiddenInput.value = checkbox.value;
                    selectedEnrollments.appendChild(hiddenInput);
                });
            } else {
                bulkActions.classList.remove('show');
            }
            
            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.enrollment-checkbox');
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
                selectAll.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
            }
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.enrollment-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            
            updateBulkActions();
        }

        function confirmBulkAction() {
            const checkboxes = document.querySelectorAll('.enrollment-checkbox:checked');
            const actionSelect = document.querySelector('select[name="bulk_action_type"]');
            
            if (checkboxes.length === 0) {
                alert('Please select at least one enrollment.');
                return false;
            }
            
            if (!actionSelect.value) {
                alert('Please select an action.');
                return false;
            }
            
            const actionText = actionSelect.options[actionSelect.selectedIndex].text;
            const count = checkboxes.length;
            
            return confirm(`Are you sure you want to "${actionText}" for ${count} selected enrollment(s)?`);
        }

        // Theme switcher support
        document.addEventListener('themeChanged', function(e) {
            document.documentElement.setAttribute('data-theme', e.detail.theme);
        });

        // Instant client-side filtering (no reload)
        function initializeSearchAndFilters() {
            const statusEl = document.getElementById('status');
            const deptEl = document.getElementById('department');
            const semEl = document.getElementById('semester');
            const yearEl = document.getElementById('academic_year');
            const sectionEl = document.getElementById('section');

            // Prevent auto-scroll on initial load; only scroll after user interacts
            let hasScrolledToResults = false;
            let userInteractedWithFilters = false;
            function scrollToResultsOnce() {
                if (hasScrolledToResults || !userInteractedWithFilters) return;
                const container = document.querySelector('.enrollments-container') || document.querySelector('.enrollment-card');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    hasScrolledToResults = true;
                }
            }

            function filterEnrollments() {
                const vStatus = (statusEl?.value || '').toLowerCase();
                const vDept = (deptEl?.value || '').toLowerCase();
                const vSem = (semEl?.value || '').toLowerCase();
                const vYear = (yearEl?.value || '').toLowerCase();
                const vSection = (sectionEl?.value || '').toLowerCase();

                const rows = document.querySelectorAll('.enrollment-row');
                const cards = document.querySelectorAll('.enrollment-card');

                let visibleCount = 0;
                function apply(elements) {
                    elements.forEach(el => {
                        const mStatus = !vStatus || (el.dataset.status || '').toLowerCase() === vStatus;
                        const mDept = !vDept || (el.dataset.department || '').toLowerCase() === vDept;
                        const mSem = !vSem || (el.dataset.semester || '').toLowerCase() === vSem;
                        const mYear = !vYear || (el.dataset.year || '').toLowerCase() === vYear;
                        const mSec = !vSection || (el.dataset.section || '').toLowerCase() === vSection;
                        const show = mStatus && mDept && mSem && mYear && mSec;
                        el.style.display = show ? '' : 'none';
                        if (show) visibleCount++;
                    });
                }

                apply(rows);
                apply(cards);

                // Hide alerts while filtering to keep header clean
                const alerts = document.querySelectorAll('.alert');
                const filtering = vStatus || vDept || vSem || vYear || vSection;
                alerts.forEach(a => { a.style.display = filtering ? 'none' : ''; });

                scrollToResultsOnce();
            }

            // Attach listeners (mark as user interaction before filtering)
            [statusEl, deptEl, semEl, yearEl, sectionEl].forEach(el => {
                if (!el) return;
                el.addEventListener('change', () => {
                    userInteractedWithFilters = true;
                    filterEnrollments();
                });
            });

            // Initial pass (apply preselected values WITHOUT scrolling)
            // userInteractedWithFilters remains false here, so no auto-scroll occurs
            filterEnrollments();
        }

        function clearFilters() {
            const statusEl = document.getElementById('status');
            const deptEl = document.getElementById('department');
            const semEl = document.getElementById('semester');
            const yearEl = document.getElementById('academic_year');
            const sectionEl = document.getElementById('section');
            if (statusEl) statusEl.value = '';
            if (deptEl) deptEl.value = '';
            if (semEl) semEl.value = '';
            if (yearEl) yearEl.value = '';
            if (sectionEl) sectionEl.value = '';
            if (typeof initializeSearchAndFilters === 'function') {
                initializeSearchAndFilters();
            }
        }

        // API Functions for Enrollment Details
        async function viewEnrollmentDetails(enrollmentId) {
            const modal = new bootstrap.Modal(document.getElementById('enrollmentDetailsModal'));
            const modalContent = document.getElementById('enrollmentDetailsContent');
            
            // Show modal with loading state
            modal.show();
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="loading-shimmer" style="height: 200px; border-radius: 8px;"></div>
                    <p class="mt-2 text-muted">Loading enrollment details...</p>
                </div>
            `;
            
            try {
                const apiUrl = `../../includes/api/enrollment-details.php?id=${enrollmentId}`;
                console.log('Fetching from:', apiUrl); // Debug log
                
                const response = await fetch(apiUrl);
                console.log('Response status:', response.status); // Debug log
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Response data:', data); // Debug log
                
                if (data.success) {
                    modalContent.innerHTML = formatEnrollmentDetails(data.enrollment);
                } else {
                    modalContent.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>Error:</strong> ${data.message || 'Failed to load enrollment details'}
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

        function formatEnrollmentDetails(enrollment) {
            const statusBadgeClass = `badge-status-${enrollment.status}`;
            const enrollmentDate = new Date(enrollment.enrollment_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            return `
                <div class="row">
                    <!-- Student Information -->
                    <div class="col-md-6">
                        <div class="info-section mb-4">
                            <h6 class="section-title mb-3">👨‍🎓 Student Information</h6>
                            <div class="d-flex align-items-center mb-3">
                                <div class="student-avatar me-3" style="width: 50px; height: 50px; font-size: 1.1rem;">
                                    ${enrollment.student_first_name.charAt(0)}${enrollment.student_last_name.charAt(0)}
                                </div>
                                <div>
                                    <h6 class="mb-1">${enrollment.student_first_name} ${enrollment.student_last_name}</h6>
                                    <small class="text-muted">${enrollment.student_number}</small>
                                </div>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <strong>Department:</strong> ${enrollment.student_department}
                                </div>
                                <div class="info-item">
                                    <strong>Year of Study:</strong> Year ${enrollment.year_of_study}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subject Information -->
                    <div class="col-md-6">
                        <div class="info-section mb-4">
                            <h6 class="section-title mb-3">📚 Subject Information</h6>
                            <div class="info-grid">
                                <div class="info-item">
                                    <strong>Subject Code:</strong> ${enrollment.subject_code}
                                </div>
                                <div class="info-item">
                                    <strong>Subject Name:</strong> ${enrollment.subject_name}
                                </div>
                                <div class="info-item">
                                    <strong>Credits:</strong> ${enrollment.credits} Credits
                                </div>
                                <div class="info-item">
                                    <strong>Department:</strong> ${enrollment.subject_department}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enrollment Details -->
                <div class="info-section mb-4">
                    <h6 class="section-title mb-3">📋 Enrollment Details</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="info-item">
                                <strong>Section:</strong> ${enrollment.section}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <strong>Semester:</strong> Semester ${enrollment.semester}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <strong>Academic Year:</strong> ${enrollment.academic_year}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-item">
                                <strong>Status:</strong> <span class="badge ${statusBadgeClass}">${enrollment.status.charAt(0).toUpperCase() + enrollment.status.slice(1)}</span>
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
                                <strong>Enrollment Date:</strong> ${enrollmentDate}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Enrolled By:</strong> ${enrollment.enrolled_by_username || 'System'}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-4 d-flex gap-2 justify-content-end">
                    <a href="edit.php?id=${enrollment.enrollment_id}" class="btn-action btn-outline">
                        ✏️ Edit Enrollment
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
                </style>
            `;
        }
    </script>
</body>
</html>