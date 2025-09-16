<?php
/**
 * Admin Time Slots Create - Time Slot Creation Interface
 * Timetable Management System
 * 
 * Professional interface for admin to create new time slots with overlap detection
 * and comprehensive validation for academic scheduling
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
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$timeSlotManager = new TimeSlot();

// Initialize variables
$error_message = '';
$success_message = '';
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error
        $formData = $_POST;
        
        // Basic validation
        $required_fields = ['day_of_week', 'start_time', 'end_time', 'slot_name', 'slot_type'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
        }
        
        // Validate slot name length
        if (strlen($_POST['slot_name']) > 20) {
            throw new Exception('Slot name cannot exceed 20 characters.');
        }
        
        // Validate time format and logic
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $startTime)) {
            throw new Exception('Invalid start time format. Use HH:MM format.');
        }
        
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $endTime)) {
            throw new Exception('Invalid end time format. Use HH:MM format.');
        }
        
        // Convert to DateTime for comparison
        $startDateTime = DateTime::createFromFormat('H:i', $startTime);
        $endDateTime = DateTime::createFromFormat('H:i', $endTime);
        
        if ($startDateTime >= $endDateTime) {
            throw new Exception('End time must be after start time.');
        }
        
        // Check minimum duration (15 minutes)
        $duration = $endDateTime->getTimestamp() - $startDateTime->getTimestamp();
        if ($duration < 900) { // 15 minutes = 900 seconds
            throw new Exception('Time slot must be at least 15 minutes long.');
        }
        
        // Check maximum duration (4 hours)
        if ($duration > 14400) { // 4 hours = 14400 seconds
        }
        
        // Prepare time slot data
        $timeSlotData = [
            'day_of_week' => $_POST['day_of_week'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'slot_name' => trim($_POST['slot_name']),
            'slot_type' => $_POST['slot_type'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Create time slot
        $result = $timeSlotManager->createTimeSlot($timeSlotData);
        
        if ($result['success']) {
            $success_message = $result['message'];
            $formData = []; // Clear form data on success
            
            // Redirect after short delay to show success message
            header("refresh:2;url=index.php");
        } else {
            $error_message = $result['message'];
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Set page title
$pageTitle = "Create Time Slot";
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

        [data-theme="light"] .glass-card {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] .glass-card {
            background: var(--bg-secondary);
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

        /* Form Container */
        .form-container {
            padding: 2rem;
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

        /* Day Selection Cards */
        .day-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .day-card {
            position: relative;
            padding: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
        }

        [data-theme="dark"] .day-card {
            border-color: var(--border-color);
            background: var(--bg-tertiary);
        }

        [data-theme="light"] .day-card {
            border-color: var(--border-color);
            background: var(--bg-secondary);
        }

        .day-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .day-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-alpha);
        }

        .day-card input[type="radio"] {
            display: none;
        }

        .day-card .day-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .day-card .day-abbr {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* Type Selection */
        .type-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
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

        /* Time Input Enhancements */
        .time-input-group {
            position: relative;
        }

        .time-input-group .form-control {
            padding-left: 2.5rem;
        }

        .time-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            z-index: 2;
        }

        /* Duration Display */
        .duration-display {
            padding: 0.75rem;
            border-radius: 8px;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            text-align: center;
            font-weight: 600;
            color: var(--primary-color);
        }

        .duration-display.warning {
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.3);
            color: var(--warning-color);
        }

        .duration-display.error {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: var(--error-color);
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

            .form-container {
                padding: 1rem;
            }

            .day-selection {
                grid-template-columns: repeat(2, 1fr);
            }

            .type-selection {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .day-selection,
            .type-selection {
                grid-template-columns: 1fr;
            }
        }

        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        .slide-up {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-card glass-card fade-in">
                <div class="header-text">
                    <h1 class="page-title">⏰ Create Time Slot</h1>
                </div>
                <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                    <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success glass-card" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong> <?= htmlspecialchars($success_message) ?>
                <br><small>Redirecting to time slot list...</small>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Create Time Slot Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" id="createTimeSlotForm" novalidate>
                <!-- Day Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">📅 Select Day</h3>
                    <div class="day-selection">
                        <?php
                        $days = [
                            'Monday' => 'Mon',
                            'Tuesday' => 'Tue', 
                            'Wednesday' => 'Wed',
                            'Thursday' => 'Thu',
                            'Friday' => 'Fri',
                            'Saturday' => 'Sat'
                        ];
                        
                        $selectedDay = $formData['day_of_week'] ?? '';
                        ?>
                        
                        <?php foreach ($days as $day => $abbr): ?>
                        <div class="day-card <?= $selectedDay === $day ? 'selected' : '' ?>" 
                             onclick="selectDay('<?= $day ?>')">
                            <input type="radio" name="day_of_week" value="<?= $day ?>" 
                                   <?= $selectedDay === $day ? 'checked' : '' ?> id="day_<?= strtolower($day) ?>">
                            <div class="day-name"><?= $day ?></div>
                            <div class="day-abbr"><?= $abbr ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i> 
                        Select the day of the week for this time slot
                    </div>
                </div>

                <!-- Time Configuration -->
                <div class="form-section">
                    <h3 class="form-section-title">⏱️ Time Configuration</h3>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="start_time" class="form-label">Start Time *</label>
                            <div class="time-input-group">
                                <i class="fas fa-clock time-icon"></i>
                                <input type="time" class="form-control" id="start_time" name="start_time" 
                                       value="<?= htmlspecialchars($formData['start_time'] ?? '') ?>" 
                                       required onchange="calculateDuration()">
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="end_time" class="form-label">End Time *</label>
                            <div class="time-input-group">
                                <i class="fas fa-clock time-icon"></i>
                                <input type="time" class="form-control" id="end_time" name="end_time" 
                                       value="<?= htmlspecialchars($formData['end_time'] ?? '') ?>" 
                                       required onchange="calculateDuration()">
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duration</label>
                            <div class="duration-display" id="durationDisplay">
                                <i class="fas fa-hourglass-half"></i> Select times
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i> 
                        Minimum duration: 15 minutes. Maximum duration: 4 hours. Use 24-hour format.
                    </div>
                </div>

                <!-- Slot Details -->
                <div class="form-section">
                    <h3 class="form-section-title">📝 Slot Details</h3>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="slot_name" class="form-label">Slot Name *</label>
                            <input type="text" class="form-control" id="slot_name" name="slot_name" 
                                   value="<?= htmlspecialchars($formData['slot_name'] ?? '') ?>" 
                                   placeholder="e.g., Period 1, Break, Lunch" maxlength="20" required>
                            <div class="character-counter" id="slotNameCounter">0 / 20</div>
                            <div class="form-text">
                                <i class="fas fa-tag"></i> 
                                A descriptive name for this time slot
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="slot_type" class="form-label">Slot Type *</label>
                            <select class="form-select" id="slot_type" name="slot_type" required>
                                <option value="">Select type...</option>
                                <option value="regular" <?= ($formData['slot_type'] ?? '') === 'regular' ? 'selected' : '' ?>>
                                    Regular Class
                                </option>
                                <option value="break" <?= ($formData['slot_type'] ?? '') === 'break' ? 'selected' : '' ?>>
                                    Break Time
                                </option>
                                <option value="lunch" <?= ($formData['slot_type'] ?? '') === 'lunch' ? 'selected' : '' ?>>
                                    Lunch Break
                                </option>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-list"></i> 
                                Categorize the purpose of this time slot
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Type Selection Visual -->
                <div class="form-section">
                    <h3 class="form-section-title">🎯 Quick Type Selection</h3>
                    
                    <div class="type-selection">
                        <div class="type-card <?= ($formData['slot_type'] ?? '') === 'regular' ? 'selected' : '' ?>" 
                             onclick="selectType('regular')">
                            <div class="type-icon">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div class="type-name">Regular Class</div>
                        </div>
                        
                        <div class="type-card <?= ($formData['slot_type'] ?? '') === 'break' ? 'selected' : '' ?>" 
                             onclick="selectType('break')">
                            <div class="type-icon">
                                <i class="fas fa-coffee"></i>
                            </div>
                            <div class="type-name">Break Time</div>
                        </div>
                        
                        <div class="type-card <?= ($formData['slot_type'] ?? '') === 'lunch' ? 'selected' : '' ?>" 
                             onclick="selectType('lunch')">
                            <div class="type-icon">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <div class="type-name">Lunch Break</div>
                        </div>
                    </div>
                </div>

                <!-- Settings -->
                <div class="form-section">
                    <h3 class="form-section-title">⚙️ Settings</h3>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                               <?= ($formData['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            <strong>Active Time Slot</strong>
                        </label>
                        <div class="form-text mt-1">
                            <i class="fas fa-toggle-on"></i> 
                            Active time slots are available for scheduling. Uncheck to create as inactive.
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
                            🔄 Reset Form
                        </button>
                        <button type="button" class="btn-action btn-warning" onclick="previewTimeSlot()">
                            👁️ Preview
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn">
                            ✅ Create Time Slot
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
                        <i class="fas fa-eye"></i> Time Slot Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="time-slot-preview" id="timeSlotPreview">
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
            
            // Set initial selections
            updateDaySelection();
            updateTypeSelection();
            
            // Calculate initial duration
            calculateDuration();
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
            const form = document.getElementById('createTimeSlotForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
                submitBtn.disabled = true;
            });
        }

        /**
         * Validate form inputs
         */
        function validateForm() {
            const dayOfWeek = document.querySelector('input[name="day_of_week"]:checked');
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            const slotName = document.getElementById('slot_name').value.trim();
            const slotType = document.getElementById('slot_type').value;
            
            // Check required fields
            if (!dayOfWeek) {
                showError('Please select a day of the week.');
                return false;
            }
            
            if (!startTime) {
                showError('Please enter a start time.');
                return false;
            }
            
            if (!endTime) {
                showError('Please enter an end time.');
                return false;
            }
            
            if (!slotName) {
                showError('Please enter a slot name.');
                return false;
            }
            
            if (!slotType) {
                showError('Please select a slot type.');
                return false;
            }
            
            // Validate time logic
            const startDateTime = new Date('2000-01-01 ' + startTime);
            const endDateTime = new Date('2000-01-01 ' + endTime);
            
            if (startDateTime >= endDateTime) {
                showError('End time must be after start time.');
                return false;
            }
            
            // Check duration limits
            const duration = (endDateTime - startDateTime) / 1000 / 60; // minutes
            
            if (duration < 15) {
                showError('Time slot must be at least 15 minutes long.');
                return false;
            }
            
            if (duration > 240) {
                showError('Time slot cannot exceed 4 hours.');
                return false;
            }
            
            // Validate slot name length
            if (slotName.length > 20) {
                showError('Slot name cannot exceed 20 characters.');
                return false;
            }
            
            return true;
        }

        /**
         * Initialize character counters
         */
        function initializeCharacterCounters() {
            const slotNameInput = document.getElementById('slot_name');
            const slotNameCounter = document.getElementById('slotNameCounter');
            
            function updateCounter(input, counter, maxLength) {
                const currentLength = input.value.length;
                counter.textContent = `${currentLength} / ${maxLength}`;
                
                // Update counter color based on usage
                counter.classList.remove('near-limit', 'at-limit');
                if (currentLength >= maxLength) {
                    counter.classList.add('at-limit');
                } else if (currentLength >= maxLength * 0.8) {
                    counter.classList.add('near-limit');
                }
            }
            
            // Initial count
            updateCounter(slotNameInput, slotNameCounter, 20);
            
            // Event listener
            slotNameInput.addEventListener('input', () => updateCounter(slotNameInput, slotNameCounter, 20));
        }

        /**
         * Select day of week
         */
        function selectDay(day) {
            // Remove selected class from all cards
            document.querySelectorAll('.day-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            document.querySelector(`#day_${day.toLowerCase()}`).closest('.day-card').classList.add('selected');
            
            // Check the radio button
            document.querySelector(`#day_${day.toLowerCase()}`).checked = true;
        }

        /**
         * Select slot type
         */
        function selectType(type) {
            // Remove selected class from all cards
            document.querySelectorAll('.type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Update the select dropdown
            document.getElementById('slot_type').value = type;
        }

        /**
         * Update day selection visual state
         */
        function updateDaySelection() {
            const selectedDay = document.querySelector('input[name="day_of_week"]:checked');
            if (selectedDay) {
                selectDay(selectedDay.value);
            }
        }

        /**
         * Update type selection visual state
         */
        function updateTypeSelection() {
            const selectedType = document.getElementById('slot_type').value;
            if (selectedType) {
                document.querySelectorAll('.type-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                const typeCard = document.querySelector(`[onclick="selectType('${selectedType}')"]`);
                if (typeCard) {
                    typeCard.classList.add('selected');
                }
            }
        }

        /**
         * Calculate and display duration
         */
        function calculateDuration() {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            const durationDisplay = document.getElementById('durationDisplay');
            
            if (!startTime || !endTime) {
                durationDisplay.innerHTML = '<i class="fas fa-hourglass-half"></i> Select times';
                durationDisplay.className = 'duration-display';
                return;
            }
            
            const startDateTime = new Date('2000-01-01 ' + startTime);
            const endDateTime = new Date('2000-01-01 ' + endTime);
            
            if (startDateTime >= endDateTime) {
                durationDisplay.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Invalid time range';
                durationDisplay.className = 'duration-display error';
                return;
            }
            
            const duration = (endDateTime - startDateTime) / 1000 / 60; // minutes
            const hours = Math.floor(duration / 60);
            const minutes = duration % 60;
            
            let durationText = '';
            if (hours > 0) {
                durationText += `${hours}h `;
            }
            if (minutes > 0) {
                durationText += `${minutes}m`;
            }
            
            // Determine status
            let className = 'duration-display';
            let icon = 'fas fa-clock';
            
            if (duration < 15) {
                className += ' error';
                icon = 'fas fa-exclamation-triangle';
                durationText += ' (Too short)';
            } else if (duration > 240) {
                className += ' error';
                icon = 'fas fa-exclamation-triangle';
                durationText += ' (Too long)';
            } else if (duration > 180) {
                className += ' warning';
                icon = 'fas fa-exclamation-triangle';
            }
            
            durationDisplay.innerHTML = `<i class="${icon}"></i> ${durationText}`;
            durationDisplay.className = className;
        }

        /**
         * Reset form to initial state
         */
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? This will clear all entered data.')) {
                document.getElementById('createTimeSlotForm').reset();
                
                // Reset visual selections
                document.querySelectorAll('.day-card, .type-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                // Reset duration display
                document.getElementById('durationDisplay').innerHTML = '<i class="fas fa-hourglass-half"></i> Select times';
                document.getElementById('durationDisplay').className = 'duration-display';
                
                // Reset character counter
                document.getElementById('slotNameCounter').textContent = '0 / 20';
                document.getElementById('slotNameCounter').classList.remove('near-limit', 'at-limit');
            }
        }

        /**
         * Preview time slot
         */
        function previewTimeSlot() {
            const dayOfWeek = document.querySelector('input[name="day_of_week"]:checked');
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            const slotName = document.getElementById('slot_name').value.trim();
            const slotType = document.getElementById('slot_type').value;
            const isActive = document.getElementById('is_active').checked;
            
            if (!dayOfWeek || !startTime || !endTime || !slotName || !slotType) {
                showError('Please fill in all required fields to preview.');
                return;
            }
            
            // Format times
            const formatTime = (time) => {
                const [hours, minutes] = time.split(':');
                const hour12 = hours % 12 || 12;
                const ampm = hours < 12 ? 'AM' : 'PM';
                return `${hour12}:${minutes} ${ampm}`;
            };
            
            // Calculate duration
            const startDateTime = new Date('2000-01-01 ' + startTime);
            const endDateTime = new Date('2000-01-01 ' + endTime);
            const duration = (endDateTime - startDateTime) / 1000 / 60; // minutes
            const hours = Math.floor(duration / 60);
            const minutes = duration % 60;
            
            let durationText = '';
            if (hours > 0) durationText += `${hours}h `;
            if (minutes > 0) durationText += `${minutes}m`;
            
            // Get type icon and color
            const typeIcons = {
                'regular': { icon: 'fas fa-chalkboard-teacher', color: '#3b82f6' },
                'break': { icon: 'fas fa-coffee', color: '#f59e0b' },
                'lunch': { icon: 'fas fa-utensils', color: '#10b981' }
            };
            
            const typeInfo = typeIcons[slotType] || typeIcons['regular'];
            
            // Create preview HTML
            const previewHtml = `
                <div class="time-slot-preview-item" style="
                    border: 2px solid var(--border-color);
                    border-radius: 12px;
                    padding: 1.5rem;
                    background: var(--bg-tertiary);
                    position: relative;
                ">
                    <div class="d-flex align-items-start gap-3">
                        <div class="slot-type-icon" style="
                            width: 50px;
                            height: 50px;
                            border-radius: 10px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: white;
                            background: ${typeInfo.color};
                            flex-shrink: 0;
                        ">
                            <i class="${typeInfo.icon}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0" style="color: var(--text-primary); font-weight: 600;">
                                    ${escapeHtml(slotName)}
                                </h5>
                                <div class="d-flex gap-2">
                                    <span class="badge" style="background: ${typeInfo.color}; font-size: 0.7rem;">
                                        ${slotType.toUpperCase()}
                                    </span>
                                    ${!isActive ? '<span class="badge" style="background: var(--error-color); font-size: 0.7rem;">INACTIVE</span>' : ''}
                                </div>
                            </div>
                            <div class="slot-details" style="color: var(--text-secondary); font-size: 0.9rem;">
                                <div class="mb-1">
                                    <i class="fas fa-calendar-day"></i> 
                                    <strong>${dayOfWeek.value}</strong>
                                </div>
                                <div class="mb-1">
                                    <i class="fas fa-clock"></i> 
                                    ${formatTime(startTime)} - ${formatTime(endTime)}
                                    <span style="color: var(--primary-color); font-weight: 600;">(${durationText})</span>
                                </div>
                                <div>
                                    <i class="fas fa-info-circle"></i> 
                                    Preview - ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('timeSlotPreview').innerHTML = previewHtml;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('previewModal')).show();
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
            const pageHeader = document.querySelector('.page-header');
            pageHeader.insertAdjacentHTML('afterend', alertHtml);
            
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
        window.selectDay = selectDay;
        window.selectType = selectType;
        window.calculateDuration = calculateDuration;
        window.resetForm = resetForm;
        window.previewTimeSlot = previewTimeSlot;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>