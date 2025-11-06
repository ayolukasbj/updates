<?php
// api/song-data.php
// Fetch song data for the Luo player

require_once '../config/config.php';
require_once '../includes/song-storage.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['error' => 'Song ID is required']);
    exit;
}

$songId = $_GET['id'];

// Fetch song from storage
$song = getSongById($songId);

if (!$song) {
    echo json_encode(['error' => 'Song not found']);
    exit;
}

// Format song data for the player
$response = [
    'id' => $song['id'],
    'title' => $song['title'] ?? 'Unknown Song',
    'artist' => $song['artist'] ?? 'Unknown Artist',
    'album' => $song['album'] ?? '',
    'audio_file' => '',
    'cover_art' => $song['cover_art'] ?? '',
    'duration' => $song['duration'] ?? '3:45',
    'plays' => $song['plays'] ?? 0,
    'downloads' => $song['downloads'] ?? 0,
    'genre' => $song['genre'] ?? '',
    'uploaded_at' => $song['uploaded_at'] ?? ''
];

// Set audio file - check both audio_file and file_path fields
if (!empty($song['audio_file'])) {
    $response['audio_file'] = $song['audio_file'];
} elseif (!empty($song['file_path'])) {
    $response['audio_file'] = $song['file_path'];
} else {
    // Fallback to demo audio
    $response['audio_file'] = 'demo-audio.mp3';
}

// Ensure cover art exists or use default
if (empty($response['cover_art'])) {
    $response['cover_art'] = 'assets/images/default-avatar.svg';
}

echo json_encode($response);
?>
