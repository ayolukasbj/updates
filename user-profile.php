<?php
// user-profile.php - Frontend user profile page
require_once 'config/config.php';
require_once 'config/database.php';

$user_id = $_GET['id'] ?? null;
$user_data = null;
$user_songs = [];
$user_stats = [
    'total_songs' => 0,
    'total_plays' => 0,
    'total_downloads' => 0
];

if (empty($user_id)) {
    header('Location: index.php');
    exit;
}

// Get user from database
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check which verified column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM users");
    $columns = $colCheck->fetchAll(PDO::FETCH_COLUMN);
    $verifiedCol = 'u.is_verified';
    if (!in_array('is_verified', $columns) && in_array('email_verified', $columns)) {
        $verifiedCol = 'u.email_verified as is_verified';
    }
    
    // Get user data with collaboration stats
    $userStmt = $conn->prepare("
        SELECT u.id, u.username, u.avatar, u.bio, $verifiedCol as is_verified,
               u.facebook, u.twitter, u.instagram, u.youtube,
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
    $userStmt->execute([$user_id]);
    $user_data = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_data) {
        // Get user songs - include both uploaded and collaborated songs
        $songsStmt = $conn->prepare("
            SELECT DISTINCT s.*, COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name
            FROM songs s
            LEFT JOIN users u ON s.uploaded_by = u.id
            LEFT JOIN song_collaborators sc ON sc.song_id = s.id
            WHERE (s.uploaded_by = ? OR sc.user_id = ?)
            AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
            ORDER BY s.plays DESC, s.downloads DESC
        ");
        $songsStmt->execute([$user_id, $user_id]);
        $user_songs = $songsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate stats (already done in query, but recalculate for consistency)
        $user_stats['total_songs'] = count($user_songs);
        $user_stats['total_plays'] = array_sum(array_column($user_songs, 'plays'));
        $user_stats['total_downloads'] = array_sum(array_column($user_songs, 'downloads'));
    }
} catch (Exception $e) {
    error_log("Error in user-profile.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user_data['username'] ?? 'User'); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .user-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .user-info {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .user-avatar {
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
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-details h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .user-bio {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .user-stats {
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
        }

        .song-item:last-child {
            border-bottom: none;
        }

        .song-item:hover {
            background: #f8f9fa;
            border-radius: 8px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .song-cover {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            margin-right: 15px;
            object-fit: cover;
        }

        .song-info {
            flex: 1;
        }

        .song-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .song-title a {
            color: inherit;
            text-decoration: none;
        }

        .song-title a:hover {
            color: #667eea;
        }

        .song-artist {
            font-size: 14px;
            color: #666;
        }

        .song-stats {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .play-btn {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin-left: 15px;
            transition: all 0.3s;
        }

        .play-btn:hover {
            background: #5568d3;
            transform: scale(1.1);
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .social-link:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .user-info {
                flex-direction: column;
                text-align: center;
            }

            .user-stats {
                justify-content: center;
            }

            .song-item {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="main-content">
        <?php if (!empty($user_data)): ?>
            <div class="user-header">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php if (!empty($user_data['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($user_data['avatar']); ?>" alt="<?php echo htmlspecialchars($user_data['username']); ?>">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <h1>
                            <?php echo htmlspecialchars($user_data['username']); ?>
                            <?php if (!empty($user_data['is_verified']) && $user_data['is_verified'] == 1): ?>
                                <i class="fas fa-check-circle" style="color: #1da1f2; font-size: 24px; vertical-align: middle;"></i>
                            <?php endif; ?>
                        </h1>
                        <?php if (!empty($user_data['bio'])): ?>
                            <p class="user-bio"><?php echo nl2br(htmlspecialchars($user_data['bio'])); ?></p>
                        <?php endif; ?>
                        <div class="user-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $user_stats['total_songs']; ?></span>
                                <span class="stat-label">Songs</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo number_format($user_stats['total_plays']); ?></span>
                                <span class="stat-label">Plays</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo number_format($user_stats['total_downloads']); ?></span>
                                <span class="stat-label">Downloads</span>
                            </div>
                        </div>
                        <?php if ($user_data['facebook'] || $user_data['twitter'] || $user_data['instagram'] || $user_data['youtube']): ?>
                            <div class="social-links">
                                <?php if ($user_data['facebook']): ?>
                                    <a href="<?php echo htmlspecialchars($user_data['facebook']); ?>" target="_blank" class="social-link" title="Facebook">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($user_data['twitter']): ?>
                                    <a href="<?php echo htmlspecialchars($user_data['twitter']); ?>" target="_blank" class="social-link" title="Twitter">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($user_data['instagram']): ?>
                                    <a href="<?php echo htmlspecialchars($user_data['instagram']); ?>" target="_blank" class="social-link" title="Instagram">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($user_data['youtube']): ?>
                                    <a href="<?php echo htmlspecialchars($user_data['youtube']); ?>" target="_blank" class="social-link" title="YouTube">
                                        <i class="fab fa-youtube"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="songs-section">
                <h2 class="section-title">Songs</h2>
                <?php if (!empty($user_songs)): ?>
                    <?php foreach ($user_songs as $song): ?>
                        <div class="song-item">
                            <img src="<?php echo htmlspecialchars($song['cover_art'] ?? 'assets/images/default-cover.png'); ?>" 
                                 alt="<?php echo htmlspecialchars($song['title']); ?>" 
                                 class="song-cover"
                                 onerror="this.src='assets/images/default-cover.png'">
                            <div class="song-info">
                                <h3 class="song-title">
                                    <?php
                                    // Generate song slug for URL
                                    $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
                                    $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                                    $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['artist'] ?? 'unknown-artist'));
                                    $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                                    $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                                    ?>
                                    <a href="/song/<?php echo urlencode($songSlug); ?>">
                                        <?php echo htmlspecialchars($song['title']); ?>
                                    </a>
                                </h3>
                                <p class="song-artist"><?php echo htmlspecialchars($song['artist_name']); ?></p>
                                <div class="song-stats">
                                    <i class="fas fa-play"></i> <?php echo number_format($song['plays'] ?? 0); ?> plays
                                    <span style="margin: 0 10px;">|</span>
                                    <i class="fas fa-download"></i> <?php echo number_format($song['downloads'] ?? 0); ?> downloads
                                </div>
                            </div>
                            <button class="play-btn" onclick="playSong(<?php echo $song['id']; ?>)">
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #999; text-align: center; padding: 40px;">No songs available yet.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="songs-section">
                <h2 class="section-title">User Not Found</h2>
                <p>The user you're looking for was not found.</p>
                <a href="index.php" class="btn btn-primary">Back to Home</a>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function playSong(songId) {
            // Implement play functionality
            console.log('Play song:', songId);
        }
    </script>
</body>
</html>

