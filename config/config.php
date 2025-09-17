<?php
/**
 * Main Configuration File
 * Timetable Management System
 * 
 * Central configuration for the entire application
 * Professional PHP development practices
 */

// Define system access if not already defined
if (!defined('SYSTEM_ACCESS')) {
    define('SYSTEM_ACCESS', true);
}

// Prevent direct access
defined('SYSTEM_ACCESS') or die('Direct access denied');

// Include Composer autoloader for professional libraries
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * ===========================================
 * SYSTEM CONSTANTS & BASIC SETTINGS
 * ===========================================
 */

// System Information
define('SYSTEM_NAME', 'Timetable Management System');
define('SYSTEM_VERSION', '1.0.0');
define('SYSTEM_AUTHOR', 'Final Year Project');
define('SYSTEM_EMAIL', 'admin@university.edu');

// Security-related constant names (used throughout the app)
// Name of the PHP session – keeps it distinct from other apps on the same domain
define('SESSION_NAME', 'tms_session');
// Array-key used to store the CSRF token in $_SESSION
define('CSRF_TOKEN_NAME', 'csrf_token');
// Session idle timeout in seconds (e.g., 1800 = 30 minutes)
define('SESSION_TIMEOUT', 1800);

// Environment Configuration
// TEMP: enable verbose error output while debugging
define('ENVIRONMENT', 'development'); // development, production, testing
define('DEBUG_MODE', ENVIRONMENT === 'development');
define('MAINTENANCE_MODE', false);

// Timezone Configuration
date_default_timezone_set('UTC');
define('SYSTEM_TIMEZONE', 'UTC');

// Base URL Configuration (adjust for your XAMPP setup)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
// Force IP address for consistent email links (especially when sent from background processes)
$host = 'localhost'; // Use IP address directly instead of $_SERVER['HTTP_HOST']192.168.117.42
$path = '/timetable-management/'; // Adjust this to your folder name in htdocs

define('BASE_URL', $protocol . $host . $path);
define('ASSETS_URL', BASE_URL . 'assets/');
define('UPLOADS_URL', BASE_URL . 'assets/uploads/');
define('EXPORTS_URL', BASE_URL . 'exports/');

// Directory Paths
define('ROOT_PATH', dirname(__DIR__) . '/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('CLASSES_PATH', ROOT_PATH . 'classes/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');
define('UPLOADS_PATH', ASSETS_PATH . 'uploads/');
define('LOGS_PATH', ROOT_PATH . 'logs/');

// Additional Paths (ADD THESE)
// Use admin/backups/ as the canonical backups directory
define('BACKUPS_PATH', ROOT_PATH . 'admin/backups/');
define('EXPORTS_PATH', ROOT_PATH . 'exports/');
define('PERFORMANCE_LOG_FILE', LOGS_PATH . 'performance.log');
define('SLOW_QUERY_THRESHOLD', 2.0); // seconds
define('AUDIT_RETENTION_DAYS', 365); // 1 year
define('NOTIFICATION_RETENTION_DAYS', 90); // 3 months

/**
 * ===========================================
 * ENHANCED EMAIL & SECURITY CONSTANTS
 * ===========================================
 */

// Email verification settings
if (!defined('EMAIL_VERIFICATION_EXPIRY')) {
    define('EMAIL_VERIFICATION_EXPIRY', 86400); // 24 hours in seconds
}
if (!defined('PASSWORD_RESET_EXPIRY')) {
    define('PASSWORD_RESET_EXPIRY', 3600);      // 1 hour in seconds
}

// Professional library settings
define('PDF_ENABLED', true);
// Note: PDF_AUTHOR, PDF_CREATOR, PDF_SUBJECT are defined by TCPDF library

define('EXCEL_ENABLED', true);
define('EXCEL_DEFAULT_FORMAT', 'Xlsx');

// Enhanced security settings
define('REMEMBER_TOKEN_EXPIRY', 2592000); // 30 days
define('MAX_REMEMBER_TOKENS', 5);

/**
 * ===========================================
 * SESSION & SECURITY CONFIGURATION
 * ===========================================
 */

// Start secure session & configure cookie params only once
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS (change to 1 on HTTPS)
    ini_set('session.gc_maxlifetime', 1800); // 30 minutes

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();

    // Check for remember me token if not logged in
    if (!User::isLoggedIn() && !defined('SKIP_REMEMBER_ME')) {
        require_once CLASSES_PATH . 'User.php';
        $user = new User();
        $user->checkRememberMe();
    }
}

/**
 * ----------------------------------------------------------
 * REMEMBER-ME AUTO-LOGIN
 * ----------------------------------------------------------
 * If the visitor is not logged in but has a valid remember_token
 * cookie, attempt automatic authentication using the helper
 * method already implemented in the User class.
 */

// Security Settings
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 3600); // 1 hour lockout

// Encryption Keys (Change these in production!)
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here!');
define('CSRF_SECRET', 'your-csrf-secret-key-here');

/**
 * ===========================================
 * DATABASE CONFIGURATION
 * ===========================================
 */

// Include database configuration
require_once CONFIG_PATH . 'database.php';

/**
 * ===========================================
 * EMAIL CONFIGURATION
 * ===========================================
 */

// Email Settings (PHPMailer configuration)
define('MAIL_ENABLED', true);  // Email sending enabled

// SMTP Configuration - UPDATE THESE FOR PRODUCTION
define('MAIL_HOST', 'smtp.gmail.com'); // Gmail SMTP server
define('MAIL_PORT', 465); // Use 587 for TLS, 465 for SSL
define('MAIL_USERNAME', 'provencalcollins@gmail.com'); // TODO: Replace with your Gmail address
define('MAIL_PASSWORD', 'dqkbwmvmdpsppmbd'); // TODO: Replace with Gmail App Password (16 characters)
define('MAIL_ENCRYPTION', 'ssl'); // Use 'tls' for port 587, 'ssl' for port 465
define('MAIL_FROM_ADDRESS', 'provencalcollins@gmail.com'); // TODO: Replace with your Gmail address
define('MAIL_FROM_NAME', SYSTEM_NAME);

// Development email settings
define('DEV_EMAIL_LOG', false); // Log emails instead of sending in development mode

// Email Templates Directory
define('EMAIL_TEMPLATES_PATH', ROOT_PATH . 'templates/email/');

// Email Configuration Notes:
// 1. For Gmail, you need to:
//    - Enable 2-factor authentication
//    - Generate an App Password (16 characters)
//    - Use the App Password, not your regular password
// 2. For other providers, adjust MAIL_HOST, MAIL_PORT, and MAIL_ENCRYPTION accordingly
// 3. In development, emails are logged to logs/emails.log instead of being sent

/**
 * ===========================================
 * FILE UPLOAD CONFIGURATION
 * ===========================================
 */

// Upload Settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Upload Directories
define('PROFILE_UPLOAD_PATH', UPLOADS_PATH . 'profiles/');
define('DOCUMENT_UPLOAD_PATH', UPLOADS_PATH . 'documents/');

/**
 * ===========================================
 * PAGINATION & DISPLAY SETTINGS
 * ===========================================
 */

define('DEFAULT_RECORDS_PER_PAGE', 10);
define('MAX_RECORDS_PER_PAGE', 100);
define('PAGINATION_LINKS', 5);

/**
 * ===========================================
 * ACADEMIC SETTINGS
 * ===========================================
 */

// Academic Configuration
define('CURRENT_ACADEMIC_YEAR', '2025-2026');
define('CURRENT_SEMESTER', 1);
define('MAX_SEMESTER', 2);
define('MAX_YEAR_OF_STUDY', 4);

// Time Slot Configuration
define('SLOT_DURATION_MINUTES', 60);
define('BREAK_DURATION_MINUTES', 15);
define('LUNCH_DURATION_MINUTES', 60);

// Working Days
define('WORKING_DAYS', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday',]);

/**
 * ===========================================
 * ERROR HANDLING & LOGGING
 * ===========================================
 */

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Log File Configuration
define('ERROR_LOG_FILE', LOGS_PATH . 'error.log');
define('ACCESS_LOG_FILE', LOGS_PATH . 'access.log');
define('AUTH_LOG_FILE', LOGS_PATH . 'authentication.log');

/**
 * ===========================================
 * HELPER FUNCTIONS
 * ===========================================
 */

/**
 * Get system configuration value
 * @param string $key Configuration key
 * @param mixed $default Default value if not found
 * @return mixed
 */
function getConfig($key, $default = null) {
    if (defined($key)) {
        return constant($key);
    }
    return $default;
}

/**
 * Check if system is in maintenance mode
 * @return bool
 */
function isMaintenanceMode() {
    return MAINTENANCE_MODE;
}

/**
 * Get current academic year
 * @return string
 */
function getCurrentAcademicYear() {
    return CURRENT_ACADEMIC_YEAR;
}

/**
 * Get current semester
 * @return int
 */
function getCurrentSemester() {
    return CURRENT_SEMESTER;
}

/**
 * Generate secure random string
 * @param int $length String length
 * @return string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Secure hash function
 * @param string $data Data to hash
 * @return string
 */
function secureHash($data) {
    return hash('sha256', $data . ENCRYPTION_KEY);
}

/**
 * Create directories if they don't exist
 */
function createDirectories() {
    // List path constants we expect
    $pathConstants = [
        'UPLOADS_PATH',
        'PROFILE_UPLOAD_PATH',
        'DOCUMENT_UPLOAD_PATH',
        'LOGS_PATH',
        'BACKUPS_PATH',
        'EXPORTS_PATH'
    ];

    foreach ($pathConstants as $const) {
        if (defined($const)) {
            $dir = constant($const);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}

/**
 * Log system events
 * @param string $message Log message
 * @param string $type Log type (error, info, warning)
 * @param string $file Log file
 */
function logMessage($message, $type = 'info', $file = ERROR_LOG_FILE) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
    file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * ===========================================
 * SYSTEM INITIALIZATION
 * ===========================================
 */

// Create necessary directories
createDirectories();

// Initialize CSRF token
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = generateRandomString();
}

// Log system access (in production, you might want to limit this)
if (DEBUG_MODE) {
    $access_info = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ];
    logMessage(json_encode($access_info), 'access', ACCESS_LOG_FILE);
}

/**
 * ===========================================
 * PROFESSIONAL LIBRARY CONFIGURATIONS
 * ===========================================
 */

// Configure Monolog (Professional Logging)
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

$logger = new Logger('TimetableSystem');
$logger->pushHandler(new RotatingFileHandler(LOGS_PATH . 'system.log', 0, Logger::DEBUG));

// Make logger globally available
function getLogger() {
    global $logger;
    return $logger;
}

/**
 * ===========================================
 * DEVELOPMENT HELPERS
 * ===========================================
 */

if (DEBUG_MODE) {
    /**
     * Debug function for development
     * @param mixed $data Data to debug
     * @param bool $die Whether to stop execution
     */
    function dd($data, $die = true) {
        echo '<pre style="background: #f4f4f4; padding: 10px; border: 1px solid #ddd; margin: 10px 0;">';
        var_dump($data);
        echo '</pre>';
        if ($die) die();
    }
    
    /**
     * Simple debug print
     * @param mixed $data Data to print
     */
    function debug($data) {
        dd($data, false);
    }
}

// Configuration complete message
if (DEBUG_MODE) {
    logMessage('System configuration loaded successfully', 'info');
}