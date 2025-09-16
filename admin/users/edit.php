<?php
/**
 * Admin Users Edit - User Edit Interface
 * Timetable Management System
 * 
 * Professional interface for admin to edit existing user accounts
 * with role-specific profile updates and status management
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
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$userManager = new User();

// Get user ID to edit
$editUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$editUserId) {
    header('Location: index.php?error=' . urlencode('User ID is required'));
    exit;
}

// Initialize variables
$user = null;
$userProfile = null;
$error_message = '';
$success_message = '';
$departments = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error
        $formData = $_POST;
        
        // Ensure $user is loaded before any role-specific logic
        if (!$user) {
            $user = $db->fetchRow(
                "
        SELECT u.*, 
               CASE 
                   WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                   WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                   WHEN u.role = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                   ELSE u.username
               END as display_name,
               CASE 
                   WHEN u.role = 'student' THEN s.department
                   WHEN u.role = 'faculty' THEN f.department
                   WHEN u.role = 'admin' THEN a.department
                   ELSE NULL
               END as current_department,
               CASE 
                   WHEN u.role = 'student' THEN s.year_of_study
                   ELSE NULL
               END as current_year,
               CASE 
                   WHEN u.role = 'faculty' THEN f.designation
                   WHEN u.role = 'admin' THEN a.designation
                   ELSE NULL
               END as current_designation
        FROM users u
        LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
        LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
        LEFT JOIN admin_profiles a ON u.user_id = a.user_id AND u.role = 'admin'
        WHERE u.user_id = ?
                ",
                [$editUserId]
            );
            if (!$user) {
                header('Location: index.php?error=' . urlencode('User not found'));
                exit;
            }
        }
        
        // Basic validation
        $required_fields = ['username', 'email', 'first_name', 'last_name', 'status'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Email validation
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Password validation (only if changing)
        if (!empty($_POST['password'])) {
            if ($_POST['password'] !== $_POST['confirm_password']) {
                throw new Exception('Passwords do not match.');
            }
            
            if (strlen($_POST['password']) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }
        }
        
        // Role-specific validation
        if ($user['role'] === 'student') {
            if (empty($_POST['department']) || empty($_POST['year_of_study'])) {
                throw new Exception('Department and year of study are required for students.');
            }
        } elseif ($user['role'] === 'faculty') {
            if (empty($_POST['department']) || empty($_POST['designation'])) {
                throw new Exception('Department and designation are required for faculty.');
            }
        }
        
        $db->beginTransaction();
        
        try {
            // Update user table
            $updateUserSql = "UPDATE users SET username = ?, email = ?, status = ? WHERE user_id = ?";
            $updateUserParams = [trim($_POST['username']), trim($_POST['email']), $_POST['status'], $editUserId];
            
            // Add password update if provided
            if (!empty($_POST['password'])) {
                $updateUserSql = "UPDATE users SET username = ?, email = ?, password_hash = ?, status = ? WHERE user_id = ?";
                $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $updateUserParams = [trim($_POST['username']), trim($_POST['email']), $passwordHash, $_POST['status'], $editUserId];
            }
            
            $db->execute($updateUserSql, $updateUserParams);
            
            // Update role-specific profile based on current user role
            if ($user['role'] === 'student') {
                // Update student profile
                $updateStudentSql = "
                    UPDATE students 
                    SET first_name = ?, last_name = ?, department = ?, year_of_study = ?, 
                        semester = ?, phone = ?, updated_at = NOW()
                    WHERE user_id = ?
                ";
                $db->execute($updateStudentSql, [
                    trim($_POST['first_name']),
                    trim($_POST['last_name']),
                    trim($_POST['department']),
                    (int)$_POST['year_of_study'],
                    !empty($_POST['semester']) ? (int)$_POST['semester'] : $userProfile['semester'],
                    !empty($_POST['phone']) ? trim($_POST['phone']) : null,
                    $editUserId
                ]);
                
                // Update student number if provided
                if (!empty($_POST['student_number'])) {
                    $db->execute(
                        "UPDATE students SET student_number = ? WHERE user_id = ?",
                        [trim($_POST['student_number']), $editUserId]
                    );
                }
                
            } elseif ($user['role'] === 'faculty') {
                // Update faculty profile
                $updateFacultySql = "
                    UPDATE faculty 
                    SET first_name = ?, last_name = ?, department = ?, designation = ?, 
                        phone = ?, specialization = ?, qualification = ?, experience_years = ?, 
                        office_location = ?, updated_at = NOW()
                    WHERE user_id = ?
                ";
                $db->execute($updateFacultySql, [
                    trim($_POST['first_name']),
                    trim($_POST['last_name']),
                    trim($_POST['department']),
                    trim($_POST['designation']),
                    !empty($_POST['phone']) ? trim($_POST['phone']) : null,
                    !empty($_POST['specialization']) ? trim($_POST['specialization']) : null,
                    !empty($_POST['qualification']) ? trim($_POST['qualification']) : null,
                    !empty($_POST['experience_years']) ? (int)$_POST['experience_years'] : null,
                    !empty($_POST['office_location']) ? trim($_POST['office_location']) : null,
                    $editUserId
                ]);
                
                // Update employee ID if provided
                if (!empty($_POST['employee_id'])) {
                    $db->execute(
                        "UPDATE faculty SET employee_id = ? WHERE user_id = ?",
                        [trim($_POST['employee_id']), $editUserId]
                    );
                }
                
            } elseif ($user['role'] === 'admin') {
                // Update admin profile
                $updateAdminSql = "
                    UPDATE admin_profiles 
                    SET first_name = ?, last_name = ?, department = ?, designation = ?, 
                        phone = ?, bio = ?, office_location = ?, updated_at = NOW()
                    WHERE user_id = ?
                ";
                $db->execute($updateAdminSql, [
                    trim($_POST['first_name']),
                    trim($_POST['last_name']),
                    trim($_POST['department']),
                    trim($_POST['designation']),
                    !empty($_POST['phone']) ? trim($_POST['phone']) : null,
                    !empty($_POST['bio']) ? trim($_POST['bio']) : null,
                    !empty($_POST['office_location']) ? trim($_POST['office_location']) : null,
                    $editUserId
                ]);
                
                // Update employee ID if provided
                if (!empty($_POST['employee_id'])) {
                    $db->execute(
                        "UPDATE admin_profiles SET employee_id = ? WHERE user_id = ?",
                        [trim($_POST['employee_id']), $editUserId]
                    );
                }
            }
            
            $db->commit();
            $success_message = 'User profile updated successfully.';
            
            // Clear form data to show updated values
            $formData = [];
            
            // Redirect after short delay to show success message
            header("refresh:2;url=index.php");
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

try {
    // Get comprehensive user information
    $user = $db->fetchRow("
        SELECT u.*, 
               CASE 
                   WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                   WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                   WHEN u.role = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                   ELSE u.username
               END as full_name,
               CASE 
                   WHEN u.role = 'student' THEN s.first_name
                   WHEN u.role = 'faculty' THEN f.first_name
                   WHEN u.role = 'admin' THEN a.first_name
                   ELSE NULL
               END as first_name,
               CASE 
                   WHEN u.role = 'student' THEN s.last_name
                   WHEN u.role = 'faculty' THEN f.last_name
                   WHEN u.role = 'admin' THEN a.last_name
                   ELSE NULL
               END as last_name,
               CASE 
                   WHEN u.role = 'student' THEN s.department
                   WHEN u.role = 'faculty' THEN f.department
                   WHEN u.role = 'admin' THEN a.department
                   ELSE NULL
               END as current_department,
               CASE 
                   WHEN u.role = 'student' THEN s.student_number
                   WHEN u.role = 'faculty' THEN f.employee_id
                   WHEN u.role = 'admin' THEN a.employee_id
                   ELSE NULL
               END as identifier,
               CASE 
                   WHEN u.role = 'student' THEN s.phone
                   WHEN u.role = 'faculty' THEN f.phone
                   WHEN u.role = 'admin' THEN a.phone
                   ELSE NULL
               END as current_phone,
               CASE 
                   WHEN u.role = 'faculty' THEN f.designation
                   WHEN u.role = 'admin' THEN a.designation
                   ELSE NULL
               END as current_designation,
               CASE 
                   WHEN u.role = 'student' THEN s.student_number
                   WHEN u.role = 'faculty' THEN f.employee_id
                   WHEN u.role = 'admin' THEN a.employee_id
                   ELSE NULL
               END as employee_id,
               CASE 
                   WHEN u.role = 'faculty' THEN f.designation
                   WHEN u.role = 'admin' THEN a.designation
                   ELSE NULL
               END as designation,
               CASE 
                   WHEN u.role = 'faculty' THEN f.qualification
                   ELSE NULL
               END as qualification,
               CASE 
                   WHEN u.role = 'faculty' THEN f.specialization
                   ELSE NULL
               END as specialization,
               CASE 
                   WHEN u.role = 'student' THEN s.year_of_study
                   ELSE NULL
               END as year_of_study,
               CASE 
                   WHEN u.role = 'student' THEN s.semester
                   ELSE NULL
               END as semester
        FROM users u
        LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
        LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
        LEFT JOIN admin_profiles a ON u.user_id = a.user_id AND u.role = 'admin'
        WHERE u.user_id = ?
    ", [$editUserId]);
    
    if (!$user) {
        header('Location: index.php?error=' . urlencode('User not found'));
        exit;
    }
    
    // Get role-specific profile data
    $userProfile = null; // Initialize
    if ($user['role'] === 'student') {
        $userProfile = $db->fetchRow("SELECT * FROM students WHERE user_id = ?", [$editUserId]);
    } elseif ($user['role'] === 'faculty') {
        $userProfile = $db->fetchRow("SELECT * FROM faculty WHERE user_id = ?", [$editUserId]);
    } elseif ($user['role'] === 'admin') {
        $userProfile = $db->fetchRow("SELECT * FROM admin_profiles WHERE user_id = ?", [$editUserId]);
    }
    
    // DEBUG: Check if profile was loaded
    echo "<!-- DEBUG PROFILE: Role=" . $user['role'] . ", Profile=" . ($userProfile ? 'LOADED' : 'NULL') . " -->";
    
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
    error_log("Edit User Error: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('Failed to load user data'));
    exit;
}

// Set page title
$pageTitle = "Edit User - " . ($user['full_name'] ?? $user['username']);
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
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/admin.css">
    
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

        .user-info-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar-large {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .user-info-details h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .user-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }

        .user-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
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

        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-badge.inactive {
            background: rgba(100, 116, 139, 0.2);
            color: #64748b;
            border: 1px solid rgba(100, 116, 139, 0.3);
        }

        .status-badge.rejected {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Password Section Toggle */
        .password-toggle {
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            font-weight: 500;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            background: var(--primary-color-alpha);
        }

        .password-section {
            display: none;
            margin-top: 1rem;
        }

        .password-section.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
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

            .user-info-header {
                flex-direction: column;
                text-align: center;
            }

            .user-meta {
                justify-content: center;
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
                <div class="user-info-header">
                    <div class="user-avatar-large">
                        <?php 
                        $name = $user['full_name'] ?? $user['username'];
                        $initials = '';
                        $nameParts = explode(' ', $name);
                        foreach (array_slice($nameParts, 0, 2) as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-info-details">
                        <h2>✏️ Edit <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></h2>
                        <div class="user-meta">
                            <span><i class="fas fa-user"></i> <?= ucfirst($user['role']) ?></span>
                            <span><i class="fas fa-id-badge"></i> <?= htmlspecialchars($user['identifier'] ?? 'N/A') ?></span>
                            <span class="status-badge <?= $user['status'] ?>">
                                <i class="fas fa-circle"></i> <?= ucfirst($user['status']) ?>
                            </span>
                            <span><i class="fas fa-calendar"></i> Joined <?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
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

        <!-- Edit User Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" id="editUserForm" novalidate>
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">📝 Basic Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($formData['username'] ?? $user['username'] ?? '') ?>" 
                                   placeholder="Enter username" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                Username must be unique
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($formData['email'] ?? $user['email'] ?? '') ?>" 
                                   placeholder="Enter email address" required>
                            <div class="form-text">
                                <i class="fas fa-envelope"></i> 
                                Used for login and notifications
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($formData['first_name'] ?? $user['first_name'] ?? '') ?>" 
                                   placeholder="Enter first name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?= htmlspecialchars($formData['last_name'] ?? $user['last_name'] ?? '') ?>" 
                                   placeholder="Enter last name" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($formData['phone'] ?? $user['current_phone'] ?? '') ?>" 
                                   placeholder="Enter phone number">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Account Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <?php
                                $statuses = ['pending', 'active', 'inactive', 'rejected'];
                                $currentStatus = $formData['status'] ?? $user['status'];
                                ?>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status ?>" <?= $currentStatus === $status ? 'selected' : '' ?>>
                                        <?= ucfirst($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-toggle-on"></i> 
                                Change user's account status
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Password Change Section -->
                <div class="form-section">
                    <h3 class="form-section-title">🔐 Password Management</h3>
                    
                    <div class="password-toggle" onclick="togglePasswordSection()">
                        <i class="fas fa-key"></i>
                        <span>Change Password</span>
                        <i class="fas fa-chevron-down" id="passwordToggleIcon"></i>
                    </div>
                    
                    <div class="password-section" id="passwordSection">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter new password">
                                <div class="form-text">
                                    <i class="fas fa-lock"></i> 
                                    Leave empty to keep current password
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm new password">
                                <div class="form-text" id="passwordMatchText">
                                    <i class="fas fa-check"></i> 
                                    Passwords must match
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Role-Specific Information -->
                <?php if ($user['role'] === 'student'): ?>
                <!-- Student-Specific Information -->
                <div class="form-section">
                    <h3 class="form-section-title">🎒 Student Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_number" class="form-label">Student Number</label>
                            <input type="text" class="form-control" id="student_number" name="student_number" 
                                   value="<?= htmlspecialchars($formData['student_number'] ?? $user['identifier'] ?? '') ?>" 
                                   placeholder="Enter student registration number">
                            <div class="form-text">
                                <i class="fas fa-id-card"></i> 
                                Unique registration number for the student
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department *</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <?php 
                                $currentDept = $formData['department'] ?? $user['current_department'] ?? '';
                                foreach ($departments as $dept): 
                                ?>
                                    <option value="<?= htmlspecialchars($dept['department_name']) ?>" 
                                            <?= $currentDept === $dept['department_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="year_of_study" class="form-label">Year of Study *</label>
                            <select class="form-select" id="year_of_study" name="year_of_study" required>
                                <option value="">Select Year</option>
                                <?php 
                                $currentYear = $formData['year_of_study'] ?? $user['year_of_study'] ?? '';
                                for ($i = 1; $i <= 4; $i++): 
                                ?>
                                    <option value="<?= $i ?>" <?= $currentYear == $i ? 'selected' : '' ?>>
                                        Year <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="semester" class="form-label">Current Semester</label>
                            <select class="form-select" id="semester" name="semester">
                                <option value="">Select Semester</option>
                                <?php 
                                $currentSem = $formData['semester'] ?? $user['semester'] ?? '';
                                for ($i = 1; $i <= 8; $i++): 
                                ?>
                                    <option value="<?= $i ?>" <?= $currentSem == $i ? 'selected' : '' ?>>
                                        Semester <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($user['role'] === 'faculty'): ?>
                <!-- Faculty-Specific Information -->
                <div class="form-section">
                    <h3 class="form-section-title">🎓 Faculty Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="employee_id" class="form-label">Employee ID</label>
                            <input type="text" class="form-control" id="employee_id" name="employee_id" 
                                   value="<?= htmlspecialchars($formData['employee_id'] ?? $user['employee_id'] ?? '') ?>" 
                                   placeholder="Enter employee ID">
                            <div class="form-text">
                                <i class="fas fa-id-badge"></i> 
                                Unique identifier for faculty member
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department *</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <?php 
                                $currentDept = $formData['department'] ?? $user['current_department'] ?? '';
                                foreach ($departments as $dept): 
                                ?>
                                    <option value="<?= htmlspecialchars($dept['department_name']) ?>" 
                                            <?= $currentDept === $dept['department_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="designation" class="form-label">Designation *</label>
                            <input type="text" class="form-control" id="designation" name="designation" 
                                   value="<?= htmlspecialchars($formData['designation'] ?? $user['designation'] ?? '') ?>" 
                                   placeholder="e.g., Professor, Associate Professor" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" 
                                   value="<?= htmlspecialchars($formData['qualification'] ?? $user['qualification'] ?? '') ?>" 
                                   placeholder="e.g., Ph.D., M.Tech, M.Sc">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="experience_years" class="form-label">Experience (Years)</label>
                            <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                   value="<?= htmlspecialchars($formData['experience_years'] ?? $user['experience_years'] ?? '') ?>" 
                                   placeholder="Years of experience" min="0" max="50">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="office_location" class="form-label">Office Location</label>
                            <input type="text" class="form-control" id="office_location" name="office_location" 
                                   value="<?= htmlspecialchars($formData['office_location'] ?? $userProfile['office_location'] ?? '') ?>" 
                                   placeholder="e.g., Room 301, CS Building">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="specialization" class="form-label">Specialization/Research Areas</label>
                        <textarea class="form-control" id="specialization" name="specialization" rows="3" 
                                  placeholder="Areas of expertise, research interests, etc."><?= htmlspecialchars($formData['specialization'] ?? $userProfile['specialization'] ?? '') ?></textarea>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($user['role'] === 'admin'): ?>
                <!-- Admin-Specific Information -->
                <div class="form-section">
                    <h3 class="form-section-title">👑 Administrator Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="employee_id" class="form-label">Employee ID</label>
                            <input type="text" class="form-control" id="employee_id" name="employee_id" 
                                   value="<?= htmlspecialchars($formData['employee_id'] ?? $userProfile['employee_id'] ?? '') ?>" 
                                   placeholder="ADMIN001">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department">
                                <option value="">Select Department</option>
                                <?php 
                                $currentDept = $formData['department'] ?? $user['current_department'] ?? '';
                                foreach ($departments as $dept): 
                                ?>
                                    <option value="<?= htmlspecialchars($dept['department_name']) ?>" 
                                            <?= $currentDept === $dept['department_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation" 
                                   value="<?= htmlspecialchars($formData['designation'] ?? $userProfile['designation'] ?? '') ?>" 
                                   placeholder="System Administrator">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="office_location" class="form-label">Office Location</label>
                            <input type="text" class="form-control" id="office_location" name="office_location" 
                                   value="<?= htmlspecialchars($formData['office_location'] ?? $userProfile['office_location'] ?? '') ?>" 
                                   placeholder="Admin Office, Main Building">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio/Description</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3" 
                                  placeholder="Brief description or bio"><?= htmlspecialchars($formData['bio'] ?? $userProfile['bio'] ?? '') ?></textarea>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Form Actions -->
                <div class="form-section">
                    <div class="d-flex gap-3 justify-content-end flex-wrap">
                        <a href="index.php" class="btn-action btn-outline">
                            ❌ Cancel
                        </a>
                        <button type="button" class="btn-action btn-outline" onclick="resetForm()">
                            🔄 Reset Changes
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn">
                            ✅ Update User
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
            
            // Initialize password change functionality
            initializePasswordSection();
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
            const form = document.getElementById('editUserForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating User...';
                submitBtn.disabled = true;
            });
            
            // Real-time validation
            document.getElementById('email').addEventListener('input', validateEmail);
            document.getElementById('username').addEventListener('input', validateUsername);
            
            // Password validation if password section is active
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (passwordInput && confirmPasswordInput) {
                passwordInput.addEventListener('input', validatePasswordMatch);
                confirmPasswordInput.addEventListener('input', validatePasswordMatch);
            }
        }

        /**
         * Initialize password section functionality
         */
        function initializePasswordSection() {
            // Password match validation
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (passwordInput && confirmPasswordInput) {
                passwordInput.addEventListener('input', validatePasswordMatch);
                confirmPasswordInput.addEventListener('input', validatePasswordMatch);
            }
        }

        /**
         * Toggle password change section
         */
        function togglePasswordSection() {
            const section = document.getElementById('passwordSection');
            const icon = document.getElementById('passwordToggleIcon');
            
            section.classList.toggle('active');
            
            if (section.classList.contains('active')) {
                icon.className = 'fas fa-chevron-up';
            } else {
                icon.className = 'fas fa-chevron-down';
                // Clear password fields when hiding
                document.getElementById('password').value = '';
                document.getElementById('confirm_password').value = '';
                resetPasswordMatchIndicator();
            }
        }

        /**
         * Validate password match
         */
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('passwordMatchText');
            
            if (password.length === 0 && confirmPassword.length === 0) {
                matchText.innerHTML = '<i class="fas fa-check"></i> Passwords must match';
                matchText.style.color = '#64748b';
                return true;
            }
            
            if (password === confirmPassword && password.length > 0) {
                matchText.innerHTML = '<i class="fas fa-check text-success"></i> Passwords match';
                matchText.style.color = '#10b981';
                return true;
            } else if (password !== confirmPassword) {
                matchText.innerHTML = '<i class="fas fa-times text-danger"></i> Passwords do not match';
                matchText.style.color = '#ef4444';
                return false;
            }
            
            return true;
        }

        /**
         * Reset password match indicator
         */
        function resetPasswordMatchIndicator() {
            const matchText = document.getElementById('passwordMatchText');
            matchText.innerHTML = '<i class="fas fa-check"></i> Passwords must match';
            matchText.style.color = '#64748b';
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
            
            // Check email format
            if (!validateEmail()) {
                isValid = false;
                errors.push('Please enter a valid email address');
            }
            
            // Check password if being changed
            const passwordSection = document.getElementById('passwordSection');
            if (passwordSection.classList.contains('active')) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password.length > 0) {
                    if (password.length < 8) {
                        isValid = false;
                        errors.push('Password must be at least 8 characters long');
                    }
                    
                    if (!validatePasswordMatch()) {
                        isValid = false;
                        errors.push('Passwords do not match');
                    }
                }
            }
            
            if (!isValid) {
                showError('Please fix the following errors:\n• ' + errors.join('\n• '));
            }
            
            return isValid;
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
        window.togglePasswordSection = togglePasswordSection;
        window.resetForm = resetForm;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>