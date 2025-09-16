<?php
/**
 * Admin Subject Delete - Subject Deletion Handler
 * Timetable Management System
 * 
 * Handles subject deletion with proper validation and security checks
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

// Initialize variables
$error_message = '';
$success_message = '';
$targetSubject = null;
$confirmationStep = false;
$relatedData = [];

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle actual deletion
    handleDeletion();
} elseif (isset($_GET['id'])) {
    // Show confirmation page
    $targetSubjectId = (int)$_GET['id'];
    $targetSubject = getSubjectDetails($targetSubjectId);
    if ($targetSubject) {
        $confirmationStep = true;
        $relatedData = getRelatedData($targetSubjectId);
    } else {
        $error_message = "Subject not found.";
    }
} else {
    // Redirect to subject management if no ID provided
    header('Location: index.php');
    exit;
}

/**
 * Handle subject deletion
 */
function handleDeletion() {
    global $currentUserId, $subjectManager, $success_message, $error_message;
    
    try {
        $targetSubjectId = (int)$_POST['subject_id'];
        $deleteType = $_POST['delete_type'] ?? 'deactivate';
        
        $targetSubject = getSubjectDetails($targetSubjectId);
        if (!$targetSubject) {
            throw new Exception("Subject not found.");
        }
        
        // Check for dependencies
        $relatedData = getRelatedData($targetSubjectId);
        
        if ($deleteType === 'permanent') {
            // Permanent deletion (only if no dependencies)
            if ($relatedData['active_assignments'] > 0 || $relatedData['active_enrollments'] > 0 || $relatedData['active_timetables'] > 0) {
                throw new Exception("Cannot permanently delete subject with active assignments, enrollments, or timetables. Please deactivate instead.");
            }
            
            $result = $subjectManager->deleteSubject($targetSubjectId, $currentUserId, true);
            $action = "permanently deleted";
        } else {
            // Soft deletion (deactivation)
            $result = $subjectManager->deleteSubject($targetSubjectId, $currentUserId, false);
            $action = "deactivated";
        }
        
        if ($result['success']) {
            $success_message = "Subject '{$targetSubject['subject_code']}' has been {$action} successfully.";
        } else {
            $error_message = $result['message'];
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Get subject details for confirmation
 */
function getSubjectDetails($subjectId) {
    global $db;
    
    try {
        return $db->fetchRow("
            SELECT s.*, 
                   d.department_name as department_full_name,
                   CASE s.type
                       WHEN 'theory' THEN 'Theory'
                       WHEN 'practical' THEN 'Practical'
                       WHEN 'lab' THEN 'Laboratory'
                       ELSE 'Unknown'
                   END as type_display
            FROM subjects s
            LEFT JOIN departments d ON s.department_id = d.department_id
            WHERE s.subject_id = ?
        ", [$subjectId]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get related data that might be affected by deletion
 */
function getRelatedData($subjectId) {
    global $db;
    
    try {
        $data = [];
        
        // Faculty assignments
        $assignments = $db->fetchRow("
            SELECT COUNT(*) as total,
                   COUNT(CASE WHEN is_active = 1 THEN 1 END) as active
            FROM faculty_subjects 
            WHERE subject_id = ?
        ", [$subjectId]);
        $data['total_assignments'] = $assignments['total'];
        $data['active_assignments'] = $assignments['active'];
        
        // Student enrollments
        $enrollments = $db->fetchRow("
            SELECT COUNT(*) as total,
                   COUNT(CASE WHEN status = 'enrolled' THEN 1 END) as active
            FROM enrollments 
            WHERE subject_id = ?
        ", [$subjectId]);
        $data['total_enrollments'] = $enrollments['total'];
        $data['active_enrollments'] = $enrollments['active'];
        
        // Timetable entries
        $timetables = $db->fetchRow("
            SELECT COUNT(*) as total,
                   COUNT(CASE WHEN is_active = 1 THEN 1 END) as active
            FROM timetables 
            WHERE subject_id = ?
        ", [$subjectId]);
        $data['total_timetables'] = $timetables['total'];
        $data['active_timetables'] = $timetables['active'];
        
        // Get faculty currently assigned
        $data['assigned_faculty'] = $db->fetchAll("
            SELECT f.first_name, f.last_name, f.employee_id, fs.is_active
            FROM faculty_subjects fs
            JOIN faculty f ON fs.faculty_id = f.faculty_id
            WHERE fs.subject_id = ? AND fs.is_active = 1
        ", [$subjectId]);
        
        return $data;
        
    } catch (Exception $e) {
        return [
            'total_assignments' => 0,
            'active_assignments' => 0,
            'total_enrollments' => 0,
            'active_enrollments' => 0,
            'total_timetables' => 0,
            'active_timetables' => 0,
            'assigned_faculty' => []
        ];
    }
}

// Set page title
$pageTitle = $confirmationStep ? 
    "Delete Subject: " . ($targetSubject['subject_code'] ?? 'Unknown') : 
    "Subject Deletion";
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

        /* Confirmation Container */
        .confirmation-container {
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Subject Info Card */
        .subject-info-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .subject-info-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .subject-info-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .subject-avatar {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            flex-shrink: 0;
        }

        .subject-details h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .subject-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .subject-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Impact Analysis */
        .impact-analysis {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .impact-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--warning-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .impact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .impact-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }

        [data-theme="dark"] .impact-item {
            background: var(--bg-secondary);
        }

        [data-theme="light"] .impact-item {
            background: var(--bg-primary);
        }

        .impact-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--warning-color);
        }

        .impact-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Faculty List */
        .faculty-list {
            margin-top: 1rem;
        }

        .faculty-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Deletion Options */
        .deletion-options {
            margin-bottom: 2rem;
        }

        .option-card {
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
        }

        [data-theme="dark"] .option-card {
            border-color: var(--border-color);
            background: var(--bg-tertiary);
        }

        [data-theme="light"] .option-card {
            border-color: var(--border-color);
            background: var(--bg-secondary);
        }

        .option-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .option-card.selected {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
        }

        .option-card.danger.selected {
            border-color: var(--error-color);
            background: rgba(239, 68, 68, 0.1);
        }

        .option-card input[type="radio"] {
            display: none;
        }

        .option-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .option-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .option-icon.safe {
            background: var(--warning-color);
        }

        .option-icon.danger {
            background: var(--error-color);
        }

        .option-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .option-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.4;
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

            .subject-info-card .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .subject-avatar {
                margin: 0 auto 1rem auto;
            }

            .impact-grid {
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

        @media (max-width: 480px) {
            .impact-grid {
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
        <?php if ($confirmationStep && $targetSubject): ?>
            <!-- Confirmation Page -->
            <div class="page-header">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Delete Subject</h1>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <div class="confirmation-container glass-card slide-up">
                <!-- Subject Information -->
                <div class="subject-info-card">
                    <div class="d-flex align-items-center">
                        <div class="subject-avatar">
                            <?= htmlspecialchars(substr($targetSubject['subject_code'], 0, 3)) ?>
                        </div>
                        <div class="subject-details flex-grow-1 ms-3">
                            <h3><?= htmlspecialchars($targetSubject['subject_code']) ?> - <?= htmlspecialchars($targetSubject['subject_name']) ?></h3>
                            <div class="subject-meta">
                                <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($targetSubject['type_display']) ?></span>
                                <span><i class="fas fa-star"></i> <?= $targetSubject['credits'] ?> Credit<?= $targetSubject['credits'] > 1 ? 's' : '' ?></span>
                                <span><i class="fas fa-clock"></i> <?= $targetSubject['duration_hours'] ?> Hour<?= $targetSubject['duration_hours'] > 1 ? 's' : '' ?></span>
                                <span><i class="fas fa-building"></i> <?= htmlspecialchars($targetSubject['department']) ?></span>
                                <span><i class="fas fa-layer-group"></i> Year <?= $targetSubject['year_level'] ?>, Sem <?= $targetSubject['semester'] ?></span>
                                <span><i class="fas fa-toggle-<?= $targetSubject['is_active'] ? 'on' : 'off' ?>"></i> <?= $targetSubject['is_active'] ? 'Active' : 'Inactive' ?></span>
                            </div>
                            <?php if (!empty($targetSubject['description'])): ?>
                            <p class="text-muted mb-0"><?= htmlspecialchars(substr($targetSubject['description'], 0, 200)) ?><?= strlen($targetSubject['description']) > 200 ? '...' : '' ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Impact Analysis -->
                <div class="impact-analysis">
                    <div class="impact-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        Impact Analysis
                    </div>
                    
                    <div class="impact-grid">
                        <div class="impact-item">
                            <div class="impact-number"><?= $relatedData['active_assignments'] ?></div>
                            <div class="impact-label">Active Faculty<br>Assignments</div>
                        </div>
                        <div class="impact-item">
                            <div class="impact-number"><?= $relatedData['active_enrollments'] ?></div>
                            <div class="impact-label">Active Student<br>Enrollments</div>
                        </div>
                        <div class="impact-item">
                            <div class="impact-number"><?= $relatedData['active_timetables'] ?></div>
                            <div class="impact-label">Active Timetable<br>Entries</div>
                        </div>
                    </div>

                    <?php if (!empty($relatedData['assigned_faculty'])): ?>
                    <div class="faculty-list">
                        <strong>Currently Assigned Faculty:</strong>
                        <?php foreach ($relatedData['assigned_faculty'] as $faculty): ?>
                        <div class="faculty-item">
                            <i class="fas fa-user"></i>
                            <?= htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']) ?>
                            (<?= htmlspecialchars($faculty['employee_id']) ?>)
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Deletion Form -->
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="subject_id" value="<?= $targetSubject['subject_id'] ?>">
                    
                    <!-- Deletion Options -->
                    <div class="deletion-options">
                        <h4 style="color: var(--text-primary); margin-bottom: 1rem;">Choose Deletion Type:</h4>
                        
                        <!-- Deactivate Option -->
                        <div class="option-card" onclick="selectOption('deactivate')">
                            <input type="radio" name="delete_type" value="deactivate" checked>
                            <div class="option-header">
                                <div class="option-icon safe">
                                    <i class="fas fa-pause"></i>
                                </div>
                                <div class="option-title">Deactivate Subject</div>
                            </div>
                            <div class="option-description">
                                Safely deactivate the subject while preserving all data. The subject will be hidden from new assignments and enrollments but existing data remains intact. This action can be reversed.
                            </div>
                        </div>

                        <!-- Permanent Delete Option -->
                        <?php if ($relatedData['active_assignments'] == 0 && $relatedData['active_enrollments'] == 0 && $relatedData['active_timetables'] == 0): ?>
                        <div class="option-card danger" onclick="selectOption('permanent')">
                            <input type="radio" name="delete_type" value="permanent">
                            <div class="option-header">
                                <div class="option-icon danger">
                                    <i class="fas fa-trash"></i>
                                </div>
                                <div class="option-title">Permanently Delete</div>
                            </div>
                            <div class="option-description">
                                <strong>⚠️ DANGER:</strong> Completely remove the subject from the system. All historical data will be lost. This action cannot be undone.
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ Permanent deletion not available</strong><br>
                            Cannot permanently delete subject with active assignments, enrollments, or timetables. 
                            Please deactivate instead to preserve data integrity.
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Confirmation Actions -->
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="index.php" class="btn-action btn-outline">
                            ❌ Cancel
                        </a>
                        <button type="submit" class="btn-action btn-danger" id="deleteBtn">
                            🗑️ Confirm Deletion
                        </button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Result Page -->
            <div class="page-header">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Subject Deletion</h1>
                        <p class="page-subtitle">Subject deletion result</p>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <div class="confirmation-container glass-card slide-up">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <strong>✅ Success!</strong><br>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                    <div class="text-center">
                        <p id="redirectMessage">Redirecting to subject management in <span id="countdown">3</span> seconds...</p>
                        <a href="index.php" class="btn-action btn-primary">
                            📚 Return to Subjects
                        </a>
                    </div>
                    <script>
                        // Auto-redirect after successful deletion
                        let countdown = 3;
                        const countdownElement = document.getElementById('countdown');
                        const redirectMessage = document.getElementById('redirectMessage');
                        
                        const timer = setInterval(() => {
                            countdown--;
                            if (countdownElement) {
                                countdownElement.textContent = countdown;
                            }
                            
                            if (countdown <= 0) {
                                clearInterval(timer);
                                if (redirectMessage) {
                                    redirectMessage.textContent = 'Redirecting now...';
                                }
                                window.location.href = 'index.php';
                            }
                        }, 1000);
                    </script>
                <?php elseif (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <strong>❌ Error!</strong><br>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <div class="text-center">
                        <a href="index.php" class="btn-action btn-primary">
                            📚 Return to Subjects
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        /**
         * Handle option selection for deletion type
         */
        function selectOption(type) {
            // Remove selected class from all options
            document.querySelectorAll('.option-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
        
        /**
         * Confirm deletion with additional validation
         */
        function confirmDeletion() {
            const deleteType = document.querySelector('input[name="delete_type"]:checked').value;
            const subjectCode = '<?= addslashes($targetSubject['subject_code'] ?? '') ?>';
            const subjectName = '<?= addslashes($targetSubject['subject_name'] ?? '') ?>';
            
            let message;
            if (deleteType === 'permanent') {
                message = `Are you absolutely sure you want to PERMANENTLY DELETE subject "${subjectCode}"?\n\n` +
                         `Subject: ${subjectName}\n\n` +
                         `This action will:\n` +
                         `• Completely remove the subject from the system\n` +
                         `• Delete all associated historical data\n` +
                         `• Remove all past assignments and enrollments\n` +
                         `• Cannot be undone\n\n` +
                         `Type "DELETE" to confirm:`;
                         
                const confirmation = prompt(message);
                if (confirmation !== 'DELETE') {
                    alert('Deletion cancelled. You must type "DELETE" exactly to confirm permanent deletion.');
                    return false;
                }
            } else {
                message = `Are you sure you want to deactivate subject "${subjectCode}"?\n\n` +
                         `Subject: ${subjectName}\n\n` +
                         `This action will:\n` +
                         `• Hide the subject from new assignments and enrollments\n` +
                         `• Preserve all existing data and history\n` +
                         `• Can be reversed by reactivating the subject\n\n` +
                         `Click OK to confirm or Cancel to abort.`;
                         
                if (!confirm(message)) {
                    return false;
                }
            }
            
            return true;
        }
        
        /**
         * Initialize page interactions
         */
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Handle sidebar toggle events
            handleSidebarToggle();
            
            // Add click handlers to option cards
            document.querySelectorAll('.option-card').forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    document.querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
                    
                    // Add selected class to current card
                    this.classList.add('selected');
                    
                    // Check the radio button
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });
            
            // Set initial selected state
            const checkedInput = document.querySelector('input[name="delete_type"]:checked');
            if (checkedInput) {
                checkedInput.closest('.option-card').classList.add('selected');
            }
            
            // Handle form submission with proper loading state
            const deleteForm = document.getElementById('deleteForm');
            if (deleteForm) {
                deleteForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent default submission
                    
                    if (confirmDeletion()) {
                        // Show loading state
                        const deleteBtn = document.getElementById('deleteBtn');
                        deleteBtn.innerHTML = '⏳ Processing...';
                        deleteBtn.disabled = true;
                        
                        // Submit the form
                        this.submit();
                    }
                });
            }
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