<?php
/**
 * Time Slot Details API
 * Timetable Management System
 * 
 * Returns detailed time slot information for modal display
 * Admin-only access for time slot management
 */

// Start session and security checks
session_start();
// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/TimeSlot.php';

// Set JSON content type
header('Content-Type: application/json');

// Ensure user is logged in and has admin role
if (!User::isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

if (!User::hasRole('admin')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

// Get time slot ID from request
$slotId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$slotId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Time slot ID is required'
    ]);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get comprehensive time slot information with usage statistics
    $slot = $db->fetchRow("
        SELECT ts.*, 
               COUNT(DISTINCT t.timetable_id) as usage_count,
               COUNT(DISTINCT CASE WHEN t.is_active = 1 THEN t.timetable_id END) as active_usage_count,
               COUNT(DISTINCT t.subject_id) as subjects_count,
               COUNT(DISTINCT t.faculty_id) as faculty_count,
               COUNT(DISTINCT t.classroom_id) as classrooms_count,
               GROUP_CONCAT(DISTINCT s.subject_code ORDER BY s.subject_code SEPARATOR ', ') as subject_codes,
               GROUP_CONCAT(DISTINCT CONCAT(f.first_name, ' ', f.last_name) ORDER BY f.last_name SEPARATOR ', ') as faculty_names,
               GROUP_CONCAT(DISTINCT c.room_number ORDER BY c.room_number SEPARATOR ', ') as room_numbers
        FROM time_slots ts
        LEFT JOIN timetables t ON ts.slot_id = t.slot_id
        LEFT JOIN subjects s ON t.subject_id = s.subject_id
        LEFT JOIN faculty f ON t.faculty_id = f.faculty_id
        LEFT JOIN classrooms c ON t.classroom_id = c.classroom_id
        WHERE ts.slot_id = ?
        GROUP BY ts.slot_id
    ", [$slotId]);
    
    if (!$slot) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Time slot not found'
        ]);
        exit;
    }
    
    // Get detailed timetable entries for this slot
    $scheduledClasses = [];
    if ($slot['usage_count'] > 0) {
        $scheduledClasses = $db->fetchAll("
            SELECT t.timetable_id, t.section, t.semester, t.academic_year, t.is_active,
                   s.subject_code, s.subject_name,
                   CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                   c.room_number, c.building,
                   COUNT(e.enrollment_id) as enrolled_students
            FROM timetables t
            JOIN subjects s ON t.subject_id = s.subject_id
            JOIN faculty f ON t.faculty_id = f.faculty_id
            JOIN classrooms c ON t.classroom_id = c.classroom_id
            LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
                AND t.section = e.section 
                AND t.semester = e.semester 
                AND t.academic_year = e.academic_year 
                AND e.status = 'enrolled'
            WHERE t.slot_id = ?
            GROUP BY t.timetable_id
            ORDER BY t.academic_year DESC, t.semester ASC, s.subject_code ASC
        ", [$slotId]);
    }
    
    // Get conflicts for this time slot (same day/time as this slot)
    $potentialConflicts = $db->fetchAll("
        SELECT ts2.slot_id, ts2.slot_name, ts2.day_of_week, ts2.start_time, ts2.end_time,
               COUNT(t.timetable_id) as usage_count
        FROM time_slots ts1
        JOIN time_slots ts2 ON ts1.day_of_week = ts2.day_of_week
            AND ts1.slot_id != ts2.slot_id
            AND (
                (ts2.start_time BETWEEN ts1.start_time AND ts1.end_time) OR
                (ts2.end_time BETWEEN ts1.start_time AND ts1.end_time) OR
                (ts1.start_time BETWEEN ts2.start_time AND ts2.end_time)
            )
        LEFT JOIN timetables t ON ts2.slot_id = t.slot_id AND t.is_active = 1
        WHERE ts1.slot_id = ?
        GROUP BY ts2.slot_id
        ORDER BY ts2.start_time ASC
    ", [$slotId]);
    
    // Calculate duration in minutes
    $startTime = new DateTime($slot['start_time']);
    $endTime = new DateTime($slot['end_time']);
    $duration = $endTime->diff($startTime);
    $durationMinutes = ($duration->h * 60) + $duration->i;
    
    // Prepare response data
    $responseData = [
        'slot_id' => (int)$slot['slot_id'],
        'slot_name' => $slot['slot_name'],
        'day_of_week' => $slot['day_of_week'],
        'start_time' => $slot['start_time'],
        'end_time' => $slot['end_time'],
        'duration_minutes' => $durationMinutes,
        'slot_type' => $slot['slot_type'],
        'is_active' => (bool)$slot['is_active'],
        'usage_count' => (int)$slot['usage_count'],
        'active_usage_count' => (int)$slot['active_usage_count'],
        'subjects_count' => (int)$slot['subjects_count'],
        'faculty_count' => (int)$slot['faculty_count'],
        'classrooms_count' => (int)$slot['classrooms_count'],
        'subject_codes' => $slot['subject_codes'],
        'faculty_names' => $slot['faculty_names'],
        'room_numbers' => $slot['room_numbers'],
        'scheduled_classes' => $scheduledClasses,
        'potential_conflicts' => $potentialConflicts
    ];
    
    echo json_encode([
        'success' => true,
        'slot' => $responseData
    ]);
    
} catch (Exception $e) {
    error_log("Time Slot Details API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error occurred while fetching time slot details'
    ]);
}
?>