<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync Artists to Database</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; color: #155724; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; margin: 8px 0; border-radius: 4px; border-left: 4px solid #17a2b8; }
        h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .btn { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin: 5px; cursor: pointer; }
        .btn:hover { background: #5568d3; }
    </style>
</head>
<body>

<h1>🎤 Sync Artists from Songs to Database</h1>

<div class="info">
    <strong>What this does:</strong>
    <ul style="margin: 10px 0; padding-left: 20px;">
        <li>Extracts unique artists from your songs in the database</li>
        <li>Creates artist profiles for each one</li>
        <li>Calculates their play counts and download stats</li>
        <li>Makes them appear on the frontend with proper verification badges</li>
    </ul>
</div>

<?php
$synced = 0;
$skipped = 0;
$errors = [];

try {
    // Get all unique artists from songs table
    $stmt = $conn->query("
        SELECT 
            artist_id,
            MIN(s.id) as first_song_id
        FROM songs s
        WHERE artist_id IS NOT NULL
        GROUP BY artist_id
    ");
    
    $song_artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<div class="info">📋 Found ' . count($song_artists) . ' artists in songs table</div>';
    
    foreach ($song_artists as $sa) {
        $artist_id = $sa['artist_id'];
        
        // Check if artist already exists in artists table
        $check = $conn->prepare("SELECT id FROM artists WHERE id = ?");
        $check->execute([$artist_id]);
        
        if ($check->rowCount() > 0) {
            $skipped++;
            continue;
        }
        
        // Get artist info from first song
        $stmt = $conn->prepare("SELECT * FROM songs WHERE artist_id = ? LIMIT 1");
        $stmt->execute([$artist_id]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$song) continue;
        
        // Calculate stats
        $stats = $conn->prepare("
            SELECT 
                COUNT(*) as total_songs,
                COALESCE(SUM(plays), 0) as total_plays,
                COALESCE(SUM(downloads), 0) as total_downloads
            FROM songs 
            WHERE artist_id = ?
        ");
        $stats->execute([$artist_id]);
        $artist_stats = $stats->fetch(PDO::FETCH_ASSOC);
        
        // Insert artist
        $insert = $conn->prepare("
            INSERT INTO artists (
                id, name, bio, total_plays, total_downloads, verified
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $artist_name = $song['artist'] ?? 'Unknown Artist';
        $bio = "Artist with {$artist_stats['total_songs']} songs";
        
        $insert->execute([
            $artist_id,
            $artist_name,
            $bio,
            $artist_stats['total_plays'],
            $artist_stats['total_downloads'],
            0 // not verified by default
        ]);
        
        $synced++;
        echo "<div class='success'>✅ Synced: $artist_name (ID: $artist_id) - {$artist_stats['total_songs']} songs, {$artist_stats['total_plays']} plays</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}

echo '<h2>📊 Summary</h2>';
echo '<div class="success">✅ Synced: ' . $synced . ' artists</div>';
if ($skipped > 0) {
    echo '<div class="info">⏭️ Skipped: ' . $skipped . ' (already exist)</div>';
}

if ($synced > 0) {
    echo '<div class="success"><h3>🎉 Artists synced successfully!</h3><p>Visit the frontend to see them with verification badges</p></div>';
} elseif ($skipped > 0) {
    echo '<div class="info"><h3>✓ All artists already synced!</h3></div>';
} else {
    echo '<div class="error"><h3>❌ No artists found to sync</h3><p>Make sure you have songs in the database first.</p></div>';
}

echo '<p style="text-align: center; margin-top: 30px;">';
echo '<a href="artists.php" class="btn">🎤 View Artists in Admin</a>';
echo '<a href="../artistes.php" class="btn">🌐 View Artists on Frontend</a>';
echo '<a href="index.php" class="btn">🏠 Dashboard</a>';
echo '</p>';
?>

</body>
</html>

