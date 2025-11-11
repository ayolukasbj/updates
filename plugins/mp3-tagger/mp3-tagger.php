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

// Define plugin constants
define('MP3_TAGGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MP3_TAGGER_PLUGIN_URL', plugin_dir_url(__FILE__));
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
 * Add admin menu - Register menu items
 */
add_action('admin_menu', function() {
    // Prevent redeclaration
    if (defined('MP3_TAGGER_MENU_REGISTERED')) {
        return;
    }
    define('MP3_TAGGER_MENU_REGISTERED', true);
    
    // Log menu registration attempt
    error_log("MP3 Tagger: Registering admin menu");
    
    // Register main menu page
    add_menu_page(
        'MP3 Tagger Settings',           // Page title
        'MP3 Tagger',                     // Menu title
        'manage_options',                 // Capability
        'mp3-tagger.php?tab=settings',    // Menu slug
        '',                               // Function (handled by router)
        'fas fa-tag',                     // Icon
        30                                // Position
    );
    
    error_log("MP3 Tagger: Main menu registered");
    
    // Register submenu pages
    add_submenu_page(
        'mp3-tagger.php?tab=settings',    // Parent slug
        'MP3 Tagger Settings',             // Page title
        'Settings',                        // Menu title
        'manage_options',                  // Capability
        'mp3-tagger.php?tab=settings',     // Menu slug
        ''                                // Function (handled by router)
    );
    
    add_submenu_page(
        'mp3-tagger.php?tab=settings',    // Parent slug
        'Sync ID3 Tags',                   // Page title
        'Sync Tags',                      // Menu title
        'manage_options',                  // Capability
        'mp3-tagger.php?tab=sync',        // Menu slug
        ''                                // Function (handled by router)
    );
    
    add_submenu_page(
        'mp3-tagger.php?tab=settings',    // Parent slug
        'Edit MP3 Tags',                  // Page title
        'Edit Tags',                      // Menu title
        'manage_options',                 // Capability
        'mp3-tagger.php?tab=edit',         // Menu slug
        ''                                // Function (handled by router)
    );
    
    error_log("MP3 Tagger: All menus registered");
    
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

/**
 * Automatic sync hook - tag newly uploaded songs automatically
 * This runs after a song is uploaded via the upload form
 */
add_action('song_uploaded', function($song_id, $song_data) {
    // Check if auto-tagging is enabled
    if (get_option('id3_auto_tagging_enabled', '1') !== '1') {
        return; // Auto-tagging disabled
    }
    
    try {
        // Get song data from database
        if (!function_exists('get_db_connection')) {
            return;
        }
        
        $conn = get_db_connection();
        if (!$conn) {
            return;
        }
        
        $stmt = $conn->prepare("
            SELECT s.*, u.username as uploader_name, a.name as artist_name
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            LEFT JOIN artists a ON s.artist_id = a.id
            WHERE s.id = ?
        ");
        $stmt->execute([$song_id]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$song || empty($song['file_path'])) {
            return;
        }
        
        // Resolve file path
        $file_path = $song['file_path'];
        $full_file_path = strpos($file_path, '/') === 0 || strpos($file_path, ':\\') !== false 
            ? $file_path 
            : __DIR__ . '/../../' . ltrim($file_path, '/');
        
        $full_file_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_file_path);
        
        // Check if file exists and is MP3
        if (!file_exists($full_file_path)) {
            error_log("MP3 Auto-sync: File not found: $full_file_path");
            return;
        }
        
        $file_ext = strtolower(pathinfo($full_file_path, PATHINFO_EXTENSION));
        if ($file_ext !== 'mp3') {
            return; // Not MP3, skip
        }
        
        // Load AutoTagger if not already loaded
        if (!class_exists('AutoTagger')) {
            $auto_tagger_file = __DIR__ . '/includes/class-auto-tagger.php';
            if (file_exists($auto_tagger_file)) {
                require_once $auto_tagger_file;
            }
        }
        
        if (!class_exists('AutoTagger')) {
            error_log("MP3 Auto-sync: AutoTagger class not found");
            return;
        }
        
        // Prepare song data for tagging
        $tag_song_data = [
            'title' => $song['title'] ?? '',
            'artist' => $song['artist_name'] ?? $song['artist'] ?? '',
            'year' => $song['release_year'] ?? $song['year'] ?? '',
            'genre' => $song['genre'] ?? '',
        ];
        
        $uploader_name = $song['uploader_name'] ?? '';
        
        // Auto-tag the file
        error_log("MP3 Auto-sync: Tagging song ID $song_id: " . $song['title']);
        $tag_result = AutoTagger::tagUploadedSong($full_file_path, $tag_song_data, $uploader_name);
        
        if ($tag_result['success']) {
            error_log("MP3 Auto-sync: Successfully tagged song ID $song_id");
        } else {
            error_log("MP3 Auto-sync: Failed to tag song ID $song_id");
        }
    } catch (Exception $e) {
        error_log("MP3 Auto-sync error: " . $e->getMessage());
    }
}, 10, 2);

