<?php
// Simple test - no dependencies
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test 1: Basic PHP works<br>";

echo "Test 2: Checking plugin-loader.php syntax...<br>";
$loader_file = __DIR__ . '/../includes/plugin-loader.php';
if (file_exists($loader_file)) {
    // Check syntax
    $output = [];
    $return_var = 0;
    exec("php -l \"$loader_file\" 2>&1", $output, $return_var);
    if ($return_var === 0) {
        echo "✓ plugin-loader.php syntax is valid<br>";
    } else {
        echo "✗ plugin-loader.php has syntax errors:<br>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
    }
} else {
    echo "✗ plugin-loader.php not found<br>";
}

echo "Test 3: Trying to include plugin-loader.php...<br>";
try {
    ob_start();
    include $loader_file;
    $output = ob_get_clean();
    if (!empty($output)) {
        echo "Output: " . htmlspecialchars($output) . "<br>";
    }
    echo "✓ File included successfully<br>";
} catch (Throwable $e) {
    echo "✗ Error including file: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
}

echo "Test 4: Checking if PluginLoader class exists...<br>";
if (class_exists('PluginLoader')) {
    echo "✓ PluginLoader class exists<br>";
} else {
    echo "✗ PluginLoader class not found<br>";
}

echo "Test 5: Checking plugins directory...<br>";
$plugins_dir = __DIR__ . '/../plugins';
if (is_dir($plugins_dir)) {
    echo "✓ Plugins directory exists<br>";
    $plugins = glob($plugins_dir . '/*', GLOB_ONLYDIR);
    echo "Found " . count($plugins) . " plugin folders:<br>";
    foreach ($plugins as $plugin) {
        echo "- " . basename($plugin) . "<br>";
    }
} else {
    echo "✗ Plugins directory does not exist<br>";
}

echo "<br>Test complete!";

