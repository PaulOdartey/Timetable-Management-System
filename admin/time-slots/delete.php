<?php
/**
 * Admin Time Slots Delete - Time Slot Deletion Handler
 * Timetable Management System
 * 
 * Handles time slot deletion with proper validation and dependency checks
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/TimeSlot.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$timeSlotManager = new TimeSlot();

// Initialize variables
$error_message = '';
$success_message = '';
$targetTimeSlot = null;
$confirmationStep = false;
$dependencyCheck = null;

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle actual deletion
    handleDeletion();
} elseif (isset($_GET['id'])) {
    // Show confirmation page
    $targetSlotId = (int)$_GET['id'];
    $targetTimeSlot = getTimeSlotDetails($targetSlotId);
    if ($targetTimeSlot) {
        $confirmationStep = true;
        $dependencyCheck = checkTimeSlotDependencies($targetSlotId);
    } else {
        $error_message = "Time slot not found.";
    }
} else {
    // Redirect to time slot management if no ID provided
    header('Location: index.php');
    exit;
}

/**
 * Handle time slot deletion
 */
function handleDeletion() {
    global $currentUserId, $timeSlotManager, $success_message, $error_message;
    
    try {
        $targetSlotId = (int)$_POST['slot_id'];
        $deleteType = $_POST['delete_type'] ?? 'deactivate';
        
        $targetTimeSlot = getTimeSlotDetails($targetSlotId);
        if (!$targetTimeSlot) {
            throw new Exception("Time slot not found.");
        }
        
        // Check dependencies
        $dependencies = checkTimeSlotDependencies($targetSlotId);
        
        if ($deleteType === 'permanent') {
            // Permanent deletion (only for slots with no dependencies)
            if ($dependencies['has_dependencies']) {
                throw new Exception("Cannot permanently delete time slot. It is being used in " . 
                                  $dependencies['total_dependencies'] . " timetable entries.");
            }
            
            $result = $timeSlotManager->deleteTimeSlot($targetSlotId, true);
            $action = "permanently deleted";
        } else {
            // Soft deletion (deactivation)
            if ($dependencies['has_dependencies']) {
                // Warn about consequences but allow deactivation
                $result = $timeSlotManager->deactivateTimeSlot($targetSlotId);
                $action = "deactivated (affects " . $dependencies['total_dependencies'] . " active schedules)";
            } else {
                $result = $timeSlotManager->deactivateTimeSlot($targetSlotId);
                $action = "deactivated";
            }
        }
        
        if ($result['success']) {
            $success_message = "Time slot has been {$action} successfully.";
        } else {
            $error_message = $result['message'];
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Get time slot details for confirmation
 */
function getTimeSlotDetails($slotId) {
    global $db;
    
    try {
        return $db->fetchRow("
            SELECT slot_id, day_of_week, start_time, end_time, slot_name, slot_type, is_active
            FROM time_slots 
            WHERE slot_id = ?
        ", [$slotId]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check time slot dependencies (timetables using this slot)
 */
function checkTimeSlotDependencies($slotId) {
    global $db;
    
    try {
        // Get active timetables using this slot
        $activeTimetables = $db->fetchAll("
            SELECT t.timetable_id, t.subject_id, t.faculty_id, t.classroom_id, t.section, 
                   t.semester, t.academic_year,
                   s.subject_code, s.subject_name,
                   CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                   c.room_number, c.building
            FROM timetables t
            LEFT JOIN subjects s ON t.subject_id = s.subject_id
            LEFT JOIN faculty f ON t.faculty_id = f.faculty_id
            LEFT JOIN classrooms c ON t.classroom_id = c.classroom_id
            WHERE t.slot_id = ? AND t.is_active = 1
            ORDER BY s.subject_code, t.section
        ", [$slotId]);
        
        // Get all timetables (including inactive)
        $allTimetables = $db->fetchRow("
            SELECT COUNT(*) as total_count
            FROM timetables 
            WHERE slot_id = ?
        ", [$slotId]);
        
        return [
            'has_dependencies' => count($activeTimetables) > 0,
            'active_dependencies' => $activeTimetables,
            'total_dependencies' => (int)$allTimetables['total_count']
        ];
        
    } catch (Exception $e) {
        return [
            'has_dependencies' => false,
            'active_dependencies' => [],
            'total_dependencies' => 0
        ];
    }
}

// Set page title
$pageTitle = $confirmationStep ? "Delete Time Slot" : "Time Slot Deletion Result";
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

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
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

        /* Confirmation Container */
        .confirmation-container {
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Time Slot Info Card */
        .timeslot-info-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .timeslot-info-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .timeslot-info-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .timeslot-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
            margin-right: 1rem;
        }

        .timeslot-icon.regular { background: var(--info-color); }
        .timeslot-icon.break { background: var(--warning-color); }
        .timeslot-icon.lunch { background: var(--success-color); }

        .timeslot-details h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .timeslot-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .timeslot-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Warning Section */
        .warning-section {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .warning-section h5 {
            color: var(--warning-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Dependencies List */
        .dependencies-list {
            max-height: 300px;
            overflow-y: auto;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
        }

        [data-theme="dark"] .dependencies-list {
            background: var(--bg-primary);
        }

        [data-theme="light"] .dependencies-list {
            background: var(--bg-tertiary);
        }

        .dependency-item {
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: rgba(255, 255, 255, 0.02);
        }

        [data-theme="dark"] .dependency-item {
            border-color: var(--border-color);
            background: var(--bg-secondary);
        }

        [data-theme="light"] .dependency-item {
            border-color: var(--border-color);
            background: var(--bg-primary);
        }

        .dependency-item:last-child {
            margin-bottom: 0;
        }

        /* Deletion Options */
        .deletion-options {
            margin-bottom: 2rem;
        }

        .deletion-options h5 {
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .option-card {
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem;
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

        .option-card input[type="radio"] {
            margin-right: 0.75rem;
            transform: scale(1.2);
        }

        .cursor-pointer {
            cursor: pointer;
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

            .timeslot-info-card .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .timeslot-icon {
                margin: 0 auto 1rem auto;
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

            .timeslot-meta {
                justify-content: center;
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
        <?php if ($confirmationStep && $targetTimeSlot): ?>
            <!-- Confirmation Page -->
            <div class="page-header compact">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Delete Time Slot</h1>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <div class="confirmation-container glass-card slide-up">
                <!-- Time Slot Information -->
                <div class="timeslot-info-card">
                    <div class="d-flex align-items-center">
                        <div class="timeslot-icon <?= strtolower($targetTimeSlot['slot_type']) ?>">
                            <?php
                            $icons = [
                                'regular' => 'fas fa-chalkboard-teacher',
                                'break' => 'fas fa-coffee',
                                'lunch' => 'fas fa-utensils'
                            ];
                            echo '<i class="' . ($icons[$targetTimeSlot['slot_type']] ?? 'fas fa-clock') . '"></i>';
                            ?>
                        </div>
                        <div class="timeslot-details flex-grow-1">
                            <h3><?= htmlspecialchars($targetTimeSlot['slot_name']) ?></h3>
                            <div class="timeslot-meta">
                                <span><i class="fas fa-calendar-day"></i> <?= htmlspecialchars($targetTimeSlot['day_of_week']) ?></span>
                                <span><i class="fas fa-clock"></i> 
                                    <?= date('g:i A', strtotime($targetTimeSlot['start_time'])) ?> - 
                                    <?= date('g:i A', strtotime($targetTimeSlot['end_time'])) ?>
                                </span>
                                <span><i class="fas fa-tag"></i> <?= ucfirst($targetTimeSlot['slot_type']) ?></span>
                                <span><i class="fas fa-toggle-<?= $targetTimeSlot['is_active'] ? 'on' : 'off' ?>"></i> 
                                    <?= $targetTimeSlot['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning Section -->
                <div class="warning-section">
                    <h5><i class="fas fa-exclamation-triangle"></i> Important Notice</h5>
                    <p><strong>You are about to delete the time slot "<?= htmlspecialchars($targetTimeSlot['slot_name']) ?>"</strong>. 
                    Please consider the following:</p>
                    <ul>
                        <li><strong>Schedule Impact:</strong> This may affect existing timetables and schedules</li>
                        <li><strong>System References:</strong> Active classes may lose their time slot assignment</li>
                        <li><strong>Data Integrity:</strong> Consider deactivation instead of permanent deletion</li>
                    </ul>
                </div>

                <?php if ($dependencyCheck['has_dependencies']): ?>
                <!-- Dependencies Alert -->
                <div class="alert alert-warning">
                    <h6><i class="fas fa-link me-2"></i>Active Dependencies Found</h6>
                    <p>This time slot is currently being used by <strong><?= count($dependencyCheck['active_dependencies']) ?> active timetable entries</strong>:</p>
                    
                    <div class="dependencies-list">
                        <?php foreach ($dependencyCheck['active_dependencies'] as $dependency): ?>
                        <div class="dependency-item">
                            <strong><?= htmlspecialchars($dependency['subject_code']) ?> - <?= htmlspecialchars($dependency['subject_name']) ?></strong><br>
                            <small>
                                Faculty: <?= htmlspecialchars($dependency['faculty_name']) ?> | 
                                Room: <?= htmlspecialchars($dependency['room_number']) ?>, <?= htmlspecialchars($dependency['building']) ?> | 
                                Section: <?= htmlspecialchars($dependency['section']) ?> | 
                                Semester: <?= htmlspecialchars($dependency['semester']) ?> (<?= htmlspecialchars($dependency['academic_year']) ?>)
                            </small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Deletion Options -->
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="slot_id" value="<?= $targetTimeSlot['slot_id'] ?>">
                    
                    <div class="deletion-options">
                        <h5>Choose Deletion Type:</h5>
                        
                        <div class="option-card" onclick="selectOption('deactivate')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="deactivate" checked>
                                <div>
                                    <strong>Deactivate Time Slot (Recommended)</strong>
                                    <p class="mb-0 text-muted">Time slot will be deactivated but data preserved. 
                                    Existing schedules remain but slot won't be available for new bookings.</p>
                                    <?php if ($dependencyCheck['has_dependencies']): ?>
                                    <small class="text-warning">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        This will affect <?= count($dependencyCheck['active_dependencies']) ?> active schedules.
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>

                        <?php if (!$dependencyCheck['has_dependencies']): ?>
                        <div class="option-card" onclick="selectOption('permanent')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="permanent">
                                <div>
                                    <strong style="color: var(--error-color);">Permanent Deletion</strong>
                                    <p class="mb-0 text-muted">Completely remove time slot and all associated data. 
                                    This action cannot be undone.</p>
                                </div>
                            </label>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ Permanent deletion not available</strong><br>
                            Time slots with active dependencies cannot be permanently deleted. 
                            Current dependencies: <strong><?= count($dependencyCheck['active_dependencies']) ?> active schedules</strong>
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
            <div class="page-header compact">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Time Slot Deletion</h1>
                        <p class="page-subtitle">Time slot deletion result</p>
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
                        <p>Redirecting to time slot management in 3 seconds...</p>
                        <a href="index.php" class="btn-action btn-primary">
                            ⏰ Return to Time Slots
                        </a>
                    </div>
                <?php elseif (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <strong>❌ Error!</strong><br>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <div class="text-center">
                        <a href="index.php" class="btn-action btn-primary">
                            ⏰ Return to Time Slots
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($success_message)): ?>
            <script>
                // Auto-redirect to index after 3 seconds
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 3000);
            </script>
            <?php endif; ?>
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
            const slotName = '<?= addslashes($targetTimeSlot['slot_name'] ?? '') ?>';
            const hasDependencies = <?= $dependencyCheck['has_dependencies'] ? 'true' : 'false' ?>;
            const dependencyCount = <?= count($dependencyCheck['active_dependencies'] ?? []) ?>;
            
            let message;
            if (deleteType === 'permanent') {
                message = `Are you absolutely sure you want to PERMANENTLY DELETE the time slot "${slotName}"?\n\n` +
                         `This action will:\n` +
                         `• Completely remove the time slot from the system\n` +
                         `• Delete all associated historical data\n` +
                         `• Cannot be undone\n\n` +
                         `Type "DELETE" to confirm:`;
                         
                const confirmation = prompt(message);
                if (confirmation !== 'DELETE') {
                    alert('Deletion cancelled. You must type "DELETE" exactly to confirm permanent deletion.');
                    return false;
                }
            } else {
                let dependencyWarning = '';
                if (hasDependencies) {
                    dependencyWarning = `\n\n⚠️ WARNING: This will affect ${dependencyCount} active timetable entries. ` +
                                      `Those schedules will remain but may show as having invalid time slots.`;
                }
                
                message = `Are you sure you want to deactivate the time slot "${slotName}"?${dependencyWarning}\n\n` +
                         `This action will:\n` +
                         `• Set the time slot as inactive\n` +
                         `• Preserve all data for potential reactivation\n` +
                         `• Can be reversed later\n\n` +
                         `Click OK to confirm or Cancel to abort.`;
                         
                if (!confirm(message)) {
                    return false;
                }
            }
            
            return true;
        }
        
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