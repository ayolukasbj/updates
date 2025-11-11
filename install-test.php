<?php
// Simple installation test
echo "<h1>Music Streaming Platform - Installation Test</h1>";
echo "<p>PHP is working!</p>";

// Test database connection
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Test if database exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS music_streaming");
    echo "<p style='color: green;'>✅ Database 'music_streaming' created/exists!</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>If you see green checkmarks above, your environment is ready!</li>";
echo "<li>Go to <a href='install.php'>install.php</a> to run the full installation</li>";
echo "<li>Or go to <a href='index.php'>index.php</a> to see the homepage</li>";
echo "</ol>";
?>
