<?php
/**
 * Universal Secure File Download Handler
 * Timetable Management System
 * 
 * Handles downloading of export files for all user roles with comprehensive security checks
 * Supports: Admin, Faculty, and Student exports
 */

// Define system access
define('SYSTEM_ACCESS', true);

// Include configuration files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Start session for authentication check
session_start();

// Check if user is logged in
if (!User::isLoggedIn()) {
    http_response_code(403);
    die('Access denied. Login required.');
}

// Get current user info
$userId = User::getCurrentUserId();
$userRole = User::getCurrentUserRole();

if (!$userId || !$userRole) {
    http_response_code(403);
    die('Invalid user session.');
}

// Get requested file
$requestedFile = $_GET['file'] ?? '';
if (empty($requestedFile)) {
    http_response_code(400);
    die('No file specified.');
}

// Initialize database for additional security checks
$db = Database::getInstance();

// Define allowed file patterns for each role
$allowedPatterns = [];
$userSpecificId = null;

switch ($userRole) {
    case 'admin':
        // Admin can download system-wide reports
        $allowedPatterns = [
            '/^admin_users_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx|csv)$/',
            '/^admin_system_stats_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx|csv)$/',
            '/^admin_reports_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx|csv)$/',
            '/^system_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(zip|sql)$/'
        ];
        break;
        
    case 'faculty':
        // Faculty can only download their own files
        // Get faculty_id from database
        try {
            $facultyRecord = $db->fetchRow("SELECT faculty_id FROM faculty WHERE user_id = ?", [$userId]);
            if (!$facultyRecord) {
                http_response_code(403);
                die('Faculty record not found.');
            }
            $userSpecificId = $facultyRecord['faculty_id'];
            
            $allowedPatterns = [
                '/^faculty_schedule_' . preg_quote($userSpecificId, '/') . '_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx|csv)$/',
                '/^faculty_students_' . preg_quote($userSpecificId, '/') . '_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx|csv)$/',
                '/^faculty_reports_' . preg_quote($userSpecificId, '/') . '_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx|csv)$/'
            ];
        } catch (Exception $e) {
            error_log("Download handler - Faculty ID lookup error: " . $e->getMessage());
            http_response_code(500);
            die('Unable to verify faculty permissions.');
        }
        break;
        
    case 'student':
        // Student can only download their own files
        // Get student_id from database
        try {
            $studentRecord = $db->fetchRow("SELECT student_id FROM students WHERE user_id = ?", [$userId]);
            if (!$studentRecord) {
                http_response_code(403);
                die('Student record not found.');
            }
            $userSpecificId = $studentRecord['student_id'];
            
            $allowedPatterns = [
                '/^student_schedule_' . preg_quote($userSpecificId, '/') . '_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx|csv)$/',
                '/^student_enrollments_' . preg_quote($userSpecificId, '/') . '_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx|csv)$/',
                '/^student_transcript_' . preg_quote($userSpecificId, '/') . '_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.(pdf|xlsx|csv)$/'
            ];
        } catch (Exception $e) {
            error_log("Download handler - Student ID lookup error: " . $e->getMessage());
            http_response_code(500);
            die('Unable to verify student permissions.');
        }
        break;
        
    default:
        http_response_code(403);
        die('Invalid user role.');
}

// Check if requested file matches any allowed pattern
$isAllowed = false;
foreach ($allowedPatterns as $pattern) {
    if (preg_match($pattern, $requestedFile)) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    error_log("Download handler - Unauthorized file access attempt: User ID {$userId} ({$userRole}) tried to access {$requestedFile}");
    http_response_code(403);
    die('Unauthorized file access.');
}

// Construct full file path
$filePath = EXPORTS_PATH . $requestedFile;

// Check if file exists and is readable
if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found.');
}

if (!is_readable($filePath)) {
    http_response_code(403);
    die('File is not accessible.');
}

// Additional security: Check file age (files older than 7 days are automatically cleaned up)
$fileAge = time() - filemtime($filePath);
$maxAge = 7 * 24 * 60 * 60; // 7 days in seconds

if ($fileAge > $maxAge) {
    // Clean up old file
    unlink($filePath);
    http_response_code(410);
    die('File has expired and been removed.');
}

// Get file info
$fileSize = filesize($filePath);
$fileName = basename($filePath);
$fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// Set appropriate MIME type
$mimeTypes = [
    'pdf' => 'application/pdf',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'csv' => 'text/csv',
    'zip' => 'application/zip',
    'sql' => 'application/sql'
];

$mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';

// Log the download activity
try {
    $db->execute("
        INSERT INTO audit_logs (user_id, action, description, ip_address, timestamp) 
        VALUES (?, 'FILE_DOWNLOAD', ?, ?, NOW())
    ", [
        $userId, 
        "Downloaded file: {$fileName} ({$fileSize} bytes)",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
} catch (Exception $e) {
    error_log("Download handler - Audit log error: " . $e->getMessage());
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Download headers
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Clear any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

// Serve the file
readfile($filePath);

// Optional: Clean up file after download (uncomment if you want immediate cleanup)
// unlink($filePath);

exit();
?>
