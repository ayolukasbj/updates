<?php
/**
 * MP3 Tagger Plugin Router
 * Routes to plugin admin pages
 */

require_once 'auth-check.php';
require_once __DIR__ . '/../config/database.php';

// Load plugin system
if (file_exists(__DIR__ . '/../includes/plugin-loader.php')) {
    require_once __DIR__ . '/../includes/plugin-loader.php';
}
if (file_exists(__DIR__ . '/../includes/plugin-api.php')) {
    require_once __DIR__ . '/../includes/plugin-api.php';
}

// Initialize plugin system
if (class_exists('PluginLoader')) {
    PluginLoader::init();
}

// Check if MP3 Tagger plugin is active
$mp3_tagger_active = false;
$plugin_file = null;

if (class_exists('PluginLoader')) {
    $active_plugins = PluginLoader::getActivePlugins();
    foreach ($active_plugins as $plugin_path) {
        if (strpos($plugin_path, 'mp3-tagger') !== false) {
            $mp3_tagger_active = true;
            $plugin_file = $plugin_path;
            break;
        }
    }
}

if (!$mp3_tagger_active) {
    die('MP3 Tagger plugin is not active. Please activate it from the Plugins page.');
}

// Get plugin directory and resolve paths
$plugin_dir = dirname($plugin_file);
$plugin_dir_normalized = str_replace('\\', '/', $plugin_dir);

// Try multiple possible paths for the admin router
$admin_file = null;
$possible_paths = [
    $plugin_dir . '/admin/mp3-tagger.php',
    $plugin_dir_normalized . '/admin/mp3-tagger.php',
    realpath($plugin_dir . '/admin/mp3-tagger.php'),
    __DIR__ . '/../plugins/mp3-tagger/admin/mp3-tagger.php',
    realpath(__DIR__ . '/../plugins/mp3-tagger/admin/mp3-tagger.php')
];

foreach ($possible_paths as $path) {
    if ($path && file_exists($path)) {
        $admin_file = $path;
        break;
    }
}

if (!$admin_file || !file_exists($admin_file)) {
    $error_msg = 'MP3 Tagger admin file not found. Please check plugin installation.<br>';
    $error_msg .= 'Plugin file: ' . htmlspecialchars($plugin_file) . '<br>';
    $error_msg .= 'Plugin dir: ' . htmlspecialchars($plugin_dir) . '<br>';
    $error_msg .= 'Tried paths:<br>';
    foreach ($possible_paths as $path) {
        $error_msg .= '- ' . htmlspecialchars($path ?: 'null') . '<br>';
    }
    die($error_msg);
}

// Define that we're routing from admin (prevents double includes)
define('MP3_TAGGER_ROUTED_FROM_ADMIN', true);

// Get tab parameter
$tab = $_GET['tab'] ?? 'settings';

// Make tab available to included files
$GLOBALS['mp3_tagger_tab'] = $tab;

// Include admin header
include __DIR__ . '/includes/header.php';

// Route to appropriate page based on tab
try {
    // Normalize plugin directory path
    $plugin_dir_normalized = str_replace('\\', '/', $plugin_dir);
    
    // Try multiple path variations for admin files
    $admin_files = [
        'settings' => [
            $plugin_dir . '/admin/settings.php',
            $plugin_dir_normalized . '/admin/settings.php',
            realpath($plugin_dir . '/admin/settings.php'),
            __DIR__ . '/../plugins/mp3-tagger/admin/settings.php'
        ],
        'sync' => [
            $plugin_dir . '/admin/sync.php',
            $plugin_dir_normalized . '/admin/sync.php',
            realpath($plugin_dir . '/admin/sync.php'),
            __DIR__ . '/../plugins/mp3-tagger/admin/sync.php'
        ],
        'edit' => [
            $plugin_dir . '/admin/edit.php',
            $plugin_dir_normalized . '/admin/edit.php',
            realpath($plugin_dir . '/admin/edit.php'),
            __DIR__ . '/../plugins/mp3-tagger/admin/edit.php'
        ]
    ];
    
    $admin_file_to_load = null;
    $tab_to_load = $tab;
    
    // Find the correct file for the requested tab
    if (isset($admin_files[$tab])) {
        foreach ($admin_files[$tab] as $path) {
            if ($path && file_exists($path)) {
                $admin_file_to_load = $path;
                break;
            }
        }
    }
    
    // Fallback to settings if file not found
    if (!$admin_file_to_load) {
        $tab_to_load = 'settings';
        foreach ($admin_files['settings'] as $path) {
            if ($path && file_exists($path)) {
                $admin_file_to_load = $path;
                break;
            }
        }
    }
    
    if (!$admin_file_to_load || !file_exists($admin_file_to_load)) {
        die('MP3 Tagger admin page file not found for tab: ' . htmlspecialchars($tab) . '<br>Plugin dir: ' . htmlspecialchars($plugin_dir));
    }
    
    // Load the admin page
    require_once $admin_file_to_load;
} catch (ParseError $e) {
    die('Parse Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
} catch (Error $e) {
    die('Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . '<br><pre>' . $e->getTraceAsString() . '</pre>');
} catch (Exception $e) {
    die('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . '<br><pre>' . $e->getTraceAsString() . '</pre>');
}

// Include admin footer
include __DIR__ . '/includes/footer.php';

