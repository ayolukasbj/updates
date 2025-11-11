<?php
// Clear database script
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Drop the database if it exists
    $pdo->exec("DROP DATABASE IF EXISTS music_streaming");
    echo "✅ Database 'music_streaming' dropped successfully!<br>";
    
    // Create fresh database
    $pdo->exec("CREATE DATABASE music_streaming");
    echo "✅ Database 'music_streaming' created successfully!<br>";
    
    echo "<hr>";
    echo "<h3>Database cleared! Now you can:</h3>";
    echo "<ol>";
    echo "<li><a href='install.php'>Run the installation again</a></li>";
    echo "<li>Or <a href='index.php'>go to homepage</a> if installation was already completed</li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
