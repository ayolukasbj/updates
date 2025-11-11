<?php
/**
 * Test Plugin System
 * Use this to debug plugin system issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Plugin System Test</h1>";

echo "<h2>Step 1: Check Files</h2>";
$files = [
    'plugin-loader.php' => __DIR__ . '/../includes/plugin-loader.php',
    'plugin-api.php' => __DIR__ . '/../includes/plugin-api.php',
    'database.php' => __DIR__ . '/../config/database.php',
    'auth-check.php' => __DIR__ . '/auth-check.php',
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✓ $name exists<br>";
    } else {
        echo "✗ $name NOT FOUND at: $path<br>";
    }
}

echo "<h2>Step 2: Load Database</h2>";
try {
    require_once __DIR__ . '/../config/database.php';
    echo "✓ Database class loaded<br>";
    
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        echo "✓ Database connection successful<br>";
    } else {
        echo "✗ Database connection failed<br>";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

echo "<h2>Step 3: Load Plugin Loader</h2>";
try {
    $loader_file = __DIR__ . '/../includes/plugin-loader.php';
    if (file_exists($loader_file)) {
        require_once $loader_file;
        echo "✓ PluginLoader file included<br>";
    } else {
        echo "✗ PluginLoader file not found at: $loader_file<br>";
    }
    
    if (class_exists('PluginLoader')) {
        echo "✓ PluginLoader class exists<br>";
        
        echo "Attempting to initialize...<br>";
        ob_start();
        PluginLoader::init();
        $output = ob_get_clean();
        if (!empty($output)) {
            echo "Output during init: " . htmlspecialchars($output) . "<br>";
        }
        echo "✓ PluginLoader initialized<br>";
    } else {
        echo "✗ PluginLoader class not found after require<br>";
    }
} catch (Exception $e) {
    echo "✗ PluginLoader error: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "✗ PluginLoader fatal error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
} catch (Throwable $e) {
    echo "✗ PluginLoader throwable: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Step 4: Load Plugin API</h2>";
try {
    require_once __DIR__ . '/../includes/plugin-api.php';
    echo "✓ Plugin API loaded<br>";
    
    if (function_exists('get_option')) {
        echo "✓ get_option function exists<br>";
    } else {
        echo "✗ get_option function not found<br>";
    }
    
    if (function_exists('update_option')) {
        echo "✓ update_option function exists<br>";
    } else {
        echo "✗ update_option function not found<br>";
    }
} catch (Exception $e) {
    echo "✗ Plugin API error: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Step 5: Test Plugin Functions</h2>";
try {
    if (class_exists('PluginLoader')) {
        $plugins = PluginLoader::getPlugins();
        echo "✓ getPlugins() returned " . count($plugins) . " plugins<br>";
        
        $active = PluginLoader::getActivePlugins();
        echo "✓ getActivePlugins() returned " . count($active) . " active plugins<br>";
    }
} catch (Exception $e) {
    echo "✗ Plugin functions error: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Test Complete</h2>";
echo "<p>If all steps show ✓, the plugin system should be working.</p>";

