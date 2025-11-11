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

// Get plugin directory
$plugin_dir = dirname($plugin_file);
$admin_file = $plugin_dir . '/admin/mp3-tagger.php';

if (!file_exists($admin_file)) {
    die('MP3 Tagger admin file not found. Please check plugin installation.');
}

// Define that we're routing from admin (prevents double includes)
define('MP3_TAGGER_ROUTED_FROM_ADMIN', true);

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the plugin admin file with error handling
try {
    require_once $admin_file;
} catch (ParseError $e) {
    die('Parse Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
} catch (Error $e) {
    die('Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . '<br><pre>' . $e->getTraceAsString() . '</pre>');
} catch (Exception $e) {
    die('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . '<br><pre>' . $e->getTraceAsString() . '</pre>');
}

