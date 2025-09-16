<?php
/**
 * User Login Page
 * Timetable Management System
 * 
 * Modern, attractive login interface with advanced design
 * Handles authentication and redirects based on user role
 */

// Define system access
define('SYSTEM_ACCESS', true);

// Include configuration files
require_once '../config/config.php';
require_once '../classes/User.php';

// Initialize user class
$user = new User();

// Check for remember me token first (auto-login)
if (!User::isLoggedIn()) {
    $autoLoggedIn = $user->checkRememberMe();
    if ($autoLoggedIn) {
        // User was automatically logged in via remember me token
        $redirectUrl = $user->getRedirectUrl(User::getCurrentUserRole());
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Check if already logged in
if (User::isLoggedIn() && !User::isSessionExpired()) {
    $redirectUrl = $user->getRedirectUrl(User::getCurrentUserRole());
    header('Location: ' . $redirectUrl);
    exit;
}

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... CSRF validation code ...
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (!empty($email) && !empty($password)) {
        // Call enhanced login method with remember me parameter
        $loginResult = $user->login($email, $password, $rememberMe);
        
        if ($loginResult['success']) {
            header('Location: ' . $loginResult['redirect']);
            exit;
        } else {
            $errors['general'] = $loginResult['message'];
        }
    }
}

// Get remembered email if available
$rememberedEmail = '';
if (isset($_COOKIE['remember_user'])) {
    $cookieData = base64_decode($_COOKIE['remember_user']);
    $parts = explode('|', $cookieData);
    if (count($parts) === 2 && (time() - $parts[1]) < (30 * 24 * 60 * 60)) {
        $rememberedEmail = $parts[0];
    }
}

$pageTitle = 'Login - ' . SYSTEM_NAME;
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
            --secondary-gradient: linear-gradient(135deg, #667eea 20%, #764ba2 80%);
            --accent-gradient: linear-gradient(135deg, #ff6b6b, #ffa500);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --shadow-soft: 0 20px 60px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 60%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            animation: backgroundMove 20s ease-in-out infinite;
            z-index: 1;
        }
        
        @keyframes backgroundMove {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(-5%, -5%) rotate(1deg); }
            66% { transform: translate(5%, -5%) rotate(-1deg); }
        }
        
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            margin: 20px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--glass-border);
            padding: 50px 40px;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-gradient);
            border-radius: 24px 24px 0 0;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .brand-logo {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: var(--shadow-medium);
            position: relative;
            overflow: hidden;
        }
        
        .brand-logo::before {
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
        
        .brand-logo i {
            font-size: 2.5rem;
            color: white;
            z-index: 2;
            position: relative;
        }
        
        .login-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .login-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 400;
            margin-bottom: 0;
        }
        
        .form-group {
            position: relative;
            margin-bottom: 24px;
        }
        
        .form-control {
            width: 100%;
            padding: 16px 20px 16px 54px;
            border: 2px solid rgba(0, 0, 0, 0.06);
            border-radius: 16px;
            font-size: 16px;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.95);
            color: #2d3748;
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
            outline: none;
        }
        
        .form-control:focus {
            border-color: #667eea;
            background: #ffffff;
            color: #2d3748;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .form-control::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }
        
        .form-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.1rem;
            z-index: 10;
            transition: color 0.3s ease;
        }
        
        .form-group:focus-within .form-icon {
            color: #667eea;
        }
        
        .password-toggle {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            z-index: 10;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .password-toggle:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .remember-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        
        .custom-checkbox {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            user-select: none;
        }
        
        .custom-checkbox input[type="checkbox"] {
            appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .custom-checkbox input[type="checkbox"]:checked {
            background: var(--primary-gradient);
            border-color: #667eea;
        }
        
        .custom-checkbox input[type="checkbox"]:checked::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 10px;
        }
        
        .custom-checkbox span {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .custom-checkbox:hover span {
            color: #5a67d8;
        }
        
        .forgot-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .forgot-link:hover {
            color: #5a67d8;
        }
        
        .login-btn {
            width: 100%;
            padding: 16px 32px;
            background: var(--primary-gradient);
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
            margin-bottom: 32px;
        }
        
        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
        }
        
        .login-btn:hover::before {
            left: 100%;
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .login-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .login-btn.loading::after {
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
        
        .divider {
            position: relative;
            text-align: center;
            margin: 32px 0;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
        }
        
        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 20px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
        }
        
        .register-links {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .register-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 16px;
            background: rgba(255, 255, 255, 0.6);
            border: 2px solid rgba(0, 0, 0, 0.06);
            border-radius: 16px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
            backdrop-filter: blur(10px);
        }
        
        .register-link:hover {
            background: rgba(255, 255, 255, 0.9);
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
            color: #667eea;
        }
        
        .register-link i {
            font-size: 1.5rem;
            margin-bottom: 8px;
            transition: transform 0.3s ease;
        }
        
        .register-link:hover i {
            transform: scale(1.1);
        }
        
        .register-link span {
            font-size: 14px;
            font-weight: 600;
            text-align: center;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.1));
            color: #16a34a;
            border-left: 4px solid #22c55e;
        }
        
        .footer-info {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(0, 0, 0, 0.06);
        }
        
        .footer-info p {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
        }
        
        /* Mobile Responsive */
        @media (max-width: 576px) {
            .login-container {
                margin: 10px;
                padding: 40px 30px;
                border-radius: 20px;
            }
            
            .brand-logo {
                width: 70px;
                height: 70px;
                border-radius: 16px;
            }
            
            .brand-logo i {
                font-size: 2rem;
            }
            
            .login-title {
                font-size: 1.75rem;
            }
            
            .register-links {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .register-link {
                flex-direction: row;
                justify-content: center;
                padding: 16px;
            }
            
            .register-link i {
                margin-bottom: 0;
                margin-right: 12px;
                font-size: 1.25rem;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .login-container {
                background: rgba(17, 24, 39, 0.95);
                border: 1px solid rgba(75, 85, 99, 0.3);
            }
            
            .login-title {
                color: #f9fafb;
            }
            
            .login-subtitle {
                color: #d1d5db;
            }
            
            .form-control {
                background: rgba(30, 41, 59, 0.95);
                border-color: rgba(75, 85, 99, 0.3);
                color: #f1f5f9;
            }
            
            .form-control:focus {
                background: rgba(30, 41, 59, 1);
                color: #f1f5f9;
            }
            
            .form-control::placeholder {
                color: #94a3b8;
            }
            
            .register-link {
                background: rgba(17, 24, 39, 0.6);
                border-color: rgba(75, 85, 99, 0.3);
                color: #f9fafb;
            }
            
            .register-link:hover {
                background: rgba(17, 24, 39, 0.9);
                color: #667eea;
            }
        }
        
        /* Accessibility improvements */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        /* Focus styles for keyboard navigation */
        .form-control:focus,
        .login-btn:focus,
        .register-link:focus,
        .forgot-link:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <!-- Header -->
            <div class="login-header">
                <div class="brand-logo">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h1 class="login-title"><?php echo SYSTEM_NAME; ?></h1>
                <p class="login-subtitle">Sign in to access your dashboard</p>
            </div>
            
            <!-- Error/Success Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" action="" id="loginForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                
                <!-- Email Field -->
                <div class="form-group">
                    <i class="fas fa-envelope form-icon"></i>
                    <input type="email" 
                           class="form-control" 
                           name="email" 
                           placeholder="Email Address"
                           value="<?php echo htmlspecialchars($rememberedEmail); ?>"
                           required 
                           autocomplete="email">
                </div>
                
                <!-- Password Field -->
                <div class="form-group">
                    <i class="fas fa-lock form-icon"></i>
                    <input type="password" 
                           class="form-control" 
                           name="password" 
                           id="password"
                           placeholder="Password"
                           required 
                           autocomplete="current-password">
                    <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="passwordToggleIcon"></i>
                    </button>
                </div>
                
                <!-- Remember Me & Forgot Password -->
                <div class="remember-section">
                    <label class="custom-checkbox">
                        <input type="checkbox" 
                               name="remember_me" 
                               value="1"
                               <?php echo !empty($rememberedEmail) ? 'checked' : ''; ?>>
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>
                
                <!-- Login Button -->
                <button type="submit" class="login-btn" id="loginBtn">
                    <span id="btnText">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In
                    </span>
                </button>
            </form>
            
            <!-- Divider -->
            <div class="divider">
                <span>New to the system?</span>
            </div>
            
            <!-- Registration Links -->
            <div class="register-links">
                <a href="register.php?type=faculty" class="register-link">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Faculty<br>Registration</span>
                </a>
                <a href="register.php?type=student" class="register-link">
                    <i class="fas fa-user-graduate"></i>
                    <span>Student<br>Registration</span>
                </a>
            </div>
            
            <!-- Footer -->
            <div class="footer-info">
                <p>Version <?php echo SYSTEM_VERSION; ?> | <?php echo SYSTEM_AUTHOR; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            
            // Don't prevent default - let form submit normally
            loginBtn.classList.add('loading');
            btnText.style.opacity = '0';
            
            // Don't disable form elements - this might interfere with submission
            setTimeout(() => {
                if (loginBtn.classList.contains('loading')) {
                    loginBtn.classList.remove('loading');
                    btnText.style.opacity = '1';
                }
            }, 10000); // Reset after 10 seconds if still loading
        });
        
        // Auto-focus on email field if empty
        window.addEventListener('load', function() {
            const emailField = document.querySelector('input[name="email"]');
            if (!emailField.value) {
                emailField.focus();
            } else {
                document.querySelector('input[name="password"]').focus();
            }
        });
        
        // Enhanced form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const form = document.getElementById('loginForm');
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            }, false);
        })();
        
        // Clear error messages on input
        document.querySelectorAll('input').forEach(function(input) {
            input.addEventListener('input', function() {
                const alert = document.querySelector('.alert-danger');
                if (alert) {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + L for quick login focus
            if (e.altKey && e.key === 'l') {
                e.preventDefault();
                document.querySelector('input[name="email"]').focus();
            }
        });
        
        // Add subtle hover effects
        document.querySelectorAll('.register-link').forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px) scale(1.02)';
            });
            
            link.addEventListener('mouseleave', function() {
                this.style.transform = '';
            });
        });
        
        // Smooth error message appearance
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                alert.style.transition = 'all 0.3s ease';
                alert.style.opacity = '1';
                alert.style.transform = 'translateY(0)';
            }, 100);
        }
        
        // Add loading animation to page
        window.addEventListener('load', function() {
            const container = document.querySelector('.login-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s cubic-bezier(0.4, 0.0, 0.2, 1)';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>