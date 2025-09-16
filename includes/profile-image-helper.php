<?php
/**
 * Profile Image Helper Functions
 * Timetable Management System
 * 
 * Reusable functions for displaying user profile images across the system
 */

// Prevent direct access
if (!defined('SYSTEM_ACCESS')) {
    die('Direct access denied');
}

/**
 * Get user's profile image URL
 * @param int $userId User ID
 * @return string Profile image URL or default avatar URL
 */
function getUserProfileImageUrl($userId) {
    try {
        $db = Database::getInstance();
        $result = $db->fetchRow("SELECT profile_image FROM users WHERE user_id = ?", [$userId]);
        $profileImage = $result['profile_image'] ?? null;
        
        return $profileImage 
            ? UPLOADS_URL . 'profiles/' . $profileImage 
            : ASSETS_URL . 'images/default-avatar.svg';
    } catch (Exception $e) {
        return ASSETS_URL . 'images/default-avatar.svg';
    }
}

/**
 * Display profile image or initials fallback
 * @param int $userId User ID
 * @param string $fullName User's full name for initials fallback
 * @param string $size Size class (small, medium, large)
 * @param array $attributes Additional HTML attributes
 * @return string HTML for profile image display
 */
function displayProfileImage($userId, $fullName, $size = 'medium', $attributes = []) {
    $profileImageUrl = getUserProfileImageUrl($userId);
    $hasProfileImage = !str_contains($profileImageUrl, 'default-avatar.svg');
    
    // Size classes
    $sizeClasses = [
        'small' => 'profile-img-sm',
        'medium' => 'profile-img-md', 
        'large' => 'profile-img-lg'
    ];
    
    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['medium'];
    
    // Build attributes string
    $attrString = '';
    foreach ($attributes as $key => $value) {
        $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }
    
    if ($hasProfileImage) {
        return '<div class="profile-avatar ' . $sizeClass . '"' . $attrString . '>
                    <img src="' . htmlspecialchars($profileImageUrl) . '" alt="Profile" class="profile-avatar-img">
                </div>';
    } else {
        $initials = strtoupper(substr($fullName, 0, 1));
        if (strpos($fullName, ' ') !== false) {
            $nameParts = explode(' ', $fullName);
            $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
        }
        
        return '<div class="profile-avatar ' . $sizeClass . '"' . $attrString . '>
                    <span class="profile-initials">' . htmlspecialchars($initials) . '</span>
                </div>';
    }
}

/**
 * Get profile image HTML for page headers
 * @param int $userId User ID
 * @param string $fullName User's full name
 * @return string HTML for page header profile image
 */
function getPageHeaderProfileImage($userId, $fullName) {
    return displayProfileImage($userId, $fullName, 'large', ['class' => 'page-header-avatar']);
}

/**
 * Get profile image HTML for cards/lists
 * @param int $userId User ID
 * @param string $fullName User's full name
 * @return string HTML for card/list profile image
 */
function getCardProfileImage($userId, $fullName) {
    return displayProfileImage($userId, $fullName, 'medium', ['class' => 'card-avatar']);
}

/**
 * Get profile image HTML for small displays (tables, etc.)
 * @param int $userId User ID
 * @param string $fullName User's full name
 * @return string HTML for small profile image
 */
function getSmallProfileImage($userId, $fullName) {
    return displayProfileImage($userId, $fullName, 'small', ['class' => 'table-avatar']);
}
?>

<style>
/* Profile Avatar Base Styles */
.profile-avatar {
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    overflow: hidden;
    flex-shrink: 0;
}

.profile-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.profile-initials {
    color: white;
    font-weight: 600;
}

/* Size Variants */
.profile-img-sm {
    width: 32px;
    height: 32px;
    font-size: 0.75rem;
}

.profile-img-md {
    width: 48px;
    height: 48px;
    font-size: 1rem;
}

.profile-img-lg {
    width: 80px;
    height: 80px;
    font-size: 1.5rem;
}

/* Context-specific styles */
.page-header-avatar {
    border: 3px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.card-avatar {
    border: 2px solid rgba(255, 255, 255, 0.15);
}

.table-avatar {
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Dark mode support */
[data-theme="dark"] .profile-avatar {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
}

[data-theme="dark"] .page-header-avatar {
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

[data-theme="dark"] .card-avatar {
    border-color: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .table-avatar {
    border-color: rgba(255, 255, 255, 0.05);
}

/* Hover effects */
.profile-avatar:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

[data-theme="dark"] .profile-avatar:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
}
</style>
