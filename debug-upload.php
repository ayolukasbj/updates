<?php
// Debug upload.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Upload Debug</h1>";

try {
    require_once 'config/config.php';
    echo "<p>✅ Config loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Config error: " . $e->getMessage() . "</p>";
    exit;
}

try {
    require_once 'config/database.php';
    echo "<p>✅ Database config loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Database config error: " . $e->getMessage() . "</p>";
    exit;
}

try {
    require_once 'classes/User.php';
    require_once 'classes/Song.php';
    require_once 'classes/Artist.php';
    echo "<p>✅ Classes loaded</p>";
} catch (Exception $e) {
    echo "<p>❌ Classes error: " . $e->getMessage() . "</p>";
    exit;
}

// Check if user is logged in
if (!is_logged_in()) {
    echo "<p>❌ User not logged in</p>";
    echo "<p><a href='login.php'>Login</a></p>";
    exit;
}

echo "<p>✅ User logged in</p>";

// Test database connection
try {
    $database = new Database();
    $db = $database->getConnection();
    echo "<p>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection error: " . $e->getMessage() . "</p>";
    exit;
}

echo "<hr>";
echo "<p><a href='upload.php'>Try Upload Page</a></p>";
?>
