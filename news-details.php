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

// Register shutdown function to catch fatal errors and prevent HTTP 500
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Log the fatal error
        error_log("Fatal error in news-details.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        // If output hasn't started, show a generic error page
        if (!headers_sent()) {
            http_response_code(500);
            echo '<!DOCTYPE html><html><head><title>Error</title><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; padding: 40px; text-align: center;">';
            echo '<h1 style="color: #e74c3c;">An error occurred</h1>';
            echo '<p>Please try again later. If the problem persists, contact support.</p>';
            echo '<p style="color: #999; font-size: 12px; margin-top: 20px;">Error logged. Please check error logs for details.</p>';
            echo '</body></html>';
        }
    }
});

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
    if (!isset($news_item['featured_image']) || empty($news_item['featured_image']) || trim($news_item['featured_image']) === '') {
        $news_item['featured_image'] = $news_item['display_image'] ?? $news_item['image'] ?? '';
    }
    // Ensure image field is set if empty - prioritize actual image field
    if (empty($news_item['image']) || trim($news_item['image']) === '') {
        if (!empty($news_item['display_image']) && trim($news_item['display_image']) !== '') {
            $news_item['image'] = $news_item['display_image'];
        } elseif (!empty($news_item['featured_image']) && trim($news_item['featured_image']) !== '') {
            $news_item['image'] = $news_item['featured_image'];
        }
    }
    // Also ensure featured_image is set
    if (empty($news_item['featured_image']) || trim($news_item['featured_image']) === '') {
        if (!empty($news_item['image']) && trim($news_item['image']) !== '') {
            $news_item['featured_image'] = $news_item['image'];
        } elseif (!empty($news_item['display_image']) && trim($news_item['display_image']) !== '') {
            $news_item['featured_image'] = $news_item['display_image'];
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
    // First check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'news_comments'");
    if ($table_check->rowCount() > 0) {
        // Check if is_approved column exists
        $col_check = $conn->query("SHOW COLUMNS FROM news_comments LIKE 'is_approved'");
        $has_is_approved = $col_check->rowCount() > 0;
        
        // Check if name column exists
        $name_check = $conn->query("SHOW COLUMNS FROM news_comments LIKE 'name'");
        $has_name = $name_check->rowCount() > 0;
        
        if ($has_is_approved) {
            $comment_stmt = $conn->prepare("SELECT COUNT(*) as count FROM news_comments WHERE news_id = ? AND (is_approved = 1 OR is_approved IS NULL)");
            $comment_stmt->execute([$news_item['id']]);
            $comment_result = $comment_stmt->fetch(PDO::FETCH_ASSOC);
            $comment_count = $comment_result['count'] ?? 0;
            
            // Fetch comments - try with is_approved first
            try {
                if ($has_name) {
                    $comments_stmt = $conn->prepare("
                        SELECT nc.*, 
                               COALESCE(u.username, nc.name, 'Anonymous') as display_name,
                               COALESCE(u.avatar, '') as avatar
                        FROM news_comments nc
                        LEFT JOIN users u ON nc.user_id = u.id
                        WHERE nc.news_id = ? AND (nc.is_approved = 1 OR nc.is_approved IS NULL)
                        ORDER BY nc.created_at DESC
                    ");
                } else {
                    $comments_stmt = $conn->prepare("
                        SELECT nc.*, 
                               COALESCE(u.username, 'Anonymous') as display_name,
                               COALESCE(u.avatar, '') as avatar
                        FROM news_comments nc
                        LEFT JOIN users u ON nc.user_id = u.id
                        WHERE nc.news_id = ? AND (nc.is_approved = 1 OR nc.is_approved IS NULL)
                        ORDER BY nc.created_at DESC
                    ");
                }
                $comments_stmt->execute([$news_item['id']]);
                $news_comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Fallback without is_approved filter
                error_log("Comments query with is_approved failed: " . $e->getMessage());
                if ($has_name) {
                    $comments_stmt = $conn->prepare("
                        SELECT nc.*, 
                               COALESCE(u.username, nc.name, 'Anonymous') as display_name,
                               COALESCE(u.avatar, '') as avatar
                        FROM news_comments nc
                        LEFT JOIN users u ON nc.user_id = u.id
                        WHERE nc.news_id = ?
                        ORDER BY nc.created_at DESC
                    ");
                } else {
                    $comments_stmt = $conn->prepare("
                        SELECT nc.*, 
                               COALESCE(u.username, 'Anonymous') as display_name,
                               COALESCE(u.avatar, '') as avatar
                        FROM news_comments nc
                        LEFT JOIN users u ON nc.user_id = u.id
                        WHERE nc.news_id = ?
                        ORDER BY nc.created_at DESC
                    ");
                }
                $comments_stmt->execute([$news_item['id']]);
                $news_comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
                $comment_count = count($news_comments);
            }
        } else {
            // Fallback without is_approved
            $comment_stmt = $conn->prepare("SELECT COUNT(*) as count FROM news_comments WHERE news_id = ?");
            $comment_stmt->execute([$news_item['id']]);
            $comment_result = $comment_stmt->fetch(PDO::FETCH_ASSOC);
            $comment_count = $comment_result['count'] ?? 0;
            
            if ($has_name) {
                $comments_stmt = $conn->prepare("
                    SELECT nc.*, 
                           COALESCE(u.username, nc.name, 'Anonymous') as display_name,
                           COALESCE(u.avatar, '') as avatar
                    FROM news_comments nc
                    LEFT JOIN users u ON nc.user_id = u.id
                    WHERE nc.news_id = ?
                    ORDER BY nc.created_at DESC
                ");
            } else {
                $comments_stmt = $conn->prepare("
                    SELECT nc.*, 
                           COALESCE(u.username, 'Anonymous') as display_name,
                           COALESCE(u.avatar, '') as avatar
                    FROM news_comments nc
                    LEFT JOIN users u ON nc.user_id = u.id
                    WHERE nc.news_id = ?
                    ORDER BY nc.created_at DESC
                ");
            }
            $comments_stmt->execute([$news_item['id']]);
            $news_comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log('Error fetching comments: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    $comment_count = 0;
    $news_comments = [];
    
    // Last resort: Try a simple query without any joins
    try {
        $simple_stmt = $conn->prepare("SELECT * FROM news_comments WHERE news_id = ? ORDER BY created_at DESC");
        $simple_stmt->execute([$news_item['id']]);
        $news_comments = $simple_stmt->fetchAll(PDO::FETCH_ASSOC);
        $comment_count = count($news_comments);
        
        // Process comments to add display_name
        foreach ($news_comments as &$comment) {
            if (empty($comment['display_name'])) {
                $comment['display_name'] = $comment['name'] ?? $comment['username'] ?? 'Anonymous';
            }
            if (empty($comment['avatar'])) {
                $comment['avatar'] = '';
            }
        }
        unset($comment); // Break reference
    } catch (Exception $e2) {
        error_log('Simple comments query also failed: ' . $e2->getMessage());
    }
}

// Function to insert ads after every 4 paragraphs
function insertAdsInContent($content, $adCode) {
    if (empty($adCode)) {
        return $content;
    }
    
    // Clean ad code - remove head script if present (handled separately in <head>)
    $cleanAdCode = $adCode;
    // Remove AdSense head script tag (it goes in <head>, not body)
    $cleanAdCode = preg_replace('/<script[^>]*src=["\'][^"\']*pagead2\.googlesyndication\.com[^"\']*["\'][^>]*><\/script>/i', '', $cleanAdCode);
    $cleanAdCode = trim($cleanAdCode);
    
    if (empty($cleanAdCode)) {
        return $content;
    }
    
    // Wrap ad code in container - OUTPUT RAW HTML (no escaping)
    $wrappedAd = '<div class="in-article-ad" style="margin: 30px auto; text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px; width: 100%; max-width: 100%; box-sizing: border-box; overflow: visible; min-height: 100px;">' . $cleanAdCode . '</div>';
    
    // Split content by paragraph tags
    $paragraphs = preg_split('/(<\/p\s*>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    $result = '';
    $paragraphCount = 0;
    $totalParts = count($paragraphs);
    
    foreach ($paragraphs as $index => $part) {
        $result .= $part;
        
        // Check if this is a closing </p> tag
        if (preg_match('/<\/p\s*>/i', $part)) {
            $paragraphCount++;
            
            // Insert ad after every 4 paragraphs (after 4th, 8th, 12th, etc.)
            if ($paragraphCount % 4 == 0 && $index < $totalParts - 2) {
                $result .= $wrappedAd;
            }
        }
    }
    
    // If no paragraphs found, don't insert ads
    if ($paragraphCount == 0) {
        return $content;
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
    // Check if featured_image column exists for related news query
    $has_featured_image_col = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM news LIKE 'featured_image'");
        $has_featured_image_col = $col_check->rowCount() > 0;
    } catch (Exception $e) {
        $has_featured_image_col = false;
    }
    
    if ($has_featured_image_col) {
        $related_stmt = $conn->prepare("
            SELECT n.*, COALESCE(u.username, 'Unknown') as author,
                   COALESCE(n.featured_image, n.image) as display_image,
                   n.featured_image,
                   n.image
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id 
            WHERE n.category = ? AND n.id != ? AND n.is_published = 1 
            ORDER BY n.created_at DESC 
            LIMIT 6
        ");
    } else {
        $related_stmt = $conn->prepare("
            SELECT n.*, COALESCE(u.username, 'Unknown') as author,
                   n.image as display_image
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id 
            WHERE n.category = ? AND n.id != ? AND n.is_published = 1 
            ORDER BY n.created_at DESC 
            LIMIT 6
        ");
    }
    $related_stmt->execute([$news_item['category'] ?? 'News', $news_item['id']]);
    $related_news = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching related news: ' . $e->getMessage());
}

// Get latest news for sidebar (first one large, rest as list)
$latest_news = [];
try {
    // Check if featured_image column exists for latest news query
    $has_featured_image_col = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM news LIKE 'featured_image'");
        $has_featured_image_col = $col_check->rowCount() > 0;
    } catch (Exception $e) {
        $has_featured_image_col = false;
    }
    
    if ($has_featured_image_col) {
        $latest_stmt = $conn->prepare("
            SELECT n.*, COALESCE(u.username, 'Unknown') as author,
                   COALESCE(n.featured_image, n.image) as display_image,
                   n.featured_image,
                   n.image
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id 
            WHERE n.is_published = 1 AND n.id != ?
            ORDER BY n.created_at DESC 
            LIMIT 6
        ");
    } else {
        $latest_stmt = $conn->prepare("
            SELECT n.*, COALESCE(u.username, 'Unknown') as author,
                   n.image as display_image
            FROM news n 
            LEFT JOIN users u ON n.author_id = u.id 
            WHERE n.is_published = 1 AND n.id != ?
            ORDER BY n.created_at DESC 
            LIMIT 6
        ");
    }
    $latest_stmt->execute([$news_item['id']]);
    $latest_news = $latest_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching latest news: ' . $e->getMessage());
}

// Helper function for asset paths (matches song-details.php logic)
if (!function_exists('asset_path')) {
    function asset_path($path) {
        if (empty($path)) return '';
        
        // If already absolute URL, return as is (but upgrade HTTP to HTTPS if needed)
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            // If we're on HTTPS, upgrade HTTP URLs to HTTPS
            $isHttps = false;
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $isHttps = true;
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $isHttps = true;
            } elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
                $isHttps = true;
            } elseif (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
                $isHttps = true;
            }
            
            if ($isHttps && strpos($path, 'http://') === 0) {
                return str_replace('http://', 'https://', $path);
            }
            return $path;
        }
        
        // Get base URL - properly detect HTTPS for ngrok/proxy
        $protocol = 'http://';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $protocol = 'https://';
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $protocol = 'https://';
        } elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            $protocol = 'https://';
        } elseif (!empty($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') {
            $protocol = 'https://';
        }
        
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . $host;
        
        // Use SITE_URL if defined (it should already include protocol, host, and path)
        if (defined('SITE_URL') && !empty(SITE_URL)) {
            $siteUrl = rtrim(SITE_URL, '/');
            
            // If path starts with /, append directly to SITE_URL
            if (strpos($path, '/') === 0) {
                return $siteUrl . $path;
            }
            
            // Remove any '../' prefixes
            $cleanPath = ltrim($path, '../');
            $cleanPath = ltrim($cleanPath, '/');
            
            // Append path to SITE_URL
            return $siteUrl . '/' . $cleanPath;
        }
        
        // If SITE_URL not defined, use BASE_PATH if available
        $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
        
        // Ensure base_path is properly formatted
        if ($base_path !== '/' && substr($base_path, -1) !== '/') {
            $base_path .= '/';
        }
        if ($base_path !== '/' && substr($base_path, 0, 1) !== '/') {
            $base_path = '/' . $base_path;
        }
        
        // If path starts with /, make it absolute URL
        if (strpos($path, '/') === 0) {
            return $baseUrl . $path;
        }
        
        // Otherwise, make it absolute using base path
        // Remove any '../' prefixes
        $cleanPath = ltrim($path, '../');
        $cleanPath = ltrim($cleanPath, '/');
        
        return $baseUrl . $base_path . $cleanPath;
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

// Safe wrapper function to display ads without breaking the page
function safeDisplayAd($position) {
    try {
        if (!function_exists('displayAd')) {
            return '';
        }
        return displayAd($position);
    } catch (Exception $e) {
        error_log("Error displaying ad for position $position: " . $e->getMessage());
        return '';
    } catch (Error $e) {
        error_log("Fatal error displaying ad for position $position: " . $e->getMessage());
        return '';
    }
}

// Extract AdSense head scripts from ALL ad positions that might contain AdSense
$adsense_head_scripts = [];
$ad_positions_to_check = [
    'news_header', 'news_after_header', 'news_before_content', 'news_in_article',
    'news_after_content', 'news_after_tags', 'news_after_author', 'news_after_related',
    'news_after_comments', 'news_sidebar_top', 'news_sidebar', 'news_sidebar_bottom'
];

if (function_exists('getAdsByPosition')) {
    foreach ($ad_positions_to_check as $position) {
        try {
            $ad = getAdsByPosition($position);
            if ($ad && isset($ad['type']) && $ad['type'] === 'code' && !empty($ad['content'])) {
                // Check if ad content contains AdSense script tag
                if (preg_match('/<script[^>]*src=["\'][^"\']*pagead2\.googlesyndication\.com[^"\']*["\'][^>]*><\/script>/i', $ad['content'], $script_matches)) {
                    // Extract the script tag for head (avoid duplicates)
                    $script_tag = $script_matches[0];
                    if (!empty($script_tag) && !in_array($script_tag, $adsense_head_scripts)) {
                        $adsense_head_scripts[] = $script_tag;
                    }
                }
            }
        } catch (Exception $e) {
            // Continue checking other positions - don't break the page
            error_log("Error checking ad position $position for AdSense head script: " . $e->getMessage());
        } catch (Error $e) {
            // Also catch PHP 7+ Error exceptions
            error_log("Fatal error checking ad position $position for AdSense head script: " . $e->getMessage());
        }
    }
}
$adsense_head_script = !empty($adsense_head_scripts) ? implode("\n    ", $adsense_head_scripts) : '';

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
// Ensure absolute URL for Open Graph
$share_image = '';
$site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
if (empty($site_url)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $site_url = $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

if (!empty($news_item['featured_image'])) {
    $img_path = $news_item['featured_image'];
    if (strpos($img_path, 'http') === 0) {
        $share_image = $img_path; // Already absolute
    } else {
        $share_image = $site_url . '/' . ltrim($img_path, '/');
    }
} elseif (!empty($news_item['display_image'])) {
    $img_path = $news_item['display_image'];
    if (strpos($img_path, 'http') === 0) {
        $share_image = $img_path;
    } else {
        $share_image = $site_url . '/' . ltrim($img_path, '/');
    }
} elseif (!empty($news_item['image'])) {
    $img_path = $news_item['image'];
    if (strpos($img_path, 'http') === 0) {
        $share_image = $img_path;
    } else {
        $share_image = $site_url . '/' . ltrim($img_path, '/');
    }
} else {
    $share_image = $site_url . '/assets/images/default-news.jpg';
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
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/jpeg">
    <?php endif; ?>
    <meta property="og:site_name" content="<?php echo htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : ''); ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $share_title; ?>">
    <meta name="twitter:description" content="<?php echo $share_description; ?>">
    <?php if (!empty($share_image)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($share_image); ?>">
    <meta name="twitter:image:alt" content="<?php echo htmlspecialchars($share_title); ?>">
    <?php endif; ?>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/luo-style.css" rel="stylesheet">
    
    <?php 
    // Output AdSense head script if found in ad code
    if (!empty($adsense_head_script)) {
        echo $adsense_head_script . "\n    ";
    }
    ?>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html {
            width: 100% !important;
            max-width: 100vw !important;
            overflow-x: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        body {
            background: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #222;
            line-height: 1.6;
            width: 100% !important;
            max-width: 100vw !important;
            overflow-x: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        img, video, iframe {
            max-width: 100%;
            height: auto;
        }
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 30px;
            overflow-x: hidden;
            width: 100%;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .main-content {
                padding: 15px 15px !important;
                width: 100% !important;
                max-width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }
        @media (max-width: 480px) {
            .main-content {
                padding: 10px 10px !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }
        .article-container {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 40px;
            margin-top: 20px;
            width: 100%;
            box-sizing: border-box;
            padding: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        @media (max-width: 1024px) {
            .article-container {
                grid-template-columns: 1fr;
                gap: 30px;
                width: 100% !important;
                padding: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }
        .article-main {
            background: #fff;
            padding: 40px 30px;
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        @media (max-width: 768px) {
            .article-main {
                padding: 15px 15px !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }
        @media (max-width: 480px) {
            .article-main {
                padding: 10px 10px !important;
                margin: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
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
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }
        .article-body p {
            margin-bottom: 24px;
            width: 100%;
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
            box-sizing: border-box;
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        @media (max-width: 768px) {
            .article-body p {
                margin-bottom: 18px;
                padding-left: 0 !important;
                padding-right: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
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
                padding: 15px 15px !important;
                margin: 20px 0 !important;
                font-size: 18px;
                margin-left: 0 !important;
                margin-right: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
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
        
        /* CRITICAL: Fix page edges and prevent overflow */
        @media (max-width: 768px) {
            html, body {
                width: 100% !important;
                max-width: 100vw !important;
                overflow-x: hidden !important;
                margin: 0 !important;
                padding: 0 !important;
                position: relative !important;
            }
            
            /* Force equal padding on all containers */
            .main-content {
                padding: 15px 15px !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .article-container {
                padding: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                gap: 20px !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .article-main {
                padding: 15px 15px !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .article-body {
                padding: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .article-header {
                padding: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            .sidebar {
                padding: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .sidebar-widget {
                padding: 15px 15px !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            /* Force all content to fit */
            .article-body > *,
            .article-body p,
            .article-body div:not(.in-article-ad):not(.ad-container),
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
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            
            .article-body img {
                height: auto !important;
                max-width: 100% !important;
            }
            
            table {
                width: 100% !important;
                max-width: 100% !important;
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
            
            a {
                word-break: break-all !important;
                overflow-wrap: break-word !important;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 10px 10px !important;
            }
            .article-main {
                padding: 10px 10px !important;
            }
            .sidebar-widget {
                padding: 10px 10px !important;
            }
        }
        
        /* Ensure no element can cause horizontal scroll - EQUAL PADDING */
        @media (max-width: 480px) {
            .main-content {
                padding: 10px 10px !important;
            }
            .article-main {
                padding: 10px 10px !important;
            }
            .article-body {
                padding: 0 !important;
            }
            .article-body p,
            .article-body h2,
            .article-body h3,
            .article-body blockquote,
            .article-body ul,
            .article-body ol {
                max-width: 100% !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
        }
    </style>
</head>
<body>
    <?php
    // Get logo from settings BEFORE including header (same as artist-profile-mobile.php)
    // This ensures logo is available when header.php loads
    $site_logo_for_header = '';
    try {
        if ($conn) {
            $logoStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_logo'");
            $logoStmt->execute();
            $logo_result = $logoStmt->fetch(PDO::FETCH_ASSOC);
            if ($logo_result && !empty($logo_result['setting_value'])) {
                $site_logo_for_header = $logo_result['setting_value'];
                // Normalize logo path (same as artist-profile-mobile.php)
                $normalizedLogo = str_replace('\\', '/', $site_logo_for_header);
                $normalizedLogo = preg_replace('#^\.\./#', '', $normalizedLogo);
                $normalizedLogo = str_replace('../', '', $normalizedLogo);
                
                // Build full URL if needed (same as artist-profile-mobile.php)
                if (!empty($normalizedLogo) && strpos($normalizedLogo, 'http') !== 0) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
                    $baseUrl = $protocol . $host . $base_path;
                    $site_logo_for_header = $baseUrl . ltrim($normalizedLogo, '/');
                } else {
                    $site_logo_for_header = $normalizedLogo;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting logo in news-details.php: " . $e->getMessage());
        $site_logo_for_header = '';
    }
    
    // Make logo available to header.php via global variable
    $GLOBALS['site_logo_preloaded'] = $site_logo_for_header;
    
    // Ensure asset_path function is available before header is included
    if (!function_exists('asset_path')) {
        function asset_path($path) {
            if (empty($path)) return '';
            
            // If already absolute URL, return as is
            if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
                return $path;
            }
            
            // Get base URL
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . $host;
            
            // Use SITE_URL if defined
            if (defined('SITE_URL') && !empty(SITE_URL)) {
                $siteUrl = rtrim(SITE_URL, '/');
                if (strpos($path, '/') === 0) {
                    return $siteUrl . $path;
                }
                return $siteUrl . '/' . ltrim($path, '/');
            }
            
            // Build path
            $path = str_replace('\\', '/', $path);
            $path = preg_replace('#^\.\./#', '', $path);
            $path = ltrim($path, '/');
            
            $base_path = defined('BASE_PATH') ? BASE_PATH : '/';
            if ($base_path !== '/' && substr($base_path, -1) !== '/') {
                $base_path .= '/';
            }
            
            return $baseUrl . $base_path . $path;
        }
    }
    
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
            // Ensure database connection is available for header
            if ($conn) {
                // Connection is already available
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

                <?php
                // Display header ad (before article)
                $header_ad = safeDisplayAd('news_header');
                if (!empty($header_ad)): ?>
                    <div class="ad-container" style="margin: 20px 0; text-align: center; width: 100%; max-width: 100%;">
                        <?php echo $header_ad; ?>
                    </div>
                <?php endif; ?>

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

                <?php
                // Display ad after header (before featured image)
                $after_header_ad = safeDisplayAd('news_after_header');
                if (!empty($after_header_ad)): ?>
                    <div class="ad-container" style="margin: 20px 0; text-align: center; width: 100%; max-width: 100%;">
                        <?php echo $after_header_ad; ?>
                    </div>
                <?php endif; ?>

                <!-- Featured Image -->
                <?php 
                // Get featured image - use same logic as homepage slider (direct image field)
                // Homepage slider uses: $carousel_news['image'] directly
                $featured_image = '';
                
                // Use image field directly (same as homepage slider)
                if (!empty($news_item['image']) && trim($news_item['image']) !== '') {
                    $featured_image = trim($news_item['image']);
                } 
                // Fallback to featured_image if image is empty
                elseif (!empty($news_item['featured_image']) && trim($news_item['featured_image']) !== '') {
                    $featured_image = trim($news_item['featured_image']);
                } 
                // Fallback to display_image
                elseif (!empty($news_item['display_image']) && trim($news_item['display_image']) !== '') {
                    $featured_image = trim($news_item['display_image']);
                }
                
                // If still empty, query database directly (same as homepage does)
                if (empty($featured_image)) {
                    try {
                        $imgStmt = $conn->prepare("SELECT image, featured_image FROM news WHERE id = ?");
                        $imgStmt->execute([$news_item['id']]);
                        $imgData = $imgStmt->fetch(PDO::FETCH_ASSOC);
                        if ($imgData) {
                            if (!empty($imgData['image']) && trim($imgData['image']) !== '') {
                                $featured_image = trim($imgData['image']);
                            } elseif (!empty($imgData['featured_image']) && trim($imgData['featured_image']) !== '') {
                                $featured_image = trim($imgData['featured_image']);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error fetching image from DB: " . $e->getMessage());
                    }
                }
                
                // Display image directly (same as homepage slider - no asset_path needed)
                if (!empty($featured_image)): 
                ?>
                <div class="article-featured-image" style="margin: 20px 0; width: 100%;">
                    <img src="<?php echo htmlspecialchars($featured_image); ?>" 
                         alt="<?php echo htmlspecialchars($news_item['title']); ?>"
                         style="max-width: 100%; height: auto; display: block; width: 100%; border-radius: 8px;"
                         onerror="console.error('Featured image failed to load: <?php echo htmlspecialchars($featured_image); ?>'); this.style.display='none'; this.parentElement.innerHTML='<div style=\'padding: 40px; text-align: center; background: #f5f5f5; border-radius: 8px; color: #999;\'><i class=\'fas fa-image\' style=\'font-size: 48px; margin-bottom: 10px; display: block;\'></i><p>Image not available</p></div>';">
                </div>
                <?php else: ?>
                <!-- No featured image available for this news article -->
                <?php endif; ?>

                <?php
                // Display ad before content (before article body)
                $before_content_ad = safeDisplayAd('news_before_content');
                if (!empty($before_content_ad)): ?>
                    <div class="ad-container" style="margin: 20px 0; text-align: center; width: 100%; max-width: 100%;">
                        <?php echo $before_content_ad; ?>
                    </div>
                <?php endif; ?>

                <!-- Article Body -->
                <div class="article-body" style="width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; padding: 0 !important; margin-left: 0 !important; margin-right: 0 !important;">
                    <?php 
                    // Get ad code for in-article ads
                    $in_article_ad = safeDisplayAd('news_in_article');
                    // Insert ads after every 4 paragraphs
                    $content_with_ads = insertAdsInContent($news_item['content'], $in_article_ad);
                    // Output content directly - RAW HTML (no escaping)
                    // Content from database is already HTML, scripts will execute
                    echo $content_with_ads;
                    ?>
                </div>

                <?php
                // Display ad after content (after article body)
                $after_content_ad = safeDisplayAd('news_after_content');
                if (!empty($after_content_ad)): ?>
                    <div class="ad-container" style="margin: 30px 0; text-align: center; width: 100%; max-width: 100%;">
                        <?php echo $after_content_ad; ?>
                    </div>
                <?php endif; ?>

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

                <?php
                // Display ad after tags (before author box)
                $after_tags_ad = safeDisplayAd('news_after_tags');
                if (!empty($after_tags_ad)): ?>
                    <div class="ad-container" style="margin: 30px 0; text-align: center; width: 100%; max-width: 100%;">
                        <?php echo $after_tags_ad; ?>
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

                <?php
                // Display ad after author (before related posts)
                $after_author_ad = safeDisplayAd('news_after_author');
                if (!empty($after_author_ad)): ?>
                    <div class="ad-container" style="margin: 30px 0; text-align: center; width: 100%; max-width: 100%;">
                        <?php echo $after_author_ad; ?>
                    </div>
                <?php endif; ?>

                <!-- Similar News (Related Posts) -->
                <?php if (!empty($related_news)): ?>
                <div class="related-posts">
                    <h3>Similar News</h3>
                    <div class="related-grid">
                        <?php foreach (array_slice($related_news, 0, 6) as $related): ?>
                        <div class="related-post-item">
                            <?php
                            // Get image URL - use same logic as homepage slider (direct image field)
                            $related_image = '';
                            // Use image field directly (same as homepage slider)
                            if (!empty($related['image']) && trim($related['image']) !== '') {
                                $related_image = trim($related['image']);
                            } elseif (!empty($related['featured_image']) && trim($related['featured_image']) !== '') {
                                $related_image = trim($related['featured_image']);
                            } elseif (!empty($related['display_image']) && trim($related['display_image']) !== '') {
                                $related_image = trim($related['display_image']);
                            }
                            ?>
                            <?php if (!empty($related_image)): ?>
                            <div class="related-post-thumb">
                                <a href="<?php echo !empty($related['slug']) ? 'news-details.php?slug=' . urlencode($related['slug']) : 'news-details.php?id=' . $related['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($related_image); ?>" 
                                         alt="<?php echo htmlspecialchars($related['title']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover; display: block;"
                                         onerror="this.onerror=null; this.style.display='none'; this.parentElement.parentElement.innerHTML='<div style=\'display: flex; align-items: center; justify-content: center; color: #ccc; height: 100%; background: #f5f5f5;\'><i class=\'fas fa-newspaper\' style=\'font-size: 50px;\'></i></div>';">
                                    <?php if (!empty($related['category'])): ?>
                                    <span class="related-post-category"><?php echo htmlspecialchars($related['category']); ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="related-post-thumb" style="display: flex; align-items: center; justify-content: center; color: #ccc; background: #f5f5f5; min-height: 150px;">
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

                <?php
                // Display ad after related posts (before navigation)
                $after_related_ad = safeDisplayAd('news_after_related');
                if (!empty($after_related_ad)): ?>
                    <div class="ad-container" style="margin: 30px 0; text-align: center; width: 100%; max-width: 100%;">
                        <?php echo $after_related_ad; ?>
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

                <!-- Comments Section (JavaScript-based like song-details.php) -->
                <div class="comments-section" style="margin-top: 40px; padding: 30px; background: white; border-radius: 10px;">
                    <h2 class="section-title" style="margin-bottom: 30px; font-size: 24px; font-weight: 700;">Comments</h2>
                    
                    <!-- Add Comment Form -->
                    <?php if ($is_logged_in): ?>
                    <div class="add-comment-form" style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e0e0e0;">
                        <h3 style="margin-bottom: 15px; font-size: 18px;">Add a Comment</h3>
                        <div style="margin-bottom: 15px;">
                            <textarea id="comment-text" placeholder="Write your comment..." rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; resize: vertical; font-size: 14px; font-family: inherit; box-sizing: border-box;"></textarea>
                        </div>
                        <button id="submit-comment" style="padding: 10px 20px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 14px;">
                            Post Comment
                        </button>
                    </div>
                    <?php else: ?>
                    <div style="margin-bottom: 30px; padding: 20px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffc107; text-align: center;">
                        <i class="fas fa-lock" style="font-size: 24px; color: #856404; margin-bottom: 10px;"></i>
                        <p style="color: #856404; margin: 0; font-size: 14px;">
                            <a href="login.php" style="color: #e74c3c; text-decoration: underline; font-weight: 600;">Login</a> to add comments
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Comments List -->
                    <div id="comments-list" style="margin-top: 20px;">
                        <!-- Comments will be loaded here via JavaScript -->
                        <div style="text-align: center; color: #999; padding: 20px;">Loading comments...</div>
                    </div>
                </div>

                <?php
                // Display ad after comments
                $after_comments_ad = safeDisplayAd('news_after_comments');
                if (!empty($after_comments_ad)): ?>
                    <div class="ad-container" style="margin: 40px 0; text-align: center; width: 100%; max-width: 100%;">
                        <?php echo $after_comments_ad; ?>
                    </div>
                <?php endif; ?>
            </article>

            <!-- Sidebar -->
            <aside class="sidebar">
                <?php
                // Display sidebar top ad
                $sidebar_top_ad = safeDisplayAd('news_sidebar_top');
                if (!empty($sidebar_top_ad)): ?>
                    <div class="sidebar-widget">
                        <?php echo $sidebar_top_ad; ?>
                    </div>
                <?php endif; ?>

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
                                <?php
                                // Get image URL - use same logic as homepage slider (direct image field)
                                $first_news_image = '';
                                // Use image field directly (same as homepage slider)
                                if (!empty($first_news['image']) && trim($first_news['image']) !== '') {
                                    $first_news_image = trim($first_news['image']);
                                } elseif (!empty($first_news['featured_image']) && trim($first_news['featured_image']) !== '') {
                                    $first_news_image = trim($first_news['featured_image']);
                                } elseif (!empty($first_news['display_image']) && trim($first_news['display_image']) !== '') {
                                    $first_news_image = trim($first_news['display_image']);
                                }
                                ?>
                                <?php if (!empty($first_news_image)): ?>
                                <img src="<?php echo htmlspecialchars($first_news_image); ?>" 
                                     alt="<?php echo htmlspecialchars($first_news['title']); ?>"
                                     onerror="this.onerror=null; this.style.display='none'; this.parentElement.innerHTML='<div style=\'width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #eee; color: #999;\'><i class=\'fas fa-newspaper\' style=\'font-size: 50px;\'></i></div>';">
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
                            <?php
                            // Get image URL - use same logic as homepage slider (direct image field)
                            $latest_image = '';
                            // Use image field directly (same as homepage slider)
                            if (!empty($latest['image']) && trim($latest['image']) !== '') {
                                $latest_image = trim($latest['image']);
                            } elseif (!empty($latest['featured_image']) && trim($latest['featured_image']) !== '') {
                                $latest_image = trim($latest['featured_image']);
                            } elseif (!empty($latest['display_image']) && trim($latest['display_image']) !== '') {
                                $latest_image = trim($latest['display_image']);
                            }
                            ?>
                            <?php if (!empty($latest_image)): ?>
                            <div class="widget-post-thumb">
                                <a href="<?php echo !empty($latest['slug']) ? 'news-details.php?slug=' . urlencode($latest['slug']) : 'news-details.php?id=' . $latest['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($latest_image); ?>" 
                                         alt="<?php echo htmlspecialchars($latest['title']); ?>"
                                         onerror="this.onerror=null; this.style.display='none'; this.parentElement.parentElement.innerHTML='<div style=\'display: flex; align-items: center; justify-content: center; color: #ccc;\'><i class=\'fas fa-newspaper\'></i></div>';">
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
                // Display sidebar ad (middle of sidebar)
                $sidebar_ad = safeDisplayAd('news_sidebar');
                if (!empty($sidebar_ad)): ?>
                    <div class="sidebar-widget">
                        <?php echo $sidebar_ad; ?>
                    </div>
                <?php endif; ?>

                <?php
                // Display sidebar bottom ad (after all widgets)
                $sidebar_bottom_ad = safeDisplayAd('news_sidebar_bottom');
                if (!empty($sidebar_bottom_ad)): ?>
                    <div class="sidebar-widget">
                        <?php echo $sidebar_bottom_ad; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <!-- Comments and Rating JavaScript (like song-details.php) -->
    <script>
        (function() {
            const newsId = <?php echo (int)$news_item['id']; ?>;
            
            // Use absolute URL for all API calls
            let basePath = window.location.pathname;
            if (basePath.endsWith('.php') || basePath.split('/').pop().includes('.')) {
                basePath = basePath.substring(0, basePath.lastIndexOf('/') + 1);
            } else if (!basePath.endsWith('/')) {
                basePath += '/';
            }
            const apiBaseUrl = window.location.origin + basePath;
            
            // Load comments
            function loadComments() {
                const alternativePaths = [
                    apiBaseUrl + 'api/news-comments.php',
                    window.location.origin + '/api/news-comments.php',
                    'api/news-comments.php'
                ];
                
                function tryLoadComments(urlIndex) {
                    if (urlIndex >= alternativePaths.length) {
                        const commentsList = document.getElementById('comments-list');
                        if (commentsList) {
                            commentsList.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">Failed to load comments.</div>';
                        }
                        return;
                    }
                    
                    const url = alternativePaths[urlIndex] + '?action=list&news_id=' + newsId;
                    
                    fetch(url, {
                        method: 'GET',
                        mode: 'cors',
                        cache: 'no-cache'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            const commentsList = document.getElementById('comments-list');
                            if (commentsList && data.comments && data.comments.length > 0) {
                                commentsList.innerHTML = data.comments.map(comment => {
                                    const date = new Date(comment.created_at);
                                    const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                                    const avatar = comment.avatar || '';
                                    const displayName = comment.display_name || comment.name || 'Anonymous';
                                    const firstLetter = displayName.charAt(0).toUpperCase();
                                    
                                    return `
                                        <div class="comment-item" style="padding: 20px; margin-bottom: 20px; border-bottom: 1px solid #eee; background: #f9f9f9; border-radius: 4px;">
                                            <div class="comment-header" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                                ${avatar ? 
                                                    `<img src="${avatar}" alt="${displayName}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">` :
                                                    `<div style="width: 40px; height: 40px; border-radius: 50%; background: #e74c3c; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; flex-shrink: 0;">${firstLetter}</div>`
                                                }
                                                <div style="flex: 1; min-width: 0;">
                                                    <div style="font-weight: 600; color: #222; font-size: 14px; word-wrap: break-word;">${displayName}</div>
                                                    <div style="font-size: 12px; color: #999;">${formattedDate}</div>
                                                </div>
                                            </div>
                                            <div class="comment-text" style="color: #333; line-height: 1.6; font-size: 14px; word-wrap: break-word; overflow-wrap: break-word;">${(comment.comment || '').replace(/\n/g, '<br>')}</div>
                                        </div>
                                    `;
                                }).join('');
                            } else if (commentsList) {
                                commentsList.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">No comments yet. Be the first to comment!</div>';
                            }
                        } else {
                            tryLoadComments(urlIndex + 1);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading comments:', error, 'URL:', url);
                        tryLoadComments(urlIndex + 1);
                    });
                }
                
                tryLoadComments(0);
            }
            
            // Check if user is logged in
            const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
            
            // Submit comment handler
            const submitBtn = document.getElementById('submit-comment');
            if (submitBtn) {
                submitBtn.addEventListener('click', function() {
                    if (!isLoggedIn) {
                        alert('Please login to post comments');
                        window.location.href = 'login.php';
                        return;
                    }
                    
                    const commentEl = document.getElementById('comment-text');
                    const comment = commentEl ? commentEl.value.trim() : '';
                    
                    if (!comment) {
                        alert('Please enter a comment');
                        return;
                    }
                    
                    this.disabled = true;
                    this.textContent = 'Posting...';
                    
                    const alternativePaths = [
                        apiBaseUrl + 'api/news-comments.php',
                        window.location.origin + '/api/news-comments.php',
                        'api/news-comments.php'
                    ];
                    
                    function tryCommentSubmission(urlIndex) {
                        if (urlIndex >= alternativePaths.length) {
                            alert('Failed to post comment. Please try again.');
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Post Comment';
                            return;
                        }
                        
                        const url = alternativePaths[urlIndex] + '?action=add';
                        
                        fetch(url, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            mode: 'cors',
                            cache: 'no-cache',
                            body: JSON.stringify({
                                news_id: newsId,
                                comment: comment
                            })
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data && data.success) {
                                if (commentEl) commentEl.value = '';
                                setTimeout(function() {
                                    loadComments();
                                }, 100);
                                setTimeout(function() {
                                    window.location.reload();
                                }, 500);
                            } else {
                                alert(data.error || 'Failed to post comment');
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Post Comment';
                            }
                        })
                        .catch(error => {
                            console.error('Error posting comment:', error);
                            if (urlIndex < alternativePaths.length - 1) {
                                tryCommentSubmission(urlIndex + 1);
                            } else {
                                alert('Failed to post comment. Please try again.');
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Post Comment';
                            }
                        });
                    }
                    
                    tryCommentSubmission(0);
                });
            }
            
            // Load comments on page load
            loadComments();
        })();
    </script>
    
    <script>
    // Initialize AdSense ads - COMPREHENSIVE INITIALIZATION
    (function() {
        var initAttempts = 0;
        var maxAttempts = 10;
        
        function initAdSense() {
            initAttempts++;
            
            // Check if AdSense script is loaded
            if (typeof adsbygoogle === 'undefined') {
                // Check if script tag exists
                var scriptExists = false;
                var scripts = document.getElementsByTagName('script');
                for (var i = 0; i < scripts.length; i++) {
                    if (scripts[i].src && scripts[i].src.indexOf('pagead2.googlesyndication.com') !== -1) {
                        scriptExists = true;
                        break;
                    }
                }
                
                if (scriptExists && initAttempts < maxAttempts) {
                    // Script is loading, wait more
                    setTimeout(initAdSense, 1000);
                }
                return;
            }
            
            // AdSense is available, initialize ads
            try {
                // Find all .adsbygoogle elements
                var adElements = document.querySelectorAll('.adsbygoogle');
                
                if (adElements.length > 0) {
                    adElements.forEach(function(adElement) {
                        // Only initialize if not already initialized
                        if (!adElement.hasAttribute('data-adsbygoogle-status')) {
                            try {
                                (adsbygoogle = window.adsbygoogle || []).push({});
                                adElement.setAttribute('data-adsbygoogle-status', 'done');
                                console.log('AdSense ad initialized');
                            } catch(e) {
                                console.log('AdSense push error:', e);
                            }
                        }
                    });
                }
                
                // Also execute any push scripts in ad containers
                var adContainers = document.querySelectorAll('.in-article-ad');
                adContainers.forEach(function(container) {
                    var scripts = container.querySelectorAll('script');
                    scripts.forEach(function(script) {
                        if (script.textContent && script.textContent.indexOf('adsbygoogle') !== -1 && script.textContent.indexOf('push') !== -1) {
                            try {
                                // Execute the push script
                                eval(script.textContent);
                            } catch(e) {
                                // Might have already executed
                            }
                        }
                    });
                });
                
            } catch(e) {
                console.log('AdSense initialization error:', e);
            }
        }
        
        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initAdSense, 2000);
            });
        } else {
            setTimeout(initAdSense, 2000);
        }
        
        // Also try after page fully loads
        window.addEventListener('load', function() {
            setTimeout(initAdSense, 3000);
        });
        
        // Monitor for dynamically added ads
        if (window.MutationObserver) {
            var observer = new MutationObserver(function(mutations) {
                var hasNewAds = false;
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            if ((node.classList && node.classList.contains('adsbygoogle')) || 
                                (node.querySelector && node.querySelector('.adsbygoogle'))) {
                                hasNewAds = true;
                            }
                        }
                    });
                });
                if (hasNewAds) {
                    setTimeout(initAdSense, 1000);
                }
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    })();
    </script>

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
