<?php
require_once 'config/config.php';
require_once 'config/database.php';

if (!is_logged_in()) {
    die('Please log in first');
}

$user_id = get_user_id();
$db = new Database();
$conn = $db->getConnection();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Check Songs Database</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .success { background: #d4edda; padding: 15px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border: 1px solid #ffc107; }
        .error { background: #f8d7da; padding: 15px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .info { background: #cce5ff; padding: 15px; margin: 10px 0; border: 1px solid #b8daff; }
    </style>
</head>
<body>
    <h1>Songs Database Diagnostic</h1>
    
    <div class="info">
        <strong>Your User ID:</strong> <?php echo $user_id; ?><br>
        <strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?>
    </div>
    
    <h2>All Songs in Database:</h2>
    <?php
    try {
        $stmt = $conn->query("SELECT id, title, uploaded_by, artist_id, upload_date FROM songs ORDER BY id DESC LIMIT 20");
        $all_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($all_songs) > 0) {
            echo '<div class="success">Found ' . count($all_songs) . ' songs in database</div>';
            echo '<table>';
            echo '<tr><th>ID</th><th>Title</th><th>Uploaded By</th><th>Artist ID</th><th>Upload Date</th></tr>';
            foreach ($all_songs as $song) {
                $highlight = ($song['uploaded_by'] == $user_id) ? 'background: #ffffcc;' : '';
                echo '<tr style="' . $highlight . '">';
                echo '<td>' . $song['id'] . '</td>';
                echo '<td>' . htmlspecialchars($song['title']) . '</td>';
                echo '<td>' . $song['uploaded_by'] . ($song['uploaded_by'] == $user_id ? ' <strong>(YOU)</strong>' : '') . '</td>';
                echo '<td>' . ($song['artist_id'] ?? 'NULL') . '</td>';
                echo '<td>' . $song['upload_date'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="warning">No songs found in database! This means uploads are still going to JSON.</div>';
            echo '<p><strong>Action:</strong> Run <a href="fix-songs-table.php">fix-songs-table.php</a></p>';
        }
    } catch (Exception $e) {
        echo '<div class="error">Error querying songs: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>
    
    <h2>Your Songs (uploaded_by = <?php echo $user_id; ?>):</h2>
    <?php
    try {
        $stmt = $conn->prepare("SELECT * FROM songs WHERE uploaded_by = ? ORDER BY id DESC");
        $stmt->execute([$user_id]);
        $your_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($your_songs) > 0) {
            echo '<div class="success">Found ' . count($your_songs) . ' of your songs</div>';
            echo '<table>';
            echo '<tr><th>ID</th><th>Title</th><th>Cover Art</th><th>File Path</th><th>Plays</th><th>Downloads</th></tr>';
            foreach ($your_songs as $song) {
                echo '<tr>';
                echo '<td>' . $song['id'] . '</td>';
                echo '<td>' . htmlspecialchars($song['title']) . '</td>';
                echo '<td>' . ($song['cover_art'] ? '✓' : '✗') . '</td>';
                echo '<td>' . htmlspecialchars($song['file_path']) . '</td>';
                echo '<td>' . ($song['plays'] ?? 0) . '</td>';
                echo '<td>' . ($song['downloads'] ?? 0) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="warning">No songs found for your user ID (' . $user_id . ')</div>';
            echo '<p><strong>Possible Reasons:</strong></p>';
            echo '<ul>';
            echo '<li>Songs were saved to JSON but not database</li>';
            echo '<li>Songs uploaded with different user_id</li>';
            echo '<li>Database insert failed during upload</li>';
            echo '</ul>';
            echo '<p><strong>Solution:</strong> Try uploading a new song after running <a href="fix-songs-table.php">fix-songs-table.php</a></p>';
        }
    } catch (Exception $e) {
        echo '<div class="error">Error querying your songs: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>
    
    <h2>Check Error Logs:</h2>
    <div class="info">
        <p>Check these files for detailed information:</p>
        <ul>
            <li><code>C:\xampp\apache\logs\error.log</code></li>
            <li>Look for: "Fetched X songs for user_id: <?php echo $user_id; ?>"</li>
            <li>Look for: "Song uploaded successfully! Song ID:"</li>
            <li>Look for: "CRITICAL: Song upload failed"</li>
        </ul>
    </div>
    
    <h2>Quick Actions:</h2>
    <p>
        <a href="fix-songs-table.php" style="padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">Fix Songs Table</a>
        <a href="upload.php" style="padding: 10px 15px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;">Upload Song</a>
        <a href="artist-profile-mobile.php?tab=music&debug=1" style="padding: 10px 15px; background: #FF9800; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;">View Music Tab (Debug)</a>
    </p>
</body>
</html>

