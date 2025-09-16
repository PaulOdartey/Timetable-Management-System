<?php
/**
 * Subject Management Class
 * Handles all subject-related operations in the Timetable Management System
 * 
 * Features:
 * - CRUD operations for subjects
 * - Faculty assignment management
 * - Department-based filtering
 * - Subject statistics and analytics
 * - Bulk operations support
 */

defined('SYSTEM_ACCESS') or die('Direct access denied');

class Subject {
    private $db;
    private $logger;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = function_exists('getLogger') ? getLogger() : null;
    }

    /**
     * Log messages with fallback
     */
    private function log($message, $level = 'info') {
        if ($this->logger) {
            $this->logger->$level($message);
        } else {
            error_log("[Subject] [{$level}] {$message}");
        }
    }

    /**
     * Get all subjects with optional filtering
     * @param array $filters Optional filters (department, type, status, year_level)
     * @param string $search Optional search term
     * @return array
     */
    public function getAllSubjects($filters = [], $search = '') {
        try {
            // Include both active and inactive by default so admin can toggle
            $whereConditions = ['1=1'];
            $params = [];

            // Apply filters
            if (!empty($filters['department'])) {
                $whereConditions[] = "s.department = ?";
                $params[] = $filters['department'];
            }

            if (!empty($filters['type'])) {
                $whereConditions[] = "s.type = ?";
                $params[] = $filters['type'];
            }

            if (!empty($filters['year_level'])) {
                $whereConditions[] = "s.year_level = ?";
                $params[] = $filters['year_level'];
            }

            if (!empty($filters['semester'])) {
                $whereConditions[] = "s.semester = ?";
                $params[] = $filters['semester'];
            }

            // Apply search
            if (!empty($search)) {
                $whereConditions[] = "(s.subject_code LIKE ? OR s.subject_name LIKE ? OR s.description LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $whereConditions);

            $sql = "
                SELECT s.*,
                       d.department_name,
                       COUNT(DISTINCT fs.faculty_id) as assigned_faculty_count,
                       COUNT(DISTINCT e.student_id) as enrolled_students_count,
                       COUNT(DISTINCT t.timetable_id) as scheduled_classes_count,
                       GROUP_CONCAT(DISTINCT CONCAT(f.first_name, ' ', f.last_name) SEPARATOR ', ') as faculty_names
                FROM subjects s
                LEFT JOIN departments d ON s.department_id = d.department_id
                LEFT JOIN faculty_subjects fs ON s.subject_id = fs.subject_id AND fs.is_active = 1
                LEFT JOIN faculty f ON fs.faculty_id = f.faculty_id
                LEFT JOIN enrollments e ON s.subject_id = e.subject_id AND e.status = 'enrolled'
                LEFT JOIN timetables t ON s.subject_id = t.subject_id AND t.is_active = 1
                WHERE {$whereClause}
                GROUP BY s.subject_id
                ORDER BY s.subject_code ASC
            ";

            return $this->db->fetchAll($sql, $params);

        } catch (Exception $e) {
            $this->log("Error fetching subjects: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get subject by ID with comprehensive details
     * @param int $subjectId
     * @return array|null
     */
    public function getSubjectById($subjectId) {
        try {
            $sql = "
                SELECT s.*,
                       d.department_name,
                       COUNT(DISTINCT fs.faculty_id) as assigned_faculty_count,
                       COUNT(DISTINCT e.student_id) as enrolled_students_count,
                       COUNT(DISTINCT t.timetable_id) as scheduled_classes_count
                FROM subjects s
                LEFT JOIN departments d ON s.department_id = d.department_id
                LEFT JOIN faculty_subjects fs ON s.subject_id = fs.subject_id AND fs.is_active = 1
                LEFT JOIN enrollments e ON s.subject_id = e.subject_id AND e.status = 'enrolled'
                LEFT JOIN timetables t ON s.subject_id = t.subject_id AND t.is_active = 1
                WHERE s.subject_id = ?
                GROUP BY s.subject_id
            ";

            return $this->db->fetchRow($sql, [$subjectId]);

        } catch (Exception $e) {
            $this->log("Error fetching subject {$subjectId}: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Create new subject
     * @param array $data Subject data
     * @return array Result with success status and message
     */
    public function createSubject($data) {
        try {
            // Validate required fields
            $required = ['subject_code', 'subject_name', 'department', 'credits', 'duration_hours', 'semester', 'year_level'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field '{$field}' is required"];
                }
            }

            // Check for duplicate subject code
            if ($this->subjectCodeExists($data['subject_code'])) {
                return ['success' => false, 'message' => 'Subject code already exists'];
            }

            // Get department ID
            $departmentId = $this->getDepartmentIdByName($data['department']);

            $sql = "
                INSERT INTO subjects (
                    subject_code, subject_name, department_id, credits, duration_hours,
                    type, department, semester, year_level, prerequisites, description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $params = [
                $data['subject_code'],
                $data['subject_name'],
                $departmentId,
                $data['credits'],
                $data['duration_hours'],
                $data['type'] ?? 'theory',
                $data['department'],
                $data['semester'],
                $data['year_level'],
                $data['prerequisites'] ?? null,
                $data['description'] ?? null
            ];

            $result = $this->db->execute($sql, $params);

            if ($result) {
                $this->log("Subject created: " . $data['subject_code'], 'info');
                return ['success' => true, 'message' => 'Subject created successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to create subject'];
            }

        } catch (Exception $e) {
            $this->log("Error creating subject: " . $e->getMessage(), 'error');
            // Map duplicate key error to friendly message
            $msg = $e->getMessage();
            if (stripos($msg, 'SQLSTATE[23000]') !== false && stripos($msg, '1062') !== false) {
                return ['success' => false, 'message' => 'Subject code already exists'];
            }
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Update subject
     * @param int $subjectId
     * @param array $data Updated data
     * @return array Result with success status and message
     */
    public function updateSubject($subjectId, $data, $userId = null) {
        try {
            // Check if subject exists
            $existing = $this->getSubjectById($subjectId);
            if (!$existing) {
                return ['success' => false, 'message' => 'Subject not found'];
            }

            // Check for duplicate subject code (excluding current subject)
            if (!empty($data['subject_code']) && $data['subject_code'] !== $existing['subject_code']) {
                if ($this->subjectCodeExists($data['subject_code'], $subjectId)) {
                    return ['success' => false, 'message' => 'Subject code already exists'];
                }
            }

            // Get department ID if department is being updated
            $departmentId = null;
            if (!empty($data['department'])) {
                $departmentId = $this->getDepartmentIdByName($data['department']);
            }

            $sql = "
                UPDATE subjects SET
                    subject_code = COALESCE(?, subject_code),
                    subject_name = COALESCE(?, subject_name),
                    department_id = COALESCE(?, department_id),
                    credits = COALESCE(?, credits),
                    duration_hours = COALESCE(?, duration_hours),
                    type = COALESCE(?, type),
                    department = COALESCE(?, department),
                    semester = COALESCE(?, semester),
                    year_level = COALESCE(?, year_level),
                    prerequisites = ?,
                    description = ?,
                    is_active = COALESCE(?, is_active),
                    updated_at = CURRENT_TIMESTAMP
                WHERE subject_id = ?
            ";

            $params = [
                $data['subject_code'] ?? null,
                $data['subject_name'] ?? null,
                $departmentId,
                $data['credits'] ?? null,
                $data['duration_hours'] ?? null,
                $data['type'] ?? null,
                $data['department'] ?? null,
                $data['semester'] ?? null,
                $data['year_level'] ?? null,
                $data['prerequisites'] ?? null,
                $data['description'] ?? null,
                $data['is_active'] ?? null,
                $subjectId
            ];

            $result = $this->db->execute($sql, $params);

            if ($result) {
                $this->log("Subject updated: {$subjectId}", 'info');
                return ['success' => true, 'message' => ''];
            } else {
                return ['success' => false, 'message' => 'Failed to update subject'];
            }

        } catch (Exception $e) {
            $this->log("Error updating subject {$subjectId}: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Delete subject (soft delete)
     * @param int $subjectId
     * @return array Result with success status and message
     */
    public function deleteSubject($subjectId) {
        try {
            // Check if subject exists
            $subject = $this->getSubjectById($subjectId);
            if (!$subject) {
                return ['success' => false, 'message' => 'Subject not found'];
            }

            // Check if subject is being used in timetables
            $timetableResult = $this->db->fetchRow(
                "SELECT COUNT(*) as count FROM timetables WHERE subject_id = ? AND is_active = 1",
                [$subjectId]
            );
            $timetableCount = $timetableResult ? $timetableResult['count'] : 0;

            if ($timetableCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete subject: it is currently scheduled in timetables'];
            }

            // Check if subject has enrolled students
            $enrollmentResult = $this->db->fetchRow(
                "SELECT COUNT(*) as count FROM enrollments WHERE subject_id = ? AND status = 'enrolled'",
                [$subjectId]
            );
            $enrollmentCount = $enrollmentResult ? $enrollmentResult['count'] : 0;

            if ($enrollmentCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete subject: students are currently enrolled'];
            }

            // Soft delete the subject
            $sql = "UPDATE subjects SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE subject_id = ?";
            $result = $this->db->execute($sql, [$subjectId]);

            if ($result) {
                // Also deactivate faculty assignments
                $this->db->execute(
                    "UPDATE faculty_subjects SET is_active = 0 WHERE subject_id = ?",
                    [$subjectId]
                );

                $this->log("Subject deleted: {$subjectId}", 'info');
                return ['success' => true, 'message' => 'Subject deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete subject'];
            }

        } catch (Exception $e) {
            $this->log("Error deleting subject {$subjectId}: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Get subjects statistics
     * @return array
     */
    public function getSubjectsStatistics() {
        try {
            $stats = $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_subjects,
                    COUNT(CASE WHEN type = 'theory' THEN 1 END) as theory_subjects,
                    COUNT(CASE WHEN type = 'practical' THEN 1 END) as practical_subjects,
                    COUNT(CASE WHEN type = 'lab' THEN 1 END) as lab_subjects,
                    COUNT(CASE WHEN year_level = 1 THEN 1 END) as year_1_subjects,
                    COUNT(CASE WHEN year_level = 2 THEN 1 END) as year_2_subjects,
                    COUNT(CASE WHEN year_level = 3 THEN 1 END) as year_3_subjects,
                    COUNT(CASE WHEN year_level = 4 THEN 1 END) as year_4_subjects,
                    AVG(credits) as avg_credits,
                    AVG(duration_hours) as avg_duration
                FROM subjects 
                WHERE is_active = 1
            ");

            // Get subjects by department
            $departmentStats = $this->db->fetchAll("
                SELECT 
                    department,
                    COUNT(*) as subject_count,
                    SUM(credits) as total_credits
                FROM subjects 
                WHERE is_active = 1 
                GROUP BY department 
                ORDER BY subject_count DESC
            ");

            // Get faculty assignment statistics
            $facultyStats = $this->db->fetchRow("
                SELECT 
                    COUNT(DISTINCT s.subject_id) as subjects_with_faculty,
                    COUNT(DISTINCT fs.faculty_id) as assigned_faculty_count,
                    COUNT(fs.assignment_id) as total_assignments
                FROM subjects s
                LEFT JOIN faculty_subjects fs ON s.subject_id = fs.subject_id AND fs.is_active = 1
                WHERE s.is_active = 1
            ");

            // Get enrollment statistics
            $enrollmentStats = $this->db->fetchRow("
                SELECT 
                    COUNT(DISTINCT s.subject_id) as subjects_with_enrollments,
                    COUNT(e.enrollment_id) as total_enrollments,
                    AVG(enrollment_counts.student_count) as avg_students_per_subject
                FROM subjects s
                LEFT JOIN enrollments e ON s.subject_id = e.subject_id AND e.status = 'enrolled'
                LEFT JOIN (
                    SELECT subject_id, COUNT(*) as student_count 
                    FROM enrollments 
                    WHERE status = 'enrolled' 
                    GROUP BY subject_id
                ) enrollment_counts ON s.subject_id = enrollment_counts.subject_id
                WHERE s.is_active = 1
            ");

            return array_merge($stats ?: [], [
                'department_breakdown' => $departmentStats,
                'faculty_assignments' => $facultyStats ?: [],
                'enrollment_stats' => $enrollmentStats ?: []
            ]);

        } catch (Exception $e) {
            $this->log("Error fetching subjects statistics: " . $e->getMessage(), 'error');
            return [
                'total_subjects' => 0,
                'theory_subjects' => 0,
                'practical_subjects' => 0,
                'lab_subjects' => 0,
                'year_1_subjects' => 0,
                'year_2_subjects' => 0,
                'year_3_subjects' => 0,
                'year_4_subjects' => 0,
                'avg_credits' => 0,
                'avg_duration' => 0,
                'department_breakdown' => [],
                'faculty_assignments' => [],
                'enrollment_stats' => []
            ];
        }
    }

    /**
     * Assign faculty to subject
     * @param int $facultyId
     * @param int $subjectId
     * @param int $assignedBy Admin user ID
     * @param array $options Additional options (max_students, notes)
     * @return array Result with success status and message
     */
    public function assignFacultyToSubject($facultyId, $subjectId, $assignedBy, $options = []) {
        try {
            // Check if assignment already exists
            $existing = $this->db->fetchRow(
                "SELECT * FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ? AND is_active = 1",
                [$facultyId, $subjectId]
            );

            if ($existing) {
                return ['success' => false, 'message' => 'Faculty is already assigned to this subject'];
            }

            $sql = "
                INSERT INTO faculty_subjects (
                    faculty_id, subject_id, max_students, assigned_by, notes
                ) VALUES (?, ?, ?, ?, ?)
            ";

            $params = [
                $facultyId,
                $subjectId,
                $options['max_students'] ?? 60,
                $assignedBy,
                $options['notes'] ?? null
            ];

            $result = $this->db->execute($sql, $params);

            if ($result) {
                $this->log("Faculty {$facultyId} assigned to subject {$subjectId}", 'info');
                return ['success' => true, 'message' => 'Faculty assigned successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to assign faculty'];
            }

        } catch (Exception $e) {
            $this->log("Error assigning faculty to subject: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Remove faculty assignment from subject
     * @param int $facultyId
     * @param int $subjectId
     * @return array Result with success status and message
     */
    public function removeFacultyFromSubject($facultyId, $subjectId) {
        try {
            // Check if there are active timetables
            $timetableResult = $this->db->fetchRow(
                "SELECT COUNT(*) as count FROM timetables WHERE faculty_id = ? AND subject_id = ? AND is_active = 1",
                [$facultyId, $subjectId]
            );
            $timetableCount = $timetableResult ? $timetableResult['count'] : 0;

            if ($timetableCount > 0) {
                return ['success' => false, 'message' => 'Cannot remove assignment: faculty is scheduled to teach this subject'];
            }

            $sql = "UPDATE faculty_subjects SET is_active = 0 WHERE faculty_id = ? AND subject_id = ?";
            $result = $this->db->execute($sql, [$facultyId, $subjectId]);

            if ($result) {
                $this->log("Faculty {$facultyId} removed from subject {$subjectId}", 'info');
                return ['success' => true, 'message' => 'Faculty assignment removed successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to remove faculty assignment'];
            }

        } catch (Exception $e) {
            $this->log("Error removing faculty assignment: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    /**
     * Get assigned faculty for a subject
     * @param int $subjectId
     * @return array
     */
    public function getAssignedFaculty($subjectId) {
        try {
            $sql = "
                SELECT f.*, fs.assigned_date, fs.max_students, fs.notes,
                       CONCAT(f.first_name, ' ', f.last_name) as full_name
                FROM faculty f
                INNER JOIN faculty_subjects fs ON f.faculty_id = fs.faculty_id
                WHERE fs.subject_id = ? AND fs.is_active = 1
                ORDER BY fs.assigned_date DESC
            ";

            return $this->db->fetchAll($sql, [$subjectId]);

        } catch (Exception $e) {
            $this->log("Error fetching assigned faculty for subject {$subjectId}: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get available faculty for assignment (not already assigned to the subject)
     * @param int $subjectId
     * @param string $department Optional department filter
     * @return array
     */
    public function getAvailableFaculty($subjectId, $department = '') {
        try {
            $whereConditions = [
                "f.faculty_id NOT IN (
                    SELECT faculty_id FROM faculty_subjects 
                    WHERE subject_id = ? AND is_active = 1
                )"
            ];
            $params = [$subjectId];

            if (!empty($department)) {
                $whereConditions[] = "f.department = ?";
                $params[] = $department;
            }

            $sql = "
                SELECT f.*, CONCAT(f.first_name, ' ', f.last_name) as full_name,
                       u.status as user_status
                FROM faculty f
                INNER JOIN users u ON f.user_id = u.user_id
                WHERE " . implode(' AND ', $whereConditions) . "
                  AND u.status = 'active'
                ORDER BY f.first_name, f.last_name
            ";

            return $this->db->fetchAll($sql, $params);

        } catch (Exception $e) {
            $this->log("Error fetching available faculty: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Bulk operations for subjects
     * @param array $subjectIds
     * @param string $action (activate, deactivate, delete)
     * @param int $userId User performing the action
     * @return array Result with success status and message
     */
    public function bulkAction($subjectIds, $action, $userId) {
        try {
            if (empty($subjectIds)) {
                return ['success' => false, 'message' => 'No subjects selected'];
            }

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($subjectIds as $subjectId) {
                switch ($action) {
                    case 'delete':
                        $result = $this->deleteSubject($subjectId);
                        break;
                    case 'deactivate':
                        $result = $this->updateSubject($subjectId, ['is_active' => 0]);
                        break;
                    case 'activate':
                        $result = $this->updateSubject($subjectId, ['is_active' => 1]);
                        break;
                    default:
                        $result = ['success' => false, 'message' => 'Invalid action'];
                }

                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = "Subject ID {$subjectId}: " . $result['message'];
                }
            }

            $message = "Bulk operation completed. Success: {$successCount}, Errors: {$errorCount}";
            if (!empty($errors)) {
                $message .= "\nErrors: " . implode(', ', $errors);
            }

            return [
                'success' => $successCount > 0,
                'message' => $message,
                'success_count' => $successCount,
                'error_count' => $errorCount
            ];

        } catch (Exception $e) {
            $this->log("Error in bulk action: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Bulk operation failed'];
        }
    }

    /**
     * Check if subject code exists
     * @param string $subjectCode
     * @param int $excludeId Optional ID to exclude from check
     * @return bool
     */
    private function subjectCodeExists($subjectCode, $excludeId = null) {
        // Match DB unique index by checking across all rows (active and inactive)
        $sql = "SELECT COUNT(*) as count FROM subjects WHERE subject_code = ?";
        $params = [$subjectCode];

        if ($excludeId) {
            $sql .= " AND subject_id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->fetchRow($sql, $params);
        return ($result && $result['count'] > 0);
    }

    /**
     * Get department ID by name
     * @param string $departmentName
     * @return int|null
     */
    private function getDepartmentIdByName($departmentName) {
        try {
            $result = $this->db->fetchRow(
                "SELECT department_id FROM departments WHERE department_name = ? OR department_code = ? LIMIT 1",
                [$departmentName, $departmentName]
            );
            return $result ? $result['department_id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get unique departments from subjects
     * @return array
     */
    public function getDepartments() {
        try {
            return $this->db->fetchAll("
                SELECT DISTINCT department 
                FROM subjects 
                WHERE is_active = 1 AND department IS NOT NULL
                ORDER BY department ASC
            ");
        } catch (Exception $e) {
            $this->log("Error fetching departments: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get subject types
     * @return array
     */
    public function getSubjectTypes() {
        return [
            ['type' => 'theory', 'label' => 'Theory'],
            ['type' => 'practical', 'label' => 'Practical'],
            ['type' => 'lab', 'label' => 'Laboratory']
        ];
    }

    /**
     * Get year levels
     * @return array
     */
    public function getYearLevels() {
        return [
            ['level' => 1, 'label' => 'Year 1'],
            ['level' => 2, 'label' => 'Year 2'],
            ['level' => 3, 'label' => 'Year 3'],
            ['level' => 4, 'label' => 'Year 4'],
            ['level' => 5, 'label' => 'Year 5'],
            ['level' => 6, 'label' => 'Year 6']
        ];
    }
}
?>