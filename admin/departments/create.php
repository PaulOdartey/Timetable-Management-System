<?php
/**
 * Admin Departments Create - Department Creation Interface
 * Timetable Management System
 * 
 * Professional interface for admin to create new departments
 * with comprehensive information and validation
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
$error_message = '';
$success_message = '';
$formData = [];
$availableHeads = [];
// Track if we used the fallback heads query so the template can render helpful hints
$usedFallbackHeads = false;

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
        
        // Department code validation
        $deptCode = strtoupper(trim($_POST['department_code']));
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $deptCode)) {
            throw new Exception('Department code must be 2-10 characters long and contain only uppercase letters and numbers.');
        }
        
        // Department name validation
        if (strlen($_POST['department_name']) > 100) {
            throw new Exception('Department name cannot exceed 100 characters.');
        }
        
        // Email validation if provided
        if (!empty($_POST['contact_email']) && !filter_var($_POST['contact_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid contact email address.');
        }
        
        // Budget validation if provided
        if (!empty($_POST['budget_allocation']) && (float)$_POST['budget_allocation'] < 0) {
            throw new Exception('Budget allocation cannot be negative.');
        }
        
        // Date validation if provided
        if (!empty($_POST['established_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $_POST['established_date']);
            if (!$date || $date > new DateTime()) {
                throw new Exception('Please enter a valid established date that is not in the future.');
            }
        }
        
        // Prepare department data
        $departmentData = [
            'department_code' => $deptCode,
            'department_name' => trim($_POST['department_name']),
            'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
            'department_head_id' => !empty($_POST['department_head_id']) ? (int)$_POST['department_head_id'] : null,
            'established_date' => !empty($_POST['established_date']) ? $_POST['established_date'] : null,
            'contact_email' => !empty($_POST['contact_email']) ? trim($_POST['contact_email']) : null,
            'contact_phone' => !empty($_POST['contact_phone']) ? trim($_POST['contact_phone']) : null,
            'building_location' => !empty($_POST['building_location']) ? trim($_POST['building_location']) : null,
            'budget_allocation' => !empty($_POST['budget_allocation']) ? (float)$_POST['budget_allocation'] : null
        ];
        
        // Create department
        $result = $departmentManager->createDepartment($departmentData, $userId);
        
        if ($result['success']) {
            $success_message = $result['message'];
            $formData = []; // Clear form data on success
            
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
    // Get available faculty members for department head selection
    $availableHeads = $departmentManager->getAvailableDepartmentHeads();
    $usedFallbackHeads = false;
    // Fallback: if none eligible (likely no faculty or all already heads), fetch all active faculty
    if (empty($availableHeads)) {
        $usedFallbackHeads = true;
        $availableHeads = $db->fetchAll("
            SELECT 
                f.faculty_id,
                CONCAT(f.first_name, ' ', f.last_name) AS full_name,
                f.employee_id,
                f.designation,
                CASE WHEN d.department_id IS NOT NULL THEN 1 ELSE 0 END AS is_head_of_any
            FROM faculty f
            INNER JOIN users u ON u.user_id = f.user_id
            LEFT JOIN departments d ON d.department_head_id = f.faculty_id AND d.is_active = 1
            WHERE u.role = 'faculty'
              AND u.status = 'active'
              AND (f.is_active = 1 OR f.is_active IS NULL)
            ORDER BY f.first_name ASC, f.last_name ASC
        ", []);
    }
    
    // Get existing building locations for dropdown
    $buildingLocations = $departmentManager->getBuildingLocations();
    
} catch (Exception $e) {
    error_log("Create Department Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the form data: " . htmlspecialchars($e->getMessage());
}

// Set page title
$pageTitle = "Create Department";
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
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            margin-left: 70px;
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
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color-alpha);
            position: relative;
        }

        .form-section-title::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--primary-color);
        }

        /* Form Controls */
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        /* Character Counter */
        .character-counter {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-align: right;
            margin-top: 0.25rem;
        }

        .character-counter.near-limit {
            color: var(--warning-color);
        }

        .character-counter.at-limit {
            color: var(--error-color);
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

        /* Input Groups */
        .input-group {
            position: relative;
            margin-bottom: 1rem;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            z-index: 2;
        }

        .input-icon + .form-control {
            padding-left: 2.5rem;
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

            .form-container {
                padding: 1rem;
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
<body data-theme="light">
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
                    <h1 class="page-title">🏢 Create Department</h1>
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
                <br><small>Redirecting to department list...</small>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Create Department Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" id="createDepartmentForm" novalidate autocomplete="off">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">📋 Basic Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department_code" class="form-label required">Department Code</label>
                            <div class="input-group">
                                <i class="fas fa-tag input-icon"></i>
                                <input type="text" class="form-control" id="department_code" name="department_code" 
                                       value="<?= htmlspecialchars($formData['department_code'] ?? '') ?>" 
                                       maxlength="10" required style="text-transform: uppercase;" autocomplete="off">
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                2-10 characters, letters and numbers only. Will be converted to uppercase.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department_name" class="form-label required">Department Name</label>
                            <div class="input-group">
                                <i class="fas fa-university input-icon"></i>
                                <input type="text" class="form-control" id="department_name" name="department_name" 
                                       value="<?= htmlspecialchars($formData['department_name'] ?? '') ?>" 
                                       maxlength="100" required autocomplete="off">
                            </div>
                            <div class="character-counter" id="nameCounter">0 / 100</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <div class="input-group">
                            <i class="fas fa-align-left input-icon"></i>
                            <textarea class="form-control" id="description" name="description" rows="3" autocomplete="off"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                        </div>
                        <div class="character-counter" id="descCounter">0 / 500</div>
                    </div>
                </div>

                <!-- Leadership & Contact -->
                <div class="form-section">
                    <h3 class="form-section-title">👑 Leadership & Contact</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department_head_id" class="form-label">Department Head</label>
                            <div class="input-group">
                                <i class="fas fa-user-tie input-icon"></i>
                                <select class="form-select" id="department_head_id" name="department_head_id">
                                    <option value="">Select Department Head (Optional)</option>
                                    <?php if (!empty($availableHeads)): ?>
                                        <?php if (!empty($usedFallbackHeads)): ?>
                                            <?php
                                                $eligible = [];
                                                $alreadyHeads = [];
                                                foreach ($availableHeads as $head) {
                                                    $isHead = isset($head['is_head_of_any']) && (int)$head['is_head_of_any'] === 1;
                                                    if ($isHead) { $alreadyHeads[] = $head; } else { $eligible[] = $head; }
                                                }
                                            ?>
                                            <?php if (!empty($eligible)): ?>
                                                <optgroup label="Eligible Faculty">
                                                    <?php foreach ($eligible as $head): ?>
                                                        <option value="<?= $head['faculty_id'] ?>"
                                                                <?= ($formData['department_head_id'] ?? '') == $head['faculty_id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($head['full_name'] . ' (' . $head['employee_id'] . ')') ?>
                                                            <?php if (!empty($head['designation'])): ?>
                                                                - <?= htmlspecialchars($head['designation']) ?>
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                            <?php if (!empty($alreadyHeads)): ?>
                                                <optgroup label="Already Department Heads (disabled)">
                                                    <?php foreach ($alreadyHeads as $head): ?>
                                                        <option value="<?= $head['faculty_id'] ?>" disabled>
                                                            <?= htmlspecialchars($head['full_name'] . ' (' . $head['employee_id'] . ')') ?> - Already a Head
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php foreach ($availableHeads as $head): ?>
                                                <option value="<?= $head['faculty_id'] ?>"
                                                        <?= ($formData['department_head_id'] ?? '') == $head['faculty_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($head['full_name'] . ' (' . $head['employee_id'] . ')') ?>
                                                    <?php if (!empty($head['designation'])): ?>
                                                        - <?= htmlspecialchars($head['designation']) ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </select>
                                <?php if (!empty($usedFallbackHeads)): ?>
                                    <?php if (empty($eligible)): ?>
                                        <div class="alert alert-info mt-2" role="alert">
                                            <i class="fas fa-info-circle me-1"></i>
                                            All active faculty are already assigned as department heads. You can create or activate a faculty member via <a class="text-decoration-underline" href="../../setup-faculty.php">setup-faculty.php</a> and then select them here.
                                        </div>
                                    <?php else: ?>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i>
                                            Showing eligible faculty; existing heads are listed below as disabled.
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                Only active faculty members are available for selection.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="established_date" class="form-label">Established Date</label>
                            <div class="input-group">
                                <i class="fas fa-calendar input-icon"></i>
                                <input type="date" class="form-control" id="established_date" name="established_date" 
                                       value="<?= htmlspecialchars($formData['established_date'] ?? '') ?>" 
                                       max="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <div class="input-group">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                       value="<?= htmlspecialchars($formData['contact_email'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <div class="input-group">
                                <i class="fas fa-phone input-icon"></i>
                                <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                       value="<?= htmlspecialchars($formData['contact_phone'] ?? '') ?>" 
                                       maxlength="20">
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                Ghana phone number format: +233 XX XXX XXXX
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location & Budget -->
                <div class="form-section">
                    <h3 class="form-section-title">📍 Location & Budget</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="building_location" class="form-label">Building Location</label>
                            <div class="input-group">
                                <i class="fas fa-building input-icon"></i>
                                <input type="text" class="form-control" id="building_location" name="building_location" 
                                       value="<?= htmlspecialchars($formData['building_location'] ?? '') ?>" 
                                       list="building_locations">
                            </div>
                            
                            <!-- Datalist for existing building locations -->
                            <datalist id="building_locations">
                                <?php foreach ($buildingLocations as $location): ?>
                                    <option value="<?= htmlspecialchars($location) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="budget_allocation" class="form-label">Budget Allocation</label>
                            <div class="input-group">
                                <i class="fas fa-money-bill input-icon"></i>
                                <input type="number" class="form-control" id="budget_allocation" name="budget_allocation" 
                                       value="<?= htmlspecialchars($formData['budget_allocation'] ?? '') ?>" 
                                       step="0.01" min="0">
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                Annual budget allocation in Ghanaian Cedis (₵).
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
                            🔄 Reset Form
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn">
                            ✅ Create Department
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
            
            // Initialize character counters
            initializeCharacterCounters();
            
            // Auto-format department code
            initializeDepartmentCodeFormatting();

            // Wire up theme toggle in navbar/profile dropdown if present
            document.addEventListener('click', function(e) {
                const toggleEl = e.target.closest('#themeToggle');
                if (toggleEl) {
                    e.preventDefault();
                    toggleTheme();
                }
            });
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
        function toggleTheme() {
            const current = document.body.getAttribute('data-theme') || (localStorage.getItem('theme') || 'light');
            const next = current === 'dark' ? 'light' : 'dark';
            document.body.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            const themeIcon = document.querySelector('#themeToggle i');
            if (themeIcon) {
                themeIcon.className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        // React to theme changes from other tabs/components immediately
        window.addEventListener('storage', function(e) {
            if (e.key === 'theme') {
                applyCurrentTheme();
            }
        });
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
            const form = document.getElementById('createDepartmentForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
                submitBtn.disabled = true;
            });
        }

        /**
         * Validate form inputs
         */
        function validateForm() {
            const deptCode = document.getElementById('department_code').value.trim();
            const deptName = document.getElementById('department_name').value.trim();
            const contactEmail = document.getElementById('contact_email').value.trim();
            const budgetAllocation = document.getElementById('budget_allocation').value;
            const establishedDate = document.getElementById('established_date').value;
            
            // Check required fields
            if (!deptCode) {
                showError('Please enter a department code.');
                return false;
            }
            
            if (!deptName) {
                showError('Please enter a department name.');
                return false;
            }
            
            // Validate department code format
            if (!/^[A-Z0-9]{2,10}$/.test(deptCode.toUpperCase())) {
                showError('Department code must be 2-10 characters long and contain only letters and numbers.');
                return false;
            }
            
            // Validate department name length
            if (deptName.length > 100) {
                showError('Department name cannot exceed 100 characters.');
                return false;
            }
            
            // Validate email format if provided
            if (contactEmail && !isValidEmail(contactEmail)) {
                showError('Please enter a valid contact email address.');
                return false;
            }
            
            // Validate budget if provided
            if (budgetAllocation && parseFloat(budgetAllocation) < 0) {
                showError('Budget allocation cannot be negative.');
                return false;
            }
            
            // Validate established date if provided
            if (establishedDate && new Date(establishedDate) > new Date()) {
                showError('Established date cannot be in the future.');
                return false;
            }
            
            return true;
        }

        /**
         * Initialize character counters
         */
        function initializeCharacterCounters() {
            const nameInput = document.getElementById('department_name');
            const descInput = document.getElementById('description');
            const nameCounter = document.getElementById('nameCounter');
            const descCounter = document.getElementById('descCounter');
            
            function updateCounter(input, counter, maxLength) {
                const currentLength = input.value.length;
                counter.textContent = `${currentLength} / ${maxLength}`;
                
                // Update counter color based on usage
                counter.classList.remove('near-limit', 'at-limit');
                if (currentLength >= maxLength) {
                    counter.classList.add('at-limit');
                } else if (currentLength >= maxLength * 0.9) {
                    counter.classList.add('near-limit');
                }
            }
            
            // Initial count
            updateCounter(nameInput, nameCounter, 100);
            updateCounter(descInput, descCounter, 500);
            
            // Event listeners
            nameInput.addEventListener('input', () => updateCounter(nameInput, nameCounter, 100));
            descInput.addEventListener('input', () => updateCounter(descInput, descCounter, 500));
        }

        /**
         * Initialize department code formatting
         */
        function initializeDepartmentCodeFormatting() {
            const deptCodeInput = document.getElementById('department_code');
            
            deptCodeInput.addEventListener('input', function(e) {
                // Convert to uppercase and remove invalid characters
                let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                
                // Limit to 10 characters
                if (value.length > 10) {
                    value = value.substring(0, 10);
                }
                
                e.target.value = value;
            });
        }

        /**
         * Reset form to initial state
         */
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.getElementById('createDepartmentForm').reset();
                
                // Reset character counters
                document.getElementById('nameCounter').textContent = '0 / 100';
                document.getElementById('descCounter').textContent = '0 / 500';
                
                // Remove any error states
                document.querySelectorAll('.alert-danger').forEach(alert => alert.remove());
                
                // Focus on first input
                document.getElementById('department_code').focus();
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
         * Show error message
         */
        function showError(message) {
            // Remove existing alerts
            document.querySelectorAll('.alert-danger').forEach(alert => alert.remove());
            
            // Create new alert
            const alertHtml = `
                <div class="alert alert-danger glass-card fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> ${message}
                </div>
            `;
            
            // Insert after page header
            const pageHeader = document.querySelector('.page-header');
            pageHeader.insertAdjacentHTML('afterend', alertHtml);
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        /**
         * Show info/success notice (non-blocking)
         */
        function showNotice(message, type = 'info') {
            const cls = type === 'success' ? 'alert-success' : (type === 'warning' ? 'alert-warning' : 'alert-info');
            const alertHtml = `
                <div class="alert ${cls} glass-card fade-in d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'warning' ? 'fa-exclamation-circle' : 'fa-info-circle')} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="this.closest('.alert').remove()">Dismiss</button>
                </div>
            `;
            const pageHeader = document.querySelector('.page-header');
            pageHeader.insertAdjacentHTML('afterend', alertHtml);
        }

        /**
         * Auto-save form data to localStorage (optional)
         */
        function autoSaveFormData() {
            const formData = {
                department_code: document.getElementById('department_code').value,
                department_name: document.getElementById('department_name').value,
                description: document.getElementById('description').value,
                department_head_id: document.getElementById('department_head_id').value,
                established_date: document.getElementById('established_date').value,
                contact_email: document.getElementById('contact_email').value,
                contact_phone: document.getElementById('contact_phone').value,
                building_location: document.getElementById('building_location').value,
                budget_allocation: document.getElementById('budget_allocation').value
            };
            localStorage.setItem('department_form_draft', JSON.stringify(formData));
        }

        /**
         * Format phone number as user types (Ghana format)
         */
        function loadSavedFormData() {
            try {
                const draft = localStorage.getItem('department_form_draft');
                if (!draft) return;

                // Only auto-load if key fields are empty
                const codeEmpty = !document.getElementById('department_code').value.trim();
                const nameEmpty = !document.getElementById('department_name').value.trim();
                if (!codeEmpty || !nameEmpty) return;

                const data = JSON.parse(draft);
                const map = {
                    department_code: 'department_code',
                    department_name: 'department_name',
                    description: 'description',
                    department_head_id: 'department_head_id',
                    established_date: 'established_date',
                    contact_email: 'contact_email',
                    contact_phone: 'contact_phone',
                    building_location: 'building_location',
                    budget_allocation: 'budget_allocation'
                };

                Object.keys(map).forEach(key => {
                    const el = document.getElementById(map[key]);
                    if (!el || data[key] === undefined || data[key] === null) return;
                    el.value = data[key];
                });

                // Update counters/displays if present
                const nameCounter = document.getElementById('nameCounter');
                const descCounter = document.getElementById('descCounter');
                if (nameCounter) nameCounter.textContent = `${document.getElementById('department_name').value.length} / 100`;
                if (descCounter) descCounter.textContent = `${document.getElementById('description').value.length} / 500`;
                // Silently load without showing intrusive notice/banner
                sessionStorage.setItem('department_form_draft_loaded', '1');
            } catch (e) {
                console.warn('Failed to load draft form data', e);
            }
        }

        function clearSavedFormData() {
            localStorage.removeItem('department_form_draft');
        }

        function discardDraft() {
            clearSavedFormData();
            const form = document.getElementById('createDepartmentForm');
            if (form) form.reset();
            // Reset counters
            const nameCounter = document.getElementById('nameCounter');
            const descCounter = document.getElementById('descCounter');
            if (nameCounter) nameCounter.textContent = '0 / 100';
            if (descCounter) descCounter.textContent = '0 / 500';
            showNotice('Draft discarded and form reset.', 'warning');
        }
        function formatPhoneNumber() {
            const phoneInput = document.getElementById('contact_phone');
            
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                // Handle Ghana country code
                if (value.startsWith('233')) {
                    // Remove leading 233 if present, we'll add +233 prefix
                    value = value.substring(3);
                } else if (value.startsWith('0')) {
                    // Remove leading 0 for local format
                    value = value.substring(1);
                }
                
                // Limit to 9 digits after country code
                if (value.length > 9) {
                    value = value.substring(0, 9);
                }
                
                // Format as +233 XX XXX XXXX
                if (value.length >= 2) {
                    if (value.length <= 2) {
                        value = `+233 ${value}`;
                    } else if (value.length <= 5) {
                        value = `+233 ${value.substring(0, 2)} ${value.substring(2)}`;
                    } else {
                        value = `+233 ${value.substring(0, 2)} ${value.substring(2, 5)} ${value.substring(5)}`;
                    }
                } else if (value.length === 1) {
                    value = `+233 ${value}`;
                } else if (value.length === 0) {
                    value = '';
                }
                
                e.target.value = value;
            });

            // Set default country code on focus if empty
            phoneInput.addEventListener('focus', function(e) {
                if (!e.target.value) {
                    e.target.value = '+233 ';
                }
            });

            // Clear if only country code remains on blur
            phoneInput.addEventListener('blur', function(e) {
                if (e.target.value === '+233 ' || e.target.value === '+233') {
                    e.target.value = '';
                }
            });
        }

        /**
         * Format budget amount with cedis symbol and proper decimal places
         */
        function formatBudgetAmount() {
            const budgetInput = document.getElementById('budget_allocation');
            const budgetDisplay = document.createElement('div');
            budgetDisplay.className = 'form-text mt-1';
            budgetDisplay.style.fontWeight = '600';
            budgetDisplay.style.color = 'var(--success-color)';
            
            function updateBudgetDisplay() {
                const value = parseFloat(budgetInput.value);
                if (!isNaN(value) && value > 0) {
                    budgetDisplay.innerHTML = `<i class="fas fa-money-bill-wave"></i> ₵${value.toLocaleString('en-GH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    if (!budgetInput.parentNode.nextElementSibling?.contains(budgetDisplay)) {
                        budgetInput.parentNode.parentNode.appendChild(budgetDisplay);
                    }
                } else {
                    if (budgetDisplay.parentNode) {
                        budgetDisplay.remove();
                    }
                }
            }
            
            budgetInput.addEventListener('input', updateBudgetDisplay);
            budgetInput.addEventListener('blur', function(e) {
                const value = parseFloat(e.target.value);
                if (!isNaN(value) && value >= 0) {
                    e.target.value = value.toFixed(2);
                    updateBudgetDisplay();
                }
            });

            // Initial display
            updateBudgetDisplay();
        }

        // Force clear form if no POST data (fresh page load)
        function forceClearForm() {
            <?php if (empty($_POST)): ?>
            // Clear all form inputs on fresh page load
            document.getElementById('createDepartmentForm').reset();
            
            // Clear any localStorage data
            clearSavedFormData();
            
            // Explicitly clear specific fields that might have cached values
            const fieldsTolear = ['department_code', 'department_name', 'description'];
            fieldsTolear.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.value = '';
                }
            });
            <?php endif; ?>
        }

        // Initialize additional formatting on load
        setTimeout(() => {
            forceClearForm(); // Clear form first
            formatPhoneNumber();
            formatBudgetAmount();
            <?php if (!empty($_POST)): ?>
            loadSavedFormData(); // Only load saved data if there was a POST submission
            <?php endif; ?>
        }, 100);

        // Auto-save form data periodically
        setInterval(autoSaveFormData, 30000); // Save every 30 seconds

        // Clear saved data on successful submission
        window.addEventListener('beforeunload', function() {
            // Only save if form has data and no success message
            if (!document.querySelector('.alert-success')) {
                autoSaveFormData();
            } else {
                clearSavedFormData();
            }
        });

        // Make functions available globally
        window.resetForm = resetForm;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>