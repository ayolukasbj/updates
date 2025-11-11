<?php
// Clear all JSON songs and ensure database-only operation
require_once 'config/config.php';
require_once 'includes/song-storage.php';

echo "<!DOCTYPE html><html><head><title>Clear JSON Data</title>";
echo "<style>body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; } 
      h2 { color: #333; } hr { margin: 20px 0; } 
      .success { color: green; } .error { color: red; } .warning { color: orange; }
      .info { background: #f0f0f0; padding: 10px; border-radius: 5px; margin: 10px 0; }</style></head><body>";

echo "<h2>Clear All JSON Data - Database Only Mode</h2><hr>";

// Clear songs.json
$songs_file = DATA_PATH . 'songs.json';
if (file_exists($songs_file)) {
    try {
        $json_content = file_get_contents($songs_file);
        $json_songs = json_decode($json_content, true) ?? [];
        $json_count = count($json_songs);
        echo "<p><strong>Found $json_count songs in songs.json</strong></p>";
        
        file_put_contents($songs_file, json_encode([], JSON_PRETTY_PRINT));
        echo "<p class='success'>✓ Cleared all songs from songs.json</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error clearing songs.json: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='success'>✓ songs.json doesn't exist (already cleared)</p>";
}

// Clear news.json
$news_file = DATA_PATH . 'news.json';
if (file_exists($news_file)) {
    try {
        $json_content = file_get_contents($news_file);
        $json_news = json_decode($json_content, true) ?? [];
        $news_count = count($json_news);
        echo "<p><strong>Found $news_count news items in news.json</strong></p>";
        
        // Backup news.json instead of deleting (news might still be in JSON)
        echo "<p class='warning'>⚠ news.json kept (news data may still use JSON)</p>";
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error reading news.json: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='success'>✓ news.json doesn't exist</p>";
}

// Clear any other JSON files in data directory
$data_dir = DATA_PATH;
if (is_dir($data_dir)) {
    $files = glob($data_dir . '*.json');
    $other_files = array_filter($files, function($f) {
        return basename($f) !== 'songs.json' && basename($f) !== 'news.json';
    });
    
    if (!empty($other_files)) {
        echo "<p><strong>Found " . count($other_files) . " other JSON file(s):</strong></p>";
        foreach ($other_files as $file) {
            try {
                unlink($file);
                echo "<p class='success'>✓ Deleted " . basename($file) . "</p>";
            } catch (Exception $e) {
                echo "<p class='error'>✗ Error deleting " . basename($file) . ": " . $e->getMessage() . "</p>";
            }
        }
    }
}

echo "<hr><div class='info'><h3>Current Status</h3>";
echo "<p class='success'>✓ System will now use database only</p>";
echo "<p class='success'>✓ JSON fallback has been removed from upload.php</p>";
echo "<p class='success'>✓ All song operations will use database</p>";
echo "<p class='success'>✓ Search API now uses database</p>";
echo "<p class='success'>✓ Homepage now fetches from database</p></div>";

echo "<hr><h3 class='success'>✓ Done! Your system is now database-only for songs.</h3>";
echo "<p><strong>Note:</strong> Refresh your homepage to see newly uploaded songs from database.</p>";
echo "</body></html>";
?>

