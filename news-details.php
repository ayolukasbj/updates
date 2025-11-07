<?php
/**
 * News Details Page
 * Displays individual news article by slug or ID
 * Layout inspired by jnews.io professional news theme
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set execution limits
set_time_limit(30);
ini_set('max_execution_time', 30);

// Find base directory
$base_dir = __DIR__;
if (defined('CALLED_FROM_NEWS_FOLDER')) {
    $base_dir = dirname($base_dir);
}

// Load configuration
$config_path = null;
$possible_config_paths = [
    $base_dir . '/config/config.php',
    dirname(__DIR__) . '/config/config.php',
    __DIR__ . '/config/config.php',
    'config/config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/config/config.php',
];

foreach ($possible_config_paths as $path) {
    if (file_exists($path)) {
        $config_path = $path;
        $base_dir = dirname(dirname($path));
        break;
    }
}

if ($config_path && file_exists($config_path)) {
    require_once $config_path;
} else {
    $common_paths = [
        dirname(dirname(__FILE__)) . '/config/config.php',
        realpath(dirname(__FILE__) . '/../config/config.php'),
    ];
    
    $found = false;
    foreach ($common_paths as $path) {
        if ($path && file_exists($path)) {
            require_once $path;
            $base_dir = dirname(dirname($path));
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        error_log('ERROR: config/config.php not found. Tried: ' . implode(', ', $possible_config_paths));
        http_response_code(500);
        die('Configuration file not found. Please check that config/config.php exists.');
    }
}

// Load database
$db = null;
$conn = null;
try {
    $possible_db_paths = [
        $base_dir . '/config/database.php',
        dirname($config_path) . '/database.php',
        __DIR__ . '/config/database.php',
        'config/database.php',
        $_SERVER['DOCUMENT_ROOT'] . '/config/database.php',
    ];
    
    $db_path = null;
    foreach ($possible_db_paths as $path) {
        if (file_exists($path)) {
            $db_path = $path;
            break;
        }
    }
    
    if ($db_path && file_exists($db_path)) {
        require_once $db_path;
        $db = new Database();
        $conn = $db->getConnection();
    } else {
        throw new Exception('Database config not found. Tried: ' . implode(', ', $possible_db_paths));
    }
} catch (Exception $e) {
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Please check error logs.');
}

// Get slug or ID from URL
$news_slug = $_GET['slug'] ?? '';
$news_id = $_GET['id'] ?? '';
$news_item = null;

// Fetch news article
try {
    if (!empty($news_slug)) {
        $stmt = $conn->prepare("
            SELECT n.*, COALESCE(u.username, 'Unknown') as author 
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id 
            WHERE n.slug = ? AND n.is_published = 1
            LIMIT 1
        ");
        $stmt->execute([$news_slug]);
        $news_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$news_item) {
            $decoded_slug = urldecode($news_slug);
            if ($decoded_slug !== $news_slug) {
                $stmt->execute([$decoded_slug]);
                $news_item = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        
        if (!$news_item) {
            $title_search = str_replace('-', ' ', $news_slug);
            $stmt = $conn->prepare("
                SELECT n.*, COALESCE(u.username, 'Unknown') as author 
                FROM news n 
                LEFT JOIN users u ON n.author_id = u.id 
                WHERE n.title LIKE ? AND n.is_published = 1
                LIMIT 1
            ");
            $stmt->execute(['%' . $title_search . '%']);
            $news_item = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } elseif (!empty($news_id)) {
        $stmt = $conn->prepare("
            SELECT n.*, COALESCE(u.username, 'Unknown') as author 
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id 
            WHERE n.id = ? AND n.is_published = 1
        ");
        $stmt->execute([$news_id]);
        $news_item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$news_item) {
        $news_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/news.php' : '/news.php';
        if (!headers_sent()) {
            header('Location: ' . $news_url);
            exit;
        } else {
            echo '<script>window.location.href = "' . htmlspecialchars($news_url) . '";</script>';
            exit;
        }
    }
    
    // Increment view count
    try {
        $view_stmt = $conn->prepare("UPDATE news SET views = views + 1 WHERE id = ?");
        $view_stmt->execute([$news_item['id']]);
        $news_item['views'] = ($news_item['views'] ?? 0) + 1;
    } catch (Exception $e) {
        error_log('View count error: ' . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log('Error fetching news: ' . $e->getMessage());
    http_response_code(500);
    die('Error loading news article. Please check error logs.');
}

// Get related news
$related_news = [];
try {
    $related_stmt = $conn->prepare("
        SELECT n.*, COALESCE(u.username, 'Unknown') as author 
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id 
        WHERE n.category = ? AND n.id != ? AND n.is_published = 1 
        ORDER BY n.created_at DESC 
        LIMIT 4
    ");
    $related_stmt->execute([$news_item['category'] ?? 'News', $news_item['id']]);
    $related_news = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching related news: ' . $e->getMessage());
}

// Get latest news for sidebar
$latest_news = [];
try {
    $latest_stmt = $conn->prepare("
        SELECT n.*, COALESCE(u.username, 'Unknown') as author 
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id 
        WHERE n.is_published = 1 AND n.id != ?
        ORDER BY n.created_at DESC 
        LIMIT 5
    ");
    $latest_stmt->execute([$news_item['id']]);
    $latest_news = $latest_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching latest news: ' . $e->getMessage());
}

// Helper function for asset paths
if (!function_exists('asset_path')) {
    function asset_path($path) {
        if (empty($path)) return '';
        if (strpos($path, 'http') === 0) return $path;
        $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
        return $base . '/' . ltrim($path, '/');
    }
}

// Helper function for display ad
if (!function_exists('displayAd')) {
    function displayAd($position) {
        return '';
    }
}

// Current URL
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Social sharing data
$share_title = htmlspecialchars($news_item['title']);
$share_description = !empty($news_item['share_excerpt']) 
    ? htmlspecialchars($news_item['share_excerpt']) 
    : (!empty($news_item['excerpt']) 
        ? htmlspecialchars(strip_tags($news_item['excerpt'])) 
        : htmlspecialchars(substr(strip_tags($news_item['content'] ?? ''), 0, 200)));
$share_image = !empty($news_item['featured_image']) 
    ? asset_path($news_item['featured_image']) 
    : (defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/assets/images/default-news.jpg' : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $share_title; ?> - <?php echo defined('SITE_NAME') ? SITE_NAME : 'News'; ?></title>
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo htmlspecialchars($current_url); ?>">
    <meta property="og:title" content="<?php echo $share_title; ?>">
    <meta property="og:description" content="<?php echo $share_description; ?>">
    <?php if (!empty($share_image)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($share_image); ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?php echo htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : ''); ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $share_title; ?>">
    <meta name="twitter:description" content="<?php echo $share_description; ?>">
    <?php if (!empty($share_image)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($share_image); ?>">
    <?php endif; ?>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/luo-style.css" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #222;
            line-height: 1.6;
        }
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .article-container {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 40px;
            margin-top: 20px;
        }
        @media (max-width: 1024px) {
            .article-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }
        .article-main {
            background: #fff;
            padding: 0;
        }
        .article-header {
            padding: 40px 40px 30px;
            border-bottom: 1px solid #eee;
        }
        .article-category {
            display: inline-block;
            padding: 6px 12px;
            background: #e74c3c;
            color: white;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
            border-radius: 3px;
        }
        .article-title {
            font-size: 42px;
            font-weight: 700;
            line-height: 1.2;
            color: #222;
            margin-bottom: 20px;
        }
        .article-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 14px;
            color: #666;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .article-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .article-meta-item i {
            color: #999;
        }
        .article-author {
            font-weight: 600;
            color: #222;
        }
        .article-featured-image {
            width: 100%;
            margin: 0;
            padding: 0;
        }
        .article-featured-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        .article-body {
            padding: 40px;
            font-size: 18px;
            line-height: 1.8;
            color: #333;
        }
        .article-body p {
            margin-bottom: 24px;
        }
        .article-body h2 {
            font-size: 28px;
            font-weight: 700;
            margin: 40px 0 20px;
            color: #222;
            line-height: 1.3;
        }
        .article-body h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 35px 0 18px;
            color: #222;
            line-height: 1.3;
        }
        .article-body blockquote {
            border-left: 4px solid #e74c3c;
            padding: 20px 30px;
            margin: 30px 0;
            background: #f9f9f9;
            font-style: italic;
            color: #555;
            font-size: 20px;
        }
        .article-body img {
            max-width: 100%;
            height: auto;
            margin: 30px 0;
            border-radius: 4px;
        }
        .article-share {
            padding: 30px 40px;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        .share-stats {
            display: flex;
            gap: 30px;
            font-size: 14px;
            color: #666;
        }
        .share-stats-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .share-buttons {
            display: flex;
            gap: 12px;
        }
        .share-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-size: 18px;
            transition: transform 0.2s;
        }
        .share-btn:hover {
            transform: translateY(-2px);
        }
        .share-btn.facebook { background: #1877f2; }
        .share-btn.twitter { background: #1da1f2; }
        .share-btn.whatsapp { background: #25d366; }
        .author-box {
            padding: 40px;
            background: #f9f9f9;
            border-top: 1px solid #eee;
        }
        .author-box-content {
            display: flex;
            gap: 25px;
            align-items: flex-start;
        }
        .author-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #ddd;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: #999;
        }
        .author-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .author-info h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #222;
        }
        .author-info p {
            font-size: 15px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 0;
        }
        .related-posts {
            padding: 40px;
            border-top: 1px solid #eee;
        }
        .related-posts h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 30px;
            color: #222;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 18px;
        }
        .related-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }
        @media (max-width: 768px) {
            .related-grid {
                grid-template-columns: 1fr;
            }
        }
        .related-post-item {
            display: flex;
            gap: 15px;
        }
        .related-post-thumb {
            width: 120px;
            height: 90px;
            flex-shrink: 0;
            border-radius: 4px;
            overflow: hidden;
            background: #eee;
        }
        .related-post-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .related-post-content h4 {
            font-size: 16px;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 8px;
        }
        .related-post-content h4 a {
            color: #222;
            text-decoration: none;
            transition: color 0.2s;
        }
        .related-post-content h4 a:hover {
            color: #e74c3c;
        }
        .related-post-meta {
            font-size: 13px;
            color: #999;
        }
        .sidebar {
            position: sticky;
            top: 20px;
            align-self: start;
        }
        @media (max-width: 1024px) {
            .sidebar {
                position: static;
            }
        }
        .sidebar-widget {
            background: #fff;
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .sidebar-widget h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 25px;
            color: #222;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 14px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e74c3c;
        }
        .widget-post-list {
            list-style: none;
        }
        .widget-post-item {
            display: flex;
            gap: 15px;
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .widget-post-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .widget-post-thumb {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            border-radius: 4px;
            overflow: hidden;
            background: #eee;
        }
        .widget-post-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .widget-post-content h4 {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 8px;
        }
        .widget-post-content h4 a {
            color: #222;
            text-decoration: none;
            transition: color 0.2s;
        }
        .widget-post-content h4 a:hover {
            color: #e74c3c;
        }
        .widget-post-meta {
            font-size: 12px;
            color: #999;
        }
        .article-tags {
            padding: 30px 40px;
            border-top: 1px solid #eee;
        }
        .article-tags h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #222;
            text-transform: uppercase;
        }
        .tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .tag {
            padding: 6px 12px;
            background: #f5f5f5;
            color: #666;
            font-size: 13px;
            border-radius: 3px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .tag:hover {
            background: #e74c3c;
            color: white;
        }
        @media (max-width: 768px) {
            .article-header,
            .article-body,
            .article-share,
            .author-box,
            .related-posts,
            .article-tags {
                padding-left: 20px;
                padding-right: 20px;
            }
            .article-title {
                font-size: 28px;
            }
            .article-body {
                font-size: 16px;
            }
            .share-stats {
                width: 100%;
            }
            .share-buttons {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php
    // Include header with robust error handling
    try {
        $header_path = $base_dir . '/includes/header.php';
        if (!file_exists($header_path)) {
            $header_path = 'includes/header.php';
        }
        if (file_exists($header_path)) {
            if (!defined('SKIP_MAINTENANCE_CHECK')) {
                define('SKIP_MAINTENANCE_CHECK', true);
            }
            include $header_path;
        } else {
            echo '<div style="padding: 20px; background: #333; color: white;"><a href="index.php" style="color: white; text-decoration: none;">' . htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'Home') . '</a></div>';
        }
    } catch (Exception $e) {
        error_log('Error including header.php: ' . $e->getMessage());
        echo '<div style="padding: 20px; background: #333; color: white;"><a href="index.php" style="color: white; text-decoration: none;">Home</a></div>';
    } catch (Error $e) {
        error_log('Fatal error including header.php: ' . $e->getMessage());
        echo '<div style="padding: 20px; background: #333; color: white;"><a href="index.php" style="color: white; text-decoration: none;">Home</a></div>';
    }
    ?>
    
    <div class="main-content">
        <div class="article-container">
            <article class="article-main">
                <div class="article-header">
                    <?php if (!empty($news_item['category'])): ?>
                    <span class="article-category"><?php echo htmlspecialchars($news_item['category']); ?></span>
                    <?php endif; ?>
                    
                    <h1 class="article-title"><?php echo htmlspecialchars($news_item['title']); ?></h1>
                    
                    <div class="article-meta">
                        <div class="article-meta-item">
                            <i class="fas fa-user"></i>
                            <span class="article-author"><?php echo htmlspecialchars($news_item['author'] ?? 'Unknown'); ?></span>
                        </div>
                        <div class="article-meta-item">
                            <i class="far fa-calendar"></i>
                            <span><?php echo date('F j, Y', strtotime($news_item['created_at'])); ?></span>
                        </div>
                        <div class="article-meta-item">
                            <i class="far fa-eye"></i>
                            <span><?php echo number_format($news_item['views'] ?? 0); ?> Views</span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($news_item['featured_image'])): ?>
                <div class="article-featured-image">
                    <img src="<?php echo htmlspecialchars(asset_path($news_item['featured_image'])); ?>" 
                         alt="<?php echo htmlspecialchars($news_item['title']); ?>">
                </div>
                <?php endif; ?>

                <div class="article-body">
                    <?php echo $news_item['content']; ?>
                </div>

                <div class="article-share">
                    <div class="share-stats">
                        <div class="share-stats-item">
                            <i class="fas fa-share"></i>
                            <span><?php echo number_format($news_item['views'] ?? 0); ?> SHARES</span>
                        </div>
                        <div class="share-stats-item">
                            <i class="far fa-eye"></i>
                            <span><?php echo number_format($news_item['views'] ?? 0); ?> VIEWS</span>
                        </div>
                    </div>
                    <div class="share-buttons">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>" 
                           target="_blank" class="share-btn facebook" title="Share on Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($current_url); ?>&text=<?php echo urlencode($share_title); ?>" 
                           target="_blank" class="share-btn twitter" title="Share on Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode($share_title . ' ' . $current_url); ?>" 
                           target="_blank" class="share-btn whatsapp" title="Share on WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                </div>

                <?php if (!empty($news_item['category'])): ?>
                <div class="article-tags">
                    <h4>Tags</h4>
                    <div class="tags-list">
                        <a href="news.php?category=<?php echo urlencode($news_item['category']); ?>" class="tag">
                            <?php echo htmlspecialchars($news_item['category']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="author-box">
                    <div class="author-box-content">
                        <div class="author-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="author-info">
                            <h3><?php echo htmlspecialchars($news_item['author'] ?? 'Unknown'); ?></h3>
                            <p>Author of this article. Share a little biographical information to fill out your profile. This may be shown publicly.</p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($related_news)): ?>
                <div class="related-posts">
                    <h3>Related Posts</h3>
                    <div class="related-grid">
                        <?php foreach ($related_news as $related): ?>
                        <div class="related-post-item">
                            <?php if (!empty($related['featured_image']) || !empty($related['image'])): ?>
                            <div class="related-post-thumb">
                                <a href="<?php echo !empty($related['slug']) ? 'news-details.php?slug=' . urlencode($related['slug']) : 'news-details.php?id=' . $related['id']; ?>">
                                    <img src="<?php echo htmlspecialchars(asset_path($related['featured_image'] ?? $related['image'])); ?>" 
                                         alt="<?php echo htmlspecialchars($related['title']); ?>">
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="related-post-thumb" style="display: flex; align-items: center; justify-content: center; color: #ccc;">
                                <i class="fas fa-newspaper" style="font-size: 30px;"></i>
                            </div>
                            <?php endif; ?>
                            <div class="related-post-content">
                                <h4>
                                    <a href="<?php echo !empty($related['slug']) ? 'news-details.php?slug=' . urlencode($related['slug']) : 'news-details.php?id=' . $related['id']; ?>">
                                        <?php echo htmlspecialchars($related['title']); ?>
                                    </a>
                                </h4>
                                <div class="related-post-meta">
                                    <i class="far fa-calendar"></i> <?php echo date('M j, Y', strtotime($related['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </article>

            <aside class="sidebar">
                <?php if (!empty($latest_news)): ?>
                <div class="sidebar-widget">
                    <h3>Recent News</h3>
                    <ul class="widget-post-list">
                        <?php foreach ($latest_news as $latest): ?>
                        <li class="widget-post-item">
                            <?php if (!empty($latest['featured_image']) || !empty($latest['image'])): ?>
                            <div class="widget-post-thumb">
                                <a href="<?php echo !empty($latest['slug']) ? 'news-details.php?slug=' . urlencode($latest['slug']) : 'news-details.php?id=' . $latest['id']; ?>">
                                    <img src="<?php echo htmlspecialchars(asset_path($latest['featured_image'] ?? $latest['image'])); ?>" 
                                         alt="<?php echo htmlspecialchars($latest['title']); ?>">
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="widget-post-thumb" style="display: flex; align-items: center; justify-content: center; color: #ccc;">
                                <i class="fas fa-newspaper"></i>
                            </div>
                            <?php endif; ?>
                            <div class="widget-post-content">
                                <h4>
                                    <a href="<?php echo !empty($latest['slug']) ? 'news-details.php?slug=' . urlencode($latest['slug']) : 'news-details.php?id=' . $latest['id']; ?>">
                                        <?php echo htmlspecialchars($latest['title']); ?>
                                    </a>
                                </h4>
                                <div class="widget-post-meta">
                                    <i class="far fa-calendar"></i> <?php echo date('M j, Y', strtotime($latest['created_at'])); ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php
                // Display sidebar ad if exists
                try {
                    if (function_exists('displayAd')) {
                        $sidebar_ad = displayAd('news_sidebar');
                        if ($sidebar_ad): ?>
                            <div class="sidebar-widget">
                                <?php echo $sidebar_ad; ?>
                            </div>
                        <?php endif;
                    }
                } catch (Exception $e) {
                    // Ignore ad errors
                }
                ?>
            </aside>
        </div>
    </div>

    <?php
    // Include footer if exists
    $footer_path = $base_dir . '/includes/footer.php';
    if (!file_exists($footer_path)) {
        $footer_path = 'includes/footer.php';
    }
    if (file_exists($footer_path)) {
        include $footer_path;
    }
    ?>
</body>
</html>
