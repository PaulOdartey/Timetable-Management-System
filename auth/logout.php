<?php
/**
 * Logout Confirmation Page
 * Timetable Management System
 * 
 * Professional logout confirmation with security features
 */

// Define system access
define('SYSTEM_ACCESS', true);

// Include configuration
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Check if user is logged in
if (!User::isLoggedIn()) {
    header('Location: login.php?msg=' . urlencode('You are already logged out.'));
    exit;
}

// Get user info for display
$userId = User::getCurrentUserId();
$userRole = User::getCurrentUserRole();
$username = $_SESSION['username'] ?? 'User';

// Attempt to load user's profile image for display on logout confirmation
$profileImage = null;
$profileImageUrl = null;
try {
    $db = Database::getInstance();
    $row = $db->fetchRow("SELECT profile_image FROM users WHERE user_id = ?", [$userId]);
    $profileImage = $row['profile_image'] ?? null;
    $profileImageUrl = $profileImage ? (UPLOADS_URL . 'profiles/' . $profileImage) : (ASSETS_URL . 'images/default-avatar.svg');
} catch (Exception $e) {
    // Fallback to default avatar if database isn't available for any reason
    $profileImageUrl = ASSETS_URL . 'images/default-avatar.svg';
}

// Handle logout confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION[CSRF_TOKEN_NAME]) {
        if (isset($_POST['confirm_logout']) && $_POST['confirm_logout'] === 'yes') {
            // Log the logout event
            getLogger()->info('User logout confirmed', [
                'user_id' => $userId,
                'role' => $userRole,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            // Perform logout
            User::logout();
            
            // Redirect to login with success message
            header('Location: login.php?msg=' . urlencode('You have been successfully logged out. Thank you for using ' . SYSTEM_NAME . '.'));
            exit;
        } else {
            // User cancelled logout
            $redirectUrl = '../' . ($userRole === 'admin' ? 'admin/' : ($userRole === 'faculty' ? 'faculty/' : 'student/'));
            header('Location: ' . $redirectUrl);
            exit;
        }
    } else {
        $error = 'Invalid request. Please try again.';
    }
}

$pageTitle = 'Logout Confirmation - ' . SYSTEM_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --warning-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --shadow-soft: 0 20px 60px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background elements */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: backgroundMove 15s ease-in-out infinite;
            z-index: 1;
        }
        
        @keyframes backgroundMove {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-2%, -2%) rotate(1deg); }
        }
        
        .logout-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 500px;
            margin: 20px;
        }
        
        .logout-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--glass-border);
            padding: 50px 40px;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        
        .logout-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            border-radius: 24px 24px 0 0;
        }
        
        .logout-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3);
            position: relative;
            overflow: hidden;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .logout-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .logout-icon i {
            font-size: 3rem;
            color: white;
            z-index: 2;
            position: relative;
        }
        
        .logout-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }
        
        .logout-message {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .user-info {
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 16px;
            padding: 20px;
            margin: 30px 0;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }
        
        .user-details {
            text-align: left;
            flex: 1;
        }
        
        .user-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .user-role {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 8px;
        }
        
        .role-admin { background: linear-gradient(135deg, #dc3545, #ff6b6b); color: white; }
        .role-faculty { background: linear-gradient(135deg, #28a745, #6bcf7f); color: white; }
        .role-student { background: linear-gradient(135deg, #007bff, #4ea5ff); color: white; }
        
        .warning-section {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 235, 59, 0.1));
            border: 2px solid rgba(255, 193, 7, 0.3);
            border-radius: 16px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .warning-section h6 {
            color: #f57c00;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .warning-list {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: left;
        }
        
        .warning-list li {
            color: #e65100;
            margin-bottom: 8px;
            padding-left: 24px;
            position: relative;
        }
        
        .warning-list li::before {
            content: '\f00d';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            color: #f57c00;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 40px;
        }
        
        .btn-logout {
            padding: 16px 24px;
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            border: none;
            border-radius: 16px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(220, 53, 69, 0.4);
        }
        
        .btn-logout::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-logout:hover::before {
            left: 100%;
        }
        
        .btn-cancel {
            padding: 16px 24px;
            background: rgba(108, 117, 125, 0.1);
            border: 2px solid rgba(108, 117, 125, 0.3);
            border-radius: 16px;
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-cancel:hover {
            background: rgba(108, 117, 125, 0.2);
            border-color: rgba(108, 117, 125, 0.5);
            transform: translateY(-2px);
            color: var(--text-primary);
        }
        
        .session-info {
            background: rgba(23, 162, 184, 0.1);
            border: 1px solid rgba(23, 162, 184, 0.2);
            border-radius: 12px;
            padding: 16px;
            margin-top: 30px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .countdown {
            font-weight: 600;
            color: #ff6b6b;
        }
        
        /* Mobile Responsive */
        @media (max-width: 576px) {
            .logout-container {
                margin: 10px;
                padding: 40px 30px;
                border-radius: 20px;
            }
            
            .logout-icon {
                width: 80px;
                height: 80px;
            }
            
            .logout-icon i {
                font-size: 2.5rem;
            }
            
            .logout-title {
                font-size: 1.75rem;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }
            
            .user-details {
                text-align: center;
            }
        }
        
        /* Loading states */
        .btn-logout.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .btn-logout.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="logout-wrapper">
        <div class="logout-container">
            <!-- Logout Icon -->
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <!-- Title and Message -->
            <h2 class="logout-title">Confirm Logout</h2>
            <p class="logout-message">
                Are you sure you want to end your current session and logout from the system?
            </p>
            
            <!-- User Information -->
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (!empty($profileImageUrl)) : ?>
                        <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo strtoupper(substr($username, 0, 2)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="user-role">
                        Currently signed in as:
                        <span class="role-badge role-<?php echo $userRole; ?>">
                            <?php echo ucfirst($userRole); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Warning Section -->
            <div class="warning-section">
                <h6>
                    <i class="fas fa-exclamation-triangle"></i>
                    Before you logout:
                </h6>
                <ul class="warning-list">
                    <li>Make sure to save any unsaved work</li>
                    <li>You'll need to login again to access the system</li>
                    <li>Your current session will be terminated</li>
                    <li>For security, close your browser after logout</li>
                </ul>
            </div>
            
            <!-- Logout Form -->
            <form method="POST" action="" id="logoutForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                
                <div class="action-buttons">
                    <button type="submit" name="confirm_logout" value="yes" class="btn-logout" id="logoutBtn">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        <span id="logoutText">Yes, Logout</span>
                    </button>
                    
                    <a href="javascript:history.back()" class="btn-cancel" id="cancelBtn">
                        <i class="fas fa-arrow-left me-2"></i>
                        Stay Logged In
                    </a>
                </div>
            </form>
            
            <!-- Session Information -->
            <div class="session-info">
                <div class="d-flex justify-content-between align-items-center">
                    <small>
                        <i class="fas fa-clock me-1"></i>
                        Session ID: <?php echo substr(session_id(), 0, 8); ?>...
                    </small>
                    <small>
                        <i class="fas fa-shield-alt me-1"></i>
                        Secure Connection
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form submission with loading state
        document.getElementById('logoutForm').addEventListener('submit', function(e) {
            const logoutBtn = document.getElementById('logoutBtn');
            const logoutText = document.getElementById('logoutText');
            const cancelBtn = document.getElementById('cancelBtn');
            
            // Add loading state
            logoutBtn.classList.add('loading');
            logoutText.textContent = 'Logging out...';
            cancelBtn.style.pointerEvents = 'none';
            cancelBtn.style.opacity = '0.5';
        });
        
        // Double confirmation for logout
        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            if (!this.classList.contains('confirmed')) {
                e.preventDefault();
                
                // Change button state for confirmation
                this.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Click Again to Confirm';
                this.style.background = 'linear-gradient(135deg, #ff9800, #ff5722)';
                this.classList.add('confirmed');
                
                // Reset after 3 seconds
                setTimeout(() => {
                    if (this.classList.contains('confirmed')) {
                        this.innerHTML = '<i class="fas fa-sign-out-alt me-2"></i><span>Yes, Logout</span>';
                        this.style.background = 'linear-gradient(135deg, #dc3545, #ff6b6b)';
                        this.classList.remove('confirmed');
                    }
                }, 3000);
                
                return false;
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Enter to confirm logout
            if (e.key === 'Enter') {
                document.getElementById('logoutBtn').click();
            }
            // Escape to cancel
            if (e.key === 'Escape') {
                document.getElementById('cancelBtn').click();
            }
        });
        
        // Auto logout countdown (optional - can be removed)
        let countdownSeconds = 300; // 5 minutes
        function updateCountdown() {
            if (countdownSeconds > 0) {
                const minutes = Math.floor(countdownSeconds / 60);
                const seconds = countdownSeconds % 60;
                document.querySelector('.session-info small:first-child').innerHTML = 
                    `<i class="fas fa-clock me-1"></i>Auto-logout in: <span class="countdown">${minutes}:${seconds.toString().padStart(2, '0')}</span>`;
                countdownSeconds--;
                setTimeout(updateCountdown, 1000);
            } else {
                // Auto logout
                document.getElementById('logoutForm').submit();
            }
        }
        
        // Uncomment the line below to enable auto-logout countdown
        // updateCountdown();
        
        // Smooth entrance animation
        window.addEventListener('load', function() {
            const container = document.querySelector('.logout-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px) scale(0.95)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s cubic-bezier(0.4, 0.0, 0.2, 1)';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0) scale(1)';
            }, 100);
        });
        
        // Focus management for accessibility
        document.getElementById('cancelBtn').focus();
    </script>
</body>
</html>