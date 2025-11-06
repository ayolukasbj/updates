<?php
// ajax/artist-profile.php - AJAX endpoint for artist profile
require_once '../config/config.php';
require_once '../includes/song-storage.php';

$artist_name = $_GET['artist'] ?? '';
$artist_songs = [];
$artist_stats = [
    'total_songs' => 0,
    'total_plays' => 0,
    'total_downloads' => 0,
    'genres' => []
];

if ($artist_name) {
    $all_songs = getSongs();
    $artist_songs = array_filter($all_songs, function($song) use ($artist_name) {
        return strtolower($song['artist']) === strtolower($artist_name);
    });
    
    // Calculate artist stats
    $artist_stats['total_songs'] = count($artist_songs);
    $artist_stats['total_plays'] = array_sum(array_column($artist_songs, 'plays'));
    $artist_stats['total_downloads'] = array_sum(array_column($artist_songs, 'downloads'));
    
    // Get unique genres
    $genres = array_unique(array_column($artist_songs, 'genre'));
    $artist_stats['genres'] = array_filter($genres);
}

// Sort songs by plays (most popular first)
usort($artist_songs, function($a, $b) {
    return ($b['plays'] ?? 0) - ($a['plays'] ?? 0);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($artist_name); ?> - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #333;
        }

        /* Artist Header */
        .artist-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .artist-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .artist-info {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .artist-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255,255,255,0.3);
        }

        .artist-details h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .artist-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 15px;
        }

        .artist-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            font-size: 12px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Songs List */
        .songs-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
            color: #333;
        }

        .song-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
            cursor: pointer;
        }

        .song-item:hover {
            background: #f8f9fa;
        }

        .song-item:last-child {
            border-bottom: none;
        }

        .song-number {
            width: 30px;
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .song-play-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #1db954;
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .song-play-btn:hover {
            background: #1ed760;
            transform: scale(1.1);
        }

        .song-info {
            flex: 1;
            margin-left: 15px;
        }

        .song-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .song-artist {
            font-size: 14px;
            color: #666;
        }

        .song-duration {
            font-size: 14px;
            color: #666;
            margin-left: auto;
        }

        /* Featured Playlists */
        .playlists-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .playlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .playlist-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s;
            cursor: pointer;
        }

        .playlist-card:hover {
            transform: translateY(-5px);
        }

        .playlist-icon {
            width: 60px;
            height: 60px;
            background: #1db954;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 24px;
        }

        .playlist-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .playlist-description {
            font-size: 12px;
            color: #666;
        }

        /* Top Charts */
        .charts-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .chart-tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e9ecef;
            flex-wrap: wrap;
        }

        .chart-tab {
            padding: 10px 0;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .chart-tab:hover,
        .chart-tab.active {
            color: #1db954;
            border-bottom-color: #1db954;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .artist-info {
                flex-direction: column;
                text-align: center;
            }

            .artist-stats {
                justify-content: center;
            }

            .song-item {
                padding: 12px 0;
            }

            .song-play-btn {
                width: 35px;
                height: 35px;
                margin-right: 10px;
            }

            .playlist-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php if ($artist_name && !empty($artist_songs)): ?>
        <!-- Artist Header -->
        <div class="artist-header">
            <div class="artist-info">
                <div class="artist-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="artist-details">
                    <h1><?php echo htmlspecialchars($artist_name); ?></h1>
                    <div class="artist-subtitle">
                        <?php if (!empty($artist_stats['genres'])): ?>
                            <?php echo implode(', ', $artist_stats['genres']); ?> Artist
                        <?php else: ?>
                            Music Artist
                        <?php endif; ?>
                    </div>
                    <div class="artist-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $artist_stats['total_songs']; ?></span>
                            <span class="stat-label">Songs</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($artist_stats['total_plays']); ?></span>
                            <span class="stat-label">Plays</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($artist_stats['total_downloads']); ?></span>
                            <span class="stat-label">Downloads</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Songs List -->
        <div class="songs-section">
            <h2 class="section-title">Songs</h2>
            <?php foreach ($artist_songs as $index => $song): ?>
                <div class="song-item" data-song-id="<?php echo $song['id']; ?>" data-song-title="<?php echo htmlspecialchars($song['title']); ?>" data-song-artist="<?php echo htmlspecialchars($song['artist']); ?>" data-song-cover="<?php echo htmlspecialchars($song['cover_art']); ?>" data-song-duration="3:45">
                    <div class="song-number"><?php echo $index + 1; ?></div>
                    <button class="song-play-btn" onclick="playSong(this)">
                        <i class="fas fa-play"></i>
                    </button>
                    <div class="song-info">
                        <div class="song-title"><?php echo htmlspecialchars($song['title']); ?></div>
                        <div class="song-artist"><?php echo htmlspecialchars($song['artist']); ?></div>
                    </div>
                    <div class="song-duration">3:45</div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Featured Playlists -->
        <div class="playlists-section">
            <h2 class="section-title">Featured in Playlist</h2>
            <div class="playlist-grid">
                <div class="playlist-card">
                    <div class="playlist-icon">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="playlist-title">Trending Now</div>
                    <div class="playlist-description">Latest hits</div>
                </div>
                <div class="playlist-card">
                    <div class="playlist-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="playlist-title">Best of <?php echo htmlspecialchars($artist_name); ?></div>
                    <div class="playlist-description">Top tracks</div>
                </div>
                <div class="playlist-card">
                    <div class="playlist-icon">
                        <i class="fas fa-music"></i>
                    </div>
                    <div class="playlist-title">New Releases</div>
                    <div class="playlist-description">Fresh music</div>
                </div>
            </div>
        </div>

        <!-- Top Charts -->
        <div class="charts-section">
            <h2 class="section-title">Top Charts</h2>
            <div class="chart-tabs">
                <a href="#" class="chart-tab active">Top Songs</a>
                <a href="#" class="chart-tab">Nigeria</a>
                <a href="#" class="chart-tab">Ghana</a>
                <a href="#" class="chart-tab">South Africa</a>
                <a href="#" class="chart-tab">Uganda</a>
                <a href="#" class="chart-tab">USA</a>
                <a href="#" class="chart-tab">Tanzania</a>
                <a href="#" class="chart-tab">Kenya</a>
                <a href="#" class="chart-tab">Zambia</a>
                <a href="#" class="chart-tab">Cameroon</a>
                <a href="#" class="chart-tab">Malawi</a>
            </div>
            <div class="chart-tabs">
                <a href="#" class="chart-tab">Show all</a>
            </div>
        </div>

    <?php else: ?>
        <!-- No Artist Found -->
        <div class="songs-section">
            <h2 class="section-title">Artist Not Found</h2>
            <p>The artist "<?php echo htmlspecialchars($artist_name); ?>" was not found or has no songs.</p>
        </div>
    <?php endif; ?>

    <script>
        function playSong(button) {
            const songItem = button.closest('.song-item');
            const songData = {
                id: songItem.dataset.songId,
                title: songItem.dataset.songTitle,
                artist: songItem.dataset.songArtist,
                cover_art: songItem.dataset.songCover,
                duration: songItem.dataset.songDuration
            };
            
            if (window.miniPlayer) {
                window.miniPlayer.playSong(songData);
            }
        }
    </script>
</body>
</html>
