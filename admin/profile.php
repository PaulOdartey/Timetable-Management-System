<?php
/**
 * Admin Profile Management Page
 * Timetable Management System
 * 
 * Professional admin profile management page with modern glassmorphism design
 * Allows administrators to view and update their personal and professional information
 */

// Start session and security checks
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../includes/profile-image-helper.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables
$profileData = [];
$updateSuccess = false;
$updateError = '';
$passwordChangeSuccess = false;
$passwordChangeError = '';
// Default full name used by avatar helper; will be recomputed after loading profile
$fullName = 'Admin User';

// Handle flash messages
$flashMessage = null;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear the flash message after reading
}

// Get admin profile data
try {
    $adminProfile = $db->fetchRow("
        SELECT a.*, u.email, u.username, u.last_login, u.created_at as account_created,
               u.status, u.email_verified
        FROM admin_profiles a 
        JOIN users u ON a.user_id = u.user_id 
        WHERE a.user_id = ?
    ", [$userId]);

    if ($adminProfile) {
        $profileData = $adminProfile;
    } else {
        throw new Exception("Admin profile not found");
    }

    // Compute full name for avatar helper usage
    $fullName = trim((($profileData['first_name'] ?? 'Admin') . ' ' . ($profileData['last_name'] ?? 'User')));

    // Get admin statistics
    $adminId = $profileData['admin_id'];
    
    // Get user management stats
    $userStats = $db->fetchRow("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_users,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
            COUNT(CASE WHEN role = 'faculty' AND status = 'active' THEN 1 END) as active_faculty,
            COUNT(CASE WHEN role = 'student' AND status = 'active' THEN 1 END) as active_students
        FROM users
        WHERE role != 'admin'
    ");
    
    $statsData = [
        'total_users' => $userStats['total_users'] ?? 0,
        'pending_users' => $userStats['pending_users'] ?? 0,
        'active_users' => $userStats['active_users'] ?? 0,
        'active_faculty' => $userStats['active_faculty'] ?? 0,
        'active_students' => $userStats['active_students'] ?? 0
    ];

    // Get system stats
    $systemStats = $db->fetchRow("
        SELECT 
            COUNT(DISTINCT t.timetable_id) as total_timetables,
            COUNT(DISTINCT s.subject_id) as total_subjects,
            COUNT(DISTINCT c.classroom_id) as total_classrooms
        FROM timetables t
        CROSS JOIN subjects s
        CROSS JOIN classrooms c
        WHERE t.is_active = 1 AND s.is_active = 1 AND c.is_active = 1
    ");
    
    $statsData['total_timetables'] = $systemStats['total_timetables'] ?? 0;
    $statsData['total_subjects'] = $systemStats['total_subjects'] ?? 0;
    $statsData['total_classrooms'] = $systemStats['total_classrooms'] ?? 0;

    // Get recent admin actions
    $recentActions = $db->fetchAll("
        SELECT action, description, timestamp 
        FROM audit_logs 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 10
    ", [$userId]);

} catch (Exception $e) {
    error_log("Admin Profile Error: " . $e->getMessage());
    $updateError = "Unable to load profile data. Please try again later.";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $officeLocation = trim($_POST['office_location'] ?? '');
        $emergencyContact = trim($_POST['emergency_contact'] ?? '');
        $emergencyPhone = trim($_POST['emergency_phone'] ?? '');
        $dateJoined = $_POST['date_joined'] ?? null;

        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($department)) {
            throw new Exception("First name, last name, and department are required.");
        }

        // Prevent update if no changes were made compared to current profile
        $normalize = function($v) {
            if ($v === '') return null;
            if (is_string($v)) return trim($v);
            return $v;
        };

        $current = [
            'first_name' => $normalize($profileData['first_name'] ?? null),
            'last_name' => $normalize($profileData['last_name'] ?? null),
            'phone' => $normalize($profileData['phone'] ?? null),
            'department' => $normalize($profileData['department'] ?? null),
            'designation' => $normalize($profileData['designation'] ?? null),
            'bio' => $normalize($profileData['bio'] ?? null),
            'office_location' => $normalize($profileData['office_location'] ?? null),
            'emergency_contact' => $normalize($profileData['emergency_contact'] ?? null),
            'emergency_phone' => $normalize($profileData['emergency_phone'] ?? null),
            'date_joined' => $profileData['date_joined'] ?? null,
        ];

        $incoming = [
            'first_name' => $normalize($firstName),
            'last_name' => $normalize($lastName),
            'phone' => $normalize($phone),
            'department' => $normalize($department),
            'designation' => $normalize($designation),
            'bio' => $normalize($bio),
            'office_location' => $normalize($officeLocation),
            'emergency_contact' => $normalize($emergencyContact),
            'emergency_phone' => $normalize($emergencyPhone),
            'date_joined' => $dateJoined ?: null,
        ];

        $hasChanges = false;
        foreach ($incoming as $key => $value) {
            if (($incoming[$key] ?? null) !== ($current[$key] ?? null)) {
                $hasChanges = true;
                break;
            }
        }

        if (!$hasChanges) {
            $_SESSION['flash_message'] = [
                'type' => 'info',
                'message' => 'No changes detected in your profile.'
            ];
            header('Location: profile.php');
            exit();
        }

        // Update admin profile
        $updateResult = $db->execute("
            UPDATE admin_profiles SET 
                first_name = ?, 
                last_name = ?, 
                phone = ?, 
                department = ?, 
                designation = ?, 
                bio = ?,
                office_location = ?,
                emergency_contact = ?,
                emergency_phone = ?,
                date_joined = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ", [
            $firstName, $lastName, $phone, $department, $designation,
            $bio, $officeLocation, $emergencyContact, $emergencyPhone, $dateJoined, $userId
        ]);

        if ($updateResult) {
            // Log the update
            $db->execute("
                INSERT INTO audit_logs (user_id, action, description, timestamp) 
                VALUES (?, 'UPDATE_PROFILE', 'Updated admin profile', NOW())
            ", [$userId]);

            // Set flash message and redirect to prevent form resubmission
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Your admin profile has been updated successfully.'
            ];
            
            // Redirect to prevent form resubmission
            header('Location: profile.php');
            exit();
            
        } else {
            throw new Exception("Failed to update profile.");
        }

    } catch (Exception $e) {
        $updateError = $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate passwords
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception("All password fields are required.");
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception("New passwords do not match.");
        }

        if (strlen($newPassword) < 8) {
            throw new Exception("New password must be at least 8 characters long.");
        }

        // Verify current password
        $currentUser = $db->fetchRow("SELECT password_hash FROM users WHERE user_id = ?", [$userId]);
        if (!password_verify($currentPassword, $currentUser['password_hash'])) {
            throw new Exception("Current password is incorrect.");
        }

        // Update password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateResult = $db->execute("
            UPDATE users SET password_hash = ? WHERE user_id = ?
        ", [$newPasswordHash, $userId]);

        if ($updateResult) {
            // Log the password change
            $db->execute("
                INSERT INTO audit_logs (user_id, action, description, timestamp) 
                VALUES (?, 'CHANGE_PASSWORD', 'Password changed successfully', NOW())
            ", [$userId]);

            // Set flash message and redirect
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Your password has been changed successfully.'
            ];
            
            header('Location: profile.php');
            exit();
        } else {
            throw new Exception("Failed to update password.");
        }

    } catch (Exception $e) {
        $passwordChangeError = $e->getMessage();
    }
}

// Set page title and current page for navigation
$pageTitle = "Admin Profile";
$currentPage = "profile";

// Helper function to format time ago
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (!$datetime) return 'Never';
        
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time/60) . ' minutes ago';
        if ($time < 86400) return floor($time/3600) . ' hours ago';
        if ($time < 2592000) return floor($time/86400) . ' days ago';
        if ($time < 31536000) return floor($time/2592000) . ' months ago';
        
        return floor($time/31536000) . ' years ago';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $pageTitle ?> - <?= SYSTEM_NAME ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-tertiary: #94a3b8;
            --border-color: #e2e8f0;
            --border-strong: #cbd5e1; /* stronger border for light mode cards */
            --accent-color: #667eea;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --primary-color: #667eea;
            --primary-color-alpha: rgba(102, 126, 234, 0.1);
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --navbar-height: 64px;
        }

        /* Dark theme variables */
        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --bg-tertiary: #404040;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --text-tertiary: #808080;
            --border-color: #404040;
            --border-strong: #555555; /* stronger border for dark mode cards */
            --glass-bg: rgba(0, 0, 0, 0.25);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        /* Prevent horizontal overflow on small screens */
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }

        /* Main Content Container with responsive sidebar support */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* Sidebar collapsed state */
        body.sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Glassmorphism Card Effects */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            margin-top: 1rem;
        }

        .welcome-card {
            padding: 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        /* Avatar wrapper with overlaid change button */
        .avatar-wrapper {
            position: relative;
            display: inline-block;
        }

        /* Ensure header avatar image is responsive */
        .avatar-wrapper img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        /* Override generic image rule for profile avatar images inside header */
        .avatar-wrapper .profile-avatar-img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
            display: block;
        }

        .change-photo-btn {
            position: absolute;
            right: 6px;
            bottom: 6px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--glass-border);
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: var(--text-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .change-photo-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(0,0,0,0.2);
            background: rgba(255,255,255,0.35);
        }

        .change-photo-btn:active {
            transform: translateY(0);
        }

        /* Dark mode tweak for button */
        [data-theme="dark"] .change-photo-btn {
            background: rgba(0,0,0,0.35);
            border-color: rgba(255,255,255,0.15);
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 600;
            box-shadow: 0 8px 32px rgba(220, 38, 38, 0.3);
        }

        .admin-details h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        .admin-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .admin-meta div {
            margin-bottom: 0.25rem;
        }

        .admin-meta div:last-child {
            margin-bottom: 0;
        }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-top: 1rem;
        }

        /* Profile Content */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .profile-section {
            padding: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #94a3b8;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.25);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.35);
            box-shadow: 0 0 0 3px var(--primary-color-alpha), 0 4px 20px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }

        .form-control:hover {
            border-color: #64748b;
            background: rgba(255, 255, 255, 0.3);
        }

        .form-control::placeholder {
            color: var(--text-tertiary);
        }

        /* Dark mode form controls */
        [data-theme="dark"] .form-control {
            border: 2px solid #6b7280;
            background: rgba(0, 0, 0, 0.3);
            color: var(--text-primary);
        }

        [data-theme="dark"] .form-control:focus {
            border-color: var(--primary-color);
            background: rgba(0, 0, 0, 0.4);
        }

        [data-theme="dark"] .form-control:hover {
            border-color: #9ca3af;
            background: rgba(0, 0, 0, 0.35);
        }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: var(--text-primary);
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            color: var(--text-primary);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            border: none;
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            color: white;
        }

        /* Inline form actions */
        .form-actions-inline {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: nowrap; /* default: keep inline on larger screens */
        }

        .form-actions-inline .btn-primary,
        .form-actions-inline .btn-secondary,
        .form-actions-inline .btn-danger {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        /* Make inline form actions stack nicely on narrow screens */
        @media (max-width: 576px) {
            .form-actions-inline {
                flex-wrap: wrap;
            }
            .form-actions-inline .btn-primary,
            .form-actions-inline .btn-secondary,
            .form-actions-inline .btn-danger {
                width: 100%;
            }
        }

        /* Quick Action Buttons */
        .quick-action-btn {
            width: 100%;
            padding: 1rem 1.5rem;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
            backdrop-filter: blur(10px);
            margin-bottom: 0.75rem;
        }

        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            color: var(--text-primary);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .quick-action-btn .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .quick-action-btn.danger .icon {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }

        .quick-action-btn .content {
            flex: 1;
        }

        .quick-action-btn .title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .quick-action-btn .description {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: none;
            backdrop-filter: blur(10px);
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert-info {
            background: rgba(102, 126, 234, 0.2);
            color: var(--primary-color);
            border: 1px solid rgba(102, 126, 234, 0.3);
        }

        /* Stats Grid and Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-primary);
            border: 2px solid var(--border-strong);
            border-radius: 16px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
            padding: 1.25rem 1.5rem;
            transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card .stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 0.35rem;
        }

        .stat-card .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        /* Dark mode adjustments for stat cards */
        [data-theme="dark"] .stat-card {
            background: var(--bg-secondary);
            border-color: var(--border-strong);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.35);
        }

        /* Info Cards */
        .info-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-wrap: wrap; /* allow wrapping to avoid overflow */
            gap: 0.5rem;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .info-value {
            color: var(--text-primary);
            font-weight: 500;
            word-break: break-word; /* break long values */
            max-width: 100%;
        }

        .info-value.highlight {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Stack label/value vertically on very small screens */
        @media (max-width: 576px) {
            .info-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .info-value {
                width: 100%;
                text-align: left;
            }
        }

        /* Professional Tab Styles */
        .profile-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 0.25rem;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .tab-button {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 50px;
        }

        .tab-button:hover {
            background: rgba(255, 255, 255, 0.15);
            color: var(--text-primary);
            transform: translateY(-1px);
        }

        .tab-button.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
            transform: translateY(-2px);
        }

        .tab-button.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
            border-radius: 12px;
        }

        .tab-content {
            display: none;
            animation: fadeInUp 0.5s ease-out;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Recent Actions */
        .recent-actions {
            max-height: 400px;
            overflow-y: auto;
        }

        .action-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .action-item:last-child {
            border-bottom: none;
        }

        .action-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .action-content {
            flex: 1;
            min-width: 0;
        }

        .action-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .action-description {
            color: var(--text-secondary);
            font-size: 0.75rem;
            line-height: 1.4;
        }

        .action-time {
            color: var(--text-tertiary);
            font-size: 0.75rem;
            white-space: nowrap;
        }

        /* System Stats Grid (responsive override only) */
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Enhanced Responsive Design */
        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 0px;
                --sidebar-collapsed-width: 0px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .welcome-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            /* Responsive adjustments */
            }
            
            .admin-info {
                text-align: center;
                justify-content: center;
            }

            .profile-tabs {
                flex-direction: column;
                gap: 0.5rem;
            }

            .tab-button {
                text-align: left;
            }

            .welcome-card .d-flex {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-up {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            border-radius: 2px;
            background: rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: var(--error-color); width: 25%; }
        .strength-medium { background: var(--warning-color); width: 50%; }
        .strength-good { background: var(--success-color); width: 75%; }
        .strength-strong { background: var(--success-color); width: 100%; }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>


    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="welcome-card glass-card fade-in">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="welcome-content">
                        <h1 class="welcome-title">👑 Admin Profile</h1>
                    </div>
                    
                    <div class="admin-info">
                        <div class="avatar-wrapper">
                            <?= getPageHeaderProfileImage($userId, $fullName); ?>
                            <button type="button" class="change-photo-btn" data-bs-toggle="modal" data-bs-target="#changePhotoModal" aria-label="Change profile photo" title="Change Photo">
                                📷
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Statistics -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <div class="stat-number"><?= $statsData['total_users'] ?? 0 ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $statsData['pending_users'] ?? 0 ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $statsData['active_faculty'] ?? 0 ?></div>
                <div class="stat-label">Active Faculty</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $statsData['active_students'] ?? 0 ?></div>
                <div class="stat-label">Active Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $statsData['total_subjects'] ?? 0 ?></div>
                <div class="stat-label">Total Subjects</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $statsData['total_classrooms'] ?? 0 ?></div>
                <div class="stat-label">Total Classrooms</div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($flashMessage && $flashMessage['type'] === 'success'): ?>
            <div class="alert alert-success fade-in">
                <strong>✅ Success!</strong> <?= htmlspecialchars($flashMessage['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($flashMessage && $flashMessage['type'] === 'info'): ?>
            <div class="alert alert-info fade-in">
                <strong>ℹ️ Info!</strong> <?= htmlspecialchars($flashMessage['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($updateError): ?>
            <div class="alert alert-danger fade-in">
                <strong>❌ Error!</strong> <?= htmlspecialchars($updateError) ?>
            </div>
        <?php endif; ?>

        <?php if ($passwordChangeError): ?>
            <div class="alert alert-danger fade-in">
                <strong>❌ Error!</strong> <?= htmlspecialchars($passwordChangeError) ?>
            </div>
        <?php endif; ?>

        <!-- Profile Tabs -->
        <div class="profile-tabs">
            <button class="tab-button active" onclick="switchTab(event, 'personal-info')">
                👤 Personal Info
            </button>
            <button class="tab-button" onclick="switchTab(event, 'account-info')">
                🏛️ Account Info
            </button>
            <button class="tab-button" onclick="switchTab(event, 'admin-actions')">
                ⚡ Admin Actions
            </button>
            <button class="tab-button" onclick="switchTab(event, 'security')">
                🔐 Security
            </button>
        </div>

        <!-- Tab Content: Personal Information -->
        <div id="personal-info" class="tab-content active">
            <div class="profile-grid">
                <!-- Edit Profile Form -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">✏️</div>
                        Edit Admin Profile
                    </h3>

                    <form method="POST" action="" id="profileForm">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="first_name" 
                                   name="first_name" 
                                   value="<?= htmlspecialchars($profileData['first_name'] ?? '') ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="last_name" 
                                   name="last_name" 
                                   value="<?= htmlspecialchars($profileData['last_name'] ?? '') ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?= htmlspecialchars($profileData['phone'] ?? '') ?>" 
                                   placeholder="e.g., +233-555-0123">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="department">Department *</label>
                            <select class="form-control" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <option value="Information Technology" <?= ($profileData['department'] ?? '') === 'Information Technology' ? 'selected' : '' ?>>Information Technology</option>
                                <option value="Administration" <?= ($profileData['department'] ?? '') === 'Administration' ? 'selected' : '' ?>>Administration</option>
                                <option value="Academic Affairs" <?= ($profileData['department'] ?? '') === 'Academic Affairs' ? 'selected' : '' ?>>Academic Affairs</option>
                                <option value="Student Affairs" <?= ($profileData['department'] ?? '') === 'Student Affairs' ? 'selected' : '' ?>>Student Affairs</option>
                                <option value="Finance" <?= ($profileData['department'] ?? '') === 'Finance' ? 'selected' : '' ?>>Finance</option>
                                <option value="Human Resources" <?= ($profileData['department'] ?? '') === 'Human Resources' ? 'selected' : '' ?>>Human Resources</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="designation">Designation</label>
                            <select class="form-control" id="designation" name="designation">
                                <option value="">Select Designation</option>
                                <option value="System Administrator" <?= ($profileData['designation'] ?? '') === 'System Administrator' ? 'selected' : '' ?>>System Administrator</option>
                                <option value="Database Administrator" <?= ($profileData['designation'] ?? '') === 'Database Administrator' ? 'selected' : '' ?>>Database Administrator</option>
                                <option value="Academic Administrator" <?= ($profileData['designation'] ?? '') === 'Academic Administrator' ? 'selected' : '' ?>>Academic Administrator</option>
                                <option value="Registrar" <?= ($profileData['designation'] ?? '') === 'Registrar' ? 'selected' : '' ?>>Registrar</option>
                                <option value="Deputy Registrar" <?= ($profileData['designation'] ?? '') === 'Deputy Registrar' ? 'selected' : '' ?>>Deputy Registrar</option>
                                <option value="IT Manager" <?= ($profileData['designation'] ?? '') === 'IT Manager' ? 'selected' : '' ?>>IT Manager</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="bio">Bio/Description</label>
                            <textarea class="form-control" 
                                      id="bio" 
                                      name="bio" 
                                      rows="4" 
                                      placeholder="Brief description about yourself and your role..."><?= htmlspecialchars($profileData['bio'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="office_location">Office Location</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="office_location" 
                                   name="office_location" 
                                   value="<?= htmlspecialchars($profileData['office_location'] ?? '') ?>" 
                                   placeholder="e.g., Admin Block, Room 101">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="date_joined">Date Joined</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_joined" 
                                   name="date_joined" 
                                   value="<?= $profileData['date_joined'] ?? '' ?>">
                        </div>

                        <div class="form-actions-inline">
                            <button type="submit" class="btn-primary">
                                💾 Update Profile
                            </button>
                            <button type="reset" class="btn-secondary">
                                🔄 Reset Form
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Emergency Contact Information -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">🚨</div>
                        Emergency Contact
                    </h3>

                    <form method="POST" action="" id="emergencyForm">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label class="form-label" for="emergency_contact">Emergency Contact Name</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="emergency_contact" 
                                   name="emergency_contact" 
                                   value="<?= htmlspecialchars($profileData['emergency_contact'] ?? '') ?>" 
                                   placeholder="Full name of emergency contact">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="emergency_phone">Emergency Phone Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="emergency_phone" 
                                   name="emergency_phone" 
                                   value="<?= htmlspecialchars($profileData['emergency_phone'] ?? '') ?>" 
                                   placeholder="e.g., +233-555-0123">
                        </div>

                        <div class="info-card">
                            <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1rem;">📞 Current Emergency Contact</h4>
                            <div class="info-row">
                                <span class="info-label">Contact Name</span>
                                <span class="info-value"><?= htmlspecialchars($profileData['emergency_contact'] ?? 'Not Set') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Contact Phone</span>
                                <span class="info-value"><?= htmlspecialchars($profileData['emergency_phone'] ?? 'Not Set') ?></span>
                            </div>
                        </div>

                        <div class="form-actions-inline">
                            <button type="submit" class="btn-primary">
                                🚨 Update Emergency Contact
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab Content: Account Information -->
        <div id="account-info" class="tab-content">
            <div class="profile-grid">
                <!-- Account Details -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">🏛️</div>
                        Account Details
                    </h3>

                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Username</span>
                            <span class="info-value highlight"><?= htmlspecialchars($profileData['username'] ?? 'Not Set') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email Address</span>
                            <span class="info-value">
                                <?= htmlspecialchars($profileData['email'] ?? 'Not Set') ?>
                                <?php if (isset($profileData['email_verified']) && $profileData['email_verified']): ?>
                                    <span style="color: var(--success-color); margin-left: 0.5rem;">✅ Verified</span>
                                <?php else: ?>
                                    <span style="color: var(--warning-color); margin-left: 0.5rem;">⚠️ Unverified</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Account Status</span>
                            <span class="info-value">
                                <?php 
                                $status = $profileData['status'] ?? 'unknown';
                                $statusColor = $status === 'active' ? 'var(--success-color)' : 'var(--warning-color)';
                                $statusIcon = $status === 'active' ? '✅' : '⚠️';
                                ?>
                                <span style="color: <?= $statusColor ?>;"><?= $statusIcon ?> <?= ucfirst($status) ?></span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Role</span>
                            <span class="info-value highlight">👑 System Administrator</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Account Created</span>
                            <span class="info-value">
                                <?= $profileData['account_created'] ? date('F j, Y g:i A', strtotime($profileData['account_created'])) : 'Unknown' ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Last Login</span>
                            <span class="info-value">
                                <?= $profileData['last_login'] ? date('F j, Y g:i A', strtotime($profileData['last_login'])) : 'Never' ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Profile Updated</span>
                            <span class="info-value">
                                <?= $profileData['updated_at'] ? date('F j, Y g:i A', strtotime($profileData['updated_at'])) : 'Never' ?>
                            </span>
                        </div>
                    </div>
                </div>
            <!-- Professional Information -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">💼</div>
                        Professional Information
                    </h3>

                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Admin ID</span>
                            <span class="info-value highlight"><?= htmlspecialchars($profileData['admin_id'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Employee ID</span>
                            <span class="info-value highlight"><?= htmlspecialchars($profileData['employee_id'] ?? 'Not Set') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Department</span>
                            <span class="info-value"><?= htmlspecialchars($profileData['department'] ?? 'Not Set') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Designation</span>
                            <span class="info-value"><?= htmlspecialchars($profileData['designation'] ?? 'Not Set') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Office Location</span>
                            <span class="info-value"><?= htmlspecialchars($profileData['office_location'] ?? 'Not Set') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date Joined</span>
                            <span class="info-value">
                                <?= isset($profileData['date_joined']) ? date('M j, Y', strtotime($profileData['date_joined'])) : 'N/A' ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($profileData['bio'])): ?>
                        <div class="info-card">
                            <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">📋 Bio</h4>
                            <p style="color: var(--text-secondary); line-height: 1.6;">
                                <?= htmlspecialchars($profileData['bio']) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Administrative Privileges -->
                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1.5rem; font-size: 1.1rem;">🛡️ Administrative Privileges</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div style="text-align: center; padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
                                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">👥</div>
                                <div style="color: var(--success-color); font-weight: 600; font-size: 0.875rem;">User Management</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
                                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">📅</div>
                                <div style="color: var(--success-color); font-weight: 600; font-size: 0.875rem;">Timetable Management</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
                                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">⚙️</div>
                                <div style="color: var(--success-color); font-weight: 600; font-size: 0.875rem;">System Settings</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
                                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">📊</div>
                                <div style="color: var(--success-color); font-weight: 600; font-size: 0.875rem;">Analytics & Reports</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Admin Actions -->
        <div id="admin-actions" class="tab-content">
            <div class="profile-grid">
                <!-- Quick Admin Actions -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">⚡</div>
                        Quick Admin Actions
                    </h3>

                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1.5rem; font-size: 1.1rem;">🎯 Most Used Actions</h4>
                        <div style="display: flex; flex-direction: column; gap: 0;">
                            <a href="users.php" class="quick-action-btn">
                                <div class="icon">👥</div>
                                <div class="content">
                                    <div class="title">Manage Users</div>
                                    <div class="description">Approve registrations, manage user accounts</div>
                                </div>
                            </a>
                            <a href="timetables.php" class="quick-action-btn">
                                <div class="icon">📅</div>
                                <div class="content">
                                    <div class="title">Manage Timetables</div>
                                    <div class="description">Create and edit class schedules</div>
                                </div>
                            </a>
                            <a href="subjects.php" class="quick-action-btn">
                                <div class="icon">📚</div>
                                <div class="content">
                                    <div class="title">Manage Subjects</div>
                                    <div class="description">Add, edit, and assign subjects</div>
                                </div>
                            </a>
                            <a href="classrooms.php" class="quick-action-btn">
                                <div class="icon">🏫</div>
                                <div class="content">
                                    <div class="title">Manage Classrooms</div>
                                    <div class="description">Configure classrooms and facilities</div>
                                </div>
                            </a>
                            <a href="settings.php" class="quick-action-btn">
                                <div class="icon">⚙️</div>
                                <div class="content">
                                    <div class="title">System Settings</div>
                                    <div class="description">Configure system parameters</div>
                                </div>
                            </a>
                            <a href="reports.php" class="quick-action-btn">
                                <div class="icon">📊</div>
                                <div class="content">
                                    <div class="title">View Reports</div>
                                    <div class="description">Generate system analytics and reports</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- System Maintenance -->
                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1.5rem; font-size: 1.1rem;">🔧 System Maintenance</h4>
                        <div style="display: flex; flex-direction: column; gap: 0;">
                            <a href="settings.php#maintenance-settings" class="quick-action-btn danger">
                                <div class="icon">🧹</div>
                                <div class="content">
                                    <div class="title">Run Maintenance</div>
                                    <div class="description">Clean logs, reset tokens, optimize database</div>
                                </div>
                            </a>
                            <a href="settings.php#backup-settings" class="quick-action-btn">
                                <div class="icon">💾</div>
                                <div class="content">
                                    <div class="title">Create Backup</div>
                                    <div class="description">Generate database backup</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Admin Actions -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">📋</div>
                        Recent Activities
                    </h3>

                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1.5rem; font-size: 1.1rem;">🕒 Latest Actions</h4>
                        
                        <?php if (!empty($recentActions)): ?>
                            <div class="recent-actions">
                                <?php foreach ($recentActions as $action): ?>
                                    <div class="action-item">
                                        <div class="action-icon">
                                            <?php
                                            $iconMap = [
                                                'CREATE_USER' => '👤',
                                                'UPDATE_PROFILE' => '✏️',
                                                'CHANGE_PASSWORD' => '🔐',
                                                'CREATE_BACKUP' => '💾',
                                                'SYSTEM_MAINTENANCE' => '🔧',
                                                'UPDATE_SYSTEM_SETTINGS' => '⚙️',
                                                'TIMETABLE_CREATE' => '📅',
                                                'TIMETABLE_UPDATE' => '📝',
                                                'TIMETABLE_DELETE' => '🗑️',
                                                'LOGIN_SUCCESS' => '🔓'
                                            ];
                                            echo $iconMap[$action['action']] ?? '📝';
                                            ?>
                                        </div>
                                        <div class="action-content">
                                            <div class="action-title"><?= ucwords(str_replace('_', ' ', $action['action'])) ?></div>
                                            <div class="action-description">
                                                <?= htmlspecialchars($action['description'] ?? 'No description available') ?>
                                            </div>
                                        </div>
                                        <div class="action-time">
                                            <?= timeAgo($action['timestamp']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="form-description">No recent actions found.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Action Summary -->
                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">📈 Action Summary</h4>
                        <div class="stats-grid">
                            <?php
                            $actionCounts = [];
                            foreach ($recentActions as $action) {
                                $actionCounts[$action['action']] = ($actionCounts[$action['action']] ?? 0) + 1;
                            }
                            
                            $topActions = array_slice($actionCounts, 0, 4, true);
                            foreach ($topActions as $actionType => $count): ?>
                                <div class="stat-card">
                                    <div class="stat-number"><?= $count ?></div>
                                    <div class="stat-label"><?= ucwords(str_replace('_', ' ', $actionType)) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Security Settings -->
        <div id="security" class="tab-content">
            <div class="profile-grid">
                <!-- Change Password -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">🔐</div>
                        Change Password
                    </h3>

                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label class="form-label" for="current_password">Current Password *</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="current_password" 
                                   name="current_password" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="new_password">New Password *</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="new_password" 
                                   name="new_password" 
                                   required
                                   minlength="8"
                                   onkeyup="checkPasswordStrength(this.value)">
                            <div class="password-strength" id="passwordStrength">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <small style="color: var(--text-tertiary); margin-top: 0.5rem; display: block;">
                                Password must be at least 8 characters long with mixed case, numbers, and symbols
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm New Password *</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required
                                   onkeyup="checkPasswordMatch()">
                            <small id="passwordMatch" style="margin-top: 0.5rem; display: block;"></small>
                        </div>

                        <div class="form-actions-inline">
                            <button type="submit" class="btn-danger" id="changePasswordBtn">
                                🔐 Change Password
                            </button>
                            <button type="reset" class="btn-secondary">
                                🔄 Clear Form
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Information -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">🛡️</div>
                        Security Information
                    </h3>

                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">🔒 Admin Security Status</h4>
                        <div class="info-row">
                            <span class="info-label">Security Level</span>
                            <span class="info-value highlight">🛡️ Maximum</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Two-Factor Authentication</span>
                            <span class="info-value" style="color: var(--warning-color);">🔒 Not Enabled</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email Verification</span>
                            <span class="info-value">
                                <?php if (isset($profileData['email_verified']) && $profileData['email_verified']): ?>
                                    <span style="color: var(--success-color);">✅ Verified</span>
                                <?php else: ?>
                                    <span style="color: var(--warning-color);">⚠️ Unverified</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Admin Privileges</span>
                            <span class="info-value highlight">👑 Full Access</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Login Attempts</span>
                            <span class="info-value" style="color: var(--success-color);">✅ Normal</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Account Security</span>
                            <span class="info-value" style="color: var(--success-color);">🔐 Secure</span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">🔐 Password Security</h4>
                        <div style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 1rem;">
                            <p style="margin-bottom: 0.5rem;">As an administrator, ensure your password:</p>
                            <ul style="margin-left: 1rem; margin-bottom: 1rem;">
                                <li>Contains at least 8 characters (12+ recommended)</li>
                                <li>Includes uppercase and lowercase letters</li>
                                <li>Contains at least one number</li>
                                <li>Includes special characters (!@#$%^&*)</li>
                                <li>Is changed regularly (every 90 days)</li>
                                <li>Is unique and not used elsewhere</li>
                            </ul>
                        </div>
                    </div>

                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">⚠️ Admin Security Guidelines</h4>
                        <div style="color: var(--text-secondary); line-height: 1.6;">
                            <ul style="margin-left: 1rem;">
                                <li>Never share your admin credentials with anyone</li>
                                <li>Always log out when leaving your workstation</li>
                                <li>Monitor system logs for suspicious activities</li>
                                <li>Report security incidents immediately</li>
                                <li>Use secure networks for admin access</li>
                                <li>Keep your contact information updated</li>
                                <li>Review user activities regularly</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Change Photo Modal -->
    <div class="modal fade" id="changePhotoModal" tabindex="-1" aria-labelledby="changePhotoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePhotoModalLabel">Change Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php
                        // Reusable profile image upload/remove component
                        include_once '../includes/profile-image-component.php';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize admin profile page functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Add animation delays for staggered effect
            const animatedElements = document.querySelectorAll('.slide-up');
            animatedElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });

            // Handle sidebar toggle events
            handleSidebarToggle();

            // Form validation
            setupFormValidation();

            // Auto-hide success messages
            autoHideAlerts();
        });

        // Tab switching functionality
        function switchTab(evt, tabName) {
            // Hide all tab contents
            const tabcontents = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabcontents.length; i++) {
                tabcontents[i].classList.remove("active");
            }

            // Remove active class from all tab buttons
            const tablinks = document.getElementsByClassName("tab-button");
            for (let i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }

            // Show the specific tab content and mark button as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthIndicator = document.getElementById('passwordStrength');
            
            if (!password) {
                strengthBar.className = 'password-strength-bar';
                strengthBar.style.width = '0%';
                return;
            }

            let score = 0;
            
            // Length check
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            
            // Character variety checks
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            // Set strength class and width
            strengthBar.className = 'password-strength-bar';
            if (score <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (score <= 4) {
                strengthBar.classList.add('strength-medium');
            } else if (score <= 5) {
                strengthBar.classList.add('strength-good');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }

        // Password match checker
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchIndicator = document.getElementById('passwordMatch');
            const changeBtn = document.getElementById('changePasswordBtn');

            if (!confirmPassword) {
                matchIndicator.textContent = '';
                return;
            }

            if (newPassword === confirmPassword) {
                matchIndicator.textContent = '✅ Passwords match';
                matchIndicator.style.color = 'var(--success-color)';
                changeBtn.disabled = false;
            } else {
                matchIndicator.textContent = '❌ Passwords do not match';
                matchIndicator.style.color = 'var(--error-color)';
                changeBtn.disabled = true;
            }
        }

        // Form validation setup
        function setupFormValidation() {
            const profileForm = document.getElementById('profileForm');
            const passwordForm = document.getElementById('passwordForm');
            const emergencyForm = document.getElementById('emergencyForm');

            // Profile form validation
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const firstName = document.getElementById('first_name').value.trim();
                    const lastName = document.getElementById('last_name').value.trim();
                    const department = document.getElementById('department').value.trim();

                    if (!firstName || !lastName || !department) {
                        e.preventDefault();
                        showAlert('Please fill in all required fields (First Name, Last Name, Department)', 'error');
                        return false;
                    }
                });
            }

            // Password form validation
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const currentPassword = document.getElementById('current_password').value;
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;

                    if (!currentPassword || !newPassword || !confirmPassword) {
                        e.preventDefault();
                        showAlert('All password fields are required', 'error');
                        return false;
                    }

                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        showAlert('New passwords do not match', 'error');
                        return false;
                    }

                    if (newPassword.length < 8) {
                        e.preventDefault();
                        showAlert('New password must be at least 8 characters long', 'error');
                        return false;
                    }

                    // Additional admin password strength validation
                    const hasUpper = /[A-Z]/.test(newPassword);
                    const hasLower = /[a-z]/.test(newPassword);
                    const hasNumber = /[0-9]/.test(newPassword);
                    const hasSymbol = /[^A-Za-z0-9]/.test(newPassword);

                    if (!(hasUpper && hasLower && hasNumber && hasSymbol)) {
                        e.preventDefault();
                        showAlert('Admin password must contain uppercase, lowercase, numbers, and symbols', 'error');
                        return false;
                    }
                });
            }
        }

        // Show alert function
        function showAlert(message, type = 'info') {
            // Create alert element
            const alert = document.createElement('div');
            alert.className = `alert alert-${type === 'error' ? 'danger' : 'success'} fade-in`;
            alert.innerHTML = `
                <strong>${type === 'error' ? '❌ Error!' : '✅ Success!'}</strong> ${message}
            `;

            // Insert at the top of main content
            const mainContent = document.querySelector('.main-content');
            const firstChild = mainContent.querySelector('.page-header').nextElementSibling;
            mainContent.insertBefore(alert, firstChild);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);

            // Scroll to top to show the alert
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Enhanced sidebar toggle handling
        function handleSidebarToggle() {
            window.addEventListener('sidebarToggled', function(e) {
                const body = document.body;
                
                if (e.detail && e.detail.collapsed) {
                    body.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                }
            });

            const sidebar = document.querySelector('.tms-sidebar');
            if (sidebar) {
                if (sidebar.classList.contains('collapsed')) {
                    document.body.classList.add('sidebar-collapsed');
                }
                
                if (window.innerWidth <= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                }
            }

            window.addEventListener('resize', function() {
                if (window.innerWidth <= 1024) {
                    document.body.classList.add('sidebar-collapsed');
                } else {
                    const sidebar = document.querySelector('.tms-sidebar');
                    if (sidebar) {
                        if (sidebar.classList.contains('collapsed')) {
                            document.body.classList.add('sidebar-collapsed');
                        } else {
                            document.body.classList.remove('sidebar-collapsed');
                        }
                    }
                }
            });
        }


        // Apply current theme
        function applyCurrentTheme() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        }

        // Listen for theme changes
        window.addEventListener('themeChanged', function(event) {
            applyCurrentTheme();
        });

        // Auto-hide success messages after 5 seconds
        function autoHideAlerts() {
            const successAlerts = document.querySelectorAll('.alert-success, .alert-info');
            successAlerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        }

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save profile
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const activeTab = document.querySelector('.tab-content.active');
                if (activeTab && activeTab.id === 'personal-info') {
                    document.getElementById('profileForm').dispatchEvent(new Event('submit', { cancelable: true }));
                }
            }

            // Ctrl/Cmd + 1,2,3,4 for tab switching
            if ((e.ctrlKey || e.metaKey) && ['1', '2', '3', '4'].includes(e.key)) {
                e.preventDefault();
                const tabIndex = parseInt(e.key) - 1;
                const tabs = ['personal-info', 'account-info', 'admin-actions', 'security'];
                const buttons = document.querySelectorAll('.tab-button');
                
                if (tabs[tabIndex] && buttons[tabIndex]) {
                    switchTab({ currentTarget: buttons[tabIndex] }, tabs[tabIndex]);
                }
            }
        });

        // Real-time form validation feedback
        function setupRealTimeValidation() {
            const requiredFields = ['first_name', 'last_name', 'department'];
            
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (field) {
                    field.addEventListener('blur', function() {
                        validateField(this);
                    });
                    
                    field.addEventListener('input', function() {
                        if (this.classList.contains('is-invalid')) {
                            validateField(this);
                        }
                    });
                }
            });
        }

        function validateField(field) {
            const value = field.value.trim();
            const isValid = value.length > 0;
            
            if (isValid) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
                removeFieldError(field);
            } else {
                field.classList.remove('is-valid');
                field.classList.add('is-invalid');
                showFieldError(field, 'This field is required');
            }
        }

        function showFieldError(field, message) {
            let errorElement = field.parentNode.querySelector('.field-error');
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'field-error';
                errorElement.style.cssText = `
                    color: var(--error-color);
                    font-size: 0.75rem;
                    margin-top: 0.25rem;
                `;
                field.parentNode.appendChild(errorElement);
            }
            errorElement.textContent = message;
        }

        function removeFieldError(field) {
            const errorElement = field.parentNode.querySelector('.field-error');
            if (errorElement) {
                errorElement.remove();
            }
        }

        // Initialize real-time validation
        setupRealTimeValidation();

        // Auto-save draft functionality for profile form
        let autoSaveTimeout;
        const profileInputs = document.querySelectorAll('#profileForm input, #profileForm textarea, #profileForm select, #emergencyForm input');
        
        profileInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    saveFormDraft();
                }, 2000); // Save draft after 2 seconds of no typing
            });
        });

        function saveFormDraft() {
            const formData = {};
            profileInputs.forEach(input => {
                if (input.name) {
                    formData[input.name] = input.value;
                }
            });
            localStorage.setItem('adminProfileFormDraft', JSON.stringify(formData));
            
            // Show subtle indication that draft was saved
            showDraftSavedIndicator();
        }

        function loadFormDraft() {
            const draft = localStorage.getItem('adminProfileFormDraft');
            if (draft) {
                try {
                    const formData = JSON.parse(draft);
                    Object.entries(formData).forEach(([name, value]) => {
                        const input = document.querySelector(`[name="${name}"]`);
                        if (input && !input.value) { // Only fill if current value is empty
                            input.value = value;
                        }
                    });
                } catch (e) {
                    console.error('Error loading form draft:', e);
                }
            }
        }

        function clearFormDraft() {
            localStorage.removeItem('adminProfileFormDraft');
        }

        function showDraftSavedIndicator() {
            // Create or update draft saved indicator
            let indicator = document.getElementById('draftSavedIndicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'draftSavedIndicator';
                indicator.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: var(--glass-bg);
                    backdrop-filter: blur(10px);
                    border: 1px solid var(--glass-border);
                    border-radius: 8px;
                    padding: 0.5rem 1rem;
                    color: var(--text-secondary);
                    font-size: 0.75rem;
                    z-index: 1000;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                `;
                document.body.appendChild(indicator);
            }

            indicator.textContent = '💾 Draft saved';
            indicator.style.opacity = '1';

            setTimeout(() => {
                indicator.style.opacity = '0';
            }, 2000);
        }

        // Load draft on page load
        window.addEventListener('load', function() {
            loadFormDraft();
        });

        // Clear draft when form is successfully submitted
        document.getElementById('profileForm')?.addEventListener('submit', function() {
            setTimeout(() => {
                // Check if page redirected or shows success message
                if (document.querySelector('.alert-success')) {
                    clearFormDraft();
                }
            }, 100);
        });

        // Admin-specific security enhancements
        let idleTimer;
        let idleWarningShown = false;
        const IDLE_TIME = 25 * 60 * 1000; // 25 minutes
        const WARNING_TIME = 20 * 60 * 1000; // 20 minutes

        function resetIdleTimer() {
            clearTimeout(idleTimer);
            idleWarningShown = false;
            
            // Hide warning if shown
            const warningModal = document.getElementById('idleWarningModal');
            if (warningModal) {
                warningModal.remove();
            }
            
            // Set warning timer
            setTimeout(showIdleWarning, WARNING_TIME);
            
            // Set logout timer
            idleTimer = setTimeout(() => {
                if (confirm('Your session has expired due to inactivity. You will be logged out for security.')) {
                    window.location.href = '../auth/logout.php?reason=idle';
                }
            }, IDLE_TIME);
        }

        function showIdleWarning() {
            if (idleWarningShown) return;
            idleWarningShown = true;
            
            const warningModal = document.createElement('div');
            warningModal.id = 'idleWarningModal';
            warningModal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            `;
            
            warningModal.innerHTML = `
                <div style="background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 20px; padding: 2rem; max-width: 400px; text-align: center;">
                    <h3 style="color: var(--text-primary); margin-bottom: 1rem;">⚠️ Session Timeout Warning</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Your session will expire in 5 minutes due to inactivity.</p>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <button onclick="resetIdleTimer()" class="btn-primary">Stay Logged In</button>
                        <button onclick="window.location.href='../auth/logout.php'" class="btn-secondary">Logout</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(warningModal);
        }

        // Track user activity for admin security
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(event => {
            document.addEventListener(event, resetIdleTimer, true);
        });

        // Initialize idle timer
        resetIdleTimer();

        // Admin activity logging (for critical actions)
        function logAdminAction(action, details) {
            // Send to server for logging (implement as needed)
            console.log(`Admin Action: ${action}`, details);
        }

        // Enhanced form security for admin
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const formId = this.id;
                const action = `FORM_SUBMIT_${formId.toUpperCase()}`;
                logAdminAction(action, { formId, timestamp: new Date().toISOString() });
            });
        });

        // Monitor critical admin actions
        document.querySelectorAll('a[href*="users"], a[href*="settings"], a[href*="timetables"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                logAdminAction('ADMIN_NAVIGATION', { href, timestamp: new Date().toISOString() });
            });
        });
    </script>
</body>
</html>