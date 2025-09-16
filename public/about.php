<?php
/**
 * About Page
 * Timetable Management System
 * 
 * Information about the system, its features, mission, and development team
 */

require_once '../config/config.php';

$pageTitle = "About Us - University Timetable Management System";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="Learn about our mission to revolutionize academic scheduling through innovative timetable management solutions.">
    
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

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--bg-primary);
            transition: all 0.3s ease;
            padding-top: 80px;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 0;
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

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            background: var(--primary-color-alpha);
            color: var(--primary-color) !important;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
            margin-bottom: 4rem;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .page-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
        }

        /* Content Sections */
        .content-section {
            padding: 3rem 0;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .section-subtitle {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 3rem;
        }

        /* Cards */
        .card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-color), #5a67d8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .card-text {
            color: var(--text-secondary);
            line-height: 1.7;
        }

        /* Team Grid */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .team-card {
            text-align: center;
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .team-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .team-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1.5rem;
            font-weight: 600;
        }

        .team-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .team-role {
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .team-bio {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
            background: var(--bg-secondary);
            border-radius: 16px;
            border: 1px solid var(--border-color);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Footer */
        .footer {
            background: var(--bg-tertiary);
            padding: 3rem 0 2rem;
            border-top: 1px solid var(--border-color);
            margin-top: 4rem;
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
            padding: 0;
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
            .page-title {
                font-size: 2.5rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .team-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
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
                        <a class="nav-link" href="<?= BASE_URL ?>public/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php">About</a>
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

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="text-center fade-in">
                <h1 class="page-title">About TimetableMS</h1>
                <p class="page-subtitle">
                    Revolutionizing academic scheduling through innovative technology and user-centered design
                </p>
            </div>
        </div>
    </section>

    <!-- Mission Section -->
    <section class="content-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="text-center mb-5 fade-in">
                        <h2 class="section-title">Our Mission</h2>
                        <p class="section-subtitle">
                            Empowering educational institutions with efficient scheduling solutions
                        </p>
                    </div>
                    
                    <div class="card slide-up">
                        <div class="card-icon mx-auto">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <div class="text-center">
                            <h3 class="card-title">Transforming Academic Scheduling</h3>
                            <p class="card-text">
                                Our mission is to simplify and streamline the complex process of academic timetable management. 
                                We believe that efficient scheduling is the foundation of successful education, enabling 
                                institutions to maximize resource utilization while providing students and faculty with 
                                clear, accessible schedules that enhance the learning experience.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section class="content-section" style="background: var(--bg-secondary);">
        <div class="container">
            <div class="text-center mb-5 fade-in">
                <h2 class="section-title">Our Core Values</h2>
                <p class="section-subtitle">
                    The principles that guide our development and design decisions
                </p>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card slide-up">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="card-title">User-Centered</h3>
                        <p class="card-text">
                            Every feature is designed with the end user in mind. We prioritize intuitive interfaces, 
                            clear workflows, and responsive design to ensure a seamless experience for administrators, 
                            faculty, and students alike.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card slide-up">
                        <div class="card-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="card-title">Security First</h3>
                        <p class="card-text">
                            We implement enterprise-grade security measures to protect sensitive academic data. 
                            From encrypted communications to secure authentication, your institution's information 
                            is safeguarded at every level.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card slide-up">
                        <div class="card-icon">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <h3 class="card-title">Innovation</h3>
                        <p class="card-text">
                            We continuously evolve our platform with cutting-edge features like automated conflict 
                            detection, intelligent scheduling suggestions, and comprehensive analytics to stay ahead 
                            of educational technology trends.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="content-section">
        <div class="container">
            <div class="text-center mb-5 fade-in">
                <h2 class="section-title">Making an Impact</h2>
                <p class="section-subtitle">
                    Numbers that reflect our commitment to educational excellence
                </p>
            </div>
            
            <div class="stats-grid slide-up">
                <div class="stat-card">
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Institutions Served</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">10K+</div>
                    <div class="stat-label">Active Users</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">99.9%</div>
                    <div class="stat-label">System Uptime</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Support Available</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Technology Section -->
    <section class="content-section" style="background: var(--bg-secondary);">
        <div class="container">
            <div class="text-center mb-5 fade-in">
                <h2 class="section-title">Built with Modern Technology</h2>
                <p class="section-subtitle">
                    Leveraging the latest web technologies for optimal performance and reliability
                </p>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card slide-up">
                        <div class="card-icon">
                            <i class="fas fa-code"></i>
                        </div>
                        <h3 class="card-title">Modern Web Stack</h3>
                        <p class="card-text">
                            Built with PHP 8+, MySQL, Bootstrap 5, and modern JavaScript. Our technology stack 
                            ensures fast performance, cross-browser compatibility, and easy maintenance while 
                            providing a foundation for future enhancements.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card slide-up">
                        <div class="card-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3 class="card-title">Responsive Design</h3>
                        <p class="card-text">
                            Fully responsive interface that adapts seamlessly to desktop, tablet, and mobile devices. 
                            Access your schedules and manage your timetables from anywhere, on any device, 
                            with a consistent user experience.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="content-section">
        <div class="container">
            <div class="text-center mb-5 fade-in">
                <h2 class="section-title">Meet Our Team</h2>
                <p class="section-subtitle">
                    Dedicated professionals committed to educational innovation
                </p>
            </div>
            
            <div class="team-grid">
                <div class="team-card slide-up">
                    <div class="team-avatar">JS</div>
                    <h3 class="team-name">John Smith</h3>
                    <div class="team-role">Lead Developer</div>
                    <p class="team-bio">
                        Full-stack developer with 8+ years of experience in educational technology. 
                        Specializes in scalable web applications and user experience design.
                    </p>
                </div>
                
                <div class="team-card slide-up">
                    <div class="team-avatar">MJ</div>
                    <h3 class="team-name">Maria Johnson</h3>
                    <div class="team-role">UX/UI Designer</div>
                    <p class="team-bio">
                        Creative designer focused on creating intuitive interfaces for educational platforms. 
                        Expert in user research and accessibility design principles.
                    </p>
                </div>
                
                <div class="team-card slide-up">
                    <div class="team-avatar">DW</div>
                    <h3 class="team-name">David Wilson</h3>
                    <div class="team-role">System Architect</div>
                    <p class="team-bio">
                        Infrastructure specialist with expertise in database optimization and system security. 
                        Ensures our platform remains reliable and secure at scale.
                    </p>
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
                    <h4>Contact Info</h4>
                    <ul>
                        <li><i class="fas fa-envelope me-2"></i> info@timetablems.edu</li>
                        <li><i class="fas fa-phone me-2"></i> +1 (555) 123-4567</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i> University Campus</li>
                        <li><i class="fas fa-clock me-2"></i> 24/7 Support</li>
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
            
            // Add scroll animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe all animated elements
            document.querySelectorAll('.slide-up, .fade-in').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = 'all 0.6s ease-out';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>