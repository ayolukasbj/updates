<?php
// FRESH MIGRATION SCRIPT - v2.0 - <?php echo date('Y-m-d H:i:s'); ?>

require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration v2.0 - FIXED</title>
    <style>
        body { font-family: Arial; max-width: 900px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; color: #155724; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #ffc107; }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 25px; }
        .btn { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin: 5px; }
        .btn-success { background: #28a745; }
        .version { background: #667eea; color: white; padding: 5px 15px; border-radius: 15px; font-size: 12px; float: right; }
    </style>
</head>
<body>

<h1>🎵 Songs Migration Script <span class="version">v2.0 FIXED</span></h1>

<?php
// Load songs from JSON
$jsonFile = '../data/songs.json';
if (!file_exists($jsonFile)) {
    echo '<div class="error">❌ Error: songs.json not found at: ' . realpath('../data/') . '</div>';
    exit;
}

$songsData = json_decode(file_get_contents($jsonFile), true);
if (empty($songsData)) {
    echo '<div class="error">❌ Error: songs.json is empty or invalid</div>';
    exit;
}

echo '<div class="info">📋 Found <strong>' . count($songsData) . '</strong> songs in JSON file</div>';

// STEP 1: Ensure tables exist
echo '<h2>Step 1: Setting Up Database Tables</h2>';

try {
    // Artists table
    $conn->exec("CREATE TABLE IF NOT EXISTS artists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        bio TEXT,
        avatar VARCHAR(255),
        verified BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo '<div class="success">✅ Artists table ready</div>';
    
    // Albums table
    $conn->exec("CREATE TABLE IF NOT EXISTS albums (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        artist_id INT,
        release_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo '<div class="success">✅ Albums table ready</div>';
    
    // Songs table - WITHOUT created_at in column list
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
    echo '<div class="success">✅ Songs table ready</div>';
    
} catch (Exception $e) {
    echo '<div class="warning">⚠️ Tables might already exist: ' . $e->getMessage() . '</div>';
}

// Add missing columns if needed
echo '<h2>Step 2: Checking Columns</h2>';

$columns = [
    'cover_art' => 'VARCHAR(255)',
    'status' => 'VARCHAR(20) DEFAULT "approved"',
    'lyrics' => 'TEXT',
    'is_featured' => 'BOOLEAN DEFAULT FALSE',
    'is_explicit' => 'BOOLEAN DEFAULT FALSE',
    'upload_date' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
];

foreach ($columns as $colName => $colDef) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM songs LIKE '$colName'");
        if ($check->rowCount() == 0) {
            $conn->exec("ALTER TABLE songs ADD COLUMN $colName $colDef");
            echo "<div class='info'>➕ Added column: $colName</div>";
        } else {
            echo "<div class='success'>✅ Column exists: $colName</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error checking $colName: " . $e->getMessage() . "</div>";
    }
}

// STEP 3: Migrate songs
echo '<h2>Step 3: Migrating Songs</h2>';

$migrated = 0;
$skipped = 0;
$errors = [];

foreach ($songsData as $song) {
    $title = $song['title'] ?? 'Unknown';
    $artistName = $song['artist'] ?? 'Unknown Artist';
    
    try {
        $conn->beginTransaction();
        
        // Get or create artist
        $stmt = $conn->prepare("SELECT id FROM artists WHERE name = ?");
        $stmt->execute([$artistName]);
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$artist) {
            $stmt = $conn->prepare("INSERT INTO artists (name) VALUES (?)");
            $stmt->execute([$artistName]);
            $artistId = $conn->lastInsertId();
            echo "<div class='info'>➕ New artist: $artistName</div>";
        } else {
            $artistId = $artist['id'];
        }
        
        // Get or create album
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
        
        // Check if song exists
        $stmt = $conn->prepare("SELECT id FROM songs WHERE title = ? AND artist_id = ?");
        $stmt->execute([$title, $artistId]);
        if ($stmt->fetch()) {
            $skipped++;
            $conn->rollback();
            echo "<div class='warning'>⏭️ Skipped (exists): $title</div>";
            continue;
        }
        
        // Convert duration to seconds
        $duration = 0;
        if (isset($song['duration'])) {
            $parts = explode(':', $song['duration']);
            if (count($parts) == 2) {
                $duration = ($parts[0] * 60) + $parts[1];
            }
        }
        
        // INSERT WITHOUT created_at or upload_date - let MySQL handle them
        $stmt = $conn->prepare("
            INSERT INTO songs (
                title, artist_id, album_id, file_path, cover_art,
                duration, file_size, plays, downloads,
                lyrics, is_featured, is_explicit, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
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
        
        if ($result) {
            $conn->commit();
            $migrated++;
            echo "<div class='success'>✅ Migrated: <strong>$title</strong> by $artistName (Plays: " . ($song['plays'] ?? 0) . ", Downloads: " . ($song['downloads'] ?? 0) . ")</div>";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $title;
        echo "<div class='error'>❌ Failed: <strong>$title</strong><br>Error: " . $e->getMessage() . "</div>";
    }
}

// SUMMARY
echo '<h2>📊 Migration Summary</h2>';
echo '<div class="success"><strong>✅ Successfully migrated: ' . $migrated . ' songs</strong></div>';
if ($skipped > 0) {
    echo '<div class="info">⏭️ Skipped (already existed): ' . $skipped . ' songs</div>';
}
if (count($errors) > 0) {
    echo '<div class="error">❌ Failed: ' . count($errors) . ' songs</div>';
}

// Count total
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo '<div class="success"><h3 style="margin: 20px 0;">🎉 Total songs in database: ' . $total . '</h3></div>';
    
    if ($total > 0) {
        echo '<div class="info"><strong>Success!</strong> All edit/delete buttons in the admin panel are now active!</div>';
    }
} catch (Exception $e) {
    echo '<div class="error">Error counting: ' . $e->getMessage() . '</div>';
}

echo '<p style="text-align: center; margin-top: 30px;">';
echo '<a href="songs.php" class="btn btn-success">📂 View Songs in Admin</a>';
echo '<a href="index.php" class="btn">🏠 Dashboard</a>';
echo '</p>';
?>

</body>
</html>

