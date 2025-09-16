<?php
/**
 * Admin Department Delete - Department Deletion Handler
 * Timetable Management System
 * 
 * Handles department deletion with proper validation and security checks
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

// Initialize variables
$error_message = '';
$success_message = '';
$targetDepartment = null;
$confirmationStep = false;

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle actual deletion
    handleDeletion();
} elseif (isset($_GET['id'])) {
    // Show confirmation page
    $targetDepartmentId = (int)$_GET['id'];
    $targetDepartment = getDepartmentDetails($targetDepartmentId);
    if ($targetDepartment) {
        $confirmationStep = true;
    } else {
        $error_message = "Department not found.";
    }
} else {
    // Redirect to department management if no ID provided
    header('Location: index.php');
    exit;
}

/**
 * Handle department deletion
 */
function handleDeletion() {
    global $currentUserId, $departmentManager, $success_message, $error_message;
    
    try {
        $targetDepartmentId = (int)$_POST['department_id'];
        $deleteType = $_POST['delete_type'] ?? 'deactivate';
        
        $targetDepartment = getDepartmentDetails($targetDepartmentId);
        if (!$targetDepartment) {
            throw new Exception("Department not found.");
        }
        
        // Check for dependencies
        $dependencies = checkDepartmentDependencies($targetDepartmentId);
        
        if ($deleteType === 'permanent') {
            // Permanent deletion (only for inactive departments with no dependencies)
            if ($targetDepartment['is_active']) {
                throw new Exception("Only inactive departments can be permanently deleted.");
            }
            
            if ($dependencies['has_dependencies']) {
                throw new Exception("Cannot permanently delete department with existing " . implode(', ', $dependencies['types']) . ".");
            }
            
            $result = $departmentManager->deleteDepartment($targetDepartmentId, $currentUserId);
            $action = "permanently deleted";
        } else {
            // Soft deletion (deactivation)
            if ($dependencies['has_dependencies']) {
                // Move users and resources to default department or mark as unassigned
                $result = $departmentManager->deactivateDepartmentWithReassignment($targetDepartmentId, $currentUserId);
            } else {
                $result = $departmentManager->changeDepartmentStatus($targetDepartmentId, false, $currentUserId);
            }
            $action = "deactivated";
        }
        
        if ($result['success']) {
            $success_message = "Department has been {$action} successfully.";
        } else {
            $error_message = $result['message'];
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Get department details for confirmation
 */
function getDepartmentDetails($departmentId) {
    global $db;
    
    try {
        return $db->fetchRow("
            SELECT d.*, 
                   CONCAT(f.first_name, ' ', f.last_name) as head_name,
                   f.employee_id as head_employee_id,
                   COUNT(DISTINCT u_all.user_id) as total_users,
                   COUNT(DISTINCT CASE WHEN u_all.role = 'faculty' THEN u_all.user_id END) as faculty_count,
                   COUNT(DISTINCT CASE WHEN u_all.role = 'student' THEN u_all.user_id END) as student_count,
                   COUNT(DISTINCT s.subject_id) as subject_count,
                   COUNT(DISTINCT c.classroom_id) as classroom_count
            FROM departments d
            LEFT JOIN faculty f ON d.department_head_id = f.faculty_id
            LEFT JOIN users u_all ON d.department_id = u_all.department_id
            LEFT JOIN subjects s ON d.department_id = s.department_id AND s.is_active = 1
            LEFT JOIN classrooms c ON d.department_id = c.department_id AND c.is_active = 1
            WHERE d.department_id = ?
            GROUP BY d.department_id
        ", [$departmentId]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check for department dependencies
 */
function checkDepartmentDependencies($departmentId) {
    global $db;
    
    $dependencies = [
        'has_dependencies' => false,
        'types' => [],
        'details' => []
    ];
    
    try {
        // Check for users
        $userCount = $db->fetchRow("SELECT COUNT(*) as count FROM users WHERE department_id = ? AND status = 'active'", [$departmentId])['count'];
        if ($userCount > 0) {
            $dependencies['has_dependencies'] = true;
            $dependencies['types'][] = 'active users';
            $dependencies['details'][] = "$userCount active user(s)";
        }
        
        // Check for subjects
        $subjectCount = $db->fetchRow("SELECT COUNT(*) as count FROM subjects WHERE department_id = ? AND is_active = 1", [$departmentId])['count'];
        if ($subjectCount > 0) {
            $dependencies['has_dependencies'] = true;
            $dependencies['types'][] = 'subjects';
            $dependencies['details'][] = "$subjectCount active subject(s)";
        }
        
        // Check for classrooms
        $classroomCount = $db->fetchRow("SELECT COUNT(*) as count FROM classrooms WHERE department_id = ? AND is_active = 1", [$departmentId])['count'];
        if ($classroomCount > 0) {
            $dependencies['has_dependencies'] = true;
            $dependencies['types'][] = 'classrooms';
            $dependencies['details'][] = "$classroomCount active classroom(s)";
        }
        
        // Check for timetables
        $timetableCount = $db->fetchRow("
            SELECT COUNT(*) as count 
            FROM timetables t 
            JOIN subjects s ON t.subject_id = s.subject_id 
            WHERE s.department_id = ? AND t.is_active = 1
        ", [$departmentId])['count'];
        if ($timetableCount > 0) {
            $dependencies['has_dependencies'] = true;
            $dependencies['types'][] = 'active timetables';
            $dependencies['details'][] = "$timetableCount active timetable(s)";
        }
        
    } catch (Exception $e) {
        error_log("Error checking dependencies: " . $e->getMessage());
    }
    
    return $dependencies;
}

// Set page title
$pageTitle = $confirmationStep ? "Delete Department - Confirmation" : "Delete Department";
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
            --sidebar-collapsed-width: 80px;
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
            margin-top: 1rem;
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

        /* Confirmation Container */
        .confirmation-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        [data-theme="dark"] .confirmation-container {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .confirmation-container {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Department Info Card */
        .department-info-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .department-info-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .department-info-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .department-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }

        .department-details h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .department-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .department-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-badge.inactive {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }

        [data-theme="dark"] .stat-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .stat-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Warning Card */
        .warning-card {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .warning-card h5 {
            color: var(--warning-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .warning-card ul {
            margin-bottom: 0;
            color: var(--text-secondary);
        }

        /* Deletion Options */
        .deletion-options {
            margin-bottom: 2rem;
        }

        .deletion-options h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .option-card {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .option-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .option-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .option-card:hover {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .option-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .option-card input[type="radio"] {
            margin-right: 1rem;
            margin-top: 0.25rem;
        }

        .option-card strong {
            color: var(--text-primary);
            display: block;
            margin-bottom: 0.5rem;
        }

        .option-card p {
            font-size: 0.875rem;
            color: var(--text-secondary);
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

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
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

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: calc(var(--navbar-height) + 1rem);
            }

            .confirmation-container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            /* Keep compact header inline on mobile */
            .header-card {
                flex-direction: row;
                text-align: left;
                gap: 1rem;
            }

            .department-info-card .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .department-icon {
                margin: 0 auto 1rem auto;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            /* Scope stacking and full-width buttons to confirmation container only */
            .confirmation-container .d-flex.gap-3 {
                flex-direction: column;
                gap: 1rem !important;
            }

            .confirmation-container .btn-action {
                justify-content: center;
                width: 100%;
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
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../../includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($confirmationStep && $targetDepartment): ?>
            <!-- Confirmation Page -->
            <div class="page-header">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Delete Department</h1>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <div class="confirmation-container glass-card slide-up">
                <!-- Department Information -->
                <div class="department-info-card">
                    <div class="d-flex align-items-center">
                        <div class="department-icon">
                            <?= strtoupper(substr($targetDepartment['department_code'], 0, 2)) ?>
                        </div>
                        
                        <div class="department-details flex-grow-1">
                            <h3><?= htmlspecialchars($targetDepartment['department_name']) ?> (<?= htmlspecialchars($targetDepartment['department_code']) ?>)</h3>
                            
                            <div class="department-meta">
                                <span><i class="fas fa-circle"></i> 
                                    <span class="status-badge <?= $targetDepartment['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $targetDepartment['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </span>
                                <?php if ($targetDepartment['head_name']): ?>
                                    <span><i class="fas fa-user-tie"></i> Head: <?= htmlspecialchars($targetDepartment['head_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($targetDepartment['contact_email']): ?>
                                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($targetDepartment['contact_email']) ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-calendar"></i> Created <?= date('M j, Y', strtotime($targetDepartment['created_at'])) ?></span>
                            </div>

                            <!-- Department Statistics -->
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-number"><?= $targetDepartment['total_users'] ?></div>
                                    <div class="stat-label">Total Users</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-number"><?= $targetDepartment['faculty_count'] ?></div>
                                    <div class="stat-label">Faculty</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-number"><?= $targetDepartment['student_count'] ?></div>
                                    <div class="stat-label">Students</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-number"><?= $targetDepartment['subject_count'] ?></div>
                                    <div class="stat-label">Subjects</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-number"><?= $targetDepartment['classroom_count'] ?></div>
                                    <div class="stat-label">Classrooms</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning -->
                <div class="warning-card">
                    <h5><i class="fas fa-exclamation-triangle"></i> Warning: You are about to delete department 
                        <strong><?= htmlspecialchars($targetDepartment['department_name']) ?></strong>. 
                    Please consider the following:</h5>
                    <ul>
                        <li><strong>Data Impact:</strong> Associated users, subjects, and resources will be affected</li>
                        <li><strong>System References:</strong> This may affect timetables, enrollments, and assignments</li>
                        <li><strong>User Assignment:</strong> Users will need to be reassigned to other departments</li>
                        <li><strong>Reversibility:</strong> Permanent deletion cannot be undone</li>
                        <li><strong>Alternative:</strong> Consider deactivation instead of permanent deletion</li>
                    </ul>
                </div>

                <!-- Check for dependencies -->
                <?php 
                $dependencies = checkDepartmentDependencies($targetDepartment['department_id']); 
                if ($dependencies['has_dependencies']): 
                ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    <strong>Dependencies Found:</strong><br>
                    This department has <?= implode(', ', $dependencies['details']) ?>. 
                    These will be reassigned or moved during deactivation.
                </div>
                <?php endif; ?>

                <!-- Deletion Options -->
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="department_id" value="<?= $targetDepartment['department_id'] ?>">
                    
                    <div class="deletion-options">
                        <h5>Choose Deletion Type:</h5>
                        
                        <div class="option-card selected" onclick="selectOption('deactivate')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="deactivate" checked>
                                <div>
                                    <strong><i class="fas fa-pause-circle"></i> Deactivate Department (Recommended)</strong>
                                    <p class="mb-0">Department will be deactivated but data preserved. Users and resources will be reassigned or marked as unassigned. Can be reactivated later if needed.</p>
                                </div>
                            </label>
                        </div>

                        <?php if (!$targetDepartment['is_active'] && !$dependencies['has_dependencies']): ?>
                        <div class="option-card" onclick="selectOption('permanent')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="permanent">
                                <div>
                                    <strong style="color: var(--error-color);"><i class="fas fa-trash"></i> Permanent Deletion</strong>
                                    <p class="mb-0">Completely remove department and all associated data. This action cannot be undone and may affect system integrity.</p>
                                </div>
                            </label>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <strong>Permanent deletion not available</strong><br>
                            Only inactive departments with no dependencies can be permanently deleted. 
                            Current status: <strong><?= $targetDepartment['is_active'] ? 'Active' : 'Inactive' ?></strong>
                            <?php if ($dependencies['has_dependencies']): ?>
                                <br>Dependencies: <?= implode(', ', $dependencies['types']) ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="index.php" class="btn-action btn-outline">
                            ✕ Cancel
                        </a>
                        <button type="button" class="btn-action btn-danger" onclick="confirmDeletion()">
                            🗑️ Proceed with Deletion
                        </button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Result Page Header (Success or Error) -->
            <div class="page-header">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <?php if (!empty($success_message)): ?>
                            <h1 class="page-title">✅ Department Updated</h1>
                            <p class="page-subtitle">The department has been processed successfully.</p>
                        <?php else: ?>
                            <h1 class="page-title">✕ Delete Department - Error</h1>
                            <p class="page-subtitle">Unable to process department deletion request</p>
                        <?php endif; ?>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger glass-card fade-in" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success glass-card fade-in" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Success!</strong> <?= htmlspecialchars($success_message) ?>
                </div>
                <div class="text-center">
                    <p>Redirecting to department management in 3 seconds...</p>
                    <a href="index.php" class="btn-action btn-primary">
                        🏷️ Return to Departments
                    </a>
                </div>
                <script>
                    // Auto-redirect after 3 seconds
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 3000);
                </script>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
            <!-- Error Card -->
            <div class="confirmation-container glass-card slide-up">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-circle" style="font-size: 4rem; color: var(--error-color);"></i>
                    </div>
                    <h3 class="mb-3">Unable to Delete Department</h3>
                    <p class="text-muted mb-4">
                        <?= htmlspecialchars($error_message) ?>
                    </p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="index.php" class="btn-action btn-primary">
                            🏢 Return to Departments
                        </a>
                    </div>
                </div>
            </div>
            <?php elseif (!empty($success_message)): ?>
            <!-- Success Card -->
            <div class="confirmation-container glass-card slide-up">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success-color);"></i>
                    </div>
                    <h3 class="mb-3">Success</h3>
                    <p class="text-muted mb-4"><?= htmlspecialchars($success_message) ?></p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="index.php" class="btn-action btn-primary">
                            🏢 Return to Departments
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Handle sidebar toggle events
            handleSidebarToggle();
            
            // Initialize option selection
            initializeOptionSelection();
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
         * Initialize option selection
         */
        function initializeOptionSelection() {
            // Set up click handlers for option cards
            document.querySelectorAll('.option-card').forEach(card => {
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        selectOption(radio.value);
                    }
                });
            });
        }

        /**
         * Handle option selection for deletion type
         */
        function selectOption(type) {
            // Remove selected class from all options
            document.querySelectorAll('.option-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to the card containing the selected radio
            const selectedRadio = document.querySelector(`input[name="delete_type"][value="${type}"]`);
            if (selectedRadio) {
                selectedRadio.checked = true;
                selectedRadio.closest('.option-card').classList.add('selected');
            }
        }
        
        /**
         * Confirm deletion with additional validation
         */
        function confirmDeletion() {
            const deleteTypeRadio = document.querySelector('input[name="delete_type"]:checked');
            if (!deleteTypeRadio) {
                alert('Please select a deletion type.');
                return;
            }
            
            const deleteType = deleteTypeRadio.value;
            const departmentName = '<?= addslashes($targetDepartment['department_name'] ?? '') ?>';
            const departmentCode = '<?= addslashes($targetDepartment['department_code'] ?? '') ?>';
            
            let message;
            if (deleteType === 'permanent') {
                message = `⚠️ PERMANENT DELETION CONFIRMATION\n\n` +
                         `You are about to PERMANENTLY DELETE department "${departmentName} (${departmentCode})"\n\n` +
                         `This action will:\n` +
                         `• Completely remove the department from the system\n` +
                         `• Delete all associated data and relationships\n` +
                         `• Remove any resource assignments\n` +
                         `• Cannot be undone\n\n` +
                         `Are you absolutely sure you want to proceed?`;
                         
                if (!confirm(message)) {
                    return;
                }
                
                // Additional confirmation for permanent deletion
                const confirmText = prompt('To confirm permanent deletion, type "DELETE" (in capitals):');
                if (confirmText !== 'DELETE') {
                    alert('Deletion cancelled. You must type "DELETE" exactly to confirm permanent deletion.');
                    return;
                }
            } else {
                message = `Deactivate department "${departmentName} (${departmentCode})"?\n\n` +
                         `This will:\n` +
                         `• Set the department to inactive status\n` +
                         `• Reassign users and resources as needed\n` +
                         `• Preserve all data for potential reactivation\n` +
                         `• Can be reversed later if needed\n\n` +
                         `Do you want to continue?`;
                         
                if (!confirm(message)) {
                    return;
                }
            }
            
            // Submit the form
            document.getElementById('deleteForm').submit();
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
        window.selectOption = selectOption;
        window.confirmDeletion = confirmDeletion;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>