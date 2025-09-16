<?php
/**
 * Subject Details API Endpoint
 * Returns comprehensive subject information for modal display
 * Timetable Management System
 */

// Security check
defined('SYSTEM_ACCESS') or define('SYSTEM_ACCESS', true);

// Start session and include required files
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Subject.php';

// Set JSON content type
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Enable CORS for AJAX requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'subject' => null
];

try {
    // Check if user is logged in and has admin role
    if (!User::isLoggedIn()) {
        $response['message'] = 'Authentication required';
        echo json_encode($response);
        exit;
    }

    if (!User::hasRole('admin')) {
        $response['message'] = 'Insufficient permissions';
        echo json_encode($response);
        exit;
    }

    // Validate subject ID parameter
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        $response['message'] = 'Subject ID is required';
        echo json_encode($response);
        exit;
    }

    $subjectId = (int)$_GET['id'];
    if ($subjectId <= 0) {
        $response['message'] = 'Invalid subject ID';
        echo json_encode($response);
        exit;
    }

    // Initialize database and subject manager
    $db = Database::getInstance();
    $subjectManager = new Subject();

    // Get comprehensive subject details
    $subjectDetails = $db->fetchRow("
        SELECT s.*,
               d.department_name,
               d.department_code,
               COUNT(DISTINCT fs.faculty_id) as assigned_faculty_count,
               COUNT(DISTINCT e.student_id) as enrolled_students_count,
               COUNT(DISTINCT t.timetable_id) as scheduled_classes_count,
               GROUP_CONCAT(DISTINCT CONCAT(f.first_name, ' ', f.last_name) SEPARATOR ', ') as faculty_names,
               GROUP_CONCAT(DISTINCT f.employee_id SEPARATOR ', ') as faculty_employee_ids,
               GROUP_CONCAT(DISTINCT f.designation SEPARATOR ', ') as faculty_designations
        FROM subjects s
        LEFT JOIN departments d ON s.department_id = d.department_id
        LEFT JOIN faculty_subjects fs ON s.subject_id = fs.subject_id AND fs.is_active = 1
        LEFT JOIN faculty f ON fs.faculty_id = f.faculty_id
        LEFT JOIN enrollments e ON s.subject_id = e.subject_id AND e.status = 'enrolled'
        LEFT JOIN timetables t ON s.subject_id = t.subject_id AND t.is_active = 1
        WHERE s.subject_id = ? AND s.is_active = 1
        GROUP BY s.subject_id
    ", [$subjectId]);

    if (!$subjectDetails) {
        $response['message'] = 'Subject not found or has been deleted';
        echo json_encode($response);
        exit;
    }

    // Get assigned faculty details
    $assignedFaculty = $db->fetchAll("
        SELECT f.faculty_id,
               f.employee_id,
               f.first_name,
               f.last_name,
               f.designation,
               f.department,
               f.specialization,
               f.qualification,
               f.experience_years,
               f.office_location,
               fs.assigned_date,
               fs.max_students,
               fs.notes,
               u.email,
               u.status as user_status
        FROM faculty f
        INNER JOIN faculty_subjects fs ON f.faculty_id = fs.faculty_id
        INNER JOIN users u ON f.user_id = u.user_id
        WHERE fs.subject_id = ? AND fs.is_active = 1
        ORDER BY fs.assigned_date DESC
    ", [$subjectId]);

    // Get enrolled students summary by section
    $enrollmentSummary = $db->fetchAll("
        SELECT e.section,
               e.semester,
               e.academic_year,
               COUNT(*) as student_count,
               GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) ORDER BY s.last_name SEPARATOR ', ') as student_names
        FROM enrollments e
        INNER JOIN students s ON e.student_id = s.student_id
        WHERE e.subject_id = ? AND e.status = 'enrolled'
        GROUP BY e.section, e.semester, e.academic_year
        ORDER BY e.academic_year DESC, e.semester DESC, e.section ASC
    ", [$subjectId]);

    // Get scheduled classes details
    $scheduledClasses = $db->fetchAll("
        SELECT t.timetable_id,
               t.section,
               t.semester,
               t.academic_year,
               t.notes as class_notes,
               CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
               f.employee_id,
               c.room_number,
               c.building,
               c.capacity,
               ts.day_of_week,
               ts.start_time,
               ts.end_time,
               ts.slot_name,
               COUNT(e.enrollment_id) as enrolled_count
        FROM timetables t
        INNER JOIN faculty f ON t.faculty_id = f.faculty_id
        INNER JOIN classrooms c ON t.classroom_id = c.classroom_id
        INNER JOIN time_slots ts ON t.slot_id = ts.slot_id
        LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
                                AND t.section = e.section 
                                AND t.semester = e.semester 
                                AND t.academic_year = e.academic_year 
                                AND e.status = 'enrolled'
        WHERE t.subject_id = ? AND t.is_active = 1
        GROUP BY t.timetable_id
        ORDER BY ts.day_of_week, ts.start_time, t.section
    ", [$subjectId]);

    // Get prerequisite subjects if any
    $prerequisites = [];
    if (!empty($subjectDetails['prerequisites'])) {
        // Extract subject codes from prerequisites text (assuming comma-separated codes)
        $prereqCodes = array_map('trim', explode(',', $subjectDetails['prerequisites']));
        if (!empty($prereqCodes)) {
            $placeholders = str_repeat('?,', count($prereqCodes) - 1) . '?';
            $prerequisites = $db->fetchAll("
                SELECT subject_code, subject_name 
                FROM subjects 
                WHERE subject_code IN ($placeholders) AND is_active = 1
                ORDER BY subject_code
            ", $prereqCodes);
        }
    }

    // Get subjects that have this subject as prerequisite
    $dependentSubjects = $db->fetchAll("
        SELECT subject_code, subject_name, prerequisites
        FROM subjects 
        WHERE prerequisites LIKE ? AND is_active = 1
        ORDER BY subject_code
    ", ["%{$subjectDetails['subject_code']}%"]);

    // Calculate additional statistics
    $stats = [
        'avg_class_size' => 0,
        'total_class_hours_per_week' => 0,
        'utilization_rate' => 0
    ];

    if (!empty($scheduledClasses)) {
        $totalEnrolled = array_sum(array_column($scheduledClasses, 'enrolled_count'));
        $classCount = count($scheduledClasses);
        $stats['avg_class_size'] = $classCount > 0 ? round($totalEnrolled / $classCount, 1) : 0;
        $stats['total_class_hours_per_week'] = $classCount * $subjectDetails['duration_hours'];
        
        // Calculate utilization based on classroom capacity
        $totalCapacity = array_sum(array_column($scheduledClasses, 'capacity'));
        $stats['utilization_rate'] = $totalCapacity > 0 ? round(($totalEnrolled / $totalCapacity) * 100, 1) : 0;
    }

    // Format time slots for display
    foreach ($scheduledClasses as &$class) {
        $class['time_display'] = date('g:i A', strtotime($class['start_time'])) . ' - ' . date('g:i A', strtotime($class['end_time']));
        $class['location_display'] = $class['room_number'] . ', ' . $class['building'];
    }

    // Prepare comprehensive subject data
    $subjectData = [
        // Basic subject information
        'subject_id' => $subjectDetails['subject_id'],
        'subject_code' => $subjectDetails['subject_code'],
        'subject_name' => $subjectDetails['subject_name'],
        'department' => $subjectDetails['department'],
        'department_name' => $subjectDetails['department_name'] ?? $subjectDetails['department'],
        'department_code' => $subjectDetails['department_code'] ?? '',
        'credits' => $subjectDetails['credits'],
        'duration_hours' => $subjectDetails['duration_hours'],
        'type' => $subjectDetails['type'],
        'year_level' => $subjectDetails['year_level'],
        'semester' => $subjectDetails['semester'],
        'prerequisites' => $subjectDetails['prerequisites'],
        'description' => $subjectDetails['description'],
        'syllabus' => $subjectDetails['syllabus'] ?? null,
        'created_at' => $subjectDetails['created_at'],
        'updated_at' => $subjectDetails['updated_at'],
        
        // Statistics
        'assigned_faculty_count' => (int)$subjectDetails['assigned_faculty_count'],
        'enrolled_students_count' => (int)$subjectDetails['enrolled_students_count'],
        'scheduled_classes_count' => (int)$subjectDetails['scheduled_classes_count'],
        
        // Calculated statistics
        'avg_class_size' => $stats['avg_class_size'],
        'total_class_hours_per_week' => $stats['total_class_hours_per_week'],
        'utilization_rate' => $stats['utilization_rate'],
        
        // Faculty information
        'faculty_names' => $subjectDetails['faculty_names'],
        'assigned_faculty' => $assignedFaculty,
        
        // Enrollment information
        'enrollment_summary' => $enrollmentSummary,
        
        // Schedule information
        'scheduled_classes' => $scheduledClasses,
        
        // Prerequisites and dependencies
        'prerequisite_subjects' => $prerequisites,
        'dependent_subjects' => $dependentSubjects,
        
        // Timestamps formatted for display
        'created_at_formatted' => date('M j, Y g:i A', strtotime($subjectDetails['created_at'])),
        'updated_at_formatted' => $subjectDetails['updated_at'] ? date('M j, Y g:i A', strtotime($subjectDetails['updated_at'])) : null,
    ];

    // Success response
    $response['success'] = true;
    $response['message'] = 'Subject details retrieved successfully';
    $response['subject'] = $subjectData;

} catch (PDOException $e) {
    error_log("Database error in subject-details API: " . $e->getMessage());
    $response['message'] = 'Database error occurred while fetching subject details';
} catch (Exception $e) {
    error_log("General error in subject-details API: " . $e->getMessage());
    $response['message'] = 'An unexpected error occurred';
} finally {
    // Always return JSON response
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>