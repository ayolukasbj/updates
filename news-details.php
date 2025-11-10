<?php
/**
 * News Details Page
 * Displays individual news article by slug or ID
 * Layout matching jnews.io professional news theme exactly
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

// Check if featured_image column exists
$has_featured_image = false;
try {
    $column_check = $conn->query("SHOW COLUMNS FROM news LIKE 'featured_image'");
    $has_featured_image = $column_check->rowCount() > 0;
} catch (Exception $e) {
    $has_featured_image = false;
}

// Fetch news article
try {
    if (!empty($news_slug)) {
        // Build query based on whether featured_image column exists
        if ($has_featured_image) {
            $stmt = $conn->prepare("
                SELECT n.*, COALESCE(u.username, 'Unknown') as author,
                       COALESCE(n.featured_image, n.image) as display_image,
                       n.featured_image,
                       n.image
                FROM news n 
                LEFT JOIN users u ON n.author_id = u.id 
                WHERE n.slug = ? AND n.is_published = 1
                LIMIT 1
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT n.*, COALESCE(u.username, 'Unknown') as author,
                       n.image as display_image
                FROM news n 
                LEFT JOIN users u ON n.author_id = u.id 
                WHERE n.slug = ? AND n.is_published = 1
                LIMIT 1
            ");
        }
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
            if ($has_featured_image) {
                $stmt = $conn->prepare("
                    SELECT n.*, COALESCE(u.username, 'Unknown') as author,
                           COALESCE(n.featured_image, n.image) as display_image,
                           n.featured_image,
                           n.image
                    FROM news n 
                    LEFT JOIN users u ON n.author_id = u.id 
                    WHERE n.title LIKE ? AND n.is_published = 1
                    LIMIT 1
                ");
            } else {
                $stmt = $conn->prepare("
                    SELECT n.*, COALESCE(u.username, 'Unknown') as author,
                           n.image as display_image
                    FROM news n 
                    LEFT JOIN users u ON n.author_id = u.id 
                    WHERE n.title LIKE ? AND n.is_published = 1
                    LIMIT 1
                ");
            }
            $stmt->execute(['%' . $title_search . '%']);
            $news_item = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } elseif (!empty($news_id)) {
        if ($has_featured_image) {
            $stmt = $conn->prepare("
                SELECT n.*, COALESCE(u.username, 'Unknown') as author,
                       COALESCE(n.featured_image, n.image) as display_image,
                       n.featured_image,
                       n.image
                FROM news n 
                LEFT JOIN users u ON n.author_id = u.id 
                WHERE n.id = ? AND n.is_published = 1
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT n.*, COALESCE(u.username, 'Unknown') as author,
                       n.image as display_image
                FROM news n 
                LEFT JOIN users u ON n.author_id = u.id 
                WHERE n.id = ? AND n.is_published = 1
            ");
        }
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
    
    // Set featured_image for display (use display_image from query or fallback to image)
    if (!isset($news_item['featured_image'])) {
        $news_item['featured_image'] = $news_item['display_image'] ?? $news_item['image'] ?? '';
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
    error_log('Stack trace: ' . $e->getTraceAsString());
    error_log('SQL query attempted: slug=' . ($news_slug ?? '') . ', id=' . ($news_id ?? ''));
    
    // Show more helpful error message in development
    $debug_mode = defined('DEBUG_MODE') && DEBUG_MODE === true;
    if ($debug_mode) {
        http_response_code(500);
        die('Error loading news article: ' . htmlspecialchars($e->getMessage()) . '<br>Check error logs for details.');
    } else {
        http_response_code(500);
        die('Error loading news article. Please check error logs.');
    }
} catch (Error $e) {
    error_log('Fatal error fetching news: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    die('Fatal error loading news article. Please check error logs.');
}

// Get comment count and comments
$comment_count = 0;
$news_comments = [];
try {
    // Check if is_approved column exists
    $col_check = $conn->query("SHOW COLUMNS FROM news_comments LIKE 'is_approved'");
    $has_is_approved = $col_check->rowCount() > 0;
    
    if ($has_is_approved) {
        $comment_stmt = $conn->prepare("SELECT COUNT(*) as count FROM news_comments WHERE news_id = ? AND is_approved = 1");
        $comment_stmt->execute([$news_item['id']]);
        $comment_result = $comment_stmt->fetch(PDO::FETCH_ASSOC);
        $comment_count = $comment_result['count'] ?? 0;
        
        // Fetch comments
        $comments_stmt = $conn->prepare("
            SELECT nc.*, 
                   COALESCE(u.username, nc.name, 'Anonymous') as display_name,
                   COALESCE(u.avatar, '') as avatar
            FROM news_comments nc
            LEFT JOIN users u ON nc.user_id = u.id
            WHERE nc.news_id = ? AND nc.is_approved = 1
            ORDER BY nc.created_at DESC
        ");
        $comments_stmt->execute([$news_item['id']]);
        $news_comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Fallback without is_approved
        $comment_stmt = $conn->prepare("SELECT COUNT(*) as count FROM news_comments WHERE news_id = ?");
        $comment_stmt->execute([$news_item['id']]);
        $comment_result = $comment_stmt->fetch(PDO::FETCH_ASSOC);
        $comment_count = $comment_result['count'] ?? 0;
        
        $comments_stmt = $conn->prepare("
            SELECT nc.*, 
                   COALESCE(u.username, 'Anonymous') as display_name,
                   COALESCE(u.avatar, '') as avatar
            FROM news_comments nc
            LEFT JOIN users u ON nc.user_id = u.id
            WHERE nc.news_id = ?
            ORDER BY nc.created_at DESC
        ");
        $comments_stmt->execute([$news_item['id']]);
        $news_comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Error fetching comments: ' . $e->getMessage());
    $comment_count = 0;
    $news_comments = [];
}

// Function to insert ads after every 4 paragraphs
function insertAdsInContent($content, $adCode) {
    if (empty($adCode)) {
        return $content;
    }
    
    // Split content by paragraph tags
    $paragraphs = preg_split('/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    $result = '';
    $paragraphCount = 0;
    
    foreach ($paragraphs as $index => $part) {
        $result .= $part;
        
        // Check if this is a closing </p> tag
        if (stripos($part, '</p>') !== false) {
            $paragraphCount++;
            
            // Insert ad after every 4 paragraphs
            if ($paragraphCount % 4 == 0 && $index < count($paragraphs) - 1) {
                $result .= '<div class="in-article-ad" style="margin: 30px 0; text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">' . $adCode . '</div>';
            }
        }
    }
    
    return $result;
}

// Get previous and next news
$prev_news = null;
$next_news = null;
try {
    // Previous (older)
    $prev_stmt = $conn->prepare("
        SELECT n.*, COALESCE(u.username, 'Unknown') as author 
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id 
        WHERE n.is_published = 1 AND n.created_at < ? 
        ORDER BY n.created_at DESC 
        LIMIT 1
    ");
    $prev_stmt->execute([$news_item['created_at']]);
    $prev_news = $prev_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Next (newer)
    $next_stmt = $conn->prepare("
        SELECT n.*, COALESCE(u.username, 'Unknown') as author 
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id 
        WHERE n.is_published = 1 AND n.created_at > ? 
        ORDER BY n.created_at ASC 
        LIMIT 1
    ");
    $next_stmt->execute([$news_item['created_at']]);
    $next_news = $next_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching prev/next news: ' . $e->getMessage());
}

// Get related news (for Similar News section)
$related_news = [];
try {
    $related_stmt = $conn->prepare("
        SELECT n.*, COALESCE(u.username, 'Unknown') as author 
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id 
        WHERE n.category = ? AND n.id != ? AND n.is_published = 1 
        ORDER BY n.created_at DESC 
        LIMIT 6
    ");
    $related_stmt->execute([$news_item['category'] ?? 'News', $news_item['id']]);
    $related_news = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching related news: ' . $e->getMessage());
}

// Get latest news for sidebar (first one large, rest as list)
$latest_news = [];
try {
    $latest_stmt = $conn->prepare("
        SELECT n.*, COALESCE(u.username, 'Unknown') as author 
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id 
        WHERE n.is_published = 1 AND n.id != ?
        ORDER BY n.created_at DESC 
        LIMIT 6
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

// Get social media links from admin settings
function getSocialLinks($conn) {
    try {
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'social_%'");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'twitter' => $settings['social_twitter'] ?? '',
            'instagram' => $settings['social_instagram'] ?? '',
            'facebook' => $settings['social_facebook'] ?? '',
            'youtube' => $settings['social_youtube'] ?? '',
            'tiktok' => $settings['social_tiktok'] ?? '',
        ];
    } catch (Exception $e) {
        return [];
    }
}

$social_links = getSocialLinks($conn);

// Load ads helper
$ads_path = $base_dir . '/includes/ads.php';
if (file_exists($ads_path)) {
    require_once $ads_path;
} elseif (file_exists('includes/ads.php')) {
    require_once 'includes/ads.php';
} elseif (file_exists(__DIR__ . '/includes/ads.php')) {
    require_once __DIR__ . '/includes/ads.php';
}

// Helper function for display ad (fallback if ads.php not found)
if (!function_exists('displayAd')) {
    function displayAd($position) {
        return '';
    }
}

// Check if user is logged in
$is_logged_in = false;
$user_id = null;
if (function_exists('is_logged_in')) {
    $is_logged_in = is_logged_in();
    $user_id = function_exists('get_user_id') ? get_user_id() : ($_SESSION['user_id'] ?? null);
} else {
    $is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    $user_id = $_SESSION['user_id'] ?? null;
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

// Get share image - check multiple possible field names
$share_image = '';
if (!empty($news_item['featured_image'])) {
    $share_image = asset_path($news_item['featured_image']);
} elseif (!empty($news_item['display_image'])) {
    $share_image = asset_path($news_item['display_image']);
} elseif (!empty($news_item['image'])) {
    $share_image = asset_path($news_item['image']);
} else {
    $share_image = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/assets/images/default-news.jpg' : '';
}

// Calculate share count (using views as proxy)
$share_count = round(($news_item['views'] ?? 0) * 0.14); // Approximate 14% share rate
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?php echo $share_title; ?> - <?php echo defined('SITE_NAME') ? SITE_NAME : 'News'; ?></title>
    <style>
        /* Prevent horizontal scroll on all devices */
        html, body {
            overflow-x: hidden !important;
            width: 100% !important;
            max-width: 100% !important;
        }
    </style>
    
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
        html {
            width: 100%;
            overflow-x: hidden;
        }
        body {
            background: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #222;
            line-height: 1.6;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }
        img, video, iframe {
            max-width: 100%;
            height: auto;
        }
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
            overflow-x: hidden;
            width: 100%;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 15px;
                width: 100%;
                max-width: 100%;
            }
        }
        @media (max-width: 480px) {
            .main-content {
                padding: 10px 10px;
            }
        }
        .article-container {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 40px;
            margin-top: 20px;
            width: 100%;
            box-sizing: border-box;
        }
        @media (max-width: 1024px) {
            .article-container {
                grid-template-columns: 1fr;
                gap: 30px;
                width: 100%;
            }
        }
        .article-main {
            background: #fff;
            padding: 40px;
            overflow-x: hidden;
            width: 100%;
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        @media (max-width: 768px) {
            .article-main {
                padding: 15px 15px;
                width: 100%;
                max-width: 100%;
                margin: 0;
            }
        }
        @media (max-width: 480px) {
            .article-main {
                padding: 10px 10px;
            }
        }
        
        /* In-article ads styling */
        .in-article-ad {
            margin: 30px 0 !important;
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 4px;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        @media (max-width: 768px) {
            .in-article-ad {
                padding: 15px !important;
                margin: 20px 0 !important;
            }
        }
        
        /* Share Stats Bar at Top */
        .share-stats-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 30px;
        }
        .share-stats {
            display: flex;
            gap: 30px;
            font-size: 13px;
            color: #666;
            font-weight: 600;
        }
        .share-stats-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .share-buttons-top {
            display: flex;
            gap: 8px;
        }
        .share-btn-top {
            padding: 8px 16px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s;
        }
        .share-btn-top:hover {
            opacity: 0.9;
        }
        .share-btn-top.facebook { background: #1877f2; }
        .share-btn-top.twitter { background: #000; }
        .share-btn-top.email { background: #e74c3c; }
        .share-btn-top.more { background: #999; }
        
        /* Article Header */
        .article-header {
            padding: 0;
            margin-bottom: 30px;
        }
        .article-category {
            display: inline-block;
            padding: 6px 12px;
            background: #e74c3c;
            color: white;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
            border-radius: 2px;
        }
        .article-title {
            font-size: 48px;
            font-weight: 700;
            line-height: 1.2;
            color: #222;
            margin-bottom: 20px;
            width: 100%;
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .article-title {
                font-size: 28px;
                line-height: 1.3;
                margin-bottom: 15px;
            }
        }
        @media (max-width: 480px) {
            .article-title {
                font-size: 24px;
            }
        }
        .article-byline {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        .article-byline .author {
            color: #e74c3c;
            font-weight: 600;
        }
        .article-byline .separator {
            color: #999;
        }
        .article-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
        }
        .text-size-controls {
            display: flex;
            gap: 5px;
        }
        .text-size-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #ddd;
            background: #fff;
            color: #666;
            border-radius: 3px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .text-size-btn:hover {
            border-color: #e74c3c;
            color: #e74c3c;
        }
        .comment-count {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-size: 14px;
        }
        .comment-count i {
            font-size: 16px;
        }
        
        /* Featured Image */
        .article-featured-image {
            width: 100%;
            margin: 30px 0;
            padding: 0;
        }
        .article-featured-image img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 4px;
        }
        
        /* Article Body */
        .article-body {
            font-size: 18px;
            line-height: 1.85;
            color: #333;
            margin: 30px 0;
            padding: 0;
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .article-body {
                font-size: 16px;
                line-height: 1.7;
                margin: 20px 0;
                padding: 0;
                width: 100%;
            }
        }
        .article-body p {
            margin-bottom: 24px;
            width: 100%;
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        @media (max-width: 768px) {
            .article-body p {
                margin-bottom: 18px;
            }
        }
        .article-body h2 {
            font-size: 32px;
            font-weight: 700;
            margin: 40px 0 20px;
            color: #222;
            line-height: 1.3;
            width: 100%;
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        @media (max-width: 768px) {
            .article-body h2 {
                font-size: 24px;
                margin: 30px 0 15px;
            }
        }
        .article-body h3 {
            font-size: 26px;
            font-weight: 700;
            margin: 35px 0 18px;
            color: #222;
            line-height: 1.3;
            width: 100%;
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        @media (max-width: 768px) {
            .article-body h3 {
                font-size: 20px;
                margin: 25px 0 12px;
            }
        }
        .article-body blockquote {
            border-left: 4px solid #e74c3c;
            padding: 20px 30px;
            margin: 30px 0;
            background: #f9f9f9;
            font-style: italic;
            color: #555;
            font-size: 20px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        @media (max-width: 768px) {
            .article-body blockquote {
                padding: 15px 20px;
                margin: 20px 0;
                font-size: 18px;
            }
        }
        .article-body img {
            max-width: 100%;
            width: 100%;
            height: auto;
            margin: 30px 0;
            border-radius: 4px;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .article-body img {
                margin: 20px 0;
            }
        }
        
        /* Tags Section */
        .article-tags {
            padding: 30px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            margin: 30px 0;
        }
        .article-tags-label {
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
        
        /* Author Box */
        .author-box {
            padding: 40px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            margin: 30px 0;
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
            position: relative;
        }
        .author-avatar::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 20px;
            height: 20px;
            background: #e74c3c;
            border-radius: 50%;
            border: 3px solid #fff;
        }
        .author-info h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #e74c3c;
        }
        .author-info p {
            font-size: 15px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .author-social {
            display: flex;
            gap: 10px;
        }
        .author-social a {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f5f5f5;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s;
        }
        .author-social a:hover {
            background: #e74c3c;
            color: white;
        }
        
        /* Related Posts (Similar News) */
        .related-posts {
            padding: 40px 0;
            border-top: 1px solid #eee;
        }
        .related-posts h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 30px;
            color: #222;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e74c3c;
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
            position: relative;
        }
        .related-post-thumb {
            width: 100%;
            height: 200px;
            border-radius: 4px;
            overflow: hidden;
            background: #eee;
            margin-bottom: 15px;
            position: relative;
        }
        .related-post-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .related-post-category {
            position: absolute;
            bottom: 10px;
            left: 10px;
            padding: 4px 10px;
            background: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 2px;
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
            font-size: 12px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Previous/Next Navigation */
        .post-navigation {
            display: flex;
            justify-content: space-between;
            padding: 30px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            margin: 30px 0;
        }
        .nav-post {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: #222;
            transition: color 0.2s;
        }
        .nav-post:hover {
            color: #e74c3c;
        }
        .nav-post.prev {
            text-align: left;
        }
        .nav-post.next {
            text-align: right;
            flex-direction: row-reverse;
        }
        .nav-post-bar {
            width: 4px;
            height: 60px;
            background: #e74c3c;
            flex-shrink: 0;
        }
        .nav-post.next .nav-post-bar {
            background: #999;
        }
        .nav-post-content h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .nav-post-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
        }
        
        /* Comments Section */
        .comments-section {
            padding: 40px 0;
        }
        .comments-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #222;
        }
        .comment-form {
            background: #fff;
            padding: 30px;
            border: 1px solid #eee;
            border-radius: 4px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .comment-form {
                padding: 20px;
            }
        }
        @media (max-width: 480px) {
            .comment-form {
                padding: 15px;
            }
        }
        .comment-form p {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }
        .comment-form p .required {
            color: #e74c3c;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #222;
        }
        .form-group textarea,
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="url"] {
            width: 100%;
            max-width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .form-group textarea,
            .form-group input[type="text"],
            .form-group input[type="email"],
            .form-group input[type="url"] {
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        .form-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
        }
        .form-checkbox input[type="checkbox"] {
            margin-top: 4px;
        }
        .form-checkbox label {
            font-size: 14px;
            color: #666;
            font-weight: normal;
        }
        .submit-btn {
            padding: 12px 30px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .submit-btn:hover {
            background: #c0392b;
        }
        
        /* Sidebar */
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
            border: 1px solid #eee;
            border-radius: 4px;
        }
        .sidebar-widget h3 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 25px;
            color: #222;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e74c3c;
        }
        
        /* Email Subscription */
        .subscribe-form {
            margin-top: 15px;
        }
        .subscribe-form p {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        .subscribe-input-group {
            display: flex;
            gap: 10px;
        }
        .subscribe-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .subscribe-btn {
            padding: 10px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        .subscribe-disclaimer {
            font-size: 12px;
            color: #999;
            margin-top: 10px;
        }
        
        /* Recent News - Large Featured */
        .recent-featured {
            margin-bottom: 30px;
        }
        .recent-featured-thumb {
            width: 100%;
            height: 250px;
            border-radius: 4px;
            overflow: hidden;
            background: #eee;
            margin-bottom: 15px;
        }
        .recent-featured-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .recent-featured-title {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 10px;
        }
        .recent-featured-title a {
            color: #222;
            text-decoration: none;
            transition: color 0.2s;
        }
        .recent-featured-title a:hover {
            color: #e74c3c;
        }
        .recent-featured-meta {
            font-size: 12px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Recent News List */
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
        
        /* Social Media Followers */
        .social-followers {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .social-item {
            text-align: center;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .social-item i {
            font-size: 24px;
            margin-bottom: 8px;
            color: #666;
        }
        .social-item .count {
            font-size: 16px;
            font-weight: 700;
            color: #222;
        }
        .social-item .label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
        }
        
        @media (max-width: 768px) {
            .article-title {
                font-size: 32px;
            }
            .article-body {
                font-size: 16px;
            }
            .share-stats-bar {
                flex-direction: column;
                gap: 15px;
            }
            .share-stats {
                width: 100%;
            }
            .share-buttons-top {
                width: 100%;
                flex-wrap: wrap;
            }
        }
        
        /* Critical mobile fixes to prevent overflow */
        @media (max-width: 768px) {
            body, html {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: hidden !important;
                position: relative;
            }
            .main-content,
            .article-container,
            .article-main,
            .article-body,
            .article-header,
            .comment-form,
            .sidebar,
            .sidebar-widget,
            .article-body > *,
            .article-body p,
            .article-body div,
            .article-body span,
            .article-body h1,
            .article-body h2,
            .article-body h3,
            .article-body h4,
            .article-body h5,
            .article-body h6,
            .article-body blockquote,
            .article-body ul,
            .article-body ol,
            .article-body li,
            .article-body a,
            .article-body img,
            .article-body table,
            .article-body td,
            .article-body th {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                word-break: break-word !important;
            }
            .article-body img {
                height: auto !important;
            }
            table {
                width: 100% !important;
                display: block !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
            }
            pre, code {
                word-wrap: break-word !important;
                white-space: pre-wrap !important;
                max-width: 100% !important;
                overflow-x: auto !important;
            }
            /* Force break long URLs and words */
            a {
                word-break: break-all !important;
                overflow-wrap: break-word !important;
            }
        }
        
        /* Ensure no element can cause horizontal scroll */
        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }
            .article-main {
                padding: 10px;
            }
            .article-body {
                padding: 0;
            }
            .article-body p,
            .article-body h2,
            .article-body h3,
            .article-body blockquote,
            .article-body ul,
            .article-body ol {
                max-width: 100%;
                word-wrap: break-word;
                overflow-wrap: break-word;
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
    
    <div class="main-content" style="width: 100% !important; max-width: 100% !important; overflow-x: hidden !important; box-sizing: border-box !important;">
        <div class="article-container" style="width: 100% !important; max-width: 100% !important; box-sizing: border-box !important;">
            <article class="article-main" style="width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; overflow-x: hidden !important;">
                <!-- Share Stats Bar at Top -->
                <div class="share-stats-bar">
                    <div class="share-stats">
                        <div class="share-stats-item">
                            <i class="fas fa-share"></i>
                            <span><?php echo number_format($share_count); ?> SHARES</span>
                        </div>
                        <div class="share-stats-item">
                            <i class="far fa-eye"></i>
                            <span><?php echo number_format($news_item['views'] ?? 0); ?> VIEWS</span>
                        </div>
                    </div>
                    <div class="share-buttons-top">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($current_url); ?>" 
                           target="_blank" class="share-btn-top facebook">
                            <i class="fab fa-facebook-f"></i> Share on Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($current_url); ?>&text=<?php echo urlencode($share_title); ?>" 
                           target="_blank" class="share-btn-top twitter">
                            <i class="fab fa-x-twitter"></i> Share on Twitter
                        </a>
                        <a href="mailto:?subject=<?php echo urlencode($share_title); ?>&body=<?php echo urlencode($current_url); ?>" 
                           class="share-btn-top email">
                            <i class="far fa-envelope"></i> Email
                        </a>
                    </div>
                </div>

                <!-- Article Header -->
                <div class="article-header">
                    <?php if (!empty($news_item['category'])): ?>
                    <span class="article-category"><?php echo htmlspecialchars($news_item['category']); ?></span>
                    <?php endif; ?>
                    
                    <h1 class="article-title"><?php echo htmlspecialchars($news_item['title']); ?></h1>
                    
                    <div class="article-byline">
                        <span class="author"><?php echo htmlspecialchars($news_item['author'] ?? 'Unknown'); ?></span>
                        <span class="separator">—</span>
                        <span><?php echo date('F j, Y', strtotime($news_item['created_at'])); ?></span>
                        <?php if (!empty($news_item['category'])): ?>
                        <span class="separator">in</span>
                        <span><?php echo htmlspecialchars($news_item['category']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="article-controls">
                        <div class="text-size-controls">
                            <button class="text-size-btn" onclick="document.body.style.fontSize='16px'">A</button>
                            <button class="text-size-btn" onclick="document.body.style.fontSize='18px'">A</button>
                        </div>
                        <div class="comment-count">
                            <i class="far fa-comment"></i>
                            <span><?php echo $comment_count; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Featured Image -->
                <?php 
                // Get featured image - match homepage logic exactly
                // Homepage uses: $carousel_news['image'] directly
                // Since we use SELECT n.*, we have access to both 'image' and 'featured_image' fields
                // Use the same logic as homepage: check 'image' field directly
                if (!empty($news_item['image'])): 
                ?>
                <div class="article-featured-image">
                    <img src="<?php echo htmlspecialchars($news_item['image']); ?>" 
                         alt="<?php echo htmlspecialchars($news_item['title']); ?>"
                         style="max-width: 100%; height: auto; display: block;"
                         onerror="console.error('Image failed to load: <?php echo htmlspecialchars($news_item['image']); ?>'); this.style.display='none';">
                </div>
                <?php elseif (!empty($news_item['featured_image'])): ?>
                <div class="article-featured-image">
                    <img src="<?php echo htmlspecialchars($news_item['featured_image']); ?>" 
                         alt="<?php echo htmlspecialchars($news_item['title']); ?>"
                         style="max-width: 100%; height: auto; display: block;"
                         onerror="console.error('Image failed to load: <?php echo htmlspecialchars($news_item['featured_image']); ?>'); this.style.display='none';">
                </div>
                <?php endif; ?>

                <!-- Article Body -->
                <div class="article-body" style="width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; word-wrap: break-word !important; overflow-wrap: break-word !important; word-break: break-word !important;">
                    <div style="width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; word-wrap: break-word !important; overflow-wrap: break-word !important; word-break: break-word !important;">
                        <?php 
                        // Get ad code for in-article ads
                        $in_article_ad = '';
                        if (function_exists('displayAd')) {
                            $in_article_ad = displayAd('news_in_article');
                        }
                        // Insert ads after every 4 paragraphs
                        $content_with_ads = insertAdsInContent($news_item['content'], $in_article_ad);
                        echo $content_with_ads; 
                        ?>
                    </div>
                </div>

                <!-- Tags -->
                <?php if (!empty($news_item['category'])): ?>
                <div class="article-tags">
                    <div class="article-tags-label">Tags:</div>
                    <div class="tags-list">
                        <a href="news.php?category=<?php echo urlencode($news_item['category']); ?>" class="tag">
                            <?php echo htmlspecialchars($news_item['category']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Author Box -->
                <div class="author-box">
                    <div class="author-box-content">
                        <div class="author-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="author-info">
                            <h3><?php echo htmlspecialchars($news_item['author'] ?? 'Unknown'); ?></h3>
                            <p>Share a little biographical information to fill out your profile. This may be shown publicly. Such a coffee drinker, a late night sleeper, or whatever sound clumsy.</p>
                            <div class="author-social">
                                <a href="#" title="Website"><i class="fas fa-globe"></i></a>
                                <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                                <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                                <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                                <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Similar News (Related Posts) -->
                <?php if (!empty($related_news)): ?>
                <div class="related-posts">
                    <h3>Similar News</h3>
                    <div class="related-grid">
                        <?php foreach (array_slice($related_news, 0, 6) as $related): ?>
                        <div class="related-post-item">
                            <?php if (!empty($related['featured_image']) || !empty($related['image'])): ?>
                            <div class="related-post-thumb">
                                <a href="<?php echo !empty($related['slug']) ? 'news-details.php?slug=' . urlencode($related['slug']) : 'news-details.php?id=' . $related['id']; ?>">
                                    <img src="<?php echo htmlspecialchars(asset_path($related['featured_image'] ?? $related['image'])); ?>" 
                                         alt="<?php echo htmlspecialchars($related['title']); ?>">
                                    <?php if (!empty($related['category'])): ?>
                                    <span class="related-post-category"><?php echo htmlspecialchars($related['category']); ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="related-post-thumb" style="display: flex; align-items: center; justify-content: center; color: #ccc;">
                                <i class="fas fa-newspaper" style="font-size: 50px;"></i>
                            </div>
                            <?php endif; ?>
                            <div class="related-post-content">
                                <h4>
                                    <a href="<?php echo !empty($related['slug']) ? 'news-details.php?slug=' . urlencode($related['slug']) : 'news-details.php?id=' . $related['id']; ?>">
                                        <?php echo htmlspecialchars($related['title']); ?>
                                    </a>
                                </h4>
                                <div class="related-post-meta">
                                    <i class="far fa-calendar"></i>
                                    <span><?php echo date('M j, Y', strtotime($related['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Previous/Next Navigation -->
                <div class="post-navigation">
                    <?php if ($prev_news): ?>
                    <a href="<?php echo !empty($prev_news['slug']) ? 'news-details.php?slug=' . urlencode($prev_news['slug']) : 'news-details.php?id=' . $prev_news['id']; ?>" class="nav-post prev">
                        <div class="nav-post-bar"></div>
                        <div class="nav-post-content">
                            <div class="nav-post-label">Previous Post</div>
                            <h4><?php echo htmlspecialchars($prev_news['title']); ?></h4>
                        </div>
                    </a>
                    <?php else: ?>
                    <div></div>
                    <?php endif; ?>
                    
                    <?php if ($next_news): ?>
                    <a href="<?php echo !empty($next_news['slug']) ? 'news-details.php?slug=' . urlencode($next_news['slug']) : 'news-details.php?id=' . $next_news['id']; ?>" class="nav-post next">
                        <div class="nav-post-bar"></div>
                        <div class="nav-post-content">
                            <div class="nav-post-label">Next Post</div>
                            <h4><?php echo htmlspecialchars($next_news['title']); ?></h4>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Comments Section -->
                <div class="comments-section">
                    <h2 class="comments-title">
                        <?php echo $comment_count > 0 ? $comment_count . ' ' . ($comment_count == 1 ? 'Comment' : 'Comments') : 'Leave a Reply'; ?>
                    </h2>
                    
                    <!-- Display Existing Comments -->
                    <?php if (!empty($news_comments)): ?>
                    <div class="comments-list" style="margin-bottom: 40px;">
                        <?php foreach ($news_comments as $comment): ?>
                        <div class="comment-item" style="padding: 20px; margin-bottom: 20px; border-bottom: 1px solid #eee; background: #f9f9f9; border-radius: 4px;">
                            <div class="comment-header" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                <?php if (!empty($comment['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($comment['avatar']); ?>" alt="<?php echo htmlspecialchars($comment['display_name']); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: #e74c3c; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                    <?php echo strtoupper(substr($comment['display_name'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <strong style="color: #222; font-size: 14px;"><?php echo htmlspecialchars($comment['display_name']); ?></strong>
                                    <div style="font-size: 12px; color: #999;">
                                        <?php echo date('F j, Y \a\t g:i A', strtotime($comment['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="comment-content" style="color: #333; line-height: 1.6; font-size: 14px; word-wrap: break-word; overflow-wrap: break-word;">
                                <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <h3 class="comments-title" style="font-size: 20px; margin-bottom: 20px;">Leave a Reply</h3>
                    <div class="comment-form">
                        <p>Your email address will not be published. Required fields are marked <span class="required">*</span></p>
                        <div id="comment-message" style="display: none; padding: 12px; margin-bottom: 15px; border-radius: 4px;"></div>
                        <form id="news-comment-form" method="POST" action="api/news-comments.php">
                            <input type="hidden" name="news_id" value="<?php echo $news_item['id']; ?>">
                            <div class="form-group">
                                <label for="comment">Comment <span class="required">*</span></label>
                                <textarea id="comment" name="comment" required></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Name <span class="required">*</span></label>
                                    <input type="text" id="name" name="name" value="<?php echo $is_logged_in ? htmlspecialchars($_SESSION['username'] ?? '') : ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email <span class="required">*</span></label>
                                    <input type="email" id="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="website">Website</label>
                                    <input type="url" id="website" name="website">
                                </div>
                            </div>
                            <div class="form-checkbox">
                                <input type="checkbox" id="save-info" name="save_info">
                                <label for="save-info">Save my name, email, and website in this browser for the next time I comment.</label>
                            </div>
                            <button type="submit" class="submit-btn" id="comment-submit-btn">POST COMMENT</button>
                        </form>
                        <script>
                        document.getElementById('news-comment-form').addEventListener('submit', function(e) {
                            e.preventDefault();
                            
                            const form = this;
                            const submitBtn = document.getElementById('comment-submit-btn');
                            const messageDiv = document.getElementById('comment-message');
                            const originalText = submitBtn.textContent;
                            
                            // Disable submit button
                            submitBtn.disabled = true;
                            submitBtn.textContent = 'Submitting...';
                            messageDiv.style.display = 'none';
                            
                            // Get form data
                            const formData = new FormData(form);
                            
                            // Submit via AJAX
                            fetch('api/news-comments.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    messageDiv.style.display = 'block';
                                    messageDiv.style.backgroundColor = '#d1fae5';
                                    messageDiv.style.color = '#065f46';
                                    messageDiv.textContent = data.message || 'Comment submitted successfully! It will be visible after approval.';
                                    form.reset();
                                    
                                    // Reload page after 2 seconds to show new comment
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 2000);
                                } else {
                                    messageDiv.style.display = 'block';
                                    messageDiv.style.backgroundColor = '#fee2e2';
                                    messageDiv.style.color = '#991b1b';
                                    messageDiv.textContent = data.error || 'Error submitting comment. Please try again.';
                                }
                            })
                            .catch(error => {
                                messageDiv.style.display = 'block';
                                messageDiv.style.backgroundColor = '#fee2e2';
                                messageDiv.style.color = '#991b1b';
                                messageDiv.textContent = 'Network error. Please check your connection and try again.';
                            })
                            .finally(() => {
                                submitBtn.disabled = false;
                                submitBtn.textContent = originalText;
                            });
                        });
                        </script>
                    </div>
                </div>
            </article>

            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Email Subscription -->
                <div class="sidebar-widget">
                    <h3>Newsletter</h3>
                    <div class="subscribe-form">
                        <p>Subscribe to our mailing list to receives daily updates direct to your inbox!</p>
                        <form id="newsletterForm" method="POST" action="api/newsletter-subscribe.php" style="margin-bottom: 10px;">
                            <div class="subscribe-input-group">
                                <input type="email" name="email" id="newsletterEmail" class="subscribe-input" placeholder="Your email address" required>
                                <button type="submit" class="subscribe-btn" id="newsletterSubmit">SIGN UP</button>
                            </div>
                            <div id="newsletterMessage" style="margin-top: 10px; font-size: 14px;"></div>
                        </form>
                        <p class="subscribe-disclaimer">*we hate spam as much as you do</p>
                    </div>
                    <script>
                    document.getElementById('newsletterForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        const form = this;
                        const submitBtn = document.getElementById('newsletterSubmit');
                        const messageDiv = document.getElementById('newsletterMessage');
                        const email = document.getElementById('newsletterEmail').value;
                        
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Subscribing...';
                        messageDiv.innerHTML = '';
                        
                        fetch('api/newsletter-subscribe.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'email=' + encodeURIComponent(email)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                messageDiv.style.color = '#28a745';
                                messageDiv.innerHTML = data.message || 'Thank you for subscribing!';
                                form.reset();
                            } else {
                                messageDiv.style.color = '#dc3545';
                                messageDiv.innerHTML = data.error || 'Subscription failed. Please try again.';
                            }
                        })
                        .catch(error => {
                            messageDiv.style.color = '#dc3545';
                            messageDiv.innerHTML = 'An error occurred. Please try again later.';
                            console.error('Error:', error);
                        })
                        .finally(() => {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'SIGN UP';
                        });
                    });
                    </script>
                </div>

                <!-- Recent News - Large Featured -->
                <?php if (!empty($latest_news)): ?>
                <div class="sidebar-widget">
                    <h3>Recent News</h3>
                    <?php $first_news = $latest_news[0]; ?>
                    <div class="recent-featured">
                        <div class="recent-featured-thumb">
                            <a href="<?php echo !empty($first_news['slug']) ? 'news-details.php?slug=' . urlencode($first_news['slug']) : 'news-details.php?id=' . $first_news['id']; ?>">
                                <?php if (!empty($first_news['featured_image']) || !empty($first_news['image'])): ?>
                                <img src="<?php echo htmlspecialchars(asset_path($first_news['featured_image'] ?? $first_news['image'])); ?>" 
                                     alt="<?php echo htmlspecialchars($first_news['title']); ?>">
                                <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #eee; color: #999;">
                                    <i class="fas fa-newspaper" style="font-size: 50px;"></i>
                                </div>
                                <?php endif; ?>
                            </a>
                        </div>
                        <h4 class="recent-featured-title">
                            <a href="<?php echo !empty($first_news['slug']) ? 'news-details.php?slug=' . urlencode($first_news['slug']) : 'news-details.php?id=' . $first_news['id']; ?>">
                                <?php echo htmlspecialchars($first_news['title']); ?>
                            </a>
                        </h4>
                        <div class="recent-featured-meta">
                            <i class="far fa-calendar"></i>
                            <span><?php echo date('M j, Y', strtotime($first_news['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <!-- Recent News List -->
                    <ul class="widget-post-list">
                        <?php foreach (array_slice($latest_news, 1, 5) as $latest): ?>
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

                <!-- Social Media Followers -->
                <?php if (!empty($social_links['twitter']) || !empty($social_links['instagram']) || !empty($social_links['facebook']) || !empty($social_links['youtube'])): ?>
                <div class="sidebar-widget">
                    <h3>Stay Connected</h3>
                    <div class="social-followers">
                        <?php if (!empty($social_links['twitter'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['twitter']); ?>" target="_blank" class="social-item" style="text-decoration: none; color: inherit;">
                            <i class="fab fa-x-twitter"></i>
                            <div class="count">Follow</div>
                            <div class="label">Twitter</div>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($social_links['instagram'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['instagram']); ?>" target="_blank" class="social-item" style="text-decoration: none; color: inherit;">
                            <i class="fab fa-instagram"></i>
                            <div class="count">Follow</div>
                            <div class="label">Instagram</div>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($social_links['facebook'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['facebook']); ?>" target="_blank" class="social-item" style="text-decoration: none; color: inherit;">
                            <i class="fab fa-facebook-f"></i>
                            <div class="count">Follow</div>
                            <div class="label">Facebook</div>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($social_links['youtube'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['youtube']); ?>" target="_blank" class="social-item" style="text-decoration: none; color: inherit;">
                            <i class="fab fa-youtube"></i>
                            <div class="count">Subscribe</div>
                            <div class="label">YouTube</div>
                        </a>
                        <?php endif; ?>
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
