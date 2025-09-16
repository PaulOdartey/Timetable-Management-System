<?php
/**
 * Privacy Policy Page
 * Timetable Management System
 * 
 * Privacy policy and data protection information for users
 */

require_once '../config/config.php';

$pageTitle = "Privacy Policy - University Timetable Management System";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="Privacy policy and data protection information for the University Timetable Management System.">
    
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
            line-height: 1.7;
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

        /* Content */
        .privacy-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .privacy-section {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-content {
            color: var(--text-primary);
            line-height: 1.8;
        }

        .section-content h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 2rem 0 1rem 0;
            color: var(--text-primary);
        }

        .section-content p {
            margin-bottom: 1.5rem;
        }

        .section-content ul {
            margin-bottom: 1.5rem;
            padding-left: 2rem;
        }

        .section-content li {
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }

        .section-content strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Highlight boxes */
        .highlight-box {
            background: var(--primary-color-alpha);
            border: 1px solid var(--primary-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
        }

        .highlight-box h4 {
            color: var(--primary-color);
            margin-top: 0;
        }

        /* Contact info */
        .contact-info {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
        }

        .contact-info h4 {
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .contact-info p {
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
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

            .privacy-section {
                padding: 1.5rem;
            }

            .section-title {
                font-size: 1.5rem;
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
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="privacy.php">Privacy</a>
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
                <h1 class="page-title">Privacy Policy</h1>
                <p class="page-subtitle">
                    Your privacy and data protection are our top priorities
                </p>
                <p class="mt-3 opacity-75">
                    <i class="fas fa-calendar me-2"></i>
                    Last updated: <?= date('F j, Y') ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Privacy Content -->
    <div class="container mb-5">
        <div class="privacy-content">
            
            <!-- Introduction -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Introduction
                </h2>
                <div class="section-content">
                    <p>
                        Welcome to the University Timetable Management System ("TimetableMS", "we", "our", or "us"). 
                        This Privacy Policy explains how we collect, use, disclose, and safeguard your information when 
                        you use our timetable management platform.
                    </p>
                    <p>
                        We are committed to protecting your privacy and ensuring the security of your personal information. 
                        By using our service, you agree to the collection and use of information in accordance with this policy.
                    </p>
                    
                    <div class="highlight-box">
                        <h4><i class="fas fa-shield-alt me-2"></i>Your Privacy Matters</h4>
                        <p>
                            We implement industry-standard security measures and follow best practices to protect 
                            your academic and personal information. We never sell your data to third parties.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Information Collection -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-database"></i>
                    Information We Collect
                </h2>
                <div class="section-content">
                    <h4>Personal Information</h4>
                    <p>When you register for our service, we may collect:</p>
                    <ul>
                        <li><strong>Account Information:</strong> Name, email address, username, and password</li>
                        <li><strong>Academic Information:</strong> Student/Faculty ID, department, role, and designation</li>
                        <li><strong>Contact Details:</strong> Phone number and emergency contact information (optional)</li>
                        <li><strong>Profile Information:</strong> Academic year, semester, specialization, and office location</li>
                    </ul>

                    <h4>Usage Information</h4>
                    <p>We automatically collect certain information about your use of our service:</p>
                    <ul>
                        <li><strong>Log Data:</strong> IP address, browser type, operating system, and access times</li>
                        <li><strong>Activity Data:</strong> Pages visited, features used, and time spent on the platform</li>
                        <li><strong>Device Information:</strong> Device type, screen resolution, and browser settings</li>
                        <li><strong>Cookies:</strong> Small data files stored on your device for improved functionality</li>
                    </ul>

                    <h4>Academic Data</h4>
                    <p>In the course of providing our timetable management services, we process:</p>
                    <ul>
                        <li><strong>Schedule Information:</strong> Class schedules, time slots, and room assignments</li>
                        <li><strong>Enrollment Data:</strong> Subject registrations and academic progress</li>
                        <li><strong>Institutional Data:</strong> Department information, course details, and academic calendar</li>
                    </ul>
                </div>
            </div>

            <!-- How We Use Information -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-cogs"></i>
                    How We Use Your Information
                </h2>
                <div class="section-content">
                    <p>We use the information we collect for the following purposes:</p>
                    
                    <h4>Service Provision</h4>
                    <ul>
                        <li>Create and manage your user account</li>
                        <li>Generate and display personalized timetables</li>
                        <li>Process schedule changes and updates</li>
                        <li>Enable communication between users and administrators</li>
                    </ul>

                    <h4>System Administration</h4>
                    <ul>
                        <li>Monitor system performance and security</li>
                        <li>Troubleshoot technical issues</li>
                        <li>Generate reports and analytics</li>
                        <li>Ensure compliance with academic policies</li>
                    </ul>

                    <h4>Communication</h4>
                    <ul>
                        <li>Send important notifications about schedule changes</li>
                        <li>Provide system updates and announcements</li>
                        <li>Respond to your inquiries and support requests</li>
                        <li>Send administrative communications</li>
                    </ul>

                    <h4>Improvement and Development</h4>
                    <ul>
                        <li>Analyze usage patterns to improve our service</li>
                        <li>Develop new features and functionality</li>
                        <li>Conduct research for educational technology advancement</li>
                    </ul>
                </div>
            </div>

            <!-- Information Sharing -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-share-alt"></i>
                    Information Sharing and Disclosure
                </h2>
                <div class="section-content">
                    <div class="highlight-box">
                        <h4><i class="fas fa-lock me-2"></i>We Don't Sell Your Data</h4>
                        <p>
                            We do not sell, trade, or otherwise transfer your personal information to third parties 
                            for commercial purposes.
                        </p>
                    </div>

                    <p>We may share your information only in the following limited circumstances:</p>

                    <h4>Within Your Institution</h4>
                    <ul>
                        <li>Academic administrators for scheduling and enrollment purposes</li>
                        <li>Faculty members for class roster and attendance tracking</li>
                        <li>IT support staff for technical assistance and system maintenance</li>
                    </ul>

                    <h4>Service Providers</h4>
                    <ul>
                        <li>Hosting providers for secure data storage</li>
                        <li>Email service providers for notifications</li>
                        <li>Security services for system protection</li>
                    </ul>

                    <h4>Legal Requirements</h4>
                    <p>We may disclose your information if required by law or in response to:</p>
                    <ul>
                        <li>Valid legal processes or government requests</li>
                        <li>Protection of our rights and property</li>
                        <li>Safety concerns for users or the public</li>
                        <li>Investigation of suspected fraud or violations</li>
                    </ul>
                </div>
            </div>

            <!-- Data Security -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    Data Security
                </h2>
                <div class="section-content">
                    <p>
                        We implement comprehensive security measures to protect your information against 
                        unauthorized access, alteration, disclosure, or destruction:
                    </p>

                    <h4>Technical Safeguards</h4>
                    <ul>
                        <li><strong>Encryption:</strong> All data is encrypted in transit using TLS 1.3 and at rest using AES-256</li>
                        <li><strong>Access Controls:</strong> Role-based access controls limit data access to authorized personnel</li>
                        <li><strong>Authentication:</strong> Strong password requirements and secure session management</li>
                        <li><strong>Monitoring:</strong> Continuous monitoring for security threats and unauthorized access</li>
                    </ul>

                    <h4>Administrative Safeguards</h4>
                    <ul>
                        <li>Regular security training for all staff members</li>
                        <li>Incident response procedures for security breaches</li>
                        <li>Regular security assessments and audits</li>
                        <li>Data backup and recovery procedures</li>
                    </ul>

                    <h4>Physical Safeguards</h4>
                    <ul>
                        <li>Secure data centers with restricted access</li>
                        <li>Environmental controls and monitoring</li>
                        <li>Equipment disposal and sanitization procedures</li>
                    </ul>
                </div>
            </div>

            <!-- Your Rights -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-user-shield"></i>
                    Your Privacy Rights
                </h2>
                <div class="section-content">
                    <p>You have the following rights regarding your personal information:</p>

                    <h4>Access and Portability</h4>
                    <ul>
                        <li>Request access to your personal information</li>
                        <li>Receive a copy of your data in a portable format</li>
                        <li>Review how your information is being used</li>
                    </ul>

                    <h4>Correction and Updates</h4>
                    <ul>
                        <li>Update your profile information at any time</li>
                        <li>Correct inaccurate or incomplete data</li>
                        <li>Request assistance with data corrections</li>
                    </ul>

                    <h4>Deletion and Restriction</h4>
                    <ul>
                        <li>Request deletion of your account and associated data</li>
                        <li>Restrict certain types of data processing</li>
                        <li>Opt-out of non-essential communications</li>
                    </ul>

                    <div class="contact-info">
                        <h4><i class="fas fa-envelope me-2"></i>Exercise Your Rights</h4>
                        <p>To exercise any of these rights, please contact us at:</p>
                        <p><strong>Email:</strong> privacy@timetablems.edu</p>
                        <p><strong>Phone:</strong> +1 (555) 123-4567</p>
                        <p><strong>Response Time:</strong> We will respond to your request within 30 days</p>
                    </div>
                </div>
            </div>

            <!-- Data Retention -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-archive"></i>
                    Data Retention
                </h2>
                <div class="section-content">
                    <p>We retain your information for different periods depending on the type of data and its purpose:</p>

                    <h4>Active Account Data</h4>
                    <ul>
                        <li><strong>Profile Information:</strong> Retained while your account is active</li>
                        <li><strong>Schedule Data:</strong> Retained for the current and previous academic year</li>
                        <li><strong>Activity Logs:</strong> Retained for 12 months for security purposes</li>
                    </ul>

                    <h4>Inactive Account Data</h4>
                    <ul>
                        <li>Accounts inactive for 2+ years may be archived</li>
                        <li>Essential academic records retained per institutional policy</li>
                        <li>Personal identifiers removed from archived data</li>
                    </ul>

                    <h4>Deleted Account Data</h4>
                    <ul>
                        <li>Most personal information deleted within 30 days</li>
                        <li>Some data may be retained for legal or security purposes</li>
                        <li>Anonymized data may be retained for research purposes</li>
                    </ul>
                </div>
            </div>

            <!-- Cookies and Tracking -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-cookie-bite"></i>
                    Cookies and Tracking
                </h2>
                <div class="section-content">
                    <p>We use cookies and similar tracking technologies to improve your experience:</p>

                    <h4>Essential Cookies</h4>
                    <ul>
                        <li><strong>Session Cookies:</strong> Maintain your login session</li>
                        <li><strong>Security Cookies:</strong> Protect against fraudulent activity</li>
                        <li><strong>Preference Cookies:</strong> Remember your settings and preferences</li>
                    </ul>

                    <h4>Optional Cookies</h4>
                    <ul>
                        <li><strong>Analytics Cookies:</strong> Help us understand how you use our service</li>
                        <li><strong>Performance Cookies:</strong> Monitor system performance and reliability</li>
                    </ul>

                    <p>
                        You can control cookie settings through your browser preferences. Note that disabling 
                        essential cookies may affect the functionality of our service.
                    </p>
                </div>
            </div>

            <!-- Children's Privacy -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-child"></i>
                    Children's Privacy
                </h2>
                <div class="section-content">
                    <p>
                        Our service is designed for university-level education and is not intended for children 
                        under 13 years of age. We do not knowingly collect personal information from children 
                        under 13 without parental consent.
                    </p>
                    <p>
                        If we become aware that we have collected personal information from a child under 13 
                        without verification of parental consent, we will take steps to remove that information 
                        from our servers.
                    </p>
                </div>
            </div>

            <!-- Changes to Privacy Policy -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-edit"></i>
                    Changes to This Privacy Policy
                </h2>
                <div class="section-content">
                    <p>
                        We may update this Privacy Policy from time to time to reflect changes in our practices 
                        or for legal and regulatory reasons. When we make changes, we will:
                    </p>
                    <ul>
                        <li>Update the "Last Updated" date at the top of this policy</li>
                        <li>Notify you through email or system notifications for significant changes</li>
                        <li>Provide prominent notice on our website</li>
                        <li>Give you time to review changes before they take effect</li>
                    </ul>
                    <p>
                        Your continued use of our service after any changes indicates your acceptance of the 
                        updated Privacy Policy.
                    </p>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="privacy-section slide-up">
                <h2 class="section-title">
                    <i class="fas fa-phone"></i>
                    Contact Us
                </h2>
                <div class="section-content">
                    <p>
                        If you have any questions, concerns, or requests regarding this Privacy Policy or 
                        our data practices, please contact us:
                    </p>
                    
                    <div class="contact-info">
                        <h4><i class="fas fa-envelope me-2"></i>Privacy Officer</h4>
                        <p><strong>Email:</strong> privacy@timetablems.edu</p>
                        <p><strong>Phone:</strong> +1 (555) 123-4567</p>
                        <p><strong>Address:</strong> University Campus, IT Department</p>
                        <p><strong>Office Hours:</strong> Monday - Friday, 9:00 AM - 5:00 PM</p>
                    </div>

                    <div class="contact-info">
                        <h4><i class="fas fa-headset me-2"></i>General Support</h4>
                        <p><strong>Email:</strong> support@timetablems.edu</p>
                        <p><strong>Phone:</strong> +1 (555) 987-6543</p>
                        <p><strong>Hours:</strong> 24/7 Technical Support</p>
                    </div>

                    <p>
                        We are committed to addressing your privacy concerns promptly and will respond to 
                        your inquiry within 48 hours during business days.
                    </p>
                </div>
            </div>

        </div>
    </div>

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
                    <h4>Legal & Privacy</h4>
                    <ul>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="#terms">Terms of Service</a></li>
                        <li><a href="#cookies">Cookie Policy</a></li>
                        <li><a href="contact.php">Privacy Concerns</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TimetableMS. All rights reserved. Your privacy is protected.</p>
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