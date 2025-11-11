<?php
// Check collaboration field status
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<h2>Collaboration Field Check</h2><hr>";
    
    // Check if is_collaboration column exists
    echo "<h3>1. Column Check</h3>";
    $stmt = $conn->query("SHOW COLUMNS FROM songs LIKE 'is_collaboration'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column) {
        echo "<p style='color: green;'>✓ Column 'is_collaboration' EXISTS</p>";
        echo "<pre>";
        print_r($column);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>✗ Column 'is_collaboration' DOES NOT EXIST</p>";
        echo "<p><strong>Need to add it? Run this SQL:</strong></p>";
        echo "<pre>ALTER TABLE songs ADD COLUMN is_collaboration TINYINT(1) DEFAULT 0 AFTER artist;</pre>";
    }
    
    echo "<hr><h3>2. Sample Songs Data</h3>";
    $stmt = $conn->query("SELECT id, title, artist, is_collaboration FROM songs LIMIT 10");
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Artist</th><th>is_collaboration</th><th>Has 'x' or '&'?</th></tr>";
    
    foreach ($songs as $song) {
        $has_separator = (strpos($song['artist'], ' x ') !== false || 
                          strpos($song['artist'], ' & ') !== false || 
                          stripos($song['artist'], 'feat') !== false ||
                          stripos($song['artist'], 'ft.') !== false) ? 'YES' : 'NO';
        
        $is_collab = $song['is_collaboration'] ?? 'NULL';
        $row_color = ($is_collab == 1) ? 'background: #d4edda;' : '';
        
        echo "<tr style='$row_color'>";
        echo "<td>{$song['id']}</td>";
        echo "<td>" . htmlspecialchars($song['title']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($song['artist']) . "</strong></td>";
        echo "<td>{$is_collab}</td>";
        echo "<td>{$has_separator}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr><h3>3. Songs with Collaboration Flag = 1</h3>";
    
    // Check if song_collaborators table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'song_collaborators'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "<p style='color: green;'>✓ song_collaborators table EXISTS</p>";
    } else {
        echo "<p style='color: red;'>✗ song_collaborators table DOES NOT EXIST</p>";
        echo "<p>Creating table...</p>";
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS song_collaborators (
                id INT AUTO_INCREMENT PRIMARY KEY,
                song_id INT NOT NULL,
                user_id INT NOT NULL,
                added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_song (song_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            echo "<p style='color: green;'>✓ Table created successfully!</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error creating table: " . $e->getMessage() . "</p>";
        }
    }
    
    $stmt = $conn->query("SELECT id, title, artist, is_collaboration FROM songs WHERE is_collaboration = 1");
    $collab_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($collab_songs) > 0) {
        echo "<p>Found " . count($collab_songs) . " collaboration songs:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-top: 10px;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Artist (from songs table)</th><th>All Collaborators (from song_collaborators)</th></tr>";
        
                foreach ($collab_songs as $song) {
            // Fetch uploader first
            $uploader_name = '';
            $all_artist_names = [];
            
            if (!empty($song['id'])) {
                try {
                    // Get uploader
                    $uploaderStmt = $conn->prepare("
                        SELECT u.id, u.username 
                        FROM songs s
                        LEFT JOIN users u ON s.uploaded_by = u.id
                        WHERE s.id = ?
                    ");
                    $uploaderStmt->execute([$song['id']]);
                    $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($uploader && !empty($uploader['username'])) {
                        $all_artist_names[] = htmlspecialchars($uploader['username']);
                        $uploader_name = htmlspecialchars($uploader['username']);
                    }
                } catch (Exception $e) {
                    error_log("Error fetching uploader: " . $e->getMessage());
                }
                
                // Fetch all collaborators from song_collaborators table
                try {
                    $collabStmt = $conn->prepare("
                        SELECT sc.user_id, COALESCE(u.username, sc.user_id) as username, u.avatar
                        FROM song_collaborators sc
                        LEFT JOIN users u ON sc.user_id = u.id
                        WHERE sc.song_id = ?
                        ORDER BY sc.added_at ASC
                    ");
                    $collabStmt->execute([$song['id']]);
                    $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($collaborators)) {
                        foreach ($collaborators as $collab) {
                            $collab_username = htmlspecialchars($collab['username'] ?? 'User ID: ' . $collab['user_id']);
                            // Avoid duplicating uploader if they're also in collaborators
                            if (!in_array($collab_username, $all_artist_names)) {
                                $all_artist_names[] = $collab_username;
                            }
                        }
                    }
                    
                    if (count($all_artist_names) > 0) {
                        $collab_display = implode(' x ', $all_artist_names);
                    } else {
                        $collab_display = "<span style='color: orange;'>No collaborators found (uploader: {$uploader_name})</span>";
                    }
                } catch (Exception $e) {
                    $collab_display = "<span style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</span>";
                    if (!empty($uploader_name)) {
                        $collab_display .= " (Uploader: {$uploader_name})";
                    }
                }
            } else {
                $collab_display = "<span style='color: red;'>No song ID</span>";
            }
            
            echo "<tr>";
            echo "<td>{$song['id']}</td>";
            echo "<td><strong>" . htmlspecialchars($song['title']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($song['artist']) . "</td>";
            echo "<td>{$collab_display}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠ No songs have is_collaboration = 1</p>";
        echo "<p>This means collaboration detection won't work!</p>";
    }
    
    // Also check songs in song_collaborators table
    echo "<hr><h3>3b. Songs in song_collaborators Table</h3>";
    try {
        $stmt = $conn->query("
            SELECT sc.song_id, s.title, s.artist, COUNT(sc.user_id) as collaborator_count,
                   GROUP_CONCAT(COALESCE(u.username, sc.user_id) ORDER BY sc.added_at ASC SEPARATOR ' x ') as all_collaborators
            FROM song_collaborators sc
            LEFT JOIN songs s ON sc.song_id = s.id
            LEFT JOIN users u ON sc.user_id = u.id
            GROUP BY sc.song_id, s.title, s.artist
            ORDER BY sc.song_id DESC
        ");
        $collab_mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($collab_mappings) > 0) {
            echo "<p>Found " . count($collab_mappings) . " songs with collaborators in song_collaborators table:</p>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-top: 10px;'>";
            echo "<tr><th>Song ID</th><th>Title</th><th>Artist (from songs)</th><th>Collaborator Count</th><th>All Collaborators</th><th>is_collaboration Flag</th></tr>";
            
            foreach ($collab_mappings as $mapping) {
                // Check if is_collaboration flag is set
                $flagStmt = $conn->prepare("SELECT is_collaboration FROM songs WHERE id = ?");
                $flagStmt->execute([$mapping['song_id']]);
                $flag = $flagStmt->fetch(PDO::FETCH_ASSOC);
                $is_collab_flag = $flag['is_collaboration'] ?? 0;
                $flag_status = $is_collab_flag ? "<span style='color: green;'>✓ Set (1)</span>" : "<span style='color: red;'>✗ Not Set (0)</span>";
                
                echo "<tr>";
                echo "<td>{$mapping['song_id']}</td>";
                echo "<td><strong>" . htmlspecialchars($mapping['title'] ?? 'N/A') . "</strong></td>";
                echo "<td>" . htmlspecialchars($mapping['artist'] ?? 'N/A') . "</td>";
                echo "<td>{$mapping['collaborator_count']}</td>";
                echo "<td>" . htmlspecialchars($mapping['all_collaborators'] ?? 'N/A') . "</td>";
                echo "<td>{$flag_status}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠ No entries found in song_collaborators table</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking song_collaborators table: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr><h3>4. Songs that SHOULD be collaborations (based on text)</h3>";
    $stmt = $conn->query("SELECT id, title, artist, is_collaboration FROM songs WHERE 
                          artist LIKE '% x %' OR 
                          artist LIKE '% & %' OR 
                          artist LIKE '%feat%' OR 
                          artist LIKE '%ft.%'");
    $should_be_collab = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($should_be_collab) > 0) {
        echo "<p>Found " . count($should_be_collab) . " songs that look like collaborations:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Artist</th><th>is_collaboration</th><th>Action Needed?</th></tr>";
        
        foreach ($should_be_collab as $song) {
            $needs_update = ($song['is_collaboration'] != 1) ? 'YES - UPDATE NEEDED' : 'OK';
            $row_color = ($song['is_collaboration'] != 1) ? 'background: #f8d7da;' : 'background: #d4edda;';
            
            echo "<tr style='$row_color'>";
            echo "<td>{$song['id']}</td>";
            echo "<td>" . htmlspecialchars($song['title']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($song['artist']) . "</strong></td>";
            echo "<td>" . ($song['is_collaboration'] ?? 'NULL') . "</td>";
            echo "<td>{$needs_update}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Generate update SQL
        echo "<hr><h3>5. Auto-Fix SQL</h3>";
        echo "<p>Run this SQL to fix collaboration flags:</p>";
        echo "<pre style='background: #f0f0f0; padding: 10px;'>";
        echo "UPDATE songs SET is_collaboration = 1 WHERE \n";
        echo "  artist LIKE '% x %' OR \n";
        echo "  artist LIKE '% & %' OR \n";
        echo "  artist LIKE '%feat%' OR \n";
        echo "  artist LIKE '%ft.%';\n";
        echo "</pre>";
        
        echo "<form method='post' style='margin-top: 20px;'>";
        echo "<button type='submit' name='auto_fix' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>";
        echo "🔧 Auto-Fix Collaboration Flags Now";
        echo "</button>";
        echo "</form>";
        
        if (isset($_POST['auto_fix'])) {
            $update_stmt = $conn->prepare("UPDATE songs SET is_collaboration = 1 WHERE 
                                           artist LIKE '% x %' OR 
                                           artist LIKE '% & %' OR 
                                           artist LIKE '%feat%' OR 
                                           artist LIKE '%ft.%'");
            if ($update_stmt->execute()) {
                $affected = $update_stmt->rowCount();
                echo "<p style='color: green; font-weight: bold; margin-top: 20px;'>✓ Updated {$affected} songs!</p>";
                echo "<p><a href='check-collaboration-field.php'>Refresh this page</a></p>";
            }
        }
    } else {
        echo "<p>No songs found with collaboration indicators in artist field.</p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>

