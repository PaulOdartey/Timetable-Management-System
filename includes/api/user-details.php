<?php
/**
 * User Details API
 * Timetable Management System
 * 
 * Returns detailed user information for modal display
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';

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

if (!User::isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

// Get user ID from request
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$userId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'User ID is required'
    ]);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get comprehensive user information with better error handling
    $user = $db->fetchRow("
        SELECT u.*, 
               CASE 
                   WHEN u.role = 'student' THEN CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))
                   WHEN u.role = 'faculty' THEN CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.last_name, ''))
                   WHEN u.role = 'admin' THEN CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))
                   ELSE u.username
               END as full_name,
               CASE 
                   WHEN u.role = 'student' THEN s.department
                   WHEN u.role = 'faculty' THEN f.department
                   WHEN u.role = 'admin' THEN a.department
                   ELSE NULL
               END as department,
               CASE 
                   WHEN u.role = 'student' THEN s.student_number
                   WHEN u.role = 'faculty' THEN f.employee_id
                   WHEN u.role = 'admin' THEN a.employee_id
                   ELSE NULL
               END as identifier,
               CASE 
                   WHEN u.role = 'student' THEN s.phone
                   WHEN u.role = 'faculty' THEN f.phone
                   WHEN u.role = 'admin' THEN a.phone
                   ELSE NULL
               END as phone,
               CASE 
                   WHEN u.role = 'faculty' THEN f.designation
                   WHEN u.role = 'admin' THEN a.designation
                   ELSE NULL
               END as designation,
               CASE 
                   WHEN u.role = 'student' THEN CONCAT('Year ', COALESCE(s.year_of_study, 'N/A'))
                   ELSE NULL
               END as academic_info,
               CASE 
                   WHEN u.role = 'student' THEN s.semester
                   ELSE NULL
               END as semester,
               CASE 
                   WHEN u.role = 'faculty' THEN f.specialization
                   WHEN u.role = 'admin' THEN a.bio
                   ELSE NULL
               END as additional_info,
               CASE 
                   WHEN u.role = 'faculty' THEN f.qualification
                   ELSE NULL
               END as qualification,
               CASE 
                   WHEN u.role = 'faculty' THEN f.experience_years
                   ELSE NULL
               END as experience_years,
               CASE 
                   WHEN u.role = 'admin' THEN a.office_location
                   WHEN u.role = 'faculty' THEN f.office_location
                   ELSE NULL
               END as office_location,
               CASE 
                   WHEN u.role = 'student' THEN s.date_of_birth
                   ELSE NULL
               END as date_of_birth,
               CASE 
                   WHEN u.role = 'student' THEN s.address
                   ELSE NULL
               END as address
        FROM users u
        LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
        LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
        LEFT JOIN admin_profiles a ON u.user_id = a.user_id AND u.role = 'admin'
        WHERE u.user_id = ?
    ", [$userId]);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    // Get additional statistics for the user with better error handling
    $additionalStats = [];
    
    if ($user['role'] === 'faculty') {
        // Get faculty-specific stats with null checks
        try {
            $facultyStats = $db->fetchRow("
                SELECT 
                    COUNT(DISTINCT CASE WHEN fs.is_active = 1 THEN fs.subject_id END) as assigned_subjects,
                    COUNT(DISTINCT t.timetable_id) as active_schedules,
                    COUNT(DISTINCT CASE WHEN e.status = 'enrolled' THEN e.student_id END) as total_students
                FROM faculty f
                LEFT JOIN faculty_subjects fs ON f.faculty_id = fs.faculty_id
                LEFT JOIN timetables t ON f.faculty_id = t.faculty_id
                LEFT JOIN subjects sub ON fs.subject_id = sub.subject_id
                LEFT JOIN enrollments e ON sub.subject_id = e.subject_id
                WHERE f.user_id = ?
            ", [$userId]);
            $additionalStats = $facultyStats ?: [];
        } catch (Exception $e) {
            error_log('User Details API: faculty stats error: ' . $e->getMessage());
            $additionalStats = [];
        }
        
    } elseif ($user['role'] === 'student') {
        // Get student-specific stats with null checks
        try {
            $studentStats = $db->fetchRow("
                SELECT 
                    COUNT(DISTINCT CASE WHEN e.status = 'enrolled' THEN e.subject_id END) as enrolled_subjects,
                    COUNT(DISTINCT t.timetable_id) as scheduled_classes
                FROM students s
                LEFT JOIN enrollments e ON s.student_id = e.student_id
                LEFT JOIN timetables t ON e.subject_id = t.subject_id
                WHERE s.user_id = ?
            ", [$userId]);
            $additionalStats = $studentStats ?: [];
        } catch (Exception $e) {
            error_log('User Details API: student stats error: ' . $e->getMessage());
            $additionalStats = [];
        }
        
    } elseif ($user['role'] === 'admin') {
        // Get admin-specific stats
        try {
            $adminStats = $db->fetchRow("
                SELECT 
                    (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_users,
                    (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
                    (SELECT COUNT(*) FROM timetables) as active_schedules
            ");
            $additionalStats = $adminStats ?: [];
        } catch (Exception $e) {
            error_log('User Details API: admin stats error: ' . $e->getMessage());
            $additionalStats = [];
        }
    }
    
    // Combine user data with additional stats (ensure all values are properly set)
    $responseData = array_merge($user, $additionalStats);
    
    // Convert boolean values for JSON and handle null values
    $responseData['email_verified'] = (bool)($responseData['email_verified'] ?? false);
    $responseData['full_name'] = trim($responseData['full_name'] ?? $responseData['username'] ?? '');
    
    // Handle empty full name
    if (empty($responseData['full_name']) || $responseData['full_name'] === ' ') {
        $responseData['full_name'] = $responseData['username'] ?? 'Unknown User';
    }
    
    // Ensure numeric values are properly formatted
    foreach (['assigned_subjects', 'active_schedules', 'total_students', 'enrolled_subjects', 'scheduled_classes', 'pending_users', 'active_users'] as $field) {
        if (isset($responseData[$field])) {
            $responseData[$field] = (int)$responseData[$field];
        }
    }
    
    echo json_encode([
        'success' => true,
        'user' => $responseData
    ]);
    
} catch (Exception $e) {
    error_log("User Details API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error occurred while fetching user details',
        'error' => $e->getMessage()
    ]);
}
?>