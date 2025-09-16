<?php
/**
 * Admin Timetable Delete - Timetable Deletion Handler
 * Timetable Management System
 * 
 * Handles timetable entry deletion with proper validation and security checks
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
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$timetableManager = new Timetable();

// Initialize variables
$error_message = '';
$success_message = '';
$targetTimetable = null;
$confirmationStep = false;

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle actual deletion
    handleDeletion();
} elseif (isset($_GET['id'])) {
    // Show confirmation page
    $targetTimetableId = (int)$_GET['id'];
    $targetTimetable = getTimetableDetails($targetTimetableId);
    if ($targetTimetable) {
        $confirmationStep = true;
    } else {
        // Invalid ID -> show error on this page
        $error_message = "Timetable entry not found.";
        $confirmationStep = false;
    }

} else {
    // Redirect to timetable management if no ID provided
    header('Location: index.php');
    exit;
}

/**
 * Handle timetable deletion
 */
function handleDeletion() {
    global $currentUserId, $timetableManager, $success_message, $error_message, $confirmationStep;
    
    try {
        $targetTimetableId = (int)$_POST['timetable_id'];
        $deleteType = $_POST['delete_type'] ?? 'deactivate';
        
        $targetTimetable = getTimetableDetails($targetTimetableId);
        if (!$targetTimetable) {
            throw new Exception("Timetable entry not found.");
        }
        
        if ($deleteType === 'permanent') {
            // Permanent deletion - completely remove the entry
            $result = $timetableManager->deleteTimetableEntry($targetTimetableId, $currentUserId);
            $action = "permanently deleted";
        } else {
            // Soft deletion (deactivation) - set is_active to 0
            $result = $timetableManager->deactivateTimetableEntry($targetTimetableId, $currentUserId);
            $action = "deactivated";
        }
        
        if ($result['success']) {
            $success_message = "Timetable entry has been {$action} successfully.";
        } else {
            $error_message = $result['message'] ?? 'Failed to update timetable entry.';
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
    // Stay on this page and show result
    $confirmationStep = false;
}

/**
 * Get timetable details for confirmation
 */
function getTimetableDetails($timetableId) {
    global $db;
    
    try {
        return $db->fetchRow("
            SELECT t.*, 
                   s.subject_code,
                   s.subject_name,
                   s.department as subject_department,
                   CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                   f.employee_id,
                   c.room_number,
                   c.building,
                   c.capacity as classroom_capacity,
                   ts.day_of_week,
                   ts.start_time,
                   ts.end_time,
                   ts.slot_name,
                   CONCAT(
                       DATE_FORMAT(ts.start_time, '%h:%i %p'), 
                       ' - ', 
                       DATE_FORMAT(ts.end_time, '%h:%i %p')
                   ) as time_range,
                   COUNT(e.enrollment_id) as enrolled_students
            FROM timetables t
            JOIN subjects s ON t.subject_id = s.subject_id
            JOIN faculty f ON t.faculty_id = f.faculty_id
            JOIN classrooms c ON t.classroom_id = c.classroom_id
            JOIN time_slots ts ON t.slot_id = ts.slot_id
            LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
                AND t.section = e.section 
                AND t.semester = e.semester 
                AND t.academic_year = e.academic_year
                AND e.status = 'enrolled'
            WHERE t.timetable_id = ?
            GROUP BY t.timetable_id
        ", [$timetableId]);
    } catch (Exception $e) {
        return null;
    }
}

// Set page title
$pageTitle = $confirmationStep ? "Delete Timetable Entry - Confirmation" : "Delete Timetable Entry";
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

        /* Confirmation Container */
        .confirmation-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        [data-theme="dark"] .confirmation-container {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .confirmation-container {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Timetable Info Card */
        .timetable-info-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .timetable-info-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .timetable-info-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .timetable-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }

        .timetable-details h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .timetable-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .timetable-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        [data-theme="dark"] .timetable-meta span {
            background: var(--bg-primary);
        }

        [data-theme="light"] .timetable-meta span {
            background: var(--bg-tertiary);
        }

        .timetable-meta i {
            color: var(--primary-color);
            width: 16px;
            text-align: center;
        }

        /* Schedule Time Badge */
        .schedule-time {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .schedule-day {
            background: var(--success-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Warning Card */
        .warning-card {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .warning-card h5 {
            color: var(--warning-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .warning-card ul {
            margin-bottom: 0;
            color: var(--text-secondary);
        }

        /* Deletion Options */
        .deletion-options {
            margin-bottom: 2rem;
        }

        .deletion-options h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .option-card {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .option-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .option-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .option-card:hover {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .option-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .option-card input[type="radio"] {
            margin-right: 1rem;
            margin-top: 0.25rem;
        }

        .option-card strong {
            color: var(--text-primary);
            display: block;
            margin-bottom: 0.5rem;
        }

        .option-card p {
            font-size: 0.875rem;
            color: var(--text-secondary);
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

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            color: white;
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

            .timetable-info-card .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .timetable-icon {
                margin: 0 auto 1rem auto;
            }

            .timetable-meta {
                grid-template-columns: 1fr;
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
        <?php if ($confirmationStep && $targetTimetable): ?>
            <!-- Confirmation Page -->
            <div class="page-header">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Delete Timetable Entry</h1>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <div class="confirmation-container glass-card slide-up">
                <!-- Timetable Information -->
                <div class="timetable-info-card">
                    <div class="d-flex align-items-center">
                        <div class="timetable-icon">
                            📚
                        </div>
                        
                        <div class="timetable-details flex-grow-1">
                            <h3><?= htmlspecialchars($targetTimetable['subject_code']) ?> - <?= htmlspecialchars($targetTimetable['subject_name']) ?></h3>
                            
                            <div class="timetable-meta">
                                <span><i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($targetTimetable['faculty_name']) ?></span>
                                <span><i class="fas fa-id-badge"></i> <?= htmlspecialchars($targetTimetable['employee_id']) ?></span>
                                <span><i class="fas fa-door-open"></i> <?= htmlspecialchars($targetTimetable['room_number']) ?>, <?= htmlspecialchars($targetTimetable['building']) ?></span>
                                <span><i class="fas fa-users"></i> <?= $targetTimetable['enrolled_students'] ?> Students</span>
                                <span><i class="fas fa-calendar-day"></i> <span class="schedule-day"><?= $targetTimetable['day_of_week'] ?></span></span>
                                <span><i class="fas fa-clock"></i> <span class="schedule-time"><?= $targetTimetable['time_range'] ?></span></span>
                                <span><i class="fas fa-tag"></i> Section <?= htmlspecialchars($targetTimetable['section']) ?></span>
                                <span><i class="fas fa-layer-group"></i> Semester <?= $targetTimetable['semester'] ?></span>
                                <span><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($targetTimetable['academic_year']) ?></span>
                                <span><i class="fas fa-building"></i> <?= htmlspecialchars($targetTimetable['subject_department']) ?></span>
                                <span><i class="fas fa-plus-circle"></i> Created <?= date('M j, Y', strtotime($targetTimetable['created_at'])) ?></span>
                                <span><i class="fas fa-toggle-<?= $targetTimetable['is_active'] ? 'on' : 'off' ?>"></i> <?= $targetTimetable['is_active'] ? 'Active' : 'Inactive' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning -->
                <div class="warning-card">
                    <h5><i class="fas fa-exclamation-triangle"></i> Warning: You are about to delete the timetable entry for 
                        <strong><?= htmlspecialchars($targetTimetable['subject_code']) ?> - <?= htmlspecialchars($targetTimetable['subject_name']) ?></strong>. 
                    Please consider the following:</h5>
                    <ul>
                        <li><strong>Student Impact:</strong> <?= $targetTimetable['enrolled_students'] ?> enrolled student(s) will be affected</li>
                        <li><strong>Faculty Schedule:</strong> This will affect <?= htmlspecialchars($targetTimetable['faculty_name']) ?>'s teaching schedule</li>
                        <li><strong>Classroom Booking:</strong> <?= htmlspecialchars($targetTimetable['room_number']) ?> will be freed for this time slot</li>
                        <li><strong>Academic Records:</strong> This may affect semester scheduling and academic planning</li>
                        <li><strong>Reversibility:</strong> Permanent deletion cannot be undone</li>
                    </ul>
                </div>

                <!-- Deletion Options -->
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="timetable_id" value="<?= $targetTimetable['timetable_id'] ?>">
                    
                    <div class="deletion-options">
                        <h5>Choose Deletion Type:</h5>
                        
                        <div class="option-card selected" onclick="selectOption('deactivate')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="deactivate" checked>
                                <div>
                                    <strong><i class="fas fa-pause-circle"></i> Deactivate Entry (Recommended)</strong>
                                    <p class="mb-0">Timetable entry will be deactivated but data preserved. Can be reactivated later if needed. This is the safest option and maintains academic records.</p>
                                </div>
                            </label>
                        </div>

                        <div class="option-card" onclick="selectOption('permanent')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="permanent">
                                <div>
                                    <strong style="color: var(--error-color);"><i class="fas fa-trash"></i> Permanent Deletion</strong>
                                    <p class="mb-0">Completely remove timetable entry and all associated scheduling data. This action cannot be undone and may affect academic records and student enrollments.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="index.php" class="btn-action btn-outline">
                            ❌ Cancel
                        </a>
                        <button type="button" class="btn-action btn-danger" onclick="confirmDeletion()">
                            🗑️ Proceed with Deletion
                        </button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Result Page (Success or Error) -->
            <div class="page-header">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <?php if (!empty($success_message)): ?>
                            <h1 class="page-title">✅ Timetable Entry Updated</h1>
                            <p class="page-subtitle">The timetable entry has been processed successfully.</p>
                        <?php else: ?>
                            <h1 class="page-title">❌ Delete Timetable Entry - Error</h1>
                            <p class="page-subtitle">Unable to process timetable deletion request</p>
                        <?php endif; ?>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger glass-card fade-in" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
            <!-- Error Card -->
            <div class="confirmation-container glass-card slide-up">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-circle" style="font-size: 4rem; color: var(--error-color);"></i>
                    </div>
                    <h3 class="mb-3">Unable to Delete Timetable Entry</h3>
                    <p class="text-muted mb-4">
                        <?= htmlspecialchars($error_message) ?>
                    </p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="index.php" class="btn-action btn-primary">
                            📅 Return to Timetables
                        </a>
                    </div>
                </div>
            </div>
            <?php elseif (!empty($success_message)): ?>
            <!-- Success Card -->
            <div class="confirmation-container glass-card slide-up">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success-color);"></i>
                    </div>
                    <h3 class="mb-3">Success</h3>
                    <p class="text-muted mb-2"><?= htmlspecialchars($success_message) ?></p>
                    <p class="text-muted mb-4"><small>Redirecting to Timetables in 3 seconds...</small></p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="index.php" class="btn-action btn-primary">
                            📅 Return to Timetables
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
            
            // Initialize option selection
            initializeOptionSelection();

            // Auto-redirect after success (3 seconds)
            const deletionSuccess = <?= !empty($success_message) ? 'true' : 'false' ?>;
            if (deletionSuccess) {
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 3000);
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
                    document.body.classList.toggle('sidebar-collapsed');
                });
            }
        }

        /**
         * Initialize option selection
         */
        function initializeOptionSelection() {
            // Set up click handlers for option cards
            document.querySelectorAll('.option-card').forEach(card => {
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        selectOption(radio.value);
                    }
                });
            });
        }

        /**
         * Handle option selection for deletion type
         */
        function selectOption(type) {
            // Remove selected class from all options
            document.querySelectorAll('.option-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to the card containing the selected radio
            const selectedRadio = document.querySelector(`input[name="delete_type"][value="${type}"]`);
            if (selectedRadio) {
                selectedRadio.checked = true;
                selectedRadio.closest('.option-card').classList.add('selected');
            }
        }
        
        /**
         * Confirm deletion with additional validation
         */
        function confirmDeletion() {
            const deleteTypeRadio = document.querySelector('input[name="delete_type"]:checked');
            if (!deleteTypeRadio) {
                alert('Please select a deletion type.');
                return;
            }
            
            const deleteType = deleteTypeRadio.value;
            const subjectInfo = '<?= addslashes($targetTimetable['subject_code'] ?? '') ?> - <?= addslashes($targetTimetable['subject_name'] ?? '') ?>';
            const facultyName = '<?= addslashes($targetTimetable['faculty_name'] ?? '') ?>';
            const enrolledStudents = '<?= $targetTimetable['enrolled_students'] ?? 0 ?>';
            const timeSlot = '<?= addslashes($targetTimetable['day_of_week'] ?? '') ?> <?= addslashes($targetTimetable['time_range'] ?? '') ?>';
            
            let message;
            if (deleteType === 'permanent') {
                message = `⚠️ PERMANENT DELETION CONFIRMATION\n\n` +
                         `You are about to PERMANENTLY DELETE the timetable entry:\n` +
                         `📚 ${subjectInfo}\n` +
                         `👨‍🏫 Faculty: ${facultyName}\n` +
                         `⏰ Time: ${timeSlot}\n` +
                         `👥 Enrolled Students: ${enrolledStudents}\n\n` +
                         `This action will:\n` +
                         `• Completely remove the timetable entry from the system\n` +
                         `• Affect ${enrolledStudents} enrolled student(s)\n` +
                         `• Free up the classroom and time slot\n` +
                         `• Cannot be undone\n\n` +
                         `Are you absolutely sure you want to proceed?`;
                         
                if (!confirm(message)) {
                    return;
                }
                
                // Additional confirmation for permanent deletion
                const confirmText = prompt('To confirm permanent deletion, type "DELETE" (in capitals):');
                if (confirmText !== 'DELETE') {
                    alert('Deletion cancelled. You must type "DELETE" exactly to confirm permanent deletion.');
                    return;
                }
            } else {
                message = `Deactivate timetable entry for "${subjectInfo}"?\n\n` +
                         `👨‍🏫 Faculty: ${facultyName}\n` +
                         `⏰ Time: ${timeSlot}\n` +
                         `👥 Enrolled Students: ${enrolledStudents}\n\n` +
                         `This will:\n` +
                         `• Set the timetable entry to inactive status\n` +
                         `• Preserve all data for potential reactivation\n` +
                         `• Can be reactivated later if needed\n\n` +
                         `Do you want to continue?`;
                         
                if (!confirm(message)) {
                    return;
                }
            }
            
            // Submit the form
            document.getElementById('deleteForm').submit();
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