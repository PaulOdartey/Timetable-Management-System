<?php
/**
 * Faculty Students Page
 * Timetable Management System
 * 
 * Complete students view for faculty members
 * Shows all students enrolled in faculty's subjects
 */

// Start session and security checks
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Ensure user is logged in and has faculty role
User::requireLogin();
User::requireRole('faculty');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables
$studentsData = [
    'faculty_info' => [],
    'enrolled_students' => [],
    'students_summary' => [],
    'students_by_subject' => [],
    'total_students' => 0
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
        $studentsData['faculty_info'] = $facultyInfo;
        $facultyId = $facultyInfo['faculty_id'];
    } else {
        throw new Exception('Faculty information not found');
    }

    // Get students enrolled in faculty's subjects with detailed information
    $enrolledStudents = $db->fetchAll("
        SELECT DISTINCT
            s.*,
            u.email as student_email,
            u.last_login,
            u.status as account_status,
            sub.subject_code,
            sub.subject_name,
            sub.credits,
            sub.semester as subject_semester,
            sub.year_level,
            e.section,
            e.enrollment_date,
            e.status as enrollment_status,
            e.academic_year,
            t.timetable_id,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            c.room_number,
            c.building,
            COUNT(DISTINCT e2.student_id) as class_size,
            CASE 
                WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'recent'
                WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'moderate'
                ELSE 'inactive'
            END as activity_level
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        JOIN enrollments e ON s.student_id = e.student_id
        JOIN subjects sub ON e.subject_id = sub.subject_id
        JOIN faculty_subjects fs ON sub.subject_id = fs.subject_id
        LEFT JOIN timetables t ON sub.subject_id = t.subject_id 
            AND t.faculty_id = fs.faculty_id
            AND (t.academic_year = '2025-2026' OR t.academic_year = '2025-26')
            AND t.semester = ?
            AND t.is_active = 1
        LEFT JOIN time_slots ts ON t.slot_id = ts.slot_id
        LEFT JOIN classrooms c ON t.classroom_id = c.classroom_id
        LEFT JOIN enrollments e2 ON e.subject_id = e2.subject_id 
            AND e.section = e2.section
            AND e.academic_year = e2.academic_year
            AND e.semester = e2.semester
            AND e2.status = 'enrolled'
        WHERE fs.faculty_id = ? 
            AND fs.is_active = 1
            AND (e.academic_year = '2025-2026' OR e.academic_year = '2025-26')
            AND e.semester = ?
            AND e.status = 'enrolled'
            AND u.status = 'active'
        GROUP BY s.student_id, sub.subject_id, e.section
        ORDER BY sub.subject_name ASC, s.last_name ASC, s.first_name ASC
    ", [$currentSemester, $facultyId, $currentSemester]);

    $studentsData['enrolled_students'] = $enrolledStudents;

    // Group students by subject for better organization
    $studentsBySubject = [];
    foreach ($enrolledStudents as $student) {
        $subjectKey = $student['subject_code'] . ' - ' . $student['subject_name'];
        if (!isset($studentsBySubject[$subjectKey])) {
            $studentsBySubject[$subjectKey] = [
                'subject_info' => [
                    'subject_code' => $student['subject_code'],
                    'subject_name' => $student['subject_name'],
                    'credits' => $student['credits'],
                    'year_level' => $student['year_level'],
                    'section' => $student['section'],
                    'room_info' => $student['room_number'] ? $student['room_number'] . ' (' . $student['building'] . ')' : 'TBA',
                    'schedule' => $student['day_of_week'] && $student['start_time'] ? 
                        $student['day_of_week'] . ' ' . 
                        date('g:i A', strtotime($student['start_time'])) . ' - ' . 
                        date('g:i A', strtotime($student['end_time'])) : 'TBA'
                ],
                'students' => []
            ];
        }
        $studentsBySubject[$subjectKey]['students'][] = $student;
    }
    $studentsData['students_by_subject'] = $studentsBySubject;

    // Calculate summary statistics
    $uniqueStudents = [];
    $totalEnrollments = count($enrolledStudents);
    $activeStudents = 0;
    $recentlyActiveStudents = 0;
    $subjectsWithStudents = count($studentsBySubject);
    
    // Get unique students and activity metrics
    foreach ($enrolledStudents as $student) {
        $studentId = $student['student_id'];
        if (!isset($uniqueStudents[$studentId])) {
            $uniqueStudents[$studentId] = $student;
            if ($student['activity_level'] === 'recent') {
                $recentlyActiveStudents++;
            }
            if ($student['account_status'] === 'active') {
                $activeStudents++;
            }
        }
    }
    
    $totalUniqueStudents = count($uniqueStudents);
    
    // Get department distribution
    $departmentCounts = [];
    foreach ($uniqueStudents as $student) {
        $dept = $student['department'];
        $departmentCounts[$dept] = ($departmentCounts[$dept] ?? 0) + 1;
    }
    $departmentsTaught = count($departmentCounts);
    
    // Get year level distribution
    $yearLevelCounts = [];
    foreach ($enrolledStudents as $student) {
        $year = $student['year_level'];
        $yearLevelCounts[$year] = ($yearLevelCounts[$year] ?? 0) + 1;
    }

    $studentsData['students_summary'] = [
        'total_unique_students' => $totalUniqueStudents,
        'total_enrollments' => $totalEnrollments,
        'active_students' => $activeStudents,
        'recently_active' => $recentlyActiveStudents,
        'subjects_taught' => $subjectsWithStudents,
        'departments_taught' => $departmentsTaught,
        'year_levels_taught' => count($yearLevelCounts),
        'department_distribution' => $departmentCounts,
        'year_distribution' => $yearLevelCounts
    ];

    $studentsData['total_students'] = $totalUniqueStudents;

} catch (Exception $e) {
    error_log("Faculty Students Error: " . $e->getMessage());
    $error_message = "Unable to load students data. Please try again later.";
}

// Set page title
$pageTitle = "My Students";
$currentPage = "students";

// Helper function to get activity level badge color
function getActivityBadge($level) {
    $badges = [
        'recent' => 'bg-success',
        'moderate' => 'bg-warning',
        'inactive' => 'bg-secondary'
    ];
    return $badges[$level] ?? 'bg-secondary';
}

// Helper function to get year level suffix
function getYearSuffix($year) {
    $suffixes = [1 => 'st', 2 => 'nd', 3 => 'rd'];
    return $suffixes[$year] ?? 'th';
}

// Helper function to format last login
function formatLastLogin($lastLogin) {
    if (!$lastLogin) return 'Never';
    
    $time = strtotime($lastLogin);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 3600) return 'Less than 1 hour ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2419200) return floor($diff / 604800) . ' weeks ago';
    return date('M j, Y', $time);
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

        /* Dark mode border overrides */
        [data-theme="dark"] .student-card {
            border: 2px solid var(--border-color);
        }

        [data-theme="dark"] .student-info-item {
            border-bottom: 2px solid var(--border-color);
        }

        [data-theme="dark"] .list-student {
            border: 2px solid var(--border-color);
        }

        [data-theme="dark"] .filter-input,
        [data-theme="dark"] .filter-select {
            border: 2px solid var(--border-color);
        }

        [data-theme="dark"] .modal-content {
            border: 2px solid var(--border-color) !important;
        }

        [data-theme="dark"] .modal-header {
            border-bottom: 2px solid var(--border-color) !important;
        }

        [data-theme="dark"] .modal-footer {
            border-top: 2px solid var(--border-color) !important;
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

        .stat-icon.students {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.enrollments {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon.subjects {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon.active {
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

        /* Students Container */
        .students-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            overflow-x: auto;
        }

        .students-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .students-title {
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

        /* Students Grid */
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .student-card {
            background: var(--bg-primary);
            border: 2px solid #cbd5e1; /* stronger light-mode border */
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .student-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .student-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
        }

        .student-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .student-name {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .student-number {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .activity-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .student-details {
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

        .student-subjects {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 12px;
            margin-top: 1rem;
        }

        .subjects-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .subject-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 2px solid #cbd5e1; /* stronger light-mode border */
            font-size: 0.875rem;
        }

        .subject-item:last-child {
            border-bottom: none;
        }

        .subject-info {
            color: var(--text-primary);
        }

        .subject-meta {
            color: var(--text-secondary);
            font-size: 0.75rem;
        }

        /* Students List View */
        .students-list {
            display: none;
        }

        .students-list.active {
            display: block;
        }

        .subject-group {
            margin-bottom: 2rem;
        }

        .subject-group-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
        }

        .list-student {
            background: var(--bg-primary);
            border: 2px solid #cbd5e1; /* stronger light-mode border */
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .list-student:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .list-student-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .list-student-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .list-student-name {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .list-student-number {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .list-student-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .list-student-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        /* Empty State */
        .empty-students {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-students svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Search and Filter */
        .search-filter-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
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

            .students-container {
                padding: 1rem;
            }

            .search-filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                min-width: unset;
            }
        }

        @media (max-width: 768px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .students-grid {
                display: none !important;
            }

            .students-list {
                display: block !important;
            }

            .toggle-btn {
                display: none;
            }

            .student-details {
                grid-template-columns: 1fr;
            }

            .list-student-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .list-student-details {
                grid-template-columns: 1fr;
            }

            /* Stronger, more visible borders on mobile list view cards */
            .list-student {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.8);
            }

            /* Dark mode: slightly stronger border contrast on mobile */
            [data-theme="dark"] .list-student {
                border-style: solid;
                border-width: 3px;
                border-color: rgba(59, 130, 246, 0.9);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .students-grid {
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
            .search-filter-bar { display: none; }
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
                        <h1 class="page-title">👥 My Students</h1>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="subjects.php" class="btn-action btn-outline">
                            📚 View Subjects
                        </a>
                        <a href="export.php" class="btn-action btn-primary">
                            📄 Export List
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <?php if (!empty($studentsData['students_summary'])): ?>
            <div class="stats-grid">
                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon students">👥</div>
                    <div class="stat-number"><?= $studentsData['students_summary']['total_unique_students'] ?></div>
                    <div class="stat-label">Total Students</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon enrollments">📝</div>
                    <div class="stat-number"><?= $studentsData['students_summary']['total_enrollments'] ?></div>
                    <div class="stat-label">Total Enrollments</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon subjects">📚</div>
                    <div class="stat-number"><?= $studentsData['students_summary']['subjects_taught'] ?></div>
                    <div class="stat-label">Subjects Taught</div>
                </div>

                <div class="stat-card glass-card slide-up">
                    <div class="stat-icon active">⚡</div>
                    <div class="stat-number"><?= $studentsData['students_summary']['recently_active'] ?></div>
                    <div class="stat-label">Recently Active</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Students Display -->
        <div class="students-container">
            <div class="students-header">
                <h2 class="students-title">Enrolled Students</h2>
                <div class="view-toggles">
                    <button class="toggle-btn active" onclick="switchView('grid')">Grid View</button>
                    <button class="toggle-btn" onclick="switchView('list')">List View</button>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="search-filter-bar">
                <input type="text" id="studentSearch" class="search-input" placeholder="Search students by name, ID, or email...">
                <select id="subjectFilter" class="filter-select">
                    <option value="all">All Subjects</option>
                    <?php foreach (array_keys($studentsData['students_by_subject']) as $subject): ?>
                        <option value="<?= htmlspecialchars($subject) ?>"><?= htmlspecialchars($subject) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="activityFilter" class="filter-select">
                    <option value="all">All Activity Levels</option>
                    <option value="recent">Recently Active</option>
                    <option value="moderate">Moderately Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select id="departmentFilter" class="filter-select">
                    <option value="all">All Departments</option>
                    <?php foreach (array_keys($studentsData['students_summary']['department_distribution'] ?? []) as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($studentsData['enrolled_students'])): ?>
                <!-- Grid View -->
                <div class="students-grid" id="gridView">
                    <?php foreach ($studentsData['enrolled_students'] as $student): ?>
                        <div class="student-card" data-subject="<?= htmlspecialchars($student['subject_code'] . ' - ' . $student['subject_name']) ?>" data-activity="<?= htmlspecialchars($student['activity_level']) ?>" data-department="<?= htmlspecialchars($student['department']) ?>" onclick="showStudentDetails(<?= htmlspecialchars(json_encode($student)) ?>)">
                            <div class="student-header">
                                <div>
                                    <div class="student-name"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                    <div class="student-number"><?= htmlspecialchars($student['student_number']) ?></div>
                                </div>
                                <span class="activity-badge <?= getActivityBadge($student['activity_level']) ?>">
                                    <?= strtoupper($student['activity_level']) ?>
                                </span>
                            </div>

                            <div class="student-details">
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 21H21M3 10H21M3 7L12 3L21 7V20H3V7Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    <?= htmlspecialchars($student['department']) ?>
                                </div>
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M22 12H18L15 21L9 3L6 12H2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    Year <?= $student['year_level'] ?><?= getYearSuffix($student['year_level']) ?>
                                </div>
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    Semester <?= $student['semester'] ?>
                                </div>
                                <div class="detail-item">
                                    <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 21H21M3 10H21M3 7L12 3L21 7V20H3V7Z" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    <?= formatLastLogin($student['last_login']) ?>
                                </div>
                            </div>

                            <div class="student-subjects">
                                <div class="subjects-title">📚 Enrolled Subject</div>
                                <div class="subject-item">
                                    <div class="subject-info">
                                        <strong><?= htmlspecialchars($student['subject_code']) ?></strong> - <?= htmlspecialchars($student['subject_name']) ?>
                                    </div>
                                    <div class="subject-meta">
                                        Section <?= htmlspecialchars($student['section']) ?> • <?= $student['credits'] ?> Credits
                                    </div>
                                </div>
                                <?php if ($student['day_of_week'] && $student['start_time']): ?>
                                    <div class="subject-item">
                                        <div class="subject-info">
                                            🕒 <?= htmlspecialchars($student['day_of_week']) ?> 
                                            <?= date('g:i A', strtotime($student['start_time'])) ?> - <?= date('g:i A', strtotime($student['end_time'])) ?>
                                        </div>
                                        <div class="subject-meta">
                                            📍 <?= htmlspecialchars($student['room_number'] ? $student['room_number'] . ' (' . $student['building'] . ')' : 'TBA') ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- List View -->
                <div class="students-list" id="listView">
                    <?php if (!empty($studentsData['students_by_subject'])): ?>
                        <?php foreach ($studentsData['students_by_subject'] as $subjectKey => $subjectGroup): ?>
                            <div class="subject-group">
                                <h3 class="subject-group-title">
                                    📚 <?= htmlspecialchars($subjectKey) ?> 
                                    <span style="font-weight: 400; font-size: 0.9rem;">
                                        (<?= count($subjectGroup['students']) ?> students • Section <?= htmlspecialchars($subjectGroup['subject_info']['section']) ?>)
                                    </span>
                                </h3>
                                
                                <?php if (!empty($subjectGroup['subject_info']['schedule'])): ?>
                                    <div style="margin-bottom: 1rem; padding: 0.75rem 1rem; background: var(--bg-secondary); border-radius: 8px; font-size: 0.875rem;">
                                        🕒 <strong>Schedule:</strong> <?= htmlspecialchars($subjectGroup['subject_info']['schedule']) ?> 
                                        📍 <strong>Location:</strong> <?= htmlspecialchars($subjectGroup['subject_info']['room_info']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php foreach ($subjectGroup['students'] as $student): ?>
                                    <div class="list-student" data-subject="<?= htmlspecialchars($subjectKey) ?>" data-activity="<?= htmlspecialchars($student['activity_level']) ?>" data-department="<?= htmlspecialchars($student['department']) ?>">
                                        <div class="list-student-header">
                                            <div class="list-student-title">
                                                <div class="list-student-name"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                                <div class="list-student-number"><?= htmlspecialchars($student['student_number']) ?></div>
                                            </div>
                                            <div class="list-student-meta">
                                                <span class="activity-badge <?= getActivityBadge($student['activity_level']) ?>">
                                                    <?= strtoupper($student['activity_level']) ?>
                                                </span>
                                                <span class="badge bg-info">
                                                    Year <?= $student['year_level'] ?><?= getYearSuffix($student['year_level']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="list-student-details">
                                            <div class="detail-item">
                                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M4 4L20 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    <path d="M4 12L20 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    <path d="M4 20L12 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                </svg>
                                                <?= htmlspecialchars($student['student_email']) ?>
                                            </div>
                                            <div class="detail-item">
                                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M3 21H21M3 10H21M3 7L12 3L21 7V20H3V7Z" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                <?= htmlspecialchars($student['department']) ?>
                                            </div>
                                            <div class="detail-item">
                                                <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                Last Login: <?= formatLastLogin($student['last_login']) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($studentsData['enrolled_students'] as $student): ?>
                            <div class="list-student" data-activity="<?= htmlspecialchars($student['activity_level']) ?>" data-department="<?= htmlspecialchars($student['department']) ?>">
                                <div class="list-student-header">
                                    <div class="list-student-title">
                                        <div class="list-student-name"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                        <div class="list-student-number"><?= htmlspecialchars($student['student_number']) ?></div>
                                    </div>
                                    <div class="list-student-meta">
                                        <span class="activity-badge <?= getActivityBadge($student['activity_level']) ?>">
                                            <?= strtoupper($student['activity_level']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="list-student-details">
                                    <div class="detail-item">
                                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 4L20 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M4 12L20 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M4 20L12 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                        <?= htmlspecialchars($student['student_email']) ?>
                                    </div>
                                    <div class="detail-item">
                                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M3 21H21M3 10H21M3 7L12 3L21 7V20H3V7Z" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        <?= htmlspecialchars($student['department']) ?>
                                    </div>
                                    <div class="detail-item">
                                        <svg class="detail-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        Last Login: <?= formatLastLogin($student['last_login']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-students">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2"/>
                        <path d="M9 11C11.2091 11 13 9.20914 13 7C13 4.79086 11.2091 3 9 3C6.79086 3 5 4.79086 5 7C5 9.20914 7.79086 11 9 11Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M23 21V19C22.9993 18.1137 22.7044 17.2528 22.1614 16.5523C21.6184 15.8519 20.8581 15.3516 20 15.13" stroke="currentColor" stroke-width="2"/>
                        <path d="M16 3.13C16.8604 3.35031 17.623 3.85071 18.1676 4.55232C18.7122 5.25392 19.0078 6.11683 19.0078 7.005C19.0078 7.89318 18.7122 8.75608 18.1676 9.45769C17.623 10.1593 16.8604 10.6597 16 10.88" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <h3>No Students Found</h3>
                    <p>You don't have any students enrolled in your subjects for this semester.</p>
                    <p style="margin-top: 0.5rem;">Students will appear here once they enroll in your subjects.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger glass-card" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-primary); border: 2px solid #cbd5e1; border-radius: 16px;">
                <div class="modal-header" style="border-bottom: 2px solid #cbd5e1;">
                    <h5 class="modal-title" style="color: var(--text-primary);">Student Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody" style="color: var(--text-primary);">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer" style="border-top: 2px solid #cbd5e1;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="contactStudentBtn">Contact Student</a>
                    <a href="#" class="btn btn-info" id="viewScheduleBtn">View Schedule</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize students functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Apply theme
            applyCurrentTheme();
            
            // Initialize tooltips
            initializeTooltips();
            
            // Handle responsive view switching
            handleResponsiveView();

            // Listen for sidebar toggle events
            handleSidebarToggle();

            // Initialize search and filters
            initializeSearchAndFilters();
        });

        // Enhanced sidebar toggle handling (no sticky header)
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
                    // Mobile: always collapsed state for sticky header
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
            // No sticky header buttons on this page

            // Remove active class from all buttons
            toggleBtns.forEach(btn => btn.classList.remove('active'));

            if (viewType === 'grid') {
                gridView.style.display = 'grid';
                listView.style.display = 'none';
                const gridToggle = document.querySelector('.toggle-btn[onclick*="grid"]');
                if (gridToggle) gridToggle.classList.add('active');
                
                // No sticky header buttons to update
            } else {
                gridView.style.display = 'none';
                listView.style.display = 'block';
                listView.classList.add('active');
                const listToggle = document.querySelector('.toggle-btn[onclick*="list"]');
                if (listToggle) listToggle.classList.add('active');
                
                // No sticky header buttons to update
            }
        }

        // Show student details modal
        function showStudentDetails(studentData) {
            const modal = new bootstrap.Modal(document.getElementById('studentDetailsModal'));
            const modalBody = document.getElementById('modalBody');
            const contactStudentBtn = document.getElementById('contactStudentBtn');
            const viewScheduleBtn = document.getElementById('viewScheduleBtn');

            // Format student details
            modalBody.innerHTML = `
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">👤 Personal Information</h6>
                        <div class="mb-3">
                            <strong>Full Name:</strong><br>
                            <span style="color: var(--text-secondary);">${studentData.first_name} ${studentData.last_name}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Student Number:</strong><br>
                            <span style="color: var(--text-secondary);">${studentData.student_number}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Email:</strong><br>
                            <span style="color: var(--text-secondary);">${studentData.student_email}</span>
                        </div>
                        ${studentData.phone ? `
                        <div class="mb-3">
                            <strong>Phone:</strong><br>
                            <span style="color: var(--text-secondary);">${studentData.phone}</span>
                        </div>
                        ` : ''}
                        <div class="mb-3">
                            <strong>Department:</strong><br>
                            <span style="color: var(--text-secondary);">${studentData.department}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">🎓 Academic Information</h6>
                        <div class="mb-3">
                            <strong>Year Level:</strong><br>
                            <span style="color: var(--text-secondary);">Year ${studentData.year_level}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Current Semester:</strong><br>
                            <span style="color: var(--text-secondary);">Semester ${studentData.semester}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Enrollment Date:</strong><br>
                            <span style="color: var(--text-secondary);">${new Date(studentData.enrollment_date).toLocaleDateString()}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Enrollment Status:</strong><br>
                            <span class="badge bg-success">${studentData.enrollment_status.toUpperCase()}</span>
                        </div>
                        <div class="mb-3">
                            <strong>Account Status:</strong><br>
                            <span class="badge bg-primary">${studentData.account_status.toUpperCase()}</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">📚 Subject Enrollment</h6>
                        <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-2"><strong>Subject:</strong> ${studentData.subject_code} - ${studentData.subject_name}</div>
                                    <div class="mb-2"><strong>Section:</strong> ${studentData.section}</div>
                                    <div class="mb-2"><strong>Credits:</strong> ${studentData.credits}</div>
                                </div>
                                <div class="col-md-6">
                                    ${studentData.day_of_week && studentData.start_time ? `
                                    <div class="mb-2"><strong>Schedule:</strong> ${studentData.day_of_week} ${new Date('2000-01-01 ' + studentData.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} - ${new Date('2000-01-01 ' + studentData.end_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                                    <div class="mb-2"><strong>Location:</strong> ${studentData.room_number ? studentData.room_number + ' (' + studentData.building + ')' : 'TBA'}</div>
                                    ` : '<div class="mb-2"><strong>Schedule:</strong> TBA</div>'}
                                    <div class="mb-2"><strong>Class Size:</strong> ${studentData.class_size || 'N/A'} students</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">📊 Activity Information</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.2rem; font-weight: 600; color: var(--primary-color);">${studentData.activity_level.toUpperCase()}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Activity Level</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.2rem; font-weight: 600; color: var(--success-color);">${studentData.last_login ? formatJSDate(studentData.last_login) : 'Never'}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Last Login</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 1.2rem; font-weight: 600; color: var(--warning-color);">${studentData.academic_year}</div>
                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">Academic Year</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Set up action buttons
            contactStudentBtn.href = `mailto:${studentData.student_email}?subject=Regarding ${studentData.subject_code} - ${studentData.subject_name}`;
            viewScheduleBtn.href = `schedule.php?student=${studentData.student_id}`;

            modal.show();
        }

        // Helper function to format dates in JavaScript
        function formatJSDate(dateString) {
            if (!dateString) return 'Never';
            
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 3600000) return 'Less than 1 hour ago';
            if (diff < 86400000) return Math.floor(diff / 3600000) + ' hours ago';
            if (diff < 604800000) return Math.floor(diff / 86400000) + ' days ago';
            if (diff < 2419200000) return Math.floor(diff / 604800000) + ' weeks ago';
            return date.toLocaleDateString();
        }

        // Initialize search and filters
        function initializeSearchAndFilters() {
            const searchInput = document.getElementById('studentSearch');
            const subjectFilter = document.getElementById('subjectFilter');
            const activityFilter = document.getElementById('activityFilter');
            const departmentFilter = document.getElementById('departmentFilter');

            function applyFilters() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedSubject = subjectFilter.value;
                const selectedActivity = activityFilter.value;
                const selectedDepartment = departmentFilter.value;

                const students = document.querySelectorAll('.student-card, .list-student');
                
                students.forEach(student => {
                    let visible = true;

                    // Search filter
                    if (searchTerm) {
                        const studentText = student.textContent.toLowerCase();
                        visible = visible && studentText.includes(searchTerm);
                    }

                    // Subject filter
                    if (selectedSubject !== 'all') {
                        const studentSubject = student.getAttribute('data-subject');
                        visible = visible && (studentSubject === selectedSubject);
                    }

                    // Activity filter
                    if (selectedActivity !== 'all') {
                        const studentActivity = student.getAttribute('data-activity');
                        visible = visible && (studentActivity === selectedActivity);
                    }

                    // Department filter
                    if (selectedDepartment !== 'all') {
                        const studentDepartment = student.getAttribute('data-department');
                        visible = visible && (studentDepartment === selectedDepartment);
                    }

                    student.style.display = visible ? '' : 'none';
                });

                // Hide empty subject groups in list view
                const subjectGroups = document.querySelectorAll('.subject-group');
                subjectGroups.forEach(group => {
                    const visibleStudents = group.querySelectorAll('.list-student:not([style*="display: none"])');
                    group.style.display = visibleStudents.length > 0 ? '' : 'none';
                });
            }

            if (searchInput) searchInput.addEventListener('input', applyFilters);
            if (subjectFilter) subjectFilter.addEventListener('change', applyFilters);
            if (activityFilter) activityFilter.addEventListener('change', applyFilters);
            if (departmentFilter) departmentFilter.addEventListener('change', applyFilters);
        }

        // Apply current theme
        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Initialize tooltips
        function initializeTooltips() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
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
            // Ctrl/Cmd + P for Print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }

            // Ctrl/Cmd + E for Export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'export-students.php';
            }

            // G for Grid view
            if (e.key === 'g' && !e.ctrlKey && !e.metaKey && !e.target.matches('input, textarea')) {
                switchView('grid');
            }

            // L for List view
            if (e.key === 'l' && !e.ctrlKey && !e.metaKey && !e.target.matches('input, textarea')) {
                switchView('list');
            }

            // F for Focus search
            if (e.key === 'f' && !e.ctrlKey && !e.metaKey && !e.target.matches('input, textarea')) {
                e.preventDefault();
                const searchInput = document.getElementById('studentSearch');
                if (searchInput) searchInput.focus();
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
        const cards = document.querySelectorAll('.glass-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Performance optimization: Lazy load student details
        const studentCards = document.querySelectorAll('.student-card');
        studentCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
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
        document.querySelectorAll('.stat-card, .student-card, .list-student').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Export functionality
        function exportStudentsList() {
            window.location.href = 'export-students.php?format=excel';
        }

        function exportStudentsPDF() {
            window.location.href = 'export-students.php?format=pdf';
        }

        // Activity level badge helper
        function getActivityBadgeClass(level) {
            const badges = {
                'recent': 'bg-success',
                'moderate': 'bg-warning',
                'inactive': 'bg-secondary'
            };
            return badges[level] || 'bg-secondary';
        }

        // Contact student functionality
        function contactStudent(email, studentName, subjectCode) {
            const subject = `Regarding ${subjectCode} Course`;
            const body = `Dear ${studentName},\n\nI hope this email finds you well.\n\nBest regards,\nYour Instructor`;
            window.location.href = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
        }

        // Bulk actions (for future enhancement)
        function selectAllStudents() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = true);
        }

        function deselectAllStudents() {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }

        // Print specific student details
        function printStudentDetails(studentData) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Student Details - ${studentData.first_name} ${studentData.last_name}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .details { margin: 20px 0; }
                        .section { margin: 15px 0; }
                        .label { font-weight: bold; }
                        table { width: 100%; border-collapse: collapse; }
                        td { padding: 8px; border: 1px solid #ddd; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Student Details</h1>
                        <p>Generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                    <div class="details">
                        <div class="section">
                            <span class="label">Name:</span> ${studentData.first_name} ${studentData.last_name}<br>
                            <span class="label">Student Number:</span> ${studentData.student_number}<br>
                            <span class="label">Email:</span> ${studentData.student_email}<br>
                            <span class="label">Department:</span> ${studentData.department}<br>
                            <span class="label">Year Level:</span> Year ${studentData.year_level}<br>
                        </div>
                        <div class="section">
                            <span class="label">Subject:</span> ${studentData.subject_code} - ${studentData.subject_name}<br>
                            <span class="label">Section:</span> ${studentData.section}<br>
                            <span class="label">Credits:</span> ${studentData.credits}<br>
                            <span class="label">Enrollment Date:</span> ${new Date(studentData.enrollment_date).toLocaleDateString()}<br>
                        </div>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>

<?php
/**
 * Helper function for formatting enrollment date
 * @param string $date
 * @return string
 */
function formatEnrollmentDate($date) {
    return date('M j, Y', strtotime($date));
}

/**
 * Helper function to get enrollment status color
 * @param string $status
 * @return string
 */
function getEnrollmentStatusColor($status) {
    $colors = [
        'enrolled' => 'text-success',
        'dropped' => 'text-danger',
        'completed' => 'text-primary'
    ];
    return $colors[$status] ?? 'text-secondary';
}

/**
 * Helper function to calculate enrollment duration
 * @param string $enrollmentDate
 * @return string
 */
function getEnrollmentDuration($enrollmentDate) {
    $enrolled = strtotime($enrollmentDate);
    $now = time();
    $diff = $now - $enrolled;
    
    $days = floor($diff / (60 * 60 * 24));
    
    if ($days < 7) return $days . ' days';
    if ($days < 30) return floor($days / 7) . ' weeks';
    if ($days < 365) return floor($days / 30) . ' months';
    return floor($days / 365) . ' years';
}
?>