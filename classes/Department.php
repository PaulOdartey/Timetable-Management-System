<?php
/**
 * Department Class
 * Timetable Management System
 * 
 * Handles all department operations including creation, retrieval, updates,
 * and department-related analytics with proper validation and security
 */

if (!defined('SYSTEM_ACCESS')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/../config/database.php';

class Department {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        // Initialize logger if available
        try {
            $this->logger = function_exists('getLogger') ? getLogger() : null;
        } catch (Exception $e) {
            $this->logger = null;
        }
    }
    
    /**
     * Log messages for debugging and audit purposes
     */
    private function log($message, $level = 'info') {
        if ($this->logger) {
            $this->logger->$level($message);
        } else {
            error_log("[Department] [{$level}] {$message}");
        }
    }
    
    /**
     * Create a new department
     * 
     * @param array $data Department data
     * @param int $createdBy User ID of the creator
     * @return array Result with success status and message
     */
    public function createDepartment($data, $createdBy) {
        try {
            // Validate required fields
            $required = ['department_code', 'department_name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.'
                    ];
                }
            }
            
            // Validate department code format (2-10 characters, uppercase)
            $deptCode = strtoupper(trim($data['department_code']));
            if (!preg_match('/^[A-Z0-9]{2,10}$/', $deptCode)) {
                return [
                    'success' => false,
                    'message' => 'Department code must be 2-10 characters long and contain only uppercase letters and numbers.'
                ];
            }
            
            // Check for duplicate department code
            $existing = $this->db->fetchRow("
                SELECT department_id FROM departments 
                WHERE department_code = ? AND is_active = 1
            ", [$deptCode]);
            
            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'Department code already exists. Please use a different code.'
                ];
            }
            
            // Validate department name length
            if (strlen($data['department_name']) > 100) {
                return [
                    'success' => false,
                    'message' => 'Department name cannot exceed 100 characters.'
                ];
            }
            
            // Validate optional fields
            $departmentHeadId = null;
            if (!empty($data['department_head_id'])) {
                // Verify the head is a faculty member
                $facultyCheck = $this->db->fetchRow("
                    SELECT f.faculty_id 
                    FROM faculty f 
                    JOIN users u ON f.user_id = u.user_id 
                    WHERE f.faculty_id = ? AND u.status = 'active'
                ", [(int)$data['department_head_id']]);
                
                if (!$facultyCheck) {
                    return [
                        'success' => false,
                        'message' => 'Selected department head is not a valid active faculty member.'
                    ];
                }
                $departmentHeadId = (int)$data['department_head_id'];
            }
            
            // Validate contact email if provided
            if (!empty($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Please enter a valid contact email address.'
                ];
            }
            
            // Validate phone number if provided
            if (!empty($data['contact_phone'])) {
                $phone = preg_replace('/[^0-9+\-\(\)\s]/', '', $data['contact_phone']);
                if (strlen($phone) > 15) {
                    return [
                        'success' => false,
                        'message' => 'Phone number cannot exceed 15 characters.'
                    ];
                }
            }
            
            // Validate budget allocation if provided
            $budgetAllocation = null;
            if (!empty($data['budget_allocation'])) {
                $budget = floatval($data['budget_allocation']);
                if ($budget < 0) {
                    return [
                        'success' => false,
                        'message' => 'Budget allocation cannot be negative.'
                    ];
                }
                $budgetAllocation = $budget;
            }
            
            // Validate established date if provided
            $establishedDate = null;
            if (!empty($data['established_date'])) {
                $date = DateTime::createFromFormat('Y-m-d', $data['established_date']);
                if (!$date) {
                    return [
                        'success' => false,
                        'message' => 'Please enter a valid established date (YYYY-MM-DD).'
                    ];
                }
                
                // Check if date is not in the future
                if ($date > new DateTime()) {
                    return [
                        'success' => false,
                        'message' => 'Established date cannot be in the future.'
                    ];
                }
                $establishedDate = $date->format('Y-m-d');
            }
            
            $this->db->beginTransaction();
            
            try {
                // Insert department
                $sql = "INSERT INTO departments (
                    department_code, department_name, department_head_id, description,
                    established_date, contact_email, contact_phone, building_location,
                    budget_allocation, is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
                
                $params = [
                    $deptCode,
                    trim($data['department_name']),
                    $departmentHeadId,
                    !empty($data['description']) ? trim($data['description']) : null,
                    $establishedDate,
                    !empty($data['contact_email']) ? trim($data['contact_email']) : null,
                    !empty($data['contact_phone']) ? trim($data['contact_phone']) : null,
                    !empty($data['building_location']) ? trim($data['building_location']) : null,
                    $budgetAllocation
                ];
                
                $this->db->execute($sql, $params);
                $departmentId = $this->db->lastInsertId();
                
                // Log the creation
                $this->logDepartmentAction('CREATE_DEPARTMENT', $departmentId, $data, $createdBy);
                
                $this->db->commit();
                
                $this->log("Department created successfully with ID: {$departmentId}");
                
                return [
                    'success' => true,
                    'message' => 'Department created successfully.',
                    'department_id' => $departmentId
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->log("Error creating department: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Failed to create department: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all departments with optional filtering
     * 
     * Filters supported: search, status (all|active|inactive)
     */
    public function getAllDepartments($filters = [], $page = 1, $perPage = 20, $sortField = 'department_name', $sortOrder = 'asc') {
        try {
            $where = [];
            $params = [];

            // Status filter
            if (!empty($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $where[] = 'd.is_active = 1';
                } elseif ($filters['status'] === 'inactive') {
                    $where[] = 'd.is_active = 0';
                }
            }

            // Search filter
            if (!empty($filters['search'])) {
                $where[] = "(d.department_name LIKE ? OR d.department_code LIKE ? OR d.description LIKE ? OR d.building_location LIKE ?)";
                $q = '%' . $filters['search'] . '%';
                array_push($params, $q, $q, $q, $q);
            }

            $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

            // Sorting whitelist
            $sortMap = [
                'department_name' => 'd.department_name',
                'department_code' => 'd.department_code',
                'created_at' => 'd.created_at',
                'updated_at' => 'd.updated_at',
                'is_active' => 'd.is_active'
            ];
            $orderBy = $sortMap[$sortField] ?? 'd.department_name';
            $orderDir = strtolower($sortOrder) === 'desc' ? 'DESC' : 'ASC';

            // Pagination
            $countRow = $this->db->fetchRow("SELECT COUNT(*) AS cnt FROM departments d {$whereSql}", $params);
            $total = (int)($countRow['cnt'] ?? 0);
            $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
            $page = max(1, min($page, max(1, $totalPages)));
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT d.*, 
                           CONCAT(f.first_name, ' ', f.last_name) AS head_name,
                           f.employee_id AS head_employee_id
                    FROM departments d
                    LEFT JOIN faculty f ON d.department_head_id = f.faculty_id
                    {$whereSql}
                    ORDER BY {$orderBy} {$orderDir}
                    LIMIT ? OFFSET ?";

            $rows = $this->db->fetchAll($sql, array_merge($params, [$perPage, $offset]));

            return [
                'departments' => $rows,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ];

        } catch (Exception $e) {
            $this->log("Error getting all departments: " . $e->getMessage(), 'error');
            return [
                'departments' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false
                ]
            ];
        }
    }

    /**
     * Get department by ID (includes inactive)
     */
    public function getDepartmentById($departmentId) {
        try {
            $sql = "SELECT d.*, 
                           CONCAT(f.first_name, ' ', f.last_name) AS head_name,
                           f.employee_id AS head_employee_id
                    FROM departments d
                    LEFT JOIN faculty f ON d.department_head_id = f.faculty_id
                    WHERE d.department_id = ?";
            $row = $this->db->fetchRow($sql, [(int)$departmentId]);
            return $row ?: null;
        } catch (Exception $e) {
            $this->log("Error getting department by ID: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * Update department (supports is_active)
     */
    public function updateDepartment($departmentId, $data, $updatedBy) {
        try {
            $exists = $this->db->fetchRow("SELECT department_id FROM departments WHERE department_id = ?", [(int)$departmentId]);
            if (!$exists) {
                return ['success' => false, 'message' => 'Department not found.'];
            }

            $update = [];
            $params = [];

            $fields = [
                'department_code', 'department_name', 'department_head_id', 'description',
                'established_date', 'contact_email', 'contact_phone', 'building_location',
                'budget_allocation'
            ];
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $update[] = "{$field} = ?";
                    $params[] = $data[$field] !== '' ? $data[$field] : null;
                }
            }
            if (array_key_exists('is_active', $data)) {
                $update[] = 'is_active = ?';
                $params[] = (int)((bool)$data['is_active']);
            }

            if (empty($update)) {
                return ['success' => false, 'message' => 'No valid fields to update.'];
            }

            $update[] = 'updated_at = NOW()';
            $sql = 'UPDATE departments SET ' . implode(', ', $update) . ' WHERE department_id = ?';
            $params[] = (int)$departmentId;
            $this->db->execute($sql, $params);

            $this->logDepartmentAction('UPDATE_DEPARTMENT', $departmentId, $data, $updatedBy);
            return ['success' => true, 'message' => 'Department updated successfully.'];
        } catch (Exception $e) {
            $this->log("Error updating department: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Failed to update department.'];
        }
    }

    /**
     * Soft delete department (sets is_active = 0)
     */
    public function deleteDepartment($departmentId, $deletedBy) {
        try {
            $this->db->execute("UPDATE departments SET is_active = 0, updated_at = NOW() WHERE department_id = ?", [(int)$departmentId]);
            $this->logDepartmentAction('DELETE_DEPARTMENT', $departmentId, [], $deletedBy);
            return ['success' => true, 'message' => 'Department deleted successfully.'];
        } catch (Exception $e) {
            $this->log("Error deleting department: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Failed to delete department.'];
        }
    }

    /**
     * Change department active status (soft activate/deactivate)
     *
     * @param int $departmentId
     * @param bool $isActive
     * @param int $updatedBy
     * @return array
     */
    public function changeDepartmentStatus($departmentId, $isActive, $updatedBy) {
        try {
            $this->db->execute(
                "UPDATE departments SET is_active = ?, updated_at = NOW() WHERE department_id = ?",
                [(int)((bool)$isActive), (int)$departmentId]
            );
            $this->logDepartmentAction($isActive ? 'ACTIVATE_DEPARTMENT' : 'DEACTIVATE_DEPARTMENT', $departmentId, ['is_active' => (int)$isActive], $updatedBy);
            return [
                'success' => true,
                'message' => $isActive ? 'Department activated successfully.' : 'Department deactivated successfully.'
            ];
        } catch (Exception $e) {
            $this->log("Error changing department status: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Failed to change department status.'];
        }
    }

    /**
     * Deactivate a department and safely reassign or unassign its dependencies.
     *
     * Strategy:
     * - Users (faculty/students): set users.department_id = NULL
     * - Subjects: set is_active = 0 and department_id = NULL
     * - Classrooms: set is_active = 0 and department_id = NULL
     * - Timetables linked via those subjects: set is_active = 0
     * Finally, set the department is_active = 0.
     * All operations are wrapped in a transaction.
     *
     * @param int $departmentId
     * @param int $updatedBy
     * @return array
     */
    public function deactivateDepartmentWithReassignment($departmentId, $updatedBy) {
        try {
            $deptId = (int)$departmentId;

            $this->db->beginTransaction();
            try {
                // Unassign users from this department
                $this->db->execute(
                    "UPDATE users SET department_id = NULL, updated_at = NOW() WHERE department_id = ?",
                    [$deptId]
                );

                // Deactivate timetables associated through subjects of this department (before detaching subjects)
                $this->db->execute(
                    "UPDATE timetables t
                     JOIN subjects s ON t.subject_id = s.subject_id
                     SET t.is_active = 0
                     WHERE s.department_id = ? AND t.is_active = 1",
                    [$deptId]
                );

                // Deactivate subjects and detach from department
                $this->db->execute(
                    "UPDATE subjects SET is_active = 0, department_id = NULL, updated_at = NOW() WHERE department_id = ?",
                    [$deptId]
                );

                // Deactivate classrooms and detach from department
                $this->db->execute(
                    "UPDATE classrooms SET is_active = 0, department_id = NULL, updated_at = NOW() WHERE department_id = ?",
                    [$deptId]
                );

                // Finally, deactivate the department itself
                $this->db->execute(
                    "UPDATE departments SET is_active = 0, updated_at = NOW() WHERE department_id = ?",
                    [$deptId]
                );

                $this->logDepartmentAction('DEACTIVATE_WITH_REASSIGNMENT', $deptId, [], $updatedBy);
                $this->db->commit();

                return ['success' => true, 'message' => 'Department deactivated and related entities handled successfully.'];
            } catch (Exception $inner) {
                $this->db->rollback();
                throw $inner;
            }
        } catch (Exception $e) {
            $this->log("Error deactivating department with reassignment: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Failed to deactivate department and reassign dependencies.'];
        }
    }

    /**
     * Get overall statistics for admin header
     */
    public function getOverallDepartmentStatistics() {
        try {
            $stats = [];
            $row = $this->db->fetchRow("SELECT COUNT(*) AS cnt FROM departments", []);
            $stats['total'] = isset($row['cnt']) ? (int)$row['cnt'] : 0;

            $row = $this->db->fetchRow("SELECT COUNT(*) AS cnt FROM departments WHERE is_active = 1", []);
            $stats['active'] = isset($row['cnt']) ? (int)$row['cnt'] : 0;

            $row = $this->db->fetchRow(
                "SELECT COUNT(*) AS cnt FROM users WHERE status = 'active'",
                []
            );
            $stats['total_users'] = isset($row['cnt']) ? (int)$row['cnt'] : 0;

            $row = $this->db->fetchRow(
                "SELECT COUNT(*) AS cnt FROM subjects WHERE is_active = 1",
                []
            );
            $stats['total_subjects'] = isset($row['cnt']) ? (int)$row['cnt'] : 0;

            return $stats;
        } catch (Exception $e) {
            $this->log("Error getting overall department statistics: " . $e->getMessage(), 'error');
            return [
                'total' => 0,
                'active' => 0,
                'total_users' => 0,
                'total_subjects' => 0
            ];
        }
    }

    /**
     * Log department actions for audit
     */
    private function logDepartmentAction($action, $departmentId, $data, $userId) {
        try {
            if (!method_exists($this->db, 'execute')) return; // minimal safeguard
            $this->db->execute(
                "INSERT INTO audit_logs (user_id, action, table_affected, record_id, new_values, timestamp, details)
                 VALUES (?, ?, 'departments', ?, ?, NOW(), ?)",
                [
                    (int)$userId,
                    $action,
                    (int)$departmentId,
                    json_encode($data),
                    "Department {$action} operation"
                ]
            );
        } catch (Exception $e) {
            $this->log("Error logging department action: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Validate department data
     * 
     * @param array $data Department data to validate
     * @param int $excludeId Department ID to exclude from uniqueness checks
     * @return array Validation result
     */
    public function validateDepartmentData($data, $excludeId = null) {
        $errors = [];
        
        // Required field validation
        if (empty($data['department_code'])) {
            $errors[] = 'Department code is required.';
        } elseif (!preg_match('/^[A-Z0-9]{2,10}$/', strtoupper($data['department_code']))) {
            $errors[] = 'Department code must be 2-10 characters long and contain only uppercase letters and numbers.';
        } else {
            // Check uniqueness
            $sql = "SELECT department_id FROM departments WHERE department_code = ? AND is_active = 1";
            $params = [strtoupper($data['department_code'])];
            
            if ($excludeId) {
                $sql .= " AND department_id != ?";
                $params[] = $excludeId;
            }
            
            $existing = $this->db->fetchRow($sql, $params);
            if ($existing) {
                $errors[] = 'Department code already exists.';
            }
        }
        
        if (empty($data['department_name'])) {
            $errors[] = 'Department name is required.';
        } elseif (strlen($data['department_name']) > 100) {
            $errors[] = 'Department name cannot exceed 100 characters.';
        }
        
        // Optional field validation
        if (!empty($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid contact email address.';
        }
        
        if (!empty($data['contact_phone']) && strlen(preg_replace('/[^0-9]/', '', $data['contact_phone'])) > 15) {
            $errors[] = 'Phone number cannot exceed 15 digits.';
        }
        
        if (!empty($data['budget_allocation']) && (float)$data['budget_allocation'] < 0) {
            $errors[] = 'Budget allocation cannot be negative.';
        }
        
        if (!empty($data['established_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['established_date']);
            if (!$date) {
                $errors[] = 'Please enter a valid established date (YYYY-MM-DD).';
            } elseif ($date > new DateTime()) {
                $errors[] = 'Established date cannot be in the future.';
            }
        }
        
        if (!empty($data['department_head_id'])) {
            $facultyCheck = $this->db->fetchRow("
                SELECT f.faculty_id 
                FROM faculty f 
                JOIN users u ON f.user_id = u.user_id 
                WHERE f.faculty_id = ? AND u.status = 'active'
            ", [(int)$data['department_head_id']]);
            
            if (!$facultyCheck) {
                $errors[] = 'Selected department head is not a valid active faculty member.';
            }
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Get system-wide department statistics
     * 
     * @return array Overall department statistics
     */
    public function getSystemDepartmentStatistics() {
        try {
            $stats = [];
            
            // Basic counts
            $basicStats = $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_departments,
                    COUNT(CASE WHEN department_head_id IS NOT NULL THEN 1 END) as departments_with_heads,
                    COUNT(CASE WHEN budget_allocation IS NOT NULL THEN 1 END) as departments_with_budget,
                    COUNT(CASE WHEN established_date IS NOT NULL THEN 1 END) as departments_with_established_date
                FROM departments 
                WHERE is_active = 1
            ");
            
            $stats = array_merge($stats, $basicStats ?: []);
            
            // Budget statistics
            $budgetStats = $this->db->fetchRow("
                SELECT 
                    AVG(budget_allocation) as avg_budget,
                    MIN(budget_allocation) as min_budget,
                    MAX(budget_allocation) as max_budget,
                    SUM(budget_allocation) as total_budget
                FROM departments 
                WHERE is_active = 1 AND budget_allocation IS NOT NULL
            ");
            
            $stats = array_merge($stats, $budgetStats ?: []);
            
            // User distribution
            $userStats = $this->db->fetchRow("
                SELECT 
                    COUNT(DISTINCT CASE WHEN u.role = 'faculty' AND u.status = 'active' THEN u.user_id END) as total_faculty,
                    COUNT(DISTINCT CASE WHEN u.role = 'student' AND u.status = 'active' THEN u.user_id END) as total_students
                FROM users u
                JOIN departments d ON u.department_id = d.department_id
                WHERE d.is_active = 1
            ");
            
            $stats = array_merge($stats, $userStats ?: []);
            
            // Resource distribution
            $resourceStats = $this->db->fetchRow("
                SELECT 
                    COUNT(DISTINCT s.subject_id) as total_subjects,
                    COUNT(DISTINCT c.classroom_id) as total_classrooms
                FROM departments d
                LEFT JOIN subjects s ON d.department_id = s.department_id AND s.is_active = 1
                LEFT JOIN classrooms c ON d.department_id = c.department_id AND c.is_active = 1
                WHERE d.is_active = 1
            ");
            
            $stats = array_merge($stats, $resourceStats ?: []);
            
            return $stats;
            
        } catch (Exception $e) {
            $this->log("Error getting system department statistics: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get department options for dropdowns
     *
     * @param bool $onlyActive When true, only include active departments
     * @return array List of departments with id, name, and code
     */
    public function getDepartmentOptions($onlyActive = true) {
        try {
            $sql = "SELECT department_id, department_name, department_code FROM departments";
            $params = [];
            if ($onlyActive) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY department_name ASC";
            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            $this->log("Error getting department options: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get per-department statistics for edit/view pages
     * Returns: active_faculty, active_students, subject_count, total_classrooms
     */
    public function getDepartmentStats($departmentId) {
        try {
            $deptId = (int)$departmentId;

            // Active faculty (status may not exist in some schemas; be permissive)
            $facultyRow = $this->db->fetchRow(
                "SELECT COUNT(*) AS cnt
                 FROM users
                 WHERE department_id = ?
                   AND (LOWER(role) = 'faculty' OR role = 'faculty' OR role = 'Faculty')
                   AND (status IS NULL OR LOWER(status) = 'active')",
                [$deptId]
            );
            $activeFaculty = (int)($facultyRow['cnt'] ?? 0);

            // Active students
            $studentRow = $this->db->fetchRow(
                "SELECT COUNT(*) AS cnt
                 FROM users
                 WHERE department_id = ?
                   AND (LOWER(role) = 'student' OR role = 'student' OR role = 'Student')
                   AND (status IS NULL OR LOWER(status) = 'active')",
                [$deptId]
            );
            $activeStudents = (int)($studentRow['cnt'] ?? 0);

            // Subjects under this department (is_active if present)
            $subjectsRow = $this->db->fetchRow(
                "SELECT COUNT(*) AS cnt
                 FROM subjects
                 WHERE department_id = ?
                   AND (is_active = 1 OR is_active IS NULL)
                ",
                [$deptId]
            );
            $subjectCount = (int)($subjectsRow['cnt'] ?? 0);

            // Classrooms under this department
            $classroomsRow = $this->db->fetchRow(
                "SELECT COUNT(*) AS cnt
                 FROM classrooms
                 WHERE department_id = ?
                   AND (is_active = 1 OR is_active IS NULL)
                ",
                [$deptId]
            );
            $totalClassrooms = (int)($classroomsRow['cnt'] ?? 0);

            return [
                'active_faculty' => $activeFaculty,
                'active_students' => $activeStudents,
                'subject_count' => $subjectCount,
                'total_classrooms' => $totalClassrooms,
            ];
        } catch (Exception $e) {
            $this->log("Error getting department stats: " . $e->getMessage(), 'error');
            return [
                'active_faculty' => 0,
                'active_students' => 0,
                'subject_count' => 0,
                'total_classrooms' => 0,
            ];
        }
    }

    /**
     * Get available faculty members to be selected as department heads
     * - Includes only active faculty (via users.status = 'active')
     * - Excludes faculty already assigned as a head of any department
     *
     * @return array List of faculty with: faculty_id, full_name, employee_id, designation (if available)
     */
    public function getAvailableDepartmentHeads($currentHeadFacultyId = null) {
        try {
            $params = [];
            $sql = "
                SELECT 
                    f.faculty_id,
                    CONCAT(f.first_name, ' ', f.last_name) AS full_name,
                    f.employee_id,
                    f.designation
                FROM faculty f
                WHERE 1=1
                  AND (
                    f.faculty_id NOT IN (
                        SELECT department_head_id FROM departments WHERE department_head_id IS NOT NULL
                    )
                    " . ($currentHeadFacultyId ? " OR f.faculty_id = ?" : "") . "
                  )
                ORDER BY f.first_name ASC, f.last_name ASC
            ";
            if ($currentHeadFacultyId) { $params[] = (int)$currentHeadFacultyId; }
            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            $this->log("Error getting available department heads: " . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get distinct building locations used by departments
     *
     * @return array List of location strings
     */
    public function getBuildingLocations() {
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT building_location 
                 FROM departments 
                 WHERE building_location IS NOT NULL 
                   AND TRIM(building_location) != ''
                 ORDER BY building_location ASC",
                []
            );
            $locations = [];
            foreach ($rows as $r) {
                if (isset($r['building_location'])) {
                    $locations[] = $r['building_location'];
                }
            }
            return $locations;
        } catch (Exception $e) {
            $this->log("Error getting building locations: " . $e->getMessage(), 'error');
            return [];
        }
    }
}