<?php
// api/update-play-count.php
// Update play count for a song

require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');
// Allow CORS for remote access (IP/ngrok)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['song_id']) || empty($data['song_id'])) {
    echo json_encode(['error' => 'Song ID is required']);
    exit;
}

$songId = (int)$data['song_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Increment play count in database
    $stmt = $conn->prepare("UPDATE songs SET plays = COALESCE(plays, 0) + 1 WHERE id = ?");
    $result = $stmt->execute([$songId]);
    
    if (!$result) {
        throw new Exception('Failed to update play count');
    }
    
    // Get song and collaborators to update their stats
    $songStmt = $conn->prepare("SELECT uploaded_by FROM songs WHERE id = ?");
    $songStmt->execute([$songId]);
    $song = $songStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($song && !empty($song['uploaded_by'])) {
        // Get collaborators for this song
        $collabStmt = $conn->prepare("SELECT user_id FROM song_collaborators WHERE song_id = ?");
        $collabStmt->execute([$songId]);
        $collaborators = $collabStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Update stats for uploader and all collaborators (they should have artist records)
        $all_user_ids = array_merge([$song['uploaded_by']], $collaborators);
        $all_user_ids = array_unique($all_user_ids);
        
        foreach ($all_user_ids as $user_id) {
            // Check if user has an artist record
            $artistCheckStmt = $conn->prepare("SELECT id FROM artists WHERE user_id = ? LIMIT 1");
            $artistCheckStmt->execute([$user_id]);
            $artistRecord = $artistCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($artistRecord) {
                // Update artist stats (if they exist in artists table)
                try {
                    $updateArtistStmt = $conn->prepare("
                        UPDATE artists 
                        SET total_plays = COALESCE(total_plays, 0) + 1 
                        WHERE user_id = ?
                    ");
                    $updateArtistStmt->execute([$user_id]);
                } catch (Exception $e) {
                    // Artists table might not have total_plays column, ignore
                }
            }
        }
    }
    
    // Get updated play count
    $stmt = $conn->prepare("SELECT plays FROM songs WHERE id = ?");
    $stmt->execute([$songId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Play count updated',
        'plays' => (int)($result['plays'] ?? 0)
    ]);
} catch (Exception $e) {
    error_log("Error updating play count: " . $e->getMessage());
    echo json_encode([
        'error' => 'Failed to update play count',
        'message' => $e->getMessage()
    ]);
}
?>
