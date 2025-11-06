<?php
// debug-homepage.php - Debug version to test
require_once 'config/config.php';
require_once 'includes/song-storage.php';

echo "Config loaded successfully<br>";
echo "SITE_NAME: " . SITE_NAME . "<br>";

try {
    $featured_songs = getFeaturedSongs();
    echo "Featured songs loaded: " . count($featured_songs) . "<br>";
} catch (Exception $e) {
    echo "Error loading featured songs: " . $e->getMessage() . "<br>";
}

try {
    $recent_songs = getRecentSongs();
    echo "Recent songs loaded: " . count($recent_songs) . "<br>";
} catch (Exception $e) {
    echo "Error loading recent songs: " . $e->getMessage() . "<br>";
}

echo "Debug complete!";
?>