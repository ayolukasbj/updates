<?php
// Test script to check song display info
require_once 'config/config.php';
require_once 'config/database.php';

$song_id = 11; // Test song

$db = new Database();
$conn = $db->getConnection();

// Get song
$stmt = $conn->prepare("SELECT * FROM songs WHERE id = ?");
$stmt->execute([$song_id]);
$song = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Song Info:</h2>";
echo "<pre>";
print_r($song);
echo "</pre>";

// Get uploader
$stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->execute([$song['uploaded_by']]);
$uploader = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Uploader:</h2>";
echo "<pre>";
print_r($uploader);
echo "</pre>";

// Get collaborators
$stmt = $conn->prepare("
    SELECT sc.user_id, u.username 
    FROM song_collaborators sc
    LEFT JOIN users u ON u.id = sc.user_id
    WHERE sc.song_id = ?
");
$stmt->execute([$song_id]);
$collaborators = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Collaborators:</h2>";
echo "<pre>";
print_r($collaborators);
echo "</pre>";

// Build display name
$all_artists = [];
if ($uploader) {
    $all_artists[] = ['name' => $uploader['username']];
}
foreach ($collaborators as $c) {
    if (!empty($c['username'])) {
        $all_artists[] = ['name' => $c['username']];
    }
}

$all_artist_names = array_map(function($a) { return $a['name']; }, $all_artists);
$display_artist_name = implode(' x ', $all_artist_names);

echo "<h2>Final Display Name:</h2>";
echo "<p><strong>" . htmlspecialchars($song['title']) . " - " . htmlspecialchars($display_artist_name) . "</strong></p>";
echo "<p>All artists: " . print_r($all_artist_names, true) . "</p>";
?>

