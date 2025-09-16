<?php
/**
 * Timetable Management Class
 * 
 * Handles all timetable operations including creation, validation, 
 * conflict detection, and business rule enforcement.
 * 
 * @author University Timetable System
 * @version 1.0
 */

class Timetable {
    private $db;
    private $userId;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Create a new timetable entry
     * 
     * @param array $data Timetable data
     * @return array Result with success/error information
     */
    public function createTimetableEntry($data) {
        try {
            // Validate required fields
            $this->validateRequiredFields($data);
            
            // Sanitize and prepare data
            $cleanData = $this->sanitizeData($data);
            
            // Perform all validations
            $validationResult = $this->performAllValidations($cleanData);
            
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            // Insert timetable entry
            $timetableId = $this->insertTimetableEntry($cleanData);
            
            // Log the action
            $this->logTimetableAction('CREATE', $timetableId, null, $cleanData);
            
            return [
                'success' => true,
                'message' => 'Timetable entry created successfully',
                'timetable_id' => $timetableId,
                'warnings' => $validationResult['warnings'] ?? []
            ];
            
        } catch (Exception $e) {
            error_log("Timetable Creation Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'warnings' => []
            ];
        }
    }
    
    /**
     * Update an existing timetable entry
     * 
     * @param int $timetableId Timetable ID to update
     * @param array $data Updated data
     * @return array Result with success/error information
     */
    public function updateTimetableEntry($timetableId, $data) {
        try {
            // Get existing data for logging
            $existingData = $this->getTimetableById($timetableId);
            if (!$existingData) {
                throw new Exception('Timetable entry not found');
            }
            
            // Validate required fields
            $this->validateRequiredFields($data);
            
            // Sanitize and prepare data
            $cleanData = $this->sanitizeData($data);
            
            // Perform all validations (excluding current entry from conflict check)
            $validationResult = $this->performAllValidations($cleanData, $timetableId);
            
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            // Update timetable entry
            $this->updateTimetableRecord($timetableId, $cleanData);
            
            // Log the action
            $this->logTimetableAction('UPDATE', $timetableId, $existingData, $cleanData);
            
            return [
                'success' => true,
                'message' => 'Timetable entry updated successfully',
                'warnings' => $validationResult['warnings'] ?? []
            ];
            
        } catch (Exception $e) {
            error_log("Timetable Update Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'warnings' => []
            ];
        }
    }
    
    /**
     * Delete a timetable entry (soft delete)
     * 
     * @param int $timetableId Timetable ID to delete
     * @return array Result with success/error information
     */
    public function deleteTimetableEntry($timetableId) {
        try {
            // Get existing data for logging
            $existingData = $this->getTimetableById($timetableId);
            if (!$existingData) {
                throw new Exception('Timetable entry not found');
            }
            
            // Soft delete (set is_active = 0)
            $query = "UPDATE timetables SET is_active = 0, modified_by = ?, modified_at = NOW() WHERE timetable_id = ?";
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->execute([$this->userId, $timetableId]);
            
            // Log the action
            $this->logTimetableAction('DELETE', $timetableId, $existingData, null);
            
            return [
                'success' => true,
                'message' => 'Timetable entry deleted successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Timetable Delete Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'warnings' => []
            ];
        }
    }
    
    /**
     * Get all timetable entries for admin management (includes inactive)
     * 
     * @param array $filters Filtering criteria
     * @param int $page Page number
     * @param int $limit Records per page
     * @return array Timetable entries and pagination info
     */
    public function getAllTimetables($filters = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            $whereConditions = ['1=1']; // Show all timetables by default (like admin users system)
            $params = [];
            
            // Build WHERE conditions based on filters
            if (!empty($filters['academic_year'])) {
                $whereConditions[] = 't.academic_year = ?';
                $params[] = $filters['academic_year'];
            }
            
            if (!empty($filters['semester'])) {
                $whereConditions[] = 't.semester = ?';
                $params[] = $filters['semester'];
            }
            
            if (!empty($filters['department'])) {
                $whereConditions[] = 's.department = ?';
                $params[] = $filters['department'];
            }
            
            if (!empty($filters['faculty_id'])) {
                $whereConditions[] = 't.faculty_id = ?';
                $params[] = $filters['faculty_id'];
            }
            
            if (!empty($filters['subject_id'])) {
                $whereConditions[] = 't.subject_id = ?';
                $params[] = $filters['subject_id'];
            }
            
            if (!empty($filters['day_of_week'])) {
                $whereConditions[] = 'ts.day_of_week = ?';
                $params[] = $filters['day_of_week'];
            }
            
            // Add status filter if specified
            if (isset($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $whereConditions[] = 't.is_active = 1';
                } elseif ($filters['status'] === 'inactive') {
                    $whereConditions[] = 't.is_active = 0';
                }
                // If status is 'all' or empty, show both active and inactive
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Main query using timetable_details view
            $query = "
                SELECT 
                    t.timetable_id,
                    t.section,
                    t.semester,
                    t.academic_year,
                    t.notes,
                    t.created_at,
                    t.is_active,
                    s.department AS department,
                    s.subject_code,
                    s.subject_name,
                    s.credits,
                    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                    f.employee_id,
                    c.room_number,
                    c.building,
                    c.capacity,
                    ts.day_of_week,
                    ts.start_time,
                    ts.end_time,
                    ts.slot_name,
                    COUNT(e.enrollment_id) as enrolled_students
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                LEFT JOIN enrollments e ON (
                    t.subject_id = e.subject_id AND 
                    t.section = e.section AND 
                    t.academic_year = e.academic_year AND 
                    t.semester = e.semester AND 
                    e.status = 'enrolled'
                )
                WHERE {$whereClause}
                GROUP BY t.timetable_id
                ORDER BY ts.day_of_week, ts.start_time, s.subject_code
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $timetables = $this->db->fetchAll($query, $params);
            
            // Get total count for pagination
            $countQuery = "
                SELECT COUNT(DISTINCT t.timetable_id) as total
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                WHERE {$whereClause}
            ";
            
            $countParams = array_slice($params, 0, -2); // Remove LIMIT and OFFSET
            $totalResult = $this->db->fetchOne($countQuery, $countParams);
            $total = $totalResult['total'];
            
            return [
                'success' => true,
                'data' => $timetables,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get All Timetables Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving timetables: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get timetable entries with filtering and pagination
     * 
     * @param array $filters Filtering criteria
     * @param int $page Page number
     * @param int $limit Records per page
     * @return array Timetable entries and pagination info
     */
    public function getTimetables($filters = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            $whereConditions = ['t.is_active = 1'];
            $params = [];
            
            // Build WHERE conditions based on filters
            if (!empty($filters['academic_year'])) {
                $whereConditions[] = 't.academic_year = ?';
                $params[] = $filters['academic_year'];
            }
            
            if (!empty($filters['semester'])) {
                $whereConditions[] = 't.semester = ?';
                $params[] = $filters['semester'];
            }
            
            if (!empty($filters['department'])) {
                $whereConditions[] = 's.department = ?';
                $params[] = $filters['department'];
            }
            
            if (!empty($filters['faculty_id'])) {
                $whereConditions[] = 't.faculty_id = ?';
                $params[] = $filters['faculty_id'];
            }
            
            if (!empty($filters['subject_id'])) {
                $whereConditions[] = 't.subject_id = ?';
                $params[] = $filters['subject_id'];
            }
            
            if (!empty($filters['day_of_week'])) {
                $whereConditions[] = 'ts.day_of_week = ?';
                $params[] = $filters['day_of_week'];
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Main query using timetable_details view
            $query = "
                SELECT 
                    t.timetable_id,
                    t.section,
                    t.semester,
                    t.academic_year,
                    t.notes,
                    t.created_at,
                    s.department AS department,
                    s.subject_code,
                    s.subject_name,
                    s.credits,
                    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                    f.employee_id,
                    c.room_number,
                    c.building,
                    c.capacity,
                    ts.day_of_week,
                    ts.start_time,
                    ts.end_time,
                    ts.slot_name,
                    COUNT(e.enrollment_id) as enrolled_students
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                LEFT JOIN enrollments e ON (
                    t.subject_id = e.subject_id AND 
                    t.section = e.section AND 
                    t.academic_year = e.academic_year AND 
                    t.semester = e.semester AND 
                    e.status = 'enrolled'
                )
                WHERE {$whereClause}
                GROUP BY t.timetable_id
                ORDER BY ts.day_of_week, ts.start_time, s.subject_code
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $timetables = $this->db->fetchAll($query, $params);
            
            // Get total count for pagination
            $countQuery = "
                SELECT COUNT(DISTINCT t.timetable_id) as total
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                WHERE {$whereClause}
            ";
            
            $countParams = array_slice($params, 0, -2); // Remove LIMIT and OFFSET
            $totalResult = $this->db->fetchOne($countQuery, $countParams);
            $total = $totalResult['total'];
            
            return [
                'success' => true,
                'data' => $timetables,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get Timetables Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving timetables: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get a single timetable entry by ID
     * 
     * @param int $timetableId Timetable ID
     * @return array|null Timetable data or null if not found
     */
    public function getTimetableById($timetableId) {
        try {
            $query = "
                SELECT 
                    t.*,
                    s.subject_code,
                    s.subject_name,
                    s.credits,
                    s.department as subject_department,
                    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                    f.employee_id,
                    f.department as faculty_department,
                    c.room_number,
                    c.building,
                    c.capacity,
                    c.type as classroom_type,
                    ts.day_of_week,
                    ts.start_time,
                    ts.end_time,
                    ts.slot_name,
                    COUNT(e.enrollment_id) as enrolled_students
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                LEFT JOIN enrollments e ON (
                    t.subject_id = e.subject_id AND 
                    t.section = e.section AND 
                    t.academic_year = e.academic_year AND 
                    t.semester = e.semester AND 
                    e.status = 'enrolled'
                )
                WHERE t.timetable_id = ? AND t.is_active = 1
                GROUP BY t.timetable_id
            ";
            
            return $this->db->fetchOne($query, [$timetableId]);
            
        } catch (Exception $e) {
            error_log("Get Timetable By ID Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get available resources for timetable creation
     * 
     * @return array Available subjects, faculty, classrooms, and time slots
     */
    public function getAvailableResources() {
        try {
            // Get active subjects
            $subjects = $this->db->fetchAll("
                SELECT subject_id, subject_code, subject_name, department, credits, semester, year_level
                FROM subjects 
                WHERE is_active = 1 
                ORDER BY department, subject_code
            ");
            
            // Get active faculty with user status
            $faculty = $this->db->fetchAll("
                SELECT f.faculty_id, f.employee_id, f.first_name, f.last_name, 
                       f.department, f.designation, u.status as user_status
                FROM faculty f
                JOIN users u ON f.user_id = u.user_id
                WHERE u.status = 'active'
                ORDER BY f.department, f.last_name, f.first_name
            ");
            
            // Get available classrooms
            $classrooms = $this->db->fetchAll("
                SELECT classroom_id, room_number, building, capacity, type, facilities, status
                FROM classrooms 
                WHERE is_active = 1 AND status = 'available'
                ORDER BY building, room_number
            ");
            
            // Get active time slots
            $timeSlots = $this->db->fetchAll("
                SELECT slot_id, day_of_week, start_time, end_time, slot_name
                FROM time_slots 
                WHERE is_active = 1
                ORDER BY 
                    FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                    start_time
            ");
            
            // Get current academic year and semester from settings
            $academicYear = $this->db->fetchOne("
                SELECT setting_value 
                FROM system_settings 
                WHERE setting_key = 'academic_year_current'
            ");
            
            $currentSemester = $this->db->fetchOne("
                SELECT setting_value 
                FROM system_settings 
                WHERE setting_key = 'semester_current'
            ");
            
            return [
                'success' => true,
                'subjects' => $subjects,
                'faculty' => $faculty,
                'classrooms' => $classrooms,
                'time_slots' => $timeSlots,
                'current_academic_year' => $academicYear['setting_value'] ?? date('Y') . '-' . (date('Y') + 1),
                'current_semester' => (int)($currentSemester['setting_value'] ?? 1)
            ];
            
        } catch (Exception $e) {
            error_log("Get Available Resources Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving resources: ' . $e->getMessage(),
                'subjects' => [],
                'faculty' => [],
                'classrooms' => [],
                'time_slots' => [],
                'current_academic_year' => date('Y') . '-' . (date('Y') + 1),
                'current_semester' => 1
            ];
        }
    }
    
    /**
     * Get faculty members assigned to a specific subject
     * 
     * @param int $subjectId Subject ID
     * @return array Assigned faculty members
     */
    public function getFacultyBySubject($subjectId) {
        try {
            $query = "
                SELECT DISTINCT f.faculty_id, f.employee_id, f.first_name, f.last_name, 
                       f.department, f.designation
                FROM faculty f
                JOIN faculty_subjects fs ON f.faculty_id = fs.faculty_id
                JOIN users u ON f.user_id = u.user_id
                WHERE fs.subject_id = ? AND fs.is_active = 1 AND u.status = 'active'
                ORDER BY f.last_name, f.first_name
            ";
            
            return $this->db->fetchAll($query, [$subjectId]);
            
        } catch (Exception $e) {
            error_log("Get Faculty By Subject Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get timetable statistics for dashboard
     * 
     * @return array Statistics data
     */
    public function getTimetableStats() {
        try {
            // Get current academic year
            $academicYear = $this->db->fetchOne("
                SELECT setting_value 
                FROM system_settings 
                WHERE setting_key = 'academic_year_current'
            ");
            $currentYear = $academicYear['setting_value'] ?? date('Y') . '-' . (date('Y') + 1);
            
            // Total active timetable entries
            $totalEntries = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM timetables 
                WHERE is_active = 1 AND academic_year = ?
            ", [$currentYear]);
            
            // Unique subjects scheduled
            $subjectsScheduled = $this->db->fetchOne("
                SELECT COUNT(DISTINCT subject_id) as count 
                FROM timetables 
                WHERE is_active = 1 AND academic_year = ?
            ", [$currentYear]);
            
            // Faculty with active schedules
            $facultyScheduled = $this->db->fetchOne("
                SELECT COUNT(DISTINCT faculty_id) as count 
                FROM timetables 
                WHERE is_active = 1 AND academic_year = ?
            ", [$currentYear]);
            
            // Classroom utilization
            $classroomUtil = $this->db->fetchOne("
                SELECT 
                    COUNT(DISTINCT t.classroom_id) as used_classrooms,
                    (SELECT COUNT(*) FROM classrooms WHERE is_active = 1 AND status = 'available') as total_classrooms
                FROM timetables t
                WHERE t.is_active = 1 AND t.academic_year = ?
            ", [$currentYear]);
            
            $utilizationPercent = $classroomUtil['total_classrooms'] > 0 
                ? round(($classroomUtil['used_classrooms'] / $classroomUtil['total_classrooms']) * 100, 2)
                : 0;
            
            return [
                'success' => true,
                'stats' => [
                    'total_entries' => (int)$totalEntries['count'],
                    'subjects_scheduled' => (int)$subjectsScheduled['count'],
                    'faculty_scheduled' => (int)$facultyScheduled['count'],
                    'classroom_utilization' => $utilizationPercent,
                    'used_classrooms' => (int)$classroomUtil['used_classrooms'],
                    'total_classrooms' => (int)$classroomUtil['total_classrooms']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get Timetable Stats Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving statistics'
            ];
        }
    }
    
    // ===== VALIDATION METHODS =====
    
    /**
     * Perform all validations for timetable entry
     * 
     * @param array $data Timetable data
     * @param int|null $excludeTimetableId Exclude this timetable ID from conflict check
     * @return array Validation result
     */
    private function performAllValidations($data, $excludeTimetableId = null) {
        $warnings = [];
        
        try {
            // 1. Validate faculty-subject assignment
            $this->validateFacultySubjectAssignment($data['faculty_id'], $data['subject_id']);
            
            // 2. Validate resource availability
            $this->validateResourceAvailability($data['classroom_id'], $data['slot_id'], $data['subject_id'], $data['faculty_id']);
            
            // 3. Check scheduling conflicts
            $conflictResult = $this->checkSchedulingConflicts(
                $data['faculty_id'], 
                $data['classroom_id'], 
                $data['slot_id'], 
                $data['semester'], 
                $data['academic_year'], 
                $excludeTimetableId
            );
            
            if ($conflictResult['has_conflict']) {
                throw new Exception($conflictResult['message']);
            }
            
            // 4. Check classroom capacity (warning only)
            $capacityWarnings = $this->checkClassroomCapacity(
                $data['classroom_id'], 
                $data['subject_id'], 
                $data['section'], 
                $data['semester'], 
                $data['academic_year']
            );
            $warnings = array_merge($warnings, $capacityWarnings);
            
            // 5. Check department consistency (warning only)
            $deptWarning = $this->validateDepartmentConsistency($data['faculty_id'], $data['subject_id']);
            if ($deptWarning) {
                $warnings[] = $deptWarning;
            }
            
            // 6. Validate academic year format
            $this->validateAcademicYearFormat($data['academic_year']);
            
            return [
                'success' => true,
                'warnings' => $warnings
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate faculty-subject assignment
     * 
     * @param int $facultyId Faculty ID
     * @param int $subjectId Subject ID
     * @throws Exception if faculty not assigned to subject
     */
    private function validateFacultySubjectAssignment($facultyId, $subjectId) {
        $query = "
            SELECT fs.assignment_id, f.first_name, f.last_name, s.subject_code, s.subject_name
            FROM faculty_subjects fs
            JOIN faculty f ON fs.faculty_id = f.faculty_id
            JOIN subjects s ON fs.subject_id = s.subject_id
            WHERE fs.faculty_id = ? AND fs.subject_id = ? AND fs.is_active = 1
        ";
        
        $result = $this->db->fetchOne($query, [$facultyId, $subjectId]);
        
        if (!$result) {
            // Get faculty and subject names for better error message
            $faculty = $this->db->fetchOne("SELECT first_name, last_name FROM faculty WHERE faculty_id = ?", [$facultyId]);
            $subject = $this->db->fetchOne("SELECT subject_code, subject_name FROM subjects WHERE subject_id = ?", [$subjectId]);
            
            $facultyName = $faculty ? "{$faculty['first_name']} {$faculty['last_name']}" : "Faculty";
            $subjectName = $subject ? "{$subject['subject_code']} - {$subject['subject_name']}" : "Subject";
            
            throw new Exception("Faculty member '{$facultyName}' is not assigned to teach '{$subjectName}'. Please assign the faculty to this subject first.");
        }
    }
    
    /**
     * Validate resource availability
     * 
     * @param int $classroomId Classroom ID
     * @param int $slotId Time slot ID
     * @param int $subjectId Subject ID
     * @param int $facultyId Faculty ID
     * @throws Exception if any resource is not available
     */
    private function validateResourceAvailability($classroomId, $slotId, $subjectId, $facultyId) {
        // Check classroom availability
        $classroom = $this->db->fetchOne("
            SELECT room_number, building, status, is_active 
            FROM classrooms 
            WHERE classroom_id = ?
        ", [$classroomId]);
        
        if (!$classroom) {
            throw new Exception("Classroom not found");
        }
        
        if (!$classroom['is_active']) {
            throw new Exception("Classroom {$classroom['room_number']} ({$classroom['building']}) is not active");
        }
        
        if ($classroom['status'] !== 'available') {
            $status = ucfirst($classroom['status']);
            throw new Exception("Classroom {$classroom['room_number']} ({$classroom['building']}) is currently {$status}");
        }
        
        // Check time slot active
        $slot = $this->db->fetchOne("
            SELECT day_of_week, start_time, end_time, slot_name, is_active 
            FROM time_slots 
            WHERE slot_id = ?
        ", [$slotId]);
        
        if (!$slot) {
            throw new Exception("Time slot not found");
        }
        
        if (!$slot['is_active']) {
            throw new Exception("Time slot {$slot['slot_name']} ({$slot['day_of_week']} {$slot['start_time']}-{$slot['end_time']}) is not active");
        }
        
        // Check subject active
        $subject = $this->db->fetchOne("
            SELECT subject_code, subject_name, is_active 
            FROM subjects 
            WHERE subject_id = ?
        ", [$subjectId]);
        
        if (!$subject) {
            throw new Exception("Subject not found");
        }
        
        if (!$subject['is_active']) {
            throw new Exception("Subject {$subject['subject_code']} - {$subject['subject_name']} is not active");
        }
        
        // Check faculty user active
        $faculty = $this->db->fetchOne("
            SELECT f.first_name, f.last_name, u.status 
            FROM faculty f
            JOIN users u ON f.user_id = u.user_id 
            WHERE f.faculty_id = ?
        ", [$facultyId]);
        
        if (!$faculty) {
            throw new Exception("Faculty member not found");
        }
        
        if ($faculty['status'] !== 'active') {
            throw new Exception("Faculty member {$faculty['first_name']} {$faculty['last_name']} is not active");
        }
    }
    
    /**
     * Check scheduling conflicts using direct database queries
     * 
     * @param int $facultyId Faculty ID
     * @param int $classroomId Classroom ID
     * @param int $slotId Time slot ID
     * @param int $semester Semester
     * @param string $academicYear Academic year
     * @param int|null $excludeTimetableId Exclude this timetable ID
     * @return array Conflict result
     */
    public function checkSchedulingConflicts($facultyId, $classroomId, $slotId, $semester, $academicYear, $excludeTimetableId = null) {
        try {
            // Check faculty conflict
            $facultyConflictQuery = "
                SELECT t.timetable_id, s.subject_code, s.subject_name, c.room_number, c.building
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                WHERE t.faculty_id = ? 
                AND t.slot_id = ? 
                AND t.semester = ? 
                AND t.academic_year = ? 
                AND t.is_active = 1
                " . ($excludeTimetableId ? "AND t.timetable_id != ?" : "") . "
                LIMIT 1
            ";
            
            $facultyParams = [$facultyId, $slotId, $semester, $academicYear];
            if ($excludeTimetableId) {
                $facultyParams[] = $excludeTimetableId;
            }
            
            $facultyConflict = $this->db->fetchOne($facultyConflictQuery, $facultyParams);
            
            if ($facultyConflict) {
                return [
                    'has_conflict' => true,
                    'message' => "Faculty conflict: Already teaching {$facultyConflict['subject_code']} - {$facultyConflict['subject_name']} in {$facultyConflict['room_number']} ({$facultyConflict['building']}) at this time"
                ];
            }
            
            // Check classroom conflict
            $classroomConflictQuery = "
                SELECT t.timetable_id, s.subject_code, s.subject_name, 
                       CONCAT(f.first_name, ' ', f.last_name) as faculty_name
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                WHERE t.classroom_id = ? 
                AND t.slot_id = ? 
                AND t.semester = ? 
                AND t.academic_year = ? 
                AND t.is_active = 1
                " . ($excludeTimetableId ? "AND t.timetable_id != ?" : "") . "
                LIMIT 1
            ";
            
            $classroomParams = [$classroomId, $slotId, $semester, $academicYear];
            if ($excludeTimetableId) {
                $classroomParams[] = $excludeTimetableId;
            }
            
            $classroomConflict = $this->db->fetchOne($classroomConflictQuery, $classroomParams);
            
            if ($classroomConflict) {
                return [
                    'has_conflict' => true,
                    'message' => "Classroom conflict: Already booked for {$classroomConflict['subject_code']} - {$classroomConflict['subject_name']} with {$classroomConflict['faculty_name']} at this time"
                ];
            }
            
            // No conflicts found
            return [
                'has_conflict' => false,
                'message' => 'No conflicts detected'
            ];
            
        } catch (Exception $e) {
            error_log("Conflict Check Error: " . $e->getMessage());
            return [
                'has_conflict' => true,
                'message' => 'Error checking conflicts: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check classroom capacity vs enrolled students
     * 
     * @param int $classroomId Classroom ID
     * @param int $subjectId Subject ID
     * @param string $section Section
     * @param int $semester Semester
     * @param string $academicYear Academic year
     * @return array Warning messages
     */
    private function checkClassroomCapacity($classroomId, $subjectId, $section, $semester, $academicYear) {
        $warnings = [];
        
        try {
            // Get classroom capacity
            $classroom = $this->db->fetchOne("
                SELECT room_number, building, capacity 
                FROM classrooms 
                WHERE classroom_id = ?
            ", [$classroomId]);
            
            if (!$classroom) {
                return ['Warning: Could not verify classroom capacity'];
            }
            
            // Get enrolled students count
            $enrolled = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM enrollments 
                WHERE subject_id = ? AND section = ? AND semester = ? 
                AND academic_year = ? AND status = 'enrolled'
            ", [$subjectId, $section, $semester, $academicYear]);
            
            $enrolledCount = (int)$enrolled['count'];
            $capacity = (int)$classroom['capacity'];
            
            if ($enrolledCount > $capacity) {
                $warnings[] = "⚠️ CAPACITY EXCEEDED: {$enrolledCount} students enrolled but classroom {$classroom['room_number']} ({$classroom['building']}) only has capacity for {$capacity}";
            } elseif ($enrolledCount > ($capacity * 0.9)) {
                $warnings[] = "⚠️ NEAR CAPACITY: Classroom {$classroom['room_number']} is nearly full ({$enrolledCount}/{$capacity} students)";
            } elseif ($enrolledCount > ($capacity * 0.8)) {
                $warnings[] = "ℹ️ HIGH OCCUPANCY: Classroom {$classroom['room_number']} is {$enrolledCount}/{$capacity} students";
            }
            
        } catch (Exception $e) {
            $warnings[] = "Warning: Could not check classroom capacity - " . $e->getMessage();
        }
        
        return $warnings;
    }
    
    /**
     * Validate department consistency between faculty and subject
     * 
     * @param int $facultyId Faculty ID
     * @param int $subjectId Subject ID
     * @return string|null Warning message or null if consistent
     */
    private function validateDepartmentConsistency($facultyId, $subjectId) {
        try {
            $query = "
                SELECT 
                    f.department as faculty_dept, 
                    f.first_name,
                    f.last_name,
                    s.department as subject_dept,
                    s.subject_code,
                    s.subject_name
                FROM faculty f, subjects s 
                WHERE f.faculty_id = ? AND s.subject_id = ?
            ";
            
            $result = $this->db->fetchOne($query, [$facultyId, $subjectId]);
            
            if (!$result) {
                return "Warning: Could not verify department consistency";
            }
            
            if ($result['faculty_dept'] !== $result['subject_dept']) {
                return "ℹ️ CROSS-DEPARTMENT: {$result['first_name']} {$result['last_name']} from {$result['faculty_dept']} teaching {$result['subject_dept']} subject ({$result['subject_code']} - {$result['subject_name']})";
            }
            
        } catch (Exception $e) {
            return "Warning: Could not check department consistency - " . $e->getMessage();
        }
        
        return null;
    }
    
    /**
     * Validate academic year format
     * 
     * @param string $academicYear Academic year
     * @throws Exception if format is invalid
     */
    private function validateAcademicYearFormat($academicYear) {
        if (!preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
            throw new Exception("Academic year must be in YYYY-YYYY format (e.g., 2025-2026)");
        }
        
        $years = explode('-', $academicYear);
        $startYear = (int)$years[0];
        $endYear = (int)$years[1];
        
        if ($endYear !== $startYear + 1) {
            throw new Exception("Academic year end year must be exactly one year after start year");
        }
        
        // Check if year is reasonable (not too far in past or future)
        $currentYear = (int)date('Y');
        if ($startYear < ($currentYear - 5) || $startYear > ($currentYear + 5)) {
            throw new Exception("Academic year seems unreasonable. Please check the year range.");
        }
    }
    
    // ===== HELPER METHODS =====
    
    /**
     * Validate required fields
     * 
     * @param array $data Input data
     * @throws Exception if required fields are missing
     */
    private function validateRequiredFields($data) {
        $required = [
            'subject_id' => 'Subject',
            'faculty_id' => 'Faculty member',
            'classroom_id' => 'Classroom',
            'slot_id' => 'Time slot',
            'semester' => 'Semester',
            'academic_year' => 'Academic year'
        ];
        
        $missing = [];
        foreach ($required as $field => $label) {
            if (empty($data[$field])) {
                $missing[] = $label;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception('Please provide the following required fields: ' . implode(', ', $missing));
        }
    }
    
    /**
     * Sanitize input data
     * 
     * @param array $data Raw input data
     * @return array Sanitized data
     */
    private function sanitizeData($data) {
        return [
            'subject_id' => (int)($data['subject_id'] ?? 0),
            'faculty_id' => (int)($data['faculty_id'] ?? 0),
            'classroom_id' => (int)($data['classroom_id'] ?? 0),
            'slot_id' => (int)($data['slot_id'] ?? 0),
            'section' => trim($data['section'] ?? 'A'),
            'semester' => (int)($data['semester'] ?? 1),
            'academic_year' => trim($data['academic_year'] ?? ''),
            'max_students' => !empty($data['max_students']) ? (int)$data['max_students'] : null,
            'notes' => trim($data['notes'] ?? '') ?: null
        ];
    }
    
    /**
     * Insert new timetable entry into database
     * 
     * @param array $data Sanitized timetable data
     * @return int Inserted timetable ID
     */
    private function insertTimetableEntry($data) {
        $query = "
            INSERT INTO timetables (
                subject_id, faculty_id, classroom_id, slot_id, section, 
                semester, academic_year, max_students, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $params = [
            $data['subject_id'],
            $data['faculty_id'],
            $data['classroom_id'],
            $data['slot_id'],
            $data['section'],
            $data['semester'],
            $data['academic_year'],
            $data['max_students'],
            $data['notes'],
            $this->userId
        ];
        
        // Use the database instance to execute the query
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute($params);
        
        return $this->db->getConnection()->lastInsertId();
    }
    
    /**
     * Update existing timetable record
     * 
     * @param int $timetableId Timetable ID
     * @param array $data Updated data
     */
    private function updateTimetableRecord($timetableId, $data) {
        $query = "
            UPDATE timetables SET 
                subject_id = ?, faculty_id = ?, classroom_id = ?, slot_id = ?, 
                section = ?, semester = ?, academic_year = ?, max_students = ?, 
                notes = ?, modified_by = ?, modified_at = NOW()
            WHERE timetable_id = ?
        ";
        
        $params = [
            $data['subject_id'],
            $data['faculty_id'],
            $data['classroom_id'],
            $data['slot_id'],
            $data['section'],
            $data['semester'],
            $data['academic_year'],
            $data['max_students'],
            $data['notes'],
            $this->userId,
            $timetableId
        ];
        
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute($params);
    }
    
    /**
     * Log timetable actions for audit trail
     * 
     * @param string $action Action type (CREATE, UPDATE, DELETE)
     * @param int $timetableId Timetable ID
     * @param array|null $oldData Old data (for updates/deletes)
     * @param array|null $newData New data (for creates/updates)
     */
    private function logTimetableAction($action, $timetableId, $oldData = null, $newData = null) {
        try {
            // Get detailed timetable information for logging
            if ($action === 'CREATE' || $action === 'UPDATE') {
                $detailsQuery = "
                    SELECT 
                        t.*,
                        s.subject_code, s.subject_name, s.department as subject_department,
                        CONCAT(f.first_name, ' ', f.last_name) as faculty_name, f.employee_id,
                        c.room_number, c.building, c.capacity as classroom_capacity,
                        ts.day_of_week, ts.start_time, ts.end_time, ts.slot_name,
                        TIME_FORMAT(ts.start_time, '%h:%i %p') as start_time_formatted,
                        TIME_FORMAT(ts.end_time, '%h:%i %p') as end_time_formatted,
                        CONCAT(TIME_FORMAT(ts.start_time, '%h:%i %p'), ' - ', TIME_FORMAT(ts.end_time, '%h:%i %p')) as time_range
                    FROM timetables t
                    JOIN subjects s ON t.subject_id = s.subject_id
                    JOIN faculty f ON t.faculty_id = f.faculty_id
                    JOIN classrooms c ON t.classroom_id = c.classroom_id
                    JOIN time_slots ts ON t.slot_id = ts.slot_id
                    WHERE t.timetable_id = ?
                ";
                $logData = $this->db->fetchOne($detailsQuery, [$timetableId]);
            } else {
                $logData = $oldData;
            }
            
            $description = $this->generateLogDescription($action, $logData);
            
            // Insert audit log
            $auditQuery = "
                INSERT INTO audit_logs (
                    user_id, action, table_affected, record_id, 
                    old_values, new_values, description
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->db->getConnection()->prepare($auditQuery);
            $stmt->execute([
                $this->userId,
                'TIMETABLE_' . $action,
                'timetables',
                $timetableId,
                $oldData ? json_encode($oldData) : null,
                $newData ? json_encode($newData) : null,
                $description
            ]);
            
        } catch (Exception $e) {
            error_log("Audit Log Error: " . $e->getMessage());
            // Don't throw exception here as it shouldn't break the main operation
        }
    }
    
    /**
     * Generate descriptive log message
     * 
     * @param string $action Action type
     * @param array $data Timetable data
     * @return string Log description
     */
    private function generateLogDescription($action, $data) {
        if (!$data) {
            return "Timetable {$action} operation performed";
        }
        
        $subject = isset($data['subject_code']) ? "{$data['subject_code']} - {$data['subject_name']}" : "Subject";
        $faculty = isset($data['faculty_name']) ? $data['faculty_name'] : "Faculty";
        $location = isset($data['room_number']) ? "{$data['room_number']} ({$data['building']})" : "Classroom";
        $time = isset($data['time_range']) ? "{$data['day_of_week']} {$data['time_range']}" : "Time slot";
        
        switch ($action) {
            case 'CREATE':
                return "Created timetable: {$subject} taught by {$faculty} in {$location} on {$time}";
            case 'UPDATE':
                return "Updated timetable: {$subject} taught by {$faculty} in {$location} on {$time}";
            case 'DELETE':
                return "Deleted timetable: {$subject} taught by {$faculty} in {$location} on {$time}";
            default:
                return "Timetable {$action} operation performed";
        }
    }
    
    /**
     * Get filter options for timetable listing
     * 
     * @return array Available filter options
     */
    public function getFilterOptions() {
        try {
            // Get academic years
            $academicYears = $this->db->fetchAll("
                SELECT DISTINCT academic_year 
                FROM timetables 
                WHERE is_active = 1 
                ORDER BY academic_year DESC
            ");
            
            // Get semesters
            $semesters = $this->db->fetchAll("
                SELECT DISTINCT semester 
                FROM timetables 
                WHERE is_active = 1 
                ORDER BY semester
            ");
            
            // Get departments
            $departments = $this->db->fetchAll("
                SELECT DISTINCT s.department 
                FROM subjects s
                JOIN timetables t ON s.subject_id = t.subject_id
                WHERE t.is_active = 1 
                ORDER BY s.department
            ");
            
            // Get days of week
            $daysOfWeek = $this->db->fetchAll("
                SELECT DISTINCT ts.day_of_week 
                FROM time_slots ts
                JOIN timetables t ON ts.slot_id = t.slot_id
                WHERE t.is_active = 1 
                ORDER BY FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')
            ");
            
            return [
                'success' => true,
                'academic_years' => array_column($academicYears, 'academic_year'),
                'semesters' => array_column($semesters, 'semester'),
                'departments' => array_column($departments, 'department'),
                'days_of_week' => array_column($daysOfWeek, 'day_of_week')
            ];
            
        } catch (Exception $e) {
            error_log("Get Filter Options Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error retrieving filter options'
            ];
        }
    }
    
    /**
     * Export timetable data to CSV
     * 
     * @param array $filters Filtering criteria
     * @return array Export result
     */
    public function exportTimetableCSV($filters = []) {
        try {
            // Get timetable data without pagination
            $result = $this->getTimetables($filters, 1, 10000); // Large limit to get all records
            
            if (!$result['success']) {
                return $result;
            }
            
            $filename = 'timetable_export_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = 'exports/' . $filename;
            
            // Ensure exports directory exists
            if (!is_dir('exports')) {
                mkdir('exports', 0755, true);
            }
            
            $file = fopen($filepath, 'w');
            
            // CSV headers
            $headers = [
                'Subject Code', 'Subject Name', 'Faculty', 'Employee ID',
                'Day', 'Time', 'Classroom', 'Building', 'Section',
                'Semester', 'Academic Year', 'Enrolled Students', 'Capacity', 'Notes'
            ];
            fputcsv($file, $headers);
            
            // CSV data
            foreach ($result['data'] as $row) {
                $csvRow = [
                    $row['subject_code'],
                    $row['subject_name'],
                    $row['faculty_name'],
                    $row['employee_id'],
                    $row['day_of_week'],
                    date('g:i A', strtotime($row['start_time'])) . ' - ' . date('g:i A', strtotime($row['end_time'])),
                    $row['room_number'],
                    $row['building'],
                    $row['section'],
                    $row['semester'],
                    $row['academic_year'],
                    $row['enrolled_students'],
                    $row['capacity'],
                    $row['notes'] ?? ''
                ];
                fputcsv($file, $csvRow);
            }
            
            fclose($file);
            
            return [
                'success' => true,
                'message' => 'Timetable exported successfully',
                'filename' => $filename,
                'filepath' => $filepath,
                'record_count' => count($result['data'])
            ];
            
        } catch (Exception $e) {
            error_log("Export CSV Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error exporting timetable: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk delete timetable entries
     * 
     * @param array $timetableIds Array of timetable IDs to delete
     * @return array Result
     */
    public function bulkDeleteTimetables($timetableIds) {
        try {
            if (empty($timetableIds) || !is_array($timetableIds)) {
                throw new Exception('No timetable entries selected for deletion');
            }
            
            $deletedCount = 0;
            $errors = [];
            
            foreach ($timetableIds as $timetableId) {
                $result = $this->deleteTimetableEntry((int)$timetableId);
                if ($result['success']) {
                    $deletedCount++;
                } else {
                    $errors[] = "Timetable ID {$timetableId}: " . $result['message'];
                }
            }
            
            if ($deletedCount === count($timetableIds)) {
                return [
                    'success' => true,
                    'message' => "Successfully deleted {$deletedCount} timetable entries"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Deleted {$deletedCount} out of " . count($timetableIds) . " entries. Errors: " . implode('; ', $errors)
                ];
            }
            
        } catch (Exception $e) {
            error_log("Bulk Delete Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Activate a timetable entry
     * 
     * @param int $timetableId Timetable ID to activate
     * @return array Result with success/error information
     */
    public function activateTimetableEntry($timetableId) {
        try {
            // Get existing data for logging
            $existingData = $this->getTimetableByIdWithoutActiveFilter($timetableId);
            if (!$existingData) {
                throw new Exception('Timetable entry not found');
            }
            
            // Activate timetable entry
            $query = "UPDATE timetables SET is_active = 1, modified_by = ?, modified_at = NOW() WHERE timetable_id = ?";
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->execute([$this->userId, $timetableId]);
            
            // Log the action
            $this->logTimetableAction('ACTIVATE', $timetableId, $existingData, null);
            
            return [
                'success' => true,
                'message' => 'Timetable entry activated successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Timetable Activate Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Deactivate a timetable entry
     * 
     * @param int $timetableId Timetable ID to deactivate
     * @return array Result with success/error information
     */
    public function deactivateTimetableEntry($timetableId) {
        try {
            // Get existing data for logging
            $existingData = $this->getTimetableById($timetableId);
            if (!$existingData) {
                throw new Exception('Timetable entry not found');
            }
            
            // Deactivate timetable entry
            $query = "UPDATE timetables SET is_active = 0, modified_by = ?, modified_at = NOW() WHERE timetable_id = ?";
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->execute([$this->userId, $timetableId]);
            
            // Log the action
            $this->logTimetableAction('DEACTIVATE', $timetableId, $existingData, null);
            
            return [
                'success' => true,
                'message' => 'Timetable entry deactivated successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Timetable Deactivate Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get a single timetable entry by ID without active filter (for admin operations)
     * 
     * @param int $timetableId Timetable ID
     * @return array|null Timetable data or null if not found
     */
    public function getTimetableByIdWithoutActiveFilter($timetableId) {
        try {
            $query = "
                SELECT 
                    t.*,
                    s.subject_code,
                    s.subject_name,
                    s.credits,
                    s.department as subject_department,
                    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                    f.employee_id,
                    f.department as faculty_department,
                    c.room_number,
                    c.building,
                    c.capacity,
                    c.type as classroom_type,
                    ts.day_of_week,
                    ts.start_time,
                    ts.end_time,
                    ts.slot_name,
                    COUNT(e.enrollment_id) as enrolled_students
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                LEFT JOIN enrollments e ON (
                    t.subject_id = e.subject_id AND 
                    t.section = e.section AND 
                    t.academic_year = e.academic_year AND 
                    t.semester = e.semester AND 
                    e.status = 'enrolled'
                )
                WHERE t.timetable_id = ?
                GROUP BY t.timetable_id
            ";
            
            return $this->db->fetchOne($query, [$timetableId]);
            
        } catch (Exception $e) {
            error_log("Get Timetable By ID (No Filter) Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get available academic years for timetable creation
     * 
     * @return array Available academic years
     */
    public function getAcademicYears() {
        try {
            // Get existing academic years from timetables
            $existingYears = $this->db->fetchAll("
                SELECT DISTINCT academic_year 
                FROM timetables 
                WHERE is_active = 1 
                ORDER BY academic_year DESC
            ");
            
            // Get current year and generate some default options
            $currentYear = date('Y');
            $defaultYears = [];
            
            // Generate academic years (current year and next few years)
            for ($i = 0; $i < 5; $i++) {
                $startYear = $currentYear + $i;
                $endYear = $startYear + 1;
                $academicYear = $startYear . '-' . $endYear;
                $defaultYears[] = ['academic_year' => $academicYear];
            }
            
            // Merge existing and default years, remove duplicates
            $allYears = array_merge($existingYears, $defaultYears);
            $uniqueYears = [];
            $seenYears = [];
            
            foreach ($allYears as $year) {
                if (!in_array($year['academic_year'], $seenYears)) {
                    $uniqueYears[] = $year;
                    $seenYears[] = $year['academic_year'];
                }
            }
            
            // Sort by academic year descending
            usort($uniqueYears, function($a, $b) {
                return strcmp($b['academic_year'], $a['academic_year']);
            });
            
            return $uniqueYears;
            
        } catch (Exception $e) {
            error_log("Get Academic Years Error: " . $e->getMessage());
            
            // Return default years if database query fails
            $currentYear = date('Y');
            $defaultYears = [];
            
            for ($i = 0; $i < 5; $i++) {
                $startYear = $currentYear + $i;
                $endYear = $startYear + 1;
                $defaultYears[] = ['academic_year' => $startYear . '-' . $endYear];
            }
            
            return $defaultYears;
        }
    }

    /**
     * Create multiple timetable entries (bulk creation)
     * 
     * @param array $data Timetable data for bulk creation
     * @return array Result with success/error information
     */
    public function createTimetable($data) {
        try {
            // Validate required fields
            $this->validateRequiredFields($data);
            
            // Sanitize and prepare data
            $cleanData = $this->sanitizeData($data);
            
            // Perform all validations
            $validationResult = $this->performAllValidations($cleanData);
            
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            // Insert timetable entry
            $timetableId = $this->insertTimetableEntry($cleanData);
            
            // Log the action
            $this->logTimetableAction('CREATE', $timetableId, null, $cleanData);
            
            return [
                'success' => true,
                'message' => 'Timetable entry created successfully',
                'timetable_id' => $timetableId,
                'warnings' => $validationResult['warnings'] ?? []
            ];
            
        } catch (Exception $e) {
            error_log("Create Timetable Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>