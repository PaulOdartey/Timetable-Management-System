<?php
/**
 * Admin Classroom Delete - Classroom Deletion Handler
 * Timetable Management System
 * 
 * Handles classroom deletion with proper validation and security checks
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Classroom.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$classroomManager = new Classroom();

// Initialize variables
$error_message = '';
$success_message = '';
$targetClassroom = null;
$confirmationStep = false;

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle actual deletion
    handleDeletion();
} elseif (isset($_GET['id'])) {
    // Show confirmation page
    $targetClassroomId = (int)$_GET['id'];
    $targetClassroom = getClassroomDetails($targetClassroomId);
    if ($targetClassroom) {
        $confirmationStep = true;
    } else {
        $error_message = "Classroom not found.";
    }
} else {
    // Redirect to classroom management if no ID provided
    header('Location: index.php');
    exit;
}

/**
 * Handle classroom deletion
 */
function handleDeletion() {
    global $classroomManager, $success_message, $error_message;
    
    try {
        $targetClassroomId = (int)$_POST['classroom_id'];
        $deleteType = $_POST['delete_type'] ?? 'deactivate';
        
        $targetClassroom = getClassroomDetails($targetClassroomId);
        if (!$targetClassroom) {
            throw new Exception("Classroom not found.");
        }
        
        // Check for active schedules
        $activeSchedules = checkActiveSchedules($targetClassroomId);
        
        if ($deleteType === 'permanent') {
            // Permanent deletion (only if no active schedules)
            if ($activeSchedules > 0) {
                throw new Exception("Cannot permanently delete classroom with {$activeSchedules} active schedule(s). Please deactivate first.");
            }
            
            $result = $classroomManager->deleteClassroom($targetClassroomId);
            $action = "permanently deleted";
        } else {
            // Soft deletion (deactivation)
            $result = $classroomManager->updateClassroom($targetClassroomId, ['is_active' => 0]);
            $action = "deactivated";
        }
        
        if ($result['success']) {
            $success_message = "Classroom '{$targetClassroom['room_number']}' has been {$action} successfully.";
        } else {
            $error_message = $result['message'];
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Get classroom details for confirmation
 */
function getClassroomDetails($classroomId) {
    global $db;
    
    try {
        return $db->fetchRow("
            SELECT c.*,
                   d.department_name,
                   d.department_code,
                   COUNT(DISTINCT t.timetable_id) as total_schedules,
                   COUNT(DISTINCT CASE WHEN t.is_active = 1 THEN t.timetable_id END) as active_schedules
            FROM classrooms c
            LEFT JOIN departments d ON c.department_id = d.department_id
            LEFT JOIN timetables t ON c.classroom_id = t.classroom_id
            WHERE c.classroom_id = ?
            GROUP BY c.classroom_id
        ", [$classroomId]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check for active schedules in classroom
 */
function checkActiveSchedules($classroomId) {
    global $db;
    
    try {
        $result = $db->fetchRow("
            SELECT COUNT(*) as count 
            FROM timetables 
            WHERE classroom_id = ? AND is_active = 1
        ", [$classroomId]);
        
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Set page title
$pageTitle = $confirmationStep ? "Delete Classroom" : "Classroom Deletion";
$currentPage = "classrooms";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $pageTitle ?> - <?= SYSTEM_NAME ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-tertiary: #94a3b8;
            --border-color: #e2e8f0;
            --accent-color: #667eea;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --primary-color: #667eea;
            --primary-color-alpha: rgba(102, 126, 234, 0.1);
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --bg-tertiary: #404040;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --text-tertiary: #808080;
            --border-color: #404040;
            --glass-bg: rgba(0, 0, 0, 0.25);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
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

        /* Page Header */
        /* Fixed Page Header under navbar */
        :root { --navbar-height: 64px; }

        .page-header {
            position: sticky;
            top: var(--navbar-height);
            z-index: 998;
            margin-bottom: 1rem;
            margin-top: 1rem;
        }

        .header-card {
            padding: 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        /* Compact header variant for this page */
        .page-header.compact .header-card {
            padding: 0.75rem 1rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .page-header.compact .page-title {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }
        .page-header.compact .page-subtitle {
            font-size: 0.95rem;
            margin-bottom: 0;
        }
        .page-header.compact .header-text { display: flex; flex-direction: column; }
        .btn-action.btn-sm { padding: 0.4rem 0.75rem; border-radius: 10px; font-size: 0.8rem; }
        .page-header .btn-action.btn-sm .back-icon { font-size: 1.2rem; line-height: 1; }

        [data-theme="dark"] .page-header .header-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
            border-color: var(--border-color);
        }

        /* Confirmation Container */
        .confirmation-container {
            margin: 0 auto;
            padding: 2rem;
        }

        .user-info-card {
            background: rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .user-details h4 {
            margin: 0 0 0.5rem 0;
            font-weight: 700;
            color: var(--text-primary);
        }

        .user-meta {
            color: var(--text-secondary);
            margin: 0.25rem 0;
            font-size: 0.9rem;
        }

        .warning-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--error-color);
            text-align: center;
        }

        .warning-box h5 {
            color: var(--error-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .warning-box p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .warning-box .warning-icon {
            font-size: 2rem;
            color: var(--error-color);
            margin-bottom: 1rem;
        }

        .deletion-options {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .option-card {
            background: rgba(255, 255, 255, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .option-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .option-card input[type="radio"] {
            margin-right: 0.75rem;
        }

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

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
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

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
            color: white;
        }

        .back-icon {
            font-size: 1.125rem;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
            border-left: 4px solid #f59e0b;
        }

        /* Badge styles */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-type-lecture {
            background: rgba(6, 182, 212, 0.1);
            color: #0891b2;
        }

        .badge-type-lab {
            background: rgba(236, 72, 153, 0.1);
            color: #db2777;
        }

        .badge-type-seminar {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }

        .badge-type-auditorium {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .badge-status-available {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .badge-status-maintenance {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .badge-status-reserved {
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
        }

        .badge-status-closed {
            background: rgba(100, 116, 139, 0.1);
            color: #475569;
        }

        /* Dark mode styles */
        [data-theme="dark"] .user-info-card {
            background: rgba(30, 41, 59, 0.3);
            border: 1px solid var(--glass-border);
        }

        [data-theme="dark"] .deletion-options {
            background: rgba(30, 41, 59, 0.3);
            border: 1px solid var(--glass-border);
        }

        [data-theme="dark"] .option-card {
            background: rgba(30, 41, 59, 0.4);
            border: 2px solid var(--glass-border);
        }

        [data-theme="dark"] .warning-box {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .confirmation-container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            /* Keep compact header inline on mobile to match create.php */
            .page-header.compact .header-card {
                flex-direction: row;
                text-align: left;
                gap: 1rem;
            }

            .user-info-card .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .user-avatar {
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
        <?php if ($confirmationStep && $targetClassroom): ?>
            <!-- Confirmation Page -->
            <div class="page-header compact">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Delete Classroom</h1>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <div class="confirmation-container glass-card slide-up">
                <!-- Classroom Information -->
                <div class="user-info-card">
                    <div class="d-flex align-items-center">
                        <div class="user-avatar">
                            <?php
                            $name = $targetClassroom['room_number'];
                            $initials = '';
                            $nameParts = explode(' ', $name);
                            foreach (array_slice($nameParts, 0, 2) as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            echo $initials ?: strtoupper(substr($name, 0, 2));
                            ?>
                        </div>
                        <div class="user-details flex-grow-1">
                            <h4><?= htmlspecialchars($targetClassroom['room_number']) ?></h4>
                            <div class="user-meta">
                                <span class="badge badge-type-<?= strtolower($targetClassroom['type']) ?>">
                                    <?= ucfirst($targetClassroom['type']) ?>
                                </span>
                                <span class="badge badge-status-<?= strtolower($targetClassroom['status']) ?>">
                                    <?= ucfirst($targetClassroom['status']) ?>
                                </span>
                            </div>
                            <p class="user-meta"><strong>Building:</strong> <?= htmlspecialchars($targetClassroom['building']) ?></p>
                            <p class="user-meta"><strong>Capacity:</strong> <?= $targetClassroom['capacity'] ?> seats</p>
                            <?php if ($targetClassroom['department_name']): ?>
                                <p class="user-meta"><strong>Department:</strong> <?= htmlspecialchars($targetClassroom['department_name']) ?></p>
                            <?php endif; ?>
                            <p class="user-meta"><strong>Total Schedules:</strong> <?= $targetClassroom['total_schedules'] ?? 0 ?></p>
                            <p class="user-meta"><strong>Active Schedules:</strong> <?= $targetClassroom['active_schedules'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>

                <!-- Warning Section -->
                <div class="warning-box">
                    <div class="warning-icon">⚠️</div>
                    <h5>Warning: Classroom Deletion</h5>
                    <p>You are about to delete classroom <strong><?= htmlspecialchars($targetClassroom['room_number']) ?></strong>. 
                    Please consider the following:</p>
                    <ul>
                        <li><strong>Schedule Impact:</strong> This may affect <?= $targetClassroom['active_schedules'] ?? 0 ?> active schedule(s)</li>
                        <li><strong>Data Loss:</strong> Classroom history and associated data may be permanently lost</li>
                        <li><strong>Timetable References:</strong> All timetable entries using this classroom will be affected</li>
                        <li><strong>Irreversible:</strong> Permanent deletion cannot be undone</li>
                    </ul>
                </div>

                <!-- Deletion Options -->
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="classroom_id" value="<?= $targetClassroom['classroom_id'] ?>">
                    
                    <div class="deletion-options">
                        <h5>Choose Deletion Type:</h5>
                        
                        <div class="option-card" onclick="selectOption('deactivate')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="deactivate" checked>
                                <div>
                                    <strong>Deactivate Classroom (Recommended)</strong>
                                    <p class="mb-0 text-muted">Classroom will be deactivated but data preserved. Can be reactivated later if needed.</p>
                                </div>
                            </label>
                        </div>

                        <?php if (($targetClassroom['active_schedules'] ?? 0) == 0): ?>
                        <div class="option-card" onclick="selectOption('permanent')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="permanent">
                                <div>
                                    <strong style="color: var(--error-color);">Permanent Deletion</strong>
                                    <p class="mb-0 text-muted">Completely remove classroom and all associated data. This action cannot be undone.</p>
                                </div>
                            </label>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ Permanent deletion not available</strong><br>
                            This classroom has <?= $targetClassroom['active_schedules'] ?> active schedule(s). 
                            Please deactivate or move these schedules before attempting permanent deletion.
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
                        <h1 class="page-title">🗑️ Classroom Deletion</h1>
                        <p class="page-subtitle">Classroom deletion result</p>
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
                        <p>Redirecting to classroom management in 3 seconds...</p>
                        <a href="index.php" class="btn-action btn-primary">
                            📋 Return to Classrooms
                        </a>
                    </div>
                <?php elseif (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <strong>❌ Error!</strong><br>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <div class="text-center">
                        <a href="index.php" class="btn-action btn-primary">
                            📋 Return to Classrooms
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

    <!-- Scripts -->
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
            const classroomName = '<?= addslashes($targetClassroom['room_number'] ?? 'Unknown') ?>';
            
            let message;
            if (deleteType === 'permanent') {
                message = `Are you absolutely sure you want to PERMANENTLY DELETE classroom "${classroomName}"?\n\n` +
                         `This action will:\n` +
                         `• Completely remove the classroom from the system\n` +
                         `• Delete all associated data and history\n` +
                         `• Cannot be undone\n\n` +
                         `Type "DELETE" to confirm:`;
                         
                const confirmation = prompt(message);
                if (confirmation !== 'DELETE') {
                    alert('Deletion cancelled. You must type "DELETE" exactly to confirm permanent deletion.');
                    return false;
                }
            } else {
                message = `Are you sure you want to deactivate classroom "${classroomName}"?\n\n` +
                         `This action will:\n` +
                         `• Set the classroom as inactive\n` +
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
         * Initialize page interactions
         */
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Auto-redirect after success (if success message is shown)
            <?php if (!empty($success_message) && !$confirmationStep): ?>
            setTimeout(function() {
                if (!document.hidden) {
                    window.location.href = 'index.php';
                }
            }, 3000);
            <?php endif; ?>
        });
        
        /**
         * Handle keyboard navigation
         */
        document.addEventListener('keydown', function(e) {
            // ESC key to cancel
            if (e.key === 'Escape') {
                window.location.href = 'index.php';
            }
            
            // Enter key to submit (with confirmation)
            if (e.key === 'Enter' && e.ctrlKey) {
                document.getElementById('deleteForm')?.submit();
            }
        });
        
        /**
         * Prevent accidental navigation
         */
        <?php if ($confirmationStep): ?>
        window.addEventListener('beforeunload', function(e) {
            // Only show warning if user hasn't submitted the form
            if (!document.getElementById('deleteBtn').disabled) {
                e.preventDefault();
                e.returnValue = 'Are you sure you want to leave? Your deletion confirmation will be lost.';
                return e.returnValue;
            }
        });
        <?php endif; ?>
        
        /**
         * Dark mode handling for option cards
         */
        function updateOptionCardStyles() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const optionCards = document.querySelectorAll('.option-card');
            
            optionCards.forEach(card => {
                if (isDark) {
                    card.style.background = 'rgba(30, 41, 59, 0.4)';
                    card.style.borderColor = 'var(--glass-border)';
                } else {
                    card.style.background = 'rgba(255, 255, 255, 0.3)';
                    card.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                }
            });
        }
        
        // Watch for theme changes
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                    updateOptionCardStyles();
                }
            });
        });
        
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });
        
        // Initial style update
        updateOptionCardStyles();
    </script>
</body>
</html>