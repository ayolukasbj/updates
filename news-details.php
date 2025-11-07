<?php
// news-details.php - News Details Page
// Prevent infinite loops and set execution time limit
set_time_limit(30);
ini_set('max_execution_time', 30);

// Enable error reporting for debugging (but don't display on production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("FATAL ERROR in news-details.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html><head><title>Error</title><meta charset="UTF-8"></head><body>';
        echo '<h1>Error Loading News Page</h1>';
        echo '<p>Please check the error logs for details.</p>';
        echo '<p><a href="news.php">Go to News Listing</a></p>';
        echo '</body></html>';
        exit;
    }
});

// Start output buffering only if not already started (prevents nested buffers)
$ob_started_here = false;
if (ob_get_level() == 0) {
    ob_start();
    $ob_started_here = true;
}

// Determine base directory (handle being called from news/ folder)
$base_dir = dirname(__FILE__);
if (basename($base_dir) === 'news' || defined('CALLED_FROM_NEWS_FOLDER')) {
    $base_dir = dirname($base_dir);
}
// Also check if we're in a subdirectory
if (strpos($base_dir, 'news') !== false && basename($base_dir) !== 'news') {
    // We might be deeper, go up until we find the root
    while (basename($base_dir) !== '' && basename($base_dir) !== 'public_html' && basename($base_dir) !== 'htdocs') {
        $parent = dirname($base_dir);
        if ($parent === $base_dir) break; // Reached root
        $base_dir = $parent;
    }
}

// Load config with error handling
try {
    $config_path = $base_dir . '/config/config.php';
    if (!file_exists($config_path)) {
        // Try relative path as fallback
        $config_path = 'config/config.php';
        if (!file_exists($config_path)) {
            throw new Exception('Configuration file not found.');
        }
    }
    require_once $config_path;
    
    // Ensure required files exist
    $song_storage_path = $base_dir . '/includes/song-storage.php';
    if (!file_exists($song_storage_path)) {
        $song_storage_path = 'includes/song-storage.php';
    }
    if (file_exists($song_storage_path)) {
        require_once $song_storage_path;
    } else {
        error_log('Warning: includes/song-storage.php not found');
    }
    
    $theme_loader_path = $base_dir . '/includes/theme-loader.php';
    if (!file_exists($theme_loader_path)) {
        $theme_loader_path = 'includes/theme-loader.php';
    }
    if (file_exists($theme_loader_path)) {
        require_once $theme_loader_path;
    } else {
        error_log('Warning: includes/theme-loader.php not found');
    }
} catch (Exception $e) {
    error_log('Error loading config in news-details.php: ' . $e->getMessage());
    http_response_code(500);
    die('Error loading configuration. Please check error logs.');
}

// Safely include ads with error handling
try {
    $ads_path = $base_dir . '/includes/ads.php';
    if (!file_exists($ads_path)) {
        $ads_path = 'includes/ads.php';
    }
    if (file_exists($ads_path)) {
        require_once $ads_path;
    }
} catch (Exception $e) {
    error_log("Ads include error: " . $e->getMessage());
    // Define a fallback function if ads.php fails
    if (!function_exists('displayAd')) {
        function displayAd($position) { return ''; }
    }
}

$news_id = $_GET['id'] ?? '';
$news_slug = $_GET['slug'] ?? '';
$news_item = null;
$conn = null;

// Try to get news from database first
try {
    $db_config_path = $base_dir . '/config/database.php';
    if (!file_exists($db_config_path)) {
        $db_config_path = 'config/database.php';
        if (!file_exists($db_config_path)) {
            throw new Exception('Database config file not found');
        }
    }
    require_once $db_config_path;
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Core Author System: Admin is Priority 1 (always the author displayed)
    // Handle both slug and id parameters
    if (!empty($news_slug)) {
        // Get by slug - try exact match first, then try URL-decoded version
        $slug_variations = [
            $news_slug,
            urldecode($news_slug),
            str_replace('-', ' ', $news_slug),
            str_replace('-', '_', $news_slug)
        ];
        
        foreach ($slug_variations as $slug_to_try) {
            $stmt = $conn->prepare("
                SELECT n.*, COALESCE(u.username, 'Unknown') as author 
                FROM news n 
                LEFT JOIN users u ON n.author_id = u.id 
                WHERE (n.slug = ? OR n.slug LIKE ?) AND n.is_published = 1
                LIMIT 1
            ");
            $stmt->execute([$slug_to_try, '%' . $slug_to_try . '%']);
            $news_item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($news_item) {
                break; // Found it, stop trying
            }
        }
        
        // If still not found, try searching by title
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
        // Get by id
        $stmt = $conn->prepare("
            SELECT n.*, COALESCE(u.username, 'Unknown') as author 
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id 
            WHERE n.id = ? AND n.is_published = 1
        ");
        $stmt->execute([$news_id]);
        $news_item = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Fallback to JSON if not found in database
    if (!$news_item && !empty($news_id)) {
        $song_storage_path = $base_dir . '/includes/song-storage.php';
        if (!file_exists($song_storage_path)) {
            $song_storage_path = 'includes/song-storage.php';
        }
        if (file_exists($song_storage_path)) {
            require_once $song_storage_path;
        }
        if (function_exists('getAllNews')) {
            $all_news = getAllNews();
            foreach ($all_news as $news) {
                if ($news['id'] == $news_id) {
                    $news_item = $news;
                    break;
                }
            }
        }
    }
} catch (Exception $e) {
    // Fallback to JSON
    $song_storage_path = $base_dir . '/includes/song-storage.php';
    if (!file_exists($song_storage_path)) {
        $song_storage_path = 'includes/song-storage.php';
    }
    if (file_exists($song_storage_path)) {
        require_once $song_storage_path;
    }
    if (function_exists('getAllNews')) {
        $all_news = getAllNews();
        if (!empty($news_id)) {
            foreach ($all_news as $news) {
                if ($news['id'] == $news_id) {
                    $news_item = $news;
                    break;
                }
            }
        }
    }
}

if (!$news_item) {
    // Log the error for debugging
    error_log("News item not found. Slug: " . ($news_slug ?? 'empty') . ", ID: " . ($news_id ?? 'empty'));
    
    // Try to redirect to news listing page
    $news_url = defined('SITE_URL') ? SITE_URL . '/news.php' : '/news.php';
    if (!headers_sent()) {
        header('Location: ' . $news_url);
        exit;
    } else {
        echo '<script>window.location.href = "' . htmlspecialchars($news_url) . '";</script>';
        exit;
    }
}

// Get the news ID for related news query
$actual_news_id = $news_item['id'] ?? $news_id;

// Increment page views (only once per session per article)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$view_key = 'news_viewed_' . (int)$actual_news_id;
if (!isset($_SESSION[$view_key])) {
    try {
        if (isset($conn) && $conn && !empty($actual_news_id)) {
            // Ensure views column exists, if not create it
            try {
                $checkCol = $conn->query("SHOW COLUMNS FROM news LIKE 'views'");
                if ($checkCol->rowCount() == 0) {
                    $conn->exec("ALTER TABLE news ADD COLUMN views BIGINT DEFAULT 0");
                }
            } catch (Exception $e) {
                // Column might already exist, continue
            }
            
            // Increment views using atomic update
            $uv = $conn->prepare("UPDATE news SET views = IFNULL(views, 0) + 1 WHERE id = ?");
            $result = $uv->execute([(int)$actual_news_id]);
            
            // Fetch updated view count from database
            if ($result) {
                $viewsStmt = $conn->prepare("SELECT views FROM news WHERE id = ?");
                $viewsStmt->execute([(int)$actual_news_id]);
                $viewsResult = $viewsStmt->fetch(PDO::FETCH_ASSOC);
                if ($viewsResult && isset($viewsResult['views'])) {
                    $news_item['views'] = (int)$viewsResult['views'];
                } else {
                    // Fallback: increment locally if fetch fails
                    $news_item['views'] = (int)($news_item['views'] ?? 0) + 1;
                }
            } else {
                // If update failed, just increment locally
                $news_item['views'] = (int)($news_item['views'] ?? 0) + 1;
            }
            
            // Mark as viewed in this session
            $_SESSION[$view_key] = true;
        }
    } catch (Exception $e) {
        error_log("View counter error: " . $e->getMessage());
        // Increment locally as fallback
        if (isset($news_item['views'])) {
            $news_item['views'] = (int)$news_item['views'] + 1;
        }
    }
} else {
    // Already viewed in this session, fetch current count from database
    try {
        if (isset($conn) && $conn && !empty($actual_news_id)) {
            $viewsStmt = $conn->prepare("SELECT views FROM news WHERE id = ?");
            $viewsStmt->execute([(int)$actual_news_id]);
            $viewsResult = $viewsStmt->fetch(PDO::FETCH_ASSOC);
            if ($viewsResult && isset($viewsResult['views'])) {
                $news_item['views'] = (int)$viewsResult['views'];
            }
        }
    } catch (Exception $e) {
        // If fetch fails, keep existing value
        if (isset($news_item['views'])) { 
            $news_item['views'] = (int)$news_item['views']; 
        }
    }
}

// Determine display author: Always use admin user (ID 7)
$displayAuthorName = 'Admin';
$displayAuthorAvatar = '';
$displayAuthorBio = '';
try {
    if (!isset($conn) || !$conn) {
        require_once 'config/database.php';
        $dbTmp = new Database();
        $conn = $dbTmp->getConnection();
    }
    if ($conn) {
        // Get admin user with ID 7
        $stmt = $conn->prepare("
            SELECT username, avatar, bio FROM users 
            WHERE id = 7
            LIMIT 1
        ");
        $stmt->execute();
        $ar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ar && !empty($ar['username'])) {
            $displayAuthorName = $ar['username'];
            $displayAuthorAvatar = $ar['avatar'] ?? '';
            $displayAuthorBio = $ar['bio'] ?? 'Share a little biographical information to fill out your profile. Such a coffee drinker, a late night sleeper, or whatever sound clumsy.';
        }
    }
} catch (Exception $e) {
    error_log("Admin user query error: " . $e->getMessage());
}

// Ensure asset_path function exists before using it
if (!function_exists('asset_path')) {
    function asset_path($path) {
        if (empty($path)) return '';
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
        $baseUrl = rtrim($protocol . $host . $base_path, '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

// Build current absolute URL for sharing
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_path = defined('BASE_PATH') ? BASE_PATH : '/';
$requestUri = $_SERVER['REQUEST_URI'] ?? ($base_path . 'news-details.php?id=' . urlencode($actual_news_id));
$currentUrl = $scheme . '://' . $host . $requestUri;

// Get related news from database
$related_news = [];
try {
    if (isset($conn) && $conn) {
        $relatedStmt = $conn->prepare("
            SELECT n.*, COALESCE(u.username, 'Unknown') as author 
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id 
            WHERE n.category = ? AND n.id != ? AND n.is_published = 1 
            ORDER BY n.created_at DESC 
            LIMIT 3
        ");
        $relatedStmt->execute([$news_item['category'] ?? 'News', $actual_news_id]);
        $related_news = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Fallback to JSON if database fails or no results
    if (empty($related_news)) {
        $song_storage_path = $base_dir . '/includes/song-storage.php';
        if (!file_exists($song_storage_path)) {
            $song_storage_path = 'includes/song-storage.php';
        }
        if (file_exists($song_storage_path)) {
            require_once $song_storage_path;
        }
        if (function_exists('getAllNews')) {
            $all_news = getAllNews();
            $related_news = array_filter($all_news, function($n) use ($news_item, $actual_news_id) {
                return $n['category'] == $news_item['category'] && $n['id'] != $actual_news_id;
            });
            $related_news = array_slice($related_news, 0, 3);
        }
    }
} catch (Exception $e) {
    // Fallback to empty array
    $related_news = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($news_item['title']); ?> - <?php echo SITE_NAME; ?></title>
    
    <?php
    // Social sharing meta tags
    $newsShareUrl = $currentUrl;
    $newsShareTitle = htmlspecialchars($news_item['title']);
    $newsShareDescription = !empty($news_item['share_excerpt']) ? htmlspecialchars($news_item['share_excerpt']) : (!empty($news_item['excerpt']) ? htmlspecialchars(strip_tags($news_item['excerpt'])) : (!empty($news_item['content']) ? htmlspecialchars(strip_tags(substr($news_item['content'], 0, 200))) : htmlspecialchars($news_item['title'] . ' - ' . SITE_NAME));
    $newsShareImage = !empty($news_item['featured_image']) ? asset_path($news_item['featured_image']) : (!empty($news_item['image']) ? asset_path($news_item['image']) : (defined('SITE_URL') ? SITE_URL . '/assets/images/default-news.jpg' : ''));
    ?>
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo htmlspecialchars($newsShareUrl); ?>">
    <meta property="og:title" content="<?php echo $newsShareTitle; ?>">
    <meta property="og:description" content="<?php echo $newsShareDescription; ?>">
    <?php if (!empty($newsShareImage)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($newsShareImage); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>
    <meta property="og:site_name" content="<?php echo htmlspecialchars(SITE_NAME); ?>">
    <?php if (!empty($displayAuthorName)): ?>
    <meta property="article:author" content="<?php echo htmlspecialchars($displayAuthorName); ?>">
    <?php endif; ?>
    <?php if (!empty($news_item['created_at'])): ?>
    <meta property="article:published_time" content="<?php echo date('c', strtotime($news_item['created_at'])); ?>">
    <?php endif; ?>
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($newsShareUrl); ?>">
    <meta name="twitter:title" content="<?php echo $newsShareTitle; ?>">
    <meta name="twitter:description" content="<?php echo $newsShareDescription; ?>">
    <?php if (!empty($newsShareImage)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($newsShareImage); ?>">
    <?php endif; ?>
    
    <!-- Additional meta tags -->
    <meta name="description" content="<?php echo $newsShareDescription; ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/luo-style.css" rel="stylesheet">
    <?php renderThemeStyles(); ?>
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

        .page-wrap {
            max-width: 1160px;
            margin: 0 auto;
            padding: 24px 16px;
        }

        .breadcrumb {
            font-size: 12px;
            color: #888;
            margin-bottom: 16px;
        }

        .breadcrumb a { color: #888; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .post-header {
            background: #fff;
            border-radius: 6px;
            padding: 24px;
            border: 1px solid #eee;
        }

        .post-title { font-size: 30px; font-weight: 800; line-height: 1.3; color: #222; margin: 8px 0 14px; }
        .post-meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 13px; color: #777; border-top: 1px solid #f1f1f1; padding-top: 14px; }
        .post-meta span { display: inline-flex; align-items: center; gap: 6px; }

        .post-share { margin-left: auto; display: inline-flex; gap: 8px; }
        .post-share a { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; background: #f5f5f5; border-radius: 50%; color: #555; text-decoration: none; }
        .post-share a:hover { background: #e9e9e9; }

        .post-hero { margin: 18px 0 0; }
        .post-hero img { width: 100%; height: auto; border-radius: 6px; display: block; }
        .post-hero .placeholder { width: 100%; height: 420px; border-radius: 6px; background: #e9eef3; color: #7a8793; display: flex; align-items: center; justify-content: center; font-size: 64px; }

        /* Right sidebar sticky is enough; remove left rail */

        .content-grid { display: grid; grid-template-columns: 1fr 320px; gap: 24px; margin-top: 24px; }
        @media (max-width: 992px) { .content-grid { grid-template-columns: 1fr; } }

        .post-content { background: #fff; border-radius: 6px; border: 1px solid #eee; padding: 24px; }
        .post-content .news-body { font-size: 16px; line-height: 1.85; color: #222; }
        .post-content .news-body p { margin: 0 0 18px; }
        blockquote { border-left: 3px solid #222; background: #fafafa; padding: 14px 16px; margin: 18px 0; color: #333; font-style: italic; }

        .post-tags { margin-top: 24px; }
        .post-tags a { display: inline-block; padding: 6px 10px; background: #f3f3f3; border-radius: 3px; color: #555; font-size: 12px; margin: 0 8px 8px 0; text-decoration: none; }

        .author-box { display: flex; align-items: flex-start; gap: 20px; padding: 18px; border: 1px solid #eee; border-radius: 6px; background: #fff; margin-top: 24px; }
        .author-box .avatar { width: 80px; height: 80px; border-radius: 50%; background: #e9eef3; display: inline-block; flex-shrink: 0; }
        .author-box .name { font-weight: 700; margin-bottom: 6px; font-size: 16px; color: #333; }
        .author-box .bio { color: #666; font-size: 14px; line-height: 1.6; }

        .post-nav { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 24px; }
        .post-nav a { display: block; background: #fff; border: 1px solid #eee; border-radius: 6px; padding: 14px; color: #333; text-decoration: none; }
        .post-nav a small { display: block; color: #999; margin-bottom: 6px; }
        .post-nav a:hover { background: #fafafa; }

        .widget { background: #fff; border: 1px solid #eee; border-radius: 6px; margin-bottom: 16px; }
        .widget .widget-title { font-weight: 800; font-size: 14px; padding: 14px 16px; border-bottom: 1px solid #f2f2f2; }
        .widget .widget-body { padding: 14px 16px; }

        .widget-social { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .widget-social a { display: grid; place-items: center; height: 70px; color: #fff; border-radius: 4px; text-decoration: none; font-weight: 700; }
        .bg-fb { background: #3b5998; } .bg-tw { background: #1da1f2; } .bg-yt { background: #ff0000; } .bg-ig { background: #c13584; } .bg-pin { background: #bd081c; } .bg-vm { background: #1ab7ea; }

        .widget-list .item { display: grid; grid-template-columns: 80px 1fr; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f4f4f4; }
        .widget-list .thumb { width: 80px; height: 60px; border-radius: 4px; object-fit: cover; background: #e9eef3; }
        .widget-list .title { font-weight: 600; font-size: 14px; line-height: 1.4; color: #222; text-decoration: none; }
        .widget-list .meta { font-size: 12px; color: #888; margin-top: 4px; }

        .widget-cats a { display: inline-block; background: #f3f3f3; color: #555; padding: 6px 10px; margin: 0 8px 8px 0; border-radius: 3px; font-size: 12px; text-decoration: none; }

        /* 345x345 Ad Container Styling */
        .ad-position-news_sidebar_345 {
            width: 100%;
            max-width: 345px;
            height: 345px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .ad-position-news_sidebar_345 img {
            max-width: 345px;
            max-height: 345px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .ad-position-news_sidebar_345 iframe,
        .ad-position-news_sidebar_345 video {
            max-width: 345px;
            max-height: 345px;
            width: 100%;
            height: auto;
        }

        .widget-newsletter input[type="email"] { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; }
        .widget-newsletter button { width: 100%; margin-top: 10px; background: #222; color: #fff; border: 0; padding: 10px 12px; border-radius: 4px; font-weight: 700; }

        .comments { background: #fff; border: 1px solid #eee; border-radius: 6px; margin-top: 16px; }
        .comments .widget-title { padding: 14px 16px; border-bottom: 1px solid #f2f2f2; font-size: 14px; font-weight: 800; }
        .comments .widget-body { padding: 16px; }
        .comment-item { border: 1px solid #f1f1f1; border-radius: 6px; padding: 12px; margin-bottom: 10px; }
        .comment-meta { font-size: 12px; color: #888; margin-bottom: 6px; }
        .comment-form textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; }
        .comment-form button { margin-top: 10px; background: #222; color: #fff; font-weight: 700; border-radius: 4px; padding: 10px 14px; border: 0; }

        /* Sticky Sidebar to match JNews behavior */
        .sidebar-sticky { position: sticky; top: 90px; }
        /* Ensure sidebar visible on desktop */
        @media (max-width: 992px) { .sidebar-sticky { position: static; } }
    </style>
</head>
<body>
    <?php 
    // Include header with robust error handling
    // Skip maintenance check in header to avoid double-checking
    define('SKIP_MAINTENANCE_CHECK', true);
    
    try {
        $header_path = $base_dir . '/includes/header.php';
        if (!file_exists($header_path)) {
            $header_path = 'includes/header.php';
        }
        if (!file_exists($header_path)) {
            // Try absolute path from document root
            $header_path = $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
        }
        if (file_exists($header_path)) {
            include $header_path;
        } else {
            error_log('Warning: includes/header.php not found. Tried: ' . $base_dir . '/includes/header.php, includes/header.php, ' . ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/includes/header.php');
            // Define minimal header if header.php is missing
            if (!function_exists('renderThemeStyles')) {
                function renderThemeStyles() { return ''; }
            }
        }
    } catch (Exception $e) {
        error_log('Error including header.php in news-details.php: ' . $e->getMessage());
        // Continue without header if it fails
    } catch (Error $e) {
        error_log('Fatal error including header.php in news-details.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        // Continue without header if it fails
    }
    ?>

    <?php
    // Display header ad if exists (with error handling)
    try {
    $headerAd = displayAd('header');
    if ($headerAd) {
        echo '<div style="max-width: 1400px; margin: 10px auto; padding: 10px 15px;">' . $headerAd . '</div>';
        }
    } catch (Exception $e) {
        error_log("Header ad error: " . $e->getMessage());
    }
    ?>

    <div class="page-wrap">
        <div class="breadcrumb">
            <a href="index.php">Home</a> / <a href="news.php">News</a> / <span><?php echo htmlspecialchars($news_item['title']); ?></span>
        </div>

        <div class="post-header">
            <div><span class="news-category <?php echo strtolower(str_replace(' ', '-', $news_item['category'])); ?>" style="background:#222;color:#fff;padding:4px 10px;border-radius:3px;font-weight:700;font-size:11px;text-transform:uppercase;"><?php echo htmlspecialchars($news_item['category']); ?></span></div>
            <h1 class="post-title"><?php echo htmlspecialchars($news_item['title']); ?></h1>
            <div class="post-meta">
                <span style="text-transform: uppercase; font-weight: 600;">
                    BY <?php echo strtoupper(htmlspecialchars($displayAuthorName)); ?> • <?php echo strtoupper(date('F j, Y', strtotime($news_item['created_at'] ?? $news_item['date'] ?? 'now'))); ?>
                </span>
                <span><i class="far fa-eye"></i> <?php echo (int)($news_item['views'] ?? 25500); ?> views</span>
                <span class="post-share">
                    <a href="#" title="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Share on Twitter"><i class="fab fa-twitter"></i></a>
                </span>
            </div>
            <div class="post-hero">
                <?php if (!empty($news_item['image'])): ?>
                    <img src="<?php echo htmlspecialchars($news_item['image']); ?>" alt="<?php echo htmlspecialchars($news_item['title']); ?>">
                <?php else: ?>
                    <div class="placeholder"><i class="fas fa-image"></i></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-grid">
            <div>

                <div class="post-content">
                    <div class="news-body">
                        <?php 
                        $content = $news_item['content'] ?? '';
                        
                        // Get ads for insertion between paragraphs
                        $adParagraph = '';
                        try {
                            $adParagraph = displayAd('content_paragraph');
                        } catch (Exception $e) {
                            error_log("Paragraph ad error: " . $e->getMessage());
                        }
                        
                        // Get paragraph spacing setting from admin
                        $paragraphSpacing = 5; // Default
                        try {
                            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'ad_paragraph_spacing'");
                            $stmt->execute();
                            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($setting && is_numeric($setting['setting_value'])) {
                                $paragraphSpacing = max(2, min(20, intval($setting['setting_value']))); // Clamp between 2-20
                            }
                        } catch (Exception $e) {
                            error_log("Settings error: " . $e->getMessage());
                        }
                        
                        // Process content and insert ads between paragraphs
                        if (strip_tags($content) !== $content) {
                            // HTML content - split by <p> tags or <br><br> patterns
                            // First, normalize <br> tags
                            $content = preg_replace('/(<br\s*\/?>\s*){2,}/i', '</p><p>', $content);
                            
                            // Extract paragraphs
                            preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches);
                            $paragraphs = $matches[0] ?? [];
                            $output = '';
                            $para_count = 0;
                            
                            foreach ($paragraphs as $para) {
                                $para = trim($para);
                                if (!empty($para) && strlen(strip_tags($para)) > 10) { // Only count substantial paragraphs
                                    $para_count++;
                                    $output .= $para;
                                    
                                    // Insert ad after configured number of paragraphs
                                    $startAfter = $paragraphSpacing - 1;
                                    if ($para_count >= $startAfter && $para_count % $paragraphSpacing == 0 && $adParagraph) {
                                        $output .= '<div style="margin: 24px 0; text-align: center; clear: both;">' . $adParagraph . '</div>';
                                    }
                                } else {
                                    // Keep non-paragraph content
                                    $output .= $para;
                                }
                            }
                            
                            // If no paragraphs found, just output original content
                            if (empty($paragraphs)) {
                                echo $content;
                            } else {
                                echo $output;
                            }
                        } else {
                            // Plain text - split by double newlines (paragraphs)
                            $paragraphs = preg_split('/\n\s*\n+/', trim($content));
                            $output = '';
                            $para_count = 0;
                            
                            foreach ($paragraphs as $para) {
                                $para = trim($para);
                                if (!empty($para) && strlen($para) > 20) { // Only count substantial paragraphs
                                    $para_count++;
                                    $output .= '<p>' . nl2br(htmlspecialchars($para)) . '</p>';
                                    
                                    // Insert ad after configured number of paragraphs
                                    $startAfter = $paragraphSpacing - 1;
                                    if ($para_count >= $startAfter && $para_count % $paragraphSpacing == 0 && $adParagraph) {
                                        $output .= '<div style="margin: 24px 0; text-align: center; clear: both;">' . $adParagraph . '</div>';
                                    }
                                }
                            }
                            echo $output ?: '<p>' . nl2br(htmlspecialchars($content)) . '</p>';
                        }
                        ?>
                    </div>

                    <?php
                    // In-content ads (top/mid/bottom) from admin settings
                    try {
                        $adTop = displayAd('content_top');
                        if ($adTop) { echo '<div style="margin: 16px 0; text-align:center;">' . $adTop . '</div>'; }
                    } catch (Exception $e) {
                        error_log("Content top ad error: " . $e->getMessage());
                    }
                    ?>

                    <div class="post-tags">
                        <?php 
                        $tags = isset($news_item['tags']) && is_string($news_item['tags']) ? array_filter(array_map('trim', explode(',', $news_item['tags']))) : [];
                        foreach ($tags as $tag): ?>
                            <a href="#">#<?php echo htmlspecialchars($tag); ?></a>
                        <?php endforeach; ?>
                    </div>

        <?php
                    try {
                        $adMid = displayAd('content_mid');
                        if ($adMid) { echo '<div style="margin: 16px 0; text-align:center;">' . $adMid . '</div>'; }
                    } catch (Exception $e) {
                        error_log("Content mid ad error: " . $e->getMessage());
                    }
                    ?>

                    <div class="author-box">
                        <?php if (!empty($displayAuthorAvatar)): ?>
                        <?php 
                        // Ensure asset_path function exists
                        if (!function_exists('asset_path')) {
                            // Define a fallback if header.php hasn't been included yet
                            function asset_path($path) {
                                if (empty($path)) return '';
                                if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
                                    return $path;
                                }
                                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
                                $baseUrl = rtrim($protocol . $host . $base_path, '/');
                                return $baseUrl . '/' . ltrim($path, '/');
                            }
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars(asset_path($displayAuthorAvatar)); ?>" alt="<?php echo htmlspecialchars($displayAuthorName); ?>" class="avatar" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-right: 20px; flex-shrink: 0;">
                        <?php else: ?>
                        <span class="avatar" style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 32px; margin-right: 20px; flex-shrink: 0;">
                            <?php echo strtoupper(substr($displayAuthorName, 0, 1)); ?>
            </span>
                        <?php endif; ?>
                        <div>
                            <div class="name">
                                <?php echo htmlspecialchars($displayAuthorName); ?>
                            </div>
                            <div class="bio"><?php echo htmlspecialchars($displayAuthorBio); ?></div>
            </div>
        </div>

                    <?php
                    try {
                        $adBottom = displayAd('content_bottom');
                        if ($adBottom) { echo '<div style="margin: 16px 0; text-align:center;">' . $adBottom . '</div>'; }
                    } catch (Exception $e) {
                        error_log("Content bottom ad error: " . $e->getMessage());
                    }
                    ?>

                    <div class="post-nav">
                        <?php
                        $prev = null; $next = null;
                        try {
                            if ($conn) {
                                $ps = $conn->prepare("SELECT id, title, slug FROM news WHERE is_published = 1 AND created_at < ? ORDER BY created_at DESC LIMIT 1");
                                $ps->execute([$news_item['created_at'] ?? date('Y-m-d H:i:s')]);
                                $prev = $ps->fetch(PDO::FETCH_ASSOC);
                                $ns = $conn->prepare("SELECT id, title, slug FROM news WHERE is_published = 1 AND created_at > ? ORDER BY created_at ASC LIMIT 1");
                                $ns->execute([$news_item['created_at'] ?? date('Y-m-d H:i:s')]);
                                $next = $ns->fetch(PDO::FETCH_ASSOC);
                            }
                        } catch (Exception $e) {}
                        ?>
                        <a href="<?php echo $prev ? (!empty($prev['slug']) ? base_url('news/' . rawurlencode($prev['slug'])) : base_url('news/' . $prev['id'])) : '#'; ?>">
                            <small>Previous Post</small>
                            <div><?php echo $prev ? htmlspecialchars($prev['title']) : '—'; ?></div>
                        </a>
                        <a style="text-align:right;" href="<?php echo $next ? (!empty($next['slug']) ? base_url('news/' . rawurlencode($next['slug'])) : base_url('news/' . $next['id'])) : '#'; ?>">
                            <small>Next Post</small>
                            <div><?php echo $next ? htmlspecialchars($next['title']) : '—'; ?></div>
                        </a>
        </div>
        </div>

        <?php
                // COMMENTS
        $comments = [];
        $comment_error = '';
        $comment_success = false;
        $isLoggedIn = isset($_SESSION['user_id']);
        $currentUserId = $_SESSION['user_id'] ?? null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment']) && $isLoggedIn) {
            $comment_text = trim($_POST['comment_text'] ?? '');
            if ($comment_text === '') {
                $comment_error = 'Please write a comment.';
            } else {
                try {
                            if (!$conn) { throw new Exception('DB connection missing'); }
                            // Ensure table exists (id, news_id, user_id, comment, created_at)
                            $conn->exec("CREATE TABLE IF NOT EXISTS news_comments (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                news_id INT NOT NULL,
                                user_id INT NOT NULL,
                                comment TEXT NOT NULL,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                    $ins = $conn->prepare("INSERT INTO news_comments (news_id, user_id, comment) VALUES (?, ?, ?)");
                            $ins->execute([(int)$actual_news_id, (int)$currentUserId, $comment_text]);
                    $comment_success = true;
                } catch (Exception $e) {
                    $comment_error = 'Failed to save comment.';
                }
            }
        }
        try {
            $cstmt = $conn->prepare("SELECT nc.*, COALESCE(u.username, 'User') as username FROM news_comments nc LEFT JOIN users u ON nc.user_id = u.id WHERE nc.news_id = ? ORDER BY nc.created_at DESC LIMIT 50");
                    $cstmt->execute([(int)$actual_news_id]);
            $comments = $cstmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) { $comments = []; }
                ?>
                <div class="comments">
                    <div class="widget-title">Comments (<?php echo count($comments); ?>)</div>
                    <div class="widget-body">
                        <?php if ($comment_success): ?><div class="alert alert-success" style="margin-bottom:12px;">Comment added.</div><?php endif; ?>
                        <?php if ($comment_error): ?><div class="alert alert-warning" style="margin-bottom:12px;"><?php echo htmlspecialchars($comment_error); ?></div><?php endif; ?>
            <?php if ($isLoggedIn): ?>
                        <form method="POST" class="comment-form">
                            <textarea name="comment_text" rows="3" placeholder="Write a comment..."></textarea>
                            <button type="submit" name="add_comment">Post Comment</button>
            </form>
            <?php else: ?>
                        <div style="padding:12px;background:#fffbe6;border:1px solid #ffe58f;border-radius:6px;margin-bottom:12px;">
                            <a href="/login.php" style="font-weight:700;">Login</a> to post a comment.
            </div>
            <?php endif; ?>
            <?php foreach ($comments as $c): ?>
                        <div class="comment-item">
                            <div class="comment-meta">@<?php echo htmlspecialchars($c['username']); ?> • <?php echo date('F d, Y h:i A', strtotime($c['created_at'])); ?></div>
                            <div><?php echo nl2br(htmlspecialchars($c['comment'])); ?></div>
            </div>
            <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <aside>
                <div class="sidebar-sticky">
                <div class="widget">
                    <div class="widget-title">Share</div>
                    <div class="widget-body">
                        <div class="widget-social">
                            <a class="bg-fb" href="<?php echo 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($currentUrl); ?>" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                            <a class="bg-tw" href="<?php echo 'https://twitter.com/intent/tweet?url=' . urlencode($currentUrl) . '&text=' . urlencode($news_item['title']); ?>" target="_blank" rel="noopener"><i class="fab fa-twitter"></i></a>
                            <a class="bg-yt" href="<?php echo 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($currentUrl); ?>" target="_blank" rel="noopener"><i class="fab fa-linkedin-in"></i></a>
                            <a class="bg-ig" href="<?php echo 'https://api.whatsapp.com/send?text=' . urlencode(($news_item['title'] ?? '') . ' ' . $currentUrl); ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>
                            <a class="bg-pin" href="<?php echo 'https://t.me/share/url?url=' . urlencode($currentUrl) . '&text=' . urlencode($news_item['title']); ?>" target="_blank" rel="noopener"><i class="fab fa-telegram-plane"></i></a>
                            <a class="bg-vm" href="mailto:?subject=<?php echo rawurlencode($news_item['title'] ?? ''); ?>&body=<?php echo rawurlencode($currentUrl); ?>"><i class="fas fa-envelope"></i></a>
                        </div>
                    </div>
                </div>

                <div class="widget">
                    <?php
                    // Display 345x345 sidebar ad
                    try {
                        $adSidebar345 = displayAd('news_sidebar_345');
                        if ($adSidebar345) {
                            echo '<div class="widget-body" style="padding:10px;display:flex;align-items:center;justify-content:center;">';
                            echo $adSidebar345;
                            echo '</div>';
                        } else {
                            echo '<div class="widget-body" style="display:flex;align-items:center;justify-content:center;width:100%;max-width:345px;height:345px;border:1px dashed #ddd;border-radius:6px;margin:0 auto;">';
                            echo '<span style="color:#999;">345x345 Advertisement</span>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        error_log("Sidebar 345x345 ad error: " . $e->getMessage());
                        echo '<div class="widget-body" style="display:flex;align-items:center;justify-content:center;width:100%;max-width:345px;height:345px;border:1px dashed #ddd;border-radius:6px;margin:0 auto;">';
                        echo '<span style="color:#999;">345x345 Advertisement</span>';
                        echo '</div>';
                    }
                    ?>
                </div>

                <?php 
                // Recent News for sidebar
                $recent_news = [];
                try {
                    if ($conn) {
                        $rs = $conn->query("SELECT id, title, slug, image, category, created_at FROM news WHERE is_published = 1 ORDER BY created_at DESC LIMIT 5");
                        $recent_news = $rs->fetchAll(PDO::FETCH_ASSOC);
                    }
                } catch (Exception $e) { $recent_news = []; }
                ?>
                <div class="widget">
                    <div class="widget-title">Recent News</div>
                    <div class="widget-body widget-list">
                        <?php foreach ($recent_news as $rn): $lnk = !empty($rn['slug']) ? base_url('news/' . rawurlencode($rn['slug'])) : base_url('news/' . $rn['id']); ?>
                        <div class="item">
                            <img class="thumb" src="<?php echo htmlspecialchars($rn['image'] ?: ''); ?>" alt="" onerror="this.style.background='#e9eef3';this.src='';">
                            <div>
                                <a class="title" href="<?php echo $lnk; ?>"><?php echo htmlspecialchars($rn['title']); ?></a>
                                <div class="meta"><?php echo htmlspecialchars($rn['category'] ?? 'News'); ?> • <?php echo date('F d, Y', strtotime($rn['created_at'] ?? 'now')); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
            </div>
        </div>

        <?php
                // Categories cloud
                $cats = [];
                try {
                    if ($conn) {
                        $cs = $conn->query("SELECT DISTINCT category FROM news WHERE category IS NOT NULL AND category <> '' LIMIT 20");
                        $cats = array_map(function($r){ return $r['category']; }, $cs->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) { $cats = []; }
                ?>
                <div class="widget">
                    <div class="widget-title">Browse by Category</div>
                    <div class="widget-body widget-cats">
                        <?php foreach ($cats as $c): ?>
                            <a href="news.php?cat=<?php echo urlencode($c); ?>"><?php echo htmlspecialchars($c); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="widget">
                    <div class="widget-title">Newsletter</div>
                    <div class="widget-body widget-newsletter">
                        <form id="newsletter-form" method="POST" action="api/newsletter-subscribe.php">
                            <input type="email" name="email" id="newsletter-email" placeholder="Your email address" required>
                            <button type="submit" id="newsletter-submit">Subscribe</button>
                        </form>
                        <div id="newsletter-message" style="margin-top: 10px; font-size: 13px; display: none;"></div>
                    </div>
                </div>
                <script>
                (function() {
                    const form = document.getElementById('newsletter-form');
                    const emailInput = document.getElementById('newsletter-email');
                    const submitBtn = document.getElementById('newsletter-submit');
                    const messageDiv = document.getElementById('newsletter-message');
                    
                    if (!form) return;
                    
                    // Calculate base path dynamically
                    let basePath = window.location.pathname;
                    if (basePath.includes('/news/')) {
                        basePath = basePath.substring(0, basePath.indexOf('/news/')) + '/';
                    } else if (basePath.endsWith('.php')) {
                        basePath = basePath.substring(0, basePath.lastIndexOf('/') + 1);
                    } else if (!basePath.endsWith('/')) {
                        basePath += '/';
                    }
                    const apiUrl = window.location.origin + basePath + 'api/newsletter-subscribe.php';
                    
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const email = emailInput.value.trim();
                        if (!email) {
                            showMessage('Please enter your email address', 'error');
                            return;
                        }
                        
                        // Disable button
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Subscribing...';
                        
                        const formData = new FormData();
                        formData.append('email', email);
                        
                        fetch(apiUrl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showMessage(data.message || 'Thank you for subscribing!', 'success');
                                emailInput.value = '';
                            } else {
                                showMessage(data.error || 'Failed to subscribe. Please try again.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Newsletter subscription error:', error);
                            showMessage('Failed to subscribe. Please try again.', 'error');
                        })
                        .finally(() => {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Subscribe';
                        });
                    });
                    
                    function showMessage(message, type) {
                        messageDiv.textContent = message;
                        messageDiv.style.display = 'block';
                        messageDiv.style.color = type === 'success' ? '#10b981' : '#ef4444';
                        messageDiv.style.padding = '8px';
                        messageDiv.style.borderRadius = '4px';
                        messageDiv.style.background = type === 'success' ? '#d1fae5' : '#fee2e2';
                        
                        // Hide after 5 seconds
                        setTimeout(() => {
                            messageDiv.style.display = 'none';
                        }, 5000);
                    }
                })();
                </script>
                </div>
            </aside>
        </div>

        <?php if (count($related_news) > 0): ?>
        <div class="page-wrap" style="padding-top:0;">
            <div class="widget">
                <div class="widget-title">Related Posts</div>
                <div class="widget-body widget-list">
                    <?php foreach ($related_news as $related): $rl = !empty($related['slug']) ? base_url('news/' . rawurlencode($related['slug'])) : base_url('news/' . $related['id']); ?>
                    <div class="item">
                        <img class="thumb" src="<?php echo htmlspecialchars($related['image'] ?? ''); ?>" alt="" onerror="this.style.background='#e9eef3';this.src='';">
                        <div>
                            <a class="title" href="<?php echo $rl; ?>"><?php echo htmlspecialchars($related['title']); ?></a>
                            <div class="meta"><?php echo date('F d, Y', strtotime($related['created_at'] ?? $related['date'] ?? 'now')); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php 
    $footer_path = $base_dir . '/includes/footer.php';
    if (!file_exists($footer_path)) {
        $footer_path = 'includes/footer.php';
    }
    if (file_exists($footer_path)) {
        include $footer_path;
    } else {
        error_log('Warning: includes/footer.php not found');
    }
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" async defer></script>
    <script>
        // Mark page as loaded
        (function() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('News details page loaded');
                });
            } else {
                console.log('News details page already loaded');
            }
        })();
    </script>
</body>
</html>
<?php
// End output buffering only if we started it here
if ($ob_started_here && ob_get_level() > 0) {
    ob_end_flush();
}
// Final flush to ensure browser recognizes page as complete
flush();
?>

