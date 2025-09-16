<?php
/**
 * Admin Time Slots Management - Time Slots Overview Interface
 * Timetable Management System
 * 
 * Professional interface for admin to manage all time slots including
 * creating, editing, activating/deactivating time slots and comprehensive analytics
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
$timeSlots = array();
$slotStats = array();
$error_message = '';
$success_message = '';
$selectedDay = isset($_GET['day']) ? $_GET['day'] : '';
$selectedType = isset($_GET['type']) ? $_GET['type'] : '';
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Handle bulk actions
if (isset($_POST['action']) && $_POST['action'] === 'bulk_action' && !empty($_POST['selected_slots'])) {
    $selectedSlots = $_POST['selected_slots'];
    $bulkAction = $_POST['bulk_action_type'];
    
    try {
        if ($bulkAction === 'activate') {
            foreach ($selectedSlots as $slotId) {
                $timeSlotManager->updateSlotStatus($slotId, 1);
            }
            $success_message = "Selected time slots have been activated successfully.";
        } elseif ($bulkAction === 'deactivate') {
            foreach ($selectedSlots as $slotId) {
                $timeSlotManager->updateSlotStatus($slotId, 0);
            }
            $success_message = "Selected time slots have been deactivated successfully.";
        }
    } catch (Exception $e) {
        $error_message = "Bulk action failed: " . $e->getMessage();
    }
}

// Handle individual actions
if (isset($_GET['action']) && isset($_GET['slot_id'])) {
    $slotId = (int)$_GET['slot_id'];
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'activate':
                $timeSlotManager->updateSlotStatus($slotId, 1);
                $success_message = "Time slot has been activated successfully.";
                break;
            case 'deactivate':
                $timeSlotManager->updateSlotStatus($slotId, 0);
                $success_message = "Time slot has been deactivated successfully.";
                break;
            case 'delete':
                $timeSlotManager->deleteTimeSlot($slotId);
                $success_message = "Time slot has been deleted successfully.";
                break;
        }
    } catch (Exception $e) {
        $error_message = "Action failed: " . $e->getMessage();
    }
}

try {
    // Build filters for time slot query
    $whereConditions = array();
    $params = array();
    
    if (!empty($selectedDay)) {
        $whereConditions[] = "ts.day_of_week = ?";
        $params[] = $selectedDay;
    }
    
    if (!empty($selectedType)) {
        $whereConditions[] = "ts.slot_type = ?";
        $params[] = $selectedType;
    }
    
    if (!empty($selectedStatus)) {
        $whereConditions[] = "ts.is_active = ?";
        $params[] = $selectedStatus === 'active' ? 1 : 0;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get all time slots with usage statistics
    $timeSlots = $db->fetchAll("
        SELECT ts.*, 
               COUNT(DISTINCT t.timetable_id) as usage_count,
               COUNT(DISTINCT CASE WHEN t.is_active = 1 THEN t.timetable_id END) as active_usage_count,
               COUNT(DISTINCT t.subject_id) as subjects_count,
               COUNT(DISTINCT t.faculty_id) as faculty_count
        FROM time_slots ts
        LEFT JOIN timetables t ON ts.slot_id = t.slot_id
        {$whereClause}
        GROUP BY ts.slot_id
        ORDER BY 
            FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            ts.start_time ASC
    ", $params);

    // Get time slot statistics
    $slotStats = $db->fetchRow("
        SELECT 
            COUNT(*) as total_slots,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_slots,
            COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_slots,
            COUNT(CASE WHEN slot_type = 'regular' THEN 1 END) as regular_slots,
            COUNT(CASE WHEN slot_type = 'break' THEN 1 END) as break_slots,
            COUNT(CASE WHEN slot_type = 'lunch' THEN 1 END) as lunch_slots,
            AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration_minutes
        FROM time_slots
    ");

    // Get usage statistics
    $usageStats = $db->fetchRow("
        SELECT 
            COUNT(DISTINCT ts.slot_id) as slots_with_usage,
            COUNT(DISTINCT t.timetable_id) as total_scheduled_classes,
            COUNT(DISTINCT CASE WHEN t.is_active = 1 THEN t.timetable_id END) as active_scheduled_classes
        FROM time_slots ts
        LEFT JOIN timetables t ON ts.slot_id = t.slot_id
    ");

} catch (Exception $e) {
    error_log("Admin Time Slots Management Error: " . $e->getMessage());
    $error_message = "Unable to load time slots data. Please try again later.";
    $slotStats = array(
        'total_slots' => 0, 'active_slots' => 0, 'inactive_slots' => 0, 
        'regular_slots' => 0, 'break_slots' => 0, 'lunch_slots' => 0,
        'avg_duration_minutes' => 0
    );
    $usageStats = array(
        'slots_with_usage' => 0, 'total_scheduled_classes' => 0, 'active_scheduled_classes' => 0
    );
}

// Set page title
$pageTitle = "Time Slots Management";
$currentPage = "timeslots";
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
            --sidebar-collapsed-width: 70px;
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

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        /* Prevent horizontal overflow on small screens */
        html, body { overflow-x: hidden; }

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
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            margin-top: 1rem;
        }

        .header-card {
            padding: 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-bottom: 1px solid var(--glass-border);
            box-shadow: var(--shadow-md);
        }

        [data-theme="dark"] .header-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
            border-color: var(--border-color);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }



        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .stat-icon.total { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .stat-icon.active { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stat-icon.inactive { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }
        .stat-icon.regular { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .stat-icon.break { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-icon.lunch { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }
        .stat-icon.usage { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .stat-icon.duration { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.875rem;
        }

        /* Search and Filters */
        .search-filters {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .search-filters {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        .search-box {
            position: relative;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.5);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .search-input {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        [data-theme="dark"] .search-input:focus {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .filter-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.5);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        [data-theme="dark"] .filter-select {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        /* Time Slots Table Container */
        .timeslots-container {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .timeslots-container {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
        }

        [data-theme="dark"] .timeslots-table {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        /* Scroll container for table */
        .timeslots-container .table-responsive {
            max-height: 65vh;
            overflow-y: auto;
        }

        /* Sticky header for timeslots table */
        .timeslots-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--bg-primary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        [data-theme="dark"] .timeslots-table thead th {
            background: var(--bg-secondary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .timeslots-table {
            width: 100%;
            margin: 0;
        }

        .timeslots-table thead {
            background: rgba(255, 255, 255, 0.3);
        }

        [data-theme="dark"] .timeslots-table thead {
            background: rgba(0, 0, 0, 0.4);
        }

        .timeslots-table th {
            padding: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        [data-theme="dark"] .timeslots-table th {
            color: var(--text-primary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background-color: rgba(30, 41, 59, 0.9);
        }

        .timeslots-table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: middle;
            color: var(--text-primary);
        }

        [data-theme="dark"] .timeslots-table td {
            color: var(--text-primary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .timeslots-table tbody tr {
            transition: all 0.3s ease;
            background-color: transparent;
        }

        .timeslots-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        [data-theme="dark"] .timeslots-table tbody tr {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .timeslots-table tbody tr:hover {
            background: var(--bg-tertiary);
        }

        [data-theme="dark"] .timeslots-table tbody td {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        /* Column widths */
        .timeslots-table th:nth-child(1),
        .timeslots-table td:nth-child(1) { /* checkbox */
            width: 44px;
            text-align: center;
            white-space: nowrap;
        }

        .timeslots-table th:nth-child(8),
        .timeslots-table td:nth-child(8) { /* Actions */
            width: 130px;
            text-align: center;
            white-space: nowrap;
        }

        /* Slot styling */
        .time-slot-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .time-slot-icon.regular { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .time-slot-icon.break { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .time-slot-icon.lunch { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }

        .slot-info {
            display: flex;
            align-items: center;
        }

        .slot-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .slot-meta {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .time-display {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary-color);
        }

        .duration-display {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-style: italic;
        }

        /* Badges */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-type-regular {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }

        .badge-type-break {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .badge-type-lunch {
            background: rgba(236, 72, 153, 0.1);
            color: #db2777;
        }

        .badge-status-active {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .badge-status-inactive {
            background: rgba(100, 116, 139, 0.1);
            color: #475569;
        }

        /* Button Styles */
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

        /* Bulk Actions */
        .bulk-actions {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: none;
        }

        [data-theme="dark"] .bulk-actions {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        .bulk-actions.show {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Usage indicators */
        .usage-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .usage-bar {
            width: 60px;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            overflow: hidden;
        }

        .usage-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .usage-fill.low { background: #10b981; }
        .usage-fill.medium { background: #f59e0b; }
        .usage-fill.high { background: #ef4444; }

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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .empty-state p {
            font-size: 1rem;
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Mobile Cards for responsive design */
        .timeslot-card {
            display: none;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        [data-theme="dark"] .timeslot-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        .timeslot-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .timeslot-card-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
            font-size: 1.5rem;
        }

        .timeslot-card-avatar.regular { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .timeslot-card-avatar.break { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .timeslot-card-avatar.lunch { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }

        .timeslot-card-info h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .timeslot-card-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0.25rem 0;
        }

        .timeslot-card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .timeslot-card-detail {
            font-size: 0.875rem;
        }

        .timeslot-card-detail strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .timeslot-card-detail span {
            color: var(--text-secondary);
        }

        .timeslot-card-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: calc(var(--navbar-height) + 1rem);
            }

            .page-title {
                font-size: 2rem;
            }

            .filter-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-filters {
                padding: 1rem;
            }

        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            /* Hide table and show cards on mobile */
            .timeslots-container {
                display: none;
            }

            .timeslot-card {
                display: block;
                /* Stronger, more visible borders on mobile */
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
                width: calc(100% - 1rem); /* Account for left margin only */
                box-sizing: border-box; /* include border/padding in width to avoid overflow */
                word-wrap: break-word;
                overflow-wrap: anywhere;
                /* Add left margin for mobile spacing */
                margin-left: 1rem;
            }

            /* Tighten horizontal paddings to reduce chance of overflow */
            .main-content { padding-left: 1rem; padding-right: 1rem; }

            /* Break long values (e.g., long slot names) gracefully */
            .timeslot-card-info h6,
            .timeslot-card-detail span,
            .timeslot-card-meta {
                overflow-wrap: anywhere;
                word-break: break-word;
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .timeslot-card {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }

            .timeslot-card-details {
                grid-template-columns: 1fr;
            }

            
            

        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: repeat(1, 1fr);
                gap: 1rem;
            }

            .page-title {
                font-size: 1.75rem;
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

        /* Row highlight animation */
        .slot-row { 
            scroll-margin-top: 130px; 
        }
        
        .row-highlight {
            animation: highlightPulse 2.5s ease-in-out 1;
        }
        
        @keyframes highlightPulse {
            0% { 
                box-shadow: 0 0 0 0 rgba(56, 182, 255, 0.0); 
                background: rgba(56, 182, 255, 0.10); 
            }
            30% { 
                box-shadow: 0 0 0 6px rgba(56, 182, 255, 0.15); 
                background: rgba(56, 182, 255, 0.12); 
            }
            60% { 
                box-shadow: 0 0 0 0 rgba(56, 182, 255, 0.0); 
                background: rgba(56, 182, 255, 0.08); 
            }
            100% { 
                box-shadow: none; 
                background: transparent; 
            }
        }

        /* Loading states */
        .loading-shimmer {
            background: linear-gradient(90deg, 
                rgba(255, 255, 255, 0.1) 25%, 
                rgba(255, 255, 255, 0.3) 50%, 
                rgba(255, 255, 255, 0.1) 75%
            );
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Modal Dark Mode Styles */
        [data-theme="dark"] .modal-content {
            background: var(--bg-secondary) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .modal-header {
            border-bottom: 1px solid var(--border-color) !important;
            background: var(--bg-secondary) !important;
        }

        [data-theme="dark"] .modal-title {
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .modal-body {
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
        }

        [data-theme="dark"] .btn-close {
            filter: invert(1);
        }

        /* Light mode modal - ensure white background */
        [data-theme="light"] .modal-content,
        .modal-content {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            color: #1e293b !important;
        }

        [data-theme="light"] .modal-header,
        .modal-header {
            border-bottom: 1px solid #e2e8f0 !important;
            background: #ffffff !important;
        }

        [data-theme="light"] .modal-title,
        .modal-title {
            color: #1e293b !important;
        }

        [data-theme="light"] .modal-body,
        .modal-body {
            background: #ffffff !important;
            color: #1e293b !important;
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
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">🕐 Time Slots Management</h1>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="create.php" class="btn-action btn-primary">
                            ➕ Create Time Slot
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success glass-card fade-in">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card fade-in">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card glass-card slide-up">
                <div class="stat-icon total">🕐</div>
                <div class="stat-number"><?= isset($slotStats['total_slots']) ? $slotStats['total_slots'] : 0 ?></div>
                <div class="stat-label">Total Time Slots</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon active">✅</div>
                <div class="stat-number"><?= isset($slotStats['active_slots']) ? $slotStats['active_slots'] : 0 ?></div>
                <div class="stat-label">Active Slots</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon inactive">⏸️</div>
                <div class="stat-number"><?= isset($slotStats['inactive_slots']) ? $slotStats['inactive_slots'] : 0 ?></div>
                <div class="stat-label">Inactive Slots</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon regular">📚</div>
                <div class="stat-number"><?= isset($slotStats['regular_slots']) ? $slotStats['regular_slots'] : 0 ?></div>
                <div class="stat-label">Regular Classes</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon break">☕</div>
                <div class="stat-number"><?= isset($slotStats['break_slots']) ? $slotStats['break_slots'] : 0 ?></div>
                <div class="stat-label">Break Periods</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon lunch">🍽️</div>
                <div class="stat-number"><?= isset($slotStats['lunch_slots']) ? $slotStats['lunch_slots'] : 0 ?></div>
                <div class="stat-label">Lunch Breaks</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon usage">📈</div>
                <div class="stat-number"><?= isset($usageStats['total_scheduled_classes']) ? $usageStats['total_scheduled_classes'] : 0 ?></div>
                <div class="stat-label">Scheduled Classes</div>
            </div>

            <div class="stat-card glass-card slide-up">
                <div class="stat-icon duration">⏱️</div>
                <div class="stat-number"><?= round(isset($slotStats['avg_duration_minutes']) ? $slotStats['avg_duration_minutes'] : 0) ?></div>
                <div class="stat-label">Avg Minutes</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters glass-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search time slots..." id="searchInput">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19S2 15.194 2 10.5S5.806 2 10.5 2S19 5.806 19 10.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                
                <div class="filter-controls">
                    <div class="filter-group">
                        <label class="filter-label">Day</label>
                        <select class="filter-select" id="dayFilter" onchange="handleDayFilter()">
                            <option value="">All Days</option>
                            <option value="Monday" <?= $selectedDay == 'Monday' ? 'selected' : '' ?>>Monday</option>
                            <option value="Tuesday" <?= $selectedDay == 'Tuesday' ? 'selected' : '' ?>>Tuesday</option>
                            <option value="Wednesday" <?= $selectedDay == 'Wednesday' ? 'selected' : '' ?>>Wednesday</option>
                            <option value="Thursday" <?= $selectedDay == 'Thursday' ? 'selected' : '' ?>>Thursday</option>
                            <option value="Friday" <?= $selectedDay == 'Friday' ? 'selected' : '' ?>>Friday</option>
                            <option value="Saturday" <?= $selectedDay == 'Saturday' ? 'selected' : '' ?>>Saturday</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Type</label>
                        <select class="filter-select" id="typeFilter" onchange="handleTypeFilter()">
                            <option value="">All Types</option>
                            <option value="regular" <?= $selectedType == 'regular' ? 'selected' : '' ?>>Regular</option>
                            <option value="break" <?= $selectedType == 'break' ? 'selected' : '' ?>>Break</option>
                            <option value="lunch" <?= $selectedType == 'lunch' ? 'selected' : '' ?>>Lunch</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="filter-select" id="statusFilter" onchange="handleStatusFilter()">
                            <option value="">All Status</option>
                            <option value="active" <?= $selectedStatus == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $selectedStatus == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <button class="btn-action btn-outline" onclick="clearFilters()">
                        🔄 Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions (shown when slots are selected) -->
        <form method="post" id="bulkActionForm">
            <div class="bulk-actions" id="bulkActions">
                <div class="d-flex align-items-center gap-3">
                    <span><strong id="selectedCount">0</strong> time slots selected</span>
                    
                    <select name="bulk_action_type" class="filter-select">
                        <option value="">Choose Action</option>
                        <option value="activate">Activate Selected</option>
                        <option value="deactivate">Deactivate Selected</option>
                    </select>
                    
                    <button type="submit" name="action" value="bulk_action" class="btn-action btn-primary">
                        Apply Action
                    </button>
                    
                    <button type="button" class="btn-action btn-outline" onclick="clearSelection()">
                        Clear Selection
                    </button>
                </div>
            </div>

            <!-- Time Slots Table (Desktop) -->
            <?php if (!empty($timeSlots)): ?>
                <div class="timeslots-container glass-card">
                    <div class="table-responsive">
                        <table class="timeslots-table table table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                    </th>
                                    <th>Time Slot</th>
                                    <th>Day</th>
                                    <th>Duration</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Usage</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="timeslotsTableBody">
                                <?php foreach ($timeSlots as $slot): 
                                    $duration = (strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 60;
                                    $usagePercentage = $slot['usage_count'] > 0 ? min(($slot['usage_count'] / 10) * 100, 100) : 0;
                                    $usageClass = $usagePercentage >= 70 ? 'high' : ($usagePercentage >= 40 ? 'medium' : 'low');
                                ?>
                                    <tr class="slot-row" id="slot-<?= $slot['slot_id'] ?>"
                                         data-name="<?= strtolower($slot['slot_name']) ?>"
                                         data-day="<?= strtolower($slot['day_of_week']) ?>"
                                         data-type="<?= $slot['slot_type'] ?>"
                                         data-status="<?= $slot['is_active'] ? 'active' : 'inactive' ?>">
                                        <td>
                                            <input type="checkbox" name="selected_slots[]" value="<?= $slot['slot_id'] ?>" 
                                                   class="slot-checkbox" onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <div class="slot-info">
                                                <div class="time-slot-icon <?= $slot['slot_type'] ?>">
                                                    <?php 
                                                    $typeIcons = array(
                                                        'regular' => '📚',
                                                        'break' => '☕',
                                                        'lunch' => '🍽️'
                                                    );
                                                    echo isset($typeIcons[$slot['slot_type']]) ? $typeIcons[$slot['slot_type']] : '🕐';
                                                    ?>
                                                </div>
                                                <div class="slot-details">
                                                    <h6><?= htmlspecialchars($slot['slot_name']) ?></h6>
                                                    <p class="slot-meta">
                                                        <span class="time-display">
                                                            <?= date('g:i A', strtotime($slot['start_time'])) ?> - 
                                                            <?= date('g:i A', strtotime($slot['end_time'])) ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($slot['day_of_week']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="duration-display"><?= round($duration) ?> minutes</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-type-<?= $slot['slot_type'] ?>">
                                                <?= ucfirst($slot['slot_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-status-<?= $slot['is_active'] ? 'active' : 'inactive' ?>">
                                                <?= $slot['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="usage-indicator">
                                                <span style="font-size: 0.8125rem; color: var(--text-secondary);">
                                                    <?= $slot['usage_count'] ?> classes
                                                </span>
                                                <div class="usage-bar">
                                                    <div class="usage-fill <?= $usageClass ?>" style="width: <?= $usagePercentage ?>%"></div>
                                                </div>
                                            </div>
                                            <?php if ($slot['usage_count'] > 0): ?>
                                                <small style="color: var(--text-tertiary);">
                                                    <?= $slot['subjects_count'] ?> subjects, <?= $slot['faculty_count'] ?> faculty
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                                <button type="button" class="btn-action btn-primary btn-sm" 
                                                        onclick="viewSlotDetails(<?= $slot['slot_id'] ?>)" title="View Details">
                                                    👁️
                                                </button>

                                                <a href="edit.php?id=<?= $slot['slot_id'] ?>" 
                                                   class="btn-action btn-outline btn-sm" title="Edit Slot">
                                                    ✏️
                                                </a>

                                                <?php if ($slot['is_active']): ?>
                                                    <a href="?action=deactivate&slot_id=<?= $slot['slot_id'] ?><?= $selectedDay ? '&day=' . urlencode($selectedDay) : '' ?><?= $selectedType ? '&type=' . urlencode($selectedType) : '' ?><?= $selectedStatus ? '&status=' . urlencode($selectedStatus) : '' ?>" 
                                                       class="btn-action btn-outline btn-sm" 
                                                       onclick="return confirm('Deactivate this time slot?')" title="Deactivate">
                                                        ⏸️
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?action=activate&slot_id=<?= $slot['slot_id'] ?><?= $selectedDay ? '&day=' . urlencode($selectedDay) : '' ?><?= $selectedType ? '&type=' . urlencode($selectedType) : '' ?><?= $selectedStatus ? '&status=' . urlencode($selectedStatus) : '' ?>" 
                                                       class="btn-action btn-success btn-sm" 
                                                       onclick="return confirm('Activate this time slot?')" title="Activate">
                                                        ▶️
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($slot['usage_count'] == 0): ?>
                                                    <a href="delete.php?id=<?= $slot['slot_id'] ?>" 
                                                       class="btn-action btn-danger btn-sm" 
                                                       onclick="return confirm('Are you sure you want to delete this time slot? This action cannot be undone.')" 
                                                       title="Delete Slot">
                                                        🗑️
                                                    </a>
                                                <?php else: ?>
                                                    <span class="btn-action btn-outline btn-sm" 
                                                          style="opacity: 0.5; cursor: not-allowed;" 
                                                          title="Cannot delete - slot is in use">
                                                        🔒
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
        </form>

        <!-- Time Slots Cards (Mobile) -->
        <div id="timeslotsCards">
            <?php foreach ($timeSlots as $slot): 
                $duration = (strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 60;
                $usagePercentage = $slot['usage_count'] > 0 ? min(($slot['usage_count'] / 10) * 100, 100) : 0;
                $usageClass = $usagePercentage >= 70 ? 'high' : ($usagePercentage >= 40 ? 'medium' : 'low');
            ?>
                <div class="timeslot-card" id="timeslot-card-<?= $slot['slot_id'] ?>"
                     data-name="<?= strtolower($slot['slot_name']) ?>"
                     data-day="<?= strtolower($slot['day_of_week']) ?>"
                     data-type="<?= $slot['slot_type'] ?>"
                     data-status="<?= $slot['is_active'] ? 'active' : 'inactive' ?>">
                    
                    <div class="timeslot-card-header">
                        <div class="timeslot-card-avatar <?= $slot['slot_type'] ?>">
                            <?php 
                            $typeIcons = array(
                                'regular' => '📚',
                                'break' => '☕',
                                'lunch' => '🍽️'
                            );
                            echo isset($typeIcons[$slot['slot_type']]) ? $typeIcons[$slot['slot_type']] : '🕐';
                            ?>
                        </div>
                        <div class="timeslot-card-info">
                            <h6><?= htmlspecialchars($slot['slot_name']) ?></h6>
                            <div class="timeslot-card-meta"><?= htmlspecialchars($slot['day_of_week']) ?></div>
                            <div class="timeslot-card-meta">
                                <span class="badge badge-type-<?= $slot['slot_type'] ?>"><?= ucfirst($slot['slot_type']) ?></span>
                                <span class="badge badge-status-<?= $slot['is_active'] ? 'active' : 'inactive' ?>"><?= $slot['is_active'] ? 'Active' : 'Inactive' ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="timeslot-card-details">
                        <div class="timeslot-card-detail">
                            <strong>Start Time:</strong><br>
                            <span class="time-display"><?= date('g:i A', strtotime($slot['start_time'])) ?></span>
                        </div>
                        <div class="timeslot-card-detail">
                            <strong>End Time:</strong><br>
                            <span class="time-display"><?= date('g:i A', strtotime($slot['end_time'])) ?></span>
                        </div>
                        <div class="timeslot-card-detail">
                            <strong>Duration:</strong><br>
                            <span><?= round($duration) ?> minutes</span>
                        </div>
                        <div class="timeslot-card-detail">
                            <strong>Usage:</strong><br>
                            <span><?= $slot['usage_count'] ?> classes (<?= round($usagePercentage) ?>%)</span>
                        </div>
                        <?php if ($slot['usage_count'] > 0): ?>
                        <div class="timeslot-card-detail">
                            <strong>Subjects:</strong><br>
                            <span><?= $slot['subjects_count'] ?> different subjects</span>
                        </div>
                        <div class="timeslot-card-detail">
                            <strong>Faculty:</strong><br>
                            <span><?= $slot['faculty_count'] ?> faculty members</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="timeslot-card-actions">
                        <button type="button" class="btn-action btn-primary" onclick="viewSlotDetails(<?= $slot['slot_id'] ?>)">
                            👁️ View Details
                        </button>
                        
                        <a href="edit.php?id=<?= $slot['slot_id'] ?>" class="btn-action btn-outline">
                            ✏️ Edit Slot
                        </a>
                        
                        <?php if ($slot['is_active']): ?>
                            <a href="?action=deactivate&slot_id=<?= $slot['slot_id'] ?><?= $selectedDay ? '&day=' . urlencode($selectedDay) : '' ?>" 
                               class="btn-action btn-outline"
                               onclick="return confirm('Deactivate this time slot?')">
                                ⏸️ Deactivate
                            </a>
                        <?php else: ?>
                            <a href="?action=activate&slot_id=<?= $slot['slot_id'] ?><?= $selectedDay ? '&day=' . urlencode($selectedDay) : '' ?>" 
                               class="btn-action btn-success"
                               onclick="return confirm('Activate this time slot?')">
                                ▶️ Activate
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($slot['usage_count'] == 0): ?>
                            <a href="delete.php?id=<?= $slot['slot_id'] ?>" 
                               class="btn-action btn-danger"
                               onclick="return confirm('Are you sure you want to delete this time slot? This action cannot be undone.')">
                                🗑️ Delete Slot
                            </a>
                        <?php else: ?>
                            <span class="btn-action btn-outline" 
                                  style="opacity: 0.5; cursor: not-allowed;" 
                                  title="Cannot delete - slot is in use">
                                🔒 In Use (<?= $slot['usage_count'] ?> classes)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
            <div class="empty-state glass-card">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                </svg>
                <h3>No Time Slots Found</h3>
                <p>
                    <?php if (!empty($selectedDay) || !empty($selectedType) || !empty($selectedStatus)): ?>
                        No time slots match your current filter criteria. Try adjusting your filters or clear them to see all slots.
                    <?php else: ?>
                        No time slots are currently configured in the system. Create time slots to start building your timetable structure.
                    <?php endif; ?>
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="create.php" class="btn-action btn-primary">
                        ➕ Create First Time Slot
                    </a>
                    <?php if (!empty($selectedDay) || !empty($selectedType) || !empty($selectedStatus)): ?>
                        <button onclick="clearFilters()" class="btn-action btn-outline">
                            🔄 Clear Filters
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Time Slot Details Modal -->
    <div class="modal fade" id="slotDetailsModal" tabindex="-1" aria-labelledby="slotDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="slotDetailsModalLabel">Time Slot Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="slotDetailsContent">
                    <div class="text-center">
                        <div class="loading-shimmer" style="height: 200px; border-radius: 8px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize search and filters
            initializeSearchAndFilters();
            
            
            // Apply current theme
            applyCurrentTheme();
            
            // Add animation delays
            const animatedElements = document.querySelectorAll('.slide-up');
            animatedElements.forEach((element, index) => {
                element.style.animationDelay = (index * 0.1) + 's';
            });

            // Sidebar handlers removed to prevent unintended expansion

            // Initialize bulk actions
            updateBulkActions();

			// Auto-hide success alert after 5 seconds
			const successAlert = document.querySelector('.alert-success');
			if (successAlert) {
				setTimeout(function() {
					// Smooth fade out, then remove
					successAlert.style.transition = 'opacity 0.5s ease';
					successAlert.style.opacity = '0';
					setTimeout(function() {
						if (successAlert && successAlert.parentNode) {
							successAlert.parentNode.removeChild(successAlert);
						}
					}, 600);
				}, 5000);
			}
        });


        // Sidebar JS handlers removed to prevent unintended expansion on single link clicks.

        // Search and filter functionality (instant, no reload)
        function initializeSearchAndFilters() {
            const searchInput = document.getElementById('searchInput');
            const dayFilter = document.getElementById('dayFilter');
            const typeFilter = document.getElementById('typeFilter');
            const statusFilter = document.getElementById('statusFilter');

            let hasScrolledToResults = false;
            function scrollToResultsOnce() {
                if (hasScrolledToResults) return;
                const container = document.querySelector('.timeslots-container') || document.getElementById('timeslotsCards');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    hasScrolledToResults = true;
                }
            }

            function filterSlots() {
                const term = (searchInput?.value || '').toLowerCase();
                const vDay = (dayFilter?.value || '').toLowerCase();
                const vType = (typeFilter?.value || '').toLowerCase();
                const vStatus = (statusFilter?.value || '').toLowerCase();

                const tableRows = document.querySelectorAll('.slot-row');
                const timeslotCards = document.querySelectorAll('.timeslot-card');
                let visibleCount = 0;

                function applyFilter(elements) {
                    elements.forEach(function(element) {
                        const name = (element.dataset.name || '').toLowerCase();
                        const day = (element.dataset.day || '').toLowerCase();
                        const type = (element.dataset.type || '').toLowerCase();
                        const status = (element.dataset.status || '').toLowerCase();

                        const matchesTerm = !term || name.includes(term) || day.includes(term) || type.includes(term);
                        const matchesDay = !vDay || day === vDay;
                        const matchesType = !vType || type === vType;
                        const matchesStatus = !vStatus || status === vStatus;

                        const show = matchesTerm && matchesDay && matchesType && matchesStatus;
                        element.style.display = show ? '' : 'none';
                        if (show) visibleCount++;
                    });
                }

                applyFilter(tableRows);
                applyFilter(timeslotCards);

                if (visibleCount === 0 && (tableRows.length > 0 || timeslotCards.length > 0)) {
                    showNoResultsMessage();
                } else {
                    hideNoResultsMessage();
                }

                // Hide alerts while actively filtering/searching
                const isFiltering = !!term || !!vDay || !!vType || !!vStatus;
                document.querySelectorAll('.alert').forEach(a => { a.style.display = isFiltering ? 'none' : ''; });
            }

            // Call scrollToResultsOnce() only after user interactions
            if (searchInput) searchInput.addEventListener('input', () => { filterSlots(); scrollToResultsOnce(); });
            if (dayFilter) dayFilter.addEventListener('change', () => { filterSlots(); scrollToResultsOnce(); });
            if (typeFilter) typeFilter.addEventListener('change', () => { filterSlots(); scrollToResultsOnce(); });
            if (statusFilter) statusFilter.addEventListener('change', () => { filterSlots(); scrollToResultsOnce(); });

            // Initial pass for any preselected values
            filterSlots();
        }

        function showNoResultsMessage() {
            hideNoResultsMessage();
            
            const noResultsDiv = document.createElement('div');
            noResultsDiv.id = 'noResultsMessage';
            noResultsDiv.className = 'empty-state glass-card';
            noResultsDiv.innerHTML = '\
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">\
                    <path d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19S2 15.194 2 10.5S5.806 2 10.5 2S19 5.806 19 10.5Z" stroke="currentColor" stroke-width="2"/>\
                </svg>\
                <h3>No Time Slots Found</h3>\
                <p>No time slots match your current search criteria. Try adjusting your search terms.</p>\
            ';
            
            document.querySelector('.main-content').appendChild(noResultsDiv);
        }

        function hideNoResultsMessage() {
            const existingMessage = document.getElementById('noResultsMessage');
            if (existingMessage) {
                existingMessage.remove();
            }
        }

        // Filter functions
        function clearFilters() {
            const searchInput = document.getElementById('searchInput');
            const dayFilter = document.getElementById('dayFilter');
            const typeFilter = document.getElementById('typeFilter');
            const statusFilter = document.getElementById('statusFilter');
            if (searchInput) searchInput.value = '';
            if (dayFilter) dayFilter.value = '';
            if (typeFilter) typeFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            if (typeof initializeSearchAndFilters === 'function') initializeSearchAndFilters();
        }

        // Deprecated URL-based handlers kept as no-ops
        function handleDayFilter() { /* handled by change listener */ }
        function handleTypeFilter() { /* handled by change listener */ }
        function handleStatusFilter() { /* handled by change listener */ }

        // Deprecated updateUrlWithFilters removed (no longer used)

        // Bulk Actions Management
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const slotCheckboxes = document.querySelectorAll('.slot-checkbox');
            
            slotCheckboxes.forEach(function(checkbox) {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const selectedCheckboxes = document.querySelectorAll('.slot-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');
            
            if (selectedCount) {
                selectedCount.textContent = selectedCheckboxes.length;
            }
            
            if (bulkActions) {
                if (selectedCheckboxes.length > 0) {
                    bulkActions.classList.add('show');
                } else {
                    bulkActions.classList.remove('show');
                }
            }
            
            if (selectAll) {
                const allCheckboxes = document.querySelectorAll('.slot-checkbox');
                selectAll.indeterminate = selectedCheckboxes.length > 0 && selectedCheckboxes.length < allCheckboxes.length;
                selectAll.checked = selectedCheckboxes.length === allCheckboxes.length && allCheckboxes.length > 0;
            }
        }

        function clearSelection() {
            const slotCheckboxes = document.querySelectorAll('.slot-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            slotCheckboxes.forEach(function(checkbox) {
                checkbox.checked = false;
            });
            
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            
            updateBulkActions();
        }

        // View slot details modal
        function viewSlotDetails(slotId) {
            const modal = new bootstrap.Modal(document.getElementById('slotDetailsModal'));
            const modalContent = document.getElementById('slotDetailsContent');
            
            modalContent.innerHTML = '\
                <div class="text-center">\
                    <div class="loading-shimmer" style="height: 200px; border-radius: 8px; margin-bottom: 1rem;"></div>\
                    <p>Loading time slot details...</p>\
                </div>\
            ';
            
            modal.show();

            // Fetch data
            const xhr = new XMLHttpRequest();
            xhr.open('GET', '../../includes/api/timeslot-details.php?id=' + slotId);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.timeout = 10000;
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            modalContent.innerHTML = generateSlotDetailsHTML(data.slot);
                        } else {
                            modalContent.innerHTML = '\
                                <div class="alert alert-danger">\
                                    <strong>Error:</strong> ' + (data.message || 'Failed to load time slot details') + '\
                                </div>\
                            ';
                        }
                    } catch (e) {
                        modalContent.innerHTML = '\
                            <div class="alert alert-danger">\
                                <strong>Error:</strong> Invalid response from server\
                            </div>\
                        ';
                    }
                } else {
                    modalContent.innerHTML = '\
                        <div class="alert alert-danger">\
                            <strong>Error:</strong> Failed to load time slot details.\
                        </div>\
                    ';
                }
            };
            
            xhr.onerror = function() {
                modalContent.innerHTML = '\
                    <div class="alert alert-danger">\
                        <strong>Error:</strong> Network error occurred.\
                    </div>\
                ';
            };

            xhr.ontimeout = function() {
                modalContent.innerHTML = '\
                    <div class="alert alert-danger">\
                        <strong>Error:</strong> Request timed out.\
                    </div>\
                ';
            };
            
            xhr.send();
        }

        function generateSlotDetailsHTML(slot) {
            const typeIcons = {
                'regular': '📚',
                'break': '☕',
                'lunch': '🍽️'
            };

            return '\
                <div class="row">\
                    <div class="col-md-4 text-center mb-3">\
                        <div class="time-slot-icon ' + slot.slot_type + ' mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">\
                            ' + (typeIcons[slot.slot_type] || '🕐') + '\
                        </div>\
                        <h4>' + slot.slot_name + '</h4>\
                        <p class="text-muted">' + slot.day_of_week + '</p>\
                    </div>\
                    <div class="col-md-8">\
                        <div class="row">\
                            <div class="col-sm-6 mb-3">\
                                <strong>Start Time:</strong><br>\
                                <span class="time-display" style="font-size: 1.25rem;">' + formatTime(slot.start_time) + '</span>\
                            </div>\
                            <div class="col-sm-6 mb-3">\
                                <strong>End Time:</strong><br>\
                                <span class="time-display" style="font-size: 1.25rem;">' + formatTime(slot.end_time) + '</span>\
                            </div>\
                            <div class="col-sm-6 mb-3">\
                                <strong>Duration:</strong><br>\
                                ' + slot.duration_minutes + ' minutes\
                            </div>\
                            <div class="col-sm-6 mb-3">\
                                <strong>Status:</strong><br>\
                                <span style="color: ' + (slot.is_active ? '#10b981' : '#64748b') + ';">\
                                    ' + (slot.is_active ? '✅ Active' : '⏸️ Inactive') + '\
                                </span>\
                            </div>\
                        </div>\
                    </div>\
                </div>\
            ';
        }

        function formatTime(timeString) {
            const time = new Date('1970-01-01T' + timeString);
            return time.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit', 
                hour12: true 
            });
        }

        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Listen for theme changes
        window.addEventListener('themeChanged', function(event) {
            applyCurrentTheme();
        });

        // Form submission handling
        const bulkActionForm = document.getElementById('bulkActionForm');
        if (bulkActionForm) {
            bulkActionForm.addEventListener('submit', function(e) {
                const selectedSlots = document.querySelectorAll('.slot-checkbox:checked');
                const actionSelect = this.querySelector('select[name="bulk_action_type"]');
                const actionType = actionSelect ? actionSelect.value : '';
                
                if (selectedSlots.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one time slot to perform this action.');
                    return;
                }
                
                const bulkActions = document.getElementById('bulkActions');
                if (bulkActions && bulkActions.classList.contains('show')) {
                    if (!actionType) {
                        e.preventDefault();
                        alert('Please select an action to perform.');
                        if (actionSelect) {
                            actionSelect.focus();
                        }
                        return;
                    }
                    
                    const confirmMessage = 'Are you sure you want to ' + actionType + ' ' + selectedSlots.length + ' selected time slot(s)?';
                    if (!confirm(confirmMessage)) {
                        e.preventDefault();
                    }
                } else {
                    e.preventDefault();
                }
            });
        }
    </script>
</body>
</html>