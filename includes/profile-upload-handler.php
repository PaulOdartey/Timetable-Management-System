<?php
/**
 * Profile Upload Handler
 * Timetable Management System
 * 
 * Handles secure profile image uploads for all user roles
 * Professional implementation with validation, security, and error handling
 */

// Prevent direct access
if (!defined('SYSTEM_ACCESS')) {
    define('SYSTEM_ACCESS', true);
}

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Ensure user is logged in
User::requireLogin();

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'filename' => '',
    'url' => ''
];

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION[CSRF_TOKEN_NAME]) {
    $response['message'] = 'Invalid security token';
    echo json_encode($response);
    exit;
}

// Handle remove action
if (isset($_POST['action']) && $_POST['action'] === 'remove') {
    try {
        $userId = User::getCurrentUserId();
        $db = Database::getInstance();
        
        // Get current profile image
        $currentImageQuery = "SELECT profile_image FROM users WHERE user_id = ?";
        $currentImageResult = $db->fetchRow($currentImageQuery, [$userId]);
        $currentImage = $currentImageResult['profile_image'] ?? null;
        
        if ($currentImage) {
            // Remove from database
            $updateQuery = "UPDATE users SET profile_image = NULL, updated_at = NOW() WHERE user_id = ?";
            $updateResult = $db->execute($updateQuery, [$userId]);
            
            if ($updateResult) {
                // Delete file
                $imagePath = PROFILE_UPLOAD_PATH . $currentImage;
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
                
                $response['success'] = true;
                $response['message'] = 'Profile image removed successfully';
                
                // Log the activity
                $userRole = User::getCurrentUserRole();
                logMessage("Profile image removed by user ID: {$userId}, role: {$userRole}", 'info', ACCESS_LOG_FILE);
            } else {
                $response['message'] = 'Failed to remove profile image from database';
            }
        } else {
            $response['message'] = 'No profile image to remove';
        }
        
    } catch (Exception $e) {
        $response['message'] = 'An error occurred while removing the image';
        logMessage("Profile image removal error: " . $e->getMessage(), 'error', ERROR_LOG_FILE);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File is too large (server limit)',
        UPLOAD_ERR_FORM_SIZE => 'File is too large (form limit)',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    $error_code = $_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE;
    $response['message'] = $error_messages[$error_code] ?? 'Unknown upload error';
    echo json_encode($response);
    exit;
}

$uploadedFile = $_FILES['profile_image'];

// Validate file size
if ($uploadedFile['size'] > MAX_FILE_SIZE) {
    $maxSizeMB = MAX_FILE_SIZE / (1024 * 1024);
    $response['message'] = "File is too large. Maximum size is {$maxSizeMB}MB";
    echo json_encode($response);
    exit;
}

// Validate file type
$fileInfo = pathinfo($uploadedFile['name']);
$extension = strtolower($fileInfo['extension'] ?? '');

if (!in_array($extension, ALLOWED_IMAGE_TYPES)) {
    $allowedTypes = implode(', ', ALLOWED_IMAGE_TYPES);
    $response['message'] = "Invalid file type. Allowed types: {$allowedTypes}";
    echo json_encode($response);
    exit;
}

// Validate MIME type for additional security
$allowedMimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
finfo_close($finfo);

if (!isset($allowedMimeTypes[$extension]) || $mimeType !== $allowedMimeTypes[$extension]) {
    $response['message'] = 'File type does not match file content';
    echo json_encode($response);
    exit;
}

// Validate image dimensions and content
$imageInfo = getimagesize($uploadedFile['tmp_name']);
if ($imageInfo === false) {
    $response['message'] = 'Invalid image file';
    echo json_encode($response);
    exit;
}

// Check minimum and maximum dimensions
$minWidth = 100;
$minHeight = 100;
$maxWidth = 2000;
$maxHeight = 2000;

if ($imageInfo[0] < $minWidth || $imageInfo[1] < $minHeight) {
    $response['message'] = "Image too small. Minimum size: {$minWidth}x{$minHeight}px";
    echo json_encode($response);
    exit;
}

if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
    $response['message'] = "Image too large. Maximum size: {$maxWidth}x{$maxHeight}px";
    echo json_encode($response);
    exit;
}

try {
    // Get current user info
    $userId = User::getCurrentUserId();
    $userRole = User::getCurrentUserRole();
    $db = Database::getInstance();
    
    // Generate unique filename
    $timestamp = time();
    $randomString = bin2hex(random_bytes(8));
    $newFilename = "profile_{$userId}_{$timestamp}_{$randomString}.{$extension}";
    
    // Ensure upload directory exists
    if (!is_dir(PROFILE_UPLOAD_PATH)) {
        mkdir(PROFILE_UPLOAD_PATH, 0755, true);
    }
    
    $uploadPath = PROFILE_UPLOAD_PATH . $newFilename;
    
    // Get current profile image to delete later
    $currentImage = null;
    $currentImageQuery = "SELECT profile_image FROM users WHERE user_id = ?";
    $currentImageResult = $db->fetchRow($currentImageQuery, [$userId]);
    if ($currentImageResult && !empty($currentImageResult['profile_image'])) {
        $currentImage = $currentImageResult['profile_image'];
    }
    
    // Move uploaded file
    if (!move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
        $response['message'] = 'Failed to save uploaded file';
        echo json_encode($response);
        exit;
    }
    
    // Update database with new profile image
    $updateQuery = "UPDATE users SET profile_image = ?, updated_at = NOW() WHERE user_id = ?";
    $updateResult = $db->execute($updateQuery, [$newFilename, $userId]);
    
    if (!$updateResult) {
        // If database update fails, remove the uploaded file
        unlink($uploadPath);
        $response['message'] = 'Failed to update profile in database';
        echo json_encode($response);
        exit;
    }
    
    // Delete old profile image if it exists
    if ($currentImage && $currentImage !== $newFilename) {
        $oldImagePath = PROFILE_UPLOAD_PATH . $currentImage;
        if (file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }
    }
    
    // Log the upload activity
    $logMessage = "Profile image uploaded by user ID: {$userId}, role: {$userRole}, filename: {$newFilename}";
    logMessage($logMessage, 'info', ACCESS_LOG_FILE);
    
    // Success response
    $response['success'] = true;
    $response['message'] = 'Profile image uploaded successfully';
    $response['filename'] = $newFilename;
    $response['url'] = UPLOADS_URL . 'profiles/' . $newFilename;
    
} catch (Exception $e) {
    // Clean up uploaded file if it exists
    if (isset($uploadPath) && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    $response['message'] = 'An error occurred while processing your upload';
    logMessage("Profile upload error: " . $e->getMessage(), 'error', ERROR_LOG_FILE);
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
