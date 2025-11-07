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
    
    <!-- Styles will be included via header.php in body -->
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .news-article {
            background: white;
            border-radius: 8px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .news-title {
            font-size: 36px;
            font-weight: 800;
            color: #222;
            margin-bottom: 20px;
            line-height: 1.3;
        }
        .news-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }
        .news-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .news-featured-image {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .news-content {
            font-size: 18px;
            line-height: 1.8;
            color: #333;
        }
        .news-content p {
            margin-bottom: 20px;
        }
        .related-news {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .related-news h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #222;
        }
        .related-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .related-item:last-child {
            border-bottom: none;
        }
        .related-item a {
            color: #333;
            text-decoration: none;
            font-weight: 600;
        }
        .related-item a:hover {
            color: #007bff;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
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
    
    <div class="container">
        <a href="news.php" class="back-link">← Back to News</a>
        
        <article class="news-article">
            <h1 class="news-title"><?php echo htmlspecialchars($news_item['title']); ?></h1>
            
            <div class="news-meta">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($news_item['author'] ?? 'Unknown'); ?></span>
                <span><i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($news_item['created_at'])); ?></span>
                <?php if (!empty($news_item['category'])): ?>
                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($news_item['category']); ?></span>
                <?php endif; ?>
                <span><i class="fas fa-eye"></i> <?php echo number_format($news_item['views'] ?? 0); ?> views</span>
        </div>

            <?php if (!empty($news_item['featured_image'])): ?>
            <img src="<?php echo htmlspecialchars(asset_path($news_item['featured_image'])); ?>" 
                 alt="<?php echo htmlspecialchars($news_item['title']); ?>" 
                 class="news-featured-image">
            <?php endif; ?>
            
            <div class="news-content">
                <?php echo $news_item['content']; ?>
            </div>
        </article>
        
        <?php if (!empty($related_news)): ?>
        <div class="related-news">
            <h2>Related News</h2>
            <?php foreach ($related_news as $related): ?>
            <div class="related-item">
                <a href="news-details.php?slug=<?php echo urlencode($related['slug']); ?>">
                    <?php echo htmlspecialchars($related['title']); ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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
