<?php
// Admin Classrooms - Activate/Deactivate Handler
session_start();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Classroom.php';

// Authz
User::requireLogin();
User::requireRole('admin');

$db = Database::getInstance();
$classroomManager = new Classroom();
$userId = User::getCurrentUserId();

// Validate required parameters
if (!isset($_GET['action']) || !isset($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Invalid request parameters.'];
    header('Location: index.php');
    exit;
}

$classroomId = (int)$_GET['id'];
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
            $result = $classroomManager->updateClassroom($classroomId, ['is_active' => 1]);
            if (!$result || ($result['success'] ?? false) !== true) {
                throw new Exception($result['message'] ?? 'Failed to activate classroom.');
            }
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Classroom has been activated successfully.'];
            break;

        case 'deactivate':
            // Optional: Add any business rules to prevent deactivation if needed
            $result = $classroomManager->updateClassroom($classroomId, ['is_active' => 0]);
            if (!$result || ($result['success'] ?? false) !== true) {
                throw new Exception($result['message'] ?? 'Failed to deactivate classroom.');
            }
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Classroom has been deactivated successfully.'];
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
    $queryParams['updated_id'] = $classroomId;

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
