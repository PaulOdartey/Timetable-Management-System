<?php
/**
 * Admin Timetable Create - Timetable Creation Interface
 * Timetable Management System
 * 
 * Professional interface for admin to create timetable entries
 * with conflict detection and comprehensive validation
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
$error_message = '';
$success_message = '';
$warning_messages = [];
$formData = [];
$resources = [];

// Get available resources
try {
    $resourceResult = $timetableManager->getAvailableResources();
    if ($resourceResult['success']) {
        $resources = $resourceResult;
    } else {
        $error_message = "Error loading resources: " . $resourceResult['message'];
    }
} catch (Exception $e) {
    $error_message = "Error loading page resources: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error
        $formData = $_POST;
        
        // Create timetable entry
        $result = $timetableManager->createTimetableEntry($_POST);
        
        if ($result['success']) {
            $success_message = $result['message'] ?? 'Timetable entry created successfully';
            $warning_messages = $result['warnings'] ?? [];
            $formData = []; // Clear form data on success
            
            // Redirect after short delay to show success message
            header("refresh:3;url=index.php");
        } else {
            $error_message = $result['message'] ?? 'An error occurred while creating the timetable entry';
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Set page title
$pageTitle = "Create Timetable Entry";
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
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
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

        /* Resource Cards */
        .resource-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.5rem;
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
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .resource-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .resource-details {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Validation Indicators */
        .validation-indicator {
            margin-left: 0.5rem;
            font-size: 0.875rem;
        }

        .validation-indicator.checking {
            color: var(--warning-color);
        }

        .validation-indicator.valid {
            color: var(--success-color);
        }

        .validation-indicator.invalid {
            color: var(--error-color);
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

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(59, 130, 246, 0.3);
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

        .header-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
            color: white;
        }

        /* Conflict Detection Display */
        .conflict-check {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-tertiary);
            display: none;
        }

        .conflict-check.checking {
            display: block;
            border-color: var(--warning-color);
            background: rgba(245, 158, 11, 0.1);
        }

        .conflict-check.valid {
            display: block;
            border-color: var(--success-color);
            background: rgba(16, 185, 129, 0.1);
        }

        .conflict-check.invalid {
            display: block;
            border-color: var(--error-color);
            background: rgba(239, 68, 68, 0.1);
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
            animation: fadeIn 0.5s ease-in;
        }

        .slide-up {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                    <h1 class="page-title">📅 Create Timetable Entry</h1>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success glass-card fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong> <?= htmlspecialchars($success_message) ?>
                <?php if (!empty($warning_messages)): ?>
                    <hr class="my-2">
                    <small>
                        <?php foreach ($warning_messages as $warning): ?>
                            <div><i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($warning) ?></div>
                        <?php endforeach; ?>
                    </small>
                <?php endif; ?>
                <br><small>Redirecting to timetable list...</small>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Warning Messages from Validation -->
        <?php if (!empty($warning_messages) && empty($success_message)): ?>
            <div class="alert alert-warning glass-card fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Warnings:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($warning_messages as $warning): ?>
                        <li><?= htmlspecialchars($warning) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Create Timetable Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" id="createTimetableForm" novalidate>
                <!-- Academic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">🎓 Academic Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="academic_year" class="form-label">Academic Year *</label>
                            <select class="form-select" id="academic_year" name="academic_year" required>
                                <option value="">Select Academic Year</option>
                                <option value="<?= htmlspecialchars($resources['current_academic_year'] ?? '') ?>" 
                                    <?= ($formData['academic_year'] ?? '') === ($resources['current_academic_year'] ?? '') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($resources['current_academic_year'] ?? 'Current Year') ?> (Current)
                                </option>
                                <?php
                                // Add additional year options
                                $currentYear = date('Y');
                                for ($i = -1; $i <= 2; $i++) {
                                    $year = ($currentYear + $i) . '-' . ($currentYear + $i + 1);
                                    if ($year !== ($resources['current_academic_year'] ?? '')) {
                                        $selected = ($formData['academic_year'] ?? '') === $year ? 'selected' : '';
                                        echo "<option value=\"{$year}\" {$selected}>{$year}</option>";
                                    }
                                }
                                ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-calendar"></i> 
                                Academic year for this timetable entry
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="semester" class="form-label">Semester *</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="">Select Semester</option>
                                <?php for ($i = 1; $i <= 2; $i++): ?>
                                    <option value="<?= $i ?>" 
                                        <?= ($formData['semester'] ?? $resources['current_semester'] ?? 1) == $i ? 'selected' : '' ?>>
                                        Semester <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="section" class="form-label">Section</label>
                            <input type="text" class="form-control" id="section" name="section" 
                                   value="<?= htmlspecialchars($formData['section'] ?? 'A') ?>" 
                                   placeholder="e.g., A, B, C" maxlength="10">
                            <div class="form-text">
                                <i class="fas fa-users"></i> 
                                Class section identifier
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="max_students" class="form-label">Max Students</label>
                            <input type="number" class="form-control" id="max_students" name="max_students" 
                                   value="<?= htmlspecialchars($formData['max_students'] ?? '') ?>" 
                                   placeholder="Leave empty for classroom capacity" min="1" max="500">
                            <div class="form-text">
                                <i class="fas fa-user-graduate"></i> 
                                Override classroom capacity if needed
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">📚 Subject Selection</h3>
                    
                    <div class="mb-3">
                        <label for="subject_id" class="form-label">Subject *</label>
                        <select class="form-select" id="subject_id" name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php if (isset($resources['subjects'])): ?>
                                <?php
                                // Group subjects by department similar to classroom building/time slot day grouping
                                $currentDepartment = '';
                                foreach ($resources['subjects'] as $subject):
                                    // Open a new optgroup when department changes
                                    if ($currentDepartment !== ($subject['department'] ?? '')) {
                                        if ($currentDepartment !== '') echo '</optgroup>';
                                        $currentDepartment = $subject['department'] ?? '';
                                        echo '<optgroup label="' . htmlspecialchars($currentDepartment) . '">';
                                    }
                                ?>
                                    <option value="<?= $subject['subject_id'] ?>" 
                                        data-department="<?= htmlspecialchars($subject['department']) ?>"
                                        data-credits="<?= $subject['credits'] ?>"
                                        data-semester="<?= $subject['semester'] ?>"
                                        data-year="<?= $subject['year_level'] ?>"
                                        <?= ($formData['subject_id'] ?? '') == $subject['subject_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?>
                                        (Sem <?= $subject['semester'] ?>)
                                    </option>
                                <?php
                                endforeach;
                                if ($currentDepartment !== '') echo '</optgroup>';
                                ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">
                            <i class="fas fa-book"></i> 
                            Select the subject to be scheduled
                        </div>
                        
                        <!-- Subject Details Display -->
                        <div id="subjectDetails" class="resource-card" style="display: none;">
                            <div class="resource-title">Subject Information</div>
                            <div class="resource-details" id="subjectInfo"></div>
                        </div>
                    </div>
                </div>

                <!-- Faculty Assignment -->
                <div class="form-section">
                    <h3 class="form-section-title">👨‍🏫 Faculty Assignment</h3>
                    
                    <div class="mb-3">
                        <label for="faculty_id" class="form-label">
                            Faculty Member *
                            <span class="validation-indicator" id="facultyValidation"></span>
                        </label>
                        <select class="form-select" id="faculty_id" name="faculty_id" required>
                            <option value="">Select Faculty Member</option>
                            <?php if (isset($resources['faculty'])): ?>
                                <?php
                                // Group faculty by department similar to other grouped selects
                                $currentDept = '';
                                foreach ($resources['faculty'] as $faculty):
                                    $deptLabel = $faculty['department'] ?? '';
                                    if ($currentDept !== $deptLabel) {
                                        if ($currentDept !== '') echo '</optgroup>';
                                        $currentDept = $deptLabel;
                                        echo '<optgroup label="' . htmlspecialchars($currentDept) . '">';
                                    }
                                ?>
                                    <option value="<?= $faculty['faculty_id'] ?>" 
                                        data-department="<?= htmlspecialchars($faculty['department']) ?>"
                                        data-designation="<?= htmlspecialchars($faculty['designation']) ?>"
                                        data-employee-id="<?= htmlspecialchars($faculty['employee_id']) ?>"
                                        <?= ($formData['faculty_id'] ?? '') == $faculty['faculty_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($faculty['first_name']) ?> <?= htmlspecialchars($faculty['last_name']) ?>
                                        (<?= htmlspecialchars($faculty['employee_id']) ?>)
                                    </option>
                                <?php
                                endforeach;
                                if ($currentDept !== '') echo '</optgroup>';
                                ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">
                            <i class="fas fa-chalkboard-teacher"></i> 
                            Faculty member assigned to teach this subject
                        </div>
                        
                        <!-- Faculty Details Display -->
                        <div id="facultyDetails" class="resource-card" style="display: none;">
                            <div class="resource-title">Faculty Information</div>
                            <div class="resource-details" id="facultyInfo"></div>
                        </div>
                        
                        <!-- Faculty-Subject Assignment Check -->
                        <div id="facultySubjectCheck" class="alert alert-warning" style="display: none;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <span id="facultySubjectMessage"></span>
                        </div>
                    </div>
                </div>

                <!-- Schedule Information -->
                <div class="form-section">
                    <h3 class="form-section-title">⏰ Schedule Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="slot_id" class="form-label">
                                Time Slot *
                                <span class="validation-indicator" id="slotValidation"></span>
                            </label>
                            <select class="form-select" id="slot_id" name="slot_id" required>
                                <option value="">Select Time Slot</option>
                                <?php if (isset($resources['time_slots'])): ?>
                                    <?php
                                    $currentDay = '';
                                    foreach ($resources['time_slots'] as $slot):
                                        if ($currentDay !== $slot['day_of_week']) {
                                            if ($currentDay !== '') echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($slot['day_of_week']) . '">';
                                            $currentDay = $slot['day_of_week'];
                                        }
                                        $timeDisplay = date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time']));
                                    ?>
                                        <option value="<?= $slot['slot_id'] ?>" 
                                            data-day="<?= htmlspecialchars($slot['day_of_week']) ?>"
                                            data-start="<?= $slot['start_time'] ?>"
                                            data-end="<?= $slot['end_time'] ?>"
                                            data-name="<?= htmlspecialchars($slot['slot_name']) ?>"
                                            <?= ($formData['slot_id'] ?? '') == $slot['slot_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($slot['slot_name']) ?> (<?= $timeDisplay ?>)
                                        </option>
                                    <?php 
                                    endforeach; 
                                    if ($currentDay !== '') echo '</optgroup>';
                                    ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-clock"></i> 
                                Day and time for the class
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="classroom_id" class="form-label">
                                Classroom *
                                <span class="validation-indicator" id="classroomValidation"></span>
                            </label>
                            <select class="form-select" id="classroom_id" name="classroom_id" required>
                                <option value="">Select Classroom</option>
                                <?php if (isset($resources['classrooms'])): ?>
                                    <?php
                                    $currentBuilding = '';
                                    foreach ($resources['classrooms'] as $classroom):
                                        if ($currentBuilding !== $classroom['building']) {
                                            if ($currentBuilding !== '') echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($classroom['building'] ?? '') . '">';
                                            $currentBuilding = $classroom['building'];
                                        }
                                    ?>
                                        <option value="<?= $classroom['classroom_id'] ?>" 
                                            data-building="<?= htmlspecialchars($classroom['building'] ?? '') ?>"
                                            data-capacity="<?= $classroom['capacity'] ?>"
                                            data-type="<?= htmlspecialchars($classroom['type'] ?? '') ?>"
                                            data-facilities="<?= htmlspecialchars($classroom['facilities'] ?? '') ?>"
                                            <?= ($formData['classroom_id'] ?? '') == $classroom['classroom_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($classroom['room_number'] ?? '') ?> 
                                            (Capacity: <?= $classroom['capacity'] ?>, Type: <?= htmlspecialchars(ucfirst((string)($classroom['type'] ?? ''))) ?>)
                                        </option>
                                    <?php 
                                    endforeach; 
                                    if ($currentBuilding !== '') echo '</optgroup>';
                                    ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-door-open"></i> 
                                Room where the class will be held
                            </div>
                        </div>
                    </div>

                    <!-- Time Slot Details Display -->
                    <div id="slotDetails" class="resource-card" style="display: none;">
                        <div class="resource-title">Schedule Information</div>
                        <div class="resource-details" id="slotInfo"></div>
                    </div>

                    <!-- Classroom Details Display -->
                    <div id="classroomDetails" class="resource-card" style="display: none;">
                        <div class="resource-title">Classroom Information</div>
                        <div class="resource-details" id="classroomInfo"></div>
                    </div>

                    <!-- Conflict Detection Display -->
                    <div id="conflictCheck" class="conflict-check">
                        <div class="d-flex align-items-center">
                            <span id="conflictMessage">Checking for scheduling conflicts...</span>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="form-section">
                    <h3 class="form-section-title">📝 Additional Information</h3>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Any additional information about this class..."><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
                        <div class="form-text">
                            <i class="fas fa-sticky-note"></i> 
                            Optional notes about this timetable entry
                        </div>
                    </div>

                    <!-- Summary Card -->
                    <div id="summaryCard" class="resource-card" style="display: none;">
                        <div class="resource-title">📋 Schedule Summary</div>
                        <div class="resource-details" id="summaryInfo">
                            Complete all fields above to see schedule summary
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-section">
                    <div class="d-flex gap-3 justify-content-end flex-wrap">
                        <a href="index.php" class="btn-action btn-outline">
                            ❌ Cancel
                        </a>
                        <button type="button" class="btn-action btn-warning" onclick="resetForm()">
                            🔄 Reset Form
                        </button>
                        <button type="button" class="btn-action btn-primary" onclick="validateAndPreview()">
                            🔍 Validate & Preview
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn" disabled>
                            ✅ Create Schedule
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
            
            // Initialize form functionality
            initializeFormHandlers();
            
            // Initialize validation
            initializeValidation();
            
            // Auto-hide error alerts when user starts fixing fields
            initializeAutoHideForErrors();
            
            // Initialize real-time updates
            initializeRealTimeUpdates();
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
            
            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    document.body.classList.toggle('sidebar-collapsed');
                });
            }
        }

        /**
         * Initialize form handlers
         */
        function initializeFormHandlers() {
            // Subject change handler
            document.getElementById('subject_id').addEventListener('change', function() {
                updateSubjectDetails(this);
                updateFacultyOptions();
                checkFacultySubjectAssignment();
                updateSummary();
            });

            // Faculty change handler
            document.getElementById('faculty_id').addEventListener('change', function() {
                updateFacultyDetails(this);
                checkFacultySubjectAssignment();
                updateSummary();
            });

            // Time slot change handler
            document.getElementById('slot_id').addEventListener('change', function() {
                updateSlotDetails(this);
                checkConflicts();
                updateSummary();
            });

            // Classroom change handler
            document.getElementById('classroom_id').addEventListener('change', function() {
                updateClassroomDetails(this);
                checkConflicts();
                updateSummary();
            });

            // Form field change handlers for summary update
            ['academic_year', 'semester', 'section'].forEach(fieldId => {
                document.getElementById(fieldId).addEventListener('change', updateSummary);
            });
        }

        /**
         * Update subject details display
         */
        function updateSubjectDetails(selectElement) {
            const option = selectElement.selectedOptions[0];
            const detailsDiv = document.getElementById('subjectDetails');
            const infoDiv = document.getElementById('subjectInfo');

            if (option && option.value) {
                const department = option.getAttribute('data-department');
                const credits = option.getAttribute('data-credits');
                const semester = option.getAttribute('data-semester');
                const year = option.getAttribute('data-year');

                infoDiv.innerHTML = `
                    <strong>Code:</strong> ${option.text.split(' - ')[0]}<br>
                    <strong>Department:</strong> ${department}<br>
                    <strong>Credits:</strong> ${credits}<br>
                    <strong>Intended Semester:</strong> ${semester} (Year ${year})
                `;
                detailsDiv.style.display = 'block';
            } else {
                detailsDiv.style.display = 'none';
            }
        }

        /**
         * Update faculty details display
         */
        function updateFacultyDetails(selectElement) {
            const option = selectElement.selectedOptions[0];
            const detailsDiv = document.getElementById('facultyDetails');
            const infoDiv = document.getElementById('facultyInfo');

            if (option && option.value) {
                const department = option.getAttribute('data-department');
                const designation = option.getAttribute('data-designation');
                const employeeId = option.getAttribute('data-employee-id');

                infoDiv.innerHTML = `
                    <strong>Employee ID:</strong> ${employeeId}<br>
                    <strong>Department:</strong> ${department}<br>
                    <strong>Designation:</strong> ${designation}
                `;
                detailsDiv.style.display = 'block';
            } else {
                detailsDiv.style.display = 'none';
            }
        }

        /**
         * Update time slot details display
         */
        function updateSlotDetails(selectElement) {
            const option = selectElement.selectedOptions[0];
            const detailsDiv = document.getElementById('slotDetails');
            const infoDiv = document.getElementById('slotInfo');

            if (option && option.value) {
                const day = option.getAttribute('data-day');
                const startTime = option.getAttribute('data-start');
                const endTime = option.getAttribute('data-end');
                const slotName = option.getAttribute('data-name');

                const startFormatted = formatTime(startTime);
                const endFormatted = formatTime(endTime);

                infoDiv.innerHTML = `
                    <strong>Day:</strong> ${day}<br>
                    <strong>Time:</strong> ${startFormatted} - ${endFormatted}<br>
                    <strong>Slot:</strong> ${slotName}
                `;
                detailsDiv.style.display = 'block';
            } else {
                detailsDiv.style.display = 'none';
            }
        }

        /**
         * Update classroom details display
         */
        function updateClassroomDetails(selectElement) {
            const option = selectElement.selectedOptions[0];
            const detailsDiv = document.getElementById('classroomDetails');
            const infoDiv = document.getElementById('classroomInfo');

            if (option && option.value) {
                const building = option.getAttribute('data-building');
                const capacity = option.getAttribute('data-capacity');
                const type = option.getAttribute('data-type');
                const facilities = option.getAttribute('data-facilities');

                infoDiv.innerHTML = `
                    <strong>Building:</strong> ${building}<br>
                    <strong>Capacity:</strong> ${capacity} students<br>
                    <strong>Type:</strong> ${type}<br>
                    <strong>Facilities:</strong> ${facilities || 'Standard classroom equipment'}
                `;
                detailsDiv.style.display = 'block';
            } else {
                detailsDiv.style.display = 'none';
            }
        }

        /**
         * Update faculty options based on selected subject
         */
        function updateFacultyOptions() {
            const subjectId = document.getElementById('subject_id').value;
            const facultySelect = document.getElementById('faculty_id');
            
            if (!subjectId) return;

            // Get faculty assigned to this subject via AJAX
            fetch('ajax/get_faculty_by_subject.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ subject_id: subjectId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Highlight assigned faculty
                    Array.from(facultySelect.options).forEach(option => {
                        const facultyId = option.value;
                        const isAssigned = data.assigned_faculty.some(f => f.faculty_id == facultyId);
                        
                        if (isAssigned) {
                            option.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                            option.style.color = 'var(--success-color)';
                        } else {
                            option.style.backgroundColor = '';
                            option.style.color = '';
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching faculty assignments:', error);
            });
        }

        /**
         * Check faculty-subject assignment
         */
        function checkFacultySubjectAssignment() {
            const subjectId = document.getElementById('subject_id').value;
            const facultyId = document.getElementById('faculty_id').value;
            const checkDiv = document.getElementById('facultySubjectCheck');
            const messageSpan = document.getElementById('facultySubjectMessage');
            const validationIndicator = document.getElementById('facultyValidation');

            if (!subjectId || !facultyId) {
                checkDiv.style.display = 'none';
                validationIndicator.innerHTML = '';
                return;
            }

            validationIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            validationIndicator.className = 'validation-indicator checking';

            // Check assignment via AJAX
            fetch('ajax/check_faculty_assignment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    subject_id: subjectId, 
                    faculty_id: facultyId 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.is_assigned) {
                    validationIndicator.innerHTML = '<i class="fas fa-check"></i>';
                    validationIndicator.className = 'validation-indicator valid';
                    checkDiv.style.display = 'none';
                } else {
                    validationIndicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                    validationIndicator.className = 'validation-indicator invalid';
                    messageSpan.textContent = data.message || 'Faculty member is not assigned to teach this subject';
                    checkDiv.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error checking faculty assignment:', error);
                validationIndicator.innerHTML = '<i class="fas fa-question"></i>';
                validationIndicator.className = 'validation-indicator';
            });
        }

        /**
         * Check for scheduling conflicts
         */
        function checkConflicts() {
            const facultyId = document.getElementById('faculty_id').value;
            const classroomId = document.getElementById('classroom_id').value;
            const slotId = document.getElementById('slot_id').value;
            const semester = document.getElementById('semester').value;
            const academicYear = document.getElementById('academic_year').value;
            
            const conflictDiv = document.getElementById('conflictCheck');
            const messageSpan = document.getElementById('conflictMessage');
            const submitBtn = document.getElementById('submitBtn');

            if (!facultyId || !classroomId || !slotId || !semester || !academicYear) {
                conflictDiv.style.display = 'none';
                submitBtn.disabled = true;
                return;
            }

            // Remove spinning state; show neutral checking message without spinner
            conflictDiv.className = 'conflict-check';
            conflictDiv.style.display = 'block';
            messageSpan.textContent = 'Checking for scheduling conflicts...';

            // Check conflicts via AJAX
            fetch('ajax/check_conflicts.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    faculty_id: facultyId,
                    classroom_id: classroomId,
                    slot_id: slotId,
                    semester: semester,
                    academic_year: academicYear
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.has_conflict) {
                    conflictDiv.className = 'conflict-check invalid';
                    messageSpan.innerHTML = `<i class="fas fa-times me-2"></i>${data.message}`;
                    submitBtn.disabled = true;
                } else {
                    conflictDiv.className = 'conflict-check valid';
                    messageSpan.innerHTML = '<i class="fas fa-check me-2"></i>No conflicts detected. Schedule is available.';
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error checking conflicts:', error);
                conflictDiv.className = 'conflict-check invalid';
                messageSpan.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error checking conflicts. Please try again.';
                submitBtn.disabled = true;
            });
        }

        /**
         * Update schedule summary
         */
        function updateSummary() {
            const summaryCard = document.getElementById('summaryCard');
            const summaryInfo = document.getElementById('summaryInfo');

            const subjectSelect = document.getElementById('subject_id');
            const facultySelect = document.getElementById('faculty_id');
            const slotSelect = document.getElementById('slot_id');
            const classroomSelect = document.getElementById('classroom_id');
            const academicYear = document.getElementById('academic_year').value;
            const semester = document.getElementById('semester').value;
            const section = document.getElementById('section').value || 'A';

            if (!subjectSelect.value || !facultySelect.value || !slotSelect.value || !classroomSelect.value) {
                summaryCard.style.display = 'none';
                return;
            }

            const subjectText = subjectSelect.selectedOptions[0].text;
            const facultyText = facultySelect.selectedOptions[0].text.split(' (')[0];
            const slotText = slotSelect.selectedOptions[0].text;
            const classroomText = classroomSelect.selectedOptions[0].text.split(' (')[0];
            const slotDay = slotSelect.selectedOptions[0].getAttribute('data-day');

            summaryInfo.innerHTML = `
                <strong>${subjectText}</strong><br>
                <strong>Faculty:</strong> ${facultyText}<br>
                <strong>Schedule:</strong> ${slotDay}, ${slotText}<br>
                <strong>Location:</strong> ${classroomText}<br>
                <strong>Academic Year:</strong> ${academicYear}, Semester ${semester}, Section ${section}
            `;
            summaryCard.style.display = 'block';
        }

        /**
         * Initialize validation
         */
        function initializeValidation() {
            const form = document.getElementById('createTimetableForm');
            
            form.addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submitBtn');
                
                if (submitBtn.disabled) {
                    e.preventDefault();
                    showError('Please resolve all conflicts and validation issues before submitting.');
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<div class="spinner me-2"></div>Creating Schedule...';
                submitBtn.disabled = true;
            });
        }

        /**
         * Initialize auto-hide behavior for validation error alert
         * Hides/removes the top error as soon as user interacts with any required field
         */
        function initializeAutoHideForErrors() {
            const requiredFields = ['subject_id', 'faculty_id', 'classroom_id', 'slot_id', 'semester', 'academic_year'];
            const removeAlerts = () => {
                document.querySelectorAll('.alert-danger').forEach(alert => alert.remove());
            };
            requiredFields.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                ['input', 'change'].forEach(ev => {
                    el.addEventListener(ev, () => {
                        removeAlerts();
                        el.classList.remove('is-invalid');
                    });
                });
            });
        }

        /**
         * Validate form and show preview
         */
        function validateAndPreview() {
            const requiredFields = ['subject_id', 'faculty_id', 'classroom_id', 'slot_id', 'semester', 'academic_year'];
            const missing = [];
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    missing.push(field.previousElementSibling.textContent.replace('*', '').trim());
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (missing.length > 0) {
                showError('Please fill in the following required fields: ' + missing.join(', '));
                return;
            }
            
            // Brief success toast on passing basic validation
            showSuccess('Basic validation complete. Showing preview...');
            
            // Run all checks
            checkFacultySubjectAssignment();
            checkConflicts();
            updateSummary();
            
            // Scroll to summary
            document.getElementById('summaryCard').scrollIntoView({ behavior: 'smooth' });
        }

        /**
         * Reset form to initial state
         */
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.getElementById('createTimetableForm').reset();
                
                // Hide all detail cards
                ['subjectDetails', 'facultyDetails', 'slotDetails', 'classroomDetails', 'summaryCard', 'facultySubjectCheck', 'conflictCheck'].forEach(id => {
                    document.getElementById(id).style.display = 'none';
                });
                
                // Reset validation indicators
                document.querySelectorAll('.validation-indicator').forEach(indicator => {
                    indicator.innerHTML = '';
                    indicator.className = 'validation-indicator';
                });
                
                // Reset form validation classes
                document.querySelectorAll('.is-valid, .is-invalid').forEach(field => {
                    field.classList.remove('is-valid', 'is-invalid');
                });
                
                // Disable submit button
                document.getElementById('submitBtn').disabled = true;
            }
        }

        /**
         * Format time to 12-hour format
         */
        function formatTime(timeString) {
            const time = new Date('1970-01-01T' + timeString + 'Z');
            return time.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit', 
                hour12: true 
            });
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
            
            // Insert at top of main content
            const mainContent = document.querySelector('.main-content');
            const firstElement = mainContent.querySelector('.page-header').nextElementSibling;
            firstElement.insertAdjacentHTML('beforebegin', alertHtml);
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        /**
         * Show brief success toast (auto-dismiss)
         */
        function showSuccess(message) {
            // Remove existing success toasts
            document.querySelectorAll('.alert-success').forEach(alert => alert.remove());

            const toastHtml = `
                <div class="alert alert-success glass-card fade-in" role="alert" style="position: sticky; top: 0; z-index: 1050;">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Success:</strong> ${message}
                </div>
            `;

            const mainContent = document.querySelector('.main-content');
            const firstElement = mainContent.querySelector('.page-header').nextElementSibling;
            firstElement.insertAdjacentHTML('beforebegin', toastHtml);

            // Auto-dismiss
            setTimeout(() => {
                const el = mainContent.querySelector('.alert-success');
                if (el) el.remove();
            }, 2500);
        }

        /**
         * Theme toggle functionality
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
        window.validateAndPreview = validateAndPreview;
        window.resetForm = resetForm;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>