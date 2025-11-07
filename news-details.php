<?php
/**
 * News Details Page
 * Displays individual news article by slug or ID
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

// Find base directory - handle being called from news/ folder or root
$base_dir = __DIR__;
$config_path = null;

// If called from news/ folder
if (defined('CALLED_FROM_NEWS_FOLDER')) {
    $base_dir = dirname($base_dir);
}

// Try multiple paths for config file
$possible_config_paths = [
    $base_dir . '/config/config.php',  // Standard path
    dirname(__DIR__) . '/config/config.php',  // If called from subdirectory
    __DIR__ . '/config/config.php',  // If in root
    'config/config.php',  // Relative path
    $_SERVER['DOCUMENT_ROOT'] . '/config/config.php',  // Absolute from document root
];

foreach ($possible_config_paths as $path) {
    if (file_exists($path)) {
        $config_path = $path;
        $base_dir = dirname(dirname($path));  // Update base_dir based on found config
        break;
    }
}

// Load configuration
if ($config_path && file_exists($config_path)) {
    require_once $config_path;
} else {
    // Last attempt: check common locations
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
    // Try multiple paths for database config
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
        // Try exact match first
        $stmt = $conn->prepare("
            SELECT n.*, COALESCE(u.username, 'Unknown') as author 
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id 
            WHERE n.slug = ? AND n.is_published = 1
            LIMIT 1
        ");
        $stmt->execute([$news_slug]);
        $news_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If not found, try URL decoded version
        if (!$news_item) {
            $decoded_slug = urldecode($news_slug);
            if ($decoded_slug !== $news_slug) {
                $stmt->execute([$decoded_slug]);
                $news_item = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        
        // If still not found, try title search
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
        // Get by ID
        $stmt = $conn->prepare("
            SELECT n.*, COALESCE(u.username, 'Unknown') as author 
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id 
            WHERE n.id = ? AND n.is_published = 1
        ");
        $stmt->execute([$news_id]);
        $news_item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // If not found, redirect to news listing
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
            LIMIT 3
        ");
    $related_stmt->execute([$news_item['category'] ?? 'News', $news_item['id']]);
    $related_news = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching related news: ' . $e->getMessage());
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
    
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/luo-style.css" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #333;
            line-height: 1.6;
            padding-bottom: 120px;
        }
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px 20px;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
            margin-top: 24px;
        }
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        .post-content {
            background: #fff;
            border-radius: 6px;
            border: 1px solid #eee;
            padding: 24px;
        }
        .post-title {
            font-size: 30px;
            font-weight: 800;
            line-height: 1.3;
            color: #222;
            margin: 8px 0 14px;
        }
        .post-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 13px;
            color: #777;
            border-top: 1px solid #f1f1f1;
            padding-top: 14px;
        }
        .post-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .post-hero {
            margin: 18px 0 0;
        }
        .post-hero img {
            width: 100%;
            height: auto;
            border-radius: 6px;
            display: block;
        }
        .post-content .news-body {
            font-size: 16px;
            line-height: 1.85;
            color: #222;
            margin-top: 20px;
        }
        .post-content .news-body p {
            margin: 0 0 18px;
        }
        blockquote {
            border-left: 3px solid #222;
            background: #fafafa;
            padding: 14px 16px;
            margin: 18px 0;
            color: #333;
            font-style: italic;
        }
        .post-share {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .post-share a {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            border-radius: 50%;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
        }
        .post-share a:hover {
            background: #e9e9e9;
            transform: translateY(-2px);
        }
        .post-share .facebook { color: #1877f2; }
        .post-share .twitter { color: #1da1f2; }
        .post-share .whatsapp { color: #25d366; }
        .sidebar-sticky {
            position: sticky;
            top: 20px;
        }
        @media (max-width: 992px) {
            .sidebar-sticky {
                position: static;
            }
        }
        .sidebar-widget {
            background: #fff;
            border-radius: 6px;
            border: 1px solid #eee;
            padding: 20px;
            margin-bottom: 20px;
        }
        .sidebar-widget h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #222;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .widget-list .item {
            display: grid;
            grid-template-columns: 80px 1fr;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f4f4f4;
        }
        .widget-list .item:last-child {
            border-bottom: none;
        }
        .widget-list .thumb {
            width: 80px;
            height: 60px;
            border-radius: 4px;
            object-fit: cover;
            background: #e9eef3;
        }
        .widget-list .title {
            font-weight: 600;
            font-size: 14px;
            line-height: 1.4;
            color: #222;
            text-decoration: none;
            display: block;
        }
        .widget-list .title:hover {
            color: #007bff;
        }
        .widget-list .meta {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
        .author-box {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 18px;
            border: 1px solid #eee;
            border-radius: 6px;
            background: #fff;
            margin-top: 24px;
        }
        .author-box .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e9eef3;
            display: inline-block;
            flex-shrink: 0;
        }
        .author-box .name {
            font-weight: 700;
            margin-bottom: 6px;
            font-size: 16px;
            color: #333;
        }
        .author-box .bio {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
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
            // Minimal header if header.php doesn't exist
            echo '<div style="padding: 20px; background: #333; color: white;">';
            echo '<a href="index.php" style="color: white; text-decoration: none;">' . htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'Home') . '</a>';
            echo '</div>';
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
        <div class="content-grid">
            <div class="post-content">
                <h1 class="post-title"><?php echo htmlspecialchars($news_item['title']); ?></h1>
                
                <div class="post-meta">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($news_item['author'] ?? 'Unknown'); ?></span>
                    <span><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($news_item['created_at'])); ?></span>
                    <?php if (!empty($news_item['category'])): ?>
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($news_item['category']); ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-eye"></i> <?php echo number_format($news_item['views'] ?? 0); ?> views</span>
                </div>

                <?php if (!empty($news_item['featured_image'])): ?>
                <div class="post-hero">
                    <img src="<?php echo htmlspecialchars(asset_path($news_item['featured_image'])); ?>" 
                         alt="<?php echo htmlspecialchars($news_item['title']); ?>">
                </div>
                <?php endif; ?>
                
                <div class="news-body">
                    <?php echo $news_item['content']; ?>
                </div>

                <div class="post-share">
                    <span style="font-weight: 600; color: #555;">Share:</span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>" 
                       target="_blank" class="facebook" title="Share on Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($current_url); ?>&text=<?php echo urlencode($share_title); ?>" 
                       target="_blank" class="twitter" title="Share on Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://wa.me/?text=<?php echo urlencode($share_title . ' ' . $current_url); ?>" 
                       target="_blank" class="whatsapp" title="Share on WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>

                <div class="author-box">
                    <div class="avatar">
                        <i class="fas fa-user-circle" style="font-size: 80px; color: #ccc;"></i>
                    </div>
                    <div>
                        <div class="name"><?php echo htmlspecialchars($news_item['author'] ?? 'Unknown'); ?></div>
                        <div class="bio">Author of this article</div>
                    </div>
                </div>
            </div>

            <div class="sidebar-sticky">
                <?php if (!empty($related_news)): ?>
                <div class="sidebar-widget">
                    <h3>Related News</h3>
                    <div class="widget-list">
                        <?php foreach ($related_news as $related): ?>
                        <div class="item">
                            <?php if (!empty($related['featured_image']) || !empty($related['image'])): ?>
                            <img src="<?php echo htmlspecialchars(asset_path($related['featured_image'] ?? $related['image'])); ?>" 
                                 alt="<?php echo htmlspecialchars($related['title']); ?>" 
                                 class="thumb">
                            <?php else: ?>
                            <div class="thumb" style="display: flex; align-items: center; justify-content: center; color: #999;">
                                <i class="fas fa-newspaper"></i>
                            </div>
                            <?php endif; ?>
                            <div>
                                <a href="<?php echo !empty($related['slug']) ? 'news-details.php?slug=' . urlencode($related['slug']) : 'news-details.php?id=' . $related['id']; ?>" class="title">
                                    <?php echo htmlspecialchars($related['title']); ?>
                                </a>
                                <div class="meta">
                                    <i class="far fa-calendar"></i> <?php echo date('M j, Y', strtotime($related['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
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
            </div>
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
