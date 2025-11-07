<?php
// Simple router for /news/{slug}
// Accept slug via query (?slug=...) from .htaccess or parse from REQUEST_URI

// Enable error reporting for debugging (but don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Normalize base path if the site is served under /music/
$slug = $_GET['slug'] ?? '';
if ($slug === '' && isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    // Remove query string
    if (strpos($uri, '?') !== false) {
        $uri = strtok($uri, '?');
    }
    // Find segment after /news/
    $parts = explode('/news/', $uri, 2);
    if (count($parts) === 2) {
        $slug = trim($parts[1], '/');
    }
}

if ($slug !== '') {
    $_GET['slug'] = $slug;
}

// Fallback: if slug missing, try id from last segment if numeric
if ($slug === '' && isset($_SERVER['REQUEST_URI'])) {
    $last = basename($_SERVER['REQUEST_URI']);
    if (ctype_digit($last)) {
        $_GET['id'] = $last;
    }
}

// Set flag to indicate we're being called from news/ folder
define('CALLED_FROM_NEWS_FOLDER', true);

// Include news-details.php with error handling
try {
    $news_details_path = __DIR__ . '/../news-details.php';
    if (!file_exists($news_details_path)) {
        throw new Exception('news-details.php not found at: ' . $news_details_path);
    }
    require_once $news_details_path;
} catch (Exception $e) {
    error_log('Error in news/index.php: ' . $e->getMessage());
    http_response_code(500);
    die('Error loading news page. Please check error logs.');
}
?>










