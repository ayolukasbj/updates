<?php
// index-basic.php - Ultra-simple homepage
require_once 'config/config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>" . SITE_NAME . "</title></head><body>";
echo "<h1>Welcome to " . SITE_NAME . "</h1>";
echo "<p>This is a basic working homepage.</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

// Test song storage
try {
    require_once 'includes/song-storage.php';
    $songs = getSongs();
    echo "<h2>Songs (" . count($songs) . ")</h2>";
    echo "<ul>";
    foreach (array_slice($songs, 0, 10) as $song) {
        echo "<li>" . htmlspecialchars($song['title']) . " by " . htmlspecialchars($song['artist']) . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>Error loading songs: " . $e->getMessage() . "</p>";
}

echo "<h2>Debug Info</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Config loaded: " . (defined('SITE_NAME') ? 'Yes' : 'No') . "</p>";
echo "<p>Data file exists: " . (file_exists('data/songs.json') ? 'Yes' : 'No') . "</p>";

echo "</body></html>";
?>
