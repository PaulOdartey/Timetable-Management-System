<?php
/**
 * AJAX Handler: Get Faculty by Subject
 * 
 * Returns faculty members assigned to teach a specific subject
 * Used for highlighting qualified faculty in the dropdown
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
    
    if (!$input || !isset($input['subject_id'])) {
        throw new Exception('Subject ID is required');
    }
    
    $subjectId = (int)$input['subject_id'];
    
    if ($subjectId <= 0) {
        throw new Exception('Invalid subject ID');
    }
    
    // Initialize timetable manager
    $timetableManager = new Timetable();
    
    // Get faculty assigned to this subject
    $assignedFaculty = $timetableManager->getFacultyBySubject($subjectId);
    
    // Get subject information for context
    $db = Database::getInstance();
    $subject = $db->fetchOne("
        SELECT subject_code, subject_name, department 
        FROM subjects 
        WHERE subject_id = ? AND is_active = 1
    ", [$subjectId]);
    
    if (!$subject) {
        throw new Exception('Subject not found or inactive');
    }
    
    // Prepare response data
    $response = [
        'success' => true,
        'subject' => [
            'subject_code' => $subject['subject_code'],
            'subject_name' => $subject['subject_name'],
            'department' => $subject['department']
        ],
        'assigned_faculty' => $assignedFaculty,
        'total_assigned' => count($assignedFaculty),
        'message' => count($assignedFaculty) > 0 
            ? count($assignedFaculty) . ' faculty member(s) assigned to teach this subject'
            : 'No faculty members are currently assigned to teach this subject'
    ];
    
    // Add assignment details for each faculty
    foreach ($response['assigned_faculty'] as &$faculty) {
        $faculty['display_name'] = $faculty['first_name'] . ' ' . $faculty['last_name'];
        $faculty['identifier'] = $faculty['employee_id'] . ' - ' . $faculty['department'];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    error_log("Get Faculty by Subject Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'assigned_faculty' => [],
        'total_assigned' => 0
    ]);
}
?>