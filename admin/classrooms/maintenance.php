<?php
define('SYSTEM_ACCESS', true);
/**
 * View Classroom Details
 * Comprehensive view of classroom information and usage
 */

session_start();
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Classroom.php';

// Consume flash message (success/error) for top header alert
$topFlash = null;
if (!empty($_SESSION['flash_message'])) {
    $topFlash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Check authentication and admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit();
}

$classroom = new Classroom();

// Get classroom ID
$classroom_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$classroom_id) {
    header('Location: index.php?error=' . urlencode('Invalid classroom ID'));
    exit();
}

// Get classroom data
$classroomData = $classroom->getById($classroom_id);

if (!$classroomData) {
    header('Location: index.php?error=' . urlencode('Classroom not found'));
    exit();
}

// Get current timetable assignments
$currentAssignments = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT 
            t.timetable_id,
            s.subject_name,
            s.subject_code,
            CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
            ts.day_of_week,
            ts.start_time,
            ts.end_time,
            t.section,
            t.semester,
            t.academic_year,
            COUNT(e.enrollment_id) as enrolled_students
        FROM timetables t
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN faculty f ON t.faculty_id = f.faculty_id
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        LEFT JOIN enrollments e ON s.subject_id = e.subject_id 
            AND t.section = e.section 
            AND t.semester = e.semester 
            AND t.academic_year = e.academic_year
        WHERE t.classroom_id = ?
        GROUP BY t.timetable_id
        ORDER BY 
            CASE ts.day_of_week 
                WHEN 'Monday' THEN 1
                WHEN 'Tuesday' THEN 2
                WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4
                WHEN 'Friday' THEN 5
                WHEN 'Saturday' THEN 6
                ELSE 7
            END,
            ts.start_time
    ");
    $stmt->execute([$classroom_id]);
    $currentAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Get assignments error: " . $e->getMessage());
}

// Calculate utilization
$totalSlots = 0;
$usedSlots = count($currentAssignments);
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM time_slots WHERE is_active = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalSlots = $result['total'];
} catch (Exception $e) {
    // Handle error silently
}

$utilizationPercentage = $totalSlots > 0 ? ($usedSlots / $totalSlots) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Classroom Details - <?= htmlspecialchars($classroomData['room_number']) ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        body {
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
            color: #2d3748;
            margin: 0;
            padding: 0;
        }

        .admin-main-wrapper {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            position: relative;
        }

        .admin-main-wrapper.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed-width);
        }

      .page-header {
            background: white;
            padding: 20px 30px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            transition: margin-left 0.3s ease;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .admin-main-wrapper.sidebar-collapsed .page-header {
            margin-left: 0;
        }

        .page-title {
            color: #2d3748;
            font-weight: 700;
            font-size: 2rem;
            margin: 0;
        }

        .page-subtitle {
            color: #718096;
            font-weight: 400;
            margin-top: 0.5rem;
        }

        .content-wrapper {
            padding: 0 2rem 2rem;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .card-title {
            color: #2d3748;
            font-weight: 600;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 0.75rem;
            color: #667eea;
            font-size: 1.1rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #718096;
            display: flex;
            align-items: center;
        }

        .info-label i {
            margin-right: 0.5rem;
            color: #667eea;
            width: 16px;
        }

        .info-value {
            font-weight: 600;
            color: #2d3748;
        }

        .utilization-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .utilization-percentage {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: block;
        }

        .utilization-label {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .utilization-details {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .schedule-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .schedule-table table {
            margin: 0;
        }

        .schedule-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .schedule-table td {
            padding: 1rem;
            border-color: rgba(102, 126, 234, 0.1);
            vertical-align: middle;
        }

        .schedule-table tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }

        .badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .badge.bg-success { background: var(--success-color) !important; }
        .badge.bg-warning { background: var(--warning-color) !important; }
        .badge.bg-danger { background: var(--danger-color) !important; }
        .badge.bg-info { background: var(--info-color) !important; }
        .badge.bg-primary { background: #667eea !important; }
        .badge.bg-secondary { background: #a0aec0 !important; }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .btn-outline-primary {
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: #667eea;
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            border: 2px solid #a0aec0;
            color: #a0aec0;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: #a0aec0;
            color: white;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #f56565, #e53e3e);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #718096;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-available { background-color: var(--success-color); }
        .status-maintenance { background-color: var(--warning-color); }
        .status-reserved { background-color: var(--info-color); }

        .quick-actions-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        @media (max-width: 768px) {
            .page-header {
                margin-left: 0;
                padding: 1rem;
            }
            
            .content-wrapper {
                padding: 0 1rem 1rem;
            }
            
            .info-card {
                padding: 1.5rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .utilization-percentage {
                font-size: 2.5rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Include Admin Sidebar using absolute path -->
    <?php 
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Define BASE_URL if not defined
    if (!defined('BASE_URL')) {
        define('BASE_URL', '/timetable-management/');
    }
    
    // Include sidebar with absolute path
    include $_SERVER['DOCUMENT_ROOT'] . '/timetable-management/admin/partials/admin_sidebar.php';
    ?>

    <!-- Main Content Wrapper -->
    <div class="admin-main-wrapper">
        <!-- Header -->
        <div class="page-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col">
                        <h1 class="page-title">
                            <i class="fas fa-door-open me-3"></i>
                            Classroom Details
                        </h1>
                        <p class="page-subtitle">
                            <?= htmlspecialchars($classroomData['room_number']) ?> - <?= htmlspecialchars($classroomData['building']) ?>
                        </p>
                        <?php if ($topFlash && $topFlash['type'] === 'success'): ?>
                            <div class="alert alert-success glass-card mt-3" id="topSuccessAlert" role="alert">
                                <strong>✅ Success!</strong> <?= htmlspecialchars($topFlash['message']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($topFlash && $topFlash['type'] === 'error'): ?>
                            <div class="alert alert-danger glass-card mt-3" role="alert">
                                <strong>❌ Error!</strong> <?= htmlspecialchars($topFlash['message']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-auto">
                        <a href="index.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                        <a href="edit.php?id=<?= $classroom_id ?>" class="btn btn-warning me-2">
                            <i class="fas fa-edit me-2"></i>Edit
                        </a>
                        <a href="delete.php?id=<?= $classroom_id ?>" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="info-card">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            Basic Information
                        </h3>
                        
                        <div class="info-grid">
                            <div>
                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-hashtag"></i>
                                        Classroom ID
                                    </span>
                                    <span class="info-value">#<?= $classroomData['classroom_id'] ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-door-open"></i>
                                        Room Number
                                    </span>
                                    <span class="info-value"><?= htmlspecialchars($classroomData['room_number']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-building"></i>
                                        Building
                                    </span>
                                    <span class="info-value"><?= htmlspecialchars($classroomData['building']) ?></span>
                                </div>
                            </div>
                            
                            <div>
                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-tag"></i>
                                        Type
                                    </span>
                                    <span class="info-value">
                                        <span class="badge bg-<?= $classroomData['type'] === 'lecture' ? 'primary' : ($classroomData['type'] === 'lab' ? 'success' : 'info') ?>">
                                            <?= ucfirst($classroomData['type']) ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-users"></i>
                                        Capacity
                                    </span>
                                    <span class="info-value"><?= $classroomData['capacity'] ?> students</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-flag"></i>
                                        Status
                                    </span>
                                    <span class="info-value">
                                        <div class="status-indicator">
                                            <div class="status-dot status-<?= $classroomData['status'] ?>"></div>
                                            <span class="badge bg-<?= $classroomData['status'] === 'available' ? 'success' : ($classroomData['status'] === 'maintenance' ? 'warning' : 'info') ?>">
                                                <?= ucfirst($classroomData['status']) ?>
                                            </span>
                                        </div>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item mt-3">
                            <span class="info-label">
                                <i class="fas fa-calendar-plus"></i>
                                Created
                            </span>
                            <span class="info-value"><?= date('M j, Y g:i A', strtotime($classroomData['created_at'])) ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-toggle-<?= $classroomData['is_active'] ? 'on' : 'off' ?>"></i>
                                Active Status
                            </span>
                            <span class="info-value">
                                <span class="badge bg-<?= $classroomData['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $classroomData['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </span>
                        </div>
                    </div>

                    <!-- Facilities -->
                    <?php if ($classroomData['facilities']): ?>
                    <div class="info-card">
                        <h3 class="card-title">
                            <i class="fas fa-cogs"></i>
                            Facilities & Equipment
                        </h3>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($classroomData['facilities'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Current Schedule -->
                    <div class="info-card">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-week"></i>
                            Current Schedule (<?= count($currentAssignments) ?> assignments)
                        </h3>
                        
                        <?php if (!empty($currentAssignments)): ?>
                            <div class="schedule-table">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Day & Time</th>
                                                <th>Subject</th>
                                                <th>Faculty</th>
                                                <th>Section</th>
                                                <th>Students</th>
                                                <th>Academic Year</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($currentAssignments as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= $assignment['day_of_week'] ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= date('g:i A', strtotime($assignment['start_time'])) ?> - 
                                                        <?= date('g:i A', strtotime($assignment['end_time'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($assignment['subject_code']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($assignment['subject_name']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($assignment['faculty_name']) ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        Sec <?= htmlspecialchars($assignment['section']) ?>
                                                    </span><br>
                                                    <small class="text-muted">Sem <?= $assignment['semester'] ?></small>
                                                </td>
                                                <td>
                                                    <i class="fas fa-users me-1"></i>
                                                    <?= $assignment['enrolled_students'] ?>
                                                </td>
                                                <td><?= htmlspecialchars($assignment['academic_year']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h5>No Schedule Assignments</h5>
                                <p>This classroom is not currently assigned to any timetable entries.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Utilization Summary -->
                    <div class="utilization-card">
                        <span class="utilization-percentage"><?= number_format($utilizationPercentage, 1) ?>%</span>
                        <div class="utilization-label">Current Utilization</div>
                        <div class="utilization-details">
                            <?= $usedSlots ?> of <?= $totalSlots ?> time slots used
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="quick-actions-card">
                        <h4 class="mb-3">
                            <i class="fas fa-bolt me-2"></i>
                            Quick Actions
                        </h4>
                        
                        <div class="d-grid gap-2">
                            <a href="edit.php?id=<?= $classroom_id ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-2"></i>
                                Edit Classroom
                            </a>
                            
                            <a href="../timetables/create.php?classroom_id=<?= $classroom_id ?>" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-2"></i>
                                Add to Timetable
                            </a>
                            
                            <a href="utilization.php" class="btn btn-outline-secondary">
                                <i class="fas fa-chart-bar me-2"></i>
                                View All Utilization
                            </a>
                            
                            <hr>
                            
                            <a href="delete.php?id=<?= $classroom_id ?>" class="btn btn-danger">
                                <i class="fas fa-trash me-2"></i>
                                Delete Classroom
                            </a>
                        </div>
                    </div>

                    <!-- Usage Statistics -->
                    <div class="quick-actions-card">
                        <h4 class="mb-3">
                            <i class="fas fa-chart-line me-2"></i>
                            Usage Statistics
                        </h4>
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h4 text-primary mb-0"><?= count($currentAssignments) ?></div>
                                    <small class="text-muted">Total Classes</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h4 text-success mb-0">
                                        <?= array_sum(array_column($currentAssignments, 'enrolled_students')) ?>
                                    </div>
                                    <small class="text-muted">Total Students</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h4 text-warning mb-0">
                                        <?= $totalSlots - $usedSlots ?>
                                    </div>
                                    <small class="text-muted">Free Slots</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h4 text-info mb-0">
                                        <?= !empty($currentAssignments) ? number_format(array_sum(array_column($currentAssignments, 'enrolled_students')) / count($currentAssignments), 1) : '0' ?>
                                    </div>
                                    <small class="text-muted">Avg Students</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide success flash after 5s to match other pages
        (function(){
            const alertEl = document.getElementById('topSuccessAlert');
            if (alertEl) {
                setTimeout(() => {
                    alertEl.classList.add('fade');
                    setTimeout(() => {
                        alertEl.classList.add('slide-up');
                        setTimeout(() => alertEl.remove(), 400);
                    }, 150);
                }, 5000);
            }
        })();
    </script>
</body>
</html>