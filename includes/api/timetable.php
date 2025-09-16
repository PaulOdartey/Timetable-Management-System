<?php
/**
 * General Timetable API
 * Timetable Management System
 * 
 * Unified API endpoint for all timetable operations
 * Handles requests from admin, faculty, and student roles
 */

// Prevent direct access
if (!defined('SYSTEM_ACCESS')) {
    define('SYSTEM_ACCESS', true);
}

// Start session and include required files
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

try {
    // Ensure user is logged in
    if (!User::isLoggedIn()) {
        throw new Exception('Authentication required', 401);
    }

    // Get database instance
    $db = Database::getInstance();
    $userId = User::getCurrentUserId();
    $userRole = User::getCurrentUserRole();

    // Get request method and action
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Route requests based on action
    switch ($action) {
        case 'get_schedule':
            echo json_encode(getSchedule($db, $userId, $userRole));
            break;
            
        case 'get_weekly_schedule':
            echo json_encode(getWeeklySchedule($db, $userId, $userRole));
            break;
            
        case 'get_daily_schedule':
            echo json_encode(getDailySchedule($db, $userId, $userRole));
            break;
            
        case 'get_schedule_stats':
            echo json_encode(getScheduleStats($db, $userId, $userRole));
            break;
            
        case 'search_schedule':
            echo json_encode(searchSchedule($db, $userId, $userRole));
            break;
            
        case 'get_conflicts':
            echo json_encode(getScheduleConflicts($db, $userId, $userRole));
            break;
            
        case 'export_schedule':
            echo json_encode(exportSchedule($db, $userId, $userRole));
            break;
            
        case 'get_available_slots':
            echo json_encode(getAvailableSlots($db, $userId, $userRole));
            break;
            
        // Admin-specific actions
        case 'create_schedule':
            if ($userRole !== 'admin') {
                throw new Exception('Unauthorized access', 403);
            }
            echo json_encode(createSchedule($db, $userId));
            break;
            
        case 'update_schedule':
            if ($userRole !== 'admin') {
                throw new Exception('Unauthorized access', 403);
            }
            echo json_encode(updateSchedule($db, $userId));
            break;
            
        case 'delete_schedule':
            if ($userRole !== 'admin') {
                throw new Exception('Unauthorized access', 403);
            }
            echo json_encode(deleteSchedule($db, $userId));
            break;
            
        default:
            throw new Exception('Invalid action specified', 400);
    }

} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    http_response_code($statusCode);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $statusCode
    ]);
}

/**
 * Get schedule based on user role
 */
function getSchedule($db, $userId, $userRole) {
    $week = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
    $view = $_GET['view'] ?? 'week';
    $academicYear = $_GET['academic_year'] ?? '2025-2026';
    $semester = $_GET['semester'] ?? 1;
    
    try {
        switch ($userRole) {
            case 'student':
                return getStudentSchedule($db, $userId, $week, $view, $academicYear, $semester);
            case 'faculty':
                return getFacultySchedule($db, $userId, $week, $view, $academicYear, $semester);
            case 'admin':
                return getAdminSchedule($db, $userId, $week, $view, $academicYear, $semester);
            default:
                throw new Exception('Invalid user role');
        }
    } catch (Exception $e) {
        throw new Exception('Failed to fetch schedule: ' . $e->getMessage());
    }
}

/**
 * Get student schedule
 */
function getStudentSchedule($db, $userId, $week, $view, $academicYear, $semester) {
    // Get student ID
    $student = $db->fetchRow("SELECT student_id FROM students WHERE user_id = ?", [$userId]);
    if (!$student) {
        throw new Exception('Student profile not found');
    }
    
    $studentId = $student['student_id'];
    
    // Get enrolled subjects with schedule
    $schedule = $db->fetchAll("
        SELECT 
            t.timetable_id,
            s.subject_code,
            s.subject_name,
            s.credits,
            c.room_number,
            c.building,
            c.capacity,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.slot_name,
            t.section,
            t.semester,
            t.academic_year,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
            f.designation,
            t.notes,
            e.enrollment_date
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN timetables t ON s.subject_id = t.subject_id 
            AND e.section = t.section
            AND e.semester = t.semester 
            AND e.academic_year = t.academic_year
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        JOIN faculty f ON t.faculty_id = f.faculty_id
        WHERE e.student_id = ? 
        AND e.status = 'enrolled'
        AND t.is_active = 1
        AND (t.academic_year = ? OR t.academic_year = ?)
        AND t.semester = ?
        ORDER BY 
            CASE ts.day_of_week
                WHEN 'Monday' THEN 1
                WHEN 'Tuesday' THEN 2
                WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4
                WHEN 'Friday' THEN 5
                WHEN 'Saturday' THEN 6
            END,
            ts.start_time ASC
    ", [$studentId, $academicYear, str_replace('-', '-', $academicYear), $semester]);
    
    return [
        'success' => true,
        'data' => [
            'schedule' => $schedule,
            'week' => $week,
            'view' => $view,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'user_role' => 'student'
        ]
    ];
}

/**
 * Get faculty schedule
 */
function getFacultySchedule($db, $userId, $week, $view, $academicYear, $semester) {
    // Get faculty ID
    $faculty = $db->fetchRow("SELECT faculty_id FROM faculty WHERE user_id = ?", [$userId]);
    if (!$faculty) {
        throw new Exception('Faculty profile not found');
    }
    
    $facultyId = $faculty['faculty_id'];
    
    // Get faculty teaching schedule
    $schedule = $db->fetchAll("
        SELECT 
            t.timetable_id,
            s.subject_code,
            s.subject_name,
            s.credits,
            c.room_number,
            c.building,
            c.capacity,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.slot_name,
            t.section,
            t.semester,
            t.academic_year,
            t.notes,
            COUNT(e.student_id) as enrolled_count
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        LEFT JOIN enrollments e ON s.subject_id = e.subject_id 
            AND e.section = t.section
            AND e.semester = t.semester 
            AND e.academic_year = t.academic_year
            AND e.status = 'enrolled'
        WHERE t.faculty_id = ? 
        AND t.is_active = 1
        AND (t.academic_year = ? OR t.academic_year = ?)
        AND t.semester = ?
        GROUP BY t.timetable_id
        ORDER BY 
            CASE ts.day_of_week
                WHEN 'Monday' THEN 1
                WHEN 'Tuesday' THEN 2
                WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4
                WHEN 'Friday' THEN 5
                WHEN 'Saturday' THEN 6
            END,
            ts.start_time ASC
    ", [$facultyId, $academicYear, str_replace('-', '-', $academicYear), $semester]);
    
    return [
        'success' => true,
        'data' => [
            'schedule' => $schedule,
            'week' => $week,
            'view' => $view,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'user_role' => 'faculty'
        ]
    ];
}

/**
 * Get admin schedule (all schedules)
 */
function getAdminSchedule($db, $userId, $week, $view, $academicYear, $semester) {
    $department = $_GET['department'] ?? '';
    $faculty_id = $_GET['faculty_id'] ?? '';
    $subject_id = $_GET['subject_id'] ?? '';
    
    // Build WHERE clause based on filters
    $whereConditions = ['t.is_active = 1'];
    $params = [];
    
    if (!empty($academicYear)) {
        $whereConditions[] = '(t.academic_year = ? OR t.academic_year = ?)';
        $params[] = $academicYear;
        $params[] = str_replace('-', '-', $academicYear);
    }
    
    if (!empty($semester)) {
        $whereConditions[] = 't.semester = ?';
        $params[] = $semester;
    }
    
    if (!empty($faculty_id)) {
        $whereConditions[] = 't.faculty_id = ?';
        $params[] = $faculty_id;
    }
    
    if (!empty($subject_id)) {
        $whereConditions[] = 't.subject_id = ?';
        $params[] = $subject_id;
    }
    
    if (!empty($department)) {
        $whereConditions[] = 's.department = ?';
        $params[] = $department;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get complete schedule
    $schedule = $db->fetchAll("
        SELECT 
            t.timetable_id,
            s.subject_code,
            s.subject_name,
            s.credits,
            s.department,
            c.room_number,
            c.building,
            c.capacity,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.slot_name,
            t.section,
            t.semester,
            t.academic_year,
            t.notes,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
            f.designation,
            f.employee_id,
            COUNT(e.student_id) as enrolled_count,
            t.created_at,
            t.modified_at
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        JOIN faculty f ON t.faculty_id = f.faculty_id
        LEFT JOIN enrollments e ON s.subject_id = e.subject_id 
            AND e.section = t.section
            AND e.semester = t.semester 
            AND e.academic_year = t.academic_year
            AND e.status = 'enrolled'
        WHERE {$whereClause}
        GROUP BY t.timetable_id
        ORDER BY 
            CASE ts.day_of_week
                WHEN 'Monday' THEN 1
                WHEN 'Tuesday' THEN 2
                WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4
                WHEN 'Friday' THEN 5
                WHEN 'Saturday' THEN 6
            END,
            ts.start_time ASC
    ", $params);
    
    return [
        'success' => true,
        'data' => [
            'schedule' => $schedule,
            'week' => $week,
            'view' => $view,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'user_role' => 'admin',
            'filters' => [
                'department' => $department,
                'faculty_id' => $faculty_id,
                'subject_id' => $subject_id
            ]
        ]
    ];
}

/**
 * Get weekly schedule view
 */
function getWeeklySchedule($db, $userId, $userRole) {
    $scheduleData = getSchedule($db, $userId, $userRole);
    
    if (!$scheduleData['success']) {
        return $scheduleData;
    }
    
    $schedule = $scheduleData['data']['schedule'];
    
    // Organize schedule by day and time
    $weeklySchedule = [];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    // Initialize empty schedule
    foreach ($days as $day) {
        $weeklySchedule[$day] = [];
    }
    
    // Group schedule items by day
    foreach ($schedule as $item) {
        $day = $item['day_of_week'];
        if (!isset($weeklySchedule[$day])) {
            $weeklySchedule[$day] = [];
        }
        $weeklySchedule[$day][] = $item;
    }
    
    // Sort each day's schedule by time
    foreach ($weeklySchedule as $day => $daySchedule) {
        usort($weeklySchedule[$day], function($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });
    }
    
    return [
        'success' => true,
        'data' => [
            'weekly_schedule' => $weeklySchedule,
            'days' => $days,
            'user_role' => $userRole
        ]
    ];
}

/**
 * Get daily schedule view
 */
function getDailySchedule($db, $userId, $userRole) {
    $selectedDay = $_GET['day'] ?? date('l'); // Default to today
    $scheduleData = getSchedule($db, $userId, $userRole);
    
    if (!$scheduleData['success']) {
        return $scheduleData;
    }
    
    $schedule = $scheduleData['data']['schedule'];
    
    // Filter schedule for selected day
    $dailySchedule = array_filter($schedule, function($item) use ($selectedDay) {
        return $item['day_of_week'] === $selectedDay;
    });
    
    // Sort by time
    usort($dailySchedule, function($a, $b) {
        return strcmp($a['start_time'], $b['start_time']);
    });
    
    return [
        'success' => true,
        'data' => [
            'daily_schedule' => array_values($dailySchedule),
            'selected_day' => $selectedDay,
            'user_role' => $userRole
        ]
    ];
}

/**
 * Get schedule statistics
 */
function getScheduleStats($db, $userId, $userRole) {
    try {
        switch ($userRole) {
            case 'student':
                return getStudentStats($db, $userId);
            case 'faculty':
                return getFacultyStats($db, $userId);
            case 'admin':
                return getAdminStats($db, $userId);
            default:
                throw new Exception('Invalid user role');
        }
    } catch (Exception $e) {
        throw new Exception('Failed to fetch statistics: ' . $e->getMessage());
    }
}

/**
 * Get student statistics
 */
function getStudentStats($db, $userId) {
    $student = $db->fetchRow("SELECT student_id FROM students WHERE user_id = ?", [$userId]);
    if (!$student) {
        throw new Exception('Student profile not found');
    }
    
    $stats = $db->fetchRow("
        SELECT 
            COUNT(DISTINCT e.subject_id) as total_subjects,
            COUNT(DISTINCT t.timetable_id) as total_classes,
            COUNT(DISTINCT t.classroom_id) as different_classrooms,
            COUNT(DISTINCT t.faculty_id) as different_faculty,
            SUM(s.credits) as total_credits
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN timetables t ON s.subject_id = t.subject_id 
            AND e.section = t.section
            AND e.semester = t.semester 
            AND e.academic_year = t.academic_year
        WHERE e.student_id = ? 
        AND e.status = 'enrolled'
        AND t.is_active = 1
        AND (t.academic_year = '2025-2026' OR t.academic_year = '2025-26')
        AND t.semester = 1
    ", [$student['student_id']]);
    
    return [
        'success' => true,
        'data' => array_merge($stats ?: [], ['user_role' => 'student'])
    ];
}

/**
 * Get faculty statistics
 */
function getFacultyStats($db, $userId) {
    $faculty = $db->fetchRow("SELECT faculty_id FROM faculty WHERE user_id = ?", [$userId]);
    if (!$faculty) {
        throw new Exception('Faculty profile not found');
    }
    
    $stats = $db->fetchRow("
        SELECT 
            COUNT(DISTINCT t.subject_id) as total_subjects,
            COUNT(DISTINCT t.timetable_id) as total_classes,
            COUNT(DISTINCT t.classroom_id) as different_classrooms,
            COUNT(DISTINCT e.student_id) as total_students
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        LEFT JOIN enrollments e ON s.subject_id = e.subject_id 
            AND e.section = t.section
            AND e.semester = t.semester 
            AND e.academic_year = t.academic_year
            AND e.status = 'enrolled'
        WHERE t.faculty_id = ? 
        AND t.is_active = 1
        AND (t.academic_year = '2025-2026' OR t.academic_year = '2025-26')
        AND t.semester = 1
    ", [$faculty['faculty_id']]);
    
    return [
        'success' => true,
        'data' => array_merge($stats ?: [], ['user_role' => 'faculty'])
    ];
}

/**
 * Get admin statistics
 */
function getAdminStats($db, $userId) {
    $stats = $db->fetchRow("
        SELECT 
            COUNT(DISTINCT t.subject_id) as total_subjects,
            COUNT(DISTINCT t.timetable_id) as total_classes,
            COUNT(DISTINCT t.classroom_id) as classrooms_used,
            COUNT(DISTINCT t.faculty_id) as faculty_teaching,
            COUNT(DISTINCT e.student_id) as students_enrolled
        FROM timetables t
        LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
            AND e.section = t.section
            AND e.semester = t.semester 
            AND e.academic_year = t.academic_year
            AND e.status = 'enrolled'
        WHERE t.is_active = 1
        AND (t.academic_year = '2025-2026' OR t.academic_year = '2025-26')
        AND t.semester = 1
    ");
    
    return [
        'success' => true,
        'data' => array_merge($stats ?: [], ['user_role' => 'admin'])
    ];
}

/**
 * Search schedule
 */
function searchSchedule($db, $userId, $userRole) {
    $query = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? 'all'; // subject, faculty, classroom, all
    
    if (empty($query) || strlen($query) < 2) {
        return [
            'success' => false,
            'error' => 'Search query must be at least 2 characters'
        ];
    }
    
    // Get base schedule first
    $scheduleData = getSchedule($db, $userId, $userRole);
    if (!$scheduleData['success']) {
        return $scheduleData;
    }
    
    $schedule = $scheduleData['data']['schedule'];
    $query = strtolower($query);
    
    // Filter schedule based on search query
    $filteredSchedule = array_filter($schedule, function($item) use ($query, $type) {
        $searchFields = [
            'subject' => strtolower($item['subject_code'] . ' ' . $item['subject_name']),
            'faculty' => strtolower($item['faculty_name'] ?? ''),
            'classroom' => strtolower($item['room_number'] . ' ' . $item['building']),
            'all' => strtolower($item['subject_code'] . ' ' . $item['subject_name'] . ' ' . 
                               ($item['faculty_name'] ?? '') . ' ' . 
                               $item['room_number'] . ' ' . $item['building'])
        ];
        
        $searchIn = $searchFields[$type] ?? $searchFields['all'];
        return strpos($searchIn, $query) !== false;
    });
    
    return [
        'success' => true,
        'data' => [
            'results' => array_values($filteredSchedule),
            'query' => $query,
            'type' => $type,
            'count' => count($filteredSchedule)
        ]
    ];
}

/**
 * Get schedule conflicts
 */
function getScheduleConflicts($db, $userId, $userRole) {
    if ($userRole !== 'admin') {
        throw new Exception('Unauthorized access', 403);
    }
    
    // Find schedule conflicts
    $conflicts = $db->fetchAll("
        SELECT 
            t1.timetable_id as conflict_id_1,
            t2.timetable_id as conflict_id_2,
            s1.subject_code as subject_1,
            s2.subject_code as subject_2,
            c.room_number,
            c.building,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            'classroom' as conflict_type
        FROM timetables t1
        JOIN timetables t2 ON t1.classroom_id = t2.classroom_id 
            AND t1.slot_id = t2.slot_id
            AND t1.academic_year = t2.academic_year
            AND t1.semester = t2.semester
            AND t1.timetable_id < t2.timetable_id
        JOIN subjects s1 ON t1.subject_id = s1.subject_id
        JOIN subjects s2 ON t2.subject_id = s2.subject_id
        JOIN classrooms c ON t1.classroom_id = c.classroom_id
        JOIN time_slots ts ON t1.slot_id = ts.slot_id
        WHERE t1.is_active = 1 AND t2.is_active = 1
        
        UNION ALL
        
        SELECT 
            t1.timetable_id as conflict_id_1,
            t2.timetable_id as conflict_id_2,
            s1.subject_code as subject_1,
            s2.subject_code as subject_2,
            f.first_name || ' ' || f.last_name as room_number,
            f.department as building,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            'faculty' as conflict_type
        FROM timetables t1
        JOIN timetables t2 ON t1.faculty_id = t2.faculty_id 
            AND t1.slot_id = t2.slot_id
            AND t1.academic_year = t2.academic_year
            AND t1.semester = t2.semester
            AND t1.timetable_id < t2.timetable_id
        JOIN subjects s1 ON t1.subject_id = s1.subject_id
        JOIN subjects s2 ON t2.subject_id = s2.subject_id
        JOIN faculty f ON t1.faculty_id = f.faculty_id
        JOIN time_slots ts ON t1.slot_id = ts.slot_id
        WHERE t1.is_active = 1 AND t2.is_active = 1
        
        ORDER BY day_of_week, start_time
    ");
    
    return [
        'success' => true,
        'data' => [
            'conflicts' => $conflicts,
            'count' => count($conflicts)
        ]
    ];
}

/**
 * Export schedule (placeholder - actual export would be more complex)
 */
function exportSchedule($db, $userId, $userRole) {
    $format = $_GET['format'] ?? 'pdf'; // pdf, excel, csv
    
    // Get schedule data
    $scheduleData = getSchedule($db, $userId, $userRole);
    if (!$scheduleData['success']) {
        return $scheduleData;
    }
    
    // In a real implementation, you would generate the actual export file here
    // For now, we'll return a success message with download URL
    
    $filename = "timetable_" . $userRole . "_" . date('Y_m_d_H_i_s') . "." . $format;
    
    return [
        'success' => true,
        'data' => [
            'message' => 'Export prepared successfully',
            'filename' => $filename,
            'format' => $format,
            'download_url' => '/exports/' . $filename,
            'schedule_count' => count($scheduleData['data']['schedule'])
        ]
    ];
}

/**
 * Get available time slots
 */
function getAvailableSlots($db, $userId, $userRole) {
    if ($userRole !== 'admin') {
        throw new Exception('Unauthorized access', 403);
    }
    
    $date = $_GET['date'] ?? date('Y-m-d');
    $classroom_id = $_GET['classroom_id'] ?? '';
    $faculty_id = $_GET['faculty_id'] ?? '';
    
    // Get all time slots for the day
    $dayOfWeek = date('l', strtotime($date));
    
    $allSlots = $db->fetchAll("
        SELECT * FROM time_slots 
        WHERE day_of_week = ? AND is_active = 1 
        ORDER BY start_time
    ", [$dayOfWeek]);
    
    // Get occupied slots
    $occupiedSlots = [];
    
    if (!empty($classroom_id)) {
        $occupied = $db->fetchAll("
            SELECT slot_id FROM timetables 
            WHERE classroom_id = ? AND is_active = 1
        ", [$classroom_id]);
        $occupiedSlots = array_merge($occupiedSlots, array_column($occupied, 'slot_id'));
    }
    
    if (!empty($faculty_id)) {
        $occupied = $db->fetchAll("
            SELECT slot_id FROM timetables 
            WHERE faculty_id = ? AND is_active = 1
        ", [$faculty_id]);
        $occupiedSlots = array_merge($occupiedSlots, array_column($occupied, 'slot_id'));
    }
    
    // Filter available slots
    $availableSlots = array_filter($allSlots, function($slot) use ($occupiedSlots) {
        return !in_array($slot['slot_id'], $occupiedSlots);
    });
    
    return [
        'success' => true,
        'data' => [
            'available_slots' => array_values($availableSlots),
            'occupied_slots' => array_unique($occupiedSlots),
            'date' => $date,
            'day_of_week' => $dayOfWeek
        ]
    ];
}

/**
 * Create new schedule entry (Admin only)
 */
function createSchedule($db, $userId) {
    // Implementation would go here
    return ['success' => false, 'message' => 'Not implemented yet'];
}

/**
 * Update schedule entry (Admin only)
 */
function updateSchedule($db, $userId) {
    // Implementation would go here
    return ['success' => false, 'message' => 'Not implemented yet'];
}

/**
 * Delete schedule entry (Admin only)
 */
function deleteSchedule($db, $userId) {
    // Implementation would go here
    return ['success' => false, 'message' => 'Not implemented yet'];
}
?>