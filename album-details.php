<?php
// album-details.php - Album details page
// Error reporting - only log errors, don't display in production
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Album.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load theme settings
if (file_exists(__DIR__ . '/includes/theme-loader.php')) {
    require_once __DIR__ . '/includes/theme-loader.php';
}

// Check if user is logged in
$isLoggedIn = function_exists('is_logged_in') ? is_logged_in() : false;
$current_user_id = function_exists('get_user_id') ? get_user_id() : null;

// Check if user is admin or artist (for reordering songs)
$can_reorder = false;
if ($isLoggedIn && $current_user_id) {
    try {
        $db_temp = new Database();
        $conn_temp = $db_temp->getConnection();
        
        // Check if user is admin
        try {
            $roleStmt = $conn_temp->query("SHOW COLUMNS FROM users LIKE 'role'");
            $roleExists = $roleStmt->rowCount() > 0;
            
            if ($roleExists) {
                $userStmt = $conn_temp->prepare("SELECT role FROM users WHERE id = ?");
                $userStmt->execute([$current_user_id]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && in_array($user['role'], ['admin', 'super_admin'])) {
                    $can_reorder = true;
                }
            }
        } catch (Exception $e) {
            // Ignore
        }
        
        // Check if user uploaded any songs in this album (will check later after album is loaded)
        
    } catch (Exception $e) {
        // Ignore
    }
}

// Get album ID from URL
$albumId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (empty($albumId)) {
    header('Location: index.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $album_model = new Album($conn);
    $album = $album_model->getAlbumById($albumId);
    
    if (!$album) {
        header('Location: index.php');
        exit;
    }
    
    // Get album songs
    $album_songs = $album_model->getAlbumSongs($albumId);
    
    // Check if current user can reorder (if they uploaded any songs in this album)
    if ($isLoggedIn && $current_user_id && !$can_reorder) {
        foreach ($album_songs as $song) {
            if (isset($song['uploaded_by']) && (int)$song['uploaded_by'] === $current_user_id) {
                $can_reorder = true;
                break;
            }
        }
    }
    
    // Calculate total duration
    $total_duration = 0;
    foreach ($album_songs as $song) {
        $duration_val = $song['duration'] ?? 0;
        // Handle duration - might be string or int
        if (is_string($duration_val) && strpos($duration_val, ':') !== false) {
            $parts = explode(':', $duration_val);
            if (count($parts) === 2) {
                $duration_val = (int)$parts[0] * 60 + (int)$parts[1];
            } elseif (count($parts) === 3) {
                $duration_val = (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
            } else {
                $duration_val = (int)$duration_val;
            }
        } else {
            $duration_val = (int)$duration_val;
        }
        $total_duration += $duration_val;
    }
    
    $hours = floor($total_duration / 3600);
    $minutes = floor(($total_duration % 3600) / 60);
    $seconds = $total_duration % 60;
    $duration_text = '';
    if ($hours > 0) {
        $duration_text = $hours . 'h ' . $minutes . 'm';
    } else {
        $duration_text = $minutes . 'm ' . $seconds . 's';
    }
    
} catch (Exception $e) {
    error_log("Error in album-details.php: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Ensure BASE_PATH is defined
if (!defined('BASE_PATH')) {
    // Try to get from config.php
    if (file_exists('config/config.php')) {
        require_once 'config/config.php';
    } else {
        define('BASE_PATH', '/music/');
    }
}

// Get base URL for audio files (same as song-details.php)
$protocol = 'http://';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $protocol = 'https://';
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $protocol = 'https://';
} elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
    $protocol = 'https://';
} elseif (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
    $protocol = 'https://';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . $host . BASE_PATH;

// Define asset_path function if not exists (same as song-details.php)
if (!function_exists('asset_path')) {
    function asset_path($path) {
        if (empty($path)) return '';
        if (strpos($path, 'http') === 0) return $path;
        global $baseUrl;
        return $baseUrl . ltrim($path, '/');
    }
}

include 'includes/header.php';
?>
<!-- Add viewport meta if not already present -->
<script>
if (typeof document !== 'undefined') {
    if (!document.querySelector('meta[name="viewport"]')) {
        var meta = document.createElement('meta');
        meta.name = 'viewport';
        meta.content = 'width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes';
        document.getElementsByTagName('head')[0].appendChild(meta);
    }
}
</script>
<style>
    /* Ensure viewport meta tag is present - add to head if missing */
    @supports not (display: grid) {
        /* Fallback for older browsers */
    }
    
    * {
        box-sizing: border-box;
    }
    
    html, body {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: hidden !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    body {
        position: relative !important;
    }
    
    .album-details-page {
        max-width: 1400px;
        width: 100%;
        margin: 0 auto;
        padding: 20px;
        padding-bottom: 40px;
        box-sizing: border-box;
    }
    
    .album-header {
        display: flex;
        gap: 40px;
        margin-bottom: 40px;
        flex-wrap: wrap;
    }
    
    .album-cover {
        flex: 0 0 300px;
        max-width: 300px;
        aspect-ratio: 1;
        border-radius: 15px;
        overflow: hidden;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    
    .album-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .album-cover i {
        font-size: 120px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
    }
    
    .album-info {
        flex: 1;
        min-width: 300px;
    }
    
    .album-type {
        text-transform: uppercase;
        font-size: 12px;
        font-weight: 600;
        color: #ec4899;
        letter-spacing: 1px;
        margin-bottom: 10px;
    }
    
    .album-title {
        font-size: 48px;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 15px;
        line-height: 1.2;
    }
    
    .album-artist {
        font-size: 20px;
        color: #666;
        margin-bottom: 20px;
    }
    
    .album-artist a {
        color: #ec4899;
        text-decoration: none;
        font-weight: 600;
    }
    
    .album-artist a:hover {
        text-decoration: underline;
    }
    
    .album-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 20px;
        font-size: 14px;
        color: #666;
    }
    
    .album-meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .album-description {
        color: #666;
        line-height: 1.6;
        margin-top: 20px;
    }
    
    .album-songs-section {
        margin-top: 40px;
        margin-left: -20px;
        margin-right: -20px;
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .section-title {
        font-size: 28px;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 20px;
    }
    
    .songs-list {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding-left: 0;
    }
    
    .song-item {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 15px 20px 15px 0;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }
    
    .song-item.sortable-item {
        cursor: move;
    }
    
    .song-item.sortable-item:hover {
        background: #f9f9f9;
    }
    
    .song-item.ui-sortable-helper {
        background: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: 1px solid #ddd;
    }
    
    .drag-handle {
        cursor: grab;
        color: #999;
    }
    
    .drag-handle:active {
        cursor: grabbing;
    }
    
    .song-item:hover {
        background: #f9f9f9;
    }
    
    .song-item:last-child {
        border-bottom: none;
    }
    
    .song-number {
        font-size: 16px;
        font-weight: 600;
        color: #999;
        width: 30px;
        text-align: left;
        flex-shrink: 0;
        margin-left: 0;
        padding-left: 0;
        margin-right: 10px;
    }
    
    .song-cover-small {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        overflow: hidden;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        flex-shrink: 0;
        margin-left: 0;
    }
    
    .song-cover-small img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .song-cover-small i {
        font-size: 24px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
    }
    
    .song-details {
        flex: 1;
        min-width: 0;
        margin-right: auto;
    }
    
    .song-title {
        font-size: 16px;
        font-weight: 600;
        color: #1a1a1a;
        margin-bottom: 5px;
    }
    
    .song-artist {
        font-size: 14px;
        color: #666;
    }
    
    .song-duration {
        font-size: 14px;
        color: #999;
        margin-left: auto;
        white-space: nowrap;
        flex-shrink: 0;
    }
    
    
    @media (max-width: 768px) {
        .album-details-page {
            max-width: 100% !important;
            width: 100% !important;
            padding: 10px !important;
            padding-bottom: 40px !important;
            margin: 0 !important;
        }
        
        .album-songs-section {
            margin-left: -10px !important;
            margin-right: -10px !important;
            padding-left: 10px !important;
            padding-right: 10px !important;
        }
        
        .album-header {
            flex-direction: column !important;
            gap: 20px !important;
            align-items: center !important;
            margin-bottom: 30px !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        
        .album-cover {
            width: 200px !important;
            max-width: 200px !important;
            min-width: 200px !important;
            flex: 0 0 200px !important;
            margin: 0 auto !important;
            aspect-ratio: 1 !important;
        }
        
        .album-info {
            min-width: 100% !important;
            width: 100% !important;
            max-width: 100% !important;
            text-align: center !important;
            padding: 0 !important;
        }
        
        .album-title {
            font-size: 24px !important;
            line-height: 1.3 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            margin-bottom: 10px !important;
        }
        
        .album-artist {
            font-size: 16px !important;
            margin-bottom: 15px !important;
        }
        
        .album-meta {
            flex-direction: column !important;
            gap: 10px !important;
            justify-content: center !important;
            align-items: center !important;
            font-size: 13px !important;
        }
        
        .album-meta-item {
            justify-content: center !important;
        }
        
        .album-description {
            font-size: 14px !important;
            text-align: center !important;
            margin-top: 15px !important;
        }
        
        .section-title {
            font-size: 22px !important;
            margin-bottom: 15px !important;
            text-align: center !important;
        }
        
        .songs-list {
            background: white !important;
            border-radius: 10px !important;
            overflow: hidden !important;
        }
        
        .song-item {
            padding: 15px 20px 15px 0 !important;
            flex-wrap: nowrap !important;
            gap: 2px !important;
            border-bottom: 1px solid #f0f0f0 !important;
        }
        
        .song-number {
            width: 30px !important;
            font-size: 16px !important;
            flex-shrink: 0 !important;
        }
        
        .song-cover-small {
            width: 50px !important;
            height: 50px !important;
            flex-shrink: 0 !important;
        }
        
        .song-details {
            flex: 1 !important;
            min-width: 0 !important;
            overflow: hidden !important;
            margin-right: auto !important;
        }
        
        .song-title {
            font-size: 16px !important;
            margin-bottom: 5px !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        
        .song-artist {
            font-size: 14px !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        
        .song-duration {
            margin-left: auto !important;
            font-size: 14px !important;
            flex-shrink: 0 !important;
            white-space: nowrap !important;
        }
    }
    
    @media (max-width: 480px) {
        .album-title {
            font-size: 24px;
        }
        
        .album-songs-section {
            margin-left: -10px !important;
            margin-right: -10px !important;
            padding-left: 0 !important;
            padding-right: 10px !important;
        }
        
        .song-item {
            padding: 8px 20px 8px 0 !important;
        }
        
        .songs-list {
            padding-left: 0 !important;
            margin-left: 0 !important;
        }
        
        .song-number {
            width: 25px !important;
            font-size: 14px !important;
            flex-shrink: 0 !important;
            margin-left: 0 !important;
            padding-left: 0 !important;
            text-align: left !important;
            margin-right: 8px !important;
        }
        
        .song-cover-small {
            margin-left: 0 !important;
        }
    }
</style>

<div class="album-details-page">
    <div class="album-header">
        <div class="album-cover">
            <?php if (!empty($album['cover_art'])): ?>
                <img src="<?php echo htmlspecialchars($album['cover_art']); ?>" alt="<?php echo htmlspecialchars($album['title']); ?>">
            <?php else: ?>
                <i class="fas fa-compact-disc"></i>
            <?php endif; ?>
        </div>
        
        <div class="album-info">
            <div class="album-type">Album</div>
            <h1 class="album-title"><?php echo htmlspecialchars($album['title']); ?></h1>
            
            <?php if (!empty($album['artist_name'])): ?>
                <div class="album-artist">
                    By <a href="artist-profile.php?id=<?php echo urlencode($album['artist_id'] ?? ''); ?>">
                        <?php echo htmlspecialchars($album['artist_name']); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="album-meta">
                <div class="album-meta-item">
                    <i class="fas fa-music"></i>
                    <span><?php echo count($album_songs); ?> songs</span>
                </div>
                <div class="album-meta-item" id="total-duration-display">
                    <i class="fas fa-clock"></i>
                    <span><?php echo htmlspecialchars($duration_text); ?></span>
                </div>
                <?php if (!empty($album['release_date'])): ?>
                    <div class="album-meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('Y', strtotime($album['release_date'])); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($album['genre_name'])): ?>
                    <div class="album-meta-item">
                        <i class="fas fa-tag"></i>
                        <span><?php echo htmlspecialchars($album['genre_name']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($album['description'])): ?>
                <div class="album-description">
                    <?php echo nl2br(htmlspecialchars($album['description'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="album-songs-section">
        <h2 class="section-title">Songs in this album</h2>
        
        
        <?php if (!empty($album_songs)): ?>
            <div class="songs-list" <?php echo $can_reorder ? 'id="sortable-songs-list"' : ''; ?>>
                <?php foreach ($album_songs as $index => $song): ?>
                    <?php
                    // Format duration - duration should be stored in seconds in database
                    // But it might be stored as string "MM:SS" or as integer seconds
                    $song_duration = 0;
                    $duration_value = isset($song['duration']) ? $song['duration'] : null;
                    
                    // Debug: log the raw value
                    error_log("DEBUG Album {$albumId} Song {$song['id']} '{$song['title']}': Raw duration value = " . var_export($duration_value, true) . " Type: " . gettype($duration_value));
                    
                    if ($duration_value !== null && $duration_value !== '') {
                        // Check if duration is a string like "3:45" or "3:45:30"
                        if (is_string($duration_value) && strpos($duration_value, ':') !== false) {
                            // Parse string format like "3:45" or "3:45:30"
                            $parts = explode(':', trim($duration_value));
                            if (count($parts) === 2) {
                                // Format: MM:SS - convert to seconds
                                $song_duration = (int)$parts[0] * 60 + (int)$parts[1];
                            } elseif (count($parts) === 3) {
                                // Format: HH:MM:SS - convert to seconds
                                $song_duration = (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
                            } else {
                                // Try to convert to int
                                $song_duration = (int)$duration_value;
                            }
                        } elseif (is_numeric($duration_value)) {
                            // It's already a number (seconds)
                            $song_duration = (int)$duration_value;
                        } else {
                            // Try to parse as string number
                            $song_duration = (int)$duration_value;
                        }
                    }
                    
                    // Ensure duration is in seconds (not milliseconds)
                    // If duration is very large (> 10000), it's probably in milliseconds
                    if ($song_duration > 10000 && $song_duration < 1000000) {
                        $song_duration = floor($song_duration / 1000);
                    }
                    
                    // Format as MM:SS (always show duration, even if 0:00)
                    $song_minutes = floor($song_duration / 60);
                    $song_seconds = $song_duration % 60;
                    $song_duration_text = sprintf('%d:%02d', $song_minutes, $song_seconds);
                    
                    // Debug: log final calculated duration
                    error_log("DEBUG Album {$albumId} Song {$song['id']} '{$song['title']}': Calculated duration = {$song_duration} seconds = {$song_duration_text}");
                    
                    // Generate song slug for URL
                    $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
                    $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                    
                    // Get artist name for song - include collaborators
                    $song_artist_name = 'Unknown Artist';
                    if (!empty($song['artist'])) {
                        $song_artist_name = $song['artist'];
                    } elseif (!empty($song['uploader_username'])) {
                        $song_artist_name = $song['uploader_username'];
                    }
                    
                    // Add collaborators if they exist
                    $collaborators = $song['collaborators'] ?? [];
                    if (!empty($collaborators)) {
                        $collab_names = array_map(function($c) {
                            return $c['username'] ?? '';
                        }, array_filter($collaborators, function($c) {
                            return !empty($c['username']);
                        }));
                        if (!empty($collab_names)) {
                            $song_artist_name .= ' ft. ' . implode(', ', $collab_names);
                        }
                    }
                    
                    $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song_artist_name));
                    $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                    $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                    ?>
                    <div class="song-item<?php echo $can_reorder ? ' sortable-item' : ''; ?>" data-song-id="<?php echo $song['id']; ?>" data-song-index="<?php echo $index; ?>">
                        <?php if ($can_reorder): ?>
                            <div class="drag-handle" style="cursor: move; padding: 0 10px; color: #999;">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                        <?php endif; ?>
                        <div class="song-number"><?php echo $index + 1; ?></div>
                        <div class="song-cover-small">
                            <?php if (!empty($song['cover_art'])): ?>
                                <img src="<?php echo htmlspecialchars($song['cover_art']); ?>" alt="<?php echo htmlspecialchars($song['title']); ?>">
                            <?php else: ?>
                                <i class="fas fa-music"></i>
                            <?php endif; ?>
                        </div>
                        <div class="song-details">
                            <div class="song-title">
                                <a href="/song/<?php echo urlencode($songSlug); ?>" style="text-decoration: none; color: inherit;">
                                    <?php echo htmlspecialchars($song['title']); ?>
                                </a>
                            </div>
                            <div class="song-artist"><?php echo htmlspecialchars($song_artist_name); ?></div>
                        </div>
                        <div class="song-duration" data-song-id="<?php echo $song['id']; ?>" data-song-url="<?php echo htmlspecialchars($song['file_path'] ?? ''); ?>">
                            <?php echo $song_duration_text; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #999; padding: 40px;">No songs in this album yet.</p>
        <?php endif; ?>
    </div>
    
    <!-- Contributors to the Album Section -->
    <?php
    // Get all contributors to the album (artists who are in at least one song in the album)
    // Use the exact same strategy as song-details.php: query song_collaborators directly
    try {
        $all_contributors = []; // Similar to all_artists in song-details.php
        $contributor_ids = []; // Track unique contributor IDs to avoid duplicates
        
        // Check for verified column
        $colCheck = $conn->query("SHOW COLUMNS FROM users");
        $columns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
        $verifiedCol = '0 as is_verified';
        if (in_array('is_verified', $columns)) {
            $verifiedCol = 'u.is_verified';
        } else if (in_array('email_verified', $columns)) {
            $verifiedCol = 'u.email_verified as is_verified';
        }
        
        // First, get all uploaders from songs in this album
        foreach ($album_songs as $song) {
            if (!empty($song['uploaded_by'])) {
                $uploader_id = (int)$song['uploaded_by'];
                
                // Skip if already added
                if (!in_array($uploader_id, $contributor_ids)) {
                    $contributor_ids[] = $uploader_id;
                    
                    // Get uploader stats - same query as song-details.php
                    try {
                        $userStmt = $conn->prepare("
                            SELECT u.*,
                                   u.username as artist_name,
                                   $verifiedCol,
                                   COALESCE((
                                       SELECT COUNT(DISTINCT s.id)
                                       FROM songs s
                                       WHERE s.uploaded_by = u.id
                                          OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                                   ), 0) as total_songs,
                                   COALESCE((
                                       SELECT SUM(s.plays)
                                       FROM songs s
                                       WHERE s.uploaded_by = u.id
                                          OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                                   ), 0) as total_plays,
                                   COALESCE((
                                       SELECT SUM(s.downloads)
                                       FROM songs s
                                       WHERE s.uploaded_by = u.id
                                          OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                                   ), 0) as total_downloads
                            FROM users u
                            WHERE u.id = ?
                        ");
                        $userStmt->execute([$uploader_id]);
                        $user_data = $userStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user_data && !empty($user_data['username'])) {
                            $all_contributors[] = [
                                'id' => (int)$user_data['id'],
                                'name' => ucwords(strtolower(trim($user_data['username']))),
                                'username' => $user_data['username'],
                                'avatar' => !empty($user_data['avatar']) ? trim($user_data['avatar']) : null,
                                'verified' => (int)($user_data['is_verified'] ?? ($user_data['email_verified'] ?? 0)),
                                'bio' => $user_data['bio'] ?? '',
                                'total_songs' => (int)($user_data['total_songs'] ?? 0),
                                'total_plays' => (int)($user_data['total_plays'] ?? 0),
                                'total_downloads' => (int)($user_data['total_downloads'] ?? 0)
                            ];
                        }
                    } catch (Exception $e) {
                        error_log("Error fetching uploader data for user {$uploader_id}: " . $e->getMessage());
                    }
                }
            }
        }
        
        // Then get all collaborators from song_collaborators table - same as song-details.php
        // Check if song_collaborators table exists
        try {
            $tableCheckStmt = $conn->query("SHOW TABLES LIKE 'song_collaborators'");
            $collabTableExists = $tableCheckStmt->rowCount() > 0;
            
            if ($collabTableExists) {
                // Get all collaborators from all songs in this album
                foreach ($album_songs as $song) {
                    if (!empty($song['id'])) {
                        try {
                            // Build query based on available columns - same as song-details.php
                            $selectCols = ["sc.user_id", "u.username", "u.avatar"];
                            if (in_array('is_verified', $columns)) {
                                $selectCols[] = "u.is_verified";
                            } else if (in_array('email_verified', $columns)) {
                                $selectCols[] = "u.email_verified as is_verified";
                            }
                            if (in_array('bio', $columns)) {
                                $selectCols[] = "u.bio";
                            }
                            
                            $mapStmt = $conn->prepare("
                                SELECT " . implode(', ', $selectCols) . "
                                FROM song_collaborators sc
                                LEFT JOIN users u ON u.id = sc.user_id
                                WHERE sc.song_id = ?
                                ORDER BY sc.added_at ASC
                            ");
                            $mapStmt->execute([$song['id']]);
                            $mapped_collaborators = $mapStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Process each collaborator - same logic as song-details.php
                            if (!empty($mapped_collaborators)) {
                                // Track IDs already in all_contributors (to avoid duplicates with uploader)
                                $seen_ids = array_map(function($a) { return $a['id']; }, $all_contributors);
                                
                                foreach ($mapped_collaborators as $mc) {
                                    $collab_id = !empty($mc['user_id']) ? (int)$mc['user_id'] : 0;
                                    
                                    // Skip if already added (e.g., uploader added themselves as collaborator)
                                    if ($collab_id > 0 && !in_array($collab_id, $seen_ids)) {
                                        $seen_ids[] = $collab_id;
                                        
                                        // Get full user data with stats in ONE query - same as song-details.php
                                        $collabStmt = $conn->prepare("
                                            SELECT u.*,
                                                   u.username as artist_name,
                                                   $verifiedCol,
                                                   COALESCE((
                                                       SELECT COUNT(DISTINCT s.id)
                                                       FROM songs s
                                                       WHERE s.uploaded_by = u.id
                                                          OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                                                   ), 0) as total_songs,
                                                   COALESCE((
                                                       SELECT SUM(s.plays)
                                                       FROM songs s
                                                       WHERE s.uploaded_by = u.id
                                                          OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                                                   ), 0) as total_plays,
                                                   COALESCE((
                                                       SELECT SUM(s.downloads)
                                                       FROM songs s
                                                       WHERE s.uploaded_by = u.id
                                                          OR s.id IN (SELECT song_id FROM song_collaborators WHERE user_id = u.id)
                                                   ), 0) as total_downloads
                                            FROM users u
                                            WHERE u.id = ?
                                        ");
                                        $collabStmt->execute([$collab_id]);
                                        $collab_user = $collabStmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if ($collab_user && !empty($collab_user['username'])) {
                                            $all_contributors[] = [
                                                'id' => $collab_id,
                                                'name' => ucwords(strtolower(trim($collab_user['username']))),
                                                'username' => $collab_user['username'],
                                                'avatar' => !empty($collab_user['avatar']) ? trim($collab_user['avatar']) : null,
                                                'verified' => (int)($collab_user['is_verified'] ?? ($collab_user['email_verified'] ?? 0)),
                                                'bio' => $collab_user['bio'] ?? '',
                                                'total_songs' => (int)($collab_user['total_songs'] ?? 0),
                                                'total_plays' => (int)($collab_user['total_plays'] ?? 0),
                                                'total_downloads' => (int)($collab_user['total_downloads'] ?? 0)
                                            ];
                                        }
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching collaborators for song {$song['id']}: " . $e->getMessage());
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error checking song_collaborators table: " . $e->getMessage());
        }
        
        // Remove duplicates by ID - same logic as song-details.php
        if (!empty($all_contributors)) {
            $unique_contributors = [];
            $seen_ids = [];
            $seen_names = [];
            
            foreach ($all_contributors as $contributor) {
                $contributor_id = !empty($contributor['id']) ? (int)$contributor['id'] : 0;
                $contributor_name = !empty($contributor['name']) ? strtolower(trim($contributor['name'])) : '';
                
                // Skip if already seen by ID
                if ($contributor_id > 0 && in_array($contributor_id, $seen_ids)) {
                    continue;
                }
                
                // Skip if already seen by name (for contributors without ID)
                if ($contributor_id == 0 && !empty($contributor_name) && in_array($contributor_name, $seen_names)) {
                    continue;
                }
                
                // Add to seen lists
                if ($contributor_id > 0) {
                    $seen_ids[] = $contributor_id;
                }
                if (!empty($contributor_name)) {
                    $seen_names[] = $contributor_name;
                }
                
                $unique_contributors[] = $contributor;
            }
            
            $all_contributors = $unique_contributors;
        }
        
        // Convert to format expected by display section
        $contributors = [];
        foreach ($all_contributors as $contributor) {
            $contributors[] = [
                'id' => $contributor['id'],
                'username' => $contributor['username'],
                'avatar' => $contributor['avatar'],
                'stage_name' => null,
                'artist' => null,
                'is_verified' => $contributor['verified'],
                'display_name' => $contributor['name'],
                'total_songs' => $contributor['total_songs'],
                'total_plays' => $contributor['total_plays']
            ];
        }
        
        // Sort contributors by display name
        usort($contributors, function($a, $b) {
            return strcmp(strtolower($a['display_name']), strtolower($b['display_name']));
        });
        
    } catch (Exception $e) {
        error_log("Error fetching contributors: " . $e->getMessage());
        $contributors = [];
    }
    ?>
    
    <!-- Contributors to the Album Section -->
    <div class="contributors-section" style="margin-top: 50px;">
        <h2 class="section-title">Contributors to the Album</h2>
        <?php if (!empty($contributors)): ?>
            <div class="contributors-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; margin-top: 20px;">
                <?php foreach ($contributors as $contributor): ?>
                    <?php
                    // Create artist slug for URL
                    $artistSlug = strtolower($contributor['display_name'] ?? $contributor['username']);
                    $artistSlug = preg_replace('/[^a-z0-9\s]+/', '', $artistSlug);
                    $artistSlug = preg_replace('/\s+/', '-', $artistSlug);
                    $artistSlug = trim($artistSlug, '-');
                    $artistUrl = '/artist/' . $artistSlug;
                    ?>
                    <a href="<?php echo htmlspecialchars($artistUrl); ?>" style="text-decoration: none; color: inherit;">
                        <div class="contributor-card" style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s; cursor: pointer; text-align: center; padding: 20px;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                            <div class="contributor-avatar" style="width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 15px; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                                <?php if (!empty($contributor['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($contributor['avatar']); ?>" alt="<?php echo htmlspecialchars($contributor['display_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/images/default-avatar.svg'">
                                <?php else: ?>
                                    <i class="fas fa-user" style="font-size: 60px; color: white;"></i>
                                <?php endif; ?>
                            </div>
                            <div style="font-weight: 600; font-size: 16px; margin-bottom: 5px; color: #1a1a1a;">
                                <?php echo htmlspecialchars($contributor['display_name']); ?>
                                <?php if (!empty($contributor['is_verified']) && $contributor['is_verified'] == 1): ?>
                                    <i class="fas fa-check-circle" style="color: #4CAF50; margin-left: 5px;"></i>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 13px; color: #666; margin-top: 8px;">
                                <?php echo number_format((int)($contributor['total_songs'] ?? 0)); ?> Songs<br>
                                <?php echo number_format((int)($contributor['total_plays'] ?? 0)); ?> plays
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #999; padding: 40px;">No contributors found for this album.</p>
        <?php endif; ?>
    </div>
    
    <!-- Similar Albums Section -->
    <?php
    // Get similar albums (same genre or same artist)
    try {
        $similar_albums = [];
        if (!empty($album['genre_id']) || !empty($album['artist_id'])) {
            $where_clauses = [];
            $params = [$albumId];
            
            if (!empty($album['genre_id'])) {
                $where_clauses[] = "a.genre_id = ?";
                $params[] = $album['genre_id'];
            }
            if (!empty($album['artist_id'])) {
                $where_clauses[] = "a.artist_id = ?";
                $params[] = $album['artist_id'];
            }
            
            if (!empty($where_clauses)) {
                $similarStmt = $conn->prepare("
                    SELECT a.*, ar.name as artist_name, g.name as genre_name
                    FROM albums a
                    LEFT JOIN artists ar ON a.artist_id = ar.id
                    LEFT JOIN genres g ON a.genre_id = g.id
                    WHERE a.id != ? 
                    AND (" . implode(' OR ', $where_clauses) . ")
                    AND a.total_tracks > 0
                    ORDER BY a.total_plays DESC
                    LIMIT 6
                ");
                
                $similarStmt->execute($params);
                $similar_albums = $similarStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching similar albums: " . $e->getMessage());
        $similar_albums = [];
    }
    ?>
    
    <!-- Similar Albums Section -->
    <div class="similar-albums-section" style="margin-top: 50px;">
        <h2 class="section-title">Similar Albums</h2>
        <?php if (!empty($similar_albums)): ?>
            <div class="albums-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                <?php foreach ($similar_albums as $similar_album): ?>
                    <a href="album-details.php?id=<?php echo $similar_album['id']; ?>" style="text-decoration: none; color: inherit;">
                        <div class="album-card" style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                            <div class="album-cover" style="width: 100%; aspect-ratio: 1; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); overflow: hidden;">
                                <?php if (!empty($similar_album['cover_art'])): ?>
                                    <img src="<?php echo htmlspecialchars($similar_album['cover_art']); ?>" alt="<?php echo htmlspecialchars($similar_album['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: white;">
                                        <i class="fas fa-compact-disc" style="font-size: 60px;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="padding: 15px;">
                                <div style="font-weight: 600; font-size: 16px; margin-bottom: 5px; color: #1a1a1a;"><?php echo htmlspecialchars($similar_album['title']); ?></div>
                                <div style="font-size: 14px; color: #666;"><?php echo htmlspecialchars($similar_album['artist_name'] ?? 'Unknown Artist'); ?></div>
                                <?php if (!empty($similar_album['total_tracks'])): ?>
                                    <div style="font-size: 12px; color: #999; margin-top: 5px;"><?php echo $similar_album['total_tracks']; ?> tracks</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #999; padding: 40px;">No similar albums found.</p>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Contributors section responsive */
    @media (max-width: 768px) {
        .contributors-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)) !important;
            gap: 15px !important;
        }
        
        .contributor-avatar {
            width: 100px !important;
            height: 100px !important;
        }
    }
    
    @media (max-width: 480px) {
        .contributors-grid {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
            gap: 10px !important;
        }
        
        .contributor-avatar {
            width: 80px !important;
            height: 80px !important;
        }
    }
</style>

<?php if ($can_reorder): ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css">
<script>
$(document).ready(function() {
    if ($('#sortable-songs-list').length) {
        $('#sortable-songs-list').sortable({
            handle: '.drag-handle',
            axis: 'y',
            cursor: 'move',
            opacity: 0.8,
            placeholder: 'song-item ui-sortable-placeholder',
            tolerance: 'pointer',
            update: function(event, ui) {
                var songOrders = {};
                $('#sortable-songs-list .song-item').each(function(index) {
                    var songId = $(this).data('song-id');
                    songOrders[index] = songId;
                });
                
                // Send reorder request to API
                fetch('<?php echo BASE_PATH; ?>api/reorder-songs.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        album_id: <?php echo $albumId; ?>,
                        song_orders: songOrders
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update song numbers
                        $('#sortable-songs-list .song-item').each(function(index) {
                            $(this).find('.song-number').text(index + 1);
                        });
                        
                        // Show success message
                        var message = $('<div style="background: #28a745; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; text-align: center; z-index: 9999;">Songs reordered successfully!</div>');
                        $('.album-songs-section').prepend(message);
                        setTimeout(function() {
                            message.fadeOut(function() {
                                $(this).remove();
                            });
                        }, 3000);
                    } else {
                        alert('Error: ' + (data.error || 'Failed to reorder songs'));
                        // Reload page to restore original order
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error reordering songs. Please try again.');
                    location.reload();
                });
            }
        });
    }
});
</script>
<?php endif; ?>

<script>
// Fix duration display by getting actual audio file duration (like song-details.php does)
// Also calculate and update total album duration
document.addEventListener('DOMContentLoaded', function() {
    const durationElements = document.querySelectorAll('.song-duration[data-song-url]');
    let totalDurationSeconds = 0;
    let loadedCount = 0;
    const totalSongs = durationElements.length;
    
    // Function to format total duration
    function formatTotalDuration(totalSeconds) {
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        
        if (hours > 0) {
            return hours + 'h ' + minutes + 'm';
        } else if (minutes > 0) {
            return minutes + 'm ' + seconds + 's';
        } else {
            return seconds + 's';
        }
    }
    
    // Function to update total duration display
    function updateTotalDuration() {
        const totalDurationEl = document.getElementById('total-duration-display');
        if (totalDurationEl && totalDurationSeconds > 0) {
            const span = totalDurationEl.querySelector('span');
            if (span) {
                span.textContent = formatTotalDuration(totalDurationSeconds);
            }
        }
    }
    
    durationElements.forEach(function(element) {
        const songUrl = element.getAttribute('data-song-url');
        if (!songUrl) {
            loadedCount++;
            if (loadedCount === totalSongs) {
                updateTotalDuration();
            }
            return;
        }
        
        // Create temporary audio element to get duration from actual file
        const audio = new Audio(songUrl);
        audio.preload = 'metadata';
        
        audio.addEventListener('loadedmetadata', function() {
            if (audio.duration && !isNaN(audio.duration) && audio.duration > 0) {
                const totalSeconds = Math.floor(audio.duration);
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;
                const formattedDuration = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                element.textContent = formattedDuration;
                
                // Add to total duration
                totalDurationSeconds += totalSeconds;
            }
            
            loadedCount++;
            
            // Update total duration when all songs are loaded
            if (loadedCount === totalSongs) {
                updateTotalDuration();
            }
        });
        
        audio.addEventListener('error', function() {
            // Keep the original duration if audio fails to load
            console.log('Failed to load audio for duration:', songUrl);
            
            loadedCount++;
            if (loadedCount === totalSongs) {
                updateTotalDuration();
            }
        });
    });
    
    // If no songs, update immediately
    if (totalSongs === 0) {
        updateTotalDuration();
    }
});
</script>


<?php include 'includes/footer.php'; ?>



