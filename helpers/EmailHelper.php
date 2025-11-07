<?php
// helpers/EmailHelper.php
// Email helper functions

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class EmailHelper {
    
    /**
     * Get email settings from database
     */
    private static function getEmailSettings() {
        static $settings = null;
        if ($settings !== null) {
            return $settings;
        }
        
        $settings = [];
        try {
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->query("SELECT setting_key, setting_value FROM email_settings");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Fallback to config constants
            error_log("Error loading email settings: " . $e->getMessage());
        }
        
        // Fallback to config constants if database settings not available
        if (empty($settings)) {
            $settings = [
                'email_method' => defined('SMTP_HOST') ? 'smtp' : 'mail',
                'smtp_host' => defined('SMTP_HOST') ? SMTP_HOST : '',
                'smtp_port' => defined('SMTP_PORT') ? SMTP_PORT : '587',
                'smtp_username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
                'smtp_password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
                'smtp_encryption' => 'tls',
                'from_email' => defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@example.com',
                'from_name' => defined('FROM_NAME') ? FROM_NAME : SITE_NAME,
                'reply_to_email' => ''
            ];
        }
        
        return $settings;
    }
    
    /**
     * Get email template by slug
     */
    public static function getTemplate($slug, $variables = []) {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT * FROM email_templates WHERE slug = ? AND is_active = 1");
            $stmt->execute([$slug]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                return null;
            }
            
            // Replace variables in subject and body
            $subject = self::replaceVariables($template['subject'], $variables);
            $body = self::replaceVariables($template['body'], $variables);
            
            return [
                'subject' => $subject,
                'body' => $body
            ];
        } catch (Exception $e) {
            error_log("Error loading template: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Replace variables in text
     */
    private static function replaceVariables($text, $variables = []) {
        // Default variables
        $defaults = [
            'site_name' => SITE_NAME,
            'site_url' => SITE_URL,
            'name' => '',
            'email' => ''
        ];
        
        $vars = array_merge($defaults, $variables);
        
        foreach ($vars as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        
        return $text;
    }
    
    /**
     * Send email using template
     */
    public static function sendEmailFromTemplate($to, $template_slug, $variables = []) {
        $template = self::getTemplate($template_slug, $variables);
        if (!$template) {
            error_log("Template not found: $template_slug");
            return false;
        }
        
        return self::sendEmail($to, $template['subject'], $template['body']);
    }
    
    /**
     * Send email using SMTP or mail() function
     */
    public static function sendEmail($to, $subject, $message, $headers = []) {
        $settings = self::getEmailSettings();
        $email_method = $settings['email_method'] ?? 'smtp';
        
        // Check if PHPMailer is available and method is SMTP
        if ($email_method === 'smtp' && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return self::sendEmailSMTP($to, $subject, $message);
        }
        
        // Fallback to mail() function
        return self::sendEmailBasic($to, $subject, $message, $headers);
    }
    
    /**
     * Send email using SMTP (PHPMailer)
     */
    private static function sendEmailSMTP($to, $subject, $message) {
        try {
            $settings = self::getEmailSettings();
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_username'] ?? '';
            $mail->Password = $settings['smtp_password'] ?? '';
            
            $encryption = $settings['smtp_encryption'] ?? 'tls';
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Port = (int)($settings['smtp_port'] ?? 587);
            
            // Recipients
            $from_email = $settings['from_email'] ?? (defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@example.com');
            $from_name = $settings['from_name'] ?? (defined('FROM_NAME') ? FROM_NAME : SITE_NAME);
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            
            // Reply-To
            if (!empty($settings['reply_to_email'])) {
                $mail->addReplyTo($settings['reply_to_email']);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            
            // Anti-spam settings - Improved headers
            $mail->Priority = 3; // Normal priority
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            
            // SPF/DKIM friendly headers
            $domain = parse_url(defined('SITE_URL') ? SITE_URL : 'http://localhost', PHP_URL_HOST);
            $mail->addCustomHeader('Message-ID', '<' . time() . '.' . md5($to . $subject) . '@' . $domain . '>');
            $mail->addCustomHeader('Date', date('r'));
            $mail->addCustomHeader('X-Mailer', 'PHP/' . phpversion());
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
            $mail->addCustomHeader('Importance', 'Normal');
            
            // List management headers
            $mail->addCustomHeader('List-Unsubscribe', '<' . (defined('SITE_URL') ? SITE_URL : '') . '/unsubscribe>');
            $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            
            // Authentication headers
            $mail->addCustomHeader('Authentication-Results', $domain . '; auth=pass');
            
            // Precedence header
            $mail->addCustomHeader('Precedence', 'bulk');
            
            // Content language
            $mail->addCustomHeader('Content-Language', 'en-US');
            
            $mail->send();
            error_log("Email sent successfully via SMTP to: $to");
            
            // Log to email queue
            self::logToQueue($to, $subject, $message, 'sent');
            
            return true;
            
        } catch (Exception $e) {
            error_log("SMTP Error: " . ($mail->ErrorInfo ?? $e->getMessage()));
            // Log to queue as failed
            self::logToQueue($to, $subject, $message, 'failed', $mail->ErrorInfo ?? $e->getMessage());
            // Fallback to basic mail
            return self::sendEmailBasic($to, $subject, $message);
        }
    }
    
    /**
     * Send email using basic mail() function
     */
    private static function sendEmailBasic($to, $subject, $message, $headers = []) {
        $settings = self::getEmailSettings();
        
        // Build headers
        $from_email = $settings['from_email'] ?? (defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@example.com');
        $from_name = $settings['from_name'] ?? (defined('FROM_NAME') ? FROM_NAME : SITE_NAME);
        $reply_to = $settings['reply_to_email'] ?? $from_email;
        
        // Build comprehensive headers to prevent spam
        $email_headers = "MIME-Version: 1.0" . "\r\n";
        $email_headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $email_headers .= "From: " . $from_name . " <" . $from_email . ">" . "\r\n";
        $email_headers .= "Reply-To: " . $reply_to . "\r\n";
        $email_headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        // Anti-spam headers - Improved
        $domain = parse_url(defined('SITE_URL') ? SITE_URL : 'http://localhost', PHP_URL_HOST);
        $email_headers .= "X-Priority: 3" . "\r\n";
        $email_headers .= "X-MSMail-Priority: Normal" . "\r\n";
        $email_headers .= "Importance: Normal" . "\r\n";
        $email_headers .= "Precedence: bulk" . "\r\n";
        $email_headers .= "Content-Language: en-US" . "\r\n";
        
        // List management headers
        $email_headers .= "List-Unsubscribe: <" . (defined('SITE_URL') ? SITE_URL : '') . "/unsubscribe>" . "\r\n";
        $email_headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click" . "\r\n";
        
        // Message-ID for better deliverability
        $message_id = '<' . time() . '.' . md5($to . $subject) . '@' . $domain . '>';
        $email_headers .= "Message-ID: " . $message_id . "\r\n";
        
        // Date header
        $email_headers .= "Date: " . date('r') . "\r\n";
        
        // Authentication headers
        $email_headers .= "Authentication-Results: " . $domain . "; auth=pass" . "\r\n";
        
        // Add custom headers
        if (!empty($headers)) {
            foreach ($headers as $header) {
                $email_headers .= $header . "\r\n";
            }
        }
        
        // Try to send email
        $sent = @mail($to, $subject, $message, $email_headers);
        
        // Log the attempt
        if ($sent) {
            error_log("Email sent successfully via mail() to: $to");
            self::logToQueue($to, $subject, $message, 'sent');
        } else {
            $error = error_get_last();
            $error_msg = $error ? $error['message'] : 'Unknown error';
            error_log("Email sending failed to: $to. Error: " . $error_msg);
            
            self::logToQueue($to, $subject, $message, 'failed', $error_msg);
            
            // On localhost/XAMPP, mail() often fails, so log for debugging
            if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                error_log("NOTE: mail() function may not work on localhost. Consider using SMTP.");
                // For localhost, we can write to a log file as fallback
                self::logEmailForLocalhost($to, $subject, $message);
            }
        }
        
        return $sent;
    }
    
    /**
     * Log email to queue
     */
    private static function logToQueue($to, $subject, $body, $status, $error_message = null) {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Check if email_queue table exists
            $checkTable = $conn->query("SHOW TABLES LIKE 'email_queue'");
            if ($checkTable->rowCount() > 0) {
                $stmt = $conn->prepare("
                    INSERT INTO email_queue (to_email, subject, body, status, error_message, sent_at) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $sent_at = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
                $stmt->execute([$to, $subject, $body, $status, $error_message, $sent_at]);
            }
        } catch (Exception $e) {
            // Silently fail - queue logging is optional
            error_log("Error logging to email queue: " . $e->getMessage());
        }
    }
    
    /**
     * Log email for localhost development (when mail() doesn't work)
     */
    private static function logEmailForLocalhost($to, $subject, $message) {
        $log_dir = __DIR__ . '/../logs/emails/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . date('Y-m-d') . '.txt';
        $log_entry = "\n" . str_repeat('=', 80) . "\n";
        $log_entry .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $log_entry .= "To: $to\n";
        $log_entry .= "Subject: $subject\n";
        $log_entry .= "Message:\n$message\n";
        $log_entry .= str_repeat('=', 80) . "\n";
        
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        error_log("Email logged to: $log_file");
    }
    
    /**
     * Send verification email
     */
    public static function sendVerificationEmail($email, $token) {
        $subject = 'Verify Your Email - ' . SITE_NAME;
        $verification_url = SITE_URL . '/verify-email.php?token=' . urlencode($token);
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Email Verification</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <h2 style='color: #2196F3; margin-bottom: 20px;'>Welcome to " . htmlspecialchars(SITE_NAME) . "!</h2>
                <p>Thank you for registering. Please click the button below to verify your email address:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . htmlspecialchars($verification_url) . "' style='background: #2196F3; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Verify Email</a>
                </p>
                <p style='font-size: 12px; color: #666; margin-top: 20px;'>Or copy and paste this link into your browser:</p>
                <p style='font-size: 12px; color: #666; word-break: break-all; background: #f9f9f9; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($verification_url) . "</p>
                <p style='font-size: 12px; color: #999; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;'>If you didn't create an account, please ignore this email.</p>
            </div>
        </body>
        </html>
        ";
        
        return self::sendEmail($email, $subject, $message);
    }
    
    /**
     * Send password reset email
     */
    public static function sendPasswordResetEmail($email, $token) {
        $subject = 'Password Reset - ' . SITE_NAME;
        $reset_url = SITE_URL . '/reset-password.php?token=' . urlencode($token);
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <h2 style='color: #2196F3; margin-bottom: 20px;'>Password Reset Request</h2>
                <p>You requested a password reset. Click the button below to reset your password:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . htmlspecialchars($reset_url) . "' style='background: #2196F3; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>Reset Password</a>
                </p>
                <p style='font-size: 12px; color: #666;'>Or copy and paste this link into your browser:</p>
                <p style='font-size: 12px; color: #666; word-break: break-all; background: #f9f9f9; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($reset_url) . "</p>
                <p style='font-size: 12px; color: #999; margin-top: 20px;'>This link will expire in 1 hour.</p>
                <p style='font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px;'>If you didn't request this, please ignore this email.</p>
            </div>
        </body>
        </html>
        ";
        
        return self::sendEmail($email, $subject, $message);
    }
}

