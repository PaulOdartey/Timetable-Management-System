<?php
/**
 * Faculty Export Page
 * Timetable Management System
 * 
 * Export functionality for faculty members
 * Allows exporting schedules, student lists, and subject information
 */

// Start session and security checks
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Export_Helper.php';

// Ensure user is logged in and has faculty role
User::requireLogin();
User::requireRole('faculty');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables
$exportData = [
    'faculty_info' => [],
    'export_stats' => [],
    'available_exports' => [],
    'recent_exports' => []
];

// Get current academic year and semester
$currentYear = '2025-2026';
$currentSemester = 1;

try {
    // Get faculty info
    $facultyInfo = $db->fetchRow("
        SELECT f.*, u.email, u.username
        FROM faculty f 
        JOIN users u ON f.user_id = u.user_id 
        WHERE f.user_id = ?
    ", [$userId]);

    if ($facultyInfo) {
        $exportData['faculty_info'] = $facultyInfo;
        $facultyId = $facultyInfo['faculty_id'];
    } else {
        throw new Exception('Faculty information not found');
    }

    // Get export statistics
    $assignedSubjects = $db->fetchColumn("
        SELECT COUNT(*) 
        FROM faculty_subjects fs 
        JOIN subjects s ON fs.subject_id = s.subject_id 
        WHERE fs.faculty_id = ? AND fs.is_active = 1 AND s.is_active = 1
    ", [$facultyId]);

    $totalStudents = $db->fetchColumn("
        SELECT COUNT(DISTINCT e.student_id)
        FROM enrollments e
        JOIN faculty_subjects fs ON e.subject_id = fs.subject_id
        WHERE fs.faculty_id = ? AND e.status = 'enrolled'
        AND (e.academic_year = '2025-2026' OR e.academic_year = '2025-26')
        AND e.semester = ?
    ", [$facultyId, $currentSemester]);

    $scheduledClasses = $db->fetchColumn("
        SELECT COUNT(*)
        FROM timetables t
        WHERE t.faculty_id = ? AND t.is_active = 1
        AND (t.academic_year = '2025-2026' OR t.academic_year = '2025-26')
        AND t.semester = ?
    ", [$facultyId, $currentSemester]);

    $totalSections = $db->fetchColumn("
        SELECT COUNT(DISTINCT t.section)
        FROM timetables t
        WHERE t.faculty_id = ? AND t.is_active = 1
        AND (t.academic_year = '2025-2026' OR t.academic_year = '2025-26')
        AND t.semester = ?
    ", [$facultyId, $currentSemester]);

    $exportData['export_stats'] = [
        'assigned_subjects' => $assignedSubjects,
        'total_students' => $totalStudents,
        'scheduled_classes' => $scheduledClasses,
        'total_sections' => $totalSections
    ];

    // Define available export types
    $exportData['available_exports'] = [
        [
            'id' => 'teaching_schedule',
            'title' => 'Teaching Schedule',
            'description' => 'Export your complete teaching timetable with subject details, timings, and classroom information',
            'icon' => '📅',
            'formats' => ['PDF', 'Excel'],
            'data_count' => $scheduledClasses,
            'last_updated' => 'Real-time'
        ],
        [
            'id' => 'student_lists',
            'title' => 'Student Lists',
            'description' => 'Export lists of students enrolled in your subjects with contact information and academic details',
            'icon' => '👥',
            'formats' => ['PDF', 'Excel'],
            'data_count' => $totalStudents,
            'last_updated' => 'Real-time'
        ],
        [
            'id' => 'subject_assignments',
            'title' => 'Subject Assignments',
            'description' => 'Export your assigned subjects with detailed information including credits, duration, and descriptions',
            'icon' => '📚',
            'formats' => ['PDF', 'Excel'],
            'data_count' => $assignedSubjects,
            'last_updated' => 'Real-time'
        ],
        [
            'id' => 'class_attendance',
            'title' => 'Class Summary',
            'description' => 'Export summary of your classes including enrollment numbers, sections, and classroom assignments',
            'icon' => '📊',
            'formats' => ['PDF', 'Excel'],
            'data_count' => $scheduledClasses,
            'last_updated' => 'Real-time'
        ]
    ];

} catch (Exception $e) {
    error_log("Faculty Export Error: " . $e->getMessage());
    $error_message = "Unable to load export data. Please try again later.";
}

// Handle export requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_type'])) {
    try {
        $exportService = new ExportService();
        $exportType = $_POST['export_type'];
        $format = $_POST['format'] ?? 'pdf';
        
        $result = ['success' => false, 'message' => 'Export type not found'];
        
        switch ($exportType) {
            case 'teaching_schedule':
                $result = $exportService->exportFacultySchedule($facultyId, $format);
                break;
                
            case 'student_lists':
                $result = $exportService->exportFacultyStudents($facultyId, $format);
                break;
                
            case 'subject_assignments':
                // Use the dedicated subject assignments export with landscape orientation
                $result = $exportService->exportFacultySubjectAssignments($facultyId, $format);
                break;
                
            case 'class_attendance':
                // Use the dedicated class summary export with landscape orientation
                $result = $exportService->exportFacultyClassSummary($facultyId, $format, $currentSemester);
                break;
        }
        
        if ($result['success']) {
            // Log the export activity
            $db->execute("
                INSERT INTO audit_logs (user_id, action, description, timestamp)
                VALUES (?, 'EXPORT_DATA', ?, NOW())
            ", [$userId, "Exported {$exportType} in {$format} format"]);
            
            // Return download response
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        } else {
            // Return error response as JSON and exit
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Export failed']);
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Export Error: " . $e->getMessage());
        // Return error response as JSON and exit
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Export failed. Please try again later.']);
        exit;
    }
}

// Set page title
$pageTitle = "Export Data";
$currentPage = "export";

// Helper function to format numbers
function formatCount($count) {
    if ($count >= 1000) {
        return number_format($count / 1000, 1) . 'K';
    }
    return number_format($count);
}
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
    
    <!-- Custom Styles -->
    <style>
        /* Import shared CSS variables and base styles */
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
            --navbar-height: 64px;
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

        /* Dark mode border overrides */
        [data-theme="dark"] .form-control {
            border: 2px solid var(--border-color);
        }

        [data-theme="dark"] .export-card {
            border: 2px solid var(--border-color);
        }

        [data-theme="dark"] .file-item {
            border: 2px solid var(--border-color);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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

        /* Sidebar collapsed state */
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
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
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

        .btn-action {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            border: none;
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

        /* Summary Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            padding: 1.5rem;
            text-align: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .stat-icon.subjects {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.students {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon.classes {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon.sections {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Export Container */
        .export-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
        }

        .export-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .export-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .view-toggles {
            display: flex;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.25rem;
        }

        .toggle-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: none;
            color: var(--text-secondary);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .toggle-btn.active {
            background: var(--primary-color);
            color: white;
        }

        /* Export Grid */
        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--bg-secondary);
            border: 2px solid #cbd5e1; /* stronger light-mode border */
            border-radius: 12px;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .export-card {
            background: var(--bg-primary);
            border: 2px solid #cbd5e1; /* stronger light-mode border */
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .export-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .export-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
        }

        .export-header-content {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .export-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .export-card-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .export-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.4;
            margin-bottom: 1rem;
        }

        .export-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 0.75rem;
            color: var(--text-tertiary);
        }

        .export-formats {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .format-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--primary-color-alpha);
            color: var(--primary-color);
        }

        .export-actions {
            display: flex;
            gap: 0.5rem;
        }

        .export-btn {
            flex: 1;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .export-btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .export-btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }

        .export-btn-secondary {
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .export-btn-secondary:hover {
            background: rgba(0, 0, 0, 0.1);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #cbd5e1; /* stronger light-mode border */
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        /* Export List View */
        .export-list {
            display: none;
        }

        .export-list.active {
            display: block;
        }

        .list-export {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .list-export:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .list-export-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .list-export-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .list-export-icon {
            font-size: 1.5rem;
        }

        .list-export-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .list-export-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Loading States */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-exports {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-exports svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 0px;
                --sidebar-collapsed-width: 0px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-content h1 {
                font-size: 2rem;
            }

            .export-container {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            /* Hide the header refresh button on mobile */
            .refresh-btn {
                display: none !important;
            }

            .export-grid {
                display: none !important;
            }

            .export-list {
                display: block !important;
            }

            .toggle-btn {
                display: none;
            }

            .export-actions {
                flex-direction: column;
            }

            .list-export-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .list-export-actions {
                flex-wrap: wrap;
                justify-content: flex-start;
            }

            /* Stronger, more visible borders on mobile list view cards */
            .list-export {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .list-export {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .export-grid {
                grid-template-columns: 1fr;
            }

            .export-actions {
                gap: 0.25rem;
            }

            .export-btn {
                font-size: 0.75rem;
                padding: 0.5rem 0.75rem;
            }
        }

        /* Success/Error Messages */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: none;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Print Styles */
        @media print {
            body { background: white !important; }
            .glass-card { 
                background: white !important;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
            .header-actions { display: none; }
            .view-toggles { display: none; }
            .export-actions { display: none; }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>


    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-card glass-card fade-in">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">📥My Exports</h1>
                    </div>
                    <button class="btn-action btn-success refresh-btn" onclick="location.reload()">
                        🔄 Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <?php if (!empty($exportData['export_stats'])): ?>
            <div class="stats-grid">
                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon subjects">📚</div>
                    <div class="stat-number"><?= $exportData['export_stats']['assigned_subjects'] ?></div>
                    <div class="stat-label">Assigned Subjects</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon students">👥</div>
                    <div class="stat-number"><?= $exportData['export_stats']['total_students'] ?></div>
                    <div class="stat-label">Total Students</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon classes">🏫</div>
                    <div class="stat-number"><?= $exportData['export_stats']['scheduled_classes'] ?></div>
                    <div class="stat-label">Scheduled Classes</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon sections">⭐</div>
                    <div class="stat-number"><?= $exportData['export_stats']['total_sections'] ?></div>
                    <div class="stat-label">Total Sections</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Export Display -->
        <div class="export-container">
            <div class="export-header">
                <h2 class="export-title">Available Exports</h2>
                <div class="view-toggles">
                    <button class="toggle-btn active" onclick="switchView('grid')">Grid View</button>
                    <button class="toggle-btn" onclick="switchView('list')">List View</button>
                </div>
            </div>

            <!-- Display any error messages -->
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($export_error)): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Export Error:</strong> <?= htmlspecialchars($export_error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($exportData['available_exports'])): ?>
                <!-- Grid View -->
                <div class="export-grid" id="gridView">
                    <?php foreach ($exportData['available_exports'] as $export): ?>
                        <div class="export-card">
                            <div class="export-header-content">
                                <div>
                                    <div class="export-icon"><?= $export['icon'] ?></div>
                                    <div class="export-card-title"><?= htmlspecialchars($export['title']) ?></div>
                                </div>
                            </div>

                            <div class="export-description">
                                <?= htmlspecialchars($export['description']) ?>
                            </div>

                            <div class="export-meta">
                                <span><?= formatCount($export['data_count']) ?> records available</span>
                                <span>Updated: <?= htmlspecialchars($export['last_updated']) ?></span>
                            </div>

                            <div class="export-formats">
                                <?php foreach ($export['formats'] as $format): ?>
                                    <span class="format-badge"><?= htmlspecialchars($format) ?></span>
                                <?php endforeach; ?>
                            </div>

                            <div class="export-actions">
                                <button class="export-btn export-btn-primary" onclick="exportData('<?= $export['id'] ?>', 'pdf')">
                                    📄 Export PDF
                                </button>
                                <button class="export-btn export-btn-secondary" onclick="exportData('<?= $export['id'] ?>', 'excel')">
                                    📊 Export Excel
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- List View -->
                <div class="export-list" id="listView">
                    <?php foreach ($exportData['available_exports'] as $export): ?>
                        <div class="list-export">
                            <div class="list-export-header">
                                <div class="list-export-title">
                                    <div class="list-export-icon"><?= $export['icon'] ?></div>
                                    <div class="list-export-name"><?= htmlspecialchars($export['title']) ?></div>
                                </div>
                                <div class="list-export-actions">
                                    <button class="export-btn export-btn-primary" onclick="exportData('<?= $export['id'] ?>', 'pdf')">
                                        📄 PDF
                                    </button>
                                    <button class="export-btn export-btn-secondary" onclick="exportData('<?= $export['id'] ?>', 'excel')">
                                        📊 Excel
                                    </button>
                                </div>
                            </div>
                            <div class="export-description">
                                <?= htmlspecialchars($export['description']) ?>
                            </div>
                            <div class="export-meta">
                                <span><strong><?= formatCount($export['data_count']) ?></strong> records available</span>
                                <span>Last updated: <?= htmlspecialchars($export['last_updated']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-exports">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <h3>No Export Data Available</h3>
                    <p>You don't have any data available for export at this time.</p>
                    <p style="margin-top: 0.5rem;">Please check back once you have teaching assignments.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize export functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Apply theme
            applyCurrentTheme();
            
            // Handle responsive view switching
            handleResponsiveView();

            // Listen for sidebar toggle events
            handleSidebarToggle();
        });

        // Sidebar toggle handling (no sticky header)
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

            // Check for existing sidebar state on load
            const sidebar = document.querySelector('.tms-sidebar');
            if (sidebar) {
                // Check if sidebar is collapsed
                if (sidebar.classList.contains('collapsed')) {
                    document.body.classList.add('sidebar-collapsed');
                }
                
                // For mobile, always treat as collapsed
                if (window.innerWidth <= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                }
            }

            // Handle window resize for responsive behavior
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 1024) {
                    // Mobile: always collapsed state
                    document.body.classList.add('sidebar-collapsed');
                } else {
                    // Desktop: check actual sidebar state
                    const sidebar = document.querySelector('.tms-sidebar');
                    if (sidebar) {
                        if (sidebar.classList.contains('collapsed')) {
                            document.body.classList.add('sidebar-collapsed');
                        } else {
                            document.body.classList.remove('sidebar-collapsed');
                        }
                    }
                }
            });
        }

        // View switching functionality
        function switchView(viewType) {
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const toggleBtns = document.querySelectorAll('.toggle-btn');

            // Remove active class from all buttons
            toggleBtns.forEach(btn => btn.classList.remove('active'));

            if (viewType === 'grid') {
                gridView.style.display = 'grid';
                listView.style.display = 'none';
                document.querySelector('.toggle-btn[onclick*="grid"]').classList.add('active');
                
                // No sticky header buttons to update
            } else {
                gridView.style.display = 'none';
                listView.style.display = 'block';
                document.querySelector('.toggle-btn[onclick*="list"]').classList.add('active');
                // No sticky header buttons to update
            }
        }

        // Export data function
        function exportData(exportType, format) {
            const button = event.target;
            const originalText = button.innerHTML;
            
            // Show loading state
            button.classList.add('loading');
            button.disabled = true;
            button.innerHTML = 'Exporting...';
            
            // Create form data
            const formData = new FormData();
            formData.append('export_type', exportType);
            formData.append('format', format);
            
            // Make the request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create download link
                    const link = document.createElement('a');
                    link.href = data.download_url;
                    link.download = data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Show success message
                    showNotification('Export completed successfully!', 'success');
                } else {
                    throw new Error(data.error || 'Export failed');
                }
            })
            .catch(error => {
                console.error('Export error:', error);
                showNotification('Export failed: ' + error.message, 'error');
            })
            .finally(() => {
                // Reset button state
                button.classList.remove('loading');
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }

        // Export all data
        function exportAll() {
            if (confirm('This will export all your data. Continue?')) {
                const exports = ['teaching_schedule', 'student_lists', 'subject_assignments', 'class_attendance'];
                let completed = 0;
                
                showNotification('Starting bulk export...', 'info');
                
                exports.forEach((exportType, index) => {
                    setTimeout(() => {
                        exportData(exportType, 'pdf');
                        completed++;
                        
                        if (completed === exports.length) {
                            setTimeout(() => {
                                showNotification('All exports completed!', 'success');
                            }, 1000);
                        }
                    }, index * 2000); // Stagger exports by 2 seconds
                });
            }
        }

        // Refresh data
        function refreshData() {
            location.reload();
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'}`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.minWidth = '300px';
            notification.innerHTML = `<strong>${type.charAt(0).toUpperCase() + type.slice(1)}:</strong> ${message}`;
            
            document.body.appendChild(notification);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // Apply current theme
        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Handle responsive view switching
        function handleResponsiveView() {
            function checkScreenSize() {
                if (window.innerWidth <= 768) {
                    switchView('list');
                } else {
                    switchView('grid');
                }
            }

            // Check on load
            checkScreenSize();

            // Check on resize
            window.addEventListener('resize', checkScreenSize);
        }

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + E for Export All
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                exportAll();
            }

            // Ctrl/Cmd + R for Refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                refreshData();
            }

            // G for Grid view
            if (e.key === 'g' && !e.ctrlKey && !e.metaKey) {
                switchView('grid');
            }

            // L for List view
            if (e.key === 'l' && !e.ctrlKey && !e.metaKey) {
                switchView('list');
            }
        });

        // Listen for theme changes
        window.addEventListener('themeChanged', function(event) {
            applyCurrentTheme();
        });

        // Add smooth scrolling
        window.addEventListener('load', function() {
            document.documentElement.style.scrollBehavior = 'smooth';
        });

        // Enhanced hover effects for cards
        const cards = document.querySelectorAll('.glass-card, .export-card, .list-export');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Intersection Observer for animation
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all cards
        document.querySelectorAll('.stat-card, .export-card, .list-export').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>

<?php
/**
 * Helper function for formatting data counts
 * @param int $count
 * @return string
 */
function formatDataSize($count) {
    if ($count >= 1000000) {
        return number_format($count / 1000000, 1) . 'M';
    } elseif ($count >= 1000) {
        return number_format($count / 1000, 1) . 'K';
    }
    return number_format($count);
}

/**
 * Helper function to get export type icon
 * @param string $type
 * @return string
 */
function getExportIcon($type) {
    $icons = [
        'teaching_schedule' => '📅',
        'student_lists' => '👥',
        'subject_assignments' => '📚',
        'class_attendance' => '📊'
    ];
    return $icons[$type] ?? '📄';
}

/**
 * Helper function to validate export permissions
 * @param string $type
 * @param int $facultyId
 * @return bool
 */
function canExport($type, $facultyId) {
    // Faculty can export their own data
    $allowedTypes = ['teaching_schedule', 'student_lists', 'subject_assignments', 'class_attendance'];
    return in_array($type, $allowedTypes);
}
?>