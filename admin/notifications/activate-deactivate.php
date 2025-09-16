<?php
// Admin Notifications - Activate/Deactivate Handler
session_start();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../classes/User.php';
require_once '../../classes/Notification.php';

// Authz
User::requireLogin();
User::requireRole('admin');

$db = Database::getInstance();
$notificationManager = new Notification();
$userId = User::getCurrentUserId();

// Validate required parameters
if (!isset($_GET['action']) || !isset($_GET['id'])) {
    flash_set('error', 'Invalid request parameters.');
    redirect_to('index.php');
}

$targetNotificationId = (int)$_GET['id'];
$action = $_GET['action'];

// Validate action
if (!in_array($action, ['activate', 'deactivate'])) {
    flash_set('error', 'Invalid action specified.');
    redirect_to('index.php');
}

try {
    // Get notification details for validation
    $notification = $notificationManager->getNotificationById($targetNotificationId);
    if (!$notification) {
        throw new Exception('Notification not found.');
    }
    
    switch ($action) {
        case 'activate':
            $result = $notificationManager->updateNotification($targetNotificationId, ['is_active' => 1], $userId);
            if ($result['success']) {
                flash_set('success', 'Notification has been activated successfully.');
            } else {
                throw new Exception($result['message']);
            }
            break;
            
        case 'deactivate':
            $result = $notificationManager->updateNotification($targetNotificationId, ['is_active' => 0], $userId);
            if ($result['success']) {
                flash_set('success', 'Notification has been deactivated successfully.');
            } else {
                throw new Exception($result['message']);
            }
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
    $queryParams['updated_id'] = $targetNotificationId;
    
    // Build final redirect URL
    $baseUrl = $parsedUrl['path'] ?? 'index.php';
    $finalUrl = $baseUrl . '?' . http_build_query($queryParams);
    
    redirect_to($finalUrl);
    
} catch (Exception $e) {
    flash_set('error', $e->getMessage());
    redirect_to('index.php');
}
?>
