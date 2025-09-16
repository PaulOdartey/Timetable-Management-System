<?php
/**
 * Classroom Class
 * Handles all classroom-related operations
 * Timetable Management System
 */

defined('SYSTEM_ACCESS') or die('Direct access denied');

class Classroom {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        // Initialize logger with fallback
        try {
            $this->logger = function_exists('getLogger') ? getLogger() : null;
        } catch (Exception $e) {
            $this->logger = null;
        }
    }
    
    /**
     * Log message with fallback
     */
    private function log($message, $level = 'info') {
        if ($this->logger) {
            $this->logger->$level($message);
        } else {
            error_log("[Classroom] [{$level}] {$message}");
        }
    }
    
    /**
     * Get all classrooms with optional filters
     * @param array $filters Optional filters
     * @return array
     */
    public function getAllClassrooms($filters = []) {
        try {
            // Build WHERE clause based on filters
            $whereConditions = [];
            $params = [];
            
            if (!empty($filters['building'])) {
                $whereConditions[] = "c.building = ?";
                $params[] = $filters['building'];
            }
            
            if (!empty($filters['type'])) {
                $whereConditions[] = "c.type = ?";
                $params[] = $filters['type'];
            }
            
            if (!empty($filters['status'])) {
                $whereConditions[] = "c.status = ?";
                $params[] = $filters['status'];
            }
            
            if (isset($filters['is_active'])) {
                $whereConditions[] = "c.is_active = ?";
                $params[] = $filters['is_active'] ? 1 : 0;
            }
            
            if (!empty($filters['department_id'])) {
                $whereConditions[] = "c.department_id = ?";
                $params[] = $filters['department_id'];
            }
            
            if (!empty($filters['capacity_min'])) {
                $whereConditions[] = "c.capacity >= ?";
                $params[] = $filters['capacity_min'];
            }
            
            if (!empty($filters['capacity_max'])) {
                $whereConditions[] = "c.capacity <= ?";
                $params[] = $filters['capacity_max'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get classrooms with department info and usage statistics
            $sql = "
                SELECT c.*,
                       d.department_name,
                       d.department_code,
                       COUNT(DISTINCT t.timetable_id) as active_schedules,
                       COUNT(DISTINCT CASE WHEN t.is_active = 1 THEN t.timetable_id END) as current_bookings,
                       GROUP_CONCAT(DISTINCT CONCAT(sub.subject_code, ' (', ts.day_of_week, ')') 
                                   ORDER BY ts.day_of_week, ts.start_time SEPARATOR ', ') as scheduled_subjects
                FROM classrooms c
                LEFT JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN timetables t ON c.classroom_id = t.classroom_id AND t.is_active = 1
                LEFT JOIN subjects sub ON t.subject_id = sub.subject_id
                LEFT JOIN time_slots ts ON t.slot_id = ts.slot_id
                {$whereClause}
                GROUP BY c.classroom_id
                ORDER BY c.building ASC, c.room_number ASC
            ";
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            $this->log("Error fetching classrooms: " . $e->getMessage(), 'error');
            throw new Exception("Failed to fetch classrooms: " . $e->getMessage());
        }
    }
    
    /**
     * Get classroom by ID
     * @param int $classroomId
     * @return array|null
     */
    public function getClassroomById($classroomId) {
        try {
            $sql = "
                SELECT c.*,
                       d.department_name,
                       d.department_code,
                       COUNT(DISTINCT t.timetable_id) as total_schedules,
                       COUNT(DISTINCT CASE WHEN t.is_active = 1 THEN t.timetable_id END) as active_schedules
                FROM classrooms c
                LEFT JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN timetables t ON c.classroom_id = t.classroom_id
                WHERE c.classroom_id = ?
                GROUP BY c.classroom_id
            ";
            
            return $this->db->fetchRow($sql, [$classroomId]);
            
        } catch (Exception $e) {
            $this->log("Error fetching classroom {$classroomId}: " . $e->getMessage(), 'error');
            throw new Exception("Failed to fetch classroom details");
        }
    }
    
    /**
     * Create new classroom
     * @param array $data
     * @return array
     */
    public function createClassroom($data) {
        try {
            // Validate required fields
            $requiredFields = ['room_number', 'building', 'capacity', 'type'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Field '{$field}' is required"
                    ];
                }
            }
            
            // Check for duplicate room number in same building
            $existing = $this->db->fetchRow(
                "SELECT classroom_id FROM classrooms WHERE room_number = ? AND building = ?",
                [$data['room_number'], $data['building']]
            );
            
            if ($existing) {
                return [
                    'success' => false,
                    'message' => "Room {$data['room_number']} already exists in {$data['building']}"
                ];
            }
            
            // Prepare data for insertion
            $insertData = [
                'room_number' => trim($data['room_number']),
                'building' => trim($data['building']),
                'floor' => !empty($data['floor']) ? (int)$data['floor'] : null,
                'capacity' => (int)$data['capacity'],
                'type' => $data['type'],
                'equipment' => !empty($data['equipment']) ? trim($data['equipment']) : null,
                'status' => $data['status'] ?? 'available',
                'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                'department_id' => !empty($data['department_id']) ? (int)$data['department_id'] : null,
                'is_shared' => isset($data['is_shared']) ? (int)$data['is_shared'] : 0
            ];
            
            // Handle facilities JSON
            if (!empty($data['facilities']) && is_array($data['facilities'])) {
                $insertData['facilities'] = json_encode($data['facilities']);
            }
            
            $sql = "
                INSERT INTO classrooms (
                    room_number, building, floor, capacity, type, equipment, 
                    status, is_active, department_id, is_shared, facilities,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            
            $params = [
                $insertData['room_number'],
                $insertData['building'],
                $insertData['floor'],
                $insertData['capacity'],
                $insertData['type'],
                $insertData['equipment'],
                $insertData['status'],
                $insertData['is_active'],
                $insertData['department_id'],
                $insertData['is_shared'],
                $insertData['facilities'] ?? null
            ];
            
            $result = $this->db->execute($sql, $params);
            
            if ($result) {
                // Try different methods to get last insert ID
                $classroomId = null;
                try {
                    // Method 1: Try getLastInsertId if it exists
                    if (method_exists($this->db, 'getLastInsertId')) {
                        $classroomId = $this->db->getLastInsertId();
                    }
                    // Method 2: Try direct PDO lastInsertId
                    elseif (method_exists($this->db, 'getConnection')) {
                        $classroomId = $this->db->getConnection()->lastInsertId();
                    }
                    // Method 3: Query for the last inserted record
                    else {
                        $lastRecord = $this->db->fetchRow(
                            "SELECT classroom_id FROM classrooms WHERE room_number = ? AND building = ? ORDER BY classroom_id DESC LIMIT 1",
                            [$insertData['room_number'], $insertData['building']]
                        );
                        $classroomId = $lastRecord ? $lastRecord['classroom_id'] : null;
                    }
                } catch (Exception $e) {
                    // Fallback: Query for the record we just created
                    $lastRecord = $this->db->fetchRow(
                        "SELECT classroom_id FROM classrooms WHERE room_number = ? AND building = ? ORDER BY classroom_id DESC LIMIT 1",
                        [$insertData['room_number'], $insertData['building']]
                    );
                    $classroomId = $lastRecord ? $lastRecord['classroom_id'] : null;
                }
                
                $this->log("Classroom created successfully: ID {$classroomId}", 'info');
                
                return [
                    'success' => true,
                    'message' => "Classroom '{$insertData['room_number']}' created successfully",
                    'classroom_id' => $classroomId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Failed to create classroom"
                ];
            }
            
        } catch (Exception $e) {
            $this->log("Error creating classroom: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => "Database error occurred while creating classroom"
            ];
        }
    }
    
    /**
     * Update classroom
     * @param int $classroomId
     * @param array $data
     * @return array
     */
    public function updateClassroom($classroomId, $data) {
        try {
            // Check if classroom exists
            $existing = $this->getClassroomById($classroomId);
            if (!$existing) {
                return [
                    'success' => false,
                    'message' => "Classroom not found"
                ];
            }
            
            // Check for duplicate room number in same building (excluding current)
            if (!empty($data['room_number']) && !empty($data['building'])) {
                $duplicate = $this->db->fetchRow(
                    "SELECT classroom_id FROM classrooms WHERE room_number = ? AND building = ? AND classroom_id != ?",
                    [$data['room_number'], $data['building'], $classroomId]
                );
                
                if ($duplicate) {
                    return [
                        'success' => false,
                        'message' => "Room {$data['room_number']} already exists in {$data['building']}"
                    ];
                }
            }
            
            // Prepare update data
            $updateFields = [];
            $params = [];
            
            $allowedFields = [
                'room_number', 'building', 'floor', 'capacity', 'type', 
                'equipment', 'status', 'is_active', 'department_id', 'is_shared'
            ];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateFields[] = "{$field} = ?";
                    
                    if ($field === 'capacity' || $field === 'floor' || $field === 'department_id') {
                        $params[] = !empty($data[$field]) ? (int)$data[$field] : null;
                    } elseif ($field === 'is_active' || $field === 'is_shared') {
                        $params[] = (int)$data[$field];
                    } else {
                        $params[] = $data[$field];
                    }
                }
            }
            
            // Handle facilities JSON
            if (array_key_exists('facilities', $data)) {
                $updateFields[] = "facilities = ?";
                if (is_array($data['facilities'])) {
                    $params[] = json_encode($data['facilities']);
                } else {
                    $params[] = $data['facilities'];
                }
            }
            
            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'message' => "No valid fields to update"
                ];
            }
            
            $updateFields[] = "updated_at = NOW()";
            $params[] = $classroomId;
            
            $sql = "UPDATE classrooms SET " . implode(', ', $updateFields) . " WHERE classroom_id = ?";
            
            $result = $this->db->execute($sql, $params);
            
            if ($result) {
                $this->log("Classroom updated successfully: ID {$classroomId}", 'info');
                return [
                    'success' => true,
                    'message' => "Classroom updated successfully"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Failed to update classroom"
                ];
            }
            
        } catch (Exception $e) {
            $this->log("Error updating classroom {$classroomId}: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => "Database error occurred while updating classroom"
            ];
        }
    }
    
    /**
     * Delete classroom
     * @param int $classroomId
     * @return array
     */
    public function deleteClassroom($classroomId) {
        try {
            // Check if classroom exists
            $classroom = $this->getClassroomById($classroomId);
            if (!$classroom) {
                return [
                    'success' => false,
                    'message' => "Classroom not found"
                ];
            }
            
            // Check if classroom is being used in timetables
            $usage = $this->db->fetchRow(
                "SELECT COUNT(*) as count FROM timetables WHERE classroom_id = ? AND is_active = 1",
                [$classroomId]
            );
            
            if ($usage['count'] > 0) {
                return [
                    'success' => false,
                    'message' => "Cannot delete classroom as it is currently being used in {$usage['count']} active schedule(s)"
                ];
            }
            
            $result = $this->db->execute(
                "DELETE FROM classrooms WHERE classroom_id = ?",
                [$classroomId]
            );
            
            if ($result) {
                $this->log("Classroom deleted successfully: ID {$classroomId} ({$classroom['room_number']})", 'info');
                return [
                    'success' => true,
                    'message' => "Classroom '{$classroom['room_number']}' deleted successfully"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Failed to delete classroom"
                ];
            }
            
        } catch (Exception $e) {
            $this->log("Error deleting classroom {$classroomId}: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => "Database error occurred while deleting classroom"
            ];
        }
    }
    
    /**
     * Get classroom statistics
     * @return array
     */
    public function getClassroomStatistics() {
        try {
            $stats = $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_classrooms,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_classrooms,
                    COUNT(CASE WHEN status = 'available' THEN 1 END) as available_classrooms,
                    COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_classrooms,
                    COUNT(CASE WHEN status = 'reserved' THEN 1 END) as reserved_classrooms,
                    COUNT(CASE WHEN type = 'lecture' THEN 1 END) as lecture_rooms,
                    COUNT(CASE WHEN type = 'lab' THEN 1 END) as lab_rooms,
                    COUNT(CASE WHEN type = 'seminar' THEN 1 END) as seminar_rooms,
                    COUNT(CASE WHEN type = 'auditorium' THEN 1 END) as auditorium_rooms,
                    COUNT(DISTINCT building) as unique_buildings,
                    SUM(capacity) as total_capacity,
                    AVG(capacity) as average_capacity,
                    MAX(capacity) as max_capacity,
                    MIN(capacity) as min_capacity
                FROM classrooms
            ");
            
            // Get utilization stats
            $utilization = $this->db->fetchRow("
                SELECT 
                    COUNT(DISTINCT c.classroom_id) as utilized_classrooms,
                    COUNT(t.timetable_id) as total_bookings,
                    ROUND(AVG(c.capacity), 0) as avg_utilized_capacity
                FROM classrooms c
                INNER JOIN timetables t ON c.classroom_id = t.classroom_id AND t.is_active = 1
                WHERE c.is_active = 1
            ");
            
            // Merge stats
            return array_merge($stats, [
                'utilized_classrooms' => $utilization['utilized_classrooms'] ?? 0,
                'total_bookings' => $utilization['total_bookings'] ?? 0,
                'avg_utilized_capacity' => $utilization['avg_utilized_capacity'] ?? 0,
                'utilization_rate' => $stats['active_classrooms'] > 0 
                    ? round(($utilization['utilized_classrooms'] ?? 0) / $stats['active_classrooms'] * 100, 1)
                    : 0
            ]);
            
        } catch (Exception $e) {
            $this->log("Error fetching classroom statistics: " . $e->getMessage(), 'error');
            return [
                'total_classrooms' => 0,
                'active_classrooms' => 0,
                'available_classrooms' => 0,
                'maintenance_classrooms' => 0,
                'reserved_classrooms' => 0,
                'lecture_rooms' => 0,
                'lab_rooms' => 0,
                'seminar_rooms' => 0,
                'auditorium_rooms' => 0,
                'unique_buildings' => 0,
                'total_capacity' => 0,
                'average_capacity' => 0,
                'max_capacity' => 0,
                'min_capacity' => 0,
                'utilized_classrooms' => 0,
                'total_bookings' => 0,
                'avg_utilized_capacity' => 0,
                'utilization_rate' => 0
            ];
        }
    }
    
    /**
     * Get unique buildings
     * @return array
     */
    public function getBuildings() {
        try {
            return $this->db->fetchAll("
                SELECT DISTINCT building 
                FROM classrooms 
                WHERE building IS NOT NULL AND building != ''
                ORDER BY building ASC
            ");
        } catch (Exception $e) {
            $this->log("Error fetching buildings: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Get classroom types
     * @return array
     */
    public function getClassroomTypes() {
        return [
            'lecture' => 'Lecture Hall',
            'lab' => 'Laboratory',
            'seminar' => 'Seminar Room',
            'auditorium' => 'Auditorium'
        ];
    }
    
    /**
     * Get classroom statuses
     * @return array
     */
    public function getClassroomStatuses() {
        return [
            'available' => 'Available',
            'maintenance' => 'Under Maintenance',
            'reserved' => 'Reserved',
            'closed' => 'Closed'
        ];
    }
    
    /**
     * Check classroom availability for a specific time slot
     * @param int $classroomId
     * @param int $slotId
     * @param string $academicYear
     * @param int $semester
     * @param int $excludeTimetableId
     * @return bool
     */
    public function checkAvailability($classroomId, $slotId, $academicYear, $semester, $excludeTimetableId = null) {
        try {
            $sql = "
                SELECT COUNT(*) as count
                FROM timetables 
                WHERE classroom_id = ? 
                AND slot_id = ? 
                AND academic_year = ? 
                AND semester = ? 
                AND is_active = 1
            ";
            
            $params = [$classroomId, $slotId, $academicYear, $semester];
            
            if ($excludeTimetableId) {
                $sql .= " AND timetable_id != ?";
                $params[] = $excludeTimetableId;
            }
            
            $result = $this->db->fetchRow($sql, $params);
            return $result['count'] == 0;
            
        } catch (Exception $e) {
            $this->log("Error checking classroom availability: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Get classroom schedule for a specific day
     * @param int $classroomId
     * @param string $dayOfWeek
     * @param string $academicYear
     * @param int $semester
     * @return array
     */
    public function getClassroomSchedule($classroomId, $dayOfWeek, $academicYear, $semester) {
        try {
            return $this->db->fetchAll("
                SELECT t.*, s.subject_name, s.subject_code,
                       f.first_name as faculty_first, f.last_name as faculty_last,
                       ts.start_time, ts.end_time, ts.slot_name
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN faculty f ON t.faculty_id = f.faculty_id
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                WHERE t.classroom_id = ?
                AND ts.day_of_week = ?
                AND t.academic_year = ?
                AND t.semester = ?
                AND t.is_active = 1
                ORDER BY ts.start_time ASC
            ", [$classroomId, $dayOfWeek, $academicYear, $semester]);
            
        } catch (Exception $e) {
            $this->log("Error fetching classroom schedule: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Bulk update classroom status
     * @param array $classroomIds
     * @param string $status
     * @return array
     */
    public function bulkUpdateStatus($classroomIds, $status) {
        try {
            if (empty($classroomIds) || !is_array($classroomIds)) {
                return [
                    'success' => false,
                    'message' => "No classrooms selected"
                ];
            }
            
            $validStatuses = ['available', 'maintenance', 'reserved', 'closed'];
            if (!in_array($status, $validStatuses)) {
                return [
                    'success' => false,
                    'message' => "Invalid status"
                ];
            }
            
            $placeholders = str_repeat('?,', count($classroomIds) - 1) . '?';
            $params = array_merge([$status], $classroomIds);
            
            $sql = "UPDATE classrooms SET status = ?, updated_at = NOW() WHERE classroom_id IN ({$placeholders})";
            
            $result = $this->db->execute($sql, $params);
            
            if ($result) {
                $count = count($classroomIds);
                $this->log("Bulk status update: {$count} classrooms set to {$status}", 'info');
                return [
                    'success' => true,
                    'message' => "Updated status for {$count} classroom(s) to " . ucfirst($status)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Failed to update classroom statuses"
                ];
            }
            
        } catch (Exception $e) {
            $this->log("Error in bulk status update: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => "Database error occurred during bulk update"
            ];
        }
    }
}
?>