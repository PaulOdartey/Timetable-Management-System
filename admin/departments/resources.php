<?php
/**
 * Admin Department Resources - Resource Management Interface
 * Timetable Management System
 * 
 * Professional interface for admin to manage department resources
 * including classrooms, faculty assignments, and resource sharing
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
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$departmentManager = new Department();

// Get department ID
$departmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Selection mode when no department id provided
$selectMode = $departmentId === 0;

// Initialize variables
$department = null;
$error_message = '';
$success_message = '';
$resources = [];
$availableClassrooms = [];
$availableFaculty = [];
$formData = [];

// Handle form submission for resource assignment (PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'assign_classroom':
                handleClassroomAssignment();
                break;
            case 'assign_faculty':
                handleFacultyAssignment();
                break;
            case 'update_sharing':
                handleResourceSharing();
                break;
            case 'remove_resource':
                handleResourceRemoval();
                break;
            default:
                throw new Exception('Invalid action specified.');
        }
        // On success, redirect with flash to avoid resubmission prompt
        $msg = !empty($success_message) ? $success_message : 'Action completed successfully.';
        redirect_with_flash('resources.php?id=' . $departmentId, 'success', $msg);
    } catch (Exception $e) {
        // On error, redirect with flash error
        redirect_with_flash('resources.php?id=' . $departmentId, 'error', $e->getMessage());
    }
}

/**
 * Handle classroom assignment to department
 */
function handleClassroomAssignment() {
    global $departmentId, $currentUserId, $db, $success_message, $error_message;
    
    $classroomIds = $_POST['classroom_ids'] ?? [];
    $sharingConditions = $_POST['sharing_conditions'] ?? '';
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    
    if (empty($classroomIds)) {
        throw new Exception('Please select at least one classroom to assign.');
    }
    
    $assignedCount = 0;
    $db->beginTransaction();
    
    try {
        foreach ($classroomIds as $classroomId) {
            // Check if classroom is already assigned
            $existing = $db->fetchRow("
                SELECT resource_id FROM department_resources 
                WHERE owner_department_id = ? AND resource_type = 'classroom' 
                AND resource_reference_id = ? AND is_active = 1
            ", [$departmentId, $classroomId]);
            
            if (!$existing) {
                // Assign classroom to department
                $db->execute("
                    INSERT INTO department_resources 
                    (owner_department_id, resource_type, resource_reference_id, 
                     sharing_conditions, start_date, end_date, created_by, created_at)
                    VALUES (?, 'classroom', ?, ?, ?, ?, ?, NOW())
                ", [$departmentId, $classroomId, $sharingConditions, $startDate, $endDate, $currentUserId]);
                
                // Update classroom department_id
                $db->execute("
                    UPDATE classrooms 
                    SET department_id = ?, is_shared = 0 
                    WHERE classroom_id = ?
                ", [$departmentId, $classroomId]);
                
                $assignedCount++;
            }
        }
        
        $db->commit();
        $success_message = "Successfully assigned {$assignedCount} classroom(s) to the department.";
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception("Failed to assign classrooms: " . $e->getMessage());
    }
}

/**
 * Handle faculty assignment to department
 */
function handleFacultyAssignment() {
    global $departmentId, $currentUserId, $db, $success_message, $error_message;
    
    $facultyIds = $_POST['faculty_ids'] ?? [];
    $sharingConditions = $_POST['sharing_conditions'] ?? '';
    
    if (empty($facultyIds)) {
        throw new Exception('Please select at least one faculty member to assign.');
    }
    
    $assignedCount = 0;
    $db->beginTransaction();
    
    try {
        foreach ($facultyIds as $facultyId) {
            // Check if faculty is already assigned
            $existing = $db->fetchRow("
                SELECT resource_id FROM department_resources 
                WHERE owner_department_id = ? AND resource_type = 'faculty' 
                AND resource_reference_id = ? AND is_active = 1
            ", [$departmentId, $facultyId]);
            
            if (!$existing) {
                // Assign faculty to department
                $db->execute("
                    INSERT INTO department_resources 
                    (owner_department_id, resource_type, resource_reference_id, 
                     sharing_conditions, created_by, created_at)
                    VALUES (?, 'faculty', ?, ?, ?, NOW())
                ", [$departmentId, $facultyId, $sharingConditions, $currentUserId]);
                
                $assignedCount++;
            }
        }
        
        $db->commit();
        $success_message = "Successfully assigned {$assignedCount} faculty member(s) to the department.";
        
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception("Failed to assign faculty: " . $e->getMessage());
    }
}

/**
 * Handle resource sharing updates
 */
function handleResourceSharing() {
    global $db, $currentUserId, $success_message;
    
    $resourceId = (int)$_POST['resource_id'];
    $sharedWithDept = $_POST['shared_with_department'] ?? null;
    $sharingConditions = $_POST['sharing_conditions'] ?? '';
    
    if (!$resourceId) {
        throw new Exception('Resource ID is required.');
    }
    
    $db->execute("
        UPDATE department_resources 
        SET shared_with_department_id = ?, sharing_conditions = ?
        WHERE resource_id = ?
    ", [$sharedWithDept ?: null, $sharingConditions, $resourceId]);
    
    $success_message = "Resource sharing settings updated successfully.";
}

/**
 * Handle resource removal
 */
function handleResourceRemoval() {
    global $db, $success_message;
    
    $resourceId = (int)$_POST['resource_id'];
    
    if (!$resourceId) {
        throw new Exception('Resource ID is required.');
    }
    
    $db->execute("
        UPDATE department_resources 
        SET is_active = 0 
        WHERE resource_id = ?
    ", [$resourceId]);
    
    $success_message = "Resource removed successfully.";
}

try {
    if (!$selectMode) {
        // Get department details
        $department = $db->fetchRow("
            SELECT d.*, 
                   CONCAT(f.first_name, ' ', f.last_name) as head_name,
                   f.employee_id as head_employee_id
            FROM departments d
            LEFT JOIN faculty f ON d.department_head_id = f.faculty_id
            WHERE d.department_id = ?
        ", [$departmentId]);
        
        if (!$department) {
            header('Location: index.php?error=' . urlencode('Department not found'));
            exit;
        }
        
        // Get current department resources
        $resources = $db->fetchAll("
            SELECT dr.*, 
                   CASE 
                       WHEN dr.resource_type = 'classroom' THEN 
                           CONCAT(c.room_number, ' - ', c.building, ' (', c.capacity, ' seats)')
                       WHEN dr.resource_type = 'faculty' THEN 
                           CONCAT(f.first_name, ' ', f.last_name, ' (', f.designation, ')')
                   END as resource_name,
                   CASE 
                       WHEN dr.resource_type = 'classroom' THEN c.type
                       WHEN dr.resource_type = 'faculty' THEN f.specialization
                   END as resource_details,
                   sd.department_name as shared_with_name
            FROM department_resources dr
            LEFT JOIN classrooms c ON dr.resource_type = 'classroom' AND dr.resource_reference_id = c.classroom_id
            LEFT JOIN faculty f ON dr.resource_type = 'faculty' AND dr.resource_reference_id = f.faculty_id
            LEFT JOIN departments sd ON dr.shared_with_department_id = sd.department_id
            WHERE dr.owner_department_id = ? AND dr.is_active = 1
            ORDER BY dr.resource_type, dr.created_at DESC
        ", [$departmentId]);
        
        // Get available classrooms (not assigned to any department)
        $availableClassrooms = $db->fetchAll("
            SELECT c.classroom_id, c.room_number, c.building, c.capacity, c.type, c.equipment
            FROM classrooms c
            WHERE c.is_active = 1 
            AND (c.department_id IS NULL OR c.department_id = ?)
            ORDER BY c.building, c.room_number
        ", [$departmentId]);
        
        // Get available faculty (not already assigned to this department)
        $availableFaculty = $db->fetchAll("
            SELECT f.faculty_id, f.first_name, f.last_name, f.employee_id, 
                   f.designation, f.specialization, f.department
            FROM faculty f
            INNER JOIN users u ON f.user_id = u.user_id
            WHERE u.status = 'active'
            AND f.faculty_id NOT IN (
                SELECT dr.resource_reference_id 
                FROM department_resources dr 
                WHERE dr.owner_department_id = ? 
                AND dr.resource_type = 'faculty' 
                AND dr.is_active = 1
            )
            ORDER BY f.first_name, f.last_name
        ", [$departmentId]);
        
        // Get all departments for sharing options
        $allDepartments = $db->fetchAll("
            SELECT department_id, department_name, department_code
            FROM departments 
            WHERE is_active = 1 AND department_id != ?
            ORDER BY department_name
        ", [$departmentId]);
    } else {
        // In selection mode, load departments list for chooser
        $departmentsList = $db->fetchAll("
            SELECT department_id, department_name, department_code
            FROM departments
            WHERE is_active = 1
            ORDER BY department_name
        ");
    }
} catch (Exception $e) {
    error_log("Department Resources Error: " . $e->getMessage());
    $error_message = "An error occurred while loading department resources.";
}

// Set page title
$pageTitle = "Department Resources - " . ($department['department_name'] ?? 'Unknown');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            --sidebar-collapsed-width: 80px;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
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
            padding-top: calc(var(--navbar-height) + 2rem);
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
        [data-theme="dark"] .glass-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .glass-card {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Department Info Header */
        .dept-info-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        [data-theme="dark"] .dept-info-header {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .dept-info-header {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .dept-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
            background: var(--primary-color);
        }

        .dept-details h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .dept-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .dept-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Resource Cards */
        .resource-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        [data-theme="dark"] .resource-section {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .resource-section {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .resource-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .resource-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .resource-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .resource-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .resource-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .resource-info {
            flex: 1;
        }

        .resource-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .resource-details {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }

        .resource-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .resource-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-label.required::after {
            content: ' *';
            color: var(--error-color);
            font-weight: 500;
            margin-left: 0.25rem;
        }

        .form-control, .form-select {
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
            background: rgba(255, 255, 255, 0.7);
        }

        /* Dark mode form controls */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
        }

        /* Light mode: stronger borders for visibility */
        body:not([data-theme="dark"]) .form-control,
        body:not([data-theme="dark"]) .form-select {
            border-color: #cbd5e1;
            border-width: 2px;
            background: #ffffff;
        }

        body:not([data-theme="dark"]) .form-control:focus,
        body:not([data-theme="dark"]) .form-select:focus {
            background: #ffffff;
            box-shadow: 0 0 0 4px var(--primary-color-alpha);
        }

        .form-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Multi-select styling */
        .multi-select-container {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 0.5rem;
        }

        [data-theme="dark"] .multi-select-container {
            border-color: var(--border-color);
        }

        .form-check {
            margin-bottom: 0.5rem;
        }

        .form-check-input {
            margin-top: 0.125rem;
        }

        .form-check-label {
            font-size: 0.875rem;
            color: var(--text-primary);
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

        /* Button Styles */
        .btn-action {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
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

        /* Resource Type Badges */
        .resource-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .resource-badge.classroom {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }

        .resource-badge.faculty {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        .resource-badge.shared {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        /* Empty state */
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: calc(var(--navbar-height) + 1rem);
            }

            .resource-section {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .header-card {
                flex-direction: row;
                text-align: left;
                gap: 1rem;
            }

            .dept-info-header {
                flex-direction: column;
                text-align: center;
            }

            .dept-meta {
                justify-content: center;
            }

            .resource-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .resource-actions {
                width: 100%;
                justify-content: flex-start;
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

        /* Tabs */
        .nav-tabs {
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--text-secondary);
            padding: 1rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            color: var(--text-primary);
            border-bottom-color: var(--primary-color-alpha);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: transparent;
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
                    <?php if ($selectMode): ?>
                        <h1 class="page-title">🏢 Select Department</h1>
                    <?php else: ?>
                        <h1 class="page-title">🏢 Department Resources</h1>
                    <?php endif; ?>
                </div>
                <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                    <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                </a>
            </div>
        </div>

        <!-- Alerts (Flash + local fallback) -->
        <?php 
            $flash_success = function_exists('flash_has') && flash_has('success') ? flash_get('success') : '';
            $flash_error = function_exists('flash_has') && flash_has('error') ? flash_get('error') : '';
        ?>
        <?php if (!empty($flash_success) || !empty($success_message)): ?>
            <div class="alert alert-success glass-card fade-in">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars(!empty($flash_success) ? $flash_success : $success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($flash_error) || !empty($error_message)): ?>
            <div class="alert alert-danger glass-card fade-in">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars(!empty($flash_error) ? $flash_error : $error_message) ?>
            </div>
        <?php endif; ?>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert.alert-success');
            if (successAlert) {
                // Auto-hide after 5 seconds with smooth fade-out
                setTimeout(() => {
                    successAlert.style.transition = 'opacity 300ms ease';
                    successAlert.style.opacity = '0';
                    successAlert.addEventListener('transitionend', () => {
                        if (successAlert && successAlert.parentNode) {
                            successAlert.parentNode.removeChild(successAlert);
                        }
                    }, { once: true });
                }, 5000);
            }
        });
        </script>

        <?php if ($selectMode): ?>
            <!-- Department Selection Grid -->
            <div class="resource-section fade-in">
                <h2 class="section-title"><i class="fas fa-building"></i> Departments</h2>
                <?php if (!empty($departmentsList)): ?>
                    <div class="row g-3">
                        <?php foreach ($departmentsList as $dept): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="resource-card">
                                    <div class="resource-header">
                                        <div class="resource-info">
                                            <div class="resource-name">
                                                <?= htmlspecialchars($dept['department_code']) ?> - <?= htmlspecialchars($dept['department_name']) ?>
                                            </div>
                                            <div class="resource-details">Manage classrooms, faculty and sharing</div>
                                        </div>
                                        <div class="resource-actions">
                                            <a class="btn-action btn-primary" href="resources.php?id=<?= (int)$dept['department_id'] ?>">
                                                <i class="fas fa-arrow-right"></i> Manage
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No active departments found.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <!-- Department Info Header -->
        <?php if ($department): ?>
        <div class="dept-info-header fade-in">
            <div class="dept-icon">
                🏢
            </div>
            <div class="dept-details flex-grow-1">
                <h2><?= htmlspecialchars($department['department_code']) ?> - <?= htmlspecialchars($department['department_name']) ?></h2>
                <div class="dept-meta">
                    <?php if ($department['head_name']): ?>
                        <span><i class="fas fa-user-tie"></i> Head: <?= htmlspecialchars($department['head_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($department['contact_email']): ?>
                        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($department['contact_email']) ?></span>
                    <?php endif; ?>
                    <?php if ($department['building_location']): ?>
                        <span><i class="fas fa-building"></i> <?= htmlspecialchars($department['building_location']) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-calendar"></i> Established <?= $department['established_date'] ? date('M j, Y', strtotime($department['established_date'])) : 'N/A' ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs fade-in" id="resourceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="current-resources-tab" data-bs-toggle="tab" 
                        data-bs-target="#current-resources" type="button" role="tab">
                    <i class="fas fa-list"></i> Current Resources
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="assign-classroom-tab" data-bs-toggle="tab" 
                        data-bs-target="#assign-classroom" type="button" role="tab">
                    <i class="fas fa-door-open"></i> Assign Classrooms
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="assign-faculty-tab" data-bs-toggle="tab" 
                        data-bs-target="#assign-faculty" type="button" role="tab">
                    <i class="fas fa-user-plus"></i> Assign Faculty
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content slide-up" id="resourceTabsContent">
            <!-- Current Resources Tab -->
            <div class="tab-pane fade show active" id="current-resources" role="tabpanel">
                <div class="resource-section glass-card">
                    <h3 class="section-title">
                        <i class="fas fa-boxes"></i> Current Department Resources
                    </h3>
                    
                    <?php if (!empty($resources)): ?>
                        <?php 
                        $groupedResources = [];
                        foreach ($resources as $resource) {
                            $groupedResources[$resource['resource_type']][] = $resource;
                        }
                        ?>
                        
                        <?php foreach ($groupedResources as $type => $typeResources): ?>
                            <?php 
                                // Proper pluralization and labeling
                                $label = '';
                                switch ($type) {
                                    case 'faculty':
                                        $label = 'Faculty';
                                        break;
                                    case 'classroom':
                                        $label = 'Classrooms';
                                        break;
                                    default:
                                        $label = ucfirst($type) . 's';
                                }
                            ?>
                            <h4 class="mb-3 mt-4">
                                <i class="fas fa-<?= $type === 'classroom' ? 'door-open' : 'users' ?>"></i>
                                <?= $label ?> (<?= count($typeResources) ?>)
                            </h4>
                            
                            <?php foreach ($typeResources as $resource): ?>
                                <div class="resource-card">
                                    <div class="resource-header">
                                        <div class="resource-info">
                                            <div class="resource-name">
                                                <?= htmlspecialchars($resource['resource_name']) ?>
                                                <span class="resource-badge <?= $resource['resource_type'] ?>">
                                                    <?= ucfirst($resource['resource_type']) ?>
                                                </span>
                                                <?php if ($resource['shared_with_department_id']): ?>
                                                    <?php 
                                                        $sharedWith = $resource['shared_with_name'] ?? 'Another department';
                                                        $cond = trim((string)($resource['sharing_conditions'] ?? ''));
                                                        $title = 'Shared with: ' . $sharedWith . ($cond !== '' ? " | Conditions: " . $cond : '');
                                                    ?>
                                                    <span class="resource-badge shared" title="<?= htmlspecialchars($title) ?>">Shared</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($resource['resource_details']): ?>
                                                <div class="resource-details">
                                                    <?= htmlspecialchars($resource['resource_details']) ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="resource-meta">
                                                <span><i class="fas fa-calendar-plus"></i> Added: <?= date('M j, Y', strtotime($resource['created_at'])) ?></span>
                                                
                                                <?php if ($resource['shared_with_name']): ?>
                                                    <span><i class="fas fa-share"></i> Shared with: <?= htmlspecialchars($resource['shared_with_name']) ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if ($resource['sharing_conditions']): ?>
                                                    <span><i class="fas fa-info-circle"></i> Conditions: <?= htmlspecialchars($resource['sharing_conditions']) ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if ($resource['start_date'] || $resource['end_date']): ?>
                                                    <span><i class="fas fa-clock"></i> 
                                                        <?= $resource['start_date'] ? date('M j, Y', strtotime($resource['start_date'])) : 'No start' ?> - 
                                                        <?= $resource['end_date'] ? date('M j, Y', strtotime($resource['end_date'])) : 'No end' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="resource-actions">
                                            <button type="button" class="btn-action btn-warning btn-sm" 
                                                    onclick="editResourceSharing(<?= $resource['resource_id'] ?>, '<?= htmlspecialchars($resource['shared_with_department_id'] ?? '') ?>', '<?= htmlspecialchars($resource['sharing_conditions'] ?? '') ?>')">
                                                <i class="fas fa-share-alt"></i> Share
                                            </button>
                                            <button type="button" class="btn-action btn-danger btn-sm" 
                                                    onclick="removeResource(<?= $resource['resource_id'] ?>, '<?= htmlspecialchars($resource['resource_name']) ?>')">
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h4>No Resources Assigned</h4>
                            <p>This department doesn't have any resources assigned yet. Use the tabs above to assign classrooms and faculty.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assign Classroom Tab -->
            <div class="tab-pane fade" id="assign-classroom" role="tabpanel">
                <div class="resource-section glass-card">
                    <h3 class="section-title">
                        <i class="fas fa-door-open"></i> Assign Classrooms to Department
                    </h3>
                    
                    <?php if (!empty($availableClassrooms)): ?>
                        <form method="POST" id="assignClassroomForm">
                            <input type="hidden" name="action" value="assign_classroom">
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Sharing Conditions</label>
                                    <textarea class="form-control" name="sharing_conditions" rows="3" 
                                              placeholder="Specify any conditions for resource usage or sharing..."></textarea>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle"></i> 
                                        Optional conditions or restrictions for resource usage
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="end_date">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label required">Select Classrooms to Assign</label>
                                <div class="multi-select-container">
                                    <?php foreach ($availableClassrooms as $classroom): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="classroom_ids[]" value="<?= $classroom['classroom_id'] ?>" 
                                                   id="classroom_<?= $classroom['classroom_id'] ?>">
                                            <label class="form-check-label" for="classroom_<?= $classroom['classroom_id'] ?>">
                                                <strong><?= htmlspecialchars($classroom['room_number']) ?> - <?= htmlspecialchars($classroom['building']) ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    Capacity: <?= $classroom['capacity'] ?> | Type: <?= ucfirst($classroom['type']) ?>
                                                    <?php if ($classroom['equipment']): ?>
                                                        | Equipment: <?= htmlspecialchars($classroom['equipment']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    Select one or more classrooms to assign to this department
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3 justify-content-end">
                                <button type="button" class="btn-action btn-outline" onclick="clearClassroomSelection()">
                                    🔄 Clear Selection
                                </button>
                                <button type="submit" class="btn-action btn-success">
                                    ✅ Assign Selected Classrooms
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-door-closed"></i>
                            <h4>No Available Classrooms</h4>
                            <p>All classrooms are currently assigned to departments or unavailable.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assign Faculty Tab -->
            <div class="tab-pane fade" id="assign-faculty" role="tabpanel">
                <div class="resource-section glass-card">
                    <h3 class="section-title">
                        <i class="fas fa-user-plus"></i> Assign Faculty to Department
                    </h3>
                    
                    <?php if (!empty($availableFaculty)): ?>
                        <form method="POST" id="assignFacultyForm">
                            <input type="hidden" name="action" value="assign_faculty">
                            
                            <div class="mb-4">
                                <label class="form-label">Sharing Conditions</label>
                                <textarea class="form-control" name="sharing_conditions" rows="3" 
                                          placeholder="Specify any conditions for faculty assignment or collaboration..."></textarea>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    Optional conditions for faculty assignment or cross-department collaboration
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label required">Select Faculty to Assign</label>
                                <div class="multi-select-container">
                                    <?php foreach ($availableFaculty as $faculty): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="faculty_ids[]" value="<?= $faculty['faculty_id'] ?>" 
                                                   id="faculty_<?= $faculty['faculty_id'] ?>">
                                            <label class="form-check-label" for="faculty_<?= $faculty['faculty_id'] ?>">
                                                <strong><?= htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']) ?></strong>
                                                (<?= htmlspecialchars($faculty['employee_id']) ?>)
                                                <br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($faculty['designation']) ?> | Current Dept: <?= htmlspecialchars($faculty['department']) ?>
                                                    <?php if ($faculty['specialization']): ?>
                                                        <br>Specialization: <?= htmlspecialchars($faculty['specialization']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    Select faculty members to assign collaborative access to this department
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3 justify-content-end">
                                <button type="button" class="btn-action btn-outline" onclick="clearFacultySelection()">
                                    🔄 Clear Selection
                                </button>
                                <button type="submit" class="btn-action btn-success">
                                    ✅ Assign Selected Faculty
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <h4>No Available Faculty</h4>
                            <p>All faculty members are currently assigned or there are no available faculty to assign.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Resource Sharing Modal -->
    <div class="modal fade" id="sharingModal" tabindex="-1" aria-labelledby="sharingModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="sharingModalLabel" style="color: var(--text-primary);">
                        <i class="fas fa-share-alt"></i> Resource Sharing Settings
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="sharingForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_sharing">
                        <input type="hidden" name="resource_id" id="sharingResourceId">
                        
                        <div class="mb-3">
                            <label for="sharingDepartment" class="form-label">Share with Department</label>
                            <select class="form-select" name="shared_with_department" id="sharingDepartment">
                                <option value="">-- Not Shared --</option>
                                <?php foreach ($allDepartments ?? [] as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>">
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Select a department to share this resource with, or leave blank to make it exclusive
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sharingConditions" class="form-label">Sharing Conditions</label>
                            <textarea class="form-control" name="sharing_conditions" id="sharingConditions" rows="3" 
                                      placeholder="Specify conditions for sharing this resource..."></textarea>
                            <div class="form-text">
                                Optional conditions or restrictions for resource sharing
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                        <button type="button" class="btn-action btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-action btn-success">
                            <i class="fas fa-save"></i> Update Sharing
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Remove Resource Modal -->
    <div class="modal fade" id="removeModal" tabindex="-1" aria-labelledby="removeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="removeModalLabel" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-triangle text-warning"></i> Remove Resource
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="removeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="remove_resource">
                        <input type="hidden" name="resource_id" id="removeResourceId">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> You are about to remove this resource from the department.
                        </div>
                        
                        <p>Are you sure you want to remove <strong id="removeResourceName"></strong> from this department?</p>
                        
                        <p class="text-muted small">
                            <i class="fas fa-info-circle"></i>
                            This action will remove the resource assignment but won't delete the actual resource. 
                            The resource will become available for assignment to other departments.
                        </p>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                        <button type="button" class="btn-action btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-action btn-danger">
                            <i class="fas fa-trash"></i> Remove Resource
                        </button>
                    </div>
                </form>
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
            
            // Initialize form validation
            initializeFormValidation();
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
         * Initialize form validation
         */
        function initializeFormValidation() {
            // Validate classroom assignment form
            const classroomForm = document.getElementById('assignClassroomForm');
            if (classroomForm) {
                classroomForm.addEventListener('submit', function(e) {
                    const selectedClassrooms = document.querySelectorAll('input[name="classroom_ids[]"]:checked');
                    if (selectedClassrooms.length === 0) {
                        e.preventDefault();
                        showError('Please select at least one classroom to assign.');
                        return false;
                    }
                });
            }

            // Validate faculty assignment form
            const facultyForm = document.getElementById('assignFacultyForm');
            if (facultyForm) {
                facultyForm.addEventListener('submit', function(e) {
                    const selectedFaculty = document.querySelectorAll('input[name="faculty_ids[]"]:checked');
                    if (selectedFaculty.length === 0) {
                        e.preventDefault();
                        showError('Please select at least one faculty member to assign.');
                        return false;
                    }
                });
            }
        }

        /**
         * Clear classroom selection
         */
        function clearClassroomSelection() {
            const checkboxes = document.querySelectorAll('input[name="classroom_ids[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }

        /**
         * Clear faculty selection
         */
        function clearFacultySelection() {
            const checkboxes = document.querySelectorAll('input[name="faculty_ids[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }

        /**
         * Edit resource sharing
         */
        function editResourceSharing(resourceId, sharedWithDept, conditions) {
            document.getElementById('sharingResourceId').value = resourceId;
            document.getElementById('sharingDepartment').value = sharedWithDept || '';
            document.getElementById('sharingConditions').value = conditions || '';
            
            new bootstrap.Modal(document.getElementById('sharingModal')).show();
        }

        /**
         * Remove resource
         */
        function removeResource(resourceId, resourceName) {
            document.getElementById('removeResourceId').value = resourceId;
            document.getElementById('removeResourceName').textContent = resourceName;
            
            new bootstrap.Modal(document.getElementById('removeModal')).show();
        }

        /**
         * Show error message
         */
        function showError(message) {
            // Remove existing alerts
            document.querySelectorAll('.alert-danger').forEach(alert => alert.remove());
            
            // Create new alert
            const alertHtml = `
                <div class="alert alert-danger glass-card fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </div>
            `;
            
            // Insert at top of main content after page header
            const pageHeader = document.querySelector('.page-header');
            pageHeader.insertAdjacentHTML('afterend', alertHtml);
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
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
        window.clearClassroomSelection = clearClassroomSelection;
        window.clearFacultySelection = clearFacultySelection;
        window.editResourceSharing = editResourceSharing;
        window.removeResource = removeResource;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>