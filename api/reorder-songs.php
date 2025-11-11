<?php
// api/reorder-songs.php
// API endpoint for reordering songs in an album

header('Content-Type: application/json');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$album_id = isset($input['album_id']) ? (int)$input['album_id'] : 0;
$song_orders = isset($input['song_orders']) ? $input['song_orders'] : [];

if (empty($album_id) || empty($song_orders)) {
    echo json_encode(['success' => false, 'error' => 'Album ID and song orders are required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get album info
    $albumStmt = $conn->prepare("SELECT * FROM albums WHERE id = ?");
    $albumStmt->execute([$album_id]);
    $album = $albumStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$album) {
        echo json_encode(['success' => false, 'error' => 'Album not found']);
        exit;
    }
    
    // Check if user is admin or artist who owns the album
    $is_admin = false;
    $is_artist = false;
    
    // Check if user is admin
    try {
        $roleStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        $roleExists = $roleStmt->rowCount() > 0;
        
        if ($roleExists) {
            $userStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $userStmt->execute([$user_id]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && in_array($user['role'], ['admin', 'super_admin'])) {
                $is_admin = true;
            }
        }
    } catch (Exception $e) {
        error_log("Error checking admin role: " . $e->getMessage());
    }
    
    // Check if user is the artist who owns the album
    // Check if any songs in the album were uploaded by this user
    $artistCheckStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM songs 
        WHERE album_id = ? AND uploaded_by = ?
    ");
    $artistCheckStmt->execute([$album_id, $user_id]);
    $artistCheck = $artistCheckStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($artistCheck && $artistCheck['count'] > 0) {
        $is_artist = true;
    }
    
    // If user is neither admin nor artist, deny access
    if (!$is_admin && !$is_artist) {
        echo json_encode(['success' => false, 'error' => 'You do not have permission to reorder songs in this album']);
        exit;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Update track_number for each song
        foreach ($song_orders as $position => $song_id) {
            $song_id = (int)$song_id;
            $position = (int)$position + 1; // Start from 1, not 0
            
            $updateStmt = $conn->prepare("
                UPDATE songs 
                SET track_number = ? 
                WHERE id = ? AND album_id = ?
            ");
            $updateStmt->execute([$position, $song_id, $album_id]);
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Songs reordered successfully']);
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error reordering songs: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to reorder songs: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    error_log("Error in reorder-songs.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>










