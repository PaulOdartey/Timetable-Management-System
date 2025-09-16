<?php
/**
 * Admin Notifications Edit - Notification Edit Interface
 * Timetable Management System
 * 
 * Professional interface for admin to edit existing notifications
 * with type, priority, targeting, and expiration management
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

// Get notification ID to edit
$editNotificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$editNotificationId) {
    header('Location: index.php?error=' . urlencode('Notification ID is required'));
    exit;
}

// Initialize variables
$notification = null;
$error_message = '';
$success_message = '';
$formData = [];
$targetUsers = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error
        $formData = $_POST;
        
        // Basic validation
        $required_fields = ['title', 'message', 'type', 'priority'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Validate message length
        if (strlen($_POST['message']) > 1000) {
            throw new Exception('Message cannot exceed 1000 characters.');
        }
        
        // Validate title length
        if (strlen($_POST['title']) > 100) {
            throw new Exception('Title cannot exceed 100 characters.');
        }
        
        // Validate expiration date if provided
        if (!empty($_POST['expires_at'])) {
            $expirationDate = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['expires_at']);
            if (!$expirationDate || $expirationDate <= new DateTime()) {
                throw new Exception('Expiration date must be in the future.');
            }
        }
        
        // Prepare update data
        $updateData = [
            'title' => trim($_POST['title']),
            'message' => trim($_POST['message']),
            'type' => $_POST['type'],
            'priority' => $_POST['priority'],
            'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Update notification
        $result = $notificationManager->updateNotification($editNotificationId, $updateData, $currentUserId);
        
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
    // Get notification details
    $notification = $notificationManager->getNotificationById($editNotificationId);
    
    if (!$notification) {
        header('Location: index.php?error=' . urlencode('Notification not found'));
        exit;
    }
    
    // Get list of users for target selection
    $targetUsers = $db->fetchAll("
        SELECT u.user_id, u.username, u.role,
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
               END as department
        FROM users u
        LEFT JOIN students s ON u.user_id = s.user_id
        LEFT JOIN faculty f ON u.user_id = f.user_id
        LEFT JOIN admin_profiles a ON u.user_id = a.user_id
        WHERE u.status = 'active'
        ORDER BY u.role, full_name
    ");
    
} catch (Exception $e) {
    error_log("Edit Notification Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the notification.";
}

// Set page title
$pageTitle = "Edit Notification";
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
            color: var(--text-primary);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
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
        .notification-info-header {
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

        [data-theme="dark"] .notification-info-header {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .notification-info-header {
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

        .notification-details h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .notification-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .notification-meta span {
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
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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

        /* Priority Selection */
        .priority-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 1rem;
        }

        .priority-card {
            position: relative;
            padding: 0.75rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
        }

        [data-theme="dark"] .priority-card {
            border-color: var(--border-color);
            background: var(--bg-tertiary);
        }

        [data-theme="light"] .priority-card {
            border-color: var(--border-color);
            background: var(--bg-secondary);
        }

        .priority-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .priority-card input[type="radio"] {
            display: none;
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

            .notification-info-header {
                flex-direction: column;
                text-align: center;
            }

            .notification-meta {
                justify-content: center;
            }

            .form-container {
                padding: 1rem;
            }

            .type-selection,
            .priority-selection {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .type-selection,
            .priority-selection {
                grid-template-columns: 1fr;
            }
        }

        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body data-theme="light">
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
                    <h1 class="page-title">📝 Edit Notification</h1>
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

        <!-- Notification Info Header -->
        <?php if ($notification): ?>
        <div class="notification-info-header">
            <div class="notification-icon <?= strtolower($notification['type']) ?>">
                <?php
                $icons = [
                    'info' => 'fas fa-info',
                    'success' => 'fas fa-check',
                    'warning' => 'fas fa-exclamation-triangle',
                    'error' => 'fas fa-times',
                    'urgent' => 'fas fa-exclamation'
                ];
                ?>
                <i class="<?= $icons[$notification['type']] ?? 'fas fa-bell' ?>"></i>
            </div>
            <div class="notification-details flex-grow-1">
                <h2><?= htmlspecialchars($notification['title']) ?></h2>
                <div class="notification-meta">
                    <span><i class="fas fa-tag"></i> <?= ucfirst($notification['type']) ?></span>
                    <span><i class="fas fa-flag"></i> <?= ucfirst($notification['priority']) ?></span>
                    <span><i class="fas fa-users"></i> <?= ucfirst($notification['target_role']) ?></span>
                    <span><i class="fas fa-clock"></i> Created <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?></span>
                    <span><i class="fas fa-user"></i> By <?= htmlspecialchars($notification['creator_full_name'] ?? $notification['creator_name']) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="form-container">
            <form method="POST" action="" id="editNotificationForm">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="form-section-title">📋 Basic Information</h3>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Notification Title *</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?= htmlspecialchars($formData['title'] ?? $notification['title'] ?? '') ?>" 
                               placeholder="Enter notification title" maxlength="100" required>
                        <div class="character-counter" id="titleCounter">0 / 100</div>
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Message Content *</label>
                        <textarea class="form-control" id="message" name="message" rows="5" 
                                  placeholder="Enter the notification message..." maxlength="1000" required><?= htmlspecialchars($formData['message'] ?? $notification['message'] ?? '') ?></textarea>
                        <div class="character-counter" id="messageCounter">0 / 1000</div>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> 
                            Use clear, concise language. This message will be displayed to users.
                        </div>
                    </div>
                </div>

                <!-- Type Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">🎯 Notification Type</h3>
                    
                    <div class="type-selection">
                        <?php
                        $types = [
                            'info' => ['icon' => 'fas fa-info-circle', 'name' => 'Info'],
                            'success' => ['icon' => 'fas fa-check-circle', 'name' => 'Success'],
                            'warning' => ['icon' => 'fas fa-exclamation-triangle', 'name' => 'Warning'],
                            'error' => ['icon' => 'fas fa-times-circle', 'name' => 'Error'],
                            'urgent' => ['icon' => 'fas fa-exclamation', 'name' => 'Urgent']
                        ];
                        
                        $selectedType = $formData['type'] ?? $notification['type'] ?? 'info';
                        ?>
                        
                        <?php foreach ($types as $value => $type): ?>
                        <div class="type-card <?= $selectedType === $value ? 'selected' : '' ?>" 
                             onclick="selectType('<?= $value ?>')">
                            <input type="radio" name="type" value="<?= $value ?>" 
                                   <?= $selectedType === $value ? 'checked' : '' ?> id="type_<?= $value ?>">
                            <div class="type-icon">
                                <i class="<?= $type['icon'] ?>"></i>
                            </div>
                            <div class="type-name"><?= $type['name'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Priority Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">⚡ Priority Level</h3>
                    
                    <div class="priority-selection">
                        <?php
                        $priorities = [
                            'low' => 'Low',
                            'normal' => 'Normal',
                            'high' => 'High',
                            'urgent' => 'Urgent'
                        ];
                        
                        $selectedPriority = $formData['priority'] ?? $notification['priority'] ?? 'normal';
                        ?>
                        
                        <?php foreach ($priorities as $value => $label): ?>
                        <div class="priority-card <?= $selectedPriority === $value ? 'selected' : '' ?>" 
                             onclick="selectPriority('<?= $value ?>')">
                            <input type="radio" name="priority" value="<?= $value ?>" 
                                   <?= $selectedPriority === $value ? 'checked' : '' ?> id="priority_<?= $value ?>">
                            <div><?= $label ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Settings -->
                <div class="form-section">
                    <h3 class="form-section-title">⚙️ Settings</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="expires_at" class="form-label">Expiration Date & Time</label>
                            <input type="datetime-local" class="form-control" id="expires_at" name="expires_at" 
                                   value="<?= !empty($formData['expires_at']) ? $formData['expires_at'] : (!empty($notification['expires_at']) ? date('Y-m-d\TH:i', strtotime($notification['expires_at'])) : '') ?>">
                            <div class="form-text">
                                <i class="fas fa-clock"></i> 
                                Leave empty for permanent notification
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3 d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       <?= ($formData['is_active'] ?? $notification['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    <strong>Active Notification</strong>
                                </label>
                                <div class="form-text mt-1">
                                    <i class="fas fa-toggle-on"></i> 
                                    Uncheck to disable this notification
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Targeting Info -->
                <div class="form-section">
                    <h3 class="form-section-title">👥 Current Targeting</h3>
                    
                    <div class="alert" style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.3); color: var(--primary-color);">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Current Target:</strong> 
                        <?php if ($notification['target_role'] === 'all'): ?>
                            All Users
                        <?php elseif ($notification['target_user_id']): ?>
                            Specific User (ID: <?= $notification['target_user_id'] ?>)
                        <?php else: ?>
                            <?= ucfirst($notification['target_role']) ?> Users
                        <?php endif; ?>
                        
                        <div class="mt-2" style="font-size: 0.875rem; opacity: 0.8;">
                            <i class="fas fa-lock"></i> 
                            Targeting cannot be changed when editing. Create a new notification to change targeting.
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
                        <button type="button" class="btn-action btn-warning" onclick="previewNotification()">
                            👁️ Preview
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn">
                            ✅ Update Notification
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
                        <i class="fas fa-eye"></i> Notification Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="notification-preview" id="notificationPreview">
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
            
            // Initialize character counters
            initializeCharacterCounters();
            
            // Set initial type and priority selections
            updateTypeSelection();
            updatePrioritySelection();
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
            const form = document.getElementById('editNotificationForm');
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
            const title = document.getElementById('title').value.trim();
            const message = document.getElementById('message').value.trim();
            const expiresAt = document.getElementById('expires_at').value;
            
            // Check required fields
            if (!title) {
                showError('Please enter a notification title.');
                return false;
            }
            
            if (!message) {
                showError('Please enter a notification message.');
                return false;
            }
            
            // Check length limits
            if (title.length > 100) {
                showError('Title cannot exceed 100 characters.');
                return false;
            }
            
            if (message.length > 1000) {
                showError('Message cannot exceed 1000 characters.');
                return false;
            }
            
            // Validate expiration date
            if (expiresAt) {
                const expirationDate = new Date(expiresAt);
                const now = new Date();
                
                if (expirationDate <= now) {
                    showError('Expiration date must be in the future.');
                    return false;
                }
            }
            
            return true;
        }

        /**
         * Initialize character counters
         */
        function initializeCharacterCounters() {
            const titleInput = document.getElementById('title');
            const messageInput = document.getElementById('message');
            const titleCounter = document.getElementById('titleCounter');
            const messageCounter = document.getElementById('messageCounter');
            
            function updateCounter(input, counter, maxLength) {
                const currentLength = input.value.length;
                counter.textContent = `${currentLength} / ${maxLength}`;
                
                // Update counter color based on usage
                counter.classList.remove('near-limit', 'at-limit');
                if (currentLength >= maxLength) {
                    counter.classList.add('at-limit');
                } else if (currentLength >= maxLength * 0.9) {
                    counter.classList.add('near-limit');
                }
            }
            
            // Initial count
            updateCounter(titleInput, titleCounter, 100);
            updateCounter(messageInput, messageCounter, 1000);
            
            // Event listeners
            titleInput.addEventListener('input', () => updateCounter(titleInput, titleCounter, 100));
            messageInput.addEventListener('input', () => updateCounter(messageInput, messageCounter, 1000));
        }

        /**
         * Select notification type
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
         * Select priority level
         */
        function selectPriority(priority) {
            // Remove selected class from all cards
            document.querySelectorAll('.priority-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            document.querySelector(`#priority_${priority}`).closest('.priority-card').classList.add('selected');
            
            // Check the radio button
            document.querySelector(`#priority_${priority}`).checked = true;
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
         * Update priority selection visual state
         */
        function updatePrioritySelection() {
            const selectedPriority = document.querySelector('input[name="priority"]:checked');
            if (selectedPriority) {
                selectPriority(selectedPriority.value);
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
         * Preview notification
         */
        function previewNotification() {
            const title = document.getElementById('title').value.trim();
            const message = document.getElementById('message').value.trim();
            const type = document.querySelector('input[name="type"]:checked').value;
            const priority = document.querySelector('input[name="priority"]:checked').value;
            const expiresAt = document.getElementById('expires_at').value;
            const isActive = document.getElementById('is_active').checked;
            
            if (!title || !message) {
                showError('Please fill in title and message to preview.');
                return;
            }
            
            // Create preview HTML
            const previewHtml = `
                <div class="notification-preview-item" style="
                    border: 2px solid var(--border-color);
                    border-radius: 12px;
                    padding: 1rem;
                    background: var(--bg-tertiary);
                    position: relative;
                ">
                    <div class="d-flex align-items-start gap-3">
                        <div class="notification-icon ${type}" style="
                            width: 40px;
                            height: 40px;
                            border-radius: 8px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: white;
                            flex-shrink: 0;
                        ">
                            ${getTypeIcon(type)}
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0" style="color: var(--text-primary); font-weight: 600;">
                                    ${escapeHtml(title)}
                                </h6>
                                <div class="d-flex gap-2">
                                    <span class="badge" style="background: var(--${getPriorityColor(priority)}); font-size: 0.7rem;">
                                        ${priority.toUpperCase()}
                                    </span>
                                    ${!isActive ? '<span class="badge" style="background: var(--error-color); font-size: 0.7rem;">INACTIVE</span>' : ''}
                                </div>
                            </div>
                            <p class="mb-2" style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem;">
                                ${escapeHtml(message).replace(/\n/g, '<br>')}
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small style="color: var(--text-secondary);">
                                    <i class="fas fa-clock"></i> Preview - ${new Date().toLocaleString()}
                                </small>
                                ${expiresAt ? `<small style="color: var(--warning-color);"><i class="fas fa-hourglass-half"></i> Expires: ${new Date(expiresAt).toLocaleString()}</small>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('notificationPreview').innerHTML = previewHtml;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        /**
         * Get icon for notification type
         */
        function getTypeIcon(type) {
            const icons = {
                'info': '<i class="fas fa-info"></i>',
                'success': '<i class="fas fa-check"></i>',
                'warning': '<i class="fas fa-exclamation-triangle"></i>',
                'error': '<i class="fas fa-times"></i>',
                'urgent': '<i class="fas fa-exclamation"></i>'
            };
            return icons[type] || '<i class="fas fa-bell"></i>';
        }

        /**
         * Get color for priority level
         */
        function getPriorityColor(priority) {
            const colors = {
                'low': 'info-color',
                'normal': 'primary-color',
                'high': 'warning-color',
                'urgent': 'error-color'
            };
            return colors[priority] || 'primary-color';
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
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </div>
            `;
            
            // Insert at top of main content
            const mainContent = document.querySelector('.main-content');
            mainContent.insertAdjacentHTML('afterbegin', alertHtml);
            
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
        window.selectPriority = selectPriority;
        window.resetForm = resetForm;
        window.previewNotification = previewNotification;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>