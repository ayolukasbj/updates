<?php
/**
 * Most Popular Songs Section
 * Displays songs filtered by downloads count
 */

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

$popular_today = [];
$popular_week = [];
$popular_month = [];

try {
    $conn = get_db_connection();
    if ($conn) {
        $checkSongs = $conn->query("SHOW TABLES LIKE 'songs'");
        if ($checkSongs->rowCount() > 0) {
            // Check if downloads table exists for time-based filtering
            $checkDownloads = $conn->query("SHOW TABLES LIKE 'downloads'");
            $hasDownloadsTable = $checkDownloads->rowCount() > 0;
            
            // Today - Most popular songs today (by downloads today or recent uploads)
            try {
                if ($hasDownloadsTable) {
                    $todayQuery = "
                        SELECT s.*, 
                               s.uploaded_by,
                               COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                               COALESCE(s.is_collaboration, 0) as is_collaboration,
                               COALESCE(s.plays, 0) as plays,
                               COALESCE(s.downloads, 0) as downloads,
                               COUNT(d.id) as today_downloads,
                               COALESCE(s.upload_date, s.created_at, s.uploaded_at) as uploaded_at
                        FROM songs s
                        LEFT JOIN users u ON s.uploaded_by = u.id
                        LEFT JOIN downloads d ON s.id = d.song_id AND DATE(d.download_date) = CURDATE()
                        WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                        GROUP BY s.id
                        HAVING today_downloads > 0 OR (s.downloads > 0 AND DATE(COALESCE(s.upload_date, s.created_at, s.uploaded_at)) = CURDATE())
                        ORDER BY today_downloads DESC, s.downloads DESC, s.plays DESC
                        LIMIT 5
                    ";
                } else {
                    // Fallback: songs uploaded today or with most downloads
                    $todayQuery = "
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
                        AND s.downloads > 0
                        AND (DATE(COALESCE(s.upload_date, s.created_at, s.uploaded_at)) = CURDATE() OR s.downloads > 0)
                        ORDER BY s.downloads DESC, s.plays DESC, s.id DESC
                        LIMIT 5
                    ";
                }
                $stmt = $conn->query($todayQuery);
                $popular_today = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Most Popular Today: Found " . count($popular_today) . " songs");
            } catch (Exception $e) {
                error_log("Most Popular Today Error: " . $e->getMessage());
                $popular_today = [];
            }
            
            // This Week - Most popular songs this week
            try {
                if ($hasDownloadsTable) {
                    $weekQuery = "
                        SELECT s.*, 
                               s.uploaded_by,
                               COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                               COALESCE(s.is_collaboration, 0) as is_collaboration,
                               COALESCE(s.plays, 0) as plays,
                               COALESCE(s.downloads, 0) as downloads,
                               COUNT(d.id) as week_downloads,
                               COALESCE(s.upload_date, s.created_at, s.uploaded_at) as uploaded_at
                        FROM songs s
                        LEFT JOIN users u ON s.uploaded_by = u.id
                        LEFT JOIN downloads d ON s.id = d.song_id AND d.download_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                        GROUP BY s.id
                        HAVING week_downloads > 0 OR (s.downloads > 0 AND COALESCE(s.upload_date, s.created_at, s.uploaded_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY))
                        ORDER BY week_downloads DESC, s.downloads DESC, s.plays DESC
                        LIMIT 5
                    ";
                } else {
                    // Fallback: songs uploaded this week or with most downloads
                    $weekQuery = "
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
                        AND s.downloads > 0
                        AND (COALESCE(s.upload_date, s.created_at, s.uploaded_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY) OR s.downloads > 0)
                        ORDER BY s.downloads DESC, s.plays DESC, s.id DESC
                        LIMIT 5
                    ";
                }
                $stmt = $conn->query($weekQuery);
                $popular_week = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Most Popular Week: Found " . count($popular_week) . " songs");
            } catch (Exception $e) {
                error_log("Most Popular Week Error: " . $e->getMessage());
                $popular_week = [];
            }
            
            // This Month - Most popular songs this month
            try {
                if ($hasDownloadsTable) {
                    $monthQuery = "
                        SELECT s.*, 
                               s.uploaded_by,
                               COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                               COALESCE(s.is_collaboration, 0) as is_collaboration,
                               COALESCE(s.plays, 0) as plays,
                               COALESCE(s.downloads, 0) as downloads,
                               COUNT(d.id) as month_downloads,
                               COALESCE(s.upload_date, s.created_at, s.uploaded_at) as uploaded_at
                        FROM songs s
                        LEFT JOIN users u ON s.uploaded_by = u.id
                        LEFT JOIN downloads d ON s.id = d.song_id AND d.download_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                        GROUP BY s.id
                        HAVING month_downloads > 0 OR (s.downloads > 0 AND COALESCE(s.upload_date, s.created_at, s.uploaded_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                        ORDER BY month_downloads DESC, s.downloads DESC, s.plays DESC
                        LIMIT 5
                    ";
                } else {
                    // Fallback: songs uploaded this month or with most downloads
                    $monthQuery = "
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
                        AND s.downloads > 0
                        AND (COALESCE(s.upload_date, s.created_at, s.uploaded_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR s.downloads > 0)
                        ORDER BY s.downloads DESC, s.plays DESC, s.id DESC
                        LIMIT 5
                    ";
                }
                $stmt = $conn->query($monthQuery);
                $popular_month = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Most Popular Month: Found " . count($popular_month) . " songs");
            } catch (Exception $e) {
                error_log("Most Popular Month Error: " . $e->getMessage());
                $popular_month = [];
            }
        }
    }
} catch (Exception $e) {
    error_log("Most Popular Section Query Error: " . $e->getMessage());
}
?>

<!-- Most Popular Songs Section -->
<div>
    <h2 style="font-size: 20px; font-weight: 700; color: #2c3e50; margin-bottom: 15px;">Most Popular</h2>
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;">
        <!-- Tabs -->
        <div style="display: flex; border-bottom: 2px solid #e0e0e0;">
            <button class="popular-tab-btn active" data-tab="today" style="flex: 1; padding: 12px; background: #e91e63; color: white; border: none; font-weight: 600; font-size: 12px; cursor: pointer; text-transform: uppercase;">
                Today
            </button>
            <button class="popular-tab-btn" data-tab="week" style="flex: 1; padding: 12px; background: #f5f5f5; color: #666; border: none; font-weight: 600; font-size: 12px; cursor: pointer; text-transform: uppercase;">
                This Week
            </button>
            <button class="popular-tab-btn" data-tab="month" style="flex: 1; padding: 12px; background: #f5f5f5; color: #666; border: none; font-weight: 600; font-size: 12px; cursor: pointer; text-transform: uppercase;">
                This Month
            </button>
        </div>
        
        <!-- Tab Content -->
        <div id="popular-tab-content" style="padding: 15px;">
            <?php 
            // Default to today
            $current_popular = !empty($popular_today) ? $popular_today : (!empty($popular_week) ? $popular_week : $popular_month);
            ?>
            <div class="popular-content" data-content="today" style="display: block;">
                <?php if (!empty($popular_today)): ?>
                    <?php foreach ($popular_today as $index => $pop): 
                        // Generate song slug
                        $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $pop['title'] ?? ''));
                        $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                        $songArtistForSlug = $pop['artist'] ?? 'unknown-artist';
                        $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $songArtistForSlug));
                        $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                        $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                        $songUrl = base_url('song/' . $songSlug);
                    ?>
                    <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: <?php echo $index < count($popular_today) - 1 ? '1px solid #e0e0e0' : 'none'; ?>;">
                        <a href="<?php echo $songUrl; ?>" style="text-decoration: none; color: inherit; display: block;">
                            <div style="height: 120px; overflow: hidden; border-radius: 6px; margin-bottom: 10px;">
                                <?php if (!empty($pop['cover_art'])): ?>
                                <img src="<?php echo htmlspecialchars($pop['cover_art']); ?>" alt="<?php echo htmlspecialchars($pop['title'] ?? 'Song'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                                    <i class="fas fa-music"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <h3 style="font-size: 14px; font-weight: 700; color: #2c3e50; margin: 0 0 5px; line-height: 1.4;">
                                <?php echo htmlspecialchars($pop['title'] ?? 'Unknown Title'); ?>
                            </h3>
                            <p style="font-size: 12px; color: #666; margin: 0 0 5px;">
                                <?php echo htmlspecialchars($pop['artist'] ?? 'Unknown Artist'); ?>
                            </p>
                            <p style="font-size: 11px; color: #999; margin: 0;">
                                <i class="fas fa-play"></i> <?php echo number_format($pop['plays'] ?? 0); ?> • 
                                <i class="fas fa-download"></i> <?php echo number_format($pop['downloads'] ?? 0); ?>
                            </p>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color: #999; font-size: 14px; text-align: center; padding: 20px;">No popular songs found</div>
                <?php endif; ?>
            </div>
            
            <div class="popular-content" data-content="week" style="display: none;">
                <?php if (!empty($popular_week)): ?>
                    <?php foreach ($popular_week as $index => $pop): 
                        $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $pop['title'] ?? ''));
                        $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                        $songArtistForSlug = $pop['artist'] ?? 'unknown-artist';
                        $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $songArtistForSlug));
                        $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                        $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                        $songUrl = base_url('song/' . $songSlug);
                    ?>
                    <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: <?php echo $index < count($popular_week) - 1 ? '1px solid #e0e0e0' : 'none'; ?>;">
                        <a href="<?php echo $songUrl; ?>" style="text-decoration: none; color: inherit; display: block;">
                            <div style="height: 120px; overflow: hidden; border-radius: 6px; margin-bottom: 10px;">
                                <?php if (!empty($pop['cover_art'])): ?>
                                <img src="<?php echo htmlspecialchars($pop['cover_art']); ?>" alt="<?php echo htmlspecialchars($pop['title'] ?? 'Song'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                                    <i class="fas fa-music"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <h3 style="font-size: 14px; font-weight: 700; color: #2c3e50; margin: 0 0 5px; line-height: 1.4;">
                                <?php echo htmlspecialchars($pop['title'] ?? 'Unknown Title'); ?>
                            </h3>
                            <p style="font-size: 12px; color: #666; margin: 0 0 5px;">
                                <?php echo htmlspecialchars($pop['artist'] ?? 'Unknown Artist'); ?>
                            </p>
                            <p style="font-size: 11px; color: #999; margin: 0;">
                                <i class="fas fa-play"></i> <?php echo number_format($pop['plays'] ?? 0); ?> • 
                                <i class="fas fa-download"></i> <?php echo number_format($pop['downloads'] ?? 0); ?>
                            </p>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color: #999; font-size: 14px; text-align: center; padding: 20px;">No popular songs found</div>
                <?php endif; ?>
            </div>
            
            <div class="popular-content" data-content="month" style="display: none;">
                <?php if (!empty($popular_month)): ?>
                    <?php foreach ($popular_month as $index => $pop): 
                        $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $pop['title'] ?? ''));
                        $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                        $songArtistForSlug = $pop['artist'] ?? 'unknown-artist';
                        $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $songArtistForSlug));
                        $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                        $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                        $songUrl = base_url('song/' . $songSlug);
                    ?>
                    <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: <?php echo $index < count($popular_month) - 1 ? '1px solid #e0e0e0' : 'none'; ?>;">
                        <a href="<?php echo $songUrl; ?>" style="text-decoration: none; color: inherit; display: block;">
                            <div style="height: 120px; overflow: hidden; border-radius: 6px; margin-bottom: 10px;">
                                <?php if (!empty($pop['cover_art'])): ?>
                                <img src="<?php echo htmlspecialchars($pop['cover_art']); ?>" alt="<?php echo htmlspecialchars($pop['title'] ?? 'Song'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                                    <i class="fas fa-music"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <h3 style="font-size: 14px; font-weight: 700; color: #2c3e50; margin: 0 0 5px; line-height: 1.4;">
                                <?php echo htmlspecialchars($pop['title'] ?? 'Unknown Title'); ?>
                            </h3>
                            <p style="font-size: 12px; color: #666; margin: 0 0 5px;">
                                <?php echo htmlspecialchars($pop['artist'] ?? 'Unknown Artist'); ?>
                            </p>
                            <p style="font-size: 11px; color: #999; margin: 0;">
                                <i class="fas fa-play"></i> <?php echo number_format($pop['plays'] ?? 0); ?> • 
                                <i class="fas fa-download"></i> <?php echo number_format($pop['downloads'] ?? 0); ?>
                            </p>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color: #999; font-size: 14px; text-align: center; padding: 20px;">No popular songs found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Popular tabs functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.popular-tab-btn');
    const tabContents = document.querySelectorAll('.popular-content');
    
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.getAttribute('data-tab');
            
            // Update button styles
            tabButtons.forEach(b => {
                b.style.background = '#f5f5f5';
                b.style.color = '#666';
            });
            this.style.background = '#e91e63';
            this.style.color = 'white';
            
            // Update content
            tabContents.forEach(content => {
                content.style.display = 'none';
            });
            const activeContent = document.querySelector(`.popular-content[data-content="${tab}"]`);
            if (activeContent) {
                activeContent.style.display = 'block';
            }
        });
    });
});
</script>

