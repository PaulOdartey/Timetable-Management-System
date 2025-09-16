<?php
/**
 * Universal Dashboard API
 * Provides role-based real-time data updates for all user dashboards
 * Supports: Admin, Faculty, Student roles
 */

// Include required files// new  (2 levels up from /includes/api/)
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Verify user authentication
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        throw new Exception('Unauthorized access');
    }

    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'];
    
    $response = [
        'success' => true,
        'timestamp' => time(),
        'role' => $userRole,
        'stats' => [],
        'notifications' => [],
        'criticalAlerts' => [],
        'hasNewNotifications' => false,
        'notificationCount' => 0,
        'recentActivities' => []
    ];

    // Role-based data retrieval
    switch ($userRole) {
        case 'admin':
            $response = array_merge($response, getAdminDashboardData($db, $userId));
            break;
        case 'faculty':
            $response = array_merge($response, getFacultyDashboardData($db, $userId));
            break;
        case 'student':
            $response = array_merge($response, getStudentDashboardData($db, $userId));
            break;
        default:
            throw new Exception('Invalid user role');
    }

    // Get common notifications for all roles
    $commonNotifications = getNotifications($db, $userId, $userRole);
    $response['notifications'] = $commonNotifications['notifications'];
    $response['notificationCount'] = $commonNotifications['count'];
    $response['hasNewNotifications'] = $commonNotifications['hasNew'];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Get Admin Dashboard Data
 */
function getAdminDashboardData($db, $userId) {
    // Get system statistics
    $stats = $db->fetchRow("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE status = 'active') as total_active_users,
            (SELECT COUNT(*) FROM users WHERE status = 'pending' AND email_verified = 1) as pending_users,
            (SELECT COUNT(*) FROM users WHERE role = 'faculty' AND status = 'active') as total_faculty,
            (SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active') as total_students,
            (SELECT COUNT(*) FROM subjects WHERE is_active = 1) as total_subjects,
            (SELECT COUNT(*) FROM classrooms WHERE is_active = 1) as total_classrooms,
            (SELECT COUNT(*) FROM timetables WHERE is_active = 1) as active_timetables,
            (SELECT COUNT(*) FROM departments WHERE is_active = 1) as total_departments,
            (SELECT COUNT(*) FROM enrollments WHERE status = 'enrolled') as total_enrollments
    ");

    // Get recent activities
    $recentActivities = $db->fetchAll("
        SELECT 
            al.*,
            u.username,
            CASE 
                WHEN u.role = 'admin' THEN CONCAT(ap.first_name, ' ', ap.last_name)
                WHEN u.role = 'faculty' THEN CONCAT(fp.first_name, ' ', fp.last_name)
                WHEN u.role = 'student' THEN CONCAT(sp.first_name, ' ', sp.last_name)
                ELSE u.username
            END as full_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        LEFT JOIN admin_profiles ap ON u.user_id = ap.user_id
        LEFT JOIN faculty fp ON u.user_id = fp.user_id
        LEFT JOIN students sp ON u.user_id = sp.user_id
        ORDER BY al.timestamp DESC
        LIMIT 10
    ");

    // Check for critical alerts
    $criticalAlerts = [];

    // High pending users alert
    if ($stats['pending_users'] > 10) {
        $criticalAlerts[] = [
            'type' => 'warning',
            'message' => 'High number of pending user registrations (' . $stats['pending_users'] . ')',
            'action' => 'users/pending.php'
        ];
    }

    // Low active faculty alert
    if ($stats['total_faculty'] < 5) {
        $criticalAlerts[] = [
            'type' => 'info',
            'message' => 'Consider recruiting more faculty members (' . $stats['total_faculty'] . ' active)',
            'action' => 'users/faculty.php'
        ];
    }

    return [
        'stats' => $stats,
        'recentActivities' => $recentActivities,
        'criticalAlerts' => $criticalAlerts,
        'pendingCount' => $stats['pending_users']
    ];
}

/**
 * Get Faculty Dashboard Data
 */
function getFacultyDashboardData($db, $userId) {
    // Get faculty ID (map users.user_id -> faculty.faculty_id)
    $facultyRecord = $db->fetchRow("SELECT faculty_id FROM faculty WHERE user_id = ?", [$userId]);
    $facultyId = $facultyRecord['faculty_id'] ?? null;

    if (!$facultyId) {
        throw new Exception('Faculty record not found');
    }

    // Get faculty-specific statistics
    $stats = $db->fetchRow("
        SELECT 
            (SELECT COUNT(DISTINCT t.subject_id) FROM timetables t WHERE t.faculty_id = ? AND t.is_active = 1) as my_subjects,
            (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e 
             JOIN timetables t ON e.subject_id = t.subject_id 
             WHERE t.faculty_id = ? AND e.status = 'enrolled' AND t.is_active = 1) as my_students,
            (SELECT COUNT(*) FROM timetables t 
             WHERE t.faculty_id = ? AND t.is_active = 1) as my_classes,
            (SELECT COUNT(*) FROM notifications 
             WHERE (target_role = 'faculty' OR target_role = 'all' OR target_user_id = ?) 
             AND is_active = 1 AND is_read = 0) as unread_notifications
    ", [$facultyId, $facultyId, $facultyId, $userId]);

    // Get today's schedule
    $todaySchedule = $db->fetchAll("
        SELECT 
            t.*,
            s.subject_name,
            s.subject_code,
            c.room_number as classroom_name,
            c.building,
            ts.start_time,
            ts.end_time,
            ts.day_of_week
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE t.faculty_id = ? 
        AND t.is_active = 1
        AND (ts.day_of_week = DAYNAME(CURDATE()) OR ts.day_of_week = LEFT(DAYNAME(CURDATE()), 3) OR ts.day_of_week = UPPER(LEFT(DAYNAME(CURDATE()), 3)))
        ORDER BY ts.start_time
    ", [$facultyId]);

    // Get recent student enrollments (deduplicated by avoiding multiplicative joins)
    $recentEnrollments = $db->fetchAll("
        SELECT 
            e.*,
            st.first_name,
            st.last_name,
            st.student_id as student_number,
            s.subject_name,
            s.subject_code
        FROM enrollments e
        JOIN students st ON e.student_id = st.student_id
        JOIN subjects s ON e.subject_id = s.subject_id
        WHERE e.status = 'enrolled'
          AND EXISTS (
              SELECT 1 FROM timetables t
              WHERE t.subject_id = s.subject_id
                AND t.faculty_id = ?
                AND t.is_active = 1
          )
        ORDER BY e.enrollment_date DESC
        LIMIT 5
    ", [$facultyId]);

    $criticalAlerts = [];

    // No classes today alert (only if faculty has subjects but no classes today)
    if (empty($todaySchedule) && $stats['my_subjects'] > 0) {
        $criticalAlerts[] = [
            'type' => 'info',
            'message' => 'No classes scheduled for today',
            'action' => 'timetable/'
        ];
    }

    // Low student enrollment alert (only if very low or zero)
    if ($stats['my_students'] == 0 && $stats['my_subjects'] > 0) {
        $criticalAlerts[] = [
            'type' => 'warning',
            'message' => 'No students enrolled in your subjects yet',
            'action' => 'students/'
        ];
    } elseif ($stats['my_students'] > 0 && $stats['my_students'] < 3) {
        $criticalAlerts[] = [
            'type' => 'info',
            'message' => 'Low student enrollment in your subjects (' . $stats['my_students'] . ' students)',
            'action' => 'students/'
        ];
    }

    return [
        'stats' => $stats,
        'todaySchedule' => $todaySchedule,
        'recentEnrollments' => $recentEnrollments,
        'criticalAlerts' => $criticalAlerts
    ];
}

/**
 * Get Student Dashboard Data
 */
function getStudentDashboardData($db, $userId) {
    // Get student ID
    $studentRecord = $db->fetchRow("SELECT student_id FROM students WHERE user_id = ?", [$userId]);
    $studentId = $studentRecord['student_id'] ?? null;

    if (!$studentId) {
        throw new Exception('Student record not found');
    }

    // Get student-specific statistics
    $stats = $db->fetchRow("
        SELECT 
            (SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'enrolled') as enrolled_subjects,
            (SELECT COUNT(*) FROM timetables t 
             JOIN subjects s ON t.subject_id = s.subject_id
             JOIN enrollments e ON s.subject_id = e.subject_id
             WHERE e.student_id = ? AND e.status = 'enrolled' AND t.is_active = 1) as total_classes,
            (SELECT COUNT(*) FROM notifications 
             WHERE (target_role = 'student' OR target_role = 'all' OR target_user_id = ?) 
             AND is_active = 1 AND is_read = 0) as unread_notifications,
            (SELECT AVG(credits) FROM subjects s
             JOIN enrollments e ON s.subject_id = e.subject_id
             WHERE e.student_id = ? AND e.status = 'enrolled') as avg_credits
    ", [$studentId, $studentId, $userId, $studentId]);

    // Get today's schedule
    $todaySchedule = $db->fetchAll("
        SELECT 
            t.*,
            s.subject_name,
            s.subject_code,
            c.room_number as classroom_name,
            c.building,
            ts.start_time,
            ts.end_time,
            ts.day_of_week,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN enrollments e ON s.subject_id = e.subject_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        JOIN timetables tt ON s.subject_id = tt.subject_id
        JOIN faculty f ON tt.faculty_id = f.faculty_id
        WHERE e.student_id = ? 
        AND e.status = 'enrolled'
        AND t.is_active = 1
        AND ts.day_of_week = DAYNAME(CURDATE())
        ORDER BY ts.start_time
    ", [$studentId]);

    // Get enrolled subjects
    $enrolledSubjects = $db->fetchAll("
        SELECT 
            s.*,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
            e.enrollment_date
        FROM subjects s
        JOIN enrollments e ON s.subject_id = e.subject_id
        JOIN timetables tt ON s.subject_id = tt.subject_id
        JOIN faculty f ON tt.faculty_id = f.user_id
        WHERE e.student_id = ?
        AND e.status = 'enrolled'
        ORDER BY s.subject_name
    ", [$studentId]);

    $criticalAlerts = [];

    // No classes today alert
    if (empty($todaySchedule)) {
        $criticalAlerts[] = [
            'type' => 'info',
            'message' => 'No classes scheduled for today',
            'action' => 'schedule/'
        ];
    }

    // Low enrollment alert
    if ($stats['enrolled_subjects'] < 3) {
        $criticalAlerts[] = [
            'type' => 'warning',
            'message' => 'Consider enrolling in more subjects (' . $stats['enrolled_subjects'] . ' enrolled)',
            'action' => 'subjects/'
        ];
    }

    return [
        'stats' => $stats,
        'todaySchedule' => $todaySchedule,
        'enrolledSubjects' => $enrolledSubjects,
        'criticalAlerts' => $criticalAlerts
    ];
}

/**
 * Get notifications for user
 */
function getNotifications($db, $userId, $userRole) {
    $lastCheck = $_SESSION['last_dashboard_check'] ?? time() - 300; // Default to 5 minutes ago

    // Get recent notifications
    $notifications = $db->fetchAll("
        SELECT * FROM notifications 
        WHERE (target_role = ? OR target_role = 'all' OR target_user_id = ?)
        AND is_active = 1
        ORDER BY created_at DESC
        LIMIT 10
    ", [$userRole, $userId]);

    // Count unread notifications
    $unreadCount = $db->fetchColumn("
        SELECT COUNT(*) FROM notifications 
        WHERE (target_role = ? OR target_role = 'all' OR target_user_id = ?)
        AND is_active = 1 AND is_read = 0
    ", [$userRole, $userId]);

    // Check for new notifications since last check
    $newNotifications = $db->fetchColumn("
        SELECT COUNT(*) FROM notifications 
        WHERE created_at > FROM_UNIXTIME(?) 
        AND (target_role = ? OR target_role = 'all' OR target_user_id = ?)
        AND is_active = 1
    ", [$lastCheck, $userRole, $userId]);

    $_SESSION['last_dashboard_check'] = time();

    return [
        'notifications' => $notifications,
        'count' => $unreadCount,
        'hasNew' => $newNotifications > 0
    ];
}
?>
