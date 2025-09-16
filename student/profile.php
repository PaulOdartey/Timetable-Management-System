<?php
/**
 * Student Profile Management Page
 * Timetable Management System
 * 
 * Professional profile management page with modern glassmorphism design
 * Allows students to view and update their personal information
 */

// Start session and security checks
session_start();

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../includes/profile-image-helper.php';

// Ensure user is logged in and has student role
User::requireLogin();
User::requireRole('student');

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();

// Initialize variables
$profileData = [];
$updateSuccess = false;
$updateError = '';
$passwordChangeSuccess = false;
$passwordChangeError = '';

// Handle flash messages
$flashMessage = null;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear the flash message after reading
}

// Get student profile data
try {
    $studentProfile = $db->fetchRow("
        SELECT s.*, u.email, u.username, u.last_login, u.created_at as account_created,
               u.status, u.email_verified, d.department_name, d.department_code
        FROM students s 
        JOIN users u ON s.user_id = u.user_id 
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE s.user_id = ?
    ", [$userId]);

    if ($studentProfile) {
        $profileData = $studentProfile;
    } else {
        throw new Exception("Student profile not found");
    }

    // Get student statistics for the sticky header
    $studentId = $profileData['student_id'];
    
    // Get enrolled subjects count
    $enrolledSubjects = $db->fetchRow("
        SELECT COUNT(DISTINCT e.subject_id) as count
        FROM enrollments e
        WHERE e.student_id = ? AND e.status = 'enrolled'
    ", [$studentId]);
    $statsData['subjects'] = $enrolledSubjects['count'] ?? 0;

    // Get total credits
    $totalCredits = $db->fetchRow("
        SELECT SUM(s.credits) as total_credits
        FROM enrollments e
        JOIN subjects s ON e.subject_id = s.subject_id
        WHERE e.student_id = ? 
        AND e.status = 'enrolled'
        AND (e.academic_year = '2025-2026' OR e.academic_year = '2025-26')
        AND e.semester = 1
    ", [$studentId]);
    $statsData['credits'] = $totalCredits['total_credits'] ?? 0;

    // Get schedule stats (weekly classes)
    $scheduleStats = $db->fetchRow("
        SELECT 
            COUNT(*) as total_classes_per_week,
            COUNT(DISTINCT t.classroom_id) as different_classrooms
        FROM timetables t
        JOIN enrollments e ON t.subject_id = e.subject_id
        WHERE e.student_id = ? 
        AND e.status = 'enrolled'
        AND t.is_active = 1
        AND (t.academic_year = '2025-2026' OR t.academic_year = '2025-26')
        AND t.semester = 1
    ", [$studentId]);
    $statsData['classes'] = $scheduleStats['total_classes_per_week'] ?? 0;
    $statsData['rooms'] = $scheduleStats['different_classrooms'] ?? 0;

    // Get GPA or completion stats (if available)
    $completedSubjects = $db->fetchRow("
        SELECT COUNT(*) as completed_count
        FROM enrollments e
        WHERE e.student_id = ? AND e.status = 'completed'
    ", [$studentId]);
    $statsData['completed'] = $completedSubjects['completed_count'] ?? 0;

} catch (Exception $e) {
    error_log("Student Profile Error: " . $e->getMessage());
    $updateError = "Unable to load profile data. Please try again later.";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $yearOfStudy = (int)($_POST['year_of_study'] ?? 1);
        $semester = (int)($_POST['semester'] ?? 1);
        $dateOfBirth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $address = trim($_POST['address'] ?? '');
        $guardianName = trim($_POST['guardian_name'] ?? '');
        $guardianPhone = trim($_POST['guardian_phone'] ?? '');
        $guardianEmail = trim($_POST['guardian_email'] ?? '');

        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($department)) {
            throw new Exception("First name, last name, and department are required.");
        }

        // If this is the guardian-only form submission, ensure at least one guardian field is provided
        if (isset($_POST['guardian_form'])) {
            if ($guardianName === '' && $guardianPhone === '' && $guardianEmail === '') {
                throw new Exception("Please provide at least one guardian detail to update.");
            }
        }

        // Prevent update if no changes were made compared to current profile
        $normalize = function($v) {
            if ($v === '') return null; // treat empty string as null for comparison
            if (is_string($v)) return trim($v);
            return $v;
        };

        // Build arrays for comparison
        $current = [
            'first_name' => $normalize($profileData['first_name'] ?? null),
            'last_name' => $normalize($profileData['last_name'] ?? null),
            'phone' => $normalize($profileData['phone'] ?? null),
            'department' => $normalize($profileData['department'] ?? null),
            'year_of_study' => (int)($profileData['year_of_study'] ?? 0),
            'semester' => (int)($profileData['semester'] ?? 0),
            'date_of_birth' => $normalize($profileData['date_of_birth'] ?? null),
            'address' => $normalize($profileData['address'] ?? null),
            'guardian_name' => $normalize($profileData['guardian_name'] ?? null),
            'guardian_phone' => $normalize($profileData['guardian_phone'] ?? null),
            'guardian_email' => $normalize($profileData['guardian_email'] ?? null),
        ];

        $incoming = [
            'first_name' => $normalize($firstName),
            'last_name' => $normalize($lastName),
            'phone' => $normalize($phone),
            'department' => $normalize($department),
            'year_of_study' => (int)$yearOfStudy,
            'semester' => (int)$semester,
            'date_of_birth' => $normalize($dateOfBirth),
            'address' => $normalize($address),
            'guardian_name' => $normalize($guardianName),
            'guardian_phone' => $normalize($guardianPhone),
            'guardian_email' => $normalize($guardianEmail),
        ];

        // Determine which keys to compare
        $keysToCompare = isset($_POST['guardian_form'])
            ? ['guardian_name', 'guardian_phone', 'guardian_email']
            : array_keys($current);

        $hasChanges = false;
        foreach ($keysToCompare as $key) {
            if (($incoming[$key] ?? null) !== ($current[$key] ?? null)) {
                $hasChanges = true;
                break;
            }
        }

        if (!$hasChanges) {
            // No actual changes; set info message and redirect without updating
            $_SESSION['flash_message'] = [
                'type' => 'info',
                'message' => isset($_POST['guardian_form'])
                    ? 'No changes detected in guardian information.'
                    : 'No changes detected in your profile.'
            ];
            header('Location: profile.php');
            exit();
        }

        // Validate year of study and semester
        if ($yearOfStudy < 1 || $yearOfStudy > 6) {
            throw new Exception("Year of study must be between 1 and 6.");
        }

        if ($semester < 1 || $semester > 12) {
            throw new Exception("Semester must be between 1 and 12.");
        }

        // Update student profile
        $updateResult = $db->execute("
            UPDATE students SET 
                first_name = ?, 
                last_name = ?, 
                phone = ?, 
                department = ?, 
                year_of_study = ?, 
                semester = ?, 
                date_of_birth = ?, 
                address = ?, 
                guardian_name = ?, 
                guardian_phone = ?, 
                guardian_email = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ", [
            $firstName, $lastName, $phone, $department, $yearOfStudy, $semester,
            $dateOfBirth, $address, $guardianName, $guardianPhone, $guardianEmail, $userId
        ]);

        if ($updateResult) {
            // Log the update
            $db->execute("
                INSERT INTO audit_logs (user_id, action, description, timestamp) 
                VALUES (?, 'UPDATE_PROFILE', 'Updated user profile', NOW())
            ", [$userId]);

            // Set flash message and redirect to prevent form resubmission
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Your profile has been updated successfully.'
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
$pageTitle = "Profile Settings";
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
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Ensure header avatar image is responsive */
        .avatar-wrapper img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .student-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 600;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
        }

        .student-details h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 1.5rem;
        }

        .student-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
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
            border: 2px solid #94a3b8; /* strong light-mode border */
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
            border-color: #64748b; /* darker on hover for clarity */
            background: rgba(255, 255, 255, 0.3);
        }

        .form-control::placeholder {
            color: var(--text-tertiary);
        }

        /* Dark mode form controls */
        [data-theme="dark"] .form-control {
            border: 2px solid #6b7280; /* strong dark-mode border */
            background: rgba(0, 0, 0, 0.3);
            color: var(--text-primary);
        }

        [data-theme="dark"] .form-control:focus {
            border-color: var(--primary-color);
            background: rgba(0, 0, 0, 0.4);
        }

        [data-theme="dark"] .form-control:hover {
            border-color: #9ca3af; /* lighter on hover in dark mode */
            background: rgba(0, 0, 0, 0.35);
        }

        /* Enhanced form control styles for different states */
        .form-control.is-valid {
            border-color: var(--success-color);
            background: rgba(16, 185, 129, 0.1);
        }

        .form-control.is-invalid {
            border-color: var(--error-color);
            background: rgba(239, 68, 68, 0.1);
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

        /* Inline form actions to keep buttons on one line */
        .form-actions-inline {
            display: flex;
            gap: 0.75rem; /* ~gap-3 */
            align-items: center;
            flex-wrap: nowrap; /* default: keep inline on larger screens */
        }

        .form-actions-inline .btn-primary,
        .form-actions-inline .btn-secondary {
            flex: 0 0 auto; /* prevent stretching */
            white-space: nowrap; /* keep icon + text on one line */
        }

        /* Make inline form actions stack nicely on narrow screens */
        @media (max-width: 576px) {
            .form-actions-inline {
                flex-wrap: wrap;
            }
            .form-actions-inline .btn-primary,
            .form-actions-inline .btn-secondary {
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

        /* Progress indicator styles */
        .progress-indicator {
            background: rgba(255, 255, 255, 0.1);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Academic status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.excellent { background: rgba(16, 185, 129, 0.2); color: #047857; }
        .status-badge.good { background: rgba(59, 130, 246, 0.2); color: #1d4ed8; }
        .status-badge.average { background: rgba(245, 158, 11, 0.2); color: #92400e; }
        .status-badge.needs-improvement { background: rgba(239, 68, 68, 0.2); color: #991b1b; }

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
            .student-info {
                text-align: center;
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
        }

        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.1) 25%, rgba(255, 255, 255, 0.3) 50%, rgba(255, 255, 255, 0.1) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 8px;
            height: 20px;
            margin-bottom: 10px;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
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
        
        /* Stats grid and card styles - consistent with admin profile */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--bg-primary);
            border: 2px solid var(--border-strong, #cbd5e1);
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
        [data-theme="dark"] .stat-card {
            background: var(--bg-secondary);
            border-color: var(--border-strong, #555555);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.35);
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    
    <!-- Main Content -->
    <main class="main-content">
        <style>
        /* Avatar wrapper with overlaid change button (glassmorphism) */
        .avatar-wrapper { position: relative; display: inline-block; }
        .change-photo-btn {
            position: absolute; right: 6px; bottom: 6px; width: 36px; height: 36px;
            border-radius: 50%; border: 1px solid var(--glass-border);
            background: var(--glass-bg); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 16px rgba(0,0,0,0.15); cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .change-photo-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(0,0,0,0.2); background: rgba(255,255,255,0.35); }
        .change-photo-btn:active { transform: translateY(0); }
        [data-theme="dark"] .change-photo-btn { background: rgba(0,0,0,0.35); border-color: rgba(255,255,255,0.15); }
        </style>
        <!-- Page Header -->
        <div class="page-header">
            <div class="welcome-card glass-card fade-in">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="welcome-content">
                        <h1 class="welcome-title">👤 Profile Settings</h1>
                    </div>
                    <div class="student-info">
                        <?php 
                        $fullName = trim(($profileData['first_name'] ?? '') . ' ' . ($profileData['last_name'] ?? ''));
                        ?>
                        <div class="avatar-wrapper">
                            <?= getPageHeaderProfileImage($userId, $fullName); ?>
                            <button type="button" class="change-photo-btn" data-bs-toggle="modal" data-bs-target="#changePhotoModal" aria-label="Change profile photo" title="Change Photo">📷</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Photo Modal -->
        <div class="modal fade" id="changePhotoModal" tabindex="-1" aria-labelledby="changePhotoModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="changePhotoModalLabel">Change Profile Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <?php include '../includes/profile-image-component.php'; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Academic Statistics - Strong bordered cards -->
        <section aria-label="Your Statistics" class="fade-in" style="margin-top: 0.5rem;">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= (int)($statsData['subjects'] ?? 0) ?></div>
                    <div class="stat-label">Enrolled Subjects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= (int)($statsData['credits'] ?? 0) ?></div>
                    <div class="stat-label">Total Credits</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= (int)($statsData['classes'] ?? 0) ?></div>
                    <div class="stat-label">Weekly Classes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--success-color);">
                        <?= (int)($statsData['completed'] ?? 0) ?>
                    </div>
                    <div class="stat-label">Completed Subjects</div>
                </div>
            </div>
        </section>

        <!-- Success/Error Messages -->
        <?php if ($flashMessage && $flashMessage['type'] === 'success'): ?>
            <div class="alert alert-success fade-in">
                <strong>✅ Success!</strong> <?= htmlspecialchars($flashMessage['message']) ?>
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
            <button type="button" class="tab-button active" onclick="switchTab(event, 'personal-info')">
                📝 Personal Information
            </button>
            <button type="button" class="tab-button" onclick="switchTab(event, 'academic-info')">
                🎓 Academic Information
            </button>
            <button type="button" class="tab-button" onclick="switchTab(event, 'security')">
                🔒 Security Settings
            </button>
        </div>

        <!-- Tab Content: Personal Information -->
        <div id="personal-info" class="tab-content active">
            <div class="profile-grid">
                <!-- Edit Profile Form -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">✏️</div>
                        Edit Profile
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
                            <input type="text" 
                                   class="form-control" 
                                   id="department" 
                                   name="department" 
                                   value="<?= htmlspecialchars($profileData['department'] ?? '') ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="year_of_study">Year of Study</label>
                            <select class="form-control" id="year_of_study" name="year_of_study">
                                <option value="">Select Year</option>
                                <option value="1" <?= ($profileData['year_of_study'] ?? '') == '1' ? 'selected' : '' ?>>1st Year</option>
                                <option value="2" <?= ($profileData['year_of_study'] ?? '') == '2' ? 'selected' : '' ?>>2nd Year</option>
                                <option value="3" <?= ($profileData['year_of_study'] ?? '') == '3' ? 'selected' : '' ?>>3rd Year</option>
                                <option value="4" <?= ($profileData['year_of_study'] ?? '') == '4' ? 'selected' : '' ?>>4th Year</option>
                                <option value="5" <?= ($profileData['year_of_study'] ?? '') == '5' ? 'selected' : '' ?>>5th Year</option>
                                <option value="6" <?= ($profileData['year_of_study'] ?? '') == '6' ? 'selected' : '' ?>>6th Year</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="semester">Current Semester</label>
                            <select class="form-control" id="semester" name="semester">
                                <option value="">Select Semester</option>
                                <option value="1" <?= ($profileData['semester'] ?? '') == '1' ? 'selected' : '' ?>>1st Semester</option>
                                <option value="2" <?= ($profileData['semester'] ?? '') == '2' ? 'selected' : '' ?>>2nd Semester</option>
                                <option value="3" <?= ($profileData['semester'] ?? '') == '3' ? 'selected' : '' ?>>3rd Semester</option>
                                <option value="4" <?= ($profileData['semester'] ?? '') == '4' ? 'selected' : '' ?>>4th Semester</option>
                                <option value="5" <?= ($profileData['semester'] ?? '') == '5' ? 'selected' : '' ?>>5th Semester</option>
                                <option value="6" <?= ($profileData['semester'] ?? '') == '6' ? 'selected' : '' ?>>6th Semester</option>
                                <option value="7" <?= ($profileData['semester'] ?? '') == '7' ? 'selected' : '' ?>>7th Semester</option>
                                <option value="8" <?= ($profileData['semester'] ?? '') == '8' ? 'selected' : '' ?>>8th Semester</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="date_of_birth">Date of Birth</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_of_birth" 
                                   name="date_of_birth" 
                                   value="<?= htmlspecialchars($profileData['date_of_birth'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="address">Address</label>
                            <textarea class="form-control" 
                                      id="address" 
                                      name="address" 
                                      rows="3" 
                                      placeholder="Enter your home address"><?= htmlspecialchars($profileData['address'] ?? '') ?></textarea>
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

                <!-- Current Profile Info -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">📋</div>
                        Current Information
                    </h3>

                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Full Name</span>
                            <span class="info-value"><?= htmlspecialchars(($profileData['first_name'] ?? '') . ' ' . ($profileData['last_name'] ?? '')) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Student ID</span>
                            <span class="info-value"><?= htmlspecialchars($profileData['student_number'] ?? 'Not Set') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Department</span>
                            <span class="info-value"><?= htmlspecialchars($profileData['department'] ?? 'Not Set') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Year of Study</span>
                            <span class="info-value">
                                <?= $profileData['year_of_study'] ? htmlspecialchars($profileData['year_of_study']) . getOrdinalSuffix($profileData['year_of_study']) . ' Year' : 'Not Set' ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Current Semester</span>
                            <span class="info-value">
                                <?= $profileData['semester'] ? htmlspecialchars($profileData['semester']) . getOrdinalSuffix($profileData['semester']) . ' Semester' : 'Not Set' ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone</span>
                            <span class="info-value"><?= htmlspecialchars($profileData['phone'] ?? 'Not Set') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date of Birth</span>
                            <span class="info-value">
                                <?= $profileData['date_of_birth'] ? date('F j, Y', strtotime($profileData['date_of_birth'])) : 'Not Set' ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($profileData['address'])): ?>
                        <div class="info-card">
                            <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">🏠 Home Address</h4>
                            <p style="color: var(--text-secondary); line-height: 1.6;">
                                <?= htmlspecialchars($profileData['address']) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Guardian Information -->
                    <?php if (!empty($profileData['guardian_name']) || !empty($profileData['guardian_phone'])): ?>
                        <div class="info-card">
                            <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">👥 Guardian Information</h4>
                            <?php if (!empty($profileData['guardian_name'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Guardian Name</span>
                                    <span class="info-value"><?= htmlspecialchars($profileData['guardian_name']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($profileData['guardian_phone'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Guardian Phone</span>
                                    <span class="info-value"><?= htmlspecialchars($profileData['guardian_phone']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($profileData['guardian_email'])): ?>
                                <div class="info-row">
                                    <span class="info-label">Guardian Email</span>
                                    <span class="info-value"><?= htmlspecialchars($profileData['guardian_email']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div> <!-- /.profile-section or additional info cards -->
            </div> <!-- /.profile-grid (personal-info) -->
        </div> <!-- /#personal-info.tab-content -->

        <!-- Tab Content: Academic Information -->
        <div id="academic-info" class="tab-content">
            <div class="profile-grid">
                <!-- Academic Details -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">🎓</div>
                        Academic Details
                    </h3>

                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Student ID</span>
                            <span class="info-value"><?= htmlspecialchars($profileData['student_number'] ?? 'Not Set') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Department</span>
                            <span class="info-value">
                                <?= htmlspecialchars($profileData['department_name'] ?? $profileData['department'] ?? 'Not Set') ?>
                                <?php if (!empty($profileData['department_code'])): ?>
                                    <small style="color: var(--text-tertiary);">(<?= htmlspecialchars($profileData['department_code']) ?>)</small>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Current Year</span>
                            <span class="info-value">
                                <?= $profileData['year_of_study'] ? htmlspecialchars($profileData['year_of_study']) . getOrdinalSuffix($profileData['year_of_study']) . ' Year' : 'Not Set' ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Current Semester</span>
                            <span class="info-value">
                                <?= $profileData['semester'] ? htmlspecialchars($profileData['semester']) . getOrdinalSuffix($profileData['semester']) . ' Semester' : 'Not Set' ?>
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
                            <span class="info-label">Enrollment Date</span>
                            <span class="info-value">
                                <?= $profileData['created_at'] ? date('F j, Y', strtotime($profileData['created_at'])) : 'Unknown' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Academic Performance -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">📊</div>
                        Academic Progress
                    </h3>

                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Enrolled Subjects</span>
                            <span class="info-value" style="color: var(--primary-color); font-weight: 700;">
                                <?= $statsData['subjects'] ?? 0 ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total Credits</span>
                            <span class="info-value" style="color: var(--primary-color); font-weight: 700;">
                                <?= $statsData['credits'] ?? 0 ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Weekly Classes</span>
                            <span class="info-value" style="color: var(--primary-color); font-weight: 700;">
                                <?= $statsData['classes'] ?? 0 ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Completed Subjects</span>
                            <span class="info-value" style="color: var(--success-color); font-weight: 700;">
                                <?= $statsData['completed'] ?? 0 ?>
                            </span>
                        </div>
                    </div>

                    <?php if (($statsData['subjects'] ?? 0) > 0): ?>
                        <div class="info-card">
                            <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">📚 Course Load</h4>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <span style="color: var(--text-secondary);">Credits This Semester</span>
                                <span style="color: var(--primary-color); font-weight: 700;"><?= $statsData['credits'] ?? 0 ?>/24</span>
                            </div>
                            <div class="progress-indicator">
                                <div class="progress-bar" style="width: <?= min(100, (($statsData['credits'] ?? 0) / 24) * 100) ?>%;"></div>
                            </div>
                            <small style="color: var(--text-tertiary); margin-top: 0.5rem; display: block;">
                                <?php 
                                $credits = $statsData['credits'] ?? 0;
                                if ($credits < 12) {
                                    echo 'Part-time load';
                                } elseif ($credits <= 18) {
                                    echo 'Normal load';
                                } elseif ($credits <= 21) {
                                    echo 'Heavy load';
                                } else {
                                    echo 'Overload';
                                }
                                ?>
                            </small>
                        </div>
                    <?php endif; ?>

                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1.5rem; font-size: 1.1rem;">🎯 Quick Actions</h4>
                        <div style="display: flex; flex-direction: column; gap: 0;">
                            <a href="schedule.php" class="quick-action-btn">
                                <div class="icon">📅</div>
                                <div class="content">
                                    <div class="title">View My Schedule</div>
                                    <div class="description">Check your weekly class timetable</div>
                                </div>
                            </a>
                            <a href="subjects.php" class="quick-action-btn">
                                <div class="icon">📚</div>
                                <div class="content">
                                    <div class="title">My Subjects</div>
                                    <div class="description">View enrolled courses and details</div>
                                </div>
                            </a>
                            <a href="export.php" class="quick-action-btn">
                                <div class="icon">📄</div>
                                <div class="content">
                                    <div class="title">Export Data</div>
                                    <div class="description">Download schedule and academic records</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="profile-section glass-card slide-up">
                    <h3 class="section-title">
                        <div class="section-icon">👤</div>
                        Account Information
                    </h3>

                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">Username</span>
                            <span class="info-value"><?= htmlspecialchars($profileData['username'] ?? 'Not Set') ?></span>
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
                            <span class="info-label">Account Created</span>
                            <span class="info-value">
                                <?= $profileData['account_created'] ? date('F j, Y', strtotime($profileData['account_created'])) : 'Unknown' ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Last Login</span>
                            <span class="info-value">
                                <?= $profileData['last_login'] ? date('F j, Y', strtotime($profileData['last_login'])) : 'Never' ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Profile Updated</span>
                            <span class="info-value">
                                <?= $profileData['updated_at'] ? date('F j, Y', strtotime($profileData['updated_at'])) : 'Never' ?>
                            </span>
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
                        <div class="section-icon">🔒</div>
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
                                Password must be at least 8 characters long
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
                            <button type="submit" class="btn-primary" id="changePasswordBtn">
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
                        <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">🔐 Password Security</h4>
                        <div style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 1rem;">
                            <p style="margin-bottom: 0.5rem;">For your account security, please ensure your password:</p>
                            <ul style="margin-left: 1rem; margin-bottom: 1rem;">
                                <li>Contains at least 8 characters</li>
                                <li>Includes uppercase and lowercase letters</li>
                                <li>Contains at least one number</li>
                                <li>Includes special characters (!@#$%^&*)</li>
                                <li>Avoid using personal information</li>
                            </ul>
                        </div>
                    </div>

                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">📱 Account Security</h4>
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
                            <span class="info-label">Login Attempts</span>
                            <span class="info-value" style="color: var(--success-color);">✅ Normal</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Account Access</span>
                            <span class="info-value" style="color: var(--success-color);">🔓 Secure</span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">⚠️ Security Tips</h4>
                        <div style="color: var(--text-secondary); line-height: 1.6;">
                            <ul style="margin-left: 1rem;">
                                <li>Never share your login credentials with anyone</li>
                                <li>Always log out when using shared computers</li>
                                <li>Report suspicious account activity immediately</li>
                                <li>Keep your contact information up to date</li>
                                <li>Use different passwords for different accounts</li>
                                <li>Be cautious when accessing your account on public Wi-Fi</li>
                            </ul>
                        </div>
                    </div>

                    <div class="info-card">
                        <h4 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.1rem;">📞 Need Help?</h4>
                        <div style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 1rem;">
                            <p>If you experience any security issues or need assistance:</p>
                            <ul style="margin-left: 1rem;">
                                <li>Contact IT Support: support@university.edu</li>
                                <li>Visit the Student Services Office</li>
                                <li>Call the Help Desk: +233-544250759</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Initialize profile page functionality
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
        });

        // Guardian form client-side validation: block submit if all guardian fields are empty
        (function() {
            const gf = document.getElementById('guardianForm');
            if (!gf) return;
            gf.addEventListener('submit', function(e) {
                const name = (document.getElementById('guardian_name')?.value || '').trim();
                const phone = (document.getElementById('guardian_phone')?.value || '').trim();
                const email = (document.getElementById('guardian_email')?.value || '').trim();
                if (name === '' && phone === '' && email === '') {
                    e.preventDefault();
                    if (typeof showAlert === 'function') {
                        showAlert('Please enter at least one guardian detail before updating.', 'error');
                    } else {
                        alert('Please enter at least one guardian detail before updating.');
                    }
                }
            });
        })();
        
        // Tab switching functionality (robust)
        function switchTab(evt, tabName) {
            if (evt && typeof evt.preventDefault === 'function') {
                evt.preventDefault();
            }

            const target = document.getElementById(tabName);
            if (!target) {
                console.warn('Tab target not found:', tabName);
                return;
            }

            // Hide all tab contents
            const tabcontents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabcontents.length; i++) {
                tabcontents[i].classList.remove('active');
            }

            // Remove active class from all tab buttons
            const tablinks = document.getElementsByClassName('tab-button');
            for (let i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove('active');
            }

            // Activate the requested tab
            target.classList.add('active');
            if (evt && evt.currentTarget) {
                evt.currentTarget.classList.add('active');
            } else {
                // Fallback: match button by data-target if no event target
                const btn = document.querySelector(`.tab-button[onclick*="'${tabName}'"]`);
                if (btn) btn.classList.add('active');
            }

            // Update URL hash for deep-linking without jumping
            if (history.replaceState) {
                history.replaceState(null, '', '#' + tabName);
            } else {
                window.location.hash = tabName;
            }
        }

        // Initialize tab from URL hash on load
        (function initTabFromHash() {
            const hash = (window.location.hash || '').replace('#', '');
            const validTabs = ['personal-info', 'academic-info', 'security'];
            if (validTabs.includes(hash)) {
                // Simulate click without requiring an event
                switchTab(null, hash);
            }
        })();

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
                });
            }

            // Reverted: allow native form reset behavior for both forms
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
            // Listen for sidebar collapse/expand events
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

            // Handle window resize for responsive behavior
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

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save profile (prevent browser save)
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const activeTab = document.querySelector('.tab-content.active');
                if (activeTab && activeTab.id === 'personal-info') {
                    document.getElementById('profileForm').dispatchEvent(new Event('submit', { cancelable: true }));
                }
            }

            // Ctrl/Cmd + 1,2,3 for tab switching
            if ((e.ctrlKey || e.metaKey) && ['1', '2', '3'].includes(e.key)) {
                e.preventDefault();
                const tabIndex = parseInt(e.key) - 1;
                const tabs = ['personal-info', 'academic-info', 'security'];
                const buttons = document.querySelectorAll('.tab-button');
                
                if (tabs[tabIndex] && buttons[tabIndex]) {
                    switchTab({ currentTarget: buttons[tabIndex] }, tabs[tabIndex]);
                }
            }
        });

        // Auto-save draft functionality for profile form
        let autoSaveTimeout;
        const profileInputs = document.querySelectorAll('#profileForm input, #profileForm textarea, #profileForm select');
        
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
            localStorage.setItem('studentProfileFormDraft', JSON.stringify(formData));
            
            // Show subtle indication that draft was saved
            showDraftSavedIndicator();
        }

        function loadFormDraft() {
            const draft = localStorage.getItem('studentProfileFormDraft');
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
            localStorage.removeItem('studentProfileFormDraft');
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

        // Enhanced form field styles for validation
        const validationStyles = `
            <style>
                .form-control.is-valid {
                    border-color: var(--success-color);
                    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
                }
                
                .form-control.is-invalid {
                    border-color: var(--error-color);
                    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
                }
                
                .field-error {
                    animation: slideInError 0.3s ease;
                }
                
                @keyframes slideInError {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', validationStyles);

        // Add smooth scrolling
        window.addEventListener('load', function() {
            document.documentElement.style.scrollBehavior = 'smooth';
        });

        // Enhanced hover effects for interactive elements
        const interactiveElements = document.querySelectorAll('.glass-card, .btn-primary, .btn-secondary, .tab-button');
        interactiveElements.forEach(element => {
            element.addEventListener('mouseenter', function() {
                if (!this.disabled) {
                    this.style.transform = 'translateY(-2px) scale(1.02)';
                }
            });

            element.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Print functionality
        function printProfile() {
            window.print();
        }

        // Add print button functionality if needed
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printProfile();
            }
        });

        // Auto-hide success messages after 5 seconds
        function autoHideAlerts() {
            const successAlerts = document.querySelectorAll('.alert-success');
            successAlerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000); // Hide after 5 seconds
            });
        }

        // Initialize auto-hide on page load
        document.addEventListener('DOMContentLoaded', autoHideAlerts);

        // Student-specific enhancements
        function initializeStudentFeatures() {
            // Academic progress visualization
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });

            // Add academic year calculation
            const currentYear = document.querySelector('[data-year-of-study]');
            if (currentYear) {
                const yearValue = parseInt(currentYear.dataset.yearOfStudy);
                const academicProgress = (yearValue / 4) * 100; // Assuming 4-year program
                updateAcademicProgress(academicProgress);
            }
        }

        function updateAcademicProgress(percentage) {
            const progressElement = document.querySelector('.academic-progress');
            if (progressElement) {
                progressElement.style.width = percentage + '%';
            }
        }

        // Initialize student-specific features
        document.addEventListener('DOMContentLoaded', initializeStudentFeatures);
    </script>
</body>
</html>

<?php
/**
 * Helper function to get ordinal suffix for numbers
 * @param int $number
 * @return string
 */
function getOrdinalSuffix($number) {
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
        return 'th';
    } else {
        return $ends[$number % 10];
    }
}

/**
 * Helper function for time ago display
 * @param string $datetime
 * @return string
 */
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
?>