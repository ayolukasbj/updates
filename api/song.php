<?php
// api/song.php
// API endpoint for song data and streaming

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Song.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    json_response(['success' => false, 'error' => 'Song ID required'], 400);
}

$song_id = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    $song = new Song($db);
    
    $song_data = $song->getSongById($song_id);
    
    if (!$song_data) {
        json_response(['success' => false, 'error' => 'Song not found'], 404);
    }
    
    // Check if user has permission to access this song
    // You can add subscription checks here
    
    // Increment play count
    $song->incrementPlayCount($song_id);
    
    // Record play history if user is logged in
    if (is_logged_in()) {
        $song->recordPlayHistory(get_user_id(), $song_id);
    }
    
    // Replace file_path with stream API URL for security and proper streaming
    if (!empty($song_data['id'])) {
        $song_data['file_path'] = 'api/stream.php?id=' . $song_data['id'];
        $song_data['audio_file'] = 'api/stream.php?id=' . $song_data['id'];
    }
    
    json_response([
        'success' => true,
        'song' => $song_data
    ]);
    
} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
?>

