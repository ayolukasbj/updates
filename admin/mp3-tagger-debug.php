<?php
// Start output buffering FIRST
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo "<br>SHUTDOWN ERROR: " . print_r($error, true);
    }
});

// Load auth-check FIRST (no output before this - it may redirect)
require_once 'auth-check.php';

// Now we can output (user is authenticated)
ob_end_clean();
ob_start();

echo "START<br>";
flush();
echo "ERROR REPORTING ON<br>";
flush();
echo "AFTER AUTH-CHECK<br>";
flush();
echo "AFTER AUTH-CHECK<br>";
flush();

ini_set('display_errors', 1);
echo "RE-ENABLED ERRORS<br>";
flush();

require_once '../config/database.php';
echo "AFTER DATABASE.PHP<br>";
flush();

require_once '../includes/mp3-tagger.php';
echo "AFTER MP3-TAGGER.PHP<br>";
flush();

require_once '../includes/audio-processor.php';
echo "AFTER AUDIO-PROCESSOR.PHP<br>";
flush();

require_once '../includes/audio-processor-simple.php';
echo "AFTER AUDIO-PROCESSOR-SIMPLE.PHP<br>";
flush();

$db = new Database();
$conn = $db->getConnection();
echo "AFTER DB CONNECTION<br>";
flush();

$song_id = $_GET['id'] ?? null;
echo "SONG ID: " . ($song_id ?? 'NULL') . "<br>";
flush();

if (!$song_id) {
    die("NO SONG ID");
}

$stmt = $conn->prepare("SELECT s.*, a.name as artist_name, al.title as album_title FROM songs s LEFT JOIN artists a ON s.artist_id = a.id LEFT JOIN albums al ON s.album_id = al.id WHERE s.id = ?");
$stmt->execute([$song_id]);
$song = $stmt->fetch(PDO::FETCH_ASSOC);
echo "SONG FETCHED: " . ($song ? 'YES' : 'NO') . "<br>";
flush();

if (!$song) {
    die("SONG NOT FOUND");
}

$file_path = '../' . ltrim($song['file_path'], '/');
echo "FILE PATH: " . htmlspecialchars($file_path) . "<br>";
echo "FILE EXISTS: " . (file_exists($file_path) ? 'YES' : 'NO') . "<br>";
flush();

if (file_exists($file_path)) {
    echo "TRYING TO CREATE MP3TAGGER<br>";
    flush();
    try {
        $tagger = new MP3Tagger($file_path);
        echo "MP3TAGGER CREATED<br>";
        flush();
        $tags = $tagger->readTags();
        echo "TAGS READ<br>";
        flush();
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "<br>";
        flush();
    }
}

echo "END - ALL OK<br>";
flush();

