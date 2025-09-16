<?php
/**
 * Admin Notifications Delete - Notification Deletion Handler
 * Timetable Management System
 * 
 * Handles notification deletion with proper validation and security checks
 * Follows the same design and functionality style as user delete.php
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Notification.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$notificationManager = new Notification();

// Initialize variables
$error_message = '';
$success_message = '';
$targetNotification = null;
$confirmationStep = false;

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle actual deletion
    handleDeletion();
} elseif (isset($_GET['id'])) {
    // Show confirmation page
    $targetNotificationId = (int)$_GET['id'];
    $targetNotification = getNotificationDetails($targetNotificationId);
    if ($targetNotification) {
        $confirmationStep = true;
    } else {
        $error_message = "Notification not found.";
    }
} else {
    // Redirect to notification management if no ID provided
    header('Location: index.php');
    exit;
}

/**
 * Handle notification deletion
 */
function handleDeletion() {
    global $currentUserId, $notificationManager, $success_message, $error_message;
    
    try {
        $targetNotificationId = (int)$_POST['notification_id'];
        $deleteType = $_POST['delete_type'] ?? 'deactivate';
        
        $targetNotification = getNotificationDetails($targetNotificationId);
        if (!$targetNotification) {
            throw new Exception("Notification not found.");
        }
        
        // Check if notification is already inactive
        if ($targetNotification['is_active'] == 0 && $deleteType === 'deactivate') {
            throw new Exception("Notification is already inactive.");
        }
        
        if ($deleteType === 'permanent') {
            // Permanent deletion (only for inactive notifications)
            if ($targetNotification['is_active'] == 1) {
                throw new Exception("Only inactive notifications can be permanently deleted. Please deactivate first.");
            }
            
            // For permanent deletion, we'll actually delete from database
            $result = $notificationManager->deleteNotification($targetNotificationId, $currentUserId);
            $action = "permanently deleted";
        } else {
            // Soft deletion (deactivation)
            $result = $notificationManager->updateNotification(
                $targetNotificationId, 
                ['is_active' => 0], 
                $currentUserId
            );
            $action = "deactivated";
        }
        
        if ($result['success']) {
            $success_message = "Notification has been {$action} successfully.";
            // Redirect after short delay
            header("refresh:2;url=index.php");
        } else {
            $error_message = $result['message'];
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Get notification details for confirmation
 */
function getNotificationDetails($notificationId) {
    global $notificationManager;
    
    try {
        return $notificationManager->getNotificationById($notificationId);
    } catch (Exception $e) {
        return null;
    }
}

// Set page title
$pageTitle = $confirmationStep ? "Delete Notification" : "Notification Deletion Result";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?> - Admin Panel</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #6366f1;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --info-color: #3b82f6;
            --navbar-height: 64px;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --shadow-md: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --border-color: #e2e8f0;
        }

        [data-theme="dark"] {
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --border-color: #475569;
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

        /* Confirmation container */
        .confirmation-container {
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Notification Info Card */
        .notification-info-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .notification-info-card {
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .notification-info-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .notification-icon {
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

        .notification-icon.info { background: var(--info-color); }
        .notification-icon.success { background: var(--success-color); }
        .notification-icon.warning { background: var(--warning-color); }
        .notification-icon.error { background: var(--error-color); }
        .notification-icon.urgent { background: #dc2626; }

        .notification-details h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .notification-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .notification-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .notification-message {
            background: rgba(255, 255, 255, 0.05);
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 8px;
            font-style: italic;
            color: var(--text-secondary);
        }

        [data-theme="dark"] .notification-message {
            background: var(--bg-secondary);
        }

        [data-theme="light"] .notification-message {
            background: #f8fafc;
        }

        /* Warning section */
        .warning-section {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: var(--warning-color);
        }

        .warning-section h4 {
            color: var(--warning-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .warning-section ul {
            margin-bottom: 0;
        }

        /* Deletion options */
        .deletion-options {
            margin-bottom: 2rem;
        }

        .deletion-options h5 {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
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
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .option-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
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
            margin-right: 1rem;
            margin-top: 0.25rem;
        }

        .option-card label {
            cursor: pointer;
            margin-bottom: 0;
        }

        .option-card strong {
            color: var(--text-primary);
        }

        .option-card .text-muted {
            color: var(--text-secondary) !important;
            font-size: 0.875rem;
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

            .notification-info-card .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .notification-icon {
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
        <?php if ($confirmationStep && $targetNotification): ?>
            <!-- Confirmation Page -->
            <div class="page-header compact">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Delete Notification</h1>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <div class="confirmation-container glass-card slide-up">
                <!-- Notification Information -->
                <div class="notification-info-card">
                    <div class="d-flex align-items-start gap-3">
                        <div class="notification-icon <?= strtolower($targetNotification['type']) ?>">
                            <?php
                            $icons = [
                                'info' => 'fas fa-info',
                                'success' => 'fas fa-check',
                                'warning' => 'fas fa-exclamation-triangle',
                                'error' => 'fas fa-times',
                                'urgent' => 'fas fa-exclamation'
                            ];
                            ?>
                            <i class="<?= $icons[$targetNotification['type']] ?? 'fas fa-bell' ?>"></i>
                        </div>
                        <div class="notification-details flex-grow-1">
                            <h3><?= htmlspecialchars($targetNotification['title']) ?></h3>
                            <div class="notification-meta">
                                <span><i class="fas fa-tag"></i> <?= ucfirst($targetNotification['type']) ?></span>
                                <span><i class="fas fa-flag"></i> <?= ucfirst($targetNotification['priority']) ?></span>
                                <span><i class="fas fa-users"></i> <?= ucfirst($targetNotification['target_role']) ?></span>
                                <span><i class="fas fa-clock"></i> Created <?= date('M j, Y g:i A', strtotime($targetNotification['created_at'])) ?></span>
                                <span><i class="fas fa-user"></i> By <?= htmlspecialchars($targetNotification['creator_full_name'] ?? $targetNotification['creator_name']) ?></span>
                                <span><i class="fas fa-circle <?= $targetNotification['is_active'] ? 'text-success' : 'text-danger' ?>"></i> <?= $targetNotification['is_active'] ? 'Active' : 'Inactive' ?></span>
                            </div>
                            <div class="notification-message">
                                <strong>Message:</strong> "<?= htmlspecialchars($targetNotification['message']) ?>"
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning Section -->
                <div class="warning-section">
                    <h4>⚠️ Warning</h4>
                    <p>You are about to delete the notification <strong>"<?= htmlspecialchars($targetNotification['title']) ?>"</strong>. 
                    Please consider the following:</p>
                    <ul>
                        <li><strong>User Impact:</strong> Users will no longer see this notification</li>
                        <li><strong>History Loss:</strong> Notification history may be permanently lost</li>
                        <li><strong>System References:</strong> Any logs or audit trails may be affected</li>
                        <li><strong>Irreversible:</strong> Permanent deletion cannot be undone</li>
                    </ul>
                </div>

                <!-- Deletion Options -->
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="notification_id" value="<?= $targetNotification['notification_id'] ?>">
                    
                    <div class="deletion-options">
                        <h5>Choose Deletion Type:</h5>
                        
                        <div class="option-card selected" onclick="selectOption('deactivate')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="deactivate" checked>
                                <div>
                                    <strong>Deactivate Notification (Recommended)</strong>
                                    <p class="mb-0 text-muted">Notification will be deactivated but data preserved. Can be reactivated later if needed.</p>
                                </div>
                            </label>
                        </div>

                        <?php if ($targetNotification['is_active'] == 0): ?>
                        <div class="option-card" onclick="selectOption('permanent')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="permanent">
                                <div>
                                    <strong style="color: var(--error-color);">Permanent Deletion</strong>
                                    <p class="mb-0 text-muted">Completely remove notification and all associated data. This action cannot be undone.</p>
                                </div>
                            </label>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ Permanent deletion not available</strong><br>
                            Only inactive notifications can be permanently deleted. 
                            Current notification status: <strong><?= $targetNotification['is_active'] ? 'Active' : 'Inactive' ?></strong>
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
                        <h1 class="page-title">🗑️ Notification Deletion</h1>
                        <p class="page-subtitle">Notification deletion result</p>
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
                        <p>Redirecting to notification management in 3 seconds...</p>
                        <a href="index.php" class="btn-action btn-primary">
                            🔔 Return to Notifications
                        </a>
                    </div>
                <?php elseif (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <strong>❌ Error!</strong><br>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <div class="text-center">
                        <a href="index.php" class="btn-action btn-primary">
                            🔔 Return to Notifications
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
            const notificationTitle = '<?= addslashes($targetNotification['title'] ?? '') ?>';
            
            let message;
            if (deleteType === 'permanent') {
                message = `Are you absolutely sure you want to PERMANENTLY DELETE notification "${notificationTitle}"?\n\n` +
                         `This action will:\n` +
                         `• Completely remove the notification from the system\n` +
                         `• Delete all associated data and history\n` +
                         `• Cannot be undone\n\n` +
                         `Type "DELETE" to confirm:`;
                         
                const confirmation = prompt(message);
                if (confirmation !== 'DELETE') {
                    alert('Deletion cancelled. You must type "DELETE" exactly to confirm permanent deletion.');
                    return false;
                }
            } else {
                message = `Are you sure you want to deactivate notification "${notificationTitle}"?\n\n` +
                         `This action will:\n` +
                         `• Set the notification as inactive\n` +
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
        });
    </script>
</body>
</html>