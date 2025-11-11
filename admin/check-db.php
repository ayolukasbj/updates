<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: text/plain');

echo "=== DATABASE DIAGNOSTIC TOOL ===\n\n";

// Check tables
echo "1. Checking Tables:\n";
echo "-------------------\n";
$tables = ['users', 'artists', 'albums', 'songs', 'genres'];
foreach ($tables as $table) {
    $stmt = $conn->query("SHOW TABLES LIKE '$table'");
    $exists = $stmt->rowCount() > 0;
    echo "$table: " . ($exists ? "EXISTS ✓" : "NOT FOUND ✗") . "\n";
    
    if ($exists) {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "  → Records: $count\n";
    }
}

// Check songs columns
echo "\n2. Checking 'songs' table structure:\n";
echo "------------------------------------\n";
try {
    $stmt = $conn->query("SHOW COLUMNS FROM songs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    // Check for plays and downloads
    echo "\n3. Checking plays and downloads:\n";
    echo "---------------------------------\n";
    $stmt = $conn->query("SELECT SUM(plays) as total_plays, SUM(downloads) as total_downloads FROM songs");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total plays: " . ($stats['total_plays'] ?? 0) . "\n";
    echo "Total downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    
    // Sample songs
    echo "\n4. Sample songs (first 3):\n";
    echo "-------------------------\n";
    $stmt = $conn->query("SELECT id, title, plays, downloads FROM songs LIMIT 3");
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($songs as $song) {
        echo "  [{$song['id']}] {$song['title']} - Plays: {$song['plays']}, Downloads: {$song['downloads']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check if JSON file exists
echo "\n5. Checking JSON file:\n";
echo "---------------------\n";
$jsonFile = '../data/songs.json';
if (file_exists($jsonFile)) {
    echo "songs.json: EXISTS\n";
    $data = json_decode(file_get_contents($jsonFile), true);
    echo "  → Records: " . count($data) . "\n";
} else {
    echo "songs.json: NOT FOUND\n";
}

echo "\n=== END OF DIAGNOSTIC ===\n";
?>

