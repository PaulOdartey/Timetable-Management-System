<?php
/**
 * Admin Enrollments Edit - Enrollment Edit Interface
 * Timetable Management System
 * 
 * Professional interface for admin to edit existing student enrollments
 * with section, semester, academic year, and status management
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
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$enrollmentManager = new Enrollment();

// Get enrollment ID to edit
$editEnrollmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$editEnrollmentId) {
    header('Location: index.php?error=' . urlencode('Enrollment ID is required'));
    exit;
}

// Initialize variables
$enrollment = null;
$students = [];
$subjects = [];
$sections = [];
$error_message = '';
$success_message = '';
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error
        $formData = $_POST;
        
        // Basic validation
        $required_fields = ['student_id', 'subject_id', 'section', 'semester', 'academic_year', 'status'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Validate semester (1-12)
        $semester = (int)$_POST['semester'];
        if ($semester < 1 || $semester > 12) {
            throw new Exception('Semester must be between 1 and 12.');
        }
        
        // Validate academic year format (YYYY-YYYY)
        $academicYear = trim($_POST['academic_year']);
        if (!preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
            throw new Exception('Academic year must be in format YYYY-YYYY (e.g., 2025-2026).');
        }
        
        // Check for duplicate enrollment (if student/subject/section changed)
        $currentEnrollment = $enrollmentManager->getEnrollmentById($editEnrollmentId);
        if (!$currentEnrollment) {
            throw new Exception('Enrollment not found.');
        }
        
        $newStudentId = (int)$_POST['student_id'];
        $newSubjectId = (int)$_POST['subject_id'];
        $newSection = trim($_POST['section']);
        $newAcademicYear = $academicYear;
        $newSemester = $semester;
        
        // Check for duplicate only if key fields changed
        if ($newStudentId != $currentEnrollment['student_id'] || 
            $newSubjectId != $currentEnrollment['subject_id'] || 
            $newSection != $currentEnrollment['section'] ||
            $newAcademicYear != $currentEnrollment['academic_year'] ||
            $newSemester != $currentEnrollment['semester']) {
            
            $isDuplicate = $enrollmentManager->checkDuplicateEnrollment(
                $newStudentId, $newSubjectId, $newSection, $newSemester, $newAcademicYear, $editEnrollmentId
            );
            
            if ($isDuplicate) {
                throw new Exception('This student is already enrolled in this subject and section for the specified semester and academic year.');
            }
        }
        
        // Prepare update data
        $updateData = [
            'student_id' => $newStudentId,
            'subject_id' => $newSubjectId,
            'section' => $newSection,
            'semester' => $newSemester,
            'academic_year' => $newAcademicYear,
            'status' => $_POST['status']
        ];
        
        // Update enrollment
        $result = $enrollmentManager->updateEnrollment($editEnrollmentId, $updateData, $currentUserId);
        
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
    // Get enrollment details
    $enrollment = $enrollmentManager->getEnrollmentById($editEnrollmentId);
    
    if (!$enrollment) {
        header('Location: index.php?error=' . urlencode('Enrollment not found'));
        exit;
    }
    
    // Get all active students
    $students = $db->fetchAll("
        SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.department, s.year_of_study
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        WHERE u.status = 'active'
        ORDER BY s.first_name, s.last_name
    ");
    
    // Get all active subjects
    $subjects = $db->fetchAll("
        SELECT subject_id, subject_code, subject_name, credits, department, semester
        FROM subjects
        WHERE is_active = 1
        ORDER BY subject_code
    ");
    
    // Get available sections for current subject (if any timetables exist)
    $sections = $db->fetchAll("
        SELECT DISTINCT section
        FROM timetables
        WHERE subject_id = ? AND is_active = 1
        ORDER BY section
    ", [$enrollment['subject_id'] ?? 0]);
    
    // If no sections found, provide default options
    if (empty($sections)) {
        $sections = [
            ['section' => 'A'],
            ['section' => 'B'],
            ['section' => 'C']
        ];
    }
    
} catch (Exception $e) {
    error_log("Edit Enrollment Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the enrollment data.";
}

// Set page title
$pageTitle = "Edit Enrollment";
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

        /* Enrollment Info Header */
        .enrollment-info-header {
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

        [data-theme="dark"] .enrollment-info-header {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .enrollment-info-header {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .enrollment-icon {
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

        .enrollment-details h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .enrollment-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .enrollment-meta span {
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

        /* Status Selection Cards */
        .status-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .status-card {
            position: relative;
            padding: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
        }

        [data-theme="dark"] .status-card {
            border-color: var(--border-color);
            background: var(--bg-tertiary);
        }

        [data-theme="light"] .status-card {
            border-color: var(--border-color);
            background: var(--bg-secondary);
        }

        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .status-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .status-card input[type="radio"] {
            display: none;
        }

        .status-card .status-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .status-card .status-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .status-card.enrolled .status-icon { color: var(--success-color); }
        .status-card.dropped .status-icon { color: var(--warning-color); }
        .status-card.completed .status-icon { color: var(--info-color); }
        .status-card.failed .status-icon { color: var(--error-color); }

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

        /* Student/Subject Info Cards */
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 0.5rem;
        }

        [data-theme="dark"] .info-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .info-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .info-card h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .info-card .info-details {
            color: var(--text-secondary);
            font-size: 0.875rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
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

            .enrollment-info-header {
                flex-direction: column;
                text-align: center;
            }

            .enrollment-meta {
                justify-content: center;
            }

            .form-container {
                padding: 1rem;
            }

            .status-selection {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .status-selection {
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

        [data-theme="dark"] .modal-footer {
            background: var(--bg-secondary) !important;
            border-top: 1px solid var(--border-color) !important;
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

        [data-theme="light"] .modal-footer,
        .modal-footer {
            background: #ffffff !important;
            border-top: 1px solid #e2e8f0 !important;
        }
    </style>
</head>
<body>
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
                    <h1 class="page-title">📝 Edit Enrollment</h1>
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

        <!-- Enrollment Info Header -->
        <?php if ($enrollment): ?>
        <div class="enrollment-info-header">
            <div class="enrollment-icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="enrollment-details flex-grow-1">
                <h2><?= htmlspecialchars($enrollment['student_name'] ?? 'Student') ?> - <?= htmlspecialchars($enrollment['subject_code'] ?? 'Subject') ?></h2>
                <div class="enrollment-meta">
                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($enrollment['student_number'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-book"></i> <?= htmlspecialchars($enrollment['subject_name'] ?? 'N/A') ?></span>
                    <span><i class="fas fa-layer-group"></i> Section <?= htmlspecialchars($enrollment['section'] ?? 'A') ?></span>
                    <span><i class="fas fa-calendar"></i> Semester <?= htmlspecialchars($enrollment['semester'] ?? 1) ?></span>
                    <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($enrollment['academic_year'] ?? '2025-2026') ?></span>
                    <span><i class="fas fa-info-circle"></i> <?= ucfirst($enrollment['status'] ?? 'enrolled') ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" action="" id="editEnrollmentForm">
                <!-- Student Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">👤 Student Information</h3>
                    
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Select Student *</label>
                        <select class="form-select" id="student_id" name="student_id" required onchange="updateStudentInfo()">
                            <option value="">Choose Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['student_id'] ?>" 
                                        data-number="<?= htmlspecialchars($student['student_number']) ?>"
                                        data-department="<?= htmlspecialchars($student['department']) ?>"
                                        data-year="<?= htmlspecialchars($student['year_of_study']) ?>"
                                        <?= ($formData['student_id'] ?? $enrollment['student_id'] ?? '') == $student['student_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> 
                                    (<?= htmlspecialchars($student['student_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div id="studentInfo" class="info-card" style="display: none;">
                            <h6>Student Details</h6>
                            <div class="info-details">
                                <span><strong>Student Number:</strong> <span id="studentNumber">-</span></span>
                                <span><strong>Department:</strong> <span id="studentDepartment">-</span></span>
                                <span><strong>Year of Study:</strong> <span id="studentYear">-</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">📚 Subject Information</h3>
                    
                    <div class="mb-3">
                        <label for="subject_id" class="form-label">Select Subject *</label>
                        <select class="form-select" id="subject_id" name="subject_id" required onchange="updateSubjectInfo(); loadSections();">
                            <option value="">Choose Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['subject_id'] ?>" 
                                        data-code="<?= htmlspecialchars($subject['subject_code']) ?>"
                                        data-credits="<?= htmlspecialchars($subject['credits']) ?>"
                                        data-department="<?= htmlspecialchars($subject['department']) ?>"
                                        data-semester="<?= htmlspecialchars($subject['semester']) ?>"
                                        <?= ($formData['subject_id'] ?? $enrollment['subject_id'] ?? '') == $subject['subject_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div id="subjectInfo" class="info-card" style="display: none;">
                            <h6>Subject Details</h6>
                            <div class="info-details">
                                <span><strong>Subject Code:</strong> <span id="subjectCode">-</span></span>
                                <span><strong>Credits:</strong> <span id="subjectCredits">-</span></span>
                                <span><strong>Department:</strong> <span id="subjectDepartment">-</span></span>
                                <span><strong>Intended Semester:</strong> <span id="subjectSemester">-</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Academic Details -->
                <div class="form-section">
                    <h3 class="form-section-title">🎓 Academic Details</h3>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="section" class="form-label">Section *</label>
                            <select class="form-select" id="section" name="section" required>
                                <option value="">Select Section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= htmlspecialchars($section['section']) ?>" 
                                            <?= ($formData['section'] ?? $enrollment['section'] ?? '') === $section['section'] ? 'selected' : '' ?>>
                                        Section <?= htmlspecialchars($section['section']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                Available sections for the selected subject
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="semester" class="form-label">Semester *</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="">Select Semester</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" 
                                            <?= ($formData['semester'] ?? $enrollment['semester'] ?? '') == $i ? 'selected' : '' ?>>
                                        Semester <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="academic_year" class="form-label">Academic Year *</label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                   value="<?= htmlspecialchars($formData['academic_year'] ?? $enrollment['academic_year'] ?? '') ?>" 
                                   placeholder="e.g., 2025-2026" pattern="\d{4}-\d{4}" required>
                            <div class="form-text">
                                <i class="fas fa-calendar-alt"></i> 
                                Format: YYYY-YYYY (e.g., 2025-2026)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">📊 Enrollment Status</h3>
                    
                    <div class="status-selection">
                        <?php
                        $statuses = [
                            'enrolled' => ['icon' => 'fas fa-check-circle', 'name' => 'Enrolled'],
                            'dropped' => ['icon' => 'fas fa-times-circle', 'name' => 'Dropped'],
                            'completed' => ['icon' => 'fas fa-graduation-cap', 'name' => 'Completed'],
                            'failed' => ['icon' => 'fas fa-exclamation-triangle', 'name' => 'Failed']
                        ];
                        
                        $selectedStatus = $formData['status'] ?? $enrollment['status'] ?? 'enrolled';
                        ?>
                        
                        <?php foreach ($statuses as $value => $status): ?>
                        <div class="status-card <?= $value ?> <?= $selectedStatus === $value ? 'selected' : '' ?>" 
                             onclick="selectStatus('<?= $value ?>')">
                            <input type="radio" name="status" value="<?= $value ?>" 
                                   <?= $selectedStatus === $value ? 'checked' : '' ?> id="status_<?= $value ?>">
                            <div class="status-icon">
                                <i class="<?= $status['icon'] ?>"></i>
                            </div>
                            <div class="status-name"><?= $status['name'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i> 
                        Select the current status of this enrollment
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
                        <button type="button" class="btn-action btn-warning" onclick="previewEnrollment()">
                            👁️ Preview
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn">
                            ✅ Update Enrollment
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="previewModalLabel" style="color: var(--text-primary);">
                        <i class="fas fa-eye"></i> Enrollment Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="enrollment-preview" id="enrollmentPreview">
                        <!-- Preview content will be inserted here -->
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn-action btn-outline" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn-action btn-success" onclick="submitForm()">
                        ✅ Confirm & Update
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Store original data for reset functionality
        const originalData = <?= json_encode($enrollment) ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Handle sidebar toggle events
            handleSidebarToggle();
            
            // Initialize form validation
            initializeFormValidation();
            
            // Update initial info displays
            updateStudentInfo();
            updateSubjectInfo();
            updateStatusSelection();
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
         * Update student information display
         */
        function updateStudentInfo() {
            const studentSelect = document.getElementById('student_id');
            const studentInfo = document.getElementById('studentInfo');
            const selectedOption = studentSelect.options[studentSelect.selectedIndex];
            
            if (selectedOption.value) {
                document.getElementById('studentNumber').textContent = selectedOption.dataset.number || '-';
                document.getElementById('studentDepartment').textContent = selectedOption.dataset.department || '-';
                document.getElementById('studentYear').textContent = 'Year ' + (selectedOption.dataset.year || '-');
                studentInfo.style.display = 'block';
            } else {
                studentInfo.style.display = 'none';
            }
        }

        /**
         * Update subject information display
         */
        function updateSubjectInfo() {
            const subjectSelect = document.getElementById('subject_id');
            const subjectInfo = document.getElementById('subjectInfo');
            const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
            
            if (selectedOption.value) {
                document.getElementById('subjectCode').textContent = selectedOption.dataset.code || '-';
                document.getElementById('subjectCredits').textContent = selectedOption.dataset.credits || '-';
                document.getElementById('subjectDepartment').textContent = selectedOption.dataset.department || '-';
                document.getElementById('subjectSemester').textContent = selectedOption.dataset.semester || '-';
                subjectInfo.style.display = 'block';
            } else {
                subjectInfo.style.display = 'none';
            }
        }

        /**
         * Load sections for selected subject
         */
        function loadSections() {
            const subjectId = document.getElementById('subject_id').value;
            const sectionSelect = document.getElementById('section');
            
            if (!subjectId) {
                sectionSelect.innerHTML = '<option value="">Select Section</option>';
                return;
            }
            
            // For now, provide default sections
            // In a real implementation, you'd make an AJAX call to get available sections
            const defaultSections = ['A', 'B', 'C'];
            const currentSection = sectionSelect.value;
            
            sectionSelect.innerHTML = '<option value="">Select Section</option>';
            defaultSections.forEach(section => {
                const option = document.createElement('option');
                option.value = section;
                option.textContent = `Section ${section}`;
                if (section === currentSection) {
                    option.selected = true;
                }
                sectionSelect.appendChild(option);
            });
        }

        /**
         * Select enrollment status
         */
        function selectStatus(status) {
            // Remove selected class from all cards
            document.querySelectorAll('.status-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            document.querySelector(`#status_${status}`).closest('.status-card').classList.add('selected');
            
            // Check the radio button
            document.querySelector(`#status_${status}`).checked = true;
        }

        /**
         * Update status selection visual state
         */
        function updateStatusSelection() {
            const selectedStatus = document.querySelector('input[name="status"]:checked');
            if (selectedStatus) {
                selectStatus(selectedStatus.value);
            }
        }

        /**
         * Initialize form validation
         */
        function initializeFormValidation() {
            const form = document.getElementById('editEnrollmentForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                submitBtn.disabled = true;
            });
            
            // Academic year pattern validation
            const academicYearInput = document.getElementById('academic_year');
            academicYearInput.addEventListener('input', function() {
                validateAcademicYear(this);
            });
        }

        /**
         * Validate academic year format
         */
        function validateAcademicYear(input) {
            const value = input.value;
            const pattern = /^\d{4}-\d{4}$/;
            
            if (value && !pattern.test(value)) {
                input.setCustomValidity('Academic year must be in format YYYY-YYYY');
            } else if (value && pattern.test(value)) {
                const years = value.split('-');
                const startYear = parseInt(years[0]);
                const endYear = parseInt(years[1]);
                
                if (endYear !== startYear + 1) {
                    input.setCustomValidity('End year must be exactly one year after start year');
                } else {
                    input.setCustomValidity('');
                }
            } else {
                input.setCustomValidity('');
            }
        }

        /**
         * Validate form inputs
         */
        function validateForm() {
            const requiredFields = ['student_id', 'subject_id', 'section', 'semester', 'academic_year'];
            const errors = [];
            
            // Check required fields
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value.trim()) {
                    errors.push(`${field.previousElementSibling.textContent.replace('*', '')} is required`);
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Check status selection
            const statusSelected = document.querySelector('input[name="status"]:checked');
            if (!statusSelected) {
                errors.push('Please select an enrollment status');
            }
            
            // Validate academic year format
            const academicYear = document.getElementById('academic_year').value;
            if (academicYear && !/^\d{4}-\d{4}$/.test(academicYear)) {
                errors.push('Academic year must be in format YYYY-YYYY');
            }
            
            // Validate semester range
            const semester = parseInt(document.getElementById('semester').value);
            if (semester && (semester < 1 || semester > 12)) {
                errors.push('Semester must be between 1 and 12');
            }
            
            if (errors.length > 0) {
                showError('Please fix the following errors:\n• ' + errors.join('\n• '));
                return false;
            }
            
            return true;
        }

        /**
         * Preview enrollment before submitting
         */
        function previewEnrollment() {
            if (!validateForm()) {
                return;
            }
            
            const studentSelect = document.getElementById('student_id');
            const subjectSelect = document.getElementById('subject_id');
            const studentOption = studentSelect.options[studentSelect.selectedIndex];
            const subjectOption = subjectSelect.options[subjectSelect.selectedIndex];
            const section = document.getElementById('section').value;
            const semester = document.getElementById('semester').value;
            const academicYear = document.getElementById('academic_year').value;
            const status = document.querySelector('input[name="status"]:checked').value;
            
            const previewHtml = `
                <div class="enrollment-preview-item" style="
                    border: 2px solid var(--border-color);
                    border-radius: 12px;
                    padding: 1.5rem;
                    background: var(--bg-tertiary);
                ">
                    <h5 style="color: var(--text-primary); margin-bottom: 1rem;">Enrollment Summary</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 style="color: var(--text-primary);">Student Information</h6>
                            <p style="color: var(--text-secondary); margin-bottom: 0.5rem;">
                                <strong>Name:</strong> ${studentOption.textContent.split('(')[0].trim()}
                            </p>
                            <p style="color: var(--text-secondary); margin-bottom: 0.5rem;">
                                <strong>Student Number:</strong> ${studentOption.dataset.number}
                            </p>
                            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                                <strong>Department:</strong> ${studentOption.dataset.department}
                            </p>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 style="color: var(--text-primary);">Subject Information</h6>
                            <p style="color: var(--text-secondary); margin-bottom: 0.5rem;">
                                <strong>Subject:</strong> ${subjectOption.textContent}
                            </p>
                            <p style="color: var(--text-secondary); margin-bottom: 0.5rem;">
                                <strong>Credits:</strong> ${subjectOption.dataset.credits}
                            </p>
                            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                                <strong>Department:</strong> ${subjectOption.dataset.department}
                            </p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <h6 style="color: var(--text-primary);">Enrollment Details</h6>
                            <div class="d-flex flex-wrap gap-3" style="color: var(--text-secondary);">
                                <span><strong>Section:</strong> ${section}</span>
                                <span><strong>Semester:</strong> ${semester}</span>
                                <span><strong>Academic Year:</strong> ${academicYear}</span>
                                <span><strong>Status:</strong> <span class="badge" style="background: var(--${getStatusColor(status)});">${status.toUpperCase()}</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('enrollmentPreview').innerHTML = previewHtml;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        /**
         * Get status color
         */
        function getStatusColor(status) {
            const colors = {
                'enrolled': 'success-color',
                'dropped': 'warning-color',
                'completed': 'info-color',
                'failed': 'error-color'
            };
            return colors[status] || 'primary-color';
        }

        /**
         * Submit form from preview modal
         */
        function submitForm() {
            document.getElementById('editEnrollmentForm').submit();
        }

        /**
         * Reset form to original values
         */
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes? This will restore the original values.')) {
                if (originalData) {
                    document.getElementById('student_id').value = originalData.student_id || '';
                    document.getElementById('subject_id').value = originalData.subject_id || '';
                    document.getElementById('section').value = originalData.section || '';
                    document.getElementById('semester').value = originalData.semester || '';
                    document.getElementById('academic_year').value = originalData.academic_year || '';
                    
                    // Reset status
                    const statusRadio = document.querySelector(`input[name="status"][value="${originalData.status || 'enrolled'}"]`);
                    if (statusRadio) {
                        statusRadio.checked = true;
                        selectStatus(originalData.status || 'enrolled');
                    }
                    
                    // Update info displays
                    updateStudentInfo();
                    updateSubjectInfo();
                    loadSections();
                    
                    // Remove validation classes
                    document.querySelectorAll('.is-invalid').forEach(field => {
                        field.classList.remove('is-invalid');
                    });
                } else {
                    location.reload();
                }
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
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message.replace(/\n/g, '<br>')}
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
        window.updateStudentInfo = updateStudentInfo;
        window.updateSubjectInfo = updateSubjectInfo;
        window.loadSections = loadSections;
        window.selectStatus = selectStatus;
        window.resetForm = resetForm;
        window.previewEnrollment = previewEnrollment;
        window.submitForm = submitForm;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>