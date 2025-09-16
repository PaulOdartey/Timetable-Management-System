<?php
/**
 * Admin Departments Edit - Department Edit Interface
 * Timetable Management System
 * 
 * Professional interface for admin to edit existing departments
 * with comprehensive information management and validation
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

// Get department ID to edit
$editDepartmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$editDepartmentId) {
    header('Location: index.php?error=' . urlencode('Department ID is required'));
    exit;
}

// Initialize variables
$department = null;
$error_message = '';
$success_message = '';
$formData = [];
$availableHeads = [];
$buildingLocations = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error
        $formData = $_POST;
        
        // Basic validation
        $required_fields = ['department_code', 'department_name'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Additional validation using Department class
        $validation = $departmentManager->validateDepartmentData($_POST, $editDepartmentId);
        if (!$validation['is_valid']) {
            throw new Exception(implode(' ', $validation['errors']));
        }
        
        // Prepare update data
        $updateData = [
            'department_code' => strtoupper(trim($_POST['department_code'])),
            'department_name' => trim($_POST['department_name']),
            'department_head_id' => !empty($_POST['department_head_id']) ? (int)$_POST['department_head_id'] : null,
            'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
            'established_date' => !empty($_POST['established_date']) ? $_POST['established_date'] : null,
            'contact_email' => !empty($_POST['contact_email']) ? trim($_POST['contact_email']) : null,
            'contact_phone' => !empty($_POST['contact_phone']) ? trim($_POST['contact_phone']) : null,
            'building_location' => !empty($_POST['building_location']) ? trim($_POST['building_location']) : null,
            'budget_allocation' => !empty($_POST['budget_allocation']) ? floatval($_POST['budget_allocation']) : null
        ];
        
        // Update department
        $result = $departmentManager->updateDepartment($editDepartmentId, $updateData, $currentUserId);
        
        if ($result['success']) {
            $success_message = $result['message'];
            // Redirect after short delay to show success message
            header("refresh:2;url=index.php");
        } else {
            $error_message = $result['message'];
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

try {
    // Get department details
    $department = $departmentManager->getDepartmentById($editDepartmentId);
    
    if (!$department) {
        header('Location: index.php?error=' . urlencode('Department not found'));
        exit;
    }
    
    // Get per-department statistics for the header cards
    $deptStats = $departmentManager->getDepartmentStats($editDepartmentId);
    if (is_array($deptStats)) {
        // Merge stats into $department so existing template keys work
        $department = array_merge($department, $deptStats);
    }

    // Get available faculty for department head selection (include current head if set)
    $availableHeads = $departmentManager->getAvailableDepartmentHeads($department['department_head_id'] ?? null);
    
    // Get building locations for dropdown
    $buildingLocations = $departmentManager->getBuildingLocations();
    
} catch (Exception $e) {
    error_log("Edit Department Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the department.";
}

// Set page title
$pageTitle = "Edit Department";
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
            color: var(--text-primary);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
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

        /* Department Info Header */
        .department-info-header {
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

        [data-theme="dark"] .department-info-header {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .department-info-header {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .department-icon {
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

        .department-details h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .department-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .department-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Form Container */
        .form-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        [data-theme="dark"] .form-container {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .form-container {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] .form-section {
            border-bottom-color: var(--border-color);
        }

        [data-theme="light"] .form-section {
            border-bottom-color: var(--border-color);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .form-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form Controls */
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-label.required::after {
            content: ' *';
            color: var(--error-color);
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

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
            background: rgba(255, 255, 255, 0.7);
        }

        /* Dark mode form controls */
        [data-theme="dark"] .form-control {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .form-control:focus {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        [data-theme="dark"] .form-select {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .form-select:focus {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
        }

        /* Light mode: stronger input/select borders for visibility */
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: calc(var(--navbar-height) + 1rem);
            }

            .form-container {
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

            .department-info-header {
                flex-direction: column;
                text-align: center;
            }

            .department-meta {
                justify-content: center;
            }

            .form-container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body data-theme="light">
    <!-- Include Navbar -->
    <?php include '../../includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content fade-in">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-card glass-card fade-in">
                <div class="header-text">
                    <h1 class="page-title">🏢 Edit Department</h1>
                </div>
                <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                    <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Department Info Header -->
        <?php if ($department): ?>
        <div class="department-info-header">
            <div class="department-icon">
                <i class="fas fa-building"></i>
            </div>
            <div class="department-details flex-grow-1">
                <h2><?= htmlspecialchars($department['department_code']) ?> - <?= htmlspecialchars($department['department_name']) ?></h2>
                <div class="department-meta">
                    <?php if ($department['head_name']): ?>
                        <span><i class="fas fa-user-tie"></i> Head: <?= htmlspecialchars($department['head_name']) ?></span>
                    <?php else: ?>
                        <span><i class="fas fa-user-slash"></i> No Department Head</span>
                    <?php endif; ?>
                    
                    <?php if ($department['building_location']): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($department['building_location']) ?></span>
                    <?php endif; ?>
                    
                    <span><i class="fas fa-calendar"></i> Created <?= date('M j, Y', strtotime($department['created_at'])) ?></span>
                    
                    <?php if ($department['established_date']): ?>
                        <span><i class="fas fa-flag"></i> Est. <?= date('Y', strtotime($department['established_date'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Department Statistics -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <div class="stat-value"><?= $department['active_faculty'] ?? 0 ?></div>
                <div class="stat-label">Active Faculty</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $department['active_students'] ?? 0 ?></div>
                <div class="stat-label">Active Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $department['subject_count'] ?? 0 ?></div>
                <div class="stat-label">Subjects</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $department['total_classrooms'] ?? 0 ?></div>
                <div class="stat-label">Classrooms</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="form-container glass-card">
            <form method="POST" action="" id="editDepartmentForm">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">📋 Basic Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department_code" class="form-label required">Department Code</label>
                            <input type="text" class="form-control" id="department_code" name="department_code" 
                                   value="<?= htmlspecialchars($formData['department_code'] ?? $department['department_code'] ?? '') ?>" 
                                   placeholder="CS, MATH, ENG" maxlength="10" style="text-transform: uppercase;" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                2-10 characters, letters and numbers only
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department_name" class="form-label required">Department Name</label>
                            <input type="text" class="form-control" id="department_name" name="department_name" 
                                   value="<?= htmlspecialchars($formData['department_name'] ?? $department['department_name'] ?? '') ?>" 
                                   placeholder="Computer Science" maxlength="100" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Brief description of the department..."><?= htmlspecialchars($formData['description'] ?? $department['description'] ?? '') ?></textarea>
                        <div class="form-text">
                            <i class="fas fa-edit"></i> 
                            Optional description for department overview
                        </div>
                    </div>
                </div>

                <!-- Management Information -->
                <div class="form-section">
                    <h3 class="form-section-title">👥 Management</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department_head_id" class="form-label">Department Head</label>
                            <select class="form-select" id="department_head_id" name="department_head_id">
                                <option value="">No Department Head</option>
                                
                                <!-- Current head (if exists and not in available list) -->
                                <?php if ($department['department_head_id'] && $department['head_name']): ?>
                                    <option value="<?= $department['department_head_id'] ?>" 
                                            <?= ($formData['department_head_id'] ?? $department['department_head_id']) == $department['department_head_id'] ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($department['head_name']) ?> (<?= htmlspecialchars($department['head_employee_id']) ?>) - Current
                                    </option>
                                <?php endif; ?>
                                
                                <?php foreach ($availableHeads as $head): ?>
                                    <option value="<?= htmlspecialchars($head['faculty_id']) ?>" 
                                            <?= ($formData['department_head_id'] ?? '') == ($head['faculty_id'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($head['full_name'] ?? '')) ?> (<?= htmlspecialchars($head['employee_id'] ?? '') ?>)
                                        <?php if (!empty($head['department'])): ?>
                                            - <?= htmlspecialchars($head['department']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-user-tie"></i> 
                                Select an active faculty member to lead this department
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="established_date" class="form-label">Established Date</label>
                            <input type="date" class="form-control" id="established_date" name="established_date" 
                                   value="<?= $formData['established_date'] ?? $department['established_date'] ?? '' ?>" 
                                   max="<?= date('Y-m-d') ?>">
                            <div class="form-text">
                                <i class="fas fa-calendar"></i> 
                                When was this department established?
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="form-section">
                    <h3 class="form-section-title">📞 Contact Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                   value="<?= htmlspecialchars($formData['contact_email'] ?? $department['contact_email'] ?? '') ?>" 
                                   placeholder="department@university.edu">
                            <div class="form-text">
                                <i class="fas fa-envelope"></i> 
                                Official email for department inquiries
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                   value="<?= htmlspecialchars($formData['contact_phone'] ?? $department['contact_phone'] ?? '') ?>" 
                                   placeholder="+233 20 123 4567" maxlength="15">
                            <div class="form-text">
                                <i class="fas fa-phone"></i> 
                                Primary contact number
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location & Resources -->
                <div class="form-section">
                    <h3 class="form-section-title">🏗️ Location & Resources</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="building_location" class="form-label">Building Location</label>
                            <input type="text" class="form-control" id="building_location" name="building_location" 
                                   value="<?= htmlspecialchars($formData['building_location'] ?? $department['building_location'] ?? '') ?>" 
                                   placeholder="Main Building, Science Complex" 
                                   list="building_locations">
                            
                            <datalist id="building_locations">
                                <?php foreach ($buildingLocations as $location): ?>
                                    <option value="<?= htmlspecialchars($location) ?>">
                                <?php endforeach; ?>
                            </datalist>
                            
                            <div class="form-text">
                                <i class="fas fa-map-marker-alt"></i> 
                                Primary building or location for this department
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="budget_allocation" class="form-label">Budget Allocation</label>
                            <div class="input-group">
                                <span class="input-group-text">₵</span>
                                <input type="number" class="form-control" id="budget_allocation" name="budget_allocation" 
                                       value="<?= $formData['budget_allocation'] ?? $department['budget_allocation'] ?? '' ?>" 
                                       placeholder="0.00" min="0" step="0.01">
                            </div>
                            <div class="form-text">
                                <i class="fas fa-money-bill"></i> 
                                Annual budget allocation in Ghanaian Cedis (₵)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-section">
                    <div class="d-flex gap-3 justify-content-end flex-wrap">
                        <a href="index.php" class="btn-action btn-outline">
                            ❌ Cancel
                        </a>
                        <button type="button" class="btn-action btn-outline" onclick="resetForm()">
                            🔄 Reset Changes
                        </button>
                        <button type="button" class="btn-action btn-warning" onclick="validateForm()">
                            ✅ Validate
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn">
                            💾 Update Department
                        </button>
                    </div>
                </div>
            </form>
        </div>
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
            
            // Initialize form validation
            initializeFormValidation();
            
            // Initialize department code uppercase conversion
            initializeDepartmentCodeFormat();
            
            // Initialize budget formatting
            initializeBudgetFormatting();
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
                    mainContent.classList.toggle('expanded');
                });
            }
        }

        /**
         * Initialize form validation
         */
        function initializeFormValidation() {
            const form = document.getElementById('editDepartmentForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function(e) {
                if (!validateFormData()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                submitBtn.disabled = true;
            });
        }

        /**
         * Initialize department code formatting
         */
        function initializeDepartmentCodeFormat() {
            const deptCodeInput = document.getElementById('department_code');
            
            deptCodeInput.addEventListener('input', function() {
                // Convert to uppercase and remove invalid characters
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });
        }

        /**
         * Initialize budget formatting
         */
        function initializeBudgetFormatting() {
            const budgetInput = document.getElementById('budget_allocation');
            
            budgetInput.addEventListener('input', function() {
                // Ensure positive values only
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        }

        /**
         * Validate form inputs
         */
        function validateFormData() {
            const deptCode = document.getElementById('department_code').value.trim();
            const deptName = document.getElementById('department_name').value.trim();
            const contactEmail = document.getElementById('contact_email').value.trim();
            const contactPhone = document.getElementById('contact_phone').value.trim();
            const budgetAllocation = document.getElementById('budget_allocation').value;
            const establishedDate = document.getElementById('established_date').value;
            
            // Check required fields
            if (!deptCode) {
                showError('Department code is required.');
                return false;
            }
            
            if (!deptName) {
                showError('Department name is required.');
                return false;
            }
            
            // Validate department code format
            if (!/^[A-Z0-9]{2,10}$/.test(deptCode)) {
                showError('Department code must be 2-10 characters long and contain only uppercase letters and numbers.');
                return false;
            }
            
            // Validate department name length
            if (deptName.length > 100) {
                showError('Department name cannot exceed 100 characters.');
                return false;
            }
            
            // Validate contact email if provided
            if (contactEmail && !isValidEmail(contactEmail)) {
                showError('Please enter a valid contact email address.');
                return false;
            }
            
            // Validate phone number if provided
            if (contactPhone && contactPhone.replace(/[^0-9]/g, '').length > 15) {
                showError('Phone number cannot exceed 15 digits.');
                return false;
            }
            
            // Validate budget allocation
            if (budgetAllocation && parseFloat(budgetAllocation) < 0) {
                showError('Budget allocation cannot be negative.');
                return false;
            }
            
            // Validate established date
            if (establishedDate) {
                const date = new Date(establishedDate);
                const now = new Date();
                
                if (date > now) {
                    showError('Established date cannot be in the future.');
                    return false;
                }
            }
            
            return true;
        }

        /**
         * Validate form without submitting
         */
        function validateForm() {
            if (validateFormData()) {
                showSuccess('Form validation passed! All fields are valid.');
            }
        }

        /**
         * Reset form to original values
         */
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes? This will restore the original values.')) {
                location.reload();
            }
        }

        /**
         * Validate email format
         */
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        /**
         * Show success message
         */
        function showSuccess(message) {
            // Remove existing alerts
            document.querySelectorAll('.alert-success').forEach(alert => alert.remove());
            
            // Create new alert
            const alertHtml = `
                <div class="alert alert-success fade-in">
                    <i class="fas fa-check-circle me-2"></i>
                    ${message}
                </div>
            `;
            
            // Insert at top of main content
            const mainContent = document.querySelector('.main-content');
            const pageHeader = document.querySelector('.page-header');
            pageHeader.insertAdjacentHTML('afterend', alertHtml);
            
            // Scroll to alert
            document.querySelector('.alert-success').scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                const alert = document.querySelector('.alert-success');
                if (alert) {
                    alert.remove();
                }
            }, 5000);
        }

        /**
         * Show error message
         */
        function showError(message) {
            // Remove existing alerts
            document.querySelectorAll('.alert-danger').forEach(alert => alert.remove());
            
            // Create new alert
            const alertHtml = `
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </div>
            `;
            
            // Insert at top of main content
            const mainContent = document.querySelector('.main-content');
            const pageHeader = document.querySelector('.page-header');
            pageHeader.insertAdjacentHTML('afterend', alertHtml);
            
            // Scroll to alert
            document.querySelector('.alert-danger').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        /**
         * Format currency input
         */
        function formatCurrency(input) {
            let value = input.value.replace(/[^0-9.]/g, '');
            
            // Ensure only one decimal point
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            
            // Limit decimal places to 2
            if (parts[1] && parts[1].length > 2) {
                value = parts[0] + '.' + parts[1].substring(0, 2);
            }
            
            input.value = value;
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
        window.validateForm = validateForm;
        window.resetForm = resetForm;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>              