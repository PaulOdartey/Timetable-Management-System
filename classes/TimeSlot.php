<?php
/**
 * TimeSlot Class
 * Handles time slot management operations
 * Timetable Management System
 */

// Security check
defined('SYSTEM_ACCESS') or die('Direct access denied');

class TimeSlot {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new time slot
     * @param array $data Time slot data
     * @return array Result with success status and message
     */
    public function createTimeSlot($data) {
        try {
            // Validate required fields
            $requiredFields = array('day_of_week', 'start_time', 'end_time', 'slot_name');
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field {$field} is required");
                }
            }
            
            // Check for duplicate slot
            $existing = $this->db->fetchRow("
                SELECT slot_id FROM time_slots 
                WHERE day_of_week = ? AND start_time = ? AND end_time = ?
            ", array($data['day_of_week'], $data['start_time'], $data['end_time']));
            
            if ($existing) {
                throw new Exception("A time slot with the same day and time already exists");
            }
            
            // Validate time range
            if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
                throw new Exception("Start time must be before end time");
            }
            
            // Check for overlapping slots on the same day
            $overlapping = $this->db->fetchRow("
                SELECT slot_id FROM time_slots 
                WHERE day_of_week = ? 
                AND (
                    (start_time < ? AND end_time > ?) OR
                    (start_time < ? AND end_time > ?) OR
                    (start_time >= ? AND start_time < ?) OR
                    (end_time > ? AND end_time <= ?)
                )
            ", array(
                $data['day_of_week'],
                $data['start_time'], $data['start_time'],
                $data['end_time'], $data['end_time'],
                $data['start_time'], $data['end_time'],
                $data['start_time'], $data['end_time']
            ));
            
            if ($overlapping) {
                throw new Exception("This time slot overlaps with an existing slot");
            }
            
            // Set defaults
            $slotType = isset($data['slot_type']) ? $data['slot_type'] : 'regular';
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
            
            // Insert new time slot
            $result = $this->db->execute("
                INSERT INTO time_slots (day_of_week, start_time, end_time, slot_name, slot_type, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ", array(
                $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                $data['slot_name'],
                $slotType,
                $isActive
            ));
            
            if ($result) {
                $slotId = $this->db->lastInsertId();
                
                // Log the creation
                if (class_exists('AuditLogger')) {
                    AuditLogger::log('CREATE_TIMESLOT', 'time_slots', $slotId, null, $data);
                }
                
                return array(
                    'success' => true,
                    'message' => 'Time slot created successfully',
                    'slot_id' => $slotId
                );
            } else {
                throw new Exception("Failed to create time slot");
            }
            
        } catch (Exception $e) {
            error_log("TimeSlot::createTimeSlot Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Update time slot status (activate/deactivate)
     * @param int $slotId Time slot ID
     * @param int $status Status (1 for active, 0 for inactive)
     * @return array Result with success status and message
     */
    public function updateSlotStatus($slotId, $status) {
        try {
            $slotId = (int)$slotId;
            $status = (int)$status;
            
            // Check if slot exists
            $slot = $this->db->fetchRow("SELECT * FROM time_slots WHERE slot_id = ?", array($slotId));
            if (!$slot) {
                throw new Exception("Time slot not found");
            }
            
            // Update status
            $result = $this->db->execute("
                UPDATE time_slots 
                SET is_active = ? 
                WHERE slot_id = ?
            ", array($status, $slotId));
            
            if ($result) {
                // Log the status change
                if (class_exists('AuditLogger')) {
                    $action = $status ? 'ACTIVATE_TIMESLOT' : 'DEACTIVATE_TIMESLOT';
                    AuditLogger::log($action, 'time_slots', $slotId, $slot, array('is_active' => $status));
                }
                
                $statusText = $status ? 'activated' : 'deactivated';
                return array(
                    'success' => true,
                    'message' => "Time slot has been {$statusText} successfully"
                );
            } else {
                throw new Exception("Failed to update time slot status");
            }
            
        } catch (Exception $e) {
            error_log("TimeSlot::updateSlotStatus Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Delete a time slot
     * @param int $slotId Time slot ID
     * @return array Result with success status and message
     */
    public function deleteTimeSlot($slotId, $permanent = false) {
        try {
            $slotId = (int)$slotId;
            
            // Check if slot exists
            $slot = $this->db->fetchRow("SELECT * FROM time_slots WHERE slot_id = ?", array($slotId));
            if (!$slot) {
                throw new Exception("Time slot not found");
            }
            
            // Check if slot is being used in any timetables
            $usage = $this->db->fetchRow("
                SELECT COUNT(*) as usage_count 
                FROM timetables 
                WHERE slot_id = ?
            ", array($slotId));
            
            if ($usage['usage_count'] > 0) {
                throw new Exception("Cannot delete time slot that is being used in {$usage['usage_count']} timetable(s)");
            }
            
            // Delete the time slot
            $result = $this->db->execute("DELETE FROM time_slots WHERE slot_id = ?", array($slotId));
            
            if ($result) {
                // Log the deletion
                if (class_exists('AuditLogger')) {
                    AuditLogger::log('DELETE_TIMESLOT', 'time_slots', $slotId, $slot, null);
                }
                
                return array(
                    'success' => true,
                    'message' => 'Time slot deleted successfully'
                );
            } else {
                throw new Exception("Failed to delete time slot");
            }
            
        } catch (Exception $e) {
            error_log("TimeSlot::deleteTimeSlot Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Deactivate (soft delete) a time slot
     * @param int $slotId Time slot ID
     * @return array Result with success status and message
     */
    public function deactivateTimeSlot($slotId) {
        // Proxy to updateSlotStatus with inactive flag
        return $this->updateSlotStatus($slotId, 0);
    }
    
    /**
     * Update time slot information
     * @param int $slotId Time slot ID
     * @param array $data Updated data
     * @return array Result with success status and message
     */
    public function updateTimeSlot($slotId, $data) {
        try {
            $slotId = (int)$slotId;
            
            // Check if slot exists
            $existingSlot = $this->db->fetchRow("SELECT * FROM time_slots WHERE slot_id = ?", array($slotId));
            if (!$existingSlot) {
                throw new Exception("Time slot not found");
            }
            
            // Validate time range if provided
            if (isset($data['start_time']) && isset($data['end_time'])) {
                if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
                    throw new Exception("Start time must be before end time");
                }
            }
            
            // Check for conflicts if time or day is being changed
            if (isset($data['day_of_week']) || isset($data['start_time']) || isset($data['end_time'])) {
                $checkDay = isset($data['day_of_week']) ? $data['day_of_week'] : $existingSlot['day_of_week'];
                $checkStart = isset($data['start_time']) ? $data['start_time'] : $existingSlot['start_time'];
                $checkEnd = isset($data['end_time']) ? $data['end_time'] : $existingSlot['end_time'];
                
                $conflicting = $this->db->fetchRow("
                    SELECT slot_id FROM time_slots 
                    WHERE slot_id != ? AND day_of_week = ? 
                    AND (
                        (start_time < ? AND end_time > ?) OR
                        (start_time < ? AND end_time > ?) OR
                        (start_time >= ? AND start_time < ?) OR
                        (end_time > ? AND end_time <= ?)
                    )
                ", array(
                    $slotId, $checkDay,
                    $checkStart, $checkStart,
                    $checkEnd, $checkEnd,
                    $checkStart, $checkEnd,
                    $checkStart, $checkEnd
                ));
                
                if ($conflicting) {
                    throw new Exception("Updated time slot would conflict with an existing slot");
                }
            }
            
            // Build update query
            $updateFields = array();
            $updateValues = array();
            
            $allowedFields = array('day_of_week', 'start_time', 'end_time', 'slot_name', 'slot_type', 'is_active');
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "{$field} = ?";
                    $updateValues[] = $data[$field];
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception("No valid fields provided for update");
            }
            
            // Add slot ID to values
            $updateValues[] = $slotId;
            
            // Execute update
            $result = $this->db->execute("
                UPDATE time_slots 
                SET " . implode(', ', $updateFields) . "
                WHERE slot_id = ?
            ", $updateValues);
            
            if ($result) {
                // Log the update
                if (class_exists('AuditLogger')) {
                    AuditLogger::log('UPDATE_TIMESLOT', 'time_slots', $slotId, $existingSlot, $data);
                }
                
                return array(
                    'success' => true,
                    'message' => 'Time slot updated successfully'
                );
            } else {
                throw new Exception("Failed to update time slot");
            }
            
        } catch (Exception $e) {
            error_log("TimeSlot::updateTimeSlot Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get time slot by ID
     * @param int $slotId Time slot ID
     * @return array|null Time slot data or null if not found
     */
    public function getTimeSlotById($slotId) {
        try {
            $slotId = (int)$slotId;
            return $this->db->fetchRow("SELECT * FROM time_slots WHERE slot_id = ?", array($slotId));
        } catch (Exception $e) {
            error_log("TimeSlot::getTimeSlotById Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all time slots with optional filtering
     * @param array $filters Optional filters
     * @return array List of time slots
     */
    public function getAllTimeSlots($filters = array()) {
        try {
            $whereConditions = array();
            $params = array();
            
            // Add filters
            if (isset($filters['day_of_week']) && !empty($filters['day_of_week'])) {
                $whereConditions[] = "day_of_week = ?";
                $params[] = $filters['day_of_week'];
            }
            
            if (isset($filters['slot_type']) && !empty($filters['slot_type'])) {
                $whereConditions[] = "slot_type = ?";
                $params[] = $filters['slot_type'];
            }
            
            if (isset($filters['is_active'])) {
                $whereConditions[] = "is_active = ?";
                $params[] = (int)$filters['is_active'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            return $this->db->fetchAll("
                SELECT * FROM time_slots 
                {$whereClause}
                ORDER BY 
                    FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                    start_time ASC
            ", $params);
            
        } catch (Exception $e) {
            error_log("TimeSlot::getAllTimeSlots Error: " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get time slots for a specific day
     * @param string $dayOfWeek Day of the week
     * @return array List of time slots
     */
    public function getTimeSlotsByDay($dayOfWeek) {
        try {
            return $this->db->fetchAll("
                SELECT * FROM time_slots 
                WHERE day_of_week = ? AND is_active = 1
                ORDER BY start_time ASC
            ", array($dayOfWeek));
        } catch (Exception $e) {
            error_log("TimeSlot::getTimeSlotsByDay Error: " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Check if a time slot conflicts with existing slots
     * @param string $dayOfWeek Day of the week
     * @param string $startTime Start time
     * @param string $endTime End time
     * @param int $excludeSlotId Optional slot ID to exclude from conflict check
     * @return bool True if there's a conflict
     */
    public function hasConflict($dayOfWeek, $startTime, $endTime, $excludeSlotId = null) {
        try {
            $params = array($dayOfWeek, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime, $startTime, $endTime);
            $query = "
                SELECT slot_id FROM time_slots 
                WHERE day_of_week = ? 
                AND (
                    (start_time < ? AND end_time > ?) OR
                    (start_time < ? AND end_time > ?) OR
                    (start_time >= ? AND start_time < ?) OR
                    (end_time > ? AND end_time <= ?)
                )
            ";
            
            if ($excludeSlotId) {
                $query .= " AND slot_id != ?";
                $params[] = (int)$excludeSlotId;
            }
            
            $conflict = $this->db->fetchRow($query, $params);
            return !empty($conflict);
            
        } catch (Exception $e) {
            error_log("TimeSlot::hasConflict Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get usage statistics for time slots
     * @return array Usage statistics
     */
    public function getUsageStatistics() {
        try {
            return $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_slots,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_slots,
                    COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_slots,
                    COUNT(CASE WHEN slot_type = 'regular' THEN 1 END) as regular_slots,
                    COUNT(CASE WHEN slot_type = 'break' THEN 1 END) as break_slots,
                    COUNT(CASE WHEN slot_type = 'lunch' THEN 1 END) as lunch_slots,
                    AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration_minutes
                FROM time_slots
            ");
        } catch (Exception $e) {
            error_log("TimeSlot::getUsageStatistics Error: " . $e->getMessage());
            return array(
                'total_slots' => 0,
                'active_slots' => 0,
                'inactive_slots' => 0,
                'regular_slots' => 0,
                'break_slots' => 0,
                'lunch_slots' => 0,
                'avg_duration_minutes' => 0
            );
        }
    }
    
    /**
     * Get available time slots for scheduling
     * @param string $dayOfWeek Optional day filter
     * @return array Available time slots
     */
    public function getAvailableSlots($dayOfWeek = null) {
        try {
            $whereClause = "WHERE is_active = 1 AND slot_type = 'regular'";
            $params = array();
            
            if ($dayOfWeek) {
                $whereClause .= " AND day_of_week = ?";
                $params[] = $dayOfWeek;
            }
            
            return $this->db->fetchAll("
                SELECT * FROM time_slots 
                {$whereClause}
                ORDER BY 
                    FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                    start_time ASC
            ", $params);
            
        } catch (Exception $e) {
            error_log("TimeSlot::getAvailableSlots Error: " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Validate time slot data
     * @param array $data Time slot data
     * @return array Validation result
     */
    public function validateTimeSlotData($data) {
        $errors = array();
        
        // Required fields
        $requiredFields = array('day_of_week', 'start_time', 'end_time', 'slot_name');
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Field {$field} is required";
            }
        }
        
        // Day of week validation
        $validDays = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
        if (!empty($data['day_of_week']) && !in_array($data['day_of_week'], $validDays)) {
            $errors[] = "Invalid day of week";
        }
        
        // Time format validation
        if (!empty($data['start_time']) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $data['start_time'])) {
            $errors[] = "Invalid start time format (HH:MM:SS required)";
        }
        
        if (!empty($data['end_time']) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $data['end_time'])) {
            $errors[] = "Invalid end time format (HH:MM:SS required)";
        }
        
        // Time range validation
        if (!empty($data['start_time']) && !empty($data['end_time'])) {
            if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
                $errors[] = "Start time must be before end time";
            }
        }
        
        // Slot type validation
        if (!empty($data['slot_type'])) {
            $validTypes = array('regular', 'break', 'lunch');
            if (!in_array($data['slot_type'], $validTypes)) {
                $errors[] = "Invalid slot type. Must be one of: " . implode(', ', $validTypes);
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
}
?>