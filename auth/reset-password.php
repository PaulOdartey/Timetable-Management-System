<?php
/**
 * Reset Password Page
 * Timetable Management System
 * 
 * Handles password reset tokens sent to users via email
 */

// Define system access
define('SYSTEM_ACCESS', true);

// Include configuration and classes
require_once '../config/config.php';
require_once '../classes/User.php';

// Initialize user class
$user = new User();

// Get token from URL
$token = $_GET['token'] ?? '';
$resetResult = null;
$errorMessage = '';
$successMessage = '';

// Process password reset if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($password !== $confirm) {
            $errorMessage = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $errorMessage = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
            $errorMessage = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
        } else {
            $resetResult = $user->resetPassword($token, $password);
            if ($resetResult['success']) {
                $successMessage = $resetResult['message'];
            } else {
                $errorMessage = $resetResult['message'];
            }
        }
    } catch (Exception $e) {
        $errorMessage = 'An error occurred during password reset. Please try again or contact support.';
        error_log("Password reset error: " . $e->getMessage());
    }
}

// Validate token for GET view
$tokenValid = false;
if ($token) {
    try {
        $db = Database::getInstance();
        $check = $db->fetchRow("SELECT user_id FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW()", [$token]);
        $tokenValid = $check !== false;
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        $tokenValid = false;
    }
}

$pageTitle = 'Reset Password - ' . SYSTEM_NAME;
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
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            /* Mobile webview compatibility */
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        .reset-container {
            background: rgba(255, 255, 255, 0.95);
            /* Fallback for mobile webviews that don't support backdrop-filter */
            background: #ffffff;
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
            /* Mobile webview compatibility */
            -webkit-transform: translateZ(0);
            transform: translateZ(0);
        }
        
        /* Shimmer effect - disabled for mobile webviews */
        @media not all and (max-width: 768px) {
            .reset-container::before {
                content: '';
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
                transform: rotate(45deg);
                animation: shimmer 3s infinite;
            }
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .reset-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .success-icon {
            color: #28a745;
            animation: bounceIn 0.8s ease-out;
        }
        
        .error-icon {
            color: #dc3545;
            animation: shake 0.8s ease-out;
        }
        
        .loading-icon {
            color: #007bff;
            animation: spin 2s linear infinite;
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .reset-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .reset-message {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        
        .success-title { color: #28a745; }
        .error-title { color: #dc3545; }
        
        .success-message { color: #155724; }
        .error-message { color: #721c24; }
        
        .action-buttons {
            position: relative;
            z-index: 1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            margin: 5px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            margin: 5px;
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
            color: white;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 12px;
            width: 100%;
            font-size: 16px;
        }
        
        .password-input-group {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            font-size: 16px;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #007bff;
        }
        
        .password-rules {
            background: rgba(248, 249, 250, 0.9);
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        
        .password-rules h6 {
            color: #495057;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .rule-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            transition: color 0.3s ease;
        }
        
        .rule-item i {
            margin-right: 8px;
            width: 16px;
        }
        
        .rule-valid {
            color: #28a745;
        }
        
        .rule-invalid {
            color: #dc3545;
        }
        
        .password-match {
            margin-top: 10px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .match-valid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .match-invalid {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }
        
        .support-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            position: relative;
            z-index: 1;
        }
        
        .support-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .support-email {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .support-email:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        
        .system-info {
            margin-top: 20px;
            padding: 15px;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 10px;
            font-size: 0.85rem;
            color: #6c757d;
            position: relative;
            z-index: 1;
        }
        
        /* Mobile Responsive & Webview Compatibility */
        @media (max-width: 768px) {
            body {
                padding: 10px;
                /* Disable complex animations on mobile */
                animation: none !important;
            }
            
            .reset-container {
                margin: 10px;
                padding: 30px 20px;
                /* Simplified background for mobile webviews */
                background: #ffffff;
                backdrop-filter: none;
                border: 1px solid #e9ecef;
                /* Disable complex effects */
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            
            .reset-container::before {
                display: none;
            }
            
            .reset-icon {
                font-size: 3rem;
                /* Disable complex animations on mobile */
                animation: none !important;
            }
            
            .reset-title {
                font-size: 1.5rem;
            }
            
            .reset-message {
                font-size: 1rem;
            }
            
            /* Simplified buttons for mobile webviews */
            .btn {
                background: #007bff !important;
                border: 1px solid #007bff !important;
                transform: none !important;
                transition: none !important;
            }
            
            .btn-secondary {
                background: #6c757d !important;
                border: 1px solid #6c757d !important;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .reset-container {
                background: rgba(33, 37, 41, 0.95);
                color: #f8f9fa;
            }
            
            .success-message { color: #d4edda; }
            .error-message { color: #f8d7da; }
            
            .system-info {
                background: rgba(52, 58, 64, 0.8);
                color: #adb5bd;
            }
            
            .support-text {
                color: #adb5bd;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if (!empty($successMessage)): ?>
            <!-- Success State -->
            <div class="reset-icon success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="reset-title success-title">Password Reset!</h1>
            <p class="reset-message success-message">
                <?php echo htmlspecialchars($successMessage); ?>
            </p>
            <div class="action-buttons">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Login Now
                </a>
                <a href="../public/index.php" class="btn btn-secondary">
                    <i class="fas fa-home me-2"></i>Home
                </a>
            </div>
            
        <?php elseif (!empty($errorMessage) || (!$tokenValid && $token)): ?>
            <!-- Error State -->
            <div class="reset-icon error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="reset-title error-title">Reset Failed</h1>
            <p class="reset-message error-message">
                <?php echo htmlspecialchars($errorMessage ?: 'This password reset link is invalid or has expired.'); ?>
            </p>
            <div class="action-buttons">
                <a href="forgot-password.php" class="btn btn-primary">
                    <i class="fas fa-redo me-2"></i>Request New Link
                </a>
                <a href="login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt me-2"></i>Try Login
                </a>
            </div>
            
        <?php else: ?>
            <!-- Reset Form State -->
            <div class="reset-icon loading-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="reset-title">Set New Password</h1>
            <p class="reset-message">
                Create a strong password to secure your account. Make sure it's unique and memorable.
            </p>
            
            <form method="POST" id="resetPasswordForm">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>New Password
                    </label>
                    <div class="password-input-group">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="Enter new password"
                            required 
                            minlength="8"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-shield-alt me-2"></i>Confirm Password
                    </label>
                    <div class="password-input-group">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Confirm new password"
                            required 
                            minlength="8"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye" id="confirm_password-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Password Requirements -->
                <div class="password-rules" id="passwordRules" style="display: none;">
                    <h6><i class="fas fa-shield-alt me-2"></i>Password Requirements</h6>
                    <div class="rule-item" id="rule-length">
                        <i class="fas fa-times"></i>
                        <span>At least 8 characters</span>
                    </div>
                    <div class="rule-item" id="rule-uppercase">
                        <i class="fas fa-times"></i>
                        <span>One uppercase letter</span>
                    </div>
                    <div class="rule-item" id="rule-lowercase">
                        <i class="fas fa-times"></i>
                        <span>One lowercase letter</span>
                    </div>
                    <div class="rule-item" id="rule-number">
                        <i class="fas fa-times"></i>
                        <span>One number</span>
                    </div>
                    <div class="rule-item" id="rule-special">
                        <i class="fas fa-times"></i>
                        <span>One special character (@$!%*?&)</span>
                    </div>
                </div>
                
                <!-- Password Match Indicator -->
                <div class="password-match" id="passwordMatch" style="display: none;"></div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-check me-2"></i>Update Password
                </button>
            </form>
        <?php endif; ?>
        
        <!-- Support Section -->
        <div class="support-section">
            <p class="support-text">Need help?</p>
            <a href="mailto:<?php echo SYSTEM_EMAIL; ?>" class="support-email">
                <i class="fas fa-envelope me-1"></i><?php echo SYSTEM_EMAIL; ?>
            </a>
        </div>
        
        <!-- System Information -->
        <div class="system-info">
            <strong><?php echo SYSTEM_NAME; ?></strong><br>
            <small>Version <?php echo SYSTEM_VERSION; ?> | <?php echo date('Y'); ?></small>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mobile webview detection
        function isMobileWebview() {
            const userAgent = navigator.userAgent || navigator.vendor || window.opera;
            return /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(userAgent) ||
                   /wv|webview/i.test(userAgent) ||
                   window.navigator.standalone === true;
        }
        
        // Simplified functionality for mobile webviews
        if (!isMobileWebview()) {
            // Smooth animations on load (desktop only)
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.querySelector('.reset-container');
                if (container) {
                    container.style.opacity = '0';
                    container.style.transform = 'translateY(20px)';
                    
                    setTimeout(function() {
                        container.style.transition = 'all 0.6s ease';
                        container.style.opacity = '1';
                        container.style.transform = 'translateY(0)';
                    }, 100);
                }
            });
            
            // Add hover effects (desktop only)
            document.querySelectorAll('.btn').forEach(function(btn) {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px) scale(1.02)';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        }
        
        // Universal functionality (works on all devices)
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure container is visible (fallback)
            const container = document.querySelector('.reset-container');
            if (container && isMobileWebview()) {
                container.style.opacity = '1';
                container.style.transform = 'none';
            }
            
            // Password visibility toggle
            window.togglePassword = function(fieldId) {
                const field = document.getElementById(fieldId);
                const eye = document.getElementById(fieldId + '-eye');
                
                if (field.type === 'password') {
                    field.type = 'text';
                    eye.className = 'fas fa-eye-slash';
                } else {
                    field.type = 'password';
                    eye.className = 'fas fa-eye';
                }
            };
            
            // Password validation
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('confirm_password');
            const rulesDiv = document.getElementById('passwordRules');
            const matchDiv = document.getElementById('passwordMatch');
            
            if (passwordField) {
                passwordField.addEventListener('input', function() {
                    const password = this.value;
                    
                    if (password.length > 0) {
                        rulesDiv.style.display = 'block';
                        validatePassword(password);
                    } else {
                        rulesDiv.style.display = 'none';
                    }
                    
                    checkPasswordMatch();
                });
            }
            
            if (confirmField) {
                confirmField.addEventListener('input', checkPasswordMatch);
            }
            
            function validatePassword(password) {
                const rules = {
                    'rule-length': password.length >= 8,
                    'rule-uppercase': /[A-Z]/.test(password),
                    'rule-lowercase': /[a-z]/.test(password),
                    'rule-number': /\d/.test(password),
                    'rule-special': /[@$!%*?&]/.test(password)
                };
                
                Object.keys(rules).forEach(function(ruleId) {
                    const ruleElement = document.getElementById(ruleId);
                    const icon = ruleElement.querySelector('i');
                    
                    if (rules[ruleId]) {
                        ruleElement.className = 'rule-item rule-valid';
                        icon.className = 'fas fa-check';
                    } else {
                        ruleElement.className = 'rule-item rule-invalid';
                        icon.className = 'fas fa-times';
                    }
                });
            }
            
            function checkPasswordMatch() {
                const password = passwordField ? passwordField.value : '';
                const confirm = confirmField ? confirmField.value : '';
                
                if (confirm.length > 0) {
                    matchDiv.style.display = 'block';
                    
                    if (password === confirm) {
                        matchDiv.className = 'password-match match-valid';
                        matchDiv.innerHTML = '<i class="fas fa-check me-2"></i>Passwords match';
                    } else {
                        matchDiv.className = 'password-match match-invalid';
                        matchDiv.innerHTML = '<i class="fas fa-times me-2"></i>Passwords do not match';
                    }
                } else {
                    matchDiv.style.display = 'none';
                }
            }
            
            // Enhanced form validation
            const form = document.getElementById('resetPasswordForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const password = document.getElementById('password').value;
                    const confirm = document.getElementById('confirm_password').value;
                    
                    if (password !== confirm) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                        return false;
                    }
                    
                    if (password.length < 8) {
                        e.preventDefault();
                        alert('Password must be at least 8 characters long!');
                        return false;
                    }
                    
                    if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/.test(password)) {
                        e.preventDefault();
                        alert('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character!');
                        return false;
                    }
                });
            }
        });
        
        // Simplified clipboard functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('a[href^="mailto:"]')) {
                const email = e.target.textContent.trim();
                if (navigator.clipboard && !isMobileWebview()) {
                    navigator.clipboard.writeText(email).then(function() {
                        const originalText = e.target.innerHTML;
                        e.target.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
                        setTimeout(function() {
                            e.target.innerHTML = originalText;
                        }, 2000);
                    }).catch(function() {
                        // Fallback: do nothing
                    });
                }
            }
        });
        
        // Basic keyboard navigation (universal)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.classList.contains('btn')) {
                e.target.click();
            }
        });
    </script>
</body>
</html>
