<?php
/**
 * Admin Enrollments Create - Student Enrollment Interface
 * Timetable Management System
 * 
 * Professional interface for admin to enroll students in subjects
 * with capacity checking, prerequisite validation, and conflict detection
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

// Get current user info
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$enrollmentManager = new Enrollment();

// Initialize variables
$error_message = '';
$success_message = '';
$students = [];
$subjects = [];
$sections = [];
$availableSections = [];
$formData = [];
$enrollmentMode = 'single'; // single or bulk

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capture form data for repopulation on error
        $formData = $_POST;
        $enrollmentMode = $_POST['enrollment_mode'] ?? 'single';
        
        if ($enrollmentMode === 'single') {
            // Single student enrollment
            $required_fields = ['student_id', 'subject_id', 'section', 'semester', 'academic_year'];
            $missing_fields = [];
            
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
                }
            }
            
            if (!empty($missing_fields)) {
                throw new Exception('Please fill in all required fields: ' . implode(', ', $missing_fields));
            }
            
            // Prepare enrollment data
            $enrollmentData = [
                'student_id' => (int)$_POST['student_id'],
                'subject_id' => (int)$_POST['subject_id'],
                'section' => trim($_POST['section']),
                'semester' => (int)$_POST['semester'],
                'academic_year' => trim($_POST['academic_year']),
                'enrolled_by' => $userId
            ];
            
            // Create enrollment using Enrollment::enrollStudent
            $result = $enrollmentManager->enrollStudent(
                $enrollmentData['student_id'],
                $enrollmentData['subject_id'],
                $enrollmentData['semester'],
                $enrollmentData['academic_year'],
                $enrollmentData['enrolled_by'],
                $enrollmentData['section']
            );
            
            if ($result['success']) {
                $success_message = $result['message'];
                $formData = []; // Clear form data on success
            } else {
                $error_message = $result['message'];
            }
            
        } else {
            // Bulk enrollment
            if (empty($_POST['selected_students']) || empty($_POST['bulk_subject_id'])) {
                throw new Exception('Please select students and a subject for bulk enrollment.');
            }
            
            $selectedStudents = $_POST['selected_students'];
            $bulkData = [
                'subject_id' => (int)$_POST['bulk_subject_id'],
                'section' => trim($_POST['bulk_section']),
                'semester' => (int)$_POST['bulk_semester'],
                'academic_year' => trim($_POST['bulk_academic_year']),
                'enrolled_by' => $userId
            ];
            
            // Build enrollments array expected by Enrollment::bulkEnrollStudents
            $enrollments = array_map(function($studentId) use ($bulkData) {
                return [
                    'student_id' => (int)$studentId,
                    'subject_id' => $bulkData['subject_id'],
                    'semester' => $bulkData['semester'],
                    'academic_year' => $bulkData['academic_year'],
                    'section' => $bulkData['section']
                ];
            }, $selectedStudents);

            $result = $enrollmentManager->bulkEnrollStudents($enrollments, $userId);
            
            if ($result['success']) {
                $success_message = $result['message'];
                $formData = [];
            } else {
                $error_message = $result['message'];
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle AJAX requests for dynamic data
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['action']) {
            case 'get_sections':
                $subjectId = (int)$_GET['subject_id'];
                $semester = (int)$_GET['semester'];
                $academicYear = $_GET['academic_year'];
                
                $sections = $db->fetchAll("
                    SELECT DISTINCT t.section, 
                           c.capacity,
                           COUNT(e.enrollment_id) as enrolled_count,
                           (c.capacity - COUNT(e.enrollment_id)) as available_spots,
                           CONCAT(ts.day_of_week, ' ', TIME_FORMAT(ts.start_time, '%H:%i'), '-', TIME_FORMAT(ts.end_time, '%H:%i')) as schedule,
                           CONCAT(f.first_name, ' ', f.last_name) as faculty_name
                    FROM timetables t
                    JOIN classrooms c ON t.classroom_id = c.classroom_id
                    JOIN time_slots ts ON t.slot_id = ts.slot_id
                    JOIN faculty f ON t.faculty_id = f.faculty_id
                    LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
                                             AND t.section = e.section 
                                             AND t.semester = e.semester 
                                             AND t.academic_year = e.academic_year
                                             AND e.status = 'enrolled'
                    WHERE t.subject_id = ? 
                      AND t.semester = ? 
                      AND t.academic_year = ?
                      AND t.is_active = 1
                    GROUP BY t.section, c.capacity, t.timetable_id
                    ORDER BY t.section
                ", [$subjectId, $semester, $academicYear]);
                
                echo json_encode(['success' => true, 'sections' => $sections]);
                exit;
                
            case 'check_capacity':
                $subjectId = (int)$_GET['subject_id'];
                $section = $_GET['section'];
                $semester = (int)$_GET['semester'];
                $academicYear = $_GET['academic_year'];
                
                $capacity_info = $db->fetchRow("
                    SELECT c.capacity,
                           COUNT(e.enrollment_id) as enrolled_count,
                           (c.capacity - COUNT(e.enrollment_id)) as available_spots
                    FROM timetables t
                    JOIN classrooms c ON t.classroom_id = c.classroom_id
                    LEFT JOIN enrollments e ON t.subject_id = e.subject_id 
                                             AND t.section = e.section 
                                             AND t.semester = e.semester 
                                             AND t.academic_year = e.academic_year
                                             AND e.status = 'enrolled'
                    WHERE t.subject_id = ? 
                      AND t.section = ? 
                      AND t.semester = ? 
                      AND t.academic_year = ?
                      AND t.is_active = 1
                    GROUP BY c.capacity
                ", [$subjectId, $section, $semester, $academicYear]);
                
                echo json_encode(['success' => true, 'capacity' => $capacity_info]);
                exit;
                
            case 'check_prerequisites':
                $studentId = (int)$_GET['student_id'];
                $subjectId = (int)$_GET['subject_id'];
                
                $result = $enrollmentManager->checkPrerequisites($studentId, $subjectId);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

try {
    // Get active students
    $students = $db->fetchAll("
        SELECT s.student_id, s.student_number, 
               CONCAT(s.first_name, ' ', s.last_name) as full_name,
               s.department, s.year_of_study, s.semester as current_semester,
               u.email, u.status
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        WHERE u.status = 'active'
        ORDER BY s.department, s.year_of_study, s.first_name, s.last_name
    ");
    
    // Get active subjects
    $subjects = $db->fetchAll("
        SELECT s.subject_id, s.subject_code, s.subject_name, s.credits,
               s.department, s.semester, s.prerequisites,
               COUNT(t.timetable_id) as available_sections
        FROM subjects s
        LEFT JOIN timetables t ON s.subject_id = t.subject_id AND t.is_active = 1
        WHERE s.is_active = 1
        GROUP BY s.subject_id
        HAVING available_sections > 0
        ORDER BY s.department, s.semester, s.subject_code
    ");
    
    // Get current academic year from system settings
    $currentAcademicYear = $db->fetchColumn("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'academic_year_current' AND is_active = 1
    ") ?: date('Y') . '-' . (date('Y') + 1);
    
} catch (Exception $e) {
    error_log("Create Enrollment Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the page.";
}

// Set page title
$pageTitle = "Create Enrollment";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?> - Admin Panel</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   
   
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-color-alpha: rgba(99, 102, 241, 0.1);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --info-color: #3b82f6;
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border-color: #475569;
            --navbar-height: 64px;
            --sidebar-width: 280px;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --border-color: #475569;
        }

        [data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-color: #cbd5e1;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--navbar-height) + 2rem);
        }

        body.sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Page Header - Sticky under navbar */
        .page-header {
            position: sticky;
            top: var(--navbar-height);
            z-index: 998;
            margin-bottom: 1rem;
            margin-top: 1rem;
        }

        .header-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        [data-theme="dark"] .header-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
            border-color: var(--border-color);
        }

        .header-text {
            display: flex;
            flex-direction: column;
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        /* Back button styling */
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .back-icon {
            font-size: 1rem;
            font-weight: bold;
        }

        /* Glass card styling */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] .glass-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .glass-card {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Form Container */
        .form-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        [data-theme="dark"] .form-container {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .form-container {
            background: var(--bg-primary);
            border-color: var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] .form-section {
            border-bottom-color: var(--border-color);
        }

        [data-theme="light"] .form-section {
            border-bottom-color: var(--border-color);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .form-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form Controls */
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control, .form-select {
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
            background: rgba(255, 255, 255, 0.7);
        }

        /* Dark mode form controls */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color-alpha);
        }

        /* Light mode: stronger input/select borders for visibility */
        body:not([data-theme="dark"]) .form-control,
        body:not([data-theme="dark"]) .form-select {
            border-color: #cbd5e1;
            border-width: 2px;
            background: #ffffff;
        }

        body:not([data-theme="dark"]) .form-control:focus,
        body:not([data-theme="dark"]) .form-select:focus {
            background: #ffffff;
            box-shadow: 0 0 0 4px var(--primary-color-alpha);
        }

        .form-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Enrollment Mode Toggle */
        .enrollment-mode-toggle {
            display: flex;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.25rem;
            margin-bottom: 1.5rem;
        }

        [data-theme="dark"] .enrollment-mode-toggle {
            background: var(--bg-tertiary);
        }

        [data-theme="light"] .enrollment-mode-toggle {
            background: var(--bg-secondary);
        }

        .mode-option {
            flex: 1;
            padding: 0.75rem 1rem;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .mode-option.active {
            background: var(--primary-color);
            color: white;
        }

        .mode-option:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }

        /* Capacity Information */
        .capacity-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .capacity-info.warning {
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.3);
            color: var(--warning-color);
        }

        .capacity-info.danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: var(--error-color);
        }

        /* Student Selection Grid */
        .student-selection-grid {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
        }

        .student-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        [data-theme="dark"] .student-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .student-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .student-card:hover {
            background: var(--primary-color-alpha);
            border-color: var(--primary-color);
        }

        .student-card.selected {
            background: var(--primary-color-alpha);
            border-color: var(--primary-color);
        }

        .student-card .form-check-input {
            margin-right: 0.75rem;
        }

        /* Section Cards */
        .section-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        [data-theme="dark"] .section-card {
            background: var(--bg-tertiary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .section-card {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        .section-card:hover {
            background: var(--primary-color-alpha);
            border-color: var(--primary-color);
        }

        .section-card.selected {
            background: var(--primary-color-alpha);
            border-color: var(--primary-color);
        }

        .section-card.full {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .capacity-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .capacity-badge.available {
            background: var(--success-color);
            color: white;
        }

        .capacity-badge.limited {
            background: var(--warning-color);
            color: white;
        }

        .capacity-badge.full {
            background: var(--error-color);
            color: white;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
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

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        /* Button Styles */
        .btn-action {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            color: white;
        }

        .btn-info {
            background: var(--info-color);
            color: white;
        }

        .btn-info:hover {
            background: #2563eb;
            transform: translateY(-1px);
            color: white;
        }

        /* Select2 Styling */
        .select2-container .select2-selection--single {
            height: 42px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
        }

        .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
            padding-left: 12px;
            color: var(--text-primary);
        }

        [data-theme="dark"] .select2-container .select2-selection--single {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }

        [data-theme="light"] .select2-container .select2-selection--single {
            background: #ffffff;
            border-color: #cbd5e1;
            border-width: 2px;
        }

        .select2-dropdown {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .select2-results__option {
            color: var(--text-primary);
        }

        .select2-results__option--highlighted {
            background: var(--primary-color);
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: calc(var(--navbar-height) + 1rem);
            }

            .form-container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            /* Keep compact header inline on mobile */
            .header-card {
                flex-direction: row;
                text-align: left;
                gap: 1rem;
            }

            .form-container {
                padding: 1rem;
            }

            .section-cards {
                grid-template-columns: 1fr;
            }

            .enrollment-mode-toggle {
                flex-direction: column;
            }
        }

        /* Animation classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        .slide-up {

            animation: slideUp 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-card glass-card fade-in">
                <div class="header-text">
                    <h1 class="page-title">📚 Create Enrollment</h1>
                </div>
                <a href="index.php" class="btn-action btn-outline btn-sm" aria-label="Back">
                    <span class="back-icon" aria-hidden="true">←</span> <span class="d-none d-sm-inline">Back</span>
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success glass-card fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong> <?= htmlspecialchars($success_message) ?>
            </div>
            <script>
                // Redirect to index after 2 seconds so user can see the success message
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger glass-card fade-in" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Enrollment Form -->
        <div class="form-container glass-card slide-up">
            <form method="POST" id="enrollmentForm" novalidate>
                <!-- Enrollment Mode Selection -->
                <div class="form-section">
                    <h3 class="form-section-title">🎯 Enrollment Mode</h3>
                    <div class="enrollment-mode-toggle">
                        <div class="mode-option <?= $enrollmentMode === 'single' ? 'active' : '' ?>" 
                             onclick="selectMode('single')">
                            <i class="fas fa-user"></i> Single Student
                        </div>
                        <div class="mode-option <?= $enrollmentMode === 'bulk' ? 'active' : '' ?>" 
                             onclick="selectMode('bulk')">
                            <i class="fas fa-users"></i> Bulk Enrollment
                        </div>
                    </div>
                    <input type="hidden" name="enrollment_mode" id="enrollment_mode" value="<?= $enrollmentMode ?>">
                </div>

                <!-- Single Student Enrollment -->
                <div id="single-enrollment" class="enrollment-section <?= $enrollmentMode === 'single' ? '' : 'd-none' ?>">
                    <div class="form-section">
                        <h3 class="form-section-title">👤 Student Selection</h3>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="student_id" class="form-label">Select Student *</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">Choose a student...</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= $student['student_id'] ?>" 
                                                data-department="<?= htmlspecialchars($student['department']) ?>"
                                                data-year="<?= $student['year_of_study'] ?>"
                                                data-semester="<?= $student['current_semester'] ?>"
                                                <?= ($formData['student_id'] ?? '') == $student['student_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($student['student_number'] . ' - ' . $student['full_name'] . ' (' . $student['department'] . ', Year ' . $student['year_of_study'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    Search by student number, name, or department
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Student Info</label>
                                <div id="student-info" class="capacity-info" style="display: none;">
                                    <div id="student-details"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">📖 Subject & Academic Info</h3>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="subject_id" class="form-label">Select Subject *</label>
                                <select class="form-select" id="subject_id" name="subject_id" required>
                                    <option value="">Choose a subject...</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?= $subject['subject_id'] ?>" 
                                                data-department="<?= htmlspecialchars($subject['department']) ?>"
                                                data-semester="<?= $subject['semester'] ?>"
                                                data-credits="<?= $subject['credits'] ?>"
                                                data-prerequisites="<?= htmlspecialchars($subject['prerequisites']) ?>"
                                                <?= ($formData['subject_id'] ?? '') == $subject['subject_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (' . $subject['credits'] . ' credits)') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <i class="fas fa-book"></i> 
                                    Subjects with available sections only
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="semester" class="form-label">Semester *</label>
                                <select class="form-select" id="semester" name="semester" required>
                                    <option value="">Select...</option>
                                    <?php for ($i = 1; $i <= 2; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($formData['semester'] ?? '') == $i ? 'selected' : '' ?>>
                                            Semester <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="academic_year" class="form-label">Academic Year *</label>
                                <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                       value="<?= htmlspecialchars($formData['academic_year'] ?? $currentAcademicYear) ?>" 
                                       placeholder="2024-25" required>
                            </div>
                        </div>

                        <!-- Prerequisites Warning -->
                        <div id="prerequisites-warning" class="alert alert-warning" style="display: none;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Prerequisites Required:</strong>
                            <div id="prerequisites-list"></div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">📅 Section Selection</h3>
                        
                        <div id="sections-loading" class="text-center" style="display: none;">
                            <div class="spinner"></div>
                            <span class="ms-2">Loading available sections...</span>
                        </div>
                        
                        <div id="no-sections" class="alert alert-warning" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            Please select a subject, semester, and academic year to view available sections.
                        </div>
                        
                        <div id="sections-container">
                            <div class="section-cards" id="section-cards">
                                <!-- Section cards will be loaded here dynamically -->
                            </div>
                        </div>
                        
                        <input type="hidden" name="section" id="selected_section">
                    </div>
                </div>

                <!-- Bulk Enrollment -->
                <div id="bulk-enrollment" class="enrollment-section <?= $enrollmentMode === 'bulk' ? '' : 'd-none' ?>">
                    <div class="form-section">
                        <h3 class="form-section-title">👥 Student Selection</h3>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="filter_department" class="form-label">Filter by Department</label>
                                <select class="form-select" id="filter_department">
                                    <option value="">All Departments</option>
                                    <?php 
                                    $departments = array_unique(array_column($students, 'department'));
                                    foreach ($departments as $dept): 
                                    ?>
                                        <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="filter_year" class="form-label">Filter by Year</label>
                                <select class="form-select" id="filter_year">
                                    <option value="">All Years</option>
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <option value="<?= $i ?>">Year <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="d-flex align-items-end gap-2">
                                    <button type="button" class="btn-action btn-outline" onclick="selectAllStudents()">
                                        Select All
                                    </button>
                                    <button type="button" class="btn-action btn-outline" onclick="clearAllStudents()">
                                        Clear All
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="student-selection-grid" id="student-grid">
                            <?php foreach ($students as $student): ?>
                                <div class="student-card" 
                                     data-department="<?= htmlspecialchars($student['department']) ?>"
                                     data-year="<?= $student['year_of_study'] ?>"
                                     onclick="toggleStudent(<?= $student['student_id'] ?>)">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="selected_students[]" 
                                               value="<?= $student['student_id'] ?>" 
                                               id="student_<?= $student['student_id'] ?>">
                                        <label class="form-check-label" for="student_<?= $student['student_id'] ?>">
                                            <strong><?= htmlspecialchars($student['student_number']) ?></strong> - 
                                            <?= htmlspecialchars($student['full_name']) ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($student['department']) ?> • Year <?= $student['year_of_study'] ?>
                                            </small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-2">
                            <span id="selected-count" class="badge bg-primary">0 selected</span>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="form-section-title">📖 Bulk Enrollment Details</h3>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="bulk_subject_id" class="form-label">Subject *</label>
                                <select class="form-select" id="bulk_subject_id" name="bulk_subject_id" required>
                                    <option value="">Choose a subject...</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?= $subject['subject_id'] ?>" 
                                                data-department="<?= htmlspecialchars($subject['department']) ?>"
                                                data-semester="<?= $subject['semester'] ?>"
                                                <?= ($formData['bulk_subject_id'] ?? '') == $subject['subject_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label for="bulk_section" class="form-label">Section *</label>
                                <select class="form-select" id="bulk_section" name="bulk_section" required>
                                    <option value="">Select...</option>
                                    <option value="A" <?= ($formData['bulk_section'] ?? '') === 'A' ? 'selected' : '' ?>>A</option>
                                    <option value="B" <?= ($formData['bulk_section'] ?? '') === 'B' ? 'selected' : '' ?>>B</option>
                                    <option value="C" <?= ($formData['bulk_section'] ?? '') === 'C' ? 'selected' : '' ?>>C</option>
                                    <option value="D" <?= ($formData['bulk_section'] ?? '') === 'D' ? 'selected' : '' ?>>D</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label for="bulk_semester" class="form-label">Semester *</label>
                                <select class="form-select" id="bulk_semester" name="bulk_semester" required>
                                    <option value="">Select...</option>
                                    <?php for ($i = 1; $i <= 2; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($formData['bulk_semester'] ?? '') == $i ? 'selected' : '' ?>>
                                            Semester <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label for="bulk_academic_year" class="form-label">Academic Year *</label>
                                <input type="text" class="form-control" id="bulk_academic_year" name="bulk_academic_year" 
                                       value="<?= htmlspecialchars($formData['bulk_academic_year'] ?? $currentAcademicYear) ?>" 
                                       placeholder="2024-25" required>
                            </div>
                        </div>
                        
                        <div id="bulk-capacity-info" class="capacity-info" style="display: none;">
                            <!-- Capacity information will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-section">
                    <div class="d-flex gap-3 justify-content-end flex-wrap">
                        <a href="index.php" class="btn-action btn-outline">
                            ❌ Cancel
                        </a>
                        <button type="button" class="btn-action btn-outline" onclick="resetForm()">
                            🔄 Reset Form
                        </button>
                        <button type="button" class="btn-action btn-info" onclick="validateEnrollment()" id="validateBtn">
                            🔍 Validate
                        </button>
                        <button type="submit" class="btn-action btn-success" id="submitBtn">
                            ✅ Create Enrollment
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply current theme
            applyCurrentTheme();
            
            // Handle sidebar toggle events
            handleSidebarToggle();
            
            // Initialize form functionality
            initializeForm();
            
            // Initialize Select2
            initializeSelect2();
            
            // Initialize filters
            initializeFilters();
        });

        /**
         * Apply current theme from localStorage
         */
        function applyCurrentTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            
            // Update theme toggle icon if it exists
            const themeIcon = document.querySelector('#themeToggle i');
            if (themeIcon) {
                themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        /**
         * Handle sidebar toggle
         */
        function handleSidebarToggle() {
            const toggleBtn = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (toggleBtn && sidebar && mainContent) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    document.body.classList.toggle('sidebar-collapsed');
                });
            }
        }

        /**
         * Initialize Select2
         */
        function initializeSelect2() {
            $('#student_id, #subject_id, #bulk_subject_id').select2({
                placeholder: 'Type to search...',
                allowClear: true,
                width: '100%'
            });
        }

        /**
         * Select enrollment mode
         */
        function selectMode(mode) {
            // Update toggle buttons
            document.querySelectorAll('.mode-option').forEach(option => {
                option.classList.remove('active');
            });
            document.querySelector(`[onclick="selectMode('${mode}')"]`).classList.add('active');
            
            // Update hidden input
            document.getElementById('enrollment_mode').value = mode;
            
            // Show/hide sections
            if (mode === 'single') {
                document.getElementById('single-enrollment').classList.remove('d-none');
                document.getElementById('bulk-enrollment').classList.add('d-none');
            } else {
                document.getElementById('single-enrollment').classList.add('d-none');
                document.getElementById('bulk-enrollment').classList.remove('d-none');
            }
            
            // Reset form
            resetForm();
        }

        /**
         * Initialize form functionality
         */
        function initializeForm() {
            // Student selection change
            document.getElementById('student_id').addEventListener('change', function() {
                updateStudentInfo();
                checkPrerequisites();
            });
            
            // Subject selection change
            document.getElementById('subject_id').addEventListener('change', function() {
                loadAvailableSections();
                checkPrerequisites();
            });
            
            // Semester/Academic year change
            document.getElementById('semester').addEventListener('change', loadAvailableSections);
            document.getElementById('academic_year').addEventListener('change', loadAvailableSections);
            
            // Bulk enrollment subject change
            document.getElementById('bulk_subject_id').addEventListener('change', function() {
                checkBulkCapacity();
            });
            
            // Form submission
            document.getElementById('enrollmentForm').addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Enrollment...';
                submitBtn.disabled = true;
            });
        }

        /**
         * Update student information display
         */
        function updateStudentInfo() {
            const studentSelect = document.getElementById('student_id');
            const studentInfo = document.getElementById('student-info');
            const studentDetails = document.getElementById('student-details');
            
            if (studentSelect.value) {
                const option = studentSelect.selectedOptions[0];
                const department = option.getAttribute('data-department');
                const year = option.getAttribute('data-year');
                const semester = option.getAttribute('data-semester');
                
                studentDetails.innerHTML = `
                    <strong>Department:</strong> ${department}<br>
                    <strong>Year:</strong> ${year}<br>
                    <strong>Current Semester:</strong> ${semester}
                `;
                studentInfo.style.display = 'block';
            } else {
                studentInfo.style.display = 'none';
            }
        }

        /**
         * Load available sections for selected subject
         */
        function loadAvailableSections() {
            const subjectId = document.getElementById('subject_id').value;
            const semester = document.getElementById('semester').value;
            const academicYear = document.getElementById('academic_year').value;
            
            const sectionsContainer = document.getElementById('sections-container');
            const sectionsLoading = document.getElementById('sections-loading');
            const noSections = document.getElementById('no-sections');
            const sectionCards = document.getElementById('section-cards');
            
            if (!subjectId || !semester || !academicYear) {
                noSections.style.display = 'block';
                sectionsContainer.style.display = 'none';
                return;
            }
            
            // Show loading
            sectionsLoading.style.display = 'block';
            noSections.style.display = 'none';
            sectionsContainer.style.display = 'none';
            
            // Fetch sections
            fetch(`?action=get_sections&subject_id=${subjectId}&semester=${semester}&academic_year=${encodeURIComponent(academicYear)}`)
                .then(response => response.json())
                .then(data => {
                    sectionsLoading.style.display = 'none';
                    
                    if (data.success && data.sections.length > 0) {
                        renderSectionCards(data.sections);
                        sectionsContainer.style.display = 'block';
                    } else {
                        noSections.innerHTML = '<i class="fas fa-info-circle me-2"></i>No sections available for the selected criteria.';
                        noSections.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error loading sections:', error);
                    sectionsLoading.style.display = 'none';
                    noSections.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error loading sections. Please try again.';
                    noSections.style.display = 'block';
                });
        }

        /**
         * Render section cards
         */
        function renderSectionCards(sections) {
            const sectionCards = document.getElementById('section-cards');
            
            sectionCards.innerHTML = sections.map(section => {
                const availableSpots = parseInt(section.available_spots);
                const capacity = parseInt(section.capacity);
                const enrolled = parseInt(section.enrolled_count);
                
                let badgeClass = 'available';
                let badgeText = `${availableSpots} spots`;
                
                if (availableSpots === 0) {
                    badgeClass = 'full';
                    badgeText = 'Full';
                } else if (availableSpots <= capacity * 0.2) {
                    badgeClass = 'limited';
                    badgeText = `${availableSpots} left`;
                }
                
                const isDisabled = availableSpots === 0 ? 'full' : '';
                
                return `
                    <div class="section-card ${isDisabled}" onclick="selectSection('${section.section}', ${availableSpots > 0})">
                        <div class="section-header">
                            <div class="section-title">Section ${section.section}</div>
                            <span class="capacity-badge ${badgeClass}">${badgeText}</span>
                        </div>
                        <div class="section-details">
                            <div><i class="fas fa-clock"></i> ${section.schedule}</div>
                            <div><i class="fas fa-user-tie"></i> ${section.faculty_name}</div>
                            <div><i class="fas fa-users"></i> ${enrolled}/${capacity} enrolled</div>
                        </div>
                        <input type="radio" name="section_radio" value="${section.section}" style="display: none;" ${availableSpots === 0 ? 'disabled' : ''}>
                    </div>
                `;
            }).join('');
        }

        /**
         * Select a section
         */
        function selectSection(section, isAvailable) {
            if (!isAvailable) return;
            
            // Remove selected class from all cards
            document.querySelectorAll('.section-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');
            
            // Update hidden input
            document.getElementById('selected_section').value = section;
            
            // Check radio button
            document.querySelector(`input[value="${section}"]`).checked = true;
        }

        /**
         * Check prerequisites for selected student and subject
         */
        function checkPrerequisites() {
            const studentId = document.getElementById('student_id').value;
            const subjectId = document.getElementById('subject_id').value;
            
            const prerequisitesWarning = document.getElementById('prerequisites-warning');
            const prerequisitesList = document.getElementById('prerequisites-list');
            
            if (!studentId || !subjectId) {
                prerequisitesWarning.style.display = 'none';
                return;
            }
            
            fetch(`?action=check_prerequisites&student_id=${studentId}&subject_id=${subjectId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.unmet_prerequisites && data.unmet_prerequisites.length > 0) {
                            prerequisitesList.innerHTML = data.unmet_prerequisites.map(prereq => 
                                `<li>${prereq}</li>`
                            ).join('');
                            prerequisitesWarning.style.display = 'block';
                        } else {
                            prerequisitesWarning.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking prerequisites:', error);
                });
        }

        /**
         * Initialize filters for bulk enrollment
         */
        function initializeFilters() {
            document.getElementById('filter_department').addEventListener('change', filterStudents);
            document.getElementById('filter_year').addEventListener('change', filterStudents);
        }

        /**
         * Filter students in bulk enrollment
         */
        function filterStudents() {
            const department = document.getElementById('filter_department').value;
            const year = document.getElementById('filter_year').value;
            
            document.querySelectorAll('.student-card').forEach(card => {
                const cardDept = card.getAttribute('data-department');
                const cardYear = card.getAttribute('data-year');
                
                const showCard = (!department || cardDept === department) && 
                                (!year || cardYear === year);
                
                card.style.display = showCard ? 'block' : 'none';
            });
            
            updateSelectedCount();
        }

        /**
         * Toggle student selection
         */
        function toggleStudent(studentId) {
            const checkbox = document.getElementById(`student_${studentId}`);
            const card = checkbox.closest('.student-card');
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
            
            updateSelectedCount();
        }

        /**
         * Select all visible students
         */
        function selectAllStudents() {
            document.querySelectorAll('.student-card:not([style*="display: none"]) input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = true;
                checkbox.closest('.student-card').classList.add('selected');
            });
            updateSelectedCount();
        }

        /**
         * Clear all student selections
         */
        function clearAllStudents() {
            document.querySelectorAll('.student-card input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
                checkbox.closest('.student-card').classList.remove('selected');
            });
            updateSelectedCount();
        }

        /**
         * Update selected student count
         */
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.student-card input[type="checkbox"]:checked').length;
            document.getElementById('selected-count').textContent = `${selected} selected`;
        }

        /**
         * Check bulk enrollment capacity
         */
        function checkBulkCapacity() {
            const subjectId = document.getElementById('bulk_subject_id').value;
            const section = document.getElementById('bulk_section').value;
            const semester = document.getElementById('bulk_semester').value;
            const academicYear = document.getElementById('bulk_academic_year').value;
            
            const capacityInfo = document.getElementById('bulk-capacity-info');
            
            if (!subjectId || !section || !semester || !academicYear) {
                capacityInfo.style.display = 'none';
                return;
            }
            
            fetch(`?action=check_capacity&subject_id=${subjectId}&section=${section}&semester=${semester}&academic_year=${encodeURIComponent(academicYear)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.capacity) {
                        const capacity = data.capacity;
                        const available = capacity.available_spots;
                        const total = capacity.capacity;
                        const enrolled = capacity.enrolled_count;
                        
                        let className = '';
                        let message = '';
                        
                        if (available === 0) {
                            className = 'danger';
                            message = `Section is full (${enrolled}/${total})`;
                        } else if (available <= total * 0.2) {
                            className = 'warning';
                            message = `Limited capacity: ${available} spots remaining (${enrolled}/${total})`;
                        } else {
                            message = `${available} spots available (${enrolled}/${total})`;
                        }
                        
                        capacityInfo.className = `capacity-info ${className}`;
                        capacityInfo.innerHTML = `
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Capacity Status:</strong> ${message}
                        `;
                        capacityInfo.style.display = 'block';
                    } else {
                        capacityInfo.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error checking capacity:', error);
                    capacityInfo.style.display = 'none';
                });
        }

        /**
         * Validate enrollment before submission
         */
        function validateEnrollment() {
            const mode = document.getElementById('enrollment_mode').value;
            const validateBtn = document.getElementById('validateBtn');
            
            // Show loading state
            validateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';
            validateBtn.disabled = true;
            
            setTimeout(() => {
                let isValid = true;
                let messages = [];
                
                if (mode === 'single') {
                    // Validate single enrollment
                    const studentId = document.getElementById('student_id').value;
                    const subjectId = document.getElementById('subject_id').value;
                    const section = document.getElementById('selected_section').value;
                    
                    if (!studentId) {
                        isValid = false;
                        messages.push('Please select a student');
                    }
                    
                    if (!subjectId) {
                        isValid = false;
                        messages.push('Please select a subject');
                    }
                    
                    if (!section) {
                        isValid = false;
                        messages.push('Please select a section');
                    }
                    
                } else {
                    // Validate bulk enrollment
                    const selectedStudents = document.querySelectorAll('input[name="selected_students[]"]:checked');
                    const bulkSubjectId = document.getElementById('bulk_subject_id').value;
                    
                    if (selectedStudents.length === 0) {
                        isValid = false;
                        messages.push('Please select at least one student');
                    }
                    
                    if (!bulkSubjectId) {
                        isValid = false;
                        messages.push('Please select a subject');
                    }
                }
                
                // Show validation result
                if (isValid) {
                    showValidationResult(true, 'Validation passed! Ready to create enrollment.');
                } else {
                    showValidationResult(false, 'Validation failed: ' + messages.join(', '));
                }
                
                // Reset button
                validateBtn.innerHTML = '🔍 Validate';
                validateBtn.disabled = false;
            }, 1000);
        }

        /**
         * Show validation result
         */
        function showValidationResult(isValid, message) {
            // Remove existing validation alerts
            document.querySelectorAll('.validation-alert').forEach(alert => alert.remove());
            
            const alertClass = isValid ? 'alert-success' : 'alert-danger';
            const icon = isValid ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
            
            const alertHtml = `
                <div class="alert ${alertClass} validation-alert fade-in">
                    <i class="${icon} me-2"></i>
                    ${message}
                </div>
            `;
            
            // Insert after alerts section
            const pageHeader = document.querySelector('.page-header');
            pageHeader.insertAdjacentHTML('afterend', alertHtml);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                document.querySelector('.validation-alert')?.remove();
            }, 5000);
        }

        /**
         * Validate form before submission
         */
        function validateForm() {
            const mode = document.getElementById('enrollment_mode').value;
            let isValid = true;
            let errors = [];
            
            if (mode === 'single') {
                // Single enrollment validation
                const requiredFields = [
                    { id: 'student_id', name: 'Student' },
                    { id: 'subject_id', name: 'Subject' },
                    { id: 'semester', name: 'Semester' },
                    { id: 'academic_year', name: 'Academic Year' },
                    { id: 'selected_section', name: 'Section' }
                ];
                
                requiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (!element.value.trim()) {
                        isValid = false;
                        errors.push(`${field.name} is required`);
                        element.classList.add('is-invalid');
                    } else {
                        element.classList.remove('is-invalid');
                    }
                });
                
            } else {
                // Bulk enrollment validation
                const selectedStudents = document.querySelectorAll('input[name="selected_students[]"]:checked');
                const bulkRequiredFields = [
                    { id: 'bulk_subject_id', name: 'Subject' },
                    { id: 'bulk_section', name: 'Section' },
                    { id: 'bulk_semester', name: 'Semester' },
                    { id: 'bulk_academic_year', name: 'Academic Year' }
                ];
                
                if (selectedStudents.length === 0) {
                    isValid = false;
                    errors.push('Please select at least one student');
                }
                
                bulkRequiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (!element.value.trim()) {
                        isValid = false;
                        errors.push(`${field.name} is required`);
                        element.classList.add('is-invalid');
                    } else {
                        element.classList.remove('is-invalid');
                    }
                });
            }
            
            if (!isValid) {
                showError('Please fix the following errors: ' + errors.join(', '));
            }
            
            return isValid;
        }

        /**
         * Reset form to initial state
         */
        function resetForm() {
            const form = document.getElementById('enrollmentForm');
            
            // Reset form fields
            form.reset();
            
            // Reset Select2
            $('#student_id, #subject_id, #bulk_subject_id').val(null).trigger('change');
            
            // Clear section selection
            document.getElementById('selected_section').value = '';
            document.querySelectorAll('.section-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Clear student selections in bulk mode
            document.querySelectorAll('.student-card').forEach(card => {
                card.classList.remove('selected');
                const checkbox = card.querySelector('input[type="checkbox"]');
                if (checkbox) checkbox.checked = false;
            });
            
            // Hide info sections
            document.getElementById('student-info').style.display = 'none';
            document.getElementById('prerequisites-warning').style.display = 'none';
            document.getElementById('bulk-capacity-info').style.display = 'none';
            document.getElementById('no-sections').style.display = 'block';
            document.getElementById('sections-container').style.display = 'none';
            
            // Reset validation classes
            document.querySelectorAll('.is-invalid').forEach(element => {
                element.classList.remove('is-invalid');
            });
            
            // Update selected count
            updateSelectedCount();
        }

        /**
         * Show error message
         */
        function showError(message) {
            // Remove existing alerts
            document.querySelectorAll('.alert-danger').forEach(alert => alert.remove());
            
            // Create new alert
            const alertHtml = `
                <div class="alert alert-danger glass-card fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> ${message}
                </div>
            `;
            
            // Insert at top of main content
            const mainContent = document.querySelector('.main-content');
            const firstElement = mainContent.querySelector('.page-header').nextElementSibling;
            firstElement.insertAdjacentHTML('beforebegin', alertHtml);
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        /**
         * Theme toggle functionality (if needed)
         */
        function toggleTheme() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update icon
            const themeIcon = document.querySelector('#themeToggle i');
            if (themeIcon) {
                themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }

        // Make functions available globally
        window.selectMode = selectMode;
        window.selectSection = selectSection;
        window.toggleStudent = toggleStudent;
        window.selectAllStudents = selectAllStudents;
        window.clearAllStudents = clearAllStudents;
        window.validateEnrollment = validateEnrollment;
        window.resetForm = resetForm;
        window.toggleTheme = toggleTheme;
    </script>
</body>
</html>