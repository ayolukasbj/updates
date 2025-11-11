<?php
// ajax/artist-news.php - News tab content for artist profile
// This file shows blog posts where the artist is tagged
// Disable error display for AJAX calls
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set proper headers - CORS for IP/ngrok compatibility
header('Content-Type: text/html; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fix paths - use __DIR__ to get the actual file location
// When included from artist-profile.php, relative paths break
$ajax_dir = __DIR__; // Directory of this file (ajax/)
$project_root = dirname($ajax_dir); // Parent directory (project root)

require_once $project_root . '/config/config.php';
require_once $project_root . '/config/database.php';

// Support both GET parameter names and direct variable assignment from include
$artist_name = isset($_GET['artist_name']) ? trim($_GET['artist_name']) : (isset($tab_artist_name) ? $tab_artist_name : '');
$artist_id = isset($_GET['artist_id']) ? (int)$_GET['artist_id'] : (isset($tab_artist_id) ? (int)$tab_artist_id : 0);

if (empty($artist_name) && empty($artist_id)) {
    echo '<p style="text-align: center; color: #999; padding: 40px;">No artist specified.</p>';
    exit;
}

try {
    if (!class_exists('Database')) {
        die('<p style="text-align: center; color: #999; padding: 40px;">Database class not found.</p>');
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get artist name for searching
    $artist_search_name = $artist_name;
    if ($artist_id && empty($artist_search_name)) {
        // Try to get artist name from users or artists table
        $nameStmt = $conn->prepare("
            SELECT COALESCE(u.username, a.name, '') as name 
            FROM users u 
            LEFT JOIN artists a ON a.user_id = u.id 
            WHERE u.id = ? OR a.id = ? OR a.user_id = ?
            LIMIT 1
        ");
        $nameStmt->execute([$artist_id, $artist_id, $artist_id]);
        $nameResult = $nameStmt->fetch(PDO::FETCH_ASSOC);
        if ($nameResult && !empty($nameResult['name'])) {
            $artist_search_name = $nameResult['name'];
        }
    }
    
    // Get news/blog posts where artist is tagged (in title or content)
    $search_term = '%' . $artist_search_name . '%';
    
    // Check if news table has tags or related_artists column
    $columns = $conn->prepare("SHOW COLUMNS FROM news");
    $columns->execute();
    $news_columns = $columns->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('tags', $news_columns) || in_array('related_artists', $news_columns)) {
        // If tags/related_artists column exists, use it
        $tag_column = in_array('tags', $news_columns) ? 'tags' : 'related_artists';
        $newsStmt = $conn->prepare("
            SELECT n.*, u.username as author_name
            FROM news n
            LEFT JOIN users u ON n.author_id = u.id
            WHERE n.is_published = 1
            AND (
                n.author_id = ? 
                OR n.title LIKE ? 
                OR n.content LIKE ?
                OR n.{$tag_column} LIKE ?
                OR n.co_author LIKE ?
            )
            ORDER BY n.created_at DESC
            LIMIT 10
        ");
        if ($artist_id) {
            $newsStmt->execute([$artist_id, $search_term, $search_term, $search_term, $search_term]);
        } else {
            $newsStmt->execute([0, $search_term, $search_term, $search_term, $search_term]);
        }
    } else {
        // Fallback: search in title, content, and co_author field (artist tagged in blog post or is co-author)
        $newsStmt = $conn->prepare("
            SELECT n.*, u.username as author_name
            FROM news n
            LEFT JOIN users u ON n.author_id = u.id
            WHERE n.is_published = 1
            AND (
                n.author_id = ? 
                OR n.title LIKE ? 
                OR n.content LIKE ?
                OR n.co_author LIKE ?
            )
            ORDER BY n.created_at DESC
            LIMIT 10
        ");
        if ($artist_id) {
            $newsStmt->execute([$artist_id, $search_term, $search_term, $search_term]);
        } else {
            $newsStmt->execute([0, $search_term, $search_term, $search_term]);
        }
    }
    
    $artist_news = $newsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error in artist-news.php: " . $e->getMessage());
    echo '<p style="text-align: center; color: #999; padding: 40px;">Error loading news. Please try again.</p>';
    exit;
} catch (Error $e) {
    error_log("Fatal error in artist-news.php: " . $e->getMessage());
    echo '<p style="text-align: center; color: #999; padding: 40px;">Error loading news. Please try again.</p>';
    exit;
}
?>

<div class="playlists-section">
    <h2 class="section-title">News</h2>
    <?php if (!empty($artist_news)): ?>
        <div style="margin-top: 20px;">
            <?php foreach ($artist_news as $news): ?>
                <div style="background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s; cursor: pointer;" 
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)'"
                     onclick="window.location.href='news-details.php?id=<?php echo $news['id']; ?>'">
                    <?php if (!empty($news['image'])): ?>
                        <img src="<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" 
                             style="width: 100%; max-width: 400px; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 15px;">
                    <?php endif; ?>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <?php if (!empty($news['category'])): ?>
                            <span style="background: #667eea; color: white; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: 600;">
                                <?php echo htmlspecialchars($news['category']); ?>
                            </span>
                        <?php endif; ?>
                        <span style="color: #999; font-size: 13px;">
                            <i class="far fa-calendar" style="margin-right: 5px;"></i>
                            <?php echo date('F d, Y', strtotime($news['created_at'] ?? $news['date'] ?? 'now')); ?>
                        </span>
                    </div>
                    <h3 style="color: #333; font-size: 20px; font-weight: 600; margin-bottom: 10px;">
                        <?php echo htmlspecialchars($news['title']); ?>
                    </h3>
                    <?php if (!empty($news['excerpt'])): ?>
                        <p style="color: #666; line-height: 1.6; margin-bottom: 10px;">
                            <?php echo htmlspecialchars($news['excerpt']); ?>
                        </p>
                    <?php endif; ?>
                    <a href="news-details.php?id=<?php echo $news['id']; ?>" style="color: #667eea; text-decoration: none; font-weight: 500; font-size: 14px;">
                        Read More <i class="fas fa-arrow-right" style="margin-left: 5px;"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: #999; padding: 40px;">No news available yet.</p>
    <?php endif; ?>
</div>

