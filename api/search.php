<?php
// api/search.php - Live search API
header('Content-Type: application/json');

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Return empty if query too short
if (strlen($query) < 2) {
    echo json_encode(['songs' => [], 'artists' => [], 'news' => []]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Search songs from database - including collaboration songs
    $searchTerm = '%' . $query . '%';
    
    // Check if album_title column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM songs LIKE 'album_title'");
    $has_album_title = $colCheck->rowCount() > 0;
    
    $albumTitleCondition = $has_album_title ? "OR COALESCE(s.album_title, '') LIKE ?" : "";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    if ($has_album_title) {
        $params[] = $searchTerm;
    }
    
    $stmt = $conn->prepare("
        SELECT DISTINCT s.id, s.title, 
               COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
               s.cover_art, s.uploaded_by, 
               CASE WHEN EXISTS (
                   SELECT 1 FROM song_collaborators sc2 WHERE sc2.song_id = s.id
               ) THEN 1 ELSE 0 END as is_collaboration
        FROM songs s
        LEFT JOIN users u ON s.uploaded_by = u.id
        LEFT JOIN song_collaborators sc ON sc.song_id = s.id
        LEFT JOIN users collaborator ON sc.user_id = collaborator.id
        WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
        AND (
            s.title LIKE ? 
            OR s.artist LIKE ?
            OR u.username LIKE ?
            " . ($has_album_title ? "OR COALESCE(s.album_title, '') LIKE ?" : "") . "
            OR collaborator.username LIKE ?
        )
        ORDER BY s.plays DESC, s.id DESC
        LIMIT 5
    ");
    $stmt->execute($params);
    $matchedSongs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format songs and include collaborators
    foreach ($matchedSongs as &$song) {
        $song['cover_art'] = $song['cover_art'] ?? 'assets/images/default-cover.png';
        
        // If collaboration, fetch and format all artists
        if (!empty($song['is_collaboration']) && !empty($song['id'])) {
            $artist_names = [];
            
            // Get uploader name
            if (!empty($song['uploaded_by'])) {
                $uploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                $uploaderStmt->execute([$song['uploaded_by']]);
                $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                if ($uploader && !empty($uploader['username'])) {
                    $artist_names[] = $uploader['username'];
                }
            }
            
            // Get collaborators
            $collabStmt = $conn->prepare("
                SELECT DISTINCT COALESCE(u.username, sc.user_id) as artist_name
                FROM song_collaborators sc
                LEFT JOIN users u ON sc.user_id = u.id
                WHERE sc.song_id = ?
                ORDER BY sc.added_at ASC
            ");
            $collabStmt->execute([$song['id']]);
            $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($collaborators)) {
                foreach ($collaborators as $c) {
                    $collab_name = $c['artist_name'] ?? null;
                    if (!empty($collab_name) && !in_array($collab_name, $artist_names)) {
                        $artist_names[] = $collab_name;
                    }
                }
            }
            
            // Format as "Artist1 x Artist2 x Artist3"
            if (count($artist_names) > 0) {
                $song['artist'] = implode(' x ', $artist_names);
            }
        }
        
        // Generate slug for song URL: "title-by-artist"
        $titleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
        $titleSlug = preg_replace('/\s+/', '-', trim($titleSlug));
        $artistName = $song['artist'] ?? 'unknown-artist';
        $artistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $artistName));
        $artistSlug = preg_replace('/\s+/', '-', trim($artistSlug));
        $song['slug'] = $titleSlug . '-by-' . $artistSlug;
    }
    
    // Search artists/users directly from users table
    $stmt = $conn->prepare("
        SELECT u.id, u.username as name, u.avatar,
               COUNT(DISTINCT s.id) as song_count
        FROM users u
        LEFT JOIN songs s ON s.uploaded_by = u.id
        WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved' OR s.id IS NULL)
        AND (
            u.username LIKE ? 
        )
        GROUP BY u.id, u.username, u.avatar
        HAVING song_count > 0 OR u.username LIKE ?
        ORDER BY song_count DESC, u.username ASC
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $matchedArtists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format artists with IDs for profile links
    foreach ($matchedArtists as &$artist) {
        $artist['avatar'] = $artist['avatar'] ?? 'assets/images/default-avatar.svg';
        $artist['id'] = (int)$artist['id'];
    }
    
    // Search news/posts
    $newsStmt = $conn->prepare("
        SELECT id, title, slug, category, image, excerpt, created_at
        FROM news
        WHERE is_published = 1
        AND (
            title LIKE ? 
            OR content LIKE ?
            OR excerpt LIKE ?
            OR category LIKE ?
        )
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $newsStmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $matchedNews = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format news with image paths
    foreach ($matchedNews as &$news) {
        if (!empty($news['image'])) {
            // Fix image path if needed
            if (strpos($news['image'], 'http://') !== 0 && strpos($news['image'], 'https://') !== 0) {
                $news['image'] = 'uploads/' . ltrim($news['image'], 'uploads/');
            }
        } else {
            $news['image'] = 'assets/images/default-cover.png';
        }
    }
    
    // Return results
    echo json_encode([
        'songs' => $matchedSongs,
        'artists' => $matchedArtists,
        'news' => $matchedNews
    ]);
    
} catch (Exception $e) {
    error_log("Search API error: " . $e->getMessage());
    error_log("Search API error trace: " . $e->getTraceAsString());
    // Don't send 500 error, return empty results instead
    http_response_code(200); // Return 200 OK even on error
    echo json_encode([
        'songs' => [],
        'artists' => [],
        'news' => []
        // No error field - empty results mean no matches
    ], JSON_UNESCAPED_UNICODE);
}

