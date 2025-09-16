<?php
/**
 * Faculty Export Download Handler
 * Secure file download for faculty exports
 */

session_start();
require_once '../config/config.php';
require_once '../classes/User.php';

// Ensure user is logged in and has faculty role
User::requireLogin();
User::requireRole('faculty');

$userId = User::getCurrentUserId();

if (!isset($_GET['file'])) {
    http_response_code(400);
    die('No file specified');
}

$filename = $_GET['file'];

// Security: Validate filename pattern for faculty exports
$allowedPatterns = [
    '/^faculty_schedule_\d+_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx)$/',
    '/^faculty_students_\d+_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx)$/',
    '/^faculty_subjects_\d+_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx)$/',
    '/^faculty_attendance_\d+_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx)$/'
];

$isValidFile = false;
foreach ($allowedPatterns as $pattern) {
    if (preg_match($pattern, $filename)) {
        $isValidFile = true;
        break;
    }
}

if (!$isValidFile) {
    http_response_code(403);
    die('Invalid file name');
}

// Extract faculty ID from filename and verify it matches current user
preg_match('/\d+/', $filename, $matches);
$fileFacultyId = $matches[0] ?? null;

// Get current user's faculty ID
try {
    $db = Database::getInstance();
    $facultyRecord = $db->fetchRow("SELECT faculty_id FROM faculty WHERE user_id = ?", [$userId]);
    
    if (!$facultyRecord || $facultyRecord['faculty_id'] != $fileFacultyId) {
        http_response_code(403);
        die('Access denied');
    }
} catch (Exception $e) {
    http_response_code(500);
    die('Database error');
}

// Build file path
$filepath = EXPORTS_PATH . $filename;

// Check if file exists
if (!file_exists($filepath) || !is_readable($filepath)) {
    http_response_code(404);
    die('File not found');
}

// Get file info
$filesize = filesize($filepath);
$extension = pathinfo($filename, PATHINFO_EXTENSION);

// Set appropriate headers based on file type
switch (strtolower($extension)) {
    case 'pdf':
        $contentType = 'application/pdf';
        break;
    case 'xlsx':
        $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        break;
    default:
        http_response_code(400);
        die('Unsupported file type');
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Download headers
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Log download activity
try {
    $db->execute("
        INSERT INTO audit_logs (user_id, action, description, timestamp)
        VALUES (?, 'DOWNLOAD_EXPORT', ?, NOW())
    ", [$userId, "Downloaded export file: {$filename}"]);
} catch (Exception $e) {
    // Log error but don't stop download
    error_log("Failed to log download activity: " . $e->getMessage());
}

// Serve the file
readfile($filepath);
exit();
?>
