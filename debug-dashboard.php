<?php
// Simple dashboard test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Dashboard Debug</h2>";

// Test 1: Check if config files exist
echo "<h3>1. Checking Files:</h3>";
$files_to_check = [
    'config/config.php',
    'config/database.php',
    'classes/User.php',
    'classes/Song.php',
    'classes/Playlist.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file exists</p>";
    } else {
        echo "<p style='color: red;'>❌ $file missing</p>";
    }
}

// Test 2: Check if session is working
echo "<h3>2. Session Check:</h3>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ User logged in (ID: " . $_SESSION['user_id'] . ")</p>";
} else {
    echo "<p style='color: red;'>❌ No user session found</p>";
}

// Test 3: Test database connection
echo "<h3>3. Database Connection:</h3>";
try {
    require_once 'config/config.php';
    require_once 'config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Test if users table exists
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Users table exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Users table missing</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test 4: Test class loading
echo "<h3>4. Class Loading:</h3>";
try {
    require_once 'classes/User.php';
    echo "<p style='color: green;'>✅ User class loaded</p>";
    
    require_once 'classes/Song.php';
    echo "<p style='color: green;'>✅ Song class loaded</p>";
    
    require_once 'classes/Playlist.php';
    echo "<p style='color: green;'>✅ Playlist class loaded</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Class loading error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>Try Dashboard Again</a> | <a href='index.php'>Go to Homepage</a></p>";
?>
