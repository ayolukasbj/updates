<?php
// api/playlist.php
// API endpoint for playlist data

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Playlist.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    json_response(['success' => false, 'error' => 'Playlist ID required'], 400);
}

$playlist_id = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    $playlist = new Playlist($db);
    
    $playlist_data = $playlist->getPlaylistById($playlist_id);
    
    if (!$playlist_data) {
        json_response(['success' => false, 'error' => 'Playlist not found'], 404);
    }
    
    // Check if playlist is public or user owns it
    if (!$playlist_data['is_public'] && (!is_logged_in() || $playlist_data['user_id'] != get_user_id())) {
        json_response(['success' => false, 'error' => 'Access denied'], 403);
    }
    
    $songs = $playlist->getPlaylistSongs($playlist_id);
    
    json_response([
        'success' => true,
        'playlist' => $playlist_data,
        'songs' => $songs
    ]);
    
} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
?>
