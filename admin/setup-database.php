<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; margin: 10px 0; border-radius: 4px; }
        h1 { color: #333; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Database Setup & Migration Tool</h1>

<?php
$steps = [];

// Step 1: Check if songs table exists
$stmt = $conn->query("SHOW TABLES LIKE 'songs'");
$songs_table_exists = $stmt->rowCount() > 0;

if (!$songs_table_exists) {
    echo '<div class="error">❌ Songs table does not exist! Please import the database schema first.</div>';
    echo '<div class="info">Run this SQL file: <code>database/schema.sql</code></div>';
    echo '<a href="index.php" class="btn">Back to Dashboard</a>';
    exit;
}

echo '<div class="success">✅ Songs table exists</div>';

// Step 2: Check and add missing columns
$required_columns = [
    'status' => "VARCHAR(20) DEFAULT 'approved'",
    'cover_art' => "VARCHAR(255) DEFAULT NULL"
];

foreach ($required_columns as $column => $definition) {
    $stmt = $conn->query("SHOW COLUMNS FROM songs LIKE '$column'");
    if ($stmt->rowCount() == 0) {
        try {
            $conn->exec("ALTER TABLE songs ADD COLUMN $column $definition");
            echo '<div class="success">✅ Added missing column: ' . $column . '</div>';
            $steps[] = "Added column: $column";
        } catch (Exception $e) {
            echo '<div class="error">❌ Failed to add column ' . $column . ': ' . $e->getMessage() . '</div>';
        }
    } else {
        echo '<div class="info">✓ Column exists: ' . $column . '</div>';
    }
}

// Step 3: Check current song count
$stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
$db_song_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo '<div class="info">📊 Current songs in database: ' . $db_song_count . '</div>';

// Step 4: Check JSON file
$jsonFile = '../data/songs.json';
if (!file_exists($jsonFile)) {
    echo '<div class="error">❌ No songs.json file found!</div>';
    if ($db_song_count == 0) {
        echo '<div class="info">No songs in database or JSON file. Upload some songs first!</div>';
    }
    echo '<a href="index.php" class="btn">Back to Dashboard</a>';
    echo '<a href="songs.php" class="btn btn-success">View Songs</a>';
    exit;
}

$songs_json = json_decode(file_get_contents($jsonFile), true);
$json_song_count = count($songs_json ?? []);

echo '<div class="info">📋 Songs in JSON file: ' . $json_song_count . '</div>';

if ($json_song_count == 0) {
    echo '<div class="info">No songs to migrate from JSON.</div>';
    echo '<a href="index.php" class="btn">Back to Dashboard</a>';
    echo '<a href="songs.php" class="btn btn-success">View Songs</a>';
    exit;
}

// Show sample data
echo '<h2>📄 Sample Data from JSON</h2>';
echo '<pre>';
foreach (array_slice($songs_json, 0, 2) as $idx => $song) {
    echo ($idx + 1) . ". " . ($song['title'] ?? 'Unknown') . " by " . ($song['artist'] ?? 'Unknown Artist') . "\n";
    echo "   Plays: " . ($song['plays'] ?? 0) . ", Downloads: " . ($song['downloads'] ?? 0) . "\n";
    if (!empty($song['audio_file'])) {
        echo "   Audio: " . $song['audio_file'] . "\n";
    }
    if (!empty($song['cover_art'])) {
        echo "   Cover: " . $song['cover_art'] . "\n";
    }
    echo "\n";
}
if ($json_song_count > 2) {
    echo "... and " . ($json_song_count - 2) . " more songs\n";
}
echo '</pre>';

echo '<hr>';
echo '<h2>🚀 Ready to Migrate!</h2>';
echo '<div class="info">';
echo 'This will:<br>';
echo '✓ Create artists from your JSON data<br>';
echo '✓ Create albums (if specified)<br>';
echo '✓ Import all ' . $json_song_count . ' songs to the database<br>';
echo '✓ Preserve play counts and download counts<br>';
echo '✓ Keep the JSON file as backup<br>';
echo '</div>';

echo '<div style="margin: 20px 0;">';
echo '<a href="migrate-songs.php" class="btn btn-success" style="font-size: 18px; padding: 15px 30px;">▶️ Start Migration</a>';
echo '</div>';

echo '<hr>';
echo '<a href="index.php" class="btn">← Back to Dashboard</a>';
?>

</body>
</html>

