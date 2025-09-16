<?php
/**
 * Admin Timetable Edit - Timetable Entry Modification Interface
 * Timetable Management System
 * 
 * Professional interface for admin to edit existing timetable entries
 * with comprehensive validation and conflict detection
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
$timetableData = null;
$resources = [];
$timetableId = null;

// Get timetable ID from URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $timetableId = (int)$_GET['id'];
    
    // Get existing timetable data
    $timetableData = $timetableManager->getTimetableById($timetableId);
    
    if (!$timetableData) {
        $error_message = 'Timetable entry not found or has been deleted.';
    }
} else {
    $error_message = 'Invalid timetable ID provided.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $timetableData) {
    try {
        // Basic validation
        $required_fields = ['subject_id', 'faculty_id', 'classroom_id', 'slot_id', 'semester', 'academic_year'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Academic year validation
        if (!preg_match('/^\d{4}-\d{4}$/', $_POST['academic_year'])) {
            throw new Exception('Academic year must be in YYYY-YYYY format (e.g., 2025-2026).');
        }
        
        // Semester validation
        if (!is_numeric($_POST['semester']) || $_POST['semester'] < 1 || $_POST['semester'] > 12) {
            throw new Exception('Semester must be between 1 and 12.');
        }
        
        // Prepare timetable data
        $updateData = [
            'subject_id' => (int)$_POST['subject_id'],
            'faculty_id' => (int)$_POST['faculty_id'],
            'classroom_id' => (int)$_POST['classroom_id'],
            'slot_id' => (int)$_POST['slot_id'],
            'section' => trim($_POST['section']) ?: 'A',
            'semester' => (int)$_POST['semester'],
            'academic_year' => trim($_POST['academic_year']),
            'max_students' => !empty($_POST['max_students']) ? (int)$_POST['max_students'] : null,
            'notes' => trim($_POST['notes']) ?: null
        ];
        
        // Update timetable entry
        $result = $timetableManager->updateTimetableEntry($timetableId, $updateData);
        
        if ($result['success']) {
            $success_message = $result['message'];
            $warning_messages = $result['warnings'] ?? [];
            
            // Refresh timetable data
            $timetableData = $timetableManager->getTimetableById($timetableId);
            
            // Redirect after short delay to show success message
            header("refresh:3;url=index.php");
        } else {
            $error_message = $result['message'];
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get available resources if we have valid timetable data
if ($timetableData) {
    try {
        $resourceResult = $timetableManager->getAvailableResources();
        if ($resourceResult['success']) {
            $resources = $resourceResult;
        }
    } catch (Exception $e) {
        error_log("Get Resources Error: " . $e->getMessage());
    }
}

// Set page title
$pageTitle = "Edit Timetable Entry";
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

        /* Current Schedule Info */
        .current-schedule-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .current-schedule-info h4 {
            color: var(--info-color);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .schedule-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            font-size: 0.875rem;
        }

        .schedule-detail {
            display: flex;
            flex-direction: column;
        }

        .schedule-detail .label {
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .schedule-detail .value {
            color: var(--text-primary);
            font-weight: 600;
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .back-icon {
            font-size: 1rem;
            font-weight: bold;
        }

        /* Loading state */
        .loading-state {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Validation indicators */
        .is-valid {
            border-color: var(--success-color) !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
        }

        .is-invalid {
            border-color: var(--error-color) !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
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

            .schedule-details {
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
                    <h1 class="page-title">✏️ Edit Timetable Entry</h1>
                </div>
                <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                    <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back to List</span>
                </a>
            </div>
        </div>

        <!-- Error handling for invalid timetable ID -->
        <?php if (!$timetableData): ?>
            <div class="alert alert-danger glass-card fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
                <br><br>
                <a href="index.php" class="btn-action btn-primary">
                    <i class="fas fa-arrow-left"></i> Return to Timetable List
                </a>
            </div>
        <?php else: ?>

        <!-- Current Schedule Info -->
        <div class="current-schedule-info glass-card fade-in">
            <h4>
                <i class="fas fa-info-circle"></i>
                Current Schedule Information
            </h4>
            <div class="schedule-details">
                <div class="schedule-detail">
                    <span class="label">Subject</span>
                    <span class="value"><?= htmlspecialchars($timetableData['subject_code']) ?> - <?= htmlspecialchars($timetableData['subject_name']) ?></span>
                </div>
                <div class="schedule-detail">
                    <span class="label">Faculty</span>
                    <span class="value"><?= htmlspecialchars($timetableData['faculty_name']) ?> (<?= htmlspecialchars($timetableData['employee_id']) ?>)</span>
                </div>
                <div class="schedule-detail">
                    <span class="label">Schedule</span>
                    <span class="value"><?= htmlspecialchars($timetableData['day_of_week']) ?> <?= date('g:i A', strtotime($timetableData['start_time'])) ?> - <?= date('g:i A', strtotime($timetableData['end_time'])) ?></span>
                </div>
                <div class="schedule-detail">
                    <span class="label">Classroom</span>
                    <span class="value"><?= htmlspecialchars($timetableData['room_number']) ?> (<?= htmlspecialchars($timetableData['building']) ?>)</span>
                </div>
                <div class="schedule-detail">
                    <span class="label">Section</span>
                    <span class="value"><?= htmlspecialchars($timetableData['section']) ?></span>
                </div>
                <div class="schedule-detail">
                    <span class="label">Enrolled Students</span>
                    <span class="value"><?= htmlspecialchars($timetableData['enrolled_students']) ?> / <?= htmlspecialchars($timetableData['capacity']) ?></span>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success glass-card fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong> <?= htmlspecialchars($success_message) ?>
                <br><small>Redirecting to timetable list...</small>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($warning_messages)): ?>
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

        <!-- Edit Timetable Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" id="editTimetableForm" novalidate autocomplete="off">
                <!-- Basic Schedule Information -->
                <div class="form-section">
                    <h3 class="form-section-title">📚 Schedule Information</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="subject_id" class="form-label">Subject *</label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php if (isset($resources['subjects'])): ?>
                                    <?php foreach ($resources['subjects'] as $subject): ?>
                                        <option value="<?= $subject['subject_id'] ?>" 
                                            <?= $timetableData['subject_id'] == $subject['subject_id'] ? 'selected' : '' ?>
                                            data-department="<?= htmlspecialchars($subject['department']) ?>"
                                            data-credits="<?= $subject['credits'] ?>"
                                            data-semester="<?= $subject['semester'] ?>"
                                            data-year-level="<?= $subject['year_level'] ?>">
                                            <?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?>
                                            <small>(<?= htmlspecialchars($subject['department']) ?>, Sem <?= $subject['semester'] ?>)</small>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i> 
                                Select the subject to be taught
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="faculty_id" class="form-label">Faculty Member *</label>
                            <select class="form-select" id="faculty_id" name="faculty_id" required>
                                <option value="">Select Faculty Member</option>
                                <?php if (isset($resources['faculty'])): ?>
                                    <?php foreach ($resources['faculty'] as $faculty): ?>
                                        <option value="<?= $faculty['faculty_id'] ?>" 
                                            <?= $timetableData['faculty_id'] == $faculty['faculty_id'] ? 'selected' : '' ?>
                                            data-department="<?= htmlspecialchars($faculty['department']) ?>"
                                            data-designation="<?= htmlspecialchars($faculty['designation']) ?>">
                                            <?= htmlspecialchars($faculty['first_name']) ?> <?= htmlspecialchars($faculty['last_name']) ?>
                                            (<?= htmlspecialchars($faculty['employee_id']) ?> - <?= htmlspecialchars($faculty['department']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text" id="facultyInfo">
                                <i class="fas fa-user"></i> 
                                Select the teaching faculty member
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classroom_id" class="form-label">Classroom *</label>
                            <select class="form-select" id="classroom_id" name="classroom_id" required>
                                <option value="">Select Classroom</option>
                                <?php if (isset($resources['classrooms'])): ?>
                                    <?php foreach ($resources['classrooms'] as $classroom): ?>
                                        <option value="<?= $classroom['classroom_id'] ?>" 
                                            <?= $timetableData['classroom_id'] == $classroom['classroom_id'] ? 'selected' : '' ?>
                                            data-capacity="<?= $classroom['capacity'] ?>"
                                            data-type="<?= htmlspecialchars($classroom['type']) ?>"
                                            data-building="<?= htmlspecialchars($classroom['building']) ?>">
                                            <?= htmlspecialchars($classroom['room_number']) ?> (<?= htmlspecialchars($classroom['building']) ?>)
                                            - Capacity: <?= $classroom['capacity'] ?> - <?= ucfirst($classroom['type']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text" id="classroomInfo">
                                <i class="fas fa-door-open"></i> 
                                Select classroom for the class
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="slot_id" class="form-label">Time Slot *</label>
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
                                    ?>
                                        <option value="<?= $slot['slot_id'] ?>" 
                                            <?= $timetableData['slot_id'] == $slot['slot_id'] ? 'selected' : '' ?>
                                            data-day="<?= htmlspecialchars($slot['day_of_week']) ?>"
                                            data-start="<?= $slot['start_time'] ?>"
                                            data-end="<?= $slot['end_time'] ?>">
                                            <?= htmlspecialchars($slot['slot_name']) ?> 
                                            (<?= date('g:i A', strtotime($slot['start_time'])) ?> - <?= date('g:i A', strtotime($slot['end_time'])) ?>)
                                        </option>
                                    <?php 
                                    endforeach; 
                                    if ($currentDay !== '') echo '</optgroup>';
                                    ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-clock"></i> 
                                Select day and time for the class
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">🎓 Academic Details</h3>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="academic_year" class="form-label">Academic Year *</label>
                            <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                   value="<?= htmlspecialchars($timetableData['academic_year']) ?>" 
                                   placeholder="2025-2026" required pattern="\d{4}-\d{4}">
                            <div class="form-text">
                                <i class="fas fa-calendar-alt"></i> 
                                Format: YYYY-YYYY (e.g., 2025-2026)
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="semester" class="form-label">Semester *</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="">Select Semester</option>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>" <?= $timetableData['semester'] == $i ? 'selected' : '' ?>>
                                        Semester <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-list-ol"></i> 
                                Academic semester (1-8)
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="section" class="form-label">Section</label>
                            <input type="text" class="form-control" id="section" name="section" 
                                   value="<?= htmlspecialchars($timetableData['section'] ?? '') ?>" 
                                   placeholder="A" maxlength="10">
                            <div class="form-text">
                                <i class="fas fa-users"></i> 
                                Class section (A, B, C, etc.)
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="max_students" class="form-label">Maximum Students</label>
                            <input type="number" class="form-control" id="max_students" name="max_students" 
                                   value="<?= $timetableData['max_students'] ?>" 
                                   placeholder="Auto-calculated from classroom" min="1" max="200">
                            <div class="form-text">
                                <i class="fas fa-calculator"></i> 
                                Leave empty to use classroom capacity
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <input type="text" class="form-control" id="notes" name="notes" 
                                   value="<?= htmlspecialchars($timetableData['notes'] ?? '') ?>" 
                                   placeholder="Additional information">
                            <div class="form-text">
                                <i class="fas fa-sticky-note"></i> 
                                Optional notes or special instructions
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Validation Summary -->
                <div class="form-section">
                    <div id="validationSummary" class="d-none">
                        <h3 class="form-section-title">⚠️ Validation Summary</h3>
                        <div id="validationResults" class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border spinner-border-sm me-2" role="status">
                                    <span class="visually-hidden">Validating...</span>
                                </div>
                                Checking for conflicts and validating resources...
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
                        <button type="button" class="btn-action btn-primary" onclick="validateTimetable()">
                            🔍 Validate Changes
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn">
                            ✅ Update Timetable
                        </button>
                    </div>
                </div>
            </form>
        </div>

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
            
            // Initialize form validation
            initializeFormValidation();
            
            // Initialize dynamic form interactions
            initializeDynamicInteractions();
            
            // Store original form data for reset functionality
            storeOriginalFormData();
        });

        let originalFormData = {};

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
         * Store original form data for reset functionality
         */
        function storeOriginalFormData() {
            const form = document.getElementById('editTimetableForm');
            const formData = new FormData(form);
            
            originalFormData = {};
            for (let [key, value] of formData.entries()) {
                originalFormData[key] = value;
            }
        }

        /**
         * Initialize form validation
         */
        function initializeFormValidation() {
            const form = document.getElementById('editTimetableForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function(e) {
                if (!validateFormFields()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating Timetable...';
                submitBtn.disabled = true;
            });
            
            // Real-time validation
            document.getElementById('academic_year').addEventListener('input', validateAcademicYear);
            document.getElementById('semester').addEventListener('change', validateSemester);
        }

        /**
         * Initialize dynamic form interactions
         */
        function initializeDynamicInteractions() {
            // Update faculty options based on subject selection
            document.getElementById('subject_id').addEventListener('change', function() {
                updateFacultyOptions();
                updateFacultyInfo();
                clearValidationSummary();
            });
            
            // Update classroom info based on selection
            document.getElementById('classroom_id').addEventListener('change', function() {
                updateClassroomInfo();
                clearValidationSummary();
            });
            
            // Update faculty info when faculty changes
            document.getElementById('faculty_id').addEventListener('change', function() {
                updateFacultyInfo();
                clearValidationSummary();
            });
            
            // Clear validation when time slot changes
            document.getElementById('slot_id').addEventListener('change', function() {
                clearValidationSummary();
            });
            
            // Auto-update max students based on classroom capacity
            document.getElementById('classroom_id').addEventListener('change', function() {
                autoUpdateMaxStudents();
            });
        }

        /**
         * Update faculty options based on selected subject
         */
        function updateFacultyOptions() {
            const subjectId = document.getElementById('subject_id').value;
            
            if (!subjectId) {
                // Reset to show all faculty
                document.querySelectorAll('#faculty_id option').forEach(option => {
                    if (option.value) option.style.display = '';
                });
                return;
            }
            
            // AJAX call to get faculty assigned to this subject
            fetch('get_faculty_by_subject.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ subject_id: subjectId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show only assigned faculty
                    const assignedFacultyIds = data.faculty.map(f => f.faculty_id.toString());
                    
                    document.querySelectorAll('#faculty_id option').forEach(option => {
                        if (option.value && !assignedFacultyIds.includes(option.value)) {
                            option.style.display = 'none';
                        } else {
                            option.style.display = '';
                        }
                    });
                    
                    // Show warning if current faculty is not assigned
                    const currentFacultyId = document.getElementById('faculty_id').value;
                    if (currentFacultyId && !assignedFacultyIds.includes(currentFacultyId)) {
                        showWarning('Selected faculty member is not assigned to teach this subject');
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching faculty:', error);
            });
        }

        /**
         * Update faculty information display
         */
        function updateFacultyInfo() {
            const facultySelect = document.getElementById('faculty_id');
            const selectedOption = facultySelect.options[facultySelect.selectedIndex];
            const infoDiv = document.getElementById('facultyInfo');
            
            if (selectedOption && selectedOption.value) {
                const department = selectedOption.dataset.department;
                const designation = selectedOption.dataset.designation;
                
                infoDiv.innerHTML = `
                    <i class="fas fa-user"></i> 
                    ${designation} - ${department} Department
                `;
            } else {
                infoDiv.innerHTML = `
                    <i class="fas fa-user"></i> 
                    Select the teaching faculty member
                `;
            }
        }

        /**
         * Update classroom information display
         */
        function updateClassroomInfo() {
            const classroomSelect = document.getElementById('classroom_id');
            const selectedOption = classroomSelect.options[classroomSelect.selectedIndex];
            const infoDiv = document.getElementById('classroomInfo');
            
            if (selectedOption && selectedOption.value) {
                const capacity = selectedOption.dataset.capacity;
                const type = selectedOption.dataset.type;
                const building = selectedOption.dataset.building;
                
                infoDiv.innerHTML = `
                    <i class="fas fa-door-open"></i> 
                    ${type} classroom in ${building} - Capacity: ${capacity} students
                `;
            } else {
                infoDiv.innerHTML = `
                    <i class="fas fa-door-open"></i> 
                    Select classroom for the class
                `;
            }
        }

        /**
         * Auto-update max students based on classroom capacity
         */
        function autoUpdateMaxStudents() {
            const classroomSelect = document.getElementById('classroom_id');
            const selectedOption = classroomSelect.options[classroomSelect.selectedIndex];
            const maxStudentsInput = document.getElementById('max_students');
            
            if (selectedOption && selectedOption.value && !maxStudentsInput.value) {
                const capacity = selectedOption.dataset.capacity;
                maxStudentsInput.placeholder = `Auto: ${capacity} (classroom capacity)`;
            }
        }

        /**
         * Validate academic year format
         */
        function validateAcademicYear() {
            const input = document.getElementById('academic_year');
            const value = input.value;
            
            if (value.length === 0) {
                input.classList.remove('is-valid', 'is-invalid');
                return true;
            }
            
            const regex = /^\d{4}-\d{4}$/;
            if (regex.test(value)) {
                const years = value.split('-');
                const startYear = parseInt(years[0]);
                const endYear = parseInt(years[1]);
                
                if (endYear === startYear + 1) {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                    return true;
                }
            }
            
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
            return false;
        }

        /**
         * Validate semester
         */
        function validateSemester() {
            const select = document.getElementById('semester');
            const value = parseInt(select.value);
            
            if (value >= 1 && value <= 8) {
                select.classList.remove('is-invalid');
                select.classList.add('is-valid');
                return true;
            } else if (select.value === '') {
                select.classList.remove('is-valid', 'is-invalid');
                return false;
            } else {
                select.classList.remove('is-valid');
                select.classList.add('is-invalid');
                return false;
            }
        }

        /**
         * Validate timetable for conflicts and issues
         */
        function validateTimetable() {
            const formData = getFormData();
            
            if (!validateFormFields()) {
                showError('Please fill in all required fields correctly');
                return;
            }

            // Show validation in progress UI
            showValidationInProgress();

            // AJAX call to validate timetable (conflict check)
            fetch('ajax/check_conflicts.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ...formData,
                    // Exclude current entry from conflict check
                    exclude_timetable_id: <?= $timetableId ?>
                })
            })
            .then(async (response) => {
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    throw new Error('Server returned invalid response (non-JSON). Status ' + response.status);
                }
                if (!response.ok) {
                    const msg = (data && data.message) ? data.message : 'Validation request failed';
                    throw new Error(msg);
                }
                return data;
            })
            .then((data) => {
                const transformed = {
                    success: data && data.success && !data.has_conflict,
                    message: data && data.message ? data.message : (data && data.has_conflict ? 'Conflict detected' : 'Validation completed'),
                    warnings: (data && Array.isArray(data.warnings)) ? data.warnings : []
                };
                showValidationResults(transformed);
            })
            .catch(error => {
                console.error('Validation error:', error);
                showValidationResults({
                    success: false,
                    message: 'Error validating timetable: ' + error.message
                });
            });
        }

        function showValidationInProgress() {
            const summaryDiv = document.getElementById('validationSummary');
            const resultsDiv = document.getElementById('validationResults');
            
            summaryDiv.classList.remove('d-none');
            resultsDiv.className = 'alert alert-info';
            resultsDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden">Validating...</span>
                    </div>
                    Checking for conflicts and validating resources...
                </div>
            `;
            
            // Scroll to validation summary
            summaryDiv.scrollIntoView({ behavior: 'smooth' });
        }

        /**
         * Show validation results
         */
        function showValidationResults(data) {
            const summaryDiv = document.getElementById('validationSummary');
            const resultsDiv = document.getElementById('validationResults');
            
            summaryDiv.classList.remove('d-none');
            
            if (data.success) {
                resultsDiv.className = 'alert alert-success';
                resultsDiv.innerHTML = `
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Validation Passed!</strong>
                    </div>
                    <div>No conflicts detected. The timetable entry can be updated safely.</div>
                    ${data.warnings && data.warnings.length > 0 ? `
                        <hr>
                        <div class="mt-2">
                            <strong>Warnings:</strong>
                            <ul class="mb-0 mt-1">
                                ${data.warnings.map(warning => `<li>${warning}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                `;
            } else {
                resultsDiv.className = 'alert alert-danger';
                resultsDiv.innerHTML = `
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Validation Failed!</strong>
                    </div>
                    <div>${data.message}</div>
                `;
            }
        }

        /**
         * Clear validation summary
         */
        function clearValidationSummary() {
            const summaryDiv = document.getElementById('validationSummary');
            summaryDiv.classList.add('d-none');
        }

        /**
         * Get form data as object
         */
        function getFormData() {
            const form = document.getElementById('editTimetableForm');
            const formData = new FormData(form);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            return data;
        }

        /**
         * Validate form fields
         */
        function validateFormFields() {
            let isValid = true;
            const errors = [];
            
            // Check required fields
            const requiredFields = [
                { id: 'subject_id', name: 'Subject' },
                { id: 'faculty_id', name: 'Faculty Member' },
                { id: 'classroom_id', name: 'Classroom' },
                { id: 'slot_id', name: 'Time Slot' },
                { id: 'semester', name: 'Semester' },
                { id: 'academic_year', name: 'Academic Year' }
            ];
            
            requiredFields.forEach(field => {
                const element = document.getElementById(field.id);
                if (!element.value.trim()) {
                    isValid = false;
                    errors.push(`${field.name} is required`);
                    element.classList.add('is-invalid');
                } else {
                    element.classList.remove('is-invalid');
                }
            });
            
            // Validate academic year format
            if (!validateAcademicYear()) {
                isValid = false;
                errors.push('Academic year must be in YYYY-YYYY format');
            }
            
            // Validate semester
            if (!validateSemester()) {
                isValid = false;
                errors.push('Please select a valid semester');
            }
            
            if (!isValid) {
                showError('Please fix the following errors:\n• ' + errors.join('\n• '));
            }
            
            return isValid;
        }

        /**
         * Reset form to original state
         */
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes? This will restore the original values.')) {
                const form = document.getElementById('editTimetableForm');
                
                // Reset to original values
                Object.keys(originalFormData).forEach(key => {
                    const element = form.elements[key];
                    if (element) {
                        element.value = originalFormData[key];
                    }
                });
                
                // Reset validation classes
                document.querySelectorAll('.is-valid, .is-invalid').forEach(field => {
                    field.classList.remove('is-valid', 'is-invalid');
                });
                
                // Clear validation summary
                clearValidationSummary();
                
                // Update dynamic content
                updateFacultyInfo();
                updateClassroomInfo();
                autoUpdateMaxStudents();
            }
        }

        /**
         * Show error message
         */
        function showError(message) {
            // Remove existing alerts
            document.querySelectorAll('.alert-danger').forEach(alert => {
                if (!alert.closest('#validationSummary')) {
                    alert.remove();
                }
            });
            
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
         * Show warning message
         */
        function showWarning(message) {
            // Create warning alert
            const alertHtml = `
                <div class="alert alert-warning glass-card fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> ${message}
                </div>
            `;
            
            // Insert before form container
            const formContainer = document.querySelector('.form-container');
            formContainer.insertAdjacentHTML('beforebegin', alertHtml);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                const alertElement = document.querySelector('.alert-warning:last-of-type');
                if (alertElement) {
                    alertElement.remove();
                }
            }, 5000);
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
        window.validateTimetable = validateTimetable;
        window.resetForm = resetForm;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>