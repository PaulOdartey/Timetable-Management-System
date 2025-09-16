<?php
/**
 * Available Faculty API Endpoint
 * Returns available faculty members for a specific subject assignment
 * 
 * This API filters faculty based on:
 * - Subject department (prefers same department)
 * - Faculty not already assigned to the subject
 * - Active faculty status
 * - User account status
 */

// Security check
defined('SYSTEM_ACCESS') or define('SYSTEM_ACCESS', true);

// Start session for authentication
session_start();

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Include required files
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/User.php';

// Response function
function sendResponse($success, $message = '', $data = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Error handler
function handleError($message, $error = null) {
    error_log("Available Faculty API Error: " . $message . ($error ? " - " . $error : ""));
    sendResponse(false, $message);
}

try {
    // Check authentication
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        sendResponse(false, 'Authentication required');
    }

    // Check admin role
    if ($_SESSION['role'] !== 'admin') {
        sendResponse(false, 'Insufficient privileges');
    }

    // Validate subject_id parameter
    $subjectId = $_GET['subject_id'] ?? '';
    
    if (empty($subjectId) || !is_numeric($subjectId)) {
        sendResponse(false, 'Valid subject ID is required');
    }

    $subjectId = (int)$subjectId;

    // Initialize database
    $db = Database::getInstance();

    // Get subject details first
    $subject = $db->fetchRow("
        SELECT subject_id, subject_code, subject_name, department, department_id, 
               year_level, semester, type, credits
        FROM subjects 
        WHERE subject_id = ? AND is_active = 1
    ", [$subjectId]);

    if (!$subject) {
        sendResponse(false, 'Subject not found or inactive');
    }

    // Get available faculty with comprehensive details
    // Priority: Same department faculty first, then others
    $availableFaculty = $db->fetchAll("
        SELECT f.faculty_id,
               f.employee_id,
               f.first_name,
               f.last_name,
               CONCAT(f.first_name, ' ', f.last_name) as full_name,
               f.department,
               f.designation,
               f.phone,
               f.specialization,
               f.qualification,
               f.experience_years,
               f.office_location,
               f.date_joined,
               u.email,
               u.last_login,
               u.status as user_status,
               CASE 
                   WHEN f.department = ? THEN 1 
                   ELSE 2 
               END as department_priority,
               -- Count current assignments
               (SELECT COUNT(*) 
                FROM faculty_subjects fs 
                WHERE fs.faculty_id = f.faculty_id AND fs.is_active = 1
               ) as current_assignments,
               -- Count current teaching load
               (SELECT COUNT(DISTINCT t.timetable_id) 
                FROM timetables t 
                INNER JOIN faculty_subjects fs ON t.subject_id = fs.subject_id AND t.faculty_id = fs.faculty_id
                WHERE fs.faculty_id = f.faculty_id AND t.is_active = 1 AND fs.is_active = 1
               ) as teaching_load
        FROM faculty f
        INNER JOIN users u ON f.user_id = u.user_id
        WHERE u.status = 'active'
          AND f.faculty_id NOT IN (
              SELECT fs.faculty_id 
              FROM faculty_subjects fs 
              WHERE fs.subject_id = ? AND fs.is_active = 1
          )
        ORDER BY department_priority ASC, 
                 f.department ASC, 
                 current_assignments ASC,
                 f.first_name ASC, 
                 f.last_name ASC
    ", [$subject['department'], $subjectId]);

    // Get assignment statistics for additional context
    $assignmentStats = $db->fetchRow("
        SELECT 
            COUNT(DISTINCT f.faculty_id) as total_available,
            COUNT(DISTINCT CASE WHEN f.department = ? THEN f.faculty_id END) as same_department_count,
            AVG(current_load.assignment_count) as avg_assignments
        FROM faculty f
        INNER JOIN users u ON f.user_id = u.user_id
        LEFT JOIN (
            SELECT faculty_id, COUNT(*) as assignment_count
            FROM faculty_subjects 
            WHERE is_active = 1 
            GROUP BY faculty_id
        ) current_load ON f.faculty_id = current_load.faculty_id
        WHERE u.status = 'active'
          AND f.faculty_id NOT IN (
              SELECT fs.faculty_id 
              FROM faculty_subjects fs 
              WHERE fs.subject_id = ? AND fs.is_active = 1
          )
    ", [$subject['department'], $subjectId]);

    // Get currently assigned faculty for context
    $assignedFaculty = $db->fetchAll("
        SELECT f.faculty_id,
               CONCAT(f.first_name, ' ', f.last_name) as full_name,
               f.department,
               f.designation,
               fs.assigned_date,
               fs.max_students
        FROM faculty f
        INNER JOIN faculty_subjects fs ON f.faculty_id = fs.faculty_id
        WHERE fs.subject_id = ? AND fs.is_active = 1
        ORDER BY fs.assigned_date DESC
    ", [$subjectId]);

    // Enhance faculty data with additional context
    $enhancedFaculty = array_map(function($faculty) use ($subject) {
        // Calculate compatibility score
        $compatibilityScore = 0;
        
        // Same department bonus
        if ($faculty['department'] === $subject['department']) {
            $compatibilityScore += 10;
        }
        
        // Experience bonus
        if ($faculty['experience_years']) {
            $compatibilityScore += min($faculty['experience_years'], 10);
        }
        
        // Lower teaching load bonus
        $compatibilityScore += max(0, 10 - $faculty['current_assignments']);
        
        // Specialization match (basic keyword matching)
        if ($faculty['specialization'] && $subject['subject_name']) {
            $subjectWords = explode(' ', strtolower($subject['subject_name']));
            $specializationWords = explode(' ', strtolower($faculty['specialization']));
            
            foreach ($subjectWords as $word) {
                if (strlen($word) > 3) { // Only check meaningful words
                    foreach ($specializationWords as $specWord) {
                        if (strpos($specWord, $word) !== false || strpos($word, $specWord) !== false) {
                            $compatibilityScore += 5;
                            break;
                        }
                    }
                }
            }
        }
        
        return array_merge($faculty, [
            'compatibility_score' => $compatibilityScore,
            'department_match' => $faculty['department'] === $subject['department'],
            'workload_status' => $faculty['current_assignments'] < 3 ? 'light' : 
                                ($faculty['current_assignments'] < 6 ? 'moderate' : 'heavy'),
            'experience_level' => $faculty['experience_years'] ? 
                                 ($faculty['experience_years'] < 2 ? 'junior' : 
                                  ($faculty['experience_years'] < 8 ? 'mid' : 'senior')) : 'unknown'
        ]);
    }, $availableFaculty);

    // Sort by compatibility score (highest first)
    usort($enhancedFaculty, function($a, $b) {
        return $b['compatibility_score'] <=> $a['compatibility_score'];
    });

    // Prepare response data
    $responseData = [
        'faculty' => $enhancedFaculty,
        'subject' => $subject,
        'statistics' => [
            'total_available' => (int)($assignmentStats['total_available'] ?? 0),
            'same_department' => (int)($assignmentStats['same_department_count'] ?? 0),
            'different_department' => (int)($assignmentStats['total_available'] ?? 0) - (int)($assignmentStats['same_department_count'] ?? 0),
            'average_assignments' => round($assignmentStats['avg_assignments'] ?? 0, 1)
        ],
        'currently_assigned' => $assignedFaculty,
        'recommendations' => []
    ];

    // Add recommendations based on compatibility scores
    if (!empty($enhancedFaculty)) {
        $topFaculty = array_slice($enhancedFaculty, 0, 3);
        foreach ($topFaculty as $faculty) {
            $reasons = [];
            
            if ($faculty['department_match']) {
                $reasons[] = "Same department ({$faculty['department']})";
            }
            
            if ($faculty['experience_years'] && $faculty['experience_years'] >= 5) {
                $reasons[] = "Experienced ({$faculty['experience_years']} years)";
            }
            
            if ($faculty['workload_status'] === 'light') {
                $reasons[] = "Light teaching load ({$faculty['current_assignments']} subjects)";
            }
            
            if ($faculty['specialization']) {
                $reasons[] = "Relevant specialization";
            }
            
            $responseData['recommendations'][] = [
                'faculty_id' => $faculty['faculty_id'],
                'name' => $faculty['full_name'],
                'score' => $faculty['compatibility_score'],
                'reasons' => $reasons
            ];
        }
    }

    // Log successful API call
    error_log("Available Faculty API: Successfully loaded " . count($enhancedFaculty) . " available faculty for subject {$subjectId}");

    // Send successful response
    sendResponse(true, 'Faculty data loaded successfully', $responseData);

} catch (PDOException $e) {
    handleError('Database error occurred', $e->getMessage());
} catch (Exception $e) {
    handleError('An unexpected error occurred', $e->getMessage());
}
?>