<?php
// api/artist-songs.php - API endpoint for loading artist songs with pagination
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$artist_id = $_GET['artist_id'] ?? null;
$artist_name = $_GET['artist_name'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

if (empty($artist_id) && empty($artist_name)) {
    echo json_encode(['success' => false, 'error' => 'Artist ID or name is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Build WHERE clause
    $where_conditions = ["(s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')"];
    $params = [];
    
    if ($artist_id) {
        $where_conditions[] = "(s.uploaded_by = ? OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = ?))";
        $params[] = $artist_id;
        $params[] = $artist_id;
    } else {
        $where_conditions[] = "COALESCE(s.artist, u.username, 'Unknown Artist') = ?";
        $params[] = $artist_name;
    }
    
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get total count
    $countSql = "
        SELECT COUNT(DISTINCT s.id) as total
        FROM songs s
        LEFT JOIN users u ON s.uploaded_by = u.id
        $where_sql
    ";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total / $per_page);
    
    // Get songs
    $sql = "
        SELECT DISTINCT s.*, 
               COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
               COALESCE(s.is_collaboration, 0) as is_collaboration
        FROM songs s
        LEFT JOIN users u ON s.uploaded_by = u.id
        $where_sql
        ORDER BY s.plays DESC, s.downloads DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($sql);
    
    // Add pagination params
    $params[] = $per_page;
    $params[] = $offset;
    
    // Bind parameters
    for ($i = 0; $i < count($params); $i++) {
        $stmt->bindValue($i + 1, $params[$i], is_int($params[$i]) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get collaborators for each song
    foreach ($songs as &$song) {
        if (!empty($song['is_collaboration'])) {
            $collabStmt = $conn->prepare("
                SELECT u.username
                FROM song_collaborators sc
                LEFT JOIN users u ON sc.user_id = u.id
                WHERE sc.song_id = ?
                ORDER BY sc.added_at ASC
            ");
            $collabStmt->execute([$song['id']]);
            $collaborators = $collabStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($collaborators)) {
                $artistNames = [$song['artist']];
                $artistNames = array_merge($artistNames, $collaborators);
                $artistNames = array_unique($artistNames);
                $song['display_artist'] = implode(' x ', $artistNames);
            } else {
                $song['display_artist'] = $song['artist'];
            }
        } else {
            $song['display_artist'] = $song['artist'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'songs' => $songs,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $total_pages,
            'has_more' => $page < $total_pages
        ]
    ]);
} catch (Exception $e) {
    error_log("API Error (artist-songs.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to load songs']);
}
?>


