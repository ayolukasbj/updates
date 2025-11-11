<?php
/**
 * Recently Uploaded Songs Section
 * Displays the 6 most recently published songs
 */

// Load required functions
if (!function_exists('getRecentSongs')) {
    require_once __DIR__ . '/../song-storage.php';
}

// Load database connection if needed
if (!function_exists('get_db_connection')) {
    require_once __DIR__ . '/../../config/database.php';
}

// Load base_url function if needed
if (!function_exists('base_url')) {
    function base_url($path = '') {
        $protocol = 'http://';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $protocol = 'https://';
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $protocol = 'https://';
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
        return $protocol . $host . $base_path . ltrim($path, '/');
    }
}

try {
    // Get 6 most recent songs
    $recent_songs = getRecentSongs(6);
    error_log("Recently Uploaded Section: Found " . count($recent_songs) . " songs");
    
    if (empty($recent_songs)) {
        // Try direct database query as fallback
        if (function_exists('get_db_connection')) {
            $conn = get_db_connection();
            if ($conn) {
                $stmt = $conn->prepare("
                    SELECT s.*, 
                           s.uploaded_by,
                           COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                           COALESCE(s.is_collaboration, 0) as is_collaboration,
                           COALESCE(s.plays, 0) as plays,
                           COALESCE(s.downloads, 0) as downloads,
                           COALESCE(s.upload_date, s.created_at, s.uploaded_at) as uploaded_at
                    FROM songs s
                    LEFT JOIN users u ON s.uploaded_by = u.id
                    WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                    AND s.file_path IS NOT NULL 
                    AND s.file_path != ''
                    ORDER BY s.id DESC
                    LIMIT 6
                ");
                $stmt->execute();
                $recent_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Recently Uploaded Section (Direct Query): Found " . count($recent_songs) . " songs");
            }
        }
    }
} catch (Exception $e) {
    error_log("Recently Uploaded Section Error: " . $e->getMessage());
    $recent_songs = [];
}

// Get database connection for slug generation
$conn = null;
if (function_exists('get_db_connection')) {
    $conn = get_db_connection();
}
?>

<style>
.songs-recently-added-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 20px;
}
@media (min-width: 768px) {
    .songs-recently-added-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (min-width: 1024px) {
    .songs-recently-added-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}
</style>

<!-- Recently Uploaded Songs Section -->
<div style="margin: 40px 0;">
    <?php if (!empty($recent_songs)): ?>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin: 0; padding-bottom: 10px; border-bottom: 3px solid #2196F3;">Recently Uploaded Songs</h2>
            <a href="songs.php" style="background: #2196F3; color: white; padding: 8px 20px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.3s;" onmouseover="this.style.background='#1976D2';" onmouseout="this.style.background='#2196F3';">
                View All
            </a>
        </div>
        <div class="songs-recently-added-grid">
            <?php foreach ($recent_songs as $song): 
                // Generate song slug
                $titleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title'] ?? ''));
                $titleSlug = preg_replace('/\s+/', '-', trim($titleSlug));
                $artistForSlug = $song['artist'] ?? 'unknown-artist';
                if (!empty($song['uploaded_by']) && $conn) {
                    try {
                        $slugUploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                        $slugUploaderStmt->execute([$song['uploaded_by']]);
                        $slugUploader = $slugUploaderStmt->fetch(PDO::FETCH_ASSOC);
                        if ($slugUploader && !empty($slugUploader['username'])) {
                            $artistForSlug = $slugUploader['username'];
                        }
                    } catch (Exception $e) {
                        // Keep default
                    }
                }
                $artistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $artistForSlug));
                $artistSlug = preg_replace('/\s+/', '-', trim($artistSlug));
                $songSlug = $titleSlug . '-by-' . $artistSlug;
                $songUrl = base_url('song/' . $songSlug);
                
                // Get cover art
                $coverArt = $song['cover_art'] ?? '';
                if (empty($coverArt)) {
                    $coverArt = 'assets/images/default-avatar.svg';
                }
            ?>
            <div class="music-card" onclick="window.location.href='<?php echo $songUrl; ?>'" style="cursor: pointer; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)';">
                <div style="position: relative; width: 100%; padding-top: 100%; overflow: hidden; background: linear-gradient(135deg, #667eea, #764ba2);">
                    <?php if (!empty($coverArt) && $coverArt !== 'assets/images/default-avatar.svg'): ?>
                    <img src="<?php echo htmlspecialchars($coverArt); ?>" alt="<?php echo htmlspecialchars($song['title'] ?? 'Song'); ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                        <i class="fas fa-music"></i>
                    </div>
                    <?php endif; ?>
                    <div class="play-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s;" onclick="event.stopPropagation(); playSong('<?php echo (int)$song['id']; ?>', '<?php echo htmlspecialchars(addslashes($song['title'])); ?>', '<?php echo htmlspecialchars(addslashes($song['artist'])); ?>', '<?php echo htmlspecialchars($coverArt); ?>');">
                        <div style="width: 60px; height: 60px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; transform: scale(0.9); transition: transform 0.3s;">
                            <i class="fas fa-play" style="color: #333; font-size: 20px; margin-left: 3px;"></i>
                        </div>
                    </div>
                </div>
                <div style="padding: 15px;">
                    <h3 style="font-size: 16px; font-weight: 600; color: #2c3e50; margin: 0 0 5px; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo htmlspecialchars($song['title'] ?? 'Unknown Title'); ?>
                    </h3>
                    <p style="font-size: 14px; color: #666; margin: 0 0 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo htmlspecialchars($song['artist'] ?? 'Unknown Artist'); ?>
                    </p>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #999;">
                        <span><i class="fas fa-play"></i> <?php echo number_format($song['plays'] ?? 0); ?></span>
                        <span><i class="fas fa-download"></i> <?php echo number_format($song['downloads'] ?? 0); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin: 0; padding-bottom: 10px; border-bottom: 3px solid #2196F3;">Recently Uploaded Songs</h2>
            <a href="songs.php" style="background: #2196F3; color: white; padding: 8px 20px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.3s;" onmouseover="this.style.background='#1976D2';" onmouseout="this.style.background='#2196F3';">
                View All
            </a>
        </div>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 8px; color: #666;">
            <i class="fas fa-music" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
            <p style="font-size: 16px; margin: 0;">No recently uploaded songs yet.</p>
        </div>
    <?php endif; ?>
</div>

