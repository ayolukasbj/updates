<?php
// api/get-song-lyrics.php - Get song lyrics for editing
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = get_user_id();
$song_id = isset($_GET['song_id']) ? (int)$_GET['song_id'] : 0;

if ($song_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid song ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get song title and lyrics (verify ownership)
    $stmt = $conn->prepare("SELECT id, title, lyrics FROM songs WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$song_id, $user_id]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($song) {
        echo json_encode([
            'success' => true,
            'title' => $song['title'],
            'lyrics' => $song['lyrics'] ?? ''
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Song not found or access denied']);
    }
} catch (Exception $e) {
    error_log("Error in get-song-lyrics.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error loading lyrics']);
}
?>

