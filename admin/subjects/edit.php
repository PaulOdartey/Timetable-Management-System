<?php
/**
 * Admin Subject Edit - Subject Edit Interface
 * Timetable Management System
 * 
 * Professional interface for admin to edit existing subjects
 * with comprehensive subject information and validation
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
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$subjectManager = new Subject();

// Get subject ID to edit
$editSubjectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$editSubjectId) {
    header('Location: index.php?error=' . urlencode('Subject ID is required'));
    exit;
}

// Initialize variables
$subject = null;
$error_message = '';
$success_message = '';
$departments = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error
        $formData = $_POST;
        
        // Basic validation
        $required_fields = ['subject_code', 'subject_name', 'credits', 'duration_hours', 'department', 'semester', 'year_level'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Subject code validation
        if (!preg_match('/^[A-Z]{2,5}[0-9]{3,4}$/', $_POST['subject_code'])) {
            throw new Exception('Subject code must follow format: 2-5 letters followed by 3-4 numbers (e.g., CS101, MATH1001)');
        }
        
        // Credits validation
        $credits = (int)$_POST['credits'];
        if ($credits < 1 || $credits > 6) {
            throw new Exception('Credits must be between 1 and 6.');
        }
        
        // Duration validation
        $duration = (int)$_POST['duration_hours'];
        if ($duration < 1 || $duration > 8) {
            throw new Exception('Duration hours must be between 1 and 8.');
        }
        
        // Semester validation
        $semester = (int)$_POST['semester'];
        if ($semester < 1 || $semester > 12) {
            throw new Exception('Semester must be between 1 and 12.');
        }
        
        // Year level validation
        $yearLevel = (int)$_POST['year_level'];
        if ($yearLevel < 1 || $yearLevel > 6) {
            throw new Exception('Year level must be between 1 and 6.');
        }
        
        // Validate subject name length
        if (strlen($_POST['subject_name']) > 100) {
            throw new Exception('Subject name cannot exceed 100 characters.');
        }
        
        // Check for duplicate subject code (excluding current subject)
        $existingSubject = $db->fetchRow("SELECT subject_id FROM subjects WHERE subject_code = ? AND subject_id != ?", [
            strtoupper(trim($_POST['subject_code'])), 
            $editSubjectId
        ]);
        
        if ($existingSubject) {
            throw new Exception('Subject code already exists. Please choose a different code.');
        }
        
        // Prepare subject data
        $subjectData = [
            'subject_code' => strtoupper(trim($_POST['subject_code'])),
            'subject_name' => trim($_POST['subject_name']),
            'credits' => $credits,
            'duration_hours' => $duration,
            'type' => $_POST['type'] ?? 'theory',
            'department' => trim($_POST['department']),
            'semester' => $semester,
            'year_level' => $yearLevel,
            'prerequisites' => !empty($_POST['prerequisites']) ? trim($_POST['prerequisites']) : null,
            'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
            'syllabus' => !empty($_POST['syllabus']) ? trim($_POST['syllabus']) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Update subject
        $result = $subjectManager->updateSubject($editSubjectId, $subjectData, $currentUserId);
        
        if ($result['success']) {
            $success_message = $result['message'];
            $formData = []; // Clear form data to show updated values
            
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
    // Get subject details
    $subject = $db->fetchRow("SELECT * FROM subjects WHERE subject_id = ?", [$editSubjectId]);
    
    if (!$subject) {
        header('Location: index.php?error=' . urlencode('Subject not found'));
        exit;
    }
    
    // Get departments for dropdown
    $departments = $db->fetchAll("
        SELECT DISTINCT department_name, department_code 
        FROM departments 
        WHERE is_active = 1 
        ORDER BY department_name ASC
    ");
    
    // If no departments in departments table, get from existing data
    if (empty($departments)) {
        $departments = $db->fetchAll("
            SELECT DISTINCT department as department_name, department as department_code
            FROM (
                SELECT department FROM students WHERE department IS NOT NULL
                UNION 
                SELECT department FROM faculty WHERE department IS NOT NULL
                UNION
                SELECT department FROM admin_profiles WHERE department IS NOT NULL
                UNION
                SELECT department FROM subjects WHERE department IS NOT NULL
            ) as all_departments 
            ORDER BY department ASC
        ");
    }
    
} catch (Exception $e) {
    error_log("Edit Subject Error: " . $e->getMessage());
    $error_message = "An error occurred while loading subject data.";
}

// Set page title
$pageTitle = "Edit Subject";
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

        /* Subject Info Header */
        .subject-info-header {
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

        [data-theme="dark"] .subject-info-header {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .subject-info-header {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .subject-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .subject-icon.theory { background: var(--info-color); }
        .subject-icon.practical { background: var(--warning-color); }
        .subject-icon.lab { background: var(--success-color); }

        .subject-details h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .subject-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .subject-meta span {
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

        /* Type Selection Cards */
        .type-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .type-card {
            position: relative;
            padding: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
        }

        [data-theme="dark"] .type-card {
            border-color: var(--border-color);
            background: var(--bg-tertiary);
        }

        [data-theme="light"] .type-card {
            border-color: var(--border-color);
            background: var(--bg-secondary);
        }

        .type-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .type-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .type-card input[type="radio"] {
            display: none;
        }

        .type-card .type-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .type-card .type-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
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

            .subject-info-header {
                flex-direction: column;
                text-align: center;
            }

            .subject-meta {
                justify-content: center;
            }

            .form-container {
                padding: 1rem;
            }

            .type-selection {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .type-selection {
                grid-template-columns: 1fr;
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
                    <h1 class="page-title">✏️ Edit Subject</h1>
                </div>
                <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                    <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success glass-card fade-in">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
                <br><small>Redirecting to subject list...</small>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card fade-in">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Subject Info Header -->
        <?php if ($subject): ?>
        <div class="subject-info-header fade-in">
            <div class="subject-icon <?= strtolower($subject['type']) ?>">
                <?php
                $icons = [
                    'theory' => '📖',
                    'practical' => '🔧',
                    'lab' => '⚗️'
                ];
                ?>
                <?= $icons[$subject['type']] ?? '📚' ?>
            </div>
            <div class="subject-details flex-grow-1">
                <h2><?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?></h2>
                <div class="subject-meta">
                    <span><i class="fas fa-bookmark"></i> <?= ucfirst($subject['type']) ?></span>
                    <span><i class="fas fa-coins"></i> <?= $subject['credits'] ?> Credit<?= $subject['credits'] > 1 ? 's' : '' ?></span>
                    <span><i class="fas fa-clock"></i> <?= $subject['duration_hours'] ?> Hour<?= $subject['duration_hours'] > 1 ? 's' : '' ?></span>
                    <span><i class="fas fa-building"></i> <?= htmlspecialchars($subject['department']) ?></span>
                    <span><i class="fas fa-layer-group"></i> Year <?= $subject['year_level'] ?>, Semester <?= $subject['semester'] ?></span>
                    <span><i class="fas fa-calendar"></i> Created <?= date('M j, Y', strtotime($subject['created_at'])) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" id="editSubjectForm" novalidate>
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">📋 Basic Information</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="subject_code" class="form-label required">Subject Code</label>
                            <input type="text" class="form-control" id="subject_code" name="subject_code" 
                                   value="<?= htmlspecialchars($formData['subject_code'] ?? $subject['subject_code'] ?? '') ?>" 
                                   placeholder="e.g., CS101, MATH1001" maxlength="10" required 
                                   style="text-transform: uppercase;">
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                Format: 2-5 letters followed by 3-4 numbers (automatically uppercase)
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="subject_name" class="form-label required">Subject Name</label>
                            <input type="text" class="form-control" id="subject_name" name="subject_name" 
                                   value="<?= htmlspecialchars($formData['subject_name'] ?? $subject['subject_name'] ?? '') ?>" 
                                   placeholder="Introduction to Computer Science" maxlength="100" required>
                            <div class="character-counter" id="nameCounter">0 / 100</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="credits" class="form-label required">Credits</label>
                            <select class="form-select" id="credits" name="credits" required>
                                <option value="">Select Credits</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($formData['credits'] ?? $subject['credits']) == $i ? 'selected' : '' ?>>
                                        <?= $i ?> Credit<?= $i > 1 ? 's' : '' ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="duration_hours" class="form-label required">Duration (Hours)</label>
                            <select class="form-select" id="duration_hours" name="duration_hours" required>
                                <option value="">Select Duration</option>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($formData['duration_hours'] ?? $subject['duration_hours']) == $i ? 'selected' : '' ?>>
                                        <?= $i ?> Hour<?= $i > 1 ? 's' : '' ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="department" class="form-label required">Department</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['department_name']) ?>" 
                                            <?= ($formData['department'] ?? $subject['department']) === $dept['department_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Subject Type -->
                <div class="form-section">
                    <h3 class="form-section-title">🎯 Subject Type</h3>
                    
                    <div class="type-selection">
                        <?php
                        $types = [
                            'theory' => ['icon' => '📖', 'name' => 'Theory'],
                            'practical' => ['icon' => '🔧', 'name' => 'Practical'],
                            'lab' => ['icon' => '⚗️', 'name' => 'Laboratory']
                        ];
                        
                        $selectedType = $formData['type'] ?? $subject['type'] ?? 'theory';
                        ?>
                        
                        <?php foreach ($types as $value => $type): ?>
                        <div class="type-card <?= $selectedType === $value ? 'selected' : '' ?>" 
                             onclick="selectType('<?= $value ?>')">
                            <input type="radio" name="type" value="<?= $value ?>" 
                                   <?= $selectedType === $value ? 'checked' : '' ?> id="type_<?= $value ?>">
                            <div class="type-icon"><?= $type['icon'] ?></div>
                            <div class="type-name"><?= $type['name'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">🎓 Academic Information</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="semester" class="form-label required">Semester</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="">Select Semester</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($formData['semester'] ?? $subject['semester']) == $i ? 'selected' : '' ?>>
                                        Semester <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="year_level" class="form-label required">Year Level</label>
                            <select class="form-select" id="year_level" name="year_level" required>
                                <option value="">Select Year</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($formData['year_level'] ?? $subject['year_level']) == $i ? 'selected' : '' ?>>
                                        Year <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="prerequisites" class="form-label">Prerequisites</label>
                        <textarea class="form-control" id="prerequisites" name="prerequisites" rows="3" 
                                  placeholder="List any prerequisite subjects or requirements (e.g., MATH101, Basic Mathematics)"><?= htmlspecialchars($formData['prerequisites'] ?? $subject['prerequisites'] ?? '') ?></textarea>
                        <div class="form-text">
                            <i class="fas fa-lightbulb"></i> 
                            Specify subjects that students must complete before taking this subject
                        </div>
                    </div>
                </div>

                <!-- Description & Content -->
                <div class="form-section">
                    <h3 class="form-section-title">📝 Description & Content</h3>
                    <div class="mb-3">
                        <label for="description" class="form-label">Subject Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Provide a clear description of what this subject covers..."><?= htmlspecialchars($formData['description'] ?? $subject['description'] ?? '') ?></textarea>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> 
                            Brief overview of the subject's objectives and learning outcomes
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="syllabus" class="form-label">Syllabus/Course Content</label>
                        <textarea class="form-control" id="syllabus" name="syllabus" rows="6" 
                                  placeholder="Detailed syllabus and course content outline..."><?= htmlspecialchars($formData['syllabus'] ?? $subject['syllabus'] ?? '') ?></textarea>
                        <div class="form-text">
                            <i class="fas fa-list"></i> 
                            Detailed breakdown of topics, modules, and learning materials
                        </div>
                    </div>
                </div>

                <!-- Settings -->
                <div class="form-section">
                    <h3 class="form-section-title">⚙️ Settings</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3 d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       <?= ($formData['is_active'] ?? $subject['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    <strong>Active Subject</strong>
                                </label>
                                <div class="form-text mt-1">
                                    <i class="fas fa-toggle-on"></i> 
                                    Subject will be available for assignment and enrollment
                                </div>
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
                        <button type="button" class="btn-action btn-warning" onclick="previewSubject()">
                            👁️ Preview
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn">
                            ✅ Update Subject
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
                        <i class="fas fa-eye"></i> Subject Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="subject-preview" id="subjectPreview">
                        <!-- Preview content will be inserted here -->
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn-action btn-outline" data-bs-dismiss="modal">Close</button>
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
            
            // Handle sidebar toggle events
            handleSidebarToggle();
            
            // Initialize form validation
            initializeFormValidation();
            
            // Initialize character counter
            initializeCharacterCounter();
            
            // Set initial type selection
            updateTypeSelection();
            
            // Auto-format subject code
            initializeSubjectCodeFormatting();
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
            const form = document.getElementById('editSubjectForm');
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
        }

        /**
         * Validate form inputs
         */
        function validateForm() {
            const subjectCode = document.getElementById('subject_code').value.trim();
            const subjectName = document.getElementById('subject_name').value.trim();
            const credits = document.getElementById('credits').value;
            const duration = document.getElementById('duration_hours').value;
            const department = document.getElementById('department').value;
            const semester = document.getElementById('semester').value;
            const yearLevel = document.getElementById('year_level').value;
            
            // Check required fields
            if (!subjectCode) {
                showError('Please enter a subject code.');
                return false;
            }
            
            if (!subjectName) {
                showError('Please enter a subject name.');
                return false;
            }
            
            if (!credits) {
                showError('Please select the number of credits.');
                return false;
            }
            
            if (!duration) {
                showError('Please select the duration hours.');
                return false;
            }
            
            if (!department) {
                showError('Please select a department.');
                return false;
            }
            
            if (!semester) {
                showError('Please select a semester.');
                return false;
            }
            
            if (!yearLevel) {
                showError('Please select a year level.');
                return false;
            }
            
            // Validate subject code format
            const codePattern = /^[A-Z]{2,5}[0-9]{3,4}$/;
            if (!codePattern.test(subjectCode.toUpperCase())) {
                showError('Subject code must follow format: 2-5 letters followed by 3-4 numbers (e.g., CS101, MATH1001)');
                return false;
            }
            
            // Validate subject name length
            if (subjectName.length > 100) {
                showError('Subject name cannot exceed 100 characters.');
                return false;
            }
            
            return true;
        }

        /**
         * Initialize character counter for subject name
         */
        function initializeCharacterCounter() {
            const nameInput = document.getElementById('subject_name');
            const nameCounter = document.getElementById('nameCounter');
            
            function updateCounter() {
                const currentLength = nameInput.value.length;
                nameCounter.textContent = `${currentLength} / 100`;
                
                // Update counter color based on usage
                nameCounter.classList.remove('near-limit', 'at-limit');
                if (currentLength >= 100) {
                    nameCounter.classList.add('at-limit');
                } else if (currentLength >= 90) {
                    nameCounter.classList.add('near-limit');
                }
            }
            
            // Initial count
            updateCounter();
            
            // Event listener
            nameInput.addEventListener('input', updateCounter);
        }

        /**
         * Initialize subject code formatting
         */
        function initializeSubjectCodeFormatting() {
            const codeInput = document.getElementById('subject_code');
            
            codeInput.addEventListener('input', function() {
                // Convert to uppercase and remove invalid characters
                let value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                
                // Limit length to 10 characters
                if (value.length > 10) {
                    value = value.substring(0, 10);
                }
                
                this.value = value;
                
                // Visual feedback for format validation
                const pattern = /^[A-Z]{2,5}[0-9]{3,4}$/;
                if (value.length > 0) {
                    if (pattern.test(value)) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                }
            });
        }

        /**
         * Select subject type
         */
        function selectType(type) {
            // Remove selected class from all cards
            document.querySelectorAll('.type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            document.querySelector(`#type_${type}`).closest('.type-card').classList.add('selected');
            
            // Check the radio button
            document.querySelector(`#type_${type}`).checked = true;
        }

        /**
         * Update type selection visual state
         */
        function updateTypeSelection() {
            const selectedType = document.querySelector('input[name="type"]:checked');
            if (selectedType) {
                selectType(selectedType.value);
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
         * Preview subject
         */
        function previewSubject() {
            const subjectCode = document.getElementById('subject_code').value.trim();
            const subjectName = document.getElementById('subject_name').value.trim();
            const credits = document.getElementById('credits').value;
            const duration = document.getElementById('duration_hours').value;
            const type = document.querySelector('input[name="type"]:checked').value;
            const department = document.getElementById('department').value;
            const semester = document.getElementById('semester').value;
            const yearLevel = document.getElementById('year_level').value;
            const prerequisites = document.getElementById('prerequisites').value.trim();
            const description = document.getElementById('description').value.trim();
            const syllabus = document.getElementById('syllabus').value.trim();
            const isActive = document.getElementById('is_active').checked;
            
            if (!subjectCode || !subjectName || !credits || !duration || !department || !semester || !yearLevel) {
                showError('Please fill in all required fields to preview.');
                return;
            }
            
            // Create preview HTML
            const typeIcons = {
                'theory': '📖',
                'practical': '🔧',
                'lab': '⚗️'
            };
            
            const previewHtml = `
                <div class="subject-preview-item" style="
                    border: 2px solid var(--border-color);
                    border-radius: 12px;
                    padding: 1.5rem;
                    background: var(--bg-tertiary);
                ">
                    <div class="d-flex align-items-start gap-3">
                        <div class="subject-icon ${type}" style="
                            width: 50px;
                            height: 50px;
                            border-radius: 10px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 1.5rem;
                            color: white;
                            flex-shrink: 0;
                        ">
                            ${typeIcons[type]}
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0" style="color: var(--text-primary); font-weight: 600;">
                                    ${escapeHtml(subjectCode)} - ${escapeHtml(subjectName)}
                                </h5>
                                ${!isActive ? '<span class="badge" style="background: var(--error-color); font-size: 0.7rem;">INACTIVE</span>' : ''}
                            </div>
                            <div class="d-flex gap-3 mb-2" style="font-size: 0.875rem; color: var(--text-secondary);">
                                <span><i class="fas fa-bookmark"></i> ${type.charAt(0).toUpperCase() + type.slice(1)}</span>
                                <span><i class="fas fa-coins"></i> ${credits} Credit${credits > 1 ? 's' : ''}</span>
                                <span><i class="fas fa-clock"></i> ${duration} Hour${duration > 1 ? 's' : ''}</span>
                                <span><i class="fas fa-building"></i> ${escapeHtml(department)}</span>
                            </div>
                            <div class="mb-2" style="font-size: 0.875rem; color: var(--text-secondary);">
                                <span><i class="fas fa-layer-group"></i> Year ${yearLevel}, Semester ${semester}</span>
                            </div>
                            ${prerequisites ? `<div class="mb-2"><strong>Prerequisites:</strong> ${escapeHtml(prerequisites)}</div>` : ''}
                            ${description ? `<div class="mb-2"><strong>Description:</strong> ${escapeHtml(description)}</div>` : ''}
                            ${syllabus ? `<div class="mb-2"><strong>Syllabus:</strong> ${escapeHtml(syllabus).replace(/\n/g, '<br>')}</div>` : ''}
                            <small style="color: var(--text-secondary);">
                                <i class="fas fa-eye"></i> Preview - Changes not yet saved
                            </small>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('subjectPreview').innerHTML = previewHtml;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        /**
         * Escape HTML entities
         */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
        window.selectType = selectType;
        window.resetForm = resetForm;
        window.previewSubject = previewSubject;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>