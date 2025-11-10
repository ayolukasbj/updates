<?php
// ajax/index-minimal.php - Minimal AJAX homepage
echo "<h1>Welcome to " . (defined('SITE_NAME') ? SITE_NAME : 'Music Platform') . "</h1>";
echo "<p>This is the AJAX-loaded homepage content.</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

// Test if song storage is working
try {
    if (file_exists('../includes/song-storage.php')) {
        require_once '../includes/song-storage.php';
        $songs = getSongs();
        echo "<p>✅ Songs loaded: " . count($songs) . "</p>";
        
        if (count($songs) > 0) {
            echo "<h2>Featured Songs</h2>";
            echo "<ul>";
            foreach (array_slice($songs, 0, 5) as $song) {
                echo "<li>" . htmlspecialchars($song['title']) . " by " . htmlspecialchars($song['artist']) . "</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p>❌ Song storage file not found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error loading songs: " . $e->getMessage() . "</p>";
}

echo "<div style='margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;'>";
echo "<h3>Debug Info</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Current Directory: " . getcwd() . "</p>";
echo "<p>Config exists: " . (file_exists('../config/config.php') ? 'Yes' : 'No') . "</p>";
echo "<p>Song storage exists: " . (file_exists('../includes/song-storage.php') ? 'Yes' : 'No') . "</p>";
echo "<p>Data directory exists: " . (file_exists('../data') ? 'Yes' : 'No') . "</p>";
echo "<p>Songs file exists: " . (file_exists('../data/songs.json') ? 'Yes' : 'No') . "</p>";
echo "</div>";
?>
