<?php
// api/download-count.php
// Increment download count (called when download starts)

require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

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
    
    // Increment download count in database
    $stmt = $conn->prepare("UPDATE songs SET downloads = COALESCE(downloads, 0) + 1 WHERE id = ?");
    $stmt->execute([$songId]);
    
    // Get updated download count
    $stmt = $conn->prepare("SELECT downloads FROM songs WHERE id = ?");
    $stmt->execute([$songId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'downloads' => (int)($result['downloads'] ?? 0)
    ]);
} catch (Exception $e) {
    error_log("Error updating download count: " . $e->getMessage());
    echo json_encode([
        'error' => 'Failed to update download count',
        'message' => $e->getMessage()
    ]);
}
?>

