<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = get_user_id();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$song_id = $input['song_id'] ?? 0;

if (!$song_id) {
    echo json_encode(['success' => false, 'message' => 'Song ID is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if user owns this song
    $stmt = $conn->prepare("SELECT uploaded_by FROM songs WHERE id = ?");
    $stmt->execute([$song_id]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$song) {
        echo json_encode(['success' => false, 'message' => 'Song not found']);
        exit;
    }
    
    if ($song['uploaded_by'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this song']);
        exit;
    }
    
    // Delete collaborator mappings first
    try {
        $delStmt = $conn->prepare("DELETE FROM song_collaborators WHERE song_id = ?");
        $delStmt->execute([$song_id]);
    } catch (Exception $e) {
        // Table might not exist, ignore
    }
    
    // Get file path before deleting for cleanup
    $fileStmt = $conn->prepare("SELECT file_path, cover_art FROM songs WHERE id = ?");
    $fileStmt->execute([$song_id]);
    $fileData = $fileStmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete the song from database
    $stmt = $conn->prepare("DELETE FROM songs WHERE id = ?");
    $result = $stmt->execute([$song_id]);
    
    if ($result) {
        // Optionally delete physical files
        if ($fileData) {
            if (!empty($fileData['file_path']) && file_exists($fileData['file_path'])) {
                @unlink($fileData['file_path']);
            }
            if (!empty($fileData['cover_art']) && file_exists($fileData['cover_art']) && 
                strpos($fileData['cover_art'], 'uploads/') !== false) {
                @unlink($fileData['cover_art']);
            }
        }
        echo json_encode(['success' => true, 'message' => 'Song deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete song']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

