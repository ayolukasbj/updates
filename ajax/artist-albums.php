<?php
// ajax/artist-albums.php - Albums tab content for artist profile
// Error reporting - only log errors, don't display in production
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);

// Set proper headers - CORS for IP/ngrok compatibility
header('Content-Type: text/html; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fix paths - use __DIR__ to get the actual file location
// When included from artist-profile.php, relative paths break
$ajax_dir = __DIR__; // Directory of this file (ajax/)
$project_root = dirname($ajax_dir); // Parent directory (project root)

require_once $project_root . '/config/config.php';
require_once $project_root . '/config/database.php';

// Support both GET parameter names and direct variable assignment from include
$artist_name = isset($_GET['artist_name']) ? trim($_GET['artist_name']) : (isset($tab_artist_name) ? $tab_artist_name : '');
$artist_id = isset($_GET['artist_id']) ? (int)$_GET['artist_id'] : (isset($tab_artist_id) ? (int)$tab_artist_id : 0);

if (empty($artist_name) && empty($artist_id)) {
    echo '<p style="text-align: center; color: #999; padding: 40px;">No artist specified.</p>';
    exit;
}

try {
    if (!class_exists('Database')) {
        die('<p style="text-align: center; color: #999; padding: 40px;">Database class not found.</p>');
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $artist_albums = [];
    
    // Check if albums table has user_id or artist_id column
    $colCheck = $conn->query("SHOW COLUMNS FROM albums");
    $albumColumns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
    $has_user_id = in_array('user_id', $albumColumns);
    $has_artist_id = in_array('artist_id', $albumColumns);
    
    if ($artist_id) {
        // First, determine actual user_id
        $actual_user_id = $artist_id;
        
        // Try to find artist in artists table by user_id or id
        $albumArtistStmt = $conn->prepare("
            SELECT id, user_id FROM artists 
            WHERE user_id = ? OR id = ?
            LIMIT 1
        ");
        $albumArtistStmt->execute([$artist_id, $artist_id]);
        $album_artist = $albumArtistStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($album_artist && !empty($album_artist['user_id'])) {
            $actual_user_id = $album_artist['user_id'];
        }
        
        // Query albums based on available columns
        // IMPORTANT: Only show albums where this artist is the creator (uploaded songs), NOT just a collaborator
        if ($has_artist_id && $album_artist) {
            // Get albums where artist uploaded at least one song (album creator only)
            $albumsStmt = $conn->prepare("
                SELECT DISTINCT a.*, 
                       COUNT(DISTINCT s.id) as song_count,
                       SUM(DISTINCT COALESCE(s.plays, 0)) as total_plays,
                       SUM(DISTINCT COALESCE(s.downloads, 0)) as total_downloads
                FROM albums a
                INNER JOIN songs s ON a.id = s.album_id AND s.uploaded_by = ?
                GROUP BY a.id
                ORDER BY a.release_date DESC, a.created_at DESC
            ");
            $albumsStmt->execute([$actual_user_id]);
            $artist_albums = $albumsStmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($has_user_id) {
            // Get albums where user uploaded at least one song (album creator only)
            $albumsStmt = $conn->prepare("
                SELECT DISTINCT a.*, 
                       COUNT(DISTINCT s.id) as song_count,
                       SUM(DISTINCT COALESCE(s.plays, 0)) as total_plays,
                       SUM(DISTINCT COALESCE(s.downloads, 0)) as total_downloads
                FROM albums a
                INNER JOIN songs s ON a.id = s.album_id AND s.uploaded_by = ?
                GROUP BY a.id
                ORDER BY a.release_date DESC, a.created_at DESC
            ");
            $albumsStmt->execute([$actual_user_id]);
            $artist_albums = $albumsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Try to find artist in artists table by name
        $albumArtistStmt = $conn->prepare("
            SELECT id FROM artists 
            WHERE LOWER(name) = LOWER(?)
            LIMIT 1
        ");
        $albumArtistStmt->execute([$artist_name]);
        $album_artist = $albumArtistStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($album_artist) {
            // Get user_id for this artist to check album ownership
            $artistUserStmt = $conn->prepare("SELECT user_id FROM artists WHERE id = ? LIMIT 1");
            $artistUserStmt->execute([$album_artist['id']]);
            $artistUser = $artistUserStmt->fetch(PDO::FETCH_ASSOC);
            $artist_user_id = $artistUser['user_id'] ?? null;
            
            if ($artist_user_id) {
                // Only get albums where artist uploaded at least one song (album creator)
                $albumsStmt = $conn->prepare("
                    SELECT DISTINCT a.*, 
                           COUNT(DISTINCT s.id) as song_count,
                           SUM(DISTINCT COALESCE(s.plays, 0)) as total_plays,
                           SUM(DISTINCT COALESCE(s.downloads, 0)) as total_downloads
                    FROM albums a
                    INNER JOIN songs s ON a.id = s.album_id AND s.uploaded_by = ?
                    GROUP BY a.id
                    ORDER BY a.release_date DESC, a.created_at DESC
                ");
                $albumsStmt->execute([$artist_user_id]);
                $artist_albums = $albumsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
    
    // Also try to get albums from songs if no albums found
    if (empty($artist_albums)) {
        // Determine actual user_id
        $actual_user_id = $artist_id;
        if ($artist_id) {
            $userCheckStmt = $conn->prepare("SELECT user_id FROM artists WHERE id = ? OR user_id = ? LIMIT 1");
            $userCheckStmt->execute([$artist_id, $artist_id]);
            $userCheck = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
            if ($userCheck && !empty($userCheck['user_id'])) {
                $actual_user_id = $userCheck['user_id'];
            }
        }
        
        if ($artist_id || $actual_user_id) {
            // Only get albums where the artist is the creator (uploaded songs), NOT just a collaborator
            // An album belongs to the artist who uploaded at least one song in that album
            $songsStmt = $conn->prepare("
                SELECT DISTINCT s.album_id
                FROM songs s
                WHERE s.uploaded_by = ?
                AND s.album_id IS NOT NULL
            ");
            $songsStmt->execute([$actual_user_id]);
            $albumIds = $songsStmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // Only get albums where the artist is the creator (uploaded songs), NOT just a collaborator
            $songsStmt = $conn->prepare("
                SELECT DISTINCT s.album_id
                FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                WHERE LOWER(u.username) = LOWER(?)
                AND s.album_id IS NOT NULL
            ");
            $songsStmt->execute([$artist_name]);
            $albumIds = $songsStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        if (!empty($albumIds)) {
            $albumIds = array_filter(array_unique($albumIds));
            $placeholders = implode(',', array_fill(0, count($albumIds), '?'));
            // Need to determine user_id for the WHERE clause
            $check_user_id = $actual_user_id;
            if (empty($check_user_id) && !empty($artist_name)) {
                $userCheckStmt = $conn->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
                $userCheckStmt->execute([$artist_name]);
                $userCheck = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                $check_user_id = $userCheck['id'] ?? null;
            }
            
            if ($check_user_id) {
                // Only get albums where artist uploaded at least one song (album creator)
                $albumsFromSongsStmt = $conn->prepare("
                    SELECT DISTINCT a.*, 
                           COUNT(DISTINCT s.id) as song_count,
                           SUM(DISTINCT COALESCE(s.plays, 0)) as total_plays,
                           SUM(DISTINCT COALESCE(s.downloads, 0)) as total_downloads
                    FROM albums a
                    INNER JOIN songs s ON a.id = s.album_id AND s.uploaded_by = ?
                    WHERE a.id IN ($placeholders)
                    GROUP BY a.id
                    ORDER BY a.release_date DESC, a.created_at DESC
                ");
                $params = array_merge([$check_user_id], $albumIds);
                $albumsFromSongsStmt->execute($params);
                $artist_albums = $albumsFromSongsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Error in artist-albums.php: " . $e->getMessage());
    echo '<p style="text-align: center; color: #999; padding: 40px;">Error loading albums. Please try again.</p>';
    exit;
} catch (Error $e) {
    error_log("Fatal error in artist-albums.php: " . $e->getMessage());
    echo '<p style="text-align: center; color: #999; padding: 40px;">Error loading albums. Please try again.</p>';
    exit;
}
?>

<div class="playlists-section">
    <h2 class="section-title">Albums</h2>
    <?php if (!empty($artist_albums)): ?>
        <div class="playlist-grid" style="margin-top: 20px;">
            <?php foreach ($artist_albums as $album): ?>
                <div class="playlist-card" style="cursor: pointer;" onclick="window.location.href='album-details.php?id=<?php echo $album['id']; ?>'">
                    <div class="playlist-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: 100%; aspect-ratio: 1; border-radius: 15px; margin-bottom: 15px;">
                        <?php if (!empty($album['cover_art'])): ?>
                            <img src="<?php echo htmlspecialchars($album['cover_art']); ?>" alt="<?php echo htmlspecialchars($album['title']); ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover; border-radius: 15px;">
                        <?php else: ?>
                            <i class="fas fa-compact-disc" style="font-size: 48px;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="playlist-title"><?php echo htmlspecialchars($album['title']); ?></div>
                    <div class="playlist-description">
                        <?php echo (int)($album['song_count'] ?? 0); ?> songs
                        <?php if (!empty($album['release_date'])): ?>
                            • <?php echo date('Y', strtotime($album['release_date'])); ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($album['total_plays'])): ?>
                        <div style="font-size: 11px; color: #999; margin-top: 5px;">
                            <i class="fas fa-play" style="margin-right: 3px;"></i>
                            <?php echo number_format((int)$album['total_plays']); ?> plays
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: #999; padding: 40px;">No albums available yet.</p>
    <?php endif; ?>
</div>

