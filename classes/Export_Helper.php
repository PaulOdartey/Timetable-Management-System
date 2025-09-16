<?php
/**
 * Universal Export Service Class
 * Handles exports for all user roles: Admin, Faculty, Students
 */

if (!defined('SYSTEM_ACCESS')) {
    require_once __DIR__ . '/../config/config.php';
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExportService {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        try {
            $this->logger = function_exists('getLogger') ? getLogger() : null;
        } catch (Exception $e) {
            $this->logger = null;
        }
        
        if (!file_exists(EXPORTS_PATH)) {
            mkdir(EXPORTS_PATH, 0755, true);
        }
    }

    /**
     * Generate Enrollment History PDF (styled like Class Schedule)
     */
    private function generateEnrollmentHistoryPDFStyled($student, array $enrollments, string $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';

            $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            // Doc info
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('University Administration');
            $pdf->SetTitle('Student Enrollment Records');
            $pdf->SetSubject('Enrollment Records');

            // Layout
            $pdf->SetMargins(10, 20, 10);
            $pdf->SetHeaderMargin(8);
            $pdf->SetFooterMargin(12);
            $pdf->SetAutoPageBreak(TRUE, 20);
            // Explicitly force landscape regardless of defaults
            $pdf->setPageOrientation('L', true, 0);
            $pdf->AddPage('L');

            $margins = $pdf->getMargins();
            $usableWidth = $pdf->getPageWidth() - ($margins['left'] + $margins['right']);

            // Title
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 10, 'ENROLLMENT RECORDS', 0, 1, 'C');
            $pdf->Ln(5);

            // Student info box
            $pdf->SetFillColor(240, 248, 255);
            $pdf->SetDrawColor(70, 130, 180);
            $pdf->SetLineWidth(0.5);
            $pdf->Rect($margins['left'], $pdf->GetY(), $usableWidth, 35, 'DF');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 8, 'STUDENT INFORMATION', 0, 1, 'C');

            $pdf->SetFont('helvetica', '', 11);
            $pdf->SetTextColor(0, 0, 0);
            $leftX = $margins['left'] + 10;
            $rightX = $leftX + ($usableWidth / 2);
            $y = $pdf->GetY();
            $pdf->SetXY($leftX, $y);
            $pdf->Cell(90, 6, 'Name: ' . trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(90, 6, 'Student ID: ' . ($student['student_number'] ?? 'N/A'), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(90, 6, 'Year Level: ' . ($student['year_level'] ?? 'N/A'), 0, 1);

            $pdf->SetXY($rightX, $y);
            $pdf->Cell(90, 6, 'Email: ' . ($student['email'] ?? 'N/A'), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(90, 6, 'Academic Year: ' . date('Y') . '-' . (date('Y') + 1), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(90, 6, 'Generated: ' . date('M j, Y g:i A'), 0, 1);

            $pdf->Ln(12);

            // Table header
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(41, 128, 185);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetDrawColor(52, 73, 94);
            $pdf->SetLineWidth(0.5);

            // Column ratios sum to 1.00
            $colRatios = [
                0.08, // CODE
                0.26, // SUBJECT
                0.05, // CR
                0.08, // YEAR
                0.06, // SEM
                0.05, // SEC
                0.09, // STATUS
                0.13, // ENROLLED
                0.20  // FACULTY
            ];
            $colWidths = array_map(function($r) use ($usableWidth) { return round($usableWidth * $r, 2); }, $colRatios);
            $headers = ['CODE','SUBJECT','CR','YEAR','SEM','SEC','STATUS','ENROLLED ON','FACULTY'];
            foreach ($headers as $i => $h) {
                $pdf->Cell($colWidths[$i], 11, $h, 1, $i === count($headers)-1 ? 1 : 0, 'C', true);
            }

            // Rows with wrapping
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(44, 62, 80);
            $pdf->setCellPaddings(2, 2, 2, 2);
            $pdf->setCellHeightRatio(1.15);
            $rowIndex = 0;
            foreach ($enrollments as $enr) {
                $fill = ($rowIndex % 2 == 0) ? [250, 251, 252] : [255, 255, 255];
                $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);

                $code = (string)($enr['subject_code'] ?? '');
                $name = (string)($enr['subject_name'] ?? '');
                $cr   = (string)($enr['credits'] ?? '');
                $year = (string)($enr['academic_year'] ?? '');
                $sem  = (string)($enr['semester'] ?? '');
                $sec  = (string)($enr['section'] ?? '');
                $status = strtoupper(substr((string)($enr['status'] ?? ''), 0, 12));
                $enrolledOn = $enr['enrollment_date'] ? date('M j, Y', strtotime($enr['enrollment_date'])) : '-';
                $faculty = trim(($enr['faculty_first'] ?? '') . ' ' . ($enr['faculty_last'] ?? ''));
                if ($faculty === '') { $faculty = 'Not Assigned'; }

                // Measure variable-height columns
                $hSubject = $pdf->getStringHeight($colWidths[1], $name, false, true, '', 1);
                $hFaculty = $pdf->getStringHeight($colWidths[8], $faculty, false, true, '', 1);
                $rowHeight = max(9, $hSubject, $hFaculty);

                $x = $pdf->GetX();
                $y = $pdf->GetY();

                $pdf->MultiCell($colWidths[0], $rowHeight, $code, 1, 'C', true, 0, $x, $y, true); $x += $colWidths[0];
                $pdf->MultiCell($colWidths[1], $rowHeight, $name, 1, 'L', true, 0, $x, $y, true); $x += $colWidths[1];
                $pdf->MultiCell($colWidths[2], $rowHeight, $cr, 1, 'C', true, 0, $x, $y, true); $x += $colWidths[2];
                $pdf->MultiCell($colWidths[3], $rowHeight, $year, 1, 'C', true, 0, $x, $y, true); $x += $colWidths[3];
                $pdf->MultiCell($colWidths[4], $rowHeight, $sem, 1, 'C', true, 0, $x, $y, true); $x += $colWidths[4];
                $pdf->MultiCell($colWidths[5], $rowHeight, $sec, 1, 'C', true, 0, $x, $y, true); $x += $colWidths[5];
                $pdf->MultiCell($colWidths[6], $rowHeight, $status, 1, 'C', true, 0, $x, $y, true); $x += $colWidths[6];
                $pdf->MultiCell($colWidths[7], $rowHeight, $enrolledOn, 1, 'C', true, 0, $x, $y, true); $x += $colWidths[7];
                $pdf->MultiCell($colWidths[8], $rowHeight, $faculty, 1, 'L', true, 1, $x, $y, true);

                $rowIndex++;
            }

            // Footer
            $pdf->SetY(-20);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(128, 128, 128);
            $pdf->Cell(0, 5, 'Generated by University Timetable Management System', 0, 1, 'C');
            $pdf->Cell(0, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');

            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');
            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'file_path' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.pdf'
            ];
        } catch (Exception $e) {
            $this->log("Error generating styled enrollment PDF: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function log($message, $level = 'info') {
        if ($this->logger) {
            $this->logger->$level($message);
        } else {
            error_log("[ExportService] [{$level}] {$message}");
        }
    }

    /**
     * Generate a simple statistics report (key/value) in Excel or PDF
     */
    private function generateStatsReport(array $stats, string $filename, string $format = 'excel') {
        try {
            if ($format === 'pdf') {
                // PDF output using TCPDF (Landscape + Title + Generated timestamp)
                require_once __DIR__ . '/../vendor/autoload.php';
                $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $pdf->SetCreator('Timetable Management System');
                $pdf->SetAuthor('System');
                $pdf->SetTitle('System Statistics');
                $pdf->setPageOrientation('L', true, 0);
                $pdf->AddPage('L');
                $html = '<h2 style="text-align:center; color:#2F5597; margin:0;">System Statistics</h2>';
                $html .= '<p style="text-align:center; margin:4px 0 12px 0; color:#606060;">Generated on: ' . date('F j, Y g:i A') . '</p>';
                // Use colgroup to enforce column widths (TCPDF supports it better than width on th)
                $html .= '<style>
                    table.sys-stats { width:100%; border-collapse: collapse; font-family: helvetica; }
                    .sys-stats th, .sys-stats td { border:1px solid #444; padding:6px 8px; font-size:10px; }
                    .sys-stats thead th { background:#f0f0f0; font-weight:bold; text-align:center; }
                    .sys-stats td.metric { text-align:left; }
                    .sys-stats td.value { text-align:right; }
                </style>';
                $html .= '<table class="sys-stats">'
                      . '<colgroup>'
                      . '<col style="width:45%" />'
                      . '<col style="width:55%" />'
                      . '</colgroup>'
                      . '<thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
                foreach ($stats as $k => $v) {
                    $k2 = ucwords(str_replace('_', ' ', (string)$k));
                    $html .= '<tr><td class="metric">' . htmlspecialchars((string)$k2) . '</td><td class="value">' . htmlspecialchars((string)$v) . '</td></tr>';
                }
                $html .= '</tbody></table>';
                $pdf->writeHTML($html, true, false, true, false, '');
                $filepath = EXPORTS_PATH . $filename . '.pdf';
                $pdf->Output($filepath, 'F');
                return [
                    'success' => true,
                    'filename' => $filename . '.pdf',
                    'filepath' => $filepath,
                    'download_url' => EXPORTS_URL . $filename . '.pdf'
                ];
            }
            // Excel output
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('System Stats');
            $sheet->fromArray([['Metric', 'Value']], null, 'A1');
            $sheet->getStyle('A1:B1')->getFont()->setBold(true);
            $row = 2;
            foreach ($stats as $label => $value) {
                $sheet->setCellValue('A' . $row, (string)$label);
                $sheet->setCellValue('B' . $row, (string)$value);
                $row++;
            }
            foreach (['A','B'] as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];
        } catch (Exception $e) {
            $this->log('Error generating stats report: ' . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Export a filtered users dataset (array of associative rows) to Excel/PDF
     */
    public function exportFilteredUsers(array $users, string $format = 'excel', array $filters = []) {
        try {
            $filename = 'filtered_users_' . date('Y_m_d_H_i_s');
            // Derive headers from first row
            $headers = [];
            if (!empty($users)) {
                $headers = array_keys((array)$users[0]);
            } else {
                // Provide a minimal header set if no data
                $headers = ['user_id','username','full_name','email','role','status','department','created_at'];
            }

            if ($format === 'pdf') {
                require_once __DIR__ . '/../vendor/autoload.php';
                $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $pdf->SetCreator('Timetable Management System');
                $pdf->SetAuthor('Admin');
                $pdf->SetTitle('Filtered Users');
                $pdf->setPageOrientation('L', true, 0);
                $pdf->AddPage('L');
                $html = '<h2 style="text-align:center; color:#2F5597; margin:0;">Filtered Users</h2>';
                $html .= '<p style="text-align:center; margin:4px 0 12px 0; color:#606060;">Generated on: ' . date('F j, Y g:i A') . '</p>';
                $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">';
                $html .= '<thead style="background-color:#f0f0f0;"><tr>';
                foreach ($headers as $h) {
                    $html .= '<th>' . htmlspecialchars((string)$h) . '</th>';
                }
                $html .= '</tr></thead><tbody>';
                foreach ($users as $row) {
                    $html .= '<tr>';
                    foreach ($headers as $h) {
                        $val = isset($row[$h]) ? $row[$h] : '';
                        $html .= '<td>' . htmlspecialchars((string)$val) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
                $pdf->writeHTML($html, true, false, true, false, '');
                $filepath = EXPORTS_PATH . $filename . '.pdf';
                $pdf->Output($filepath, 'F');
                return [
                    'success' => true,
                    'filename' => $filename . '.pdf',
                    'filepath' => $filepath,
                    'download_url' => EXPORTS_URL . $filename . '.pdf'
                ];
            }

            // Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Filtered Users');
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')->getFont()->setBold(true);
            $rowIndex = 2;
            foreach ($users as $row) {
                $colIndex = 1;
                foreach ($headers as $h) {
                    $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, isset($row[$h]) ? $row[$h] : '');
                    $colIndex++;
                }
                $rowIndex++;
            }
            for ($i = 1; $i <= count($headers); $i++) { $sheet->getColumnDimensionByColumn($i)->setAutoSize(true); }
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];
        } catch (Exception $e) {
            $this->log('Error exporting filtered users: ' . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Export department analysis dataset
     */
    public function exportDepartmentAnalysis(array $departmentData, string $format = 'excel') {
        try {
            $filename = 'department_analysis_' . date('Y_m_d_H_i_s');
            // Determine raw keys present in the dataset (fallback to a canonical set)
            $rawKeys = [];
            if (!empty($departmentData)) {
                $rawKeys = array_keys((array)$departmentData[0]);
            } else {
                $rawKeys = ['department','total_users','faculty_count','student_count','active_users','pending_users','recent_additions','activity_rate'];
            }

            if ($format === 'pdf') {
                require_once __DIR__ . '/../vendor/autoload.php';
                $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $pdf->SetCreator('Timetable Management System');
                $pdf->SetAuthor('Admin');
                $pdf->SetTitle('Department Analysis');
                $pdf->setPageOrientation('L', true, 0);
                $pdf->AddPage('L');
                $html = '<h2 style="text-align:center; color:#2F5597; margin:0;">Department Analysis</h2>';
                $html .= '<p style="text-align:center; margin:4px 0 12px 0; color:#606060;">Generated on: ' . date('F j, Y g:i A') . '</p>';
                $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">';
                // Headers
                $headers = ['Department','Total Users','Faculty','Students','Active','Pending','Recent Additions','Activity %'];
                $html .= '<thead style="background-color:#f0f0f0;"><tr>';
                foreach ($headers as $h) { $html .= '<th>' . htmlspecialchars($h) . '</th>'; }
                $html .= '</tr></thead><tbody>';
                foreach ($departmentData as $row) {
                    $html .= '<tr>'
                        . '<td>' . htmlspecialchars((string)($row['department'] ?? '')) . '</td>'
                        . '<td>' . htmlspecialchars((string)($row['total_users'] ?? 0)) . '</td>'
                        . '<td>' . htmlspecialchars((string)($row['faculty_count'] ?? 0)) . '</td>'
                        . '<td>' . htmlspecialchars((string)($row['student_count'] ?? 0)) . '</td>'
                        . '<td>' . htmlspecialchars((string)($row['active_users'] ?? 0)) . '</td>'
                        . '<td>' . htmlspecialchars((string)($row['pending_users'] ?? 0)) . '</td>'
                        . '<td>' . htmlspecialchars((string)($row['recent_additions'] ?? 0)) . '</td>'
                        . '<td>' . htmlspecialchars((string)($row['activity_rate'] ?? 0)) . '</td>'
                    . '</tr>';
                }
                $html .= '</tbody></table>';
                $pdf->writeHTML($html, true, false, true, false, '');
                $filepath = EXPORTS_PATH . $filename . '.pdf';
                $pdf->Output($filepath, 'F');
                return [
                    'success' => true,
                    'filename' => $filename . '.pdf',
                    'filepath' => $filepath,
                    'download_url' => EXPORTS_URL . $filename . '.pdf'
                ];
            }
            // Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Department Analysis');
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:H1')->getFont()->setBold(true);
            $row = 2;
            foreach ($departmentData as $row) {
                $sheet->setCellValue('A'.$row, $row['department'] ?? '');
                $sheet->setCellValue('B'.$row, $row['total_users'] ?? 0);
                $sheet->setCellValue('C'.$row, $row['faculty_count'] ?? 0);
                $sheet->setCellValue('D'.$row, $row['student_count'] ?? 0);
                $sheet->setCellValue('E'.$row, $row['active_users'] ?? 0);
                $sheet->setCellValue('F'.$row, $row['pending_users'] ?? 0);
                $sheet->setCellValue('G'.$row, $row['recent_additions'] ?? 0);
                $sheet->setCellValue('H'.$row, $row['activity_rate'] ?? 0);
                $row++;
            }
            for ($i = 1; $i <= 8; $i++) { $sheet->getColumnDimensionByColumn($i)->setAutoSize(true); }
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];
        } catch (Exception $e) {
            $this->log('Error exporting department analysis: ' . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Export registration trends dataset
     */
    public function exportRegistrationTrends(array $trends, string $format = 'excel') {
        try {
            $filename = 'registration_trends_' . date('Y_m_d_H_i_s');
            $headers = [];
            if (!empty($trends)) {
                $headers = array_keys((array)$trends[0]);
            } else {
                $headers = ['month','total_registrations','faculty_registrations','student_registrations','approved_count'];
            }

            if ($format === 'pdf') {
                require_once __DIR__ . '/../vendor/autoload.php';
                $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $pdf->SetCreator('Timetable Management System');
                $pdf->SetAuthor('Admin');
                $pdf->SetTitle('Registration Trends');
                $pdf->setPageOrientation('L', true, 0);
                $pdf->AddPage('L');
                $html = '<h2 style="text-align:center; color:#2F5597; margin:0;">Registration Trends</h2>';
                $html .= '<p style="text-align:center; margin:4px 0 12px 0; color:#606060;">Generated on: ' . date('F j, Y g:i A') . '</p>';
                $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">';
                $html .= '<thead style="background-color:#f0f0f0;"><tr>';
                foreach ($headers as $h) { $html .= '<th>' . htmlspecialchars((string)$h) . '</th>'; }
                $html .= '</tr></thead><tbody>';
                foreach ($trends as $row) {
                    $html .= '<tr>';
                    foreach ($headers as $h) {
                        $val = isset($row[$h]) ? $row[$h] : '';
                        $html .= '<td>' . htmlspecialchars((string)$val) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
                $pdf->writeHTML($html, true, false, true, false, '');
                $filepath = EXPORTS_PATH . $filename . '.pdf';
                $pdf->Output($filepath, 'F');
                return [
                    'success' => true,
                    'filename' => $filename . '.pdf',
                    'filepath' => $filepath,
                    'download_url' => EXPORTS_URL . $filename . '.pdf'
                ];
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Registration Trends');
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:' . chr(ord('A') + count($headers) - 1) . '1')->getFont()->setBold(true);
            $row = 2;
            foreach ($trends as $row) {
                $col = 1;
                foreach ($headers as $h) {
                    $sheet->setCellValueByColumnAndRow($col, $row, isset($row[$h]) ? $row[$h] : '');
                    $col++;
                }
                $row++;
            }
            for ($i = 1; $i <= count($headers); $i++) { $sheet->getColumnDimensionByColumn($i)->setAutoSize(true); }
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];
        } catch (Exception $e) {
            $this->log('Error exporting registration trends: ' . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ===========================================
    // ADMIN EXPORTS
    // ===========================================
    
    /**
     * Export all users list
     */
    public function exportAllUsers($format = 'excel') {
        try {
            $users = $this->db->fetchAll("
                SELECT u.user_id, u.username, u.email, u.role, u.status, 
                       u.created_at, u.last_login,
                       CASE 
                           WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                           WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                           ELSE u.username
                       END as full_name
                FROM users u
                LEFT JOIN faculty f ON u.user_id = f.user_id
                LEFT JOIN students s ON u.user_id = s.user_id
                ORDER BY u.role, u.username
            ");
            
            $filename = 'all_users_' . date('Y_m_d_H_i_s');
            
            if ($format === 'pdf') {
                return $this->generateUsersPDF($users, $filename);
            } else {
                return $this->generateUsersExcel($users, $filename);
            }
            
        } catch (Exception $e) {
            $this->log("Error exporting all users: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Export system statistics
     */
    public function exportSystemStats($format = 'excel') {
        try {
            $stats = [
                'total_users' => $this->db->fetchColumn("SELECT COUNT(*) FROM users"),
                'total_faculty' => $this->db->fetchColumn("SELECT COUNT(*) FROM faculty"),
                'total_students' => $this->db->fetchColumn("SELECT COUNT(*) FROM students"),
                'total_subjects' => $this->db->fetchColumn("SELECT COUNT(*) FROM subjects"),
                'active_timetables' => $this->db->fetchColumn("SELECT COUNT(*) FROM timetables WHERE is_active = 1"),
                'total_enrollments' => $this->db->fetchColumn("SELECT COUNT(*) FROM enrollments WHERE status = 'enrolled'")
            ];
            
            $filename = 'system_stats_' . date('Y_m_d_H_i_s');
            return $this->generateStatsReport($stats, $filename, $format);
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Export timetables data (admin) with professional formatting
     * @param array $rows Flat timetables dataset from Report::generateCustomReport
     * @param string $format 'excel'|'pdf'
     * @param string|null $filename Optional base filename
     */
    public function exportTimetables(array $rows, string $format = 'excel', ?string $filename = null) {
        try {
            $filename = $filename ?: ('timetables_report_' . date('Y_m_d_H_i_s'));
            if ($format === 'pdf') {
                return $this->generateTimetablesPDF($rows, $filename);
            }
            return $this->generateTimetablesExcel($rows, $filename);
        } catch (Exception $e) {
            $this->log('Error exporting timetables: ' . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Build Timetables Excel */
    private function generateTimetablesExcel(array $rows, string $filename) {
        // Define ordered headers mapping
        $ordered = [
            'academic_year' => 'Academic Year',
            'semester' => 'Semester',
            'day' => 'Day',
            'start_time' => 'Start Time',
            'end_time' => 'End Time',
            'slot_name' => 'Slot',
            'subject_code' => 'Subject Code',
            'subject_name' => 'Subject Name',
            'section' => 'Section',
            'faculty_name' => 'Faculty',
            'room_number' => 'Room',
            'building' => 'Building',
            'department' => 'Department',
        ];

        // Normalize rows to ordered keys and friendly values
        $data = [];
        foreach ($rows as $r) {
            $day = isset($r['day_of_week']) ? $this->mapDayName($r['day_of_week']) : ($r['day'] ?? '');
            $data[] = [
                'academic_year' => $r['academic_year'] ?? '',
                'semester' => $r['semester'] ?? '',
                'day' => $day,
                'start_time' => isset($r['start_time']) ? date('g:i A', strtotime($r['start_time'])) : '',
                'end_time' => isset($r['end_time']) ? date('g:i A', strtotime($r['end_time'])) : '',
                'slot_name' => $r['slot_name'] ?? '',
                'subject_code' => $r['subject_code'] ?? '',
                'subject_name' => $r['subject_name'] ?? '',
                'section' => $r['section'] ?? '',
                'faculty_name' => $r['faculty_name'] ?? ($r['faculty_full_name'] ?? ''),
                'room_number' => $r['room_number'] ?? '',
                'building' => $r['building'] ?? '',
                'department' => $r['department'] ?? '',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Timetables');

        // Title
        $sheet->setCellValue('A1', 'Timetables Report');
        $sheet->setCellValue('A2', 'Generated: ' . date('F j, Y g:i A'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // Headers
        $headers = array_values($ordered);
        $sheet->fromArray($headers, null, 'A4');
        $lastColIndex = count($headers);
        $lastColLetter = chr(ord('A') + $lastColIndex - 1);
        $sheet->getStyle('A4:' . $lastColLetter . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Data
        $rowIdx = 5;
        foreach ($data as $row) {
            $colIdx = 1;
            foreach ($ordered as $key => $label) {
                $sheet->setCellValueByColumnAndRow($colIdx, $rowIdx, $row[$key] ?? '');
                $colIdx++;
            }
            $rowIdx++;
        }

        // Freeze header, autosize cols
        $sheet->freezePane('A5');
        for ($i = 1; $i <= $lastColIndex; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        $filepath = EXPORTS_PATH . $filename . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        return [
            'success' => true,
            'filename' => $filename . '.xlsx',
            'filepath' => $filepath,
            'download_url' => EXPORTS_URL . $filename . '.xlsx'
        ];
    }

    /**
     * Export Resource Utilization (Classrooms, Faculty, Subjects) in PDF (landscape) or Excel
     */
    public function exportResourceUtilization(string $format = 'pdf') {
        try {
            // Collect summaries
            $classrooms = $this->getClassroomUtilizationSummary();
            $faculty    = $this->getFacultyUtilizationSummary();
            $subjects   = $this->getSubjectUtilizationSummary();

            $data = [
                'classrooms' => $classrooms,
                'faculty'    => $faculty,
                'subjects'   => $subjects,
            ];

            // Use a conventional filename prefix
            $filename = 'resource_utilization_report_' . date('Y_m_d_H_i_s');
            if (strtolower($format) === 'pdf') {
                return $this->generateResourceUtilizationPDF($data, $filename);
            }
            return $this->generateResourceUtilizationExcel($data, $filename);
        } catch (Exception $e) {
            $this->log('Error exporting resource utilization: ' . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Classroom utilization summary (compact)
     */
    private function getClassroomUtilizationSummary(): array {
        $sql = "
            SELECT 
                c.building,
                c.room_number,
                c.capacity,
                COUNT(t.timetable_id) AS sessions,
                ROUND(COUNT(t.timetable_id) * 100.0 / 40, 2) AS utilization_rate
            FROM classrooms c
            LEFT JOIN timetables t ON t.classroom_id = c.classroom_id AND t.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.classroom_id
            ORDER BY utilization_rate DESC, c.building, c.room_number
        ";
        return $this->db->fetchAll($sql) ?: [];
    }

    /**
     * Faculty utilization summary
     */
    private function getFacultyUtilizationSummary(): array {
        $sql = "
            SELECT 
                CONCAT(f.first_name, ' ', f.last_name) AS faculty_name,
                d.department_name AS department,
                COUNT(t.timetable_id) AS classes_assigned,
                COUNT(DISTINCT t.subject_id) AS unique_subjects,
                ROUND(COUNT(t.timetable_id) * 100.0 / 40, 2) AS load_utilization
            FROM faculty f
            LEFT JOIN users u ON f.user_id = u.user_id
            LEFT JOIN timetables t ON t.faculty_id = f.faculty_id AND t.is_active = 1
            LEFT JOIN departments d ON f.department_id = d.department_id
            WHERE f.is_active = 1 OR f.is_active IS NULL
            GROUP BY f.faculty_id
            ORDER BY load_utilization DESC, faculty_name
        ";
        return $this->db->fetchAll($sql) ?: [];
    }

    /**
     * Subject utilization summary (occupancy vs capacity)
     */
    private function getSubjectUtilizationSummary(): array {
        $sql = "
            SELECT 
                s.subject_code,
                s.subject_name,
                d.department_name AS department,
                COUNT(t.timetable_id) AS classes_scheduled,
                ROUND(AVG(c.capacity), 0) AS avg_room_capacity,
                ROUND(AVG(occ.enrolled_count), 0) AS avg_enrollment,
                ROUND(
                    CASE WHEN AVG(c.capacity) IS NULL OR AVG(c.capacity) = 0 THEN 0
                         ELSE AVG(occ.enrolled_count) / AVG(c.capacity) * 100 END
                , 2) AS occupancy_rate
            FROM subjects s
            LEFT JOIN departments d ON s.department_id = d.department_id
            LEFT JOIN timetables t ON t.subject_id = s.subject_id AND t.is_active = 1
            LEFT JOIN classrooms c ON t.classroom_id = c.classroom_id
            LEFT JOIN (
                SELECT 
                    t2.subject_id,
                    t2.section,
                    t2.semester,
                    t2.academic_year,
                    COUNT(e.enrollment_id) AS enrolled_count
                FROM timetables t2
                LEFT JOIN enrollments e ON t2.subject_id = e.subject_id 
                    AND t2.section = e.section 
                    AND t2.semester = e.semester 
                    AND t2.academic_year = e.academic_year
                    AND e.status = 'enrolled'
                GROUP BY t2.subject_id, t2.section, t2.semester, t2.academic_year
            ) occ ON occ.subject_id = s.subject_id
            WHERE s.is_active = 1 OR s.is_active IS NULL
            GROUP BY s.subject_id
            ORDER BY occupancy_rate DESC, s.subject_code
        ";
        return $this->db->fetchAll($sql) ?: [];
    }

    /** Generate Resource Utilization PDF (landscape) */
    private function generateResourceUtilizationPDF(array $data, string $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            // Force landscape on A4 explicitly
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

            // Meta
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('Admin');
            $pdf->SetTitle('Resource Utilization');
            // Slightly tighter top margin to fit more width/height
            $pdf->SetMargins(10, 15, 10);
            $pdf->SetHeaderMargin(8);
            $pdf->SetFooterMargin(12);
            $pdf->SetAutoPageBreak(TRUE, 20);
            // Re-assert orientation for the first page before adding it
            $pdf->setPageOrientation('L', true, 0);
            $pdf->AddPage('L');

            // Title and timestamp
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 12, 'RESOURCE UTILIZATION', 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(96, 96, 96);
            $pdf->Cell(0, 8, 'Generated on: ' . date('F j, Y g:i A'), 0, 1, 'C');
            $pdf->Ln(2);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 10);

            // Styles (neutral headers)
            $style = '<style>
                table { border-collapse: collapse; width: 100%; font-family: helvetica; }
                th { color: #000; font-weight: bold; padding: 6px; text-align: center; border: 1px solid #999; font-size: 10px; }
                td { padding: 6px; border: 1px solid #999; text-align: left; font-size: 10px; }
                .center { text-align: center; }
                .section { font-weight: bold; font-size: 12px; margin: 10px 0 6px 0; color: #1E3A8A; }
            </style>';

            // Section: Classrooms
            $html = $style;
            $html .= '<div class="section">Classroom Utilization</div>';
            $html .= '<table><thead><tr>
                        <th>Building</th>
                        <th>Room</th>
                        <th class="center">Capacity</th>
                        <th class="center">Sessions</th>
                        <th class="center">Utilization %</th>
                      </tr></thead><tbody>';
            foreach (($data['classrooms'] ?? []) as $r) {
                $html .= '<tr>'
                      . '<td>' . htmlspecialchars((string)($r['building'] ?? '')) . '</td>'
                      . '<td>' . htmlspecialchars((string)($r['room_number'] ?? '')) . '</td>'
                      . '<td class="center">' . htmlspecialchars((string)($r['capacity'] ?? '')) . '</td>'
                      . '<td class="center">' . htmlspecialchars((string)($r['sessions'] ?? '')) . '</td>'
                      . '<td class="center">' . htmlspecialchars((string)($r['utilization_rate'] ?? '')) . '</td>'
                      . '</tr>';
            }
            $html .= '</tbody></table>';

            // Section: Faculty
            $html .= '<div class="section">Faculty Utilization</div>';
            $html .= '<table><thead><tr>
                        <th>Faculty</th>
                        <th>Department</th>
                        <th class="center">Classes</th>
                        <th class="center">Unique Subjects</th>
                        <th class="center">Load %</th>
                      </tr></thead><tbody>';
            foreach (($data['faculty'] ?? []) as $r) {
                $html .= '<tr>'
                      . '<td>' . htmlspecialchars((string)($r['faculty_name'] ?? '')) . '</td>'
                      . '<td>' . htmlspecialchars((string)($r['department'] ?? '')) . '</td>'
                      . '<td class="center">' . htmlspecialchars((string)($r['classes_assigned'] ?? '')) . '</td>'
                      . '<td class="center">' . htmlspecialchars((string)($r['unique_subjects'] ?? '')) . '</td>'
                      . '<td class="center">' . htmlspecialchars((string)($r['load_utilization'] ?? '')) . '</td>'
                      . '</tr>';
            }
            $html .= '</tbody></table>';

            // Section: Subjects
            $html .= '<div class="section">Subject Utilization</div>';
            $html .= '<table><thead><tr>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Department</th>
                        <th class="center">Classes</th>
                        <th class="center">Avg Capacity</th>
                        <th class="center">Avg Enrollment</th>
                        <th class="center">Occupancy %</th>
                      </tr></thead><tbody>';
            foreach (($data['subjects'] ?? []) as $r) {
                $html .= '<tr>'
                      . '<td>' . htmlspecialchars((string)($r['subject_code'] ?? '')) . '</td>'
                      . '<td>' . htmlspecialchars((string)($r['subject_name'] ?? '')) . '</td>'
                      . '<td>' . htmlspecialchars((string)($r['department'] ?? '')) . '</td>'
                      . '<td class="center">' . htmlspecialchars((string)($r['classes_scheduled'] ?? '')) . '</td>'
                      . '<td class="center">' . htmlspecialchars((string)($r['avg_room_capacity'] ?? '')) . '</td>'
                      . '<td class="center">' . htmlspecialchars((string)($r['avg_enrollment'] ?? '')) . '</td>'
                      . '<td class="center">' . htmlspecialchars((string)($r['occupancy_rate'] ?? '')) . '</td>'
                      . '</tr>';
            }
            $html .= '</tbody></table>';

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
            $this->log('Error generating resource utilization PDF: ' . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Generate Resource Utilization Excel (3 sheets) */
    private function generateResourceUtilizationExcel(array $data, string $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            $spreadsheet = new Spreadsheet();

            // Sheet 1: Classrooms
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Classrooms');
            $sheet->setCellValue('A1', 'Resource Utilization - Classrooms');
            $sheet->mergeCells('A1:E1');
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            $sheet->setCellValue('A2', 'Generated on: ' . date('F j, Y g:i A'));
            $sheet->mergeCells('A2:E2');
            $sheet->getStyle('A2')->applyFromArray([
                'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '606060']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            $headers = ['Building','Room','Capacity','Sessions','Utilization %'];
            $sheet->fromArray($headers, null, 'A4');
            $sheet->getStyle('A4:E4')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4682B4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            $row = 5;
            foreach (($data['classrooms'] ?? []) as $r) {
                $sheet->setCellValue('A'.$row, $r['building'] ?? '');
                $sheet->setCellValue('B'.$row, $r['room_number'] ?? '');
                $sheet->setCellValue('C'.$row, $r['capacity'] ?? '');
                $sheet->setCellValue('D'.$row, $r['sessions'] ?? '');
                $sheet->setCellValue('E'.$row, $r['utilization_rate'] ?? '');
                $row++;
            }
            foreach (['A','B','C','D','E'] as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
            $sheet->freezePane('A5');

            // Sheet 2: Faculty
            $facultySheet = $spreadsheet->createSheet();
            $facultySheet->setTitle('Faculty');
            $facultySheet->setCellValue('A1', 'Resource Utilization - Faculty');
            $facultySheet->mergeCells('A1:E1');
            $facultySheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            $facultySheet->setCellValue('A2', 'Generated on: ' . date('F j, Y g:i A'));
            $facultySheet->mergeCells('A2:E2');
            $facultySheet->getStyle('A2')->applyFromArray([
                'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '606060']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            $headers = ['Faculty','Department','Classes','Unique Subjects','Load %'];
            $facultySheet->fromArray($headers, null, 'A4');
            $facultySheet->getStyle('A4:E4')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4682B4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            $row = 5;
            foreach (($data['faculty'] ?? []) as $r) {
                $facultySheet->setCellValue('A'.$row, $r['faculty_name'] ?? '');
                $facultySheet->setCellValue('B'.$row, $r['department'] ?? '');
                $facultySheet->setCellValue('C'.$row, $r['classes_assigned'] ?? '');
                $facultySheet->setCellValue('D'.$row, $r['unique_subjects'] ?? '');
                $facultySheet->setCellValue('E'.$row, $r['load_utilization'] ?? '');
                $row++;
            }
            foreach (['A','B','C','D','E'] as $col) { $facultySheet->getColumnDimension($col)->setAutoSize(true); }
            $facultySheet->freezePane('A5');

            // Sheet 3: Subjects
            $subjectSheet = $spreadsheet->createSheet();
            $subjectSheet->setTitle('Subjects');
            $subjectSheet->setCellValue('A1', 'Resource Utilization - Subjects');
            $subjectSheet->mergeCells('A1:G1');
            $subjectSheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            $subjectSheet->setCellValue('A2', 'Generated on: ' . date('F j, Y g:i A'));
            $subjectSheet->mergeCells('A2:G2');
            $subjectSheet->getStyle('A2')->applyFromArray([
                'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '606060']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            $headers = ['Subject Code','Subject Name','Department','Classes','Avg Capacity','Avg Enrollment','Occupancy %'];
            $subjectSheet->fromArray($headers, null, 'A4');
            $subjectSheet->getStyle('A4:G4')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4682B4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
            $row = 5;
            foreach (($data['subjects'] ?? []) as $r) {
                $subjectSheet->setCellValue('A'.$row, $r['subject_code'] ?? '');
                $subjectSheet->setCellValue('B'.$row, $r['subject_name'] ?? '');
                $subjectSheet->setCellValue('C'.$row, $r['department'] ?? '');
                $subjectSheet->setCellValue('D'.$row, $r['classes_scheduled'] ?? '');
                $subjectSheet->setCellValue('E'.$row, $r['avg_room_capacity'] ?? '');
                $subjectSheet->setCellValue('F'.$row, $r['avg_enrollment'] ?? '');
                $subjectSheet->setCellValue('G'.$row, $r['occupancy_rate'] ?? '');
                $row++;
            }
            foreach (['A','B','C','D','E','F','G'] as $col) { $subjectSheet->getColumnDimension($col)->setAutoSize(true); }
            $subjectSheet->freezePane('A5');

            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];
        } catch (Exception $e) {
            $this->log('Error generating resource utilization Excel: ' . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Build Timetables PDF */
    private function generateTimetablesPDF(array $rows, string $filename) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Timetable Management System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Timetables Report');
        $pdf->AddPage('L');

        $headers = ['Academic Year','Semester','Day','Start Time','End Time','Slot','Subject Code','Subject Name','Section','Faculty','Room','Building','Department'];
        $html = '<h2>Timetables Report</h2><table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th><strong>' . htmlspecialchars((string)$h) . '</strong></th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $day = isset($r['day_of_week']) ? $this->mapDayName($r['day_of_week']) : ($r['day'] ?? '');
            $cells = [
                $r['academic_year'] ?? '',
                $r['semester'] ?? '',
                $day,
                isset($r['start_time']) ? date('g:i A', strtotime($r['start_time'])) : '',
                isset($r['end_time']) ? date('g:i A', strtotime($r['end_time'])) : '',
                $r['slot_name'] ?? '',
                $r['subject_code'] ?? '',
                $r['subject_name'] ?? '',
                $r['section'] ?? '',
                $r['faculty_name'] ?? ($r['faculty_full_name'] ?? ''),
                $r['room_number'] ?? '',
                $r['building'] ?? '',
                $r['department'] ?? '',
            ];
            $html .= '<tr>';
            foreach ($cells as $v) { $html .= '<td>' . htmlspecialchars((string)$v) . '</td>'; }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $filepath = EXPORTS_PATH . $filename . '.pdf';
        $pdf->Output($filepath, 'F');
        return [
            'success' => true,
            'filename' => $filename . '.pdf',
            'filepath' => $filepath,
            'download_url' => EXPORTS_URL . $filename . '.pdf'
        ];
    }

    /** Map numeric or text day to name */
    private function mapDayName($value) {
        if (is_numeric($value)) {
            $map = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];
            $i = (int)$value;
            return $map[$i] ?? (string)$value;
        }
        return (string)$value;
    }
    
    // ===========================================
    // FACULTY EXPORTS
    // ===========================================
    
    /**
     * Export faculty schedule (universal method)
     */
    public function exportFacultySchedule($facultyId, $format = 'pdf') {
        try {
            $faculty = $this->db->fetchRow("
                SELECT f.*, u.username, u.email 
                FROM faculty f 
                JOIN users u ON f.user_id = u.user_id 
                WHERE f.faculty_id = ?
            ", [$facultyId]);
            
            if (!$faculty) {
                throw new Exception("Faculty not found");
            }
            
            // Use the timetable_details view for complete information
            $schedule = $this->db->fetchAll("
                SELECT td.*, 
                       CONCAT(td.faculty_name) as faculty_full_name,
                       CONCAT(td.room_number, ' (', td.building, ')') as room_info
                FROM timetable_details td
                WHERE td.timetable_id IN (
                    SELECT timetable_id FROM timetables 
                    WHERE faculty_id = ? AND is_active = 1
                )
                ORDER BY 
                    FIELD(td.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                    td.start_time
            ", [$facultyId]);
            
            $filename = 'faculty_schedule_' . $facultyId . '_' . date('Y_m_d_H_i_s');
            
            if ($format === 'pdf') {
                return $this->generateSchedulePDF($faculty, $schedule, $filename, 'faculty');
            } else {
                return $this->generateScheduleExcel($faculty, $schedule, $filename, 'faculty');
            }
            
        } catch (Exception $e) {
            $this->log("Error exporting faculty schedule: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Export faculty's student list
     */
    public function exportFacultyStudents($facultyId, $format = 'excel') {
        try {
            $faculty = $this->db->fetchRow("\n                SELECT f.*, u.email \n                FROM faculty f \n                JOIN users u ON f.user_id = u.user_id \n                WHERE f.faculty_id = ?\n            ", [$facultyId]);
            $students = $this->db->fetchAll("\n                SELECT DISTINCT s.student_id, s.first_name, s.last_name, \n                       u.email, s.phone, s.department, s.year_of_study AS year_level,\n                       sub.subject_name, sub.subject_code, sub.credits,\n                       e.section, e.academic_year, e.semester, e.enrollment_date\n                FROM students s\n                JOIN users u ON s.user_id = u.user_id\n                JOIN enrollments e ON s.student_id = e.student_id\n                JOIN subjects sub ON e.subject_id = sub.subject_id\n                JOIN faculty_subjects fs ON sub.subject_id = fs.subject_id\n                WHERE fs.faculty_id = ? AND e.status = 'enrolled' AND fs.is_active = 1\n                ORDER BY sub.subject_name, s.last_name, s.first_name\n            ", [$facultyId]);
            
            $filename = 'faculty_students_' . $facultyId . '_' . date('Y_m_d_H_i_s');
            
            // Generate based on requested format
            if (strtolower($format) === 'pdf') {
                return $this->generateStudentListPDF($students, $filename, $faculty ?: []);
            } else {
                return $this->generateStudentListExcel($students, $filename);
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Export faculty subject assignments with landscape orientation and detailed information
     */
    public function exportFacultySubjectAssignments($facultyId, $format = 'pdf') {
        try {
            $faculty = $this->db->fetchRow("
                SELECT f.*, u.username, u.email 
                FROM faculty f 
                JOIN users u ON f.user_id = u.user_id 
                WHERE f.faculty_id = ?
            ", [$facultyId]);
            
            if (!$faculty) {
                throw new Exception("Faculty not found");
            }
            
            // Get detailed subject assignments with schedule information
            $subjects = $this->db->fetchAll("
                SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name, s.credits, 
                       s.description, s.semester, s.prerequisites, s.department,
                       d.department_name, d.department_code,
                       fs.assigned_date, fs.is_active as assignment_active,
                       COUNT(DISTINCT e.student_id) as enrolled_students,
                       COUNT(DISTINCT t.timetable_id) as scheduled_classes,
                       GROUP_CONCAT(DISTINCT CONCAT(ts.day_of_week, ' ', 
                           DATE_FORMAT(ts.start_time, '%h:%i %p'), '-', 
                           DATE_FORMAT(ts.end_time, '%h:%i %p'), ' (', 
                           c.room_number, ')') SEPARATOR '; ') as schedule_info
                FROM subjects s
                JOIN faculty_subjects fs ON s.subject_id = fs.subject_id
                LEFT JOIN departments d ON s.department_id = d.department_id
                LEFT JOIN enrollments e ON s.subject_id = e.subject_id AND e.status = 'enrolled'
                LEFT JOIN timetables t ON s.subject_id = t.subject_id AND t.faculty_id = fs.faculty_id AND t.is_active = 1
                LEFT JOIN time_slots ts ON t.slot_id = ts.slot_id
                LEFT JOIN classrooms c ON t.classroom_id = c.classroom_id
                WHERE fs.faculty_id = ? AND fs.is_active = 1 AND s.is_active = 1
                GROUP BY s.subject_id, s.subject_code, s.subject_name, s.credits, 
                         s.description, s.semester, s.prerequisites, s.department,
                         d.department_name, d.department_code, fs.assigned_date, fs.is_active
                ORDER BY s.subject_code
            ", [$facultyId]);
            
            $filename = 'faculty_subject_assignments_' . $facultyId . '_' . date('Y_m_d_H_i_s');
            
            if ($format === 'pdf') {
                return $this->generateSubjectAssignmentsPDF($faculty, $subjects, $filename);
            } else {
                return $this->generateSubjectAssignmentsExcel($faculty, $subjects, $filename);
            }
            
        } catch (Exception $e) {
            $this->log("Error exporting faculty subject assignments: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Export faculty class summary with landscape orientation and detailed information
     */
    public function exportFacultyClassSummary($facultyId, $format = 'pdf', $semester = null) {
        try {
            $faculty = $this->db->fetchRow("
                SELECT f.*, u.username, u.email 
                FROM faculty f 
                JOIN users u ON f.user_id = u.user_id 
                WHERE f.faculty_id = ?
            ", [$facultyId]);
            
            if (!$faculty) {
                throw new Exception("Faculty not found");
            }
            
            // Use current semester if not specified
            if (!$semester) {
                $semester = date('n') <= 6 ? 2 : 1; // Spring = 2, Fall = 1
            }
            
            // Get detailed class summary with enrollment and classroom information
            $classSummary = $this->db->fetchAll("
                SELECT s.subject_code, s.subject_name, s.credits, s.type,
                       t.section, t.academic_year, t.semester,
                       c.room_number, c.building, c.capacity, c.type as room_type,
                       ts.day_of_week, ts.start_time, ts.end_time, ts.slot_name,
                       COUNT(DISTINCT e.student_id) as enrolled_students,
                       ROUND((COUNT(DISTINCT e.student_id) / c.capacity) * 100, 1) as capacity_utilization,
                       d.department_name, d.department_code,
                       GROUP_CONCAT(DISTINCT CONCAT(st.first_name, ' ', st.last_name) 
                           ORDER BY st.last_name SEPARATOR ', ') as student_names
                FROM timetables t
                JOIN subjects s ON t.subject_id = s.subject_id
                JOIN classrooms c ON t.classroom_id = c.classroom_id
                JOIN time_slots ts ON t.slot_id = ts.slot_id
                LEFT JOIN departments d ON s.department_id = d.department_id
                LEFT JOIN enrollments e ON s.subject_id = e.subject_id 
                    AND e.section = t.section 
                    AND e.status = 'enrolled'
                    AND e.academic_year = t.academic_year 
                    AND e.semester = t.semester
                LEFT JOIN students st ON e.student_id = st.student_id
                WHERE t.faculty_id = ? AND t.is_active = 1
                    AND t.semester = ?
                GROUP BY t.timetable_id, s.subject_code, s.subject_name, s.credits, s.type,
                         t.section, t.academic_year, t.semester,
                         c.room_number, c.building, c.capacity, c.type,
                         ts.day_of_week, ts.start_time, ts.end_time, ts.slot_name,
                         d.department_name, d.department_code
                ORDER BY ts.day_of_week, ts.start_time, s.subject_code
            ", [$facultyId, $semester]);
            
            $filename = 'faculty_class_summary_' . $facultyId . '_sem' . $semester . '_' . date('Y_m_d_H_i_s');
            
            if ($format === 'pdf') {
                return $this->generateClassSummaryPDF($faculty, $classSummary, $filename, $semester);
            } else {
                return $this->generateClassSummaryExcel($faculty, $classSummary, $filename, $semester);
            }
            
        } catch (Exception $e) {
            $this->log("Error exporting faculty class summary: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ===========================================
    // STUDENT EXPORTS
    // ===========================================
    
    /**
     * Export student's personal schedule
     */
    public function exportStudentSchedule($studentId, $format = 'pdf') {
        try {
            $student = $this->db->fetchRow("
                SELECT s.*, u.username, u.email 
                FROM students s 
                JOIN users u ON s.user_id = u.user_id 
                WHERE s.student_id = ?
            ", [$studentId]);
            
            if (!$student) {
                throw new Exception("Student not found");
            }
            
            // Get student's schedule using timetable_details view
            $schedule = $this->db->fetchAll("
                SELECT td.*,
                       CONCAT(td.room_number, ' (', td.building, ')') as room_info
                FROM timetable_details td
                JOIN timetables t ON td.timetable_id = t.timetable_id
                JOIN enrollments e ON t.subject_id = e.subject_id
                WHERE e.student_id = ? AND e.status = 'enrolled' AND t.is_active = 1
                ORDER BY 
                    FIELD(td.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                    td.start_time
            ", [$studentId]);
            
            $filename = 'student_schedule_' . $studentId . '_' . date('Y_m_d_H_i_s');
            
            if ($format === 'pdf') {
                return $this->generateSchedulePDF($student, $schedule, $filename, 'student');
            } else {
                return $this->generateScheduleExcel($student, $schedule, $filename, 'student');
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Export student's enrollment history
     */
    public function exportStudentEnrollments($studentId, $format = 'excel') {
        try {
            // Fetch student details for PDF header box styling
            $student = $this->db->fetchRow(
                "\n                SELECT s.*, u.username, u.email \n                FROM students s \n                JOIN users u ON s.user_id = u.user_id \n                WHERE s.student_id = ?\n            ", [$studentId]
            );
            $enrollments = $this->db->fetchAll("\n                SELECT e.*, s.subject_name, s.subject_code, s.credits,\n                       f.first_name as faculty_first, f.last_name as faculty_last\n                FROM enrollments e\n                JOIN subjects s ON e.subject_id = s.subject_id\n                LEFT JOIN faculty_subjects fs ON s.subject_id = fs.subject_id\n                LEFT JOIN faculty f ON fs.faculty_id = f.faculty_id\n                WHERE e.student_id = ?\n                ORDER BY e.academic_year DESC, e.semester DESC, s.subject_name\n            ", [$studentId]);
            
            $filename = 'student_enrollments_' . $studentId . '_' . date('Y_m_d_H_i_s');
            
            // Generate based on requested format
            if (strtolower($format) === 'pdf') {
                return $this->generateEnrollmentHistoryPDFStyled($student ?: [], $enrollments, $filename);
            } else {
                return $this->generateEnrollmentHistoryExcel($enrollments, $filename);
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // ===========================================
    // BACKWARD COMPATIBLE FACULTY METHODS
    // ===========================================
    
    /**
     * Export faculty schedule as PDF (backward compatible)
     */
    public function exportFacultySchedulePDF($facultyId, $semester = null) {
        return $this->exportFacultySchedule($facultyId, 'pdf');
    }
    
    /**
     * Export faculty schedule as Excel (backward compatible)
     */
    public function exportFacultyScheduleExcel($facultyId, $semester = null) {
        return $this->exportFacultySchedule($facultyId, 'excel');
    }
    
    // ===========================================
    // PDF GENERATION METHODS
    // ===========================================
    
    private function generateFacultySchedulePDF($faculty, $schedule, $filename) {
        require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator(SYSTEM_NAME);
        $pdf->SetAuthor(SYSTEM_NAME);
        $pdf->SetTitle('Faculty Schedule - ' . $faculty['first_name'] . ' ' . $faculty['last_name']);
        
        $pdf->SetHeaderData('', 0, SYSTEM_NAME, 'Faculty Schedule Report');
        $pdf->setHeaderFont(['helvetica', '', 12]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(15, 27, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        $pdf->AddPage();
        
        // Faculty info
        $html = '<h2>Faculty Schedule</h2>';
        $html .= '<p><strong>Name:</strong> ' . htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']) . '</p>';
        $html .= '<p><strong>Employee ID:</strong> ' . htmlspecialchars($faculty['employee_id']) . '</p>';
        $html .= '<p><strong>Department:</strong> ' . htmlspecialchars($faculty['department']) . '</p>';
        $html .= '<p><strong>Generated:</strong> ' . date('F j, Y g:i A') . '</p>';
        
        // Schedule table
        $html .= '<table border="1" cellpadding="5">';
        $html .= '<tr style="background-color:#f0f0f0;"><th>Day</th><th>Time</th><th>Subject</th><th>Room</th></tr>';
        
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        
        foreach ($schedule as $class) {
            $dayName = $days[$class['day_of_week'] - 1] ?? 'Unknown';
            $html .= '<tr>';
            $html .= '<td>' . $dayName . '</td>';
            $html .= '<td>' . date('g:i A', strtotime($class['start_time'])) . ' - ' . date('g:i A', strtotime($class['end_time'])) . '</td>';
            $html .= '<td>' . htmlspecialchars($class['subject_code'] . ' - ' . $class['subject_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($class['room_number'] . ' - ' . $class['building']) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filepath = EXPORTS_PATH . $filename . '.pdf';
        $pdf->Output($filepath, 'F');
        
        return [
            'success' => true,
            'filename' => $filename . '.pdf',
            'filepath' => $filepath,
            'download_url' => EXPORTS_URL . $filename . '.pdf'
        ];
}

// Removed duplicate generateUsersExcel block introduced by a previous edit

    /**
     * Generate Users PDF (Admin User Reports) - styled like Timetable Reports
     */
    private function generateUsersPDF($users, $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';

            $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('Admin');
            $pdf->SetTitle('User Reports');

            // Margins and page
            $pdf->SetMargins(10, 20, 10);
            $pdf->SetHeaderMargin(8);
            $pdf->SetFooterMargin(12);
            $pdf->SetAutoPageBreak(TRUE, 20);
            $pdf->AddPage('L');

            // Title in blue (like other reports) + neutral body text like Timetables
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 12, 'USER REPORTS', 0, 1, 'C');
            // Generated timestamp (centered, subtle gray) like timetable reports
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(96, 96, 96);
            $pdf->Cell(0, 8, 'Generated on: ' . date('F j, Y g:i A'), 0, 1, 'C');
            $pdf->Ln(2);
            // Reset for table body
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(0, 0, 0);

            // Neutral table styles (no colored header), similar to Timetables Reports
            $html = '<style>
                table { border-collapse: collapse; width: 100%; font-family: helvetica; }
                th { color: #000000; font-weight: bold; padding: 6px 6px; text-align: center; border: 1px solid #999999; font-size: 10px; }
                td { padding: 6px 6px; border: 1px solid #999999; text-align: left; font-size: 10px; }
                .center { text-align: center; }
            </style>';

            // Fixed layout with explicit widths (TCPDF is limited; set inline widths on th/td)
            $w = ['7%','12%','17%','30%','10%','8%','8%','8%']; // email adjusted to 30%
            $html .= '<table style="table-layout: fixed; width:100%;"><thead><tr>'
                . '<th class="center" style="width:' . $w[0] . '">USER ID</th>'
                . '<th style="width:' . $w[1] . '">USERNAME</th>'
                . '<th style="width:' . $w[2] . '">FULL NAME</th>'
                . '<th style="width:' . $w[3] . '">EMAIL</th>'
                . '<th class="center" style="width:' . $w[4] . '">ROLE</th>'
                . '<th class="center" style="width:' . $w[5] . '">STATUS</th>'
                . '<th style="width:' . $w[6] . '">CREATED</th>'
                . '<th style="width:' . $w[7] . '">LAST LOGIN</th>'
                . '</tr></thead><tbody>';

            foreach ($users as $user) {
                $html .= '<tr>';
                $html .= '<td class="center" style="width:' . $w[0] . '">' . htmlspecialchars((string)($user['user_id'] ?? '')) . '</td>';
                $html .= '<td style="width:' . $w[1] . '">' . htmlspecialchars((string)($user['username'] ?? '')) . '</td>';
                $html .= '<td style="width:' . $w[2] . '">' . htmlspecialchars((string)($user['full_name'] ?? '')) . '</td>';
                $html .= '<td style="width:' . $w[3] . '">' . htmlspecialchars((string)($user['email'] ?? '')) . '</td>';
                $role = isset($user['role']) ? ucfirst((string)$user['role']) : '';
                $status = isset($user['status']) ? ucfirst((string)$user['status']) : '';
                $html .= '<td class="center" style="width:' . $w[4] . '">' . htmlspecialchars($role) . '</td>';
                $html .= '<td class="center" style="width:' . $w[5] . '">' . htmlspecialchars($status) . '</td>';
                $html .= '<td style="width:' . $w[6] . '">' . htmlspecialchars((string)($user['created_at'] ?? '')) . '</td>';
                $html .= '<td style="width:' . $w[7] . '">' . htmlspecialchars((string)($user['last_login'] ?? 'Never')) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
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
            $this->log('Error generating users PDF: ' . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function generateUsersExcel($users, $filename) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Title sheet name to match style
        $sheet->setTitle('User Reports');

        // Big title and generated timestamp (match Timetable Reports pattern)
        $sheet->setCellValue('A1', 'User Reports');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F8FF']]
        ]);

        $sheet->setCellValue('A2', 'Generated: ' . date('F j, Y g:i A'));
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '2C3E50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Headers at row 4 to mirror timetable layout spacing
        $headers = ['User ID', 'Username', 'Full Name', 'Email', 'Role', 'Status', 'Created', 'Last Login'];
        $sheet->fromArray($headers, null, 'A4');
        
        // Header styling aligned with timetable reports
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4682B4']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2E5C86']]]
        ];
        $sheet->getStyle('A4:H4')->applyFromArray($headerStyle);
        $sheet->getRowDimension(4)->setRowHeight(20);

        // Freeze after header
        $sheet->freezePane('A5');

        // Enhanced data formatting with alternating row colors
        $row = 5;
        foreach ($users as $index => $user) {
            $sheet->setCellValue('A' . $row, $user['user_id']);
            $sheet->setCellValue('B' . $row, $user['username']);
            $sheet->setCellValue('C' . $row, $user['full_name']);
            $sheet->setCellValue('D' . $row, $user['email']);
            $sheet->setCellValue('E' . $row, ucfirst($user['role']));
            $sheet->setCellValue('F' . $row, ucfirst($user['status']));
            $sheet->setCellValue('G' . $row, $user['created_at']);
            $sheet->setCellValue('H' . $row, $user['last_login'] ?? 'Never');
            
            // Professional alternating row colors and borders
            $fillColor = ($index % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
            $rowStyle = [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BDC3C7']]]
            ];
            $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($rowStyle);
            
            // Left align text columns
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            
            $row++;
        }
        
        // Professional column sizing
        $sheet->getColumnDimension('A')->setWidth(10);  // User ID
        $sheet->getColumnDimension('B')->setWidth(15);  // Username
        $sheet->getColumnDimension('C')->setWidth(25);  // Full Name
        $sheet->getColumnDimension('D')->setWidth(30);  // Email
        $sheet->getColumnDimension('E')->setWidth(12);  // Role
        $sheet->getColumnDimension('F')->setWidth(12);  // Status
        $sheet->getColumnDimension('G')->setWidth(18);  // Created
        $sheet->getColumnDimension('H')->setWidth(18);  // Last Login
        
        $filepath = EXPORTS_PATH . $filename . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        return [
            'success' => true,
            'filename' => $filename . '.xlsx',
            'filepath' => $filepath,
            'download_url' => EXPORTS_URL . $filename . '.xlsx'
        ];
    }
    
    private function generateFacultyScheduleExcel($faculty, $schedule, $filename) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $sheet->setTitle('Faculty Schedule');
        
        // Faculty info
        $sheet->setCellValue('A1', 'Faculty Schedule');
        $sheet->setCellValue('A2', 'Name: ' . $faculty['first_name'] . ' ' . $faculty['last_name']);
        $sheet->setCellValue('A3', 'Employee ID: ' . $faculty['employee_id']);
        $sheet->setCellValue('A4', 'Department: ' . $faculty['department']);
        $sheet->setCellValue('A5', 'Generated: ' . date('F j, Y g:i A'));
        
        // Headers
        $headers = ['Day', 'Start Time', 'End Time', 'Subject Code', 'Subject Name', 'Room'];
        $sheet->fromArray($headers, null, 'A7');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A7:F7')->applyFromArray($headerStyle);
        
        // Data
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $row = 8;
        foreach ($schedule as $class) {
            $dayName = $days[$class['day_of_week'] - 1] ?? 'Unknown';
            $sheet->setCellValue('A' . $row, $dayName);
            $sheet->setCellValue('B' . $row, date('g:i A', strtotime($class['start_time'])));
            $sheet->setCellValue('C' . $row, date('g:i A', strtotime($class['end_time'])));
            $sheet->setCellValue('D' . $row, $class['subject_code']);
            $sheet->setCellValue('E' . $row, $class['subject_name']);
            $sheet->setCellValue('F' . $row, $class['room_number'] . ' - ' . $class['building']);
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $filepath = EXPORTS_PATH . $filename . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        return [
            'success' => true,
            'filename' => $filename . '.xlsx',
            'filepath' => $filepath,
            'download_url' => EXPORTS_URL . $filename . '.xlsx'
        ];
    }
    
    /**
     * Clean up old export files - Optimized for large datasets
     */
    public function cleanupOldFiles($daysOld = 7) {
        try {
            // Use more efficient file scanning for large directories
            $exportPath = rtrim(EXPORTS_PATH, '/') . '/';
            $cutoff = time() - ($daysOld * 24 * 60 * 60);
            $deleted = 0;
            $batchSize = 100; // Process files in batches to avoid memory issues
            
            if (!is_dir($exportPath)) {
                return 0;
            }
            
            $handle = opendir($exportPath);
            if (!$handle) {
                throw new Exception("Cannot open exports directory");
            }
            
            $filesToDelete = [];
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') continue;
                
                $fullPath = $exportPath . $file;
                if (is_file($fullPath) && filemtime($fullPath) < $cutoff) {
                    $filesToDelete[] = $fullPath;
                    
                    // Process in batches to manage memory
                    if (count($filesToDelete) >= $batchSize) {
                        foreach ($filesToDelete as $fileToDelete) {
                            if (unlink($fileToDelete)) {
                                $deleted++;
                            }
                        }
                        $filesToDelete = [];
                    }
                }
            }
            closedir($handle);
            
            // Process remaining files
            foreach ($filesToDelete as $fileToDelete) {
                if (unlink($fileToDelete)) {
                    $deleted++;
                }
            }
            
            $this->log("Cleaned up {$deleted} old export files (older than {$daysOld} days)");
            return $deleted;
            
        } catch (Exception $e) {
            $this->log("Error cleaning up files: " . $e->getMessage(), 'error');
            return 0;
        }
    }
    
    // ===========================================
    // UNIFIED GENERATION METHODS
    // ===========================================
    
    /**
     * Generate schedule PDF for faculty or student - LANDSCAPE ORIENTATION
     */
    private function generateSchedulePDF($user, $schedule, $filename, $userType = 'faculty') {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            // Force landscape orientation for all PDF exports
            $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $title = $userType === 'faculty' ? 'Teaching Schedule' : 'Class Schedule';
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('University Administration');
            $pdf->SetTitle($title);
            $pdf->SetSubject('Academic Schedule');
            
            // Optimized margins for landscape
            $pdf->SetMargins(10, 20, 10);
            $pdf->SetHeaderMargin(8);
            $pdf->SetFooterMargin(12);
            $pdf->SetAutoPageBreak(TRUE, 20);
            
            // Add landscape page
            $pdf->AddPage('L');
            
            // Calculate usable width based on current page and margins
            $margins = $pdf->getMargins();
            $usableWidth = $pdf->getPageWidth() - ($margins['left'] + $margins['right']);
            
            // Simple Title Header
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(25, 25, 112); // Dark blue
            $pdf->Cell(0, 10, $title, 0, 1, 'C');
            $pdf->Ln(5);
            
            // Faculty Information Box
            $pdf->SetFillColor(240, 248, 255); // Light blue background
            $pdf->SetDrawColor(70, 130, 180);
            $pdf->SetLineWidth(0.5);
            $pdf->Rect($margins['left'], $pdf->GetY(), $usableWidth, 35, 'DF');
            
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 8, strtoupper($userType === 'faculty' ? 'FACULTY INFORMATION' : 'STUDENT INFORMATION'), 0, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 11);
            $pdf->SetTextColor(0, 0, 0);
            
            // Two column layout for faculty info
            $leftX = $margins['left'] + 10;
            $rightX = $leftX + ($usableWidth / 2);
            $currentY = $pdf->GetY();
            
            // Left column
            $pdf->SetXY($leftX, $currentY);
            if ($userType === 'faculty') {
                $pdf->Cell(80, 6, 'Name: ' . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''), 0, 1);
                $pdf->SetX($leftX);
                $pdf->Cell(80, 6, 'Employee ID: ' . ($user['employee_id'] ?? 'N/A'), 0, 1);
                $pdf->SetX($leftX);
                $pdf->Cell(80, 6, 'Department: ' . ($user['department'] ?? 'N/A'), 0, 1);
            } else {
                $pdf->Cell(80, 6, 'Name: ' . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''), 0, 1);
                $pdf->SetX($leftX);
                $pdf->Cell(80, 6, 'Student ID: ' . ($user['student_number'] ?? 'N/A'), 0, 1);
                $pdf->SetX($leftX);
                $pdf->Cell(80, 6, 'Year Level: ' . ($user['year_level'] ?? 'N/A'), 0, 1);
            }
            
            // Right column
            $pdf->SetXY($rightX, $currentY);
            $pdf->Cell(80, 6, 'Email: ' . ($user['email'] ?? 'N/A'), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(80, 6, 'Academic Year: ' . date('Y') . '-' . (date('Y') + 1), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(80, 6, 'Generated: ' . date('M j, Y g:i A'), 0, 1);
            
            $pdf->Ln(15);
            
            if (empty($schedule)) {
                $pdf->SetFont('helvetica', 'I', 14);
                $pdf->SetTextColor(128, 128, 128);
                $pdf->Cell(0, 15, 'No subject assignments found for this ' . $userType . '.', 0, 1, 'C');
            } else {
                // Schedule Title
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->SetTextColor(25, 25, 112);
                $pdf->Cell(0, 10, 'WEEKLY SCHEDULE', 0, 1, 'C');
                $pdf->Ln(5);
                
                // Professional table with enhanced styling and gridlines
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetFillColor(41, 128, 185); // Professional blue header
                $pdf->SetTextColor(255, 255, 255); // White text
                $pdf->SetDrawColor(52, 73, 94); // Dark border color
                $pdf->SetLineWidth(0.5); // Thicker borders for better visibility
                
                // Optimized column widths for landscape orientation
                $colRatios = [0.14, 0.22, 0.12, 0.35, 0.17];
                $colWidths = array_map(function($r) use ($usableWidth) { return round($usableWidth * $r, 2); }, $colRatios);
                $headers = ['DAY', 'TIME SLOT', 'CODE', 'SUBJECT NAME', 'ROOM & BUILDING'];
                
                // Draw header with professional styling
                foreach ($headers as $i => $h) {
                    $pdf->Cell($colWidths[$i], 12, $h, 1, $i === count($headers)-1 ? 1 : 0, 'C', true);
                }
                
                // Professional table content with enhanced formatting
                $pdf->SetFont('helvetica', '', 10);
                $pdf->SetTextColor(44, 62, 80); // Professional dark text
                
                $rowCount = 0;
                foreach ($schedule as $class) {
                    // Professional alternating row colors
                    if ($rowCount % 2 == 0) {
                        $pdf->SetFillColor(250, 251, 252); // Very light blue-gray
                    } else {
                        $pdf->SetFillColor(255, 255, 255); // White
                    }
                    
                    $dayName = strtoupper($class['day_of_week']);
                    $timeRange = date('g:i A', strtotime($class['start_time'])) . ' - ' . date('g:i A', strtotime($class['end_time']));
                    
                    // Enhanced cell formatting with consistent height
                    $pdf->Cell($colWidths[0], 10, $dayName, 1, 0, 'C', true);
                    $pdf->Cell($colWidths[1], 10, $timeRange, 1, 0, 'C', true);
                    $pdf->Cell($colWidths[2], 10, $class['subject_code'], 1, 0, 'C', true);
                    
                    // Handle long subject names with better truncation
                    $subjectName = $class['subject_name'];
                    if (mb_strlen($subjectName) > 45) {
                        $subjectName = mb_substr($subjectName, 0, 42) . '...';
                    }
                    $pdf->Cell($colWidths[3], 10, $subjectName, 1, 0, 'L', true);
                    
                    // Enhanced room info formatting
                    $roomInfo = $class['room_number'];
                    if (isset($class['building']) && !empty($class['building'])) {
                        $roomInfo .= ' (' . $class['building'] . ')';
                    }
                    if (mb_strlen($roomInfo) > 20) {
                        $roomInfo = mb_substr($roomInfo, 0, 17) . '...';
                    }
                    $pdf->Cell($colWidths[4], 10, $roomInfo, 1, 1, 'C', true);
                    
                    $rowCount++;
                }
                
                // Summary section
                $pdf->Ln(10);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->SetTextColor(25, 25, 112);
                $pdf->Cell(0, 8, 'SCHEDULE SUMMARY', 0, 1, 'L');
                
                $pdf->SetFont('helvetica', '', 10);
                $pdf->SetTextColor(0, 0, 0);
                $totalClasses = count($schedule);
                $pdf->Cell(0, 6, '• Total Classes: ' . $totalClasses, 0, 1);
                
                // Count unique subjects
                $uniqueSubjects = array_unique(array_column($schedule, 'subject_code'));
                $pdf->Cell(0, 6, '• Total Subjects: ' . count($uniqueSubjects), 0, 1);
                
                // Weekly hours calculation
                $totalMinutes = 0;
                foreach ($schedule as $class) {
                    $start = strtotime($class['start_time']);
                    $end = strtotime($class['end_time']);
                    $totalMinutes += ($end - $start) / 60;
                }
                $totalHours = round($totalMinutes / 60, 1);
                $pdf->Cell(0, 6, '• Weekly Teaching Hours: ' . $totalHours . ' hours', 0, 1);
            }
            
            // Footer with page numbers and branding
            $pdf->SetY(-20);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(128, 128, 128);
            $pdf->Cell(0, 5, 'Generated by University Timetable Management System', 0, 1, 'C');
            $pdf->Cell(0, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');
            
            // Save the PDF
            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');
            
            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.pdf'
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating schedule PDF: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate schedule Excel for faculty or student
     */
    private function generateScheduleExcel($user, $schedule, $filename, $userType = 'faculty') {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('University Timetable System')
                ->setTitle(ucfirst($userType) . ' Subject Assignments')
                ->setSubject('Academic Schedule')
                ->setDescription('Weekly schedule and subject assignments');
            
            // Set title
            $title = ucfirst($userType) . ' Subject Assignments';
            
            $sheet->setTitle('Subject Assignments');
            $sheet->setCellValue('A1', $title);
            $sheet->mergeCells('A1:G1');
            
            // Title styling
            $titleStyle = [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F8FF']]
            ];
            $sheet->getStyle('A1')->applyFromArray($titleStyle);
            
            // Faculty Information Section
            $row = 3;
            $sheet->setCellValue('A' . $row, 'FACULTY INFORMATION');
            $sheet->mergeCells('A' . $row . ':G' . $row);
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F3FF']]
            ]);
            
            $row++;
            // Two-column layout for faculty info
            $sheet->setCellValue('A' . $row, 'Name:');
            $sheet->setCellValue('B' . $row, ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $sheet->setCellValue('D' . $row, 'Email:');
            $sheet->setCellValue('E' . $row, $user['email'] ?? 'N/A');
            
            $row++;
            if ($userType === 'faculty') {
                $sheet->setCellValue('A' . $row, 'Employee ID:');
                $sheet->setCellValue('B' . $row, $user['employee_id'] ?? 'N/A');
            } else {
                $sheet->setCellValue('A' . $row, 'Student ID:');
                $sheet->setCellValue('B' . $row, $user['student_number'] ?? 'N/A');
            }
            $sheet->setCellValue('D' . $row, 'Academic Year:');
            $sheet->setCellValue('E' . $row, date('Y') . '-' . (date('Y') + 1));
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Department:');
            $sheet->setCellValue('B' . $row, $user['department'] ?? 'N/A');
            $sheet->setCellValue('D' . $row, 'Generated:');
            $sheet->setCellValue('E' . $row, date('M j, Y g:i A'));
            
            // Style faculty info section
            $infoStyle = [
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ];
            $sheet->getStyle('A' . ($row-2) . ':A' . $row)->applyFromArray($infoStyle);
            $sheet->getStyle('D' . ($row-2) . ':D' . $row)->applyFromArray($infoStyle);
            
            $row += 3;
            
            if (empty($schedule)) {
                $sheet->setCellValue('A' . $row, 'No subject assignments found for this ' . $userType . '.');
                $sheet->mergeCells('A' . $row . ':G' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray([
                    'font' => ['italic' => true, 'size' => 12, 'color' => ['rgb' => '666666']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);
            } else {
                // Schedule section title
                $sheet->setCellValue('A' . $row, 'WEEKLY SCHEDULE');
                $sheet->mergeCells('A' . $row . ':G' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E3A8A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F3FF']]
                ]);
                
                $row += 2;
                
                // Enhanced table headers
                $headers = ['DAY', 'TIME SLOT', 'SUBJECT CODE', 'SUBJECT NAME', 'ROOM', 'CREDITS', 'SECTION'];
                foreach ($headers as $index => $header) {
                    $col = chr(65 + $index); // A, B, C, etc.
                    $sheet->setCellValue($col . $row, $header);
                }
                
                // Header styling
                $headerStyle = [
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4682B4']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
                ];
                $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($headerStyle);
                
                $row++;
                $startDataRow = $row;
                
                // Table content with enhanced styling
                foreach ($schedule as $index => $class) {
                    $dayName = strtoupper($class['day_of_week']);
                    $timeSlot = date('g:i A', strtotime($class['start_time'])) . ' - ' . date('g:i A', strtotime($class['end_time']));
                    
                    $sheet->setCellValue('A' . $row, $dayName);
                    $sheet->setCellValue('B' . $row, $timeSlot);
                    $sheet->setCellValue('C' . $row, $class['subject_code']);
                    $sheet->setCellValue('D' . $row, $class['subject_name']);
                    $sheet->setCellValue('E' . $row, $class['room_number'] . (isset($class['building']) ? ' (' . $class['building'] . ')' : ''));
                    $sheet->setCellValue('F' . $row, $class['credits'] ?? '3');
                    $sheet->setCellValue('G' . $row, $class['section'] ?? 'A');
                    
                    // Alternating row colors
                    $fillColor = ($index % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
                    $rowStyle = [
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
                    ];
                    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($rowStyle);
                    
                    // Left align subject name
                    $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    
                    $row++;
                }
                
                // Summary section
                $row += 2;
                $sheet->setCellValue('A' . $row, 'SCHEDULE SUMMARY');
                $sheet->mergeCells('A' . $row . ':G' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E3A8A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F3FF']]
                ]);
                
                $row++;
                $totalClasses = count($schedule);
                $uniqueSubjects = array_unique(array_column($schedule, 'subject_code'));
                
                // Calculate total hours
                $totalMinutes = 0;
                foreach ($schedule as $class) {
                    $start = strtotime($class['start_time']);
                    $end = strtotime($class['end_time']);
                    $totalMinutes += ($end - $start) / 60;
                }
                $totalHours = round($totalMinutes / 60, 1);
                
                $sheet->setCellValue('A' . $row, '• Total Classes: ' . $totalClasses);
                $row++;
                $sheet->setCellValue('A' . $row, '• Total Subjects: ' . count($uniqueSubjects));
                $row++;
                $sheet->setCellValue('A' . $row, '• Weekly Teaching Hours: ' . $totalHours . ' hours');
                
                // Style summary
                $summaryStyle = [
                    'font' => ['size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
                ];
                $sheet->getStyle('A' . ($row-2) . ':A' . $row)->applyFromArray($summaryStyle);
            }
            
            // Auto-size columns
            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Set minimum column widths
            $sheet->getColumnDimension('A')->setWidth(12); // Day
            $sheet->getColumnDimension('B')->setWidth(18); // Time
            $sheet->getColumnDimension('C')->setWidth(12); // Code
            $sheet->getColumnDimension('D')->setWidth(25); // Subject
            $sheet->getColumnDimension('E')->setWidth(15); // Room
            $sheet->getColumnDimension('F')->setWidth(10); // Credits
            $sheet->getColumnDimension('G')->setWidth(10); // Section
            
            // Save the Excel file
            $writer = new Xlsx($spreadsheet);
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer->save($filepath);
            
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating schedule Excel: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate enrollment history Excel file
     */
    private function generateEnrollmentHistoryExcel($enrollments, $filename) {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('Timetable Management System')
                ->setTitle('Student Enrollment History')
                ->setDescription('Student enrollment records and academic history');
            
            // Set sheet title
            $sheet->setTitle('Enrollment History');
            
            // Header row
            $headers = [
                'A1' => 'Subject Code',
                'B1' => 'Subject Name', 
                'C1' => 'Credits',
                'D1' => 'Academic Year',
                'E1' => 'Semester',
                'F1' => 'Section',
                'G1' => 'Status',
                'H1' => 'Enrollment Date',
                'I1' => 'Faculty'
            ];
            
            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }
            
            // Professional header styling with enhanced formatting
            $headerStyle = [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2980B9']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '34495E']]]
            ];
            
            $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(18);
            
            // Data rows
            $row = 2;
            foreach ($enrollments as $enrollment) {
                $facultyName = '';
                if (!empty($enrollment['faculty_first']) && !empty($enrollment['faculty_last'])) {
                    $facultyName = $enrollment['faculty_first'] . ' ' . $enrollment['faculty_last'];
                }
                
                $sheet->setCellValue('A' . $row, $enrollment['subject_code'] ?? 'N/A');
                $sheet->setCellValue('B' . $row, $enrollment['subject_name'] ?? 'N/A');
                $sheet->setCellValue('C' . $row, $enrollment['credits'] ?? 0);
                $sheet->setCellValue('D' . $row, $enrollment['academic_year'] ?? 'N/A');
                $sheet->setCellValue('E' . $row, $enrollment['semester'] ?? 'N/A');
                $sheet->setCellValue('F' . $row, $enrollment['section'] ?? 'N/A');
                $sheet->setCellValue('G' . $row, ucfirst($enrollment['status'] ?? 'enrolled'));
                $sheet->setCellValue('H' . $row, $enrollment['enrollment_date'] ? date('M d, Y', strtotime($enrollment['enrollment_date'])) : 'N/A');
                $sheet->setCellValue('I' . $row, $facultyName ?: 'Not Assigned');
                
                // Professional alternating row colors and borders
                $fillColor = (($row - 2) % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
                $rowStyle = [
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BDC3C7']]]
                ];
                $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($rowStyle);
                
                // Left align text columns
                $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                
                $row++;
            }
            
            // Professional column sizing
            $sheet->getColumnDimension('A')->setWidth(12);  // Subject Code
            $sheet->getColumnDimension('B')->setWidth(25);  // Subject Name
            $sheet->getColumnDimension('C')->setWidth(10);  // Credits
            $sheet->getColumnDimension('D')->setWidth(15);  // Academic Year
            $sheet->getColumnDimension('E')->setWidth(12);  // Semester
            $sheet->getColumnDimension('F')->setWidth(10);  // Section
            $sheet->getColumnDimension('G')->setWidth(12);  // Status
            $sheet->getColumnDimension('H')->setWidth(18);  // Enrollment Date
            $sheet->getColumnDimension('I')->setWidth(20);  // Faculty
            
            // Save the file
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);
            
            $this->log("Generated enrollment history Excel: " . $filename . '.xlsx');
            
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'file_path' => $filepath,
                'download_url' => '../student/download.php?file=' . urlencode($filename . '.xlsx')
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating enrollment history Excel: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate enrollment history PDF file
     */
    private function generateEnrollmentHistoryPDF($enrollments, $filename) {
        try {
            require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
            
            $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('TMS');
            $pdf->SetTitle('Student Enrollment History');
            $pdf->SetSubject('Enrollment Records');
            
            // Set default header data
            $pdf->SetHeaderData('', 0, 'Student Enrollment History', 'Generated on ' . date('F j, Y g:i A'));
            
            // Set header and footer fonts
            $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
            
            // Set default monospaced font
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            
            // Set margins
            $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            
            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            
            // Add a page
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('helvetica', '', 10);
            
            // Create HTML table
            $html = '<style>
                table { border-collapse: collapse; width: 100%; }
                th { background-color: #4A90E2; color: white; font-weight: bold; padding: 8px; text-align: center; border: 1px solid #ddd; }
                td { padding: 6px; border: 1px solid #ddd; text-align: left; }
                tr:nth-child(even) { background-color: #f8f9fa; }
            </style>';
            
            $html .= '<h2 style="color: #1E3A8A; text-align: center; margin-bottom: 16px;">Student Enrollment History</h2>';

            // Academic Summary block (styled to match schedule summary feel)
            if (!empty($enrollments)) {
                $totalEnrollments = count($enrollments);
                $uniqueSubjects = [];
                $totalCredits = 0;
                $semesters = [];
                $years = [];
                foreach ($enrollments as $enr) {
                    if (!empty($enr['subject_code'])) { $uniqueSubjects[$enr['subject_code']] = true; }
                    $totalCredits += isset($enr['credits']) ? (int)$enr['credits'] : 0;
                    if (!empty($enr['semester'])) { $semesters[$enr['semester']] = true; }
                    if (!empty($enr['academic_year'])) { $years[$enr['academic_year']] = true; }
                }
                $uniqueSubjectCount = count($uniqueSubjects);
                $semestersList = implode(', ', array_keys($semesters));
                $yearsList = implode(', ', array_keys($years));

                $html .= '<div style="background:#F0F8FF;border:1px solid #4682B4;padding:12px 14px;border-radius:6px;margin:0 0 16px 0;">'
                      . '<div style="text-align:left;color:#1E3A8A;font-weight:bold;font-size:14px;margin-bottom:6px;">ACADEMIC SUMMARY</div>'
                      . '<div style="color:#2C3E50;font-size:12px;line-height:1.6;">'
                      . '• Total Enrollments: <strong>' . (int)$totalEnrollments . '</strong><br/>'
                      . '• Total Unique Subjects: <strong>' . (int)$uniqueSubjectCount . '</strong><br/>'
                      . '• Total Credits: <strong>' . (int)$totalCredits . '</strong><br/>'
                      . '• Semesters Covered: <strong>' . htmlspecialchars($semestersList) . '</strong><br/>'
                      . '• Academic Years: <strong>' . htmlspecialchars($yearsList) . '</strong>'
                      . '</div>'
                      . '</div>';
            }
            
            if (empty($enrollments)) {
                $html .= '<p style="text-align: center; color: #666;">No enrollment records found.</p>';
            } else {
                $html .= '<table>';
                $html .= '<thead><tr>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Credits</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                    <th>Section</th>
                    <th>Status</th>
                    <th>Enrollment Date</th>
                    <th>Faculty</th>
                </tr></thead><tbody>';
                
                foreach ($enrollments as $enrollment) {
                    $facultyName = '';
                    if (!empty($enrollment['faculty_first']) && !empty($enrollment['faculty_last'])) {
                        $facultyName = $enrollment['faculty_first'] . ' ' . $enrollment['faculty_last'];
                    }
                    
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($enrollment['subject_code'] ?? 'N/A') . '</td>';
                    $html .= '<td>' . htmlspecialchars($enrollment['subject_name'] ?? 'N/A') . '</td>';
                    $html .= '<td>' . htmlspecialchars($enrollment['credits'] ?? '0') . '</td>';
                    $html .= '<td>' . htmlspecialchars($enrollment['academic_year'] ?? 'N/A') . '</td>';
                    $html .= '<td>' . htmlspecialchars($enrollment['semester'] ?? 'N/A') . '</td>';
                    $html .= '<td>' . htmlspecialchars($enrollment['section'] ?? 'N/A') . '</td>';
                    $html .= '<td>' . htmlspecialchars(ucfirst($enrollment['status'] ?? 'enrolled')) . '</td>';
                    $html .= '<td>' . htmlspecialchars($enrollment['enrollment_date'] ? date('M d, Y', strtotime($enrollment['enrollment_date'])) : 'N/A') . '</td>';
                    $html .= '<td>' . htmlspecialchars($facultyName ?: 'Not Assigned') . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody></table>';
            }
            
            // Print the HTML content
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Save the file
            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');
            
            $this->log("Generated enrollment history PDF: " . $filename . '.pdf');
            
            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'file_path' => $filepath,
                'download_url' => '../student/download.php?file=' . urlencode($filename . '.pdf')
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating enrollment history PDF: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Export student's faculty contacts (PDF)
     */
    public function exportStudentFacultyContacts($studentId, $format = 'pdf') {
        try {
            // Fetch student
            $student = $this->db->fetchRow(
                "\n                SELECT s.*, u.username, u.email \n                FROM students s \n                JOIN users u ON s.user_id = u.user_id \n                WHERE s.student_id = ?\n            ", [$studentId]
            );
            if (!$student) {
                throw new Exception('Student not found');
            }

            // Fetch distinct faculty associated to student's current enrollments
            $rows = $this->db->fetchAll(
                "\n                SELECT DISTINCT f.faculty_id, f.first_name, f.last_name, f.department, u.email as faculty_email,\n                       s.subject_code, s.subject_name\n                FROM enrollments e\n                JOIN subjects s ON e.subject_id = s.subject_id\n                LEFT JOIN faculty_subjects fs ON s.subject_id = fs.subject_id\n                LEFT JOIN faculty f ON fs.faculty_id = f.faculty_id\n                LEFT JOIN users u ON f.user_id = u.user_id\n                WHERE e.student_id = ? AND e.status = 'enrolled'\n                ORDER BY f.last_name, f.first_name, s.subject_code\n            ", [$studentId]
            );

            // Aggregate subjects per faculty
            $facultyList = [];
            foreach ($rows as $r) {
                if (empty($r['faculty_id'])) { continue; }
                $fid = $r['faculty_id'];
                if (!isset($facultyList[$fid])) {
                    $facultyList[$fid] = [
                        'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                        'email' => $r['faculty_email'] ?? 'N/A',
                        'department' => $r['department'] ?? 'N/A',
                        'subjects' => []
                    ];
                }
                $code = $r['subject_code'] ?? '';
                $name = $r['subject_name'] ?? '';
                if ($code || $name) {
                    $facultyList[$fid]['subjects'][] = trim($code . ($name ? ' - ' . $name : ''));
                }
            }
            // Reindex to sequential array
            $facultyList = array_values($facultyList);

            $filename = 'student_faculty_contacts_' . $studentId . '_' . date('Y_m_d_H_i_s');
            if (strtolower($format) === 'pdf') {
                return $this->generateFacultyContactsPDF($student, $facultyList, $filename);
            }

            return ['success' => false, 'error' => 'Unsupported format'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate student list Excel file for faculty
     */
    private function generateStudentListExcel($students, $filename) {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('Timetable Management System')
                ->setTitle('Faculty Student List')
                ->setDescription('List of students enrolled in faculty subjects');
            
            // Set sheet title
            $sheet->setTitle('Student List');
            
            // Header row
            $headers = [
                'A1' => 'Student ID',
                'B1' => 'First Name',
                'C1' => 'Last Name', 
                'D1' => 'Email',
                'E1' => 'Phone',
                'F1' => 'Department',
                'G1' => 'Year Level',
                'H1' => 'Subject Code',
                'I1' => 'Subject Name',
                'J1' => 'Credits',
                'K1' => 'Section',
                'L1' => 'Academic Year',
                'M1' => 'Semester',
                'N1' => 'Enrollment Date'
            ];
            
            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }
            
            // Style header row
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4A90E2']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                ]
            ];
            
            $sheet->getStyle('A1:N1')->applyFromArray($headerStyle);
            
            // Data rows
            $row = 2;
            foreach ($students as $student) {
                $sheet->setCellValue('A' . $row, $student['student_id'] ?? 'N/A');
                $sheet->setCellValue('B' . $row, $student['first_name'] ?? 'N/A');
                $sheet->setCellValue('C' . $row, $student['last_name'] ?? 'N/A');
                $sheet->setCellValue('D' . $row, $student['email'] ?? 'N/A');
                $sheet->setCellValue('E' . $row, $student['phone'] ?? 'N/A');
                $sheet->setCellValue('F' . $row, $student['department'] ?? 'N/A');
                $sheet->setCellValue('G' . $row, $student['year_level'] ?? 'N/A');
                $sheet->setCellValue('H' . $row, $student['subject_code'] ?? 'N/A');
                $sheet->setCellValue('I' . $row, $student['subject_name'] ?? 'N/A');
                $sheet->setCellValue('J' . $row, $student['credits'] ?? 0);
                $sheet->setCellValue('K' . $row, $student['section'] ?? 'N/A');
                $sheet->setCellValue('L' . $row, $student['academic_year'] ?? 'N/A');
                $sheet->setCellValue('M' . $row, $student['semester'] ?? 'N/A');
                $sheet->setCellValue('N' . $row, $student['enrollment_date'] ? date('M d, Y', strtotime($student['enrollment_date'])) : 'N/A');
                
                // Alternate row colors
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':N' . $row)
                          ->getFill()
                          ->setFillType(Fill::FILL_SOLID)
                          ->getStartColor()->setRGB('F8F9FA');
                }
                
                $row++;
            }
            
            // Auto-size columns
            foreach (range('A', 'N') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Add borders to all data
            $dataRange = 'A1:N' . ($row - 1);
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]
                ]
            ]);
            
            // Save the file
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $writer->save($filepath);
            
            $this->log("Generated faculty student list Excel: " . $filename . '.xlsx');
            
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'file_path' => $filepath,
                'download_url' => '../faculty/download.php?file=' . urlencode($filename . '.xlsx')
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating student list Excel: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate student list PDF file for faculty
     */
    private function generateStudentListPDF($students, $filename, $faculty = []) {
        try {
            require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
            
            $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Document meta
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('University Administration');
            $pdf->SetTitle('Student Lists');
            $pdf->SetSubject('Enrolled Students by Subject');
            
            // Layout
            $pdf->SetMargins(15, 25, 15);
            $pdf->SetHeaderMargin(10);
            $pdf->SetFooterMargin(15);
            $pdf->SetAutoPageBreak(TRUE, 25);
            $pdf->AddPage('L');
            
            // Width calc
            $margins = $pdf->getMargins();
            $usableWidth = $pdf->getPageWidth() - ($margins['left'] + $margins['right']);
            
            // Title
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 10, 'STUDENT LISTS', 0, 1, 'C');
            $pdf->Ln(4);
            
            // Faculty info box
            $pdf->SetFillColor(240, 248, 255);
            $pdf->SetDrawColor(70, 130, 180);
            $pdf->SetLineWidth(0.5);
            $pdf->Rect($margins['left'], $pdf->GetY(), $usableWidth, 30, 'DF');
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 8, 'FACULTY INFORMATION', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(0, 0, 0);
            $leftX = $margins['left'] + 10;
            $rightX = $leftX + ($usableWidth / 2);
            $currentY = $pdf->GetY();
            $pdf->SetXY($leftX, $currentY);
            $pdf->Cell(80, 6, 'Name: ' . (trim(($faculty['first_name'] ?? '') . ' ' . ($faculty['last_name'] ?? '')) ?: 'N/A'), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(80, 6, 'Employee ID: ' . ($faculty['employee_id'] ?? 'N/A'), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(80, 6, 'Department: ' . ($faculty['department'] ?? 'N/A'), 0, 1);
            $pdf->SetXY($rightX, $currentY);
            $pdf->Cell(80, 6, 'Email: ' . ($faculty['email'] ?? 'N/A'), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(80, 6, 'Academic Year: ' . date('Y') . '-' . (date('Y') + 1), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(80, 6, 'Generated: ' . date('M j, Y g:i A'), 0, 1);
            
            $pdf->Ln(10);
            
            if (empty($students)) {
                $pdf->SetFont('helvetica', 'I', 14);
                $pdf->SetTextColor(128, 128, 128);
                $pdf->Cell(0, 15, 'No enrolled students found.', 0, 1, 'C');
            } else {
                // Header styling
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->SetFillColor(70, 130, 180);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetDrawColor(50, 50, 50);
                $pdf->SetLineWidth(0.3);
                
                // Column widths (ID, Name, Email, Dept, Year, Subject, Section, Sem)
                // Widen Department and Email slightly; rebalance others
                $colRatios = [0.09, 0.18, 0.25, 0.15, 0.05, 0.11, 0.08, 0.09];
                $colWidths = array_map(function($r) use ($usableWidth) { return round($usableWidth * $r, 2); }, $colRatios);
                $headers = ['STUDENT ID','NAME','EMAIL','DEPARTMENT','YEAR','SUBJECT','SECTION','SEM'];
                foreach ($headers as $i => $h) {
                    $pdf->Cell($colWidths[$i], 10, $h, 1, $i === count($headers)-1 ? 1 : 0, 'C', true);
                }
                
                // Body rows
                $pdf->SetFont('helvetica', '', 9);
                $pdf->SetTextColor(0, 0, 0);
                $rowIndex = 0;
                foreach ($students as $s) {
                    if ($rowIndex % 2 == 0) { $pdf->SetFillColor(248, 249, 250); } else { $pdf->SetFillColor(255, 255, 255); }
                    $fullName = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
                    $nameOut = (mb_strlen($fullName) > 32) ? (mb_substr($fullName, 0, 29) . '...') : $fullName;
                    $emailRaw = $s['email'] ?? 'N/A';
                    $emailOut = (mb_strlen($emailRaw) > 40) ? (mb_substr($emailRaw, 0, 37) . '...') : $emailRaw;
                    
                    $pdf->Cell($colWidths[0], 8, (string)($s['student_id'] ?? 'N/A'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths[1], 8, $nameOut ?: 'N/A', 1, 0, 'L', true);
                    $pdf->Cell($colWidths[2], 8, $emailOut, 1, 0, 'L', true);
                    $pdf->Cell($colWidths[3], 8, (string)($s['department'] ?? 'N/A'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths[4], 8, (string)($s['year_level'] ?? 'N/A'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths[5], 8, (string)($s['subject_code'] ?? 'N/A'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths[6], 8, (string)($s['section'] ?? 'N/A'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths[7], 8, (string)($s['semester'] ?? 'N/A'), 1, 1, 'C', true);
                    $rowIndex++;
                }
                
                // Summary
                $pdf->Ln(8);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->SetTextColor(25, 25, 112);
                $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 10);
                $pdf->SetTextColor(0, 0, 0);
                $totalStudents = count($students);
                $uniqueSubjects = array_unique(array_filter(array_map(function($r){ return $r['subject_code'] ?? null; }, $students)));
                $uniqueSections = array_unique(array_filter(array_map(function($r){ return $r['section'] ?? null; }, $students)));
                $pdf->Cell(0, 6, '• Total Students: ' . $totalStudents, 0, 1);
                $pdf->Cell(0, 6, '• Unique Subjects: ' . count($uniqueSubjects), 0, 1);
                $pdf->Cell(0, 6, '• Sections Covered: ' . count($uniqueSections), 0, 1);
            }
            
            // Save the file
            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');
            
            $this->log("Generated faculty student list PDF: " . $filename . '.pdf');
            
            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'file_path' => $filepath,
                'download_url' => '../faculty/download.php?file=' . urlencode($filename . '.pdf')
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating student list PDF: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate Faculty Contacts PDF for student - landscape and styled like schedule
     */
    private function generateFacultyContactsPDF($student, array $facultyList, string $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';

            $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // Document info
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('University Administration');
            $pdf->SetTitle('Faculty Contacts');
            $pdf->SetSubject('Contacts for Enrolled Subjects');

            // Margins and page
            $pdf->SetMargins(10, 20, 10);
            $pdf->SetHeaderMargin(8);
            $pdf->SetFooterMargin(12);
            $pdf->SetAutoPageBreak(TRUE, 20);
            $pdf->AddPage('L');

            // Width
            $margins = $pdf->getMargins();
            $usableWidth = $pdf->getPageWidth() - ($margins['left'] + $margins['right']);

            // Title
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 10, 'FACULTY CONTACTS', 0, 1, 'C');
            $pdf->Ln(5);

            // Student info box
            $pdf->SetFillColor(240, 248, 255);
            $pdf->SetDrawColor(70, 130, 180);
            $pdf->SetLineWidth(0.5);
            $pdf->Rect($margins['left'], $pdf->GetY(), $usableWidth, 35, 'DF');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 8, 'STUDENT INFORMATION', 0, 1, 'C');

            $pdf->SetFont('helvetica', '', 11);
            $pdf->SetTextColor(0, 0, 0);
            $leftX = $margins['left'] + 10;
            $rightX = $leftX + ($usableWidth / 2);
            $currentY = $pdf->GetY();
            $pdf->SetXY($leftX, $currentY);
            $pdf->Cell(90, 6, 'Name: ' . trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(90, 6, 'Student ID: ' . ($student['student_number'] ?? 'N/A'), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(90, 6, 'Year Level: ' . ($student['year_level'] ?? 'N/A'), 0, 1);

            $pdf->SetXY($rightX, $currentY);
            $pdf->Cell(90, 6, 'Email: ' . ($student['email'] ?? 'N/A'), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(90, 6, 'Academic Year: ' . date('Y') . '-' . (date('Y') + 1), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(90, 6, 'Generated: ' . date('M j, Y g:i A'), 0, 1);

            $pdf->Ln(12);

            // Table header
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(41, 128, 185);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetDrawColor(52, 73, 94);
            $pdf->SetLineWidth(0.5);

            $colRatios = [0.22, 0.28, 0.20, 0.30];
            $colWidths = array_map(function($r) use ($usableWidth) { return round($usableWidth * $r, 2); }, $colRatios);
            $headers = ['NAME', 'EMAIL', 'DEPARTMENT', 'SUBJECTS TAUGHT'];
            foreach ($headers as $i => $h) {
                $pdf->Cell($colWidths[$i], 12, $h, 1, $i === count($headers)-1 ? 1 : 0, 'C', true);
            }

            // Rows
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(44, 62, 80);
            $pdf->setCellHeightRatio(1.15);
            $pdf->setCellPaddings(2, 2, 2, 2);
            $rowCount = 0;
            foreach ($facultyList as $f) {
                // Alternate row fill
                $fillColor = ($rowCount % 2 == 0) ? [250, 251, 252] : [255, 255, 255];
                $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);

                $name = $f['name'] ?: 'N/A';
                $email = $f['email'] ?: 'N/A';
                $dept = $f['department'] ?: 'N/A';
                $subjects = implode(', ', array_unique($f['subjects'] ?? []));
                // Ensure commas have space to encourage wraps
                $subjects = preg_replace('/,\s*/', ', ', (string)$subjects);

                // Compute needed height for the subjects cell with wrapping
                $startX = $pdf->GetX();
                $startY = $pdf->GetY();
                $subjectsHeight = $pdf->getStringHeight($colWidths[3], $subjects, false, true, '', 1);
                $rowHeight = max(10, $subjectsHeight);

                // Draw fixed-height cells for first three columns
                $pdf->Cell($colWidths[0], $rowHeight, $name, 1, 0, 'L', true);
                $pdf->Cell($colWidths[1], $rowHeight, $email, 1, 0, 'L', true);
                $pdf->Cell($colWidths[2], $rowHeight, $dept, 1, 0, 'C', true);

                // Draw wrapped subjects with MultiCell to stay inside borders
                $pdf->MultiCell(
                    $colWidths[3],
                    $rowHeight,
                    $subjects,
                    1,
                    'L',
                    true,
                    1,
                    '',
                    '',
                    true,
                    0,
                    false,
                    true,
                    $rowHeight,
                    'M'
                );

                $rowCount++;
            }

            // Summary
            $pdf->Ln(8);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 8, 'CONTACTS SUMMARY', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 6, '• Total Faculty Contacts: ' . count($facultyList), 0, 1);

            // Footer
            $pdf->SetY(-20);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(128, 128, 128);
            $pdf->Cell(0, 5, 'Generated by University Timetable Management System', 0, 1, 'C');
            $pdf->Cell(0, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');

            // Save
            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');
            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.pdf'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Export student's academic summary (PDF preferred)
     */
    public function exportStudentAcademicSummary($studentId, $format = 'pdf') {
        try {
            // Fetch student
            $student = $this->db->fetchRow(
                "\n                SELECT s.*, u.username, u.email \n                FROM students s \n                JOIN users u ON s.user_id = u.user_id \n                WHERE s.student_id = ?\n            ", [$studentId]
            );
            if (!$student) { throw new Exception('Student not found'); }

            // Fetch academic summary rows
            $rows = $this->db->fetchAll(
                "\n                SELECT s.subject_code, s.subject_name, s.credits, e.section, e.semester, e.academic_year, e.status,\n                       CONCAT(f.first_name, ' ', f.last_name) as faculty_name,\n                       c.room_number, c.building, ts.day_of_week, ts.start_time, ts.end_time\n                FROM enrollments e\n                JOIN subjects s ON e.subject_id = s.subject_id\n                LEFT JOIN timetables t ON s.subject_id = t.subject_id\n                    AND e.section = t.section\n                    AND e.academic_year = t.academic_year\n                    AND e.semester = t.semester\n                    AND t.is_active = 1\n                LEFT JOIN faculty f ON t.faculty_id = f.faculty_id\n                LEFT JOIN classrooms c ON t.classroom_id = c.classroom_id\n                LEFT JOIN time_slots ts ON t.slot_id = ts.slot_id\n                WHERE e.student_id = ? AND e.status IN ('enrolled','completed','dropped')\n                ORDER BY e.academic_year DESC, e.semester, s.subject_code\n            ", [$studentId]
            );

            $filename = 'student_academic_summary_' . $studentId . '_' . date('Y_m_d_H_i_s');
            if (strtolower($format) === 'pdf') {
                return $this->generateAcademicSummaryPDF($student, $rows, $filename);
            }
            return $this->exportFilteredUsers($rows, $format);
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate Academic Summary PDF for a student - schedule-styled
     */
    private function generateAcademicSummaryPDF($student, array $rows, string $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            $pdf = new \TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // Meta
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('University Administration');
            $pdf->SetTitle('Academic Summary');
            $pdf->SetSubject('Comprehensive Academic Overview');

            // Page setup
            $pdf->SetMargins(10, 20, 10);
            $pdf->SetHeaderMargin(8);
            $pdf->SetFooterMargin(12);
            $pdf->SetAutoPageBreak(TRUE, 20);
            $pdf->AddPage('L');

            $margins = $pdf->getMargins();
            $usableWidth = $pdf->getPageWidth() - ($margins['left'] + $margins['right']);

            // Title
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 10, 'ACADEMIC SUMMARY', 0, 1, 'C');
            $pdf->Ln(5);

            // Info box
            $pdf->SetFillColor(240, 248, 255);
            $pdf->SetDrawColor(70, 130, 180);
            $pdf->SetLineWidth(0.5);
            $pdf->Rect($margins['left'], $pdf->GetY(), $usableWidth, 35, 'DF');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 8, 'STUDENT INFORMATION', 0, 1, 'C');

            $pdf->SetFont('helvetica', '', 11);
            $pdf->SetTextColor(0, 0, 0);
            $leftX = $margins['left'] + 10;
            $rightX = $leftX + ($usableWidth / 2);
            $y = $pdf->GetY();
            $pdf->SetXY($leftX, $y);
            $pdf->Cell(90, 6, 'Name: ' . trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(90, 6, 'Student ID: ' . ($student['student_number'] ?? 'N/A'), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(90, 6, 'Year Level: ' . ($student['year_level'] ?? 'N/A'), 0, 1);

            $pdf->SetXY($rightX, $y);
            $pdf->Cell(90, 6, 'Email: ' . ($student['email'] ?? 'N/A'), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(90, 6, 'Academic Year: ' . date('Y') . '-' . (date('Y') + 1), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(90, 6, 'Generated: ' . date('M j, Y g:i A'), 0, 1);

            $pdf->Ln(12);

            // Header row
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(41, 128, 185);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetDrawColor(52, 73, 94);
            $pdf->SetLineWidth(0.5);

            // Column ratios sum to 1.00 for exact fit; tighten SUBJECT and FACULTY, widen STATUS
            $colRatios = [
                0.08, // CODE
                0.20, // SUBJECT (reduced)
                0.05, // CR
                0.12, // FACULTY (reduced)
                0.06, // DAY
                0.12, // TIME
                0.11, // ROOM
                0.06, // SEM
                0.08, // YEAR
                0.05, // SEC
                0.07  // STATUS (increased)
            ];
            $colWidths = array_map(function($r) use ($usableWidth) { return round($usableWidth * $r, 2); }, $colRatios);
            $headers = ['CODE','SUBJECT','CR','FACULTY','DAY','TIME','ROOM','SEM','YEAR','SEC','STATUS'];
            foreach ($headers as $i => $h) {
                $pdf->Cell($colWidths[$i], 11, $h, 1, $i === count($headers)-1 ? 1 : 0, 'C', true);
            }

            // Rows
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(44, 62, 80);
            $pdf->setCellPaddings(2, 2, 2, 2);
            $pdf->setCellHeightRatio(1.15);
            $rowIndex = 0;
            foreach ($rows as $r) {
                $fillColor = ($rowIndex % 2 == 0) ? [250, 251, 252] : [255, 255, 255];
                $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);

                $code = $r['subject_code'] ?? '';
                $name = (string)($r['subject_name'] ?? '');
                $cr = (string)($r['credits'] ?? '');
                $fac = (string)($r['faculty_name'] ?? 'N/A');
                $day = isset($r['day_of_week']) && $r['day_of_week'] ? strtoupper(substr($r['day_of_week'],0,3)) : '-';
                $time = (isset($r['start_time']) && isset($r['end_time'])) ? (date('g:i A', strtotime($r['start_time'])) . ' - ' . date('g:i A', strtotime($r['end_time']))) : '-';
                $room = trim(($r['room_number'] ?? '') . (isset($r['building']) && $r['building'] ? ' (' . $r['building'] . ')' : ''));
                if ($room === '' || $room === '()' || $room === ' ( )') { $room = 'TBA'; }
                $sem = (string)($r['semester'] ?? '');
                $year = (string)($r['academic_year'] ?? '');
                $sec = (string)($r['section'] ?? '');
                $status = strtoupper(substr((string)($r['status'] ?? ''), 0, 8));

                // Measure heights for wrapping columns (SUBJECT, FACULTY, TIME, ROOM)
                $hSubject = $pdf->getStringHeight($colWidths[1], $name, false, true, '', 1);
                $hFaculty = $pdf->getStringHeight($colWidths[3], $fac, false, true, '', 1);
                $hTime    = $pdf->getStringHeight($colWidths[5], $time, false, true, '', 1);
                $hRoom    = $pdf->getStringHeight($colWidths[6], $room, false, true, '', 1);
                $rowHeight = max(9, $hSubject, $hFaculty, $hTime, $hRoom);

                // Keep starting coordinates for the row
                $x = $pdf->GetX();
                $y = $pdf->GetY();

                // CODE
                $pdf->MultiCell($colWidths[0], $rowHeight, $code, 1, 'C', true, 0, $x, $y, true, 0, false, true, $rowHeight, 'M');
                $x += $colWidths[0];
                // SUBJECT (wrapped)
                $pdf->MultiCell($colWidths[1], $rowHeight, $name, 1, 'L', true, 0, $x, $y, true, 0, false, true, $rowHeight, 'M');
                $x += $colWidths[1];
                // CR
                $pdf->MultiCell($colWidths[2], $rowHeight, $cr, 1, 'C', true, 0, $x, $y, true, 0, false, true, $rowHeight, 'M');
                $x += $colWidths[2];
                // FACULTY (wrapped)
                $pdf->MultiCell($colWidths[3], $rowHeight, $fac, 1, 'L', true, 0, $x, $y, true, 0, false, true, $rowHeight, 'M');
                $x += $colWidths[3];
                // DAY
                $pdf->MultiCell($colWidths[4], $rowHeight, $day, 1, 'C', true, 0, $x, $y, true, 0, false, true, $rowHeight, 'M');
                $x += $colWidths[4];
                // TIME (wrapped)
                $pdf->MultiCell($colWidths[5], $rowHeight, $time, 1, 'C', true, 0, $x, $y, true, 0, false, true, $rowHeight, 'M');
                $x += $colWidths[5];
                // ROOM (wrapped)
                $pdf->MultiCell($colWidths[6], $rowHeight, $room, 1, 'C', true, 0, $x, $y, true, 0, false, true, $rowHeight, 'M');
                $x += $colWidths[6];
                // SEM
                $pdf->MultiCell($colWidths[7], $rowHeight, $sem, 1, 'C', true, 0, $x, $y, true, 0, false, true, $rowHeight, 'M');
                $x += $colWidths[7];
                // YEAR
                $pdf->MultiCell($colWidths[8], $rowHeight, $year, 1, 'C', true, 0, $x, $y, true, 0, false, true, $rowHeight, 'M');
                $x += $colWidths[8];
                // SEC
                $pdf->MultiCell($colWidths[9], $rowHeight, $sec, 1, 'C', true, 0, $x, $y, true, 0, false, true, $rowHeight, 'M');
                $x += $colWidths[9];
                // STATUS (last cell, move to next line)
                $pdf->MultiCell($colWidths[10], $rowHeight, $status, 1, 'C', true, 1, $x, $y, true, 0, false, true, $rowHeight, 'M');

                $rowIndex++;
            }

            // Summary
            $pdf->Ln(8);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 8, 'ACADEMIC SUMMARY STATISTICS', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(0, 0, 0);
            $totalCourses = count($rows);
            $uniqueCodes = count(array_unique(array_map(function($r){ return $r['subject_code'] ?? null; }, $rows)));
            $totalCredits = array_sum(array_map(function($r){ return (int)($r['credits'] ?? 0); }, $rows));
            $pdf->Cell(0, 6, '• Total Enrollments: ' . $totalCourses, 0, 1);
            $pdf->Cell(0, 6, '• Unique Subjects: ' . $uniqueCodes, 0, 1);
            $pdf->Cell(0, 6, '• Total Credits: ' . $totalCredits, 0, 1);

            // Footer
            $pdf->SetY(-20);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(128, 128, 128);
            $pdf->Cell(0, 5, 'Generated by University Timetable Management System', 0, 1, 'C');
            $pdf->Cell(0, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');

            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');
            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.pdf'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate Subject Assignments PDF with landscape orientation
     */
    private function generateSubjectAssignmentsPDF($faculty, $subjects, $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('University Administration');
            $pdf->SetTitle('Faculty Subject Assignments');
            $pdf->SetSubject('Subject Assignment Details');
            
            // Set margins and auto page breaks
            $pdf->SetMargins(15, 25, 15);
            $pdf->SetHeaderMargin(10);
            $pdf->SetFooterMargin(15);
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Add a page in landscape orientation
            $pdf->AddPage('L');
            
            // Calculate usable width based on current page and margins
            $margins = $pdf->getMargins();
            $usableWidth = $pdf->getPageWidth() - ($margins['left'] + $margins['right']);
            
            // Title Header
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(25, 25, 112); // Dark blue
            $pdf->Cell(0, 10, 'FACULTY SUBJECT ASSIGNMENTS', 0, 1, 'C');
            $pdf->Ln(5);
            
            // Faculty Information Box
            $pdf->SetFillColor(240, 248, 255); // Light blue background
            $pdf->SetDrawColor(70, 130, 180);
            $pdf->SetLineWidth(0.5);
            $pdf->Rect($margins['left'], $pdf->GetY(), $usableWidth, 35, 'DF');
            
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 8, 'FACULTY INFORMATION', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 11);
            $pdf->SetTextColor(0, 0, 0);
            
            // Two column layout for faculty info
            $leftX = $margins['left'] + 10;
            $rightX = $leftX + ($usableWidth / 2);
            $currentY = $pdf->GetY();
            
            // Left column
            $pdf->SetXY($leftX, $currentY);
            $pdf->Cell(80, 6, 'Name: ' . ($faculty['first_name'] ?? '') . ' ' . ($faculty['last_name'] ?? ''), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(80, 6, 'Employee ID: ' . ($faculty['employee_id'] ?? 'N/A'), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(80, 6, 'Department: ' . ($faculty['department'] ?? 'N/A'), 0, 1);
            
            // Right column
            $pdf->SetXY($rightX, $currentY);
            $pdf->Cell(80, 6, 'Email: ' . ($faculty['email'] ?? 'N/A'), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(80, 6, 'Academic Year: ' . date('Y') . '-' . (date('Y') + 1), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(80, 6, 'Generated: ' . date('M j, Y g:i A'), 0, 1);
            
            $pdf->Ln(15);
            
            if (empty($subjects)) {
                $pdf->SetFont('helvetica', 'I', 14);
                $pdf->SetTextColor(128, 128, 128);
                $pdf->Cell(0, 15, 'No subject assignments found for this faculty.', 0, 1, 'C');
            } else {
                // Subject Assignments Title
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->SetTextColor(25, 25, 112);
                $pdf->Cell(0, 10, 'ASSIGNED SUBJECTS', 0, 1, 'C');
                $pdf->Ln(5);
                
                // Enhanced table with better styling for landscape
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(70, 130, 180); // Header background
                $pdf->SetTextColor(255, 255, 255); // White text
                $pdf->SetDrawColor(50, 50, 50);
                $pdf->SetLineWidth(0.3);
                
                // Column widths optimized for landscape orientation
                $colRatios = [0.12, 0.25, 0.08, 0.15, 0.12, 0.10, 0.18];
                $colWidths = array_map(function($r) use ($usableWidth) { return round($usableWidth * $r, 2); }, $colRatios);
                $headers = ['CODE', 'SUBJECT NAME', 'CREDITS', 'DEPARTMENT', 'STUDENTS', 'CLASSES', 'SCHEDULE'];
                
                foreach ($headers as $i => $h) {
                    $pdf->Cell($colWidths[$i], 10, $h, 1, $i === count($headers)-1 ? 1 : 0, 'C', true);
                }
                
                // Table content with alternating colors
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                
                $rowCount = 0;
                foreach ($subjects as $subject) {
                    // Alternating row colors
                    if ($rowCount % 2 == 0) {
                        $pdf->SetFillColor(248, 249, 250); // Light gray
                    } else {
                        $pdf->SetFillColor(255, 255, 255); // White
                    }
                    
                    // Handle long subject names
                    $subjectName = $subject['subject_name'];
                    if (mb_strlen($subjectName) > 35) {
                        $subjectName = mb_substr($subjectName, 0, 32) . '...';
                    }
                    
                    // Handle schedule info
                    $scheduleInfo = $subject['schedule_info'] ?? 'Not Scheduled';
                    if (mb_strlen($scheduleInfo) > 25) {
                        $scheduleInfo = mb_substr($scheduleInfo, 0, 22) . '...';
                    }
                    
                    $pdf->Cell($colWidths[0], 8, $subject['subject_code'], 1, 0, 'C', true);
                    $pdf->Cell($colWidths[1], 8, $subjectName, 1, 0, 'L', true);
                    $pdf->Cell($colWidths[2], 8, $subject['credits'] ?? '3', 1, 0, 'C', true);
                    $pdf->Cell($colWidths[3], 8, $subject['department_code'] ?? 'N/A', 1, 0, 'C', true);
                    $pdf->Cell($colWidths[4], 8, $subject['enrolled_students'] ?? '0', 1, 0, 'C', true);
                    $pdf->Cell($colWidths[5], 8, $subject['scheduled_classes'] ?? '0', 1, 0, 'C', true);
                    $pdf->Cell($colWidths[6], 8, $scheduleInfo, 1, 1, 'L', true);
                    
                    $rowCount++;
                }
                
                // Summary section
                $pdf->Ln(10);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->SetTextColor(25, 25, 112);
                $pdf->Cell(0, 8, 'ASSIGNMENT SUMMARY', 0, 1, 'L');
                
                $pdf->SetFont('helvetica', '', 10);
                $pdf->SetTextColor(0, 0, 0);
                $totalSubjects = count($subjects);
                $totalStudents = array_sum(array_column($subjects, 'enrolled_students'));
                $totalClasses = array_sum(array_column($subjects, 'scheduled_classes'));
                $totalCredits = array_sum(array_column($subjects, 'credits'));
                
                $pdf->Cell(0, 6, '• Total Assigned Subjects: ' . $totalSubjects, 0, 1);
                $pdf->Cell(0, 6, '• Total Credit Hours: ' . $totalCredits, 0, 1);
                $pdf->Cell(0, 6, '• Total Enrolled Students: ' . $totalStudents, 0, 1);
                $pdf->Cell(0, 6, '• Total Scheduled Classes: ' . $totalClasses, 0, 1);
            }
            
            // Footer with page numbers and branding
            $pdf->SetY(-20);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(128, 128, 128);
            $pdf->Cell(0, 5, 'Generated by University Timetable Management System', 0, 1, 'C');
            $pdf->Cell(0, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');
            
            // Save the PDF
            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');
            
            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.pdf'
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating subject assignments PDF: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate Subject Assignments Excel with detailed information
     */
    private function generateSubjectAssignmentsExcel($faculty, $subjects, $filename) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('University Timetable System')
                ->setTitle('Faculty Subject Assignments')
                ->setSubject('Subject Assignment Details')
                ->setDescription('Detailed subject assignments with schedule and enrollment information');
            
            // Set title
            $sheet->setTitle('Subject Assignments');
            $sheet->setCellValue('A1', 'Faculty Subject Assignments');
            $sheet->mergeCells('A1:I1');
            
            // Title styling
            $titleStyle = [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F8FF']]
            ];
            $sheet->getStyle('A1')->applyFromArray($titleStyle);
            
            // Faculty Information Section
            $row = 3;
            $sheet->setCellValue('A' . $row, 'FACULTY INFORMATION');
            $sheet->mergeCells('A' . $row . ':I' . $row);
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F3FF']]
            ]);
            
            $row++;
            // Faculty info in columns
            $sheet->setCellValue('A' . $row, 'Name:');
            $sheet->setCellValue('B' . $row, ($faculty['first_name'] ?? '') . ' ' . ($faculty['last_name'] ?? ''));
            $sheet->setCellValue('D' . $row, 'Employee ID:');
            $sheet->setCellValue('E' . $row, $faculty['employee_id'] ?? 'N/A');
            $sheet->setCellValue('G' . $row, 'Email:');
            $sheet->setCellValue('H' . $row, $faculty['email'] ?? 'N/A');
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Department:');
            $sheet->setCellValue('B' . $row, $faculty['department'] ?? 'N/A');
            $sheet->setCellValue('D' . $row, 'Academic Year:');
            $sheet->setCellValue('E' . $row, date('Y') . '-' . (date('Y') + 1));
            $sheet->setCellValue('G' . $row, 'Generated:');
            $sheet->setCellValue('H' . $row, date('M j, Y g:i A'));
            
            // Style faculty info section
            $infoStyle = [
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ];
            $sheet->getStyle('A' . ($row-1) . ':A' . $row)->applyFromArray($infoStyle);
            $sheet->getStyle('D' . ($row-1) . ':D' . $row)->applyFromArray($infoStyle);
            $sheet->getStyle('G' . ($row-1) . ':G' . $row)->applyFromArray($infoStyle);
            
            $row += 3;
            
            if (empty($subjects)) {
                $sheet->setCellValue('A' . $row, 'No subject assignments found for this faculty.');
                $sheet->mergeCells('A' . $row . ':I' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray([
                    'font' => ['italic' => true, 'size' => 12, 'color' => ['rgb' => '666666']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);
            } else {
                // Subject assignments section title
                $sheet->setCellValue('A' . $row, 'ASSIGNED SUBJECTS');
                $sheet->mergeCells('A' . $row . ':I' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E3A8A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F3FF']]
                ]);
                
                $row += 2;
                
                // Enhanced table headers
                $headers = ['SUBJECT CODE', 'SUBJECT NAME', 'CREDITS', 'DEPARTMENT', 'DESCRIPTION', 'ENROLLED STUDENTS', 'SCHEDULED CLASSES', 'PREREQUISITES', 'SCHEDULE INFO'];
                foreach ($headers as $index => $header) {
                    $col = chr(65 + $index); // A, B, C, etc.
                    $sheet->setCellValue($col . $row, $header);
                }
                
                // Header styling
                $headerStyle = [
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4682B4']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
                ];
                $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($headerStyle);
                
                $row++;
                $startDataRow = $row;
                
                // Table content with enhanced styling
                foreach ($subjects as $index => $subject) {
                    $sheet->setCellValue('A' . $row, $subject['subject_code']);
                    $sheet->setCellValue('B' . $row, $subject['subject_name']);
                    $sheet->setCellValue('C' . $row, $subject['credits'] ?? '3');
                    $sheet->setCellValue('D' . $row, $subject['department_name'] ?? 'N/A');
                    $sheet->setCellValue('E' . $row, $subject['description'] ?? 'No description available');
                    $sheet->setCellValue('F' . $row, $subject['enrolled_students'] ?? '0');
                    $sheet->setCellValue('G' . $row, $subject['scheduled_classes'] ?? '0');
                    $sheet->setCellValue('H' . $row, $subject['prerequisites'] ?? 'None');
                    $sheet->setCellValue('I' . $row, $subject['schedule_info'] ?? 'Not Scheduled');
                    
                    // Alternating row colors
                    $fillColor = ($index % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
                    $rowStyle = [
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
                    ];
                    $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($rowStyle);
                    
                    // Center align numeric columns
                    $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    
                    $row++;
                }
                
                // Summary section
                $row += 2;
                $sheet->setCellValue('A' . $row, 'ASSIGNMENT SUMMARY');
                $sheet->mergeCells('A' . $row . ':I' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E3A8A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F3FF']]
                ]);
                
                $row++;
                $totalSubjects = count($subjects);
                $totalStudents = array_sum(array_column($subjects, 'enrolled_students'));
                $totalClasses = array_sum(array_column($subjects, 'scheduled_classes'));
                $totalCredits = array_sum(array_column($subjects, 'credits'));
                
                $sheet->setCellValue('A' . $row, '• Total Assigned Subjects: ' . $totalSubjects);
                $row++;
                $sheet->setCellValue('A' . $row, '• Total Credit Hours: ' . $totalCredits);
                $row++;
                $sheet->setCellValue('A' . $row, '• Total Enrolled Students: ' . $totalStudents);
                $row++;
                $sheet->setCellValue('A' . $row, '• Total Scheduled Classes: ' . $totalClasses);
                
                // Style summary
                $summaryStyle = [
                    'font' => ['size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
                ];
                $sheet->getStyle('A' . ($row-3) . ':A' . $row)->applyFromArray($summaryStyle);
            }
            
            // Auto-size columns
            foreach (range('A', 'I') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Set minimum column widths for better readability
            $sheet->getColumnDimension('A')->setWidth(12); // Subject Code
            $sheet->getColumnDimension('B')->setWidth(30); // Subject Name
            $sheet->getColumnDimension('C')->setWidth(10); // Credits
            $sheet->getColumnDimension('D')->setWidth(15); // Department
            $sheet->getColumnDimension('E')->setWidth(35); // Description
            $sheet->getColumnDimension('F')->setWidth(12); // Students
            $sheet->getColumnDimension('G')->setWidth(12); // Classes
            $sheet->getColumnDimension('H')->setWidth(20); // Prerequisites
            $sheet->getColumnDimension('I')->setWidth(25); // Schedule Info
            
            // Save the Excel file
            $writer = new Xlsx($spreadsheet);
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer->save($filepath);
            
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating subject assignments Excel: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate Class Summary PDF with landscape orientation
     */
    private function generateClassSummaryPDF($faculty, $classSummary, $filename, $semester) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Timetable Management System');
            $pdf->SetAuthor('University Administration');
            $pdf->SetTitle('Faculty Class Summary');
            $pdf->SetSubject('Class Summary Report');
            
            // Set margins and auto page breaks
            $pdf->SetMargins(15, 25, 15);
            $pdf->SetHeaderMargin(10);
            $pdf->SetFooterMargin(15);
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Add a page in landscape orientation
            $pdf->AddPage('L');
            
            // Calculate usable width based on current page and margins
            $margins = $pdf->getMargins();
            $usableWidth = $pdf->getPageWidth() - ($margins['left'] + $margins['right']);
            
            // Title Header
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(25, 25, 112); // Dark blue
            $pdf->Cell(0, 10, 'FACULTY CLASS SUMMARY', 0, 1, 'C');
            $pdf->Ln(5);
            
            // Faculty Information Box
            $pdf->SetFillColor(240, 248, 255); // Light blue background
            $pdf->SetDrawColor(70, 130, 180);
            $pdf->SetLineWidth(0.5);
            $pdf->Rect($margins['left'], $pdf->GetY(), $usableWidth, 35, 'DF');
            
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(25, 25, 112);
            $pdf->Cell(0, 8, 'FACULTY INFORMATION', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 11);
            $pdf->SetTextColor(0, 0, 0);
            
            // Two column layout for faculty info
            $leftX = $margins['left'] + 10;
            $rightX = $leftX + ($usableWidth / 2);
            $currentY = $pdf->GetY();
            
            // Left column
            $pdf->SetXY($leftX, $currentY);
            $pdf->Cell(80, 6, 'Name: ' . ($faculty['first_name'] ?? '') . ' ' . ($faculty['last_name'] ?? ''), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(80, 6, 'Employee ID: ' . ($faculty['employee_id'] ?? 'N/A'), 0, 1);
            $pdf->SetX($leftX);
            $pdf->Cell(80, 6, 'Department: ' . ($faculty['department'] ?? 'N/A'), 0, 1);
            
            // Right column
            $pdf->SetXY($rightX, $currentY);
            $pdf->Cell(80, 6, 'Email: ' . ($faculty['email'] ?? 'N/A'), 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(80, 6, 'Semester: ' . $semester . ' (' . date('Y') . '-' . (date('Y') + 1) . ')', 0, 1);
            $pdf->SetX($rightX);
            $pdf->Cell(80, 6, 'Generated: ' . date('M j, Y g:i A'), 0, 1);
            
            $pdf->Ln(15);
            
            if (empty($classSummary)) {
                $pdf->SetFont('helvetica', 'I', 14);
                $pdf->SetTextColor(128, 128, 128);
                $pdf->Cell(0, 15, 'No classes found for this faculty in the selected semester.', 0, 1, 'C');
            } else {
                // Class Summary Title
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->SetTextColor(25, 25, 112);
                $pdf->Cell(0, 10, 'CLASS SUMMARY', 0, 1, 'C');
                $pdf->Ln(5);
                
                // Enhanced table with better styling for landscape
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetFillColor(70, 130, 180); // Header background
                $pdf->SetTextColor(255, 255, 255); // White text
                $pdf->SetDrawColor(50, 50, 50);
                $pdf->SetLineWidth(0.3);
                
                // Column widths optimized for landscape orientation
                $colRatios = [0.10, 0.20, 0.08, 0.08, 0.15, 0.12, 0.10, 0.10, 0.07];
                $colWidths = array_map(function($r) use ($usableWidth) { return round($usableWidth * $r, 2); }, $colRatios);
                $headers = ['CODE', 'SUBJECT', 'SEC', 'DAY', 'TIME', 'ROOM', 'ENROLLED', 'CAPACITY', 'UTIL%'];
                
                foreach ($headers as $i => $h) {
                    $pdf->Cell($colWidths[$i], 10, $h, 1, $i === count($headers)-1 ? 1 : 0, 'C', true);
                }
                
                // Table content with alternating colors
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(0, 0, 0);
                
                $rowCount = 0;
                $totalEnrolled = 0;
                $totalCapacity = 0;
                
                foreach ($classSummary as $class) {
                    // Alternating row colors
                    if ($rowCount % 2 == 0) {
                        $pdf->SetFillColor(248, 249, 250); // Light gray
                    } else {
                        $pdf->SetFillColor(255, 255, 255); // White
                    }
                    
                    // Handle long subject names
                    $subjectName = $class['subject_name'];
                    if (mb_strlen($subjectName) > 25) {
                        $subjectName = mb_substr($subjectName, 0, 22) . '...';
                    }
                    
                    // Format time
                    $timeRange = date('g:i A', strtotime($class['start_time'])) . '-' . date('g:i A', strtotime($class['end_time']));
                    
                    // Room info
                    $roomInfo = $class['room_number'] . ' (' . $class['building'] . ')';
                    if (mb_strlen($roomInfo) > 15) {
                        $roomInfo = $class['room_number'];
                    }
                    
                    $enrolled = $class['enrolled_students'] ?? 0;
                    $capacity = $class['capacity'] ?? 0;
                    $utilization = $class['capacity_utilization'] ?? 0;
                    
                    $totalEnrolled += $enrolled;
                    $totalCapacity += $capacity;
                    
                    $pdf->Cell($colWidths[0], 8, $class['subject_code'], 1, 0, 'C', true);
                    $pdf->Cell($colWidths[1], 8, $subjectName, 1, 0, 'L', true);
                    $pdf->Cell($colWidths[2], 8, $class['section'] ?? 'A', 1, 0, 'C', true);
                    $pdf->Cell($colWidths[3], 8, strtoupper(substr($class['day_of_week'], 0, 3)), 1, 0, 'C', true);
                    $pdf->Cell($colWidths[4], 8, $timeRange, 1, 0, 'C', true);
                    $pdf->Cell($colWidths[5], 8, $roomInfo, 1, 0, 'C', true);
                    $pdf->Cell($colWidths[6], 8, $enrolled, 1, 0, 'C', true);
                    $pdf->Cell($colWidths[7], 8, $capacity, 1, 0, 'C', true);
                    $pdf->Cell($colWidths[8], 8, $utilization . '%', 1, 1, 'C', true);
                    
                    $rowCount++;
                }
                
                // Summary section
                $pdf->Ln(10);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->SetTextColor(25, 25, 112);
                $pdf->Cell(0, 8, 'CLASS SUMMARY STATISTICS', 0, 1, 'L');
                
                $pdf->SetFont('helvetica', '', 10);
                $pdf->SetTextColor(0, 0, 0);
                $totalClasses = count($classSummary);
                $avgUtilization = $totalCapacity > 0 ? round(($totalEnrolled / $totalCapacity) * 100, 1) : 0;
                $uniqueSubjects = count(array_unique(array_column($classSummary, 'subject_code')));
                $uniqueRooms = count(array_unique(array_column($classSummary, 'room_number')));
                
                $pdf->Cell(0, 6, '• Total Classes: ' . $totalClasses, 0, 1);
                $pdf->Cell(0, 6, '• Unique Subjects: ' . $uniqueSubjects, 0, 1);
                $pdf->Cell(0, 6, '• Total Enrolled Students: ' . $totalEnrolled, 0, 1);
                $pdf->Cell(0, 6, '• Total Classroom Capacity: ' . $totalCapacity, 0, 1);
                $pdf->Cell(0, 6, '• Average Utilization: ' . $avgUtilization . '%', 0, 1);
                $pdf->Cell(0, 6, '• Classrooms Used: ' . $uniqueRooms, 0, 1);
            }
            
            // Footer with page numbers and branding
            $pdf->SetY(-20);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(128, 128, 128);
            $pdf->Cell(0, 5, 'Generated by University Timetable Management System', 0, 1, 'C');
            $pdf->Cell(0, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');
            
            // Save the PDF
            $filepath = EXPORTS_PATH . $filename . '.pdf';
            $pdf->Output($filepath, 'F');
            
            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.pdf'
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating class summary PDF: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate Class Summary Excel with detailed information
     */
    private function generateClassSummaryExcel($faculty, $classSummary, $filename, $semester) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('University Timetable System')
                ->setTitle('Faculty Class Summary')
                ->setSubject('Class Summary Report')
                ->setDescription('Detailed class summary with enrollment and capacity information');
            
            // Set title
            $sheet->setTitle('Class Summary');
            $sheet->setCellValue('A1', 'Faculty Class Summary');
            $sheet->mergeCells('A1:K1');
            
            // Title styling
            $titleStyle = [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F8FF']]
            ];
            $sheet->getStyle('A1')->applyFromArray($titleStyle);
            
            // Faculty Information Section
            $row = 3;
            $sheet->setCellValue('A' . $row, 'FACULTY INFORMATION');
            $sheet->mergeCells('A' . $row . ':K' . $row);
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F3FF']]
            ]);
            
            $row++;
            // Faculty info in columns
            $sheet->setCellValue('A' . $row, 'Name:');
            $sheet->setCellValue('B' . $row, ($faculty['first_name'] ?? '') . ' ' . ($faculty['last_name'] ?? ''));
            $sheet->setCellValue('D' . $row, 'Employee ID:');
            $sheet->setCellValue('E' . $row, $faculty['employee_id'] ?? 'N/A');
            $sheet->setCellValue('G' . $row, 'Email:');
            $sheet->setCellValue('H' . $row, $faculty['email'] ?? 'N/A');
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Department:');
            $sheet->setCellValue('B' . $row, $faculty['department'] ?? 'N/A');
            $sheet->setCellValue('D' . $row, 'Semester:');
            $sheet->setCellValue('E' . $row, $semester . ' (' . date('Y') . '-' . (date('Y') + 1) . ')');
            $sheet->setCellValue('G' . $row, 'Generated:');
            $sheet->setCellValue('H' . $row, date('M j, Y g:i A'));
            
            // Style faculty info section
            $infoStyle = [
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ];
            $sheet->getStyle('A' . ($row-1) . ':A' . $row)->applyFromArray($infoStyle);
            $sheet->getStyle('D' . ($row-1) . ':D' . $row)->applyFromArray($infoStyle);
            $sheet->getStyle('G' . ($row-1) . ':G' . $row)->applyFromArray($infoStyle);
            
            $row += 3;
            
            if (empty($classSummary)) {
                $sheet->setCellValue('A' . $row, 'No classes found for this faculty in the selected semester.');
                $sheet->mergeCells('A' . $row . ':K' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray([
                    'font' => ['italic' => true, 'size' => 12, 'color' => ['rgb' => '666666']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);
            } else {
                // Class summary section title
                $sheet->setCellValue('A' . $row, 'CLASS SUMMARY');
                $sheet->mergeCells('A' . $row . ':K' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E3A8A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F3FF']]
                ]);
                
                $row += 2;
                
                // Enhanced table headers
                $headers = ['SUBJECT CODE', 'SUBJECT NAME', 'SECTION', 'DAY', 'TIME SLOT', 'ROOM', 'BUILDING', 'ENROLLED', 'CAPACITY', 'UTILIZATION %', 'CREDITS'];
                foreach ($headers as $index => $header) {
                    $col = chr(65 + $index); // A, B, C, etc.
                    $sheet->setCellValue($col . $row, $header);
                }
                
                // Header styling
                $headerStyle = [
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4682B4']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
                ];
                $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray($headerStyle);
                
                $row++;
                $startDataRow = $row;
                
                // Table content with enhanced styling
                $totalEnrolled = 0;
                $totalCapacity = 0;
                
                foreach ($classSummary as $index => $class) {
                    $timeSlot = date('g:i A', strtotime($class['start_time'])) . ' - ' . date('g:i A', strtotime($class['end_time']));
                    $enrolled = $class['enrolled_students'] ?? 0;
                    $capacity = $class['capacity'] ?? 0;
                    $utilization = $class['capacity_utilization'] ?? 0;
                    
                    $totalEnrolled += $enrolled;
                    $totalCapacity += $capacity;
                    
                    $sheet->setCellValue('A' . $row, $class['subject_code']);
                    $sheet->setCellValue('B' . $row, $class['subject_name']);
                    $sheet->setCellValue('C' . $row, $class['section'] ?? 'A');
                    $sheet->setCellValue('D' . $row, $class['day_of_week']);
                    $sheet->setCellValue('E' . $row, $timeSlot);
                    $sheet->setCellValue('F' . $row, $class['room_number']);
                    $sheet->setCellValue('G' . $row, $class['building']);
                    $sheet->setCellValue('H' . $row, $enrolled);
                    $sheet->setCellValue('I' . $row, $capacity);
                    $sheet->setCellValue('J' . $row, $utilization . '%');
                    $sheet->setCellValue('K' . $row, $class['credits'] ?? '3');
                    
                    // Alternating row colors
                    $fillColor = ($index % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
                    $rowStyle = [
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]]
                    ];
                    $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray($rowStyle);
                    
                    // Left align subject name
                    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    
                    $row++;
                }
                
                // Summary section
                $row += 2;
                $sheet->setCellValue('A' . $row, 'CLASS SUMMARY STATISTICS');
                $sheet->mergeCells('A' . $row . ':K' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E3A8A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F3FF']]
                ]);
                
                $row++;
                $totalClasses = count($classSummary);
                $avgUtilization = $totalCapacity > 0 ? round(($totalEnrolled / $totalCapacity) * 100, 1) : 0;
                $uniqueSubjects = count(array_unique(array_column($classSummary, 'subject_code')));
                $uniqueRooms = count(array_unique(array_column($classSummary, 'room_number')));
                
                $sheet->setCellValue('A' . $row, '• Total Classes: ' . $totalClasses);
                $row++;
                $sheet->setCellValue('A' . $row, '• Unique Subjects: ' . $uniqueSubjects);
                $row++;
                $sheet->setCellValue('A' . $row, '• Total Enrolled Students: ' . $totalEnrolled);
                $row++;
                $sheet->setCellValue('A' . $row, '• Total Classroom Capacity: ' . $totalCapacity);
                $row++;
                $sheet->setCellValue('A' . $row, '• Average Utilization: ' . $avgUtilization . '%');
                $row++;
                $sheet->setCellValue('A' . $row, '• Classrooms Used: ' . $uniqueRooms);
                
                // Style summary
                $summaryStyle = [
                    'font' => ['size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
                ];
                $sheet->getStyle('A' . ($row-5) . ':A' . $row)->applyFromArray($summaryStyle);
            }
            
            // Auto-size columns
            foreach (range('A', 'K') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            // Set minimum column widths for better readability
            $sheet->getColumnDimension('A')->setWidth(12); // Subject Code
            $sheet->getColumnDimension('B')->setWidth(25); // Subject Name
            $sheet->getColumnDimension('C')->setWidth(8);  // Section
            $sheet->getColumnDimension('D')->setWidth(12); // Day
            $sheet->getColumnDimension('E')->setWidth(15); // Time Slot
            $sheet->getColumnDimension('F')->setWidth(10); // Room
            $sheet->getColumnDimension('G')->setWidth(12); // Building
            $sheet->getColumnDimension('H')->setWidth(10); // Enrolled
            $sheet->getColumnDimension('I')->setWidth(10); // Capacity
            $sheet->getColumnDimension('J')->setWidth(12); // Utilization
            $sheet->getColumnDimension('K')->setWidth(8);  // Credits
            
            // Save the Excel file
            $writer = new Xlsx($spreadsheet);
            $filepath = EXPORTS_PATH . $filename . '.xlsx';
            $writer->save($filepath);
            
            return [
                'success' => true,
                'filename' => $filename . '.xlsx',
                'filepath' => $filepath,
                'download_url' => EXPORTS_URL . $filename . '.xlsx'
            ];
            
        } catch (Exception $e) {
            $this->log("Error generating class summary Excel: " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
