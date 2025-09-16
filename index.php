 <?php
/**
 * Timetable Management System - Main Entry Point
 * 
 * This file serves as the primary router for the application,
 * directing users to appropriate interfaces based on their
 * authentication status and role.
 * 
 * @author Your Name
 * @version 1.0
 * @since 2025
 */

// Start session for authentication check
session_start();

// Include configuration files
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/User.php';

// Set the timezone
date_default_timezone_set('UTC');

// Basic security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

/**
 * Check if user is authenticated and get their role
 */
function checkAuthentication() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    // Additional security check - verify session hasn't expired
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        // Session expired
        session_unset();
        session_destroy();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Get user's dashboard URL based on their role
 */
function getDashboardUrl($role) {
    switch($role) {
        case 'admin':
            return BASE_URL . 'admin/';
        case 'faculty':
            return BASE_URL . 'faculty/';
        case 'student':
            return BASE_URL . 'student/';
        default:
            return BASE_URL . 'auth/login.php';
    }
}

/**
 * Get system status for display
 */
function getSystemStatus() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_maintenance'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? ($result['setting_value'] === 'true') : false;
    } catch (Exception $e) {
        return false;
    }
}

// Check if system is in maintenance mode
$maintenanceMode = getSystemStatus();

// If in maintenance mode, show maintenance page (except for admin)
if ($maintenanceMode && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    include 'maintenance.php';
    exit;
}

// Check authentication status
$isAuthenticated = checkAuthentication();

if ($isAuthenticated) {
    // User is logged in - redirect to appropriate dashboard
    $dashboardUrl = getDashboardUrl($_SESSION['role']);
    header("Location: $dashboardUrl");
    exit;
} else {
    // User is not logged in - always redirect to public homepage
    header("Location: " . BASE_URL . "public/");
    exit;
}

/**
 * Landing page content (shown when user is not authenticated)
 */
function renderLandingPage() {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($GLOBALS['pageTitle']); ?></title>
        
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        
        <!-- Font Awesome -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <!-- Custom CSS -->
        <style>
            :root {
                --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                --accent-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                --glass-bg: rgba(255, 255, 255, 0.1);
                --glass-border: rgba(255, 255, 255, 0.2);
                --text-primary: #2d3748;
                --text-secondary: #4a5568;
                --shadow-soft: 0 10px 25px rgba(0, 0, 0, 0.1);
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', sans-serif;
                background: var(--primary-gradient);
                min-height: 100vh;
                overflow-x: hidden;
            }

            /* Animated background */
            .bg-animated {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: -1;
                background: var(--primary-gradient);
            }

            .bg-animated::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><radialGradient id="grad1" cx="50%" cy="50%" r="50%"><stop offset="0%" style="stop-color:rgba(255,255,255,0.1);stop-opacity:1" /><stop offset="100%" style="stop-color:rgba(255,255,255,0);stop-opacity:0" /></radialGradient></defs><circle cx="25" cy="25" r="20" fill="url(%23grad1)"/><circle cx="75" cy="75" r="15" fill="url(%23grad1)"/></svg>') repeat;
                animation: float 20s ease-in-out infinite;
            }

            @keyframes float {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                50% { transform: translateY(-20px) rotate(180deg); }
            }

            /* Glassmorphism effect */
            .glass {
                background: var(--glass-bg);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid var(--glass-border);
                border-radius: 20px;
                box-shadow: var(--shadow-soft);
            }

            /* Main container */
            .landing-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem 1rem;
            }

            .main-card {
                max-width: 1000px;
                width: 100%;
                padding: 3rem;
                text-align: center;
                animation: slideUp 0.8s ease-out;
            }

            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Typography */
            .display-1 {
                font-weight: 700;
                color: white;
                margin-bottom: 1.5rem;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .lead {
                color: rgba(255, 255, 255, 0.9);
                font-weight: 400;
                margin-bottom: 3rem;
                font-size: 1.25rem;
                line-height: 1.6;
            }

            /* Feature cards */
            .feature-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 2rem;
                margin: 3rem 0;
            }

            .feature-card {
                padding: 2rem;
                background: rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(10px);
                border-radius: 16px;
                border: 1px solid rgba(255, 255, 255, 0.1);
                transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
                animation: fadeInUp 0.6s ease-out forwards;
                opacity: 0;
            }

            .feature-card:nth-child(1) { animation-delay: 0.2s; }
            .feature-card:nth-child(2) { animation-delay: 0.4s; }
            .feature-card:nth-child(3) { animation-delay: 0.6s; }

            @keyframes fadeInUp {
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
            }

            .feature-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
                background: rgba(255, 255, 255, 0.12);
            }

            .feature-icon {
                font-size: 3rem;
                color: #fff;
                margin-bottom: 1rem;
                display: block;
            }

            .feature-title {
                color: white;
                font-weight: 600;
                margin-bottom: 1rem;
                font-size: 1.25rem;
            }

            .feature-text {
                color: rgba(255, 255, 255, 0.8);
                line-height: 1.6;
            }

            /* Action buttons */
            .action-buttons {
                display: flex;
                gap: 1rem;
                justify-content: center;
                flex-wrap: wrap;
                margin-top: 3rem;
            }

            .btn-custom {
                padding: 1rem 2rem;
                border-radius: 50px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
                position: relative;
                overflow: hidden;
                min-width: 180px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .btn-primary-custom {
                background: rgba(255, 255, 255, 0.2);
                color: white;
                border: 2px solid rgba(255, 255, 255, 0.3);
                backdrop-filter: blur(10px);
            }

            .btn-primary-custom:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                color: white;
            }

            .btn-secondary-custom {
                background: transparent;
                color: white;
                border: 2px solid rgba(255, 255, 255, 0.5);
            }

            .btn-secondary-custom:hover {
                background: rgba(255, 255, 255, 0.1);
                color: white;
                transform: translateY(-2px);
            }

            /* Responsive design */
            @media (max-width: 768px) {
                .main-card {
                    padding: 2rem 1.5rem;
                }

                .display-1 {
                    font-size: 2.5rem;
                }

                .lead {
                    font-size: 1.1rem;
                }

                .feature-grid {
                    grid-template-columns: 1fr;
                    gap: 1.5rem;
                }

                .action-buttons {
                    flex-direction: column;
                    align-items: center;
                }

                .btn-custom {
                    width: 100%;
                    max-width: 300px;
                }
            }

            @media (max-width: 480px) {
                .landing-container {
                    padding: 1rem;
                }

                .main-card {
                    padding: 1.5rem 1rem;
                }

                .display-1 {
                    font-size: 2rem;
                }
            }

            /* Status indicator */
            .status-indicator {
                position: fixed;
                top: 20px;
                right: 20px;
                background: rgba(34, 197, 94, 0.9);
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 25px;
                font-size: 0.875rem;
                font-weight: 500;
                z-index: 1000;
                backdrop-filter: blur(10px);
            }

            .maintenance .status-indicator {
                background: rgba(239, 68, 68, 0.9);
            }
        </style>
    </head>
    <body>
        <div class="bg-animated"></div>
        
        <!-- Status Indicator -->
        <div class="status-indicator">
            <i class="fas fa-circle me-2"></i>
            System Online
        </div>

        <div class="landing-container">
            <div class="main-card glass">
                <!-- Main heading -->
                <h1 class="display-1">
                    <i class="fas fa-calendar-alt me-3"></i>
                    Timetable Management System
                </h1>
                
                <p class="lead">
                    Streamline your academic scheduling with our comprehensive, modern timetable management solution.
                    Built for universities, designed for efficiency.
                </p>

                <!-- Feature cards -->
                <div class="feature-grid">
                    <div class="feature-card">
                        <i class="fas fa-users feature-icon"></i>
                        <h3 class="feature-title">Role-Based Access</h3>
                        <p class="feature-text">
                            Secure authentication system with distinct interfaces for administrators, faculty members, and students.
                        </p>
                    </div>

                    <div class="feature-card">
                        <i class="fas fa-calendar-check feature-icon"></i>
                        <h3 class="feature-title">Smart Scheduling</h3>
                        <p class="feature-text">
                            Intelligent conflict detection ensures no double-booking of faculty, classrooms, or resources.
                        </p>
                    </div>

                    <div class="feature-card">
                        <i class="fas fa-mobile-alt feature-icon"></i>
                        <h3 class="feature-title">Fully Responsive</h3>
                        <p class="feature-text">
                            Access your schedules anywhere, anytime with our mobile-first responsive design approach.
                        </p>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="action-buttons">
                    <a href="auth/login.php" class="btn btn-custom btn-primary-custom">
                        <i class="fas fa-sign-in-alt"></i>
                        Sign In
                    </a>
                    
                    <a href="auth/register.php" class="btn btn-custom btn-secondary-custom">
                        <i class="fas fa-user-plus"></i>
                        Register Account
                    </a>
                </div>

                <!-- Additional info -->
                <div class="mt-5">
                    <p class="text-light opacity-75">
                        <small>
                            <i class="fas fa-shield-alt me-2"></i>
                            Secure • Modern • Professional
                        </small>
                    </p>
                </div>
            </div>
        </div>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- Custom animations -->
        <script>
            // Add smooth animations on scroll
            document.addEventListener('DOMContentLoaded', function() {
                // Animate feature cards on load
                const cards = document.querySelectorAll('.feature-card');
                cards.forEach((card, index) => {
                    setTimeout(() => {
                        card.style.transform = 'translateY(0)';
                        card.style.opacity = '1';
                    }, 200 * (index + 1));
                });

                // Add click effects to buttons
                const buttons = document.querySelectorAll('.btn-custom');
                buttons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        const ripple = document.createElement('span');
                        ripple.style.cssText = `
                            position: absolute;
                            border-radius: 50%;
                            background: rgba(255, 255, 255, 0.5);
                            transform: scale(0);
                            animation: ripple 600ms linear;
                            left: ${e.offsetX - 10}px;
                            top: ${e.offsetY - 10}px;
                            width: 20px;
                            height: 20px;
                        `;
                        
                        this.appendChild(ripple);
                        
                        setTimeout(() => {
                            ripple.remove();
                        }, 600);
                    });
                });
            });

            // CSS for ripple animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        </script>
    </body>
    </html>
    <?php
}

// No fallback rendering; unauthenticated users are redirected to public/
?>