<?php
// Enhanced Navbar Component - Timetable Management System
// Ensure user is logged in
if (!isset($_SESSION['role']) || !isset($_SESSION['username'])) {
    header('Location: /timetable-management/auth/login.php');
    exit();
}

// Get user information
$userRole = $_SESSION['role'];
$username = $_SESSION['username'];
$userId = $_SESSION['user_id'];

// Get notification count (you can modify this query based on your notification system)
try {
    $db = Database::getInstance();
    $notificationCount = 0;
    
    if ($userRole === 'admin') {
        // Admin sees all notifications + pending registrations
        $result = $db->fetchRow("
            SELECT 
                (SELECT COUNT(*) FROM notifications WHERE (target_role = 'admin' OR target_role = 'all') AND is_read = 0 AND is_active = 1) +
                (SELECT COUNT(*) FROM users WHERE status = 'pending') as total_count
        ");
        $notificationCount = $result['total_count'] ?? 0;
    } else {
        // Faculty and students see role-specific notifications
        $result = $db->fetchRow("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE (target_role = ? OR target_role = 'all' OR target_user_id = ?) 
            AND is_read = 0 AND is_active = 1
        ", [$userRole, $userId]);
        $notificationCount = $result['count'] ?? 0;
    }
} catch (Exception $e) {
    $notificationCount = 0;
}

// Get user's full name and profile image
$fullName = $username;
$profileImage = null;
try {
    // Get profile image from users table
    $userProfile = $db->fetchRow("SELECT profile_image FROM users WHERE user_id = ?", [$userId]);
    $profileImage = $userProfile['profile_image'] ?? null;
    
    // Get full name based on role
    if ($userRole === 'admin') {
        $profile = $db->fetchRow("SELECT first_name, last_name FROM admin_profiles WHERE user_id = ?", [$userId]);
    } elseif ($userRole === 'faculty') {
        $profile = $db->fetchRow("SELECT first_name, last_name FROM faculty WHERE user_id = ?", [$userId]);
    } elseif ($userRole === 'student') {
        $profile = $db->fetchRow("SELECT first_name, last_name FROM students WHERE user_id = ?", [$userId]);
    }
    
    if (!empty($profile)) {
        $fullName = trim($profile['first_name'] . ' ' . $profile['last_name']);
    }
} catch (Exception $e) {
    // Use username as fallback
}

// Generate profile image URL
$profileImageUrl = $profileImage 
    ? UPLOADS_URL . 'profiles/' . $profileImage 
    : ASSETS_URL . 'images/default-avatar.svg';
?>

<nav class="tms-navbar">
    <!-- Left Section - Mobile Menu Toggle + Logo -->
    <div class="navbar-left">
        <!-- Mobile Menu Toggle - Now first -->
        <button class="mobile-menu-toggle" onclick="toggleSidebar()">
            <span></span>
            <span></span>
            <span></span>
        </button>
        
        <div class="navbar-brand">
            <div class="brand-icon">
                <!-- NEW BRAND ICON: Academic/University Style -->
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="brand-text">
                <span class="brand-title">TMS</span>
                <span class="brand-subtitle desktop-only">Timetable System</span>
            </div>
        </div>
    </div>
    
    <!-- Right Section - Actions -->
    <div class="navbar-right">
        
        <!-- Quick Info Section -->
        <div class="quick-info-section desktop-only">
            <div class="current-time" id="currentTime"></div>
            <div class="system-status">
                <span class="status-indicator online"></span>
                <span class="status-text">System Online</span>
            </div>
        </div>
        
        <!-- Notifications -->
        <div class="notification-dropdown">
            <button class="notification-btn" onclick="toggleNotifications()" title="Notifications">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M13.73 21C13.5542 21.3031 13.3019 21.5556 12.9988 21.7314C12.6956 21.9072 12.3522 21.999 12 21.999C11.6478 21.999 11.3044 21.9072 11.0012 21.7314C10.6981 21.5556 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span>
                <?php endif; ?>
            </button>
            
            <!-- Notification Dropdown -->
            <div class="notification-menu" id="notificationMenu">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <?php if ($notificationCount > 0): ?>
                        <button class="mark-all-read" onclick="markAllNotificationsRead()">Mark all read</button>
                    <?php endif; ?>
                </div>
                <div class="notification-list" id="notificationList">
                    <!-- Notifications will be loaded here via JavaScript -->
                    <div class="loading-notifications">
                        <div class="loading-spinner"></div>
                        <span>Loading notifications...</span>
                    </div>
                </div>
                <div class="notification-footer">
                    <a href="/timetable-management/<?= $userRole ?>/notifications/index.php">View all notifications</a>
                </div>
            </div>
        </div>
        
        <!-- User Profile Dropdown -->
        <div class="user-dropdown">
            <button class="user-btn" onclick="toggleUserMenu()">
                <div class="user-avatar">
                    <?php if ($profileImage): ?>
                        <img src="<?= htmlspecialchars($profileImageUrl) ?>" alt="Profile" class="user-avatar-img">
                    <?php else: ?>
                        <span><?= strtoupper(substr($fullName, 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-info desktop-only">
                    <span class="user-name"><?= htmlspecialchars($fullName) ?></span>
                    <span class="user-role"><?= ucfirst($userRole) ?></span>
                </div>
                <svg class="dropdown-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            
            <!-- User Dropdown Menu -->
            <div class="user-menu" id="userMenu">
                <!-- Profile Image Preview Section -->
                <div class="profile-preview-section">
                    <div class="profile-image-container" onclick="viewProfileImage()">
                        <?php if ($profileImage): ?>
                            <img src="<?= htmlspecialchars($profileImageUrl) ?>" alt="Profile" class="profile-preview-img" id="profilePreviewImg">
                            <div class="profile-image-overlay">
                                <i class="fas fa-search-plus"></i>
                                <span>View Image</span>
                            </div>
                        <?php else: ?>
                            <div class="profile-preview-placeholder">
                                <span class="profile-initials-large"><?= strtoupper(substr($fullName, 0, 1)) ?></span>
                                <div class="profile-image-overlay">
                                    <i class="fas fa-camera"></i>
                                    <span>Add Photo</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="user-menu-header">
                    <div class="user-avatar-large">
                        <?php if ($profileImage): ?>
                            <img src="<?= htmlspecialchars($profileImageUrl) ?>" alt="Profile" class="user-avatar-large-img">
                        <?php else: ?>
                            <span><?= strtoupper(substr($fullName, 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <strong><?= htmlspecialchars($fullName) ?></strong>
                        <span><?= ucfirst($userRole) ?></span>
                        <small><?= htmlspecialchars($_SESSION['email'] ?? '') ?></small>
                    </div>
                </div>
                
                <div class="user-menu-divider"></div>
                
                <div class="user-menu-items">
                    <a href="/timetable-management/<?= $userRole ?>/profile.php" class="user-menu-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>My Profile</span>
                    </a>
                    
                    <?php if ($userRole === 'admin'): ?>
                    <a href="/timetable-management/admin/settings/index.php" class="user-menu-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.2435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Settings</span>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Dark Mode Toggle -->
                    <button class="user-menu-item theme-toggle" onclick="toggleTheme()" title="Toggle Dark Mode">
                        <svg class="sun-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 17C14.7614 17 17 14.7614 17 12C17 9.23858 14.7614 7 12 7C9.23858 7 7 9.23858 7 12C7 14.7614 9.23858 17 12 17Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M21 12H3" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <svg class="moon-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <span>Dark Mode</span>
                    </button>
                    
                    <div class="user-menu-divider"></div>
                    
                    <a href="/timetable-management/auth/logout.php" class="user-menu-item logout-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M16 17L21 12L16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M21 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Profile Image Modal -->
<div id="profileImageModal" class="profile-image-modal" onclick="closeProfileModal()">
    <span class="profile-image-modal-close" onclick="closeProfileModal()">&times;</span>
    <div class="profile-image-modal-content" onclick="event.stopPropagation()">
        <img id="modalProfileImage" src="" alt="Profile Image">
        <div class="profile-image-modal-info">
            <strong id="modalUserName"></strong>
            <p>Click outside or press ESC to close</p>
        </div>
    </div>
</div>

<script>
    // Profile image viewing functionality
    function viewProfileImage() {
        const profileImg = document.getElementById('profilePreviewImg');
        const modal = document.getElementById('profileImageModal');
        const modalImg = document.getElementById('modalProfileImage');
        const modalUserName = document.getElementById('modalUserName');
        
        if (profileImg && modal && modalImg) {
            modalImg.src = profileImg.src;
            modalUserName.textContent = '<?= htmlspecialchars($fullName) ?>';
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        } else {
            // If no profile image, redirect to profile page
            window.location.href = '/timetable-management/<?= $userRole ?>/profile.php';
        }
    }

    function closeProfileModal() {
        const modal = document.getElementById('profileImageModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        }
    }

    // Close modal with ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeProfileModal();
        }
    });

    // Enhanced user menu toggle with profile image updates
    function toggleUserMenu() {
        const menu = document.getElementById('userMenu');
        const isOpen = menu.classList.contains('show');
        
        if (isOpen) {
            menu.classList.remove('show');
            document.removeEventListener('click', closeUserMenuOnOutsideClick);
        } else {
            menu.classList.add('show');
            setTimeout(() => {
                document.addEventListener('click', closeUserMenuOnOutsideClick);
            }, 10);
        }
    }

    function closeUserMenuOnOutsideClick(event) {
        const menu = document.getElementById('userMenu');
        const userBtn = document.querySelector('.user-btn');
        
        if (!menu.contains(event.target) && !userBtn.contains(event.target)) {
            menu.classList.remove('show');
            document.removeEventListener('click', closeUserMenuOnOutsideClick);
        }
    }

    // Update profile image in navbar after upload
    function updateNavbarProfileImage(imageUrl) {
        const avatarImg = document.querySelector('.user-avatar-img');
        const avatarLargeImg = document.querySelector('.user-avatar-large-img');
        const previewImg = document.getElementById('profilePreviewImg');
        
        if (avatarImg) {
            avatarImg.src = imageUrl;
        }
        if (avatarLargeImg) {
            avatarLargeImg.src = imageUrl;
        }
        if (previewImg) {
            previewImg.src = imageUrl;
        }
        
        // Hide initials and show images
        const avatarSpans = document.querySelectorAll('.user-avatar span, .user-avatar-large span');
        avatarSpans.forEach(span => {
            span.style.display = 'none';
        });
        
        // Update profile preview section if no image was set before
        const previewSection = document.querySelector('.profile-preview-section');
        if (previewSection && !previewImg) {
            location.reload(); // Reload to update the entire navbar structure
        }
    }

    // Make function globally available for profile upload pages
    window.updateNavbarProfileImage = updateNavbarProfileImage;
</script>

<?php if ($userRole === 'admin'): ?>
<script>
// Globally suppress confirmation dialogs on all admin pages
(function () {
    try {
        // Override built-in confirm to always return true
        window.confirm = function () { return true; };
    } catch (e) {
        // No-op if override is blocked
    }
})();
</script>
<?php endif; ?>

<!-- Enhanced Navbar Styles with Dark Mode Fix -->
<style>
/* ============================================
   ENHANCED CSS VARIABLES FOR FULL DARK MODE
   ============================================ */

/* Light theme (default) */
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
    --accent-color: #667eea;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --error-color: #ef4444;
    --primary-color: #667eea;
    --primary-color-alpha: rgba(102, 126, 234, 0.2);
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.4);
    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.5);
}

/* PREVENT FLASH OF UNSTYLED CONTENT */
html {
    background-color: var(--bg-primary);
    color: var(--text-primary);
    /* Removed transition to prevent flash */
}

body {
    background-color: var(--bg-primary);
    color: var(--text-primary);
    /* Removed transition to prevent flash */
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    /* Prevent layout shifts */
    overflow-x: hidden;
}

/* MAIN CONTENT WRAPPER */
.main-content {
    background-color: var(--bg-primary);
    color: var(--text-primary);
    transition: all 0.3s ease;
    min-height: 100vh;
    padding-top: 64px; /* Account for fixed navbar */
}

/* Navbar Styles */
.tms-navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1.5rem;
    height: 64px;
    background: var(--bg-primary);
    border-bottom: 1px solid var(--border-color);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    /* Removed transition to prevent flash */
    /* Ensure navbar stays stable */
    min-width: 100%;
    box-sizing: border-box;
}

/* Left Section */
.navbar-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.navbar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    color: var(--text-primary);
}

.brand-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
    transition: all 0.3s ease;
}

.brand-icon:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.brand-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
}

.brand-subtitle {
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-weight: 500;
}

/* Mobile Menu Toggle */
.mobile-menu-toggle {
    display: none;
    flex-direction: column;
    gap: 3px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 6px;
    transition: background-color 0.2s;
    order: -1;
}

.mobile-menu-toggle:hover {
    background: var(--bg-secondary);
}

.mobile-menu-toggle span {
    width: 20px;
    height: 2px;
    background: var(--text-primary);
    transition: all 0.3s ease;
    border-radius: 1px;
}

/* Fix viewport switching issues */
@media (max-width: 768px) {
    .mobile-menu-toggle {
        display: flex !important;
    }
    
    .desktop-only {
        display: none !important;
    }
    
    .navbar-right {
        gap: 0.5rem;
    }
}

@media (min-width: 769px) {
    .mobile-menu-toggle {
        display: none !important;
    }
    
    .desktop-only {
        display: flex !important;
    }
    
    .tms-sidebar {
        transform: translateX(0) !important;
    }
    
    .sidebar-overlay {
        display: none !important;
    }
    
    body.sidebar-open {
        overflow: auto !important;
    }
}

/* Right Section */
.navbar-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Quick Info Section Styles */
.quick-info-section {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 1rem;
    background: var(--bg-secondary);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.current-time {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    font-family: 'Courier New', monospace;
}

.system-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--success-color);
    animation: pulse 2s infinite;
}

.status-indicator.online {
    background: var(--success-color);
}

.status-indicator.offline {
    background: var(--error-color);
}

.status-text {
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-weight: 500;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-input {
    width: 280px;
    padding: 0.5rem 1rem 0.5rem 2.5rem;
    border: 1px solid var(--border-color);
    border-radius: 20px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px var(--primary-color-alpha);
    width: 320px;
}

.search-input::placeholder {
    color: var(--text-tertiary);
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    color: var(--text-secondary);
    pointer-events: none;
}

/* Enhanced Search Results Dropdown */
.search-results-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    z-index: 1001;
    max-height: 400px;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
}

.search-results-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.search-results-header {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-secondary);
    border-radius: 12px 12px 0 0;
}

.search-results-title {
    font-weight: 600;
    color: var(--text-primary);
}

.search-close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.search-close-btn:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}

.search-results-content {
    max-height: 320px;
    overflow-y: auto;
}

.search-loading,
.search-no-results,
.search-minimum-query {
    padding: 2rem;
    text-align: center;
    color: var(--text-secondary);
    display: none;
}

.search-loading.show,
.search-no-results.show,
.search-minimum-query.show {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.search-results-list {
    padding: 0.5rem 0;
}

.search-result-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background-color 0.2s ease;
    border-bottom: 1px solid var(--border-color);
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background: var(--bg-secondary);
}

.search-result-icon {
    font-size: 1.25rem;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-color-alpha);
    border-radius: 6px;
    flex-shrink: 0;
}

.search-result-content {
    flex: 1;
    min-width: 0;
}

.search-result-title {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.search-result-subtitle {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-bottom: 0.125rem;
}

.search-result-description {
    font-size: 0.75rem;
    color: var(--text-tertiary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.search-category-header {
    padding: 0.5rem 1rem;
    background: var(--bg-tertiary);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border-color);
}

/* Existing notification and user dropdown styles remain the same... */
/* Notifications */
.notification-dropdown {
    position: relative;
}

.notification-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: var(--bg-secondary);
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    transition: all 0.2s ease;
    position: relative;
}

.notification-btn:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}

.notification-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #ef4444;
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.125rem 0.375rem;
    border-radius: 10px;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.notification-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 360px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 1001;
    max-height: 400px;
    overflow: hidden;
}

.notification-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notification-header {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.notification-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.mark-all-read {
    background: none;
    border: none;
    color: var(--primary-color);
    font-size: 0.875rem;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.mark-all-read:hover {
    background: var(--primary-color-alpha);
}

.notification-list {
    max-height: 280px;
    overflow-y: auto;
}

.loading-notifications {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    color: var(--text-secondary);
}

.loading-spinner {
    width: 24px;
    height: 24px;
    border: 2px solid var(--border-color);
    border-top: 2px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.notification-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid var(--border-color);
    text-align: center;
}

.notification-footer a {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
}

.notification-footer a:hover {
    text-decoration: underline;
}

/* Mobile-optimized notification panel */
@media (max-width: 768px) {
    /* Ensure the trigger container doesn't constrain the menu */
    .notification-dropdown {
        position: static;
    }

    /* Turn the dropdown into a full-width panel under the navbar */
    .notification-menu {
        position: fixed;
        top: 64px; /* matches navbar height */
        left: 0;
        right: 0;
        width: 100vw;
        max-height: calc(100vh - 64px);
        border-radius: 0 0 12px 12px;
        border-left: none;
        border-right: none;
        transform: translateY(0); /* prevent offscreen transform */
        opacity: 0;
        visibility: hidden;
        overflow: hidden; /* clip header/footer; inner list scrolls */
        z-index: 1001; /* above content, below any overlays */
    }

    .notification-menu.show {
        opacity: 1;
        visibility: visible;
    }

    /* Make the list area scroll within the fixed panel */
    .notification-list {
        max-height: calc(100vh - 64px - 56px - 48px); /* total minus header/footer approx */
        overflow-y: auto;
        -webkit-overflow-scrolling: touch; /* smooth scrolling on iOS */
    }
}

/* User Dropdown */
.user-dropdown {
    position: relative;
}

.user-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    border: none;
    background: var(--bg-secondary);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    color: var(--text-primary);
}

.user-btn:hover {
    background: var(--bg-tertiary);
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
    overflow: hidden;
}

.user-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.user-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
}

.user-role {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: capitalize;
}

.dropdown-arrow {
    color: var(--text-secondary);
    transition: transform 0.2s ease;
}

.user-btn.active .dropdown-arrow {
    transform: rotate(180deg);
}

.user-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 280px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 1001;
}

.user-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.user-menu-header {
    padding: 1.5rem;
    display: flex;
    gap: 1rem;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
}

.user-avatar-large {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.125rem;
    flex-shrink: 0;
    overflow: hidden;
}

.user-avatar-large-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

/* Profile Preview Section */
.profile-preview-section {
    padding: 1rem;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 0.5rem;
}

.profile-image-container {
    position: relative;
    display: inline-block;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.profile-image-container:hover {
    transform: scale(1.05);
}

.profile-preview-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.profile-preview-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid rgba(255, 255, 255, 0.2);
    position: relative;
}

.profile-initials-large {
    color: white;
    font-size: 1.5rem;
    font-weight: 600;
}

.profile-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    color: white;
    font-size: 0.75rem;
}

.profile-image-container:hover .profile-image-overlay {
    opacity: 1;
}

.profile-image-overlay i {
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
}

/* Profile Image Modal */
.profile-image-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    backdrop-filter: blur(5px);
}

.profile-image-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    max-width: 90%;
    max-height: 90%;
    text-align: center;
}

.profile-image-modal img {
    max-width: 100%;
    max-height: 80vh;
    border-radius: 10px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.profile-image-modal-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 2rem;
    font-weight: bold;
    cursor: pointer;
    z-index: 10001;
    transition: color 0.3s ease;
}

.profile-image-modal-close:hover {
    color: #ccc;
}

.profile-image-modal-info {
    color: white;
    margin-top: 1rem;
    font-size: 1.1rem;
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.user-details strong {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-details span {
    font-size: 0.875rem;
    color: var(--text-secondary);
    text-transform: capitalize;
}

.user-details small {
    font-size: 0.75rem;
    color: var(--text-tertiary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-menu-divider {
    height: 1px;
    background: var(--border-color);
    margin: 0.5rem 0;
}

.user-menu-items {
    padding: 0.5rem;
}

.user-menu-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 8px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
    width: 100%;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 0.875rem;
    text-align: left;
}

.user-menu-item:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.user-menu-item svg {
    color: var(--text-secondary);
    flex-shrink: 0;
}

.logout-btn {
    color: #ef4444 !important;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.1) !important;
}

.logout-btn svg {
    color: #ef4444 !important;
}

/* Theme toggle icons */
.user-menu-item .sun-icon {
    display: block;
}

.user-menu-item .moon-icon {
    display: none;
}

[data-theme="dark"] .user-menu-item .sun-icon {
    display: none;
}

[data-theme="dark"] .user-menu-item .moon-icon {
    display: block;
}

/* Responsive Design */
@media (max-width: 768px) {
    .tms-navbar {
        padding: 0 1rem;
    }
    
    .desktop-only {
        display: none !important;
    }
    
    .mobile-menu-toggle {
        display: flex;
    }
    
    .search-input {
        width: 200px;
    }
    
    .search-input:focus {
        width: 220px;
    }
    
    .user-info {
        display: none;
    }
    
    .notification-menu,
    .user-menu {
        width: 280px;
        right: -1rem;
    }
}

@media (max-width: 480px) {
    .navbar-right {
        gap: 0.5rem;
    }
    
    .search-container {
        display: none;
    }
    
    .notification-menu,
    .user-menu {
        width: calc(100vw - 2rem);
        right: -1rem;
    }
}

/* Mobile and Tablet Styles */
@media (max-width: 1024px) {
    .mobile-menu-toggle {
        display: flex;
    }
    
    .desktop-only {
        display: none !important;
    }
    
    .brand-icon {
        width: 32px;
        height: 32px;
    }
    
    .brand-icon svg {
        width: 24px;
        height: 24px;
    }
    
    .brand-title {
        font-size: 1.1rem;
    }
    
    .navbar-left {
        gap: 0.75rem;
    }
    
    .tms-navbar {
        padding: 0 1rem;
    }
}

/* Mobile sidebar body scroll lock */
body.sidebar-open {
    overflow: hidden;
    position: fixed;
    width: 100%;
}

/* ============================================
   Global Dark Mode Overrides (Role-agnostic)
   Applies consistent styling system-wide
   ============================================ */

[data-theme="dark"] {
    /* Links */
    a { color: var(--primary-color); }
    a:hover { color: #8ea2ff; }
}

/* Page headers and sticky headers */
[data-theme="dark"] .page-header,
[data-theme="dark"] .sticky-header,
[data-theme="dark"] .sticky-header-info {
    background: rgba(255,255,255,0.04);
    border-color: var(--border-color);
    color: var(--text-primary);
    backdrop-filter: blur(8px);
}
[data-theme="dark"] .sticky-header-stats .stat-item {
    background: rgba(255,255,255,0.03);
    border-color: var(--border-color);
    color: var(--text-secondary);
}

/* Cards / panels */
[data-theme="dark"] .card,
[data-theme="dark"] .panel,
[data-theme="dark"] .glass-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    box-shadow: var(--shadow-md);
    backdrop-filter: blur(8px);
}
[data-theme="dark"] .card-header,
[data-theme="dark"] .panel-heading {
    background: rgba(255,255,255,0.03);
    border-bottom: 1px solid var(--border-color);
    color: var(--text-secondary);
}

/* Tables */
[data-theme="dark"] table,
[data-theme="dark"] .table {
    color: var(--text-primary);
    background: transparent;
}
[data-theme="dark"] .table thead th {
    background: rgba(255,255,255,0.04);
    border-color: var(--border-color);
    color: var(--text-secondary);
}
[data-theme="dark"] .table tbody tr {
    border-color: var(--border-color);
}
[data-theme="dark"] .table tbody tr:hover {
    background: rgba(255,255,255,0.03);
}
[data-theme="dark"] .table td, 
[data-theme="dark"] .table th {
    border-color: var(--border-color);
}
[data-theme="dark"] .table-striped tbody tr:nth-of-type(odd) {
    background: rgba(255,255,255,0.02);
}

/* Forms */
[data-theme="dark"] input[type="text"],
[data-theme="dark"] input[type="email"],
[data-theme="dark"] input[type="password"],
[data-theme="dark"] input[type="number"],
[data-theme="dark"] input[type="search"],
[data-theme="dark"] select,
[data-theme="dark"] textarea,
[data-theme="dark"] .form-control {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}
[data-theme="dark"] .form-control::placeholder,
[data-theme="dark"] input::placeholder,
[data-theme="dark"] textarea::placeholder {
    color: var(--text-tertiary);
}
[data-theme="dark"] .form-control:focus,
[data-theme="dark"] input:focus,
[data-theme="dark"] select:focus,
[data-theme="dark"] textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px var(--primary-color-alpha);
}
[data-theme="dark"] .form-check-input {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
}

/* Buttons */
[data-theme="dark"] .btn,
[data-theme="dark"] .action-button,
[data-theme="dark"] button,
[data-theme="dark"] .btn-default {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}
[data-theme="dark"] .btn:hover,
[data-theme="dark"] .action-button:hover,
[data-theme="dark"] button:hover {
    background: var(--bg-tertiary);
}
[data-theme="dark"] .btn-primary,
[data-theme="dark"] .action-button.primary {
    background: var(--primary-color);
    border-color: transparent;
    color: #fff;
}
[data-theme="dark"] .btn-primary:hover,
[data-theme="dark"] .action-button.primary:hover {
    filter: brightness(1.05);
}
[data-theme="dark"] .btn-success { background: var(--success-color); color: #fff; border-color: transparent; }
[data-theme="dark"] .btn-warning { background: var(--warning-color); color: #1a1a1a; border-color: transparent; }
[data-theme="dark"] .btn-danger { background: var(--error-color); color: #fff; border-color: transparent; }

/* Badges / Pills */
[data-theme="dark"] .badge,
[data-theme="dark"] .label,
[data-theme="dark"] .tag {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border: 1px solid var(--border-color);
}
[data-theme="dark"] .badge-primary { background: var(--primary-color); color: #fff; border-color: transparent; }
[data-theme="dark"] .badge-success { background: var(--success-color); color: #fff; border-color: transparent; }
[data-theme="dark"] .badge-warning { background: var(--warning-color); color: #1a1a1a; border-color: transparent; }
[data-theme="dark"] .badge-danger { background: var(--error-color); color: #fff; border-color: transparent; }

/* Dropdowns / Modals */
[data-theme="dark"] .dropdown-menu,
[data-theme="dark"] .modal-content,
[data-theme="dark"] .popover {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    box-shadow: var(--shadow-lg);
}
[data-theme="dark"] .modal-header,
[data-theme="dark"] .dropdown-header { border-bottom: 1px solid var(--border-color); }
[data-theme="dark"] .modal-footer { border-top: 1px solid var(--border-color); }

/* Alerts */
[data-theme="dark"] .alert { color: #fff; border: none; }
[data-theme="dark"] .alert-primary { background: rgba(102,126,234,0.15); }
[data-theme="dark"] .alert-success { background: rgba(16,185,129,0.15); }
[data-theme="dark"] .alert-warning { background: rgba(245,158,11,0.18); color: #f9dba7; }
[data-theme="dark"] .alert-danger { background: rgba(239,68,68,0.18); }

/* Utilities */
[data-theme="dark"] .border { border-color: var(--border-color) !important; }
[data-theme="dark"] .bg-secondary { background: var(--bg-secondary) !important; }
[data-theme="dark"] .bg-tertiary { background: var(--bg-tertiary) !important; }
[data-theme="dark"] .text-muted { color: var(--text-tertiary) !important; }
[data-theme="dark"] .shadow,
[data-theme="dark"] .shadow-md { box-shadow: var(--shadow-md) !important; }
[data-theme="dark"] .shadow-lg { box-shadow: var(--shadow-lg) !important; }
}
/* --- Extended global content containers --- */
[data-theme="dark"] .container,
[data-theme="dark"] .container-fluid,
[data-theme="dark"] .page-content,
[data-theme="dark"] .content-wrapper,
[data-theme="dark"] .content,
[data-theme="dark"] .section,
[data-theme="dark"] .wrapper,
[data-theme="dark"] .app-content,
[data-theme="dark"] .page,
[data-theme="dark"] .main,
[data-theme="dark"] .page-body {
    background-color: var(--bg-primary) !important;
    color: var(--text-primary) !important;
}

/* Lists */
[data-theme="dark"] .list-group,
[data-theme="dark"] .list-group-item {
    background: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border-color: var(--border-color) !important;
}
[data-theme="dark"] .list-group-item.active {
    background: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: #fff !important;
}

/* Tabs / Pills */
[data-theme="dark"] .nav-tabs,
[data-theme="dark"] .nav-pills {
    border-color: var(--border-color) !important;
}
[data-theme="dark"] .nav-tabs .nav-link,
[data-theme="dark"] .nav-pills .nav-link {
    color: var(--text-secondary) !important;
    background: transparent !important;
    border-color: var(--border-color) !important;
}
[data-theme="dark"] .nav-tabs .nav-link.active,
[data-theme="dark"] .nav-pills .nav-link.active {
    color: var(--text-primary) !important;
    background: var(--bg-secondary) !important;
    border-color: var(--border-color) var(--border-color) transparent !important;
}

/* Table containers */
[data-theme="dark"] .table-responsive {
    background: transparent !important;
    border-color: var(--border-color) !important;
}

/* Breadcrumbs */
[data-theme="dark"] .breadcrumb {
    background: var(--bg-secondary) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-secondary) !important;
}
[data-theme="dark"] .breadcrumb .breadcrumb-item.active {
    color: var(--text-primary) !important;
}

/* Pagination */
[data-theme="dark"] .pagination .page-link {
    background: var(--bg-secondary) !important;
    border-color: var(--border-color) !important;
    color: var(--text-secondary) !important;
}
[data-theme="dark"] .pagination .page-item.active .page-link {
    background: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: #fff !important;
}

/* Code blocks */
[data-theme="dark"] pre,
[data-theme="dark"] code,
[data-theme="dark"] .code-block {
    background: #111 !important;
    color: #e5e7eb !important;
    border: 1px solid #2a2a2a !important;
}

/* Horizontal rules */
[data-theme="dark"] hr { border-color: var(--border-color) !important; }

/* Button links */
[data-theme="dark"] .btn-link { color: var(--primary-color) !important; }

/* Common light-forcing utilities -> dark equivalents */
[data-theme="dark"] .bg-white { background: var(--bg-secondary) !important; }
[data-theme="dark"] .bg-light { background: var(--bg-secondary) !important; }
[data-theme="dark"] .text-dark { color: var(--text-primary) !important; }
[data-theme="dark"] .border-top,
[data-theme="dark"] .border-right,
[data-theme="dark"] .border-bottom,
[data-theme="dark"] .border-left { border-color: var(--border-color) !important; }

/* Card internals */
[data-theme="dark"] .card-body,
[data-theme="dark"] .card-footer { background: transparent !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }

/* Inputs and groups */
[data-theme="dark"] .form-select,
[data-theme="dark"] .input-group-text,
[data-theme="dark"] .input-group .form-control {
    background: var(--bg-secondary) !important;
    color: var(--text-primary) !important;
    border-color: var(--border-color) !important;
}

/* Outline buttons */
[data-theme="dark"] .btn-outline-primary { color: var(--primary-color) !important; border-color: var(--primary-color) !important; background: transparent !important; }
[data-theme="dark"] .btn-outline-primary:hover { background: var(--primary-color) !important; color: #fff !important; }
[data-theme="dark"] .btn-outline-secondary { color: var(--text-secondary) !important; border-color: var(--border-color) !important; }
[data-theme="dark"] .btn-outline-secondary:hover { background: var(--bg-tertiary) !important; color: var(--text-primary) !important; }

/* Dropdown items */
[data-theme="dark"] .dropdown-item { color: var(--text-primary) !important; }
[data-theme="dark"] .dropdown-item:hover, 
[data-theme="dark"] .dropdown-item:focus { background: var(--bg-secondary) !important; color: var(--text-primary) !important; }

/* Table hover variant */
[data-theme="dark"] .table-hover tbody tr:hover { background: rgba(255,255,255,0.03) !important; }

/* Header light variants */
[data-theme="dark"] .thead-light th { background: rgba(255,255,255,0.04) !important; color: var(--text-secondary) !important; border-color: var(--border-color) !important; }

/* --- Dashboard-specific: welcome and management sections --- */
/* Welcome cards often use light gradients inline; force dark variant */
[data-theme="dark"] .welcome-card {
    background: linear-gradient(135deg, rgba(24,24,24,0.9) 0%, rgba(24,24,24,0.7) 100%) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-primary) !important;
}
[data-theme="dark"] .welcome-card .welcome-title,
[data-theme="dark"] .welcome-card .welcome-subtitle,
[data-theme="dark"] .welcome-card .faculty-details,
[data-theme="dark"] .welcome-card .admin-info {
    color: var(--text-primary) !important;
}
[data-theme="dark"] .welcome-card .faculty-meta span,
[data-theme="dark"] .welcome-card .meta,
[data-theme="dark"] .welcome-card .subtitle { color: var(--text-secondary) !important; }

/* Management sections (Recent Activities, System Notifications, Department Overview) */
[data-theme="dark"] .management-card {
    background: var(--glass-bg) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-primary) !important;
}
[data-theme="dark"] .management-card .section-title { color: var(--text-primary) !important; }

/* Common list rows/cards inside management sections */
[data-theme="dark"] .management-card .activity-item,
[data-theme="dark"] .management-card .notification-item,
[data-theme="dark"] .management-card .pending-user-item,
[data-theme="dark"] .management-card .list-item,
[data-theme="dark"] .management-card .item-row,
[data-theme="dark"] .management-card .item,
[data-theme="dark"] .department-grid .department-card,
[data-theme="dark"] .department-grid .department-item,
[data-theme="dark"] .department-grid .dept-card,
[data-theme="dark"] .department-grid .dept-item {
    background: var(--bg-secondary) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-primary) !important;
}
[data-theme="dark"] .management-card .activity-item:hover,
[data-theme="dark"] .management-card .notification-item:hover,
[data-theme="dark"] .management-card .list-item:hover,
[data-theme="dark"] .management-card .item-row:hover,
[data-theme="dark"] .department-grid .department-card:hover,
[data-theme="dark"] .department-grid .department-item:hover,
[data-theme="dark"] .department-grid .dept-card:hover,
[data-theme="dark"] .department-grid .dept-item:hover {
    background: rgba(255,255,255,0.04) !important;
}

/* Text subtleties inside rows */
[data-theme="dark"] .management-card .item-title,
[data-theme="dark"] .management-card .title { color: var(--text-primary) !important; }
[data-theme="dark"] .management-card .item-subtitle,
[data-theme="dark"] .management-card .meta,
[data-theme="dark"] .management-card .timestamp,
[data-theme="dark"] .management-card .status,
[data-theme="dark"] .management-card .description { color: var(--text-secondary) !important; }

/* Divider lines within lists */
[data-theme="dark"] .management-card .divider,
[data-theme="dark"] .management-card hr { border-color: var(--border-color) !important; }

/* --- Faculty dashboard rows: schedule and subjects --- */
[data-theme="dark"] .schedule-item,
[data-theme="dark"] .subject-item {
    background: var(--bg-secondary) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
}
[data-theme="dark"] .schedule-item:hover,
[data-theme="dark"] .subject-item:hover {
    background: rgba(255,255,255,0.05) !important;
}
[data-theme="dark"] .subject-avatar {
    border-color: var(--border-color) !important;
}
</style>

<!-- Enhanced Navbar JavaScript with Working Search -->
<script>
// Enhanced Search Functionality - Integration with Backend API
class TimetableSearch {
    constructor() {
        this.searchInput = document.getElementById('navbarSearch');
        this.searchResults = document.getElementById('searchResults');
        this.searchResultsList = document.querySelector('.search-results-list');
        this.searchLoading = document.querySelector('.search-loading');
        this.searchNoResults = document.querySelector('.search-no-results');
        this.searchMinimumQuery = document.querySelector('.search-minimum-query');
        this.searchTimeout = null;
        this.currentQuery = '';
        this.isSearching = false;
        
        this.init();
    }
    
    init() {
        if (!this.searchInput) return;
        
        // Add event listeners
        this.searchInput.addEventListener('input', (e) => this.handleSearchInput(e));
        this.searchInput.addEventListener('keydown', (e) => this.handleKeyDown(e));
        this.searchInput.addEventListener('focus', () => this.handleFocus());
        
        // Close search results when clicking outside
        document.addEventListener('click', (e) => this.handleOutsideClick(e));
        
        // Escape key to close search
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideSearchResults();
            }
        });
    }
    
    handleSearchInput(e) {
        const query = e.target.value.trim();
        this.currentQuery = query;
        
        // Clear previous timeout
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        if (query.length === 0) {
            this.hideSearchResults();
            return;
        }
        
        if (query.length < 2) {
            this.showMinimumQueryMessage();
            return;
        }
        
        // Debounce search - wait 300ms after user stops typing
        this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
        }, 300);
    }
    
    handleKeyDown(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = e.target.value.trim();
            if (query.length >= 2) {
                this.performSearch(query);
            }
        }
    }
    
    handleFocus() {
        if (this.currentQuery.length >= 2) {
            this.showSearchResults();
        } else if (this.currentQuery.length > 0) {
            this.showMinimumQueryMessage();
        }
    }
    
    handleOutsideClick(e) {
        if (!this.searchInput.contains(e.target) && !this.searchResults.contains(e.target)) {
            this.hideSearchResults();
        }
    }
    
    async performSearch(query) {
        if (this.isSearching) return;
        
        this.isSearching = true;
        this.showLoading();
        this.showSearchResults();
        
        try {
            const response = await fetch(`../includes/api/search.php?q=${encodeURIComponent(query)}&category=all&limit=10`);
            const data = await response.json();
            
            if (data.success) {
                this.displayResults(data.results, data.total_results);
            } else {
                this.showError(data.error || 'Search failed');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showError('Network error. Please try again.');
        } finally {
            this.isSearching = false;
            this.hideLoading();
        }
    }
    
    displayResults(results, totalCount) {
        if (totalCount === 0) {
            this.showNoResults();
            return;
        }
        
        this.hideAllStates();
        this.searchResultsList.innerHTML = '';
        
        // Display results by category
        if (results.users && results.users.length > 0) {
            this.addCategoryHeader('Users');
            results.users.forEach(item => this.addResultItem(item));
        }
        
        if (results.subjects && results.subjects.length > 0) {
            this.addCategoryHeader('Subjects');
            results.subjects.forEach(item => this.addResultItem(item));
        }
        
        if (results.classrooms && results.classrooms.length > 0) {
            this.addCategoryHeader('Classrooms');
            results.classrooms.forEach(item => this.addResultItem(item));
        }
        
        if (results.schedules && results.schedules.length > 0) {
            this.addCategoryHeader('Schedules');
            results.schedules.forEach(item => this.addResultItem(item));
        }
        
        this.searchResultsList.style.display = 'block';
    }
    
    addCategoryHeader(title) {
        const header = document.createElement('div');
        header.className = 'search-category-header';
        header.textContent = title;
        this.searchResultsList.appendChild(header);
    }
    
    addResultItem(item) {
        const resultItem = document.createElement('div');
        resultItem.className = 'search-result-item';
        resultItem.onclick = () => this.selectResult(item);
        
        resultItem.innerHTML = `
            <div class="search-result-icon">${item.icon}</div>
            <div class="search-result-content">
                <div class="search-result-title">${this.escapeHtml(item.title)}</div>
                <div class="search-result-subtitle">${this.escapeHtml(item.subtitle)}</div>
                <div class="search-result-description">${this.escapeHtml(item.description)}</div>
            </div>
        `;
        
        this.searchResultsList.appendChild(resultItem);
    }
    
    selectResult(item) {
        // Navigate to the result URL
        if (item.url && item.url !== '#') {
            window.location.href = item.url;
        }
        this.hideSearchResults();
    }
    
    showSearchResults() {
        this.searchResults.style.display = 'block';
        setTimeout(() => {
            this.searchResults.classList.add('show');
        }, 10);
    }
    
    hideSearchResults() {
        this.searchResults.classList.remove('show');
        setTimeout(() => {
            this.searchResults.style.display = 'none';
        }, 200);
    }
    
    showLoading() {
        this.hideAllStates();
        this.searchLoading.classList.add('show');
    }
    
    hideLoading() {
        this.searchLoading.classList.remove('show');
    }
    
    showNoResults() {
        this.hideAllStates();
        this.searchNoResults.classList.add('show');
    }
    
    showMinimumQueryMessage() {
        this.hideAllStates();
        this.searchMinimumQuery.classList.add('show');
        this.showSearchResults();
    }
    
    showError(message) {
        this.hideAllStates();
        this.searchResultsList.innerHTML = `
            <div style="padding: 2rem; text-align: center; color: var(--error-color);">
                <p>${this.escapeHtml(message)}</p>
            </div>
        `;
        this.searchResultsList.style.display = 'block';
    }
    
    hideAllStates() {
        this.searchLoading.classList.remove('show');
        this.searchNoResults.classList.remove('show');
        this.searchMinimumQuery.classList.remove('show');
        this.searchResultsList.style.display = 'none';
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Enhanced theme toggle functionality with full dark mode support
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    // Apply theme to document
    document.documentElement.setAttribute('data-theme', newTheme);
    
    // Save theme preference
    localStorage.setItem('theme', newTheme);
    
    // Update theme toggle icons
    updateThemeIcons(newTheme);
    
    // Apply theme to body and main content immediately
    applyThemeToElements(newTheme);
    
    // Trigger theme change event for other components
    window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: newTheme } }));
}

// Apply theme to body and main content elements
function applyThemeToElements(theme) {
    const body = document.body;
    const html = document.documentElement;
    const mainContent = document.querySelector('.main-content');
    
    // Ensure transitions are smooth
    if (!body.style.transition) {
        body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
    }
    if (!html.style.transition) {
        html.style.transition = 'background-color 0.3s ease, color 0.3s ease';
    }
    if (mainContent && !mainContent.style.transition) {
        mainContent.style.transition = 'all 0.3s ease';
    }
}

// Update theme toggle icons
function updateThemeIcons(theme) {
    const sunIcons = document.querySelectorAll('.sun-icon');
    const moonIcons = document.querySelectorAll('.moon-icon');
    
    if (theme === 'dark') {
        sunIcons.forEach(icon => icon.style.display = 'none');
        moonIcons.forEach(icon => icon.style.display = 'block');
    } else {
        sunIcons.forEach(icon => icon.style.display = 'block');
        moonIcons.forEach(icon => icon.style.display = 'none');
    }
}

// Initialize theme on page load
function initializeTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcons(savedTheme);
    applyThemeToElements(savedTheme);
}

// Toggle functions
function toggleSidebar() {
    const sidebar = document.querySelector('.tms-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;
    
    if (sidebar) {
        sidebar.classList.toggle('mobile-open');
        body.classList.toggle('sidebar-open');
        
        if (overlay) {
            overlay.classList.toggle('show');
        }
    }
}

function toggleNotifications() {
    const menu = document.getElementById('notificationMenu');
    const btn = document.querySelector('.notification-btn');
    
    menu.classList.toggle('show');
    
    if (menu.classList.contains('show')) {
        loadNotifications();
        // Close user menu if open
        document.getElementById('userMenu').classList.remove('show');
        document.querySelector('.user-btn').classList.remove('active');
    }
    
    // Close when clicking outside
    setTimeout(() => {
        document.addEventListener('click', closeNotificationsOnOutsideClick);
    }, 0);
}

function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    const btn = document.querySelector('.user-btn');
    
    menu.classList.toggle('show');
    btn.classList.toggle('active');
    
    if (menu.classList.contains('show')) {
        // Close notification menu if open
        document.getElementById('notificationMenu').classList.remove('show');
    }
    
    // Close when clicking outside
    setTimeout(() => {
        document.addEventListener('click', closeUserMenuOnOutsideClick);
    }, 0);
}

function closeNotificationsOnOutsideClick(event) {
    const menu = document.getElementById('notificationMenu');
    const btn = document.querySelector('.notification-btn');
    
    if (!menu.contains(event.target) && !btn.contains(event.target)) {
        menu.classList.remove('show');
        document.removeEventListener('click', closeNotificationsOnOutsideClick);
    }
}

function closeUserMenuOnOutsideClick(event) {
    const menu = document.getElementById('userMenu');
    const btn = document.querySelector('.user-btn');
    
    if (!menu.contains(event.target) && !btn.contains(event.target)) {
        menu.classList.remove('show');
        btn.classList.remove('active');
        document.removeEventListener('click', closeUserMenuOnOutsideClick);
    }
}

// Shared small helpers
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

function formatRelativeTime(dateInput) {
    try {
        const d = new Date(dateInput);
        const now = new Date();
        const diff = Math.floor((now - d) / 1000);
        if (!isFinite(diff)) return '';
        if (diff < 60) return 'just now';
        const mins = Math.floor(diff / 60);
        if (mins < 60) return `${mins}m ago`;
        const hours = Math.floor(mins / 60);
        if (hours < 24) return `${hours}h ago`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `${days}d ago`;
        return d.toLocaleDateString();
    } catch (e) {
        return '';
    }
}

// Hide search results function
function hideSearchResults() {
    const searchInstance = window.timetableSearch;
    if (searchInstance) {
        searchInstance.hideSearchResults();
    }
}

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        // Redirect to unified logout endpoint (handles session destroy and redirect)
        window.location.href = '/timetable-management/auth/logout.php';
    }
}

// Load notifications (dynamic implementation)
function loadNotifications() {
    const notificationList = document.getElementById('notificationList');
    
    // Show loading state
    notificationList.innerHTML = `
        <div class="loading-notifications">
            <div class="loading-spinner"></div>
            <span>Loading notifications...</span>
        </div>
    `;

    const apiUrl = '/timetable-management/includes/api/notifications.php?action=unread&limit=7';
    fetch(apiUrl, { credentials: 'same-origin' })
        .then(res => res.json())
        .then(data => {
            if (!data || data.success !== true) {
                throw new Error(data && data.error ? data.error : 'Failed to load notifications');
            }

            const items = Array.isArray(data.notifications) ? data.notifications : [];
            if (items.length === 0) {
                notificationList.innerHTML = `
                    <div class="no-notifications">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M13.73 21C13.5542 21.3031 13.3019 21.5556 12.9988 21.7314C12.6956 21.9072 12.3522 21.999 12 21.999C11.6478 21.999 11.3044 21.9072 11.0012 21.7314C10.6981 21.5556 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <div>No new notifications</div>
                    </div>
                `;
                return;
            }

            const html = items.map(n => {
                const type = (n.type || 'info');
                const title = escapeHtml(n.title || 'Notification');
                const message = escapeHtml(n.message || '');
                const time = formatRelativeTime(n.created_at);
                return `
                    <div class="notification-item unread" data-id="${n.notification_id}">
                        <div class="notification-content">
                            <div class="notification-title">${title}</div>
                            <div class="notification-message">${message}</div>
                            <div class="notification-time">${time}</div>
                        </div>
                        <span class="notification-dot"></span>
                    </div>
                `;
            }).join('');

            notificationList.innerHTML = html;
        })
        .catch(err => {
            console.error('Notification load error:', err);
            notificationList.innerHTML = `
                <div class="notification-error">${escapeHtml(err.message || 'Error loading notifications')}</div>
            `;
        });
}

// Mark all notifications as read (dynamic implementation)
function markAllNotificationsRead() {
    const apiUrl = '/timetable-management/includes/api/notifications.php';
    const body = new URLSearchParams({ action: 'mark_all_read' });
    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body
    })
    .then(res => res.json())
    .then(data => {
        if (!data || data.success !== true) {
            throw new Error(data && data.error ? data.error : 'Failed to mark all as read');
        }
        // Refresh list and update badge
        loadNotifications();
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.style.display = 'none';
        }
        const markBtn = document.querySelector('.mark-all-read');
        if (markBtn) {
            markBtn.style.display = 'none';
        }
    })
    .catch(err => {
        console.error('Mark all read error:', err);
        // Optional UI feedback
        const notificationList = document.getElementById('notificationList');
        if (notificationList) {
            notificationList.insertAdjacentHTML('afterbegin', `<div class="notification-error">${escapeHtml(err.message || 'Failed to mark all as read')}</div>`);
        }
    });
}

// Fix viewport switching issues
function handleViewportChange() {
    // Force layout recalculation when viewport changes
    const navbar = document.querySelector('.tms-navbar');
    const sidebar = document.querySelector('.tms-sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (navbar) {
        navbar.style.display = 'none';
        navbar.offsetHeight; // Force reflow
        navbar.style.display = 'flex';
    }
    
    if (sidebar) {
        sidebar.classList.remove('mobile-open');
        document.body.classList.remove('sidebar-open');
    }
    
    if (mainContent) {
        mainContent.style.paddingTop = '64px';
    }
}

// Update current time
function updateCurrentTime() {
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        timeElement.textContent = timeString;
    }
}

// Initialize navbar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize theme
    initializeTheme();
    
    // Initialize time display
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000); // Update every second
    
    // Handle viewport changes
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(handleViewportChange, 250);
    });
    
    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.tms-sidebar');
        const toggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.querySelector('.sidebar-overlay');
        
        if (sidebar && sidebar.classList.contains('mobile-open')) {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('mobile-open');
                document.body.classList.remove('sidebar-open');
                if (overlay) {
                    overlay.classList.remove('show');
                }
            }
        }
    });
});

// Additional notification styles
const additionalStyles = `
<style>
.notification-item {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
    transition: background-color 0.2s;
    position: relative;
}

.notification-item:hover {
    background: var(--bg-secondary);
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item.unread {
    background: rgba(102, 126, 234, 0.05);
}

.notification-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.notification-title {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-primary);
}

.notification-message {
    font-size: 0.8125rem;
    color: var(--text-secondary);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.notification-time {
    font-size: 0.75rem;
    color: var(--text-tertiary);
    margin-top: 0.25rem;
}

.notification-dot {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 8px;
    height: 8px;
    background: var(--primary-color);
    border-radius: 50%;
}

.no-notifications {
    padding: 2rem;
    text-align: center;
    color: var(--text-secondary);
}

.no-notifications svg {
    margin-bottom: 1rem;
    color: var(--text-tertiary);
}

.notification-error {
    padding: 1rem;
    text-align: center;
    color: #ef4444;
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', additionalStyles);
</script>