<?php
// Minimal test to see if we can get any output
echo "Test 1: Script started<br>";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Test 2: Error reporting enabled<br>";

// Test auth-check
echo "Test 3: Loading auth-check.php<br>";
if (file_exists('auth-check.php')) {
    require_once 'auth-check.php';
    echo "Test 4: auth-check.php loaded<br>";
} else {
    die("auth-check.php not found");
}

// Test database
echo "Test 5: Checking database<br>";
if (class_exists('Database')) {
    echo "Test 6: Database class exists<br>";
    try {
        $db = new Database();
        $conn = $db->getConnection();
        if ($conn) {
            echo "Test 7: Database connection successful<br>";
        } else {
            die("Test 7: Database connection failed");
        }
    } catch (Exception $e) {
        die("Test 7: Database error: " . $e->getMessage());
    }
} else {
    die("Database class not found");
}

// Test includes
echo "Test 8: Loading mp3-tagger.php<br>";
if (file_exists('../includes/mp3-tagger.php')) {
    require_once '../includes/mp3-tagger.php';
    echo "Test 9: mp3-tagger.php loaded<br>";
} else {
    die("mp3-tagger.php not found");
}

echo "Test 10: All files loaded successfully!<br>";
echo "If you see this, the basic includes work. The issue is likely in the main mp3-tagger.php logic.";

