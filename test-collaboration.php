<?php
// Quick collaboration test
require_once 'config/config.php';
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    die("Usage: test-collaboration.php?id=SONG_ID");
}

$songId = $_GET['id'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get song data
    $stmt = $conn->prepare("SELECT id, title, artist, is_collaboration, uploaded_by FROM songs WHERE id = ?");
    $stmt->execute([$songId]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$song) {
        die("Song not found!");
    }
    
    echo "<h2>Collaboration Test for Song ID: $songId</h2><hr>";
    
    echo "<h3>Song Data:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>{$song['id']}</td></tr>";
    echo "<tr><td>Title</td><td>{$song['title']}</td></tr>";
    echo "<tr><td>Artist (raw)</td><td><strong style='color: blue;'>{$song['artist']}</strong></td></tr>";
    echo "<tr><td>is_collaboration</td><td>" . ($song['is_collaboration'] ?? 'NULL') . "</td></tr>";
    echo "<tr><td>uploaded_by</td><td>{$song['uploaded_by']}</td></tr>";
    echo "</table>";
    
    echo "<hr><h3>Artist Field Parsing:</h3>";
    
    // Test detection
    $is_collaboration = false;
    if (!empty($song['artist'])) {
        if (!empty($song['is_collaboration']) && $song['is_collaboration'] == 1) {
            $is_collaboration = true;
            echo "<p style='color: green;'>✓ Detected via is_collaboration flag</p>";
        }
        else if (preg_match('/(\\sx\\s|\\s&\\s|\\sfeat\\.?\\s|\\sft\\.?\\s|\\sfeaturing\\s)/i', $song['artist'])) {
            $is_collaboration = true;
            echo "<p style='color: green;'>✓ Auto-detected from artist field content</p>";
        } else {
            echo "<p style='color: orange;'>⚠ No collaboration detected</p>";
        }
    }
    
    if ($is_collaboration) {
        // Parse artists
        $collaborators_raw = preg_split('/(\\sx\\s|\\s&\\s|\\sfeat\\.?\\s|\\sft\\.?\\s|\\sfeaturing\\s)/i', $song['artist']);
        $collaborators_raw = array_map('trim', $collaborators_raw);
        $collaborators_raw = array_filter($collaborators_raw);
        
        echo "<p><strong>Parsed Artist Names:</strong></p>";
        echo "<ol>";
        foreach ($collaborators_raw as $idx => $name) {
            echo "<li><strong>" . htmlspecialchars($name) . "</strong></li>";
        }
        echo "</ol>";
        
        echo "<hr><h3>Database Lookup Results:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>#</th><th>Parsed Name</th><th>Found in DB?</th><th>User ID</th><th>Username</th><th>Avatar</th></tr>";
        
        $found_artists = [];
        foreach ($collaborators_raw as $idx => $collab_name) {
            $num = $idx + 1;
            
            // Search in database
            $stmt = $conn->prepare("SELECT id, username, avatar FROM users WHERE LOWER(username) = LOWER(?)");
            $stmt->execute([$collab_name]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                echo "<tr style='background: #d4edda;'>";
                echo "<td>{$num}</td>";
                echo "<td>" . htmlspecialchars($collab_name) . "</td>";
                echo "<td style='color: green;'>✓ FOUND</td>";
                echo "<td>{$user['id']}</td>";
                echo "<td><strong>{$user['username']}</strong></td>";
                echo "<td>" . ($user['avatar'] ? "Has avatar" : "No avatar") . "</td>";
                echo "</tr>";
                $found_artists[] = $user;
            } else {
                echo "<tr style='background: #f8d7da;'>";
                echo "<td>{$num}</td>";
                echo "<td>" . htmlspecialchars($collab_name) . "</td>";
                echo "<td style='color: red;'>✗ NOT FOUND</td>";
                echo "<td colspan='3'>No matching user in database</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
        
        echo "<hr><h3>All Users in Database:</h3>";
        echo "<p><em>For reference, here are all usernames in the users table:</em></p>";
        $stmt = $conn->query("SELECT id, username FROM users ORDER BY username");
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<ul>";
        foreach ($all_users as $u) {
            echo "<li>ID {$u['id']}: <strong>{$u['username']}</strong></li>";
        }
        echo "</ul>";
        
        if (count($found_artists) > 0) {
            echo "<hr><h3 style='color: green;'>✓ Success!</h3>";
            echo "<p>Found " . count($found_artists) . " out of " . count($collaborators_raw) . " artists in the database.</p>";
        } else {
            echo "<hr><h3 style='color: red;'>⚠ Problem!</h3>";
            echo "<p>None of the parsed artist names match any users in the database.</p>";
            echo "<p><strong>Possible issues:</strong></p>";
            echo "<ul>";
            echo "<li>Artist names in the song don't match usernames exactly</li>";
            echo "<li>Artists haven't registered as users yet</li>";
            echo "<li>There's a typo in the artist field</li>";
            echo "</ul>";
        }
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>

