<?php
/**
 * Contact Page
 * Timetable Management System
 * 
 * Contact form and information for users to reach out for support,
 * feedback, or general inquiries
 */

// Start session for flash messages
session_start();

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Basic validation
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Name is required';
        }
        
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($subject)) {
            $errors[] = 'Subject is required';
        }
        
        if (empty($message)) {
            $errors[] = 'Message is required';
        } elseif (strlen($message) < 10) {
            $errors[] = 'Message must be at least 10 characters long';
        }
        
        if (!empty($errors)) {
            $error_message = implode(', ', $errors);
        } else {
            // In a real application, you would send the email here
            // For demonstration, we'll just show a success message
            $success_message = 'Thank you for your message! We\'ll get back to you within 24 hours.';
            
            // Clear form data on success
            $_POST = [];
        }
        
    } catch (Exception $e) {
        $error_message = 'An error occurred while sending your message. Please try again.';
    }
}

require_once '../config/config.php';

$pageTitle = "Contact Us - University Timetable Management System";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="Get in touch with our team for support, feedback, or inquiries about the timetable management system.">
    
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

        /* Contact Section */
        .contact-section {
            padding: 4rem 0;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: start;
        }

        .contact-info {
            background: var(--bg-secondary);
            padding: 3rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }

        .contact-form {
            background: var(--bg-primary);
            padding: 3rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--bg-primary);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-color), #5a67d8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }

        .contact-details h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .contact-details p {
            color: var(--text-secondary);
            margin: 0;
        }

        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* FAQ Section */
        .faq-section {
            background: var(--bg-secondary);
            padding: 4rem 0;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
            text-align: center;
        }

        .section-subtitle {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 3rem;
            text-align: center;
        }

        .accordion {
            max-width: 800px;
            margin: 0 auto;
        }

        .accordion-button {
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            font-weight: 600;
            padding: 1.25rem 1.5rem;
        }

        .accordion-button:not(.collapsed) {
            background: var(--primary-color-alpha);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .accordion-button:focus {
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        .accordion-body {
            background: var(--bg-primary);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            border-top: none;
            padding: 1.5rem;
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
        @media (max-width: 992px) {
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2.5rem;
            }

            .contact-info,
            .contact-form {
                padding: 2rem;
            }

            .contact-item {
                flex-direction: column;
                text-align: center;
            }

            .contact-icon {
                margin-right: 0;
                margin-bottom: 1rem;
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
                        <a class="nav-link active" href="contact.php">Contact</a>
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
                <h1 class="page-title">Get in Touch</h1>
                <p class="page-subtitle">
                    We're here to help with any questions or support you need
                </p>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="contact-grid">
                <!-- Contact Information -->
                <div class="contact-info slide-up">
                    <h2 class="mb-4" style="color: var(--text-primary); font-weight: 700;">Contact Information</h2>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-details">
                            <h4>Email Us</h4>
                            <p>support@timetablems.edu<br>info@timetablems.edu</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-details">
                            <h4>Call Us</h4>
                            <p>+1 (555) 123-4567<br>Available 24/7</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-details">
                            <h4>Visit Us</h4>
                            <p>University Campus<br>Administration Building, Room 205</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="contact-details">
                            <h4>Response Time</h4>
                            <p>Within 24 hours<br>Emergency support available</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="contact-form slide-up">
                    <h2 class="mb-4" style="color: var(--text-primary); font-weight: 700;">Send us a Message</h2>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                                       placeholder="Your full name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                       placeholder="your.email@example.com" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <select class="form-control" id="subject" name="subject" required>
                                <option value="">Select a subject</option>
                                <option value="general" <?= ($_POST['subject'] ?? '') === 'general' ? 'selected' : '' ?>>
                                    General Inquiry
                                </option>
                                <option value="support" <?= ($_POST['subject'] ?? '') === 'support' ? 'selected' : '' ?>>
                                    Technical Support
                                </option>
                                <option value="feature" <?= ($_POST['subject'] ?? '') === 'feature' ? 'selected' : '' ?>>
                                    Feature Request
                                </option>
                                <option value="bug" <?= ($_POST['subject'] ?? '') === 'bug' ? 'selected' : '' ?>>
                                    Bug Report
                                </option>
                                <option value="account" <?= ($_POST['subject'] ?? '') === 'account' ? 'selected' : '' ?>>
                                    Account Issues
                                </option>
                                <option value="other" <?= ($_POST['subject'] ?? '') === 'other' ? 'selected' : '' ?>>
                                    Other
                                </option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" 
                                      placeholder="Please describe your inquiry in detail..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            <div class="form-text" style="color: var(--text-secondary);">
                                Minimum 10 characters required
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>
                            Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <div class="fade-in">
                <h2 class="section-title">Frequently Asked Questions</h2>
                <p class="section-subtitle">
                    Quick answers to common questions about our timetable management system
                </p>
                
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How do I register for an account?
                            </button>
                        </h3>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                You can register by clicking the "Register" button in the navigation menu. Faculty and students 
                                can self-register, but accounts require administrative approval before activation. Fill out the 
                                registration form with your institutional details and verify your email address.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                What should I do if I forget my password?
                            </button>
                        </h3>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Use the "Forgot Password" link on the login page. Enter your email address and we'll send you 
                                a secure reset link. Follow the instructions in the email to create a new password. The reset 
                                link expires after 1 hour for security purposes.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                How do I view my class schedule?
                            </button>
                        </h3>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                After logging in, navigate to your dashboard where you'll find your personalized schedule. 
                                Students can view their class timetable, while faculty can see their teaching schedules. 
                                You can also export your schedule as a PDF for offline viewing.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Can I access the system on my mobile device?
                            </button>
                        </h3>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes! Our system is fully responsive and works seamlessly on smartphones, tablets, and desktop 
                                computers. The interface automatically adapts to your screen size, providing an optimal 
                                experience across all devices.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                Who do I contact for technical support?
                            </button>
                        </h3>
                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                For technical support, you can email us at support@timetablems.edu or use the contact form above. 
                                We provide 24/7 support and typically respond within 24 hours. For urgent issues, you can also 
                                call our support hotline at +233 (544250759).
                            </div>
                        </div>
                    </div>
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
                    <h4>Support</h4>
                    <ul>
                        <li><a href="../auth/login.php">Login Help</a></li>
                        <li><a href="../auth/register.php">Registration Guide</a></li>
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="mailto:support@timetablems.edu">Email Support</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <ul>
                        <li><i class="fas fa-envelope me-2"></i> support@timetablems.edu</li>
                        <li><i class="fas fa-phone me-2"></i> +233 (544) 250-0759</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i> University Campus</li>
                        <li><i class="fas fa-clock me-2"></i> 24/7 Support Available</li>
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
            
            // Form validation
            const form = document.querySelector('form');
            const messageTextarea = document.getElementById('message');
            
            form.addEventListener('submit', function(e) {
                const message = messageTextarea.value.trim();
                
                if (message.length < 10) {
                    e.preventDefault();
                    alert('Message must be at least 10 characters long.');
                    messageTextarea.focus();
                }
            });
            
            // Character counter for message
            messageTextarea.addEventListener('input', function() {
                const length = this.value.length;
                const formText = this.nextElementSibling;
                
                if (length < 10) {
                    formText.textContent = `Minimum 10 characters required (${length}/10)`;
                    formText.style.color = 'var(--error-color)';
                } else {
                    formText.textContent = `${length} characters`;
                    formText.style.color = 'var(--text-secondary)';
                }
            });
            
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