<?php
/**
 * Admin Users Delete - User Deletion Handler
 * Timetable Management System
 * 
 * Handles user deletion with proper validation and security checks
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

// Initialize variables
$error_message = '';
$success_message = '';
$targetUser = null;
$confirmationStep = false;

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle actual deletion
    handleDeletion();
} elseif (isset($_GET['id'])) {
    // Show confirmation page
    $targetUserId = (int)$_GET['id'];
    $targetUser = getUserDetails($targetUserId);
    if ($targetUser) {
        $confirmationStep = true;
    } else {
        $error_message = "User not found.";
    }
} else {
    // Redirect to user management if no ID provided
    header('Location: index.php');
    exit;
}

/**
 * Handle user deletion
 */
function handleDeletion() {
    global $currentUserId, $userManager, $success_message, $error_message;
    
    try {
        $targetUserId = (int)$_POST['user_id'];
        $deleteType = $_POST['delete_type'] ?? 'deactivate';
        
        // Security checks
        if ($targetUserId === $currentUserId) {
            throw new Exception("You cannot delete your own account.");
        }
        
        $targetUser = getUserDetails($targetUserId);
        if (!$targetUser) {
            throw new Exception("User not found.");
        }
        
        // Prevent deletion of other admins
        if ($targetUser['role'] === 'admin') {
            throw new Exception("Admin accounts cannot be deleted for security reasons.");
        }
        
        if ($deleteType === 'permanent') {
            // Permanent deletion (only for rejected/inactive users)
            if (!in_array($targetUser['status'], ['rejected', 'inactive'])) {
                throw new Exception("Only inactive or rejected users can be permanently deleted.");
            }
            
            $result = $userManager->deleteUser($targetUserId, $currentUserId);
            $action = "permanently deleted";
        } else {
            // Soft deletion (deactivation)
            $result = $userManager->changeUserStatus($targetUserId, 'inactive', $currentUserId);
            $action = "deactivated";
        }
        
        if ($result['success']) {
            $success_message = "User has been {$action} successfully.";
        } else {
            $error_message = ($result['message'] ?? 'User deletion failed.');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

/**
 * Get user details for confirmation
 */
function getUserDetails($userId) {
    global $db;
    
    try {
        return $db->fetchRow("
            SELECT u.*, 
                   CASE 
                       WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                       WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                       WHEN u.role = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                       ELSE u.username
                   END as full_name,
                   CASE 
                       WHEN u.role = 'student' THEN s.department
                       WHEN u.role = 'faculty' THEN f.department
                       WHEN u.role = 'admin' THEN a.department
                       ELSE NULL
                   END as department,
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
                   END as phone
            FROM users u
            LEFT JOIN students s ON u.user_id = s.user_id
            LEFT JOIN faculty f ON u.user_id = f.user_id
            LEFT JOIN admin_profiles a ON u.user_id = a.user_id
            WHERE u.user_id = ?
        ", [$userId]);
    } catch (Exception $e) {
        return null;
    }
}

// Set page title
$pageTitle = $confirmationStep ? "Delete User - Confirmation" : "Delete User";
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

        /* User Info Card */
        .user-info-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .user-info-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .user-info-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .user-avatar {
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

        .user-details h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .user-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .user-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-badge.inactive {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .status-badge.rejected {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
        }

        /* Role Badge */
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-badge.admin {
            background: rgba(147, 51, 234, 0.1);
            color: #9333ea;
        }

        .role-badge.faculty {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        .role-badge.student {
            background: rgba(236, 72, 153, 0.1);
            color: #ec4899;
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
        <?php if ($confirmationStep && $targetUser): ?>
            <!-- Confirmation Page -->
            <div class="page-header">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Delete User</h1>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <div class="confirmation-container glass-card slide-up">
                <!-- User Information -->
                <div class="user-info-card">
                    <div class="d-flex align-items-center">
                        <div class="user-avatar">
                            <?php 
                            $name = $targetUser['full_name'] ?? $targetUser['username'];
                            $initials = '';
                            $nameParts = explode(' ', $name);
                            foreach (array_slice($nameParts, 0, 2) as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            echo $initials ?: strtoupper(substr($targetUser['username'], 0, 2));
                            ?>
                        </div>
                        
                        <div class="user-details flex-grow-1">
                            <h3><?= htmlspecialchars($targetUser['full_name'] ?? $targetUser['username']) ?></h3>
                            
                            <div class="user-meta">
                                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($targetUser['email']) ?></span>
                                <span><i class="fas fa-user-tag"></i> 
                                    <span class="role-badge <?= $targetUser['role'] ?>"><?= ucfirst($targetUser['role']) ?></span>
                                </span>
                                <span><i class="fas fa-circle"></i> 
                                    <span class="status-badge <?= $targetUser['status'] ?>"><?= ucfirst($targetUser['status']) ?></span>
                                </span>
                                <?php if ($targetUser['identifier']): ?>
                                    <span><i class="fas fa-id-badge"></i> <?= htmlspecialchars($targetUser['identifier']) ?></span>
                                <?php endif; ?>
                                <?php if ($targetUser['department']): ?>
                                    <span><i class="fas fa-building"></i> <?= htmlspecialchars($targetUser['department']) ?></span>
                                <?php endif; ?>
                                <?php if ($targetUser['phone']): ?>
                                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($targetUser['phone']) ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-calendar"></i> Created <?= date('M j, Y', strtotime($targetUser['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warning -->
                <div class="warning-card">
                    <h5><i class="fas fa-exclamation-triangle"></i> Warning: You are about to delete user 
                        <strong><?= htmlspecialchars($targetUser['full_name'] ?? $targetUser['username']) ?></strong>. 
                    Please consider the following:</h5>
                    <ul>
                        <li><strong>Data Impact:</strong> User's data and history may be affected</li>
                        <li><strong>System References:</strong> This may affect timetables, enrollments, and assignments</li>
                        <li><strong>Reversibility:</strong> Permanent deletion cannot be undone</li>
                        <li><strong>Alternative:</strong> Consider deactivation instead of permanent deletion</li>
                    </ul>
                </div>

                <!-- Deletion Options -->
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="user_id" value="<?= $targetUser['user_id'] ?>">
                    <?php
                        // Determine return URL to preserve filters on redirect
                        $defaultReturn = 'index.php';
                        $returnParam = isset($_GET['return']) ? $_GET['return'] : ($_SERVER['HTTP_REFERER'] ?? $defaultReturn);
                        // Basic safety: only allow same directory targets
                        $safeReturn = (strpos($returnParam, 'delete.php') === false) ? $returnParam : $defaultReturn;
                    ?>
                    <input type="hidden" name="return" value="<?= htmlspecialchars($safeReturn) ?>">
                    
                    <div class="deletion-options">
                        <h5>Choose Deletion Type:</h5>
                        
                        <div class="option-card selected" onclick="selectOption('deactivate')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="deactivate" checked>
                                <div>
                                    <strong><i class="fas fa-pause-circle"></i> Deactivate Account (Recommended)</strong>
                                    <p class="mb-0">User account will be deactivated but data preserved. Can be reactivated later if needed. This is the safest option.</p>
                                </div>
                            </label>
                        </div>

                        <?php if (in_array($targetUser['status'], ['rejected', 'inactive'])): ?>
                        <div class="option-card" onclick="selectOption('permanent')">
                            <label class="d-flex align-items-start cursor-pointer">
                                <input type="radio" name="delete_type" value="permanent">
                                <div>
                                    <strong style="color: var(--error-color);"><i class="fas fa-trash"></i> Permanent Deletion</strong>
                                    <p class="mb-0">Completely remove user and all associated data. This action cannot be undone and may affect system integrity.</p>
                                </div>
                            </label>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <strong>Permanent deletion not available</strong><br>
                            Only inactive or rejected users can be permanently deleted. Current status: <strong><?= ucfirst($targetUser['status']) ?></strong>
                        </div>
                        <?php endif; ?>
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
            <!-- Result Page -->
            <div class="page-header">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <?php if (!empty($success_message)): ?>
                            <h1 class="page-title">✅ Delete User - Success</h1>
                            <p class="page-subtitle">User deletion completed successfully</p>
                        <?php else: ?>
                            <h1 class="page-title">❌ Delete User - Error</h1>
                            <p class="page-subtitle">Unable to process user deletion request</p>
                        <?php endif; ?>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>


            <?php if (!empty($error_message)): ?>
            <!-- Error Card -->
            <div class="confirmation-container glass-card slide-up">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-circle" style="font-size: 4rem; color: var(--error-color);"></i>
                    </div>
                    <h3 class="mb-3">Unable to Delete User</h3>
                    <p class="text-muted mb-4">
                        <?= htmlspecialchars($error_message) ?>
                    </p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="index.php" class="btn-action btn-primary">
                            👥 Return to Users
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
                    <p class="text-muted mb-4"><small id="redirectMessage">Redirecting to Users in <span id="countdown">3</span> seconds...</small></p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="index.php" class="btn-action btn-primary">
                            👥 Return to Users
                        </a>
                    </div>
                </div>
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

            // Auto-redirect functionality is now handled inline in the success section
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
            const userName = '<?= addslashes($targetUser['full_name'] ?? $targetUser['username']) ?>';
            
            let message;
            if (deleteType === 'permanent') {
                message = `⚠️ PERMANENT DELETION CONFIRMATION\n\n` +
                         `You are about to PERMANENTLY DELETE user "${userName}"\n\n` +
                         `This action will:\n` +
                         `• Completely remove the user from the system\n` +
                         `• Delete all associated data and history\n` +
                         `• Remove any timetable assignments\n` +
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
                message = `Deactivate user "${userName}"?\n\n` +
                         `This will:\n` +
                         `• Set the user account to inactive status\n` +
                         `• Prevent login but preserve all data\n` +
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