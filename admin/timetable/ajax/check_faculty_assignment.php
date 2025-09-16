<?php
/**
 * AJAX Handler: Check Faculty Assignment
 * 
 * Validates if a faculty member is assigned to teach a specific subject
 * Returns detailed information about the assignment status
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
    
    if (!$input || !isset($input['subject_id']) || !isset($input['faculty_id'])) {
        throw new Exception('Subject ID and Faculty ID are required');
    }
    
    $subjectId = (int)$input['subject_id'];
    $facultyId = (int)$input['faculty_id'];
    
    if ($subjectId <= 0 || $facultyId <= 0) {
        throw new Exception('Invalid subject or faculty ID');
    }
    
    // Initialize database connection
    $db = Database::getInstance();
    
    // Check faculty-subject assignment
    $assignment = $db->fetchOne("
        SELECT 
            fs.assignment_id,
            fs.assigned_date,
            fs.is_active,
            f.first_name,
            f.last_name,
            f.employee_id,
            f.department as faculty_department,
            f.designation,
            s.subject_code,
            s.subject_name,
            s.department as subject_department,
            u.status as user_status
        FROM faculty_subjects fs
        JOIN faculty f ON fs.faculty_id = f.faculty_id
        JOIN subjects s ON fs.subject_id = s.subject_id
        JOIN users u ON f.user_id = u.user_id
        WHERE fs.faculty_id = ? AND fs.subject_id = ?
        ORDER BY fs.assigned_date DESC
        LIMIT 1
    ", [$facultyId, $subjectId]);
    
    // Get faculty information even if not assigned
    $faculty = $db->fetchOne("
        SELECT 
            f.first_name,
            f.last_name,
            f.employee_id,
            f.department,
            f.designation,
            u.status
        FROM faculty f
        JOIN users u ON f.user_id = u.user_id
        WHERE f.faculty_id = ?
    ", [$facultyId]);
    
    // Get subject information
    $subject = $db->fetchOne("
        SELECT subject_code, subject_name, department
        FROM subjects 
        WHERE subject_id = ?
    ", [$subjectId]);
    
    if (!$faculty || !$subject) {
        throw new Exception('Faculty member or subject not found');
    }
    
    $facultyName = $faculty['first_name'] . ' ' . $faculty['last_name'];
    $subjectName = $subject['subject_code'] . ' - ' . $subject['subject_name'];
    
    // Determine assignment status
    if (!$assignment) {
        // No assignment found
        $response = [
            'success' => true,
            'is_assigned' => false,
            'status' => 'not_assigned',
            'message' => "Faculty member '{$facultyName}' is not assigned to teach '{$subjectName}'",
            'details' => [
                'faculty_name' => $facultyName,
                'employee_id' => $faculty['employee_id'],
                'faculty_department' => $faculty['department'],
                'subject_name' => $subjectName,
                'subject_department' => $subject['department'],
                'user_status' => $faculty['status']
            ],
            'recommendations' => [
                'action' => 'assign_faculty',
                'message' => 'You can assign this faculty member to the subject in the Faculty-Subject Assignments section.'
            ]
        ];
    } elseif (!$assignment['is_active']) {
        // Assignment exists but is inactive
        $response = [
            'success' => true,
            'is_assigned' => false,
            'status' => 'inactive_assignment',
            'message' => "Faculty member '{$facultyName}' was previously assigned to '{$subjectName}' but the assignment is currently inactive",
            'details' => [
                'faculty_name' => $facultyName,
                'employee_id' => $faculty['employee_id'],
                'faculty_department' => $faculty['department'],
                'subject_name' => $subjectName,
                'subject_department' => $subject['department'],
                'assigned_date' => $assignment['assigned_date'],
                'user_status' => $faculty['status']
            ],
            'recommendations' => [
                'action' => 'reactivate_assignment',
                'message' => 'You can reactivate this assignment in the Faculty-Subject Assignments section.'
            ]
        ];
    } elseif ($faculty['status'] !== 'active') {
        // Assignment is active but faculty user is not active
        $response = [
            'success' => true,
            'is_assigned' => false,
            'status' => 'inactive_faculty',
            'message' => "Faculty member '{$facultyName}' is assigned to '{$subjectName}' but their user account is {$faculty['status']}",
            'details' => [
                'faculty_name' => $facultyName,
                'employee_id' => $faculty['employee_id'],
                'faculty_department' => $faculty['department'],
                'subject_name' => $subjectName,
                'subject_department' => $subject['department'],
                'assigned_date' => $assignment['assigned_date'],
                'user_status' => $faculty['status']
            ],
            'recommendations' => [
                'action' => 'activate_faculty',
                'message' => 'Please activate the faculty member\'s user account first.'
            ]
        ];
    } else {
        // All good - faculty is assigned and active
        $response = [
            'success' => true,
            'is_assigned' => true,
            'status' => 'assigned_active',
            'message' => "Faculty member '{$facultyName}' is authorized to teach '{$subjectName}'",
            'details' => [
                'faculty_name' => $facultyName,
                'employee_id' => $faculty['employee_id'],
                'faculty_department' => $faculty['department'],
                'designation' => $faculty['designation'],
                'subject_name' => $subjectName,
                'subject_department' => $subject['department'],
                'assigned_date' => $assignment['assigned_date'],
                'user_status' => $faculty['status']
            ]
        ];
        
        // Add department consistency check
        if ($faculty['department'] !== $subject['department']) {
            $response['warnings'] = [
                'cross_department' => "Cross-department teaching: {$faculty['department']} faculty teaching {$subject['department']} subject"
            ];
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    error_log("Check Faculty Assignment Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'is_assigned' => false,
        'status' => 'error',
        'message' => 'Error checking faculty assignment: ' . $e->getMessage()
    ]);
}
?>