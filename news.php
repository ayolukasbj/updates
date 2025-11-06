<?php
// news.php - News Page
require_once 'config/config.php';
require_once 'config/database.php';

// Get all news from database - same approach as homepage
$db = new Database();
$conn = $db->getConnection();

try {
    // Get all published news from database - JOIN with users to get author username
    $stmt = $conn->query("
        SELECT n.*, COALESCE(u.username, 'Unknown') as author 
        FROM news n 
        LEFT JOIN users u ON n.author_id = u.id 
        WHERE n.is_published = 1 
        ORDER BY n.created_at DESC
    ");
    $all_news = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fallback to JSON if no database news
    if (empty($all_news)) {
        require_once 'includes/song-storage.php';
        $all_news = getAllNews();
    }
} catch (Exception $e) {
    error_log("Error fetching news: " . $e->getMessage());
    // Fallback to JSON
    require_once 'includes/song-storage.php';
    $all_news = getAllNews();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/luo-style.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding-bottom: 120px;
        }


        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 8px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            text-align: center;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .news-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .news-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-3px);
        }

        .news-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 64px;
        }

        .news-content {
            padding: 20px;
        }

        .news-meta {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .news-category {
            display: inline-block;
            padding: 5px 12px;
            background: #2196F3;
            color: white;
            font-size: 11px;
            font-weight: 600;
            border-radius: 3px;
            text-transform: uppercase;
        }

        .news-category.exclusive { background: #f44336; }
        .news-category.hot { background: #ff9800; }
        .news-category.shocking { background: #9c27b0; }
        .news-category.entertainment { background: #E91E63; }
        .news-category.politics { background: #3F51B5; }

        .news-date {
            font-size: 13px;
            color: #999;
            margin-left: 12px;
        }

        .news-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 12px 0;
            line-height: 1.5;
        }

        .news-excerpt {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            margin-top: 10px;
        }

        .read-more {
            display: inline-block;
            margin-top: 12px;
            color: #2196F3;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
        }

        .read-more:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .news-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 style="font-size: 36px; font-weight: 700; margin-bottom: 10px;">Latest News</h1>
            <p style="font-size: 16px; opacity: 0.9;">Stay updated with the latest news from Eastern Uganda and other regions of Uganda and the world</p>
        </div>

        <div class="news-grid">
            <?php foreach ($all_news as $news): ?>
            <?php 
            $news_slug = $news['slug'] ?? '';
            $news_id = $news['id'] ?? '';
            $news_link = !empty($news_slug) ? '/music/news/' . rawurlencode($news_slug) : '/music/news/' . $news_id;
            ?>
            <div class="news-card" onclick="window.location.href='<?php echo $news_link; ?>'">
                <div class="news-image">
                    <?php if (!empty($news['image'])): ?>
                        <img src="<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-newspaper"></i>
                    <?php endif; ?>
                </div>
                <div class="news-content">
                    <div class="news-meta">
                        <span class="news-category <?php echo strtolower(str_replace(' ', '-', $news['category'] ?? 'News')); ?>">
                            <?php echo htmlspecialchars($news['category'] ?? 'News'); ?>
                        </span>
                        <span class="news-date"><?php echo date('d M Y', strtotime($news['created_at'] ?? $news['date'] ?? 'now')); ?></span>
                    </div>
                    <a href="<?php echo $news_link; ?>" style="text-decoration: none; color: inherit;">
                    <h3 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                    </a>
                    <p class="news-excerpt"><?php echo htmlspecialchars(substr($news['content'] ?? $news['excerpt'] ?? '', 0, 120)); ?>...</p>
                    <a href="<?php echo $news_link; ?>" class="read-more">Read More →</a>
                </div>
            </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/luo-player.js"></script>
    <script>
        const player = new LuoPlayer();
    </script>
</body>
</html>

