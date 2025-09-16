<?php
/**
 * Timetable Details API
 * Returns detailed info for a single timetable entry by ID
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

// JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

try {
    // Auth
    if (!User::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid timetable ID']);
        exit;
    }

    $db = Database::getInstance();

    // Fetch timetable details with joins
    $timetable = $db->fetchRow("
        SELECT 
            t.timetable_id,
            t.subject_id,
            s.subject_code,
            s.subject_name,
            s.credits,
            s.department AS subject_department,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            ts.slot_name,
            t.section,
            t.semester,
            t.academic_year,
            t.notes,
            c.room_number,
            c.building,
            c.capacity,
            CONCAT(f.first_name, ' ', f.last_name) AS faculty_name,
            f.employee_id,
            f.department AS faculty_department,
            t.created_at,
            u.username AS created_by_username,
            (
                SELECT COUNT(e.student_id) 
                FROM enrollments e 
                WHERE e.subject_id = t.subject_id 
                  AND e.section = t.section 
                  AND e.semester = t.semester 
                  AND e.academic_year = t.academic_year 
                  AND e.status = 'enrolled'
            ) AS enrolled_students
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        JOIN classrooms c ON t.classroom_id = c.classroom_id
        JOIN faculty f ON t.faculty_id = f.faculty_id
        LEFT JOIN users u ON t.created_by = u.user_id
        WHERE t.timetable_id = ?
        LIMIT 1
    ", [$id]);

    if (!$timetable) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Timetable not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'timetable' => $timetable
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>






