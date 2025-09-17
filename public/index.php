<?php
/**
 * Public Index Page
 * Timetable Management System
 * 
 * Landing page for the timetable management system with user registration
 * and login functionality, system overview, and feature highlights
 */

// Start session
session_start();

// Include configuration
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is already logged in and redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: ' . BASE_URL . 'admin/');
            exit;
        case 'faculty':
            header('Location: ' . BASE_URL . 'faculty/');
            exit;
        case 'student':
            header('Location: ' . BASE_URL . 'student/');
            exit;
    }
}

// Get system statistics for display
try {
    $db = Database::getInstance();
    $systemStats = [
        'total_users' => $db->fetchColumn("SELECT COUNT(*) FROM users WHERE status = 'active'") ?? 0,
        'total_faculty' => $db->fetchColumn("SELECT COUNT(*) FROM faculty f JOIN users u ON f.user_id = u.user_id WHERE u.status = 'active'") ?? 0,
        'total_students' => $db->fetchColumn("SELECT COUNT(*) FROM students s JOIN users u ON s.user_id = u.user_id WHERE u.status = 'active'") ?? 0,
        'total_subjects' => $db->fetchColumn("SELECT COUNT(*) FROM subjects WHERE is_active = 1") ?? 0,
        'total_timetables' => $db->fetchColumn("SELECT COUNT(*) FROM timetables WHERE is_active = 1") ?? 0,
        'total_departments' => $db->fetchColumn("SELECT COUNT(*) FROM departments WHERE is_active = 1") ?? 0
    ];
} catch (Exception $e) {
    // Fallback values if database is not accessible
    $systemStats = [
        'total_users' => 0,
        'total_faculty' => 0,
        'total_students' => 0,
        'total_subjects' => 0,
        'total_timetables' => 0,
        'total_departments' => 0
    ];
}

$pageTitle = "Timetable Management System";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="timetable management system for students, faculty, and administrators. Streamline academic scheduling with our comprehensive platform.">
    <meta name="keywords" content="timetable, schedule, management, academic, students, faculty">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Enhanced Border Visibility Fix -->
    <link href="../assets/css/border-fix.css" rel="stylesheet">

    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-color-alpha: rgba(99, 102, 241, 0.1);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --info-color: #3b82f6;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-color: #cbd5e1;
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border-color: #475569;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--bg-primary);
            transition: all 0.3s ease;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 0;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .navbar {
            background: rgba(15, 23, 42, 0.95) !important;
            border-bottom-color: var(--border-color);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
        }

        .navbar-nav .nav-link {
            color: var(--text-primary) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            background: var(--primary-color-alpha);
            color: var(--primary-color) !important;
        }

        /* Make the active nav link clearly visible */
        .navbar-nav .nav-link.active {
            color: var(--primary-color) !important;
            background: var(--primary-color-alpha);
            border-bottom: 2px solid #ef4444; /* red underline to highlight active */
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8rem 0 6rem;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" style="stop-color:rgba(255,255,255,.1)"/><stop offset="100%" style="stop-color:rgba(255,255,255,0)"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"/><circle cx="800" cy="400" r="150" fill="url(%23a)"/><circle cx="400" cy="800" r="120" fill="url(%23a)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 2rem;
            margin-top: 4rem;
        }

        .hero-stat {
            text-align: center;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .hero-stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: white;
        }

        .hero-stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-color);
            border: none;
            color: #fff;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid white;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline:hover {
            background: white;
            color: var(--primary-color);
        }

        /* Features Section */
        .features-section {
            padding: 6rem 0;
            background: var(--bg-secondary);
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 4rem;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--bg-primary);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            text-align: center;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), #5a67d8);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        .feature-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .feature-description {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* CTA Section */
        .cta-section {
            background: var(--bg-primary);
            padding: 6rem 0;
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .cta-subtitle {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Footer */
        .footer {
            background: var(--bg-tertiary);
            padding: 3rem 0 2rem;
            border-top: 1px solid var(--border-color);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section ul li a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section ul li a:hover {
            color: var(--primary-color);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        /* Ensure in-page anchors account for fixed navbar */
        #help { scroll-margin-top: 90px; }

        /* Theme Toggle */
        .theme-toggle {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .theme-toggle:hover {
            transform: scale(1.1);
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }

        .slide-up {
            animation: slideUp 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }

            .hero-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .section-title {
                font-size: 2rem;
            }

            .cta-title {
                font-size: 2rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .hero-stats {
                grid-template-columns: 1fr;
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body data-theme="light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?= BASE_URL ?>public/">
                <i class="fas fa-calendar-alt me-2"></i>
                TimetableMS
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= BASE_URL ?>public/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="privacy.php">Privacy</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>
                            Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/register.php">
                            <i class="fas fa-user-plus me-1"></i>
                            Register
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content fade-in">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h1 class="hero-title">Modern Timetable Management System</h1>
                        <p class="hero-subtitle">
                            Streamline academic scheduling with our comprehensive platform designed for universities, 
                            faculty, and students. Efficient, reliable, and user-friendly.
                        </p>
                        <div class="d-flex gap-3 flex-wrap">
                            <a href="../auth/register.php" class="btn-primary">
                                <i class="fas fa-rocket"></i>
                                Get Started
                            </a>
                            <a href="#features" class="btn-outline">
                                <i class="fas fa-play"></i>
                                Learn More
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-6 text-center">
                        <div class="hero-stats slide-up">
                            <div class="hero-stat">
                                <div class="hero-stat-number"><?= number_format($systemStats['total_users']) ?>+</div>
                                <div class="hero-stat-label">Active Users</div>
                            </div>
                            <div class="hero-stat">
                                <div class="hero-stat-number"><?= number_format($systemStats['total_faculty']) ?>+</div>
                                <div class="hero-stat-label">Faculty Members</div>
                            </div>
                            <div class="hero-stat">
                                <div class="hero-stat-number"><?= number_format($systemStats['total_students']) ?>+</div>
                                <div class="hero-stat-label">Students</div>
                            </div>
                            <div class="hero-stat">
                                <div class="hero-stat-number"><?= number_format($systemStats['total_subjects']) ?>+</div>
                                <div class="hero-stat-label">Subjects</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="fade-in">
                <h2 class="section-title">Powerful Features</h2>
                <p class="section-subtitle">Everything you need to manage academic schedules efficiently</p>
                
                <div class="feature-grid">
                    <div class="feature-card slide-up">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="feature-title">User Management</h3>
                        <p class="feature-description">
                            Comprehensive user management with role-based access control for administrators, 
                            faculty, and students. Secure registration and approval workflow.
                        </p>
                    </div>
                    
                    <div class="feature-card slide-up">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3 class="feature-title">Smart Scheduling</h3>
                        <p class="feature-description">
                            Advanced timetable creation with conflict detection, resource optimization, 
                            and automated scheduling suggestions for efficient resource utilization.
                        </p>
                    </div>
                    
                    <div class="feature-card slide-up">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3 class="feature-title">Mobile Responsive</h3>
                        <p class="feature-description">
                            Fully responsive design that works seamlessly across all devices. 
                            Access your schedules anytime, anywhere, on any device.
                        </p>
                    </div>
                    
                    <div class="feature-card slide-up">
                        <div class="feature-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3 class="feature-title">Analytics & Reports</h3>
                        <p class="feature-description">
                            Comprehensive reporting and analytics with export functionality. 
                            Generate PDF reports, Excel exports, and system usage statistics.
                        </p>
                    </div>
                    
                    <div class="feature-card slide-up">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3 class="feature-title">Notifications</h3>
                        <p class="feature-description">
                            Real-time notifications for schedule changes, announcements, and important updates. 
                            Email and in-app notification system with role-based targeting.
                        </p>
                    </div>
                    
                    <div class="feature-card slide-up">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="feature-title">Secure & Reliable</h3>
                        <p class="feature-description">
                            Enterprise-grade security with encrypted data, secure authentication, 
                            audit logs, and regular backups to ensure data integrity and privacy.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="fade-in">
                <h2 class="cta-title">Ready to Get Started?</h2>
                <p class="cta-subtitle">
                    Join thousands of institutions already using our timetable management system
                </p>
                <div class="cta-buttons">
                    <a href="../auth/register.php" class="btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </a>
                    <a href="../auth/login.php" class="btn btn-outline-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Sign In
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Help Center Section -->
    <section id="help" class="features-section">
        <div class="container">
            <div class="fade-in text-center">
                <h2 class="section-title">Help Center</h2>
                <p class="section-subtitle">Find answers to common questions or reach out to us</p>
                <div class="feature-grid">
                    <div class="feature-card slide-up">
                        <div class="feature-icon"><i class="fas fa-question-circle"></i></div>
                        <h3 class="feature-title">Getting Started</h3>
                        <p class="feature-description">Create an account, verify your email, and log in to access your dashboard.</p>
                    </div>
                    <div class="feature-card slide-up">
                        <div class="feature-icon"><i class="fas fa-user-shield"></i></div>
                        <h3 class="feature-title">Account Access</h3>
                        <p class="feature-description">If you forgot your password, use the reset link on the login page.</p>
                    </div>
                    <div class="feature-card slide-up">
                        <div class="feature-icon"><i class="fas fa-life-ring"></i></div>
                        <h3 class="feature-title">Need More Help?</h3>
                        <p class="feature-description">Contact our support team and we’ll get back to you shortly.</p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="contact.php" class="btn-primary"><i class="fas fa-envelope"></i> Contact Support</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>
                        <i class="fas fa-calendar-alt me-2 text-primary"></i>
                        TimetableMS
                    </h4>
                    <p>
                        Modern university timetable management system designed to streamline 
                        academic scheduling and enhance educational efficiency.
                    </p>
                </div>
                
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?= BASE_URL ?>public/">Home</a></li>
                        <li><a href="about.php">About</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>For Users</h4>
                    <ul>
                        <li><a href="../auth/login.php">Login</a></li>
                        <li><a href="../auth/register.php">Register</a></li>
                        <li><a href="../auth/forgot-password.php">Forgot Password</a></li>
                        <li><a href="#help">Help Center</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>System Status</h4>
                    <ul>
                        <li>Active Users: <?= number_format($systemStats['total_users']) ?></li>
                        <li>Faculty: <?= number_format($systemStats['total_faculty']) ?></li>
                        <li>Students: <?= number_format($systemStats['total_students']) ?></li>
                        <li>Active Schedules: <?= number_format($systemStats['total_timetables']) ?></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TimetableMS. All rights reserved. Built with ❤️ for education.</p>
            </div>
        </div>
    </footer>

    <!-- Theme Toggle Button -->
    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light mode">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Theme functionality
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('themeIcon');
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Apply saved theme on load
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            const themeIcon = document.getElementById('themeIcon');
            
            document.body.setAttribute('data-theme', savedTheme);
            themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>