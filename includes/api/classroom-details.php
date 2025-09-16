<?php
/**
 * Classroom Details API Endpoint
 * Returns detailed information about a specific classroom
 * Timetable Management System
 */

// Start session and set content type
session_start();
header('Content-Type: application/json');

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Classroom.php';

// Response helper function
function sendResponse($success, $message = '', $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'classroom' => $data // Change 'data' to 'classroom' to match the JavaScript
    ]);
    exit;
}

try {
    // Check if user is logged in and has appropriate role
    if (!User::isLoggedIn()) {
        sendResponse(false, 'Authentication required');
    }

    // Check if user has appropriate role (admin, faculty, or student can view classroom details)
    $userRole = User::getCurrentUserRole();
    if (!in_array($userRole, ['admin', 'faculty', 'student'])) {
        sendResponse(false, 'Insufficient permissions');
    }

    // Get classroom ID from request
    $classroomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$classroomId) {
        sendResponse(false, 'Classroom ID is required');
    }

    // Initialize classroom manager
    $classroomManager = new Classroom();
    $db = Database::getInstance();

    // Get detailed classroom information
    $classroom = $db->fetchRow("
        SELECT c.*,
               d.department_name,
               d.department_code,
               COUNT(DISTINCT t.timetable_id) as total_schedules,
               COUNT(DISTINCT CASE WHEN t.is_active = 1 THEN t.timetable_id END) as current_bookings,
               GROUP_CONCAT(DISTINCT CONCAT(sub.subject_code, ' (', ts.day_of_week, ' ', 
                           TIME_FORMAT(ts.start_time, '%H:%i'), '-', 
                           TIME_FORMAT(ts.end_time, '%H:%i'), ')') 
                           ORDER BY ts.day_of_week, ts.start_time SEPARATOR ', ') as scheduled_subjects
        FROM classrooms c
        LEFT JOIN departments d ON c.department_id = d.department_id
        LEFT JOIN timetables t ON c.classroom_id = t.classroom_id
        LEFT JOIN subjects sub ON t.subject_id = sub.subject_id
        LEFT JOIN time_slots ts ON t.slot_id = ts.slot_id AND t.is_active = 1
        WHERE c.classroom_id = ?
        GROUP BY c.classroom_id
    ", [$classroomId]);

    if (!$classroom) {
        sendResponse(false, 'Classroom not found');
    }

    // Get additional statistics for this classroom
    $utilizationStats = $db->fetchRow("
        SELECT 
            COUNT(DISTINCT DATE(t.created_at)) as days_in_use,
            COUNT(DISTINCT t.faculty_id) as different_faculty,
            COUNT(DISTINCT t.subject_id) as different_subjects,
            MIN(ts.start_time) as earliest_class,
            MAX(ts.end_time) as latest_class
        FROM timetables t
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE t.classroom_id = ? AND t.is_active = 1
    ", [$classroomId]);

    // Get recent activity
    $recentActivity = $db->fetchAll("
        SELECT t.*, s.subject_name, s.subject_code,
               f.first_name as faculty_first, f.last_name as faculty_last,
               ts.day_of_week, ts.start_time, ts.end_time, ts.slot_name
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN faculty f ON t.faculty_id = f.faculty_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        WHERE t.classroom_id = ? AND t.is_active = 1
        ORDER BY ts.day_of_week, ts.start_time
        LIMIT 10
    ", [$classroomId]);

    // Prepare enhanced classroom data
    $enhancedClassroom = array_merge($classroom, [
        'utilization_stats' => $utilizationStats ?: [],
        'recent_activity' => $recentActivity ?: [],
        
        // Calculate utilization percentage
        'utilization_percentage' => $classroom['current_bookings'] > 0 
            ? min(round(($classroom['current_bookings'] / 20) * 100, 1), 100) // Assuming max 20 slots per week
            : 0,
            
        // Format dates for display
        'created_at_formatted' => date('F j, Y g:i A', strtotime($classroom['created_at'])),
        'updated_at_formatted' => date('F j, Y g:i A', strtotime($classroom['updated_at'])),
        
        // Additional computed fields
        'is_heavily_utilized' => ($classroom['current_bookings'] ?? 0) > 15,
        'peak_usage_time' => $utilizationStats && $utilizationStats['earliest_class'] && $utilizationStats['latest_class'] 
            ? date('g:i A', strtotime($utilizationStats['earliest_class'])) . ' - ' . 
              date('g:i A', strtotime($utilizationStats['latest_class']))
            : 'No active schedules'
    ]);

    // Send successful response with classroom data
    sendResponse(true, 'Classroom details retrieved successfully', $enhancedClassroom);

} catch (Exception $e) {
    error_log("Error in classroom-details.php: " . $e->getMessage());
    sendResponse(false, 'An error occurred while retrieving classroom details: ' . $e->getMessage());
}
?>