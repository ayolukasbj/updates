<?php
// ajax/artist-songs.php - Songs tab content for artist profile
// Disable error display for AJAX calls
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Only set headers if not already sent (for included files)
if (!headers_sent()) {
    // Set proper headers - CORS for IP/ngrok compatibility
    header('Content-Type: text/html; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Handle preflight (only if standalone request)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
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
$artist_name = isset($_GET['artist_name']) ? trim($_GET['artist_name']) : (isset($tab_artist_name) ? trim($tab_artist_name) : '');
$artist_id = isset($_GET['artist_id']) ? (int)$_GET['artist_id'] : (isset($tab_artist_id) ? (int)$tab_artist_id : 0);

// Debug: Log what we received
error_log("artist-songs.php - artist_name: " . ($artist_name ?? 'empty') . ", artist_id: " . ($artist_id ?? 0) . ", tab_artist_name: " . (isset($tab_artist_name) ? $tab_artist_name : 'not set'));

// Force immediate output
echo '<!-- DEBUG: artist-songs.php loaded, artist_name=' . htmlspecialchars($artist_name ?? 'empty') . ', artist_id=' . ($artist_id ?? 0) . ' -->';


// Initialize variables
$artist_songs = [];
$total_songs = 0;
$show_error = false;
$error_message = '';

if (empty($artist_name) && empty($artist_id)) {
    // Show error message but continue to render the section below
    $show_error = true;
    $error_message = 'No artist specified.';
}

if (!$show_error) {
    try {
        if (!class_exists('Database')) {
            $show_error = true;
            $error_message = 'Database class not found.';
        } else {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Initialize artist_songs array
            $artist_songs = [];
            
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
                           COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name,
                           CASE WHEN EXISTS (
                               SELECT 1 FROM song_collaborators sc2 WHERE sc2.song_id = s.id
                           ) THEN 1 ELSE 0 END as is_collaboration
                    FROM songs s
                    LEFT JOIN users u ON s.uploaded_by = u.id
                    LEFT JOIN song_collaborators sc ON sc.song_id = s.id
                    WHERE s.uploaded_by = ? OR sc.user_id = ?
                    ORDER BY s.plays DESC, s.downloads DESC
                ");
                $stmt->execute([$actual_user_id, $actual_user_id]);
                $artist_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Fetch collaborators for each song
                foreach ($artist_songs as &$song) {
                    $collabStmt = $conn->prepare("
                        SELECT DISTINCT u.id, u.username
                        FROM song_collaborators sc
                        JOIN users u ON sc.user_id = u.id
                        WHERE sc.song_id = ?
                    ");
                    $collabStmt->execute([$song['id']]);
                    $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Also get uploader
                    $uploaderStmt = $conn->prepare("
                        SELECT id, username
                        FROM users WHERE id = ?
                    ");
                    $uploaderStmt->execute([$song['uploaded_by']]);
                    $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $all_artists = [];
                    if ($uploader) {
                        $all_artists[] = $uploader;
                    }
                    foreach ($collaborators as $collab) {
                        if ($collab['id'] != $song['uploaded_by']) {
                            $all_artists[] = $collab;
                        }
                    }
                    $song['collaboration_artists'] = $all_artists;
                }
                unset($song);
            } else {
                $stmt = $conn->prepare("
                    SELECT DISTINCT s.*, 
                           COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name,
                           CASE WHEN EXISTS (
                               SELECT 1 FROM song_collaborators sc2 WHERE sc2.song_id = s.id
                           ) THEN 1 ELSE 0 END as is_collaboration
                    FROM songs s
                    LEFT JOIN users u ON s.uploaded_by = u.id
                    LEFT JOIN song_collaborators sc ON sc.song_id = s.id
                    WHERE LOWER(u.username) = LOWER(?)
                    ORDER BY s.plays DESC, s.downloads DESC
                ");
                $stmt->execute([$artist_name]);
                $artist_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Fetch collaborators for each song
                foreach ($artist_songs as &$song) {
                    $collabStmt = $conn->prepare("
                        SELECT DISTINCT u.id, u.username
                        FROM song_collaborators sc
                        JOIN users u ON sc.user_id = u.id
                        WHERE sc.song_id = ?
                    ");
                    $collabStmt->execute([$song['id']]);
                    $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Also get uploader
                    $uploaderStmt = $conn->prepare("
                        SELECT id, username
                        FROM users WHERE id = ?
                    ");
                    $uploaderStmt->execute([$song['uploaded_by']]);
                    $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $all_artists = [];
                    if ($uploader) {
                        $all_artists[] = $uploader;
                    }
                    foreach ($collaborators as $collab) {
                        if ($collab['id'] != $song['uploaded_by']) {
                            $all_artists[] = $collab;
                        }
                    }
                    $song['collaboration_artists'] = $all_artists;
                }
                unset($song);
            }
            
            // Get total songs count
            $total_songs = count($artist_songs);
            
            // Debug: Log songs count
            error_log("artist-songs.php - Found " . $total_songs . " songs for artist_name=" . ($artist_name ?? 'empty') . ", artist_id=" . ($artist_id ?? 0));
        }
    }
    catch (Exception $e) {
        error_log("Error in artist-songs.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $show_error = true;
        $error_message = 'Error loading songs: ' . htmlspecialchars($e->getMessage());
        // Set empty array to prevent undefined variable errors
        $artist_songs = [];
        $total_songs = 0;
    } catch (Error $e) {
        error_log("Fatal error in artist-songs.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $show_error = true;
        $error_message = 'Fatal error loading songs: ' . htmlspecialchars($e->getMessage());
        // Set empty array to prevent undefined variable errors
        $artist_songs = [];
        $total_songs = 0;
    }
}
?>

<div class="songs-section" style="display: block !important; visibility: visible !important; opacity: 1 !important; width: 100% !important; height: auto !important; padding: 20px; background: rgba(255,255,255,0.1); border-radius: 10px;">
    <h2 class="section-title" style="display: block !important; visibility: visible !important; color: #fff; font-size: 24px; margin-bottom: 20px;">All Songs</h2>
    
    <?php if ($show_error): ?>
    <p style="text-align: center; color: #f00; padding: 40px; font-size: 14px; background: #fff; border: 2px solid #f00;">
        <?php echo htmlspecialchars($error_message); ?>
        <?php if (empty($artist_name) && empty($artist_id)): ?>
            <br><small style="color: #999;">Debug: tab_artist_name=<?php echo isset($tab_artist_name) ? htmlspecialchars($tab_artist_name) : 'not set'; ?>, tab_artist_id=<?php echo isset($tab_artist_id) ? $tab_artist_id : 'not set'; ?>, artist_name=<?php echo htmlspecialchars($artist_name ?? 'empty'); ?>, artist_id=<?php echo $artist_id ?? 0; ?></small>
        <?php endif; ?>
    </p>
    <?php elseif (!empty($artist_songs)): ?>
    <div class="songs-grid">
        <?php foreach ($artist_songs as $index => $song): 
            $cover_art = !empty($song['cover_art']) ? $song['cover_art'] : 'assets/images/default-avatar.svg';
            $main_artist = '';
            $featured_artist = '';
            
            // Get collaborators from song_collaborators table
            if (!empty($song['collaboration_artists']) && count($song['collaboration_artists']) > 0) {
                $uploader_name = '';
                $collab_names = [];
                
                foreach ($song['collaboration_artists'] as $artist_info) {
                    $name = trim($artist_info['username']);
                    if ($artist_info['id'] == $song['uploaded_by']) {
                        $uploader_name = $name;
                    } else {
                        $collab_names[] = $name;
                    }
                }
                
                // If uploader not found in collaborators, get from artist column
                if (empty($uploader_name) && !empty($song['uploaded_by'])) {
                    $uploader_name = $song['artist'] ?? $song['artist_name'] ?? 'Unknown Artist';
                }
                
                $main_artist = !empty($uploader_name) ? $uploader_name : ($song['artist'] ?? $song['artist_name'] ?? 'Unknown Artist');
                
                // Build featured artist string
                if (!empty($collab_names)) {
                    $featured_artist = 'ft ' . implode(' x ', array_unique($collab_names));
                }
            } else {
                // Single artist song
                $main_artist = $song['artist'] ?? $song['artist_name'] ?? 'Unknown Artist';
            }
            
            // Generate song slug for URL
            $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
            $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
            $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $main_artist));
            $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
            $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
        ?>
            <div class="song-card" data-song-id="<?php echo $song['id']; ?>" data-song-title="<?php echo htmlspecialchars($song['title']); ?>" data-song-artist="<?php echo htmlspecialchars($song['artist'] ?? $song['artist_name'] ?? ''); ?>" data-song-cover="<?php echo htmlspecialchars($song['cover_art'] ?? ''); ?>" onclick="window.location.href='/song/<?php echo urlencode($songSlug); ?>'">
                <div class="song-card-image">
                    <img src="<?php echo htmlspecialchars($cover_art); ?>" alt="<?php echo htmlspecialchars($song['title']); ?>" onerror="this.src='assets/images/default-avatar.svg'">
                    <button class="song-card-play-btn" onclick="event.stopPropagation(); playSongCard(this)">
                        <i class="fas fa-play"></i>
                    </button>
                </div>
                <div class="song-card-info">
                    <div class="song-card-title"><?php echo htmlspecialchars($song['title']); ?></div>
                    <div class="song-card-artist">
                        <?php echo htmlspecialchars($main_artist); ?>
                        <?php if (!empty($featured_artist)): ?>
                            <div class="song-card-featured"><?php echo htmlspecialchars($featured_artist); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="text-align: center; color: #999; padding: 40px;">No songs available yet.</p>
    <?php endif; ?>
</div>

<script>
function playSongCard(button) {
    const songCard = button.closest('.song-card');
    const songId = songCard.dataset.songId;
    const songTitle = songCard.dataset.songTitle;
    const songArtist = songCard.dataset.songArtist;
    const songCover = songCard.dataset.songCover;
    
    // Use absolute URL for IP/ngrok compatibility
    let basePath = window.location.pathname;
    if (basePath.endsWith('.php') || basePath.split('/').pop().includes('.')) {
        basePath = basePath.substring(0, basePath.lastIndexOf('/') + 1);
    } else if (!basePath.endsWith('/')) {
        basePath += '/';
    }
    const apiBaseUrl = window.location.origin + basePath;
    fetch(`${apiBaseUrl}api/song-data.php?id=${songId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success || data.id) {
                if (window.miniPlayer) {
                    window.miniPlayer.playSong(data);
                } else if (window.player) {
                    window.player.loadSong(data);
                    window.player.play();
                }
            }
        })
        .catch(error => console.error('Error playing song:', error));
}
</script>

