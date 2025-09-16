<?php
/**
 * Admin Settings Dashboard - System Configuration Management
 * Timetable Management System
 * 
 * Professional enterprise-grade settings management interface with:
 * - Categorized settings organization
 * - System health monitoring
 * - Backup management
 * - Audit log tracking
 * - Maintenance controls
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Settings.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$settingsManager = new Settings();

// Get current admin profile for UI
$currentUserProfile = [];
try {
    $currentUserProfile = $db->fetchRow(
        "SELECT u.role, COALESCE(a.first_name, u.username) AS first_name, a.last_name 
         FROM users u 
         LEFT JOIN admin_profiles a ON a.user_id = u.user_id 
         WHERE u.user_id = ?",
        [$currentUserId]
    ) ?: [];
} catch (Exception $e) {
    // Non-fatal if profile missing
}

// Initialize variables
$error_message = '';
$success_message = '';
$activeCategory = $_GET['category'] ?? 'general';
$currentView = $_GET['view'] ?? 'settings';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_settings':
                // Prefer nested settings array if present (names like settings[foo])
                if (isset($_POST['settings']) && is_array($_POST['settings'])) {
                    $settings = $_POST['settings'];
                } else {
                    // Fallback: flatten all POST fields except action
                    $settings = [];
                    foreach ($_POST as $key => $value) {
                        if ($key === 'action') continue;
                        $settings[$key] = $value;
                    }
                }

                // Clean up boolean checkbox handling - ensure proper values
                $settingsByCategory = $settingsManager->getAllSettingsByCategory();
                foreach ($settingsByCategory as $categoryData) {
                    foreach ($categoryData['settings'] as $settingKey => $settingData) {
                        if ($settingData['schema']['type'] === 'boolean' && isset($settings[$settingKey])) {
                            // Normalize boolean values: '1', 1, true, 'true' -> '1', everything else -> '0'
                            $settings[$settingKey] = (
                                $settings[$settingKey] === '1' || 
                                $settings[$settingKey] === 1 || 
                                $settings[$settingKey] === true || 
                                $settings[$settingKey] === 'true'
                            ) ? '1' : '0';
                        }
                    }
                }

                if (!empty($settings)) {
                    $result = $settingsManager->updateMultipleSettings($settings, $currentUserId);

                    if ($result['success']) {
                        $success_message = $result['message'];
                    } else {
                        $error_message = $result['message'];
                    }
                } else {
                    $error_message = 'No settings to update.';
                }
                break;
                
            case 'create_manual_backup':
                $description = $_POST['backup_description'] ?? 'Manual backup';
                $result = $settingsManager->createManualBackup($currentUserId, $description);
                
                if ($result['success']) {
                    $success_message = $result['message'] . '. File: ' . $result['backup_file'];
                } else {
                    $error_message = $result['message'];
                }
                break;
                
            case 'delete_backup':
                $backupId = $_POST['backup_id'] ?? 0;
                if ($backupId) {
                    $result = $settingsManager->deleteBackup($backupId, $currentUserId);
                    
                    if ($result['success']) {
                        $success_message = $result['message'];
                    } else {
                        $error_message = $result['message'];
                    }
                } else {
                    $error_message = 'Invalid backup ID.';
                }
                break;
                
            case 'perform_maintenance':
                $tasks = $_POST['maintenance_tasks'] ?? [];
                if (!empty($tasks)) {
                    $result = $settingsManager->performMaintenance($tasks, $currentUserId);
                    
                    if ($result['success']) {
                        $completed = count($result['tasks_completed']);
                        $failed = count($result['tasks_failed']);
                        $success_message = "Maintenance completed: {$completed} tasks successful" . 
                                         ($failed > 0 ? ", {$failed} failed" : "");
                    } else {
                        $error_message = $result['message'];
                    }
                } else {
                    $error_message = 'No maintenance tasks selected.';
                }
                break;
                
            case 'export_settings':
                $categories = $_POST['export_categories'] ?? [];
                $exportData = $settingsManager->exportSettings($categories);
                
                if (!isset($exportData['error'])) {
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="system_settings_' . date('Y-m-d_H-i-s') . '.json"');
                    echo json_encode($exportData, JSON_PRETTY_PRINT);
                    exit;
                } else {
                    $error_message = $exportData['error'];
                }
                break;
                
            case 'import_settings':
                if (isset($_FILES['settings_file']) && $_FILES['settings_file']['error'] === UPLOAD_ERR_OK) {
                    $importData = json_decode(file_get_contents($_FILES['settings_file']['tmp_name']), true);
                    
                    if ($importData) {
                        $result = $settingsManager->importSettings($importData, $currentUserId);
                        
                        if ($result['success']) {
                            $success_message = $result['message'];
                        } else {
                            $error_message = $result['message'];
                        }
                    } else {
                        $error_message = 'Invalid settings file format.';
                    }
                } else {
                    $error_message = 'Please select a valid settings file.';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error_message = 'An error occurred: ' . $e->getMessage();
    }
}

try {
    // Get organized settings by category
    $settingsByCategory = $settingsManager->getAllSettingsByCategory();
    
    // Get system health status
    $systemHealth = $settingsManager->getSystemHealth();
    
    // Get backup status
    $backupStatus = $settingsManager->getBackupStatus();
    
    // Get backup history if viewing backups
    $backupHistory = [];
    if ($currentView === 'backups') {
        $backupHistory = $settingsManager->getBackupHistory(20);
    }
    
    // Get audit logs if viewing audit
    $auditLogs = [];
    if ($currentView === 'audit') {
        $filters = [
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
            'action' => $_GET['action'] ?? '',
            'user_id' => $_GET['user_id'] ?? ''
        ];
        $auditLogs = $settingsManager->getAuditLogs(50, 0, $filters);
    }
    
} catch (Exception $e) {
    error_log("Settings Dashboard Error: " . $e->getMessage());
    $error_message = "An error occurred while loading settings.";
    $settingsByCategory = [];
    $systemHealth = ['overall_status' => 'error'];
    $backupStatus = ['error' => 'Unable to load backup status'];
    $backupHistory = [];
    $auditLogs = [];
}

// Set page title
$pageTitle = "System Settings";
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
            --sidebar-collapsed-width: 70px;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.3);
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
            --glass-bg: rgba(30, 41, 59, 0.8);
            --glass-border: rgba(71, 85, 105, 0.3);
        }

        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-color: #cbd5e1;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(203, 213, 225, 0.3);
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

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            margin-top: 1rem;
        }

        .header-card {
            padding: 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
        }

        [data-theme="dark"] .header-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(30, 41, 59, 0.7) 100%);
            border-color: var(--border-color);
        }

        [data-theme="light"] .header-card {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            margin-bottom: 0;
        }

        }


        /* Glass Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
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

        /* Dark mode table styling */
        [data-theme="dark"] .table {
            --bs-table-bg: var(--bg-secondary);
            --bs-table-striped-bg: rgba(255, 255, 255, 0.05);
            --bs-table-hover-bg: rgba(255, 255, 255, 0.075);
            --bs-table-border-color: var(--border-color);
            color: var(--text-primary);
        }

        [data-theme="dark"] .table th,
        [data-theme="dark"] .table td {
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        [data-theme="dark"] .table thead th {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        /* Settings Layout */
        .settings-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            min-height: calc(100vh - 250px);
        }

            border-radius: 20px;
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: calc(var(--navbar-height) + 1rem);
        }

        [data-theme="dark"] .settings-sidebar {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .settings-sidebar {
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        .settings-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .settings-nav-item {
            margin-bottom: 0.5rem;
        }

        .settings-nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: var(--text-secondary);
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .settings-nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }

        .settings-nav-link.active {
            background: var(--primary-color);
            color: white;
        }

        .settings-nav-icon {
            width: 20px;
            text-align: center;
        }

        /* Settings Content */
        .settings-content {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            min-height: 600px;
        }

        [data-theme="dark"] .settings-content {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .settings-content {
            background: var(--bg-primary);
            border-color: var(--border-color);
        }

        /* System Health Dashboard */
        .system-health {
            margin-bottom: 2rem;
        }

        .health-status {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .health-status.healthy {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success-color);
        }

        .health-status.warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: var(--warning-color);
        }

        .health-status.critical {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--error-color);
        }

        .health-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .metric-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .metric-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        [data-theme="light"] .form-control,
        [data-theme="light"] .form-select {
            background: var(--bg-secondary);
            border-color: #d1d5db;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        .form-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .form-check {
            margin-bottom: 0.5rem;
        }

        .form-check-input {
            margin-top: 0.25rem;
        }

        .form-check-label {
            font-size: 0.875rem;
            color: var(--text-primary);
            margin-left: 0.5rem;
        }

        /* Button Styles */
        .btn-action {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
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

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
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

        /* Badge Styles */
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
        }

        .badge.bg-success {
            background-color: var(--success-color) !important;
        }

        .badge.bg-danger {
            background-color: var(--error-color) !important;
        }

        .badge.bg-warning {
            background-color: var(--warning-color) !important;
        }

        .badge.bg-info {
            background-color: var(--info-color) !important;
        }

        .badge.bg-secondary {
            background-color: #6c757d !important;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: calc(var(--navbar-height) + 1rem);
            }

            .settings-layout {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .settings-sidebar {
                position: static;
                height: auto;
                margin-bottom: 1rem;
            }

            .settings-nav {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 0.5rem;
            }

            .settings-nav-item {
                margin-bottom: 0;
            }

            .page-title {
                font-size: 2rem;
            }

            
            .sticky-stat {
                min-width: 45px;
            }
        }

        @media (max-width: 768px) {
            .header-card {
                padding: 1.5rem;
                text-align: center;
            }

            .health-metrics {
                grid-template-columns: 1fr;
            }

            .settings-nav {
                grid-template-columns: 1fr;
            }

            
            .sticky-stat-number {
                font-size: 1rem;
            }
            
            .sticky-stat-label {
                font-size: 0.65rem;
            }

            /* Mobile fixes for backup and audit sections */
            .table-responsive {
                font-size: 0.85rem;
            }

            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 120px;
            }

            /* Hide less important columns on mobile - different for audit vs backup */
            /* For audit logs: hide User, Resource, IP Address (keep Date, Action, View) */
            .audit-logs .table th:nth-child(2),
            .audit-logs .table td:nth-child(2),
            .audit-logs .table th:nth-child(4),
            .audit-logs .table td:nth-child(4),
            .audit-logs .table th:nth-child(5),
            .audit-logs .table td:nth-child(5) {
                display: none;
            }

            /* For backup management: hide Type, File Size, Created By (keep Date, Description, Status, Actions) */
            .backup-management .table th:nth-child(3),
            .backup-management .table td:nth-child(3),
            .backup-management .table th:nth-child(5),
            .backup-management .table td:nth-child(5),
            .backup-management .table th:nth-child(6),
            .backup-management .table td:nth-child(6) {
                display: none;
            }

            /* Stack backup form elements */
            .row.g-3 .col-md-8,
            .row.g-3 .col-md-4 {
                margin-bottom: 1rem;
            }

            /* Compact buttons */
            .btn-action {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }

            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }

            /* Better mobile table layout */
            .table-responsive table {
                min-width: 100%;
            }

            /* Compact badges */
            .badge {
                font-size: 0.65rem;
                padding: 0.2rem 0.4rem;
            }

            /* Mobile-friendly section headers */
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 1rem;
            }

            .d-flex.justify-content-between .btn-action {
                align-self: flex-start;
                width: fit-content;
            }
        }

        /* Extra small screens */
        @media (max-width: 480px) {
            .table th,
            .table td {
                font-size: 0.75rem;
                padding: 0.4rem 0.2rem;
                max-width: 80px;
            }

            /* Show only essential columns on very small screens */
            /* For audit logs: show only Date, Action, View */
            .audit-logs .table th:nth-child(2),
            .audit-logs .table td:nth-child(2),
            .audit-logs .table th:nth-child(4),
            .audit-logs .table td:nth-child(4),
            .audit-logs .table th:nth-child(5),
            .audit-logs .table td:nth-child(5) {
                display: none;
            }

            /* For backup management, hide less important columns but keep Status (column 4) visible */
            .backup-management .table th:nth-child(3),
            .backup-management .table td:nth-child(3),
            .backup-management .table th:nth-child(5),
            .backup-management .table td:nth-child(5),
            .backup-management .table th:nth-child(6),
            .backup-management .table td:nth-child(6) {
                display: none;
            }
            
            /* Adjust delete button positioning for mobile */
            .backup-management .table .btn-sm {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
                min-width: 44px;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-left: auto;
            }
            
            .backup-management .table td:last-child {
                text-align: right;
                padding-right: 0.5rem;
            }
            
            /* Style audit logs View column - push to right and make small */
            .audit-logs .table th:nth-child(6),
            .audit-logs .table td:nth-child(6) {
                text-align: right;
                padding-right: 0.5rem;
            }
            
            .audit-logs .table .btn-outline-primary {
                padding: 0.2rem 0.4rem;
                font-size: 0.7rem;
                min-width: 32px;
                min-height: 32px;
            }

            .glass-card {
                padding: 1rem !important;
                margin-bottom: 1rem !important;
            }

            .form-control,
            .form-select {
                font-size: 0.9rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .settings-content {
                padding: 1rem;
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
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">⚙ System Settings</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn-action btn-success" onclick="location.reload()">
                            🔄 Refresh Data
                        </button>
                    </div>
                </div>
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

        <!-- System Health Overview -->
        <div class="system-health glass-card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="margin-bottom: 1rem; color: var(--text-primary);">
                <i class="fas fa-heartbeat me-2"></i>System Health
            </h3>
            
            <?php if (isset($systemHealth['overall_status'])): ?>
            <div class="health-status <?= $systemHealth['overall_status'] ?>">
                <i class="fas fa-<?= $systemHealth['overall_status'] === 'healthy' ? 'check-circle' : ($systemHealth['overall_status'] === 'warning' ? 'exclamation-triangle' : 'times-circle') ?>"></i>
                <div>
                    <strong>System Status: <?= ucfirst($systemHealth['overall_status']) ?></strong>
                    <div style="font-size: 0.875rem; margin-top: 0.25rem;">
                        <?= $systemHealth['overall_status'] === 'healthy' ? 'All systems operational' : 'Some issues detected - check details below' ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($systemHealth['metrics'])): ?>
            <div class="health-metrics">
                <?php if (isset($systemHealth['metrics']['users'])): ?>
                <div class="metric-card">
                    <div class="metric-value"><?= $systemHealth['metrics']['users']['active_users'] ?? 0 ?></div>
                    <div class="metric-label">Active Users</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value"><?= $systemHealth['metrics']['users']['total_users'] ?? 0 ?></div>
                    <div class="metric-label">Total Users</div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($systemHealth['metrics']['performance'])): ?>
                <div class="metric-card">
                    <div class="metric-value"><?= $systemHealth['metrics']['performance']['database_size_mb'] ?? 0 ?>MB</div>
                    <div class="metric-label">Database Size</div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-value"><?= $systemHealth['metrics']['performance']['memory_usage'] ?? 'N/A' ?></div>
                    <div class="metric-label">Memory Usage</div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Settings Layout -->
        <div class="settings-layout">
            <!-- Settings Navigation -->
            <div class="settings-sidebar">
                <h4 style="margin-bottom: 1rem; color: var(--text-primary);">Configuration</h4>
                <ul class="settings-nav">
                    <?php foreach ($settingsByCategory as $categoryKey => $categoryData): ?>
                    <li class="settings-nav-item">
                        <a href="?category=<?= $categoryKey ?>" 
                           class="settings-nav-link <?= ($activeCategory === $categoryKey && $currentView === 'settings') ? 'active' : '' ?>">
                            <span class="settings-nav-icon">
                                <i class="<?= $categoryData['icon'] ?>"></i>
                            </span>
                            <?= $categoryData['title'] ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    
                    <!-- Management Links -->
                    <li class="settings-nav-item" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <a href="?view=backups" class="settings-nav-link <?= $currentView === 'backups' ? 'active' : '' ?>">
                            <span class="settings-nav-icon">
                                <i class="fas fa-database"></i>
                            </span>
                            Backup Management
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="?view=audit" class="settings-nav-link <?= $currentView === 'audit' ? 'active' : '' ?>">
                            <span class="settings-nav-icon">
                                <i class="fas fa-history"></i>
                            </span>
                            Audit Logs
                        </a>
                    </li>
                </ul>

                <!-- Quick Actions -->
                <div style="margin-top: 2rem;">
                    <h5 style="margin-bottom: 1rem; color: var(--text-primary);">Quick Actions</h5>
                    
                    <button class="btn-action btn-warning w-100 mb-2" onclick="performMaintenance()">
                        <i class="fas fa-tools"></i> System Maintenance
                    </button>
                    
                    <button class="btn-action btn-success w-100 mb-2" onclick="createManualBackup()">
                        <i class="fas fa-database"></i> Create Backup
                    </button>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                <?php if ($currentView === 'audit'): ?>
                    <!-- Audit Logs View -->
                    <div class="audit-logs">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h3 style="margin: 0; color: var(--text-primary);">
                                <i class="fas fa-history me-2"></i>Audit Logs
                            </h3>
                            <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                                Track system activities and user actions
                            </p>
                        </div>
                      
                    </div>

                    <!-- Filters -->
                    <div class="glass-card" style="padding: 1rem; margin-bottom: 1.5rem;">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="view" value="audit">
                            <div class="col-md-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?= $_GET['date_from'] ?? '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?= $_GET['date_to'] ?? '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Action</label>
                                <input type="text" class="form-control" name="action" placeholder="Search action..." value="<?= $_GET['action'] ?? '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn-action btn-primary">Filter</button>
                                    <a href="?view=audit" class="btn-action btn-outline">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Audit Logs Table -->
                    <div class="glass-card" style="padding: 1.5rem;">
                        <?php if (!empty($auditLogs['logs'])): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Target</th>
                                            <th>IP Address</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($auditLogs['logs'] as $log): ?>
                                        <tr>
                                            <td>
                                                <small style="color: var(--text-primary);">
                                                    <?= !empty($log['created_at']) ? date('M j, Y', strtotime($log['created_at'])) : 'N/A' ?><br>
                                                    <?= !empty($log['created_at']) ? date('H:i:s', strtotime($log['created_at'])) : 'N/A' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong style="color: var(--text-primary);"><?= htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'System') ?></strong><br>
                                                <small style="color: var(--text-secondary);">@<?= htmlspecialchars($log['username'] ?? 'system') ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    strpos($log['action'], 'LOGIN') !== false ? 'success' : 
                                                    (strpos($log['action'], 'DELETE') !== false ? 'danger' : 
                                                    (strpos($log['action'], 'UPDATE') !== false ? 'warning' : 'info')) 
                                                ?>">
                                                    <?= htmlspecialchars($log['action']) ?>
                                                </span>
                                            </td>
                                            <td style="color: var(--text-primary);">
                                                <?php if (!empty($log['table_name'])): ?>
                                                    <?= htmlspecialchars($log['table_name']) ?>
                                                    <?php if (!empty($log['record_id'])): ?>
                                                        #<?= $log['record_id'] ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <em>System</em>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color: var(--text-secondary); font-family: monospace; font-size: 0.85rem;">
                                                <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($log['details'])): ?>
                                                    <?php 
                                                    // Ensure details is properly encoded for JavaScript
                                                    $detailsJson = is_string($log['details']) ? $log['details'] : json_encode($log['details']);
                                                    $detailsAttr = htmlspecialchars($detailsJson, ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="showAuditDetails('<?= $detailsAttr ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span style="color: var(--text-secondary); font-style: italic;">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <small style="color: var(--text-secondary);">
                                    Showing <?= count($auditLogs['logs']) ?> of <?= $auditLogs['total'] ?> total records
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="text-center" style="padding: 3rem;">
                                <i class="fas fa-history" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                                <h4 style="color: var(--text-primary);">No Audit Logs Found</h4>
                                <p style="color: var(--text-secondary);">No activities match your current filters.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    </div>

                <?php elseif ($currentView === 'backups'): ?>
                    <!-- Backup Management View -->
                    <div class="backup-management">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h3 style="margin: 0; color: var(--text-primary);">
                                <i class="fas fa-database me-2"></i>Backup Management
                            </h3>
                            <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                                Create and manage system backups
                            </p>
                        </div>
                       
                    </div>

                    <!-- Create Backup Form -->
                    <div class="glass-card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                        <h5 style="margin-bottom: 1rem; color: var(--text-primary);">Create Manual Backup</h5>
                        <form method="POST" style="margin-bottom: 0;">
                            <input type="hidden" name="action" value="create_manual_backup">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Backup Description</label>
                                    <input type="text" class="form-control" name="backup_description" 
                                           placeholder="e.g., Before major update, Weekly backup..." 
                                           value="Manual backup - <?= date('F j, Y') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn-action btn-success w-100">
                                            <i class="fas fa-database"></i> Create Backup
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Backup History -->
                    <div class="glass-card" style="padding: 1.5rem;">
                        <h5 style="margin-bottom: 1rem; color: var(--text-primary);">Backup History</h5>
                        <?php if (!empty($backupHistory)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date Created</th>
                                            <th>Description</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>File Size</th>
                                            <th>Created By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backupHistory as $backup): ?>
                                        <tr>
                                            <td style="color: var(--text-primary);">
                                                <?= !empty($backup['created_at']) ? date('M j, Y H:i', strtotime($backup['created_at'])) : 'N/A' ?>
                                            </td>
                                            <td style="color: var(--text-primary);">
                                                <?= !empty($backup['description']) ? htmlspecialchars($backup['description']) : 'No description' ?>
                                                <?php if (!empty($backup['backup_file'])): ?>
                                                    <br><small style="color: var(--text-secondary); font-family: monospace;">
                                                        <?= htmlspecialchars($backup['backup_file']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $backup['backup_type'] === 'manual' ? 'primary' : 'info' ?>">
                                                    <?= ucfirst($backup['backup_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $backup['status'] === 'completed' ? 'success' : 
                                                    ($backup['status'] === 'failed' ? 'danger' : 
                                                    ($backup['status'] === 'deleted' ? 'secondary' : 'warning'))
                                                ?>">
                                                    <?= ucfirst($backup['status']) ?>
                                                </span>
                                            </td>
                                            <td style="color: var(--text-primary);">
                                                <?php if ($backup['file_size'] > 0): ?>
                                                    <?= number_format($backup['file_size'] / 1024 / 1024, 2) ?> MB
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td style="color: var(--text-primary);">
                                                <?= htmlspecialchars($backup['created_by_name'] ?? 'System') ?>
                                            </td>
                                            <td>
                                                <?php if ($backup['status'] === 'completed'): ?>
                                                    <button class="btn btn-sm btn-danger" 
                                                            onclick="deleteBackup(<?= $backup['backup_id'] ?>, '<?= htmlspecialchars($backup['filename']) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center" style="padding: 3rem;">
                                <i class="fas fa-database" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                                <h4 style="color: var(--text-primary);">No Backups Found</h4>
                                <p style="color: var(--text-secondary);">Create your first backup using the form above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    </div>

                <?php elseif (isset($settingsByCategory[$activeCategory])): ?>
                    <?php $category = $settingsByCategory[$activeCategory]; ?>
                    
                    <!-- Messages will be inserted here by JavaScript -->
                    <div id="form-messages"></div>
                    
                    <!-- Settings Category View -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h3 style="margin: 0; color: var(--text-primary);">
                                <i class="<?= $category['icon'] ?> me-2"></i><?= $category['title'] ?>
                            </h3>
                            <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                                <?= $category['description'] ?>
                            </p>
                        </div>
                    </div>

                    <form method="POST" id="settingsForm">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <?php foreach ($category['settings'] as $settingKey => $settingData): ?>
                        <div class="form-group">
                            <label class="form-label" for="<?= $settingKey ?>">
                                <?= $settingData['schema']['label'] ?>
                                <?php if (in_array('required', $settingData['schema']['validation'] ?? [])): ?>
                                    <span style="color: var(--error-color);">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php 
                            $inputType = $settingData['schema']['type'];
                            $inputValue = $settingData['value'];
                            $inputName = "settings[{$settingKey}]";
                            ?>
                            
                            <?php if ($inputType === 'boolean'): ?>
                                <div class="form-check">
                                    <input type="hidden" name="<?= $inputName ?>" value="0">
                                    <input class="form-check-input" type="checkbox" 
                                           id="<?= $settingKey ?>" name="<?= $inputName ?>" 
                                           value="1" <?= $inputValue ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $settingKey ?>">
                                        Enable this option
                                    </label>
                                </div>
                                
                            <?php elseif ($inputType === 'select'): ?>
                                <select class="form-select" id="<?= $settingKey ?>" name="<?= $inputName ?>">
                                    <?php 
                                    $options = $settingData['schema']['options'] ?? [];
                                    foreach ($options as $optionValue => $optionLabel): ?>
                                        <option value="<?= htmlspecialchars($optionValue) ?>" 
                                                <?= $inputValue == $optionValue ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($optionLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                            <?php elseif ($inputType === 'multiselect'): ?>
                                <?php 
                                $selectedValues = is_array($inputValue) ? $inputValue : (json_decode($inputValue, true) ?: []);
                                $options = $settingData['schema']['options'] ?? [];
                                ?>
                                <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem;">
                                    <?php foreach ($options as $optionValue => $optionLabel): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="<?= $settingKey ?>_<?= $optionValue ?>" 
                                                   name="<?= $inputName ?>[]" 
                                                   value="<?= htmlspecialchars($optionValue) ?>"
                                                   <?= in_array($optionValue, $selectedValues) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="<?= $settingKey ?>_<?= $optionValue ?>">
                                                <?= htmlspecialchars($optionLabel) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                            <?php elseif ($inputType === 'textarea'): ?>
                                <textarea class="form-control" id="<?= $settingKey ?>" name="<?= $inputName ?>" 
                                          rows="4" placeholder="<?= htmlspecialchars($settingData['schema']['description'] ?? '') ?>"><?= htmlspecialchars($inputValue) ?></textarea>
                                
                            <?php elseif ($inputType === 'password'): ?>
                                <input type="password" class="form-control" id="<?= $settingKey ?>" 
                                       name="<?= $inputName ?>" value="<?= htmlspecialchars($inputValue) ?>" 
                                       placeholder="<?= htmlspecialchars($settingData['schema']['description'] ?? '') ?>">
                                
                            <?php elseif ($inputType === 'email'): ?>
                                <input type="email" class="form-control" id="<?= $settingKey ?>" 
                                       name="<?= $inputName ?>" value="<?= htmlspecialchars($inputValue) ?>" 
                                       placeholder="<?= htmlspecialchars($settingData['schema']['description'] ?? '') ?>">
                                
                            <?php elseif ($inputType === 'integer'): ?>
                                <input type="number" class="form-control" id="<?= $settingKey ?>" 
                                       name="<?= $inputName ?>" value="<?= htmlspecialchars($inputValue) ?>" 
                                       placeholder="<?= htmlspecialchars($settingData['schema']['description'] ?? '') ?>"
                                       <?php
                                       $validation = $settingData['schema']['validation'] ?? [];
                                       foreach ($validation as $rule) {
                                           if (is_array($rule)) {
                                               $ruleName = key($rule);
                                               $ruleValue = $rule[$ruleName];
                                               if ($ruleName === 'min') echo "min=\"{$ruleValue}\"";
                                               if ($ruleName === 'max') echo "max=\"{$ruleValue}\"";
                                           }
                                       }
                                       ?>>
                                
                            <?php else: ?>
                                <input type="text" class="form-control" id="<?= $settingKey ?>" 
                                       name="<?= $inputName ?>" value="<?= htmlspecialchars($inputValue) ?>" 
                                       placeholder="<?= htmlspecialchars($settingData['schema']['description'] ?? '') ?>">
                            <?php endif; ?>
                            
                            <?php if (!empty($settingData['schema']['description'])): ?>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    <?= htmlspecialchars($settingData['schema']['description']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="d-flex gap-3 justify-content-end" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                            <button type="button" class="btn-action btn-outline" onclick="resetForm()">
                                <i class="fas fa-undo"></i> Reset Changes
                            </button>
                            <button type="submit" class="btn-action btn-success" id="saveBtn">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                    
                <?php else: ?>
                    <!-- Default View -->
                    <div class="text-center" style="padding: 3rem;">
                        <i class="fas fa-cog" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                        <h4 style="color: var(--text-primary);">Welcome to System Settings</h4>
                        <p style="color: var(--text-secondary);">Choose a settings category from the sidebar to configure system parameters, or use the management tools to handle backups and audit logs.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Maintenance Modal -->
    <div class="modal fade" id="maintenanceModal" tabindex="-1" aria-labelledby="maintenanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="maintenanceModalLabel" style="color: var(--text-primary);">
                        <i class="fas fa-tools"></i> System Maintenance
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
                        Select maintenance tasks to perform. These operations will help optimize system performance and clean up old data.
                    </p>
                    
                    <form id="maintenanceForm">
                        <input type="hidden" name="action" value="perform_maintenance">
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="maintenance_tasks[]" value="cleanup_notifications" id="cleanup_notifications" checked>
                            <label class="form-check-label" for="cleanup_notifications" style="color: var(--text-primary);">
                                <strong>Clean Up Notifications</strong>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Remove expired and old read notifications</div>
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="maintenance_tasks[]" value="cleanup_login_attempts" id="cleanup_login_attempts" checked>
                            <label class="form-check-label" for="cleanup_login_attempts" style="color: var(--text-primary);">
                                <strong>Clean Up Login Attempts</strong>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Remove old failed login attempt records</div>
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="maintenance_tasks[]" value="optimize_database" id="optimize_database">
                            <label class="form-check-label" for="optimize_database" style="color: var(--text-primary);">
                                <strong>Optimize Database</strong>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Optimize database tables for better performance</div>
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="maintenance_tasks[]" value="clear_cache" id="clear_cache">
                            <label class="form-check-label" for="clear_cache" style="color: var(--text-primary);">
                                <strong>Clear System Cache</strong>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Clear all cached data and temporary files</div>
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="maintenance_tasks[]" value="cleanup_logs" id="cleanup_logs" checked>
                            <label class="form-check-label" for="cleanup_logs" style="color: var(--text-primary);">
                                <strong>Clean Up Audit Logs</strong>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Remove old audit logs based on retention settings</div>
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="maintenance_tasks[]" value="backup_system" id="backup_system">
                            <label class="form-check-label" for="backup_system" style="color: var(--text-primary);">
                                <strong>Create System Backup</strong>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">Create a full system backup before maintenance</div>
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn-action btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-action btn-warning" onclick="submitMaintenance()">
                        <i class="fas fa-tools"></i> Run Maintenance
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Details Modal -->
    <div class="modal fade" id="auditDetailsModal" tabindex="-1" aria-labelledby="auditDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="auditDetailsModalLabel" style="color: var(--text-primary);">
                        <i class="fas fa-info-circle"></i> Audit Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                </div>
                <div class="modal-body">
                    <pre id="auditDetailsContent" style="color: var(--text-primary); white-space: pre-wrap; word-wrap: break-word; margin: 0; font-family: 'Courier New', monospace; font-size: 0.9rem;"></pre>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-scroll to settings form or management sections on page load
            const urlParams = new URLSearchParams(window.location.search);
            const category = urlParams.get('category');
            const view = urlParams.get('view');

            setTimeout(() => {
                // If viewing a specific settings category
                if (category && !view) {
                    const settingsForm = document.querySelector('#settingsForm');
                    const settingsContent = document.querySelector('.settings-content');
                    if (settingsForm) {
                        settingsForm.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
                    } else if (settingsContent) {
                        settingsContent.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
                    }
                    return;
                }

                // If viewing backups or audit, scroll to their sections
                if (view === 'backups' || view === 'audit') {
                    const targetSelector = view === 'backups' ? '.backup-management' : '.audit-logs';
                    const target = document.querySelector(targetSelector);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
                    }
                }
            }, 300);
        });

        // Settings form handling
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Sidebar handlers removed to prevent unintended expansion
            
            // Apply responsive layout immediately
            applyResponsiveLayout();
            
            // Initialize form validation
            initializeFormValidation();
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });

        /**
         * Apply responsive layout
         */
        function applyResponsiveLayout() {
            const isNarrow = window.matchMedia('(max-width: 1024px)').matches;
            const body = document.body;
            
            if (isNarrow) {
                body.classList.add('sidebar-collapsed');
                document.documentElement.style.setProperty('--sidebar-width', '0px');
            } else {
                body.classList.remove('sidebar-collapsed');
                document.documentElement.style.removeProperty('--sidebar-width');
            }

            requestAnimationFrame(() => {
                window.dispatchEvent(new Event('scroll'));
            });
        }

        // Re-apply on viewport changes
        window.addEventListener('resize', applyResponsiveLayout);
        window.addEventListener('orientationchange', applyResponsiveLayout);
        window.addEventListener('pageshow', applyResponsiveLayout);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') applyResponsiveLayout();
        });
        
        // Auto-hide success messages after 5 seconds
        const successMessages = document.querySelectorAll('.alert-success');
        successMessages.forEach(message => {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            }, 5000);
        });

        /**
         * Apply current theme from localStorage
         */
        function applyCurrentTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            
            const themeIcon = document.querySelector('#themeToggle i');
            if (themeIcon) {
                themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        /**
         * Handle sidebar toggle
         */
        function handleSidebarToggle() {
            window.addEventListener('sidebarToggled', function(e) {
                const body = document.body;
                
                if (e.detail && e.detail.collapsed) {
                    body.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                }
            });

            const sidebar = document.querySelector('.sidebar');
            if (sidebar && sidebar.classList.contains('collapsed')) {
                document.body.classList.add('sidebar-collapsed');
            }
        }

        /**
         * Initialize form validation
         */
        function initializeFormValidation() {
            const form = document.getElementById('settingsForm');
            if (!form) return;
            
            const saveBtn = document.getElementById('saveBtn');
            const messagesContainer = document.createElement('div');
            messagesContainer.id = 'form-messages';
            form.parentNode.insertBefore(messagesContainer, form);
            
            function showMessage(message, type = 'success') {
                const alert = document.createElement('div');
                alert.className = `alert alert-${type} fade-in`;
                alert.innerHTML = `
                    <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle me-2"></i>
                    ${message}
                `;
                messagesContainer.prepend(alert);
                
                // Scroll to the message
                setTimeout(() => {
                    const messagePosition = messagesContainer.getBoundingClientRect().top + window.pageYOffset - 100;
                    window.scrollTo({
                        top: messagePosition,
                        behavior: 'smooth'
                    });
                }, 100);
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            }
            
            // Handle form submission
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Validate form
                if (!validateForm()) {
                    return false;
                }
                
                // Prepare the real form data (includes nested names like settings[...])
                const formData = new FormData(form);
                
                // Show loading state
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Check for new messages in the response
                    const successMessage = doc.querySelector('.alert-success');
                    const errorMessage = doc.querySelector('.alert-danger');
                    
                    if (successMessage) {
                        showMessage(successMessage.textContent.trim(), 'success');
                        // Reload the page to show updated settings
                        setTimeout(() => window.location.reload(), 1500);
                    } else if (errorMessage) {
                        showMessage(errorMessage.textContent.trim(), 'danger');
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                    } else {
                        // If no specific message but form was submitted successfully
                        showMessage('Settings updated successfully!', 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showMessage('An error occurred while saving settings.', 'danger');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                }
            });
        }

        /**
         * Validate form inputs
         */
        function validateForm() {
            return true;
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
         * Create manual backup
         */
        function createManualBackup() {
            const description = prompt('Enter backup description:', 'Manual backup - ' + new Date().toLocaleDateString());
            
            if (description !== null && description.trim() !== '') {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'create_manual_backup';
                form.appendChild(actionInput);
                
                const descInput = document.createElement('input');
                descInput.type = 'hidden';
                descInput.name = 'backup_description';
                descInput.value = description.trim();
                form.appendChild(descInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        /**
         * Delete backup
         */
        function deleteBackup(backupId, filename) {
            if (confirm(`Are you sure you want to delete backup "${filename}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_backup';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'backup_id';
                idInput.value = backupId;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        /**
         * Perform system maintenance
         */
        function performMaintenance() {
            const modal = new bootstrap.Modal(document.getElementById('maintenanceModal'));
            modal.show();
        }

        /**
         * Submit maintenance tasks
         */
        function submitMaintenance() {
            const form = document.getElementById('maintenanceForm');
            const formData = new FormData(form);
            
            const checkedTasks = formData.getAll('maintenance_tasks[]');
            
            if (checkedTasks.length === 0) {
                alert('Please select at least one maintenance task.');
                return;
            }
            
            if (confirm('Are you sure you want to run the selected maintenance tasks? This may take a few minutes.')) {
                const hiddenForm = document.createElement('form');
                hiddenForm.method = 'POST';
                hiddenForm.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'perform_maintenance';
                hiddenForm.appendChild(actionInput);
                
                checkedTasks.forEach(task => {
                    const taskInput = document.createElement('input');
                    taskInput.type = 'hidden';
                    taskInput.name = 'maintenance_tasks[]';
                    taskInput.value = task;
                    hiddenForm.appendChild(taskInput);
                });
                
                document.body.appendChild(hiddenForm);
                hiddenForm.submit();
            }
        }

        /**
         * Export settings
         */
        function exportSettings() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export_settings';
            form.appendChild(actionInput);
            
            const categories = ['general', 'security', 'notifications', 'backup', 'performance', 'maintenance'];
            categories.forEach(category => {
                const categoryInput = document.createElement('input');
                categoryInput.type = 'hidden';
                categoryInput.name = 'export_categories[]';
                categoryInput.value = category;
                form.appendChild(categoryInput);
            });
            
            document.body.appendChild(form);
            form.submit();
        }

        /**
         * Import settings
         */
        function importSettings(input) {
            if (input.files && input.files[0]) {
                if (confirm('Are you sure you want to import these settings? This will overwrite existing configuration.')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    form.enctype = 'multipart/form-data';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'import_settings';
                    form.appendChild(actionInput);
                    
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.name = 'settings_file';
                    fileInput.files = input.files;
                    form.appendChild(fileInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }

        /**
         * Show audit details modal
         */
        function showAuditDetails(details) {
            const detailsContent = document.getElementById('auditDetailsContent');
            
            try {
                if (typeof details === 'string') {
                    // Try to parse as JSON first
                    try {
                        const parsed = JSON.parse(details);
                        detailsContent.textContent = JSON.stringify(parsed, null, 2);
                    } catch (e) {
                        // If not valid JSON, display as plain text
                        detailsContent.textContent = details;
                    }
                } else if (typeof details === 'object' && details !== null) {
                    // Already an object, format it nicely
                    detailsContent.textContent = JSON.stringify(details, null, 2);
                } else {
                    detailsContent.textContent = 'No details available';
                }
            } catch (e) {
                console.error('Error displaying audit details:', e);
                detailsContent.textContent = 'Error displaying details: ' + (e.message || 'Unknown error');
            }
            
            const modal = new bootstrap.Modal(document.getElementById('auditDetailsModal'));
            modal.show();
        }

        /**
         * Theme toggle functionality
         */
        function toggleTheme() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            const themeIcon = document.querySelector('#themeToggle i');
            if (themeIcon) {
                themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        // Sidebar JS handlers removed to prevent unintended expansion on single link clicks.
        // Pages will rely on static CSS margins as before.

        // Make functions available globally
        window.resetForm = resetForm;
        window.createManualBackup = createManualBackup;
        window.deleteBackup = deleteBackup;
        window.performMaintenance = performMaintenance;
        window.submitMaintenance = submitMaintenance;
        window.exportSettings = exportSettings;
        window.importSettings = importSettings;
        window.showAuditDetails = showAuditDetails;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>