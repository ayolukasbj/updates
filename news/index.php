<?php
// Simple router for /news/{slug}
// Accept slug via query (?slug=...) from .htaccess or parse from REQUEST_URI

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

require_once __DIR__ . '/../news-details.php';
?>










