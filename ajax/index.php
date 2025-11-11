<?php
// ajax/index.php - AJAX homepage matching MDUNDO design
require_once '../config/config.php';
require_once '../includes/song-storage.php';

$featured_songs = getFeaturedSongs();
$recent_songs = getRecentSongs();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Music Streaming Platform</title>
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

        /* Header */
        .header {
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #1db954;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-link {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: #1db954;
        }

        .nav-link.active {
            color: #1db954;
            font-weight: 600;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .premium-banner {
            background: linear-gradient(135deg, #1db954 0%, #1ed760 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .premium-banner:hover {
            transform: scale(1.05);
            color: white;
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 60px 40px;
            color: white;
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .hero-subtitle {
            font-size: 20px;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .hero-cta {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-button {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 15px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
        }

        .cta-button:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            color: white;
        }

        .cta-button.primary {
            background: white;
            color: #667eea;
        }

        .cta-button.primary:hover {
            background: #f8f9fa;
            color: #667eea;
        }

        /* Songs Grid */
        .songs-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .section-link {
            color: #1db954;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .section-link:hover {
            color: #1ed760;
        }

        .songs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .song-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .song-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .song-cover {
            width: 120px;
            height: 120px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            position: relative;
            overflow: hidden;
        }

        .song-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
            border-radius: 8px;
        }

        .song-card:hover .play-overlay {
            opacity: 1;
        }

        .play-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #1db954;
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .play-btn:hover {
            background: #1ed760;
            transform: scale(1.1);
        }

        .song-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            line-height: 1.3;
        }

        .song-artist {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .song-stats {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #999;
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .playlist-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .playlist-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .playlist-icon {
            width: 80px;
            height: 80px;
            background: #1db954;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 32px;
        }

        .playlist-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .playlist-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .playlist-count {
            font-size: 12px;
            color: #999;
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
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                gap: 20px;
            }

            .hero-section {
                padding: 40px 20px;
            }

            .hero-title {
                font-size: 36px;
            }

            .hero-subtitle {
                font-size: 18px;
            }

            .hero-cta {
                flex-direction: column;
                align-items: center;
            }

            .songs-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }

            .song-card {
                padding: 15px;
            }

            .song-cover {
                width: 100px;
                height: 100px;
            }

            .playlist-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 20px 15px;
            }

            .hero-title {
                font-size: 28px;
            }

            .songs-section,
            .playlists-section,
            .charts-section {
                padding: 20px;
            }

            .songs-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">Discover Amazing Music</h1>
            <p class="hero-subtitle">Stream, download, and enjoy your favorite songs from talented artists</p>
            <div class="hero-cta">
                <a href="#" class="cta-button primary">Start Listening</a>
                <a href="#" class="cta-button">Browse Artists</a>
            </div>
        </div>
    </div>

    <!-- Featured Songs -->
    <div class="songs-section">
        <div class="section-header">
            <h2 class="section-title">Featured Songs</h2>
            <a href="#" class="section-link">View All</a>
        </div>
        <div class="songs-grid">
            <?php foreach ($featured_songs as $song): ?>
                <div class="song-card" data-song-id="<?php echo $song['id']; ?>" data-song-title="<?php echo htmlspecialchars($song['title']); ?>" data-song-artist="<?php echo htmlspecialchars($song['artist']); ?>" data-song-cover="<?php echo htmlspecialchars($song['cover_art']); ?>" data-song-duration="3:45">
                    <div class="song-cover">
                        <?php if (!empty($song['cover_art'])): ?>
                            <img src="<?php echo htmlspecialchars($song['cover_art']); ?>" alt="Cover Art">
                        <?php else: ?>
                            <i class="fas fa-music"></i>
                        <?php endif; ?>
                        <div class="play-overlay">
                            <button class="play-btn" onclick="playSong(this)">
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                    </div>
                    <div class="song-title"><?php echo htmlspecialchars($song['title']); ?></div>
                    <div class="song-artist"><?php echo htmlspecialchars($song['artist']); ?></div>
                    <div class="song-stats">
                        <span><?php echo number_format($song['plays'] ?? 0); ?> plays</span>
                        <span><?php echo number_format($song['downloads'] ?? 0); ?> downloads</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent Songs -->
    <div class="songs-section">
        <div class="section-header">
            <h2 class="section-title">Recently Added</h2>
            <a href="#" class="section-link">View All</a>
        </div>
        <div class="songs-grid">
            <?php foreach ($recent_songs as $song): ?>
                <div class="song-card" data-song-id="<?php echo $song['id']; ?>" data-song-title="<?php echo htmlspecialchars($song['title']); ?>" data-song-artist="<?php echo htmlspecialchars($song['artist']); ?>" data-song-cover="<?php echo htmlspecialchars($song['cover_art']); ?>" data-song-duration="3:45">
                    <div class="song-cover">
                        <?php if (!empty($song['cover_art'])): ?>
                            <img src="<?php echo htmlspecialchars($song['cover_art']); ?>" alt="Cover Art">
                        <?php else: ?>
                            <i class="fas fa-music"></i>
                        <?php endif; ?>
                        <div class="play-overlay">
                            <button class="play-btn" onclick="playSong(this)">
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                    </div>
                    <div class="song-title"><?php echo htmlspecialchars($song['title']); ?></div>
                    <div class="song-artist"><?php echo htmlspecialchars($song['artist']); ?></div>
                    <div class="song-stats">
                        <span><?php echo number_format($song['plays'] ?? 0); ?> plays</span>
                        <span><?php echo number_format($song['downloads'] ?? 0); ?> downloads</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Featured Playlists -->
    <div class="playlists-section">
        <div class="section-header">
            <h2 class="section-title">Featured Playlists</h2>
            <a href="#" class="section-link">View All</a>
        </div>
        <div class="playlist-grid">
            <div class="playlist-card">
                <div class="playlist-icon">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="playlist-title">Trending Now</div>
                <div class="playlist-description">The hottest tracks right now</div>
                <div class="playlist-count">50 songs</div>
            </div>
            <div class="playlist-card">
                <div class="playlist-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="playlist-title">Best of 2024</div>
                <div class="playlist-description">Top hits from this year</div>
                <div class="playlist-count">30 songs</div>
            </div>
            <div class="playlist-card">
                <div class="playlist-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="playlist-title">Love Songs</div>
                <div class="playlist-description">Romantic melodies</div>
                <div class="playlist-count">25 songs</div>
            </div>
        </div>
    </div>

    <!-- Top Charts -->
    <div class="charts-section">
        <div class="section-header">
            <h2 class="section-title">Top Charts</h2>
            <a href="#" class="section-link">Show All</a>
        </div>
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
    </div>

    <script>
        function playSong(button) {
            const songCard = button.closest('.song-card');
            const songData = {
                id: songCard.dataset.songId,
                title: songCard.dataset.songTitle,
                artist: songCard.dataset.songArtist,
                cover_art: songCard.dataset.songCover,
                duration: songCard.dataset.songDuration
            };
            
            if (window.miniPlayer) {
                window.miniPlayer.playSong(songData);
            }
        }
    </script>
</body>
</html>
