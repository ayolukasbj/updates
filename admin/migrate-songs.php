<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migrate Songs to Database</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .song-item { background: #f8f9fa; padding: 10px; margin: 5px 0; border-left: 3px solid #007bff; }
        h1 { color: #333; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>🎵 Migrate Songs from JSON to Database</h1>

<?php
// Check if JSON file exists
$jsonFile = '../data/songs.json';
if (!file_exists($jsonFile)) {
    echo '<div class="error">❌ No songs.json file found!</div>';
    echo '<a href="index.php" class="btn">Back to Dashboard</a>';
    exit;
}

// Read songs from JSON
$songs = json_decode(file_get_contents($jsonFile), true);
if (empty($songs)) {
    echo '<div class="error">❌ No songs found in JSON file!</div>';
    echo '<a href="index.php" class="btn">Back to Dashboard</a>';
    exit;
}

echo '<div class="info">📋 Found ' . count($songs) . ' songs to migrate</div>';

$migrated = 0;
$skipped = 0;
$errors = [];

foreach ($songs as $song) {
    $song_title = $song['title'] ?? 'Unknown';
    $artist_name = $song['artist'] ?? 'Unknown Artist';
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // 1. Get or create artist
        $stmt = $conn->prepare("SELECT id FROM artists WHERE name = ?");
        $stmt->execute([$artist_name]);
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$artist) {
            // Create artist
            $stmt = $conn->prepare("INSERT INTO artists (name, bio, created_at) VALUES (?, '', NOW())");
            $stmt->execute([$artist_name]);
            $artist_id = $conn->lastInsertId();
            echo '<div class="info">➕ Created new artist: ' . htmlspecialchars($artist_name) . '</div>';
        } else {
            $artist_id = $artist['id'];
        }
        
        // 2. Get or create album (if specified)
        $album_id = null;
        if (!empty($song['album'])) {
            $album_title = $song['album'];
            $stmt = $conn->prepare("SELECT id FROM albums WHERE title = ? AND artist_id = ?");
            $stmt->execute([$album_title, $artist_id]);
            $album = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$album) {
                // Create album
                $stmt = $conn->prepare("INSERT INTO albums (title, artist_id, release_date, created_at) VALUES (?, ?, NULL, NOW())");
                $stmt->execute([$album_title, $artist_id]);
                $album_id = $conn->lastInsertId();
                echo '<div class="info">➕ Created new album: ' . htmlspecialchars($album_title) . '</div>';
            } else {
                $album_id = $album['id'];
            }
        }
        
        // 3. Check if song already exists
        $stmt = $conn->prepare("SELECT id FROM songs WHERE title = ? AND artist_id = ?");
        $stmt->execute([$song_title, $artist_id]);
        if ($stmt->fetch()) {
            echo '<div class="info">⏭️ Skipped (already exists): ' . htmlspecialchars($song_title) . ' by ' . htmlspecialchars($artist_name) . '</div>';
            $skipped++;
            $conn->rollback();
            continue;
        }
        
        // 4. Convert duration to seconds
        $duration = 0;
        if (isset($song['duration'])) {
            $parts = explode(':', $song['duration']);
            if (count($parts) == 2) {
                $duration = ($parts[0] * 60) + $parts[1];
            }
        }
        
        // 5. Check if status and cover_art columns exist
        $stmt = $conn->query("SHOW COLUMNS FROM songs LIKE 'status'");
        $has_status = $stmt->rowCount() > 0;
        
        $stmt = $conn->query("SHOW COLUMNS FROM songs LIKE 'cover_art'");
        $has_cover_art = $stmt->rowCount() > 0;
        
        // 6. Insert song (with or without optional columns)
        if ($has_status && $has_cover_art) {
            $stmt = $conn->prepare("
                INSERT INTO songs (
                    title, artist_id, album_id, file_path, cover_art,
                    duration, file_size, plays, downloads,
                    lyrics, is_featured, is_explicit, status,
                    upload_date, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                $song_title,
                $artist_id,
                $album_id,
                $song['audio_file'] ?? '',
                $song['cover_art'] ?? '',
                $duration,
                $song['file_size'] ?? 0,
                $song['plays'] ?? 0,
                $song['downloads'] ?? 0,
                $song['lyrics'] ?? '',
                isset($song['featured']) ? $song['featured'] : 0,
                isset($song['explicit']) ? $song['explicit'] : 0,
                'approved'
            ]);
        } else {
            // Fallback for basic schema
            $stmt = $conn->prepare("
                INSERT INTO songs (
                    title, artist_id, album_id, file_path,
                    duration, file_size, plays, downloads,
                    lyrics, is_featured, is_explicit,
                    upload_date, created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                $song_title,
                $artist_id,
                $album_id,
                $song['audio_file'] ?? '',
                $duration,
                $song['file_size'] ?? 0,
                $song['plays'] ?? 0,
                $song['downloads'] ?? 0,
                $song['lyrics'] ?? '',
                isset($song['featured']) ? $song['featured'] : 0,
                isset($song['explicit']) ? $song['explicit'] : 0
            ]);
        }
        
        $conn->commit();
        
        echo '<div class="success">✅ Migrated: ' . htmlspecialchars($song_title) . ' by ' . htmlspecialchars($artist_name) . 
             ' (Plays: ' . ($song['plays'] ?? 0) . ', Downloads: ' . ($song['downloads'] ?? 0) . ')</div>';
        $migrated++;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = 'Error migrating "' . htmlspecialchars($song_title) . '": ' . $e->getMessage();
        echo '<div class="error">❌ ' . $error_msg . '</div>';
        $errors[] = $error_msg;
    }
}

echo '<hr>';
echo '<h2>📊 Migration Summary</h2>';
echo '<div class="success">✅ Successfully migrated: ' . $migrated . ' songs</div>';
if ($skipped > 0) {
    echo '<div class="info">⏭️ Skipped (already exist): ' . $skipped . ' songs</div>';
}
if (count($errors) > 0) {
    echo '<div class="error">❌ Errors: ' . count($errors) . ' songs</div>';
}

if ($migrated > 0) {
    echo '<div class="info" style="margin-top: 20px;">';
    echo '<strong>✨ Migration complete! Your songs are now in the database.</strong><br><br>';
    echo 'You can now:<br>';
    echo '• View them in <a href="songs.php">Song Management</a><br>';
    echo '• See updated statistics on <a href="index.php">Dashboard</a><br>';
    echo '• The JSON file will remain as backup';
    echo '</div>';
}
?>

<div style="margin-top: 30px;">
    <a href="index.php" class="btn">← Back to Dashboard</a>
    <a href="songs.php" class="btn" style="background: #28a745;">View Songs</a>
</div>

</body>
</html>

