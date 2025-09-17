<?php
/**
 * Report Class
 * Timetable Management System
 * 
 * Handles all reporting and analytics operations including system statistics,
 * user reports, timetable analytics, and resource utilization with integration
 * to the existing ExportService for PDF and Excel generation
 */

if (!defined('SYSTEM_ACCESS')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Export_Helper.php';

class Report {
    private $db;
    private $exportService;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->exportService = new ExportService();
        
        // Initialize logger if available
        try {
            $this->logger = function_exists('getLogger') ? getLogger() : null;
        } catch (Exception $e) {
            $this->logger = null;
        }
    }
    
    /**
     * Generate Resource Utilization Excel with multiple sheets.
     *
     * Expected $data structure keys:
     * - classroom_utilization: [ {room_number, building, capacity, type, scheduled_classes, utilization_percentage}, ... ]
     * - faculty_workload: [ {employee_id, faculty_name, department, teaching_load, subjects_taught, classrooms_used}, ... ]
     * - subject_popularity: [ {subject_code, subject_name, department, enrolled_students, faculty_assigned, credits}, ... ]
     */
    private function generateResourceExcel($data, $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            // Helper to write a sheet
            $writeSheet = function($sheet, $title, $headers, $rows) {
                $sheet->setTitle($title);
                // Title row
                $sheet->setCellValue('A1', $title . ' Report');
                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($headers)));
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Headers at row 3
                $sheet->fromArray($headers, null, 'A3');
                $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
                ]);

                // Data rows start at row 4
                $rowIdx = 4;
                foreach ($rows as $r) {
                    $sheet->fromArray($r, null, 'A' . $rowIdx);
                    $rowIdx++;
                }

                // Borders and zebra stripes
                $lastRow = $rowIdx - 1;
                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                }
                for ($r = 4; $r <= $lastRow; $r++) {
                    if ($r % 2 === 0) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F7F7F7');
                    }
                }

                // Auto-size
                for ($c = 1; $c <= max(1, count($headers)); $c++) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $sheet->freezePane('A4');
            };

            // Sheet 1: Classrooms
            $sheet1 = $spreadsheet->getActiveSheet();
            $classrooms = $data['classroom_utilization'] ?? [];
            $classroomHeaders = ['Room Number','Building','Capacity','Type','Scheduled Classes','Utilization %'];
            $classroomRows = array_map(function($c){
                return [
                    $c['room_number'] ?? '',
                    $c['building'] ?? '',
                    $c['capacity'] ?? '',
                    $c['type'] ?? '',
                    $c['scheduled_classes'] ?? 0,
                    $c['utilization_percentage'] ?? 0,
                ];
            }, $classrooms);
            $writeSheet($sheet1, 'Classrooms', $classroomHeaders, $classroomRows);

            // Sheet 2: Faculty
            $sheet2 = $spreadsheet->createSheet();
            $faculty = $data['faculty_workload'] ?? [];
            $facultyHeaders = ['Employee ID','Faculty Name','Department','Teaching Load','Subjects Taught','Classrooms Used'];
            $facultyRows = array_map(function($f){
                return [
                    $f['employee_id'] ?? '',
                    $f['faculty_name'] ?? '',
                    $f['department'] ?? '',
                    $f['teaching_load'] ?? 0,
                    $f['subjects_taught'] ?? 0,
                    $f['classrooms_used'] ?? 0,
                ];
            }, $faculty);
            $writeSheet($sheet2, 'Faculty', $facultyHeaders, $facultyRows);

            // Sheet 3: Subjects
            $sheet3 = $spreadsheet->createSheet();
            $subjects = $data['subject_popularity'] ?? [];
            $subjectHeaders = ['Subject Code','Subject Name','Department','Enrolled Students','Faculty Assigned','Credits'];
            $subjectRows = array_map(function($s){
                return [
                    $s['subject_code'] ?? '',
                    $s['subject_name'] ?? '',
                    $s['department'] ?? '',
                    $s['enrolled_students'] ?? 0,
                    $s['faculty_assigned'] ?? 0,
                    $s['credits'] ?? '',
                ];
            }, $subjects);
            $writeSheet($sheet3, 'Subjects', $subjectHeaders, $subjectRows);

            // Save
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filepath);

            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];
        } catch (Exception $e) {
            $this->log("Error generating resource Excel: " . $e->getMessage(), 'error');
            return [ 'success' => false, 'error' => $e->getMessage() ];
        }
    }

    /**
     * Generate Resource Utilization PDF with sectioned tables
     */
    private function generateResourcePDF($data, $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';

            // Force landscape orientation explicitly for this report
            $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('System Administrator');
            $pdf->SetTitle('Resource Utilization Report');
            $pdf->SetMargins(12, 20, 12);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            // Re-assert landscape before adding first page
            $pdf->setPageOrientation('L', true, 0);
            $pdf->AddPage('L');

            $html = '<h2 style="text-align:center; color:#4472C4; margin:0;">Resource Utilization</h2>';
            $html .= '<p style="text-align:center; margin:4px 0 12px 0;">Generated on: ' . date('F j, Y g:i A') . '</p>';

            // Helper to build table
            $renderTable = function($title, $headers, $rows) {
                $section = '<h3 style="color:#444; margin:8px 0 6px 0;">' . htmlspecialchars($title) . '</h3>';
                if (empty($rows)) {
                    return $section . '<p>No data available.</p>';
                }
                $section .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
                $section .= '<thead style="background-color:#f0f0f0;"><tr>';
                foreach ($headers as $h) { $section .= '<th align="left">' . htmlspecialchars($h) . '</th>'; }
                $section .= '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    $section .= '<tr>';
                    foreach ($r as $v) { $section .= '<td>' . htmlspecialchars((string)($v ?? '')) . '</td>'; }
                    $section .= '</tr>';
                }
                $section .= '</tbody></table><br/>';
                return $section;
            };

            // Sections
            $classrooms = $data['classroom_utilization'] ?? [];
            $classroomHeaders = ['Room Number','Building','Capacity','Type','Scheduled Classes','Utilization %'];
            $classroomRows = array_map(function($c){
                return [
                    $c['room_number'] ?? '',
                    $c['building'] ?? '',
                    $c['capacity'] ?? '',
                    $c['type'] ?? '',
                    $c['scheduled_classes'] ?? 0,
                    $c['utilization_percentage'] ?? 0,
                ];
            }, $classrooms);
            $html .= $renderTable('Classroom Utilization', $classroomHeaders, $classroomRows);

            $faculty = $data['faculty_workload'] ?? [];
            $facultyHeaders = ['Employee ID','Faculty Name','Department','Teaching Load','Subjects Taught','Classrooms Used'];
            $facultyRows = array_map(function($f){
                return [
                    $f['employee_id'] ?? '',
                    $f['faculty_name'] ?? '',
                    $f['department'] ?? '',
                    $f['teaching_load'] ?? 0,
                    $f['subjects_taught'] ?? 0,
                    $f['classrooms_used'] ?? 0,
                ];
            }, $faculty);
            $html .= $renderTable('Faculty Workload', $facultyHeaders, $facultyRows);

            $subjects = $data['subject_popularity'] ?? [];
            $subjectHeaders = ['Subject Code','Subject Name','Department','Enrolled Students','Faculty Assigned','Credits'];
            $subjectRows = array_map(function($s){
                return [
                    $s['subject_code'] ?? '',
                    $s['subject_name'] ?? '',
                    $s['department'] ?? '',
                    $s['enrolled_students'] ?? 0,
                    $s['faculty_assigned'] ?? 0,
                    $s['credits'] ?? '',
                ];
            }, $subjects);
            $html .= $renderTable('Subject Popularity', $subjectHeaders, $subjectRows);

            $pdf->writeHTML($html, true, false, true, false, '');
            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');

            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.pdf'
            ];
        } catch (Exception $e) {
            $this->log("Error generating resource PDF: " . $e->getMessage(), 'error');
            return [ 'success' => false, 'error' => $e->getMessage() ];
        }
    }


    /**
     * Generate a well-formatted System Activity Excel export
     */
    private function generateActivityExcel($data, $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('System Activity');

            $headers = ['Timestamp', 'User', 'Role', 'Action', 'Table', 'Description'];
            $sheet->fromArray(['System Activity Report'], null, 'A1');
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sheet->fromArray($headers, null, 'A3');
            $sheet->getStyle('A3:F3')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ]);

            $row = 4;
            foreach (($data ?? []) as $r) {
                $sheet->fromArray([
                    $r['timestamp'] ?? '',
                    $r['user_full_name'] ?? ($r['username'] ?? ''),
                    $r['role'] ?? '',
                    $r['action'] ?? '',
                    $r['table_affected'] ?? '',
                    $r['description'] ?? ''
                ], null, 'A' . $row);
                $row++;
            }

            $lastRow = $row - 1;
            $sheet->getStyle('A3:F' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            for ($i = 4; $i <= $lastRow; $i++) {
                if ($i % 2 === 0) {
                    $sheet->getStyle('A' . $i . ':F' . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F7F7F7');
                }
            }
            foreach (range('A', 'F') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filepath);
            return ['success' => true, 'filename' => $filename . '.xlsx', 'filepath' => $filepath, 'download_url' => EXPORTS_URL . $filename . '.xlsx'];
        } catch (Exception $e) {
            $this->log("Error generating activity Excel: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate a well-formatted System Activity PDF export
     */
    private function generateActivityPDF($data, $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('System Administrator');
            $pdf->SetTitle('System Activity Report');
            $pdf->SetMargins(12, 20, 12);
            $pdf->AddPage();

            $html = '<h2 style="text-align:center; color:#2F5597; margin:0;">System Activity Report</h2>';
            $html .= '<p style="text-align:center; margin:4px 0 12px 0; color:#606060;">Generated on: ' . date('F j, Y g:i A') . '</p>';
            $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">'
                . '<thead style="background-color:#f0f0f0;">'
                . '<tr>'
                . '<th align="left">Timestamp</th>'
                . '<th align="left">User</th>'
                . '<th align="left">Role</th>'
                . '<th align="left">Action</th>'
                . '<th align="left">Table</th>'
                . '<th align="left">Description</th>'
                . '</tr></thead><tbody>';

            foreach (($data ?? []) as $r) {
                $html .= '<tr>'
                    . '<td>' . htmlspecialchars((string)($r['timestamp'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string)($r['user_full_name'] ?? ($r['username'] ?? ''))) . '</td>'
                    . '<td>' . htmlspecialchars((string)($r['role'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string)($r['action'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string)($r['table_affected'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string)($r['description'] ?? '')) . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table>';

            $pdf->writeHTML($html, true, false, true, false, '');
            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');
            return ['success' => true, 'filename' => $filename . '.pdf', 'filepath' => $filepath, 'download_url' => EXPORTS_URL . $filename . '.pdf'];
        } catch (Exception $e) {
            $this->log("Error generating activity PDF: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Log messages for debugging and audit purposes
     */
    private function log($message, $level = 'info') {
        if ($this->logger) {
            $this->logger->$level($message);
        } else {
            error_log("[Report] [{$level}] {$message}");
        }
    }
    
    // ===========================================
    // SYSTEM STATISTICS & ANALYTICS
    // ===========================================
    
    /**
     * Get comprehensive system statistics for admin dashboard
     * 
     * @return array System statistics and metrics
     */
    public function getSystemStatistics() {
        try {
            $stats = [];
            
            // User Statistics
            $userStats = $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_users,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_users,
                    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_users,
                    COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
                    COUNT(CASE WHEN role = 'faculty' THEN 1 END) as faculty_count,
                    COUNT(CASE WHEN role = 'student' THEN 1 END) as student_count
                FROM users
            ");
            $stats['users'] = $userStats;
            
            // Academic Resources Statistics
            $resourceStats = $this->db->fetchRow("
                SELECT 
                    (SELECT COUNT(*) FROM subjects WHERE is_active = 1) as active_subjects,
                    (SELECT COUNT(*) FROM classrooms WHERE is_active = 1) as active_classrooms,
                    (SELECT COUNT(*) FROM time_slots WHERE is_active = 1) as active_time_slots,
                    (SELECT COUNT(*) FROM departments WHERE is_active = 1) as active_departments,
                    (SELECT COUNT(*) FROM faculty_subjects WHERE is_active = 1) as faculty_assignments
            ");
            $stats['resources'] = $resourceStats;
            
            // Timetable Statistics
            $timetableStats = $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_schedules,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_schedules,
                    COUNT(DISTINCT faculty_id) as scheduled_faculty,
                    COUNT(DISTINCT classroom_id) as utilized_classrooms,
                    COUNT(DISTINCT subject_id) as scheduled_subjects
                FROM timetables
            ");
            $stats['timetables'] = $timetableStats;
            
            // Enrollment Statistics
            $enrollmentStats = $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_enrollments,
                    COUNT(CASE WHEN status = 'enrolled' THEN 1 END) as active_enrollments,
                    COUNT(CASE WHEN status = 'dropped' THEN 1 END) as dropped_enrollments,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_enrollments,
                    COUNT(DISTINCT student_id) as enrolled_students
                FROM enrollments
            ");
            $stats['enrollments'] = $enrollmentStats;
            
            // Recent Activity (last 30 days)
            $activityStats = $this->db->fetchRow("
                SELECT 
                    COUNT(CASE WHEN action = 'LOGIN' AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_logins,
                    COUNT(CASE WHEN action = 'CREATE_USER' AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_registrations,
                    COUNT(CASE WHEN table_affected = 'timetables' AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_schedule_changes,
                    COUNT(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as weekly_activities
                FROM audit_logs
            ");
            $stats['activity'] = $activityStats;
            
            // System Health
            $systemHealth = $this->db->fetchRow("
                SELECT 
                    (SELECT COUNT(*) FROM notifications WHERE is_active = 1 AND expires_at < NOW()) as expired_notifications,
                    (SELECT COUNT(*) FROM backup_logs WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_backups,
                    (SELECT COUNT(*) FROM login_attempts WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as failed_logins_24h
            ");
            $stats['system_health'] = $systemHealth;
            
            $this->log("Generated system statistics successfully");
            return $stats;
            
        } catch (Exception $e) {
            $this->log("Error generating system statistics: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Get user statistics by role and department
     * 
     * @param array $filters Optional filters
     * @return array User statistics
     */
    public function getUserStatistics($filters = []) {
        try {
            $whereConditions = ["1=1"];
            $params = [];
            
            // Apply filters
            if (!empty($filters['role'])) {
                $whereConditions[] = "u.role = ?";
                $params[] = $filters['role'];
            }
            
            if (!empty($filters['status'])) {
                $whereConditions[] = "u.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['department'])) {
                $whereConditions[] = "(
                    (u.role = 'student' AND s.department = ?) OR
                    (u.role = 'faculty' AND f.department = ?) OR
                    (u.role = 'admin' AND a.department = ?)
                )";
                $params[] = $filters['department'];
                $params[] = $filters['department'];
                $params[] = $filters['department'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "DATE(u.created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "DATE(u.created_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // User counts by role and status
            $userCounts = $this->db->fetchAll("
                SELECT 
                    u.role,
                    u.status,
                    COUNT(*) as count,
                    CASE 
                        WHEN u.role = 'student' THEN s.department
                        WHEN u.role = 'faculty' THEN f.department
                        WHEN u.role = 'admin' THEN a.department
                        ELSE 'Unknown'
                    END as department
                FROM users u
                LEFT JOIN students s ON u.user_id = s.user_id
                LEFT JOIN faculty f ON u.user_id = f.user_id
                LEFT JOIN admin_profiles a ON u.user_id = a.user_id
                WHERE {$whereClause}
                GROUP BY u.role, u.status, department
                ORDER BY u.role, department, u.status
            ", $params);
            
            // Registration trends (last 12 months)
            $registrationTrends = $this->db->fetchAll("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    role,
                    COUNT(*) as registrations
                FROM users u
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m'), role
                ORDER BY month DESC, role
            ");
            
            return [
                'user_counts' => $userCounts,
                'registration_trends' => $registrationTrends
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating user statistics: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Get timetable and resource utilization statistics
     * 
     * @param array $filters Optional filters
     * @return array Timetable utilization data
     */
    public function getTimetableUtilization($filters = []) {
        try {
            $currentAcademicYear = $filters['academic_year'] ?? null;
            $currentSemester = $filters['semester'] ?? null;

            // Auto-detect latest term if not provided
            if (empty($currentAcademicYear) || empty($currentSemester)) {
                $latest = $this->db->fetchRow(
                    "SELECT academic_year, semester
                     FROM timetables
                     WHERE is_active = 1
                     ORDER BY academic_year DESC, semester DESC
                     LIMIT 1"
                );
                if ($latest) {
                    $currentAcademicYear = $currentAcademicYear ?: $latest['academic_year'];
                    $currentSemester = $currentSemester ?: (int)$latest['semester'];
                } else {
                    // Fallback if no timetables exist
                    $currentAcademicYear = $currentAcademicYear ?: (date('Y') . '-' . (date('Y') + 1));
                    $currentSemester = $currentSemester ?: 1;
                }
            }

            // Classroom utilization
            $classroomUtil = $this->db->fetchAll(
                "SELECT 
                    c.room_number,
                    c.building,
                    c.capacity,
                    c.type,
                    COUNT(t.timetable_id) as scheduled_slots,
                    ROUND(COUNT(t.timetable_id) / (
                        SELECT COUNT(*) FROM time_slots
                    ) * 100, 2) as utilization_percentage
                 FROM classrooms c
                 LEFT JOIN timetables t ON c.classroom_id = t.classroom_id 
                    AND t.academic_year = ?
                    AND t.semester = ?
                 GROUP BY c.classroom_id, c.room_number, c.building, c.capacity, c.type
                 ORDER BY utilization_percentage DESC",
                [$currentAcademicYear, $currentSemester]
            );

            // Faculty workload (include classrooms_used)
            $facultyWorkload = $this->db->fetchAll(
                "SELECT 
                    f.employee_id,
                    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                    f.department,
                    f.designation,
                    COUNT(t.timetable_id) as teaching_slots,
                    COUNT(DISTINCT t.subject_id) as unique_subjects,
                    COUNT(DISTINCT t.classroom_id) as classrooms_used,
                    ROUND(AVG(sub.credits), 2) as avg_credits
                 FROM faculty f
                 LEFT JOIN timetables t ON f.faculty_id = t.faculty_id 
                    AND t.academic_year = ?
                    AND t.semester = ?
                 LEFT JOIN subjects sub ON t.subject_id = sub.subject_id
                 GROUP BY f.faculty_id, f.employee_id, f.first_name, f.last_name, f.department, f.designation
                 ORDER BY teaching_slots DESC",
                [$currentAcademicYear, $currentSemester]
            );

            // Subject enrollment statistics
            $subjectStats = $this->db->fetchAll(
                "SELECT 
                    sub.subject_code,
                    sub.subject_name,
                    sub.department,
                    sub.credits,
                    COUNT(DISTINCT e.student_id) as enrolled_students,
                    COUNT(DISTINCT t.faculty_id) as assigned_faculty,
                    COUNT(DISTINCT t.classroom_id) as used_classrooms
                 FROM subjects sub
                 LEFT JOIN enrollments e ON sub.subject_id = e.subject_id 
                    AND e.status = 'enrolled'
                    AND e.academic_year = ?
                    AND e.semester = ?
                 LEFT JOIN timetables t ON sub.subject_id = t.subject_id 
                    AND t.academic_year = ?
                    AND t.semester = ?
                 GROUP BY sub.subject_id, sub.subject_code, sub.subject_name, sub.department, sub.credits
                 ORDER BY enrolled_students DESC",
                [$currentAcademicYear, $currentSemester, $currentAcademicYear, $currentSemester]
            );

            // Time slot utilization
            $timeSlotUtil = $this->db->fetchAll(
                "SELECT 
                    ts.slot_name,
                    ts.start_time,
                    ts.end_time,
                    ts.day_of_week,
                    COUNT(t.timetable_id) as scheduled_classes,
                    ROUND(COUNT(t.timetable_id) / (
                        SELECT COUNT(*) FROM classrooms
                    ) * 100, 2) as utilization_percentage
                 FROM time_slots ts
                 LEFT JOIN timetables t ON ts.slot_id = t.slot_id 
                    AND t.academic_year = ?
                    AND t.semester = ?
                 GROUP BY ts.slot_id, ts.slot_name, ts.start_time, ts.end_time, ts.day_of_week
                 ORDER BY ts.day_of_week, ts.start_time",
                [$currentAcademicYear, $currentSemester]
            );

            return [
                'classroom_utilization' => $classroomUtil,
                'faculty_workload' => $facultyWorkload,
                'subject_statistics' => $subjectStats,
                'time_slot_utilization' => $timeSlotUtil
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating timetable utilization: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Get department-wise statistics and analytics
     * 
     * @return array Department statistics
     */
    public function getDepartmentStatistics() {
        try {
            // Department overview
            $departmentStats = $this->db->fetchAll("
                SELECT 
                    d.department_name,
                    d.department_code,
                    d.head_of_department,
                    COUNT(DISTINCT CASE WHEN u.role = 'faculty' THEN u.user_id END) as faculty_count,
                    COUNT(DISTINCT CASE WHEN u.role = 'student' THEN u.user_id END) as student_count,
                    COUNT(DISTINCT s.subject_id) as subject_count,
                    COUNT(DISTINCT c.classroom_id) as classroom_count
                FROM departments d
                LEFT JOIN users u ON (
                    (u.role = 'faculty' AND EXISTS(SELECT 1 FROM faculty f WHERE f.user_id = u.user_id AND f.department = d.department_name)) OR
                    (u.role = 'student' AND EXISTS(SELECT 1 FROM students st WHERE st.user_id = u.user_id AND st.department = d.department_name))
                ) AND u.status = 'active'
                LEFT JOIN subjects s ON s.department = d.department_name AND s.is_active = 1
                LEFT JOIN classrooms c ON c.department_id = d.department_id AND c.is_active = 1
                WHERE d.is_active = 1
                GROUP BY d.department_id
                ORDER BY d.department_name
            ");
            
            // Resource distribution by department
            $resourceDistribution = $this->db->fetchAll("
                SELECT 
                    department,
                    COUNT(*) as resource_count,
                    'subjects' as resource_type
                FROM subjects 
                WHERE is_active = 1 
                GROUP BY department
                
                UNION ALL
                
                SELECT 
                    d.department_name as department,
                    COUNT(c.classroom_id) as resource_count,
                    'classrooms' as resource_type
                FROM departments d
                LEFT JOIN classrooms c ON d.department_id = c.department_id AND c.is_active = 1
                WHERE d.is_active = 1
                GROUP BY d.department_id, d.department_name
                
                ORDER BY department, resource_type
            ");
            
            return [
                'department_overview' => $departmentStats,
                'resource_distribution' => $resourceDistribution
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating department statistics: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Get system activity and audit statistics
     * 
     * @param int $days Number of days to analyze
     * @return array Activity statistics
     */
    public function getActivityStatistics($days = 30) {
        try {
            // Activity by action type
            $actionStats = $this->db->fetchAll("
                SELECT 
                    action,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users,
                    MAX(timestamp) as last_occurrence
                FROM audit_logs 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY action
                ORDER BY count DESC
            ", [$days]);
            
            // Daily activity trends
            $dailyActivity = $this->db->fetchAll("
                SELECT 
                    DATE(timestamp) as activity_date,
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT user_id) as active_users,
                    COUNT(CASE WHEN action = 'LOGIN' THEN 1 END) as login_count,
                    COUNT(CASE WHEN action LIKE 'CREATE_%' THEN 1 END) as create_actions,
                    COUNT(CASE WHEN action LIKE 'UPDATE_%' THEN 1 END) as update_actions,
                    COUNT(CASE WHEN action LIKE 'DELETE_%' THEN 1 END) as delete_actions
                FROM audit_logs 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(timestamp)
                ORDER BY activity_date DESC
            ", [$days]);
            
            // Most active users
            $activeUsers = $this->db->fetchAll("
                SELECT 
                    u.username,
                    u.role,
                    CASE 
                        WHEN u.role = 'admin' THEN CONCAT(ap.first_name, ' ', ap.last_name)
                        WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                        WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                        ELSE u.username
                    END as full_name,
                    COUNT(al.log_id) as activity_count,
                    MAX(al.timestamp) as last_activity
                FROM users u
                INNER JOIN audit_logs al ON u.user_id = al.user_id
                LEFT JOIN admin_profiles ap ON u.user_id = ap.user_id AND u.role = 'admin'
                LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
                LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
                WHERE al.timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY u.user_id
                ORDER BY activity_count DESC
                LIMIT 10
            ", [$days]);
            
            return [
                'action_statistics' => $actionStats,
                'daily_activity' => $dailyActivity,
                'most_active_users' => $activeUsers
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating activity statistics: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Get backup and system maintenance statistics
     * 
     * @return array Backup statistics
     */
    public function getBackupStatistics() {
        try {
            // Backup summary
            $backupSummary = $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_backups,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_backups,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_backups,
                    SUM(file_size) as total_backup_size,
                    MAX(created_at) as last_backup_date,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_backups
                FROM backup_logs
                WHERE deleted_at IS NULL
            ");
            
            // Backup history
            $backupHistory = $this->db->fetchAll("
                SELECT 
                    backup_id,
                    filename,
                    backup_type,
                    file_size,
                    frequency,
                    status,
                    created_at,
                    description
                FROM backup_logs
                WHERE deleted_at IS NULL
                ORDER BY created_at DESC
                LIMIT 20
            ");
            
            // Backup frequency analysis
            $frequencyStats = $this->db->fetchAll("
                SELECT 
                    frequency,
                    COUNT(*) as count,
                    AVG(file_size) as avg_size,
                    MAX(created_at) as last_backup
                FROM backup_logs
                WHERE deleted_at IS NULL AND status = 'completed'
                GROUP BY frequency
                ORDER BY count DESC
            ");
            
            return [
                'summary' => $backupSummary,
                'history' => $backupHistory,
                'frequency_analysis' => $frequencyStats
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating backup statistics: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    // ===========================================
    // CUSTOM REPORT GENERATION
    // ===========================================
    
    /**
     * Generate custom report based on filters and parameters
     * 
     * @param array $reportConfig Report configuration
     * @param int $createdBy User ID generating the report
     * @return array Report data and metadata
     */
    public function generateCustomReport($reportConfig, $createdBy) {
        try {
            $reportType = $reportConfig['type'] ?? 'users';
            $filters = $reportConfig['filters'] ?? [];
            $format = $reportConfig['format'] ?? 'table';
            
            $reportData = [];
            $reportMetadata = [
                'title' => $reportConfig['title'] ?? 'Custom Report',
                'generated_by' => $createdBy,
                'generated_at' => date('Y-m-d H:i:s'),
                'filters_applied' => $filters,
                'type' => $reportType
            ];
            
            switch ($reportType) {
                case 'users':
                    $reportData = $this->generateUserReport($filters);
                    break;
                    
                case 'timetables':
                    $reportData = $this->generateTimetableReport($filters);
                    break;
                    
                case 'resources':
                    $reportData = $this->generateResourceReport($filters);
                    break;
                    
                case 'activity':
                    $reportData = $this->generateActivityReport($filters);
                    break;
                    
                case 'analytics':
                    $reportData = $this->generateAnalyticsReport($filters);
                    break;
                    
                default:
                    throw new Exception("Unknown report type: {$reportType}");
            }
            
            // Log report generation
            $this->log("Custom report generated: {$reportType} by user {$createdBy}");
            
            return [
                'success' => true,
                'data' => $reportData,
                'metadata' => $reportMetadata
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating custom report: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate user report with filtering
     * 
     * @param array $filters Report filters
     * @return array User report data
     */
    private function generateUserReport($filters) {
        $whereConditions = ["1=1"];
        $params = [];
        
        // Apply filters
        if (!empty($filters['role'])) {
            $whereConditions[] = "u.role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "u.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "(
                (u.role = 'student' AND s.department = ?) OR
                (u.role = 'faculty' AND f.department = ?) OR
                (u.role = 'admin' AND a.department = ?)
            )";
            $params[] = $filters['department'];
            $params[] = $filters['department'];
            $params[] = $filters['department'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(u.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(u.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        return $this->db->fetchAll("
            SELECT 
                u.user_id,
                u.username,
                u.email,
                u.role,
                u.status,
                u.created_at,
                u.last_login,
                CASE 
                    WHEN u.role = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                    WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                    WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                    ELSE u.username
                END as full_name,
                CASE 
                    WHEN u.role = 'admin' THEN a.department
                    WHEN u.role = 'faculty' THEN f.department
                    WHEN u.role = 'student' THEN s.department
                    ELSE NULL
                END as department,
                CASE 
                    WHEN u.role = 'admin' THEN a.employee_id
                    WHEN u.role = 'faculty' THEN f.employee_id
                    WHEN u.role = 'student' THEN s.student_number
                    ELSE NULL
                END as identifier
            FROM users u
            LEFT JOIN admin_profiles a ON u.user_id = a.user_id AND u.role = 'admin'
            LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
            LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
            WHERE {$whereClause}
            ORDER BY u.created_at DESC
        ", $params);
    }
    
    /**
     * Generate timetable report with filtering
     * 
     * @param array $filters Report filters
     * @return array Timetable report data
     */
    private function generateTimetableReport($filters) {
        $whereConditions = ["t.is_active = 1"];
        $params = [];
        
        // Apply filters
        if (!empty($filters['academic_year'])) {
            $whereConditions[] = "t.academic_year = ?";
            $params[] = $filters['academic_year'];
        }
        
        if (!empty($filters['semester'])) {
            $whereConditions[] = "t.semester = ?";
            $params[] = $filters['semester'];
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "sub.department = ?";
            $params[] = $filters['department'];
        }
        
        if (!empty($filters['faculty_id'])) {
            $whereConditions[] = "t.faculty_id = ?";
            $params[] = $filters['faculty_id'];
        }
        
        if (!empty($filters['subject_id'])) {
            $whereConditions[] = "t.subject_id = ?";
            $params[] = $filters['subject_id'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        return $this->db->fetchAll("
            SELECT 
                t.timetable_id,
                sub.subject_code,
                sub.subject_name,
                sub.department,
                CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                c.room_number,
                c.building,
                ts.slot_name,
                ts.start_time,
                ts.end_time,
                ts.day_of_week,
                t.section,
                t.semester,
                t.academic_year,
                t.created_at
            FROM timetables t
            JOIN subjects sub ON t.subject_id = sub.subject_id
            JOIN faculty f ON t.faculty_id = f.faculty_id
            JOIN classrooms c ON t.classroom_id = c.classroom_id
            JOIN time_slots ts ON t.slot_id = ts.slot_id
            WHERE {$whereClause}
            ORDER BY ts.day_of_week, ts.start_time, sub.department
        ", $params);
    }
    
    /**
     * Generate resource utilization report
     * 
     * @param array $filters Report filters
     * @return array Resource report data
     */
    private function generateResourceReport($filters) {
        $reportData = [];
        
        // Classroom utilization
        $reportData['classroom_utilization'] = $this->db->fetchAll("
            SELECT 
                c.room_number,
                c.building,
                c.capacity,
                c.type,
                COUNT(t.timetable_id) as scheduled_classes,
                ROUND(COUNT(t.timetable_id) / (
                    SELECT COUNT(*) FROM time_slots WHERE is_active = 1
                ) * 100, 2) as utilization_percentage
            FROM classrooms c
            LEFT JOIN timetables t ON c.classroom_id = t.classroom_id AND t.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.classroom_id, c.room_number, c.building, c.capacity, c.type
            ORDER BY utilization_percentage DESC
        ");
        
        // Faculty workload distribution
        $reportData['faculty_workload'] = $this->db->fetchAll("
            SELECT 
                f.employee_id,
                CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                f.department,
                COUNT(t.timetable_id) as teaching_load,
                COUNT(DISTINCT t.subject_id) as subjects_taught,
                COUNT(DISTINCT t.classroom_id) as classrooms_used
            FROM faculty f
            LEFT JOIN timetables t ON f.faculty_id = t.faculty_id AND t.is_active = 1
            WHERE f.is_active = 1
            GROUP BY f.faculty_id, f.employee_id, f.first_name, f.last_name, f.department
            ORDER BY teaching_load DESC
        ");
        
        // Subject popularity
        $reportData['subject_popularity'] = $this->db->fetchAll("
            SELECT 
                sub.subject_code,
                sub.subject_name,
                sub.department,
                COUNT(DISTINCT e.student_id) as enrolled_students,
                COUNT(DISTINCT t.faculty_id) as faculty_assigned,
                sub.credits
            FROM subjects sub
            LEFT JOIN enrollments e ON sub.subject_id = e.subject_id AND e.status = 'enrolled'
            LEFT JOIN timetables t ON sub.subject_id = t.subject_id AND t.is_active = 1
            WHERE sub.is_active = 1
            GROUP BY sub.subject_id, sub.subject_code, sub.subject_name, sub.department, sub.credits
            ORDER BY enrolled_students DESC
        ");
        
        return $reportData;
    }
    
    /**
     * Generate activity report
     * 
     * @param array $filters Report filters
     * @return array Activity report data
     */
    private function generateActivityReport($filters) {
        $days = $filters['days'] ?? 30;
        
        return $this->db->fetchAll("
            SELECT 
                al.timestamp,
                al.action,
                al.table_affected,
                al.description,
                u.username,
                u.role,
                CASE 
                    WHEN u.role = 'admin' THEN CONCAT(ap.first_name, ' ', ap.last_name)
                    WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                    WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                    ELSE u.username
                END as user_full_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.user_id
            LEFT JOIN admin_profiles ap ON u.user_id = ap.user_id AND u.role = 'admin'
            LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
            LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
            WHERE al.timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY al.timestamp DESC
            LIMIT 500
        ", [$days]);
    }
    
    /**
     * Generate analytics summary report
     * 
     * @param array $filters Report filters
     * @return array Analytics report data
     */
    private function generateAnalyticsReport($filters) {
        $reportData = [];
        
        // System overview
        $reportData['system_overview'] = $this->getSystemStatistics();
        
        // Utilization metrics
        $reportData['utilization'] = $this->getTimetableUtilization($filters);
        
        // Department breakdown
        $reportData['departments'] = $this->getDepartmentStatistics();
        
        // Growth trends
        $reportData['growth_trends'] = $this->db->fetchAll("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as new_users,
                COUNT(CASE WHEN role = 'student' THEN 1 END) as new_students,
                COUNT(CASE WHEN role = 'faculty' THEN 1 END) as new_faculty
            FROM users
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");
        
        return $reportData;
    }
    
    // ===========================================
    // EXPORT INTEGRATION
    // ===========================================
    
    /**
     * Export report data using ExportService
     * 
     * @param array $reportData Report data to export
     * @param string $reportType Type of report
     * @param int $exportedBy User ID performing export
     * @param string $format Export format (pdf|excel)
     * @return array Export result
     */
    public function exportReport($reportData, $reportType, $exportedBy, $format = 'excel') {
        try {
            $filename = $reportType . '_report_' . date('Y_m_d_H_i_s');
            
            switch ($reportType) {
                case 'users':
                    if ($format === 'pdf') {
                        return $this->exportService->generateUsersPDF($reportData, $filename);
                    } else {
                        return $this->exportService->generateUsersExcel($reportData, $filename);
                    }
                    break;
                
                case 'timetables':
                case 'timetables_custom':
                    if ($format === 'pdf') {
                        return $this->generateTimetablePDF($reportData, $filename);
                    } else {
                        return $this->generateTimetableExcel($reportData, $filename);
                    }
                    break;

                case 'resources':
                case 'resource_utilization':
                    // Use specialized resource utilization exporters that expect structured data
                    if ($format === 'pdf') {
                        return $this->generateResourcePDF($reportData, $filename);
                    }
                    return $this->generateResourceExcel($reportData, $filename);
                    break;

                case 'activity':
                case 'system_activity':
                    if ($format === 'pdf') {
                        return $this->generateActivityPDF($reportData, $filename);
                    } else {
                        return $this->generateActivityExcel($reportData, $filename);
                    }
                    break;

                case 'system_stats':
                    return $this->exportService->exportSystemStats($format);
                    break;
                
                default:
                    return $this->exportGenericReport($reportData, $filename, $format, $reportType);
            }
            
        } catch (Exception $e) {
            $this->log("Error exporting report: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Export generic report data
     * 
     * @param array $data Report data
     * @param string $filename Base filename
     * @param string $format Export format
     * @param string $type Report type
     * @return array Export result
     */
    private function exportGenericReport($data, $filename, $format, $type) {
        if ($format === 'excel') {
            return $this->generateGenericExcel($data, $filename, $type);
        } else {
            return $this->generateGenericPDF($data, $filename, $type);
        }
    }
    
    /**
     * Generate generic Excel export
     * 
     * @param array $data Report data
     * @param string $filename Filename
     * @param string $type Report type
     * @return array Export result
     */
    private function generateGenericExcel($data, $filename, $type) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(ucfirst($type) . ' Report');
            
            if (empty($data)) {
                $sheet->setCellValue('A1', 'No data available for this report');
                return [
                    'success' => false,
                    'message' => 'No data to export'
                ];
            }
            
            // Get headers from first row
            $headers = array_keys($data[0]);
            $sheet->fromArray($headers, null, 'A1');
            
            // Style headers
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ];
            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray($headerStyle);
            
            // Add data
            $row = 2;
            foreach ($data as $record) {
                $col = 1;
                foreach ($record as $value) {
                    $sheet->setCellValueByColumnAndRow($col, $row, $value);
                    $col++;
                }
                $row++;
            }
            
            // Auto-size columns
            foreach (range('A', $lastColumn) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filepath);
            
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating generic Excel report: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate generic PDF export
     * 
     * @param array $data Report data
     * @param string $filename Filename
     * @param string $type Report type
     * @return array Export result
     */
    private function generateGenericPDF($data, $filename, $type) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('System Administrator');
            $pdf->SetTitle(ucfirst($type) . ' Report');
            $pdf->SetSubject('System Report');
            
            // Set margins and add page
            $pdf->SetMargins(15, 27, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            $pdf->AddPage();
            
            // Title
            $html = '<h1 style="text-align:center; color:#4472C4;">' . ucfirst($type) . ' Report</h1>';
            $html .= '<p style="text-align:center;">Generated on: ' . date('F j, Y g:i A') . '</p><br>';
            
            if (empty($data)) {
                $html .= '<p>No data available for this report.</p>';
            } else {
                // Create table
                $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;">';
                
                // Headers
                $headers = array_keys($data[0]);
                $html .= '<tr style="background-color:#f0f0f0;">';
                foreach ($headers as $header) {
                    $html .= '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . '</th>';
                }
                $html .= '</tr>';
                
                // Data rows
                foreach ($data as $record) {
                    $html .= '<tr>';
                    foreach ($record as $value) {
                        $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
                    }
                    $html .= '</tr>';
                }
                
                $html .= '</table>';
            }
            
            $pdf->writeHTML($html, true, false, true, false, '');
            
            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');
            
            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.pdf'
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating generic PDF report: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate a well-formatted Timetable Excel export
     *
     * @param array $data Timetable rows (from generateTimetableReport)
     * @param string $filename Base filename without extension
     * @return array Export result
     */
    private function generateTimetableExcel($data, $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Timetables');

            // Title
            $sheet->setCellValue('A1', 'Timetable Report');
            $sheet->mergeCells('A1:K1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Headers
            $headers = [
                'Day', 'Start Time', 'End Time', 'Subject Code', 'Subject Name', 'Section',
                'Faculty', 'Room', 'Building', 'Department', 'Academic Year / Sem.'
            ];
            $sheet->fromArray($headers, null, 'A3');
            $sheet->getStyle('A3:K3')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
            ]);

            if (empty($data)) {
                $sheet->setCellValue('A5', 'No timetable data available for the selected filters.');
                $sheet->mergeCells('A5:K5');
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $filepath = EXPORTS_PATH . $filename . '.xlsx';
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save($filepath);
                return [
                    'success' => true,
                    'filename' => $filename . '.xlsx',
                    'filepath' => $filepath,
                    'download_url' => EXPORTS_URL . $filename . '.xlsx'
                ];
            }

            // Sort by day_of_week then start_time
            usort($data, function ($a, $b) {
                $dayA = $a['day_of_week'] ?? '';
                $dayB = $b['day_of_week'] ?? '';
                if ($dayA === $dayB) {
                    return strcmp((string)($a['start_time'] ?? ''), (string)($b['start_time'] ?? ''));
                }
                return strcmp($dayA, $dayB);
            });

            // Data rows start at row 4
            $row = 4;
            foreach ($data as $r) {
                $sheet->fromArray([
                    $r['day_of_week'] ?? '',
                    $r['start_time'] ?? '',
                    $r['end_time'] ?? '',
                    $r['subject_code'] ?? '',
                    $r['subject_name'] ?? '',
                    $r['section'] ?? '',
                    $r['faculty_name'] ?? '',
                    $r['room_number'] ?? '',
                    $r['building'] ?? '',
                    $r['department'] ?? '',
                    ($r['academic_year'] ?? '') . ' / ' . ($r['semester'] ?? '')
                ], null, 'A' . $row);
                $row++;
            }

            // Styling: borders and zebra stripes
            $lastRow = $row - 1;
            $sheet->getStyle("A3:K{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            for ($r = 4; $r <= $lastRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:K{$r}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F7F7F7');
                }
            }

            // Auto width and freeze header
            foreach (range('A', 'K') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->freezePane('A4');
            $sheet->setAutoFilter("A3:K{$lastRow}");

            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filepath);

            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];

        } catch (Exception $e) {
            $this->log("Error generating timetable Excel: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate a well-formatted Timetable PDF export
     *
     * @param array $data Timetable rows (from generateTimetableReport)
     * @param string $filename Base filename without extension
     * @return array Export result
     */
    private function generateTimetablePDF($data, $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';

            $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('System Administrator');
            $pdf->SetTitle('Timetable Report');
            $pdf->SetMargins(12, 20, 12);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            $pdf->AddPage();

            $html = '<h2 style="text-align:center; color:#2F5597; margin:0;">Timetable Report</h2>';
            $html .= '<p style="text-align:center; margin:4px 0 12px 0;">Generated on: ' . date('F j, Y g:i A') . '</p>';

            if (empty($data)) {
                $html .= '<p>No timetable data available for the selected filters.</p>';
            } else {
                // Sort and group by day
                usort($data, function ($a, $b) {
                    $dayA = $a['day_of_week'] ?? '';
                    $dayB = $b['day_of_week'] ?? '';
                    if ($dayA === $dayB) {
                        return strcmp((string)($a['start_time'] ?? ''), (string)($b['start_time'] ?? ''));
                    }
                    return strcmp($dayA, $dayB);
                });

                $currentDay = null;
                foreach ($data as $r) {
                    if ($currentDay !== ($r['day_of_week'] ?? '')) {
                        if ($currentDay !== null) {
                            $html .= '</tbody></table><br/>';
                        }
                        $currentDay = $r['day_of_week'] ?? '';
                        $html .= '<h3 style="color:#444; margin:8px 0 6px 0;">' . htmlspecialchars($currentDay) . '</h3>';
                        $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">'
                            . '<thead style="background-color:#f0f0f0;">'
                            . '<tr>'
                            . '<th align="left">Time</th>'
                            . '<th align="left">Subject</th>'
                            . '<th align="left">Section</th>'
                            . '<th align="left">Faculty</th>'
                            . '<th align="left">Room</th>'
                            . '<th align="left">Building</th>'
                            . '<th align="left">Department</th>'
                            . '<th align="left">AY/Sem</th>'
                            . '</tr></thead><tbody>';
                    }

                    $time = htmlspecialchars(($r['start_time'] ?? '') . ' - ' . ($r['end_time'] ?? ''));
                    $subject = htmlspecialchars(($r['subject_code'] ?? '') . ' - ' . ($r['subject_name'] ?? ''));
                    $section = htmlspecialchars($r['section'] ?? '');
                    $faculty = htmlspecialchars($r['faculty_name'] ?? '');
                    $room = htmlspecialchars($r['room_number'] ?? '');
                    $building = htmlspecialchars($r['building'] ?? '');
                    $dept = htmlspecialchars($r['department'] ?? '');
                    $aysem = htmlspecialchars(($r['academic_year'] ?? '') . ' / ' . ($r['semester'] ?? ''));

                    $html .= '<tr>'
                        . '<td>' . $time . '</td>'
                        . '<td>' . $subject . '</td>'
                        . '<td>' . $section . '</td>'
                        . '<td>' . $faculty . '</td>'
                        . '<td>' . $room . '</td>'
                        . '<td>' . $building . '</td>'
                        . '<td>' . $dept . '</td>'
                        . '<td>' . $aysem . '</td>'
                        . '</tr>';
                }
                $html .= '</tbody></table>';
            }

            $pdf->writeHTML($html, true, false, true, false, '');
            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');

            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.pdf'
            ];

        } catch (Exception $e) {
            $this->log("Error generating timetable PDF: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // ===========================================
    // DASHBOARD METRICS
    // ===========================================
    
    /**
     * Get key metrics for admin dashboard
     * 
     * @return array Dashboard metrics
     */
    public function getDashboardMetrics() {
        try {
            // Quick stats for dashboard cards
            $metrics = $this->db->fetchRow("
                SELECT 
                    (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_registrations,
                    (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
                    (SELECT COUNT(*) FROM timetables WHERE is_active = 1) as active_schedules,
                    (SELECT COUNT(*) FROM notifications WHERE is_active = 1 AND is_read = 0) as unread_notifications,
                    (SELECT COUNT(*) FROM backup_logs WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_backups,
                    (SELECT COUNT(*) FROM audit_logs WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as daily_activities
            ");
            
            // Recent activities for dashboard feed - filter out detailed settings logs
            $recentActivities = $this->db->fetchAll("
                SELECT 
                    al.action,
                    al.description,
                    al.timestamp,
                    u.username,
                    CASE 
                        WHEN u.role = 'admin' THEN CONCAT(ap.first_name, ' ', ap.last_name)
                        WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                        WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                        ELSE u.username
                    END as user_full_name,
                    u.role
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                LEFT JOIN admin_profiles ap ON u.user_id = ap.user_id AND u.role = 'admin'
                LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
                LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
                WHERE al.action NOT IN ('UPDATE_SETTING') 
                AND al.description NOT LIKE '%{%'
                AND al.description NOT LIKE '%BULK_UPDATE_SETTINGS%'
                ORDER BY al.timestamp DESC
                LIMIT 10
            ");
            
            // System health indicators
            $systemHealth = $this->db->fetchRow("
                SELECT 
                    (SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as active_users_week,
                    (SELECT COUNT(*) FROM login_attempts WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as failed_logins_24h,
                    (SELECT COUNT(*) FROM notifications WHERE expires_at < NOW() AND is_active = 1) as expired_notifications
            ");
            
            return [
                'quick_metrics' => $metrics,
                'recent_activities' => $recentActivities,
                'system_health' => $systemHealth
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating dashboard metrics: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Get chart data for dashboard visualizations
     * 
     * @return array Chart data
     */
    public function getChartData() {
        try {
            // User registration trends (last 6 months)
            $registrationTrends = $this->db->fetchAll("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    DATE_FORMAT(created_at, '%M %Y') as month_name,
                    COUNT(*) as total,
                    COUNT(CASE WHEN role = 'student' THEN 1 END) as students,
                    COUNT(CASE WHEN role = 'faculty' THEN 1 END) as faculty
                FROM users
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
            ");
            
            // Department distribution (count active users per department via users table)
            $departmentDistribution = $this->db->fetchAll("
                SELECT 
                    f.department as department,
                    COUNT(*) as user_count,
                    'faculty' as user_type
                FROM faculty f
                INNER JOIN users u ON u.user_id = f.user_id AND u.role = 'faculty' AND u.status = 'active'
                GROUP BY f.department
                
                UNION ALL
                
                SELECT 
                    s.department as department,
                    COUNT(*) as user_count,
                    'student' as user_type
                FROM students s
                INNER JOIN users u ON u.user_id = s.user_id AND u.role = 'student' AND u.status = 'active'
                GROUP BY s.department
                
                ORDER BY department, user_type
            ");
            
            // Daily activity levels (last 14 days)
            $dailyActivity = $this->db->fetchAll("
                SELECT 
                    DATE(timestamp) as date,
                    COUNT(*) as activity_count,
                    COUNT(DISTINCT user_id) as unique_users
                FROM audit_logs
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                GROUP BY DATE(timestamp)
                ORDER BY date DESC
            ");
            
            // Resource utilization by type
            $resourceUtilization = $this->db->fetchRow("
                SELECT 
                    (SELECT COUNT(*) FROM classrooms WHERE is_active = 1) as total_classrooms,
                    (SELECT COUNT(DISTINCT classroom_id) FROM timetables WHERE is_active = 1) as utilized_classrooms,
                    (SELECT COUNT(*) FROM users WHERE role = 'faculty' AND status = 'active') as total_faculty,
                    (SELECT COUNT(DISTINCT faculty_id) FROM timetables WHERE is_active = 1) as active_faculty,
                    (SELECT COUNT(*) FROM subjects WHERE is_active = 1) as total_subjects,
                    (SELECT COUNT(DISTINCT subject_id) FROM timetables WHERE is_active = 1) as scheduled_subjects
            ");
            
            return [
                'registration_trends' => $registrationTrends,
                'department_distribution' => $departmentDistribution,
                'daily_activity' => $dailyActivity,
                'resource_utilization' => $resourceUtilization
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating chart data: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    // ===========================================
    // UTILITY METHODS
    // ===========================================
    
    /**
     * Get available report types and their descriptions
     * 
     * @return array Available report types
     */
    public function getAvailableReportTypes() {
        return [
            'users' => [
                'name' => 'User Reports',
                'description' => 'User accounts, roles, and registration analytics',
                'icon' => 'fas fa-users',
                'color' => 'primary'
            ],
            'timetables' => [
                'name' => 'Timetable Reports',
                'description' => 'Schedule data, conflicts, and timetable analytics',
                'icon' => 'fas fa-calendar',
                'color' => 'success'
            ],
            'resources' => [
                'name' => 'Resource Utilization',
                'description' => 'Classroom, faculty, and subject utilization statistics',
                'icon' => 'fas fa-chart-bar',
                'color' => 'warning'
            ],
            'activity' => [
                'name' => 'System Activity',
                'description' => 'User activities, audit logs, and system usage',
                'icon' => 'fas fa-clipboard-list',
                'color' => 'info'
            ],
            'analytics' => [
                'name' => 'Advanced Analytics',
                'description' => 'Comprehensive system analytics and insights',
                'icon' => 'fas fa-chart-line',
                'color' => 'danger'
            ]
        ];
    }
    
    /**
     * Get available departments for filtering
     * 
     * @return array Department list
     */
    public function getAvailableDepartments() {
        try {
            return $this->db->fetchAll("
                SELECT DISTINCT department_name as name, department_code as code
                FROM departments 
                WHERE is_active = 1
                ORDER BY department_name
            ");
        } catch (Exception $e) {
            // Fallback to user departments if departments table is empty
            return $this->db->fetchAll("
                SELECT DISTINCT department as name, department as code
                FROM (
                    SELECT department FROM faculty WHERE department IS NOT NULL AND is_active = 1
                    UNION 
                    SELECT department FROM students WHERE department IS NOT NULL AND is_active = 1
                    UNION
                    SELECT department FROM admin_profiles WHERE department IS NOT NULL
                ) as all_departments 
                ORDER BY department
            ");
        }
    }
    
    /**
     * Get available academic years for filtering
     * 
     * @return array Academic year list
     */
    public function getAvailableAcademicYears() {
        try {
            $years = $this->db->fetchAll("
                SELECT DISTINCT academic_year 
                FROM timetables 
                WHERE academic_year IS NOT NULL
                ORDER BY academic_year DESC
            ");
            
            // If no years in timetables, provide current and next year
            if (empty($years)) {
                $currentYear = date('Y');
                $nextYear = $currentYear + 1;
                return [
                    ['academic_year' => $currentYear . '-' . substr($nextYear, 2)],
                    ['academic_year' => $nextYear . '-' . substr($nextYear + 1, 2)]
                ];
            }
            
            return $years;
            
        } catch (Exception $e) {
            $this->log("Error getting academic years: " . $e->getMessage(), 'error');
            return [];
        }
    }
    
    /**
     * Clean up old report files
     * 
     * @param int $daysOld Number of days to keep files
     * @return array Cleanup result
     */
    public function cleanupOldReports($daysOld = 7) {
        try {
            // ExportService::cleanupOldFiles returns an integer count, normalize to array
            $deleted = $this->exportService->cleanupOldFiles($daysOld);
            return [
                'success' => true,
                'deleted' => (int)$deleted,
                'message' => "Cleaned up {$deleted} file(s) older than {$daysOld} day(s)."
            ];
        } catch (Exception $e) {
            $this->log("Error cleaning up old reports: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate report filters
     * 
     * @param array $filters Filters to validate
     * @return array Validation result
     */
    public function validateReportFilters($filters) {
        $errors = [];
        
        // Validate date range
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $dateFrom = DateTime::createFromFormat('Y-m-d', $filters['date_from']);
            $dateTo = DateTime::createFromFormat('Y-m-d', $filters['date_to']);
            
            if (!$dateFrom || !$dateTo) {
                $errors[] = 'Invalid date format. Please use YYYY-MM-DD format.';
            } elseif ($dateFrom > $dateTo) {
                $errors[] = 'Start date cannot be after end date.';
            } elseif ($dateTo > new DateTime()) {
                $errors[] = 'End date cannot be in the future.';
            }
        }
        
        // Validate role
        if (!empty($filters['role'])) {
            $validRoles = ['admin', 'faculty', 'student'];
            if (!in_array($filters['role'], $validRoles)) {
                $errors[] = 'Invalid role specified.';
            }
        }
        
        // Validate status
        if (!empty($filters['status'])) {
            $validStatuses = ['pending', 'active', 'inactive', 'rejected'];
            if (!in_array($filters['status'], $validStatuses)) {
                $errors[] = 'Invalid status specified.';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
?>