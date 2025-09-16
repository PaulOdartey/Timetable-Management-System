<?php
// Admin Users - Activate/Deactivate Handler
session_start();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';

// Authz
User::requireLogin();
User::requireRole('admin');

$db = Database::getInstance();
$userManager = new User();
$userId = User::getCurrentUserId();

// Validate required parameters
if (!isset($_GET['action']) || !isset($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid request parameters.'];
    header('Location: index.php');
    exit;
}

$targetUserId = (int)$_GET['id'];
$action = $_GET['action'];

// Validate action
if (!in_array($action, ['activate', 'deactivate'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid action specified.'];
    header('Location: index.php');
    exit;
}

try {
    switch ($action) {
        case 'activate':
            $result = $userManager->changeUserStatus($targetUserId, 'active', $userId);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'User has been activated successfully.'];
            break;
            
        case 'deactivate':
            // Security check - prevent deactivating admin users
            $userInfo = $db->fetchRow('SELECT role FROM users WHERE user_id = ?', [$targetUserId]);
            if ($userInfo && $userInfo['role'] === 'admin') {
                throw new Exception('Cannot deactivate admin users for security reasons.');
            }
            // Prevent self-deactivation
            if ($targetUserId == $userId) {
                throw new Exception('You cannot deactivate your own account.');
            }
            $result = $userManager->changeUserStatus($targetUserId, 'inactive', $userId);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'User has been deactivated successfully.'];
            break;
    }
    
    // Build redirect URL with filters preserved and updated_id for auto-scroll
    $returnUrl = $_GET['return'] ?? 'index.php';
    $parsedUrl = parse_url($returnUrl);
    $queryParams = [];
    
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
    }
    
    // Add updated parameter for auto-scroll
    $queryParams['updated'] = '1';
    $queryParams['updated_id'] = $targetUserId;
    
    // Build final redirect URL
    $baseUrl = $parsedUrl['path'] ?? 'index.php';
    $finalUrl = $baseUrl . '?' . http_build_query($queryParams);
    
    header('Location: ' . $finalUrl);
    exit;
    
} catch (Exception $e) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => $e->getMessage()];
    header('Location: index.php');
    exit;
}
?>
