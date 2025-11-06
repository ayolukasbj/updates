<?php
// config/config.php
// Auto-generated during installation - DO NOT EDIT MANUALLY

// Installation Status
define('SITE_INSTALLED', true);

// Site Configuration
define('SITE_NAME', 'ubuntu stage');
define('SITE_SLOGAN', 'am testing');
define('SITE_DESCRIPTION', '');

// Auto-detect SITE_URL and BASE_PATH
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Auto-detect base path from script location
$script_path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$base_path = $script_path === '/' ? '/' : rtrim($script_path, '/') . '/';

// If installed in root, base_path should be '/'
if (strpos($script_path, '/admin') !== false) {
    // We're in admin folder, go up one level
    $base_path = dirname($script_path) === '/' ? '/' : dirname($script_path) . '/';
} elseif ($script_path === '/' || empty($script_path)) {
    $base_path = '/';
}

define('SITE_URL', $protocol . $host . $base_path);
define('BASE_PATH', $base_path);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'music_streaming');
define('DB_USER', 'root');
define('DB_PASS', '');

// File Upload Configuration
define('UPLOAD_PATH', 'uploads/');
define('MUSIC_PATH', 'uploads/music/');
define('IMAGES_PATH', 'uploads/images/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024);
define('ALLOWED_AUDIO_FORMATS', ['mp3', 'wav', 'flac', 'aac', 'm4a']);
define('ALLOWED_IMAGE_FORMATS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Environment
define('ENVIRONMENT', 'production');

// License Configuration
define('LICENSE_SERVER_URL', 'http://localhost/license-server');
define('LICENSE_KEY', 'R6N2-A536-WYR3-5FTJ-RNNM');

// Security
define('ENCRYPTION_KEY', bin2hex(random_bytes(32)));
define('SESSION_LIFETIME', 3600);
define('PASSWORD_MIN_LENGTH', 8);

// Subscription Configuration
define('FREE_DAILY_DOWNLOADS', 10);
define('PREMIUM_DAILY_DOWNLOADS', 100);
define('ARTIST_DAILY_DOWNLOADS', 500);

// Streaming Configuration
define('DEFAULT_STREAMING_QUALITY', 'high');
define('STREAMING_BUFFER_SIZE', 8192);

// Pagination
define('SONGS_PER_PAGE', 20);
define('PLAYLISTS_PER_PAGE', 12);
define('USERS_PER_PAGE', 15);

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600);

// Email Configuration (will be set from admin settings)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@example.com');
define('FROM_NAME', 'ubuntu stage');

// Payment Configuration
define('PAYPAL_CLIENT_ID', '');
define('PAYPAL_CLIENT_SECRET', '');
define('STRIPE_PUBLISHABLE_KEY', '');
define('STRIPE_SECRET_KEY', '');

// Social Media Configuration
define('FACEBOOK_APP_ID', '');
define('GOOGLE_CLIENT_ID', '');

// Helper Functions
// Start session if not already started (only if not in CLI mode)
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start(); // Suppress warnings if headers already sent
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    } else {
        echo '<script>window.location.href = "' . htmlspecialchars($url) . '";</script>';
        exit;
    }
}

/**
 * Get base URL path (removes hardcoded /music/)
 */
function base_url($path = '') {
    $base = defined('BASE_PATH') ? BASE_PATH : '/';
    // Remove leading slash from path if base already has trailing slash
    if ($base !== '/' && $path && $path[0] === '/') {
        $path = substr($path, 1);
    }
    return $base . $path;
}
