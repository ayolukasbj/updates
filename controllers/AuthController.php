<?php
// controllers/AuthController.php
// Authentication controller for login, register, and password management

// Load config with proper path resolution
$config_path = __DIR__ . '/../config/config.php';
if (!file_exists($config_path)) {
    $config_path = 'config/config.php';
}
if (!file_exists($config_path)) {
    // Try absolute path
    $config_path = $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
}
require_once $config_path;

// Ensure sanitize_input function exists
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        if (is_null($data)) {
            return '';
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

$db_path = __DIR__ . '/../config/database.php';
if (!file_exists($db_path)) {
    $db_path = 'config/database.php';
}
require_once $db_path;

$user_class_path = __DIR__ . '/../classes/User.php';
if (!file_exists($user_class_path)) {
    $user_class_path = 'classes/User.php';
}
require_once $user_class_path;

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
            try {
                $errors = $this->validateRegistration($data);
            } catch (Exception $e) {
                error_log('Validation error: ' . $e->getMessage());
                $_SESSION['error_message'] = 'Validation error: ' . $e->getMessage();
                $errors = ['Validation failed'];
            }
            
            if (empty($errors)) {
                try {
                    $result = $this->user->register($data);
                } catch (Exception $e) {
                    error_log('Register method error: ' . $e->getMessage());
                    error_log('Stack trace: ' . $e->getTraceAsString());
                    $_SESSION['error_message'] = 'Registration failed: ' . $e->getMessage();
                    $result = ['success' => false, 'error' => 'Registration failed. Please try again.'];
                } catch (Error $e) {
                    error_log('Register method fatal error: ' . $e->getMessage());
                    error_log('Stack trace: ' . $e->getTraceAsString());
                    $_SESSION['error_message'] = 'Registration failed. Please contact support.';
                    $result = ['success' => false, 'error' => 'Registration failed. Please contact support.'];
                }
                
                if (isset($result['success']) && $result['success']) {
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
                            try {
                                if (method_exists($this->user, 'verifyEmail')) {
                                    $this->user->verifyEmail($result['verification_token']);
                                }
                            } catch (Exception $e) {
                                error_log('Auto-verify error: ' . $e->getMessage());
                            }
                        }
                    } else {
                        // No verification required - auto verify
                        try {
                            if (method_exists($this->user, 'verifyEmail')) {
                                $this->user->verifyEmail($result['verification_token']);
                            }
                        } catch (Exception $e) {
                            error_log('Auto-verify error: ' . $e->getMessage());
                        }
                        $_SESSION['success_message'] = 'Registration successful! You can now login.';
                    }
                    
                    // Clear output buffer before redirect
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    redirect(SITE_URL . '/login.php');
                } else {
                    $error_msg = isset($result['error']) ? $result['error'] : 'Registration failed. Please try again.';
                    $_SESSION['error_message'] = $error_msg;
                    error_log('Registration failed in controller: ' . $error_msg);
                }
            } else {
                $_SESSION['error_message'] = implode('<br>', $errors);
            }
        }
        
        $view_path = __DIR__ . '/../views/auth/register.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            // Fallback to relative path
            include 'views/auth/register.php';
        }
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
                    $_SESSION['unverified_email'] = $result['user']['email']; // Store email for resend
                    // Don't redirect, show login form with resend button
                } else {
                    $_SESSION['success_message'] = 'Welcome back!';
                    
                    // Clear any output buffers
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    
                    // Redirect to dashboard - use absolute URL for reliability
                    $redirect_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/dashboard.php' : '/dashboard.php';
                    
                    // Simple header redirect - most reliable
                    if (!headers_sent()) {
                        header('Location: ' . $redirect_url, true, 302);
                        exit;
                    } else {
                        // Headers already sent - use JavaScript
                        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><script>window.location.href = "' . htmlspecialchars($redirect_url) . '";</script></head><body>Redirecting...</body></html>';
                        exit;
                    }
                }
                } else {
                    $_SESSION['error_message'] = $result['error'];
                }
            }
        }
        
        $view_path = __DIR__ . '/../views/auth/login.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            // Fallback to relative path
            include 'views/auth/login.php';
        }
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
        
        $view_path = __DIR__ . '/../views/auth/forgot-password.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            // Fallback to relative path
            include 'views/auth/forgot-password.php';
        }
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
        
        $view_path = __DIR__ . '/../views/auth/reset-password.php';
        if (file_exists($view_path)) {
            include $view_path;
        } else {
            // Fallback to relative path
            include 'views/auth/reset-password.php';
        }
    }

    // Handle email verification
    public function verifyEmail() {
        try {
            $token = $_GET['token'] ?? '';
            
            if (empty($token)) {
                $_SESSION['error_message'] = 'Verification token is required.';
                redirect(SITE_URL . '/login.php');
                return;
            }
            
            if (method_exists($this->user, 'verifyEmail')) {
                $result = $this->user->verifyEmail($token);
                
                if ($result) {
                    $_SESSION['success_message'] = 'Email verified successfully! You can now login.';
                } else {
                    $_SESSION['error_message'] = 'Invalid or expired verification token. Please request a new verification email.';
                }
            } else {
                $_SESSION['error_message'] = 'Email verification is not available.';
            }
        } catch (Exception $e) {
            error_log('Email verification error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            $_SESSION['error_message'] = 'Email verification failed. Please contact support.';
        } catch (Error $e) {
            error_log('Email verification fatal error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            $_SESSION['error_message'] = 'Email verification failed. Please contact support.';
        }
        
        // Clear output buffer before redirect
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        redirect(SITE_URL . '/login.php');
    }
    
    // Resend verification email
    public function resendVerification() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = sanitize_input($_POST['email'] ?? '');
            
            if (empty($email)) {
                $_SESSION['error_message'] = 'Email address is required.';
                return;
            }
            
            try {
                // Get user by email
                $user_data = $this->user->getUserByEmail($email);
                
                if (!$user_data) {
                    $_SESSION['error_message'] = 'Email address not found.';
                    return;
                }
                
                // Check if already verified
                if (!empty($user_data['email_verified'])) {
                    $_SESSION['error_message'] = 'This email is already verified.';
                    return;
                }
                
                // Generate new verification token
                if (!function_exists('generate_token')) {
                    function generate_token($length = 32) {
                        if (function_exists('random_bytes')) {
                            return bin2hex(random_bytes($length));
                        } elseif (function_exists('openssl_random_pseudo_bytes')) {
                            return bin2hex(openssl_random_pseudo_bytes($length));
                        } else {
                            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                            $token = '';
                            for ($i = 0; $i < $length * 2; $i++) {
                                $token .= $characters[rand(0, strlen($characters) - 1)];
                            }
                            return $token;
                        }
                    }
                }
                
                $new_token = generate_token();
                
                // Update user with new token
                try {
                    $stmt = $this->db->prepare("UPDATE users SET verification_token = ? WHERE email = ?");
                    $stmt->execute([$new_token, $email]);
                } catch (Exception $e) {
                    error_log('Error updating verification token: ' . $e->getMessage());
                    $_SESSION['error_message'] = 'Failed to generate verification token.';
                    return;
                }
                
                // Send verification email
                $email_sent = false;
                if (class_exists('EmailHelper')) {
                    $email_sent = EmailHelper::sendVerificationEmail($email, $new_token);
                } else {
                    $email_sent = $this->sendVerificationEmail($email, $new_token);
                }
                
                if ($email_sent) {
                    $_SESSION['success_message'] = 'Verification email sent! Please check your inbox and spam folder.';
                } else {
                    $_SESSION['error_message'] = 'Failed to send verification email. Please try again later or contact support.';
                }
            } catch (Exception $e) {
                error_log('Resend verification error: ' . $e->getMessage());
                $_SESSION['error_message'] = 'Failed to resend verification email. Please try again later.';
            }
        }
    }

    // Validate registration data
    private function validateRegistration($data) {
        $errors = [];
        
        if (empty($data['username'])) {
            $errors[] = 'Username is required.';
        } elseif (strlen($data['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters long.';
        } else {
            try {
                if (method_exists($this->user, 'usernameExists') && $this->user->usernameExists($data['username'])) {
                    $errors[] = 'Username already exists.';
                }
            } catch (Exception $e) {
                error_log('Error checking username: ' . $e->getMessage());
            }
        }
        
        if (empty($data['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        } else {
            try {
                if (method_exists($this->user, 'emailExists') && $this->user->emailExists($data['email'])) {
                    $errors[] = 'Email already exists.';
                }
            } catch (Exception $e) {
                error_log('Error checking email: ' . $e->getMessage());
            }
        }
        
        if (empty($data['password'])) {
            $errors[] = 'Password is required.';
        } else {
            $min_length = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 6;
            if (strlen($data['password']) < $min_length) {
                $errors[] = 'Password must be at least ' . $min_length . ' characters long.';
            }
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
