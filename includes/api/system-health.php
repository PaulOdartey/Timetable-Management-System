<?php
/**
 * System Health API
 * Monitors system health indicators
 */

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session and check authentication
session_start();

try {
    // Verify admin access
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access');
    }

    $db = Database::getInstance();
    
    // Test database connection
    $dbStatus = 'healthy';
    try {
        $db->fetchRow("SELECT 1");
    } catch (Exception $e) {
        $dbStatus = 'error';
    }

    // Check last backup
    $lastBackup = $db->fetchColumn("SELECT MAX(created_at) FROM backup_logs WHERE status = 'completed'");
    $lastBackupRecent = false;
    
    if ($lastBackup) {
        $lastBackupRecent = strtotime($lastBackup) > strtotime('-24 hours');
    }

    // Get active sessions (users logged in within last 24 hours)
    $activeSessions = $db->fetchColumn("
        SELECT COUNT(DISTINCT user_id) 
        FROM users 
        WHERE last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");

    // Check storage usage (simplified - you can expand this)
    $storageStatus = 'normal';
    $totalTables = $db->fetchColumn("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ");

    if ($totalTables < 10) {
        $storageStatus = 'warning';
    }

    // Check error rate (from audit logs)
    $recentErrors = $db->fetchColumn("
        SELECT COUNT(*) FROM audit_logs 
        WHERE description LIKE '%error%' 
        OR description LIKE '%failed%'
        AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");

    $errorRate = 'low';
    if ($recentErrors > 10) {
        $errorRate = 'high';
    } elseif ($recentErrors > 5) {
        $errorRate = 'medium';
    }

    // Memory usage (simplified - you can use system functions for real memory usage)
    $memoryUsage = 'normal';

    $response = [
        'success' => true,
        'timestamp' => time(),
        'health' => [
            'database_status' => $dbStatus,
            'last_backup_recent' => $lastBackupRecent,
            'last_backup' => $lastBackup,
            'active_sessions' => $activeSessions,
            'storage_status' => $storageStatus,
            'error_rate' => $errorRate,
            'recent_errors' => $recentErrors,
            'memory_usage' => $memoryUsage,
            'system_uptime' => 'normal' // You can implement real uptime checking
        ],
        'metrics' => [
            'total_users' => $db->fetchColumn("SELECT COUNT(*) FROM users"),
            'total_sessions_today' => $activeSessions,
            'database_size' => $totalTables . ' tables',
            'last_maintenance' => $db->fetchColumn("
                SELECT MAX(timestamp) FROM audit_logs 
                WHERE action = 'SYSTEM_MAINTENANCE'
            ")
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'health' => [
            'database_status' => 'error',
            'last_backup_recent' => false,
            'active_sessions' => 0,
            'storage_status' => 'unknown',
            'error_rate' => 'high'
        ]
    ]);
}
?>