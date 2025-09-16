<?php
/**
 * Admin Users Create - User Creation Interface
 * Timetable Management System
 * 
 * Professional interface for admin to manually create faculty and student accounts
 * with immediate activation and comprehensive profile setup
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$userManager = new User();

// Initialize variables
$error_message = '';
$success_message = '';
$departments = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error (excluding sensitive fields)
        $formData = $_POST;
        // Remove sensitive fields from repopulation for security
        unset($formData['password'], $formData['confirm_password']);
        
        // Basic validation
        $required_fields = ['username', 'email', 'password', 'confirm_password', 'role', 'first_name', 'last_name'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Password validation
        if ($_POST['password'] !== $_POST['confirm_password']) {
            throw new Exception('Passwords do not match.');
        }
        
        if (strlen($_POST['password']) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }
        
        // Email validation
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Role-specific validation
        if ($_POST['role'] === 'student') {
            if (empty($_POST['student_number']) || empty($_POST['department']) || empty($_POST['year_of_study'])) {
                throw new Exception('Student number, department, and year of study are required for students.');
            }
        } elseif ($_POST['role'] === 'faculty') {
            if (empty($_POST['employee_id']) || empty($_POST['department']) || empty($_POST['designation'])) {
                throw new Exception('Employee ID, department, and designation are required for faculty.');
            }
        }
        
        // Prepare user data
        $userData = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'password' => $_POST['password'],
            'role' => $_POST['role'],
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'phone' => !empty($_POST['phone']) ? trim($_POST['phone']) : null,
            'department' => !empty($_POST['department']) ? trim($_POST['department']) : null
        ];
        
        // Add role-specific data
        if ($_POST['role'] === 'student') {
            $userData['student_number'] = trim($_POST['student_number']);
            $userData['year_of_study'] = (int)$_POST['year_of_study'];
            $userData['semester'] = !empty($_POST['semester']) ? (int)$_POST['semester'] : 1;
        } elseif ($_POST['role'] === 'faculty') {
            $userData['employee_id'] = trim($_POST['employee_id']);
            $userData['designation'] = trim($_POST['designation']);
            $userData['specialization'] = !empty($_POST['specialization']) ? trim($_POST['specialization']) : null;
            $userData['qualification'] = !empty($_POST['qualification']) ? trim($_POST['qualification']) : null;
            $userData['experience_years'] = !empty($_POST['experience_years']) ? (int)$_POST['experience_years'] : null;
            $userData['office_location'] = !empty($_POST['office_location']) ? trim($_POST['office_location']) : null;
        }
        
        // Create user account
        $result = $userManager->createUserAccount($userData, $userId);
        
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
    // Get departments for dropdown
    $departments = $db->fetchAll("
        SELECT DISTINCT department_name, department_code 
        FROM departments 
        WHERE is_active = 1 
        ORDER BY department_name ASC
    ");
    
    // If no departments in departments table, get from existing users
    if (empty($departments)) {
        $departments = $db->fetchAll("
            SELECT DISTINCT department as department_name, department as department_code
            FROM (
                SELECT department FROM students WHERE department IS NOT NULL
                UNION 
                SELECT department FROM faculty WHERE department IS NOT NULL
                UNION
                SELECT department FROM admin_profiles WHERE department IS NOT NULL
            ) as all_departments 
            ORDER BY department ASC
        ");
    }
    
} catch (Exception $e) {
    error_log("Create User Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the page.";
}

// Set page title
$pageTitle = "Create User";
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

        /* Role Selection Cards */
        .role-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .role-card {
            position: relative;
            padding: 1.5rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
        }

        [data-theme="dark"] .role-card {
            border-color: var(--border-color);
            background: var(--bg-tertiary);
        }

        [data-theme="light"] .role-card {
            border-color: var(--border-color);
            background: var(--bg-secondary);
        }

        .role-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .role-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .role-card input[type="radio"] {
            display: none;
        }

        .role-card .role-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .role-card .role-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .role-card .role-description {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* Role-specific sections */
        .role-specific-section {
            display: none;
            animation: fadeIn 0.3s ease-out;
        }

        .role-specific-section.active {
            display: block;
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

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 0.5rem;
        }

        .password-strength-bar {
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.25rem;
        }

        .password-strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .password-strength-fill.weak {
            width: 25%;
            background: var(--error-color);
        }

        .password-strength-fill.fair {
            width: 50%;
            background: var(--warning-color);
        }

        .password-strength-fill.good {
            width: 75%;
            background: var(--info-color);
        }

        .password-strength-fill.strong {
            width: 100%;
            background: var(--success-color);
        }

        .password-strength-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
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

            .role-selection {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .role-selection {
                grid-template-columns: 1fr;
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
                    <h1 class="page-title">➕ Create User</h1>
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
                <br><small>Redirecting to user list...</small>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Create User Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" id="createUserForm" novalidate autocomplete="off">
                <!-- Role Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">👤 Select User Role</h3>
                    <div class="role-selection">
                        <div class="role-card <?= ($formData['role'] ?? '') === 'faculty' ? 'selected' : '' ?>" onclick="selectRole('faculty')">
                            <input type="radio" name="role" value="faculty" id="role_faculty"
                                   <?= ($formData['role'] ?? '') === 'faculty' ? 'checked' : '' ?>>
                            <div class="role-icon">🎓</div>
                            <div class="role-title">Faculty Member</div>
                            <div class="role-description">Teaching staff with subject assignments</div>
                        </div>
                        
                        <div class="role-card <?= ($formData['role'] ?? '') === 'student' ? 'selected' : '' ?>" onclick="selectRole('student')">
                            <input type="radio" name="role" value="student" id="role_student"
                                   <?= ($formData['role'] ?? '') === 'student' ? 'checked' : '' ?>>
                            <div class="role-icon">🎒</div>
                            <div class="role-title">Student</div>
                            <div class="role-description">Students enrolled in courses</div>
                        </div>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">📝 Basic Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="" 
                                   placeholder="Enter username" required autocomplete="username">
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                Username must be unique and cannot be changed later
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($formData['email'] ?? '') ?>" 
                                   placeholder="Enter email address" required>
                            <div class="form-text">
                                <i class="fas fa-envelope"></i> 
                                Used for login and notifications
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter password" required autocomplete="new-password">
                            <div class="password-strength">
                                <div class="password-strength-bar">
                                    <div class="password-strength-fill" id="passwordStrengthFill"></div>
                                </div>
                                <div class="password-strength-text" id="passwordStrengthText">
                                    Password strength will appear here
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm password" required autocomplete="new-password">
                            <div class="form-text" id="passwordMatchText">
                                <i class="fas fa-lock"></i> 
                                Passwords must match
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>" 
                                   placeholder="Enter first name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>" 
                                   placeholder="Enter last name" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($formData['phone'] ?? '') ?>" 
                                   placeholder="Enter phone number">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department *</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['department_name']) ?>" 
                                        <?= ($formData['department'] ?? '') === $dept['department_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Faculty-Specific Information -->
                <div class="form-section role-specific-section" id="faculty-section">
                    <h3 class="form-section-title">🎓 Faculty Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="employee_id" class="form-label">Employee ID *</label>
                            <input type="text" class="form-control" id="employee_id" name="employee_id" 
                                   value="<?= htmlspecialchars($formData['employee_id'] ?? '') ?>" 
                                   placeholder="Enter employee ID">
                            <div class="form-text">
                                <i class="fas fa-id-badge"></i> 
                                Unique identifier for faculty member
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="designation" class="form-label">Designation *</label>
                            <input type="text" class="form-control" id="designation" name="designation" 
                                   value="<?= htmlspecialchars($formData['designation'] ?? '') ?>" 
                                   placeholder="e.g., Professor, Associate Professor">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" 
                                   value="<?= htmlspecialchars($formData['qualification'] ?? '') ?>" 
                                   placeholder="e.g., Ph.D., M.Tech, M.Sc">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="experience_years" class="form-label">Experience (Years)</label>
                            <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                   value="<?= htmlspecialchars($formData['experience_years'] ?? '') ?>" 
                                   placeholder="Years of experience" min="0" max="50">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="office_location" class="form-label">Office Location</label>
                            <input type="text" class="form-control" id="office_location" name="office_location" 
                                   value="<?= htmlspecialchars($formData['office_location'] ?? '') ?>" 
                                   placeholder="e.g., Room 301, CS Building">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="specialization" class="form-label">Specialization/Research Areas</label>
                        <textarea class="form-control" id="specialization" name="specialization" rows="3" 
                                  placeholder="Areas of expertise, research interests, etc."><?= htmlspecialchars($formData['specialization'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Student-Specific Information -->
                <div class="form-section role-specific-section" id="student-section">
                    <h3 class="form-section-title">🎒 Student Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_number" class="form-label">Student Number *</label>
                            <input type="text" class="form-control" id="student_number" name="student_number" 
                                   value="<?= htmlspecialchars($formData['student_number'] ?? '') ?>" 
                                   placeholder="Enter student registration number">
                            <div class="form-text">
                                <i class="fas fa-id-card"></i> 
                                Unique registration number for the student
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="year_of_study" class="form-label">Year of Study *</label>
                            <select class="form-select" id="year_of_study" name="year_of_study">
                                <option value="">Select Year</option>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($formData['year_of_study'] ?? '') == $i ? 'selected' : '' ?>>
                                        Year <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="semester" class="form-label">Current Semester</label>
                            <select class="form-select" id="semester" name="semester">
                                <option value="">Select Semester</option>
                                <?php for ($i = 1; $i <= 2; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($formData['semester'] ?? '') == $i ? 'selected' : '' ?>>
                                        Semester <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-calendar"></i> 
                                Leave empty to default to Semester 1
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
                            ✅ Create User
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
            
            // Initialize password strength checker
            initializePasswordStrength();
            
            // Initialize role-specific sections
            initializeRoleSections();
            
            // Initialize form reset functionality
            initializeFormReset();
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
         * Select role and show/hide relevant sections
         */
        function selectRole(role) {
            // Remove selected class from all cards
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            document.querySelector(`#role_${role}`).closest('.role-card').classList.add('selected');
            
            // Check the radio button
            document.querySelector(`#role_${role}`).checked = true;
            
            // Show/hide role-specific sections
            showRoleSpecificSection(role);
            
            // Update required fields
            updateRequiredFields(role);
        }

        /**
         * Show role-specific section
         */
        function showRoleSpecificSection(role) {
            // Hide all role-specific sections
            document.querySelectorAll('.role-specific-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show the selected role section
            const targetSection = document.getElementById(`${role}-section`);
            if (targetSection) {
                targetSection.classList.add('active');
            }
        }

        /**
         * Update required fields based on role
         */
        function updateRequiredFields(role) {
            // Remove required attribute from all role-specific fields
            document.querySelectorAll('#faculty-section [required], #student-section [required]').forEach(field => {
                field.removeAttribute('required');
            });
            
            // Add required attribute to current role fields
            if (role === 'faculty') {
                document.getElementById('employee_id').setAttribute('required', '');
                document.getElementById('designation').setAttribute('required', '');
            } else if (role === 'student') {
                document.getElementById('student_number').setAttribute('required', '');
                document.getElementById('year_of_study').setAttribute('required', '');
            }
        }

        /**
         * Initialize role sections based on current selection
         */
        function initializeRoleSections() {
            const selectedRole = document.querySelector('input[name="role"]:checked');
            if (selectedRole) {
                showRoleSpecificSection(selectedRole.value);
                updateRequiredFields(selectedRole.value);
                selectedRole.closest('.role-card').classList.add('selected');
            }
        }

        /**
         * Initialize form validation
         */
        function initializeFormValidation() {
            const form = document.getElementById('createUserForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating User...';
                submitBtn.disabled = true;
            });
            
            // Real-time validation
            document.getElementById('email').addEventListener('input', validateEmail);
            document.getElementById('username').addEventListener('input', validateUsername);
            document.getElementById('confirm_password').addEventListener('input', validatePasswordMatch);
        }

        /**
         * Initialize password strength checker
         */
        function initializePasswordStrength() {
            const passwordInput = document.getElementById('password');
            const strengthFill = document.getElementById('passwordStrengthFill');
            const strengthText = document.getElementById('passwordStrengthText');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);
                
                // Update visual indicator
                strengthFill.className = `password-strength-fill ${strength.level}`;
                strengthText.textContent = strength.text;
                strengthText.style.color = strength.color;
            });
        }

        /**
         * Calculate password strength
         */
        function calculatePasswordStrength(password) {
            let score = 0;
            let feedback = [];
            
            if (password.length >= 8) score += 1;
            else feedback.push('at least 8 characters');
            
            if (/[a-z]/.test(password)) score += 1;
            else feedback.push('lowercase letters');
            
            if (/[A-Z]/.test(password)) score += 1;
            else feedback.push('uppercase letters');
            
            if (/[0-9]/.test(password)) score += 1;
            else feedback.push('numbers');
            
            if (/[^A-Za-z0-9]/.test(password)) score += 1;
            else feedback.push('special characters');
            
            const levels = [
                { level: 'weak', text: 'Very Weak', color: '#ef4444' },
                { level: 'weak', text: 'Weak', color: '#ef4444' },
                { level: 'fair', text: 'Fair', color: '#f59e0b' },
                { level: 'good', text: 'Good', color: '#3b82f6' },
                { level: 'strong', text: 'Strong', color: '#10b981' }
            ];
            
            if (password.length === 0) {
                return { level: 'weak', text: 'Enter a password', color: '#64748b' };
            }
            
            if (feedback.length > 0) {
                return { 
                    level: levels[score].level, 
                    text: `Missing: ${feedback.join(', ')}`, 
                    color: levels[score].color 
                };
            }
            
            return levels[score] || levels[4];
        }

        /**
         * Validate password match
         */
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatchText');
            
            if (confirmPassword.length === 0) {
                matchText.innerHTML = '<i class="fas fa-lock"></i> Passwords must match';
                matchText.style.color = '#64748b';
                return true;
            }
            
            if (password === confirmPassword) {
                matchText.innerHTML = '<i class="fas fa-check text-success"></i> Passwords match';
                matchText.style.color = '#10b981';
                return true;
            } else {
                matchText.innerHTML = '<i class="fas fa-times text-danger"></i> Passwords do not match';
                matchText.style.color = '#ef4444';
                return false;
            }
        }

        /**
         * Validate email format
         */
        function validateEmail() {
            const email = document.getElementById('email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            const field = document.getElementById('email');
            if (email.length === 0) {
                field.classList.remove('is-valid', 'is-invalid');
                return true;
            }
            
            if (emailRegex.test(email)) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                return true;
            } else {
                field.classList.remove('is-valid');
                field.classList.add('is-invalid');
                return false;
            }
        }

        /**
         * Validate username
         */
        function validateUsername() {
            const username = document.getElementById('username').value;
            const field = document.getElementById('username');
            
            if (username.length === 0) {
                field.classList.remove('is-valid', 'is-invalid');
                return true;
            }
            
            if (username.length >= 3 && /^[a-zA-Z0-9_]+$/.test(username)) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                return true;
            } else {
                field.classList.remove('is-valid');
                field.classList.add('is-invalid');
                return false;
            }
        }

        /**
         * Validate entire form
         */
        function validateForm() {
            let isValid = true;
            const errors = [];
            
            // Check required fields
            const requiredFields = document.querySelectorAll('input[required], select[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    errors.push(`${field.previousElementSibling.textContent.replace('*', '')} is required`);
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Check role selection
            const roleSelected = document.querySelector('input[name="role"]:checked');
            if (!roleSelected) {
                isValid = false;
                errors.push('Please select a user role');
            }
            
            // Check password match
            if (!validatePasswordMatch()) {
                isValid = false;
                errors.push('Passwords do not match');
            }
            
            // Check email format
            if (!validateEmail()) {
                isValid = false;
                errors.push('Please enter a valid email address');
            }
            
            // Check password strength
            const password = document.getElementById('password').value;
            if (password.length < 8) {
                isValid = false;
                errors.push('Password must be at least 8 characters long');
            }
            
            if (!isValid) {
                showError('Please fix the following errors:\n• ' + errors.join('\n• '));
            }
            
            return isValid;
        }

        /**
         * Initialize form reset functionality
         */
        function initializeFormReset() {
            // Reset form when role changes
            document.querySelectorAll('input[name="role"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    selectRole(this.value);
                });
            });
        }

        /**
         * Reset form to initial state
         */
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.getElementById('createUserForm').reset();
                
                // Reset role selection
                document.querySelectorAll('.role-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                // Hide role-specific sections
                document.querySelectorAll('.role-specific-section').forEach(section => {
                    section.classList.remove('active');
                });
                
                // Reset validation classes
                document.querySelectorAll('.is-valid, .is-invalid').forEach(field => {
                    field.classList.remove('is-valid', 'is-invalid');
                });
                
                // Reset password strength
                document.getElementById('passwordStrengthFill').className = 'password-strength-fill';
                document.getElementById('passwordStrengthText').textContent = 'Password strength will appear here';
                document.getElementById('passwordStrengthText').style.color = '#64748b';
                
                // Reset password match text
                document.getElementById('passwordMatchText').innerHTML = '<i class="fas fa-lock"></i> Passwords must match';
                document.getElementById('passwordMatchText').style.color = '#64748b';
            }
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
                    <strong>Error:</strong> ${message.replace(/\n/g, '<br>')}
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
        window.selectRole = selectRole;
        window.resetForm = resetForm;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>