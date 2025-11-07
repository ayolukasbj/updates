<?php
// register.php
// Registration page

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
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
    http_response_code(500);
    die('Error: ' . htmlspecialchars($e->getMessage()));
}

// Redirect if already logged in
if (function_exists('is_logged_in') && is_logged_in()) {
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
    
    $auth = new AuthController();
    $auth->register();
} catch (Exception $e) {
    http_response_code(500);
    die('Error loading authentication: ' . htmlspecialchars($e->getMessage()));
}
?>
