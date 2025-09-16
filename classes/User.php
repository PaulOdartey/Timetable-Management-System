<?php
/**
 * User Management Class
 * Timetable Management System
 * 
 * Handles all user-related operations including authentication,
 * registration, profile management, and role-based access control
 */

// Prevent direct access
defined('SYSTEM_ACCESS') or die('Direct access denied');

class User {
    private $db;
    private $logger;
    
    // User roles constants
    const ROLE_ADMIN = 'admin';
    const ROLE_FACULTY = 'faculty';
    const ROLE_STUDENT = 'student';
    
    // User status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_REJECTED = 'rejected';
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = getLogger();
    }
    
    /**
     * ==========================================
     * AUTHENTICATION METHODS
     * ==========================================
     */
    
    /**
     * User login authentication
     * @param string $email User email
     * @param string $password User password
     * @return array Login result with user data or error
     */
public function login($email, $password, $rememberMe = false) {
        try {
            // Check for too many failed attempts
            if ($this->isAccountLocked($email)) {
                return [
                    'success' => false,
                    'message' => 'Account temporarily locked due to too many failed attempts. Try again later.'
                ];
            }
            
            // Get user from database
            $user = $this->db->fetchRow(
                "SELECT * FROM users WHERE email = ? AND status = ?",
                [$email, self::STATUS_ACTIVE]
            );
            
            if (!$user) {
                $this->recordLoginAttempt($email, false);
                return [
                    'success' => false,
                    'message' => 'Invalid email or password.'
                ];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->recordLoginAttempt($email, false);
                return [
                    'success' => false,
                    'message' => 'Invalid email or password.'
                ];
            }
            
            // Check if email is verified
            if (!$user['email_verified']) {
                return [
                    'success' => false,
                    'message' => 'Please verify your email address before logging in.'
                ];
            }
            
            // Successful login
            $this->recordLoginAttempt($email, true);
            $this->updateLastLogin($user['user_id']);
            
            // Set session variables
            $this->setUserSession($user);
            
            // Handle Remember Me
            if ($rememberMe) {
                $this->createRememberToken($user['user_id']);
            }
            
            // Get additional profile information
            $profileData = $this->getUserProfile($user['user_id'], $user['role']);
            
            $this->logger->info('User logged in successfully', [
                'user_id' => $user['user_id'],
                'email' => $email,
                'role' => $user['role'],
                'remember_me' => $rememberMe
            ]);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => array_merge($user, $profileData),
                'redirect' => $this->getRedirectUrl($user['role'])
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Login error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'An error occurred during login. Please try again.'
            ];
        }
    }

    
    /**
     * User registration
     * @param array $userData User registration data
     * @return array Registration result
     */
    public function register($userData) {
        try {
            // Validate input data
            $validation = $this->validateRegistrationData($userData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                    'errors' => $validation['errors']
                ];
            }
            
            // Check if email already exists
            if ($this->emailExists($userData['email'])) {
                return [
                    'success' => false,
                    'message' => 'Email address already registered.'
                ];
            }
            
            // Check if username already exists
            if ($this->usernameExists($userData['username'])) {
                return [
                    'success' => false,
                    'message' => 'Username already taken.'
                ];
            }
            
            $this->db->beginTransaction();
            
            try {
                // Hash password
                $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
                
                // Generate verification token
                $verificationToken = bin2hex(random_bytes(32));
                
                // Insert user record
                $userSql = "
                    INSERT INTO users (username, email, password_hash, role, status, email_verified, verification_token, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ";
                
                $this->db->execute($userSql, [
                    $userData['username'],
                    $userData['email'],
                    $passwordHash,
                    $userData['role'],
                    self::STATUS_PENDING,
                    0,
                    $verificationToken
                ]);
                
                $userId = $this->db->lastInsertId();
                
                // Insert role-specific profile data
                if ($userData['role'] === self::ROLE_STUDENT) {
                    $this->createStudentProfile($userId, $userData);
                } elseif ($userData['role'] === self::ROLE_FACULTY) {
                    $this->createFacultyProfile($userId, $userData);
                }
                
                $this->db->commit();
                
                // Send verification email
                $this->sendVerificationEmail($userData['email'], $verificationToken);
                
                $this->logger->info('User registered successfully', [
                    'user_id' => $userId,
                    'email' => $userData['email'],
                    'role' => $userData['role']
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Registration successful! Please check your email to verify your account.',
                    'user_id' => $userId
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Registration error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Registration failed. Please try again.'
            ];
        }
    }
    
    /**
     * Verify email address
     * @param string $token Verification token
     * @return array Verification result
     */
    public function verifyEmail($token) {
        try {
            $user = $this->db->fetchRow(
                "SELECT user_id, email FROM users WHERE verification_token = ? AND email_verified = 0",
                [$token]
            );
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification token.'
                ];
            }
            
            // Update user as verified
            $this->db->execute(
                "UPDATE users SET email_verified = 1, verification_token = NULL WHERE user_id = ?",
                [$user['user_id']]
            );
            
            $this->logger->info('Email verified successfully', [
                'user_id' => $user['user_id'],
                'email' => $user['email']
            ]);
            
            return [
                'success' => true,
                'message' => 'Email verified successfully! Your account is now pending admin approval.'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Email verification error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Verification failed. Please try again.'
            ];
        }
    }
    
    /**
     * ==========================================
     * USER MANAGEMENT METHODS
     * ==========================================
     */
    
    /**
     * Get pending user registrations for admin approval
     * @return array List of pending users
     */
    public function getPendingUsers() {
        return $this->db->fetchAll("
            SELECT u.*, 
                   CASE 
                       WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                       WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                       ELSE u.username
                   END as full_name,
                   CASE 
                       WHEN u.role = 'student' THEN s.department
                       WHEN u.role = 'faculty' THEN f.department
                       ELSE NULL
                   END as department
            FROM users u
            LEFT JOIN students s ON u.user_id = s.user_id
            LEFT JOIN faculty f ON u.user_id = f.user_id
            WHERE u.status = ?
            ORDER BY u.created_at ASC
        ", [self::STATUS_PENDING]);
    }
    
    /**
     * Approve user registration
     * @param int $userId User ID to approve
     * @param int $approvedBy Admin user ID who is approving
     * @return array Approval result
     */
    public function approveUser($userId, $approvedBy) {
        try {
            $user = $this->db->fetchRow(
                "SELECT email, role FROM users WHERE user_id = ? AND status = ?",
                [$userId, self::STATUS_PENDING]
            );
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found or already processed.'
                ];
            }
            
            // Update user status
            $this->db->execute(
                "UPDATE users SET status = ?, approved_by = ?, approved_at = NOW() WHERE user_id = ?",
                [self::STATUS_ACTIVE, $approvedBy, $userId]
            );
            
            // Send approval email
            $this->sendApprovalEmail($user['email'], true);
            
            $this->logger->info('User approved successfully', [
                'user_id' => $userId,
                'approved_by' => $approvedBy,
                'role' => $user['role']
            ]);
            
            return [
                'success' => true,
                'message' => 'User approved successfully.'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('User approval error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Approval failed. Please try again.'
            ];
        }
    }
    
    /**
     * Reject user registration
     * @param int $userId User ID to reject
     * @param int $rejectedBy Admin user ID who is rejecting
     * @return array Rejection result
     */
    public function rejectUser($userId, $rejectedBy) {
        try {
            $user = $this->db->fetchRow(
                "SELECT email FROM users WHERE user_id = ? AND status = ?",
                [$userId, self::STATUS_PENDING]
            );
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found or already processed.'
                ];
            }
            
            // Update user status
            $this->db->execute(
                "UPDATE users SET status = ?, approved_by = ?, approved_at = NOW() WHERE user_id = ?",
                [self::STATUS_REJECTED, $rejectedBy, $userId]
            );
            
            // Send rejection email
            $this->sendApprovalEmail($user['email'], false);
            
            $this->logger->info('User rejected', [
                'user_id' => $userId,
                'rejected_by' => $rejectedBy
            ]);
            
            return [
                'success' => true,
                'message' => 'User registration rejected.'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('User rejection error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Rejection failed. Please try again.'
            ];
        }
    }
    
    /**
     * Get user profile information
     * @param int $userId User ID
     * @param string $role User role
     * @return array User profile data
     */
    public function getUserProfile($userId, $role) {
        if ($role === self::ROLE_STUDENT) {
            return $this->db->fetchRow(
                "SELECT * FROM students WHERE user_id = ?",
                [$userId]
            ) ?: [];
        } elseif ($role === self::ROLE_FACULTY) {
            return $this->db->fetchRow(
                "SELECT * FROM faculty WHERE user_id = ?",
                [$userId]
            ) ?: [];
        } elseif ($role === self::ROLE_ADMIN) {
            return $this->db->fetchRow(
                "SELECT * FROM admin_profiles WHERE user_id = ?",
                [$userId]
            ) ?: [];
        }
        return [];
    }
    
    /**
     * Get all users with filters
     * @param array $filters Optional filters
     * @return array List of users
     */
    public function getAllUsers($filters = []) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['role'])) {
            $whereConditions[] = "u.role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "u.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(u.email LIKE ? OR u.username LIKE ? OR CONCAT(COALESCE(s.first_name, f.first_name, a.first_name), ' ', COALESCE(s.last_name, f.last_name, a.last_name)) LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['department'])) {
            $whereConditions[] = "(s.department = ? OR f.department = ? OR a.department = ?)";
            $params[] = $filters['department'];
            $params[] = $filters['department'];
            $params[] = $filters['department'];
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        return $this->db->fetchAll("
            SELECT u.*, 
                   CASE 
                       WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                       WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                       WHEN u.role = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                       ELSE u.username
                   END as full_name,
                   CASE 
                       WHEN u.role = 'student' THEN s.department
                       WHEN u.role = 'faculty' THEN f.department
                       WHEN u.role = 'admin' THEN a.department
                       ELSE NULL
                   END as department,
                   CASE 
                       WHEN u.role = 'student' THEN s.student_number
                       WHEN u.role = 'faculty' THEN f.employee_id
                       WHEN u.role = 'admin' THEN a.employee_id
                       ELSE NULL
                   END as identifier
            FROM users u
            LEFT JOIN students s ON u.user_id = s.user_id
            LEFT JOIN faculty f ON u.user_id = f.user_id
            LEFT JOIN admin_profiles a ON u.user_id = a.user_id
            {$whereClause}
            ORDER BY u.created_at DESC
        ", $params);
    }
    
    /**
     * Create user account (admin function)
     * @param array $userData User data
     * @param int $createdBy Admin user ID
     * @return array Creation result
     */
    public function createUserAccount($userData, $createdBy) {
        try {
            // Validate input data
            $validation = $this->validateRegistrationData($userData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                    'errors' => $validation['errors']
                ];
            }
            
            // Check if email already exists
            if ($this->emailExists($userData['email'])) {
                return [
                    'success' => false,
                    'message' => 'Email address already registered.'
                ];
            }
            
            $this->db->beginTransaction();
            
            try {
                // Hash password
                $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
                
                // Insert user record (admin-created accounts are immediately active)
                $userSql = "
                    INSERT INTO users (username, email, password_hash, role, status, email_verified, approved_by, approved_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ";
                
                $this->db->execute($userSql, [
                    $userData['username'],
                    $userData['email'],
                    $passwordHash,
                    $userData['role'],
                    self::STATUS_ACTIVE,
                    1,
                    $createdBy
                ]);
                
                $userId = $this->db->lastInsertId();
                
                // Insert role-specific profile data
                if ($userData['role'] === self::ROLE_STUDENT) {
                    $this->createStudentProfile($userId, $userData);
                } elseif ($userData['role'] === self::ROLE_FACULTY) {
                    $this->createFacultyProfile($userId, $userData);
                } elseif ($userData['role'] === self::ROLE_ADMIN) {
                    $this->createAdminProfile($userId, $userData);
                }
                
                $this->db->commit();
                
                // Send welcome email
                $this->sendWelcomeEmail($userData['email'], $userData['password']);
                
                $this->logger->info('User account created by admin', [
                    'user_id' => $userId,
                    'email' => $userData['email'],
                    'role' => $userData['role'],
                    'created_by' => $createdBy
                ]);
                
                return [
                    'success' => true,
                    'message' => 'User account created successfully.',
                    'user_id' => $userId
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error('User creation error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Failed to create user account. Please try again.'
            ];
        }
    }
    
    /**
     * Update user profile
     * @param int $userId User ID
     * @param array $profileData Profile data
     * @return array Update result
     */
    public function updateUserProfile($userId, $profileData) {
        try {
            $user = $this->db->fetchRow("SELECT role FROM users WHERE user_id = ?", [$userId]);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found.'
                ];
            }
            
            $this->db->beginTransaction();
            
            try {
                // Update role-specific profile
                if ($user['role'] === self::ROLE_STUDENT) {
                    $this->updateStudentProfile($userId, $profileData);
                } elseif ($user['role'] === self::ROLE_FACULTY) {
                    $this->updateFacultyProfile($userId, $profileData);
                } elseif ($user['role'] === self::ROLE_ADMIN) {
                    $this->updateAdminProfile($userId, $profileData);
                }
                
                $this->db->commit();
                
                // Log the update
                $this->logAuditAction($userId, 'UPDATE_PROFILE', null, null, 'Updated user profile');
                
                $this->logger->info('User profile updated', [
                    'user_id' => $userId,
                    'role' => $user['role']
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Profile updated successfully.'
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Profile update error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Failed to update profile. Please try again.'
            ];
        }
    }
    
    /**
     * ==========================================
     * PASSWORD MANAGEMENT
     * ==========================================
     */
    
    /**
     * Change user password
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array Password change result
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->db->fetchRow(
                "SELECT password_hash FROM users WHERE user_id = ?",
                [$userId]
            );
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found.'
                ];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect.'
                ];
            }
            
            // Validate new password
            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                return [
                    'success' => false,
                    'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.'
                ];
            }
            
            // Hash new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $this->db->execute(
                "UPDATE users SET password_hash = ? WHERE user_id = ?",
                [$passwordHash, $userId]
            );
            
            // Log the change
            $this->logAuditAction($userId, 'CHANGE_PASSWORD', null, null, 'Password changed successfully');
            
            $this->logger->info('Password changed successfully', [
                'user_id' => $userId
            ]);
            
            return [
                'success' => true,
                'message' => 'Password changed successfully.'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Password change error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Failed to change password. Please try again.'
            ];
        }
    }
    
    /**
     * Request password reset
     * @param string $email User email
     * @return array Reset request result
     */
    public function requestPasswordReset($email) {
        try {
            $user = $this->db->fetchRow(
                "SELECT user_id, username FROM users WHERE email = ? AND status = ?",
                [$email, self::STATUS_ACTIVE]
            );
            
            if (!$user) {
                // Don't reveal if email exists for security
                return [
                    'success' => true,
                    'message' => 'If your email is registered, you will receive a password reset link.'
                ];
            }
            
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);
            
            // Update user with reset token
            $this->db->execute(
                "UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE user_id = ?",
                [$resetToken, $expiresAt, $user['user_id']]
            );
            
            // Send reset email
            $this->sendPasswordResetEmail($email, $resetToken);
            
            $this->logger->info('Password reset requested', [
                'user_id' => $user['user_id'],
                'email' => $email
            ]);
            
            return [
                'success' => true,
                'message' => 'Password reset instructions sent to your email.'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Password reset request error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Failed to process password reset request.'
            ];
        }
    }
    
    /**
     * Reset password with token
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return array Reset result
     */
    public function resetPassword($token, $newPassword) {
        try {
            $user = $this->db->fetchRow(
                "SELECT user_id, email FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW()",
                [$token]
            );
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token.'
                ];
            }
            
            // Validate new password
            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                return [
                    'success' => false,
                    'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.'
                ];
            }
            
            // Hash new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update user password and clear reset token
            $this->db->execute(
                "UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE user_id = ?",
                [$passwordHash, $user['user_id']]
            );
            
            $this->logger->info('Password reset successfully', [
                'user_id' => $user['user_id'],
                'email' => $user['email']
            ]);
            
            return [
                'success' => true,
                'message' => 'Password reset successfully. You can now login with your new password.'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Password reset error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Failed to reset password. Please try again.'
            ];
        }
    }
    
   /**
     * ==========================================
     * REMEMBER ME FUNCTIONALITY
     * ==========================================
     */
    
    /**
     * Check and process remember me token
     * Call this at the beginning of your application
     * @return bool True if user was automatically logged in
     */
    public function checkRememberMe() {
        try {
            $this->logger->debug('checkRememberMe() called', [
                'has_session' => session_status() === PHP_SESSION_ACTIVE,
                'is_logged_in' => self::isLoggedIn(),
                'has_cookie' => isset($_COOKIE['remember_token']),
                'cookie_value' => isset($_COOKIE['remember_token']) ? substr($_COOKIE['remember_token'], 0, 10) . '...' : 'none'
            ]);

            // Skip if user is already logged in
            if (self::isLoggedIn() && !self::isSessionExpired()) {
                $this->logger->debug('User already logged in, skipping remember me check');
                return false;
            }
            
            // Check if remember token exists
            if (!isset($_COOKIE['remember_token'])) {
                $this->logger->debug('No remember_token cookie found');
                return false;
            }
            
            $token = $_COOKIE['remember_token'];
            $tokenHash = hash('sha256', $token);
            
            $this->logger->debug('Looking up token in database', [
                'token_prefix' => substr($token, 0, 10) . '...',
                'hash_prefix' => substr($tokenHash, 0, 10) . '...'
            ]);

            // Find valid token in database
            $tokenData = $this->db->fetchRow(
                "SELECT rt.*, u.* FROM remember_tokens rt 
                 JOIN users u ON rt.user_id = u.user_id 
                 WHERE rt.token_hash = ? AND rt.expires_at > NOW() AND rt.is_active = 1 AND u.status = ?",
                [$tokenHash, self::STATUS_ACTIVE]
            );
            
            if (!$tokenData) {
                $this->logger->warning('Invalid or expired remember token', [
                    'token_exists' => !empty($tokenData),
                    'token_active' => $tokenData['is_active'] ?? 'n/a',
                    'token_expired' => isset($tokenData['expires_at']) && strtotime($tokenData['expires_at']) < time() ? 'yes' : 'no'
                ]);
                // Invalid or expired token, remove cookie
                $this->clearRememberToken();
                return false;
            }
            
            $this->logger->debug('Valid token found', [
                'user_id' => $tokenData['user_id'],
                'email' => $tokenData['email']
            ]);
            
            // Additional security checks
            $currentIP = $this->getClientIP();
            
            // Update token last used time and IP
            $this->db->execute(
                "UPDATE remember_tokens SET last_used_at = NOW(), ip_address = ? WHERE token_hash = ?",
                [$currentIP, $tokenHash]
            );
            
            // Log the user in automatically
            $this->setUserSession($tokenData);
            $this->updateLastLogin($tokenData['user_id']);
            
            $this->logger->info('User automatically logged in via remember token', [
                'user_id' => $tokenData['user_id'],
                'email' => $tokenData['email'],
                'ip_address' => $currentIP
            ]);
            
            // Rotate the token for security (optional but recommended)
            $this->rotateRememberToken($tokenData['user_id'], $tokenHash);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Remember me check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->clearRememberToken();
            return false;
        }
    }
    
    /**
     * Create remember me token
     * @param int $userId User ID
     * @return string|false Token string or false on failure
     */
    private function createRememberToken($userId) {
        try {
            // Generate secure random token
            $token = bin2hex(random_bytes(32)); // 64 character token
            $tokenHash = hash('sha256', $token);
            
            // Set expiration (30 days from now)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // Get user agent and IP for security
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = $this->getClientIP();
            
            // Clean up old tokens for this user (keep only 5 most recent)
            $this->cleanupOldRememberTokens($userId);
            
            // Insert new token
            $this->db->execute(
                "INSERT INTO remember_tokens (user_id, token_hash, expires_at, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)",
                [$userId, $tokenHash, $expiresAt, $userAgent, $ipAddress]
            );
            
            // Set cookie (30 days, secure, httponly, samesite)
            $expires = time() + (30 * 24 * 60 * 60); // 30 days
            
            // PHP 7.3+ supports the options array. Older versions require the long signature.
            if (PHP_VERSION_ID >= 70300) {
                setcookie('remember_token', $token, [
                    'expires'   => $expires,
                    'path'      => '/',
                    'secure'    => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly'  => true,
                    'samesite'  => 'Strict'
                ]);
            } else {
                // Fallback for PHP 7.2 and below (SameSite not supported, but at least sets cookie)
                setcookie('remember_token', $token, $expires, '/');
            }
            
            $this->logger->info('Remember token created', [
                'user_id' => $userId,
                'expires_at' => $expiresAt,
                'ip_address' => $ipAddress
            ]);
            
            return $token;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to create remember token', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Rotate remember token for security
     * @param int $userId User ID
     * @param string $oldTokenHash Old token hash to invalidate
     */
    private function rotateRememberToken($userId, $oldTokenHash) {
        try {
            // Invalidate old token
            $this->db->execute(
                "UPDATE remember_tokens SET is_active = 0 WHERE token_hash = ?",
                [$oldTokenHash]
            );
            
            // Create new token
            $this->createRememberToken($userId);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to rotate remember token', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Clear remember me token and cookie
     */
    public function clearRememberToken() {
        try {
            // Remove cookie
            $past = time() - 3600;
            
            // PHP 7.3+ supports the options array. Older versions require the long signature.
            if (PHP_VERSION_ID >= 70300) {
                setcookie('remember_token', '', [
                    'expires'  => $past,
                    'path'     => '/',
                    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            } else {
                setcookie('remember_token', '', $past, '/');
            }
            
            // If we have the token, invalidate it in database
            if (isset($_COOKIE['remember_token'])) {
                $tokenHash = hash('sha256', $_COOKIE['remember_token']);
                $this->invalidateRememberToken($tokenHash);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to clear remember token', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Invalidate remember token in database
     * @param string $tokenHash Token hash to invalidate
     */
    private function invalidateRememberToken($tokenHash) {
        try {
            $this->db->execute(
                "UPDATE remember_tokens SET is_active = 0 WHERE token_hash = ?",
                [$tokenHash]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to invalidate remember token', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Clean up old remember tokens for a user (keep only 5 most recent)
     * @param int $userId User ID
     */
    private function cleanupOldRememberTokens($userId) {
        try {
            // Get count of active tokens
            $tokenCount = $this->db->fetchRow(
                "SELECT COUNT(*) as count FROM remember_tokens WHERE user_id = ? AND is_active = 1",
                [$userId]
            );
            
            if ($tokenCount['count'] >= 5) {
                // Deactivate oldest tokens, keep 4 most recent
                $this->db->execute(
                    "UPDATE remember_tokens SET is_active = 0 
                     WHERE user_id = ? AND is_active = 1 
                     AND token_id NOT IN (
                         SELECT token_id FROM (
                             SELECT token_id FROM remember_tokens 
                             WHERE user_id = ? AND is_active = 1 
                             ORDER BY created_at DESC LIMIT 4
                         ) AS recent_tokens
                     )",
                    [$userId, $userId]
                );
            }
            
            // Delete expired tokens
            $this->db->execute(
                "DELETE FROM remember_tokens WHERE expires_at < NOW() OR (is_active = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY))"
            );
            
        } catch (Exception $e) {
            $this->logger->error('Failed to cleanup old remember tokens', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get all active remember tokens for a user (for account security page)
     * @param int $userId User ID
     * @return array List of active tokens
     */
    public function getUserRememberTokens($userId) {
        try {
            return $this->db->fetchAll(
                "SELECT token_id, created_at, last_used_at, user_agent, ip_address 
                 FROM remember_tokens 
                 WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
                 ORDER BY last_used_at DESC, created_at DESC",
                [$userId]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to get user remember tokens', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Revoke a specific remember token
     * @param int $userId User ID
     * @param int $tokenId Token ID to revoke
     * @return bool Success status
     */
    public function revokeRememberToken($userId, $tokenId) {
        try {
            $result = $this->db->execute(
                "UPDATE remember_tokens SET is_active = 0 WHERE token_id = ? AND user_id = ?",
                [$tokenId, $userId]
            );
            
            $this->logger->info('Remember token revoked', [
                'user_id' => $userId,
                'token_id' => $tokenId
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to revoke remember token', [
                'user_id' => $userId,
                'token_id' => $tokenId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Revoke all remember tokens for a user
     * @param int $userId User ID
     * @return bool Success status
     */
    public function revokeAllRememberTokens($userId) {
        try {
            $this->db->execute(
                "UPDATE remember_tokens SET is_active = 0 WHERE user_id = ?",
                [$userId]
            );
            
            $this->logger->info('All remember tokens revoked', ['user_id' => $userId]);
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to revoke all remember tokens', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }


    /**
     * ==========================================
     * USER PREFERENCES & SETTINGS
     * ==========================================
     */
    
    /**
     * Get user preferences
     * @param int $userId User ID
     * @return array User preferences
     */
    public function getUserPreferences($userId) {
        try {
            $preferences = $this->db->fetchAll(
                "SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE ?",
                ["user_pref_%"]
            );
            
            $userPrefs = [];
            foreach ($preferences as $pref) {
                $key = str_replace('user_pref_', '', $pref['setting_key']);
                $userPrefs[$key] = $pref['setting_value'];
            }
            
            return $userPrefs;
            
        } catch (Exception $e) {
            $this->logger->error('Error getting user preferences', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Update user preferences
     * @param int $userId User ID
     * @param array $preferences Preferences array
     * @return array Update result
     */
    public function updateUserPreferences($userId, $preferences) {
        try {
            $this->db->beginTransaction();
            
            foreach ($preferences as $key => $value) {
                $settingKey = 'user_pref_' . $key;
                
                // Check if setting exists
                $existing = $this->db->fetchRow(
                    "SELECT setting_id FROM system_settings WHERE setting_key = ?",
                    [$settingKey]
                );
                
                if ($existing) {
                    // Update existing setting
                    $this->db->execute(
                        "UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?",
                        [$value, $userId, $settingKey]
                    );
                    $this->logAuditAction($userId, 'UPDATE_SETTING', null, null, "Updated setting: $settingKey");
                } else {
                    // Create new setting
                    $this->db->execute(
                        "INSERT INTO system_settings (setting_key, setting_value, setting_type, description, updated_by) VALUES (?, ?, 'string', 'User preference', ?)",
                        [$settingKey, $value, $userId]
                    );
                    $this->logAuditAction($userId, 'CREATE_SETTING', null, null, "Created setting: $settingKey");
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Preferences updated successfully.'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logger->error('Error updating user preferences', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Failed to update preferences.'
            ];
        }
    }
    
    /**
     * ==========================================
     * BULK OPERATIONS
     * ==========================================
     */
    
    /**
     * Bulk approve users
     * @param array $userIds Array of user IDs
     * @param int $approvedBy Admin user ID
     * @return array Bulk approval result
     */
    public function bulkApproveUsers($userIds, $approvedBy) {
        try {
            if (empty($userIds) || !is_array($userIds)) {
                return [
                    'success' => false,
                    'message' => 'No users selected for approval.'
                ];
            }
            
            $this->db->beginTransaction();
            
            $approved = 0;
            $failed = 0;
            $results = [];
            
            foreach ($userIds as $userId) {
                $result = $this->approveUser($userId, $approvedBy);
                if ($result['success']) {
                    $approved++;
                } else {
                    $failed++;
                    $results[] = "User ID $userId: " . $result['message'];
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Bulk approval completed. Approved: $approved, Failed: $failed",
                'details' => $results,
                'approved' => $approved,
                'failed' => $failed
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logger->error('Bulk approval error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Bulk approval failed. Please try again.'
            ];
        }
    }
    
    /**
     * Bulk reject users
     * @param array $userIds Array of user IDs
     * @param int $rejectedBy Admin user ID
     * @return array Bulk rejection result
     */
    public function bulkRejectUsers($userIds, $rejectedBy) {
        try {
            if (empty($userIds) || !is_array($userIds)) {
                return [
                    'success' => false,
                    'message' => 'No users selected for rejection.'
                ];
            }
            
            $this->db->beginTransaction();
            
            $rejected = 0;
            $failed = 0;
            $results = [];
            
            foreach ($userIds as $userId) {
                $result = $this->rejectUser($userId, $rejectedBy);
                if ($result['success']) {
                    $rejected++;
                } else {
                    $failed++;
                    $results[] = "User ID $userId: " . $result['message'];
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Bulk rejection completed. Rejected: $rejected, Failed: $failed",
                'details' => $results,
                'rejected' => $rejected,
                'failed' => $failed
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logger->error('Bulk rejection error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Bulk rejection failed. Please try again.'
            ];
        }
    }
    
    /**
     * Export users data
     * @param array $filters Optional filters
     * @param string $format Export format (csv, json)
     * @return array Export result
     */
    public function exportUsers($filters = [], $format = 'csv') {
        try {
            $users = $this->getAllUsers($filters);
            
            if (empty($users)) {
                return [
                    'success' => false,
                    'message' => 'No users found to export.'
                ];
            }
            
            $timestamp = date('Y_m_d_H_i_s');
            $filename = "users_export_{$timestamp}.{$format}";
            
            if ($format === 'csv') {
                $filePath = $this->exportToCSV($users, $filename);
            } elseif ($format === 'json') {
                $filePath = $this->exportToJSON($users, $filename);
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid export format.'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Users exported successfully.',
                'filename' => $filename,
                'filepath' => $filePath,
                'count' => count($users)
            ];
            
        } catch (Exception $e) {
            $this->logger->error('User export error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Export failed. Please try again.'
            ];
        }
    }
    
    /**
     * ==========================================
     * ENHANCED STATISTICS & ANALYTICS
     * ==========================================
     */
    
    /**
     * Get comprehensive user statistics
     * @return array Enhanced user statistics
     */
    public function getEnhancedUserStats() {
        try {
            $stats = [];
            
            // Total users by status
            $statusCounts = $this->db->fetchAll("
                SELECT status, COUNT(*) as count 
                FROM users 
                GROUP BY status
            ");
            
            foreach ($statusCounts as $row) {
                $stats['by_status'][$row['status']] = $row['count'];
            }
            
            // Total users by role
            $roleCounts = $this->db->fetchAll("
                SELECT role, COUNT(*) as count 
                FROM users 
                WHERE status = 'active'
                GROUP BY role
            ");
            
            foreach ($roleCounts as $row) {
                $stats['by_role'][$row['role']] = $row['count'];
            }
            
            // Department distribution
            $deptStats = $this->db->fetchAll("
                SELECT 
                    COALESCE(s.department, f.department, a.department) as department,
                    COUNT(*) as count
                FROM users u
                LEFT JOIN students s ON u.user_id = s.user_id
                LEFT JOIN faculty f ON u.user_id = f.user_id
                LEFT JOIN admin_profiles a ON u.user_id = a.user_id
                WHERE u.status = 'active' 
                AND COALESCE(s.department, f.department, a.department) IS NOT NULL
                GROUP BY COALESCE(s.department, f.department, a.department)
                ORDER BY count DESC
            ");
            
            $stats['by_department'] = $deptStats;
            
            // Registration trends (last 30 days)
            $trendData = $this->db->fetchAll("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as registrations
                FROM users 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            
            $stats['registration_trend'] = $trendData;
            
            // Recent activities
            $recentActivities = $this->db->fetchAll("
                SELECT 
                    u.username,
                    u.role,
                    u.last_login,
                    u.created_at,
                    u.status
                FROM users u
                ORDER BY u.last_login DESC
                LIMIT 10
            ");
            
            $stats['recent_activities'] = $recentActivities;
            
            // Pending approvals by role
            $pendingByRole = $this->db->fetchAll("
                SELECT role, COUNT(*) as count 
                FROM users 
                WHERE status = 'pending'
                GROUP BY role
            ");
            
            $stats['pending_by_role'] = $pendingByRole;
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logger->error('Error getting enhanced user statistics', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get user activity analytics
     * @param int $days Number of days to analyze
     * @return array Activity analytics
     */
    public function getUserActivityAnalytics($days = 30) {
        try {
            $analytics = [];
            
            // Login frequency
            $loginStats = $this->db->fetchAll("
                SELECT 
                    u.role,
                    COUNT(DISTINCT u.user_id) as active_users,
                    COUNT(u.last_login) as total_logins
                FROM users u
                WHERE u.last_login >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND u.status = 'active'
                GROUP BY u.role
            ", [$days]);
            
            $analytics['login_stats'] = $loginStats;
            
            // Daily active users
            $dailyActive = $this->db->fetchAll("
                SELECT 
                    DATE(last_login) as date,
                    COUNT(DISTINCT user_id) as active_users
                FROM users
                WHERE last_login >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(last_login)
                ORDER BY date DESC
            ", [$days]);
            
            $analytics['daily_active'] = $dailyActive;
            
            // User engagement by department
            $deptEngagement = $this->db->fetchAll("
                SELECT 
                    COALESCE(s.department, f.department, a.department) as department,
                    COUNT(DISTINCT u.user_id) as total_users,
                    COUNT(DISTINCT CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN u.user_id END) as active_users,
                    ROUND(
                        (COUNT(DISTINCT CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN u.user_id END) * 100.0 / COUNT(DISTINCT u.user_id)), 2
                    ) as engagement_rate
                FROM users u
                LEFT JOIN students s ON u.user_id = s.user_id
                LEFT JOIN faculty f ON u.user_id = f.user_id
                LEFT JOIN admin_profiles a ON u.user_id = a.user_id
                WHERE u.status = 'active'
                AND COALESCE(s.department, f.department, a.department) IS NOT NULL
                GROUP BY COALESCE(s.department, f.department, a.department)
                ORDER BY engagement_rate DESC
            ", [$days, $days]);
            
            $analytics['department_engagement'] = $deptEngagement;
            
            return $analytics;
            
        } catch (Exception $e) {
            $this->logger->error('Error getting user activity analytics', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * ==========================================
     * VALIDATION METHODS
     * ==========================================
     */
    
    /**
     * Validate registration data
     * @param array $data Registration data
     * @return array Validation result
     */
    private function validateRegistrationData($data) {
        $errors = [];
        
        // Required fields
        $required = ['username', 'email', 'password', 'role', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst($field) . ' is required.';
            }
        }
        
        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format.';
        }
        
        // Password validation
        if (!empty($data['password'])) {
            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
            }
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $data['password'])) {
                $errors['password'] = 'Password must contain uppercase, lowercase, number and special character.';
            }
        }
        
        // Role validation
        if (!empty($data['role']) && !in_array($data['role'], [self::ROLE_ADMIN, self::ROLE_FACULTY, self::ROLE_STUDENT])) {
            $errors['role'] = 'Invalid role selected.';
        }
        
        // Role-specific validation
        if ($data['role'] === self::ROLE_STUDENT) {
            if (empty($data['student_number'])) {
                $errors['student_number'] = 'Student number is required.';
            }
            if (empty($data['department'])) {
                $errors['department'] = 'Department is required.';
            }
            if (empty($data['year_of_study']) || !is_numeric($data['year_of_study'])) {
                $errors['year_of_study'] = 'Valid year of study is required.';
            }
        } elseif ($data['role'] === self::ROLE_FACULTY) {
            if (empty($data['employee_id'])) {
                $errors['employee_id'] = 'Employee ID is required.';
            }
            if (empty($data['department'])) {
                $errors['department'] = 'Department is required.';
            }
            if (empty($data['designation'])) {
                $errors['designation'] = 'Designation is required.';
            }
        } elseif ($data['role'] === self::ROLE_ADMIN) {
            if (empty($data['employee_id'])) {
                $errors['employee_id'] = 'Employee ID is required.';
            }
            if (empty($data['department'])) {
                $errors['department'] = 'Department is required.';
            }
            if (empty($data['designation'])) {
                $errors['designation'] = 'Designation is required.';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'message' => empty($errors) ? 'Validation passed' : 'Please correct the errors below.'
        ];
    }
    
    /**
     * ==========================================
     * HELPER METHODS
     * ==========================================
     */
    
    /**
     * Check if email exists
     * @param string $email Email address
     * @return bool
     */
    private function emailExists($email) {
        $result = $this->db->fetchRow(
            "SELECT user_id FROM users WHERE email = ?",
            [$email]
        );
        return $result !== false;
    }
    
    /**
     * Check if username exists
     * @param string $username Username
     * @return bool
     */
    private function usernameExists($username) {
        $result = $this->db->fetchRow(
            "SELECT user_id FROM users WHERE username = ?",
            [$username]
        );
        return $result !== false;
    }
    
    /**
     * Check if account is locked due to failed attempts
     * @param string $email Email address
     * @return bool
     */
    private function isAccountLocked($email) {
        $user = $this->db->fetchRow(
            "SELECT login_attempts, last_attempt_time FROM users WHERE email = ?",
            [$email]
        );
        
        if (!$user) return false;
        
        if ($user['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $lockoutTime = strtotime($user['last_attempt_time']) + LOGIN_TIMEOUT;
            return time() < $lockoutTime;
        }
        
        return false;
    }
    
    /**
     * Record login attempt
     * @param string $email Email address
     * @param bool $success Whether login was successful
     */
    private function recordLoginAttempt($email, $success) {
        if ($success) {
            // Reset failed attempts on successful login
            $this->db->execute(
                "UPDATE users SET login_attempts = 0, last_attempt_time = NULL, last_login = NOW() WHERE email = ?",
                [$email]
            );
        } else {
            // Increment failed attempts
            $this->db->execute(
                "UPDATE users SET login_attempts = login_attempts + 1, last_attempt_time = NOW() WHERE email = ?",
                [$email]
            );
        }
    }
    
    /**
     * Update last login timestamp
     * @param int $userId User ID
     */
    private function updateLastLogin($userId) {
        $this->db->execute(
            "UPDATE users SET last_login = NOW() WHERE user_id = ?",
            [$userId]
        );
    }
    
    /**
     * Set user session variables
     * @param array $user User data
     */
    private function setUserSession($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
    }
    
    /**
     * Get redirect URL based on user role
     * @param string $role User role
     * @return string Redirect URL
     */
    public function getRedirectUrl($role) {
        switch ($role) {
            case self::ROLE_ADMIN:
                return BASE_URL . 'admin/';
            case self::ROLE_FACULTY:
                return BASE_URL . 'faculty/';
            case self::ROLE_STUDENT:
                return BASE_URL . 'student/';
            default:
                return BASE_URL;
        }
    }
    
    /**
     * Create admin profile
     * @param int $userId User ID
     * @param array $userData User data
     */
    private function createAdminProfile($userId, $userData) {
        $sql = "
            INSERT INTO admin_profiles (user_id, employee_id, first_name, last_name, department, designation, phone, date_joined, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        
        $this->db->execute($sql, [
            $userId,
            $userData['employee_id'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['department'],
            $userData['designation'] ?? 'Administrator',
            $userData['phone'] ?? null
        ]);
    }
    
    /**
     * Create student profile
     * @param int $userId User ID
     * @param array $userData User data
     */
    private function createStudentProfile($userId, $userData) {
        $sql = "
            INSERT INTO students (user_id, student_number, first_name, last_name, department, year_of_study, semester, phone, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $this->db->execute($sql, [
            $userId,
            $userData['student_number'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['department'],
            $userData['year_of_study'],
            $userData['semester'] ?? 1,
            $userData['phone'] ?? null
        ]);
    }
    
    /**
     * Create faculty profile
     * @param int $userId User ID
     * @param array $userData User data
     */
   private function createFacultyProfile($userId, $userData) {
    // Get department name - try department_id first, fallback to department name
    $departmentName = '';
    
    if (!empty($userData['department_id'])) {
        // Use department_id if provided
        $department = $this->getDepartmentById($userData['department_id']);
        $departmentName = $department ? $department['department_name'] : '';
    } elseif (!empty($userData['department'])) {
        // Fallback to department name if department_id not provided
        $departmentName = $userData['department'];
    }
    
    $sql = "
        INSERT INTO faculty (user_id, employee_id, first_name, last_name, department, designation, phone, specialization, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $this->db->execute($sql, [
        $userId,
        $userData['employee_id'],
        $userData['first_name'],
        $userData['last_name'],
        $departmentName,
        $userData['designation'],
        $userData['phone'] ?? null,
        $userData['specialization'] ?? null
    ]);
}
    
    /**
     * Update admin profile
     * @param int $userId User ID
     * @param array $profileData Profile data
     */
    private function updateAdminProfile($userId, $profileData) {
        $sql = "
            UPDATE admin_profiles 
            SET first_name = ?, last_name = ?, department = ?, designation = ?, phone = ?, 
                bio = ?, office_location = ?, emergency_contact = ?, emergency_phone = ?, updated_at = NOW()
            WHERE user_id = ?
        ";
        
        $this->db->execute($sql, [
            $profileData['first_name'],
            $profileData['last_name'],
            $profileData['department'],
            $profileData['designation'],
            $profileData['phone'] ?? null,
            $profileData['bio'] ?? null,
            $profileData['office_location'] ?? null,
            $profileData['emergency_contact'] ?? null,
            $profileData['emergency_phone'] ?? null,
            $userId
        ]);
    }
    
    /**
     * Update student profile
     * @param int $userId User ID
     * @param array $profileData Profile data
     */
    private function updateStudentProfile($userId, $profileData) {
        $sql = "
            UPDATE students 
            SET first_name = ?, last_name = ?, department = ?, year_of_study = ?, semester = ?, 
                phone = ?, date_of_birth = ?, address = ?, guardian_name = ?, guardian_phone = ?, 
                guardian_email = ?, updated_at = NOW()
            WHERE user_id = ?
        ";
        
        $this->db->execute($sql, [
            $profileData['first_name'],
            $profileData['last_name'],
            $profileData['department'],
            $profileData['year_of_study'],
            $profileData['semester'],
            $profileData['phone'] ?? null,
            $profileData['date_of_birth'] ?? null,
            $profileData['address'] ?? null,
            $profileData['guardian_name'] ?? null,
            $profileData['guardian_phone'] ?? null,
            $profileData['guardian_email'] ?? null,
            $userId
        ]);
    }
    
    /**
     * Update faculty profile
     * @param int $userId User ID
     * @param array $profileData Profile data
     */
    private function updateFacultyProfile($userId, $profileData) {
        $sql = "
            UPDATE faculty 
            SET first_name = ?, last_name = ?, department = ?, designation = ?, phone = ?, 
                specialization = ?, qualification = ?, experience_years = ?, office_location = ?, 
                updated_at = NOW()
            WHERE user_id = ?
        ";
        
        $this->db->execute($sql, [
            $profileData['first_name'],
            $profileData['last_name'],
            $profileData['department'],
            $profileData['designation'],
            $profileData['phone'] ?? null,
            $profileData['specialization'] ?? null,
            $profileData['qualification'] ?? null,
            $profileData['experience_years'] ?? null,
            $profileData['office_location'] ?? null,
            $userId
        ]);
    }

    // =====================================================
// STEP 3: ADD getClientIP() HELPER METHOD
// =====================================================

// FIND THE END OF updateFacultyProfile() METHOD (around line 1180)
// LOOK FOR THE CLOSING BRACE } OF updateFacultyProfile()

// ADD THIS METHOD RIGHT AFTER updateFacultyProfile():

    /**
     * Get client IP address (helper method)
     * @return string Client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * ==========================================
     * AUDIT LOGGING
     * ==========================================
     */
    
    /**
     * Log audit action
     * @param int $userId User ID
     * @param string $action Action performed
     * @param string $table Table affected
     * @param int $recordId Record ID
     * @param string $description Action description
     */
    private function logAuditAction($userId, $action, $table = null, $recordId = null, $description = null) {
        try {
            $sql = "
                INSERT INTO audit_logs (user_id, action, table_affected, record_id, description, timestamp)
                VALUES (?, ?, ?, ?, ?, NOW())
            ";
            
            $this->db->execute($sql, [
                $userId,
                $action,
                $table,
                $recordId,
                $description
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Audit logging failed', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * ==========================================
     * EMAIL METHODS
     * ==========================================
     */
    
    /**
     * Send email verification
     * @param string $email Email address
     * @param string $token Verification token
     */
    private function sendVerificationEmail($email, $token) {
        try {
            $verificationUrl = BASE_URL . 'auth/verify-email.php?token=' . $token;
            
            $subject = 'Verify Your Email - ' . SYSTEM_NAME;
            $message = "
                <h2>Email Verification Required</h2>
                <p>Thank you for registering with " . SYSTEM_NAME . ".</p>
                <p>Please click the link below to verify your email address:</p>
                <p><a href='{$verificationUrl}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verify Email</a></p>
                <p>Or copy and paste this URL in your browser: {$verificationUrl}</p>
                <p>This link will expire in 24 hours.</p>
                <p>If you didn't register for this account, please ignore this email.</p>
            ";
            
            $this->sendEmail($email, $subject, $message);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to send verification email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send approval/rejection email
     * @param string $email Email address
     * @param bool $approved Whether user was approved
     */
    private function sendApprovalEmail($email, $approved) {
        try {
            if ($approved) {
                $subject = 'Account Approved - ' . SYSTEM_NAME;
                $message = "
                    <h2>Account Approved!</h2>
                    <p>Your account has been approved by the administrator.</p>
                    <p>You can now log in to " . SYSTEM_NAME . ".</p>
                    <p><a href='" . BASE_URL . "auth/login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Now</a></p>
                ";
            } else {
                $subject = 'Account Registration Rejected - ' . SYSTEM_NAME;
                $message = "
                    <h2>Registration Rejected</h2>
                    <p>We regret to inform you that your registration request has been rejected.</p>
                    <p>Please contact the administrator for more information.</p>
                    <p>Administrator Email: " . SYSTEM_EMAIL . "</p>
                ";
            }
            
            $this->sendEmail($email, $subject, $message);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to send approval email', [
                'email' => $email,
                'approved' => $approved,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send welcome email for admin-created accounts
     * @param string $email Email address
     * @param string $password Temporary password
     */
    private function sendWelcomeEmail($email, $password) {
        try {
            $subject = 'Welcome to ' . SYSTEM_NAME;
            $message = "
                <h2>Welcome to " . SYSTEM_NAME . "!</h2>
                <p>Your account has been created by the administrator.</p>
                <p><strong>Login Details:</strong></p>
                <ul>
                    <li>Email: {$email}</li>
                    <li>Temporary Password: {$password}</li>
                </ul>
                <p>Please change your password after your first login for security.</p>
                <p><a href='" . BASE_URL . "auth/login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Now</a></p>
            ";
            
            $this->sendEmail($email, $subject, $message);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to send welcome email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send password reset email
     * @param string $email Email address
     * @param string $token Reset token
     */
    private function sendPasswordResetEmail($email, $token) {
        try {
            $resetUrl = BASE_URL . 'auth/reset-password.php?token=' . $token;
            
            $subject = 'Password Reset - ' . SYSTEM_NAME;
            $message = "
                <h2>Password Reset Request</h2>
                <p>You have requested to reset your password for " . SYSTEM_NAME . ".</p>
                <p>Click the link below to reset your password:</p>
                <p><a href='{$resetUrl}' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                <p>Or copy and paste this URL in your browser: {$resetUrl}</p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this password reset, please ignore this email.</p>
            ";
            
            $this->sendEmail($email, $subject, $message);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send email using PHPMailer or log in development
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Email message (HTML)
     */
    private function sendEmail($to, $subject, $message) {
        try {
            // Only log emails when specifically enabled, not in all development environments
            if (!MAIL_ENABLED || DEV_EMAIL_LOG) {
                $this->logEmail($to, $subject, $message);
                return true;
            }
            
            // Check if PHPMailer is available and email is configured
            if (class_exists('\PHPMailer\PHPMailer\PHPMailer') && !empty(MAIL_USERNAME)) {
                // Use PHPMailer for production email sending
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                
                // Enable optional debug output
                $mail->SMTPDebug = (defined('MAIL_DEBUG') && MAIL_DEBUG) ? \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER : \PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
                $mail->Debugoutput = function ($str, $level) {
                    // Stream PHPMailer debug output into Monolog at DEBUG level
                    getLogger()->debug(trim($str));
                };

                // Server settings
                $mail->isSMTP();
                $mail->Host = MAIL_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = MAIL_USERNAME;
                $mail->Password = MAIL_PASSWORD;
                $mail->SMTPSecure = MAIL_ENCRYPTION;
                $mail->Port = MAIL_PORT;
                
                // Recipients
                $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
                $mail->addAddress($to);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $this->getEmailTemplate($message, $subject);
                
                $mail->send();
                
                $this->logger->info('Email sent successfully', [
                    'to' => $to,
                    'subject' => $subject
                ]);
                
                return true;
            } else {
                // Fallback: log email since PHPMailer not configured
                $this->logEmail($to, $subject, $message);
                return true;
            }
            
        } catch (Exception $e) {
            // Log the error and fallback to email logging
            $this->logger->error('Email sending failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            
            // Log email content instead of failing
            $this->logEmail($to, $subject, $message);
            return false;
        }
    }
    
    /**
     * Log email content for development/debugging
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Email message
     */
    private function logEmail($to, $subject, $message) {
        $emailLog = [
            'timestamp' => date('Y-m-d H:i:s'),
            'to' => $to,
            'subject' => $subject,
            'message' => strip_tags($message), // Remove HTML for cleaner logs
            'html_message' => $message
        ];
        
        // Log to file
        $logFile = LOGS_PATH . 'emails.log';
        $logEntry = "[" . $emailLog['timestamp'] . "] EMAIL TO: " . $emailLog['to'] . "\n";
        $logEntry .= "SUBJECT: " . $emailLog['subject'] . "\n";
        $logEntry .= "MESSAGE: " . $emailLog['message'] . "\n";
        $logEntry .= str_repeat("-", 80) . "\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log with Monolog
        $this->logger->info('Email logged (not sent - development mode)', [
            'to' => $to,
            'subject' => $subject,
            'logged_to' => $logFile
        ]);
    }
    
    /**
     * Get professional email template with enhanced styling
     * @param string $content Email content
     * @param string $title Email title
     * @return string Formatted email HTML
     */
    private function getEmailTemplate($content, $title) {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background-color: #f4f4f4;
                }
                .email-container {
                    max-width: 600px;
                    margin: 20px auto;
                    background: white;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px 20px;
                    text-align: center;
                }
                .header h1 {
                    font-size: 28px;
                    font-weight: 300;
                    margin-bottom: 5px;
                }
                .header p {
                    font-size: 14px;
                    opacity: 0.9;
                }
                .content {
                    padding: 40px 30px;
                    background: white;
                }
                .content h2 {
                    color: #495057;
                    margin-bottom: 20px;
                    font-size: 24px;
                }
                .content p {
                    margin-bottom: 15px;
                    color: #666;
                }
                .btn {
                    display: inline-block;
                    padding: 15px 30px;
                    background: #007bff;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 20px 0;
                    font-weight: 500;
                    transition: background-color 0.3s ease;
                }
                .btn:hover {
                    background: #0056b3;
                }
                .btn-success { background: #28a745; }
                .btn-success:hover { background: #1e7e34; }
                .btn-danger { background: #dc3545; }
                .btn-danger:hover { background: #c82333; }
                .footer {
                    background: #f8f9fa;
                    padding: 25px 30px;
                    text-align: center;
                    border-top: 1px solid #e9ecef;
                }
                .footer p {
                    font-size: 12px;
                    color: #6c757d;
                    margin-bottom: 8px;
                }
                .footer a {
                    color: #007bff;
                    text-decoration: none;
                }
                .divider {
                    height: 1px;
                    background: #e9ecef;
                    margin: 20px 0;
                }
                .info-box {
                    background: #e7f3ff;
                    border-left: 4px solid #007bff;
                    padding: 15px;
                    margin: 20px 0;
                }
                .warning-box {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 15px;
                    margin: 20px 0;
                }
                @media only screen and (max-width: 600px) {
                    .email-container { margin: 10px; }
                    .content { padding: 20px; }
                    .header { padding: 20px; }
                    .btn { display: block; text-align: center; }
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>" . SYSTEM_NAME . "</h1>
                </div>
                <div class='content'>
                    {$content}
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . SYSTEM_NAME . ". All rights reserved.</p>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>For support, contact: <a href='mailto:" . SYSTEM_EMAIL . "'>" . SYSTEM_EMAIL . "</a></p>
                    <div class='divider'></div>
                    <p style='font-size: 10px; color: #999;'>
                        Email sent at " . date('Y-m-d H:i:s T') . "
                    </p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * ==========================================
     * EXPORT HELPER METHODS
     * ==========================================
     */
    
    /**
     * Export users to CSV
     * @param array $users User data
     * @param string $filename Filename
     * @return string File path
     */
    private function exportToCSV($users, $filename) {
        $filePath = EXPORTS_PATH . $filename;
        $file = fopen($filePath, 'w');
        
        // Headers
        $headers = ['ID', 'Username', 'Email', 'Role', 'Status', 'Full Name', 'Department', 'Identifier', 'Created At', 'Last Login'];
        fputcsv($file, $headers);
        
        // Data
        foreach ($users as $user) {
            $row = [
                $user['user_id'],
                $user['username'],
                $user['email'],
                $user['role'],
                $user['status'],
                $user['full_name'] ?? '',
                $user['department'] ?? '',
                $user['identifier'] ?? '',
                $user['created_at'],
                $user['last_login'] ?? 'Never'
            ];
            fputcsv($file, $row);
        }
        
        fclose($file);
        return $filePath;
    }
    
    /**
     * Export users to JSON
     * @param array $users User data
     * @param string $filename Filename
     * @return string File path
     */
    private function exportToJSON($users, $filename) {
        $filePath = EXPORTS_PATH . $filename;
        
        $exportData = [
            'export_date' => date('Y-m-d H:i:s'),
            'total_users' => count($users),
            'users' => $users
        ];
        
        file_put_contents($filePath, json_encode($exportData, JSON_PRETTY_PRINT));
        return $filePath;
    }
    
    /**
     * ==========================================
     * SESSION MANAGEMENT
     * ==========================================
     */
    
    /**
     * Check if user is logged in
     * @return bool
     */
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Check if session is expired
     * @return bool
     */
    public static function isSessionExpired() {
        if (!isset($_SESSION['login_time'])) {
            return true;
        }
        
        return (time() - $_SESSION['login_time']) > SESSION_TIMEOUT;
    }
    
    /**
     * Get current user role
     * @return string|null
     */
    public static function getCurrentUserRole() {
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Get current user ID
     * @return int|null
     */
    public static function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Check if current user has specific role
     * @param string $role Role to check
     * @return bool
     */
    public static function hasRole($role) {
        return self::getCurrentUserRole() === $role;
    }
    
    /**
     * Check if current user is admin
     * @return bool
     */
    public static function isAdmin() {
        return self::hasRole(self::ROLE_ADMIN);
    }
    
    /**
     * Check if current user is faculty
     * @return bool
     */
    public static function isFaculty() {
        return self::hasRole(self::ROLE_FACULTY);
    }
    
    /**
     * Check if current user is student
     * @return bool
     */
    public static function isStudent() {
        return self::hasRole(self::ROLE_STUDENT);
    }
    
    /**
     * Logout user
     * @return bool
     */
    public static function logout() {
        // Clear remember token
        $user = new User();
        $user->clearRememberToken();
        
        // Log the logout event
        if (self::isLoggedIn()) {
            getLogger()->info('User logged out', [
                'user_id' => self::getCurrentUserId(),
                'role' => self::getCurrentUserRole()
            ]);
        }
        
        // Clear session variables
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params["path"],
                'secure' => $params["secure"],
                'httponly' => $params["httponly"]
            ]);
        }
        
        // Destroy session
        session_destroy();
        
        return true;
    }

    /**
     * Require login (redirect if not logged in)
     * @param string $redirectTo Where to redirect after login
     */
    public static function requireLogin($redirectTo = null) {
        if (!self::isLoggedIn() || self::isSessionExpired()) {
            $redirectUrl = BASE_URL . 'auth/login.php';
            if ($redirectTo) {
                $redirectUrl .= '?redirect=' . urlencode($redirectTo);
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
    
    /**
     * Require specific role (redirect if insufficient permissions)
     * @param string|array $requiredRole Required role(s)
     */
    public static function requireRole($requiredRole) {
        self::requireLogin();
        
        $userRole = self::getCurrentUserRole();
        $hasPermission = false;
        
        if (is_array($requiredRole)) {
            $hasPermission = in_array($userRole, $requiredRole);
        } else {
            $hasPermission = ($userRole === $requiredRole);
        }
        
        if (!$hasPermission) {
            header('Location: ' . BASE_URL . 'auth/unauthorized.php');
            exit;
        }
    }
    
    /**
     * Get user statistics for admin dashboard
     * @return array User statistics
     */
    public function getUserStats() {
        try {
            $stats = [];
            
            // Total users by status
            $statusCounts = $this->db->fetchAll("
                SELECT status, COUNT(*) as count 
                FROM users 
                GROUP BY status
            ");
            
            foreach ($statusCounts as $row) {
                $stats['by_status'][$row['status']] = $row['count'];
            }
            
            // Total users by role
            $roleCounts = $this->db->fetchAll("
                SELECT role, COUNT(*) as count 
                FROM users 
                WHERE status = 'active'
                GROUP BY role
            ");
            
            foreach ($roleCounts as $row) {
                $stats['by_role'][$row['role']] = $row['count'];
            }
            
            // Recent registrations (last 7 days)
            $recentResult = $this->db->fetchRow("
                SELECT COUNT(*) as count 
                FROM users 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stats['recent_registrations'] = $recentResult['count'];
            
            // Pending approvals
            $pendingResult = $this->db->fetchRow("
                SELECT COUNT(*) as count 
                FROM users 
                WHERE status = 'pending'
            ");
            $stats['pending_approvals'] = $pendingResult['count'];
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logger->error('Error getting user statistics', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Delete user account (admin function)
     * @param int $userId User ID to delete
     * @param int $deletedBy Admin user ID who is deleting
     * @return array Deletion result
     */
    public function deleteUser($userId, $deletedBy) {
        try {
            $user = $this->db->fetchRow(
                "SELECT email, role FROM users WHERE user_id = ?",
                [$userId]
            );
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found.'
                ];
            }
            
            // Don't allow deleting admin users
            if ($user['role'] === self::ROLE_ADMIN) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete admin users.'
                ];
            }
            
            $this->db->beginTransaction();
            
            try {
                // Delete user record (cascading deletes will handle profile tables)
                $this->db->execute("DELETE FROM users WHERE user_id = ?", [$userId]);
                
                $this->db->commit();
                
                $this->logger->info('User deleted', [
                    'user_id' => $userId,
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'deleted_by' => $deletedBy
                ]);
                
                return [
                    'success' => true,
                    'message' => 'User deleted successfully.'
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error('User deletion error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Failed to delete user. Please try again.'
            ];
        }
    }
    
    /**
     * Change user status (admin function)
     * @param int $userId User ID
     * @param string $newStatus New status
     * @param int $changedBy Admin user ID
     * @return array Status change result
     */
    public function changeUserStatus($userId, $newStatus, $changedBy) {
        try {
            $validStatuses = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_REJECTED];
            
            if (!in_array($newStatus, $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid status.'
                ];
            }
            
            $user = $this->db->fetchRow(
                "SELECT email, role, status FROM users WHERE user_id = ?",
                [$userId]
            );
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found.'
                ];
            }
            
            if ($user['status'] === $newStatus) {
                return [
                    'success' => false,
                    'message' => 'User already has this status.'
                ];
            }
            
            // Update user status
            $this->db->execute(
                "UPDATE users SET status = ?, approved_by = ?, approved_at = NOW() WHERE user_id = ?",
                [$newStatus, $changedBy, $userId]
            );
            
            $this->logger->info('User status changed', [
                'user_id' => $userId,
                'old_status' => $user['status'],
                'new_status' => $newStatus,
                'changed_by' => $changedBy
            ]);
            
            return [
                'success' => true,
                'message' => 'User status updated successfully.'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Status change error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Failed to update user status.'
            ];
        }
    }
    
    /**
     * ==========================================
     * SEARCH AND FILTERING
     * ==========================================
     */
    
    /**
     * Search users with advanced filters
     * @param array $criteria Search criteria
     * @return array Search results
     */
    public function searchUsers($criteria) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Text search
            if (!empty($criteria['search'])) {
                $whereConditions[] = "(u.email LIKE ? OR u.username LIKE ? OR CONCAT(COALESCE(s.first_name, f.first_name, a.first_name), ' ', COALESCE(s.last_name, f.last_name, a.last_name)) LIKE ?)";
                $searchTerm = '%' . $criteria['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Role filter
            if (!empty($criteria['role'])) {
                $whereConditions[] = "u.role = ?";
                $params[] = $criteria['role'];
            }
            
            // Status filter
            if (!empty($criteria['status'])) {
                $whereConditions[] = "u.status = ?";
                $params[] = $criteria['status'];
            }
            
            // Department filter
            if (!empty($criteria['department'])) {
                $whereConditions[] = "(s.department = ? OR f.department = ? OR a.department = ?)";
                $params[] = $criteria['department'];
                $params[] = $criteria['department'];
                $params[] = $criteria['department'];
            }
            
            // Date range filter
            if (!empty($criteria['date_from'])) {
                $whereConditions[] = "u.created_at >= ?";
                $params[] = $criteria['date_from'];
            }
            
            if (!empty($criteria['date_to'])) {
                $whereConditions[] = "u.created_at <= ?";
                $params[] = $criteria['date_to'] . ' 23:59:59';
            }
            
            // Last login filter
            if (!empty($criteria['last_login_days'])) {
                $whereConditions[] = "u.last_login >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                $params[] = $criteria['last_login_days'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Sorting
            $orderBy = "ORDER BY u.created_at DESC";
            if (!empty($criteria['sort'])) {
                switch ($criteria['sort']) {
                    case 'name':
                        $orderBy = "ORDER BY full_name ASC";
                        break;
                    case 'email':
                        $orderBy = "ORDER BY u.email ASC";
                        break;
                    case 'role':
                        $orderBy = "ORDER BY u.role ASC";
                        break;
                    case 'last_login':
                        $orderBy = "ORDER BY u.last_login DESC";
                        break;
                }
            }
            
            // Pagination
            $limit = "";
            if (!empty($criteria['limit'])) {
                $limit = "LIMIT " . (int)$criteria['limit'];
                if (!empty($criteria['offset'])) {
                    $limit .= " OFFSET " . (int)$criteria['offset'];
                }
            }
            
            $sql = "
                SELECT u.*, 
                       CASE 
                           WHEN u.role = 'student' THEN CONCAT(s.first_name, ' ', s.last_name)
                           WHEN u.role = 'faculty' THEN CONCAT(f.first_name, ' ', f.last_name)
                           WHEN u.role = 'admin' THEN CONCAT(a.first_name, ' ', a.last_name)
                           ELSE u.username
                       END as full_name,
                       CASE 
                           WHEN u.role = 'student' THEN s.department
                           WHEN u.role = 'faculty' THEN f.department
                           WHEN u.role = 'admin' THEN a.department
                           ELSE NULL
                       END as department,
                       CASE 
                           WHEN u.role = 'student' THEN s.student_number
                           WHEN u.role = 'faculty' THEN f.employee_id
                           WHEN u.role = 'admin' THEN a.employee_id
                           ELSE NULL
                       END as identifier
                FROM users u
                LEFT JOIN students s ON u.user_id = s.user_id
                LEFT JOIN faculty f ON u.user_id = f.user_id
                LEFT JOIN admin_profiles a ON u.user_id = a.user_id
                {$whereClause}
                {$orderBy}
                {$limit}
            ";
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            $this->logger->error('User search error', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get user count for search criteria
     * @param array $criteria Search criteria
     * @return int User count
     */
    public function getUserSearchCount($criteria) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Apply same filters as searchUsers but just count
            if (!empty($criteria['search'])) {
                $whereConditions[] = "(u.email LIKE ? OR u.username LIKE ? OR CONCAT(COALESCE(s.first_name, f.first_name, a.first_name), ' ', COALESCE(s.last_name, f.last_name, a.last_name)) LIKE ?)";
                $searchTerm = '%' . $criteria['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($criteria['role'])) {
                $whereConditions[] = "u.role = ?";
                $params[] = $criteria['role'];
            }
            
            if (!empty($criteria['status'])) {
                $whereConditions[] = "u.status = ?";
                $params[] = $criteria['status'];
            }
            
            if (!empty($criteria['department'])) {
                $whereConditions[] = "(s.department = ? OR f.department = ? OR a.department = ?)";
                $params[] = $criteria['department'];
                $params[] = $criteria['department'];
                $params[] = $criteria['department'];
            }
            
            if (!empty($criteria['date_from'])) {
                $whereConditions[] = "u.created_at >= ?";
                $params[] = $criteria['date_from'];
            }
            
            if (!empty($criteria['date_to'])) {
                $whereConditions[] = "u.created_at <= ?";
                $params[] = $criteria['date_to'] . ' 23:59:59';
            }
            
            if (!empty($criteria['last_login_days'])) {
                $whereConditions[] = "u.last_login >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                $params[] = $criteria['last_login_days'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $sql = "
                SELECT COUNT(*) as total
                FROM users u
                LEFT JOIN students s ON u.user_id = s.user_id
                LEFT JOIN faculty f ON u.user_id = f.user_id
                LEFT JOIN admin_profiles a ON u.user_id = a.user_id
                {$whereClause}
            ";
            
            $result = $this->db->fetchRow($sql, $params);
            return (int)$result['total'];
            
        } catch (Exception $e) {
            $this->logger->error('User search count error', ['error' => $e->getMessage()]);
            return 0;
        }
    }
    
    /**
     * ==========================================
     * DEPARTMENT MANAGEMENT METHODS
     * ==========================================
     */
    
    /**
     * Get all active departments
     * @return array List of departments
     */
    public function getDepartments() {
        try {
            return $this->db->fetchAll(
                "SELECT department_id, department_name, department_code FROM departments WHERE is_active = 1 ORDER BY department_name"
            );
        } catch (Exception $e) {
            $this->logger->error('Error getting departments', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get department by ID
     * @param int $departmentId Department ID
     * @return array|null Department data or null if not found
     */
    public function getDepartmentById($departmentId) {
        try {
            return $this->db->fetchRow(
                "SELECT * FROM departments WHERE department_id = ? AND is_active = 1",
                [$departmentId]
            );
        } catch (Exception $e) {
            $this->logger->error('Error getting department', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Get users by department
     * @param int $departmentId Department ID
     * @param string $role Optional role filter
     * @return array List of users
     */
    public function getUsersByDepartment($departmentId, $role = null) {
        try {
            $sql = "
                SELECT u.*, 
                       COALESCE(s.first_name, f.first_name, a.first_name) as first_name,
                       COALESCE(s.last_name, f.last_name, a.last_name) as last_name,
                       COALESCE(s.phone, f.phone, a.phone) as phone
                FROM users u
                LEFT JOIN students s ON u.user_id = s.user_id
                LEFT JOIN faculty f ON u.user_id = f.user_id  
                LEFT JOIN admin_profiles a ON u.user_id = a.user_id
                WHERE u.department_id = ? AND u.status = 'active'
            ";
            
            $params = [$departmentId];
            
            if ($role) {
                $sql .= " AND u.role = ?";
                $params[] = $role;
            }
            
            $sql .= " ORDER BY COALESCE(s.last_name, f.last_name, a.last_name), COALESCE(s.first_name, f.first_name, a.first_name)";
            
            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            $this->logger->error('Error getting users by department', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get current user's department ID from session
     * @return int|null Department ID
     */
    public static function getCurrentUserDepartmentId() {
        return $_SESSION['department_id'] ?? null;
    }
    
    /**
     * Check if current user can access department data
     * @param int $departmentId Department ID to check
     * @return bool True if user can access
     */
    public static function canAccessDepartment($departmentId) {
        // Super admin can access all departments
        if (self::getCurrentUserRole() === 'super_admin') {
            return true;
        }
        
        // Regular users can only access their own department
        return self::getCurrentUserDepartmentId() == $departmentId;

    }
    

    
    
    /**
     * Get department filter for SQL queries
     * @return array Array with SQL condition and parameters
     */
    public static function getDepartmentFilter() {
        $currentRole = self::getCurrentUserRole();
        $currentDepartmentId = self::getCurrentUserDepartmentId();
        
        // Super admin sees everything
        if ($currentRole === 'super_admin') {
            return ['condition' => '', 'params' => []];
        }
        
        // Regular users see only their department
        if ($currentDepartmentId) {
            return [
                'condition' => ' AND u.department_id = ?',
                'params' => [$currentDepartmentId]
            ];
        }
        
        // No department assigned - see nothing
        return ['condition' => ' AND 1 = 0', 'params' => []];
    }
}
?>