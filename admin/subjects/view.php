<?php
/**
 * Subject Details (Read-only View)
 * Part of Timetable Management System – Admin module
 */

// Allow core includes
define('SYSTEM_ACCESS', true);

session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Subject.php';

// Authentication (admin only)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

// Validate subject id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid subject ID.';
    header('Location: index.php');
    exit;
}
$subjectId   = (int) $_GET['id'];
$subjectInst = new Subject();
$subject     = $subjectInst->getSubjectById($subjectId);
if (!$subject) {
    $_SESSION['error_message'] = 'Subject not found.';
    header('Location: index.php');
    exit;
}

// Fetch assigned faculty list
$db = Database::getInstance();
$facultyAssignments = $db->fetchAll(
    "SELECT CONCAT(f.first_name,' ',f.last_name)            AS faculty_name,
            f.employee_id,
            fs.max_students,
            fs.assigned_date
       FROM faculty_subjects fs
       JOIN faculty f   ON f.faculty_id = fs.faculty_id
      WHERE fs.subject_id = ? AND fs.is_active = 1
      ORDER BY f.first_name, f.last_name",
    [$subjectId]
);

// Check timetable usage
$timetableCount = $db->fetchRow(
    'SELECT COUNT(*) AS cnt FROM timetables WHERE subject_id = ? AND is_active = 1',
    [$subjectId]
)['cnt'] ?? 0;
$inUse = $timetableCount > 0;

$pageTitle = 'Subject Details – ' . htmlspecialchars($subject['subject_code']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $pageTitle ?></title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #e9ecef;
            --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: white;
            color: #495057;
        }

        .admin-top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            transition: margin-left 0.3s ease;
            border-bottom: 1px solid var(--border-color);
        }
        
        .admin-main-wrapper.sidebar-collapsed .admin-top-navbar {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        .admin-content-area {
            padding: 0 30px 30px;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .page-header h1 {
            color: #2c3e50;
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .page-header .icon-wrapper {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: var(--shadow);
        }
        
        .page-header .subtitle {
            color: #6c757d;
            font-size: 1.2rem;
            margin: 0;
            font-weight: 400;
        }

        .content-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .badge-success {
            background: var(--success-color);
            color: white;
        }
        
        .badge-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .badge-warning {
            background: var(--warning-color);
            color: #212529;
        }

        .btn {
            border-radius: 12px;
            font-weight: 600;
            padding: 12px 24px;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }
        
        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            padding: 20px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: white;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .info-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .faculty-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .faculty-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .faculty-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            background: white;
        }
        
        .faculty-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .faculty-details {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .description-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid var(--border-color);
        }
        
        .description-card h5 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .description-text {
            color: #495057;
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 768px) {
            .admin-top-navbar {
                margin-left: 0;
                padding: 15px;
            }
            
            .admin-content-area {
                padding: 0 15px 15px;
            }
            
            .content-card {
                padding: 20px;
                border-radius: 12px;
            }
            
            .page-header {
                padding: 30px 20px;
            }
            
            .page-header h1 {
                font-size: 2rem;
                flex-direction: column;
                gap: 10px;
            }
            
            .info-grid {
                grid-template-columns: 1fr !important;
            }
            
            .faculty-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Admin Sidebar Partial -->
    <?php include '../partials/admin_sidebar.php'; ?>
    
    <!-- Main Content Wrapper -->
    <div class="admin-main-wrapper">
        <!-- Top Navigation -->
        <div class="admin-top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h4 class="mb-0 ms-2">Subject Details: <?= htmlspecialchars($subject['subject_code']) ?></h4>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Back to Subjects
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="admin-content admin-content-area">
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <div class="icon-wrapper">
                        <i class="fas fa-book"></i>
                    </div>
                    Subject Details
                </h1>
                <p class="subtitle"><?= htmlspecialchars($subject['subject_code']) ?> – <?= htmlspecialchars($subject['subject_name']) ?></p>
            </div>

            <!-- Main Subject Information -->
            <div class="content-card">
                <h3 class="mb-4">
                    <i class="fas fa-info-circle me-2 text-primary"></i>
                    Subject Information
                </h3>
                
                <div class="row g-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-code"></i>
                            Subject Code
                        </div>
                        <div class="info-value"><?= htmlspecialchars($subject['subject_code']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-book"></i>
                            Subject Name
                        </div>
                        <div class="info-value"><?= htmlspecialchars($subject['subject_name']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-building"></i>
                            Department
                        </div>
                        <div class="info-value"><?= htmlspecialchars($subject['department']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-graduation-cap"></i>
                            Credits
                        </div>
                        <div class="info-value"><?= $subject['credits'] ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-clock"></i>
                            Duration (Hours)
                        </div>
                        <div class="info-value"><?= $subject['duration_hours'] ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-tag"></i>
                            Type
                        </div>
                        <div class="info-value"><?= ucfirst($subject['type']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar"></i>
                            Semester
                        </div>
                        <div class="info-value">Semester <?= $subject['semester'] ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-layer-group"></i>
                            Year Level
                        </div>
                        <div class="info-value">Year <?= $subject['year_level'] ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-toggle-on"></i>
                            Status
                        </div>
                        <div class="info-value">
                            <span class="badge <?= $subject['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                <i class="fas <?= $subject['is_active'] ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                                <?= $subject['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-check"></i>
                            Timetable Usage
                        </div>
                        <div class="info-value">
                            <?php if ($inUse): ?>
                                <span class="badge badge-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <?= $timetableCount ?> active
                                </span>
                            <?php else: ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check me-1"></i>
                                    Not in use
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prerequisites (if any) -->
            <?php if (!empty($subject['prerequisites'])): ?>
            <div class="content-card">
                <div class="description-card">
                    <h5>
                        <i class="fas fa-list-ul"></i>
                        Prerequisites
                    </h5>
                    <p class="description-text"><?= nl2br(htmlspecialchars($subject['prerequisites'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Description (if any) -->
            <?php if (!empty($subject['description'])): ?>
            <div class="content-card">
                <div class="description-card">
                    <h5>
                        <i class="fas fa-file-text"></i>
                        Description
                    </h5>
                    <p class="description-text"><?= nl2br(htmlspecialchars($subject['description'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Syllabus (if any) -->
            <?php if (!empty($subject['syllabus'])): ?>
            <div class="content-card">
                <div class="description-card">
                    <h5>
                        <i class="fas fa-book-open"></i>
                        Syllabus Overview
                    </h5>
                    <p class="description-text"><?= nl2br(htmlspecialchars($subject['syllabus'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Assigned Faculty -->
            <div class="content-card">
                <h4 class="mb-4">
                    <i class="fas fa-users me-2 text-primary"></i>
                    Assigned Faculty
                    <span class="badge bg-primary ms-2"><?= count($facultyAssignments) ?></span>
                </h4>
                
                <?php if ($facultyAssignments): ?>
                    <ul class="faculty-list">
                        <?php foreach ($facultyAssignments as $f): ?>
                            <li class="faculty-item">
                                <div>
                                    <div class="faculty-name"><?= htmlspecialchars($f['faculty_name']) ?></div>
                                    <div class="faculty-details">
                                        <i class="fas fa-id-badge me-1"></i>
                                        ID: <?= htmlspecialchars($f['employee_id']) ?> • 
                                        <i class="fas fa-users me-1"></i>
                                        Max Students: <?= $f['max_students'] ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        Since <?= date('M Y', strtotime($f['assigned_date'])) ?>
                                    </small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Faculty Assigned</h5>
                        <p class="text-muted mb-4">This subject hasn't been assigned to any faculty members yet.</p>
                        <a href="assign-faculty.php?id=<?= $subjectId ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Assign Faculty
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Metadata -->
            <div class="content-card">
                <h4 class="mb-4">
                    <i class="fas fa-info-circle me-2 text-primary"></i>
                    Metadata
                </h4>
                
                <div class="row g-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-plus"></i>
                            Created On
                        </div>
                        <div class="info-value"><?= date('M j, Y', strtotime($subject['created_at'])) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-edit"></i>
                            Last Modified
                        </div>
                        <div class="info-value"><?= date('M j, Y g:i A', strtotime($subject['updated_at'])) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-user-tie"></i>
                            Created By
                        </div>
                        <div class="info-value">
                            <?php
                            // Get creator info if available
                            if (isset($subject['created_by']) && $subject['created_by']) {
                                try {
                                    $creator = $db->fetchRow(
                                        "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE user_id = ?",
                                        [$subject['created_by']]
                                    );
                                    echo htmlspecialchars($creator['name'] ?? 'Unknown User');
                                } catch (Exception $e) {
                                    echo 'System';
                                }
                            } else {
                                echo 'System';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="content-card">
                <h4 class="mb-4">
                    <i class="fas fa-tools me-2 text-primary"></i>
                    Actions
                </h4>
                
                <div class="d-flex flex-wrap gap-3 justify-content-center">
                    <a href="edit.php?id=<?= $subjectId ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>Edit Subject
                    </a>
                    
                    <a href="assign-faculty.php?id=<?= $subjectId ?>" class="btn btn-secondary">
                        <i class="fas fa-user-plus me-2"></i>Assign Faculty
                    </a>
                    
                    <a href="../timetables/create.php?subject_id=<?= $subjectId ?>" class="btn btn-secondary">
                        <i class="fas fa-calendar-plus me-2"></i>Create Timetable
                    </a>
                    
                    <a href="../reports/subject.php?id=<?= $subjectId ?>" class="btn btn-secondary">
                        <i class="fas fa-file-pdf me-2"></i>Generate Report
                    </a>
                    
                    <?php if (!$inUse): ?>
                    <a href="delete.php?id=<?= $subjectId ?>&token=<?= $_SESSION['csrf_token'] ?? '' ?>" 
                       class="btn btn-outline-danger"
                       onclick="return confirm('Are you sure you want to delete this subject? This action cannot be undone.');">
                        <i class="fas fa-trash me-2"></i>Delete Subject
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate info items on page load
            const infoItems = document.querySelectorAll('.info-item');
            infoItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animate faculty items
            const facultyItems = document.querySelectorAll('.faculty-item');
            facultyItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, (infoItems.length * 100) + (index * 150));
            });

            // Add hover effects to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px) scale(1.02)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // E key to edit
            if (e.key === 'e' || e.key === 'E') {
                if (!e.ctrlKey && !e.metaKey && !e.altKey) {
                    const editBtn = document.querySelector('a[href*="edit.php"]');
                    if (editBtn && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                        window.location.href = editBtn.href;
                    }
                }
            }
            
            // Escape or B key to go back
            if (e.key === 'Escape' || e.key === 'b' || e.key === 'B') {
                if (!e.ctrlKey && !e.metaKey && !e.altKey) {
                    const backBtn = document.querySelector('a[href*="index.php"]');
                    if (backBtn && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                        window.location.href = backBtn.href;
                    }
                }
            }
        });

        // Print functionality
        function printSubjectDetails() {
            window.print();
        }

        // Add print styles
        const printStyles = `
            @media print {
                .admin-sidebar,
                .admin-top-navbar,
                .btn,
                .sidebar-toggle {
                    display: none !important;
                }
                
                .admin-main-wrapper {
                    margin-left: 0 !important;
                }
                
                .content-card {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }
                
                body {
                    font-size: 12pt;
                    line-height: 1.4;
                }
                
                h1, h2, h3, h4 {
                    page-break-after: avoid;
                }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>