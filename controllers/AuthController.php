<?php
// controllers/AuthController.php
// Authentication controller for login, register, and password management

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/User.php';

// Load email helper if it exists
if (file_exists(__DIR__ . '/../helpers/EmailHelper.php')) {
    require_once __DIR__ . '/../helpers/EmailHelper.php';
}

class AuthController {
    private $user;
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    // Handle registration
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'username' => sanitize_input($_POST['username']),
                'email' => sanitize_input($_POST['email']),
                'password' => $_POST['password'],
                'confirm_password' => $_POST['confirm_password'],
                'first_name' => sanitize_input($_POST['first_name']),
                'last_name' => sanitize_input($_POST['last_name']),
                'stage_name' => sanitize_input($_POST['stage_name'] ?? '')
            ];

            // Validation
            $errors = $this->validateRegistration($data);
            
            if (empty($errors)) {
                $result = $this->user->register($data);
                
                if ($result['success']) {
                    // Check if email verification is required
                    $require_verification = $this->getEmailVerificationSetting();
                    
                    if ($require_verification) {
                        // Send verification email
                        if (class_exists('EmailHelper')) {
                            $email_sent = EmailHelper::sendVerificationEmail($data['email'], $result['verification_token']);
                        } else {
                            $email_sent = $this->sendVerificationEmail($data['email'], $result['verification_token']);
                        }
                        
                        if ($email_sent) {
                            $_SESSION['success_message'] = 'Registration successful! Please check your email to verify your account.';
                        } else {
                            // Check if we're on localhost
                            $is_localhost = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                                           strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
                            
                            if ($is_localhost) {
                                $_SESSION['success_message'] = 'Registration successful! Email verification was logged (localhost mode). Check logs/emails/ folder. Token: ' . substr($result['verification_token'], 0, 10) . '...';
                            } else {
                                $_SESSION['success_message'] = 'Registration successful! However, verification email could not be sent. Please contact support.';
                            }
                            // Auto-verify if email sending fails (admin can verify manually later)
                            $this->user->verifyEmail($result['verification_token']);
                        }
                    } else {
                        // No verification required - auto verify
                        $this->user->verifyEmail($result['verification_token']);
                        $_SESSION['success_message'] = 'Registration successful! You can now login.';
                    }
                    
                    redirect(SITE_URL . '/login.php');
                } else {
                    $_SESSION['error_message'] = $result['error'];
                }
            } else {
                $_SESSION['error_message'] = implode('<br>', $errors);
            }
        }
        
        include 'views/auth/register.php';
    }

    // Handle login
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username_or_email = sanitize_input($_POST['email'] ?? $_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username_or_email)) {
                $_SESSION['error_message'] = 'Please enter your username or email address.';
            } elseif (empty($password)) {
                $_SESSION['error_message'] = 'Please enter your password.';
            } else {
                $result = $this->user->login($username_or_email, $password);
            
            if ($result['success']) {
                // Check if email verification is required
                $require_verification = $this->getEmailVerificationSetting();
                
                if ($require_verification && !$result['user']['email_verified']) {
                    $_SESSION['error_message'] = 'Please verify your email before logging in.';
                } else {
                    $_SESSION['success_message'] = 'Welcome back!';
                    redirect(SITE_URL . '/dashboard.php');
                }
                } else {
                    $_SESSION['error_message'] = $result['error'];
                }
            }
        }
        
        include 'views/auth/login.php';
    }

    // Handle logout
    public function logout() {
        session_destroy();
        redirect(SITE_URL . '/index.php');
    }

    // Handle password reset request
    public function forgotPassword() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = sanitize_input($_POST['email']);
            
            $result = $this->user->requestPasswordReset($email);
            
            if ($result['success']) {
                // Send reset email
                if (class_exists('EmailHelper')) {
                    $email_sent = EmailHelper::sendPasswordResetEmail($email, $result['reset_token']);
                } else {
                    $this->sendPasswordResetEmail($email, $result['reset_token']);
                    $email_sent = true;
                }
                
                if ($email_sent) {
                    $_SESSION['success_message'] = 'Password reset instructions sent to your email.';
                } else {
                    $_SESSION['success_message'] = 'Password reset instructions sent. If you don\'t receive the email, please check your spam folder or contact support.';
                }
            } else {
                $_SESSION['error_message'] = $result['error'];
            }
        }
        
        include 'views/auth/forgot-password.php';
    }

    // Handle password reset
    public function resetPassword() {
        $token = $_GET['token'] ?? '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                $_SESSION['error_message'] = 'Passwords do not match.';
            } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
                $_SESSION['error_message'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
            } else {
                $result = $this->user->resetPassword($token, $new_password);
                
                if ($result['success']) {
                    $_SESSION['success_message'] = 'Password reset successful! You can now login with your new password.';
                    redirect(SITE_URL . '/login.php');
                } else {
                    $_SESSION['error_message'] = $result['error'];
                }
            }
        }
        
        include 'views/auth/reset-password.php';
    }

    // Handle email verification
    public function verifyEmail() {
        $token = $_GET['token'] ?? '';
        
        if ($this->user->verifyEmail($token)) {
            $_SESSION['success_message'] = 'Email verified successfully! You can now login.';
        } else {
            $_SESSION['error_message'] = 'Invalid or expired verification token.';
        }
        
        redirect(SITE_URL . '/login.php');
    }

    // Validate registration data
    private function validateRegistration($data) {
        $errors = [];
        
        if (empty($data['username'])) {
            $errors[] = 'Username is required.';
        } elseif (strlen($data['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters long.';
        } elseif ($this->user->usernameExists($data['username'])) {
            $errors[] = 'Username already exists.';
        }
        
        if (empty($data['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        } elseif ($this->user->emailExists($data['email'])) {
            $errors[] = 'Email already exists.';
        }
        
        if (empty($data['password'])) {
            $errors[] = 'Password is required.';
        } elseif (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        }
        
        if ($data['password'] !== $data['confirm_password']) {
            $errors[] = 'Passwords do not match.';
        }
        
        if (empty($data['first_name'])) {
            $errors[] = 'First name is required.';
        }
        
        if (empty($data['stage_name'])) {
            $errors[] = 'Stage name is required.';
        } elseif (strlen($data['stage_name']) < 2) {
            $errors[] = 'Stage name must be at least 2 characters long.';
        }
        
        return $errors;
    }

    // Get email verification setting from database
    private function getEmailVerificationSetting() {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'require_email_verification'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['setting_value'] ?? 'true') === 'true';
        } catch (Exception $e) {
            // Default to true if setting not found
            return true;
        }
    }

    // Send verification email
    private function sendVerificationEmail($email, $token) {
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
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
        $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        // Try to send email
        $sent = @mail($email, $subject, $message, $headers);
        
        // Log email sending attempt with more details
        if ($sent) {
            error_log("Email verification sent successfully to: $email");
        } else {
            $error = error_get_last();
            $error_msg = $error ? $error['message'] : 'Unknown error';
            error_log("Email verification FAILED to send to: $email. Error: $error_msg");
            
            // Check if we're on localhost (mail() often doesn't work on localhost)
            $is_localhost = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                           strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
            if ($is_localhost) {
                error_log("NOTE: mail() function may not work on localhost. Please configure SMTP or use EmailHelper.");
            }
        }
        
        return $sent;
    }

    // Send password reset email
    private function sendPasswordResetEmail($email, $token) {
        $subject = 'Password Reset - ' . SITE_NAME;
        $reset_url = SITE_URL . '/reset-password.php?token=' . $token;
        
        $message = "
        <html>
        <head>
            <title>Password Reset</title>
        </head>
        <body>
            <h2>Password Reset Request</h2>
            <p>You requested a password reset. Click the link below to reset your password:</p>
            <p><a href='" . $reset_url . "'>Reset Password</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn't request this, please ignore this email.</p>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
        
        mail($email, $subject, $message, $headers);
    }
}
?>
