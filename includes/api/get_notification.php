<?php
/**
 * Get Notification Details API
 * Timetable Management System
 * 
 * Returns notification details in JSON format for modal display
 * Used by the notification index page for viewing full notification details
 */

// Set JSON response header
header('Content-Type: application/json');

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Notification.php';

// Function to send JSON response and exit
function sendResponse($success, $message = '', $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'notification' => $data
    ]);
    exit;
}

try {
    // Ensure user is logged in and has admin role
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        sendResponse(false, 'Authentication required');
    }

    $currentUserId = $_SESSION['user_id'];
    $currentUserRole = $_SESSION['role'];

    // Admin can view all notifications, others can only view notifications targeted to them
    if (!in_array($currentUserRole, ['admin', 'faculty', 'student'])) {
        sendResponse(false, 'Invalid user role');
    }

    // Get notification ID from request
    $notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$notificationId) {
        sendResponse(false, 'Notification ID is required');
    }

    // Initialize notification manager
    $notificationManager = new Notification();

    // Get notification details
    if ($currentUserRole === 'admin') {
        // Admin can view any notification
        $notification = $notificationManager->getNotificationById($notificationId);
    } else {
        // Other users can only view notifications targeted to them
        $notification = $notificationManager->getNotificationById($notificationId, $currentUserId, $currentUserRole);
    }

    if (!$notification) {
        sendResponse(false, 'Notification not found or access denied');
    }

    // Format dates for display
    $notification['created_at_formatted'] = date('F j, Y g:i A', strtotime($notification['created_at']));
    
    if ($notification['expires_at']) {
        $notification['expires_at_formatted'] = date('F j, Y g:i A', strtotime($notification['expires_at']));
        $notification['is_expired'] = strtotime($notification['expires_at']) < time();
    } else {
        $notification['expires_at_formatted'] = null;
        $notification['is_expired'] = false;
    }

    if ($notification['read_at']) {
        $notification['read_at_formatted'] = date('F j, Y g:i A', strtotime($notification['read_at']));
    } else {
        $notification['read_at_formatted'] = null;
    }

    // Add time ago information
    $notification['created_ago'] = timeAgo($notification['created_at']);

    // Format message for HTML display (convert line breaks)
    $notification['message_html'] = nl2br(htmlspecialchars($notification['message']));

    // Add status information
    $notification['status_text'] = $notification['is_active'] ? 'Active' : 'Inactive';
    $notification['status_class'] = $notification['is_active'] ? 'success' : 'secondary';

    // Add type and priority display information
    $notification['type_display'] = ucfirst($notification['type']);
    $notification['priority_display'] = ucfirst($notification['priority']);
    $notification['target_role_display'] = ucfirst($notification['target_role']);

    // Add related information if available
    if ($notification['related_table'] && $notification['related_id']) {
        $notification['related_info'] = [
            'table' => $notification['related_table'],
            'id' => $notification['related_id'],
            'display' => ucfirst($notification['related_table']) . ' #' . $notification['related_id']
        ];
    } else {
        $notification['related_info'] = null;
    }

    // If it's not an admin viewing and notification is unread, mark as read
    if ($currentUserRole !== 'admin' && !$notification['is_read']) {
        $notificationManager->markAsRead($notificationId, $currentUserId);
        $notification['is_read'] = 1;
        $notification['read_at'] = date('Y-m-d H:i:s');
        $notification['read_at_formatted'] = date('F j, Y g:i A');
    }

    // Send successful response with notification data
    sendResponse(true, 'Notification retrieved successfully', $notification);

} catch (Exception $e) {
    error_log("Get Notification API Error: " . $e->getMessage());
    sendResponse(false, 'An error occurred while retrieving notification details');
}

/**
 * Helper function for time ago display
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minute' . (floor($time/60) == 1 ? '' : 's') . ' ago';
    if ($time < 86400) return floor($time/3600) . ' hour' . (floor($time/3600) == 1 ? '' : 's') . ' ago';
    if ($time < 2592000) return floor($time/86400) . ' day' . (floor($time/86400) == 1 ? '' : 's') . ' ago';
    if ($time < 31536000) return floor($time/2592000) . ' month' . (floor($time/2592000) == 1 ? '' : 's') . ' ago';
    
    return floor($time/31536000) . ' year' . (floor($time/31536000) == 1 ? '' : 's') . ' ago';
}
?>