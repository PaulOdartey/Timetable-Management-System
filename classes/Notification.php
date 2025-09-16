<?php
/**
 * Notification Class
 * Timetable Management System
 * 
 * Handles all notification operations including creation, retrieval, 
 * marking as read, and notification management with role-based targeting
 */

if (!defined('SYSTEM_ACCESS')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/../config/database.php';

class Notification {
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
            error_log("[Notification] [{$level}] {$message}");
        }
    }
    
    /**
     * Create a new notification
     * 
     * @param array $data Notification data
     * @param int $createdBy User ID of the creator
     * @return array Result with success status and message
     */
    public function createNotification($data, $createdBy) {
        try {
            // Validate required fields
            $required = ['title', 'message'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => ucfirst($field) . ' is required.'
                    ];
                }
            }
            
            // Validate notification type
            $validTypes = ['info', 'success', 'warning', 'error', 'urgent'];
            $type = $data['type'] ?? 'info';
            if (!in_array($type, $validTypes)) {
                $type = 'info';
            }
            
            // Validate target role
            $validRoles = ['all', 'admin', 'faculty', 'student'];
            $targetRole = $data['target_role'] ?? 'all';
            if (!in_array($targetRole, $validRoles)) {
                $targetRole = 'all';
            }
            
            // Validate priority
            $validPriorities = ['low', 'normal', 'high', 'urgent'];
            $priority = $data['priority'] ?? 'normal';
            if (!in_array($priority, $validPriorities)) {
                $priority = 'normal';
            }
            
            // Prepare SQL for insertion
            $sql = "INSERT INTO notifications (
                title, message, type, target_role, target_user_id, 
                related_table, related_id, priority, created_by, 
                expires_at, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                trim($data['title']),
                trim($data['message']),
                $type,
                $targetRole,
                !empty($data['target_user_id']) ? (int)$data['target_user_id'] : null,
                !empty($data['related_table']) ? trim($data['related_table']) : null,
                !empty($data['related_id']) ? (int)$data['related_id'] : null,
                $priority,
                $createdBy,
                !empty($data['expires_at']) ? $data['expires_at'] : null,
                isset($data['is_active']) ? (int)$data['is_active'] : 1
            ];
            
            $this->db->execute($sql, $params);
            $notificationId = $this->db->lastInsertId();
            
            $this->log("Notification created successfully with ID: {$notificationId}");
            
            return [
                'success' => true,
                'message' => 'Notification created successfully.',
                'notification_id' => $notificationId
            ];
            
        } catch (Exception $e) {
            $this->log("Error creating notification: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Failed to create notification: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get notifications for a specific user based on their role and targeting
     * 
     * @param int $userId User ID
     * @param string $userRole User's role
     * @param bool $unreadOnly Whether to fetch only unread notifications
     * @param int $limit Maximum number of notifications to fetch
     * @return array List of notifications
     */
    public function getUserNotifications($userId, $userRole, $unreadOnly = false, $limit = 50) {
        try {
            $sql = "SELECT n.*, 
                           u.username as creator_name,
                           CASE 
                               WHEN u.role = 'admin' THEN CONCAT(ap.first_name, ' ', ap.last_name)
                               WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                               WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                               ELSE u.username
                           END as creator_full_name
                    FROM notifications n
                    LEFT JOIN users u ON n.created_by = u.user_id
                    LEFT JOIN admin_profiles ap ON u.user_id = ap.user_id AND u.role = 'admin'
                    LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
                    LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
                    WHERE n.is_active = 1 
                      AND (n.expires_at IS NULL OR n.expires_at > NOW())
                      AND (
                          n.target_role = 'all' 
                          OR n.target_role = ? 
                          OR n.target_user_id = ?
                      )";
            
            $params = [$userRole, $userId];
            
            if ($unreadOnly) {
                $sql .= " AND n.is_read = 0";
            }
            
            $sql .= " ORDER BY 
                        CASE n.priority 
                            WHEN 'urgent' THEN 1 
                            WHEN 'high' THEN 2 
                            WHEN 'normal' THEN 3 
                            WHEN 'low' THEN 4 
                        END ASC,
                        n.created_at DESC 
                      LIMIT ?";
            
            $params[] = $limit;
            
            $notifications = $this->db->fetchAll($sql, $params);
            
            $this->log("Retrieved " . count($notifications) . " notifications for user {$userId}");
            
            return $notifications;
            
        } catch (Exception $e) {
            $this->log("Error getting user notifications: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Get all notifications (for admin management)
     * 
     * @param array $filters Filtering options
     * @param int $page Page number for pagination
     * @param int $perPage Number of notifications per page
     * @return array Notifications with pagination info
     */
    public function getAllNotifications($filters = [], $page = 1, $perPage = 20) {
        try {
            $whereConditions = ["1=1"]; // Show all notifications by default (like users system)
            $params = [];
            
            // Apply filters
            if (!empty($filters['type'])) {
                $whereConditions[] = "n.type = ?";
                $params[] = $filters['type'];
            }
            
            if (!empty($filters['target_role'])) {
                $whereConditions[] = "n.target_role = ?";
                $params[] = $filters['target_role'];
            }
            
            if (!empty($filters['priority'])) {
                $whereConditions[] = "n.priority = ?";
                $params[] = $filters['priority'];
            }
            
            // Add status filter if specified
            if (isset($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $whereConditions[] = "n.is_active = 1";
                } elseif ($filters['status'] === 'inactive') {
                    $whereConditions[] = "n.is_active = 0";
                }
                // If status is 'all' or empty, show both active and inactive
            }
            
            if (!empty($filters['created_by'])) {
                $whereConditions[] = "n.created_by = ?";
                $params[] = $filters['created_by'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "DATE(n.created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "DATE(n.created_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM notifications n WHERE {$whereClause}";
            $totalResult = $this->db->fetchRow($countSql, $params);
            $total = $totalResult['total'];
            
            // Calculate pagination
            $totalPages = ceil($total / $perPage);
            $offset = ($page - 1) * $perPage;
            
            // Get notifications
            $sql = "SELECT n.*, 
                           u.username as creator_name,
                           CASE 
                               WHEN u.role = 'admin' THEN CONCAT(ap.first_name, ' ', ap.last_name)
                               WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                               WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                               ELSE u.username
                           END as creator_full_name
                    FROM notifications n
                    LEFT JOIN users u ON n.created_by = u.user_id
                    LEFT JOIN admin_profiles ap ON u.user_id = ap.user_id AND u.role = 'admin'
                    LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
                    LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
                    WHERE {$whereClause}
                    ORDER BY 
                        CASE n.priority 
                            WHEN 'urgent' THEN 1 
                            WHEN 'high' THEN 2 
                            WHEN 'normal' THEN 3 
                            WHEN 'low' THEN 4 
                        END ASC,
                        n.created_at DESC 
                    LIMIT ? OFFSET ?";
            
            $params[] = $perPage;
            $params[] = $offset;
            
            $notifications = $this->db->fetchAll($sql, $params);
            
            return [
                'notifications' => $notifications,
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
            $this->log("Error getting all notifications: " . $e->getMessage(), 'error');
            return [
                'notifications' => [],
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
     * Mark notification as read
     * 
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for security)
     * @return array Result with success status
     */
    public function markAsRead($notificationId, $userId) {
        try {
            // First verify the notification exists and user has access to it
            $notification = $this->db->fetchRow("
                SELECT n.*, u.role 
                FROM notifications n
                CROSS JOIN users u 
                WHERE n.notification_id = ? 
                  AND u.user_id = ?
                  AND (
                      n.target_role = 'all' 
                      OR n.target_role = u.role 
                      OR n.target_user_id = u.user_id
                  )
            ", [$notificationId, $userId]);
            
            if (!$notification) {
                return [
                    'success' => false,
                    'message' => 'Notification not found or access denied.'
                ];
            }
            
            // Mark as read
            $sql = "UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE notification_id = ?";
            
            $this->db->execute($sql, [$notificationId]);
            
            $this->log("Notification {$notificationId} marked as read by user {$userId}");
            
            return [
                'success' => true,
                'message' => 'Notification marked as read.'
            ];
            
        } catch (Exception $e) {
            $this->log("Error marking notification as read: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Failed to mark notification as read.'
            ];
        }
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId User ID
     * @param string $userRole User's role
     * @return array Result with success status
     */
    public function markAllAsRead($userId, $userRole) {
        try {
            $sql = "UPDATE notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE is_read = 0 
                      AND is_active = 1
                      AND (expires_at IS NULL OR expires_at > NOW())
                      AND (
                          target_role = 'all' 
                          OR target_role = ? 
                          OR target_user_id = ?
                      )";
            
            $result = $this->db->execute($sql, [$userRole, $userId]);
            $affectedRows = $this->db->getAffectedRows();
            
            $this->log("Marked {$affectedRows} notifications as read for user {$userId}");
            
            return [
                'success' => true,
                'message' => "Marked {$affectedRows} notifications as read.",
                'affected_rows' => $affectedRows
            ];
            
        } catch (Exception $e) {
            $this->log("Error marking all notifications as read: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Failed to mark notifications as read.'
            ];
        }
    }
    
    /**
     * Get notification by ID
     * 
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for access control)
     * @param string $userRole User's role
     * @return array|null Notification data or null if not found
     */
    public function getNotificationById($notificationId, $userId = null, $userRole = null) {
        try {
            $sql = "SELECT n.*, 
                           u.username as creator_name,
                           CASE 
                               WHEN u.role = 'admin' THEN CONCAT(ap.first_name, ' ', ap.last_name)
                               WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                               WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                               ELSE u.username
                           END as creator_full_name
                    FROM notifications n
                    LEFT JOIN users u ON n.created_by = u.user_id
                    LEFT JOIN admin_profiles ap ON u.user_id = ap.user_id AND u.role = 'admin'
                    LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
                    LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
                    WHERE n.notification_id = ?";
            
            $params = [$notificationId];
            
            // Add access control if user info provided
            if ($userId && $userRole) {
                $sql .= " AND (
                            n.target_role = 'all' 
                            OR n.target_role = ? 
                            OR n.target_user_id = ?
                          )";
                $params[] = $userRole;
                $params[] = $userId;
            }
            
            $notification = $this->db->fetchRow($sql, $params);
            
            return $notification ?: null;
            
        } catch (Exception $e) {
            $this->log("Error getting notification by ID: " . $e->getMessage(), 'error');
            return null;
        }
    }
    
    /**
     * Update notification
     * 
     * @param int $notificationId Notification ID
     * @param array $data Updated data
     * @param int $updatedBy User ID making the update
     * @return array Result with success status
     */
    public function updateNotification($notificationId, $data, $updatedBy) {
        try {
            // Verify notification exists
            $notification = $this->db->fetchRow("
                SELECT * FROM notifications WHERE notification_id = ?
            ", [$notificationId]);
            
            if (!$notification) {
                return [
                    'success' => false,
                    'message' => 'Notification not found.'
                ];
            }
            
            $updateFields = [];
            $params = [];
            
            // Build update query dynamically
            if (isset($data['title']) && !empty($data['title'])) {
                $updateFields[] = "title = ?";
                $params[] = trim($data['title']);
            }
            
            if (isset($data['message']) && !empty($data['message'])) {
                $updateFields[] = "message = ?";
                $params[] = trim($data['message']);
            }
            
            if (isset($data['type'])) {
                $validTypes = ['info', 'success', 'warning', 'error', 'urgent'];
                if (in_array($data['type'], $validTypes)) {
                    $updateFields[] = "type = ?";
                    $params[] = $data['type'];
                }
            }
            
            if (isset($data['priority'])) {
                $validPriorities = ['low', 'normal', 'high', 'urgent'];
                if (in_array($data['priority'], $validPriorities)) {
                    $updateFields[] = "priority = ?";
                    $params[] = $data['priority'];
                }
            }
            
            if (isset($data['expires_at'])) {
                $updateFields[] = "expires_at = ?";
                $params[] = $data['expires_at'] ?: null;
            }
            
            if (isset($data['is_active'])) {
                $updateFields[] = "is_active = ?";
                $params[] = (int)$data['is_active'];
            }
            
            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'message' => 'No valid fields to update.'
                ];
            }
            
            $sql = "UPDATE notifications SET " . implode(', ', $updateFields) . " WHERE notification_id = ?";
            $params[] = $notificationId;
            
            $this->db->execute($sql, $params);
            
            $this->log("Notification {$notificationId} updated by user {$updatedBy}");
            
            return [
                'success' => true,
                'message' => 'Notification updated successfully.'
            ];
            
        } catch (Exception $e) {
            $this->log("Error updating notification: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Failed to update notification: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete notification (soft delete)
     * 
     * @param int $notificationId Notification ID
     * @param int $deletedBy User ID performing the deletion
     * @return array Result with success status
     */
    public function deleteNotification($notificationId, $deletedBy) {
        try {
            $sql = "UPDATE notifications SET is_active = 0 WHERE notification_id = ?";
            $this->db->execute($sql, [$notificationId]);
            
            if ($this->db->getAffectedRows() > 0) {
                $this->log("Notification {$notificationId} deleted by user {$deletedBy}");
                return [
                    'success' => true,
                    'message' => 'Notification deleted successfully.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Notification not found or already deleted.'
                ];
            }
            
        } catch (Exception $e) {
            $this->log("Error deleting notification: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Failed to delete notification.'
            ];
        }
    }
    
    /**
     * Get unread notification count for a user
     * 
     * @param int $userId User ID
     * @param string $userRole User's role
     * @return int Number of unread notifications
     */
    public function getUnreadCount($userId, $userRole) {
        try {
            $sql = "SELECT COUNT(*) as count 
                    FROM notifications n
                    WHERE n.is_active = 1 
                      AND n.is_read = 0
                      AND (n.expires_at IS NULL OR n.expires_at > NOW())
                      AND (
                          n.target_role = 'all' 
                          OR n.target_role = ? 
                          OR n.target_user_id = ?
                      )";
            
            $result = $this->db->fetchRow($sql, [$userRole, $userId]);
            return (int)$result['count'];
            
        } catch (Exception $e) {
            $this->log("Error getting unread count: " . $e->getMessage(), 'error');
            return 0;
        }
    }
    
    /**
     * Clean up expired notifications
     * 
     * @return array Result with count of cleaned notifications
     */
    public function cleanupExpiredNotifications() {
        try {
            $sql = "UPDATE notifications 
                    SET is_active = 0 
                    WHERE expires_at IS NOT NULL 
                      AND expires_at <= NOW() 
                      AND is_active = 1";
            
            $this->db->execute($sql);
            $affectedRows = $this->db->getAffectedRows();
            
            $this->log("Cleaned up {$affectedRows} expired notifications");
            
            return [
                'success' => true,
                'message' => "Cleaned up {$affectedRows} expired notifications.",
                'cleaned_count' => $affectedRows
            ];
            
        } catch (Exception $e) {
            $this->log("Error cleaning up expired notifications: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Failed to cleanup expired notifications.'
            ];
        }
    }
    
    /**
     * Get notification statistics
     * 
     * @return array Statistics about notifications
     */
    public function getNotificationStatistics() {
        try {
            $stats = [];
            
            // Total notifications
            $total = $this->db->fetchRow("SELECT COUNT(*) as count FROM notifications WHERE is_active = 1");
            $stats['total'] = (int)$total['count'];
            
            // By type
            $byType = $this->db->fetchAll("
                SELECT type, COUNT(*) as count 
                FROM notifications 
                WHERE is_active = 1 
                GROUP BY type
            ");
            $stats['by_type'] = $byType;
            
            // By priority
            $byPriority = $this->db->fetchAll("
                SELECT priority, COUNT(*) as count 
                FROM notifications 
                WHERE is_active = 1 
                GROUP BY priority
            ");
            $stats['by_priority'] = $byPriority;
            
            // By target role
            $byRole = $this->db->fetchAll("
                SELECT target_role, COUNT(*) as count 
                FROM notifications 
                WHERE is_active = 1 
                GROUP BY target_role
            ");
            $stats['by_target_role'] = $byRole;
            
            // Read vs unread
            $readStats = $this->db->fetchRow("
                SELECT 
                    COUNT(CASE WHEN is_read = 1 THEN 1 END) as read_count,
                    COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread_count
                FROM notifications 
                WHERE is_active = 1
            ");
            $stats['read_count'] = (int)$readStats['read_count'];
            $stats['unread_count'] = (int)$readStats['unread_count'];
            
            return $stats;
            
        } catch (Exception $e) {
            $this->log("Error getting notification statistics: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Send system notification for common events
     * 
     * @param string $eventType Type of event
     * @param array $eventData Data related to the event
     * @param int $createdBy User ID creating the notification
     * @return array Result with success status
     */
    public function sendSystemNotification($eventType, $eventData, $createdBy) {
        try {
            $notificationData = [];
            
            switch ($eventType) {
                case 'user_approved':
                    $notificationData = [
                        'title' => 'Account Approved',
                        'message' => 'Your account has been approved. You can now access the system.',
                        'type' => 'success',
                        'target_role' => $eventData['user_role'] ?? 'all',
                        'target_user_id' => $eventData['user_id'] ?? null,
                        'priority' => 'high'
                    ];
                    break;
                    
                case 'user_rejected':
                    $notificationData = [
                        'title' => 'Account Registration Rejected',
                        'message' => 'Your account registration has been rejected. Please contact administration for more information.',
                        'type' => 'error',
                        'target_role' => $eventData['user_role'] ?? 'all',
                        'target_user_id' => $eventData['user_id'] ?? null,
                        'priority' => 'high'
                    ];
                    break;
                    
                case 'schedule_created':
                    $notificationData = [
                        'title' => 'New Schedule Created',
                        'message' => 'A new schedule has been created. Please check your timetable for updates.',
                        'type' => 'info',
                        'target_role' => 'all',
                        'related_table' => 'timetables',
                        'related_id' => $eventData['timetable_id'] ?? null,
                        'priority' => 'normal'
                    ];
                    break;
                    
                case 'schedule_updated':
                    $notificationData = [
                        'title' => 'Schedule Updated',
                        'message' => 'Your schedule has been updated. Please review the changes in your timetable.',
                        'type' => 'warning',
                        'target_role' => $eventData['target_role'] ?? 'all',
                        'target_user_id' => $eventData['user_id'] ?? null,
                        'related_table' => 'timetables',
                        'related_id' => $eventData['timetable_id'] ?? null,
                        'priority' => 'high'
                    ];
                    break;
                    
                case 'system_maintenance':
                    $notificationData = [
                        'title' => 'System Maintenance Notice',
                        'message' => $eventData['message'] ?? 'System maintenance is scheduled. Please save your work.',
                        'type' => 'warning',
                        'target_role' => 'all',
                        'priority' => 'urgent',
                        'expires_at' => $eventData['expires_at'] ?? null
                    ];
                    break;
                    
                default:
                    return [
                        'success' => false,
                        'message' => 'Unknown event type: ' . $eventType
                    ];
            }
            
            return $this->createNotification($notificationData, $createdBy);
            
        } catch (Exception $e) {
            $this->log("Error sending system notification: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => 'Failed to send system notification.'
            ];
        }
    }
}
?>