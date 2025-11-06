<?php
// api/artist-stats.php - API endpoint for artist statistics
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$artist_id = $_GET['artist_id'] ?? null;
$artist_name = $_GET['artist_name'] ?? '';

if (empty($artist_id) && empty($artist_name)) {
    echo json_encode(['success' => false, 'error' => 'Artist ID or name is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Build WHERE clause
    if ($artist_id) {
        $where_sql = "(s.uploaded_by = ? OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = ?))";
        $params = [$artist_id, $artist_id];
    } else {
        $where_sql = "COALESCE(s.artist, u.username, 'Unknown Artist') = ?";
        $params = [$artist_name];
    }
    
    // Get stats
    $statsSql = "
        SELECT 
            COUNT(DISTINCT s.id) as total_songs,
            SUM(COALESCE(s.plays, 0)) as total_plays,
            SUM(COALESCE(s.downloads, 0)) as total_downloads
        FROM songs s
        LEFT JOIN users u ON s.uploaded_by = u.id
        WHERE $where_sql
        AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
    ";
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get unique genres
    $genresSql = "
        SELECT DISTINCT s.genre
        FROM songs s
        LEFT JOIN users u ON s.uploaded_by = u.id
        WHERE $where_sql
        AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
        AND s.genre IS NOT NULL
        AND s.genre != ''
    ";
    $genresStmt = $conn->prepare($genresSql);
    $genresStmt->execute($params);
    $genres = $genresStmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_songs' => (int)($stats['total_songs'] ?? 0),
            'total_plays' => (int)($stats['total_plays'] ?? 0),
            'total_downloads' => (int)($stats['total_downloads'] ?? 0),
            'genres' => array_values(array_filter($genres))
        ]
    ]);
} catch (Exception $e) {
    error_log("API Error (artist-stats.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to load stats']);
}
?>


