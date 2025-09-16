<?php
/**
 * Email Service Class
 * Handles all email functionality using PHPMailer
 * Timetable Management System
 */

// Ensure config is loaded
if (!defined('SYSTEM_ACCESS')) {
    require_once __DIR__ . '/../config/config.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    public $mail;  // Changed to public for debugging access
    private $logger;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Initialize logger with fallback
        try {
            $this->logger = function_exists('getLogger') ? getLogger() : null;
        } catch (Exception $e) {
            $this->logger = null;
        }
        
        $this->configureSMTP();
    }
    
    /**
     * Log message with fallback
     */
    private function log($message, $level = 'info') {
        if ($this->logger) {
            $this->logger->$level($message);
        } else {
            // Fallback to error_log
            error_log("[EmailService] [{$level}] {$message}");
        }
    }
    
    /**
     * Configure SMTP settings
     */
    private function configureSMTP() {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = MAIL_HOST;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = MAIL_USERNAME;
            $this->mail->Password = MAIL_PASSWORD;
            $this->mail->SMTPSecure = MAIL_ENCRYPTION;
            $this->mail->Port = MAIL_PORT;
            
            // Default sender
            $this->mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            
            // Enable verbose debug output in development
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $this->mail->Debugoutput = function($str, $level) {
                    $this->log("SMTP Debug: " . $str, 'debug');
                };
            }
            
        } catch (Exception $e) {
            $this->log("SMTP Configuration Error: " . $e->getMessage(), 'error');
            throw new Exception("Email service configuration failed");
        }
    }
    
    /**
     * Send email
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $altBody Plain text alternative
     * @param array $attachments Optional attachments
     * @return bool Success status
     */
    public function sendEmail($to, $subject, $body, $altBody = '', $attachments = []) {
        try {
            // Clear previous recipients
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            
            // Recipients
            $this->mail->addAddress($to);
            
            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = $altBody ?: strip_tags($body);
            
            // Add attachments if provided
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    $this->mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                } else {
                    $this->mail->addAttachment($attachment);
                }
            }
            
            // Check if email logging is specifically enabled (only log when DEV_EMAIL_LOG is true)
            if (defined('DEV_EMAIL_LOG') && DEV_EMAIL_LOG) {
                $this->logEmail($to, $subject, $body);
                return true;
            }
            
            // Send the email
            $result = $this->mail->send();
            
            if ($result) {
                $this->log("Email sent successfully to: " . $to, 'info');
                return true;
            } else {
                $this->log("Failed to send email to: " . $to, 'error');
                return false;
            }
            
        } catch (Exception $e) {
            $this->log("Email sending failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Send verification email
     */
    public function sendVerificationEmail($email, $username, $verificationToken) {
        $verificationUrl = BASE_URL . "auth/verify-email.php?token=" . $verificationToken;
        
        $subject = "Verify Your Email - " . SYSTEM_NAME;
        $body = $this->getEmailTemplate('verification', [
            'username' => $username,
            'verification_url' => $verificationUrl,
            'system_name' => SYSTEM_NAME
        ]);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($email, $username, $resetToken) {
        $resetUrl = BASE_URL . "auth/reset-password.php?token=" . $resetToken;
        
        $subject = "Password Reset Request - " . SYSTEM_NAME;
        $body = $this->getEmailTemplate('password_reset', [
            'username' => $username,
            'reset_url' => $resetUrl,
            'system_name' => SYSTEM_NAME,
            'expiry_time' => '1 hour'
        ]);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Send notification email
     */
    public function sendNotificationEmail($email, $username, $title, $message, $actionUrl = null) {
        $subject = $title . " - " . SYSTEM_NAME;
        $body = $this->getEmailTemplate('notification', [
            'username' => $username,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'system_name' => SYSTEM_NAME
        ]);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Send timetable update notification
     */
    public function sendTimetableUpdateEmail($email, $username, $changes) {
        $subject = "Timetable Updated - " . SYSTEM_NAME;
        $body = $this->getEmailTemplate('timetable_update', [
            'username' => $username,
            'changes' => $changes,
            'system_name' => SYSTEM_NAME,
            'dashboard_url' => BASE_URL . 'dashboard.php'
        ]);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($template, $variables = []) {
        $templatePath = ROOT_PATH . "templates/email/{$template}.html";
        
        if (file_exists($templatePath)) {
            $content = file_get_contents($templatePath);
            
            // Replace variables
            foreach ($variables as $key => $value) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }
            
            return $content;
        }
        
        // Fallback to basic template
        return $this->getBasicEmailTemplate($template, $variables);
    }
    
    /**
     * Basic email template fallback
     */
    private function getBasicEmailTemplate($template, $variables) {
        $baseStyle = "
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        ";
        
        switch ($template) {
            case 'verification':
                return "
                    <html><head>{$baseStyle}</head><body>
                    <div class='container'>
                        <div class='header'><h1>Email Verification</h1></div>
                        <div class='content'>
                            <h2>Hello {$variables['username']}!</h2>
                            <p>Thank you for registering with {$variables['system_name']}. Please click the button below to verify your email address:</p>
                            <a href='{$variables['verification_url']}' class='button'>Verify Email</a>
                            <p>If the button doesn't work, copy and paste this link: {$variables['verification_url']}</p>
                        </div>
                        <div class='footer'> " . date('Y') . " {$variables['system_name']}</div>
                    </div>
                    </body></html>
                ";
                
            case 'password_reset':
                return "
                    <html><head>{$baseStyle}</head><body>
                    <div class='container'>
                        <div class='header'><h1>Password Reset</h1></div>
                        <div class='content'>
                            <h2>Hello {$variables['username']}!</h2>
                            <p>You requested a password reset for your {$variables['system_name']} account. Click the button below to reset your password:</p>
                            <a href='{$variables['reset_url']}' class='button'>Reset Password</a>
                            <p>This link will expire in {$variables['expiry_time']}.</p>
                            <p>If you didn't request this, please ignore this email.</p>
                        </div>
                        <div class='footer'> " . date('Y') . " {$variables['system_name']}</div>
                    </div>
                    </body></html>
                ";
                
            case 'notification':
                $actionButton = $variables['action_url'] ? "<a href='{$variables['action_url']}' class='button'>View Details</a>" : "";
                return "
                    <html><head>{$baseStyle}</head><body>
                    <div class='container'>
                        <div class='header'><h1>{$variables['title']}</h1></div>
                        <div class='content'>
                            <h2>Hello {$variables['username']}!</h2>
                            <p>{$variables['message']}</p>
                            {$actionButton}
                        </div>
                        <div class='footer'> " . date('Y') . " {$variables['system_name']}</div>
                    </div>
                    </body></html>
                ";
                
            default:
                return "
                    <html><head>{$baseStyle}</head><body>
                    <div class='container'>
                        <div class='header'><h1>{$variables['system_name']}</h1></div>
                        <div class='content'>
                            <h2>Hello {$variables['username']}!</h2>
                            <p>This is a notification from {$variables['system_name']}.</p>
                        </div>
                        <div class='footer'> " . date('Y') . " {$variables['system_name']}</div>
                    </div>
                    </body></html>
                ";
        }
    }
    
    /**
     * Log email for development
     */
    private function logEmail($to, $subject, $body) {
        $logFile = LOGS_PATH . 'emails.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $logEntry = "[{$timestamp}] EMAIL TO: {$to}\n";
        $logEntry .= "SUBJECT: {$subject}\n";
        $logEntry .= "MESSAGE: " . strip_tags($body) . "\n";
        $logEntry .= str_repeat("-", 80) . "\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Test email configuration
     */
    public function testConnection() {
        try {
            $this->mail->smtpConnect();
            $this->mail->smtpClose();
            return true;
        } catch (Exception $e) {
            $this->log("SMTP Connection Test Failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
}
