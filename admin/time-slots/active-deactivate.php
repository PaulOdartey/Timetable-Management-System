<?php
// Admin Time Slots - Activate/Deactivate Handler
session_start();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/TimeSlot.php';

// Authz
User::requireLogin();
User::requireRole('admin');

$db = Database::getInstance();
$timeSlotManager = new TimeSlot();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$dayParam = isset($_GET['day']) ? trim($_GET['day']) : '';

// Basic validation
if (!$id || !in_array($action, ['activate', 'deactivate'], true)) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Invalid request for status change.'
    ];
    header('Location: index.php');
    exit;
}

try {
    // Get slot info for context
    $slotInfo = $db->fetchRow('SELECT slot_name, day_of_week FROM time_slots WHERE slot_id = ?', [$id]);
    if (!$slotInfo) {
        throw new Exception('Time slot not found.');
    }

    $newStatus = ($action === 'activate') ? 1 : 0;

    // Update status using model helper
    $timeSlotManager->updateSlotStatus($id, $newStatus);

    // Flash success
    $statusText = $newStatus ? 'activated' : 'deactivated';
    $_SESSION['flash_message'] = [
        'type' => 'success',
        'message' => "Time slot '{$slotInfo['slot_name']}' has been {$statusText} successfully."
    ];

    // Build redirect back to list, preserving day filter if available
    $day = $dayParam !== '' ? $dayParam : ($slotInfo['day_of_week'] ?? '');
    $qs = 'updated=1&updated_id=' . urlencode((string)$id);
    if ($day !== '') {
        $qs .= '&day=' . urlencode($day);
    }

    header('Location: index.php?' . $qs);
    exit;
} catch (Exception $e) {
    error_log('Activate/Deactivate Slot Error: ' . $e->getMessage());
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Failed to change status: ' . $e->getMessage()
    ];
    header('Location: index.php');
    exit;
}
