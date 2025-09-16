<?php
/**
 * Email Verification Page
 * Timetable Management System
 * 
 * Handles email verification tokens sent to users during registration
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
$verificationResult = null;
$errorMessage = '';
$successMessage = '';

// Process verification if token is provided
if (!empty($token)) {
    try {
        // Verify the email token
        $verificationResult = $user->verifyEmail($token);
        
        if ($verificationResult['success']) {
            $successMessage = $verificationResult['message'];
        } else {
            $errorMessage = $verificationResult['message'];
        }
    } catch (Exception $e) {
        $errorMessage = 'An error occurred during verification. Please try again or contact support.';
        error_log("Email verification error: " . $e->getMessage());
    }
} else {
    $errorMessage = 'No verification token provided. Please check your email for the verification link.';
}

$pageTitle = 'Email Verification - ' . SYSTEM_NAME;
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
        
        .verification-container {
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
            .verification-container::before {
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
        
        .verification-icon {
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
        
        .verification-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .verification-message {
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
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        /* Mobile Responsive & Webview Compatibility */
        @media (max-width: 768px) {
            body {
                padding: 10px;
                /* Disable complex animations on mobile */
                animation: none !important;
            }
            
            .verification-container {
                margin: 10px;
                padding: 30px 20px;
                /* Simplified background for mobile webviews */
                background: #ffffff;
                backdrop-filter: none;
                border: 1px solid #e9ecef;
                /* Disable complex effects */
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            
            .verification-container::before {
                display: none;
            }
            
            .verification-icon {
                font-size: 3rem;
                /* Disable complex animations on mobile */
                animation: none !important;
            }
            
            .verification-title {
                font-size: 1.5rem;
            }
            
            .verification-message {
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
            .verification-container {
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
    <div class="verification-container">
        <?php if (!empty($successMessage)): ?>
            <!-- Success State -->
            <div class="verification-icon success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="verification-title success-title">Email Verified!</h1>
            <p class="verification-message success-message">
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
            
        <?php elseif (!empty($errorMessage)): ?>
            <!-- Error State -->
            <div class="verification-icon error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="verification-title error-title">Verification Failed</h1>
            <p class="verification-message error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
            <div class="action-buttons">
                <a href="register.php" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Register Again
                </a>
                <a href="login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt me-2"></i>Try Login
                </a>
            </div>
            
        <?php else: ?>
            <!-- Loading State -->
            <div class="verification-icon loading-icon">
                <i class="fas fa-spinner"></i>
            </div>
            <h1 class="verification-title">Verifying Email...</h1>
            <p class="verification-message">
                <span class="loading-spinner"></span>
                Please wait while we verify your email address.
            </p>
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
            // Auto-refresh for loading state (desktop only)
            <?php if (empty($successMessage) && empty($errorMessage) && !empty($token)): ?>
            setTimeout(function() {
                window.location.reload();
            }, 2000);
            <?php endif; ?>
            
            // Smooth animations on load (desktop only)
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.querySelector('.verification-container');
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
            const container = document.querySelector('.verification-container');
            if (container && isMobileWebview()) {
                container.style.opacity = '1';
                container.style.transform = 'none';
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
        
        // Mobile webview refresh fallback
        <?php if (empty($successMessage) && empty($errorMessage) && !empty($token)): ?>
        if (isMobileWebview()) {
            // Longer timeout for mobile webviews
            setTimeout(function() {
                window.location.href = window.location.href;
            }, 3000);
        }
        <?php endif; ?>
    </script>
</body>
</html>
