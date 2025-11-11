<?php
// Backfill artist names for existing songs
require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>Backfill Artist Names for Existing Songs</h2><hr>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Update all songs that have uploaded_by but no artist name
    echo "<h3>Updating Songs...</h3>";
    
    $sql = "UPDATE songs s
            INNER JOIN users u ON s.uploaded_by = u.id
            SET s.artist = u.username
            WHERE (s.artist IS NULL OR s.artist = '' OR s.artist = 'Unknown Artist') 
            AND s.uploaded_by IS NOT NULL
            AND u.username IS NOT NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $updated = $stmt->rowCount();
    
    echo "<p style='color: green; font-weight: bold;'>✓ Updated $updated songs with artist names from uploader usernames</p>";
    
    // Show sample of updated songs
    echo "<hr><h3>Sample Updated Songs:</h3>";
    $stmt = $conn->query("SELECT s.id, s.title, s.artist, s.uploaded_by, u.username 
                          FROM songs s 
                          LEFT JOIN users u ON s.uploaded_by = u.id 
                          LIMIT 10");
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($songs) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Artist Field</th><th>Uploaded By (ID)</th><th>Uploader Username</th></tr>";
        foreach ($songs as $song) {
            $match = (strcasecmp($song['artist'], $song['username']) === 0) ? '✓' : '✗';
            echo "<tr>";
            echo "<td>{$song['id']}</td>";
            echo "<td>" . htmlspecialchars($song['title']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($song['artist'] ?? 'NULL') . "</strong></td>";
            echo "<td>{$song['uploaded_by']}</td>";
            echo "<td>{$song['username']}</td>";
            echo "<td>{$match}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<hr><h3 style='color: green;'>✓ Done! Refresh your song details pages.</h3>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>

