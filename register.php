<?php
// register.php
// Registration page

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Start output buffering
if (ob_get_level() == 0) {
    ob_start();
}

// Load config with error handling
try {
    if (!file_exists('config/config.php')) {
        throw new Exception('Configuration file not found. Please run the installation.');
    }
    require_once 'config/config.php';
    
    // Check if site is installed
    if (!defined('SITE_INSTALLED') || !SITE_INSTALLED) {
        throw new Exception('Site is not installed. Please run the installation.');
    }
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('Register.php config error: ' . $e->getMessage());
    http_response_code(500);
    die('Error: ' . htmlspecialchars($e->getMessage()));
} catch (Error $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('Register.php fatal error: ' . $e->getMessage());
    http_response_code(500);
    die('Fatal error. Please check error logs.');
}

// Redirect if already logged in
if (function_exists('is_logged_in') && is_logged_in()) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (function_exists('redirect') && defined('SITE_URL')) {
        redirect(SITE_URL . '/dashboard.php');
    } else {
        header('Location: dashboard.php');
        exit;
    }
}

// Load AuthController with error handling
try {
    if (!file_exists('controllers/AuthController.php')) {
        throw new Exception('Authentication controller not found.');
    }
    require_once 'controllers/AuthController.php';
    
    if (!class_exists('AuthController')) {
        throw new Exception('AuthController class not found.');
    }
    
    $auth = new AuthController();
    $auth->register();
    
    // Flush output if no redirect happened
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('Register.php AuthController error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    die('Error loading authentication: ' . htmlspecialchars($e->getMessage()));
} catch (Error $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log('Register.php fatal error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    die('Fatal error. Please check error logs.');
}
?>
