<?php
/**
 * Admin Classroom Management - Edit Classroom
 * Timetable Management System
 * 
 * Professional interface for admin to edit existing classroom details including
 * room information, capacity, equipment, and department assignments
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Classroom.php';

defined('SYSTEM_ACCESS') or die('Direct access denied');

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$classroomManager = new Classroom();

// Get classroom ID from URL
$classroomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$classroomId) {
    header('Location: index.php?error=' . urlencode('Invalid classroom ID'));
    exit;
}

// Initialize variables
$classroom = null;
$departments = [];
$buildings = [];
$error_message = '';
$success_message = '';
$formData = [];

// Get classroom data
try {
    $classroom = $classroomManager->getClassroomById($classroomId);
    if (!$classroom) {
        header('Location: index.php?error=' . urlencode('Classroom not found'));
        exit;
    }
    
    // Populate form data with existing classroom info
    $formData = [
        'classroom_id' => $classroom['classroom_id'],
        'room_number' => $classroom['room_number'],
        'building' => $classroom['building'],
        'floor' => $classroom['floor'],
        'capacity' => $classroom['capacity'],
        'type' => $classroom['type'],
        'equipment' => $classroom['equipment'],
        'status' => $classroom['status'],
        'is_active' => $classroom['is_active'],
        'department_id' => $classroom['department_id'],
        'is_shared' => $classroom['is_shared'],
        'facilities' => $classroom['facilities']
    ];
    
    // Parse facilities JSON if it exists
    if (!empty($classroom['facilities'])) {
        $facilities = json_decode($classroom['facilities'], true);
        if ($facilities) {
            $formData['facilities'] = $facilities;
        }
    }
    
} catch (Exception $e) {
    error_log("Error fetching classroom: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('Failed to load classroom data'));
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Collect form data
        $updateData = [
            'room_number' => trim($_POST['room_number'] ?? ''),
            'building' => trim($_POST['building'] ?? ''),
            'floor' => !empty($_POST['floor']) ? (int)$_POST['floor'] : null,
            'capacity' => (int)($_POST['capacity'] ?? 0),
            'type' => $_POST['type'] ?? '',
            'equipment' => trim($_POST['equipment'] ?? ''),
            'status' => $_POST['status'] ?? 'available',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'department_id' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
            'is_shared' => isset($_POST['is_shared']) ? 1 : 0
        ];
        
        // Handle facilities
        $selectedFacilities = $_POST['facilities'] ?? [];
        if (!empty($selectedFacilities) && is_array($selectedFacilities)) {
            $updateData['facilities'] = $selectedFacilities;
        } else {
            $updateData['facilities'] = [];
        }
        
        // Store form data for repopulation on error
        $formData = array_merge($formData, $updateData);
        
        // Update classroom
        $result = $classroomManager->updateClassroom($classroomId, $updateData);
        
        if ($result['success']) {
            $success_message = $result['message'];
            
            // Refresh classroom data to show updated info
            $classroom = $classroomManager->getClassroomById($classroomId);
            
            // Auto-redirect after success
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'index.php?success=" . urlencode($result['message']) . "';
                }, 2000);
            </script>";
        } else {
            $error_message = $result['message'];
        }
        
    } catch (Exception $e) {
        error_log("Error updating classroom: " . $e->getMessage());
        $error_message = "An unexpected error occurred while updating the classroom.";
    }
}

// Get departments for dropdown
try {
    $departments = $db->fetchAll("
        SELECT department_id, department_name, department_code 
        FROM departments 
        WHERE is_active = 1 
        ORDER BY department_name ASC
    ");
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $departments = [];
}

// Get buildings for dropdown
try {
    $buildings = $classroomManager->getBuildings();
} catch (Exception $e) {
    error_log("Error fetching buildings: " . $e->getMessage());
    $buildings = [];
}

// Available facilities
$availableFacilities = [
    'projector' => 'Projector',
    'whiteboard' => 'Whiteboard',
    'smartboard' => 'Smart Board',
    'audio_system' => 'Audio System',
    'video_conferencing' => 'Video Conferencing',
    'air_conditioning' => 'Air Conditioning',
    'wifi' => 'WiFi',
    'power_outlets' => 'Power Outlets',
    'laboratory_equipment' => 'Laboratory Equipment',
    'computer_station' => 'Computer Station',
    'stage' => 'Stage/Platform',
    'microphone' => 'Microphone System'
];

// Set page title
$pageTitle = "Edit Classroom";
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

        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        :root { --navbar-height: 64px; }


        /* Page Header */
        .page-header {
            position: sticky;
            top: var(--navbar-height);
            z-index: 998;
            margin-bottom: 1rem;
            margin-top: 1rem;
        }

        .header-card {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
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

        .page-header.compact .header-text { 
            display: flex; 
            flex-direction: column; 
        }

        .page-header.compact .page-title {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .page-header.compact .page-subtitle {
            font-size: 0.95rem;
            margin-bottom: 0;
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
            cursor: pointer;
        }

        .btn-action.btn-sm { 
            padding: 0.4rem 0.75rem; 
            border-radius: 10px; 
            font-size: 0.8rem; 
        }

        .page-header .btn-action.btn-sm .back-icon { 
            font-size: 1.2rem; 
            line-height: 1; 
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

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
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

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
        }

        /* Form Styling */
        .form-container {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 2rem;
        }

        [data-theme="dark"] .form-container {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color-alpha);
            position: relative;
        }

        .form-section-title::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: var(--primary-color);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-label.required::after {
            content: ' *';
            color: var(--error-color);
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.7);
            color: var(--text-primary);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
            background: rgba(255, 255, 255, 0.9);
        }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
        }

        [data-theme="dark"] .form-select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .form-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Type Selection */
        .type-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .type-card {
            padding: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
            text-align: center;
            position: relative;
        }

        .type-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .type-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
            transform: translateY(-2px);
        }

        .type-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .type-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .type-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .type-description {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        [data-theme="dark"] .type-card {
            background: rgba(0, 0, 0, 0.2);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .type-card.selected {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.2);
        }

        /* Facilities Checkboxes */
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .facility-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .facility-item:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .facility-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-radius: 4px;
        }

        .facility-item input[type="checkbox"]:checked {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .facility-label {
            font-size: 0.875rem;
            color: var(--text-primary);
            cursor: pointer;
            user-select: none;
        }

        [data-theme="dark"] .facility-item {
            background: rgba(0, 0, 0, 0.2);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .facility-item:hover {
            background: rgba(0, 0, 0, 0.3);
        }

        /* Switch styling */
        .form-switch .form-check-input {
            width: 2.5rem;
            height: 1.25rem;
            border-radius: 1rem;
            background: rgba(100, 116, 139, 0.3);
            border: none;
        }

        .form-switch .form-check-input:checked {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-switch .form-check-input:focus {
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        /* Alert Styling */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            border-left: 4px solid;
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

        /* Current Values Display */
        .current-values {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .current-values h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .current-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
        }

        .current-item {
            font-size: 0.8125rem;
        }

        .current-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .current-value {
            color: var(--text-primary);
            font-weight: 600;
        }

        [data-theme="dark"] .current-values {
            background: rgba(102, 126, 234, 0.15);
            border-color: rgba(102, 126, 234, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 1rem; }
            .page-title { font-size: 2rem; }
            .form-container { padding: 1.5rem; }
            .current-values { padding: 0.75rem; grid-template-columns: 1fr; }
            .current-info { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .type-selection {
                grid-template-columns: 1fr;
            }

            .facilities-grid {
                grid-template-columns: 1fr;
            }

            .current-info {
                grid-template-columns: 1fr;
            }

            .page-title { font-size: 1.75rem; }
            .page-subtitle { font-size: 1rem; }
            .current-values h4 { font-size: 1rem; }
            .current-values { gap: 0.75rem; }
            .form-container { padding: 1.25rem; }

            .sticky-header {
                padding: 0.5rem;
            }

            .sticky-header-text h3 {
                font-size: 1rem;
            }

            .sticky-header-text p {
                font-size: 0.8rem;
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

        /* Enhanced Switch styling (professional look) */
        .form-switch .form-check-input {
            position: relative;
            width: 2.25rem;        /* 36px */
            height: 1.25rem;       /* 20px */
            background: rgba(100, 116, 139, 0.35);
            border: 1px solid rgba(100, 116, 139, 0.4);
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.08);
            transition: background 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .form-switch .form-check-input::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: calc(1.25rem - 4px);
            height: calc(1.25rem - 4px);
            background: #ffffff;
            border-radius: 999px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.04);
            transform: translateX(0);
            transition: transform 0.25s ease;
        }

        .form-switch .form-check-input:hover {
            box-shadow: inset 0 2px 6px rgba(0,0,0,0.12);
        }

        .form-switch .form-check-input:focus {
            outline: none;
            box-shadow: 0 0 0 2px var(--primary-color-alpha);
        }

        .form-switch .form-check-input:checked {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-switch .form-check-input:checked::before {
            transform: translateX(1rem); /* move knob to the right (width - height) */
        }

        /* Dark mode adjustments */
        [data-theme="dark"] .form-switch .form-check-input {
            background: rgba(148, 163, 184, 0.3);
            border-color: rgba(148, 163, 184, 0.35);
        }

        [data-theme="dark"] .form-switch .form-check-input::before {
            background: #e5e7eb;
            box-shadow: 0 2px 6px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05);
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
        <!-- Page Header -->
        <div class="page-header compact">
            <div class="header-card glass-card fade-in">
                <div class="header-text">
                    <h1 class="page-title">✏️ Edit Classroom</h1>
                </div>
                <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                    <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success glass-card" role="alert">
                <strong>Success!</strong> <?= htmlspecialchars($success_message) ?>
                <br><small>Redirecting to classroom list...</small>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Current Classroom Information -->
        <div class="current-values glass-card fade-in">
            <h4>📋 Current Classroom Information</h4>
            <div class="current-info">
                <div class="current-item">
                    <div class="current-label">Room Number:</div>
                    <div class="current-value"><?= htmlspecialchars($classroom['room_number']) ?></div>
                </div>
                <div class="current-item">
                    <div class="current-label">Building:</div>
                    <div class="current-value"><?= htmlspecialchars($classroom['building']) ?></div>
                </div>
                <div class="current-item">
                    <div class="current-label">Type:</div>
                    <div class="current-value"><?= ucfirst(htmlspecialchars($classroom['type'])) ?></div>
                </div>
                <div class="current-item">
                    <div class="current-label">Capacity:</div>
                    <div class="current-value"><?= htmlspecialchars($classroom['capacity']) ?> students</div>
                </div>
                <div class="current-item">
                    <div class="current-label">Status:</div>
                    <div class="current-value"><?= ucfirst(htmlspecialchars($classroom['status'])) ?></div>
                </div>
                <div class="current-item">
                    <div class="current-label">Department:</div>
                    <div class="current-value"><?= htmlspecialchars($classroom['department_name'] ?? 'Shared/General') ?></div>
                </div>
                <div class="current-item">
                    <div class="current-label">Active Schedules:</div>
                    <div class="current-value"><?= htmlspecialchars($classroom['active_schedules'] ?? 0) ?> schedules</div>
                </div>
                <div class="current-item">
                    <div class="current-label">Last Updated:</div>
                    <div class="current-value"><?= date('M j, Y g:i A', strtotime($classroom['updated_at'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Edit Classroom Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" id="editClassroomForm" novalidate>
                <!-- Room Type Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">🏫 Room Type</h3>
                    <div class="type-selection">
                        <div class="type-card <?= ($formData['type'] ?? '') === 'lecture' ? 'selected' : '' ?>" onclick="selectType('lecture')">
                            <input type="radio" name="type" value="lecture" id="type_lecture"
                                   <?= ($formData['type'] ?? '') === 'lecture' ? 'checked' : '' ?> required>
                            <div class="type-icon">🎓</div>
                            <div class="type-title">Lecture Hall</div>
                            <div class="type-description">Large rooms for theoretical classes</div>
                        </div>
                        
                        <div class="type-card <?= ($formData['type'] ?? '') === 'lab' ? 'selected' : '' ?>" onclick="selectType('lab')">
                            <input type="radio" name="type" value="lab" id="type_lab"
                                   <?= ($formData['type'] ?? '') === 'lab' ? 'checked' : '' ?> required>
                            <div class="type-icon">🔬</div>
                            <div class="type-title">Laboratory</div>
                            <div class="type-description">Equipped rooms for practical work</div>
                        </div>
                        
                        <div class="type-card <?= ($formData['type'] ?? '') === 'seminar' ? 'selected' : '' ?>" onclick="selectType('seminar')">
                            <input type="radio" name="type" value="seminar" id="type_seminar"
                                   <?= ($formData['type'] ?? '') === 'seminar' ? 'checked' : '' ?> required>
                            <div class="type-icon">💬</div>
                            <div class="type-title">Seminar Room</div>
                            <div class="type-description">Small rooms for discussions</div>
                        </div>
                        
                        <div class="type-card <?= ($formData['type'] ?? '') === 'auditorium' ? 'selected' : '' ?>" onclick="selectType('auditorium')">
                            <input type="radio" name="type" value="auditorium" id="type_auditorium"
                                   <?= ($formData['type'] ?? '') === 'auditorium' ? 'checked' : '' ?> required>
                            <div class="type-icon">🎭</div>
                            <div class="type-title">Auditorium</div>
                            <div class="type-description">Large venues for events</div>
                        </div>
                    </div>
                </div>

                <!-- Basic Room Information -->
                <div class="form-section">
                    <h3 class="form-section-title">ℹ️ Room Information</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="room_number" class="form-label required">Room Number</label>
                            <input type="text" class="form-control" id="room_number" name="room_number" 
                                   value="<?= htmlspecialchars($formData['room_number'] ?? '') ?>" 
                                   placeholder="e.g., 101, A-205, Lab-3" required>
                            <div class="form-text">Unique identifier for the room</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="building" class="form-label required">Building</label>
                            <input type="text" class="form-control" id="building" name="building" 
                                   value="<?= htmlspecialchars($formData['building'] ?? '') ?>" 
                                   placeholder="e.g., Main Building, Science Block" 
                                   list="building_list" required>
                            <datalist id="building_list">
                                <?php foreach ($buildings as $building): ?>
                                    <option value="<?= htmlspecialchars($building['building']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Building or block name</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="floor" class="form-label">Floor</label>
                            <input type="number" class="form-control" id="floor" name="floor" 
                                   value="<?= htmlspecialchars($formData['floor'] ?? '') ?>" 
                                   placeholder="1" min="0" max="50">
                            <div class="form-text">Floor number (optional)</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="capacity" class="form-label required">Capacity</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" 
                                   value="<?= htmlspecialchars($formData['capacity'] ?? '') ?>" 
                                   placeholder="30" min="1" max="1000" required>
                            <div class="form-text">Maximum number of students</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label required">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Select Status</option>
                                <option value="available" <?= ($formData['status'] ?? '') === 'available' ? 'selected' : '' ?>>Available</option>
                                <option value="maintenance" <?= ($formData['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                                <option value="reserved" <?= ($formData['status'] ?? '') === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                                <option value="closed" <?= ($formData['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                            <div class="form-text">Current room availability status</div>
                        </div>
                    </div>
                </div>

                <!-- Assignment & Settings -->
                <div class="form-section">
                    <h3 class="form-section-title">⚙️ Assignment & Settings</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Assigned Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">General/Shared Room</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>" 
                                            <?= ($formData['department_id'] ?? '') == $dept['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?> (<?= htmlspecialchars($dept['department_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Leave empty for shared rooms</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex flex-column gap-3 pt-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           <?= ($formData['is_active'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        <strong>Active Room</strong>
                                        <div class="form-text">Allow scheduling for this room</div>
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_shared" name="is_shared" 
                                           <?= ($formData['is_shared'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_shared">
                                        <strong>Shared Room</strong>
                                        <div class="form-text">Available for multiple departments</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Equipment & Description -->
                <div class="form-section">
                    <h3 class="form-section-title">🛠️ Equipment & Description</h3>
                    <div class="mb-3">
                        <label for="equipment" class="form-label">Equipment Description</label>
                        <textarea class="form-control" id="equipment" name="equipment" rows="3" 
                                  placeholder="Describe available equipment, tools, or special features..."><?= htmlspecialchars($formData['equipment'] ?? '') ?></textarea>
                        <div class="form-text">Optional description of room equipment and features</div>
                    </div>
                </div>

                <!-- Available Facilities -->
                <div class="form-section">
                    <h3 class="form-section-title">🏢 Available Facilities</h3>
                    <div class="facilities-grid">
                        <?php 
                        $selectedFacilities = $formData['facilities'] ?? [];
                        if (is_string($selectedFacilities)) {
                            $selectedFacilities = json_decode($selectedFacilities, true) ?? [];
                        }
                        
                        foreach ($availableFacilities as $key => $label): 
                        ?>
                            <div class="facility-item">
                                <input type="checkbox" id="facility_<?= $key ?>" name="facilities[]" 
                                       value="<?= $key ?>" 
                                       <?= in_array($key, $selectedFacilities) ? 'checked' : '' ?>>
                                <label for="facility_<?= $key ?>" class="facility-label">
                                    <?= htmlspecialchars($label) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text mt-2">Select all applicable facilities available in this room</div>
                </div>

                <!-- Form Actions -->
                <div class="form-section">
                    <div class="d-flex gap-3 justify-content-end flex-wrap">
                        <a href="index.php" class="btn-action btn-outline">
                            ❌ Cancel
                        </a>
                        <button type="button" class="btn-action btn-warning" onclick="resetToOriginal()">
                            🔄 Reset to Original
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn">
                            💾 Update Classroom
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Store original values for reset functionality
            const originalValues = {
                room_number: <?= json_encode($classroom['room_number']) ?>,
                building: <?= json_encode($classroom['building']) ?>,
                floor: <?= json_encode($classroom['floor']) ?>,
                capacity: <?= json_encode($classroom['capacity']) ?>,
                type: <?= json_encode($classroom['type']) ?>,
                equipment: <?= json_encode($classroom['equipment']) ?>,
                status: <?= json_encode($classroom['status']) ?>,
                is_active: <?= json_encode((bool)$classroom['is_active']) ?>,
                department_id: <?= json_encode($classroom['department_id']) ?>,
                is_shared: <?= json_encode((bool)$classroom['is_shared']) ?>,
                facilities: <?= json_encode(json_decode($classroom['facilities'] ?? '[]', true)) ?>
            };
            
            // Initialize form
            initializeForm();
            
            // Form validation
            setupFormValidation();
            
            // Auto-save prevention on page unload
            setupUnsavedChangesWarning();
        });
        
        function applyCurrentTheme() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', currentTheme);
        }
        
        function initializeForm() {
            // Show/hide role-specific sections based on current selection
            updateRoleSpecificSections();
            
            // Update submit button state
            updateSubmitButton();
            
            // Add event listeners
            const form = document.getElementById('editClassroomForm');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    updateSubmitButton();
                    markFormAsChanged();
                });
                
                input.addEventListener('input', function() {
                    updateSubmitButton();
                    markFormAsChanged();
                });
            });
        }
        
        
        
        function setupFormValidation() {
            const form = document.getElementById('editClassroomForm');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!validateForm()) {
                    return false;
                }
                
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '⏳ Updating...';
                submitBtn.disabled = true;
                
                // Submit form
                setTimeout(() => {
                    form.submit();
                }, 500);
            });
        }
        
        function validateForm() {
            const form = document.getElementById('editClassroomForm');
            let isValid = true;
            
            // Clear previous validation states
            const inputs = form.querySelectorAll('.form-control, .form-select');
            inputs.forEach(input => {
                input.classList.remove('is-invalid', 'is-valid');
            });
            
            // Required field validation
            const requiredFields = [
                { id: 'room_number', name: 'Room Number' },
                { id: 'building', name: 'Building' },
                { id: 'capacity', name: 'Capacity' },
                { id: 'status', name: 'Status' }
            ];
            
            requiredFields.forEach(field => {
                const input = document.getElementById(field.id);
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    showFieldError(input, `${field.name} is required`);
                    isValid = false;
                } else {
                    input.classList.add('is-valid');
                }
            });
            
            // Type selection validation
            const typeSelected = document.querySelector('input[name="type"]:checked');
            if (!typeSelected) {
                showAlert('Please select a room type.', 'danger');
                isValid = false;
            }
            
            // Capacity validation
            const capacity = document.getElementById('capacity');
            if (capacity.value && (parseInt(capacity.value) < 1 || parseInt(capacity.value) > 1000)) {
                capacity.classList.add('is-invalid');
                showFieldError(capacity, 'Capacity must be between 1 and 1000');
                isValid = false;
            }
            
            // Floor validation
            const floor = document.getElementById('floor');
            if (floor.value && (parseInt(floor.value) < 0 || parseInt(floor.value) > 50)) {
                floor.classList.add('is-invalid');
                showFieldError(floor, 'Floor must be between 0 and 50');
                isValid = false;
            }
            
            return isValid;
        }
        
        function showFieldError(field, message) {
            // Remove existing feedback
            const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }
            
            // Add new feedback
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = message;
            field.parentNode.appendChild(feedback);
        }
        
        function selectType(type) {
            // Update radio button
            document.getElementById(`type_${type}`).checked = true;
            
            // Update visual selection
            document.querySelectorAll('.type-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Update submit button
            updateSubmitButton();
            markFormAsChanged();
        }
        
        function updateRoleSpecificSections() {
            // This function can be used to show/hide sections based on room type if needed
            // Currently not needed for classroom form but kept for consistency
        }
        
        function updateSubmitButton() {
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('editClassroomForm');
            
            // Check if form has required fields filled
            const roomNumber = document.getElementById('room_number').value.trim();
            const building = document.getElementById('building').value.trim();
            const capacity = document.getElementById('capacity').value.trim();
            const typeSelected = document.querySelector('input[name="type"]:checked');
            const status = document.getElementById('status').value;
            
            if (roomNumber && building && capacity && typeSelected && status) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.6';
            }
        }
        
        function setupUnsavedChangesWarning() {
            let formChanged = false;
            
            window.markFormAsChanged = function() {
                formChanged = true;
            };
            
            window.addEventListener('beforeunload', function(e) {
                if (formChanged) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });
            
            // Don't warn when form is submitted
            document.getElementById('editClassroomForm').addEventListener('submit', function() {
                formChanged = false;
            });
        }
        
        function resetToOriginal() {
            if (confirm('Are you sure you want to reset all fields to their original values? This will discard all your changes.')) {
                // Reset form fields to original values
                document.getElementById('room_number').value = originalValues.room_number || '';
                document.getElementById('building').value = originalValues.building || '';
                document.getElementById('floor').value = originalValues.floor || '';
                document.getElementById('capacity').value = originalValues.capacity || '';
                document.getElementById('equipment').value = originalValues.equipment || '';
                document.getElementById('status').value = originalValues.status || '';
                document.getElementById('is_active').checked = originalValues.is_active || false;
                document.getElementById('is_shared').checked = originalValues.is_shared || false;
                
                if (originalValues.department_id) {
                    document.getElementById('department_id').value = originalValues.department_id;
                } else {
                    document.getElementById('department_id').value = '';
                }
                
                // Reset type selection
                if (originalValues.type) {
                    document.getElementById(`type_${originalValues.type}`).checked = true;
                    document.querySelectorAll('.type-card').forEach(card => {
                        card.classList.remove('selected');
                    });
                    document.querySelector(`#type_${originalValues.type}`).closest('.type-card').classList.add('selected');
                }
                
                // Reset facilities
                document.querySelectorAll('input[name="facilities[]"]').forEach(checkbox => {
                    checkbox.checked = originalValues.facilities.includes(checkbox.value);
                });
                
                // Clear validation states
                document.querySelectorAll('.form-control, .form-select').forEach(input => {
                    input.classList.remove('is-invalid', 'is-valid');
                });
                
                // Update submit button
                updateSubmitButton();
                
                showAlert('Form has been reset to original values.', 'info');
            }
        }
        
        function showAlert(message, type = 'info') {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} glass-card fade-in`;
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '20px';
            alertDiv.style.right = '20px';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.maxWidth = '400px';
            alertDiv.innerHTML = `
                <strong>${type.charAt(0).toUpperCase() + type.slice(1)}:</strong> ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        // Theme handling
        function handleThemeChange() {
            applyCurrentTheme();
        }
        
        // Listen for theme changes
        window.addEventListener('storage', function(e) {
            if (e.key === 'theme') {
                handleThemeChange();
            }
        });
        
        // Listen for custom theme change events
        document.addEventListener('themeChanged', handleThemeChange);
    </script>
</body>
</html>