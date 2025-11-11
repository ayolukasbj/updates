<?php
// api/get-download-count.php - Get current download count for a song (read-only)
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');
// Allow CORS for remote access (IP/ngrok)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$song_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($song_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Song ID is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get current download count (don't increment)
    $stmt = $conn->prepare("SELECT downloads FROM songs WHERE id = ?");
    $stmt->execute([$song_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        http_response_code(404);
        echo json_encode(['error' => 'Song not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'downloads' => (int)($result['downloads'] ?? 0)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error getting download count: " . $e->getMessage());
    echo json_encode([
        'error' => 'Failed to get download count',
        'message' => $e->getMessage()
    ]);
}
?>

