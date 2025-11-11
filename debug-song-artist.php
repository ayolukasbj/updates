<?php
// Debug script to check song artist data
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Song.php';

if (!isset($_GET['id'])) {
    die("Please provide song ID: debug-song-artist.php?id=1");
}

$songId = $_GET['id'];

echo "<h2>Song Artist Debug for ID: $songId</h2>";
echo "<hr>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    $song_model = new Song($conn);
    
    // Get raw song data
    $song = $song_model->getSongById($songId);
    
    echo "<h3>Raw Song Data from Database:</h3>";
    echo "<pre>";
    print_r($song);
    echo "</pre>";
    echo "<hr>";
    
    if ($song) {
        echo "<h3>Key Fields:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Field</th><th>Value</th><th>Empty?</th></tr>";
        
        $fields = ['id', 'title', 'artist', 'artist_name', 'uploaded_by', 'is_collaboration'];
        foreach ($fields as $field) {
            $value = $song[$field] ?? 'NOT SET';
            $isEmpty = empty($song[$field]) ? 'YES' : 'NO';
            echo "<tr><td><strong>$field</strong></td><td>$value</td><td>$isEmpty</td></tr>";
        }
        echo "</table>";
        echo "<hr>";
        
        // Check user data
        if (!empty($song['uploaded_by'])) {
            echo "<h3>User Data (uploaded_by = {$song['uploaded_by']}):</h3>";
            $stmt = $conn->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
            $stmt->execute([$song['uploaded_by']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<pre>";
            print_r($user);
            echo "</pre>";
            echo "<hr>";
        }
        
        // Check collaboration
        if (!empty($song['is_collaboration'])) {
            echo "<h3>Collaboration Analysis:</h3>";
            echo "<p><strong>is_collaboration:</strong> {$song['is_collaboration']}</p>";
            echo "<p><strong>artist field:</strong> {$song['artist']}</p>";
            
            // Try to parse collaborators
            $collaborators_raw = preg_split('/(\\sx\\s|\\s&\\s|feat\\.?\\s|ft\\.\\s)/i', $song['artist']);
            echo "<p><strong>Parsed artists:</strong></p>";
            echo "<pre>";
            print_r($collaborators_raw);
            echo "</pre>";
            
            // Search for each collaborator
            echo "<h4>Database Lookup for Each Artist:</h4>";
            foreach ($collaborators_raw as $collab_name) {
                $collab_name = trim($collab_name);
                if (empty($collab_name)) continue;
                
                echo "<p>Searching for: <strong>$collab_name</strong></p>";
                $stmt = $conn->prepare("SELECT id, username FROM users WHERE LOWER(username) = LOWER(?)");
                $stmt->execute([$collab_name]);
                $found = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($found) {
                    echo "<p style='color: green;'>✓ FOUND: ID={$found['id']}, Username={$found['username']}</p>";
                } else {
                    echo "<p style='color: red;'>✗ NOT FOUND in users table</p>";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

