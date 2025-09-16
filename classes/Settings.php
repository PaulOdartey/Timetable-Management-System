<?php
/**
 * Settings Management Class
 * Timetable Management System
 * 
 * Works with existing database schema and tables
 */

class Settings {
    private $db;
    private $cache = [];
    private $settingsSchema;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->initializeSchema();
        $this->ensureDefaultSettings();
    }
    
    /**
     * Initialize settings schema
     */
    private function initializeSchema() {
        $this->settingsSchema = [
            'general' => [
                'title' => 'General Settings',
                'icon' => 'fas fa-cog',
                'description' => 'Basic system configuration and preferences',
                'settings' => [
                    'system_name' => [
                        'type' => 'text',
                        'label' => 'Site Name',
                        'description' => 'Name of your institution or system',
                        'validation' => ['required', ['max' => 100]],
                        'default' => 'Timetable Management System'
                    ],
                    'site_description' => [
                        'type' => 'textarea',
                        'label' => 'Site Description',
                        'description' => 'Brief description of your institution',
                        'validation' => [['max' => 500]],
                        'default' => 'Professional timetable and schedule management system'
                    ],
                    'admin_email' => [
                        'type' => 'email',
                        'label' => 'Administrator Email',
                        'description' => 'Primary contact email for system administrators',
                        'validation' => ['required', 'email'],
                        'default' => 'admin@example.com'
                    ],
                    'timezone' => [
                        'type' => 'select',
                        'label' => 'System Timezone',
                        'description' => 'Default timezone for the system',
                        'options' => [
                            'UTC' => 'UTC',
                            'America/New_York' => 'Eastern Time (EST/EDT)',
                            'America/Chicago' => 'Central Time (CST/CDT)',
                            'America/Denver' => 'Mountain Time (MST/MDT)',
                            'America/Los_Angeles' => 'Pacific Time (PST/PDT)',
                            'Europe/London' => 'London (GMT/BST)',
                            'Europe/Paris' => 'Paris (CET/CEST)',
                            'Asia/Tokyo' => 'Tokyo (JST)',
                            'Asia/Shanghai' => 'Shanghai (CST)',
                            'Australia/Sydney' => 'Sydney (AEST/AEDT)'
                        ],
                        'validation' => ['required'],
                        'default' => 'UTC'
                    ],
                    'date_format' => [
                        'type' => 'select',
                        'label' => 'Date Format',
                        'description' => 'How dates should be displayed throughout the system',
                        'options' => [
                            'Y-m-d' => '2025-01-15',
                            'm/d/Y' => '01/15/2025',
                            'd/m/Y' => '15/01/2025',
                            'F j, Y' => 'January 15, 2025',
                            'j F Y' => '15 January 2025'
                        ],
                        'validation' => ['required'],
                        'default' => 'Y-m-d'
                    ],
                    'time_format' => [
                        'type' => 'select',
                        'label' => 'Time Format',
                        'description' => 'How times should be displayed',
                        'options' => [
                            'H:i' => '24-hour (14:30)',
                            'g:i A' => '12-hour (2:30 PM)'
                        ],
                        'validation' => ['required'],
                        'default' => 'H:i'
                    ],
                    'maintenance_mode' => [
                        'type' => 'boolean',
                        'label' => 'Maintenance Mode',
                        'description' => 'Put the system in maintenance mode (blocks non-admin access)',
                        'default' => false
                    ]
                ]
            ],
            'security' => [
                'title' => 'Security Settings',
                'icon' => 'fas fa-shield-alt',
                'description' => 'Authentication, session, and security configuration',
                'settings' => [
                    'session_timeout' => [
                        'type' => 'integer',
                        'label' => 'Session Timeout (minutes)',
                        'description' => 'How long users can remain idle before being logged out',
                        'validation' => ['integer', ['min' => 5], ['max' => 1440]],
                        'default' => 60
                    ],
                    'password_min_length' => [
                        'type' => 'integer',
                        'label' => 'Minimum Password Length',
                        'description' => 'Minimum number of characters required for passwords',
                        'validation' => ['integer', ['min' => 6], ['max' => 50]],
                        'default' => 8
                    ],
                    'password_require_uppercase' => [
                        'type' => 'boolean',
                        'label' => 'Require Uppercase Letters',
                        'description' => 'Passwords must contain at least one uppercase letter',
                        'default' => true
                    ],
                    'password_require_lowercase' => [
                        'type' => 'boolean',
                        'label' => 'Require Lowercase Letters',
                        'description' => 'Passwords must contain at least one lowercase letter',
                        'default' => true
                    ],
                    'password_require_numbers' => [
                        'type' => 'boolean',
                        'label' => 'Require Numbers',
                        'description' => 'Passwords must contain at least one number',
                        'default' => true
                    ],
                    'password_require_symbols' => [
                        'type' => 'boolean',
                        'label' => 'Require Special Characters',
                        'description' => 'Passwords must contain at least one special character',
                        'default' => false
                    ],
                    'max_login_attempts' => [
                        'type' => 'integer',
                        'label' => 'Maximum Login Attempts',
                        'description' => 'Number of failed attempts before account lockout',
                        'validation' => ['integer', ['min' => 3], ['max' => 10]],
                        'default' => 5
                    ],
                    'lockout_duration' => [
                        'type' => 'integer',
                        'label' => 'Lockout Duration (minutes)',
                        'description' => 'How long accounts remain locked after max attempts',
                        'validation' => ['integer', ['min' => 5], ['max' => 1440]],
                        'default' => 30
                    ]
                ]
            ],
            'notifications' => [
                'title' => 'Notification Settings',
                'icon' => 'fas fa-bell',
                'description' => 'Email notifications and alert configuration',
                'settings' => [
                    'email_notifications_enabled' => [
                        'type' => 'boolean',
                        'label' => 'Enable Email Notifications',
                        'description' => 'Send email notifications for important events',
                        'default' => true
                    ],
                    'smtp_host' => [
                        'type' => 'text',
                        'label' => 'SMTP Host',
                        'description' => 'SMTP server hostname for sending emails',
                        'validation' => [['max' => 255]],
                        'default' => 'localhost'
                    ],
                    'smtp_port' => [
                        'type' => 'integer',
                        'label' => 'SMTP Port',
                        'description' => 'SMTP server port (usually 587 for TLS, 465 for SSL)',
                        'validation' => ['integer', ['min' => 1], ['max' => 65535]],
                        'default' => 587
                    ],
                    'smtp_username' => [
                        'type' => 'text',
                        'label' => 'SMTP Username',
                        'description' => 'Username for SMTP authentication',
                        'validation' => [['max' => 255]],
                        'default' => ''
                    ],
                    'smtp_password' => [
                        'type' => 'password',
                        'label' => 'SMTP Password',
                        'description' => 'Password for SMTP authentication',
                        'validation' => [['max' => 255]],
                        'default' => ''
                    ],
                    'smtp_encryption' => [
                        'type' => 'select',
                        'label' => 'SMTP Encryption',
                        'description' => 'Encryption method for SMTP connection',
                        'options' => [
                            'none' => 'None',
                            'tls' => 'TLS (Recommended)',
                            'ssl' => 'SSL'
                        ],
                        'default' => 'tls'
                    ]
                ]
            ],
            'backup' => [
                'title' => 'Backup Settings',
                'icon' => 'fas fa-database',
                'description' => 'Automated backup and data retention configuration',
                'settings' => [
                    'backup_enabled' => [
                        'type' => 'boolean',
                        'label' => 'Enable Automatic Backups',
                        'description' => 'Automatically create system backups on schedule',
                        'default' => true
                    ],
                    'backup_frequency' => [
                        'type' => 'select',
                        'label' => 'Backup Frequency',
                        'description' => 'How often to create automatic backups',
                        'options' => [
                            '6' => 'Every 6 hours',
                            '12' => 'Every 12 hours',
                            '24' => 'Daily (Recommended)',
                            '168' => 'Weekly',
                            '720' => 'Monthly'
                        ],
                        'default' => '24'
                    ],
                    'backup_retention_days' => [
                        'type' => 'integer',
                        'label' => 'Backup Retention (days)',
                        'description' => 'How long to keep backup files before auto-deletion',
                        'validation' => ['integer', ['min' => 7], ['max' => 365]],
                        'default' => 30
                    ],
                    'backup_location' => [
                        'type' => 'text',
                        'label' => 'Backup Directory',
                        'description' => 'Directory path for storing backup files (relative to project root)',
                        'validation' => [['max' => 500]],
                        'default' => '../backups'
                    ]
                ]
            ],
            'performance' => [
                'title' => 'Performance Settings',
                'icon' => 'fas fa-tachometer-alt',
                'description' => 'System performance and optimization settings',
                'settings' => [
                    'cache_enabled' => [
                        'type' => 'boolean',
                        'label' => 'Enable Caching',
                        'description' => 'Cache frequently accessed data for better performance',
                        'default' => true
                    ],
                    'cache_duration' => [
                        'type' => 'integer',
                        'label' => 'Cache Duration (minutes)',
                        'description' => 'How long to keep cached data before refresh',
                        'validation' => ['integer', ['min' => 5], ['max' => 1440]],
                        'default' => 60
                    ],
                    'max_upload_size' => [
                        'type' => 'integer',
                        'label' => 'Max Upload Size (MB)',
                        'description' => 'Maximum file upload size limit',
                        'validation' => ['integer', ['min' => 1], ['max' => 100]],
                        'default' => 10
                    ],
                    'pagination_limit' => [
                        'type' => 'integer',
                        'label' => 'Records Per Page',
                        'description' => 'Number of records to show per page in listings',
                        'validation' => ['integer', ['min' => 10], ['max' => 100]],
                        'default' => 25
                    ]
                ]
            ],
            'maintenance' => [
                'title' => 'Maintenance Settings',
                'icon' => 'fas fa-tools',
                'description' => 'System cleanup and maintenance configuration',
                'settings' => [
                    'auto_cleanup_enabled' => [
                        'type' => 'boolean',
                        'label' => 'Enable Auto Cleanup',
                        'description' => 'Automatically clean up old data and logs on schedule',
                        'default' => true
                    ],
                    'keep_audit_logs_days' => [
                        'type' => 'integer',
                        'label' => 'Keep Audit Logs (days)',
                        'description' => 'Days to retain audit trail logs before cleanup',
                        'validation' => ['integer', ['min' => 30], ['max' => 1825]],
                        'default' => 365
                    ],
                    'keep_login_attempts_days' => [
                        'type' => 'integer',
                        'label' => 'Keep Login Attempts (days)',
                        'description' => 'Days to retain failed login attempt records',
                        'validation' => ['integer', ['min' => 7], ['max' => 365]],
                        'default' => 30
                    ],
                    'keep_notifications_days' => [
                        'type' => 'integer',
                        'label' => 'Keep Notifications (days)',
                        'description' => 'Days to retain read notifications before cleanup',
                        'validation' => ['integer', ['min' => 7], ['max' => 365]],
                        'default' => 90
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Ensure default settings exist in existing table
     */
    private function ensureDefaultSettings() {
        try {
            foreach ($this->settingsSchema as $categoryKey => $categoryData) {
                foreach ($categoryData['settings'] as $settingKey => $settingSchema) {
                    // Check if setting exists
                    $existing = $this->db->fetchRow("
                        SELECT setting_id FROM system_settings 
                        WHERE setting_key = ? AND is_active = 1
                    ", [$settingKey]);
                    
                    // Insert if doesn't exist
                    if (!$existing) {
                        $this->db->execute("
                            INSERT INTO system_settings 
                            (setting_key, setting_value, setting_type, category, is_active) 
                            VALUES (?, ?, ?, ?, 1)
                        ", [
                            $settingKey,
                            $settingSchema['default'] ?? '',
                            $settingSchema['type'],
                            $categoryKey
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error ensuring default settings: " . $e->getMessage());
        }
    }
    
    // ===========================================
    // CORE SETTINGS METHODS
    // ===========================================
    
    /**
     * Get all settings as key-value pairs
     */
    public function getAllSettings() {
        if (empty($this->cache)) {
            try {
                $settings = $this->db->fetchAll("
                    SELECT setting_key, setting_value, setting_type 
                    FROM system_settings 
                    WHERE is_active = 1
                ");
                
                foreach ($settings as $setting) {
                    $this->cache[$setting['setting_key']] = $this->castValue(
                        $setting['setting_value'], 
                        $setting['setting_type']
                    );
                }
            } catch (Exception $e) {
                error_log("Error loading settings: " . $e->getMessage());
                return [];
            }
        }
        
        return $this->cache;
    }
    
    /**
     * Get all settings organized by category
     */
    public function getAllSettingsByCategory() {
        try {
            $currentSettings = $this->getAllSettings();
            $organizedSettings = [];
            
            foreach ($this->settingsSchema as $categoryKey => $categoryData) {
                $categorySettings = [];
                
                foreach ($categoryData['settings'] as $settingKey => $settingSchema) {
                    $settingValue = $currentSettings[$settingKey] ?? $settingSchema['default'];
                    
                    $categorySettings[$settingKey] = [
                        'key' => $settingKey,
                        'value' => $this->castValue($settingValue, $settingSchema['type']),
                        'schema' => $settingSchema
                    ];
                }
                
                $organizedSettings[$categoryKey] = [
                    'title' => $categoryData['title'],
                    'icon' => $categoryData['icon'],
                    'description' => $categoryData['description'],
                    'settings' => $categorySettings
                ];
            }
            
            return $organizedSettings;
        } catch (Exception $e) {
            error_log("Error getting settings by category: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a specific setting value
     */
    public function get($key, $default = null) {
        $settings = $this->getAllSettings();
        return $settings[$key] ?? $default;
    }
    
    /**
     * Update multiple settings
     */
    public function updateMultipleSettings($settings, $updatedBy) {
        try {
            $this->db->beginTransaction();
            
            $updatedCount = 0;
            $errors = [];
            
            foreach ($settings as $key => $value) {
                $result = $this->updateSingle($key, $value, $updatedBy);
                
                if ($result['success']) {
                    $updatedCount++;
                } else {
                    $errors[] = "{$key}: {$result['message']}";
                }
            }
            
            if (empty($errors)) {
                $this->db->commit();
                $this->clearCache();
                
                // Log the bulk update
                $this->logAuditAction($updatedBy, 'BULK_UPDATE_SETTINGS', 'system_settings', null, [
                    'updated_count' => $updatedCount,
                    'settings_keys' => array_keys($settings)
                ]);
                
                return [
                    'success' => true,
                    'message' => "Successfully updated {$updatedCount} settings.",
                    'updated_count' => $updatedCount
                ];
            } else {
                $this->db->rollback();
                return [
                    'success' => false,
                    'message' => 'Some settings failed to update: ' . implode(', ', $errors),
                    'errors' => $errors
                ];
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error updating multiple settings: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update a single setting
     */
    private function updateSingle($key, $value, $updatedBy) {
        try {
            // Find setting schema
            $schema = $this->findSettingSchema($key);
            if (!$schema) {
                return [
                    'success' => false,
                    'message' => "Setting '{$key}' not found in schema."
                ];
            }
            
            // Validate value
            $validation = $this->validateSettingValue($value, $schema);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            // Get old value for audit log
            $oldValue = $this->get($key);
            
            // Prepare value for storage
            $storageValue = $this->prepareValueForStorage($value, $schema['type']);
            
            // Update in database using existing system_settings table structure
            $this->db->execute("
                UPDATE system_settings 
                SET setting_value = ?, updated_at = NOW()
                WHERE setting_key = ? AND is_active = 1
            ", [$storageValue, $key]);
            
            // Clear the settings cache
            $this->cache = [];
            
            // Log the individual update
            $this->logAuditAction($updatedBy, 'UPDATE_SETTING', 'system_settings', null, [
                'setting_key' => $key,
                'old_value' => $oldValue,
                'new_value' => $value
            ]);
            
            return [
                'success' => true,
                'message' => "Setting '{$key}' updated successfully."
            ];
        } catch (Exception $e) {
            error_log("Error updating setting '{$key}': " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Failed to update setting '{$key}': " . $e->getMessage()
            ];
        }
    }
    
    // ===========================================
    // BACKUP MANAGEMENT METHODS
    // ===========================================
    
    /**
     * Create manual backup using existing backup_logs table structure
     */
    public function createManualBackup($adminUserId, $description = 'Manual backup') {
        try {
            $backupLocation = $this->get('backup_location', '../backups');
            
            // Create backup directory if needed
            if (!is_dir($backupLocation)) {
                if (!mkdir($backupLocation, 0755, true)) {
                    throw new Exception("Cannot create backup directory: {$backupLocation}");
                }
            }
            
            $timestamp = date('Y_m_d_H_i_s');
            $filename = "manual_backup_{$timestamp}.sql";
            $backupFile = $backupLocation . "/" . $filename;
            
            // Get database name
            $dbResult = $this->db->fetchRow("SELECT DATABASE() as db_name");
            $dbName = $dbResult['db_name'] ?? 'timetable_management';
            
            // Include database configuration
            require_once __DIR__ . '/../config/database.php';
            
            // Use full path to mysqldump and include credentials
            $command = sprintf(
                '"C:\\xampp\\mysql\\bin\\mysqldump" --user=%s --password=%s --host=%s --port=%s --single-transaction --routines --triggers --lock-tables=false %s > "%s" 2>"%s"',
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_PORT),
                escapeshellarg($dbName),
                $backupFile,
                $backupFile . '.error.log'
            );
            
            // Execute the command
            exec($command, $output, $returnCode);
            
            // Log the command output for debugging (without exposing password)
            $maskedCommand = preg_replace('/(--password=)([^\s]+)/', '$1*****', $command);
            error_log("Backup command: " . $maskedCommand);
            error_log("Backup output: " . print_r($output, true));
            error_log("Backup return code: " . $returnCode);
            
            if ($returnCode === 0 && file_exists($backupFile)) {
                $fileSize = filesize($backupFile);
                
                // Insert into existing backup_logs table structure
                $this->db->execute("
                    INSERT INTO backup_logs 
                    (filename, backup_type, file_size, frequency, description, status, created_by) 
                    VALUES (?, 'full', ?, 'manual', ?, 'completed', ?)
                ", [$filename, $fileSize, $description, $adminUserId]);
                
                // Log audit action
                $this->logAuditAction($adminUserId, 'CREATE_BACKUP', 'backup_logs', null, [
                    'filename' => $filename,
                    'file_size' => $fileSize,
                    'description' => $description
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Backup created successfully',
                    'backup_file' => $filename,
                    'file_size' => $fileSize
                ];
                
            } else {
                $errorMsg = implode("\n", $output);
                
                // Log failed backup
                $this->db->execute("
                    INSERT INTO backup_logs 
                    (filename, backup_type, file_size, frequency, description, status, created_by) 
                    VALUES (?, 'full', 0, 'manual', ?, 'failed', ?)
                ", [$filename, $description, $adminUserId]);
                
                throw new Exception("Backup command failed: {$errorMsg}");
            }
            
        } catch (Exception $e) {
            error_log("Manual backup failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get backup history using existing table structure
     */
    public function getBackupHistory($limit = 50) {
        try {
            $backups = $this->db->fetchAll("
                SELECT bl.*, u.username as created_by_name
                FROM backup_logs bl
                LEFT JOIN users u ON bl.created_by = u.user_id
                WHERE bl.status != 'deleted'
                ORDER BY bl.created_at DESC 
                LIMIT ?
            ", [$limit]);
            
            return $backups;
        } catch (Exception $e) {
            error_log("Error getting backup history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete backup file using existing table structure
     */
    public function deleteBackup($backupId, $adminUserId) {
        try {
            // Get backup info
            $backup = $this->db->fetchRow("
                SELECT * FROM backup_logs WHERE backup_id = ?
            ", [$backupId]);
            
            if (!$backup) {
                return ['success' => false, 'message' => 'Backup not found'];
            }
            
            $backupLocation = $this->get('backup_location', '../backups');
            $backupPath = $backupLocation . '/' . $backup['filename'];
            
            // Delete physical file if exists
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }
            
            // Update database record using existing structure
            $this->db->execute("
                UPDATE backup_logs 
                SET status = 'deleted', deleted_at = NOW(), deleted_by = ?
                WHERE backup_id = ?
            ", [$adminUserId, $backupId]);
            
            // Log the deletion
            $this->logAuditAction($adminUserId, 'DELETE_BACKUP', 'backup_logs', $backupId, [
                'filename' => $backup['filename'],
                'original_size' => $backup['file_size']
            ]);
            
            return ['success' => true, 'message' => 'Backup deleted successfully'];
            
        } catch (Exception $e) {
            error_log("Error deleting backup: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete backup: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get backup status and statistics
     */
    public function getBackupStatus() {
        try {
            $stats = $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_backups,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_backups,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_backups,
                    SUM(CASE WHEN status = 'completed' THEN COALESCE(file_size, 0) ELSE 0 END) as total_size,
                    MAX(created_at) as last_backup_date
                FROM backup_logs 
                WHERE status != 'deleted'
            ");
            
            return [
                'statistics' => $stats ?: [
                    'total_backups' => 0,
                    'successful_backups' => 0,
                    'failed_backups' => 0,
                    'total_size' => 0,
                    'last_backup_date' => null
                ],
                'frequency_hours' => $this->get('backup_frequency', 24),
                'retention_days' => $this->get('backup_retention_days', 30)
            ];
            
        } catch (Exception $e) {
            error_log("Error getting backup status: " . $e->getMessage());
            return [
                'error' => 'Unable to retrieve backup status: ' . $e->getMessage(),
                'statistics' => [
                    'total_backups' => 0,
                    'successful_backups' => 0,
                    'failed_backups' => 0,
                    'total_size' => 0,
                    'last_backup_date' => null
                ]
            ];
        }
    }
    
    // ===========================================
    // AUDIT LOG METHODS (using existing table structure)
    // ===========================================
    
    /**
     * Get audit logs using existing audit_logs table
     */
    public function getAuditLogs($limit = 100, $offset = 0, $filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Apply filters
            if (!empty($filters['user_id'])) {
                $whereConditions[] = "al.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['action'])) {
                $whereConditions[] = "al.action LIKE ?";
                $params[] = '%' . $filters['action'] . '%';
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "DATE(al.timestamp) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "DATE(al.timestamp) <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Add limit and offset
            $params[] = $limit;
            $params[] = $offset;
            
            // Use actual audit_logs table structure: user_id, action, description, timestamp
            $auditLogs = $this->db->fetchAll("
                SELECT al.*, 
                       al.timestamp as created_at,
                       '' as table_name,
                       NULL as record_id,
                       '' as ip_address,
                       al.description as details,
                       COALESCE(u.username, 'System') as username,
                       CASE 
                           WHEN u.role = 'student' THEN CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))
                           WHEN u.role = 'faculty' THEN CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.last_name, ''))
                           WHEN u.role = 'admin' THEN CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.last_name, ''))
                           ELSE COALESCE(u.username, 'System')
                       END as full_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
                LEFT JOIN faculty f ON u.user_id = f.user_id AND u.role = 'faculty'
                LEFT JOIN admin_profiles a ON u.user_id = a.user_id AND u.role = 'admin'
                {$whereClause}
                ORDER BY al.timestamp DESC
                LIMIT ? OFFSET ?
            ", $params);
            
            // Get total count
            $countParams = array_slice($params, 0, -2);
            $totalResult = $this->db->fetchRow("
                SELECT COUNT(*) as total
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                {$whereClause}
            ", $countParams);
            
            return [
                'logs' => $auditLogs,
                'total' => $totalResult['total'] ?? 0,
                'limit' => $limit,
                'offset' => $offset
            ];
            
        } catch (Exception $e) {
            error_log("Error getting audit logs: " . $e->getMessage());
            return [
                'logs' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset
            ];
        }
    }
    
    /**
     * Log audit action using actual audit_logs table structure: user_id, action, description, timestamp
     */
    private function logAuditAction($userId, $action, $tableName = null, $recordId = null, $details = []) {
        try {
            // Create user-friendly description based on action type
            $description = $this->createUserFriendlyDescription($action, $details);
            
            $this->db->execute("
                INSERT INTO audit_logs 
                (user_id, action, description, timestamp) 
                VALUES (?, ?, ?, NOW())
            ", [
                $userId,
                $action,
                $description
            ]);
        } catch (Exception $e) {
            error_log("Error logging audit action: " . $e->getMessage());
        }
    }
    
    /**
     * Create user-friendly descriptions for audit logs
     */
    private function createUserFriendlyDescription($action, $details = []) {
        switch ($action) {
            case 'BULK_UPDATE_SETTINGS':
                if (is_array($details) && isset($details['updated_count'])) {
                    $count = $details['updated_count'];
                    $categories = [];
                    
                    // Determine which categories were updated based on setting keys
                    if (isset($details['settings_keys'])) {
                        $keys = $details['settings_keys'];
                        
                        // Group settings by category
                        $systemKeys = ['system_name', 'site_description', 'admin_email', 'timezone', 'date_format', 'time_format', 'maintenance_mode'];
                        $performanceKeys = ['cache_enabled', 'cache_duration', 'max_upload_size', 'pagination_limit'];
                        $notificationKeys = ['email_notifications_enabled', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption'];
                        $backupKeys = ['backup_enabled', 'backup_frequency', 'backup_retention_days'];
                        
                        if (array_intersect($keys, $systemKeys)) {
                            $categories[] = 'System';
                        }
                        if (array_intersect($keys, $performanceKeys)) {
                            $categories[] = 'Performance';
                        }
                        if (array_intersect($keys, $notificationKeys)) {
                            $categories[] = 'Notifications';
                        }
                        if (array_intersect($keys, $backupKeys)) {
                            $categories[] = 'Backup';
                        }
                    }
                    
                    if (!empty($categories)) {
                        return "Updated " . implode(' & ', $categories) . " settings ({$count} changes)";
                    } else {
                        return "Updated {$count} system settings";
                    }
                }
                return "Updated system settings";
                
            case 'UPDATE_SETTING':
                if (is_array($details) && isset($details['setting_key'])) {
                    $key = $details['setting_key'];
                    $friendlyNames = [
                        'system_name' => 'System Name',
                        'site_description' => 'Site Description',
                        'admin_email' => 'Admin Email',
                        'timezone' => 'Timezone',
                        'date_format' => 'Date Format',
                        'time_format' => 'Time Format',
                        'maintenance_mode' => 'Maintenance Mode',
                        'cache_enabled' => 'System Caching',
                        'cache_duration' => 'Cache Duration',
                        'max_upload_size' => 'Upload Size Limit',
                        'pagination_limit' => 'Page Size',
                        'email_notifications_enabled' => 'Email Notifications',
                        'backup_enabled' => 'Automatic Backups'
                    ];
                    
                    $friendlyName = $friendlyNames[$key] ?? ucwords(str_replace('_', ' ', $key));
                    return "Updated {$friendlyName} setting";
                }
                return "Updated a system setting";
                
            case 'CREATE_BACKUP':
                return "Created system backup";
                
            case 'RESTORE_BACKUP':
                return "Restored system from backup";
                
            case 'EXPORT_DATA':
                if (is_string($details)) {
                    return $details;
                }
                return "Exported system data";
                
            case 'USER_LOGIN':
                return "User logged in";
                
            case 'USER_LOGOUT':
                return "User logged out";
                
            case 'CREATE_USER':
                return "Created new user account";
                
            case 'UPDATE_PROFILE':
                return "Updated user profile";
                
            case 'CHANGE_PASSWORD':
                return "Changed account password";
                
            case 'TIMETABLE_CREATE':
                return "Created new timetable entry";
                
            case 'TIMETABLE_UPDATE':
                return "Updated timetable entry";
                
            case 'TIMETABLE_DELETE':
                return "Deleted timetable entry";
                
            default:
                // For unknown actions, try to make them more readable
                $readable = ucwords(str_replace('_', ' ', strtolower($action)));
                if (is_array($details) && !empty($details)) {
                    return $readable;
                } elseif (is_string($details) && !empty($details)) {
                    return $details;
                } else {
                    return $readable;
                }
        }
    }
    
    // ===========================================
    // SYSTEM HEALTH MONITORING
    // ===========================================
    
    /**
     * Get comprehensive system health status
     */
    public function getSystemHealth() {
        try {
            $health = [
                'overall_status' => 'healthy',
                'checks' => [],
                'metrics' => [],
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
            // Database health check
            $health['checks']['database'] = $this->checkDatabaseHealth();
            
            // Disk space check
            $health['checks']['disk_space'] = $this->checkDiskSpace();
            
            // Backup status check
            $health['checks']['backups'] = $this->checkBackupHealth();
            
            // Get system metrics
            $health['metrics']['users'] = $this->getUserMetrics();
            $health['metrics']['performance'] = $this->getPerformanceMetrics();
            
            // Determine overall status
            $statuses = array_column($health['checks'], 'status');
            
            if (in_array('critical', $statuses)) {
                $health['overall_status'] = 'critical';
            } elseif (in_array('warning', $statuses)) {
                $health['overall_status'] = 'warning';
            }
            
            return $health;
            
        } catch (Exception $e) {
            error_log("Error checking system health: " . $e->getMessage());
            return [
                'overall_status' => 'error',
                'message' => 'Unable to check system health: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check database health
     */
    private function checkDatabaseHealth() {
        try {
            // Test connection
            $this->db->fetchRow("SELECT 1 as test");
            
            // Get database size
            $dbResult = $this->db->fetchRow("SELECT DATABASE() as db_name");
            $dbName = $dbResult['db_name'] ?? 'unknown';
            
            $sizeResult = $this->db->fetchRow("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [$dbName]);
            
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'details' => [
                    'size_mb' => $sizeResult['size_mb'] ?? 0,
                    'connection' => 'active'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }
    
    /**
     * Check disk space
     */
    private function checkDiskSpace() {
        try {
            $freeBytes = disk_free_space('.');
            $totalBytes = disk_total_space('.');
            
            if ($freeBytes === false || $totalBytes === false) {
                return [
                    'status' => 'warning',
                    'message' => 'Unable to check disk space',
                    'details' => []
                ];
            }
            
            $freeGB = round($freeBytes / 1024 / 1024 / 1024, 2);
            $totalGB = round($totalBytes / 1024 / 1024 / 1024, 2);
            $usedPercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1);
            
            $status = 'healthy';
            $message = "Disk space: {$freeGB}GB free ({$usedPercent}% used)";
            
            if ($usedPercent > 90) {
                $status = 'critical';
                $message = "Critical: Low disk space - {$freeGB}GB free ({$usedPercent}% used)";
            } elseif ($usedPercent > 80) {
                $status = 'warning';
                $message = "Warning: Disk space running low - {$freeGB}GB free ({$usedPercent}% used)";
            }
            
            return [
                'status' => $status,
                'message' => $message,
                'details' => [
                    'free_gb' => $freeGB,
                    'total_gb' => $totalGB,
                    'used_percent' => $usedPercent
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Unable to check disk space: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }
    
    /**
     * Check backup health
     */
    private function checkBackupHealth() {
        try {
            $backupStatus = $this->getBackupStatus();
            
            if (isset($backupStatus['error'])) {
                return [
                    'status' => 'warning',
                    'message' => 'Backup status check failed',
                    'details' => []
                ];
            }
            
            $stats = $backupStatus['statistics'];
            $lastBackupDate = $stats['last_backup_date'];
            
            if (!$lastBackupDate) {
                return [
                    'status' => 'warning',
                    'message' => 'No backups found',
                    'details' => ['total_backups' => 0]
                ];
            }
            
            $lastBackup = new DateTime($lastBackupDate);
            $now = new DateTime();
            $hoursSinceBackup = ($now->getTimestamp() - $lastBackup->getTimestamp()) / 3600;
            
            $backupFrequency = $this->get('backup_frequency', 24);
            $status = 'healthy';
            $message = "Last backup: " . $lastBackup->format('M j, Y H:i');
            
            if ($hoursSinceBackup > $backupFrequency * 2) {
                $status = 'critical';
                $message = "Critical: No recent backups - last backup " . round($hoursSinceBackup) . " hours ago";
            } elseif ($hoursSinceBackup > $backupFrequency * 1.5) {
                $status = 'warning';
                $message = "Warning: Backup overdue - last backup " . round($hoursSinceBackup) . " hours ago";
            }
            
            return [
                'status' => $status,
                'message' => $message,
                'details' => [
                    'total_backups' => $stats['total_backups'],
                    'successful_backups' => $stats['successful_backups'],
                    'failed_backups' => $stats['failed_backups'],
                    'hours_since_last' => round($hoursSinceBackup, 1)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Backup check failed: ' . $e->getMessage(),
                'details' => []
            ];
        }
    }
    
    /**
     * Get user metrics
     */
    private function getUserMetrics() {
        try {
            $metrics = $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_users,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_users,
                    COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as daily_active,
                    COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as weekly_active
                FROM users
            ");
            
            return $metrics ?: [
                'total_users' => 0,
                'active_users' => 0,
                'pending_users' => 0,
                'daily_active' => 0,
                'weekly_active' => 0
            ];
            
        } catch (Exception $e) {
            error_log("Error getting user metrics: " . $e->getMessage());
            return [
                'total_users' => 0,
                'active_users' => 0,
                'pending_users' => 0,
                'daily_active' => 0,
                'weekly_active' => 0
            ];
        }
    }
    
    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics() {
        try {
            // Database size
            $dbResult = $this->db->fetchRow("SELECT DATABASE() as db_name");
            $dbName = $dbResult['db_name'] ?? 'unknown';
            
            $sizeResult = $this->db->fetchRow("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [$dbName]);
            
            // Memory usage
            $memoryUsage = 'N/A';
            if (function_exists('memory_get_usage')) {
                $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB';
            }
            
            return [
                'database_size_mb' => $sizeResult['size_mb'] ?? 0,
                'memory_usage' => $memoryUsage,
                'avg_response_time' => '< 100ms'
            ];
            
        } catch (Exception $e) {
            error_log("Error getting performance metrics: " . $e->getMessage());
            return [
                'database_size_mb' => 0,
                'memory_usage' => 'N/A',
                'avg_response_time' => 'N/A'
            ];
        }
    }
    
    // ===========================================
    // MAINTENANCE METHODS
    // ===========================================
    
    /**
     * Perform system maintenance tasks
     */
    public function performMaintenance($tasks, $performedBy) {
        try {
            $results = [
                'success' => true,
                'tasks_completed' => [],
                'tasks_failed' => []
            ];
            
            foreach ($tasks as $task) {
                try {
                    switch ($task) {
                        case 'cleanup_logs':
                            $this->cleanupAuditLogs();
                            $results['tasks_completed'][] = 'cleanup_logs';
                            break;
                            
                        case 'cleanup_notifications':
                            $this->cleanupNotifications();
                            $results['tasks_completed'][] = 'cleanup_notifications';
                            break;
                            
                        case 'cleanup_login_attempts':
                            $this->cleanupLoginAttempts();
                            $results['tasks_completed'][] = 'cleanup_login_attempts';
                            break;
                            
                        case 'optimize_database':
                            $this->optimizeDatabase();
                            $results['tasks_completed'][] = 'optimize_database';
                            break;
                            
                        case 'clear_cache':
                            $this->clearSystemCache();
                            $results['tasks_completed'][] = 'clear_cache';
                            break;
                            
                        case 'backup_system':
                            $result = $this->createManualBackup($performedBy, 'Maintenance backup');
                            if ($result['success']) {
                                $results['tasks_completed'][] = 'backup_system';
                            } else {
                                $results['tasks_failed'][] = "backup_system: " . $result['message'];
                            }
                            break;
                            
                        default:
                            $results['tasks_failed'][] = "Unknown task: {$task}";
                    }
                } catch (Exception $e) {
                    $results['tasks_failed'][] = "{$task}: " . $e->getMessage();
                }
            }
            
            // Log maintenance activity
            $this->logAuditAction($performedBy, 'SYSTEM_MAINTENANCE', null, null, [
                'tasks_completed' => $results['tasks_completed'],
                'tasks_failed' => $results['tasks_failed']
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Error performing maintenance: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Maintenance failed: ' . $e->getMessage(),
                'tasks_completed' => [],
                'tasks_failed' => []
            ];
        }
    }
    
    /**
     * Clean up old audit logs using existing table structure
     */
    private function cleanupAuditLogs() {
        $retentionDays = $this->get('keep_audit_logs_days', 365);
        
        $this->db->execute("
            DELETE FROM audit_logs 
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
        ", [$retentionDays]);
        
        error_log("Cleaned up audit logs older than {$retentionDays} days");
    }
    
    /**
     * Clean up old notifications
     */
    private function cleanupNotifications() {
        $retentionDays = $this->get('keep_notifications_days', 90);
        
        $this->db->execute("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND is_read = 1
        ", [$retentionDays]);
        
        error_log("Cleaned up notifications older than {$retentionDays} days");
    }
    
    /**
     * Clean up old login attempts
     */
    private function cleanupLoginAttempts() {
        $retentionDays = $this->get('keep_login_attempts_days', 30);
        
        // Check if login_attempts table exists first
        $tables = $this->db->fetchAll("SHOW TABLES LIKE 'login_attempts'");
        if (empty($tables)) {
            error_log("Login attempts table does not exist, skipping cleanup");
            return;
        }
        
        $this->db->execute("
            DELETE FROM login_attempts 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ", [$retentionDays]);
        
        error_log("Cleaned up login attempts older than {$retentionDays} days");
    }
    
    /**
     * Optimize database tables
     */
    private function optimizeDatabase() {
        $tables = $this->db->fetchAll("SHOW TABLES");
        $optimizedCount = 0;
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $this->db->execute("OPTIMIZE TABLE `{$tableName}`");
            $optimizedCount++;
        }
        
        error_log("Database optimization completed: {$optimizedCount} tables optimized");
    }
    
    /**
     * Clear system cache
     */
    private function clearSystemCache() {
        // Clear settings cache
        $this->clearCache();
        
        // Clear file-based caches if they exist
        $cacheDir = '../cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            $deletedCount = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $deletedCount++;
                }
            }
            error_log("Cleared system cache: {$deletedCount} cache files removed");
        } else {
            error_log("System cache cleared: settings cache only");
        }
    }
    
    // ===========================================
    // IMPORT/EXPORT METHODS
    // ===========================================
    
    /**
     * Export settings configuration
     */
    public function exportSettings($categories = []) {
        try {
            $allSettings = $this->getAllSettings();
            $exportData = [
                'export_date' => date('Y-m-d H:i:s'),
                'system_version' => '1.0.0',
                'settings' => []
            ];
            
            if (empty($categories)) {
                $exportData['settings'] = $allSettings;
            } else {
                foreach ($categories as $category) {
                    if (isset($this->settingsSchema[$category])) {
                        foreach ($this->settingsSchema[$category]['settings'] as $key => $schema) {
                            if (isset($allSettings[$key])) {
                                $exportData['settings'][$key] = $allSettings[$key];
                            }
                        }
                    }
                }
            }
            
            return $exportData;
            
        } catch (Exception $e) {
            error_log("Error exporting settings: " . $e->getMessage());
            return [
                'error' => 'Failed to export settings: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Import settings configuration
     */
    public function importSettings($importData, $importedBy) {
        try {
            if (!isset($importData['settings']) || !is_array($importData['settings'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid import data format.'
                ];
            }
            
            $this->db->beginTransaction();
            
            $importedCount = 0;
            $skippedCount = 0;
            $errors = [];
            
            foreach ($importData['settings'] as $key => $value) {
                // Validate setting exists in schema
                if (!$this->findSettingSchema($key)) {
                    $skippedCount++;
                    continue;
                }
                
                $result = $this->updateSingle($key, $value, $importedBy);
                
                if ($result['success']) {
                    $importedCount++;
                } else {
                    $errors[] = "{$key}: {$result['message']}";
                }
            }
            
            if (count($errors) < count($importData['settings']) / 2) {
                $this->db->commit();
                $this->clearCache();
                
                $this->logAuditAction($importedBy, 'IMPORT_SETTINGS', 'system_settings', null, [
                    'imported_count' => $importedCount,
                    'skipped_count' => $skippedCount,
                    'error_count' => count($errors)
                ]);
                
                return [
                    'success' => true,
                    'message' => "Import completed: {$importedCount} imported, {$skippedCount} skipped.",
                    'imported_count' => $importedCount,
                    'skipped_count' => $skippedCount,
                    'errors' => $errors
                ];
            } else {
                $this->db->rollback();
                return [
                    'success' => false,
                    'message' => 'Import failed due to too many errors.',
                    'errors' => $errors
                ];
            }
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error importing settings: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to import settings: ' . $e->getMessage()
            ];
        }
    }
    
    // ===========================================
    // UTILITY METHODS
    // ===========================================
    
    /**
     * Find setting schema by key
     */
    private function findSettingSchema($key) {
        foreach ($this->settingsSchema as $category) {
            if (isset($category['settings'][$key])) {
                return $category['settings'][$key];
            }
        }
        return null;
    }
    
    /**
     * Validate setting value
     */
    private function validateSettingValue($value, $schema) {
        $validations = $schema['validation'] ?? [];
        
        // If not required and effectively empty (after trim), skip further validations
        $hasRequired = in_array('required', array_filter($validations, 'is_string'), true);
        $trimmedValue = is_string($value) ? trim($value) : $value;
        if (!$hasRequired && ($trimmedValue === '' || $trimmedValue === null)) {
            return ['valid' => true, 'message' => ''];
        }
        
        foreach ($validations as $validation) {
            if (is_string($validation)) {
                switch ($validation) {
                    case 'required':
                        if (empty($value) && $value !== '0' && $value !== 0) {
                            return ['valid' => false, 'message' => 'This field is required.'];
                        }
                        break;
                        
                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            return ['valid' => false, 'message' => 'Please enter a valid email address.'];
                        }
                        break;
                        
                    case 'integer':
                        // Allow numeric strings but only integers (no decimals)
                        if ($trimmedValue !== '' && filter_var($trimmedValue, FILTER_VALIDATE_INT) === false) {
                            return ['valid' => false, 'message' => 'Please enter a valid integer.'];
                        }
                        break;
                }
            } elseif (is_array($validation)) {
                $rule = key($validation);
                $ruleValue = $validation[$rule];
                
                switch ($rule) {
                    case 'min':
                        if ($schema['type'] === 'integer') {
                            // Skip min check for optional empty values
                            if ($trimmedValue === '' || $trimmedValue === null) {
                                break;
                            }
                            $intVal = (int)$trimmedValue;
                            if ($intVal < (int)$ruleValue) {
                                return ['valid' => false, 'message' => "Value must be at least {$ruleValue}."];
                            }
                        } else {
                            if (strlen((string)$trimmedValue) < $ruleValue) {
                                return ['valid' => false, 'message' => "Must be at least {$ruleValue} characters."];
                            }
                        }
                        break;
                        
                    case 'max':
                        if ($schema['type'] === 'integer') {
                            // Skip max check for optional empty values
                            if ($trimmedValue === '' || $trimmedValue === null) {
                                break;
                            }
                            $intVal = (int)$trimmedValue;
                            if ($intVal > (int)$ruleValue) {
                                return ['valid' => false, 'message' => "Value must not exceed {$ruleValue}."];
                            }
                        } else {
                            if (strlen((string)$trimmedValue) > $ruleValue) {
                                return ['valid' => false, 'message' => "Must not exceed {$ruleValue} characters."];
                            }
                        }
                        break;
                }
            }
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * Cast value to appropriate type
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return (bool)$value || $value === '1' || $value === 'true';
                
            case 'integer':
                return (int)$value;
                
            case 'multiselect':
                if (is_string($value)) {
                    return json_decode($value, true) ?: [];
                }
                return is_array($value) ? $value : [];
                
            default:
                return (string)$value;
        }
    }
    
    /**
     * Prepare value for database storage
     */
    private function prepareValueForStorage($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            
            case 'integer':
                // Normalize to integer string for consistent storage
                $normalized = is_string($value) ? trim($value) : $value;
                return (string)intval($normalized);
            
            case 'multiselect':
                return is_array($value) ? json_encode($value) : $value;
            
            default:
                return (string)$value;
        }
    }
    
    /**
     * Clear settings cache
     */
    private function clearCache() {
        $this->cache = [];
    }
}
?>