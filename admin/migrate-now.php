<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migrate Songs - FIXED VERSION</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; }
        h1 { color: #333; }
        .btn { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn-success { background: #28a745; }
    </style>
</head>
<body>

<h1>🎵 Songs Migration - Fixed Version</h1>

<?php
// Check JSON file
$jsonFile = '../data/songs.json';
if (!file_exists($jsonFile)) {
    echo '<div class="error">❌ No songs.json file found!</div>';
    exit;
}

$songs = json_decode(file_get_contents($jsonFile), true);
if (empty($songs)) {
    echo '<div class="error">❌ songs.json is empty!</div>';
    exit;
}

echo '<div class="info">📋 Found ' . count($songs) . ' songs to migrate</div>';

// Step 1: Ensure tables exist with correct structure
echo '<h2>Step 1: Checking Tables</h2>';

// Create artists table
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS artists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        bio TEXT,
        avatar VARCHAR(255),
        verified BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo '<div class="success">✅ Artists table ready</div>';
} catch (Exception $e) {
    echo '<div class="error">❌ Artists table error: ' . $e->getMessage() . '</div>';
}

// Create albums table
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS albums (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        artist_id INT,
        release_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo '<div class="success">✅ Albums table ready</div>';
} catch (Exception $e) {
    echo '<div class="error">❌ Albums table error: ' . $e->getMessage() . '</div>';
}

// Create songs table with ALL needed columns
try {
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
    echo '<div class="success">✅ Songs table created/exists</div>';
} catch (Exception $e) {
    // Table might exist, try adding missing columns
    echo '<div class="info">Songs table exists, checking columns...</div>';
}

// Add missing columns if needed
$columns_to_add = [
    'cover_art' => "VARCHAR(255) DEFAULT NULL",
    'status' => "VARCHAR(20) DEFAULT 'approved'",
    'lyrics' => "TEXT",
    'is_featured' => "BOOLEAN DEFAULT FALSE",
    'is_explicit' => "BOOLEAN DEFAULT FALSE",
    'upload_date' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
];

foreach ($columns_to_add as $col_name => $col_def) {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM songs LIKE '$col_name'");
        if ($stmt->rowCount() == 0) {
            $conn->exec("ALTER TABLE songs ADD COLUMN $col_name $col_def");
            echo "<div class='info'>➕ Added column: $col_name</div>";
        }
    } catch (Exception $e) {
        // Column might exist or error
    }
}

// Step 2: Migrate songs
echo '<h2>Step 2: Migrating Songs</h2>';

$migrated = 0;
$skipped = 0;
$errors = [];

foreach ($songs as $song) {
    $title = $song['title'] ?? 'Unknown';
    $artist_name = $song['artist'] ?? 'Unknown Artist';
    
    try {
        $conn->beginTransaction();
        
        // Get or create artist
        $stmt = $conn->prepare("SELECT id FROM artists WHERE name = ?");
        $stmt->execute([$artist_name]);
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$artist) {
            $stmt = $conn->prepare("INSERT INTO artists (name) VALUES (?)");
            $stmt->execute([$artist_name]);
            $artist_id = $conn->lastInsertId();
            echo "<div class='info'>➕ Created artist: $artist_name</div>";
        } else {
            $artist_id = $artist['id'];
        }
        
        // Get or create album
        $album_id = null;
        if (!empty($song['album'])) {
            $stmt = $conn->prepare("SELECT id FROM albums WHERE title = ? AND artist_id = ?");
            $stmt->execute([$song['album'], $artist_id]);
            $album = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$album) {
                $stmt = $conn->prepare("INSERT INTO albums (title, artist_id) VALUES (?, ?)");
                $stmt->execute([$song['album'], $artist_id]);
                $album_id = $conn->lastInsertId();
                echo "<div class='info'>➕ Created album: {$song['album']}</div>";
            } else {
                $album_id = $album['id'];
            }
        }
        
        // Check if song already exists
        $stmt = $conn->prepare("SELECT id FROM songs WHERE title = ? AND artist_id = ?");
        $stmt->execute([$title, $artist_id]);
        if ($stmt->fetch()) {
            $skipped++;
            $conn->rollback();
            echo "<div class='info'>⏭️ Skipped: $title (already exists)</div>";
            continue;
        }
        
        // Convert duration
        $duration = 0;
        if (isset($song['duration'])) {
            $parts = explode(':', $song['duration']);
            if (count($parts) == 2) {
                $duration = ($parts[0] * 60) + $parts[1];
            }
        }
        
        // Insert song - SIMPLE VERSION without problematic columns
        $stmt = $conn->prepare("
            INSERT INTO songs (
                title, 
                artist_id, 
                album_id, 
                file_path, 
                cover_art,
                duration, 
                file_size, 
                plays, 
                downloads,
                lyrics, 
                is_featured, 
                is_explicit, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $title,
            $artist_id,
            $album_id,
            $song['audio_file'] ?? '',
            $song['cover_art'] ?? '',
            $duration,
            $song['file_size'] ?? 0,
            $song['plays'] ?? 0,
            $song['downloads'] ?? 0,
            $song['lyrics'] ?? '',
            ($song['featured'] ?? 0) ? 1 : 0,
            ($song['explicit'] ?? 0) ? 1 : 0,
            'approved'
        ]);
        
        if ($result) {
            $conn->commit();
            $migrated++;
            echo "<div class='success'>✅ Migrated: $title by $artist_name (Plays: " . ($song['plays'] ?? 0) . ", Downloads: " . ($song['downloads'] ?? 0) . ")</div>";
        } else {
            throw new Exception("Insert failed");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $title;
        echo "<div class='error'>❌ Error with '$title': " . $e->getMessage() . "</div>";
    }
}

// Summary
echo '<h2>📊 Migration Summary</h2>';
echo '<div class="success">✅ Successfully migrated: ' . $migrated . ' songs</div>';
if ($skipped > 0) {
    echo '<div class="info">⏭️ Skipped: ' . $skipped . ' songs (already existed)</div>';
}
if (count($errors) > 0) {
    echo '<div class="error">❌ Errors: ' . count($errors) . ' songs</div>';
}

// Final count
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo '<div class="success"><h3>✨ Total songs in database: ' . $total . '</h3></div>';
    
    if ($total > 0) {
        echo '<div class="info"><strong>🎉 Migration successful!</strong><br>All edit/delete buttons in admin panel are now active!</div>';
    }
} catch (Exception $e) {
    echo '<div class="error">Error counting: ' . $e->getMessage() . '</div>';
}

echo '<p>';
echo '<a href="songs.php" class="btn btn-success">View Songs in Admin Panel</a>';
echo '<a href="index.php" class="btn">Go to Dashboard</a>';
echo '</p>';
?>

</body>
</html>

