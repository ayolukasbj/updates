<?php
/**
 * User Dashboard
 * Main dashboard page for logged-in users
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load configuration
if (!file_exists('config/config.php')) {
    error_log('ERROR: config/config.php not found in dashboard.php');
    http_response_code(500);
    die('Configuration file not found.');
}
require_once 'config/config.php';

// Load database
try {
    if (!file_exists('config/database.php')) {
        throw new Exception('Database config not found');
    }
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Make connection available globally for is_user_verified() function
    $GLOBALS['conn'] = $conn;
} catch (Exception $e) {
    error_log('Database error in dashboard.php: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Please check error logs.');
}

// Check if user is logged in and verified
try {
    require_login();
} catch (Exception $e) {
    error_log('Dashboard require_login error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    // Fallback: just check if user is logged in
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
} catch (Error $e) {
    error_log('Dashboard require_login fatal error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    // Fallback: just check if user is logged in
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

$user_id = $_SESSION['user_id'];

// Get user data
try {
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Error fetching user in dashboard.php: ' . $e->getMessage());
    http_response_code(500);
    die('Error loading user data. Please check error logs.');
}

// Check if admin - redirect to admin dashboard
if (isset($user['role']) && in_array($user['role'], ['admin', 'super_admin'])) {
    if (!isset($_SESSION['admin_impersonating']) || !$_SESSION['admin_impersonating']) {
    header('Location: admin/index.php');
    exit;
    }
}

// Check if artist - redirect to artist profile
if (isset($user['role']) && $user['role'] === 'artist') {
    header('Location: artist-profile-mobile.php');
    exit;
}

// Get user stats
$stats = [
    'total_playlists' => 0,
    'total_favorites' => 0,
    'total_plays' => 0
];

try {
    // Check which favorites table exists
$favorites_table = 'user_favorites';
    $check_stmt = $conn->query("SHOW TABLES LIKE 'favorites'");
    if ($check_stmt && $check_stmt->rowCount() > 0) {
        $favorites_table = 'favorites';
}

// Check if play_history table exists
$play_history_exists = false;
    $check_ph = $conn->query("SHOW TABLES LIKE 'play_history'");
    if ($check_ph && $check_ph->rowCount() > 0) {
        $play_history_exists = true;
}

    // Build stats query
$stats_query = "
    SELECT 
        COUNT(DISTINCT p.id) as total_playlists,
        COUNT(DISTINCT f.id) as total_favorites" . 
            ($play_history_exists ? ", COUNT(DISTINCT ph.id) as total_plays" : ", 0 as total_plays") . "
    FROM users u
    LEFT JOIN playlists p ON p.user_id = u.id
    LEFT JOIN $favorites_table f ON f.user_id = u.id" . 
        ($play_history_exists ? " LEFT JOIN play_history ph ON ph.user_id = u.id" : "") . "
    WHERE u.id = ?
";

$stmt = $conn->prepare($stats_query);
$stmt->execute([$user_id]);
    $stats_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats_result) {
        $stats = $stats_result;
    }
} catch (Exception $e) {
    error_log('Error fetching stats: ' . $e->getMessage());
}

// Get latest news
$latest_news = [];
try {
    $news_stmt = $conn->query("SELECT * FROM news WHERE is_published = 1 ORDER BY created_at DESC LIMIT 3");
    if ($news_stmt) {
        $latest_news = $news_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Error fetching latest news: ' . $e->getMessage());
}

// Check impersonation
$is_impersonating = isset($_SESSION['admin_impersonating']) && $_SESSION['admin_impersonating'];
$admin_username = $is_impersonating ? ($_SESSION['admin_original_username'] ?? 'Admin') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> - Dashboard | <?php echo defined('SITE_NAME') ? SITE_NAME : 'Dashboard'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
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
        .profile-container {
            padding: 20px 15px;
        }
        .edit-btn {
            position: absolute;
            top: 20px;
            right: 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #333;
            text-decoration: none;
        }
        .avatar-section {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #ddd;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: #999;
            overflow: hidden;
        }
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-name {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .user-email {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 25px;
        }
        .btn {
            padding: 15px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            display: block;
            cursor: pointer;
        }
        .btn-primary {
            background: #ff6600;
            color: white;
        }
        .btn-secondary {
            background: #2196F3;
            color: white;
        }
        .news-section {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 60px;
        }
        .news-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .news-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .news-item:last-child {
            border-bottom: none;
        }
        .news-title {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            text-decoration: none;
            display: block;
        }
        .news-title:hover {
            color: #ff6600;
        }
        .news-excerpt {
            font-size: 13px;
            color: #666;
            line-height: 1.4;
            margin-bottom: 5px;
        }
        .news-date {
            font-size: 12px;
            color: #999;
        }
        .no-news {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
        }
        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            color: #666;
            text-decoration: none;
            font-size: 20px;
        }
        .bottom-nav-item.active {
            color: #ff6600;
        }
        .impersonation-banner {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 15px 20px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .impersonation-banner-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }
        .impersonation-banner-text {
            flex: 1;
            font-size: 14px;
            font-weight: 600;
        }
        .impersonation-banner-btn {
            background: white;
            color: #ff6b6b;
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php if ($is_impersonating): ?>
    <div class="impersonation-banner">
        <div class="impersonation-banner-content">
            <div class="impersonation-banner-text">
                <i class="fas fa-user-secret"></i>
                You are viewing as <strong><?php echo htmlspecialchars($user['username']); ?></strong> (Admin: <?php echo htmlspecialchars($admin_username); ?>)
            </div>
            <a href="admin/stop-impersonating.php" class="impersonation-banner-btn">
                <i class="fas fa-sign-out-alt"></i> Switch Back to Admin
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="header">
        <div class="header-left">
            <div class="site-logo">
                <i class="fas fa-music"></i>
            </div>
            <div class="site-name"><?php echo defined('SITE_NAME') ? SITE_NAME : 'Music'; ?></div>
        </div>
        <div class="header-right">
            <a href="profile.php">Account</a>
            <span>|</span>
            <a href="logout.php">Log out</a>
        </div>
    </div>
    
    <div class="nav-tabs">
        <a href="dashboard.php" class="nav-tab active">DASHBOARD</a>
        <a href="my-playlists.php" class="nav-tab">PLAYLISTS</a>
        <a href="favorites.php" class="nav-tab">FAVORITES</a>
        <a href="recently-played.php" class="nav-tab">HISTORY</a>
    </div>
    
    <div class="profile-container">
        <a href="profile.php" class="edit-btn">
            Edit <i class="fas fa-cog"></i>
        </a>
        
        <div class="avatar-section">
            <div class="avatar">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <h1 class="user-name"><?php echo htmlspecialchars($user['username']); ?></h1>
            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_playlists']); ?></div>
                <div class="stat-label">Playlists</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_favorites']); ?></div>
                <div class="stat-label">Favorites</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_plays']); ?></div>
                <div class="stat-label">Plays</div>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="browse.php" class="btn btn-primary">Discover Music</a>
            <a href="artists.php" class="btn btn-secondary">Browse Artists</a>
        </div>
        
        <div class="news-section">
            <div class="news-header">
                <i class="fas fa-newspaper"></i>
                Latest News
            </div>
            
            <?php if (!empty($latest_news)): ?>
                <?php foreach ($latest_news as $news_item): ?>
                    <div class="news-item">
                        <a href="news-details.php?id=<?php echo $news_item['id']; ?>" class="news-title">
                            <?php echo htmlspecialchars($news_item['title']); ?>
                        </a>
                        <div class="news-excerpt">
                            <?php 
                            $excerpt = strip_tags($news_item['content'] ?? '');
                            echo htmlspecialchars(substr($excerpt, 0, 100)) . '...'; 
                            ?>
                        </div>
                        <div class="news-date">
                            <i class="far fa-clock"></i> 
                            <?php echo date('M d, Y', strtotime($news_item['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="news.php" style="color: #ff6600; text-decoration: none; font-weight: 600;">
                        View All News <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="no-news">
                    <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 10px; display: block;"></i>
                    No news available at the moment
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bottom-nav">
        <a href="index.php" class="bottom-nav-item">
            <i class="fas fa-home"></i>
        </a>
        <a href="dashboard.php" class="bottom-nav-item active">
            <i class="fas fa-tachometer-alt"></i>
        </a>
        <a href="profile.php" class="bottom-nav-item">
            <i class="fas fa-user"></i>
        </a>
    </div>
</body>
</html>
