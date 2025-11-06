<?php
// ajax/artist-lyrics.php - Lyrics tab content for artist profile
// This file fetches lyrics that were filled when songs were uploaded
// Disable error display for AJAX calls
error_reporting(E_ALL);
ini_set('display_errors', 0);
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
    
    // Get artist songs with lyrics (lyrics filled when song was uploaded)
    $artist_lyrics = [];
    if ($artist_id) {
        // Check if artist_id is from artists table or users table
        $checkArtistStmt = $conn->prepare("SELECT id, user_id FROM artists WHERE id = ? OR user_id = ? LIMIT 1");
        $checkArtistStmt->execute([$artist_id, $artist_id]);
        $artistRecord = $checkArtistStmt->fetch(PDO::FETCH_ASSOC);
        
        // Determine actual user_id to use
        $actual_user_id = $artist_id;
        if ($artistRecord && !empty($artistRecord['user_id'])) {
            $actual_user_id = $artistRecord['user_id'];
        }
        
        $stmt = $conn->prepare("
            SELECT DISTINCT s.*, 
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            LEFT JOIN song_collaborators sc ON sc.song_id = s.id
            WHERE (s.uploaded_by = ? OR sc.user_id = ?)
            AND s.lyrics IS NOT NULL 
            AND s.lyrics != ''
            AND TRIM(s.lyrics) != ''
            ORDER BY s.upload_date DESC, s.plays DESC
        ");
        $stmt->execute([$actual_user_id, $actual_user_id]);
        $artist_lyrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare("
            SELECT DISTINCT s.*, 
                   COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            LEFT JOIN song_collaborators sc ON sc.song_id = s.id
            WHERE LOWER(u.username) = LOWER(?)
            AND s.lyrics IS NOT NULL 
            AND s.lyrics != ''
            AND TRIM(s.lyrics) != ''
            ORDER BY s.upload_date DESC, s.plays DESC
        ");
        $stmt->execute([$artist_name]);
        $artist_lyrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Error in artist-lyrics.php: " . $e->getMessage());
    echo '<p style="text-align: center; color: #999; padding: 40px;">Error loading lyrics. Please try again.</p>';
    exit;
} catch (Error $e) {
    error_log("Fatal error in artist-lyrics.php: " . $e->getMessage());
    echo '<p style="text-align: center; color: #999; padding: 40px;">Error loading lyrics. Please try again.</p>';
    exit;
}
?>

<div class="playlists-section">
    <h2 class="section-title">Lyrics</h2>
    <?php if (!empty($artist_lyrics)): ?>
        <div style="margin-top: 20px;">
            <?php foreach ($artist_lyrics as $song): 
                $mainArtist = $song['artist'] ?? $song['artist_name'] ?? 'Unknown Artist';
                $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
                $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $mainArtist));
                $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
            ?>
                <div style="background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #667eea;">
                    <h3 style="color: #333; font-size: 18px; font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-music" style="color: #667eea;"></i>
                        <?php echo htmlspecialchars($song['title']); ?>
                    </h3>
                    <div style="color: #666; line-height: 1.8; white-space: pre-wrap; font-size: 14px; max-height: 200px; overflow-y: auto; padding: 15px; background: white; border-radius: 8px;">
                        <?php echo nl2br(htmlspecialchars($song['lyrics'])); ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <a href="/song/<?php echo urlencode($songSlug); ?>" style="color: #667eea; text-decoration: none; font-weight: 500; font-size: 14px;">
                            View Full Song <i class="fas fa-arrow-right" style="margin-left: 5px;"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: #999; padding: 40px;">No lyrics available yet.</p>
    <?php endif; ?>
</div>

