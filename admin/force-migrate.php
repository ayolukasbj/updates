<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Force migration without any checks
echo "<!DOCTYPE html><html><head><title>Force Migration</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;padding:15px;margin:10px 0;border-radius:4px;color:#155724;}";
echo ".error{background:#f8d7da;padding:15px;margin:10px 0;border-radius:4px;color:#721c24;}";
echo ".info{background:#d1ecf1;padding:15px;margin:10px 0;border-radius:4px;color:#0c5460;}";
echo "h1{color:#333;}pre{background:#f5f5f5;padding:10px;border-radius:4px;}</style></head><body>";

echo "<h1>🚀 Force Migration Tool</h1>";

// Check JSON file
$jsonFile = '../data/songs.json';
if (!file_exists($jsonFile)) {
    echo '<div class="error">❌ No songs.json file found at: ' . $jsonFile . '</div>';
    echo '<p>Upload some songs first!</p>';
    echo '</body></html>';
    exit;
}

$songs = json_decode(file_get_contents($jsonFile), true);
if (empty($songs)) {
    echo '<div class="error">❌ songs.json is empty!</div>';
    echo '</body></html>';
    exit;
}

echo '<div class="info">📋 Found ' . count($songs) . ' songs in JSON file</div>';

// Check if tables exist, create if not
$tables_sql = [
    'artists' => "CREATE TABLE IF NOT EXISTS artists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        bio TEXT,
        avatar VARCHAR(255),
        verified BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'albums' => "CREATE TABLE IF NOT EXISTS albums (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        artist_id INT,
        release_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'songs' => "CREATE TABLE IF NOT EXISTS songs (
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
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

// Add missing columns to existing tables
foreach ($tables_sql as $table => $sql) {
    try {
        $conn->exec($sql);
        echo "<div class='info'>✅ Table '$table' ready</div>";
        
        // For songs table, ensure all columns exist
        if ($table === 'songs') {
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
                        echo "<div class='info'>➕ Added column '$col_name' to songs table</div>";
                    }
                } catch (Exception $e) {
                    // Column might already exist or error adding it
                }
            }
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error with table '$table': " . $e->getMessage() . "</div>";
    }
}

// Remove the old foreach loop below
$tables_sql = []; // Clear it so it doesn't run again

// Tables already created above with column additions

// Now migrate songs
$migrated = 0;
$skipped = 0;
$errors = 0;

echo "<h2>Starting Migration...</h2>";

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
            } else {
                $album_id = $album['id'];
            }
        }
        
        // Check if song exists
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
        
        // Insert song (let MySQL handle timestamps automatically)
        $stmt = $conn->prepare("
            INSERT INTO songs (
                title, artist_id, album_id, file_path, cover_art,
                duration, file_size, plays, downloads,
                lyrics, is_featured, is_explicit, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
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
            $song['featured'] ?? 0,
            $song['explicit'] ?? 0,
            'approved'
        ]);
        
        $conn->commit();
        $migrated++;
        
        echo "<div class='success'>✅ Migrated: $title by $artist_name (Plays: " . ($song['plays'] ?? 0) . ")</div>";
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors++;
        echo "<div class='error'>❌ Error with '$title': " . $e->getMessage() . "</div>";
    }
}

echo "<h2>📊 Migration Complete!</h2>";
echo "<div class='success'>✅ Migrated: $migrated songs</div>";
if ($skipped > 0) echo "<div class='info'>⏭️ Skipped: $skipped songs (already existed)</div>";
if ($errors > 0) echo "<div class='error'>❌ Errors: $errors songs</div>";

// Final check
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<div class='success'><h3>✨ Total songs in database: $total</h3></div>";
} catch (Exception $e) {
    echo "<div class='error'>Error counting: " . $e->getMessage() . "</div>";
}

echo '<p><a href="songs.php" style="background:#28a745;color:white;padding:15px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:10px;">View Songs in Admin Panel</a></p>';
echo '<p><a href="index.php" style="background:#007bff;color:white;padding:15px 30px;text-decoration:none;border-radius:4px;display:inline-block;margin:10px;">Go to Dashboard</a></p>';

echo "</body></html>";
?>

