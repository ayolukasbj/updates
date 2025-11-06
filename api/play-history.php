<?php
// api/play-history.php
// API endpoint for recording play history

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Song.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Authentication required'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['song_id'])) {
    json_response(['success' => false, 'error' => 'Song ID required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $song = new Song($db);
    
    $result = $song->recordPlayHistory(
        get_user_id(),
        $input['song_id'],
        $input['duration_played'] ?? 0,
        $input['completed'] ?? false
    );
    
    if ($result) {
        json_response(['success' => true]);
    } else {
        json_response(['success' => false, 'error' => 'Failed to record play history'], 500);
    }
    
} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
?>
