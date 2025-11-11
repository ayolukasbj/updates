<?php
/**
 * Plugin Name: MP3 Tagger
 * Plugin URI: https://example.com/mp3-tagger
 * Description: Professional MP3 ID3 tag editor with auto-tagging, sync, and batch editing capabilities. Edit MP3 tags directly in files and sync with database.
 * Version: 1.0.0
 * Author: Music Platform
 * Author URI: https://example.com
 * Text Domain: mp3-tagger
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH') && !function_exists('add_action')) {
    exit;
}

// Load plugin API if not already loaded
if (!function_exists('add_action')) {
    if (file_exists(__DIR__ . '/../../includes/plugin-api.php')) {
        require_once __DIR__ . '/../../includes/plugin-api.php';
    }
}

// Define plugin constants (use direct path if functions not available yet)
if (function_exists('plugin_dir_path')) {
    define('MP3_TAGGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
} else {
    define('MP3_TAGGER_PLUGIN_DIR', dirname(__FILE__) . '/');
}

if (function_exists('plugin_dir_url')) {
    define('MP3_TAGGER_PLUGIN_URL', plugin_dir_url(__FILE__));
} else {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $base_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__DIR__)));
    define('MP3_TAGGER_PLUGIN_URL', $base_url . $base_path . '/plugins/mp3-tagger/');
}

define('MP3_TAGGER_VERSION', '1.0.0');

// Load plugin classes
require_once MP3_TAGGER_PLUGIN_DIR . 'includes/class-mp3-tagger.php';
require_once MP3_TAGGER_PLUGIN_DIR . 'includes/class-auto-tagger.php';

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function() {
    // Create necessary database tables if needed
    // Set default options
    update_option('mp3_tagger_enabled', '1');
    update_option('mp3_tagger_auto_tagging', '1');
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Clean up if needed
});

/**
 * Add admin menu - Register menu item for sidebar
 */
add_action('admin_menu', function() {
    // Register main menu item
    add_menu_page(
        'MP3 Tagger Settings',           // Page title
        'MP3 Tagger',                     // Menu title
        'manage_options',                  // Capability
        'mp3-tagger.php?tab=settings',    // Menu slug (URL)
        '',                                // Function (not used, routing handled separately)
        'fas fa-tag',                     // Icon (Font Awesome class)
        30                                 // Position
    );
    
    // Register submenu items
    add_submenu_page(
        'mp3-tagger.php?tab=settings',    // Parent slug
        'MP3 Tagger Settings',            // Page title
        'Settings',                        // Menu title
        'manage_options',                  // Capability
        'mp3-tagger.php?tab=settings',    // Menu slug
        ''                                 // Function
    );
    
    add_submenu_page(
        'mp3-tagger.php?tab=settings',
        'Sync ID3 Tags',
        'Sync Tags',
        'manage_options',
        'mp3-tagger.php?tab=sync',
        ''
    );
    
    add_submenu_page(
        'mp3-tagger.php?tab=settings',
        'Edit MP3 Tags',
        'Edit Tags',
        'manage_options',
        'mp3-tagger.php?tab=edit',
        ''
    );
    
    // Fire action for other plugins/themes to detect MP3 Tagger
    do_action('mp3_tagger_menu_added');
});

/**
 * Handle admin page routing
 */
add_action('init', function() {
    // Check if we're in admin and this is an MP3 Tagger page
    if (isset($_GET['page']) && strpos($_GET['page'], 'mp3-tagger') === 0) {
        // Route to appropriate page
        $page = $_GET['page'];
        
        if ($page === 'mp3-tagger-settings') {
            add_action('admin_content', 'mp3_tagger_settings_page');
        } elseif ($page === 'mp3-tagger-sync') {
            add_action('admin_content', 'mp3_tagger_sync_page');
        } elseif ($page === 'mp3-tagger-edit') {
            add_action('admin_content', 'mp3_tagger_edit_page');
        }
    }
});

/**
 * Settings page
 */
function mp3_tagger_settings_page() {
    require_once MP3_TAGGER_PLUGIN_DIR . 'admin/settings.php';
}

/**
 * Sync page
 */
function mp3_tagger_sync_page() {
    require_once MP3_TAGGER_PLUGIN_DIR . 'admin/sync.php';
}

/**
 * Edit page
 */
function mp3_tagger_edit_page() {
    require_once MP3_TAGGER_PLUGIN_DIR . 'admin/edit.php';
}

/**
 * Add action to songs list for quick tag access
 */
add_action('admin_songs_list_actions', function($song) {
    echo '<a href="admin.php?page=mp3-tagger-edit&song_id=' . $song['id'] . '" class="btn btn-success btn-sm" title="Edit MP3 Tags">
        <i class="fas fa-tag"></i> Tags
    </a>';
});

