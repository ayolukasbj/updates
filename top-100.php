<?php
// top-100.php - Top 100 Chart Page
require_once 'config/config.php';
require_once 'config/database.php';

// Get top 100 songs from database sorted by plays
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get Top 100 query settings
    $query_type = 'plays';
    $limit = 100;
    try {
        $settingsStmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = ?");
        $settingsStmt->execute(['top100_query_type']);
        $result = $settingsStmt->fetch(PDO::FETCH_ASSOC);
        if ($result) $query_type = $result['value'];
        
        $settingsStmt->execute(['top100_limit']);
        $result = $settingsStmt->fetch(PDO::FETCH_ASSOC);
        if ($result) $limit = (int)$result['value'];
    } catch (Exception $e) {
        // Use defaults
    }
    
    // Build query based on settings
    $orderBy = 's.plays DESC';
    if ($query_type === 'downloads') {
        $orderBy = 's.downloads DESC';
    } else if ($query_type === 'plays_downloads') {
        $orderBy = '(s.plays + s.downloads) DESC';
    } else if ($query_type === 'recent') {
        $orderBy = 's.id DESC';
    }
    
    $stmt = $conn->prepare("
        SELECT s.*, 
               s.uploaded_by,
               COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
               COALESCE(s.is_collaboration, 0) as is_collaboration,
               COALESCE(s.plays, 0) as plays,
               COALESCE(s.downloads, 0) as downloads
        FROM songs s
        LEFT JOIN users u ON s.uploaded_by = u.id
        WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
        ORDER BY $orderBy
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $top_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no songs from database, use empty array
    if (empty($top_songs)) {
        $top_songs = [];
    }
} catch (Exception $e) {
    error_log("Top 100 error: " . $e->getMessage());
    $top_songs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top 100 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/luo-style.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding-bottom: 120px;
        }


        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            border-radius: 8px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            text-align: center;
        }

        .page-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .page-subtitle {
            font-size: 16px;
            opacity: 0.9;
        }

        .chart-section {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .chart-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .chart-item {
            display: flex;
            align-items: center;
            padding: 15px 12px;
            border-bottom: 1px solid #f5f5f5;
            transition: all 0.3s;
            cursor: pointer;
        }

        .chart-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .chart-number {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .chart-number.top3 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
        }

        .chart-cover {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            object-fit: cover;
            margin-right: 20px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
        }

        .chart-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .chart-info {
            flex: 1;
            min-width: 0;
        }

        .chart-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .chart-artist {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .chart-genre {
            display: inline-block;
            padding: 3px 10px;
            background: #e3f2fd;
            color: #2196F3;
            font-size: 11px;
            border-radius: 3px;
            font-weight: 500;
        }

        .chart-stats {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #999;
            white-space: nowrap;
            margin-left: 20px;
        }

        .chart-stats span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        @media (max-width: 768px) {
            .chart-item {
                flex-wrap: wrap;
            }

            .chart-stats {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
            }

            .chart-number {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .chart-cover {
                width: 60px;
                height: 60px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <?php 
    // Display header ad if exists
    require_once 'includes/ads.php';
    $headerAd = displayAd('header');
    if ($headerAd) {
        echo '<div style="max-width: 1400px; margin: 10px auto; padding: 0 15px;">' . $headerAd . '</div>';
    }
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Top 100 Chart</h1>
            <p class="page-subtitle">Most Played Songs on <?php echo SITE_NAME; ?></p>
        </div>

        <div class="chart-section">
            <ul class="chart-list">
                <?php foreach ($top_songs as $index => $song): ?>
                <?php
                // Generate slug for song URL
                $chartSongTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
                $chartSongTitleSlug = preg_replace('/\s+/', '-', trim($chartSongTitleSlug));
                
                // Get artist name for slug
                $chartSongArtistName = '';
                if (!empty($song['is_collaboration'])) {
                    try {
                        $chart_all_artist_names = [];
                        if (!empty($song['uploaded_by'])) {
                            $chartUploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                            $chartUploaderStmt->execute([$song['uploaded_by']]);
                            $chartUploader = $chartUploaderStmt->fetch(PDO::FETCH_ASSOC);
                            if ($chartUploader && !empty($chartUploader['username'])) {
                                $chart_all_artist_names[] = $chartUploader['username'];
                            }
                        }
                        $chartCollabStmt = $conn->prepare("
                            SELECT DISTINCT sc.user_id, COALESCE(u.username, sc.user_id) as artist_name
                            FROM song_collaborators sc
                            LEFT JOIN users u ON sc.user_id = u.id
                            WHERE sc.song_id = ?
                            ORDER BY sc.added_at ASC
                        ");
                        $chartCollabStmt->execute([$song['id']]);
                        $chartCollaborators = $chartCollabStmt->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($chartCollaborators)) {
                            foreach ($chartCollaborators as $c) {
                                $collab_name = $c['artist_name'] ?? 'Unknown';
                                if (!in_array($collab_name, $chart_all_artist_names)) {
                                    $chart_all_artist_names[] = $collab_name;
                                }
                            }
                        }
                        $chartSongArtistName = !empty($chart_all_artist_names) ? implode(' x ', $chart_all_artist_names) : ($song['artist'] ?? 'unknown-artist');
                    } catch (Exception $e) {
                        $chartSongArtistName = $song['artist'] ?? 'unknown-artist';
                    }
                } else {
                    $chartSongArtistName = $song['artist'] ?? 'unknown-artist';
                }
                
                $chartSongArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $chartSongArtistName));
                $chartSongArtistSlug = preg_replace('/\s+/', '-', trim($chartSongArtistSlug));
                $chartSongSlug = $chartSongTitleSlug . '-by-' . $chartSongArtistSlug;
                ?>
                <li class="chart-item" onclick="window.location.href='/song/<?php echo urlencode($chartSongSlug); ?>'" style="cursor: pointer;">
                    <div class="chart-number <?php echo $index < 3 ? 'top3' : ''; ?>">
                        <?php echo $index + 1; ?>
                    </div>
                    <div class="chart-cover">
                        <?php if (!empty($song['cover_art'])): ?>
                            <img src="<?php echo $song['cover_art']; ?>" alt="<?php echo htmlspecialchars($song['title']); ?>">
                        <?php else: ?>
                            <i class="fas fa-music"></i>
                        <?php endif; ?>
                    </div>
                    <div class="chart-info">
                        <div class="chart-title"><?php echo htmlspecialchars($song['title']); ?></div>
                        <div class="chart-artist">
                            <?php 
                            // Display collaboration artists if applicable
                            if (!empty($song['is_collaboration'])) {
                                try {
                                    $all_artist_names = [];
                                    
                                    // First, get uploader
                                    if (!empty($song['uploaded_by'])) {
                                        $uploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                                        $uploaderStmt->execute([$song['uploaded_by']]);
                                        $uploader = $uploaderStmt->fetch(PDO::FETCH_ASSOC);
                                        if ($uploader && !empty($uploader['username'])) {
                                            $all_artist_names[] = htmlspecialchars($uploader['username']);
                                        }
                                    }
                                    
                                    // Then get all collaborators
                                    $collabStmt = $conn->prepare("
                                        SELECT DISTINCT sc.user_id, COALESCE(u.username, sc.user_id) as artist_name
                                        FROM song_collaborators sc
                                        LEFT JOIN users u ON sc.user_id = u.id
                                        WHERE sc.song_id = ?
                                        ORDER BY sc.added_at ASC
                                    ");
                                    $collabStmt->execute([$song['id']]);
                                    $collaborators = $collabStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (!empty($collaborators)) {
                                        foreach ($collaborators as $c) {
                                            $collab_name = htmlspecialchars($c['artist_name'] ?? 'Unknown');
                                            // Avoid duplicating uploader
                                            if (!in_array($collab_name, $all_artist_names)) {
                                                $all_artist_names[] = $collab_name;
                                            }
                                        }
                                    }
                                    
                                    if (count($all_artist_names) > 0) {
                                        echo implode(' x ', $all_artist_names);
                                    } else {
                                        echo htmlspecialchars($song['artist']);
                                    }
                                } catch (Exception $e) {
                                    echo htmlspecialchars($song['artist']);
                                }
                            } else {
                                echo htmlspecialchars($song['artist']);
                            }
                            ?>
                        </div>
                        <?php if (!empty($song['genre'])): ?>
                            <span class="chart-genre"><?php echo htmlspecialchars($song['genre']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="chart-stats">
                        <span><i class="fas fa-play"></i> <?php echo number_format($song['plays'] ?? 0); ?> plays</span>
                        <span><i class="fas fa-download"></i> <?php echo number_format($song['downloads'] ?? 0); ?> downloads</span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Audio Player -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/luo-player.js"></script>
    <script>
        const player = new LuoPlayer();

        function playSong(songId) {
            fetch(`api/song-data.php?id=${songId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        player.loadSong(data.song);
                        player.play();
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>

