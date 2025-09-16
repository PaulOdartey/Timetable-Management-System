<?php
/**
 * Navigation Badges API
 * Provides real-time badge counts for navigation elements
 * Timetable Management System
 */

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output, log them instead

// Start session and check authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['role']) || !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit();
}

try {
    // Define system access for included files
    define('SYSTEM_ACCESS', true);

    // Include required files
    require_once '../../config/database.php';

    $db = Database::getInstance();
    $userRole = $_SESSION['role'];
    $userId = $_SESSION['user_id'];
    
    // Initialize badge data
    $badges = [
        'notifications' => 0,
        'pending_registrations' => 0,
        'unread_messages' => 0,
        'schedule_conflicts' => 0,
        'pending_approvals' => 0
    ];
    
    // Get notification count based on user role
    if ($userRole === 'admin') {
        // Admin notifications - simplified query first
        try {
            $result = $db->fetchRow("SELECT COUNT(*) as count FROM notifications WHERE target_role = 'admin' AND is_read = 0");
            $badges['notifications'] = intval($result['count'] ?? 0);
        } catch (Exception $e) {
            // If notifications table doesn't exist, set to 0
            $badges['notifications'] = 0;
        }
        
        // Pending user registrations
        try {
            $result = $db->fetchRow("SELECT COUNT(*) as count FROM users WHERE status = 'pending'");
            $badges['pending_registrations'] = intval($result['count'] ?? 0);
        } catch (Exception $e) {
            $badges['pending_registrations'] = 0;
        }
        
    } elseif ($userRole === 'faculty') {
        // Faculty notifications
        try {
            $result = $db->fetchRow("SELECT COUNT(*) as count FROM notifications WHERE target_role = 'faculty' AND is_read = 0");
            $badges['notifications'] = intval($result['count'] ?? 0);
        } catch (Exception $e) {
            $badges['notifications'] = 0;
        }
        
    } elseif ($userRole === 'student') {
        // Student notifications
        try {
            $result = $db->fetchRow("SELECT COUNT(*) as count FROM notifications WHERE target_role = 'student' AND is_read = 0");
            $badges['notifications'] = intval($result['count'] ?? 0);
        } catch (Exception $e) {
            $badges['notifications'] = 0;
        }
    }
    
    // Calculate total badge count for main notification indicator
    $totalBadges = $badges['notifications'] + $badges['pending_registrations'] + 
                   $badges['schedule_conflicts'] + $badges['pending_approvals'];
    
    // Prepare response
    $response = [
        'success' => true,
        'badges' => $badges,
        'total' => $totalBadges,
        'user_role' => $userRole,
        'user_id' => $userId,
        'timestamp' => time(),
        'debug' => [
            'session_data' => [
                'role' => $userRole,
                'username' => $_SESSION['username'],
                'user_id' => $userId
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error with more details
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    error_log("Navigation Badges API Error: " . json_encode($errorDetails));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage(),
        'debug' => [
            'error_details' => $errorDetails,
            'session_exists' => isset($_SESSION),
            'system_access_defined' => defined('SYSTEM_ACCESS')
        ],
        'badges' => [
            'notifications' => 0,
            'pending_registrations' => 0,
            'unread_messages' => 0,
            'schedule_conflicts' => 0,
            'pending_approvals' => 0
        ],
        'total' => 0
    ]);
}
?>
