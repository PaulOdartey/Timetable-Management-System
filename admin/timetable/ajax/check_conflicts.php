<?php
/**
 * AJAX Handler: Check Scheduling Conflicts
 * 
 * Checks for faculty and classroom scheduling conflicts
 * Also provides capacity warnings and additional validation
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../classes/User.php';
require_once '../../../classes/Timetable.php';

// Set JSON header
header('Content-Type: application/json');

// Security checks
try {
    // Ensure user is logged in and has admin role
    User::requireLogin();
    User::requireRole('admin');
    
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['faculty_id', 'classroom_id', 'slot_id', 'semester', 'academic_year'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Field '{$field}' is required");
        }
    }
    
    $facultyId = (int)$input['faculty_id'];
    $classroomId = (int)$input['classroom_id'];
    $slotId = (int)$input['slot_id'];
    $semester = (int)$input['semester'];
    $academicYear = trim($input['academic_year']);
    $excludeTimetableId = isset($input['exclude_timetable_id']) ? (int)$input['exclude_timetable_id'] : null;
    
    // Validate input values
    if ($facultyId <= 0 || $classroomId <= 0 || $slotId <= 0 || $semester <= 0) {
        throw new Exception('Invalid ID values provided');
    }
    
    if (!preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
        throw new Exception('Invalid academic year format');
    }
    
    // Initialize timetable manager
    $timetableManager = new Timetable();
    $db = Database::getInstance();
    
    // Get resource information for detailed messages
    $resources = $db->fetchOne("
        SELECT 
            f.first_name, f.last_name, f.employee_id,
            c.room_number, c.building, c.capacity,
            ts.day_of_week, ts.start_time, ts.end_time, ts.slot_name
        FROM faculty f, classrooms c, time_slots ts
        WHERE f.faculty_id = ? AND c.classroom_id = ? AND ts.slot_id = ?
    ", [$facultyId, $classroomId, $slotId]);
    
    if (!$resources) {
        throw new Exception('One or more resources not found');
    }
    
    $facultyName = $resources['first_name'] . ' ' . $resources['last_name'];
    $classroomName = $resources['room_number'] . ' (' . $resources['building'] . ')';
    $timeSlot = $resources['day_of_week'] . ' ' . 
                date('g:i A', strtotime($resources['start_time'])) . ' - ' . 
                date('g:i A', strtotime($resources['end_time']));
    
    // Check for conflicts using the stored procedure and additional logic
    $conflictResult = [];
    try {
        $conflictResult = $timetableManager->checkSchedulingConflicts(
            $facultyId, 
            $classroomId, 
            $slotId, 
            $semester, 
            $academicYear, 
            $excludeTimetableId
        );
    } catch (Exception $e) {
        error_log("Conflict Check Method Error: " . $e->getMessage());
        $conflictResult = [
            'has_conflict' => true,
            'message' => 'Error checking conflicts: ' . $e->getMessage()
        ];
    }
    
    // Ensure we have the required keys
    if (!isset($conflictResult['has_conflict'])) {
        $conflictResult['has_conflict'] = true;
    }
    if (!isset($conflictResult['message'])) {
        $conflictResult['message'] = 'Unknown conflict check result';
    }
    
    $response = [
        'success' => true,
        'has_conflict' => $conflictResult['has_conflict'],
        'message' => $conflictResult['message'],
        'details' => [
            'faculty_name' => $facultyName,
            'employee_id' => $resources['employee_id'],
            'classroom' => $classroomName,
            'time_slot' => $timeSlot,
            'semester' => $semester,
            'academic_year' => $academicYear
        ],
        'warnings' => [],
        'additional_info' => []
    ];
    
    if (!$conflictResult['has_conflict']) {
        // No conflicts, but check for additional warnings
        
        // 1. Check for capacity issues if subject and section are provided
        if (isset($input['subject_id']) && isset($input['section'])) {
            $subjectId = (int)$input['subject_id'];
            $section = trim($input['section']) ?: 'A';
            
            try {
                // Create a temporary method call to check capacity
                $capacityQuery = "
                    SELECT 
                        c.capacity,
                        COUNT(e.enrollment_id) as enrolled_count
                    FROM classrooms c
                    LEFT JOIN enrollments e ON (
                        e.subject_id = ? AND 
                        e.section = ? AND 
                        e.semester = ? AND 
                        e.academic_year = ? AND 
                        e.status = 'enrolled'
                    )
                    WHERE c.classroom_id = ?
                    GROUP BY c.classroom_id, c.capacity
                ";
                
                $capacityResult = $db->fetchOne($capacityQuery, [
                    $subjectId, $section, $semester, $academicYear, $classroomId
                ]);
                
                if ($capacityResult) {
                    $capacity = (int)$capacityResult['capacity'];
                    $enrolled = (int)$capacityResult['enrolled_count'];
                    
                    if ($enrolled > $capacity) {
                        $response['warnings'][] = "⚠️ CAPACITY EXCEEDED: {$enrolled} students enrolled but classroom only has capacity for {$capacity}";
                    } elseif ($enrolled > ($capacity * 0.9)) {
                        $response['warnings'][] = "⚠️ NEAR CAPACITY: Classroom is nearly full ({$enrolled}/{$capacity} students)";
                    } elseif ($enrolled > ($capacity * 0.8)) {
                        $response['warnings'][] = "ℹ️ HIGH OCCUPANCY: Classroom is {$enrolled}/{$capacity} students";
                    }
                }
            } catch (Exception $e) {
                error_log("Capacity Check Error: " . $e->getMessage());
                $response['warnings'][] = 'Could not check enrollment capacity';
            }
        }
        
        // 2. Check for nearby time slots with same faculty
        try {
            $nearbySlots = $db->fetchAll("
                SELECT 
                    s.subject_code, s.subject_name,
                    c.room_number, c.building,
                    ts.start_time, ts.end_time, ts.slot_name,
                    t.section
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                WHERE t.faculty_id = ? 
                    AND ts.day_of_week = ?
                    AND t.semester = ? 
                    AND t.academic_year = ?
                    AND t.is_active = 1
                    AND ts.slot_id != ?
                ORDER BY ts.start_time
            ", [$facultyId, $resources['day_of_week'], $semester, $academicYear, $slotId]);
            
            if (!empty($nearbySlots)) {
                $response['additional_info']['faculty_schedule'] = [
                    'message' => "Faculty member has " . count($nearbySlots) . " other class(es) on " . $resources['day_of_week'],
                    'classes' => $nearbySlots
                ];
            }
        } catch (Exception $e) {
            error_log("Faculty Schedule Check Error: " . $e->getMessage());
        }
        
        // 3. Check for classroom utilization on this day
        try {
            $classroomUsage = $db->fetchAll("
                SELECT 
                    s.subject_code, s.subject_name,
                    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                    ts.start_time, ts.end_time, ts.slot_name,
                    t.section
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                WHERE t.classroom_id = ? 
                    AND ts.day_of_week = ?
                    AND t.semester = ? 
                    AND t.academic_year = ?
                    AND t.is_active = 1
                    AND ts.slot_id != ?
                ORDER BY ts.start_time
            ", [$classroomId, $resources['day_of_week'], $semester, $academicYear, $slotId]);
            
            if (!empty($classroomUsage)) {
                $response['additional_info']['classroom_usage'] = [
                    'message' => "Classroom has " . count($classroomUsage) . " other class(es) on " . $resources['day_of_week'],
                    'classes' => $classroomUsage
                ];
            }
        } catch (Exception $e) {
            error_log("Classroom Usage Check Error: " . $e->getMessage());
        }
        
        // 4. Check time slot popularity
        try {
            $slotUsage = $db->fetchOne("
                SELECT COUNT(*) as usage_count
                FROM timetables t
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                WHERE t.slot_id = ? 
                    AND t.semester = ? 
                    AND t.academic_year = ?
                    AND t.is_active = 1
            ", [$slotId, $semester, $academicYear]);
            
            if ($slotUsage && $slotUsage['usage_count'] > 0) {
                $response['additional_info']['slot_usage'] = [
                    'message' => "This time slot is used by " . $slotUsage['usage_count'] . " other class(es)",
                    'count' => (int)$slotUsage['usage_count']
                ];
            }
        } catch (Exception $e) {
            error_log("Slot Usage Check Error: " . $e->getMessage());
        }
        
        // Final message if no conflicts
        if (empty($response['warnings'])) {
            $response['message'] = "✅ No conflicts detected. Schedule is available for {$facultyName} in {$classroomName} on {$timeSlot}";
        } else {
            $response['message'] = "⚠️ Schedule is available but please review the warnings below";
        }
    } else {
        // There are conflicts - get detailed conflict information
        try {
            $conflictDetails = $db->fetchAll("
                SELECT 
                    t.timetable_id,
                    s.subject_code, s.subject_name,
                    CONCAT(f.first_name, ' ', f.last_name) as conflicting_faculty,
                    c.room_number, c.building,
                    t.section,
                    'faculty' as conflict_type
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                WHERE t.faculty_id = ? 
                    AND t.slot_id = ?
                    AND t.semester = ? 
                    AND t.academic_year = ?
                    AND t.is_active = 1
                    " . ($excludeTimetableId ? "AND t.timetable_id != ?" : "") . "
                
                UNION ALL
                
                SELECT 
                    t.timetable_id,
                    s.subject_code, s.subject_name,
                    CONCAT(f.first_name, ' ', f.last_name) as conflicting_faculty,
                    c.room_number, c.building,
                    t.section,
                    'classroom' as conflict_type
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                WHERE t.classroom_id = ? 
                    AND t.slot_id = ?
                    AND t.semester = ? 
                    AND t.academic_year = ?
                    AND t.is_active = 1
                    " . ($excludeTimetableId ? "AND t.timetable_id != ?" : "")
            , $excludeTimetableId 
                ? [$facultyId, $slotId, $semester, $academicYear, $excludeTimetableId, 
                   $classroomId, $slotId, $semester, $academicYear, $excludeTimetableId]
                : [$facultyId, $slotId, $semester, $academicYear, 
                   $classroomId, $slotId, $semester, $academicYear]);
            
            if (!empty($conflictDetails)) {
                $response['conflict_details'] = $conflictDetails;
                
                // Create detailed conflict message
                $facultyConflicts = array_filter($conflictDetails, function($c) { return $c['conflict_type'] === 'faculty'; });
                $classroomConflicts = array_filter($conflictDetails, function($c) { return $c['conflict_type'] === 'classroom'; });
                
                $conflictMessages = [];
                
                if (!empty($facultyConflicts)) {
                    $conflict = reset($facultyConflicts);
                    $conflictMessages[] = "Faculty conflict: {$facultyName} is already teaching {$conflict['subject_code']} - {$conflict['subject_name']} (Section {$conflict['section']})";
                }
                
                if (!empty($classroomConflicts)) {
                    $conflict = reset($classroomConflicts);
                    $conflictMessages[] = "Classroom conflict: {$classroomName} is already booked for {$conflict['subject_code']} - {$conflict['subject_name']} with {$conflict['conflicting_faculty']} (Section {$conflict['section']})";
                }
                
                if (!empty($conflictMessages)) {
                    $response['message'] = "❌ " . implode('. ', $conflictMessages);
                }
            }
        } catch (Exception $e) {
            error_log("Conflict Details Error: " . $e->getMessage());
            // Keep the original conflict message from the stored procedure
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    error_log("Check Conflicts Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'has_conflict' => true,
        'message' => 'Error checking conflicts: ' . $e->getMessage(),
        'details' => [],
        'warnings' => [],
        'additional_info' => []
    ]);
}
?>