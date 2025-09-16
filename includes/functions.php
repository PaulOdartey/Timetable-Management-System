<?php
/**
 * Timetable Management System - Helper Functions
 * Save this as: includes/functions.php
 */

/**
 * Sanitize user input
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate secure random password
 */
function generate_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Format time for display
 */
function format_time($time) {
    return date('h:i A', strtotime($time));
}

/**
 * Format date for display
 */
function format_date($date) {
    return date('F j, Y', strtotime($date));
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login if not authenticated
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Display success message
 */
function show_success($message) {
    return '<div class="alert alert-success">' . $message . '</div>';
}

/**
 * Display error message
 */
function show_error($message) {
    return '<div class="alert alert-danger">' . $message . '</div>';
}

/**
 * Get current semester
 */
function get_current_semester() {
    $month = date('n');
    if ($month >= 1 && $month <= 5) {
        return 'Spring ' . date('Y');
    } elseif ($month >= 6 && $month <= 8) {
        return 'Summer ' . date('Y');
    } else {
        return 'Fall ' . date('Y');
    }
}

/**
 * Generate time slots for timetable
 */
function get_time_slots() {
    return [
        '08:00:00' => '8:00 AM',
        '09:00:00' => '9:00 AM',
        '10:00:00' => '10:00 AM',
        '11:00:00' => '11:00 AM',
        '12:00:00' => '12:00 PM',
        '13:00:00' => '1:00 PM',
        '14:00:00' => '2:00 PM',
        '15:00:00' => '3:00 PM',
        '16:00:00' => '4:00 PM',
        '17:00:00' => '5:00 PM'
    ];
}

/**
 * Get days of week
 */
function get_weekdays() {
    return [
        'Monday',
        'Tuesday', 
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday'
    ];
}

/**
 * Log activity
 */
function log_activity($user_id, $action, $details = '') {
    // This will work with Monolog later
    error_log("User $user_id: $action - $details");
}

/**
 * ------------------------------
 * Flash Messaging & PRG Helpers
 * ------------------------------
 */

/**
 * Ensure session is started (idempotent)
 */
function ensure_session_started() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Set a flash message
 * @param string $type e.g., 'success' | 'error' | 'info' | 'warning'
 * @param string $message
 */
function flash_set($type, $message) {
    ensure_session_started();
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$type] = $message;
}

/**
 * Check if a flash message exists
 */
function flash_has($type) {
    ensure_session_started();
    return !empty($_SESSION['flash'][$type]);
}

/**
 * Get and clear a flash message
 */
function flash_get($type) {
    ensure_session_started();
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

/**
 * Redirect helper (Location header + exit)
 */
function redirect_to($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Redirect with flash message (PRG)
 */
function redirect_with_flash($url, $type, $message) {
    flash_set($type, $message);
    redirect_to($url);
}

// Initialize session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>