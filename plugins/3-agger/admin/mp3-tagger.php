<?php
/**
 * MP3 Tagger Admin Router
 * Routes plugin admin pages
 */

// Define plugin directory if not set
if (!defined('MP3_TAGGER_PLUGIN_DIR')) {
    define('MP3_TAGGER_PLUGIN_DIR', __DIR__ . '/../');
}

require_once __DIR__ . '/../../admin/auth-check.php';
require_once __DIR__ . '/../../config/database.php';

$page_title = 'MP3 Tagger';
$tab = $_GET['tab'] ?? 'settings';

// Make tab available to included files
$GLOBALS['mp3_tagger_tab'] = $tab;

// Include admin header
include __DIR__ . '/../../admin/includes/header.php';

// Route to appropriate page
switch ($tab) {
    case 'settings':
        $tab = 'settings'; // Ensure tab is set
        require_once MP3_TAGGER_PLUGIN_DIR . 'admin/settings.php';
        break;
    case 'sync':
        $tab = 'sync';
        require_once MP3_TAGGER_PLUGIN_DIR . 'admin/sync.php';
        break;
    case 'edit':
        $tab = 'edit';
        require_once MP3_TAGGER_PLUGIN_DIR . 'admin/edit.php';
        break;
    default:
        $tab = 'settings';
        require_once MP3_TAGGER_PLUGIN_DIR . 'admin/settings.php';
}

// Include admin footer
if (file_exists(__DIR__ . '/../../admin/includes/footer.php')) {
    include __DIR__ . '/../../admin/includes/footer.php';
}

