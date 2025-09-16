<?php
/**
 * User Management API
 * Handles user approval/rejection actions
 */

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session and check authentication
session_start();

try {
    // Verify admin access
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access');
    }

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $action = $input['action'] ?? '';
    $userId = $input['user_id'] ?? 0;

    if (!in_array($action, ['approve', 'reject']) || !$userId) {
        throw new Exception('Invalid parameters');
    }

    $db = Database::getInstance();
    $adminId = $_SESSION['user_id'];

    // Get user details before action
    $user = $db->fetchRow("
        SELECT user_id, username, email, role, status 
        FROM users 
        WHERE user_id = ? AND status = 'pending'
    ", [$userId]);

    if (!$user) {
        throw new Exception('User not found or not pending approval');
    }

    if ($action === 'approve') {
        // Approve user
        $db->execute("
            UPDATE users 
            SET status = 'active', 
                approved_by = ?, 
                approved_at = NOW() 
            WHERE user_id = ?
        ", [$adminId, $userId]);

        // Log the approval
        $db->execute("
            INSERT INTO audit_logs (user_id, action, table_affected, record_id, description, timestamp)
            VALUES (?, 'USER_APPROVED', 'users', ?, ?, NOW())
        ", [$adminId, $userId, "Approved user: {$user['username']} ({$user['role']})"]);

        $response = [
            'success' => true,
            'message' => 'User approved successfully',
            'action' => 'approved',
            'user' => [
                'id' => $userId,
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ];

    } else { // reject
        // Reject user - update status instead of deleting
        $db->execute("
            UPDATE users 
            SET status = 'rejected', 
                approved_by = ?, 
                approved_at = NOW() 
            WHERE user_id = ?
        ", [$adminId, $userId]);

        // Log the rejection
        $db->execute("
            INSERT INTO audit_logs (user_id, action, table_affected, record_id, description, timestamp)
            VALUES (?, 'USER_REJECTED', 'users', ?, ?, NOW())
        ", [$adminId, $userId, "Rejected user: {$user['username']} ({$user['role']})"]);

        $response = [
            'success' => true,
            'message' => 'User rejected successfully',
            'action' => 'rejected',
            'user' => [
                'id' => $userId,
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>