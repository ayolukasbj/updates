<?php
// Test script to check if previous values are being retrieved correctly
require_once 'config/config.php';
require_once 'includes/song-storage.php';
require_once 'config/database.php';

if (!is_logged_in()) {
    die('Please log in first');
}

$user_id = get_user_id();
echo "<h2>Testing Previous Values Retrieval for User ID: $user_id</h2>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if user has any songs
    $stmt = $conn->prepare("SELECT COUNT(*) FROM songs WHERE uploaded_by = ?");
    $stmt->execute([$user_id]);
    $song_count = $stmt->fetch(PDO::FETCH_COLUMN);
    echo "<p><strong>Total songs for this user:</strong> $song_count</p>";
    
    if ($song_count > 0) {
        // Get all songs for this user
        $stmt = $conn->prepare("SELECT id, title, producer, composer, lyricist, record_label, album_title FROM songs WHERE uploaded_by = ? LIMIT 10");
        $stmt->execute([$user_id]);
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Sample Songs:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Title</th><th>Producer</th><th>Composer</th><th>Lyricist</th><th>Record Label</th><th>Album</th></tr>";
        foreach ($songs as $song) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($song['id']) . "</td>";
            echo "<td>" . htmlspecialchars($song['title'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($song['producer'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($song['composer'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($song['lyricist'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($song['record_label'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($song['album_title'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check columns
        $columns_check = $conn->query("SHOW COLUMNS FROM songs");
        $columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
        echo "<h3>Columns in songs table:</h3>";
        echo "<p>" . implode(', ', $columns) . "</p>";
        
        // Test getUserPreviousValues function
        echo "<h3>Testing getUserPreviousValues function:</h3>";
        
        $fields_to_test = ['producer', 'composer', 'lyricist', 'record_label', 'album_title'];
        foreach ($fields_to_test as $field) {
            $values = getUserPreviousValues($user_id, $field);
            echo "<p><strong>$field:</strong> " . count($values) . " values found<br>";
            if (count($values) > 0) {
                echo "Values: " . implode(', ', array_map('htmlspecialchars', $values)) . "</p>";
            } else {
                echo "No values found</p>";
            }
        }
        
        // Direct query test
        echo "<h3>Direct Query Test (Producer):</h3>";
        $stmt = $conn->prepare("SELECT DISTINCT producer FROM songs WHERE uploaded_by = ? AND producer IS NOT NULL AND producer != '' ORDER BY producer ASC");
        $stmt->execute([$user_id]);
        $direct_results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Direct query found " . count($direct_results) . " producers: " . implode(', ', array_map('htmlspecialchars', $direct_results)) . "</p>";
        
    } else {
        echo "<p>No songs found for this user. Upload a song first to test.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}










