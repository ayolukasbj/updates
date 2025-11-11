<?php
/**
 * Test MP3 Tagger Router
 * Debug the router to see what's happening
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>MP3 Tagger Router Test</h1>";

echo "<h2>Step 1: Check files</h2>";
$files = [
    'auth-check.php' => __DIR__ . '/auth-check.php',
    'database.php' => __DIR__ . '/../config/database.php',
    'plugin-loader.php' => __DIR__ . '/../includes/plugin-loader.php',
    'plugin-api.php' => __DIR__ . '/../includes/plugin-api.php',
    'router' => __DIR__ . '/mp3-tagger.php',
    'plugin admin' => __DIR__ . '/../plugins/mp3-tagger/admin/mp3-tagger.php',
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✓ $name exists<br>";
    } else {
        echo "✗ $name NOT FOUND: $path<br>";
    }
}

echo "<h2>Step 2: Load required files</h2>";
try {
    require_once __DIR__ . '/auth-check.php';
    echo "✓ auth-check.php loaded<br>";
    
    require_once __DIR__ . '/../config/database.php';
    echo "✓ database.php loaded<br>";
    
    require_once __DIR__ . '/../includes/plugin-loader.php';
    echo "✓ plugin-loader.php loaded<br>";
    
    require_once __DIR__ . '/../includes/plugin-api.php';
    echo "✓ plugin-api.php loaded<br>";
    
    if (class_exists('PluginLoader')) {
        PluginLoader::init();
        echo "✓ PluginLoader initialized<br>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    die();
} catch (Error $e) {
    echo "✗ Fatal Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    die();
}

echo "<h2>Step 3: Check plugin status</h2>";
if (class_exists('PluginLoader')) {
    $active_plugins = PluginLoader::getActivePlugins();
    echo "Active plugins: " . count($active_plugins) . "<br>";
    
    $mp3_tagger_found = false;
    foreach ($active_plugins as $plugin_path) {
        echo "- " . htmlspecialchars($plugin_path) . "<br>";
        if (strpos($plugin_path, 'mp3-tagger') !== false) {
            $mp3_tagger_found = true;
            $plugin_dir = dirname($plugin_path);
            $admin_file = $plugin_dir . '/admin/mp3-tagger.php';
            echo "  Plugin dir: " . htmlspecialchars($plugin_dir) . "<br>";
            echo "  Admin file: " . htmlspecialchars($admin_file) . "<br>";
            if (file_exists($admin_file)) {
                echo "  ✓ Admin file exists<br>";
            } else {
                echo "  ✗ Admin file NOT FOUND<br>";
            }
        }
    }
    
    if (!$mp3_tagger_found) {
        echo "✗ MP3 Tagger plugin not found in active plugins<br>";
    }
} else {
    echo "✗ PluginLoader class not found<br>";
}

echo "<h2>Test Complete</h2>";

