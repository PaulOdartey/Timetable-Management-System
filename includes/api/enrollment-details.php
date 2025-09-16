<?php
/**
 * Enrollment Details API Endpoint
 * Timetable Management System
 * 
 * Returns detailed information about a specific enrollment
 * Used by AJAX calls from the frontend
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Enrollment.php';

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Enable CORS if needed (adjust origins as needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Security checks
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }

    // Ensure user has admin role
    User::requireLogin();
    User::requireRole('admin');

    // Get enrollment ID
    $enrollmentId = $_GET['id'] ?? '';
    if (empty($enrollmentId) || !is_numeric($enrollmentId)) {
        throw new Exception('Valid enrollment ID is required');
    }

    // Initialize enrollment manager
    $enrollmentManager = new Enrollment();
    
    // Get enrollment details
    $enrollment = $enrollmentManager->getEnrollmentById((int)$enrollmentId);
    
    if (!$enrollment) {
        throw new Exception('Enrollment not found');
    }

    // Get additional student information
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $query = "SELECT 
                e.*,
                st.student_number,
                st.first_name as student_first_name,
                st.last_name as student_last_name,
                st.department as student_department,
                st.year_of_study,
                st.semester as student_semester,
                sub.subject_code,
                sub.subject_name,
                sub.credits,
                sub.department as subject_department,
                sub.duration_hours,
                sub.description as subject_description,
                u.username as enrolled_by_username,
                u.role as enrolled_by_role
              FROM enrollments e
              JOIN students st ON e.student_id = st.student_id
              JOIN subjects sub ON e.subject_id = sub.subject_id
              LEFT JOIN users u ON e.enrolled_by = u.user_id
              WHERE e.enrollment_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$enrollmentId]);
    $detailedEnrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$detailedEnrollment) {
        throw new Exception('Detailed enrollment information not found');
    }

    // Format response
    $response = [
        'success' => true,
        'enrollment' => [
            'enrollment_id' => (int)$detailedEnrollment['enrollment_id'],
            'student_id' => (int)$detailedEnrollment['student_id'],
            'subject_id' => (int)$detailedEnrollment['subject_id'],
            'section' => $detailedEnrollment['section'],
            'semester' => (int)$detailedEnrollment['semester'],
            'academic_year' => $detailedEnrollment['academic_year'],
            'enrollment_date' => $detailedEnrollment['enrollment_date'],
            'status' => $detailedEnrollment['status'],
            
            // Student information
            'student_number' => $detailedEnrollment['student_number'],
            'student_first_name' => $detailedEnrollment['student_first_name'],
            'student_last_name' => $detailedEnrollment['student_last_name'],
            'student_department' => $detailedEnrollment['student_department'],
            'year_of_study' => (int)$detailedEnrollment['year_of_study'],
            'student_semester' => (int)$detailedEnrollment['student_semester'],
            
            // Subject information
            'subject_code' => $detailedEnrollment['subject_code'],
            'subject_name' => $detailedEnrollment['subject_name'],
            'credits' => (int)$detailedEnrollment['credits'],
            'subject_department' => $detailedEnrollment['subject_department'],
            'duration_hours' => (int)$detailedEnrollment['duration_hours'],
            'subject_description' => $detailedEnrollment['subject_description'],
            
            // System information
            'enrolled_by_username' => $detailedEnrollment['enrolled_by_username'],
            'enrolled_by_role' => $detailedEnrollment['enrolled_by_role']
        ],
        'message' => 'Enrollment details retrieved successfully'
    ];

    // Log API access for audit purposes
    error_log("API Access: enrollment-details.php - User ID: " . $_SESSION['user_id'] . " - Enrollment ID: " . $enrollmentId);

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Log error
    error_log("API Error in enrollment-details.php: " . $e->getMessage());
    
    // Return error response
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode() ?: 500
    ];
    
    // Set appropriate HTTP status code
    if (strpos($e->getMessage(), 'Authentication') !== false) {
        http_response_code(401);
    } elseif (strpos($e->getMessage(), 'not found') !== false) {
        http_response_code(404);
    } elseif (strpos($e->getMessage(), 'required') !== false) {
        http_response_code(400);
    } else {
        http_response_code(500);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}
?>