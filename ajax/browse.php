<?php
// ajax/browse.php - Browse/Top Charts page matching MDUNDO
require_once '../config/config.php';
require_once '../includes/song-storage.php';

$all_songs = getSongs();
// Sort by plays (most popular first)
usort($all_songs, function($a, $b) {
    return ($b['plays'] ?? 0) - ($a['plays'] ?? 0);
});

$top_songs = array_slice($all_songs, 0, 50); // Top 50 songs
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Charts - <?php echo SITE_NAME; ?></title>
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

        /* Top Charts Section */
        .charts-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 25px;
            color: #333;
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

        .chart-subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }

        /* Songs List */
        .songs-list {
            margin-top: 20px;
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
            font-size: 16px;
            color: #666;
            font-weight: 600;
            text-align: center;
        }

        .song-number.top-3 {
            color: #1db954;
            font-weight: 700;
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

        /* Load More */
        .load-more {
            text-align: center;
            margin-top: 30px;
        }

        .load-more-btn {
            background: #1db954;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .load-more-btn:hover {
            background: #1ed760;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
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

            .chart-tabs {
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .charts-section,
            .playlists-section {
                padding: 20px;
            }

            .section-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Charts -->
    <div class="charts-section">
        <h1 class="section-title">Top Charts</h1>
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
        <div class="chart-subtitle">Your weekly update</div>
        
        <div class="songs-list">
            <?php foreach ($top_songs as $index => $song): ?>
                <div class="song-item" data-song-id="<?php echo $song['id']; ?>" data-song-title="<?php echo htmlspecialchars($song['title']); ?>" data-song-artist="<?php echo htmlspecialchars($song['artist']); ?>" data-song-cover="<?php echo htmlspecialchars($song['cover_art']); ?>" data-song-duration="3:45">
                    <div class="song-number <?php echo $index < 3 ? 'top-3' : ''; ?>"><?php echo $index + 1; ?></div>
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
        
        <div class="load-more">
            <button class="load-more-btn">Load 10 more</button>
        </div>
    </div>

    <!-- Featured Playlists -->
    <div class="playlists-section">
        <h2 class="section-title">Featured in Playlist</h2>
        <div class="playlist-grid">
            <div class="playlist-card">
                <div class="playlist-icon">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="playlist-title">Best of Uganda | 2024</div>
                <div class="playlist-description">Top tracks from Uganda</div>
            </div>
            <div class="playlist-card">
                <div class="playlist-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="playlist-title">Urban Vibe | 2024</div>
                <div class="playlist-description">Urban music collection</div>
            </div>
            <div class="playlist-card">
                <div class="playlist-icon">
                    <i class="fas fa-music"></i>
                </div>
                <div class="playlist-title">Coca-Cola Feast</div>
                <div class="playlist-description">Party music</div>
            </div>
            <div class="playlist-card">
                <div class="playlist-icon">
                    <i class="fas fa-headphones"></i>
                </div>
                <div class="playlist-title">DJ Mixes Uganda</div>
                <div class="playlist-description">DJ mixes and remixes</div>
            </div>
            <div class="playlist-card">
                <div class="playlist-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="playlist-title">Sprite Heat Happens</div>
                <div class="playlist-description">Hot tracks</div>
            </div>
            <div class="playlist-card">
                <div class="playlist-icon">
                    <i class="fas fa-beer"></i>
                </div>
                <div class="playlist-title">Guinness Smooth Flow</div>
                <div class="playlist-description">Smooth music</div>
            </div>
        </div>
    </div>

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
