<?php
require_once '../config/database.php';
$db = new Database();
$conn = $db->getConnection();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrate Songs - <?php echo date('H:i:s'); ?></title>
    <style>
        body{font-family:Arial;max-width:900px;margin:20px auto;padding:15px;background:#f5f5f5;}
        .success{background:#d4edda;color:#155724;padding:12px;margin:8px 0;border-radius:4px;}
        .error{background:#f8d7da;color:#721c24;padding:12px;margin:8px 0;border-radius:4px;}
        .info{background:#d1ecf1;color:#0c5460;padding:12px;margin:8px 0;border-radius:4px;}
        h1{color:#333;font-size:24px;}
        .btn{background:#667eea;color:white;padding:12px 24px;border-radius:5px;text-decoration:none;display:inline-block;margin:10px 5px;}
    </style>
</head>
<body>
<h1>🎵 Song Migration</h1>

<?php
$jsonFile = '../data/songs.json';
if (!file_exists($jsonFile)) {
    echo '<div class="error">❌ songs.json not found</div>';
    exit;
}

$songs = json_decode(file_get_contents($jsonFile), true);
echo '<div class="info">📋 Found ' . count($songs) . ' songs</div>';

// Create tables
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS artists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        bio TEXT,
        avatar VARCHAR(255),
        verified BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $conn->exec("CREATE TABLE IF NOT EXISTS albums (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        artist_id INT,
        release_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $conn->exec("CREATE TABLE IF NOT EXISTS songs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        artist_id INT,
        album_id INT,
        file_path VARCHAR(255),
        cover_art VARCHAR(255),
        duration INT DEFAULT 0,
        file_size BIGINT DEFAULT 0,
        plays BIGINT DEFAULT 0,
        downloads BIGINT DEFAULT 0,
        lyrics TEXT,
        is_featured BOOLEAN DEFAULT FALSE,
        is_explicit BOOLEAN DEFAULT FALSE,
        status VARCHAR(20) DEFAULT 'approved',
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo '<div class="success">✅ Tables ready</div>';
} catch (Exception $e) {
    echo '<div class="info">Tables exist</div>';
}

// Add missing columns
$cols = ['cover_art' => 'VARCHAR(255)', 'status' => 'VARCHAR(20) DEFAULT "approved"', 'lyrics' => 'TEXT', 'is_featured' => 'BOOLEAN DEFAULT FALSE', 'is_explicit' => 'BOOLEAN DEFAULT FALSE', 'upload_date' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'];
foreach ($cols as $col => $def) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM songs LIKE '$col'");
        if ($check->rowCount() == 0) {
            $conn->exec("ALTER TABLE songs ADD COLUMN $col $def");
            echo "<div class='info'>➕ Added: $col</div>";
        }
    } catch (Exception $e) {}
}

// Migrate songs
$migrated = 0;
$skipped = 0;
$errors = [];

foreach ($songs as $song) {
    $title = $song['title'] ?? 'Unknown';
    $artistName = $song['artist'] ?? 'Unknown';
    
    try {
        $conn->beginTransaction();
        
        // Artist
        $stmt = $conn->prepare("SELECT id FROM artists WHERE name = ?");
        $stmt->execute([$artistName]);
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$artist) {
            $stmt = $conn->prepare("INSERT INTO artists (name) VALUES (?)");
            $stmt->execute([$artistName]);
            $artistId = $conn->lastInsertId();
            echo "<div class='info'>➕ Artist: $artistName</div>";
        } else {
            $artistId = $artist['id'];
        }
        
        // Album
        $albumId = null;
        if (!empty($song['album'])) {
            $stmt = $conn->prepare("SELECT id FROM albums WHERE title = ? AND artist_id = ?");
            $stmt->execute([$song['album'], $artistId]);
            $album = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$album) {
                $stmt = $conn->prepare("INSERT INTO albums (title, artist_id) VALUES (?, ?)");
                $stmt->execute([$song['album'], $artistId]);
                $albumId = $conn->lastInsertId();
            } else {
                $albumId = $album['id'];
            }
        }
        
        // Check if exists
        $stmt = $conn->prepare("SELECT id FROM songs WHERE title = ? AND artist_id = ?");
        $stmt->execute([$title, $artistId]);
        if ($stmt->fetch()) {
            $skipped++;
            $conn->rollback();
            echo "<div class='info'>⏭️ Exists: $title</div>";
            continue;
        }
        
        // Duration
        $duration = 0;
        if (isset($song['duration'])) {
            $parts = explode(':', $song['duration']);
            if (count($parts) == 2) {
                $duration = ($parts[0] * 60) + $parts[1];
            }
        }
        
        // INSERT - NO created_at or upload_date
        $stmt = $conn->prepare("INSERT INTO songs (title, artist_id, album_id, file_path, cover_art, duration, file_size, plays, downloads, lyrics, is_featured, is_explicit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $title,
            $artistId,
            $albumId,
            $song['audio_file'] ?? '',
            $song['cover_art'] ?? '',
            $duration,
            $song['file_size'] ?? 0,
            $song['plays'] ?? 0,
            $song['downloads'] ?? 0,
            $song['lyrics'] ?? '',
            isset($song['featured']) && $song['featured'] ? 1 : 0,
            isset($song['explicit']) && $song['explicit'] ? 1 : 0,
            'approved'
        ]);
        
        $conn->commit();
        $migrated++;
        echo "<div class='success'>✅ $title by $artistName (Plays: " . ($song['plays'] ?? 0) . ")</div>";
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $title;
        echo "<div class='error'>❌ $title: " . $e->getMessage() . "</div>";
    }
}

echo '<h2>📊 Summary</h2>';
echo '<div class="success">✅ Migrated: ' . $migrated . '</div>';
if ($skipped > 0) echo '<div class="info">⏭️ Skipped: ' . $skipped . '</div>';
if (count($errors) > 0) echo '<div class="error">❌ Failed: ' . count($errors) . '</div>';

$stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
$total = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo '<div class="success"><h3>🎉 Total: ' . $total . ' songs</h3></div>';

if ($total > 0) {
    echo '<div class="info"><strong>Success!</strong> Edit/delete buttons now active!</div>';
}

echo '<p><a href="songs.php" class="btn">View Songs</a> <a href="index.php" class="btn">Dashboard</a></p>';
?>
</body>
</html>

