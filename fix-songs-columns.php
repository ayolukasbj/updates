<?php
// Fix missing columns in songs table
require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>Songs Table Column Fixer</h2><hr>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check current columns
    echo "<h3>1. Checking Current Columns...</h3>";
    $stmt = $conn->query("SHOW COLUMNS FROM songs");
    $existing_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }
    
    echo "<p>Current columns: " . implode(', ', $existing_columns) . "</p>";
    
    // Define required columns
    $required_columns = [
        'artist' => "VARCHAR(500) DEFAULT NULL COMMENT 'Artist name(s) for display'",
        'is_collaboration' => "TINYINT(1) DEFAULT 0 COMMENT 'Is this a collaboration song?'"
    ];
    
    $columns_added = 0;
    
    echo "<hr><h3>2. Adding Missing Columns...</h3>";
    
    foreach ($required_columns as $column_name => $definition) {
        if (!in_array($column_name, $existing_columns)) {
            echo "<p style='color: orange;'>⚠ Column '<strong>$column_name</strong>' is missing. Adding it...</p>";
            
            try {
                $sql = "ALTER TABLE songs ADD COLUMN $column_name $definition";
                $conn->exec($sql);
                echo "<p style='color: green;'>✓ Successfully added '<strong>$column_name</strong>'</p>";
                $columns_added++;
            } catch (PDOException $e) {
                echo "<p style='color: red;'>✗ Error adding '$column_name': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: green;'>✓ Column '<strong>$column_name</strong>' already exists</p>";
        }
    }
    
    echo "<hr><h3>3. Migrating Data to New Columns...</h3>";
    
    // Migrate artist_id to artist names if needed
    if (in_array('artist_id', $existing_columns)) {
        echo "<p>Migrating artist names from artists table...</p>";
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM songs WHERE artist IS NULL AND artist_id IS NOT NULL");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $need_migration = $result['count'];
        
        if ($need_migration > 0) {
            try {
                $sql = "UPDATE songs s 
                        LEFT JOIN artists a ON s.artist_id = a.id 
                        SET s.artist = a.name 
                        WHERE s.artist IS NULL AND s.artist_id IS NOT NULL";
                $conn->exec($sql);
                echo "<p style='color: green;'>✓ Migrated artist names for $need_migration songs</p>";
            } catch (PDOException $e) {
                echo "<p style='color: orange;'>⚠ Note: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>No migration needed for artist names</p>";
        }
    }
    
    // Auto-detect collaborations
    echo "<p>Auto-detecting collaboration songs...</p>";
    try {
        $sql = "UPDATE songs SET is_collaboration = 1 WHERE 
                artist LIKE '% x %' OR 
                artist LIKE '% & %' OR 
                artist LIKE '%feat%' OR 
                artist LIKE '%ft.%' OR 
                artist LIKE '%featuring%'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $updated = $stmt->rowCount();
        echo "<p style='color: green;'>✓ Marked $updated songs as collaborations</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ Note: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr><h3>4. Final Verification</h3>";
    
    // Show updated column list
    $stmt = $conn->query("SHOW COLUMNS FROM songs");
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $highlight = in_array($row['Field'], ['artist', 'is_collaboration']) ? 'background: #d4edda;' : '';
        echo "<tr style='$highlight'>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show sample data
    echo "<hr><h3>5. Sample Songs Data</h3>";
    $stmt = $conn->query("SELECT id, title, artist, is_collaboration FROM songs LIMIT 5");
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($songs) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Artist</th><th>is_collaboration</th></tr>";
        foreach ($songs as $song) {
            echo "<tr>";
            echo "<td>{$song['id']}</td>";
            echo "<td>" . htmlspecialchars($song['title']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($song['artist'] ?? 'NULL') . "</strong></td>";
            echo "<td>" . ($song['is_collaboration'] ?? '0') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No songs in database yet.</p>";
    }
    
    if ($columns_added > 0) {
        echo "<hr><h3 style='color: green;'>✓ SUCCESS!</h3>";
        echo "<p>Added $columns_added column(s) to the songs table.</p>";
        echo "<p><strong>Next steps:</strong></p>";
        echo "<ul>";
        echo "<li>Go back to <a href='check-collaboration-field.php'>check-collaboration-field.php</a> to verify</li>";
        echo "<li>Visit any song details page to see collaboration support</li>";
        echo "<li>Upload a new collaboration song to test</li>";
        echo "</ul>";
    } else {
        echo "<hr><h3 style='color: green;'>✓ All Required Columns Exist</h3>";
        echo "<p>Your songs table is properly configured!</p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

