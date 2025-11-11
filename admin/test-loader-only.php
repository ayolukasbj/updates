<?php
// Test loading plugin-loader.php in isolation
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Plugin Loader Isolation Test</h1>";

echo "<h2>Step 1: Load database</h2>";
require_once __DIR__ . '/../config/database.php';
echo "✓ Database loaded<br>";

echo "<h2>Step 2: Load plugin-loader.php</h2>";
$loader_file = __DIR__ . '/../includes/plugin-loader.php';

// Use output buffering to catch any output
ob_start();
try {
    require_once $loader_file;
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "Output captured: <pre>" . htmlspecialchars($output) . "</pre>";
    }
    
    if (class_exists('PluginLoader')) {
        echo "✓ PluginLoader class exists<br>";
    } else {
        echo "✗ PluginLoader class NOT found<br>";
        die("Stopping here - class not found");
    }
} catch (ParseError $e) {
    ob_end_clean();
    echo "✗ Parse Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    die();
} catch (Error $e) {
    ob_end_clean();
    echo "✗ Fatal Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    die();
} catch (Exception $e) {
    ob_end_clean();
    echo "✗ Exception: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    die();
}

echo "<h2>Step 3: Try to initialize</h2>";
try {
    PluginLoader::init();
    echo "✓ PluginLoader::init() completed<br>";
} catch (Throwable $e) {
    echo "✗ Error during init: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Step 4: Get plugins</h2>";
try {
    $plugins = PluginLoader::getPlugins();
    echo "✓ Found " . count($plugins) . " plugins<br>";
    
    foreach ($plugins as $name => $plugin) {
        echo "- " . htmlspecialchars($name) . " (" . htmlspecialchars($plugin['file']) . ")<br>";
    }
} catch (Throwable $e) {
    echo "✗ Error getting plugins: " . $e->getMessage() . "<br>";
}

echo "<h2>Test Complete</h2>";

