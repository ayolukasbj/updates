<?php
// api/favorites.php
// API endpoint for managing favorites

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Authentication required'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['song_id']) || !isset($input['action'])) {
    json_response(['success' => false, 'error' => 'Song ID and action required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $song_id = (int)$input['song_id'];
    $user_id = get_user_id();
    $action = $input['action'];
    
    if ($action === 'add') {
        $query = "INSERT INTO user_favorites (user_id, song_id) VALUES (:user_id, :song_id)";
        $message = 'Added to favorites';
    } elseif ($action === 'remove') {
        $query = "DELETE FROM user_favorites WHERE user_id = :user_id AND song_id = :song_id";
        $message = 'Removed from favorites';
    } else {
        json_response(['success' => false, 'error' => 'Invalid action'], 400);
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':song_id', $song_id);
    
    if ($stmt->execute()) {
        json_response(['success' => true, 'message' => $message]);
    } else {
        json_response(['success' => false, 'error' => 'Failed to update favorites'], 500);
    }
    
} catch (Exception $e) {
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
?>
