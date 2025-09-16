<?php
/**
 * Forgot Password Page
 * Timetable Management System
 * 
 * Handles password reset requests sent to users via email
 */

// Define system access
define('SYSTEM_ACCESS', true);

// Include configuration and classes
require_once '../config/config.php';
require_once '../classes/User.php';

// Initialize user class
$user = new User();

// Initialize variables
$successMessage = '';
$errorMessage = '';

// Process password reset request if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim($_POST['email'] ?? '');

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result = $user->requestPasswordReset($email);
            if ($result['success']) {
                $successMessage = $result['message'];
            } else {
                $errorMessage = $result['message'];
            }
        } else {
            $errorMessage = 'Please enter a valid email address.';
        }
    } catch (Exception $e) {
        $errorMessage = 'An error occurred during password reset request. Please try again or contact support.';
        error_log("Password reset request error: " . $e->getMessage());
    }
}

$pageTitle = 'Password Recovery - ' . SYSTEM_NAME;
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

        .alert i {
            font-size: 1.25rem;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: white;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            display: block;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: #fff;
            padding: 12px 16px;
            font-size: 16px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
            color: #fff;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            font-size: 15px;
        }

        /* Input icon */
        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            z-index: 2;
        }

        .input-group .form-control {
            padding-left: 2.75rem;
        }

        /* Buttons */
        .btn {
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
            color: white;
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-outline {
            background: transparent;
            color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateY(-1px);
        }

        .w-100 {
            width: 100%;
        }

        /* Back link */
        .back-link {
            text-align: center;
            margin-top: 2rem;
        }

        .back-link a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-link a:hover {
            color: white;
            transform: translateX(-2px);
        }

        /* Loading state */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Success state animation */
        .success-animation {
            animation: successPulse 0.6s ease-out;
        }

        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Mobile webview compatibility */
        @media (max-width: 768px) {
            body {
                /* Disable complex animations on mobile */
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            
            body::before {
                /* Simplify background on mobile */
                animation: none;
            }
            
            .auth-card {
                /* Fallback background for mobile webviews */
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: none;
                -webkit-backdrop-filter: none;
            }
            
            .auth-icon {
                /* Reduce animation intensity on mobile */
                animation: none;
            }
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .auth-container {
                max-width: 100%;
                margin: 0 1rem;
            }

            .auth-header {
                padding: 2rem 1.5rem 1rem;
            }

            .auth-body {
                padding: 0 1.5rem 2rem;
            }

            .auth-title {
                font-size: 1.75rem;
            }

            .auth-icon {
                width: 64px;
                height: 64px;
            }

            .auth-icon i {
                font-size: 1.5rem;
            }
        }

        /* Dark theme support */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-primary: #0f172a;
                --bg-secondary: #1e293b;
                --bg-tertiary: #334155;
                --text-primary: #f8fafc;
                --text-secondary: #cbd5e1;
                --border-color: #475569;
            }
        }

        /* Focus visible for accessibility */
        .btn:focus-visible,
        .form-control:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
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
            <h1 class="verification-title success-title">Reset Link Sent!</h1>
            <p class="verification-message success-message">
                <?php echo htmlspecialchars($successMessage); ?>
            </p>
            <div class="action-buttons">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Back to Login
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
            <h1 class="verification-title error-title">Request Failed</h1>
            <p class="verification-message error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
            <div class="action-buttons">
                <a href="forgot-password.php" class="btn btn-primary">
                    <i class="fas fa-redo me-2"></i>Try Again
                </a>
                <a href="login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt me-2"></i>Back to Login
                </a>
            </div>
            
        <?php else: ?>
            <!-- Form State -->
            <div class="verification-icon loading-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1 class="verification-title">Password Recovery</h1>
            <p class="verification-message">
                Enter your email address and we'll send you a secure link to reset your password.
            </p>
            
            <form method="POST" id="forgotPasswordForm">
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-2"></i>Email Address
                    </label>
                    <input 
                        type="email" 
                        class="form-control" 
                        id="email" 
                        name="email" 
                        placeholder="Enter your registered email address"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required 
                        autocomplete="email"
                        style="font-size: 16px; padding: 12px 16px; color: #333; font-weight: 500;"
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                    <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                </button>
            </form>
            
            <div class="action-buttons" style="margin-top: 20px;">
                <a href="login.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                </a>
            </div>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            const submitBtn = document.getElementById('submitBtn');
            const emailInput = document.getElementById('email');

            if (form && submitBtn) {
                // Form submission handling
                form.addEventListener('submit', function(e) {
                    // Add loading state
                    submitBtn.classList.add('btn-loading');
                    submitBtn.innerHTML = '<span>Sending...</span>';
                    submitBtn.disabled = true;
                });

                // Email validation
                if (emailInput) {
                    emailInput.addEventListener('input', function() {
                        const email = this.value.trim();
                        const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                        
                        if (email && !isValid) {
                            this.classList.add('is-invalid');
                        } else {
                            this.classList.remove('is-invalid');
                        }
                    });

                    // Focus email input on load
                    emailInput.focus();
                }
            }

            // Auto-hide alerts after 10 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 10000);
            });
        });

        // Keyboard navigation improvements
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = 'login.php';
            }
        });
    </script>
</body>
</html>