<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Redirect if not logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = get_user_id();

// Get user/artist data from database
$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->prepare("
        SELECT u.*, 
               COUNT(DISTINCT s.id) as total_songs,
               COALESCE(SUM(s.downloads), 0) as total_downloads,
               COALESCE(SUM(s.plays), 0) as total_plays
        FROM users u
        LEFT JOIN songs s ON s.uploaded_by = u.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        redirect('login.php');
    }

    // Get songs for detailed stats
    $stmt = $conn->prepare("
        SELECT s.title, s.plays, s.downloads
        FROM songs s
        WHERE s.uploaded_by = ?
        ORDER BY s.plays DESC
    ");
    $stmt->execute([$user_id]);
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback if there's an error
    $user = [
        'username' => $_SESSION['username'] ?? 'User',
        'total_songs' => 0,
        'total_plays' => 0,
        'total_downloads' => 0
    ];
    $songs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artist Stats - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .header {
            background: #4a4a4a;
            color: white;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .site-logo {
            width: 35px;
            height: 35px;
            background: #ff6600;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: bold;
        }
        
        .site-name {
            font-size: 14px;
            font-weight: 600;
        }
        
        .header-right {
            display: flex;
            gap: 15px;
            font-size: 13px;
        }
        
        .header-right a {
            color: white;
            text-decoration: none;
        }
        
        .nav-tabs {
            display: flex;
            background: #5a5a5a;
            overflow-x: auto;
        }
        
        .nav-tab {
            flex: 1;
            text-align: center;
            padding: 12px 10px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }
        
        .nav-tab.active {
            background: #4a4a4a;
            border-bottom-color: #ff6600;
        }
        
        .stats-container {
            padding: 20px 15px;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .songs-stats {
            background: white;
            border-radius: 8px;
            padding: 15px;
        }
        
        .songs-stats h3 {
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .song-stat-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .song-stat-item:last-child {
            border-bottom: none;
        }
        
        .song-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .song-metrics {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="site-logo">
                <i class="fas fa-music"></i>
            </div>
            <div class="site-name"><?php echo SITE_NAME; ?></div>
        </div>
        <div class="header-right">
            <a href="profile.php">Account</a>
            <span>|</span>
            <a href="logout.php">Log out</a>
        </div>
    </div>
    
    <div class="nav-tabs">
        <a href="artist-profile-mobile.php" class="nav-tab">PROFILE</a>
        <a href="my-songs.php" class="nav-tab">MUSIC</a>
        <a href="news.php" class="nav-tab">NEWS</a>
        <a href="artist-stats.php" class="nav-tab active">STATS</a>
    </div>
    
    <div class="stats-container">
        <div class="stats-summary">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($user['total_songs'] ?? 0); ?></div>
                <div class="stat-label">Total Songs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($user['total_plays'] ?? 0); ?></div>
                <div class="stat-label">Total Plays</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($user['total_downloads'] ?? 0); ?></div>
                <div class="stat-label">Total Downloads</div>
            </div>
        </div>
        
        <div class="songs-stats">
            <h3>Song Performance</h3>
            <?php if (empty($songs)): ?>
                <p style="text-align: center; color: #999; padding: 20px;">
                    <i class="fas fa-music" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                    No songs uploaded yet
                </p>
            <?php else: ?>
                <?php foreach ($songs as $song): ?>
                    <div class="song-stat-item">
                        <div class="song-title"><?php echo htmlspecialchars($song['title']); ?></div>
                        <div class="song-metrics">
                            <span><i class="fas fa-play"></i> <?php echo number_format($song['plays'] ?? 0); ?> plays</span>
                            <span><i class="fas fa-download"></i> <?php echo number_format($song['downloads'] ?? 0); ?> downloads</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
