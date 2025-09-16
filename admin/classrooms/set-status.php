<?php
define('SYSTEM_ACCESS', true);

session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Classroom.php';

try {
    // AuthZ
    User::requireLogin();
    User::requireRole('admin');

    // Params
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $status = isset($_GET['status']) ? trim(strtolower($_GET['status'])) : '';
    $returnUrl = isset($_GET['return']) ? $_GET['return'] : '';

    if ($id <= 0) {
        throw new Exception('Invalid classroom id.');
    }

    $allowed = ['available', 'maintenance', 'reserved', 'closed'];
    if (!in_array($status, $allowed, true)) {
        throw new Exception('Invalid status action.');
    }

    $classroom = new Classroom();

    // Perform update
    $result = $classroom->updateClassroom($id, ['status' => $status]);

    if (!isset($result['success']) || !$result['success']) {
        $msg = isset($result['message']) ? $result['message'] : 'Failed to update status.';
        throw new Exception($msg);
    }

    $_SESSION['flash_message'] = [
        'type' => 'success',
        'message' => isset($result['message']) && $result['message'] ? $result['message'] : 'Classroom status updated successfully.'
    ];

} catch (Exception $e) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => $e->getMessage()
    ];
}

// Build redirect
$base = '../../admin/classrooms/index.php';
$redirect = $base;
if (!empty($returnUrl)) {
    // Trust only same-origin relative URLs starting with '/'; fallback to base if suspicious
    $startsWithSlash = substr($returnUrl, 0, 1) === '/';
    if (strpos($returnUrl, '://') === false && $startsWithSlash) {
        $redirect = $returnUrl;
    } else {
        $redirect = $base;
    }
}

// Append updated markers
$sep = (strpos($redirect, '?') === false) ? '?' : '&';
$redirect .= $sep . 'updated=1&updated_id=' . urlencode((string)$id);

header('Location: ' . $redirect);
exit();
