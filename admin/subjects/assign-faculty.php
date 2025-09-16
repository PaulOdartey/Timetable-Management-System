<?php
/**
 * Admin Subject Assign Faculty - Faculty Assignment Interface
 * Timetable Management System
 * 
 * Professional interface for admin to assign faculty members to subjects
 * with department filtering, workload management, and assignment tracking
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Subject.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$subjectManager = new Subject();

// Initialize variables
$error_message = '';
$success_message = '';
$subjects = [];
$faculty = [];
$departments = [];
$formData = [];
$existingAssignments = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error
        $formData = $_POST;
        
        // Basic validation
        $required_fields = ['subject_id', 'faculty_id', 'max_students'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Validate max students
        $maxStudents = (int)$_POST['max_students'];
        if ($maxStudents < 1 || $maxStudents > 200) {
            throw new Exception('Maximum students must be between 1 and 200.');
        }
        
        // Check for duplicate assignment
        $existingCheck = $db->fetchRow("
            SELECT assignment_id FROM faculty_subjects 
            WHERE faculty_id = ? AND subject_id = ? AND is_active = 1
        ", [(int)$_POST['faculty_id'], (int)$_POST['subject_id']]);
        
        if ($existingCheck) {
            throw new Exception('This faculty member is already assigned to this subject.');
        }
        
        // Prepare assignment data
        $assignmentData = [
            'faculty_id' => (int)$_POST['faculty_id'],
            'subject_id' => (int)$_POST['subject_id'],
            'max_students' => $maxStudents,
            'assigned_by' => $userId,
            'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null,
            'is_active' => 1
        ];
        
        // Create assignment
        $db->beginTransaction();
        
        try {
            $sql = "INSERT INTO faculty_subjects (faculty_id, subject_id, max_students, assigned_by, notes, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $db->execute($sql, [
                $assignmentData['faculty_id'],
                $assignmentData['subject_id'],
                $assignmentData['max_students'],
                $assignmentData['assigned_by'],
                $assignmentData['notes'],
                $assignmentData['is_active']
            ]);
            
            $assignmentId = $db->lastInsertId();
            
            // Get assignment details for notification
            $assignmentDetails = $db->fetchRow("
                SELECT fs.*, s.subject_code, s.subject_name, 
                       CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                       f.employee_id
                FROM faculty_subjects fs
                JOIN subjects s ON fs.subject_id = s.subject_id
                JOIN faculty f ON fs.faculty_id = f.faculty_id
                WHERE fs.assignment_id = ?
            ", [$assignmentId]);
            
            $db->commit();
            
            $success_message = "Faculty successfully assigned to subject! Assignment ID: {$assignmentId}";
            $formData = []; // Clear form data on success
            
            // Create system notification (if Notification class exists)
            if (class_exists('Notification')) {
                $notificationManager = new Notification();
                $notificationManager->sendSystemNotification('faculty_assigned', [
                    'assignment_id' => $assignmentId,
                    'faculty_name' => $assignmentDetails['faculty_name'],
                    'subject_name' => $assignmentDetails['subject_name'],
                    'user_id' => $assignmentDetails['faculty_id']
                ], $userId);
            }
            
            // Redirect after short delay to show success message
            header("refresh:3;url=index.php");
            
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

try {
    // Get all active subjects
    $subjects = $db->fetchAll("
        SELECT s.*, 
               COUNT(fs.assignment_id) as assigned_faculty_count,
               GROUP_CONCAT(CONCAT(f.first_name, ' ', f.last_name) SEPARATOR ', ') as assigned_faculty
        FROM subjects s
        LEFT JOIN faculty_subjects fs ON s.subject_id = fs.subject_id AND fs.is_active = 1
        LEFT JOIN faculty f ON fs.faculty_id = f.faculty_id
        WHERE s.is_active = 1
        GROUP BY s.subject_id
        ORDER BY s.subject_code ASC
    ");
    
    // Get all active faculty
    $faculty = $db->fetchAll("
        SELECT f.*, u.status,
               COUNT(fs.assignment_id) as current_assignments
        FROM faculty f
        JOIN users u ON f.user_id = u.user_id
        LEFT JOIN faculty_subjects fs ON f.faculty_id = fs.faculty_id AND fs.is_active = 1
        WHERE u.status = 'active'
        GROUP BY f.faculty_id
        ORDER BY f.department, f.first_name, f.last_name
    ");
    
    // Get departments
    $departments = $db->fetchAll("
        SELECT DISTINCT department_name, department_code 
        FROM departments 
        WHERE is_active = 1 
        ORDER BY department_name ASC
    ");
    
    // If no departments table, get from existing data
    if (empty($departments)) {
        $departments = $db->fetchAll("
            SELECT DISTINCT department as department_name, department as department_code
            FROM (
                SELECT department FROM faculty WHERE department IS NOT NULL
                UNION 
                SELECT department FROM subjects WHERE department IS NOT NULL
            ) as all_departments 
            ORDER BY department ASC
        ");
    }
    
    // Get existing assignments for display
    $existingAssignments = $db->fetchAll("
        SELECT fs.*, s.subject_code, s.subject_name, s.department as subject_dept,
               CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
               f.employee_id, f.department as faculty_dept, f.designation,
               CONCAT(admin.first_name, ' ', admin.last_name) as assigned_by_name
        FROM faculty_subjects fs
        JOIN subjects s ON fs.subject_id = s.subject_id
        JOIN faculty f ON fs.faculty_id = f.faculty_id
        LEFT JOIN admin_profiles admin ON fs.assigned_by = admin.user_id
        WHERE fs.is_active = 1
        ORDER BY fs.assigned_date DESC
        LIMIT 10
    ");
    
} catch (Exception $e) {
    error_log("Assign Faculty Error: " . $e->getMessage());
    $error_message = "An error occurred while loading assignment data.";
}

// Set page title
$pageTitle = "Assign Faculty to Subject";
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

        .form-label.required::after {
            content: ' *';
            color: var(--error-color);
            font-weight: 500;
            margin-left: 0.25rem;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            font-weight: 500;
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

        /* Faculty Cards */
        .faculty-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .faculty-card {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        [data-theme="dark"] .faculty-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .faculty-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .faculty-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
        }

        .faculty-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .faculty-card input[type="radio"] {
            display: none;
        }

        .faculty-info h5 {
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }

        .faculty-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .assignment-count {
            background: var(--info-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Subject Info Display */
        .subject-info-card {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
            display: none;
        }

        .subject-info-card.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        /* Assignments Table */
        .assignments-table {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            overflow: hidden;
        }

        [data-theme="dark"] .assignments-table {
            background: var(--bg-tertiary);
        }

        [data-theme="light"] .assignments-table {
            background: var(--bg-secondary);
        }

        /* Recent Assignments: Card List */
        .assignment-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .assignment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 0.85rem;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .assignment-item:hover {
            transform: translateY(-2px);
            border-color: var(--primary-color);
            box-shadow: 0 10px 20px rgba(0,0,0,0.12);
        }
        [data-theme="light"] .assignment-item {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }
        .assignment-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }
        .badge-subject {
            background: rgba(99, 102, 241, 0.12);
            color: var(--text-primary);
            border: 1px solid rgba(99, 102, 241, 0.35);
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .subject-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        .faculty-line {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .badge-date {
            background: rgba(59, 130, 246, 0.12);
            color: var(--text-primary);
            border: 1px solid rgba(59, 130, 246, 0.35);
            padding: 0.25rem 0.5rem;
            border-radius: 999px;
            font-size: 0.75rem;
            white-space: nowrap;
        }

        /* Compact section title for Recent Assignments */
        .section-title-sm {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0.75rem 0 0.5rem 0;
        }
        .section-title-sm i { font-size: 0.95rem; }

        .table {
            margin-bottom: 0;
            color: var(--text-primary);
        }

        .table th {
            background: rgba(99, 102, 241, 0.1);
            border-color: var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .table td {
            border-color: var(--border-color);
            vertical-align: middle;
        }

        /* Dark mode table */
        [data-theme="dark"] .table {
            --bs-table-bg: transparent;
        }

        [data-theme="dark"] .table th {
            background: var(--bg-primary);
        }

        [data-theme="dark"] .table td {
            background: var(--bg-secondary);
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

            .faculty-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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

            .faculty-grid {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                border-radius: 12px;
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
        <!-- Page Header -->
    <div class="page-header">
        <div class="header-card glass-card fade-in">
            <div class="header-text">
                <h1 class="page-title">👨‍🏫 Assign Faculty to Subject</h1>
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
            <br><small>Redirecting to subject list...</small>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger glass-card fade-in" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

        <div class="row">
            <!-- Assignment Form -->
            <div class="col-lg-8">
                <div class="form-container glass-card slide-up">
                    <form method="POST" id="assignFacultyForm" novalidate>
                        <!-- Subject Selection -->
                        <div class="form-section">
                            <h3 class="form-section-title">📚 Select Subject</h3>
                            <div class="mb-3">
                                <label for="subject_id" class="form-label required">Subject</label>
                                <select class="form-select" id="subject_id" name="subject_id" required onchange="updateSubjectInfo()">
                                    <option value="">Select a subject to assign</option>
                                    <?php
                                        // Group subjects by department for easier navigation
                                        $subjectsByDept = [];
                                        foreach ($subjects as $subject) {
                                            $deptName = $subject['department'] ?? '';
                                            if ($deptName === '' || $deptName === null) {
                                                $deptName = 'Unassigned / No Department';
                                            }
                                            if (!isset($subjectsByDept[$deptName])) {
                                                $subjectsByDept[$deptName] = [];
                                            }
                                            $subjectsByDept[$deptName][] = $subject;
                                        }
                                        ksort($subjectsByDept, SORT_NATURAL | SORT_FLAG_CASE);
                                    ?>
                                    <?php foreach ($subjectsByDept as $deptLabel => $deptSubjects): ?>
                                        <optgroup label="<?= htmlspecialchars($deptLabel) ?>">
                                            <?php foreach ($deptSubjects as $subject): ?>
                                                <option value="<?= $subject['subject_id'] ?>"
                                                        data-code="<?= htmlspecialchars($subject['subject_code']) ?>"
                                                        data-name="<?= htmlspecialchars($subject['subject_name']) ?>"
                                                        data-department="<?= htmlspecialchars($subject['department']) ?>"
                                                        data-credits="<?= $subject['credits'] ?>"
                                                        data-duration="<?= $subject['duration_hours'] ?>"
                                                        data-semester="<?= $subject['semester'] ?>"
                                                        data-year="<?= $subject['year_level'] ?>"
                                                        data-assigned-count="<?= $subject['assigned_faculty_count'] ?>"
                                                        <?= ($formData['subject_id'] ?? '') == $subject['subject_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?>
                                                    (<?= $subject['assigned_faculty_count'] ?> faculty assigned)
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    Choose the subject that needs faculty assignment
                                </div>
                            </div>

                            <!-- Subject Info Card -->
                            <div class="subject-info-card" id="subjectInfoCard">
                                <h5 id="subjectTitle">Subject Information</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Code:</strong> <span id="subjectCode">-</span></p>
                                        <p><strong>Department:</strong> <span id="subjectDepartment">-</span></p>
                                        <p><strong>Credits:</strong> <span id="subjectCredits">-</span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Duration:</strong> <span id="subjectDuration">-</span> hours</p>
                                        <p><strong>Semester:</strong> <span id="subjectSemester">-</span></p>
                                        <p><strong>Year Level:</strong> <span id="subjectYear">-</span></p>
                                    </div>
                                </div>
                                <div class="alert alert-info mt-2" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); margin-bottom: 0;">
                                    <i class="fas fa-users"></i> 
                                    <span id="assignedFacultyCount">0</span> faculty members currently assigned to this subject
                                </div>
                            </div>
                        </div>

                        <!-- Faculty Selection -->
                        <div class="form-section">
                            <h3 class="form-section-title">👨‍🏫 Select Faculty Member</h3>
                            
                            <!-- Department Filter -->
                            <div class="mb-3">
                                <label for="department_filter" class="form-label">Filter by Department</label>
                                <select class="form-select" id="department_filter" onchange="filterFaculty()">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= htmlspecialchars($dept['department_name']) ?>">
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Faculty Grid -->
                            <div class="faculty-grid">
                                <?php foreach ($faculty as $member): ?>
                                    <div class="faculty-card" 
                                         data-faculty-id="<?= $member['faculty_id'] ?>"
                                         data-department="<?= htmlspecialchars($member['department']) ?>"
                                         onclick="selectFaculty(<?= $member['faculty_id'] ?>)">
                                        <input type="radio" name="faculty_id" value="<?= $member['faculty_id'] ?>" 
                                               id="faculty_<?= $member['faculty_id'] ?>"
                                               <?= ($formData['faculty_id'] ?? '') == $member['faculty_id'] ? 'checked' : '' ?>>
                                        
                                        <div class="faculty-info">
                                            <h5><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></h5>
                                            <div class="faculty-meta">
                                                <div><i class="fas fa-id-badge"></i> <?= htmlspecialchars($member['employee_id']) ?></div>
                                                <div><i class="fas fa-building"></i> <?= htmlspecialchars($member['department']) ?></div>
                                                <div><i class="fas fa-user-tie"></i> <?= htmlspecialchars($member['designation']) ?></div>
                                                <?php if (!empty($member['specialization'])): ?>
                                                    <div><i class="fas fa-star"></i> <?= htmlspecialchars($member['specialization']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="assignment-count">
                                                    <?= $member['current_assignments'] ?> assignments
                                                </span>
                                                <?php if (!empty($member['phone'])): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($member['phone']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Assignment Details -->
                        <div class="form-section">
                            <h3 class="form-section-title">⚙️ Assignment Details</h3>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="max_students" class="form-label required">Maximum Students</label>
                                    <input type="number" class="form-control" id="max_students" name="max_students" 
                                           value="<?= htmlspecialchars($formData['max_students'] ?? '60') ?>" 
                                           min="1" max="200" required>
                                    <div class="form-text">
                                        <i class="fas fa-users"></i> 
                                        Maximum number of students for this faculty-subject assignment
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Assignment Date</label>
                                    <input type="text" class="form-control" value="<?= date('Y-m-d H:i:s') ?>" readonly>
                                    <div class="form-text">
                                        <i class="fas fa-calendar"></i> 
                                        Assignment will be created with current timestamp
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Assignment Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Optional notes about this assignment..."><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
                                <div class="form-text">
                                    <i class="fas fa-sticky-note"></i> 
                                    Any special instructions or notes for this assignment
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
                                    🔄 Clear Form
                                </button>
                                <button type="submit" class="btn-action btn-success" id="submitBtn">
                                    ✅ Assign Faculty
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Assignments Sidebar -->
            <div class="col-lg-4">
                <div class="glass-card slide-up">
                    <div class="p-3">
                        <!-- Assignment Tips (now first) -->
                        <div class="tips-card">
                            <h6><i class="fas fa-lightbulb text-warning"></i> Assignment Tips</h6>
                            <ul class="tips-list small text-muted">
                                <li><i class="fas fa-check-circle text-success me-1"></i> Match by department and specialization for best fit.</li>
                                <li><i class="fas fa-balance-scale text-info me-1"></i> Balance workload: check each faculty’s current assignments.</li>
                                <li><i class="fas fa-user-graduate text-primary me-1"></i> Set "Maximum Students" per capacity/policy.</li>
                                <li><i class="fas fa-calendar-check text-success me-1"></i> Confirm availability to avoid schedule conflicts.</li>
                                <li><i class="fas fa-sticky-note text-secondary me-1"></i> Use Notes for constraints (sections, labs, co‑teach, rooms).</li>
                                <li><i class="fas fa-user-shield text-warning me-1"></i> Prefer verified, active users; retire stale assignments.</li>
                                <li><i class="fas fa-clipboard-check text-success me-1"></i> If multiple faculty, document rationale clearly.</li>
                            </ul>
                        </div>

                        <!-- Quick Stats -->
                        <div class="mt-4">
                            <h6>Quick Statistics</h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="text-center p-2 bg-primary bg-opacity-10 rounded">
                                        <div class="fw-bold"><?= count($subjects) ?></div>
                                        <small>Active Subjects</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 bg-success bg-opacity-10 rounded">
                                        <div class="fw-bold"><?= count($faculty) ?></div>
                                        <small>Available Faculty</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-title-sm mt-3 mb-2">
                            <i class="fas fa-history"></i>
                            <span>Recent Assignments</span>
                        </div>
                        
                        <?php if (!empty($existingAssignments)): ?>
                            <div class="assignment-list">
                                <?php foreach ($existingAssignments as $assignment): ?>
                                    <div class="assignment-item">
                                        <div class="assignment-left">
                                            <span class="badge-subject"><?= htmlspecialchars($assignment['subject_code']) ?></span>
                                            <div class="text-truncate">
                                                <div class="subject-name text-truncate"><?= htmlspecialchars($assignment['subject_name']) ?></div>
                                                <div class="faculty-line">
                                                    <i class="fas fa-user-tie"></i>
                                                    <?= htmlspecialchars($assignment['faculty_name']) ?>
                                                    <span class="text-muted">(<?= htmlspecialchars($assignment['employee_id']) ?>)</span>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="badge-date"><i class="fas fa-calendar-day me-1"></i><?= date('M j', strtotime($assignment['assigned_date'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>No recent assignments found</p>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
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
            
            // Update initial faculty selection if form data exists
            updateFacultySelection();
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
            const form = document.getElementById('assignFacultyForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
                submitBtn.disabled = true;
            });
        }

        /**
         * Validate form inputs
         */
        function validateForm() {
            const subjectId = document.getElementById('subject_id').value;
            const facultyId = document.querySelector('input[name="faculty_id"]:checked');
            const maxStudents = document.getElementById('max_students').value;
            
            // Check required fields
            if (!subjectId) {
                showError('Please select a subject.');
                return false;
            }
            
            if (!facultyId) {
                showError('Please select a faculty member.');
                return false;
            }
            
            if (!maxStudents || maxStudents < 1 || maxStudents > 200) {
                showError('Please enter a valid maximum student count (1-200).');
                return false;
            }
            
            return true;
        }

        /**
         * Update subject information display
         */
        function updateSubjectInfo() {
            const select = document.getElementById('subject_id');
            const selectedOption = select.options[select.selectedIndex];
            const infoCard = document.getElementById('subjectInfoCard');
            
            if (selectedOption.value) {
                // Update subject info
                document.getElementById('subjectCode').textContent = selectedOption.dataset.code || '-';
                document.getElementById('subjectDepartment').textContent = selectedOption.dataset.department || '-';
                document.getElementById('subjectCredits').textContent = selectedOption.dataset.credits || '-';
                document.getElementById('subjectDuration').textContent = selectedOption.dataset.duration || '-';
                document.getElementById('subjectSemester').textContent = selectedOption.dataset.semester || '-';
                document.getElementById('subjectYear').textContent = selectedOption.dataset.year || '-';
                document.getElementById('assignedFacultyCount').textContent = selectedOption.dataset.assignedCount || '0';
                
                // Show info card
                infoCard.classList.add('show');
                
                // Auto-filter faculty by subject department
                const subjectDept = selectedOption.dataset.department;
                if (subjectDept) {
                    const deptFilter = document.getElementById('department_filter');
                    deptFilter.value = subjectDept;
                    filterFaculty();
                }
            } else {
                infoCard.classList.remove('show');
            }
        }

        /**
         * Filter faculty by department
         */
        function filterFaculty() {
            const filter = document.getElementById('department_filter').value.toLowerCase();
            const facultyCards = document.querySelectorAll('.faculty-card');
            
            facultyCards.forEach(card => {
                const department = card.dataset.department.toLowerCase();
                if (!filter || department.includes(filter)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        /**
         * Select faculty member
         */
        function selectFaculty(facultyId) {
            // Remove selected class from all cards
            document.querySelectorAll('.faculty-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            const selectedCard = document.querySelector(`[data-faculty-id="${facultyId}"]`);
            selectedCard.classList.add('selected');
            
            // Check the radio button
            document.querySelector(`#faculty_${facultyId}`).checked = true;
        }

        /**
         * Update faculty selection visual state
         */
        function updateFacultySelection() {
            const selectedFaculty = document.querySelector('input[name="faculty_id"]:checked');
            if (selectedFaculty) {
                selectFaculty(selectedFaculty.value);
            }
        }

        /**
         * Reset form to empty state
         */
        function resetForm() {
            if (confirm('Are you sure you want to clear all form data? This action cannot be undone.')) {
                document.getElementById('assignFacultyForm').reset();
                
                // Clear faculty selection
                document.querySelectorAll('.faculty-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                // Hide subject info
                document.getElementById('subjectInfoCard').classList.remove('show');
                
                // Reset department filter
                document.getElementById('department_filter').value = '';
                filterFaculty();
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
                    <strong>Error:</strong> ${message}
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
        window.updateSubjectInfo = updateSubjectInfo;
        window.filterFaculty = filterFaculty;
        window.selectFaculty = selectFaculty;
        window.resetForm = resetForm;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>