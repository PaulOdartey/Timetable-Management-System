<?php
/**
 * Admin Classroom Utilization Analytics
 * Timetable Management System
 * 
 * Professional enterprise-style classroom utilization dashboard with comprehensive analytics,
 * real-time metrics, capacity analysis, and resource optimization insights
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Classroom.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$currentUserId = User::getCurrentUserId();
$db = Database::getInstance();
$classroomManager = new Classroom();

// Initialize variables
$error_message = '';
$success_message = '';
$utilizationData = [];
$weeklyUtilization = [];
$departmentUtilization = [];
$peakHours = [];
$underutilizedRooms = [];
$filters = [];

// Get filter parameters
$selectedBuilding = $_GET['building'] ?? '';
$selectedFloor = $_GET['floor'] ?? '';
$selectedDepartment = $_GET['department'] ?? '';
$selectedCapacityRange = $_GET['capacity_range'] ?? '';
$selectedPeriod = $_GET['period'] ?? 'current_week';
$selectedMetric = $_GET['metric'] ?? 'utilization_rate';

try {
    // Get current academic settings
    $currentYear = $db->fetchRow("SELECT setting_value FROM system_settings WHERE setting_key = 'academic_year_current'")['setting_value'] ?? '2025-2026';
    $currentSemester = $db->fetchRow("SELECT setting_value FROM system_settings WHERE setting_key = 'semester_current'")['setting_value'] ?? '1';
    
    // Build date range based on selected period
    $dateRange = calculateDateRange($selectedPeriod);
    
    // Get comprehensive utilization analytics
    $utilizationData = getComprehensiveUtilizationData($db, $dateRange, [
        'building' => $selectedBuilding,
        'floor' => $selectedFloor,
        'department' => $selectedDepartment,
        'capacity_range' => $selectedCapacityRange
    ]);
    
    // Get weekly utilization trends
    $weeklyUtilization = getWeeklyUtilizationTrends($db, $dateRange, $selectedBuilding);
    
    // Get department-wise utilization
    $departmentUtilization = getDepartmentUtilization($db, $dateRange);
    
    // Get peak hours analysis
    $peakHours = getPeakHoursAnalysis($db, $dateRange);
    
    // Get underutilized rooms
    $underutilizedRooms = getUnderutilizedRooms($db, $dateRange);
    
    // Get filter options
    $buildings = $db->fetchAll("SELECT DISTINCT building FROM classrooms WHERE is_active = 1 ORDER BY building");
    $floors = $db->fetchAll("SELECT DISTINCT floor FROM classrooms WHERE is_active = 1 AND floor IS NOT NULL ORDER BY floor");
    $departments = $db->fetchAll("SELECT department_code, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
    
} catch (Exception $e) {
    // Log detailed error to project error log for diagnosis
    $logFile = __DIR__ . '/../../logs/error.log';
    $logMessage = '[' . date('Y-m-d H:i:s') . "] Classroom Utilization Error: " . $e->getMessage() . "\n";
    @error_log($logMessage, 3, $logFile);
    $error_message = "An error occurred while loading utilization data.";
}

/**
 * Calculate date range based on selected period
 */
function calculateDateRange($period) {
    $endDate = new DateTime();
    $startDate = new DateTime();
    
    switch ($period) {
        case 'today':
            $startDate = new DateTime();
            break;
        case 'current_week':
            $startDate->modify('monday this week');
            $endDate->modify('sunday this week');
            break;
        case 'current_month':
            $startDate->modify('first day of this month');
            $endDate->modify('last day of this month');
            break;
        case 'last_month':
            $startDate->modify('first day of last month');
            $endDate->modify('last day of last month');
            break;
        case 'current_semester':
            $startDate->modify('-3 months');
            break;
        default:
            $startDate->modify('monday this week');
            $endDate->modify('sunday this week');
    }
    
    return [
        'start' => $startDate->format('Y-m-d'),
        'end' => $endDate->format('Y-m-d')
    ];
}

/**
 * Get comprehensive classroom utilization data
 */
function getComprehensiveUtilizationData($db, $dateRange, $filters) {
    $whereConditions = ['t.is_active = 1'];
    $params = [];
    
    // Apply filters
    if (!empty($filters['building'])) {
        $whereConditions[] = "c.building = ?";
        $params[] = $filters['building'];
    }
    
    if (!empty($filters['floor'])) {
        $whereConditions[] = "c.floor = ?";
        $params[] = $filters['floor'];
    }
    
    if (!empty($filters['department'])) {
        $whereConditions[] = "c.department_id = (SELECT department_id FROM departments WHERE department_code = ?)";
        $params[] = $filters['department'];
    }
    
    if (!empty($filters['capacity_range'])) {
        switch ($filters['capacity_range']) {
            case 'small':
                $whereConditions[] = "c.capacity <= 30";
                break;
            case 'medium':
                $whereConditions[] = "c.capacity BETWEEN 31 AND 60";
                break;
            case 'large':
                $whereConditions[] = "c.capacity > 60";
                break;
        }
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT 
            c.classroom_id,
            c.room_number,
            c.building,
            c.floor,
            c.capacity,
            c.type,
            c.facilities,
            COALESCE(d.department_name, 'Shared') as department,
            
            -- Calculate utilization metrics
            COUNT(DISTINCT t.timetable_id) as total_scheduled_hours,
            
            -- Total available hours calculation (Monday-Friday, typical academic hours)
            (5 * 8) as total_available_hours_per_week,
            
            -- Utilization rate
            ROUND(
                (COUNT(DISTINCT t.timetable_id) * 100.0) / 
                NULLIF((5 * 8), 0), 2
            ) as utilization_percentage,
            
            -- Average occupancy
            ROUND(
                AVG(COALESCE(enrollment_stats.enrolled_count, 0)) / c.capacity * 100, 2
            ) as average_occupancy_percentage,
            
            -- Peak usage information
            MAX(COALESCE(enrollment_stats.enrolled_count, 0)) as peak_occupancy,
            MIN(COALESCE(enrollment_stats.enrolled_count, 0)) as min_occupancy,
            
            -- Most common usage day
            (
                SELECT ts.day_of_week 
                FROM timetables t2 
                JOIN time_slots ts ON t2.slot_id = ts.slot_id 
                WHERE t2.classroom_id = c.classroom_id AND t2.is_active = 1
                GROUP BY ts.day_of_week 
                ORDER BY COUNT(*) DESC 
                LIMIT 1
            ) as most_used_day,
            
            -- Revenue/Cost efficiency (if applicable)
            COUNT(DISTINCT s.subject_id) as unique_subjects_hosted,
            COUNT(DISTINCT f.faculty_id) as unique_faculty_using,
            
            -- Status indicators
            CASE 
                WHEN COUNT(DISTINCT t.timetable_id) = 0 THEN 'unused'
                WHEN (COUNT(DISTINCT t.timetable_id) * 100.0) / NULLIF((5 * 8), 0) < 30 THEN 'underutilized'
                WHEN (COUNT(DISTINCT t.timetable_id) * 100.0) / NULLIF((5 * 8), 0) > 80 THEN 'overutilized'
                ELSE 'optimal'
            END as utilization_status
            
        FROM classrooms c
        LEFT JOIN timetables t ON c.classroom_id = t.classroom_id AND t.is_active = 1
        LEFT JOIN subjects s ON t.subject_id = s.subject_id
        LEFT JOIN faculty f ON t.faculty_id = f.faculty_id
        LEFT JOIN departments d ON c.department_id = d.department_id
        LEFT JOIN (
            SELECT 
                t.classroom_id,
                t.timetable_id,
                COUNT(e.enrollment_id) as enrolled_count
            FROM timetables t
            LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
                AND t.section = e.section 
                AND t.semester = e.semester 
                AND t.academic_year = e.academic_year
                AND e.status = 'enrolled'
            GROUP BY t.classroom_id, t.timetable_id
        ) enrollment_stats ON t.timetable_id = enrollment_stats.timetable_id
        WHERE c.is_active = 1 AND {$whereClause}
        GROUP BY c.classroom_id, c.room_number, c.building, c.floor, c.capacity, c.type, c.facilities, d.department_name
        ORDER BY utilization_percentage DESC, c.building, c.room_number
    ";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Get weekly utilization trends
 */
function getWeeklyUtilizationTrends($db, $dateRange, $building = '') {
    // Use separate conditions for contexts with and without table alias
    $condWithAlias = $building ? "AND c.building = ?" : "";
    $condNoAlias = $building ? "AND classrooms.building = ?" : "";
    // Provide parameters for all occurrences (JOIN + two subqueries)
    $params = $building ? [$building, $building, $building] : [];
    
    $sql = "
        SELECT 
            ts.day_of_week,
            CONCAT(DATE_FORMAT(ts.start_time, '%H:%i'), ' - ', DATE_FORMAT(ts.end_time, '%H:%i')) as time_slot,
            ts.start_time,
            COUNT(t.timetable_id) as scheduled_classes,
            COUNT(DISTINCT c.classroom_id) as rooms_in_use,
            (SELECT COUNT(*) FROM classrooms WHERE is_active = 1 {$condNoAlias}) as total_rooms,
            ROUND(
                COUNT(DISTINCT c.classroom_id) * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM classrooms WHERE is_active = 1 {$condNoAlias}), 0), 2
            ) as room_utilization_percentage
        FROM time_slots ts
        LEFT JOIN timetables t ON ts.slot_id = t.slot_id AND t.is_active = 1
        LEFT JOIN classrooms c ON t.classroom_id = c.classroom_id AND c.is_active = 1 {$condWithAlias}
        WHERE ts.is_active = 1
        GROUP BY ts.day_of_week, ts.start_time, ts.end_time
        ORDER BY 
            FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
            ts.start_time
    ";
    
    return $db->fetchAll($sql, $params);
}

/**
 * Get department utilization data
 */
function getDepartmentUtilization($db, $dateRange) {
    $sql = "
        SELECT 
            d.department_code,
            d.department_name,
            COUNT(DISTINCT c.classroom_id) as owned_classrooms,
            COUNT(DISTINCT t.timetable_id) as total_classes_scheduled,
            COUNT(DISTINCT CASE WHEN c.department_id = d.department_id THEN t.timetable_id END) as own_room_usage,
            COUNT(DISTINCT CASE WHEN c.department_id != d.department_id OR c.department_id IS NULL THEN t.timetable_id END) as external_room_usage,
            
            ROUND(
                COUNT(DISTINCT CASE WHEN c.department_id = d.department_id THEN t.timetable_id END) * 100.0 /
                NULLIF(COUNT(DISTINCT c.classroom_id) * 40, 0), 2
            ) as own_room_utilization_rate,
            
            ROUND(
                AVG(c.capacity), 0
            ) as avg_room_capacity,
            
            SUM(COALESCE(enrollment_stats.total_enrollments, 0)) as total_student_hours
            
        FROM departments d
        LEFT JOIN classrooms c ON d.department_id = c.department_id AND c.is_active = 1
        LEFT JOIN timetables t ON c.classroom_id = t.classroom_id AND t.is_active = 1
        LEFT JOIN subjects s ON t.subject_id = s.subject_id AND s.department_id = d.department_id
        LEFT JOIN (
            SELECT 
                t.subject_id,
                COUNT(e.enrollment_id) as total_enrollments
            FROM timetables t
            LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
                AND t.section = e.section 
                AND t.semester = e.semester 
                AND t.academic_year = e.academic_year
                AND e.status = 'enrolled'
            GROUP BY t.subject_id
        ) enrollment_stats ON s.subject_id = enrollment_stats.subject_id
        WHERE d.is_active = 1
        GROUP BY d.department_id, d.department_code, d.department_name
        ORDER BY own_room_utilization_rate DESC
    ";
    
    return $db->fetchAll($sql);
}

/**
 * Get peak hours analysis
 */
function getPeakHoursAnalysis($db, $dateRange) {
    $sql = "
        SELECT 
            CONCAT(DATE_FORMAT(ts.start_time, '%H:%i'), ' - ', DATE_FORMAT(ts.end_time, '%H:%i')) as time_slot,
            ts.start_time,
            ts.day_of_week,
            COUNT(t.timetable_id) as total_classes,
            COUNT(DISTINCT c.classroom_id) as rooms_in_use,
            ROUND(AVG(c.capacity), 0) as avg_room_capacity,
            
            -- Calculate demand intensity
            ROUND(
                COUNT(t.timetable_id) * 100.0 / 
                NULLIF(COUNT(DISTINCT c.classroom_id), 0), 2
            ) as demand_intensity,
            
            -- Peak indicator
            CASE 
                WHEN COUNT(t.timetable_id) > (
                    SELECT AVG(class_count) * 1.5 FROM (
                        SELECT COUNT(t2.timetable_id) as class_count
                        FROM time_slots ts2
                        LEFT JOIN timetables t2 ON ts2.slot_id = t2.slot_id
                        WHERE t2.is_active = 1
                        GROUP BY ts2.slot_id
                    ) as avg_calc
                ) THEN 'peak'
                WHEN COUNT(t.timetable_id) < (
                    SELECT AVG(class_count) * 0.5 FROM (
                        SELECT COUNT(t2.timetable_id) as class_count
                        FROM time_slots ts2
                        LEFT JOIN timetables t2 ON ts2.slot_id = t2.slot_id
                        WHERE t2.is_active = 1
                        GROUP BY ts2.slot_id
                    ) as avg_calc
                ) THEN 'low'
                ELSE 'normal'
            END as usage_level
            
        FROM time_slots ts
        LEFT JOIN timetables t ON ts.slot_id = t.slot_id AND t.is_active = 1
        LEFT JOIN classrooms c ON t.classroom_id = c.classroom_id
        WHERE ts.is_active = 1
        GROUP BY ts.slot_id, ts.start_time, ts.end_time, ts.day_of_week
        HAVING total_classes > 0
        ORDER BY total_classes DESC, ts.start_time
    ";
    
    return $db->fetchAll($sql);
}

/**
 * Get underutilized rooms
 */
function getUnderutilizedRooms($db, $dateRange) {
    $sql = "
        SELECT 
            c.classroom_id,
            c.room_number,
            c.building,
            c.floor,
            c.capacity,
            c.type,
            c.facilities,
            d.department_name,
            
            COUNT(t.timetable_id) as scheduled_hours,
            ROUND(
                COUNT(t.timetable_id) * 100.0 / 40, 2
            ) as utilization_rate,
            
            -- Potential capacity
            ROUND(c.capacity * COUNT(t.timetable_id), 0) as potential_student_hours,
            ROUND(AVG(COALESCE(enrollment_stats.enrolled_count, 0)), 0) as avg_actual_occupancy,
            
            -- Recommendations
            CASE 
                WHEN COUNT(t.timetable_id) = 0 THEN 'Consider repurposing or maintenance'
                WHEN COUNT(t.timetable_id) < 5 THEN 'High potential for additional scheduling'
                WHEN AVG(COALESCE(enrollment_stats.enrolled_count, 0)) < c.capacity * 0.3 THEN 'Consider smaller capacity alternative'
                ELSE 'Monitor for optimization opportunities'
            END as recommendation,
            
            -- Cost efficiency (if room is underutilized, cost per student hour is high)
            CASE 
                WHEN AVG(COALESCE(enrollment_stats.enrolled_count, 0)) > 0 THEN
                    ROUND(c.capacity / AVG(COALESCE(enrollment_stats.enrolled_count, 0)), 2)
                ELSE NULL
            END as capacity_efficiency_ratio
            
        FROM classrooms c
        LEFT JOIN timetables t ON c.classroom_id = t.classroom_id AND t.is_active = 1
        LEFT JOIN departments d ON c.department_id = d.department_id
        LEFT JOIN (
            SELECT 
                t.classroom_id,
                AVG(enrollment_count.enrolled_count) as enrolled_count
            FROM timetables t
            LEFT JOIN (
                SELECT 
                    t.subject_id, t.section, t.semester, t.academic_year,
                    COUNT(e.enrollment_id) as enrolled_count
                FROM timetables t
                LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
                    AND t.section = e.section 
                    AND t.semester = e.semester 
                    AND t.academic_year = e.academic_year
                    AND e.status = 'enrolled'
                GROUP BY t.subject_id, t.section, t.semester, t.academic_year
            ) enrollment_count ON t.subject_id = enrollment_count.subject_id
                AND t.section = enrollment_count.section
                AND t.semester = enrollment_count.semester
                AND t.academic_year = enrollment_count.academic_year
            GROUP BY t.classroom_id
        ) enrollment_stats ON c.classroom_id = enrollment_stats.classroom_id
        WHERE c.is_active = 1
        GROUP BY c.classroom_id, c.room_number, c.building, c.floor, c.capacity, c.type, c.facilities, d.department_name
        HAVING utilization_rate < 50 OR avg_actual_occupancy < c.capacity * 0.4
        ORDER BY utilization_rate ASC, capacity_efficiency_ratio DESC
    ";
    
    return $db->fetchAll($sql);
}

// Set page title
$pageTitle = "Classroom Utilization Analytics";
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
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
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
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
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
            --glass-bg: rgba(15, 23, 42, 0.8);
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
            --glass-border: rgba(203, 213, 225, 0.5);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--navbar-height) + 2rem);
        }

        /* Page Header - Sticky */
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
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .header-card.glass-card {
            border: none !important;
        }

        [data-theme="dark"] .header-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
            border: none;
        }

        .page-title {
            font-size: 1.75rem; /* slightly smaller to reduce header height */
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem; /* tighter spacing */
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 0;
        }

        /* Mobile header improvements */
        @media (max-width: 768px) {
            .page-header .page-title {
                font-size: 1.25rem;
                margin: 0; /* keep compact */
            }
            /* Hide export button on mobile */
            .export-btn {
                display: none !important;
            }
            .btn-action {
                padding: 0.45rem 0.65rem;
                font-size: 0.85rem;
            }
        }

        /* Glass card styling */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        /* Mobile blue border accent for cards */
        @media (max-width: 768px) {
            .glass-card {
                border-color: rgba(59, 130, 246, 0.6) !important;
                border-width: 2px !important;
                box-shadow: inset 4px 0 0 0 #3b82f6, 0 0 0 1px rgba(59, 130, 246, 0.15);
            }
            
            /* Remove all borders and shadows from header card on mobile */
            .header-card.glass-card {
                border: none !important;
                border-width: 0 !important;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
            }
        }

        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        /* Analytics Dashboard Layout */
        .analytics-grid {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Key Metrics Cards */
        .metrics-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .metric-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .metric-icon::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            border-radius: inherit;
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
        }

        .metric-icon.utilization { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
        .metric-icon.capacity { background: linear-gradient(135deg, #10b981 0%, #047857 100%); }
        .metric-icon.efficiency { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .metric-icon.optimization { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }

        .metric-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .metric-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-change {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-weight: 600;
        }

        .metric-change.positive {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .metric-change.negative {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
        }

        /* Filter Panel */
        .filter-panel {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(15px);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        /* Charts Container */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            padding: 1.5rem;
            height: 400px;
            position: relative;
            margin-bottom: 1rem;
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

     /* Data Tables */
        .data-table-container {
            padding: 1.5rem;
        }

        /* Mobile: light blue border around table display card */
        @media (max-width: 768px) {
            .glass-card .data-table-container {
                border: 1px solid rgba(59, 130, 246, 0.35); /* light blue border */
                border-radius: 12px;
                padding: 1rem; /* slightly tighter on mobile */
                box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.12) inset;
            }
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .data-table thead th {
            /* Make header fully opaque so body cells do not show through when sticky */
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 600;
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 3; /* above body cells */
            background-clip: padding-box;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        /* Ensure header row background is solid across themes */
        .data-table thead {
            background: var(--bg-secondary);
        }

        /* Create a stacking context for the scroll container so sticky headers layer correctly */
        .data-table-container > div[style*="overflow-y: auto"] {
            position: relative;
            z-index: 1;
        }

        .data-table tbody td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            vertical-align: middle;
        }

        .data-table tbody tr:hover {
            background: rgba(99, 102, 241, 0.05);
        }

        [data-theme="dark"] .data-table tbody tr:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.optimal {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-badge.underutilized {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-badge.overutilized {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-badge.unused {
            background: rgba(107, 114, 128, 0.1);
            color: var(--text-secondary);
            border: 1px solid rgba(107, 114, 128, 0.3);
        }

        /* Progress Bars */
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        [data-theme="dark"] .progress-bar-container {
            background: rgba(71, 85, 105, 0.3);
        }

        .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.8s ease-in-out;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: progress-shine 2s infinite;
        }

        @keyframes progress-shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .progress-bar.high {
            background: linear-gradient(90deg, var(--success-color), #34d399);
        }

        .progress-bar.medium {
            background: linear-gradient(90deg, var(--warning-color), #fbbf24);
        }

        .progress-bar.low {
            background: linear-gradient(90deg, var(--error-color), #f87171);
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--glass-bg);
            color: var(--text-primary);
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        /* Dark mode form controls */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: var(--bg-secondary);
            border-color: var(--border-color);
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

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            background: var(--glass-bg);
            color: var(--text-primary);
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
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

        /* Recommendation Cards */
        .recommendation-card {
            padding: 1rem;
            border-left: 4px solid var(--primary-color);
            background: rgba(99, 102, 241, 0.05);
            border-radius: 0 8px 8px 0;
            margin-bottom: 0.75rem;
        }

        .recommendation-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .recommendation-text {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: calc(var(--navbar-height) + 1rem);
            }

            .metrics-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .metrics-row {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .data-table-container {
                overflow-x: auto;
            }

            .chart-container {
                height: 300px;
            }
            /* Transform data tables into mobile cards */
            .data-table { border: 0; }
            .data-table thead { display: none; }
            .data-table, .data-table tbody, .data-table tr, .data-table td { display: block; width: 100%; }
            .data-table tbody tr {
                margin-bottom: 1rem;
                border: 1px solid var(--glass-border);
                border-radius: 12px;
                background: var(--glass-bg);
                padding: 0.75rem 0.75rem;
            }
            .data-table tbody td {
                border-bottom: 1px dashed var(--border-color);
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
            }
            .data-table tbody td:last-child { border-bottom: 0; }
            .data-table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--text-primary);
                text-align: left;
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

        /* Highlight pulse for section focus */
        .highlight-pulse {
            animation: highlightPulse 2s ease-in-out 1;
        }
        @keyframes highlightPulse {
            0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.6); }
            70% { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
            100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
        }

        /* Modal details styling */
        .details-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 1rem;
        }
        @media (max-width: 768px) {
            .details-grid { grid-template-columns: 1fr; }
        }
        .detail-badges { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .detail-badge {
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            border: 1px solid var(--border-color);
            background: var(--glass-bg);
            color: var(--text-secondary);
        }
        .meta-list { list-style: none; padding-left: 0; margin-bottom: 0; }
        .meta-list li { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0; color: var(--text-secondary); }
        .list-group-compact .list-group-item { padding: 0.5rem 0.75rem; }

        /* Loading States */
        .loading-skeleton {
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
            border-radius: 4px;
        }

        @keyframes skeleton-loading {
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

        [data-theme="dark"] .modal-footer {
            background: var(--bg-secondary) !important;
            border-top: 1px solid var(--border-color) !important;
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

        [data-theme="light"] .modal-footer,
        .modal-footer {
            background: #ffffff !important;
            border-top: 1px solid #e2e8f0 !important;
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
                    <h1 class="page-title">📊 Classroom Utilization Analytics</h1>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn-action btn-primary btn-sm export-btn" onclick="exportReport()">
                        📄 Export <span class="d-none d-md-inline">Report</span>
                    </button>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
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

        <!-- Filters Panel -->
        <div class="filter-panel slide-up">
            <form method="GET" action="" id="filterForm">
                <div class="filter-grid">
                    <div>
                        <label class="form-label">Building</label>
                        <select class="form-select" name="building" onchange="this.form.submit()">
                            <option value="">All Buildings</option>
                            <?php foreach ($buildings as $building): ?>
                                <option value="<?= htmlspecialchars($building['building']) ?>" 
                                        <?= $selectedBuilding === $building['building'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($building['building']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Floor</label>
                        <select class="form-select" name="floor" onchange="this.form.submit()">
                            <option value="">All Floors</option>
                            <?php foreach ($floors as $floor): ?>
                                <option value="<?= htmlspecialchars($floor['floor']) ?>" 
                                        <?= $selectedFloor == $floor['floor'] ? 'selected' : '' ?>>
                                    Floor <?= htmlspecialchars($floor['floor']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['department_code']) ?>" 
                                        <?= $selectedDepartment === $dept['department_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Capacity Range</label>
                        <select class="form-select" name="capacity_range" onchange="this.form.submit()">
                            <option value="">All Capacities</option>
                            <option value="small" <?= $selectedCapacityRange === 'small' ? 'selected' : '' ?>>Small (≤30)</option>
                            <option value="medium" <?= $selectedCapacityRange === 'medium' ? 'selected' : '' ?>>Medium (31-60)</option>
                            <option value="large" <?= $selectedCapacityRange === 'large' ? 'selected' : '' ?>>Large (>60)</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Time Period</label>
                        <select class="form-select" name="period" onchange="this.form.submit()">
                            <option value="current_week" <?= $selectedPeriod === 'current_week' ? 'selected' : '' ?>>Current Week</option>
                            <option value="current_month" <?= $selectedPeriod === 'current_month' ? 'selected' : '' ?>>Current Month</option>
                            <option value="current_semester" <?= $selectedPeriod === 'current_semester' ? 'selected' : '' ?>>Current Semester</option>
                        </select>
                    </div>

                    <div>
                        <button type="button" class="btn-action btn-outline" onclick="clearFilters()">
                            <i class="fas fa-filter-circle-xmark"></i> Clear Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Key Metrics -->
        <div class="metrics-row slide-up">
            <?php
            $totalRooms = count($utilizationData);
            $avgUtilization = $totalRooms > 0 ? array_sum(array_column($utilizationData, 'utilization_percentage')) / $totalRooms : 0;
            $avgOccupancy = $totalRooms > 0 ? array_sum(array_column($utilizationData, 'average_occupancy_percentage')) / $totalRooms : 0;
            $underutilizedCount = count(array_filter($utilizationData, function($room) {
                return $room['utilization_status'] === 'underutilized' || $room['utilization_status'] === 'unused';
            }));
            ?>

            <div class="metric-card glass-card">
                <div class="metric-icon utilization">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="metric-number"><?= number_format($avgUtilization, 1) ?>%</div>
                <div class="metric-label">Average Utilization</div>
                <div class="metric-change positive">+2.3%</div>
            </div>

            <div class="metric-card glass-card">
                <div class="metric-icon capacity">
                    <i class="fas fa-users"></i>
                </div>
                <div class="metric-number"><?= number_format($avgOccupancy, 1) ?>%</div>
                <div class="metric-label">Average Occupancy</div>
                <div class="metric-change positive">+1.8%</div>
            </div>

            <div class="metric-card glass-card">
                <div class="metric-icon efficiency">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="metric-number"><?= $totalRooms ?></div>
                <div class="metric-label">Active Classrooms</div>
                <div class="metric-change positive">+5</div>
            </div>

            <div class="metric-card glass-card">
                <div class="metric-icon optimization">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="metric-number"><?= $underutilizedCount ?></div>
                <div class="metric-label">Optimization Opportunities</div>
                <div class="metric-change negative">-3</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-row slide-up">
            <!-- Utilization Heatmap -->
            <div class="glass-card">
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-fire"></i>
                        Weekly Utilization Heatmap
                    </h3>
                    <canvas id="utilizationHeatmap" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Department Distribution -->
            <div class="glass-card">
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-donut"></i>
                        Department Distribution
                    </h3>
                    <canvas id="departmentChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Analytics Tables -->
        <div class="analytics-grid">
            <!-- Classroom Utilization Table -->
            <div class="glass-card slide-up">
                <div class="data-table-container">
                    <h3 class="chart-title">
                        <i class="fas fa-table"></i>
                        Classroom Utilization Details
                    </h3>
                    
                    <div style="max-height: 500px; overflow-y: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Building</th>
                                    <th>Capacity</th>
                                    <th>Department</th>
                                    <th>Utilization</th>
                                    <th>Occupancy</th>
                                    <th>Status</th>
                                    <th>Scheduled Hours</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($utilizationData as $room): ?>
                                <tr>
                                    <td data-label="Room">
                                        <strong><?= htmlspecialchars($room['room_number']) ?></strong>
                                        <?php if ($room['floor']): ?>
                                            <br><small class="text-muted">Floor <?= htmlspecialchars($room['floor']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Building"><?= htmlspecialchars($room['building']) ?></td>
                                    <td data-label="Capacity">
                                        <strong><?= number_format($room['capacity']) ?></strong>
                                        <br><small class="text-muted"><?= ucfirst($room['type']) ?></small>
                                    </td>
                                    <td data-label="Department"><?= htmlspecialchars($room['department']) ?></td>
                                    <td data-label="Utilization">
                                        <div class="progress-bar-container">
                                            <div class="progress-bar <?= 
                                                $room['utilization_percentage'] >= 70 ? 'high' : 
                                                ($room['utilization_percentage'] >= 40 ? 'medium' : 'low') 
                                            ?>" style="width: <?= min($room['utilization_percentage'], 100) ?>%"></div>
                                        </div>
                                        <small><?= number_format($room['utilization_percentage'], 1) ?>%</small>
                                    </td>
                                    <td data-label="Occupancy">
                                        <div class="progress-bar-container">
                                            <div class="progress-bar <?= 
                                                $room['average_occupancy_percentage'] >= 70 ? 'high' : 
                                                ($room['average_occupancy_percentage'] >= 40 ? 'medium' : 'low') 
                                            ?>" style="width: <?= min($room['average_occupancy_percentage'], 100) ?>%"></div>
                                        </div>
                                        <small><?= number_format($room['average_occupancy_percentage'], 1) ?>%</small>
                                    </td>
                                    <td data-label="Status">
                                        <span class="status-badge <?= strtolower($room['utilization_status']) ?>">
                                            <?= ucfirst($room['utilization_status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Scheduled Hours">
                                        <strong><?= $room['total_scheduled_hours'] ?></strong>/40
                                        <br><small class="text-muted">hours/week</small>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="btn-group-sm">
                                            <button class="btn-action btn-outline btn-sm" 
                                                    onclick="viewRoomDetails(<?= $room['classroom_id'] ?>)" 
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-outline btn-sm" 
                                                    onclick="optimizeRoom(<?= $room['classroom_id'] ?>)" 
                                                    title="Optimize">
                                                <i class="fas fa-magic"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Peak Hours Analysis -->
            <div class="glass-card slide-up">
                <div class="data-table-container">
                    <h3 class="chart-title">
                        <i class="fas fa-clock"></i>
                        Peak Hours Analysis
                    </h3>
                    
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Time Slot</th>
                                    <th>Day</th>
                                    <th>Classes</th>
                                    <th>Rooms Used</th>
                                    <th>Demand Level</th>
                                    <th>Avg Capacity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($peakHours, 0, 15) as $slot): ?>
                                <tr>
                                    <td data-label="Time Slot"><?= htmlspecialchars($slot['time_slot']) ?></td>
                                    <td data-label="Day"><?= htmlspecialchars($slot['day_of_week']) ?></td>
                                    <td data-label="Classes"><strong><?= $slot['total_classes'] ?></strong></td>
                                    <td data-label="Rooms Used"><?= $slot['rooms_in_use'] ?></td>
                                    <td data-label="Demand Level">
                                        <span class="status-badge <?= 
                                            $slot['usage_level'] === 'peak' ? 'overutilized' : 
                                            ($slot['usage_level'] === 'low' ? 'underutilized' : 'optimal') 
                                        ?>">
                                            <?= ucfirst($slot['usage_level']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Avg Capacity"><?= $slot['avg_room_capacity'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Optimization Recommendations -->
            <div class="glass-card slide-up">
                <div class="data-table-container">
                    <h3 class="chart-title" id="optimizationRecommendations">
                        <i class="fas fa-lightbulb"></i>
                        Optimization Recommendations
                    </h3>
                    
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach (array_slice($underutilizedRooms, 0, 10) as $room): ?>
                        <div class="recommendation-card">
                            <div class="recommendation-title">
                                <?= htmlspecialchars($room['room_number']) ?> - <?= htmlspecialchars($room['building']) ?>
                            </div>
                            <div class="recommendation-text">
                                <strong>Current Status:</strong> <?= number_format($room['utilization_rate'], 1) ?>% utilization, 
                                <?= number_format($room['avg_actual_occupancy']) ?>/<?= $room['capacity'] ?> average occupancy<br>
                                <strong>Recommendation:</strong> <?= htmlspecialchars($room['recommendation']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Classroom Details Modal -->
    <div class="modal fade" id="classroomDetailsModal" tabindex="-1" aria-labelledby="classroomDetailsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="classroomDetailsLabel">Classroom Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="detailsLoading" class="d-flex align-items-center gap-2">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Loading details...</span>
                    </div>
                    <div id="detailsError" class="alert alert-danger d-none"></div>
                    <div id="detailsContent" class="d-none">
                        <div class="mb-2">
                            <h4 class="mb-1" id="detailRoomName"></h4>
                            <div class="detail-badges" id="detailBadges"></div>
                        </div>
                        <div class="details-grid">
                            <div>
                                <div class="p-3 glass-card mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="fw-semibold">Utilization</div>
                                        <div id="detailUtilizationValue" class="small text-muted"></div>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div id="detailUtilizationBar" class="progress-bar" role="progressbar" style="width:0%"></div>
                                    </div>
                                </div>
                                <div class="p-3 glass-card">
                                    <div class="fw-semibold mb-2">Recent Activity</div>
                                    <ul id="detailRecent" class="list-group list-group-flush list-group-compact"></ul>
                                </div>
                            </div>
                            <div>
                                <div class="p-3 glass-card mb-3">
                                    <div class="fw-semibold mb-2">Room Info</div>
                                    <ul class="meta-list" id="detailMetaList"></ul>
                                </div>
                                <div class="p-3 glass-card">
                                    <div class="fw-semibold mb-2">Scheduled Subjects</div>
                                    <div id="detailSubjectsChips" class="detail-badges"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
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
            
            // Initialize charts
            initializeCharts();
            
            // Auto-refresh data every 5 minutes
            setInterval(refreshData, 300000);
        });

        /**
         * Apply current theme from localStorage
         */
        function applyCurrentTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
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
         * Initialize all charts
         */
        function initializeCharts() {
            initializeUtilizationHeatmap();
            initializeDepartmentChart();
        }

        /**
         * Initialize utilization heatmap
         */
        function initializeUtilizationHeatmap() {
            const ctx = document.getElementById('utilizationHeatmap');
            if (!ctx) return;

            const weeklyData = <?= json_encode($weeklyUtilization) ?>;
            
            // Process data for heatmap
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const timeSlots = [...new Set(weeklyData.map(d => d.time_slot))].sort();
            
            const heatmapData = [];
            days.forEach((day, dayIndex) => {
                timeSlots.forEach((slot, slotIndex) => {
                    const dataPoint = weeklyData.find(d => d.day_of_week === day && d.time_slot === slot);
                    heatmapData.push({
                        x: dayIndex,
                        y: slotIndex,
                        v: dataPoint ? parseFloat(dataPoint.room_utilization_percentage) : 0
                    });
                });
            });

            new Chart(ctx, {
                type: 'scatter',
                data: {
                    datasets: [{
                        label: 'Room Utilization %',
                        data: heatmapData,
                        backgroundColor: function(context) {
                            const value = context.parsed.v;
                            if (value >= 80) return 'rgba(239, 68, 68, 0.8)';
                            if (value >= 60) return 'rgba(245, 158, 11, 0.8)';
                            if (value >= 40) return 'rgba(16, 185, 129, 0.8)';
                            return 'rgba(59, 130, 246, 0.8)';
                        },
                        pointRadius: function(context) {
                            return Math.max(3, context.parsed.v / 5);
                        }
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'linear',
                            position: 'bottom',
                            min: -0.5,
                            max: 6.5,
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return days[Math.round(value)] || '';
                                }
                            },
                            title: {
                                display: true,
                                text: 'Day of Week'
                            }
                        },
                        y: {
                            type: 'linear',
                            min: -0.5,
                            max: timeSlots.length - 0.5,
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return timeSlots[Math.round(value)] || '';
                                }
                            },
                            title: {
                                display: true,
                                text: 'Time Slots'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    const point = context[0];
                                    return `${days[Math.round(point.parsed.x)]} - ${timeSlots[Math.round(point.parsed.y)]}`;
                                },
                                label: function(context) {
                                    return `Utilization: ${context.parsed.v.toFixed(1)}%`;
                                }
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
            if (!ctx) return;

            const departmentData = <?= json_encode($departmentUtilization) ?>;
            
            const labels = departmentData.map(d => d.department_name);
            const utilization = departmentData.map(d => parseFloat(d.own_room_utilization_rate) || 0);
            const colors = [
                '#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f59e0b',
                '#10b981', '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6'
            ];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: utilization,
                        backgroundColor: colors.slice(0, labels.length),
                        borderWidth: 2,
                        borderColor: 'rgba(255, 255, 255, 0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    return `${label}: ${value.toFixed(1)}% utilization`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        /**
         * View room details
         */
        function viewRoomDetails(roomId) {
            try {
                const modalEl = document.getElementById('classroomDetailsModal');
                if (!modalEl) return;
                const modal = new bootstrap.Modal(modalEl);

                // Reset states
                document.getElementById('detailsLoading').classList.remove('d-none');
                document.getElementById('detailsError').classList.add('d-none');
                document.getElementById('detailsContent').classList.add('d-none');
                document.getElementById('detailsError').textContent = '';
                document.getElementById('detailRoomName').textContent = '';
                const badges = document.getElementById('detailBadges'); if (badges) badges.innerHTML = '';
                const metaList = document.getElementById('detailMetaList'); if (metaList) metaList.innerHTML = '';
                const utilBar = document.getElementById('detailUtilizationBar'); if (utilBar) { utilBar.style.width = '0%'; utilBar.className = 'progress-bar'; }
                const utilVal = document.getElementById('detailUtilizationValue'); if (utilVal) utilVal.textContent = '';
                const subjChips = document.getElementById('detailSubjectsChips'); if (subjChips) subjChips.innerHTML = '';
                const recentList = document.getElementById('detailRecent'); if (recentList) recentList.innerHTML = '';

                modal.show();

                // Fetch details
                fetch('../../includes/api/classroom-details.php?id=' + encodeURIComponent(roomId), {
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(json => {
                    document.getElementById('detailsLoading').classList.add('d-none');
                    if (!json || !json.success) {
                        document.getElementById('detailsError').classList.remove('d-none');
                        document.getElementById('detailsError').textContent = (json && json.message) ? json.message : 'Failed to load details.';
                        return;
                    }

                    const c = json.classroom || {};
                    document.getElementById('detailsContent').classList.remove('d-none');
                    document.getElementById('classroomDetailsLabel').textContent = (c.room_name || c.room_number || 'Classroom') + ' Details';
                    document.getElementById('detailRoomName').textContent = c.room_name || c.room_number || ('Room #' + roomId);

                    // Badges
                    if (badges) {
                        const badgeHtml = [
                            c.building ? `<span class="detail-badge"><i class=\"fas fa-building\"></i> ${c.building}</span>` : '',
                            (c.capacity || c.capacity === 0) ? `<span class="detail-badge"><i class=\"fas fa-users\"></i> ${c.capacity} seats</span>` : '',
                            c.department_name ? `<span class="detail-badge"><i class=\"fas fa-layer-group\"></i> ${c.department_name}${c.department_code ? ' (' + c.department_code + ')' : ''}</span>` : ''
                        ].filter(Boolean).join(' ');
                        badges.innerHTML = badgeHtml;
                    }

                    // Meta list
                    if (metaList) {
                        const items = [];
                        if (c.total_schedules !== undefined) items.push(`<li><i class=\"fas fa-calendar-alt\"></i> Total Schedules: <strong>${c.total_schedules}</strong></li>`);
                        if (c.current_bookings !== undefined) items.push(`<li><i class=\"fas fa-book\"></i> Current Bookings: <strong>${c.current_bookings}</strong></li>`);
                        if (c.peak_usage_time) items.push(`<li><i class=\"fas fa-clock\"></i> Peak Usage: <strong>${c.peak_usage_time}</strong></li>`);
                        if (c.created_at_formatted) items.push(`<li><i class=\"fas fa-plus-circle\"></i> Created: <strong>${c.created_at_formatted}</strong></li>`);
                        if (c.updated_at_formatted) items.push(`<li><i class=\"fas fa-pen\"></i> Updated: <strong>${c.updated_at_formatted}</strong></li>`);
                        metaList.innerHTML = items.join('');
                    }

                    // Utilization
                    const utilization = (typeof c.utilization_percentage !== 'undefined') ? parseFloat(c.utilization_percentage) : (c.current_bookings ? Math.min(Math.round((c.current_bookings/20)*100), 100) : 0);
                    if (utilVal) utilVal.textContent = utilization + '%';
                    if (utilBar) {
                        utilBar.style.width = utilization + '%';
                        utilBar.classList.remove('high','medium','low');
                        if (utilization >= 70) utilBar.classList.add('high');
                        else if (utilization >= 40) utilBar.classList.add('medium');
                        else utilBar.classList.add('low');
                        utilBar.setAttribute('aria-valuenow', utilization);
                        utilBar.setAttribute('aria-valuemin', '0');
                        utilBar.setAttribute('aria-valuemax', '100');
                    }

                    // Subjects as chips
                    if (subjChips) {
                        if (c.scheduled_subjects) {
                            const chips = String(c.scheduled_subjects).split(',').map(s => s.trim()).filter(Boolean)
                                .map(s => `<span class=\"detail-badge\"><i class=\"fas fa-book-open\"></i> ${s}</span>`).join(' ');
                            subjChips.innerHTML = chips || '<span class="text-muted small">No scheduled subjects.</span>';
                        } else {
                            subjChips.innerHTML = '<span class="text-muted small">No scheduled subjects.</span>';
                        }
                    }

                    // Recent activity list
                    if (recentList) {
                        const recent = Array.isArray(c.recent_activity) ? c.recent_activity : [];
                        if (recent.length === 0) {
                            recentList.innerHTML = '<li class="list-group-item text-muted small">No recent activity.</li>';
                        } else {
                            recentList.innerHTML = recent.map(r => {
                                const day = r.day_of_week || '';
                                const st = r.start_time ? r.start_time.substring(0,5) : '';
                                const et = r.end_time ? r.end_time.substring(0,5) : '';
                                const subj = r.subject_code ? (r.subject_code + ' - ') : '';
                                const name = r.subject_name || '';
                                return `<li class=\"list-group-item\"><i class=\"fas fa-clock me-2\"></i>${day} ${st}-${et} · <i class=\"fas fa-book-open ms-2 me-1\"></i>${subj}${name}</li>`;
                            }).join('');
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('detailsLoading').classList.add('d-none');
                    document.getElementById('detailsError').classList.remove('d-none');
                    document.getElementById('detailsError').textContent = 'An unexpected error occurred.';
                });
            } catch (e) {
                console.error(e);
            }
        }

        /**
         * Optimize room usage
         */
        function optimizeRoom(roomId) {
            // Scroll to Optimization Recommendations section and highlight
            const header = document.getElementById('optimizationRecommendations');
            if (header) {
                header.scrollIntoView({ behavior: 'smooth', block: 'start' });
                header.classList.add('highlight-pulse');
                setTimeout(() => header.classList.remove('highlight-pulse'), 2000);
            } else {
                // Fallback: show details modal for manual review
                viewRoomDetails(roomId);
            }
        }

        /**
         * Export utilization report
         */
        function exportReport() {
            // Navigate to Admin Reports
            window.location.href = '../reports/';
        }

        /**
         * Refresh data
         */
        function refreshData() {
            // Show loading state
            const btn = document.querySelector('[onclick="refreshData()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            btn.disabled = true;

            // Reload page to refresh data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        /**
         * Clear all filters
         */
        function clearFilters() {
            const form = document.getElementById('filterForm');
            const selects = form.querySelectorAll('select');
            
            selects.forEach(select => {
                select.selectedIndex = 0;
            });
            
            form.submit();
        }

        /**
         * Toggle theme functionality
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

            // Reinitialize charts with new theme
            setTimeout(() => {
                initializeCharts();
            }, 100);
        }

        // Make functions available globally
        window.viewRoomDetails = viewRoomDetails;
        window.optimizeRoom = optimizeRoom;
        window.exportReport = exportReport;
        window.refreshData = refreshData;
        window.clearFilters = clearFilters;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>
        