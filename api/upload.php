<?php
// api/upload.php
// API endpoint for music upload

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Song.php';
require_once '../classes/User.php';
require_once '../classes/Artist.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Authentication required'], 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    $artist = new Artist($db);
    $song = new Song($db);

    // Get user data
    $user_data = $user->getUserById(get_user_id());
    
    // Check if user is an artist
    if ($user_data['subscription_type'] !== 'artist') {
        json_response(['success' => false, 'error' => 'Artist subscription required'], 403);
    }

    // Get artist data
    $artist_data = $artist->getArtistByUserId(get_user_id());
    if (!$artist_data) {
        json_response(['success' => false, 'error' => 'Artist profile not found'], 404);
    }

    // Validate required fields
    $required_fields = ['title', 'genre_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            json_response(['success' => false, 'error' => ucfirst($field) . ' is required'], 400);
        }
    }

    // Validate file upload
    if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'error' => 'No audio file uploaded'], 400);
    }

    $file = $_FILES['audio_file'];

    // Validate file type
    $allowed_extensions = ['mp3', 'wav', 'flac', 'aac', 'm4a'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        json_response(['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions)], 400);
    }

    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        json_response(['success' => false, 'error' => 'File too large. Maximum size: ' . format_file_size(MAX_FILE_SIZE)], 400);
    }

    // Prepare upload data
    $upload_data = [
        'title' => sanitize_input($_POST['title']),
        'artist_id' => $artist_data['id'],
        'album_id' => !empty($_POST['album_id']) ? (int)$_POST['album_id'] : null,
        'genre_id' => (int)$_POST['genre_id'],
        'quality' => $_POST['quality'] ?? 'high',
        'lyrics' => sanitize_input($_POST['lyrics'] ?? ''),
        'track_number' => !empty($_POST['track_number']) ? (int)$_POST['track_number'] : null
    ];

    // Upload the song
    $result = $song->uploadSong($upload_data, $file);
    
    if ($result['success']) {
        json_response([
            'success' => true,
            'message' => 'Song uploaded successfully',
            'song_id' => $result['song_id']
        ]);
    } else {
        json_response(['success' => false, 'error' => $result['error']], 500);
    }

} catch (Exception $e) {
    error_log('Upload error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server error occurred'], 500);
}
?>
