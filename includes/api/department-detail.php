<?php
/**
 * Department Detail API
 * Provides detailed department information for AJAX requests
 * Timetable Management System
 */

// Ensure system access flag is defined before security check
if (!defined('SYSTEM_ACCESS')) {
    define('SYSTEM_ACCESS', true);
}

// Security check
defined('SYSTEM_ACCESS') or die('Direct access denied');

// Include required classes
// Start output buffering as early as possible to capture any accidental output from includes
if (!ob_get_level()) {
    ob_start();
}
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Department.php';

// Set JSON response and no-cache headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic request timing for diagnostics
$__api_start = microtime(true);

// Log entry (only minimal info to avoid noise)
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("Department Detail API: request start id=" . ($_GET['id'] ?? $_POST['id'] ?? 'n/a'));
}

// Start output buffering to prevent stray output breaking JSON
if (!ob_get_level()) {
    ob_start();
}

// Initialize PDO connection for direct queries used below
// database.php exposes getConnection() which returns PDO
/** @var PDO $pdo */
$pdo = getConnection();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    if (ob_get_length()) { ob_clean(); }
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit();
}

// Get department ID from request
$departmentId = $_GET['id'] ?? $_POST['id'] ?? null;

if (!$departmentId || !is_numeric($departmentId)) {
    http_response_code(400);
    if (ob_get_length()) { ob_clean(); }
    echo json_encode([
        'success' => false,
        'error' => 'Invalid department ID provided'
    ]);
    exit();
}

try {
    // Initialize department manager
    $departmentManager = new Department();
    
    // Get comprehensive department information
    $department = $departmentManager->getDepartmentById($departmentId);
    
    if (!$department) {
        http_response_code(404);
        if (ob_get_length()) { ob_clean(); }
        echo json_encode([
            'success' => false,
            'error' => 'Department not found'
        ]);
        exit();
    }
    
    // Get additional department statistics and details (safe defaults if methods not available)
    $overview = [];
    $dependencies = [
        'has_dependencies' => false,
        'details' => []
    ];
    
    // Get detailed faculty information for this department
    $facultyQuery = "SELECT 
                        f.faculty_id,
                        f.employee_id,
                        CONCAT(f.first_name, ' ', f.last_name) as full_name,
                        f.designation,
                        f.specialization,
                        f.experience_years,
                        f.phone,
                        u.email,
                        u.status,
                        CASE WHEN d.department_head_id = f.faculty_id THEN 1 ELSE 0 END as is_head
                     FROM faculty f
                     JOIN users u ON f.user_id = u.user_id
                     LEFT JOIN departments d ON d.department_head_id = f.faculty_id
                     WHERE f.department = :dept_name
                     ORDER BY is_head DESC, f.first_name, f.last_name";
    
    $stmt = $pdo->prepare($facultyQuery);
    $stmt->execute(['dept_name' => $department['department_name']]);
    $faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed student information for this department
    $studentQuery = "SELECT 
                        s.student_id,
                        s.student_number,
                        CONCAT(s.first_name, ' ', s.last_name) as full_name,
                        s.year_of_study,
                        s.semester,
                        s.phone,
                        u.email,
                        u.status,
                        COUNT(e.enrollment_id) as enrolled_subjects
                     FROM students s
                     JOIN users u ON s.user_id = u.user_id
                     LEFT JOIN enrollments e ON s.student_id = e.student_id AND e.status = 'enrolled'
                     WHERE s.department = :dept_name
                     GROUP BY s.student_id
                     ORDER BY s.year_of_study, s.semester, s.first_name, s.last_name";
    
    $stmt = $pdo->prepare($studentQuery);
    $stmt->execute(['dept_name' => $department['department_name']]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get subjects offered by this department
    $subjectQuery = "SELECT 
                        s.subject_id,
                        s.subject_code,
                        s.subject_name,
                        s.credits,
                        s.duration_hours,
                        s.type,
                        s.semester,
                        s.year_level,
                        s.is_active,
                        COUNT(DISTINCT e.student_id) as enrolled_students,
                        COUNT(DISTINCT fs.faculty_id) as assigned_faculty
                     FROM subjects s
                     LEFT JOIN enrollments e ON s.subject_id = e.subject_id AND e.status = 'enrolled'
                     LEFT JOIN faculty_subjects fs ON s.subject_id = fs.subject_id AND fs.is_active = 1
                     WHERE s.department_id = :dept_id
                     GROUP BY s.subject_id
                     ORDER BY s.year_level, s.semester, s.subject_code";
    
    $stmt = $pdo->prepare($subjectQuery);
    $stmt->execute(['dept_id' => $departmentId]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get classrooms owned by this department
    // Note: join with existing `timetables` table (schema verified) instead of non-existent `timetable_schedule`
    $classroomQuery = "SELECT 
                          c.classroom_id,
                          c.room_number,
                          c.building,
                          c.capacity,
                          c.type,
                          c.facilities,
                          c.is_active,
                          COUNT(DISTINCT t.timetable_id) as current_schedules
                       FROM classrooms c
                       LEFT JOIN timetables t ON c.classroom_id = t.classroom_id AND t.is_active = 1
                       WHERE c.department_id = :dept_id
                       GROUP BY c.classroom_id
                       ORDER BY c.building, c.room_number";
    
    $stmt = $pdo->prepare($classroomQuery);
    $stmt->execute(['dept_id' => $departmentId]);
    $classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent activities/updates for this department
    $activityQuery = "SELECT 
                         'enrollment' as type,
                         CONCAT(st.first_name, ' ', st.last_name, ' enrolled in ', sub.subject_name) as description,
                         e.enrollment_date as created_at
                      FROM enrollments e
                      JOIN students st ON e.student_id = st.student_id
                      JOIN subjects sub ON e.subject_id = sub.subject_id
                      WHERE st.department = :dept_name
                      
                      UNION ALL
                      
                      SELECT 
                         'subject' as type,
                         CONCAT('Subject ', s.subject_code, ' - ', s.subject_name, ' updated') as description,
                         s.updated_at as created_at
                      FROM subjects s
                      WHERE s.department_id = :dept_id AND s.updated_at > s.created_at
                      
                      ORDER BY created_at DESC
                      LIMIT 10";
    
    $stmt = $pdo->prepare($activityQuery);
    $stmt->execute([
        'dept_name' => $department['department_name'],
        'dept_id' => $departmentId
    ]);
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compute statistics
    $statistics = [
        'total_faculty' => count($faculty),
        'active_faculty' => count(array_filter($faculty, fn($f) => ($f['status'] ?? null) === 'active')),
        'total_students' => count($students),
        'active_students' => count(array_filter($students, fn($s) => ($s['status'] ?? null) === 'active')),
        'total_subjects' => count($subjects),
        'active_subjects' => count(array_filter($subjects, fn($s) => !empty($s['is_active']))),
        'total_classrooms' => count($classrooms),
        'active_classrooms' => count(array_filter($classrooms, fn($c) => !empty($c['is_active']))),
        'total_enrollments' => (int) array_sum(array_map(fn($s) => (int)($s['enrolled_students'] ?? 0), $subjects)),
    ];

    // Map department fields to what the frontend expects and embed key stats
    $departmentOut = $department;
    $departmentOut['department_head_name'] = $department['head_name'] ?? ($department['department_head_name'] ?? null);
    $departmentOut['head_employee_id'] = $department['head_employee_id'] ?? null;
    $departmentOut['total_users'] = $statistics['total_faculty'] + $statistics['total_students'];
    $departmentOut['active_faculty'] = $statistics['active_faculty'];
    $departmentOut['active_students'] = $statistics['active_students'];
    $departmentOut['total_subjects'] = $statistics['total_subjects'];

    // Build response in the structure expected by the frontend modal
    $response = [
        'success' => true,
        'department' => $departmentOut,
        // Provide additional data for possible future UI expansions
        'overview' => $overview,
        'dependencies' => $dependencies,
        'statistics' => $statistics,
        'faculty' => $faculty,
        'students' => array_slice($students, 0, 20), // Limit to first 20 for performance
        'subjects' => $subjects,
        'classrooms' => $classrooms,
        'recent_activities' => $recentActivities,
    ];

    // Add pagination info for students if there are more than 20
    if (count($students) > 20) {
        $response['students_pagination'] = [
            'total' => count($students),
            'shown' => 20,
            'has_more' => true
        ];
    }

    // Add performance metrics
    $response['performance'] = [
        'query_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
        'memory_usage' => memory_get_peak_usage(true)
    ];

    // Ensure no stray output before JSON
    if (ob_get_length()) { ob_end_clean(); ob_start(); }
    echo json_encode($response, JSON_PRETTY_PRINT);
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $dur = round((microtime(true) - $__api_start) * 1000);
        error_log("Department Detail API: success id={$departmentId} in {$dur}ms");
    }
    exit();
    
} catch (PDOException $e) {
    http_response_code(500);
    if (ob_get_length()) { ob_end_clean(); ob_start(); }
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'details' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Internal server error'
    ]);
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $dur = round((microtime(true) - $__api_start) * 1000);
        error_log("Department Detail API: db error id=" . ($departmentId ?? 'n/a') . " in {$dur}ms: " . $e->getMessage());
    }
    exit();
    
} catch (Exception $e) {
    http_response_code(500);
    if (ob_get_length()) { ob_end_clean(); ob_start(); }
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching department details',
        'details' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Internal server error'
    ]);
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $dur = round((microtime(true) - $__api_start) * 1000);
        error_log("Department Detail API: error id=" . ($departmentId ?? 'n/a') . " in {$dur}ms: " . $e->getMessage());
    }
    exit();
}