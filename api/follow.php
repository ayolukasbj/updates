<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = get_user_id();
$data = json_decode(file_get_contents('php://input'), true);

$artist_id = $data['artist_id'] ?? null;
$action = $data['action'] ?? 'follow';

if (!$artist_id || !in_array($action, ['follow', 'unfollow'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data provided']);
    exit;
}

if ($artist_id == $user_id) {
    echo json_encode(['success' => false, 'error' => 'Cannot follow yourself']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($action === 'follow') {
        // Check if already following
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = ?");
        $checkStmt->execute([$user_id, $artist_id]);
        $exists = $checkStmt->fetchColumn() > 0;
        
        if (!$exists) {
            $stmt = $conn->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $artist_id]);
            
            // Create notification
            try {
                $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, 'new_follower', 'New Follower', ?, ?)");
                $message = "started following you";
                $notifStmt->execute([$artist_id, $message, $user_id]);
            } catch (Exception $e) {
                // Notification table might not exist, ignore
            }
        }
    } else {
        // Unfollow
        $stmt = $conn->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$user_id, $artist_id]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Error in follow.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>





