<?php
/**
 * Enrollment Management Class
 * Timetable Management System
 * 
 * Handles student enrollment operations including enrollment, bulk enrollment,
 * and enrollment management with comprehensive validation and security
 */

defined('SYSTEM_ACCESS') or die('Direct access denied');

class Enrollment {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Get all enrollments with student and subject details
     */
    public function getAllEnrollments($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Build WHERE clause based on filters
            if (!empty($filters['student_id'])) {
                $whereConditions[] = "e.student_id = ?";
                $params[] = $filters['student_id'];
            }
            
            if (!empty($filters['subject_id'])) {
                $whereConditions[] = "e.subject_id = ?";
                $params[] = $filters['subject_id'];
            }
            
            if (!empty($filters['status'])) {
                $whereConditions[] = "e.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['semester'])) {
                $whereConditions[] = "e.semester = ?";
                $params[] = $filters['semester'];
            }
            
            if (!empty($filters['academic_year'])) {
                $whereConditions[] = "e.academic_year = ?";
                $params[] = $filters['academic_year'];
            }
            
            if (!empty($filters['section'])) {
                $whereConditions[] = "e.section = ?";
                $params[] = $filters['section'];
            }
            
            if (!empty($filters['department'])) {
                // Filter by subject's department to align with options populated from subjects
                $whereConditions[] = "sub.department = ?";
                $params[] = $filters['department'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $query = "SELECT 
                        e.enrollment_id,
                        e.student_id,
                        e.subject_id,
                        e.section,
                        e.semester,
                        e.academic_year,
                        e.enrollment_date,
                        e.status,
                        e.enrolled_by,
                        st.student_number,
                        st.first_name as student_first_name,
                        st.last_name as student_last_name,
                        st.department as student_department,
                        st.year_of_study,
                        sub.subject_code,
                        sub.subject_name,
                        sub.credits,
                        sub.department as subject_department,
                        u.username as enrolled_by_username
                      FROM enrollments e
                      JOIN students st ON e.student_id = st.student_id
                      JOIN subjects sub ON e.subject_id = sub.subject_id
                      LEFT JOIN users u ON e.enrolled_by = u.user_id
                      $whereClause
                      ORDER BY e.enrollment_date DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching enrollments: " . $e->getMessage());
            throw new Exception("Failed to retrieve enrollment data");
        }
    }

    /**
     * Check for duplicate enrollment (same student, subject, section, semester, academic year)
     * Optionally exclude a specific enrollment ID (useful for edits)
     *
     * @param int $studentId
     * @param int $subjectId
     * @param string $section
     * @param int $semester
     * @param string $academicYear
     * @param int|null $excludeEnrollmentId
     * @return bool true if duplicate exists, false otherwise
     */
    public function checkDuplicateEnrollment($studentId, $subjectId, $section, $semester, $academicYear, $excludeEnrollmentId = null) {
        try {
            $params = [$studentId, $subjectId, $section, $semester, $academicYear];
            $excludeSql = '';
            if (!empty($excludeEnrollmentId)) {
                $excludeSql = ' AND e.enrollment_id <> ?';
                $params[] = $excludeEnrollmentId;
            }
            $query = "SELECT COUNT(*) AS cnt
                      FROM enrollments e
                      WHERE e.student_id = ?
                        AND e.subject_id = ?
                        AND e.section = ?
                        AND e.semester = ?
                        AND e.academic_year = ?" . $excludeSql;
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $count = (int)$stmt->fetchColumn();
            return $count > 0;
        } catch (PDOException $e) {
            error_log("Error checking duplicate enrollment: " . $e->getMessage());
            // Be safe: if error occurs, do not block update by falsely reporting duplicate
            return false;
        }
    }

    /**
     * Update an existing enrollment record
     *
     * @param int $enrollmentId
     * @param array $data Keys: student_id, subject_id, section, semester, academic_year, status
     * @param int $updatedBy User ID performing the update (stored in enrolled_by for audit if desired)
     * @return array ['success' => bool, 'message' => string]
     */
    public function updateEnrollment($enrollmentId, array $data, $updatedBy = null) {
        try {
            // Validate status if provided
            if (isset($data['status'])) {
                $validStatuses = ['enrolled', 'dropped', 'completed', 'failed'];
                if (!in_array($data['status'], $validStatuses)) {
                    throw new Exception('Invalid enrollment status');
                }
            }

            // Build dynamic SET clause safely
            $allowed = ['student_id', 'subject_id', 'section', 'semester', 'academic_year', 'status'];
            $sets = [];
            $params = [];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $data)) {
                    $sets[] = "$key = ?";
                    $params[] = $data[$key];
                }
            }
            // Optional: track who updated last via enrolled_by if provided
            if (!empty($updatedBy)) {
                $sets[] = "enrolled_by = ?";
                $params[] = $updatedBy;
            }

            if (empty($sets)) {
                return [
                    'success' => false,
                    'message' => 'No fields provided to update'
                ];
            }

            $params[] = $enrollmentId;
            $sql = "UPDATE enrollments SET " . implode(', ', $sets) . " WHERE enrollment_id = ?";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute($params);

            if ($result && $stmt->rowCount() >= 0) { // >=0 to consider no-change updates as success
                return [
                    'success' => true,
                    'message' => 'Enrollment updated successfully'
                ];
            }

            throw new Exception('Failed to update enrollment');

        } catch (PDOException $e) {
            error_log('Error updating enrollment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred while updating enrollment'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get enrollment statistics
     */
    public function getEnrollmentStats() {
        try {
            $stats = [];
            
            // Total enrollments
            $query = "SELECT COUNT(*) as total_enrollments FROM enrollments";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['total_enrollments'] = $stmt->fetchColumn();
            
            // Active enrollments
            $query = "SELECT COUNT(*) as active_enrollments FROM enrollments WHERE status = 'enrolled'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['active_enrollments'] = $stmt->fetchColumn();
            
            // Dropped enrollments
            $query = "SELECT COUNT(*) as dropped_enrollments FROM enrollments WHERE status = 'dropped'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['dropped_enrollments'] = $stmt->fetchColumn();
            
            // Completed enrollments
            $query = "SELECT COUNT(*) as completed_enrollments FROM enrollments WHERE status = 'completed'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['completed_enrollments'] = $stmt->fetchColumn();
            
            // Recent enrollments (last 7 days)
            $query = "SELECT COUNT(*) as recent_enrollments FROM enrollments WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['recent_enrollments'] = $stmt->fetchColumn();
            
            // Total subjects with enrollments
            $query = "SELECT COUNT(DISTINCT subject_id) as subjects_with_enrollments FROM enrollments";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['subjects_with_enrollments'] = $stmt->fetchColumn();
            
            // Total students enrolled
            $query = "SELECT COUNT(DISTINCT student_id) as students_enrolled FROM enrollments WHERE status = 'enrolled'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['students_enrolled'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Error fetching enrollment statistics: " . $e->getMessage());
            return [
                'total_enrollments' => 0,
                'active_enrollments' => 0,
                'dropped_enrollments' => 0,
                'completed_enrollments' => 0,
                'recent_enrollments' => 0,
                'subjects_with_enrollments' => 0,
                'students_enrolled' => 0
            ];
        }
    }
    
    /**
     * Get all departments
     */
    public function getDepartments() {
        try {
            $query = "SELECT DISTINCT department FROM subjects WHERE is_active = 1 ORDER BY department";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching departments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Enroll a student in a subject
     */
    public function enrollStudent($studentId, $subjectId, $semester, $academicYear, $enrolledBy, $section = 'A') {
        try {
            // Check if enrollment already exists
            $checkQuery = "SELECT enrollment_id FROM enrollments 
                          WHERE student_id = ? AND subject_id = ? AND section = ? AND academic_year = ?";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([$studentId, $subjectId, $section, $academicYear]);
            
            if ($checkStmt->rowCount() > 0) {
                throw new Exception("Student is already enrolled in this subject and section for the academic year");
            }
            
            // Insert new enrollment
            $query = "INSERT INTO enrollments (student_id, subject_id, section, semester, academic_year, enrolled_by)
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$studentId, $subjectId, $section, $semester, $academicYear, $enrolledBy]);
            
            if ($result) {
                return [
                    'success' => true,
                    'enrollment_id' => $this->conn->lastInsertId(),
                    'message' => 'Student enrolled successfully'
                ];
            }
            
            throw new Exception("Failed to enroll student");
            
        } catch (PDOException $e) {
            error_log("Error enrolling student: " . $e->getMessage());
            throw new Exception("Database error occurred while enrolling student");
        }
    }
    
    /**
     * Update enrollment status
     */
    public function updateEnrollmentStatus($enrollmentId, $status) {
        try {
            $validStatuses = ['enrolled', 'dropped', 'completed', 'failed'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid enrollment status");
            }
            
            $query = "UPDATE enrollments SET status = ? WHERE enrollment_id = ?";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$status, $enrollmentId]);
            
            if ($result && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Enrollment status updated successfully'
                ];
            }
            
            throw new Exception("No enrollment found with the specified ID");
            
        } catch (PDOException $e) {
            error_log("Error updating enrollment status: " . $e->getMessage());
            throw new Exception("Database error occurred while updating enrollment status");
        }
    }
    
    /**
     * Delete enrollment
     */
    public function deleteEnrollment($enrollmentId) {
        try {
            $query = "DELETE FROM enrollments WHERE enrollment_id = ?";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$enrollmentId]);
            
            if ($result && $stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Enrollment deleted successfully'
                ];
            }
            
            throw new Exception("No enrollment found with the specified ID");
            
        } catch (PDOException $e) {
            error_log("Error deleting enrollment: " . $e->getMessage());
            throw new Exception("Database error occurred while deleting enrollment");
        }
    }
    
    /**
     * Get enrollment by ID
     */
    public function getEnrollmentById($enrollmentId) {
        try {
            $query = "SELECT 
                        e.*,
                        st.student_number,
                        st.first_name as student_first_name,
                        st.last_name as student_last_name,
                        st.department as student_department,
                        sub.subject_code,
                        sub.subject_name,
                        sub.credits,
                        sub.department as subject_department
                      FROM enrollments e
                      JOIN students st ON e.student_id = st.student_id
                      JOIN subjects sub ON e.subject_id = sub.subject_id
                      WHERE e.enrollment_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$enrollmentId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching enrollment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all students for enrollment
     */
    public function getStudentsForEnrollment() {
        try {
            $query = "SELECT 
                        s.student_id,
                        s.student_number,
                        s.first_name,
                        s.last_name,
                        s.department,
                        s.year_of_study,
                        s.semester
                      FROM students s
                      JOIN users u ON s.user_id = u.user_id
                      WHERE u.status = 'active'
                      ORDER BY s.first_name, s.last_name";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching students: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all subjects for enrollment
     */
    public function getSubjectsForEnrollment() {
        try {
            $query = "SELECT 
                        subject_id,
                        subject_code,
                        subject_name,
                        credits,
                        department,
                        semester
                      FROM subjects
                      WHERE is_active = 1
                      ORDER BY subject_code";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching subjects: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Bulk enroll students
     */
    public function bulkEnrollStudents($enrollments, $enrolledBy) {
        try {
            $this->conn->beginTransaction();
            
            $successCount = 0;
            $errors = [];
            
            foreach ($enrollments as $enrollment) {
                try {
                    $result = $this->enrollStudent(
                        $enrollment['student_id'],
                        $enrollment['subject_id'],
                        $enrollment['semester'],
                        $enrollment['academic_year'],
                        $enrolledBy,
                        $enrollment['section'] ?? 'A'
                    );
                    
                    if ($result['success']) {
                        $successCount++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Student ID {$enrollment['student_id']}: " . $e->getMessage();
                }
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'enrolled_count' => $successCount,
                'errors' => $errors,
                'message' => "$successCount students enrolled successfully" . 
                           (!empty($errors) ? " with " . count($errors) . " errors" : "")
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error in bulk enrollment: " . $e->getMessage());
            throw new Exception("Bulk enrollment failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get academic years
     */
    public function getAcademicYears() {
        try {
            $query = "SELECT DISTINCT academic_year FROM enrollments ORDER BY academic_year DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // If no years found, add current academic year
            if (empty($years)) {
                $currentYear = date('Y');
                $nextYear = $currentYear + 1;
                $years[] = "$currentYear-$nextYear";
            }
            
            return $years;
            
        } catch (PDOException $e) {
            error_log("Error fetching academic years: " . $e->getMessage());
            return [date('Y') . '-' . (date('Y') + 1)];
        }
    }
}
?>