<?php
/**
 * Admin Enrollment Delete - Delete Enrollment Interface
 * Timetable Management System
 * 
 * Professional interface for admin to delete student enrollments
 * with confirmation and comprehensive validation
 */

// Start session and security checks
session_start();

// Include required files
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Enrollment.php';

// Ensure user is logged in and has admin role
User::requireLogin();
User::requireRole('admin');

// Get enrollment ID
$enrollmentId = $_GET['id'] ?? '';
if (empty($enrollmentId)) {
    header('Location: index.php');
    exit;
}

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$enrollmentManager = new Enrollment();

// Initialize variables
$error_message = '';
$success_message = '';
$enrollment = null;
$confirmationStep = true;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmationStep = false;
    
    try {
        $deleteType = $_POST['delete_type'] ?? '';
        
        if ($deleteType === 'permanent') {
            // Permanent deletion
            $result = $enrollmentManager->deleteEnrollment($enrollmentId);
            if ($result['success']) {
                $success_message = "Enrollment has been permanently deleted from the system.";
            } else {
                throw new Exception($result['message'] ?? 'Failed to delete enrollment');
            }
        } elseif ($deleteType === 'deactivate') {
            // Change status to dropped
            $result = $enrollmentManager->updateEnrollmentStatus($enrollmentId, 'dropped');
            if ($result['success']) {
                $success_message = "Enrollment has been marked as dropped and can be reactivated later.";
            } else {
                throw new Exception($result['message'] ?? 'Failed to update enrollment status');
            }
        } else {
            throw new Exception('Please select a deletion type');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $confirmationStep = true;
    }
}

// Get enrollment data for confirmation
if ($confirmationStep) {
    try {
        $enrollment = $enrollmentManager->getEnrollmentById($enrollmentId);
        if (!$enrollment) {
            throw new Exception('Enrollment not found');
        }
    } catch (Exception $e) {
        $error_message = "Error loading enrollment data: " . $e->getMessage();
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Delete Enrollment - Admin Panel</title>
    
    <!-- External CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
     
    <style>
        :root {
            --primary-color: #667eea;
            --primary-color-alpha: rgba(102, 126, 234, 0.2);
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-tertiary: #9ca3af;
            
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            
            --border-color: #e5e7eb;
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --navbar-height: 64px;
        }

        /* Dark Mode Variables */
        [data-theme="dark"] {
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --text-tertiary: #9ca3af;
            
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --bg-tertiary: #374151;
            
            --border-color: #374151;
            --glass-bg: rgba(0, 0, 0, 0.25);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        /* Base Styles */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        body.sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Page Header */
        :root { --navbar-height: 64px; }

        .page-header {
            position: sticky;
            top: var(--navbar-height);
            z-index: 998;
            margin-bottom: 1rem;
            margin-top: 1rem;
        }

        .header-card {
            padding: 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            box-shadow: var(--shadow-md);
        }

        [data-theme="dark"] .page-header .header-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
            border-color: var(--border-color);
        }

        /* Compact header variant */
        .page-header.compact .header-card {
            padding: 0.75rem 1rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .page-header.compact .header-text { 
            display: flex; 
            flex-direction: column; 
        }
        .page-header.compact .page-title {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }
        .page-header.compact .page-subtitle {
            font-size: 0.95rem;
            margin-bottom: 0;
        }
        .btn-action.btn-sm { 
            padding: 0.4rem 0.75rem; 
            border-radius: 10px; 
            font-size: 0.8rem; 
        }
        .page-header .btn-action.btn-sm .back-icon { 
            font-size: 1.2rem; 
            line-height: 1; 
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        /* Confirmation Container */
        .confirmation-container {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 2rem;
        }

        [data-theme="dark"] .confirmation-container {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
        }

        /* Enrollment Info Card */
        .enrollment-info-card {
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .enrollment-info-card {
            background: rgba(30, 41, 59, 0.3);
            border: 1px solid var(--glass-border);
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.25rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        /* Deletion Options */
        .deletion-options {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .deletion-options {
            background: rgba(30, 41, 59, 0.3);
            border: 1px solid var(--glass-border);
        }

        .option-card {
            background: rgba(255, 255, 255, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        [data-theme="dark"] .option-card {
            background: rgba(30, 41, 59, 0.4);
            border: 2px solid var(--glass-border);
        }

        .option-card:hover {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }

        .option-card.selected {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.15);
        }

        .option-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        /* Warning Box */
        .warning-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }

        [data-theme="dark"] .warning-box {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
            border-left: 4px solid #f59e0b;
        }

        /* Badge styles */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-status-enrolled {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .badge-status-dropped {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .badge-status-completed {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }

        .badge-status-failed {
            background: rgba(107, 114, 128, 0.1);
            color: #4b5563;
        }

        /* Action Buttons */
        .btn-action {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-outline {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .confirmation-container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .page-header.compact .header-card {
                flex-direction: row;
                text-align: left;
                gap: 1rem;
            }

            .enrollment-info-card .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .student-avatar {
                margin: 0 auto 1rem auto;
            }

            .confirmation-container .d-flex.gap-3 {
                flex-direction: column;
                gap: 1rem !important;
            }

            .confirmation-container .btn-action {
                justify-content: center;
                width: 100%;
            }
        }

        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-up {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../../includes/navbar.php'; ?>
    
    <!-- Include Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($confirmationStep && $enrollment): ?>
            <!-- Confirmation Page -->
            <div class="page-header compact">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Delete Enrollment</h1>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <div class="confirmation-container glass-card slide-up">
                <!-- Enrollment Information -->
                <div class="enrollment-info-card">
                    <div class="d-flex align-items-center">
                        <div class="student-avatar">
                            <?= strtoupper(substr($enrollment['student_first_name'], 0, 1) . substr($enrollment['student_last_name'], 0, 1)) ?>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1 fw-bold"><?= htmlspecialchars($enrollment['student_first_name'] . ' ' . $enrollment['student_last_name']) ?></h5>
                            <p class="mb-1 text-muted">Student Number: <?= htmlspecialchars($enrollment['student_number']) ?></p>
                            <p class="mb-0 text-muted">Department: <?= htmlspecialchars($enrollment['student_department']) ?></p>
                        </div>
                        <div class="text-end">
                            <span class="badge badge-status-<?= $enrollment['status'] ?>">
                                <?= ucfirst($enrollment['status']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Subject:</strong> <?= htmlspecialchars($enrollment['subject_code'] . ' - ' . $enrollment['subject_name']) ?><br>
                            <strong>Credits:</strong> <?= $enrollment['credits'] ?> Credits<br>
                            <strong>Section:</strong> <?= htmlspecialchars($enrollment['section']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Semester:</strong> Semester <?= $enrollment['semester'] ?><br>
                            <strong>Academic Year:</strong> <?= htmlspecialchars($enrollment['academic_year']) ?><br>
                            <strong>Enrolled:</strong> <?= date('M j, Y', strtotime($enrollment['enrollment_date'])) ?>
                        </div>
                    </div>
                </div>

                <!-- Deletion Options -->
                <form method="POST" id="deleteForm">
                    <div class="deletion-options">
                        <h5 class="mb-3">🔹 Choose Deletion Type</h5>
                        
                        <!-- Deactivate Option -->
                        <div class="option-card" onclick="selectOption('deactivate')">
                            <input type="radio" name="delete_type" value="deactivate" id="deactivate" checked>
                            <div class="d-flex align-items-start">
                                <div class="me-3">
                                    <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        📋
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1">Mark as Dropped (Recommended)</h6>
                                    <p class="mb-0 text-muted">Change enrollment status to "dropped". This preserves the enrollment record for academic history and allows reactivation if needed.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Permanent Delete Option -->
                        <?php if ($enrollment['status'] === 'dropped' || $enrollment['status'] === 'failed'): ?>
                        <div class="option-card" onclick="selectOption('permanent')">
                            <input type="radio" name="delete_type" value="permanent" id="permanent">
                            <div class="d-flex align-items-start">
                                <div class="me-3">
                                    <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        🗑️
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1">Permanent Deletion</h6>
                                    <p class="mb-0 text-muted">Completely remove this enrollment from the system. This action cannot be undone and will permanently delete all associated data.</p>
                                    <div class="warning-box mt-2">
                                        <small><strong>⚠️ Warning:</strong> This action cannot be undone. This enrollment will be permanently deleted.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ Permanent deletion not available</strong><br>
                            Only dropped or failed enrollments can be permanently deleted. 
                            Current enrollment status: <strong><?= ucfirst($enrollment['status']) ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Confirmation Actions -->
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="index.php" class="btn-action btn-outline">
                            ❌ Cancel
                        </a>
                        <button type="submit" class="btn-action btn-danger" id="deleteBtn">
                            🗑️ Confirm Deletion
                        </button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Result Page -->
            <div class="page-header compact">
                <div class="header-card glass-card fade-in">
                    <div class="header-text">
                        <h1 class="page-title">🗑️ Enrollment Deletion</h1>
                        <p class="page-subtitle">Enrollment deletion result</p>
                    </div>
                    <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                        <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                    </a>
                </div>
            </div>

            <div class="confirmation-container glass-card slide-up">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <strong>✅ Success!</strong><br>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                    <div class="text-center">
                        <p>Redirecting to enrollment management in 3 seconds...</p>
                        <a href="index.php" class="btn-action btn-primary">
                            📚 Return to Enrollments
                        </a>
                    </div>
                    <script>
                        // Auto-redirect to index after 3 seconds
                        setTimeout(function() {
                            window.location.href = 'index.php';
                        }, 3000);
                    </script>
                <?php elseif (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <strong>❌ Error!</strong><br>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <div class="text-center">
                        <a href="index.php" class="btn-action btn-primary">
                            📚 Return to Enrollments
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        /**
         * Handle option selection for deletion type
         */
        function selectOption(type) {
            // Remove selected class from all options
            document.querySelectorAll('.option-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
        
        /**
         * Confirm deletion with additional validation
         */
        function confirmDeletion() {
            const deleteType = document.querySelector('input[name="delete_type"]:checked').value;
            const enrollmentInfo = '<?= addslashes($enrollment['student_first_name'] . ' ' . $enrollment['student_last_name'] . ' - ' . $enrollment['subject_code']) ?>';
            
            let message;
            if (deleteType === 'permanent') {
                message = `Are you absolutely sure you want to PERMANENTLY DELETE this enrollment?\n\n` +
                         `Student: ${enrollmentInfo}\n\n` +
                         `This action will:\n` +
                         `• Completely remove the enrollment from the system\n` +
                         `• Delete all associated data and history\n` +
                         `• Cannot be undone\n\n` +
                         `Type "DELETE" to confirm:`;
                         
                const confirmation = prompt(message);
                if (confirmation !== 'DELETE') {
                    alert('Deletion cancelled. You must type "DELETE" exactly to confirm permanent deletion.');
                    return false;
                }
            } else {
                message = `Are you sure you want to mark this enrollment as dropped?\n\n` +
                         `Student: ${enrollmentInfo}\n\n` +
                         `This action will:\n` +
                         `• Set the enrollment status as dropped\n` +
                         `• Preserve all data for potential reactivation\n` +
                         `• Can be reversed later\n\n` +
                         `Click OK to confirm or Cancel to abort.`;
                         
                if (!confirm(message)) {
                    return false;
                }
            }
            
            return true;
        }
        
        /**
         * Initialize page interactions
         */
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Handle sidebar toggle events
            handleSidebarToggle();

            // Add click handlers to option cards
            document.querySelectorAll('.option-card').forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    document.querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
                    
                    // Add selected class to current card
                    this.classList.add('selected');
                    
                    // Check the radio button
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });
            
            // Set initial selected state
            const checkedInput = document.querySelector('input[name="delete_type"]:checked');
            if (checkedInput) {
                checkedInput.closest('.option-card').classList.add('selected');
            }
            
            // Handle form submission with proper loading state
            const deleteForm = document.getElementById('deleteForm');
            if (deleteForm) {
                deleteForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent default submission
                    
                    if (confirmDeletion()) {
                        // Show loading state
                        const deleteBtn = document.getElementById('deleteBtn');
                        deleteBtn.innerHTML = '⏳ Processing...';
                        deleteBtn.disabled = true;
                        
                        // Submit the form
                        this.submit();
                    }
                });
            }
        });

        // Apply current theme
        function applyCurrentTheme() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', currentTheme);
        }

        // Sidebar toggle handler
        function handleSidebarToggle() {
            const sidebar = document.querySelector('.sidebar');
            const body = document.body;

            if (sidebar) {
                const toggleButton = document.querySelector('[data-bs-toggle="collapse"][data-bs-target="#sidebar"]');
                if (toggleButton) {
                    toggleButton.addEventListener('click', function() {
                        setTimeout(() => {
                            body.classList.toggle('sidebar-collapsed');
                        }, 150);
                    });
                }
            }
        }

        // Theme switcher support
        document.addEventListener('themeChanged', function(e) {
            document.documentElement.setAttribute('data-theme', e.detail.theme);
        });
    </script>
</body>
</html>