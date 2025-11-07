<?php
// test-dashboard.php - Simple test to see exact error
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Testing Dashboard</h1>";

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Test 1: Config
echo "<h2>Test 1: Config</h2>";
try {
    require_once 'config/config.php';
    echo "✓ Config loaded<br>";
    echo "SITE_NAME: " . (defined('SITE_NAME') ? SITE_NAME : 'NOT DEFINED') . "<br>";
} catch (Exception $e) {
    echo "✗ Config error: " . $e->getMessage() . "<br>";
    die();
}

// Test 2: Database
echo "<h2>Test 2: Database</h2>";
try {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        echo "✓ Database connected<br>";
    } else {
        echo "✗ Database connection failed<br>";
        die();
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
    die();
}

// Test 3: Functions
echo "<h2>Test 3: Required Functions</h2>";
$functions = ['is_logged_in', 'get_user_id', 'redirect'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✓ Function exists: $func()<br>";
    } else {
        echo "✗ Function missing: $func()<br>";
    }
}

// Test 4: Session
echo "<h2>Test 4: Session</h2>";
if (isset($_SESSION['user_id'])) {
    echo "✓ User ID in session: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "⚠ No user_id in session (this is OK if not logged in)<br>";
}

// Test 5: Include dashboard.php
echo "<h2>Test 5: Include dashboard.php</h2>";
echo "<p>Attempting to include dashboard.php...</p>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";

ob_start();
try {
    include 'dashboard.php';
    $output = ob_get_clean();
    echo "✓ dashboard.php included successfully";
    echo "</pre>";
    echo "<p>Output length: " . strlen($output) . " bytes</p>";
} catch (Exception $e) {
    $output = ob_get_clean();
    echo "✗ Error including dashboard.php: " . $e->getMessage();
    echo "</pre>";
    echo "<p>Output before error: " . htmlspecialchars(substr($output, 0, 500)) . "</p>";
} catch (Error $e) {
    $output = ob_get_clean();
    echo "✗ Fatal error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    echo "</pre>";
}

?>

