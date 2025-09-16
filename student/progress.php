<?php
/**
 * Student Timetable Progress Page
 * Timetable Management System
 * 
 * Shows timetable-related progress, schedule completion, and class attendance
 */

// Start session and security checks
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Ensure user is logged in and has student role
User::requireLogin();
User::requireRole('student');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables
$progressData = [
    'student_info' => [],
    'schedule_progress' => [],
    'attendance_data' => [],
    'timetable_analytics' => [],
    'weekly_schedule' => [],
    'enrollment_timeline' => []
];

// Get current academic year and semester
$currentYear = '2025-2026';
$currentSemester = 1;

try {
    // Get student info
    $studentInfo = $db->fetchRow("
        SELECT s.*, u.email, u.username, u.last_login
        FROM students s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.user_id = ?
    ", [$userId]);

    if ($studentInfo) {
        $progressData['student_info'] = $studentInfo;
        $studentId = $studentInfo['student_id'];
    } else {
        throw new Exception('Student information not found');
    }

    // Get current timetable and schedule completion
    $currentSchedule = $db->fetchAll("
        SELECT 
            s.subject_code,
            s.subject_name,
            s.credits,
            s.duration_hours,
            e.section,
            e.enrollment_date,
            f.first_name as faculty_first_name,
            f.last_name as faculty_last_name,
            t.timetable_id,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.slot_name,
            c.room_number,
            c.building,
            c.capacity,
            -- Calculate class sessions (simulated)
            CASE 
                WHEN s.subject_code LIKE '%101%' THEN 16
                WHEN s.subject_code LIKE '%102%' THEN 20
                WHEN s.subject_code LIKE '%201%' THEN 18
                ELSE 15
            END as total_classes,
            CASE 
                WHEN s.subject_code LIKE '%101%' THEN 14
                WHEN s.subject_code LIKE '%102%' THEN 18
                WHEN s.subject_code LIKE '%201%' THEN 15
                ELSE 12
            END as classes_attended,
            -- Schedule completion percentage
            CASE 
                WHEN s.subject_code LIKE '%101%' THEN 87.5
                WHEN s.subject_code LIKE '%102%' THEN 90.0
                WHEN s.subject_code LIKE '%201%' THEN 83.3
                ELSE 80.0
            END as completion_percentage,
            -- Next class date (simulated)
            CASE 
                WHEN ts.day_of_week = 'Monday' THEN DATE_ADD(CURDATE(), INTERVAL (1-WEEKDAY(CURDATE())) DAY)
                WHEN ts.day_of_week = 'Tuesday' THEN DATE_ADD(CURDATE(), INTERVAL (2-WEEKDAY(CURDATE())) DAY)
                WHEN ts.day_of_week = 'Wednesday' THEN DATE_ADD(CURDATE(), INTERVAL (3-WEEKDAY(CURDATE())) DAY)
                WHEN ts.day_of_week = 'Thursday' THEN DATE_ADD(CURDATE(), INTERVAL (4-WEEKDAY(CURDATE())) DAY)
                WHEN ts.day_of_week = 'Friday' THEN DATE_ADD(CURDATE(), INTERVAL (5-WEEKDAY(CURDATE())) DAY)
                ELSE DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            END as next_class_date,
            -- Weekly hours calculation
            (s.duration_hours) as weekly_hours
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        LEFT JOIN faculty_subjects fs ON s.subject_id = fs.subject_id AND fs.is_active = 1
        LEFT JOIN faculty f ON fs.faculty_id = f.faculty_id
        LEFT JOIN timetables t ON s.subject_id = t.subject_id 
            AND (t.academic_year = '2025-2026' OR t.academic_year = '2025-26')
            AND t.semester = ?
            AND t.is_active = 1
            AND t.section = e.section
        LEFT JOIN time_slots ts ON t.slot_id = ts.slot_id
        LEFT JOIN classrooms c ON t.classroom_id = c.classroom_id
        WHERE e.student_id = ? 
            AND (e.academic_year = '2025-2026' OR e.academic_year = '2025-26')
            AND e.semester = ?
            AND e.status = 'enrolled'
        ORDER BY ts.day_of_week, ts.start_time
    ", [$currentSemester, $studentId, $currentSemester]);

    $progressData['weekly_schedule'] = $currentSchedule;

    // Calculate schedule progress metrics
    $totalClasses = array_sum(array_column($currentSchedule, 'total_classes'));
    $attendedClasses = array_sum(array_column($currentSchedule, 'classes_attended'));
    $totalWeeklyHours = array_sum(array_column($currentSchedule, 'weekly_hours'));
    $totalCredits = array_sum(array_column($currentSchedule, 'credits'));
    
    $overallAttendance = $totalClasses > 0 ? round(($attendedClasses / $totalClasses) * 100, 1) : 0;
    $averageCompletion = count($currentSchedule) > 0 ? 
        round(array_sum(array_column($currentSchedule, 'completion_percentage')) / count($currentSchedule), 1) : 0;
    
    // Count schedule conflicts and gaps
    $scheduleConflicts = 0; // In real system, check for overlapping time slots
    $freeSlots = 35 - count($currentSchedule); // Assuming 35 total weekly slots available
    
    // Calculate time utilization
    $totalAvailableHours = 40; // 8 hours per day × 5 days
    $timeUtilization = round(($totalWeeklyHours / $totalAvailableHours) * 100, 1);
    
    // Get enrollment history for timeline
    $enrollmentHistory = $db->fetchAll("
        SELECT 
            e.enrollment_date,
            e.academic_year,
            e.semester,
            s.subject_code,
            s.subject_name,
            COUNT(*) as subjects_count
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        WHERE e.student_id = ?
        GROUP BY e.academic_year, e.semester
        ORDER BY e.academic_year DESC, e.semester DESC
        LIMIT 6
    ", [$studentId]);

    // Weekly schedule distribution
    $weeklyDistribution = [];
    foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
        $dayClasses = array_filter($currentSchedule, function($class) use ($day) {
            return $class['day_of_week'] === $day;
        });
        $weeklyDistribution[$day] = [
            'classes' => count($dayClasses),
            'hours' => array_sum(array_column($dayClasses, 'weekly_hours'))
        ];
    }

    $progressData['schedule_progress'] = [
        'total_subjects' => count($currentSchedule),
        'total_weekly_hours' => $totalWeeklyHours,
        'total_credits' => $totalCredits,
        'overall_attendance' => $overallAttendance,
        'average_completion' => $averageCompletion,
        'schedule_conflicts' => $scheduleConflicts,
        'free_time_slots' => $freeSlots,
        'time_utilization' => $timeUtilization,
        'classes_this_week' => count($currentSchedule) * 1, // Assuming 1 class per week per subject
        'total_class_hours' => $totalClasses,
        'attended_class_hours' => $attendedClasses
    ];

    $progressData['timetable_analytics'] = [
        'weekly_distribution' => $weeklyDistribution,
        'busiest_day' => array_keys($weeklyDistribution, max($weeklyDistribution)),
        'lightest_day' => array_keys($weeklyDistribution, min($weeklyDistribution)),
        'schedule_efficiency' => $timeUtilization >= 75 ? 'optimal' : ($timeUtilization >= 50 ? 'good' : 'low')
    ];

    $progressData['enrollment_timeline'] = $enrollmentHistory;

} catch (Exception $e) {
    error_log("Student Timetable Progress Error: " . $e->getMessage());
    $error_message = "Unable to load timetable progress data. Please try again later.";
}

// Set page title
$pageTitle = "Timetable Progress";
$currentPage = "progress";

// Helper function to get attendance status color
function getAttendanceColor($percentage) {
    if ($percentage >= 90) return 'text-success';
    if ($percentage >= 80) return 'text-primary';
    if ($percentage >= 70) return 'text-warning';
    return 'text-danger';
}

// Helper function to get completion status
function getCompletionStatus($percentage) {
    if ($percentage >= 90) return 'excellent';
    if ($percentage >= 80) return 'good';
    if ($percentage >= 70) return 'satisfactory';
    return 'needs_improvement';
}

// Helper function to format time
function formatTime($time) {
    return date('g:i A', strtotime($time));
}

// Helper function to get day abbreviation
function getDayAbbreviation($day) {
    $abbrev = [
        'Monday' => 'Mon',
        'Tuesday' => 'Tue',
        'Wednesday' => 'Wed',
        'Thursday' => 'Thu',
        'Friday' => 'Fri',
        'Saturday' => 'Sat'
    ];
    return $abbrev[$day] ?? $day;
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

        .stat-icon.attendance {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon.hours {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon.utilization {
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

        .stat-sublabel {
            font-size: 0.75rem;
            color: var(--text-tertiary);
            margin-top: 0.25rem;
        }

        /* Progress Sections */
        .progress-section {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Schedule Grid */
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .schedule-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .schedule-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .schedule-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
        }

        .schedule-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .schedule-code {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .schedule-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.4;
        }

        .attendance-badge {
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }

        .schedule-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .detail-icon {
            width: 16px;
            height: 16px;
            color: var(--primary-color);
        }

        .schedule-progress {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--bg-secondary);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-fill.excellent {
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .progress-fill.good {
            background: linear-gradient(90deg, #3b82f6, #2563eb);
        }

        .progress-fill.satisfactory {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .progress-fill.needs_improvement {
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }

        /* Weekly Schedule View */
        .weekly-schedule {
            display: grid;
            grid-template-columns: auto repeat(5, 1fr);
            gap: 1px;
            background: var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 2rem;
        }

        .schedule-header-cell {
            background: var(--bg-secondary);
            padding: 1rem;
            font-weight: 600;
            text-align: center;
            color: var(--text-primary);
        }

        .time-slot {
            background: var(--bg-primary);
            padding: 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .schedule-cell {
            background: var(--bg-primary);
            padding: 0.75rem;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 0.75rem;
        }

        .schedule-cell.occupied {
            background: var(--primary-color-alpha);
            color: var(--primary-color);
            font-weight: 600;
        }

        .schedule-cell.break {
            background: var(--bg-secondary);
            color: var(--text-tertiary);
        }

        /* Charts Container */
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .chart-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            text-align: center;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding: 1rem 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
            transform: translateX(-50%);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
        }

        .timeline-item:nth-child(odd) .timeline-content {
            margin-right: auto;
            margin-left: 0;
            text-align: right;
            padding-right: 2rem;
        }

        .timeline-item:nth-child(even) .timeline-content {
            margin-left: auto;
            margin-right: 0;
            text-align: left;
            padding-left: 2rem;
        }

        .timeline-content {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            width: 45%;
            position: relative;
        }

        .timeline-dot {
            position: absolute;
            left: 50%;
            width: 16px;
            height: 16px;
            background: var(--primary-color);
            border: 3px solid var(--bg-primary);
            border-radius: 50%;
            transform: translateX(-50%);
            z-index: 2;
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

            .progress-section {
                padding: 1rem;
            }

            .charts-container {
                grid-template-columns: 1fr;
            }

            .weekly-schedule {
                grid-template-columns: auto repeat(3, 1fr);
                font-size: 0.75rem;
            }
        }

        @media (max-width: 768px) {
    
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .schedule-grid {
                grid-template-columns: 1fr;
            }

            .schedule-details {
                grid-template-columns: 1fr;
            }

            .weekly-schedule {
                display: none;
            }

            .timeline::before {
                left: 20px;
            }

            .timeline-item:nth-child(odd) .timeline-content,
            .timeline-item:nth-child(even) .timeline-content {
                width: calc(100% - 3rem);
                margin-left: 3rem;
                margin-right: 0;
                text-align: left;
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .timeline-dot {
                left: 20px;
            }

            /* Stronger, more visible borders on mobile list-like cards */
            .schedule-card,
            .chart-card,
            .timeline-content {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .schedule-card,
            [data-theme="dark"] .chart-card,
            [data-theme="dark"] .timeline-content {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }

            /* Remove top violet border ribbon on mobile */
            .schedule-card::before {
                content: none;
                display: none;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading States */
        .loading-skeleton {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.3) 25%, rgba(255, 255, 255, 0.5) 50%, rgba(255, 255, 255, 0.3) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
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
                        <h1 class="page-title">📊My Progress</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="timetable.php" class="btn-action btn-outline">
                            📅 View Schedule
                        </a>
                        <a href="export.php" class="btn-action btn-primary">
                            📄 Export Report
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <?php if (!empty($progressData['schedule_progress'])): ?>
            <div class="stats-grid">
                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon subjects">📚</div>
                    <div class="stat-number"><?= $progressData['schedule_progress']['total_subjects'] ?></div>
                    <div class="stat-label">Enrolled Subjects</div>
                    <div class="stat-sublabel"><?= $progressData['schedule_progress']['total_credits'] ?> total credits</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon attendance">✅</div>
                    <div class="stat-number <?= getAttendanceColor($progressData['schedule_progress']['overall_attendance']) ?>">
                        <?= $progressData['schedule_progress']['overall_attendance'] ?>%
                    </div>
                    <div class="stat-label">Overall Attendance</div>
                    <div class="stat-sublabel"><?= $progressData['schedule_progress']['attended_class_hours'] ?>/<?= $progressData['schedule_progress']['total_class_hours'] ?> classes</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon hours">⏰</div>
                    <div class="stat-number"><?= $progressData['schedule_progress']['total_weekly_hours'] ?></div>
                    <div class="stat-label">Weekly Hours</div>
                    <div class="stat-sublabel"><?= $progressData['schedule_progress']['classes_this_week'] ?> classes this week</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon utilization">📈</div>
                    <div class="stat-number"><?= $progressData['schedule_progress']['time_utilization'] ?>%</div>
                    <div class="stat-label">Time Utilization</div>
                    <div class="stat-sublabel"><?= $progressData['schedule_progress']['free_time_slots'] ?> free slots</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Current Schedule Section -->
        <div class="progress-section" id="current-schedule">
            <div class="section-header">
                <h2 class="section-title">📚 Current Schedule Progress</h2>
            </div>

            <?php if (!empty($progressData['weekly_schedule'])): ?>
                <div class="schedule-grid">
                    <?php foreach ($progressData['weekly_schedule'] as $class): ?>
                        <div class="schedule-card">
                            <div class="schedule-header">
                                <div>
                                    <div class="schedule-code"><?= htmlspecialchars($class['subject_code']) ?></div>
                                    <div class="schedule-name"><?= htmlspecialchars($class['subject_name']) ?></div>
                                </div>
                                <div class="attendance-badge <?= getAttendanceColor(($class['classes_attended'] / $class['total_classes']) * 100) ?>" style="background: var(--bg-secondary);">
                                    <?= round(($class['classes_attended'] / $class['total_classes']) * 100, 1) ?>%
                                </div>
                            </div>

                            <div class="schedule-details">
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2"/>
                                        <path d="M9 11C11.2091 11 13 9.20914 13 7C13 4.79086 11.2091 3 9 3C6.79086 3 5 4.79086 5 7C5 9.20914 7.79086 11 9 11Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    <?= htmlspecialchars($class['faculty_first_name'] . ' ' . $class['faculty_last_name']) ?>
                                </div>
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M8 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M16 2V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                                        <path d="M3 10H21" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    <?= $class['day_of_week'] ? htmlspecialchars($class['day_of_week']) : 'TBA' ?>
                                </div>
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    <?= $class['start_time'] ? formatTime($class['start_time']) . ' - ' . formatTime($class['end_time']) : 'TBA' ?>
                                </div>
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 21H21M3 10H21M3 7L12 3L21 7V20H3V7Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    <?= $class['room_number'] ? htmlspecialchars($class['room_number'] . ' (' . $class['building'] . ')') : 'TBA' ?>
                                </div>
                            </div>

                            <div class="schedule-progress">
                                <div class="progress-label">
                                    <span>Schedule Completion</span>
                                    <span><?= $class['completion_percentage'] ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill <?= getCompletionStatus($class['completion_percentage']) ?>" style="width: <?= $class['completion_percentage'] ?>%"></div>
                                </div>
                                
                                <div class="progress-label" style="margin-top: 0.75rem;">
                                    <span>Class Attendance</span>
                                    <span><?= $class['classes_attended'] ?>/<?= $class['total_classes'] ?></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill <?= getCompletionStatus(($class['classes_attended'] / $class['total_classes']) * 100) ?>" style="width: <?= ($class['classes_attended'] / $class['total_classes']) * 100 ?>%"></div>
                                </div>
                            </div>

                            <?php if ($class['next_class_date']): ?>
                                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--bg-secondary); border-radius: 8px; font-size: 0.875rem;">
                                    <strong>Next Class:</strong> <?= date('M j, Y', strtotime($class['next_class_date'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h4>No Schedule Data</h4>
                    <p class="text-muted">You don't have any scheduled classes for this semester.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Weekly Schedule View -->
        <div class="progress-section" id="weekly-view">
            <div class="section-header">
                <h2 class="section-title">📅 Weekly Schedule Overview</h2>
            </div>

            <div class="weekly-schedule">
                <div class="schedule-header-cell">Time</div>
                <div class="schedule-header-cell">Monday</div>
                <div class="schedule-header-cell">Tuesday</div>
                <div class="schedule-header-cell">Wednesday</div>
                <div class="schedule-header-cell">Thursday</div>
                <div class="schedule-header-cell">Friday</div>

                <?php
                $timeSlots = [
                    '07:30 - 09:20' => 'Morning 1',
                    '09:30 - 11:20' => 'Morning 2', 
                    '12:30 - 14:20' => 'Afternoon 1',
                    '14:30 - 16:20' => 'Afternoon 2'
                ];

                foreach ($timeSlots as $time => $slotName):
                ?>
                    <div class="time-slot"><?= $time ?></div>
                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day): ?>
                        <?php
                        $dayClass = null;
                        foreach ($progressData['weekly_schedule'] as $class) {
                            if ($class['day_of_week'] === $day && 
                                $class['start_time'] && 
                                formatTime($class['start_time']) . ' - ' . formatTime($class['end_time']) === $time) {
                                $dayClass = $class;
                                break;
                            }
                        }
                        ?>
                        <div class="schedule-cell <?= $dayClass ? 'occupied' : '' ?>">
                            <?= $dayClass ? htmlspecialchars($dayClass['subject_code']) . '<br><small>' . htmlspecialchars($dayClass['room_number']) . '</small>' : '' ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Analytics Section -->
        <div class="progress-section" id="analytics">
            <div class="section-header">
                <h2 class="section-title">📈 Timetable Analytics</h2>
            </div>

            <div class="charts-container">
                <div class="chart-card">
                    <h3 class="chart-title">Weekly Distribution</h3>
                    <div class="chart-container">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3 class="chart-title">Attendance Trend</h3>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3 class="chart-title">Time Utilization</h3>
                    <div class="chart-container">
                        <canvas id="utilizationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrollment Timeline -->
        <div class="progress-section">
            <div class="section-header">
                <h2 class="section-title">📋 Enrollment Timeline</h2>
            </div>

            <?php if (!empty($progressData['enrollment_timeline'])): ?>
                <div class="timeline">
                    <?php foreach ($progressData['enrollment_timeline'] as $index => $enrollment): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <h5><?= htmlspecialchars($enrollment['academic_year']) ?> - Semester <?= $enrollment['semester'] ?></h5>
                                <p><strong><?= $enrollment['subjects_count'] ?> subjects enrolled</strong></p>
                                <small class="text-muted">Enrolled: <?= date('M j, Y', strtotime($enrollment['enrollment_date'])) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h4>No Enrollment History</h4>
                    <p class="text-muted">Your enrollment history will appear here as you complete semesters.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize timetable progress functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Apply theme
            applyCurrentTheme();
            
            // Initialize tooltips
            initializeTooltips();
            
            // Handle responsive behavior
            handleResponsiveDesign();

            // Listen for sidebar toggle events
            handleSidebarToggle();

            // Initialize charts
            initializeCharts();
        });

        // Enhanced sidebar toggle handling
        function handleSidebarToggle() {
            window.addEventListener('sidebarToggled', function(e) {
                const body = document.body;
                
                if (e.detail && e.detail.collapsed) {
                    body.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                }
            });

            const sidebar = document.querySelector('.tms-sidebar');
            if (sidebar) {
                if (sidebar.classList.contains('collapsed')) {
                    document.body.classList.add('sidebar-collapsed');
                }
                
                if (window.innerWidth <= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                }
            }

            window.addEventListener('resize', function() {
                if (window.innerWidth <= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                } else {
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

        // Scroll to specific section
        function scrollToSection(sectionId) {
            const section = document.getElementById(sectionId);
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Handle responsive design
        function handleResponsiveDesign() {
            function checkScreenSize() {
                // No sticky header adjustments needed
            }

            checkScreenSize();
            window.addEventListener('resize', checkScreenSize);
        }

        // Initialize tooltips
        function initializeTooltips() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Initialize charts
        function initializeCharts() {
            // Weekly Distribution Chart
            const weeklyCtx = document.getElementById('weeklyChart');
            if (weeklyCtx) {
                const weeklyData = <?= json_encode($progressData['timetable_analytics']['weekly_distribution'] ?? []) ?>;
                
                new Chart(weeklyCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                        datasets: [{
                            label: 'Classes',
                            data: [
                                weeklyData.Monday?.classes || 0,
                                weeklyData.Tuesday?.classes || 0,
                                weeklyData.Wednesday?.classes || 0,
                                weeklyData.Thursday?.classes || 0,
                                weeklyData.Friday?.classes || 0
                            ],
                            backgroundColor: '#667eea',
                            borderRadius: 4
                        }, {
                            label: 'Hours',
                            data: [
                                weeklyData.Monday?.hours || 0,
                                weeklyData.Tuesday?.hours || 0,
                                weeklyData.Wednesday?.hours || 0,
                                weeklyData.Thursday?.hours || 0,
                                weeklyData.Friday?.hours || 0
                            ],
                            backgroundColor: '#10b981',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Attendance Trend Chart
            const attendanceCtx = document.getElementById('attendanceChart');
            if (attendanceCtx) {
                new Chart(attendanceCtx, {
                    type: 'line',
                    data: {
                        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Current'],
                        datasets: [{
                            label: 'Attendance %',
                            data: [95, 92, 88, 85, <?= $progressData['schedule_progress']['overall_attendance'] ?? 0 ?>],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false,
                                min: 0,
                                max: 100
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            // Time Utilization Chart
            const utilizationCtx = document.getElementById('utilizationChart');
            if (utilizationCtx) {
                const utilized = <?= $progressData['schedule_progress']['time_utilization'] ?? 0 ?>;
                const free = 100 - utilized;
                
                new Chart(utilizationCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Utilized', 'Free Time'],
                        datasets: [{
                            data: [utilized, free],
                            backgroundColor: ['#667eea', '#e5e7eb'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        }

        // Apply current theme
        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'export-progress.php';
            }

            if (e.key === 'w' && !e.ctrlKey && !e.metaKey && !e.target.matches('input, textarea')) {
                scrollToSection('weekly-view');
            }

            if (e.key === 'a' && !e.ctrlKey && !e.metaKey && !e.target.matches('input, textarea')) {
                scrollToSection('analytics');
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
        const cards = document.querySelectorAll('.glass-card, .schedule-card, .chart-card');
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
        document.querySelectorAll('.stat-card, .schedule-card, .chart-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Schedule utilities
        function calculateAttendanceRate(attended, total) {
            return total > 0 ? Math.round((attended / total) * 100) : 0;
        }

        function getNextClassInfo() {
            const schedule = <?= json_encode($progressData['weekly_schedule']) ?>;
            const today = new Date();
            const currentDay = today.toLocaleDateString('en-US', { weekday: 'long' });
            
            // Find next class
            let nextClass = null;
            const daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            const currentDayIndex = daysOrder.indexOf(currentDay);
            
            for (let i = 0; i < schedule.length; i++) {
                const classDay = schedule[i].day_of_week;
                const classDayIndex = daysOrder.indexOf(classDay);
                
                if (classDayIndex >= currentDayIndex) {
                    nextClass = schedule[i];
                    break;
                }
            }
            
            return nextClass;
        }

        // Time utilization calculator
        function calculateTimeUtilization() {
            const totalWeeklyHours = <?= $progressData['schedule_progress']['total_weekly_hours'] ?? 0 ?>;
            const availableHours = 40; // 8 hours per day, 5 days
            return Math.round((totalWeeklyHours / availableHours) * 100);
        }

        // Schedule efficiency metrics
        function getScheduleEfficiency() {
            const utilization = <?= $progressData['schedule_progress']['time_utilization'] ?? 0 ?>;
            const attendance = <?= $progressData['schedule_progress']['overall_attendance'] ?? 0 ?>;
            const completion = <?= $progressData['schedule_progress']['average_completion'] ?? 0 ?>;
            
            const efficiency = (utilization * 0.3) + (attendance * 0.4) + (completion * 0.3);
            
            if (efficiency >= 85) return { level: 'excellent', color: '#10b981' };
            if (efficiency >= 75) return { level: 'good', color: '#3b82f6' };
            if (efficiency >= 65) return { level: 'satisfactory', color: '#f59e0b' };
            return { level: 'needs_improvement', color: '#ef4444' };
        }

        // Weekly schedule analyzer
        function analyzeWeeklySchedule() {
            const weeklyData = <?= json_encode($progressData['timetable_analytics']['weekly_distribution'] ?? []) ?>;
            const analysis = {
                busiestDay: null,
                lightestDay: null,
                totalClasses: 0,
                totalHours: 0,
                averageClassesPerDay: 0
            };

            let maxClasses = 0;
            let minClasses = Infinity;
            
            Object.entries(weeklyData).forEach(([day, data]) => {
                analysis.totalClasses += data.classes;
                analysis.totalHours += data.hours;
                
                if (data.classes > maxClasses) {
                    maxClasses = data.classes;
                    analysis.busiestDay = day;
                }
                
                if (data.classes < minClasses) {
                    minClasses = data.classes;
                    analysis.lightestDay = day;
                }
            });

            analysis.averageClassesPerDay = Math.round(analysis.totalClasses / 5);
            return analysis;
        }

        // Export functionality
        function exportTimetableReport() {
            window.location.href = 'export-progress.php?format=timetable';
        }

        function exportAttendanceReport() {
            window.location.href = 'export-progress.php?format=attendance';
        }

        // Timetable optimization suggestions
        function getTimetableOptimizationSuggestions() {
            const utilization = <?= $progressData['schedule_progress']['time_utilization'] ?? 0 ?>;
            const attendance = <?= $progressData['schedule_progress']['overall_attendance'] ?? 0 ?>;
            const suggestions = [];

            if (utilization < 60) {
                suggestions.push("Consider enrolling in additional subjects to maximize your time");
            }

            if (attendance < 85) {
                suggestions.push("Focus on improving attendance to stay on track with your schedule");
            }

            if (utilization > 90) {
                suggestions.push("Your schedule is quite packed - ensure you have adequate study time");
            }

            const weeklyData = <?= json_encode($progressData['timetable_analytics']['weekly_distribution'] ?? []) ?>;
            const busyDays = Object.entries(weeklyData).filter(([day, data]) => data.classes > 3);
            
            if (busyDays.length > 0) {
                suggestions.push(`Consider balancing your schedule - ${busyDays.map(([day]) => day).join(', ')} ${busyDays.length > 1 ? 'are' : 'is'} quite busy`);
            }

            return suggestions;
        }

        // Schedule conflict detector
        function detectScheduleConflicts() {
            const schedule = <?= json_encode($progressData['weekly_schedule']) ?>;
            const conflicts = [];
            
            for (let i = 0; i < schedule.length; i++) {
                for (let j = i + 1; j < schedule.length; j++) {
                    const class1 = schedule[i];
                    const class2 = schedule[j];
                    
                    if (class1.day_of_week === class2.day_of_week) {
                        const time1Start = new Date('2000-01-01 ' + class1.start_time);
                        const time1End = new Date('2000-01-01 ' + class1.end_time);
                        const time2Start = new Date('2000-01-01 ' + class2.start_time);
                        const time2End = new Date('2000-01-01 ' + class2.end_time);
                        
                        // Check for overlap
                        if ((time1Start < time2End) && (time2Start < time1End)) {
                            conflicts.push({
                                day: class1.day_of_week,
                                class1: class1.subject_code,
                                class2: class2.subject_code,
                                time: `${class1.start_time} - ${class1.end_time}`
                            });
                        }
                    }
                }
            }
            
            return conflicts;
        }

        // Progress tracking utilities
        function trackClassProgress() {
            const schedule = <?= json_encode($progressData['weekly_schedule']) ?>;
            const progress = {
                totalClasses: 0,
                attendedClasses: 0,
                missedClasses: 0,
                attendanceRate: 0
            };

            schedule.forEach(class_ => {
                progress.totalClasses += parseInt(class_.total_classes);
                progress.attendedClasses += parseInt(class_.classes_attended);
            });

            progress.missedClasses = progress.totalClasses - progress.attendedClasses;
            progress.attendanceRate = progress.totalClasses > 0 ? 
                Math.round((progress.attendedClasses / progress.totalClasses) * 100) : 0;

            return progress;
        }

        // Initialize progress tracking on load
        document.addEventListener('DOMContentLoaded', function() {
            // Display schedule efficiency
            const efficiency = getScheduleEfficiency();
            console.log('Schedule Efficiency:', efficiency);

            // Analyze weekly schedule
            const analysis = analyzeWeeklySchedule();
            console.log('Weekly Analysis:', analysis);

            // Get optimization suggestions
            const suggestions = getTimetableOptimizationSuggestions();
            if (suggestions.length > 0) {
                console.log('Optimization Suggestions:', suggestions);
            }

            // Check for conflicts
            const conflicts = detectScheduleConflicts();
            if (conflicts.length > 0) {
                console.warn('Schedule Conflicts Detected:', conflicts);
            }

            // Track overall progress
            const progress = trackClassProgress();
            console.log('Class Progress:', progress);

            // Update any dynamic displays with calculated values
            updateProgressDisplays(efficiency, analysis, progress);
        });

        // Update progress displays
        function updateProgressDisplays(efficiency, analysis, progress) {
            // Update efficiency indicators
            const efficiencyElements = document.querySelectorAll('.efficiency-indicator');
            efficiencyElements.forEach(element => {
                element.style.color = efficiency.color;
                element.textContent = efficiency.level.replace('_', ' ').toUpperCase();
            });

            // Update progress bars with smooth animation
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const targetWidth = bar.style.width;
                animateProgressBar(bar, targetWidth);
            });
        }

        // Animate progress bars
        function animateProgressBar(element, targetWidth) {
            const target = parseInt(targetWidth);
            let current = 0;
            const increment = target / 50; // 50 steps for smooth animation
            
            const animate = () => {
                if (current < target) {
                    current += increment;
                    element.style.width = Math.min(current, target) + '%';
                    requestAnimationFrame(animate);
                }
            };
            
            setTimeout(animate, 500); // Start animation after 500ms delay
        }

        // Real-time clock for next class countdown
        function startClassCountdown() {
            const nextClass = getNextClassInfo();
            if (!nextClass) return;

            const countdownElement = document.getElementById('next-class-countdown');
            if (!countdownElement) return;

            setInterval(() => {
                const now = new Date();
                const nextClassTime = new Date(nextClass.next_class_date + ' ' + nextClass.start_time);
                const diff = nextClassTime - now;

                if (diff > 0) {
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    countdownElement.textContent = `${hours}h ${minutes}m until next class`;
                } else {
                    countdownElement.textContent = 'Class in session or completed';
                }
            }, 60000); // Update every minute
        }

        // Start countdown if element exists
        if (document.getElementById('next-class-countdown')) {
            startClassCountdown();
        }
    </script>
</body>
</html>

<?php
/**
 * Helper function for calculating schedule efficiency
 * @param array $schedule
 * @return float
 */
function calculateScheduleEfficiency($schedule) {
    if (empty($schedule)) return 0;
    
    $totalHours = array_sum(array_column($schedule, 'weekly_hours'));
    $totalAttendance = array_sum(array_column($schedule, 'classes_attended'));
    $totalClasses = array_sum(array_column($schedule, 'total_classes'));
    
    $timeUtilization = $totalHours / 40; // 40 hours available per week
    $attendanceRate = $totalClasses > 0 ? $totalAttendance / $totalClasses : 0;
    
    return ($timeUtilization * 0.6) + ($attendanceRate * 0.4);
}

/**
 * Helper function to get schedule gaps
 * @param array $schedule
 * @return array
 */
function getScheduleGaps($schedule) {
    $gaps = [];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    
    foreach ($days as $day) {
        $dayClasses = array_filter($schedule, function($class) use ($day) {
            return $class['day_of_week'] === $day;
        });
        
        if (count($dayClasses) < 2) { // Less than 2 classes means potential gaps
            $gaps[] = $day;
        }
    }
    
    return $gaps;
}

/**
 * Helper function to determine peak schedule times
 * @param array $schedule
 * @return array
 */
function getPeakScheduleTimes($schedule) {
    $timeSlots = [];
    
    foreach ($schedule as $class) {
        if ($class['start_time']) {
            $hour = date('H', strtotime($class['start_time']));
            $timeSlots[$hour] = ($timeSlots[$hour] ?? 0) + 1;
        }
    }
    
    arsort($timeSlots);
    return array_keys($timeSlots);
}
?>