<?php
/**
 * Admin Reports Index - Reports & Export Dashboard
 * Timetable Management System
 * 
 * Comprehensive reporting interface with system analytics, custom report generation,
 * and integrated export functionality using existing ExportService
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Report.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$reportManager = new Report();

// Current admin profile
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
    // Non-fatal if profile missing; fallback values used in view
}

// Initialize variables
$error_message = '';
$success_message = '';
$systemStats = [];
$chartData = [];
$dashboardMetrics = [];

try {
    // Get system statistics for dashboard
    $systemStats = $reportManager->getSystemStatistics();
    $dashboardMetrics = $reportManager->getDashboardMetrics();
    $chartData = $reportManager->getChartData();
    
    // Get available options for filters
    $departments = $reportManager->getAvailableDepartments();
    $academicYears = $reportManager->getAvailableAcademicYears();
    $reportTypes = $reportManager->getAvailableReportTypes();
    
} catch (Exception $e) {
    error_log("Reports Dashboard Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the dashboard.";
}

// Set page title
$pageTitle = "Reports & Export";
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
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

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
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
            margin-bottom: 1.5rem;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(20px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            padding: 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.users { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .stat-icon.resources { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.schedules { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.activities { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .stat-change {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            font-weight: 600;
        }

        .stat-change.positive { color: var(--success-color); }
        .stat-change.negative { color: var(--error-color); }
        .stat-change.neutral { color: var(--text-secondary); }

        /* Report Type Cards */
        .report-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .report-type-card {
            padding: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .report-type-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
        }

        .report-type-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
        }

        .report-type-card.primary::before { background: var(--primary-color); }
        .report-type-card.success::before { background: var(--success-color); }
        .report-type-card.warning::before { background: var(--warning-color); }
        .report-type-card.info::before { background: var(--info-color); }
        .report-type-card.danger::before { background: var(--error-color); }

        .report-type-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .report-type-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .report-type-icon.primary { background: var(--primary-color); }
        .report-type-icon.success { background: var(--success-color); }
        .report-type-icon.warning { background: var(--warning-color); }
        .report-type-icon.info { background: var(--info-color); }
        .report-type-icon.danger { background: var(--error-color); }

        .report-type-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .report-type-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .report-type-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Chart Containers */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            padding: 1.5rem;
            height: 350px;
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            text-align: center;
        }

        .chart-canvas {
            max-height: 280px;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action-card {
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-action-card:hover {
            transform: translateY(-2px);
        }

        .quick-action-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            background: var(--primary-color);
        }

        .quick-action-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .quick-action-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Recent Reports Table */
        .recent-reports-table {
            margin-top: 2rem;
        }

        .table-container {
            padding: 1.5rem;
            overflow-x: auto;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border: none;
            background: rgba(99, 102, 241, 0.1);
            color: var(--text-primary);
            font-weight: 600;
            padding: 1rem;
            white-space: nowrap;
        }

        .table td {
            border: none;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 1rem;
            vertical-align: middle;
        }

        [data-theme="dark"] .table th {
            background: var(--bg-tertiary);
        }

        [data-theme="dark"] .table td {
            border-bottom-color: var(--border-color);
        }

        .table tbody tr:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        [data-theme="dark"] .table tbody tr:hover {
            background: var(--bg-tertiary);
        }

        /* Button Styles */
        .btn-action {
            padding: 0.5rem 1rem;
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
            transform: translateY(-1px);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }

        [data-theme="dark"] .btn-outline {
            border-color: var(--border-color);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
        }

        /* System Health Indicators */
        .health-indicators {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .health-indicator {
            padding: 1rem;
            text-align: center;
            border-radius: 12px;
            border: 2px solid;
        }

        .health-indicator.healthy {
            border-color: var(--success-color);
            background: rgba(16, 185, 129, 0.1);
        }

        .health-indicator.warning {
            border-color: var(--warning-color);
            background: rgba(245, 158, 11, 0.1);
        }

        .health-indicator.critical {
            border-color: var(--error-color);
            background: rgba(239, 68, 68, 0.1);
        }

        .health-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .health-indicator.healthy .health-value { color: var(--success-color); }
        .health-indicator.warning .health-value { color: var(--warning-color); }
        .health-indicator.critical .health-value { color: var(--error-color); }

        .health-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }


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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: calc(var(--navbar-height) + 1rem);
                max-width: 100vw;
                overflow-x: hidden;
            }

            .page-title {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .charts-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .report-types-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }

            .health-indicators {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }

            .health-indicators {
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

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Custom Report Form */
        .custom-report-form {
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .form-section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        [data-theme="light"] .form-control,
        [data-theme="light"] .form-select {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
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
                        <h1 class="page-title">📊 Reports & Export</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn-action btn-success" onclick="refreshData()">
                            🔄 Refresh Data
                        </button>
                        <button class="btn-action btn-outline" onclick="cleanupFiles()">
                            🗑️ Cleanup Files
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

        <!-- System Health Indicators -->
        <div class="health-indicators">
            <?php
            $health = $dashboardMetrics['system_health'] ?? [];
            $activeUsersWeek = $health['active_users_week'] ?? 0;
            $failedLogins = $health['failed_logins_24h'] ?? 0;
            $expiredNotifications = $health['expired_notifications'] ?? 0;
            ?>
            
            <div class="health-indicator <?= $activeUsersWeek > 0 ? 'healthy' : 'warning' ?> glass-card">
                <div class="health-value"><?= $activeUsersWeek ?></div>
                <div class="health-label">Active This Week</div>
            </div>
            
            <div class="health-indicator <?= $failedLogins < 10 ? 'healthy' : ($failedLogins < 25 ? 'warning' : 'critical') ?> glass-card">
                <div class="health-value"><?= $failedLogins ?></div>
                <div class="health-label">Failed Logins (24h)</div>
            </div>
            
            <div class="health-indicator <?= $expiredNotifications == 0 ? 'healthy' : 'warning' ?> glass-card">
                <div class="health-value"><?= $expiredNotifications ?></div>
                <div class="health-label">Expired Notifications</div>
            </div>
            
            <div class="health-indicator healthy glass-card">
                <div class="health-value"><?= count($dashboardMetrics['recent_activities'] ?? []) ?></div>
                <div class="health-label">Recent Activities</div>
            </div>
        </div>

        <!-- Key Statistics -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon users">👥</div>
                <div class="stat-number"><?= $systemStats['users']['total_users'] ?? 0 ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <?= $systemStats['users']['active_users'] ?? 0 ?> active
                </div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon resources">🏛️</div>
                <div class="stat-number"><?= ($systemStats['resources']['active_subjects'] ?? 0) + ($systemStats['resources']['active_classrooms'] ?? 0) ?></div>
                <div class="stat-label">Total Resources</div>
                <div class="stat-change neutral">
                    <i class="fas fa-book"></i>
                    <?= $systemStats['resources']['active_subjects'] ?? 0 ?> subjects
                </div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon schedules">📅</div>
                <div class="stat-number"><?= $systemStats['timetables']['active_schedules'] ?? 0 ?></div>
                <div class="stat-label">Active Schedules</div>
                <div class="stat-change positive">
                    <i class="fas fa-users"></i>
                    <?= $systemStats['timetables']['scheduled_faculty'] ?? 0 ?> faculty
                </div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon activities">📈</div>
                <div class="stat-number"><?= $dashboardMetrics['quick_metrics']['daily_activities'] ?? 0 ?></div>
                <div class="stat-label">Daily Activities</div>
                <div class="stat-change positive">
                    <i class="fas fa-clock"></i>
                    Last 24 hours
                </div>
            </div>
        </div>

        <!-- Report Types -->
        <div class="glass-card">
            <div style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h3 style="color: var(--text-primary); margin-bottom: 1rem;">
                    <i class="fas fa-chart-bar me-2"></i>Available Report Types
                </h3>
            </div>
            
            <div class="report-types-grid" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <?php foreach ($reportTypes as $type => $config): ?>
                <div class="report-type-card glass-card <?= $config['color'] ?>" onclick="openReportGenerator('<?= $type ?>')">
                    <div class="report-type-header">
                        <div class="report-type-icon <?= $config['color'] ?>">
                            <i class="<?= $config['icon'] ?>"></i>
                        </div>
                        <div>
                            <div class="report-type-title"><?= $config['name'] ?></div>
                        </div>
                    </div>
                    <div class="report-type-description">
                        <?= $config['description'] ?>
                    </div>
                    <div class="report-type-actions">
                        <button class="btn-action btn-primary btn-sm" onclick="event.stopPropagation(); generateQuickReport('<?= $type ?>', 'excel')">
                            📊 Excel
                        </button>
                        <button class="btn-action btn-outline btn-sm" onclick="event.stopPropagation(); generateQuickReport('<?= $type ?>', 'pdf')">
                            📄 PDF
                        </button>
                        <button class="btn-action btn-outline btn-sm" onclick="event.stopPropagation(); openReportGenerator('<?= $type ?>')">
                            ⚙️ Custom
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <!-- Registration Trends Chart -->
            <div class="chart-container glass-card">
                <div class="chart-title">
                    <i class="fas fa-chart-line me-2"></i>User Registration Trends
                </div>
                <canvas id="registrationChart" class="chart-canvas"></canvas>
            </div>

            <!-- Department Distribution Chart -->
            <div class="chart-container glass-card">
                <div class="chart-title">
                    <i class="fas fa-chart-pie me-2"></i>Department Distribution
                </div>
                <canvas id="departmentChart" class="chart-canvas"></canvas>
            </div>

            <!-- Resource Utilization Chart -->
            <div class="chart-container glass-card">
                <div class="chart-title">
                    <i class="fas fa-chart-bar me-2"></i>Resource Utilization
                </div>
                <canvas id="utilizationChart" class="chart-canvas"></canvas>
            </div>

            <!-- Daily Activity Chart -->
            <div class="chart-container glass-card">
                <div class="chart-title">
                    <i class="fas fa-activity me-2"></i>Daily Activity (Last 14 Days)
                </div>
                <canvas id="activityChart" class="chart-canvas"></canvas>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="glass-card">
            <div style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h3 style="color: var(--text-primary); margin-bottom: 1rem;">
                    <i class="fas fa-bolt me-2"></i>Quick Report Actions
                </h3>
            </div>
            
            <div class="quick-actions" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <div class="quick-action-card glass-card" onclick="generateQuickReport('users', 'excel')">
                    <div class="quick-action-icon" style="background: var(--primary-color);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="quick-action-title">Export All Users</div>
                    <div class="quick-action-description">Complete user list with profiles</div>
                </div>

                <div class="quick-action-card glass-card" onclick="generateQuickReport('timetables', 'pdf')">
                    <div class="quick-action-icon" style="background: var(--success-color);">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="quick-action-title">Current Timetables</div>
                    <div class="quick-action-description">Active schedules PDF report</div>
                </div>

                <div class="quick-action-card glass-card" onclick="generateQuickReport('resources', 'excel')">
                    <div class="quick-action-icon" style="background: var(--warning-color);">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="quick-action-title">Resource Utilization</div>
                    <div class="quick-action-description">Classroom and faculty usage</div>
                </div>

                <div class="quick-action-card glass-card" onclick="generateQuickReport('activity', 'excel')">
                    <div class="quick-action-icon" style="background: var(--info-color);">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="quick-action-title">System Activity</div>
                    <div class="quick-action-description">Recent system activities</div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="recent-reports-table glass-card">
            <div style="padding: 1.5rem 1.5rem 0 1.5rem;">
                <h3 style="color: var(--text-primary); margin-bottom: 1rem;">
                    <i class="fas fa-clock me-2"></i>Recent System Activities
                </h3>
            </div>
            
            <div class="table-container" style="overflow: visible;">
                <?php if (!empty($dashboardMetrics['recent_activities'])): ?>
                    <div class="row g-3 px-3 pb-3">
                        <?php foreach ($dashboardMetrics['recent_activities'] as $activity): ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="glass-card" style="height: 100%; padding: 1rem; border: 1px solid var(--border-color); background: var(--bg-secondary); overflow: hidden;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge" style="background: var(--primary-color); color: #fff; padding: 0.35rem 0.6rem; border-radius: 6px; font-size: 0.75rem;">
                                        <?= htmlspecialchars(str_replace('_', ' ', $activity['action'])) ?>
                                    </span>
                                    <span style="color: var(--text-secondary); font-size: 0.8rem;">
                                        <i class="far fa-clock me-1"></i><?= date('M j, Y g:i A', strtotime($activity['timestamp'])) ?>
                                    </span>
                                </div>
                                <div class="mb-1" style="font-weight: 600; color: var(--text-primary); overflow-wrap: anywhere; word-break: break-word;">
                                    <?= htmlspecialchars($activity['user_full_name'] ?? $activity['username'] ?? 'Unknown User') ?>
                                </div>
                                <div class="mb-2" style="font-size: 0.8rem; color: var(--text-secondary); overflow-wrap: anywhere; word-break: break-word;">
                                    <?= ucfirst($activity['role'] ?? 'unknown') ?>
                                </div>
                                <div style="color: var(--text-secondary); line-height: 1.4; white-space: normal; overflow-wrap: anywhere; word-break: break-word;">
                                    <?= htmlspecialchars($activity['description'] ?? 'No description available') ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center" style="color: var(--text-secondary); padding: 2rem;">
                        <i class="fas fa-info-circle me-2"></i>
                        No recent activities found
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Custom Report Generator Modal -->
        <div class="modal fade" id="reportGeneratorModal" tabindex="-1" aria-labelledby="reportGeneratorLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                        <h5 class="modal-title" id="reportGeneratorLabel" style="color: var(--text-primary);">
                            <i class="fas fa-cog"></i> Custom Report Generator
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="customReportForm">
                            <input type="hidden" id="reportType" name="type">
                            
                            <!-- Report Configuration -->
                            <div class="form-section">
                                <h6 class="form-section-title">📋 Report Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="reportTitle" class="form-label">Report Title</label>
                                        <input type="text" class="form-control" id="reportTitle" name="title" 
                                               placeholder="Custom Report Title">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="reportFormat" class="form-label">Export Format</label>
                                        <select class="form-select" id="reportFormat" name="format">
                                            <option value="excel">Excel (.xlsx)</option>
                                            <option value="pdf">PDF Document</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Filters -->
                            <div class="form-section">
                                <h6 class="form-section-title">🔍 Filters</h6>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="filterDateFrom" class="form-label">Date From</label>
                                        <input type="date" class="form-control" id="filterDateFrom" name="date_from">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="filterDateTo" class="form-label">Date To</label>
                                        <input type="date" class="form-control" id="filterDateTo" name="date_to">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="filterDepartment" class="form-label">Department</label>
                                        <select class="form-select" id="filterDepartment" name="department">
                                            <option value="">All Departments</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= htmlspecialchars($dept['name']) ?>">
                                                    <?= htmlspecialchars($dept['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="filterRole" class="form-label">User Role</label>
                                        <select class="form-select" id="filterRole" name="role">
                                            <option value="">All Roles</option>
                                            <option value="admin">Admin</option>
                                            <option value="faculty">Faculty</option>
                                            <option value="student">Student</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="filterAcademicYear" class="form-label">Academic Year</label>
                                        <select class="form-select" id="filterAcademicYear" name="academic_year">
                                            <option value="">Current Year</option>
                                            <?php foreach ($academicYears as $year): ?>
                                                <option value="<?= htmlspecialchars($year['academic_year']) ?>">
                                                    <?= htmlspecialchars($year['academic_year']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="filterSemester" class="form-label">Semester</label>
                                        <select class="form-select" id="filterSemester" name="semester">
                                            <option value="">All Semesters</option>
                                            <option value="1">Semester 1</option>
                                            <option value="2">Semester 2</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                        <button type="button" class="btn-action btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn-action btn-primary" onclick="generateCustomReport()">
                            <i class="fas fa-download"></i> Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Modal -->
        <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-sm">
                <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="modal-body text-center" style="padding: 2rem;">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div id="loadingTitle" style="color: var(--text-primary); font-weight: 600;">
                            Generating Report...
                        </div>
                        <div id="loadingSubtitle" style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.5rem;">
                            Please wait while we prepare your report
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Chart data from PHP
        const chartData = <?= json_encode($chartData) ?>;
        const systemStats = <?= json_encode($systemStats) ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Apply theme
            applyCurrentTheme();
            
            // Sidebar handlers removed to prevent unintended expansion
            
            // Apply responsive layout immediately
            applyResponsiveLayout();
            
            // Initialize charts
            initializeCharts();
            
            // Set default date range
            setDefaultDateRange();
        });

        /**
         * Apply responsive layout (ensures correct fit when switching viewports without refresh)
         */
        function applyResponsiveLayout() {
            const isNarrow = window.matchMedia('(max-width: 1024px)').matches;
            const body = document.body;
            // Ensure main content does not keep desktop offset on mobile
            if (isNarrow) {
                body.classList.add('sidebar-collapsed');
                document.documentElement.style.setProperty('--sidebar-width', '0px');
            } else {
                body.classList.remove('sidebar-collapsed');
                document.documentElement.style.removeProperty('--sidebar-width');
            }

            // Trigger layout recalculation
            requestAnimationFrame(() => {
                window.dispatchEvent(new Event('scroll'));
            });
        }

        // Re-apply on viewport changes and when returning via bfcache
        window.addEventListener('resize', applyResponsiveLayout);
        window.addEventListener('orientationchange', applyResponsiveLayout);
        window.addEventListener('pageshow', applyResponsiveLayout);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') applyResponsiveLayout();
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
            // Listen for sidebar collapse/expand events
            window.addEventListener('sidebarToggled', function(e) {
                const body = document.body;
                
                if (e.detail && e.detail.collapsed) {
                    body.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                }
            });

            // Check initial sidebar state
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && sidebar.classList.contains('collapsed')) {
                document.body.classList.add('sidebar-collapsed');
            }
        }


        // Store chart instances for proper cleanup
        let chartInstances = {};

        /**
         * Initialize all charts
         */
        function initializeCharts() {
            // Destroy existing charts before creating new ones
            destroyAllCharts();
            
            initializeRegistrationChart();
            initializeDepartmentChart();
            initializeUtilizationChart();
            initializeActivityChart();
        }

        /**
         * Destroy all existing chart instances
         */
        function destroyAllCharts() {
            Object.keys(chartInstances).forEach(key => {
                if (chartInstances[key]) {
                    chartInstances[key].destroy();
                    delete chartInstances[key];
                }
            });
        }

        /**
         * Initialize registration trends chart
         */
        function initializeRegistrationChart() {
            const ctx = document.getElementById('registrationChart');
            if (!ctx || !chartData.registration_trends) return;

            const data = chartData.registration_trends;
            const labels = data.map(item => item.month_name || item.month);
            const studentData = data.map(item => parseInt(item.students) || 0);
            const facultyData = data.map(item => parseInt(item.faculty) || 0);

            chartInstances.registrationChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Students',
                            data: studentData,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Faculty',
                            data: facultyData,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                            },
                            grid: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                            }
                        },
                        x: {
                            ticks: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                            },
                            grid: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                            }
                        }
                    }
                }
            });
        }

        /**
         * Initialize department distribution chart
         */
        function initializeDepartmentChart() {
            const ctx = document.getElementById('departmentChart');
            if (!ctx || !chartData.department_distribution) return;

            const data = chartData.department_distribution;
            const departmentCounts = {};
            
            // Aggregate data by department
            data.forEach(item => {
                if (!departmentCounts[item.department]) {
                    departmentCounts[item.department] = 0;
                }
                departmentCounts[item.department] += parseInt(item.user_count) || 0;
            });

            const labels = Object.keys(departmentCounts);
            const values = Object.values(departmentCounts);
            const colors = [
                '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#14b8a6'
            ];

            chartInstances.departmentChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.slice(0, labels.length),
                        borderWidth: 2,
                        borderColor: getComputedStyle(document.documentElement).getPropertyValue('--bg-primary')
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary'),
                                padding: 15
                            }
                        }
                    }
                }
            });
        }

        /**
         * Initialize resource utilization chart
         */
        function initializeUtilizationChart() {
            const ctx = document.getElementById('utilizationChart');
            if (!ctx || !chartData.resource_utilization) return;

            const data = chartData.resource_utilization;
            
            const resourceData = [
                {
                    label: 'Classrooms',
                    utilized: parseInt(data.utilized_classrooms) || 0,
                    total: parseInt(data.total_classrooms) || 0
                },
                {
                    label: 'Faculty',
                    utilized: parseInt(data.active_faculty) || 0,
                    total: parseInt(data.total_faculty) || 0
                },
                {
                    label: 'Subjects',
                    utilized: parseInt(data.scheduled_subjects) || 0,
                    total: parseInt(data.total_subjects) || 0
                }
            ];

            const labels = resourceData.map(item => item.label);
            const utilizedData = resourceData.map(item => item.utilized);
            const totalData = resourceData.map(item => item.total);

            chartInstances.utilizationChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Utilized',
                            data: utilizedData,
                            backgroundColor: '#10b981'
                        },
                        {
                            label: 'Total Available',
                            data: totalData,
                            backgroundColor: '#e5e7eb'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                            },
                            grid: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                            }
                        },
                        x: {
                            ticks: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                            },
                            grid: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                            }
                        }
                    }
                }
            });
        }

        /**
         * Initialize daily activity chart
         */
        function initializeActivityChart() {
            const ctx = document.getElementById('activityChart');
            if (!ctx || !chartData.daily_activity) return;

            const data = chartData.daily_activity.slice(0, 14).reverse(); // Last 14 days, chronological order
            const labels = data.map(item => new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            const activityData = data.map(item => parseInt(item.activity_count) || 0);
            const usersData = data.map(item => parseInt(item.unique_users) || 0);

            chartInstances.activityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total Activities',
                            data: activityData,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Active Users',
                            data: usersData,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            ticks: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                            },
                            grid: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                            }
                        },
                        x: {
                            ticks: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                            },
                            grid: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                            }
                        }
                    }
                }
            });
        }

        /**
         * Open report generator modal
         */
        function openReportGenerator(reportType) {
            document.getElementById('reportType').value = reportType;
            
            // Set default title based on report type
            const reportTypes = <?= json_encode($reportTypes) ?>;
            if (reportTypes[reportType]) {
                document.getElementById('reportTitle').value = reportTypes[reportType].name;
            }
            
            new bootstrap.Modal(document.getElementById('reportGeneratorModal')).show();
        }

        /**
         * Generate quick report
         */
        function generateQuickReport(type, format) {
            showLoading();
            
            const formData = new FormData();
            formData.append('action', 'quick_report');
            formData.append('type', type);
            formData.append('format', format);
            
            fetch('generate.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    if (data.download_url) {
                        // Direct download
                        window.open(data.download_url, '_blank');
                        showSuccess('Report generated successfully!');
                    } else {
                        showSuccess(data.message || 'Report generated successfully!');
                    }
                } else {
                    showError(data.error || 'Failed to generate report');
                }
            })
            .catch(error => {
                hideLoading();
                showError('Network error: ' + error.message);
            });
        }

        /**
         * Generate custom report
         */
        function generateCustomReport() {
            const form = document.getElementById('customReportForm');
            const formData = new FormData(form);
            formData.append('action', 'custom_report');
            
            // Validate form
            const title = document.getElementById('reportTitle').value.trim();
            if (!title) {
                showError('Please enter a report title');
                return;
            }
            
            showLoading();
            bootstrap.Modal.getInstance(document.getElementById('reportGeneratorModal')).hide();
            
            fetch('generate.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    if (data.download_url) {
                        window.open(data.download_url, '_blank');
                        showSuccess('Custom report generated successfully!');
                    } else {
                        showSuccess(data.message || 'Report generated successfully!');
                    }
                } else {
                    showError(data.error || 'Failed to generate custom report');
                }
            })
            .catch(error => {
                hideLoading();
                showError('Network error: ' + error.message);
            });
        }

        /**
         * Refresh dashboard data
         */
        function refreshData() {
            showLoading();
            location.reload();
        }

        /**
         * Cleanup old files
         */
        function cleanupFiles() {
            if (!confirm('Are you sure you want to cleanup old export files? This will remove files older than 7 days.')) {
                return;
            }
            
            showLoading('Cleaning Up Files...', 'Please wait while we remove old export files');
            
            const formData = new FormData();
            formData.append('action', 'cleanup_files');
            
            fetch('generate.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess(data.message || 'Files cleaned up successfully!');
                } else {
                    showError(data.error || 'Failed to cleanup files');
                }
            })
            .catch(error => {
                hideLoading();
                showError('Network error: ' + error.message);
            });
        }

        /**
         * Set default date range (last 30 days)
         */
        function setDefaultDateRange() {
            const today = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);
            
            document.getElementById('filterDateTo').value = today.toISOString().split('T')[0];
            document.getElementById('filterDateFrom').value = thirtyDaysAgo.toISOString().split('T')[0];
        }

        /**
         * Show loading modal with custom message
         */
        function showLoading(title = 'Generating Report...', subtitle = 'Please wait while we prepare your report') {
            document.getElementById('loadingTitle').textContent = title;
            document.getElementById('loadingSubtitle').textContent = subtitle;
            new bootstrap.Modal(document.getElementById('loadingModal')).show();
        }

        /**
         * Hide loading modal
         */
        function hideLoading() {
            const modalEl = document.getElementById('loadingModal');
            if (!modalEl) return;
            
            try {
                const instance = bootstrap.Modal.getInstance(modalEl);
                if (instance) {
                    instance.hide();
                } else {
                    // Create and immediately hide if no instance exists
                    const modal = new bootstrap.Modal(modalEl);
                    modal.hide();
                }
            } catch (e) {
                console.warn('Error hiding modal, using fallback:', e);
            }
            
            // Always run fallback cleanup to ensure modal is hidden
            setTimeout(() => {
                modalEl.classList.remove('show');
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.style.display = 'none';
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('paddingRight');
            }, 100);
        }

        // Ensure loader never persists after navigation or tab visibility changes
        window.addEventListener('pageshow', () => hideLoading());
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') hideLoading();
        });
        window.addEventListener('beforeunload', () => hideLoading());

        /**
         * Show success message
         */
        function showSuccess(message) {
            // Remove existing alerts
            document.querySelectorAll('.alert').forEach(alert => alert.remove());
            
            // Create new alert
            const alertHtml = `
                <div class="alert alert-success fade-in">
                    <i class="fas fa-check-circle me-2"></i>
                    ${message}
                </div>
            `;
            
            // Insert at top of main content
            const mainContent = document.querySelector('.main-content');
            mainContent.insertAdjacentHTML('afterbegin', alertHtml);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                document.querySelector('.alert-success')?.remove();
            }, 5000);
        }

        /**
         * Show error message
         */
        function showError(message) {
            // Remove existing alerts
            document.querySelectorAll('.alert').forEach(alert => alert.remove());
            
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
         * Theme toggle functionality
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
            
            // Reinitialize charts with new theme colors
            setTimeout(() => {
                initializeCharts();
            }, 300);
        }

        // Sidebar JS handlers removed to prevent unintended expansion on single link clicks.
        // Pages will rely on static CSS margins as before.

        // Initialize charts on DOM load
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });

        // Make functions available globally
        window.openReportGenerator = openReportGenerator;
        window.generateQuickReport = generateQuickReport;
        window.generateCustomReport = generateCustomReport;
        window.refreshData = refreshData;
        window.cleanupFiles = cleanupFiles;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>