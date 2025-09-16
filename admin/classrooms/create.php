<?php
/**
 * Admin Classroom Create - Classroom Creation Interface
 * Timetable Management System
 * 
 * Professional interface for admin to create new classrooms with comprehensive
 * setup including room details, capacity, facilities, and department assignments
 */

// Start session and security checks
session_start();

// Define system access constant
define('SYSTEM_ACCESS', true);

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Classroom.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$classroomManager = new Classroom();

// Initialize variables
$error_message = '';
$success_message = '';
$departments = [];
$buildings = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error
        $formData = $_POST;
        
        // Basic validation
        $required_fields = ['room_number', 'building', 'capacity', 'type'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Capacity validation
        if (!is_numeric($_POST['capacity']) || (int)$_POST['capacity'] <= 0) {
            throw new Exception('Capacity must be a positive number.');
        }
        
        if ((int)$_POST['capacity'] > 500) {
            throw new Exception('Capacity cannot exceed 500 students.');
        }
        
        // Floor validation (if provided)
        if (!empty($_POST['floor']) && (!is_numeric($_POST['floor']) || (int)$_POST['floor'] < 0)) {
            throw new Exception('Floor must be a non-negative number.');
        }
        
        // Prepare data for creation
        $classroomData = [
            'room_number' => trim($_POST['room_number']),
            'building' => trim($_POST['building']),
            'floor' => !empty($_POST['floor']) ? (int)$_POST['floor'] : null,
            'capacity' => (int)$_POST['capacity'],
            'type' => $_POST['type'],
            'equipment' => !empty($_POST['equipment']) ? trim($_POST['equipment']) : null,
            'status' => $_POST['status'] ?? 'available',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'department_id' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null,
            'is_shared' => isset($_POST['is_shared']) ? 1 : 0
        ];
        
        // Handle facilities as JSON array
        if (!empty($_POST['facilities']) && is_array($_POST['facilities'])) {
            $classroomData['facilities'] = $_POST['facilities'];
        }
        
        // Create the classroom
        $result = $classroomManager->createClassroom($classroomData);
        
        if ($result['success']) {
            $success_message = $result['message'];
            
            // Log activity
            error_log("Admin {$userId} created new classroom: {$classroomData['room_number']} in {$classroomData['building']}");
            
            // Clear form data on success
            $formData = [];
            
            // Redirect after short delay to show success message
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>";
        } else {
            $error_message = $result['message'];
        }
        
    } catch (Exception $e) {
        error_log("Classroom creation error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}

try {
    // Get departments for dropdown
    $departments = $db->fetchAll("
        SELECT department_id, department_name, department_code 
        FROM departments 
        WHERE is_active = 1 
        ORDER BY department_name ASC
    ");
    
    // Get existing buildings for dropdown
    $buildings = $classroomManager->getBuildings();
    
} catch (Exception $e) {
    error_log("Error loading form data: " . $e->getMessage());
    $error_message = "Unable to load form data. Please try again later.";
}

// Set page title
$pageTitle = "Create Classroom";
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
            font-weight: 700;
            color: var(--text-primary);
        }

        .page-header.compact .page-subtitle {
            font-size: 0.95rem;
            margin-bottom: 0;
            color: var(--text-secondary);
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

        .form-container {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 2rem;
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

        .form-control {
            border: 1px solid var(--border-color);
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

        .form-select {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
            background: rgba(255, 255, 255, 0.7);
        }

        /* Dark mode form controls */
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
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        [data-theme="dark"] .form-select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .type-card {
            position: relative;
            padding: 1.5rem;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.3);
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .type-card:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.4);
            box-shadow: var(--shadow-md);
        }

        .type-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .type-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .type-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            display: block;
        }

        .type-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .type-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.4;
        }

        /* Facilities Checkboxes */
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        .facility-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .facility-item:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .facility-item input[type="checkbox"] {
            margin: 0;
        }

        .facility-label {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
        }

        /* Status Badge Styles */
        .status-selection {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 0.75rem;
        }

        .status-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .status-option:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .status-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .status-option input[type="radio"] {
            margin: 0;
        }

        .status-label {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
        }

        /* Buttons */
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
            transform: translateY(-2px);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .btn-secondary {
            background: var(--text-secondary);
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
            color: white;
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border-left-width: 4px;
            border-left-style: solid;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border-left-color: #10b981;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border-left-color: #ef4444;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            padding-top: 1.5rem;
            margin-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-actions-left {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .form-actions-right {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        /* Switch/Toggle Styles */
        .form-switch {
            padding-left: 2.5rem;
        }

        .form-switch .form-check-input {
            width: 2rem;
            margin-left: -2.5rem;
            border-radius: 1rem;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-switch .form-check-label {
            margin-left: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .form-container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .type-selection {
                grid-template-columns: 1fr;
            }

            .facilities-grid {
                grid-template-columns: 1fr;
            }

            .status-selection {
                flex-direction: column;
                align-items: stretch;
            }

            .form-actions {
                flex-direction: column;
                gap: 1rem;
            }

            .form-actions-left,
            .form-actions-right {
                width: 100%;
                justify-content: center;
            }
        }

        /* Dark mode specific adjustments */
        [data-theme="dark"] .form-container {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        [data-theme="dark"] .type-card {
            background: rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] .type-card:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        [data-theme="dark"] .type-card.selected {
            background: var(--primary-color-alpha);
            border-color: var(--primary-color);
        }

        [data-theme="dark"] .facility-item {
            background: rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] .facility-item:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        [data-theme="dark"] .status-option {
            background: rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] .status-option:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        [data-theme="dark"] .status-option.selected {
            background: var(--primary-color-alpha);
            border-color: var(--primary-color);
        }

        /* Capacity indicator styling */
        .capacity-info {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 0.5rem;
            padding: 0.75rem;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.2);
        }

        .capacity-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .capacity-bar {
            width: 100px;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            overflow: hidden;
        }

        .capacity-fill {
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 3px;
        }

        .capacity-small { background: #ef4444; }
        .capacity-medium { background: #f59e0b; }
        .capacity-large { background: #10b981; }
        .capacity-xlarge { background: #3b82f6; }
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
                    <h1 class="page-title">🏫 Create Classroom</h1>
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

        <!-- Create Classroom Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" id="createClassroomForm" novalidate>
                
                <!-- Room Type Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">🏛️ Room Type</h3>
                    <div class="type-selection">
                        <div class="type-card <?= ($formData['type'] ?? '') === 'lecture' ? 'selected' : '' ?>" onclick="selectType('lecture')">
                            <input type="radio" name="type" value="lecture" id="type_lecture"
                                   <?= ($formData['type'] ?? '') === 'lecture' ? 'checked' : '' ?> required>
                            <div class="type-icon">🎓</div>
                            <div class="type-title">Lecture Hall</div>
                            <div class="type-description">Standard classroom for lectures and presentations</div>
                        </div>
                        
                        <div class="type-card <?= ($formData['type'] ?? '') === 'lab' ? 'selected' : '' ?>" onclick="selectType('lab')">
                            <input type="radio" name="type" value="lab" id="type_lab"
                                   <?= ($formData['type'] ?? '') === 'lab' ? 'checked' : '' ?>>
                            <div class="type-icon">🔬</div>
                            <div class="type-title">Laboratory</div>
                            <div class="type-description">Specialized lab with equipment for practical work</div>
                        </div>
                        
                        <div class="type-card <?= ($formData['type'] ?? '') === 'seminar' ? 'selected' : '' ?>" onclick="selectType('seminar')">
                            <input type="radio" name="type" value="seminar" id="type_seminar"
                                   <?= ($formData['type'] ?? '') === 'seminar' ? 'checked' : '' ?>>
                            <div class="type-icon">💼</div>
                            <div class="type-title">Seminar Room</div>
                            <div class="type-description">Small room for discussions and group activities</div>
                        </div>
                        
                        <div class="type-card <?= ($formData['type'] ?? '') === 'auditorium' ? 'selected' : '' ?>" onclick="selectType('auditorium')">
                            <input type="radio" name="type" value="auditorium" id="type_auditorium"
                                   <?= ($formData['type'] ?? '') === 'auditorium' ? 'checked' : '' ?>>
                            <div class="type-icon">🎭</div>
                            <div class="type-title">Auditorium</div>
                            <div class="type-description">Large venue for events and major presentations</div>
                        </div>
                    </div>
                </div>

                <!-- Basic Room Information -->
                <div class="form-section">
                    <h3 class="form-section-title">🏢 Room Details</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="room_number" class="form-label required">Room Number</label>
                            <input type="text" class="form-control" id="room_number" name="room_number" 
                                   value="<?= htmlspecialchars($formData['room_number'] ?? '') ?>" 
                                   placeholder="e.g., 101, A-205, Lab-B3" required>
                            <div class="form-text">Unique identifier for the room</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="building" class="form-label required">Building</label>
                            <input type="text" class="form-control" id="building" name="building" 
                                   value="<?= htmlspecialchars($formData['building'] ?? '') ?>" 
                                   placeholder="e.g., Main Building, Science Block" 
                                   list="buildingList" required>
                            <datalist id="buildingList">
                                <?php foreach ($buildings as $building): ?>
                                    <option value="<?= htmlspecialchars($building['building']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Building or block location</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="floor" class="form-label">Floor</label>
                            <input type="number" class="form-control" id="floor" name="floor" 
                                   value="<?= htmlspecialchars($formData['floor'] ?? '') ?>" 
                                   placeholder="0" min="0" max="20">
                            <div class="form-text">Floor number (0 = Ground floor)</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="capacity" class="form-label required">Capacity</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" 
                                   value="<?= htmlspecialchars($formData['capacity'] ?? '') ?>" 
                                   placeholder="30" min="1" max="500" required>
                            <div class="form-text">Maximum number of students</div>
                            <div class="capacity-info" id="capacityInfo" style="display: none;">
                                <div class="capacity-indicator">
                                    <span id="capacityLabel">Medium</span>
                                    <div class="capacity-bar">
                                        <div class="capacity-fill" id="capacityFill"></div>
                                    </div>
                                    <span id="capacityPercent">0%</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="department_id" class="form-label">Assigned Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">No specific department (Shared)</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>" 
                                            <?= ($formData['department_id'] ?? '') == $dept['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Optional department assignment</div>
                        </div>
                    </div>
                </div>

                <!-- Equipment and Facilities -->
                <div class="form-section">
                    <h3 class="form-section-title">🛠️ Equipment & Facilities</h3>
                    
                    <div class="mb-3">
                        <label for="equipment" class="form-label">Equipment Description</label>
                        <textarea class="form-control" id="equipment" name="equipment" rows="3" 
                                  placeholder="Describe major equipment, special installations, or unique features..."><?= htmlspecialchars($formData['equipment'] ?? '') ?></textarea>
                        <div class="form-text">Optional detailed description of room equipment</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Available Facilities</label>
                        <div class="facilities-grid">
                            <?php
                            $availableFacilities = [
                                'projector' => 'Projector',
                                'whiteboard' => 'Whiteboard',
                                'smartboard' => 'Smart Board',
                                'audio_system' => 'Audio System',
                                'video_conferencing' => 'Video Conferencing',
                                'air_conditioning' => 'Air Conditioning',
                                'wifi' => 'WiFi Access',
                                'power_outlets' => 'Power Outlets',
                                'microphone' => 'Microphone',
                                'screen' => 'Projection Screen',
                                'computer' => 'Computer/Laptop',
                                'printer' => 'Printer',
                                'scanner' => 'Scanner',
                                'laboratory_equipment' => 'Lab Equipment',
                                'safety_equipment' => 'Safety Equipment',
                                'storage' => 'Storage Cabinets'
                            ];
                            
                            $selectedFacilities = $formData['facilities'] ?? [];
                            ?>
                            
                            <?php foreach ($availableFacilities as $value => $label): ?>
                                <div class="facility-item">
                                    <input type="checkbox" name="facilities[]" value="<?= $value ?>" 
                                           id="facility_<?= $value ?>"
                                           <?= in_array($value, $selectedFacilities) ? 'checked' : '' ?>>
                                    <label for="facility_<?= $value ?>" class="facility-label"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Select all applicable facilities available in this room</div>
                    </div>
                </div>

                <!-- Room Status and Settings -->
                <div class="form-section">
                    <h3 class="form-section-title">⚙️ Status & Settings</h3>
                    
                    <div class="mb-3">
                        <label class="form-label">Room Status</label>
                        <div class="status-selection">
                            <?php
                            $statuses = [
                                'available' => '✅ Available',
                                'maintenance' => '🔧 Under Maintenance',
                                'reserved' => '🚫 Reserved',
                                'closed' => '🔒 Closed'
                            ];
                            $selectedStatus = $formData['status'] ?? 'available';
                            ?>
                            
                            <?php foreach ($statuses as $value => $label): ?>
                                <div class="status-option <?= $selectedStatus === $value ? 'selected' : '' ?>" 
                                     onclick="selectStatus('<?= $value ?>')">
                                    <input type="radio" name="status" value="<?= $value ?>" 
                                           id="status_<?= $value ?>"
                                           <?= $selectedStatus === $value ? 'checked' : '' ?>>
                                    <label for="status_<?= $value ?>" class="status-label"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Current operational status of the room</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" 
                                       id="is_active" <?= isset($formData['is_active']) || !isset($formData['is_active']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Active Room
                                </label>
                                <div class="form-text">Room is available for scheduling</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_shared" 
                                       id="is_shared" <?= isset($formData['is_shared']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_shared">
                                    Shared Resource
                                </label>
                                <div class="form-text">Room can be used by multiple departments</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <div class="form-actions-left">
                        <button type="button" class="btn-action btn-secondary" onclick="resetForm()">
                            🔄 Reset Form
                        </button>
                    </div>
                    <div class="form-actions-right">
                        <a href="index.php" class="btn-action btn-outline">
                            ❌ Cancel
                        </a>
                        <button type="submit" class="btn-action btn-primary" id="submitBtn">
                            ✅ Create Classroom
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Apply theme on page load
        document.addEventListener('DOMContentLoaded', function() {
            applyCurrentTheme();
            setupFormValidation();
            setupCapacityIndicator();
            updateSelectedCards();
        });

        function selectType(type) {
            // Update radio button
            document.getElementById('type_' + type).checked = true;
            
            // Update visual selection
            document.querySelectorAll('.type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            document.querySelector('.type-card input[value="' + type + '"]').closest('.type-card').classList.add('selected');
        }

        function selectStatus(status) {
            // Update radio button
            document.getElementById('status_' + status).checked = true;
            
            // Update visual selection
            document.querySelectorAll('.status-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            document.querySelector('.status-option input[value="' + status + '"]').closest('.status-option').classList.add('selected');
        }

        function updateSelectedCards() {
            // Update type cards
            const selectedType = document.querySelector('input[name="type"]:checked');
            if (selectedType) {
                selectType(selectedType.value);
            }
            
            // Update status options
            const selectedStatus = document.querySelector('input[name="status"]:checked');
            if (selectedStatus) {
                selectStatus(selectedStatus.value);
            }
        }

        function setupCapacityIndicator() {
            const capacityInput = document.getElementById('capacity');
            const capacityInfo = document.getElementById('capacityInfo');
            const capacityFill = document.getElementById('capacityFill');
            const capacityLabel = document.getElementById('capacityLabel');
            const capacityPercent = document.getElementById('capacityPercent');

            capacityInput.addEventListener('input', function() {
                const capacity = parseInt(this.value) || 0;
                
                if (capacity > 0) {
                    capacityInfo.style.display = 'flex';
                    
                    let label, className, percent;
                    
                    if (capacity <= 30) {
                        label = 'Small';
                        className = 'capacity-small';
                        percent = Math.min((capacity / 30) * 100, 100);
                    } else if (capacity <= 60) {
                        label = 'Medium';
                        className = 'capacity-medium';
                        percent = Math.min(((capacity - 30) / 30) * 100, 100);
                    } else if (capacity <= 150) {
                        label = 'Large';
                        className = 'capacity-large';
                        percent = Math.min(((capacity - 60) / 90) * 100, 100);
                    } else {
                        label = 'Extra Large';
                        className = 'capacity-xlarge';
                        percent = Math.min(((capacity - 150) / 350) * 100, 100);
                    }
                    
                    capacityFill.className = 'capacity-fill ' + className;
                    capacityFill.style.width = percent + '%';
                    capacityLabel.textContent = label;
                    capacityPercent.textContent = Math.round(percent) + '%';
                } else {
                    capacityInfo.style.display = 'none';
                }
            });
            
            // Trigger initial update
            capacityInput.dispatchEvent(new Event('input'));
        }

        function setupFormValidation() {
            const form = document.getElementById('createClassroomForm');
            const submitBtn = document.getElementById('submitBtn');

            form.addEventListener('submit', function(e) {
                // Basic validation
                const roomNumber = document.getElementById('room_number').value.trim();
                const building = document.getElementById('building').value.trim();
                const capacity = document.getElementById('capacity').value;
                const type = document.querySelector('input[name="type"]:checked');

                if (!roomNumber) {
                    e.preventDefault();
                    alert('Please enter a room number.');
                    document.getElementById('room_number').focus();
                    return;
                }

                if (!building) {
                    e.preventDefault();
                    alert('Please enter a building name.');
                    document.getElementById('building').focus();
                    return;
                }

                if (!capacity || capacity <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid capacity greater than 0.');
                    document.getElementById('capacity').focus();
                    return;
                }

                if (capacity > 500) {
                    e.preventDefault();
                    alert('Capacity cannot exceed 500 students.');
                    document.getElementById('capacity').focus();
                    return;
                }

                if (!type) {
                    e.preventDefault();
                    alert('Please select a room type.');
                    return;
                }

                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ Creating Classroom...';
                
                // Re-enable after timeout (in case of server error)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '✅ Create Classroom';
                }, 10000);
            });
        }

        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                // Reset the form
                document.getElementById('createClassroomForm').reset();
                
                // Reset visual selections
                document.querySelectorAll('.type-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                document.querySelectorAll('.status-option').forEach(option => {
                    option.classList.remove('selected');
                });
                
                // Reset capacity indicator
                document.getElementById('capacityInfo').style.display = 'none';
                
                // Set default status to available
                selectStatus('available');
                
                // Set default active state
                document.getElementById('is_active').checked = true;
            }
        }

        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Listen for theme changes
        window.addEventListener('themeChanged', function(event) {
            applyCurrentTheme();
        });

        // Auto-suggest room number based on building and floor
        document.getElementById('building').addEventListener('change', generateRoomSuggestion);
        document.getElementById('floor').addEventListener('change', generateRoomSuggestion);

        function generateRoomSuggestion() {
            const building = document.getElementById('building').value.trim();
            const floor = document.getElementById('floor').value;
            const roomNumberField = document.getElementById('room_number');
            
            // Only auto-suggest if room number is empty
            if (roomNumberField.value.trim() === '' && building && floor !== '') {
                // Create a simple suggestion based on building and floor
                const buildingPrefix = building.charAt(0).toUpperCase();
                const suggestion = `${buildingPrefix}${floor}01`;
                roomNumberField.placeholder = `e.g., ${suggestion}`;
            }
        }

        // Form autosave (optional - saves to localStorage for recovery)
        function saveFormData() {
            const formData = new FormData(document.getElementById('createClassroomForm'));
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                if (data[key]) {
                    if (Array.isArray(data[key])) {
                        data[key].push(value);
                    } else {
                        data[key] = [data[key], value];
                    }
                } else {
                    data[key] = value;
                }
            }
            
            localStorage.setItem('classroomFormDraft', JSON.stringify(data));
        }

        function loadFormData() {
            const saved = localStorage.getItem('classroomFormDraft');
            if (saved) {
                try {
                    const data = JSON.parse(saved);
                    // Restore form data logic here if needed
                    console.log('Draft data available:', data);
                } catch (e) {
                    console.log('Failed to load draft data');
                }
            }
        }

        // Clear draft on successful submission
        window.addEventListener('beforeunload', function() {
            if (document.getElementById('createClassroomForm').querySelector('.alert-success')) {
                localStorage.removeItem('classroomFormDraft');
            }
        });

        // Auto-save periodically (every 30 seconds)
        setInterval(saveFormData, 30000);

        // Department change handler for better UX
        document.getElementById('department_id').addEventListener('change', function() {
            const sharedCheckbox = document.getElementById('is_shared');
            if (this.value === '') {
                // If no department selected, suggest it's shared
                sharedCheckbox.checked = true;
            } else {
                // If department selected, uncheck shared unless manually set
                if (!sharedCheckbox.dataset.manuallySet) {
                    sharedCheckbox.checked = false;
                }
            }
        });

        // Track manual changes to shared checkbox
        document.getElementById('is_shared').addEventListener('change', function() {
            this.dataset.manuallySet = 'true';
        });

        // Building input enhancement
        const buildingInput = document.getElementById('building');
        buildingInput.addEventListener('input', function() {
            // Auto-capitalize building names
            this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
        });

        // Room number input enhancement
        const roomNumberInput = document.getElementById('room_number');
        roomNumberInput.addEventListener('input', function() {
            // Auto-uppercase room numbers
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>