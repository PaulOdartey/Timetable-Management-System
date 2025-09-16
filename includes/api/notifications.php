<?php
/**
 * General Notifications API
 * File Location: /includes/api/notifications.php
 * 
 * Handles notification operations for both students and faculty
 * Timetable Management System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Start session and include required files
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';

// Ensure user is logged in
if (!User::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get current user info
$userId = User::getCurrentUserId();
$userRole = User::getCurrentUserRole();
$db = Database::getInstance();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Debug information (remove in production)
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("Notifications API Debug - Method: $method, Action: $action");
    error_log("GET params: " . print_r($_GET, true));
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        error_log("POST input: " . $input);
    }
}

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $db, $userId, $userRole);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            handlePostRequest($input, $db, $userId, $userRole);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false, 
                'error' => 'Method not allowed',
                'debug' => [
                    'method' => $method,
                    'action' => $action,
                    'allowed_methods' => ['GET', 'POST']
                ]
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Internal server error',
        'debug' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

/**
 * Handle GET requests
 */
function handleGetRequest($action, $db, $userId, $userRole) {
    // If no action provided, default to stats
    if (empty($action)) {
        $action = 'stats';
    }
    
    switch ($action) {
        case 'stats':
            getNotificationStats($db, $userId, $userRole);
            break;
            
        case 'get':
            getNotificationDetails($db, $userId, $userRole);
            break;
            
        case 'list':
            getNotificationsList($db, $userId, $userRole);
            break;
            
        case 'unread':
            getUnreadNotifications($db, $userId, $userRole);
            break;
            
        case 'test':
            // Test endpoint to verify API is working
            echo json_encode([
                'success' => true,
                'message' => 'API is working',
                'user_id' => $userId,
                'user_role' => $userRole,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'Invalid action',
                'debug' => [
                    'received_action' => $action,
                    'valid_actions' => ['stats', 'get', 'list', 'unread', 'test']
                ]
            ]);
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($input, $db, $userId, $userRole) {
    $action = $input['action'] ?? '';
    
    // Debug POST requests
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("POST Request - Action: $action, Input: " . print_r($input, true));
    }
    
    switch ($action) {
        case 'mark_read':
            markNotificationAsRead($input, $db, $userId, $userRole);
            break;
            
        case 'bulk_mark_read':
            bulkMarkAsRead($input, $db, $userId, $userRole);
            break;
            
        case 'mark_all_read':
            markAllAsRead($db, $userId, $userRole);
            break;
            
        case 'delete':
            deleteNotification($input, $db, $userId, $userRole);
            break;
            
        case 'bulk_delete':
            bulkDeleteNotifications($input, $db, $userId, $userRole);
            break;
            
        case 'create':
            createNotification($input, $db, $userId, $userRole);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'Invalid action',
                'debug' => [
                    'received_action' => $action,
                    'valid_actions' => ['mark_read', 'bulk_mark_read', 'mark_all_read', 'delete', 'bulk_delete', 'create'],
                    'input_data' => $input
                ]
            ]);
            break;
    }
}

/**
 * Get notification statistics
 */
function getNotificationStats($db, $userId, $userRole) {
    try {
        $whereClause = buildUserNotificationFilter($userId, $userRole);
        
        $stats = [
            'total' => $db->fetchColumn("
                SELECT COUNT(*) FROM notifications 
                WHERE {$whereClause} AND is_active = 1
            ", [$userId]),
            
            'unread' => $db->fetchColumn("
                SELECT COUNT(*) FROM notifications 
                WHERE {$whereClause} AND is_read = 0 AND is_active = 1
            ", [$userId]),
            
            'today' => $db->fetchColumn("
                SELECT COUNT(*) FROM notifications 
                WHERE {$whereClause} AND is_active = 1 AND DATE(created_at) = CURDATE()
            ", [$userId]),
            
            'this_week' => $db->fetchColumn("
                SELECT COUNT(*) FROM notifications 
                WHERE {$whereClause} AND is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)
            ", [$userId]),
            
            'this_month' => $db->fetchColumn("
                SELECT COUNT(*) FROM notifications 
                WHERE {$whereClause} AND is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ", [$userId])
        ];
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        error_log("Error getting notification stats: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to get notification statistics']);
    }
}

/**
 * Get notification details
 */
function getNotificationDetails($db, $userId, $userRole) {
    try {
        $notificationId = (int)($_GET['id'] ?? 0);
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'error' => 'Notification ID required']);
            return;
        }
        
        $whereClause = buildUserNotificationFilter($userId, $userRole);
        
        $notification = $db->fetchRow("
            SELECT * FROM notifications 
            WHERE notification_id = ? AND {$whereClause} AND is_active = 1
        ", [$notificationId, $userId]);
        
        if (!$notification) {
            echo json_encode(['success' => false, 'error' => 'Notification not found']);
            return;
        }
        
        echo json_encode(['success' => true, 'notification' => $notification]);
    } catch (Exception $e) {
        error_log("Error getting notification details: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to get notification details']);
    }
}

/**
 * Get notifications list with pagination
 */
function getNotificationsList($db, $userId, $userRole) {
    try {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(5, (int)($_GET['limit'] ?? 15)));
        $offset = ($page - 1) * $limit;
        
        $filter = $_GET['filter'] ?? 'all'; // all, unread, read
        $type = $_GET['type'] ?? 'all';
        $search = trim($_GET['search'] ?? '');
        
        $whereClause = buildUserNotificationFilter($userId, $userRole);
        $params = [$userId];
        
        // Add filters
        if ($filter === 'unread') {
            $whereClause .= " AND is_read = 0";
        } elseif ($filter === 'read') {
            $whereClause .= " AND is_read = 1";
        }
        
        if ($type !== 'all') {
            $whereClause .= " AND type = ?";
            $params[] = $type;
        }
        
        if (!empty($search)) {
            $whereClause .= " AND (title LIKE ? OR message LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        // Get total count
        $totalQuery = "SELECT COUNT(*) as total FROM notifications WHERE {$whereClause} AND is_active = 1";
        $totalResult = $db->fetchRow($totalQuery, $params);
        $total = $totalResult['total'] ?? 0;
        
        // Get notifications
        $notificationsQuery = "
            SELECT *, 
                   CASE 
                       WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'today'
                       WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK) THEN 'this_week'
                       WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 'this_month'
                       ELSE 'older'
                   END as time_group
            FROM notifications 
            WHERE {$whereClause} AND is_active = 1
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $limit;
        $params[] = $offset;
        
        $notifications = $db->fetchAll($notificationsQuery, $params);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_items' => $total,
                'per_page' => $limit
            ]
        ]);
    } catch (Exception $e) {
        error_log("Error getting notifications list: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to get notifications list']);
    }
}

/**
 * Get unread notifications for real-time updates
 */
function getUnreadNotifications($db, $userId, $userRole) {
    try {
        $limit = min(10, max(1, (int)($_GET['limit'] ?? 5)));
        $whereClause = buildUserNotificationFilter($userId, $userRole);
        
        $notifications = $db->fetchAll("
            SELECT notification_id, title, message, type, priority, created_at 
            FROM notifications 
            WHERE {$whereClause} AND is_read = 0 AND is_active = 1
            ORDER BY created_at DESC
            LIMIT ?
        ", [$userId, $limit]);
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
    } catch (Exception $e) {
        error_log("Error getting unread notifications: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to get unread notifications']);
    }
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($input, $db, $userId, $userRole) {
    try {
        $notificationId = (int)($input['notification_id'] ?? 0);
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'error' => 'Notification ID required']);
            return;
        }
        
        // Verify the notification belongs to the user
        $whereClause = buildUserNotificationFilter($userId, $userRole);
        
        $notification = $db->fetchRow("
            SELECT notification_id FROM notifications 
            WHERE notification_id = ? AND {$whereClause} AND is_active = 1
        ", [$notificationId, $userId]);
        
        if (!$notification) {
            echo json_encode(['success' => false, 'error' => 'Notification not found']);
            return;
        }
        
        // Mark as read
        $updated = $db->execute("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE notification_id = ?
        ", [$notificationId]);
        
        if ($updated) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
        }
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
    }
}

/**
 * Bulk mark notifications as read
 */
function bulkMarkAsRead($input, $db, $userId, $userRole) {
    try {
        $notificationIds = $input['notification_ids'] ?? [];
        
        if (empty($notificationIds) || !is_array($notificationIds)) {
            echo json_encode(['success' => false, 'error' => 'Notification IDs required']);
            return;
        }
        
        // Sanitize IDs
        $notificationIds = array_map('intval', $notificationIds);
        $notificationIds = array_filter($notificationIds, function($id) { return $id > 0; });
        
        if (empty($notificationIds)) {
            echo json_encode(['success' => false, 'error' => 'Valid notification IDs required']);
            return;
        }
        
        $whereClause = buildUserNotificationFilter($userId, $userRole);
        $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
        
        // Verify notifications belong to user
        $params = array_merge($notificationIds, [$userId]);
        $validNotifications = $db->fetchAll("
            SELECT notification_id FROM notifications 
            WHERE notification_id IN ({$placeholders}) AND {$whereClause} AND is_active = 1
        ", $params);
        
        $validIds = array_column($validNotifications, 'notification_id');
        
        if (empty($validIds)) {
            echo json_encode(['success' => false, 'error' => 'No valid notifications found']);
            return;
        }
        
        // Mark as read
        $placeholders = str_repeat('?,', count($validIds) - 1) . '?';
        $updated = $db->execute("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE notification_id IN ({$placeholders})
        ", $validIds);
        
        if ($updated) {
            echo json_encode([
                'success' => true, 
                'message' => 'Notifications marked as read',
                'count' => count($validIds)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to mark notifications as read']);
        }
    } catch (Exception $e) {
        error_log("Error bulk marking notifications as read: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to mark notifications as read']);
    }
}

/**
 * Mark all notifications as read
 */
function markAllAsRead($db, $userId, $userRole) {
    try {
        $whereClause = buildUserNotificationFilter($userId, $userRole);
        
        $updated = $db->execute("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE {$whereClause} AND is_read = 0 AND is_active = 1
        ", [$userId]);
        
        if ($updated !== false) {
            echo json_encode([
                'success' => true, 
                'message' => 'All notifications marked as read',
                'count' => $updated
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to mark all notifications as read']);
        }
    } catch (Exception $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to mark all notifications as read']);
    }
}

/**
 * Delete notification (soft delete)
 */
function deleteNotification($input, $db, $userId, $userRole) {
    try {
        $notificationId = (int)($input['notification_id'] ?? 0);
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'error' => 'Notification ID required']);
            return;
        }
        
        // For regular users, soft delete by setting is_active = 0
        // Only admins can hard delete notifications
        $whereClause = buildUserNotificationFilter($userId, $userRole);
        
        $notification = $db->fetchRow("
            SELECT notification_id FROM notifications 
            WHERE notification_id = ? AND {$whereClause} AND is_active = 1
        ", [$notificationId, $userId]);
        
        if (!$notification) {
            echo json_encode(['success' => false, 'error' => 'Notification not found']);
            return;
        }
        
        // Soft delete - mark as inactive
        $deleted = $db->execute("
            UPDATE notifications 
            SET is_active = 0 
            WHERE notification_id = ?
        ", [$notificationId]);
        
        if ($deleted) {
            echo json_encode(['success' => true, 'message' => 'Notification deleted']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete notification']);
        }
    } catch (Exception $e) {
        error_log("Error deleting notification: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to delete notification']);
    }
}

/**
 * Bulk delete notifications
 */
function bulkDeleteNotifications($input, $db, $userId, $userRole) {
    try {
        $notificationIds = $input['notification_ids'] ?? [];
        
        if (empty($notificationIds) || !is_array($notificationIds)) {
            echo json_encode(['success' => false, 'error' => 'Notification IDs required']);
            return;
        }
        
        // Sanitize IDs
        $notificationIds = array_map('intval', $notificationIds);
        $notificationIds = array_filter($notificationIds, function($id) { return $id > 0; });
        
        if (empty($notificationIds)) {
            echo json_encode(['success' => false, 'error' => 'Valid notification IDs required']);
            return;
        }
        
        $whereClause = buildUserNotificationFilter($userId, $userRole);
        $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
        
        // Verify notifications belong to user
        $params = array_merge($notificationIds, [$userId]);
        $validNotifications = $db->fetchAll("
            SELECT notification_id FROM notifications 
            WHERE notification_id IN ({$placeholders}) AND {$whereClause} AND is_active = 1
        ", $params);
        
        $validIds = array_column($validNotifications, 'notification_id');
        
        if (empty($validIds)) {
            echo json_encode(['success' => false, 'error' => 'No valid notifications found']);
            return;
        }
        
        // Soft delete
        $placeholders = str_repeat('?,', count($validIds) - 1) . '?';
        $deleted = $db->execute("
            UPDATE notifications 
            SET is_active = 0 
            WHERE notification_id IN ({$placeholders})
        ", $validIds);
        
        if ($deleted !== false) {
            echo json_encode([
                'success' => true, 
                'message' => 'Notifications deleted',
                'count' => count($validIds)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete notifications']);
        }
    } catch (Exception $e) {
        error_log("Error bulk deleting notifications: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to delete notifications']);
    }
}

/**
 * Create notification (admin only)
 */
function createNotification($input, $db, $userId, $userRole) {
    try {
        // Only admins can create notifications
        if ($userRole !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Unauthorized to create notifications']);
            return;
        }
        
        $title = trim($input['title'] ?? '');
        $message = trim($input['message'] ?? '');
        $type = $input['type'] ?? 'info';
        $targetRole = $input['target_role'] ?? 'all';
        $targetUserId = !empty($input['target_user_id']) ? (int)$input['target_user_id'] : null;
        $priority = $input['priority'] ?? 'normal';
        $expiresAt = !empty($input['expires_at']) ? $input['expires_at'] : null;
        
        // Validate required fields
        if (empty($title) || empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Title and message are required']);
            return;
        }
        
        // Validate enum values
        $validTypes = ['info', 'success', 'warning', 'error', 'urgent'];
        $validRoles = ['all', 'admin', 'faculty', 'student'];
        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        
        if (!in_array($type, $validTypes)) {
            $type = 'info';
        }
        
        if (!in_array($targetRole, $validRoles)) {
            $targetRole = 'all';
        }
        
        if (!in_array($priority, $validPriorities)) {
            $priority = 'normal';
        }
        
        // Insert notification
        $notificationId = $db->insert("
            INSERT INTO notifications (
                title, message, type, target_role, target_user_id, 
                priority, expires_at, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ", [
            $title, $message, $type, $targetRole, $targetUserId,
            $priority, $expiresAt, $userId
        ]);
        
        if ($notificationId) {
            echo json_encode([
                'success' => true, 
                'message' => 'Notification created successfully',
                'notification_id' => $notificationId
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create notification']);
        }
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to create notification']);
    }
}

/**
 * Build user notification filter based on role
 */
function buildUserNotificationFilter($userId, $userRole) {
    switch ($userRole) {
        case 'admin':
            // Admins see all notifications plus those targeted to them specifically
            return "(target_role = 'admin' OR target_role = 'all' OR target_user_id = ?)";
            
        case 'faculty':
            // Faculty see faculty and all notifications plus those targeted to them specifically
            return "(target_role = 'faculty' OR target_role = 'all' OR target_user_id = ?)";
            
        case 'student':
            // Students see student and all notifications plus those targeted to them specifically
            return "(target_role = 'student' OR target_role = 'all' OR target_user_id = ?)";
            
        default:
            // Default to only user-specific notifications
            return "target_user_id = ?";
    }
}

/**
 * Validate notification access
 */
function validateNotificationAccess($notificationId, $userId, $userRole, $db) {
    $whereClause = buildUserNotificationFilter($userId, $userRole);
    
    $notification = $db->fetchRow("
        SELECT notification_id FROM notifications 
        WHERE notification_id = ? AND {$whereClause} AND is_active = 1
    ", [$notificationId, $userId]);
    
    return !empty($notification);
}

/**
 * Log notification activity
 */
function logNotificationActivity($action, $notificationId, $userId, $db) {
    try {
        $db->execute("
            INSERT INTO audit_logs (
                user_id, action, table_affected, record_id, 
                description, timestamp
            ) VALUES (?, ?, 'notifications', ?, ?, NOW())
        ", [
            $userId,
            'NOTIFICATION_' . strtoupper($action),
            $notificationId,
            "Notification {$action} operation performed"
        ]);
    } catch (Exception $e) {
        error_log("Error logging notification activity: " . $e->getMessage());
    }
}

/**
 * Get notification statistics for dashboard
 */
function getDashboardNotificationStats($db, $userId, $userRole) {
    try {
        $whereClause = buildUserNotificationFilter($userId, $userRole);
        
        $stats = [
            'unread_count' => $db->fetchColumn("
                SELECT COUNT(*) FROM notifications 
                WHERE {$whereClause} AND is_read = 0 AND is_active = 1
            ", [$userId]),
            
            'urgent_count' => $db->fetchColumn("
                SELECT COUNT(*) FROM notifications 
                WHERE {$whereClause} AND priority = 'urgent' AND is_read = 0 AND is_active = 1
            ", [$userId]),
            
            'recent_notifications' => $db->fetchAll("
                SELECT notification_id, title, type, priority, created_at 
                FROM notifications 
                WHERE {$whereClause} AND is_active = 1
                ORDER BY created_at DESC 
                LIMIT 5
            ", [$userId])
        ];
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting dashboard notification stats: " . $e->getMessage());
        return [
            'unread_count' => 0,
            'urgent_count' => 0,
            'recent_notifications' => []
        ];
    }
}

/**
 * Clean up old notifications (for admin use)
 */
function cleanupOldNotifications($db, $daysOld = 90) {
    try {
        // Only clean up read notifications older than specified days
        $deleted = $db->execute("
            UPDATE notifications 
            SET is_active = 0 
            WHERE is_read = 1 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND is_active = 1
        ", [$daysOld]);
        
        return $deleted;
    } catch (Exception $e) {
        error_log("Error cleaning up old notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email notification if user has email notifications enabled
 */
function sendEmailNotification($notificationId, $db) {
    try {
        // Get notification details
        $notification = $db->fetchRow("
            SELECT n.*, u.email, u.username,
                   CASE 
                       WHEN n.target_user_id IS NOT NULL THEN u.email
                       ELSE NULL
                   END as recipient_email
            FROM notifications n
            LEFT JOIN users u ON n.target_user_id = u.user_id
            WHERE n.notification_id = ?
        ", [$notificationId]);
        
        if (!$notification) {
            return false;
        }
        
        // Check if user has email notifications enabled
        if ($notification['target_user_id']) {
            $emailEnabled = $db->fetchColumn("
                SELECT setting_value 
                FROM system_settings 
                WHERE setting_key = 'user_pref_notifications_email' 
                AND updated_by = ?
            ", [$notification['target_user_id']]);
            
            if ($emailEnabled !== 'true') {
                return false; // User has email notifications disabled
            }
        }
        
        // Send email notification using EmailService
        if (class_exists('EmailService')) {
            $emailService = new EmailService();
            
            $subject = $notification['title'];
            $message = $notification['message'];
            
            if ($notification['recipient_email']) {
                return $emailService->sendNotificationEmail(
                    $notification['recipient_email'],
                    $notification['username'],
                    $subject,
                    $message
                );
            }
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error sending email notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notification preferences for user
 */
function getNotificationPreferences($userId, $db) {
    try {
        $preferences = $db->fetchAll("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE 'user_pref_notifications_%' 
            AND updated_by = ?
        ", [$userId]);
        
        $prefs = [];
        foreach ($preferences as $pref) {
            $key = str_replace('user_pref_notifications_', '', $pref['setting_key']);
            $prefs[$key] = $pref['setting_value'] === 'true';
        }
        
        return $prefs;
    } catch (Exception $e) {
        error_log("Error getting notification preferences: " . $e->getMessage());
        return [];
    }
}

/**
 * Update notification preferences
 */
function updateNotificationPreferences($userId, $preferences, $db) {
    try {
        $db->beginTransaction();
        
        foreach ($preferences as $key => $value) {
            $settingKey = 'user_pref_notifications_' . $key;
            $settingValue = $value ? 'true' : 'false';
            
            $db->execute("
                INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)
            ", [$settingKey, $settingValue, $userId]);
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error updating notification preferences: " . $e->getMessage());
        return false;
    }
}

// Additional helper functions for specific notification types

/**
 * Create timetable change notification
 */
function createTimetableChangeNotification($changeDetails, $affectedUsers, $db, $createdBy) {
    try {
        $title = "Timetable Update";
        $message = "Your schedule has been updated. " . $changeDetails;
        
        foreach ($affectedUsers as $userId) {
            $notificationId = $db->insert("
                INSERT INTO notifications (
                    title, message, type, target_role, target_user_id,
                    priority, created_by, created_at
                ) VALUES (?, ?, 'warning', NULL, ?, 'high', ?, NOW())
            ", [$title, $message, $userId, $createdBy]);
            
            if ($notificationId) {
                // Send email notification if enabled
                sendEmailNotification($notificationId, $db);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating timetable change notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create system maintenance notification
 */
function createMaintenanceNotification($maintenanceDetails, $db, $createdBy) {
    try {
        $title = "System Maintenance Scheduled";
        $message = $maintenanceDetails;
        
        $notificationId = $db->insert("
            INSERT INTO notifications (
                title, message, type, target_role, target_user_id,
                priority, created_by, created_at
            ) VALUES (?, ?, 'warning', 'all', NULL, 'normal', ?, NOW())
        ", [$title, $message, $createdBy]);
        
        return $notificationId !== false;
    } catch (Exception $e) {
        error_log("Error creating maintenance notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create enrollment notification
 */
function createEnrollmentNotification($studentId, $subjectName, $action, $db, $createdBy) {
    try {
        $title = "Enrollment Update";
        $message = "You have been {$action} in {$subjectName}.";
        
        $notificationId = $db->insert("
            INSERT INTO notifications (
                title, message, type, target_role, target_user_id,
                priority, created_by, created_at
            ) VALUES (?, ?, 'info', NULL, ?, 'normal', ?, NOW())
        ", [$title, $message, $studentId, $createdBy]);
        
        if ($notificationId) {
            sendEmailNotification($notificationId, $db);
        }
        
        return $notificationId !== false;
    } catch (Exception $e) {
        error_log("Error creating enrollment notification: " . $e->getMessage());
        return false;
    }
}

?>