<?php
// api/update-download-count.php - Update song download count
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$song_id = $input['song_id'] ?? '';

if (empty($song_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Song ID is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if song exists
    $stmt = $conn->prepare("SELECT id FROM songs WHERE id = ?");
    $stmt->execute([$song_id]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$song) {
        http_response_code(404);
        echo json_encode(['error' => 'Song not found']);
        exit;
    }
    
    // Increment download count in database
    $stmt = $conn->prepare("UPDATE songs SET downloads = COALESCE(downloads, 0) + 1 WHERE id = ?");
    $stmt->execute([$song_id]);
    
    // Get updated download count
    $stmt = $conn->prepare("SELECT downloads FROM songs WHERE id = ?");
    $stmt->execute([$song_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'downloads' => (int)($result['downloads'] ?? 0)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error updating download count: " . $e->getMessage());
    echo json_encode([
        'error' => 'Failed to update download count',
        'message' => $e->getMessage()
    ]);
}
?>
