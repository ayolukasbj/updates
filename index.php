<?php
// index.php - Homepage (Howwe.ug style)

// Enable error reporting for debugging (disable in production)
// Set DEBUG_MODE to true in config to see errors
$debug_mode = defined('DEBUG_MODE') && DEBUG_MODE === true;
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Wrap in try-catch to prevent HTTP 500
try {
    // Start session FIRST before any output or includes
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    // Load config with error handling
    if (!file_exists('config/config.php')) {
        throw new Exception('Config file not found at: ' . __DIR__ . '/config/config.php');
    }
    require_once 'config/config.php';
    
    // Verify config loaded
    if (!defined('SITE_NAME')) {
        throw new Exception('Config file loaded but SITE_NAME not defined. Check config/config.php');
    }
    
    // Check maintenance mode (before loading anything else)
    $is_maintenance = false;
    $is_admin = false;
    try {
        require_once 'config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $maintenance_setting = $result['setting_value'] ?? 'false';
            $is_maintenance = ($maintenance_setting === 'true' || $maintenance_setting === '1');
            
            // Check if user is admin (bypass maintenance mode)
            if ($is_maintenance && isset($_SESSION['user_id'])) {
                $user_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
                $user_stmt->execute([$_SESSION['user_id']]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
                    $is_admin = true;
                    $is_maintenance = false; // Allow admin to bypass
                }
            }
        }
    } catch (Exception $e) {
        error_log("Maintenance mode check error: " . $e->getMessage());
    }
    
    // Show maintenance page if enabled and user is not admin
    if ($is_maintenance && !$is_admin) {
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Maintenance Mode - <?php echo htmlspecialchars(SITE_NAME); ?></title>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .maintenance-container {
                    background: white;
                    border-radius: 20px;
                    padding: 60px 40px;
                    max-width: 600px;
                    width: 100%;
                    text-align: center;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                }
                .maintenance-icon {
                    font-size: 80px;
                    color: #667eea;
                    margin-bottom: 30px;
                    animation: pulse 2s infinite;
                }
                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                }
                h1 { 
                    font-size: 36px;
                    color: #1f2937;
                    margin-bottom: 20px;
                    font-weight: 800;
                }
                p { 
                    font-size: 18px;
                    color: #6b7280;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .info-box {
                    background: #f3f4f6;
                    border-radius: 10px;
                    padding: 20px;
                    margin-top: 30px;
                    text-align: left;
                }
                .info-box h3 {
                    color: #1f2937;
                    margin-bottom: 10px;
                    font-size: 16px;
                }
                .info-box ul {
                    color: #6b7280;
                    margin-left: 20px;
                    line-height: 1.8;
                }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <div class="maintenance-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h1>We'll Be Back Soon!</h1>
                <p>We're currently performing scheduled maintenance to improve your experience. Please check back shortly.</p>
                <div class="info-box">
                    <h3><i class="fas fa-info-circle"></i> What's happening?</h3>
                    <ul>
                        <li>System updates and improvements</li>
                        <li>Performance optimizations</li>
                        <li>Security enhancements</li>
                    </ul>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Load song storage with error handling
    if (!file_exists('includes/song-storage.php')) {
        throw new Exception('Song storage file not found at: ' . __DIR__ . '/includes/song-storage.php');
    }
    require_once 'includes/song-storage.php';

// Get data
$all_songs = [];
$top_chart = [];
$new_songs = [];
$trending_songs = [];

try {
    $all_songs = getAllSongs();
} catch (Exception $e) {
    error_log("Error getting all songs: " . $e->getMessage());
    $all_songs = [];
}

try {
    $top_chart = getTopChart(5);
} catch (Exception $e) {
    error_log("Error getting top chart: " . $e->getMessage());
    $top_chart = [];
}

try {
    $new_songs = getNewSongs(12);
} catch (Exception $e) {
    error_log("Error getting new songs: " . $e->getMessage());
    $new_songs = [];
}

try {
    $recent_songs = getRecentSongs(8);
    error_log("index.php: getRecentSongs returned " . count($recent_songs) . " songs");
    if (empty($recent_songs)) {
        error_log("index.php: WARNING - getRecentSongs returned empty array");
    }
} catch (Exception $e) {
    error_log("Error getting recent songs: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $recent_songs = [];
}

try {
    $trending_songs = getTrendingSongs(8);
} catch (Exception $e) {
    error_log("Error getting trending songs: " . $e->getMessage());
    $trending_songs = [];
}

// Get artists using the same logic as artists.php (central point for artist stats)
$featured_artists = [];
$conn = null; // Initialize connection variable
try {
    require_once 'config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if connection is valid
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if song_collaborators table exists
    $collabTableExists = false;
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'song_collaborators'");
        $collabTableExists = $checkTable->rowCount() > 0;
    } catch (Exception $e) {
        $collabTableExists = false;
    }
    
    // Get unique artists from songs (uploaders) and collaborators - same logic as artists.php
    $sql = "
        SELECT 
            COALESCE(MAX(primary_artist_name), MAX(artist_name)) as name,
            MAX(avatar) as avatar,
            MAX(user_id) as id,
            COUNT(DISTINCT song_id) as songs_count,
            SUM(song_plays) as total_plays,
            SUM(song_downloads) as total_downloads
        FROM (
            -- First, get unique artist-song combinations with MAX plays/downloads per song
            SELECT 
                artist_name,
                primary_artist_name,
                avatar,
                user_id,
                song_id,
                MAX(song_plays) as song_plays,
                MAX(song_downloads) as song_downloads
            FROM (
                -- Songs uploaded by user
                SELECT 
                    COALESCE(s.artist, u.username, 'Unknown Artist') as artist_name,
                    COALESCE(u.username, s.artist, 'Unknown Artist') as primary_artist_name,
                    COALESCE(u.avatar, '') as avatar,
                    COALESCE(u.id, 0) as user_id,
                    s.id as song_id,
                    COALESCE(s.plays, 0) as song_plays,
                    COALESCE(s.downloads, 0) as song_downloads
                FROM songs s
                LEFT JOIN users u ON s.uploaded_by = u.id
                WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                AND (COALESCE(s.artist, u.username) IS NOT NULL)
                
                " . ($collabTableExists ? "
                UNION ALL
                -- Songs where user is a collaborator (only if they're not already the uploader)
                SELECT 
                    COALESCE(u2.username, 'Unknown Artist') as artist_name,
                    COALESCE(u2.username, 'Unknown Artist') as primary_artist_name,
                    COALESCE(u2.avatar, '') as avatar,
                    COALESCE(u2.id, 0) as user_id,
                    s2.id as song_id,
                    COALESCE(s2.plays, 0) as song_plays,
                    COALESCE(s2.downloads, 0) as song_downloads
                FROM song_collaborators sc
                INNER JOIN songs s2 ON sc.song_id = s2.id
                INNER JOIN users u2 ON sc.user_id = u2.id
                LEFT JOIN users u3 ON s2.uploaded_by = u3.id
                WHERE (s2.status = 'active' OR s2.status IS NULL OR s2.status = '' OR s2.status = 'approved')
                AND u2.username IS NOT NULL
                -- Exclude if this artist is already the uploader for this song (by user_id to prevent duplicates)
                AND NOT (s2.uploaded_by = u2.id OR COALESCE(s2.artist, u3.username) = COALESCE(u2.username, 'Unknown Artist'))
                " : "") . "
            ) as all_artist_songs
            GROUP BY artist_name, primary_artist_name, song_id, avatar, user_id
        ) as unique_songs
        GROUP BY 
            CASE WHEN user_id > 0 THEN user_id ELSE NULL END,
            LOWER(TRIM(COALESCE(primary_artist_name, artist_name)))
        HAVING songs_count > 0
        ORDER BY total_plays DESC, songs_count DESC, name ASC
        LIMIT 12
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $featured_artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching featured artists: " . $e->getMessage());
    // Fallback to getFeaturedArtists
    $featured_artists = getFeaturedArtists(12);
}

// Get news grouped by categories
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$news_by_category = [];
$categories = [];

try {
    // Check if connection is valid before using it
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if news_categories table exists
    $checkCat = $conn->query("SHOW TABLES LIKE 'news_categories'");
    if ($checkCat->rowCount() > 0) {
        // Get active categories
        $catStmt = $conn->query("SELECT * FROM news_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Check if news table exists
    $all_news = [];
    try {
        $checkNews = $conn->query("SHOW TABLES LIKE 'news'");
        if ($checkNews->rowCount() > 0) {
            // Get all news (50 articles for homepage) - Core Author System: Admin is Priority 1
            // Ensure author is always an admin (priority 1), not submitter
            $newsStmt = $conn->query("
                SELECT n.*, 
                       COALESCE(
                           CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                           (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                           'Admin'
                       ) as author 
                FROM news n 
                LEFT JOIN users u_author ON n.author_id = u_author.id 
                WHERE n.is_published = 1 
                ORDER BY n.created_at DESC 
                LIMIT 50
            ");
            $all_news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $all_news = [];
        error_log("Error loading news: " . $e->getMessage());
    }
    
    // Group news by category
    foreach ($all_news as $news) {
        $cat = $news['category'] ?? 'Uncategorized';
        
        if (!isset($news_by_category[$cat])) {
            $news_by_category[$cat] = [];
        }
        
        $news_by_category[$cat][] = $news;
    }
    
    // Check if news table exists for featured carousel
    $featured_carousel = [];
    $ticker_news = [];
    try {
        $checkNews2 = $conn->query("SHOW TABLES LIKE 'news'");
        if ($checkNews2->rowCount() > 0) {
            // Get featured news for carousel (6 most recent, prioritize featured ones) - Core Author System: Admin is Priority 1
            // Ensure author is always an admin (priority 1), not submitter
            try {
                $featuredStmt = $conn->query("
                    SELECT n.*, 
                           COALESCE(
                               CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                               (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                               'Admin'
                           ) as author 
                    FROM news n 
                    LEFT JOIN users u_author ON n.author_id = u_author.id 
                    WHERE n.is_published = 1 
                    ORDER BY n.featured DESC, n.created_at DESC 
                    LIMIT 6
                ");
                $featured_carousel = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $featured_carousel = [];
                error_log("Error loading featured carousel: " . $e->getMessage());
            }
            
            // Get news for scrolling ticker (exclusive/featured news or recent news)
            try {
            // First try to get exclusive/featured news - Core Author System: Admin is Priority 1
            // Ensure author is always an admin (priority 1), not submitter
            $tickerStmt = $conn->query("
                SELECT n.*, 
                       COALESCE(
                           CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                           (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                           'Admin'
                       ) as author 
                FROM news n 
                LEFT JOIN users u_author ON n.author_id = u_author.id 
                WHERE n.is_published = 1 AND (n.featured = 1 OR n.category = 'Exclusive' OR n.category LIKE '%Exclusive%') 
                ORDER BY n.created_at DESC 
                LIMIT 10
            ");
            $ticker_news = $tickerStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no exclusive news, get recent news (always get latest)
            if (empty($ticker_news)) {
                $tickerStmt = $conn->query("
                SELECT n.*, 
                       COALESCE(
                           CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                           (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                           'Admin'
                       ) as author 
                FROM news n 
                LEFT JOIN users u_author ON n.author_id = u_author.id 
                WHERE n.is_published = 1 
                ORDER BY n.created_at DESC 
                LIMIT 10
            ");
            $ticker_news = $tickerStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Ensure we have at least some news - get latest if still empty
        if (empty($ticker_news)) {
            $tickerStmt = $conn->query("
                SELECT n.*, 
                       COALESCE(
                           CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                           (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                           'Admin'
                       ) as author 
                FROM news n 
                LEFT JOIN users u_author ON n.author_id = u_author.id 
                WHERE n.is_published = 1 
                ORDER BY n.created_at DESC 
                LIMIT 10
            ");
            $ticker_news = $tickerStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Ticker news error: " . $e->getMessage());
        // Fallback: try to get any published news
        try {
            $tickerStmt = $conn->query("
                SELECT n.*, 
                       COALESCE(
                           CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                           (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                           'Admin'
                       ) as author 
                FROM news n 
                LEFT JOIN users u_author ON n.author_id = u_author.id 
                WHERE n.is_published = 1 
                ORDER BY n.created_at DESC 
                LIMIT 10
            ");
            $ticker_news = $tickerStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            $ticker_news = [];
        }
            } // End ticker news try
        } // End if checkNews2
    } catch (Exception $e) {
        $featured_carousel = [];
        $ticker_news = [];
        error_log("Error checking news table for carousel: " . $e->getMessage());
    }
    
    // Get featured news (most recent with featured flag) - Core Author System: Admin is Priority 1
    // Ensure author is always an admin (priority 1), not submitter
    $featured_news = null;
    try {
        if ($conn) {
            $checkNews3 = $conn->query("SHOW TABLES LIKE 'news'");
            if ($checkNews3->rowCount() > 0) {
                $featuredStmt2 = $conn->query("
                SELECT n.*, 
                       COALESCE(
                           CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                           (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                           'Admin'
                       ) as author 
                FROM news n 
                LEFT JOIN users u_author ON n.author_id = u_author.id 
                WHERE n.is_published = 1 AND n.featured = 1 
                ORDER BY n.created_at DESC 
                LIMIT 1
                ");
                $featured_news = $featuredStmt2->fetch(PDO::FETCH_ASSOC);
                
                if (!$featured_news && !empty($all_news)) {
                    $featured_news = $all_news[0]; // Use most recent if no featured
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error loading featured news: " . $e->getMessage());
        if (!empty($all_news)) {
            $featured_news = $all_news[0]; // Use most recent if no featured
        }
    }
    
    // Get news by specific categories for sections - Core Author System: Admin is Priority 1
    // Ensure author is always an admin (priority 1), not submitter
    // Politics section - Display posts from news category called 'Politics'
    $politics_news = [];
    try {
        if ($conn) {
            $checkNews4 = $conn->query("SHOW TABLES LIKE 'news'");
            if ($checkNews4->rowCount() > 0) {
                $politics_news = $conn->query("
                    SELECT n.*, 
                           COALESCE(
                               CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                               (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                               'Admin'
                           ) as author 
                    FROM news n 
                    LEFT JOIN users u_author ON n.author_id = u_author.id 
                    WHERE n.is_published = 1 AND n.category = 'Politics' 
                    ORDER BY n.created_at DESC 
                    LIMIT 6
                ")->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log("Politics news query error: " . $e->getMessage());
        $politics_news = [];
    }
    
    // Get recent news for Recent News section
    $recent_news_section = [];
    try {
        if ($conn) {
            $checkNews5 = $conn->query("SHOW TABLES LIKE 'news'");
            if ($checkNews5->rowCount() > 0) {
                $recentStmt = $conn->query("
                    SELECT n.*, 
                           COALESCE(
                               CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                               (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                               'Admin'
                           ) as author 
                    FROM news n 
                    LEFT JOIN users u_author ON n.author_id = u_author.id 
                    WHERE n.is_published = 1 
                    ORDER BY n.created_at DESC 
                    LIMIT 6
                ");
                $recent_news_section = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        error_log("Recent news query error: " . $e->getMessage());
        $recent_news_section = [];
    }
    
    // Get most popular songs for sidebar (Today, This Week, This Month)
    $popular_today = [];
    $popular_week = [];
    $popular_month = [];
    try {
        if ($conn) {
            $checkSongs = $conn->query("SHOW TABLES LIKE 'songs'");
            if ($checkSongs->rowCount() > 0) {
                // Today - Most played songs today (by upload date)
                try {
                    $popularTodayStmt = $conn->query("
                        SELECT s.*, 
                               s.uploaded_by,
                               COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                               COALESCE(s.is_collaboration, 0) as is_collaboration,
                               COALESCE(s.plays, 0) as plays,
                               COALESCE(s.downloads, 0) as downloads,
                               COALESCE(s.upload_date, s.created_at, s.uploaded_at) as uploaded_at
                        FROM songs s
                        LEFT JOIN users u ON s.uploaded_by = u.id
                        WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                        AND DATE(COALESCE(s.upload_date, s.created_at, s.uploaded_at)) = CURDATE()
                        ORDER BY s.downloads DESC, s.plays DESC, s.id DESC
                        LIMIT 5
                    ");
                    $popular_today = $popularTodayStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    error_log("Popular songs today error: " . $e->getMessage());
                    $popular_today = [];
                }
                
                // This Week - Most played songs this week
                try {
                    $popularWeekStmt = $conn->query("
                        SELECT s.*, 
                               s.uploaded_by,
                               COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                               COALESCE(s.is_collaboration, 0) as is_collaboration,
                               COALESCE(s.plays, 0) as plays,
                               COALESCE(s.downloads, 0) as downloads,
                               COALESCE(s.upload_date, s.created_at, s.uploaded_at) as uploaded_at
                        FROM songs s
                        LEFT JOIN users u ON s.uploaded_by = u.id
                        WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                        AND COALESCE(s.upload_date, s.created_at, s.uploaded_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        ORDER BY s.downloads DESC, s.plays DESC, s.id DESC
                        LIMIT 5
                    ");
                    $popular_week = $popularWeekStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    error_log("Popular songs week error: " . $e->getMessage());
                    $popular_week = [];
                }
                
                // This Month - Most played songs this month
                try {
                    $popularMonthStmt = $conn->query("
                        SELECT s.*, 
                               s.uploaded_by,
                               COALESCE(s.artist, u.username, 'Unknown Artist') as artist,
                               COALESCE(s.is_collaboration, 0) as is_collaboration,
                               COALESCE(s.plays, 0) as plays,
                               COALESCE(s.downloads, 0) as downloads,
                               COALESCE(s.upload_date, s.created_at, s.uploaded_at) as uploaded_at
                        FROM songs s
                        LEFT JOIN users u ON s.uploaded_by = u.id
                        WHERE (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                        AND COALESCE(s.upload_date, s.created_at, s.uploaded_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        ORDER BY s.downloads DESC, s.plays DESC, s.id DESC
                        LIMIT 5
                    ");
                    $popular_month = $popularMonthStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    error_log("Popular songs month error: " . $e->getMessage());
                    $popular_month = [];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Popular songs query error: " . $e->getMessage());
        $popular_today = [];
        $popular_week = [];
        $popular_month = [];
    }
    
    // Only query if connection is valid
    $community_news = [];
    try {
        if ($conn) {
            $checkNews7 = $conn->query("SHOW TABLES LIKE 'news'");
            if ($checkNews7->rowCount() > 0) {
                try {
                    $community_news = $conn->query("
                        SELECT n.*, 
                               COALESCE(
                                   CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                                   (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                                   'Admin'
                               ) as author 
                        FROM news n 
                        LEFT JOIN users u_author ON n.author_id = u_author.id 
                        WHERE n.is_published = 1 AND (n.category = 'Community' OR n.category = 'Local' OR n.category = 'Regional') 
                        ORDER BY n.created_at DESC 
                        LIMIT 6
                    ")->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $community_news = [];
                }
                
                try {
                    $entertainment_news = $conn->query("
                        SELECT n.*, 
                               COALESCE(
                                   CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                                   (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                                   'Admin'
                               ) as author 
                        FROM news n 
                        LEFT JOIN users u_author ON n.author_id = u_author.id 
                        WHERE n.is_published = 1 AND n.category = 'Entertainment' 
                        ORDER BY n.created_at DESC 
                        LIMIT 6
                    ")->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $entertainment_news = [];
                }
                
                try {
                    $featured_stories = $conn->query("
                        SELECT n.*, 
                               COALESCE(
                                   CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                                   (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                                   'Admin'
                               ) as author 
                        FROM news n 
                        LEFT JOIN users u_author ON n.author_id = u_author.id 
                        WHERE n.is_published = 1 AND n.featured = 1 
                        ORDER BY n.created_at DESC 
                        LIMIT 6
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    
                    // If no featured stories, use recent news - Core Author System: Admin is Priority 1
                    // Ensure author is always an admin (priority 1), not submitter
                    if (empty($featured_stories)) {
                        try {
                            $featured_stories = $conn->query("
                                SELECT n.*, 
                                       COALESCE(
                                           CASE WHEN u_author.role IN ('admin', 'super_admin') THEN u_author.username END,
                                           (SELECT username FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1),
                                           'Admin'
                                       ) as author 
                                FROM news n 
                                LEFT JOIN users u_author ON n.author_id = u_author.id 
                                WHERE n.is_published = 1 
                                ORDER BY n.created_at DESC 
                                LIMIT 6
                            ")->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $featured_stories = [];
                        }
                    }
                } catch (Exception $e) {
                    $featured_stories = [];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error loading community/entertainment/featured news: " . $e->getMessage());
        $community_news = [];
        $entertainment_news = [];
        $featured_stories = [];
    }
    
    // Get homepage sections from database (managed in admin/homepage-manager.php)
    $homepage_sections = [];
    try {
        if ($conn) {
            $checkSections = $conn->query("SHOW TABLES LIKE 'homepage_sections'");
            if ($checkSections->rowCount() > 0) {
                $sectionsStmt = $conn->query("SELECT * FROM homepage_sections WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
                $homepage_sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        // Table might not exist yet
        $homepage_sections = [];
        error_log("Homepage sections error: " . $e->getMessage());
    }
    
    // Get Business section tabs from database
    $business_tabs = [];
    $business_tab_news_data = [];
    try {
        if ($conn) {
            $checkTabs = $conn->query("SHOW TABLES LIKE 'business_tabs'");
            if ($checkTabs->rowCount() > 0) {
                $tabsStmt = $conn->query("SELECT * FROM business_tabs WHERE is_active = 1 ORDER BY sort_order ASC, tab_label ASC");
                $business_tabs = $tabsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        // Fetch news for each tab based on filter settings
        if (!empty($business_tabs)) {
            $checkNews8 = $conn->query("SHOW TABLES LIKE 'news'");
            if ($checkNews8->rowCount() > 0) {
                foreach ($business_tabs as $tab) {
                    try {
                        $whereClause = "is_published = 1";
                        $params = [];
                        
                        if (isset($tab['filter_type'])) {
                            if ($tab['filter_type'] === 'category') {
                                $whereClause .= " AND category = ?";
                                $params[] = $tab['filter_value'] ?? '';
                            } elseif ($tab['filter_type'] === 'keyword') {
                                $keywords = explode(',', $tab['filter_value'] ?? '');
                                $keywordConditions = [];
                                foreach ($keywords as $keyword) {
                                    $keyword = trim($keyword);
                                    if (!empty($keyword)) {
                                        $keywordConditions[] = "(title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
                                        $searchTerm = "%$keyword%";
                                        $params[] = $searchTerm;
                                        $params[] = $searchTerm;
                                        $params[] = $searchTerm;
                                    }
                                }
                                if (!empty($keywordConditions)) {
                                    $whereClause .= " AND (" . implode(" OR ", $keywordConditions) . ")";
                                }
                            } elseif ($tab['filter_type'] === 'custom') {
                                // For custom SQL, append to WHERE clause (use with caution)
                                $whereClause .= " AND (" . ($tab['filter_value'] ?? '1=0') . ")";
                            }
                        }
                        
                        $sql = "SELECT n.*, COALESCE(u.username, 'Unknown') as author FROM news n LEFT JOIN users u ON n.author_id = u.id WHERE $whereClause ORDER BY n.created_at DESC LIMIT 6";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        $business_tab_news_data[$tab['tab_key'] ?? 'tab_' . $tab['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        error_log("Error loading news for tab: " . $e->getMessage());
                        $business_tab_news_data[$tab['tab_key'] ?? 'tab_' . $tab['id']] = [];
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Fallback to default tabs if table doesn't exist
        error_log("Error loading business tabs: " . $e->getMessage());
        $business_tabs = [];
        $business_tab_news_data = [];
    }
    
    // Get trending/popular news - JOIN with users to get author username
    $trending_news = [];
    try {
        if ($conn) {
            $checkNews10 = $conn->query("SHOW TABLES LIKE 'news'");
            if ($checkNews10->rowCount() > 0) {
                $trending_news = $conn->query("
                    SELECT n.*, COALESCE(u.username, 'Unknown') as author 
                    FROM news n 
                    LEFT JOIN users u ON n.author_id = u.id 
                    WHERE n.is_published = 1 
                    ORDER BY n.views DESC, n.created_at DESC 
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        $trending_news = [];
        error_log("Error loading trending news: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("Homepage database error: " . $e->getMessage());
    // Fallback to empty arrays
    $news_by_category = [];
    $categories = [];
    $featured_news = null;
    $featured_carousel = [];
    $politics_news = [];
    $entertainment_news = [];
    $tech_news = [];
    $lifestyle_news = [];
    $featured_stories = [];
    $trending_news = [];
    $business_tabs = [];
    $business_tab_news_data = [];
}

// Load theme settings
try {
    require_once __DIR__ . '/includes/theme-loader.php';
} catch (Exception $e) {
    error_log("Error loading theme: " . $e->getMessage());
}

// Load settings manager for site name and slogan
try {
    require_once __DIR__ . '/includes/settings.php';
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
}

// Get site settings for meta tags
$site_name = SITE_NAME;
$site_slogan = '';
$site_description = '';
$site_logo = '';

try {
    if (class_exists('SettingsManager')) {
        $site_name = SettingsManager::getSiteName();
        $site_slogan = SettingsManager::getSiteSlogan();
        $site_description = SettingsManager::get('site_description', '');
        $site_logo = SettingsManager::getSiteLogo();
    }
} catch (Exception $e) {
    error_log("Error getting site settings: " . $e->getMessage());
    // Use defaults from constants
    $site_name = defined('SITE_NAME') ? SITE_NAME : 'Music Platform';
    $site_slogan = defined('SITE_SLOGAN') ? SITE_SLOGAN : '';
}

// Build title with slogan if available
$page_title = $site_name;
if (!empty($site_slogan)) {
    $page_title .= ' - ' . $site_slogan;
} else {
    $page_title .= ' - Music Streaming Platform';
}

// Build meta description
$meta_description = !empty($site_description) ? $site_description : (!empty($site_slogan) ? $site_slogan : 'Discover and stream the latest music from your favorite artists.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Meta Description -->
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php if (!empty($site_logo)): ?>
    <meta property="og:image" content="<?php echo SITE_URL . htmlspecialchars($site_logo); ?>">
    <?php endif; ?>
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo SITE_URL; ?>">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php if (!empty($site_logo)): ?>
    <meta property="twitter:image" content="<?php echo SITE_URL . htmlspecialchars($site_logo); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php renderThemeStyles(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #333;
        }

        /* Container */
        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Featured News Section - Main Slider Layout */
        .featured-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin: 30px 0;
            background: transparent;
        }
        
        /* Homepage Main Slider */
        .homepage-slider {
            position: relative;
            height: 500px;
            overflow: hidden;
            border-radius: 8px;
            background: #000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .homepage-slider-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s;
        }
        
        .homepage-slider-slide.active {
            opacity: 1;
            z-index: 2;
        }
        
        .homepage-slider-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .homepage-slider-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            padding: 50px 40px 40px;
            color: white;
            z-index: 3;
        }
        
        .homepage-slider-category {
            background: #dc3545;
            color: white;
            padding: 6px 15px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 15px;
            letter-spacing: 0.5px;
        }
        
        .homepage-slider-title {
            font-size: 36px;
            font-weight: 700;
            color: white;
            margin: 0 0 15px 0;
            line-height: 1.3;
        }
        
        .homepage-slider-meta {
            font-size: 13px;
            color: rgba(255,255,255,0.9);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .homepage-slider-nav {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: #dc3545;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 20px;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        
        .homepage-slider-nav:hover {
            background: #c82333;
        }
        
        .homepage-slider-nav.prev {
            left: 20px;
            right: auto;
        }
        
        /* Right Sidebar Slider */
        .right-slider {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            height: fit-content;
        }
        
        .right-slider-tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .right-slider-tab {
            flex: 1;
            padding: 15px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            color: #666;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        
        .right-slider-tab.active {
            color: #dc3545;
            border-bottom-color: #dc3545;
        }
        
        .right-slider-tab:hover {
            color: #dc3545;
        }
        
        .right-slider-content {
            padding: 20px;
        }
        
        .right-slider-tab-content {
            display: none;
        }
        
        .right-slider-tab-content.active {
            display: block;
        }
        
        .right-slider-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none;
            color: inherit;
            transition: background 0.2s;
        }
        
        .right-slider-item:last-child {
            border-bottom: none;
        }
        
        .right-slider-item:hover {
            background: #f8f9fa;
            padding-left: 10px;
            padding-right: 10px;
            margin-left: -10px;
            margin-right: -10px;
        }
        
        .right-slider-item-thumb {
            width: 100px;
            height: 70px;
            flex-shrink: 0;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .right-slider-item-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .right-slider-item-thumb .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .right-slider-item:hover .play-overlay {
            opacity: 1;
        }
        
        .right-slider-item-info {
            flex: 1;
        }
        
        .right-slider-item-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin: 0 0 8px 0;
            line-height: 1.4;
        }
        
        .right-slider-item-date {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .featured-main {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
            padding: 15px;
        }

        .featured-large {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            width: 300px;
            height: 300px;
            min-width: 300px;
            min-height: 300px;
            max-width: 300px;
            max-height: 300px;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .featured-large:hover {
            transform: scale(1.02);
        }

        .featured-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .featured-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            padding: 30px 20px 20px;
            color: white;
        }

        .featured-category {
            display: inline-block;
            background: #e74c3c;
            padding: 5px 15px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .featured-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .featured-meta {
            font-size: 13px;
            opacity: 0.9;
        }

        .featured-side {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .featured-small {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            width: 300px;
            height: 300px;
            min-width: 300px;
            min-height: 300px;
            max-width: 300px;
            max-height: 300px;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .featured-small:hover {
            transform: scale(1.02);
        }

        .featured-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .featured-small .featured-overlay {
            padding: 15px;
        }

        .featured-small .featured-title {
            font-size: 16px;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #e74c3c;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }

        .view-all {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        /* Hot 100 Chart */
        .chart-container {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .chart-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }

        .chart-item:hover {
            background: #f8f9fa;
        }

        .chart-rank {
            font-size: 24px;
            font-weight: 700;
            color: #e74c3c;
            min-width: 40px;
            text-align: center;
        }

        .chart-cover {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            object-fit: cover;
            background: #f0f0f0;
        }

        .chart-info {
            flex: 1;
        }

        .chart-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .chart-artist {
            font-size: 14px;
            color: #7f8c8d;
        }

        .chart-play-btn {
            background: #e74c3c;
            border: none;
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .chart-play-btn:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        /* News Grid */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .news-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s; 
        }

        .news-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-5px); 
        }

        .news-image {
            position: relative;
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .news-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .news-image i {
            font-size: 48px;
            color: rgba(255,255,255,0.5);
        }

        .news-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #e74c3c;
            color: white;
            padding: 5px 12px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .news-content {
            padding: 15px;
        }

        .news-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: #95a5a6;
        }

        .news-date {
            color: #7f8c8d;
        }

        /* Music Grid */
        .music-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .music-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                max-height: none;
            }
            
            /* Limit to 2 rows on mobile (4 items visible) */
            .music-grid > .music-card:nth-child(n+5) {
                display: none;
            }
            
            /* Songs Recently Added - 2 columns on mobile */
            .songs-recently-added-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px !important;
            }
        }
        
        /* Songs Recently Added Grid - Desktop: 4 columns */
        .songs-recently-added-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 1200px) {
            .songs-recently-added-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .songs-recently-added-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px !important;
            }
        }

        .music-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s;
        }

        .music-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }

        .music-cover {
            position: relative;
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            overflow: hidden;
        }

        .music-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .music-play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .music-play-overlay .play-button {
            pointer-events: all;
        }

        .music-card:hover .music-play-overlay {
            opacity: 1;
        }

        .play-button {
            width: 55px;
            height: 55px;
            background: #e74c3c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .play-button:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        .music-info {
            padding: 15px;
        }

        .music-title {
            font-size: 15px;
            font-weight: 600; 
            color: #2c3e50;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .music-artist {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .music-stats {
            display: flex; 
            gap: 15px;
            font-size: 12px; 
            color: #95a5a6;
        }

        /* News Ticker */
        .news-ticker {
            background: white;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            overflow: hidden;
            height: 50px;
            position: relative;
            z-index: 100;
        }
        
        .ticker-label {
            background: #dc3545;
            color: white;
            padding: 0 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            height: 100%;
            flex-shrink: 0;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .ticker-label-icon {
            width: 16px;
            height: 16px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: repeat(4, 1fr);
            gap: 1px;
        }
        
        .ticker-label-icon > div {
            background: white;
            opacity: 0.9;
        }
        
        .ticker-label-icon > div:nth-child(2),
        .ticker-label-icon > div:nth-child(3),
        .ticker-label-icon > div:nth-child(6),
        .ticker-label-icon > div:nth-child(7),
        .ticker-label-icon > div:nth-child(10),
        .ticker-label-icon > div:nth-child(11),
        .ticker-label-icon > div:nth-child(14),
        .ticker-label-icon > div:nth-child(15) {
            opacity: 0.5;
        }
        
        .ticker-content {
            flex: 1;
            overflow: hidden;
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
        }
        
        .ticker-scroll {
            display: flex;
            align-items: center;
            animation: scroll-ticker linear infinite;
            gap: 30px;
            white-space: nowrap;
        }
        
        .ticker-item {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 0 20px;
            cursor: pointer;
            transition: opacity 0.2s;
            flex-shrink: 0;
        }
        
        .ticker-item:hover {
            opacity: 0.8;
        }
        
        .ticker-item-text {
            font-weight: 700;
            font-size: 14px;
            color: #000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .ticker-item-thumb {
            width: 35px;
            height: 35px;
            border-radius: 4px;
            object-fit: cover;
            flex-shrink: 0;
        }
        
        @keyframes scroll-ticker {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }
        
        .ticker-scroll:hover {
            animation-play-state: paused;
        }
        
        @media (max-width: 768px) {
            .news-ticker {
                height: 45px;
            }
            
            .ticker-label {
                padding: 0 15px;
                font-size: 12px;
            }
            
            .ticker-item-text {
                font-size: 12px;
            }
            
            .ticker-item-thumb {
                width: 30px;
                height: 30px;
            }
        }

        /* Footer */
        .footer {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 40px 0 20px;
            margin-top: 50px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .footer-section h4 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #fff;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            }

        .footer-links li {
            margin-bottom: 8px;
            }

        .footer-links a {
            color: #bdc3c7;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            }

        .footer-links a:hover {
            color: #e74c3c;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #34495e;
            font-size: 14px;
            color: #95a5a6;
            }
        
        /* Responsive */
        @media (max-width: 1024px) {
            /* Make Music Chart and Newly Added stack on tablets */
            div[style*="grid-template-columns: 2fr 1fr"] {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
            }
            
            div[style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
            }
            
            /* Artistes grid - 4 columns on tablets */
            .artistes-grid {
                grid-template-columns: repeat(4, 1fr) !important;
            }
        }
        
        @media (max-width: 768px) {
            .featured-main {
                grid-template-columns: 1fr;
            }

            .featured-large {
                height: 300px;
            }

            .news-grid {
                grid-template-columns: 1fr;
            }
            
            .music-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px !important;
            }
            
            /* Limit to 2 rows on mobile (4 items visible) */
            .music-grid > .music-card:nth-child(n+5) {
                display: none !important;
            }

            .section-title {
                font-size: 20px;
            }
            
            /* Artistes grid - 2 columns on mobile */
            .artistes-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px !important;
            }
            
            /* Main content grid - stack on mobile */
            div[style*="grid-template-columns: 2fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
            
            /* 3-column grids to 1 column on mobile */
            div[style*="grid-template-columns: repeat(3, 1fr)"] {
                grid-template-columns: 1fr !important;
            }
            
            /* 4-column grids to 2 columns on mobile */
            div[style*="grid-template-columns: repeat(4, 1fr)"] {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            /* Homepage Slider on mobile */
            .homepage-slider {
                height: 300px !important;
            }
            
            .homepage-slider-title {
                font-size: 24px !important;
            }
            
            .featured-section {
                grid-template-columns: 1fr !important;
            }
            
            .right-slider {
                margin-top: 20px;
            }
            
            /* Carousel height on mobile */
            #newsflash-carousel,
            #newsflash-carousel .carousel-slide {
                height: 250px !important;
                min-height: 250px !important;
            }
            
            /* Ensure carousel container maintains height on mobile */
            #carousel-container {
                height: 250px !important;
            }
            
            /* Ensure all sections are responsive */
            .container-custom {
                padding: 15px !important;
            }
            
            /* Fix button sizes on mobile */
            button, .btn {
                min-height: 44px !important;
                padding: 10px 15px !important;
                font-size: 14px !important;
            }
            
            /* Fix input sizes on mobile */
            input[type="text"],
            input[type="search"],
            textarea {
                min-height: 44px !important;
                font-size: 16px !important;
            }
            
            /* Fix image responsiveness */
            img {
                max-width: 100% !important;
                height: auto !important;
            }
            
            /* Fix table responsiveness */
            table {
                display: block !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }
            
            /* Make Politics, Business, Tech sections responsive */
            div[style*="grid-template-columns: repeat(3, 1fr)"] {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }
            
            /* Fix business tabs buttons on mobile */
            .business-tab,
            .tech-tab {
                font-size: 12px !important;
                padding: 6px 12px !important;
            }
            
            /* Make tab buttons wrap on mobile */
            div[style*="display: flex"] {
                flex-wrap: wrap !important;
            }
        }
    </style>
</head>
<body>
    <!-- Include Header -->
    <?php 
    // Load plugin system
    if (file_exists(__DIR__ . '/includes/plugin-loader.php')) {
        require_once __DIR__ . '/includes/plugin-loader.php';
    }
    if (file_exists(__DIR__ . '/includes/plugin-api.php')) {
        require_once __DIR__ . '/includes/plugin-api.php';
    }
    
    // Execute plugin initialization hook
    if (function_exists('do_action')) {
        do_action('init');
    }
    
    include 'includes/header.php'; 
    ?>
    
    <!-- News Ticker -->
    <?php if (!empty($ticker_news)): ?>
    <div class="news-ticker">
        <div class="ticker-label">
            <div class="ticker-label-icon">
                <?php for ($i = 1; $i <= 16; $i++): ?>
                <div></div>
                <?php endfor; ?>
            </div>
            <span>EXCLUSIVE</span>
        </div>
        <div class="ticker-content">
            <div class="ticker-scroll" id="ticker-scroll">
                <?php 
                // Duplicate items for seamless scroll
                $ticker_items = array_merge($ticker_news, $ticker_news);
                foreach ($ticker_items as $ticker_item): 
                    $ticker_slug = $ticker_item['slug'] ?? '';
                    $ticker_link = !empty($ticker_slug) ? base_url('news/' . rawurlencode($ticker_slug)) : base_url('news/' . $ticker_item['id']);
                ?>
                <div class="ticker-item" onclick="window.location.href='<?php echo $ticker_link; ?>'">
                    <span class="ticker-item-text"><?php echo htmlspecialchars($ticker_item['title']); ?></span>
                    <?php if (!empty($ticker_item['image'])): ?>
                    <img src="<?php echo htmlspecialchars($ticker_item['image']); ?>" alt="<?php echo htmlspecialchars($ticker_item['title']); ?>" class="ticker-item-thumb">
                    <?php else: ?>
                    <div class="ticker-item-thumb" style="background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 14px;">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php 
    // Display header ad if exists
    require_once 'includes/ads.php';
    $headerAd = displayAd('header');
    if ($headerAd) {
        echo '<div style="max-width: 1400px; margin: 10px auto; padding: 0 15px;">' . $headerAd . '</div>';
    }
    ?>

    <div class="container-custom">
        <?php
        // Display content ad if exists (after header, before main content)
        $contentAd = displayAd('content_top');
        if ($contentAd) {
            echo '<div style="margin: 20px 0; text-align: center;">' . $contentAd . '</div>';
        }
        ?>
        
        <!-- Homepage Slider Section with Right Sidebar -->
        <?php if (!empty($featured_carousel)): ?>
        <div class="featured-section" style="margin: 30px 0;">
            <!-- Left: Main Homepage Slider -->
            <div class="homepage-slider">
                <?php foreach ($featured_carousel as $index => $carousel_news): ?>
                <div class="homepage-slider-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>">
                    <a href="<?php echo !empty($carousel_news['slug']) ? base_url('news/' . rawurlencode($carousel_news['slug'])) : base_url('news/' . $carousel_news['id']); ?>" style="position: relative; width: 100%; height: 100%; cursor: pointer; display: block; text-decoration: none;">
                        <?php if (!empty($carousel_news['image'])): ?>
                        <img src="<?php echo htmlspecialchars($carousel_news['image']); ?>" alt="<?php echo htmlspecialchars($carousel_news['title']); ?>">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <?php endif; ?>
                        <div class="homepage-slider-overlay">
                            <div class="homepage-slider-category">
                                <?php echo htmlspecialchars(strtoupper($carousel_news['category'] ?? 'News')); ?>
                            </div>
                            <h2 class="homepage-slider-title">
                                <?php echo htmlspecialchars($carousel_news['title']); ?>
                            </h2>
                            <div class="homepage-slider-meta">
                                BY <?php echo strtoupper(htmlspecialchars($carousel_news['author'] ?? 'John Doe')); ?> • <?php echo strtoupper(date('F j, Y', strtotime($carousel_news['created_at'] ?? 'now'))); ?>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
                
                <!-- Navigation Button (Right Arrow) -->
                <button class="homepage-slider-nav" id="homepage-slider-next" onclick="nextHomepageSlide()">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <!-- Right: Sidebar Slider with Tabs -->
            <div class="right-slider">
                <div class="right-slider-tabs">
                    <button class="right-slider-tab active" onclick="switchRightTab('trending', this)">Trending</button>
                    <button class="right-slider-tab" onclick="switchRightTab('comments', this)">Comments</button>
                    <button class="right-slider-tab" onclick="switchRightTab('latest', this)">Latest</button>
                </div>
                
                <div class="right-slider-content">
                    <!-- Trending Tab -->
                    <div id="right-tab-trending" class="right-slider-tab-content active">
                        <?php 
                        // Get most viewed news of the week
                        $trending_articles = [];
                        try {
                            $trendingStmt = $conn->prepare("
                                SELECT n.*, COALESCE(u.username, 'Unknown') as author 
                                FROM news n 
                                LEFT JOIN users u ON n.author_id = u.id 
                                WHERE n.is_published = 1 
                                AND n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                ORDER BY n.views DESC, n.created_at DESC 
                                LIMIT 3
                            ");
                            $trendingStmt->execute();
                            $trending_articles = $trendingStmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            error_log("Trending query error: " . $e->getMessage());
                        }
                        if (empty($trending_articles)) {
                            $trending_articles = array_slice($featured_carousel, 0, 3);
                        }
                        foreach ($trending_articles as $article): 
                        ?>
                        <a href="<?php echo !empty($article['slug']) ? base_url('news/' . rawurlencode($article['slug'])) : base_url('news/' . $article['id']); ?>" class="right-slider-item">
                            <div class="right-slider-item-thumb">
                                <?php if (!empty($article['image'])): ?>
                                <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
                                <?php else: ?>
                                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($article['video_url'])): ?>
                                <div class="play-overlay">
                                    <i class="fas fa-play" style="color: white; font-size: 20px;"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="right-slider-item-info">
                                <h4 class="right-slider-item-title"><?php echo htmlspecialchars($article['title']); ?></h4>
                                <div class="right-slider-item-date"><?php echo strtoupper(date('F j, Y', strtotime($article['created_at'] ?? 'now'))); ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Comments Tab -->
                    <div id="right-tab-comments" class="right-slider-tab-content">
                        <?php 
                        // Get comments from both news and songs, showing the most recent commented items
                        $commented_articles = [];
                        try {
                            // Check if tables exist and get most recently commented items
                            $commentsQuery = "
                                SELECT 
                                    'news' as type,
                                    n.id,
                                    n.title,
                                    n.slug,
                                    n.image,
                                    n.created_at,
                                    n.category,
                                    COALESCE(u.username, 'Unknown') as author,
                                    nc.created_at as comment_date,
                                    nc.comment as comment_text,
                                    COALESCE(u2.username, 'User') as commenter
                                FROM news_comments nc
                                INNER JOIN news n ON nc.news_id = n.id
                                LEFT JOIN users u ON n.author_id = u.id
                                LEFT JOIN users u2 ON nc.user_id = u2.id
                                WHERE n.is_published = 1
                                
                                UNION ALL
                                
                                SELECT 
                                    'song' as type,
                                    s.id,
                                    s.title as title,
                                    s.slug as slug,
                                    s.cover_art as image,
                                    s.upload_date as created_at,
                                    'Music' as category,
                                    COALESCE(s.artist, 'Unknown') as author,
                                    sc.created_at as comment_date,
                                    sc.comment as comment_text,
                                    COALESCE(sc.username, 'User') as commenter
                                FROM song_comments sc
                                INNER JOIN songs s ON sc.song_id = s.id
                                WHERE sc.is_approved = 1
                                AND (s.status = 'active' OR s.status IS NULL OR s.status = '' OR s.status = 'approved')
                                
                                ORDER BY comment_date DESC
                                LIMIT 3
                            ";
                            $commentedStmt = $conn->query($commentsQuery);
                            $commented_articles = $commentedStmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            error_log("Comments query error: " . $e->getMessage());
                            // Fallback: try just news comments
                            try {
                                $commentedStmt = $conn->query("
                                    SELECT DISTINCT n.*, COALESCE(u.username, 'Unknown') as author 
                                    FROM news n 
                                    INNER JOIN news_comments nc ON n.id = nc.news_id
                                    LEFT JOIN users u ON n.author_id = u.id
                                    WHERE n.is_published = 1 
                                    ORDER BY nc.created_at DESC 
                                    LIMIT 3
                                ");
                                $commented_articles = $commentedStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e2) {
                                error_log("Fallback comments query error: " . $e2->getMessage());
                            }
                        }
                        if (empty($commented_articles)) {
                            $commented_articles = array_slice($featured_carousel, 0, 3);
                        }
                        foreach ($commented_articles as $article): 
                            // Determine link based on type (news or song)
                            $item_type = $article['type'] ?? 'news';
                            if ($item_type === 'song') {
                                // Generate slug if not provided
                                if (!empty($article['slug'])) {
                                    $item_link = '/song/' . rawurlencode($article['slug']);
                                } else {
                                    // Generate slug from title and artist
                                    $itemTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $article['title'] ?? ''));
                                    $itemTitleSlug = preg_replace('/\s+/', '-', trim($itemTitleSlug));
                                    $itemArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $article['artist'] ?? 'unknown-artist'));
                                    $itemArtistSlug = preg_replace('/\s+/', '-', trim($itemArtistSlug));
                                    $itemSlug = $itemTitleSlug . '-by-' . $itemArtistSlug;
                                    $item_link = '/song/' . rawurlencode($itemSlug);
                                }
                                $item_icon = 'fa-music';
                            } else {
                                $item_link = !empty($article['slug']) ? base_url('news/' . rawurlencode($article['slug'])) : base_url('news/' . $article['id']);
                                $item_icon = 'fa-newspaper';
                            }
                        ?>
                        <a href="<?php echo $item_link; ?>" class="right-slider-item">
                            <div class="right-slider-item-thumb">
                                <?php if (!empty($article['image'])): ?>
                                <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
                                <?php else: ?>
                                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                                    <i class="fas <?php echo $item_icon; ?>"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="right-slider-item-info">
                                <h4 class="right-slider-item-title"><?php echo htmlspecialchars($article['title']); ?></h4>
                                <div class="right-slider-item-date">
                                    <?php echo strtoupper($item_type === 'song' ? 'Music' : ($article['category'] ?? 'News')); ?> • 
                                    <?php echo strtoupper(date('F j, Y', strtotime($article['created_at'] ?? 'now'))); ?>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Latest Tab -->
                    <div id="right-tab-latest" class="right-slider-tab-content">
                        <?php 
                        // Get latest news articles
                        $latest_articles = [];
                        try {
                            $latestStmt = $conn->prepare("
                                SELECT n.*, COALESCE(u.username, 'Unknown') as author 
                                FROM news n 
                                LEFT JOIN users u ON n.author_id = u.id 
                                WHERE n.is_published = 1 
                                ORDER BY n.created_at DESC 
                                LIMIT 3
                            ");
                            $latestStmt->execute();
                            $latest_articles = $latestStmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            error_log("Latest query error: " . $e->getMessage());
                        }
                        if (empty($latest_articles)) {
                            $latest_articles = array_slice($featured_carousel, 0, 3);
                        }
                        foreach ($latest_articles as $article): 
                        ?>
                        <a href="<?php echo !empty($article['slug']) ? base_url('news/' . rawurlencode($article['slug'])) : base_url('news/' . $article['id']); ?>" class="right-slider-item">
                            <div class="right-slider-item-thumb">
                                <?php if (!empty($article['image'])): ?>
                                <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>">
                                <?php else: ?>
                                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="right-slider-item-info">
                                <h4 class="right-slider-item-title"><?php echo htmlspecialchars($article['title']); ?></h4>
                                <div class="right-slider-item-date"><?php echo strtoupper(date('F j, Y', strtotime($article['created_at'] ?? 'now'))); ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent News Section with Most Popular Sidebar -->
        <?php if (!empty($recent_news_section)): ?>
        <div class="recent-news-wrapper" style="margin: 40px 0; max-width: 1400px; margin-left: auto; margin-right: auto; padding: 0 15px;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start;">
                <!-- Left: Recent News -->
                <div>
                    <div style="background: #2196F3; color: white; padding: 12px 20px; text-align: center; font-weight: 700; font-size: 18px; margin-bottom: 20px; border-radius: 4px 4px 0 0;">
                        RECENT NEWS
                    </div>
                    <div style="background: white; padding: 20px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <?php foreach ($recent_news_section as $index => $news): ?>
                        <div class="recent-news-item" style="margin-bottom: <?php echo $index < count($recent_news_section) - 1 ? '30px' : '0'; ?>; padding-bottom: <?php echo $index < count($recent_news_section) - 1 ? '30px' : '0'; ?>; border-bottom: <?php echo $index < count($recent_news_section) - 1 ? '1px solid #e0e0e0' : 'none'; ?>;">
                            <a href="<?php echo !empty($news['slug']) ? base_url('news/' . rawurlencode($news['slug'])) : base_url('news/' . $news['id']); ?>" style="text-decoration: none; color: inherit; display: block;">
                                <div style="display: grid; grid-template-columns: 200px 1fr; gap: 20px;">
                                    <div style="height: 150px; overflow: hidden; border-radius: 8px;">
                                        <?php if (!empty($news['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 36px;">
                                            <i class="fas fa-newspaper"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-size: 12px; color: #e91e63; font-weight: 600; margin-bottom: 8px; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($news['category'] ?? 'News'); ?>
                                        </div>
                                        <h3 style="font-size: 18px; font-weight: 700; color: #2c3e50; margin: 0 0 10px; line-height: 1.4;">
                                            <?php echo htmlspecialchars($news['title']); ?>
                                        </h3>
                                        <p style="font-size: 14px; color: #666; line-height: 1.6; margin: 0;">
                                            <?php 
                                            $excerpt = strip_tags($news['content'] ?? $news['excerpt'] ?? '');
                                            $excerpt = strlen($excerpt) > 150 ? substr($excerpt, 0, 150) . '...' : $excerpt;
                                            echo htmlspecialchars($excerpt);
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Right: Most Popular Sidebar -->
                <div>
                    <h2 style="font-size: 20px; font-weight: 700; color: #2c3e50; margin-bottom: 15px;">Most Popular</h2>
                    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;">
                        <!-- Tabs -->
                        <div style="display: flex; border-bottom: 2px solid #e0e0e0;">
                            <button class="popular-tab-btn active" data-tab="today" style="flex: 1; padding: 12px; background: #e91e63; color: white; border: none; font-weight: 600; font-size: 12px; cursor: pointer; text-transform: uppercase;">
                                Today
                            </button>
                            <button class="popular-tab-btn" data-tab="week" style="flex: 1; padding: 12px; background: #f5f5f5; color: #666; border: none; font-weight: 600; font-size: 12px; cursor: pointer; text-transform: uppercase;">
                                This Week
                            </button>
                            <button class="popular-tab-btn" data-tab="month" style="flex: 1; padding: 12px; background: #f5f5f5; color: #666; border: none; font-weight: 600; font-size: 12px; cursor: pointer; text-transform: uppercase;">
                                This Month
                            </button>
                        </div>
                        
                        <!-- Tab Content -->
                        <div id="popular-tab-content" style="padding: 15px;">
                            <?php 
                            // Default to today
                            $current_popular = !empty($popular_today) ? $popular_today : (!empty($popular_week) ? $popular_week : $popular_month);
                            ?>
                            <div class="popular-content" data-content="today" style="display: block;">
                                <?php if (!empty($popular_today)): ?>
                                    <?php foreach ($popular_today as $index => $pop): 
                                        // Generate song slug
                                        $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $pop['title'] ?? ''));
                                        $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                                        $songArtistForSlug = $pop['artist'] ?? 'unknown-artist';
                                        $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $songArtistForSlug));
                                        $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                                        $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                                        $songUrl = base_url('song/' . $songSlug);
                                    ?>
                                    <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: <?php echo $index < count($popular_today) - 1 ? '1px solid #e0e0e0' : 'none'; ?>;">
                                        <a href="<?php echo $songUrl; ?>" style="text-decoration: none; color: inherit; display: block;">
                                            <div style="height: 120px; overflow: hidden; border-radius: 6px; margin-bottom: 10px;">
                                                <?php if (!empty($pop['cover_art'])): ?>
                                                <img src="<?php echo htmlspecialchars($pop['cover_art']); ?>" alt="<?php echo htmlspecialchars($pop['title'] ?? 'Song'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                                                    <i class="fas fa-music"></i>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <h3 style="font-size: 14px; font-weight: 700; color: #2c3e50; margin: 0 0 5px; line-height: 1.4;">
                                                <?php echo htmlspecialchars($pop['title'] ?? 'Unknown Title'); ?>
                                            </h3>
                                            <p style="font-size: 12px; color: #666; margin: 0 0 5px;">
                                                <?php echo htmlspecialchars($pop['artist'] ?? 'Unknown Artist'); ?>
                                            </p>
                                            <p style="font-size: 11px; color: #999; margin: 0;">
                                                <i class="fas fa-play"></i> <?php echo number_format($pop['plays'] ?? 0); ?> • 
                                                <i class="fas fa-download"></i> <?php echo number_format($pop['downloads'] ?? 0); ?>
                                            </p>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="color: #999; font-size: 14px; text-align: center; padding: 20px;">No popular songs today</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="popular-content" data-content="week" style="display: none;">
                                <?php if (!empty($popular_week)): ?>
                                    <?php foreach ($popular_week as $index => $pop): 
                                        // Generate song slug
                                        $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $pop['title'] ?? ''));
                                        $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                                        $songArtistForSlug = $pop['artist'] ?? 'unknown-artist';
                                        $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $songArtistForSlug));
                                        $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                                        $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                                        $songUrl = base_url('song/' . $songSlug);
                                    ?>
                                    <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: <?php echo $index < count($popular_week) - 1 ? '1px solid #e0e0e0' : 'none'; ?>;">
                                        <a href="<?php echo $songUrl; ?>" style="text-decoration: none; color: inherit; display: block;">
                                            <div style="height: 120px; overflow: hidden; border-radius: 6px; margin-bottom: 10px;">
                                                <?php if (!empty($pop['cover_art'])): ?>
                                                <img src="<?php echo htmlspecialchars($pop['cover_art']); ?>" alt="<?php echo htmlspecialchars($pop['title'] ?? 'Song'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                                                    <i class="fas fa-music"></i>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <h3 style="font-size: 14px; font-weight: 700; color: #2c3e50; margin: 0 0 5px; line-height: 1.4;">
                                                <?php echo htmlspecialchars($pop['title'] ?? 'Unknown Title'); ?>
                                            </h3>
                                            <p style="font-size: 12px; color: #666; margin: 0 0 5px;">
                                                <?php echo htmlspecialchars($pop['artist'] ?? 'Unknown Artist'); ?>
                                            </p>
                                            <p style="font-size: 11px; color: #999; margin: 0;">
                                                <i class="fas fa-play"></i> <?php echo number_format($pop['plays'] ?? 0); ?> • 
                                                <i class="fas fa-download"></i> <?php echo number_format($pop['downloads'] ?? 0); ?>
                                            </p>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="color: #999; font-size: 14px; text-align: center; padding: 20px;">No popular songs this week</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="popular-content" data-content="month" style="display: none;">
                                <?php if (!empty($popular_month)): ?>
                                    <?php foreach ($popular_month as $index => $pop): 
                                        // Generate song slug
                                        $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $pop['title'] ?? ''));
                                        $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                                        $songArtistForSlug = $pop['artist'] ?? 'unknown-artist';
                                        $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $songArtistForSlug));
                                        $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                                        $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                                        $songUrl = base_url('song/' . $songSlug);
                                    ?>
                                    <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: <?php echo $index < count($popular_month) - 1 ? '1px solid #e0e0e0' : 'none'; ?>;">
                                        <a href="<?php echo $songUrl; ?>" style="text-decoration: none; color: inherit; display: block;">
                                            <div style="height: 120px; overflow: hidden; border-radius: 6px; margin-bottom: 10px;">
                                                <?php if (!empty($pop['cover_art'])): ?>
                                                <img src="<?php echo htmlspecialchars($pop['cover_art']); ?>" alt="<?php echo htmlspecialchars($pop['title'] ?? 'Song'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                                                    <i class="fas fa-music"></i>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <h3 style="font-size: 14px; font-weight: 700; color: #2c3e50; margin: 0 0 5px; line-height: 1.4;">
                                                <?php echo htmlspecialchars($pop['title'] ?? 'Unknown Title'); ?>
                                            </h3>
                                            <p style="font-size: 12px; color: #666; margin: 0 0 5px;">
                                                <?php echo htmlspecialchars($pop['artist'] ?? 'Unknown Artist'); ?>
                                            </p>
                                            <p style="font-size: 11px; color: #999; margin: 0;">
                                                <i class="fas fa-play"></i> <?php echo number_format($pop['plays'] ?? 0); ?> • 
                                                <i class="fas fa-download"></i> <?php echo number_format($pop['downloads'] ?? 0); ?>
                                            </p>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="color: #999; font-size: 14px; text-align: center; padding: 20px;">No popular songs this month</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            @media (max-width: 968px) {
                .recent-news-wrapper > div {
                    grid-template-columns: 1fr !important;
                    gap: 20px !important;
                }
                .recent-news-item > a > div {
                    grid-template-columns: 1fr !important;
                }
                .recent-news-item > a > div > div:first-child {
                    height: 200px !important;
                    width: 100% !important;
                }
            }
        </style>
        
        <script>
        // Popular tabs functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.popular-tab-btn');
            const tabContents = document.querySelectorAll('.popular-content');
            
            tabButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const tab = this.getAttribute('data-tab');
                    
                    // Update button styles
                    tabButtons.forEach(b => {
                        b.style.background = '#f5f5f5';
                        b.style.color = '#666';
                    });
                    this.style.background = '#e91e63';
                    this.style.color = 'white';
                    
                    // Update content
                    tabContents.forEach(content => {
                        content.style.display = 'none';
                    });
                    const activeContent = document.querySelector(`.popular-content[data-content="${tab}"]`);
                    if (activeContent) {
                        activeContent.style.display = 'block';
                    }
                });
            });
        });
        </script>
        <?php endif; ?>

        <!-- ============================================
            HOME PAGE LAYOUT - REORGANIZED SECTIONS
            =============================================
            1. Songs recently added
            2. Recent News with Most Popular sidebar
            3. Political news
            4. Community news
            5. Business news
            6. Trending music/Songs
            7. Featured stories
            8. Bottom advert banner
            9. Artists
            10. Contact info or footer page
        ============================================== -->

        <!-- 1. Recently Uploaded Songs -->
        <div style="margin: 40px 0;">
        <?php if (!empty($recent_songs)): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin: 0; padding-bottom: 10px; border-bottom: 3px solid #2196F3;">Recently Uploaded Songs</h2>
                <a href="songs.php" style="background: #2196F3; color: white; padding: 8px 20px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.3s;" onmouseover="this.style.background='#1976D2';" onmouseout="this.style.background='#2196F3';">
                    View All
                </a>
            </div>
            <div class="songs-recently-added-grid music-grid">
                <?php foreach ($recent_songs as $song): 
                    // Generate song slug
                    $titleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title'] ?? ''));
                    $titleSlug = preg_replace('/\s+/', '-', trim($titleSlug));
                    $artistForSlug = $song['artist'] ?? 'unknown-artist';
                    if (!empty($song['uploaded_by'])) {
                        try {
                            $slugUploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                            $slugUploaderStmt->execute([$song['uploaded_by']]);
                            $slugUploader = $slugUploaderStmt->fetch(PDO::FETCH_ASSOC);
                            if ($slugUploader && !empty($slugUploader['username'])) {
                                $artistForSlug = $slugUploader['username'];
                            }
                        } catch (Exception $e) {
                            // Keep default
                        }
                    }
                    $artistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $artistForSlug));
                    $artistSlug = preg_replace('/\s+/', '-', trim($artistSlug));
                    $songSlug = $titleSlug . '-by-' . $artistSlug;
                    $songUrl = base_url('song/' . $songSlug);
                ?>
                <a href="<?php echo $songUrl; ?>" style="text-decoration: none; color: inherit; display: block;">
                <div class="music-card">
                    <div class="music-cover">
                        <?php if (!empty($song['cover_art'])): ?>
                        <img src="<?php echo htmlspecialchars($song['cover_art']); ?>" alt="<?php echo htmlspecialchars($song['title'] ?? 'Song'); ?>">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                            <i class="fas fa-music"></i>
                        </div>
                        <?php endif; ?>
                        <div class="music-play-overlay">
                            <button class="play-button" type="button" onclick="event.preventDefault(); event.stopPropagation(); playSong(<?php echo $song['id']; ?>);">
                                <i class="fas fa-play"></i>
                            </button>
                        </div>
                    </div>
                    <div class="music-info">
                        <div class="music-title"><?php echo htmlspecialchars($song['title'] ?? 'Unknown Title'); ?></div>
                        <div class="music-artist"><?php echo htmlspecialchars($song['artist'] ?? 'Unknown Artist'); ?></div>
                        <div class="music-stats">
                            <span><i class="fas fa-play" style="margin-right: 5px;"></i><?php echo number_format($song['plays'] ?? 0); ?></span>
                            <span><i class="fas fa-download" style="margin-right: 5px;"></i><?php echo number_format($song['downloads'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin: 0; padding-bottom: 10px; border-bottom: 3px solid #2196F3;">Recently Uploaded Songs</h2>
                <a href="songs.php" style="background: #2196F3; color: white; padding: 8px 20px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.3s;" onmouseover="this.style.background='#1976D2';" onmouseout="this.style.background='#2196F3';">
                    View All
                </a>
            </div>
            <div style="text-align: center; padding: 40px; background: white; border-radius: 8px; color: #666;">
                <i class="fas fa-music" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                <p style="font-size: 16px; margin: 0;">No recently uploaded songs yet.</p>
            </div>
        <?php endif; ?>
        </div>

        <!-- 2. Political News -->
        <?php 
        error_log("DEBUG index.php: politics_news count - " . count($politics_news ?? []));
        if (!empty($politics_news)): ?>
        <div style="margin: 40px 0;">
            <?php 
            // Display ad below politics section if exists
            $politicsAd = displayAd('below_politics');
            if ($politicsAd) {
                echo '<div style="margin: 20px 0; text-align: center;">' . $politicsAd . '</div>';
            }
            ?>
            <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #2196F3;">Political News</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <?php foreach (array_slice($politics_news, 0, 6) as $news): ?>
                <a href="<?php echo !empty($news['slug']) ? base_url('news/' . rawurlencode($news['slug'])) : base_url('news/' . $news['id']); ?>" style="text-decoration: none; color: inherit; display: block;">
                <div style="background: white; border-radius: 8px; overflow: hidden; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)';">
                    <div style="height: 180px; overflow: hidden;">
                        <?php if (!empty($news['image'])): ?>
                        <img src="<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 36px;">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 15px;">
                        <div style="font-size: 11px; color: #7f8c8d; margin-bottom: 8px; font-weight: 600; text-transform: uppercase;">Politics</div>
                        <h3 style="font-size: 16px; font-weight: 600; color: #2c3e50; margin: 0; line-height: 1.4;">
                            <?php echo htmlspecialchars($news['title']); ?>
                        </h3>
                        <div style="font-size: 12px; color: #7f8c8d; margin-top: 8px;">
                            by <?php echo htmlspecialchars($news['author'] ?? 'John Doe'); ?> • <?php echo date('M j, Y', strtotime($news['created_at'] ?? 'now')); ?>
                        </div>
                    </div>
                </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 3. Community News -->
        <?php if (!empty($community_news)): ?>
        <div style="margin: 40px 0;">
            <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #2196F3;">Community News</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <?php foreach (array_slice($community_news, 0, 6) as $news): ?>
                <a href="<?php echo !empty($news['slug']) ? base_url('news/' . rawurlencode($news['slug'])) : base_url('news/' . $news['id']); ?>" style="text-decoration: none; color: inherit; display: block;">
                <div style="background: white; border-radius: 8px; overflow: hidden; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)';">
                    <div style="height: 180px; overflow: hidden;">
                        <?php if (!empty($news['image'])): ?>
                        <img src="<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 36px;">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 15px;">
                        <div style="font-size: 11px; color: #7f8c8d; margin-bottom: 8px; font-weight: 600; text-transform: uppercase;">Community</div>
                        <h3 style="font-size: 16px; font-weight: 600; color: #2c3e50; margin: 0; line-height: 1.4;">
                            <?php echo htmlspecialchars($news['title']); ?>
                        </h3>
                        <div style="font-size: 12px; color: #7f8c8d; margin-top: 8px;">
                            by <?php echo htmlspecialchars($news['author'] ?? 'John Doe'); ?> • <?php echo date('M j, Y', strtotime($news['created_at'] ?? 'now')); ?>
                        </div>
                    </div>
                </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 4. Entertainment News -->
        <?php if (!empty($entertainment_news)): ?>
        <div style="margin: 40px 0;">
            <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #2196F3;">Entertainment</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <?php foreach (array_slice($entertainment_news, 0, 6) as $news): ?>
                <a href="<?php echo !empty($news['slug']) ? base_url('news/' . rawurlencode($news['slug'])) : base_url('news/' . $news['id']); ?>" style="text-decoration: none; color: inherit; display: block;">
                <div style="background: white; border-radius: 8px; overflow: hidden; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)';">
                    <div style="height: 180px; overflow: hidden;">
                        <?php if (!empty($news['image'])): ?>
                        <img src="<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 36px;">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 15px;">
                        <div style="font-size: 11px; color: #7f8c8d; margin-bottom: 8px; font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($news['category'] ?? 'Entertainment'); ?></div>
                        <h3 style="font-size: 16px; font-weight: 600; color: #2c3e50; margin: 0; line-height: 1.4;">
                            <?php echo htmlspecialchars($news['title']); ?>
                        </h3>
                        <div style="font-size: 12px; color: #7f8c8d; margin-top: 8px;">
                            by <?php echo htmlspecialchars($news['author'] ?? 'John Doe'); ?> • <?php echo date('M j, Y', strtotime($news['created_at'] ?? 'now')); ?>
                        </div>
                    </div>
                </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Music Chart and Songs Newly Added Section -->
        <?php if (!empty($top_chart) || !empty($new_songs)): ?>
        <div style="margin: 40px 0; display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <!-- Music Chart -->
            <?php if (!empty($top_chart)): ?>
            <div class="chart-container" style="background: white; border-radius: 8px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin: 0;">Music Chart</h2>
                    <a href="top-100.php" style="background: #2196F3; color: white; padding: 8px 20px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.3s;" onmouseover="this.style.background='#1976D2';" onmouseout="this.style.background='#2196F3';">
                        Full Chart
                    </a>
                </div>
                
                <?php foreach ($top_chart as $index => $chartSong): 
                    // Get display artist with collaborators
                    $chartDisplayArtist = $chartSong['artist'] ?? 'Unknown Artist';
                    if (!empty($chartSong['is_collaboration']) || !empty($chartSong['id'])) {
                        try {
                            $chart_all_artist_names = [];
                            
                            // Get uploader
                            if (!empty($chartSong['uploaded_by'])) {
                                $chartUploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                                $chartUploaderStmt->execute([$chartSong['uploaded_by']]);
                                $chartUploader = $chartUploaderStmt->fetch(PDO::FETCH_ASSOC);
                                if ($chartUploader && !empty($chartUploader['username'])) {
                                    $chart_all_artist_names[] = $chartUploader['username'];
                                }
                            }
                            
                            // Get collaborators
                            $chartCollabStmt = $conn->prepare("
                                SELECT DISTINCT sc.user_id, COALESCE(u.username, sc.user_id) as artist_name
                                FROM song_collaborators sc
                                LEFT JOIN users u ON sc.user_id = u.id
                                WHERE sc.song_id = ?
                                ORDER BY sc.added_at ASC
                            ");
                            $chartCollabStmt->execute([$chartSong['id']]);
                            $chartCollaborators = $chartCollabStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($chartCollaborators)) {
                                foreach ($chartCollaborators as $c) {
                                    $collab_name = $c['artist_name'] ?? 'Unknown';
                                    if (!in_array($collab_name, $chart_all_artist_names)) {
                                        $chart_all_artist_names[] = $collab_name;
                                    }
                                }
                            }
                            
                            // Build display artist string
                            if (count($chart_all_artist_names) > 1) {
                                $chartDisplayArtist = $chart_all_artist_names[0] . ' ft ' . implode(', ', array_slice($chart_all_artist_names, 1));
                            } elseif (count($chart_all_artist_names) == 1) {
                                $chartDisplayArtist = $chart_all_artist_names[0];
                            }
                        } catch (Exception $e) {
                            // Keep default
                            error_log("Chart artist error: " . $e->getMessage());
                        }
                    }
                    
                    // Generate song slug (use uploader username for slug)
                    $chartTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $chartSong['title']));
                    $chartTitleSlug = preg_replace('/\s+/', '-', trim($chartTitleSlug));
                    $chartArtistForSlug = $chartSong['artist'] ?? 'unknown-artist';
                    if (!empty($chartSong['uploaded_by'])) {
                        try {
                            $slugUploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                            $slugUploaderStmt->execute([$chartSong['uploaded_by']]);
                            $slugUploader = $slugUploaderStmt->fetch(PDO::FETCH_ASSOC);
                            if ($slugUploader && !empty($slugUploader['username'])) {
                                $chartArtistForSlug = $slugUploader['username'];
                            }
                        } catch (Exception $e) {
                            // Keep default
                        }
                    }
                    $chartArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $chartArtistForSlug));
                    $chartArtistSlug = preg_replace('/\s+/', '-', trim($chartArtistSlug));
                    $chartSongSlug = $chartTitleSlug . '-by-' . $chartArtistSlug;
                    
                    // Determine trend
                    $isNew = ($index >= 3);
                    $trendUp = ($index == 0 || $index == 2);
                    $trendDown = ($index == 1);
                ?>
                <div class="chart-item" style="display: flex; align-items: center; gap: 15px; padding: 15px 15px 15px 0; border-bottom: 1px solid #f0f0f0; transition: background 0.2s;" onmouseover="this.style.background='#f8f9fa';" onmouseout="this.style.background='white';">
                    <!-- Rank Number & Trend -->
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 5px; min-width: 50px; padding-left: 5px;">
                        <div style="font-size: 36px; font-weight: 700; color: #333;"><?php echo $index + 1; ?></div>
                        <?php if ($isNew): ?>
                        <span style="background: #E91E63; color: white; padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; text-transform: uppercase;">NEW</span>
                        <?php elseif ($trendUp): ?>
                        <div style="width: 28px; height: 28px; background: #4CAF50; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-arrow-up" style="color: white; font-size: 12px;"></i>
                        </div>
                        <?php elseif ($trendDown): ?>
                        <div style="width: 28px; height: 28px; background: #E91E63; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-arrow-down" style="color: white; font-size: 12px;"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Album Art -->
                    <a href="/song/<?php echo urlencode($chartSongSlug); ?>" style="text-decoration: none; flex-shrink: 0;">
                        <?php if (!empty($chartSong['cover_art'])): ?>
                        <img src="<?php echo htmlspecialchars($chartSong['cover_art']); ?>" alt="<?php echo htmlspecialchars($chartSong['title']); ?>" class="chart-cover" style="width: 60px; height: 60px; border-radius: 6px; object-fit: cover; background: #f0f0f0;">
                        <?php else: ?>
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                            <i class="fas fa-music"></i>
                        </div>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Song Info -->
                    <div class="chart-info" style="flex: 1;">
                        <a href="/song/<?php echo urlencode($chartSongSlug); ?>" style="text-decoration: none; color: inherit;">
                            <div class="chart-title" style="font-size: 16px; font-weight: 600; color: #2c3e50; margin-bottom: 5px;">
                                <?php echo htmlspecialchars($chartSong['title']); ?>
                            </div>
                            <div class="chart-artist" style="font-size: 14px; color: #7f8c8d;">
                                <?php echo htmlspecialchars($chartDisplayArtist); ?>
                            </div>
                        </a>
                    </div>
                    
                    <!-- Play Button -->
                    <button onclick="event.stopPropagation(); playSong('<?php echo $chartSong['id']; ?>');" class="chart-play-btn" style="background: #2196F3; border: none; color: white; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; flex-shrink: 0;" onmouseover="this.style.background='#1976D2'; this.style.transform='scale(1.1)';" onmouseout="this.style.background='#2196F3'; this.style.transform='scale(1)';">
                        <i class="fas fa-play" style="font-size: 14px; margin-left: 2px;"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Songs Newly Added Sidebar -->
            <?php if (!empty($new_songs)): ?>
            <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <div style="background: #9C27B0; color: white; padding: 15px 20px; font-weight: 700; font-size: 16px; text-transform: uppercase;">
                    Featured Music
                </div>
                <div style="padding: 0;">
                    <?php foreach (array_slice($new_songs, 0, 6) as $newSong): 
                        // Generate song slug
                        $newTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $newSong['title']));
                        $newTitleSlug = preg_replace('/\s+/', '-', trim($newTitleSlug));
                        $newArtistForSlug = $newSong['artist'] ?? 'unknown-artist';
                        $newArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $newArtistForSlug));
                        $newArtistSlug = preg_replace('/\s+/', '-', trim($newArtistSlug));
                        $newSongSlug = $newTitleSlug . '-by-' . $newArtistSlug;
                    ?>
                    <a href="/song/<?php echo urlencode($newSongSlug); ?>" style="display: flex; gap: 15px; padding: 15px; border-bottom: 1px solid #f0f0f0; text-decoration: none; color: inherit; transition: background 0.2s;" onmouseover="this.style.background='#f8f9fa'; this.style.paddingLeft='20px'; this.style.paddingRight='20px'; this.style.marginLeft='-20px'; this.style.marginRight='-20px';" onmouseout="this.style.background='transparent'; this.style.paddingLeft='15px'; this.style.paddingRight='15px'; this.style.marginLeft='0'; this.style.marginRight='0';">
                        <div style="width: 100px; height: 70px; flex-shrink: 0; border-radius: 4px; overflow: hidden; position: relative;">
                            <?php if (!empty($newSong['cover_art'])): ?>
                            <img src="<?php echo htmlspecialchars($newSong['cover_art']); ?>" alt="<?php echo htmlspecialchars($newSong['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                                <i class="fas fa-music"></i>
                            </div>
                            <?php endif; ?>
                            <div class="play-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s;">
                                <i class="fas fa-play" style="color: white; font-size: 20px;"></i>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 14px; font-weight: 600; color: #333; margin-bottom: 8px; line-height: 1.4;">
                                <?php echo htmlspecialchars($newSong['title']); ?>
                            </div>
                            <div style="font-size: 12px; color: #E91E63; margin-bottom: 5px;">
                                <?php 
                                // Display collaboration artists if applicable
                                $newDisplayArtist = $newSong['artist'] ?? 'Unknown Artist';
                                if (!empty($newSong['is_collaboration']) || !empty($newSong['id'])) {
                                    try {
                                        $new_all_artist_names = [];
                                        
                                        // Get uploader
                                        if (!empty($newSong['uploaded_by'])) {
                                            $newUploaderStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                                            $newUploaderStmt->execute([$newSong['uploaded_by']]);
                                            $newUploader = $newUploaderStmt->fetch(PDO::FETCH_ASSOC);
                                            if ($newUploader && !empty($newUploader['username'])) {
                                                $new_all_artist_names[] = htmlspecialchars($newUploader['username']);
                                            }
                                        }
                                        
                                        // Get collaborators
                                        $newCollabStmt = $conn->prepare("
                                            SELECT DISTINCT sc.user_id, COALESCE(u.username, sc.user_id) as artist_name
                                            FROM song_collaborators sc
                                            LEFT JOIN users u ON sc.user_id = u.id
                                            WHERE sc.song_id = ?
                                            ORDER BY sc.added_at ASC
                                        ");
                                        $newCollabStmt->execute([$newSong['id']]);
                                        $newCollaborators = $newCollabStmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (!empty($newCollaborators)) {
                                            foreach ($newCollaborators as $c) {
                                                $collab_name = $c['artist_name'] ?? 'Unknown';
                                                if (!in_array($collab_name, $new_all_artist_names)) {
                                                    $new_all_artist_names[] = $collab_name;
                                                }
                                            }
                                        }
                                        
                                        // Build display artist string
                                        if (count($new_all_artist_names) > 1) {
                                            $newDisplayArtist = $new_all_artist_names[0] . ' ft ' . implode(', ', array_slice($new_all_artist_names, 1));
                                        } elseif (count($new_all_artist_names) == 1) {
                                            $newDisplayArtist = $new_all_artist_names[0];
                                        }
                                    } catch (Exception $e) {
                                        error_log("New song artist error: " . $e->getMessage());
                                    }
                                }
                                echo htmlspecialchars($newDisplayArtist);
                                ?>
                            </div>
                            <div style="font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px;">
                                <?php echo number_format($newSong['plays'] ?? 0); ?> plays | <?php echo number_format($newSong['downloads'] ?? 0); ?> Downloads
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        

        <!-- 6. Featured Stories -->
        <?php if (!empty($featured_stories)): ?>
        <div style="margin: 40px 0;">
            <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #2196F3;">Featured Stories</h2>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <?php foreach (array_slice($featured_stories, 0, 6) as $news): ?>
                <a href="<?php echo !empty($news['slug']) ? base_url('news/' . rawurlencode($news['slug'])) : base_url('news/' . $news['id']); ?>" style="text-decoration: none; color: inherit; display: block;">
                <div style="background: white; border-radius: 8px; overflow: hidden; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)';">
                    <div style="height: 180px; overflow: hidden;">
                        <?php if (!empty($news['image'])): ?>
                        <img src="<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 36px;">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 15px;">
                        <div style="font-size: 11px; color: #7f8c8d; margin-bottom: 8px; font-weight: 600; text-transform: uppercase;">Featured</div>
                        <h3 style="font-size: 16px; font-weight: 600; color: #2c3e50; margin: 0; line-height: 1.4;">
                            <?php echo htmlspecialchars($news['title']); ?>
                        </h3>
                        <div style="font-size: 12px; color: #7f8c8d; margin-top: 8px;">
                            by <?php echo htmlspecialchars($news['author'] ?? 'John Doe'); ?> • <?php echo date('M j, Y', strtotime($news['created_at'] ?? 'now')); ?>
                        </div>
                    </div>
                </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 5. Trending Songs -->
        <?php 
        error_log("DEBUG index.php: trending_songs count - " . count($trending_songs ?? []));
        if (!empty($trending_songs)): ?>
        <div style="margin: 40px 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin: 0; padding-bottom: 10px; border-bottom: 3px solid #2196F3;">Trending Songs</h2>
                <a href="top-100.php" style="color: #2196F3; text-decoration: none; font-weight: 600; font-size: 14px;">View Chart ››</a>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                <?php foreach (array_slice($trending_songs, 0, 8) as $index => $song): ?>
                <?php
                // Generate slug if not provided
                if (!empty($song['slug'])) {
                    $songUrl = '/song/' . rawurlencode($song['slug']);
                } else {
                    $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
                    $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                    $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['artist'] ?? 'unknown-artist'));
                    $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                    $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                    $songUrl = '/song/' . rawurlencode($songSlug);
                }
                ?>
                <a href="<?php echo $songUrl; ?>" style="text-decoration: none; color: inherit; display: block;">
                <div style="background: white; border-radius: 8px; overflow: hidden; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05); position: relative;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)';">
                    <?php if ($index < 3): ?>
                    <div style="position: absolute; top: 10px; right: 10px; background: <?php echo $index == 0 ? '#FFD700' : ($index == 1 ? '#C0C0C0' : '#CD7F32'); ?>; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; z-index: 2; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                        <?php echo $index + 1; ?>
                    </div>
                    <?php endif; ?>
                    <div style="height: 200px; overflow: hidden; background: linear-gradient(135deg, #667eea, #764ba2);">
                        <?php if (!empty($song['cover_art'])): ?>
                        <img src="<?php echo htmlspecialchars($song['cover_art']); ?>" alt="<?php echo htmlspecialchars($song['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                            <i class="fas fa-music"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 15px;">
                        <h3 style="font-size: 16px; font-weight: 600; color: #2c3e50; margin: 0 0 5px; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo htmlspecialchars($song['title']); ?>
                        </h3>
                        <div style="font-size: 13px; color: #7f8c8d; margin-bottom: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo htmlspecialchars($song['artist'] ?? 'Unknown Artist'); ?>
                        </div>
                        <div style="font-size: 11px; color: #95a5a6; display: flex; gap: 15px;">
                            <span><i class="fas fa-play"></i> <?php echo number_format($song['plays'] ?? 0); ?></span>
                            <span><i class="fas fa-download"></i> <?php echo number_format($song['downloads'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 7. Bottom Advert Banner -->
        <?php 
        $bottomAd = displayAd('bottom_banner');
        if ($bottomAd): ?>
        <div style="margin: 40px 0; text-align: center;">
            <?php echo $bottomAd; ?>
        </div>
        <?php endif; ?>

        <!-- 8. Artists Section -->
        <?php if (!empty($featured_artists)): ?>
        <div style="margin: 40px 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h2 style="font-size: 28px; font-weight: 700; color: #2c3e50; margin: 0;">Artists</h2>
                <a href="artists.php" style="color: #2196F3; text-decoration: none; font-weight: 600; font-size: 14px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8';" onmouseout="this.style.opacity='1';">
                    View All ››
                </a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 20px;" class="artistes-grid">
                <?php 
                $artistRank = 0;
                foreach ($featured_artists as $artist): 
                    $artistRank++;
                    // Get artist profile link
                    $artistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $artist['name'] ?? $artist['username'] ?? ''));
                    $artistSlug = preg_replace('/\s+/', '-', trim($artistSlug));
                    
                    // Get stats
                    $totalSongs = $artist['songs_count'] ?? 0;
                    $totalPlays = $artist['total_plays'] ?? 0;
                    $totalDownloads = $artist['total_downloads'] ?? 0;
                    
                    // Format numbers
                    $songsFormatted = number_format($totalSongs);
                    $playsFormatted = number_format($totalPlays);
                    if ($totalPlays >= 1000000) {
                        $playsFormatted = number_format($totalPlays / 1000000, 1) . 'M';
                    } elseif ($totalPlays >= 1000) {
                        $playsFormatted = number_format($totalPlays / 1000, 1) . 'K';
                    }
                ?>
                <div style="background: white; border-radius: 12px; overflow: hidden; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: relative;" onclick="window.location.href='/artist/<?php echo urlencode($artistSlug); ?>'" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.15)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';">
                    <!-- Rank Badge -->
                    <?php if ($artistRank <= 3): ?>
                    <div style="position: absolute; top: 12px; right: 12px; background: <?php echo $artistRank == 1 ? '#FFD700' : ($artistRank == 2 ? '#C0C0C0' : '#CD7F32'); ?>; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; z-index: 2; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                        <?php echo $artistRank; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Avatar -->
                    <div style="position: relative; width: 100%; padding-top: 100%; background: linear-gradient(135deg, #667eea, #764ba2); overflow: hidden;">
                        <?php if (!empty($artist['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($artist['avatar']); ?>" alt="<?php echo htmlspecialchars($artist['name'] ?? $artist['username'] ?? 'Artist'); ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info -->
                    <div style="padding: 15px;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #2c3e50; margin: 0 0 8px 0; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($artist['name'] ?? $artist['username'] ?? 'Artist'); ?>
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: #7f8c8d; text-align: center;">
                            <div><strong style="color: #2196F3;"><?php echo $songsFormatted; ?></strong> Songs</div>
                            <div><strong style="color: #E91E63;"><?php echo $playsFormatted; ?></strong> Plays</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <!-- 9. Contact Info / Footer (included via includes/footer.php) -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/luo-player.js"></script>
    <script>
        // Initialize player
        const player = new LuoPlayer();

        // Play song function
        function playSong(songId) {
            fetch(`api/song-data.php?id=${songId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        player.loadSong(data.song);
                        player.play();
                    }
                })
                .catch(error => console.error('Error loading song:', error));
        }
        
        // Most Popular Tabs functionality
        function showPopularTab(period, buttonElement) {
            // Hide all tab contents
            document.querySelectorAll('.popular-tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.popular-tab').forEach(tab => {
                tab.style.color = '#666';
                tab.style.borderBottom = 'none';
            });
            
            // Show selected tab content
            const contentId = 'popular-' + period;
            const content = document.getElementById(contentId);
            if (content) {
                content.style.display = 'block';
            }
            
            // Add active class to clicked tab
            if (buttonElement) {
                buttonElement.style.color = '#2196F3';
                buttonElement.style.borderBottom = '3px solid #2196F3';
            }
        }
        
        // Business Tabs functionality
        function showBusinessTab(category, buttonElement) {
            // Hide all business tab contents
            document.querySelectorAll('.business-tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all business tabs
            document.querySelectorAll('.business-tab').forEach(tab => {
                tab.style.background = '#f0f0f0';
                tab.style.color = '#666';
            });
            
            // Show selected tab content (for now, all shows same content)
            const contentId = 'business-' + category;
            const content = document.getElementById(contentId);
            if (content) {
                content.style.display = 'block';
            } else {
                // Fallback: show 'all' content
                const allContent = document.getElementById('business-all');
                if (allContent) allContent.style.display = 'block';
            }
            
            // Add active class to clicked tab
            if (buttonElement) {
                buttonElement.style.background = '#2196F3';
                buttonElement.style.color = 'white';
            }
        }
        
        // Tech Tabs functionality
        function showTechTab(category, buttonElement) {
            // Hide all tech tab contents
            document.querySelectorAll('.tech-tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tech tabs
            document.querySelectorAll('.tech-tab').forEach(tab => {
                tab.style.background = '#f0f0f0';
                tab.style.color = '#666';
            });
            
            // Show selected tab content (for now, all shows same content)
            const contentId = 'tech-' + category;
            const content = document.getElementById(contentId);
            if (content) {
                content.style.display = 'block';
            } else {
                // Fallback: show 'all' content
                const allContent = document.getElementById('tech-all');
                if (allContent) allContent.style.display = 'block';
            }
            
            // Add active class to clicked tab
            if (buttonElement) {
                buttonElement.style.background = '#2196F3';
                buttonElement.style.color = 'white';
            }
        }
        
        // Carousel functionality
        let currentSlide = 0;
        let carouselInterval = null;
        
        function initCarousel() {
            const slides = document.querySelectorAll('.carousel-slide');
            const indicators = document.querySelectorAll('.carousel-indicator');
            const totalSlides = slides.length;
            
            if (totalSlides === 0) return;
            
            function showSlide(index) {
                if (index < 0) index = totalSlides - 1;
                if (index >= totalSlides) index = 0;
                
                slides.forEach((slide, i) => {
                    slide.style.opacity = i === index ? '1' : '0';
                    slide.style.zIndex = i === index ? '2' : '1';
                });
                
                indicators.forEach((indicator, i) => {
                    indicator.style.background = i === index ? 'rgba(255,255,255,0.9)' : 'rgba(255,255,255,0.4)';
                });
                
                currentSlide = index;
            }
            
            function nextSlide() {
                showSlide(currentSlide + 1);
            }
            
            function prevSlide() {
                showSlide(currentSlide - 1);
            }
            
            // Event listeners
            const prevBtn = document.getElementById('carousel-prev');
            const nextBtn = document.getElementById('carousel-next');
            
            if (prevBtn) prevBtn.addEventListener('click', prevSlide);
            if (nextBtn) nextBtn.addEventListener('click', nextSlide);
            
            indicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => {
                    clearInterval(carouselInterval);
                    showSlide(index);
                    startCarousel();
                });
            });
            
            // Auto-play carousel
            function startCarousel() {
                clearInterval(carouselInterval);
                carouselInterval = setInterval(nextSlide, 5000); // Auto-advance every 5 seconds
            }
            
            // Start autoplay immediately
            startCarousel();
            
            // Pause on hover, resume on leave
            const carouselContainer = document.getElementById('newsflash-carousel');
            if (carouselContainer) {
                carouselContainer.addEventListener('mouseenter', () => {
                    clearInterval(carouselInterval);
                });
                carouselContainer.addEventListener('mouseleave', () => {
                    startCarousel();
                });
            }
        }
        
        // Initialize carousel when DOM is ready - use multiple methods to ensure it works
        function initializeCarousel() {
            const carousel = document.getElementById('newsflash-carousel');
            if (carousel) {
                // Clear any existing interval
                if (carouselInterval) {
                    clearInterval(carouselInterval);
                    carouselInterval = null;
                }
                initCarousel();
                return true;
            }
            return false;
        }
        
        // Try multiple initialization methods
        function tryInitCarousel() {
            if (!initializeCarousel()) {
                // Retry after a short delay if carousel not found
                setTimeout(tryInitCarousel, 200);
            }
        }
        
        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', tryInitCarousel);
            
            // Homepage Slider Navigation
            let currentHomepageSlide = 0;
            let homepageSlides = [];
            let homepageSliderInterval = null;
            
            function initHomepageSlider() {
                homepageSlides = document.querySelectorAll('.homepage-slider-slide');
                if (homepageSlides.length === 0) return;
                
                // Auto-play slider
                function startHomepageSlider() {
                    clearInterval(homepageSliderInterval);
                    homepageSliderInterval = setInterval(() => {
                        nextHomepageSlide();
                    }, 5000); // Change slide every 5 seconds
                }
                
                startHomepageSlider();
                
                // Pause on hover
                const slider = document.querySelector('.homepage-slider');
                if (slider) {
                    slider.addEventListener('mouseenter', () => {
                        clearInterval(homepageSliderInterval);
                    });
                    slider.addEventListener('mouseleave', () => {
                        startHomepageSlider();
                    });
                }
            }
            
            function showHomepageSlide(index) {
                if (homepageSlides.length === 0) return;
                
                if (index < 0) index = homepageSlides.length - 1;
                if (index >= homepageSlides.length) index = 0;
                
                homepageSlides.forEach((slide, i) => {
                    if (i === index) {
                        slide.classList.add('active');
                    } else {
                        slide.classList.remove('active');
                    }
                });
                
                currentHomepageSlide = index;
            }
            
            function nextHomepageSlide() {
                showHomepageSlide(currentHomepageSlide + 1);
            }
            
            function prevHomepageSlide() {
                showHomepageSlide(currentHomepageSlide - 1);
            }
            
            // Right Sidebar Tab Switching
            function switchRightTab(tabName, buttonElement) {
                // Hide all tab contents
                document.querySelectorAll('.right-slider-tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Remove active class from all tabs
                document.querySelectorAll('.right-slider-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                // Show selected tab content
                const contentId = 'right-tab-' + tabName;
                const content = document.getElementById(contentId);
                if (content) {
                    content.classList.add('active');
                }
                
                // Add active class to clicked tab
                if (buttonElement) {
                    buttonElement.classList.add('active');
                }
            }
            
            // Initialize homepage slider on page load
            document.addEventListener('DOMContentLoaded', function() {
                initHomepageSlider();
            });
        } else {
            // DOM already loaded, try immediately
            tryInitCarousel();
        }
        
        // Also try on window load as fallback (for ngrok/IP compatibility)
        window.addEventListener('load', function() {
            setTimeout(function() {
                if (document.getElementById('newsflash-carousel')) {
                    if (!carouselInterval) {
                        initCarousel();
                    }
                }
            }, 500);
        });
        
        // Additional fallback for slow-loading pages (ngrok)
        setTimeout(function() {
            if (document.getElementById('newsflash-carousel') && !carouselInterval) {
                initCarousel();
            }
        }, 1000);
    </script>
    
    <!-- News Ticker Scroll Animation -->
    <script>
    (function() {
        const tickerScroll = document.getElementById('ticker-scroll');
        if (tickerScroll) {
            // Calculate animation duration based on content width
            const scrollWidth = tickerScroll.scrollWidth;
            const containerWidth = tickerScroll.parentElement.offsetWidth;
            const totalDistance = scrollWidth / 2; // We duplicate content for seamless loop
            const speed = 50; // pixels per second
            const duration = totalDistance / speed; // seconds
            
            // Set animation duration dynamically
            tickerScroll.style.animationDuration = duration + 's';
            
            // Reset on window resize
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    const newScrollWidth = tickerScroll.scrollWidth;
                    const newTotalDistance = newScrollWidth / 2;
                    const newDuration = newTotalDistance / speed;
                    tickerScroll.style.animationDuration = newDuration + 's';
                }, 250);
            });
        }
    })();
    </script>
    
    <!-- Include Footer -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>

<?php
} catch (Exception $e) {
    // Log the error
    error_log("Fatal error in index.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Display a user-friendly error page
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Error - Homepage</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; text-align: center; background: #f5f5f5; }
            .error-box { background: #fff; border: 1px solid #ddd; padding: 40px; border-radius: 8px; max-width: 600px; margin: 50px auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #721c24; margin-bottom: 20px; }
            p { color: #856404; line-height: 1.6; }
            a { color: #007bff; text-decoration: none; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Error Loading Homepage</h1>
            <p>An error occurred while loading the homepage.</p>
            <p>Please try refreshing the page or contact the administrator.</p>
            <p style="margin-top: 30px;">
                <a href="javascript:location.reload()">🔄 Refresh Page</a> | 
                <a href="login.php">🔐 Login</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

