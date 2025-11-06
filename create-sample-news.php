<?php
/**
 * Create Sample News Articles with Featured Images
 * Run this script once to populate the database with sample news articles
 * Access via: http://localhost/music/create-sample-news.php
 * 
 * This creates 15 news articles matching jnews.io/default/ homepage
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Function to generate placeholder image using placeholder.com API
function generatePlaceholderImage($width = 800, $height = 450, $text = '', $bgColor = '', $textColor = 'fff') {
    // Use placeholder.com or similar service
    $baseUrl = "https://via.placeholder.com/{$width}x{$height}";
    $params = [];
    
    if ($text) {
        $params[] = 'text=' . urlencode($text);
    }
    if ($bgColor) {
        $params[] = 'bg=' . urlencode($bgColor);
    }
    if ($textColor && $textColor !== 'fff') {
        $params[] = 'txtclr=' . urlencode($textColor);
    }
    
    if (!empty($params)) {
        $baseUrl .= '?' . implode('&', $params);
    }
    
    return $baseUrl;
}

// Function to download and save image
function saveImageFromUrl($url, $slug) {
    $upload_dir = __DIR__ . '/uploads/images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Download image
    $imageData = @file_get_contents($url);
    if ($imageData === false) {
        // If download fails, return placeholder URL
        return generatePlaceholderImage(800, 450, substr($slug, 0, 30));
    }
    
    // Save image
    $extension = 'jpg';
    $filename = $slug . '_featured.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (file_put_contents($filepath, $imageData)) {
        return 'uploads/images/' . $filename;
    }
    
    // Fallback to placeholder URL
    return generatePlaceholderImage(800, 450, substr($slug, 0, 30));
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if news table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'news'");
    if ($checkTable->rowCount() == 0) {
        // Create news table (matches actual schema)
        $conn->exec("
            CREATE TABLE IF NOT EXISTS news (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                category VARCHAR(100) DEFAULT 'Entertainment',
                content TEXT NOT NULL,
                excerpt TEXT,
                image VARCHAR(255),
                author_id INT,
                views BIGINT DEFAULT 0,
                is_published TINYINT(1) DEFAULT 1,
                featured TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_featured (featured),
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
    }
    
    // Category color mapping for placeholder images
    $categoryColors = [
        'Politics' => '3498db',
        'Business' => '2ecc71',
        'Tech' => '9b59b6',
        'Entertainment' => 'e74c3c',
        'Movie' => 'f39c12',
        'Music' => 'e91e63',
        'Fashion' => 'ff69b4',
        'World' => '34495e',
        'Travel' => '1abc9c',
        'Health' => '16a085',
        'Gaming' => '8e44ad',
        'Lifestyle' => '27ae60'
    ];
    
    // 15 Sample news articles matching jnews.io/default/ homepage
    $sampleNews = [
        [
            'title' => 'Best 10 Music: Listen To the Top New Music of the Year',
            'slug' => 'best-10-music-top-new-music-year',
            'category' => 'Music',
            'content' => 'It is a paradisematic country, in which roasted parts of sentences fly into your mouth. Even the all-powerful Pointing has no control about the blind texts it is an almost unorthographic life One day however a small line of blind text by the name of Lorem Ipsum decided to leave for the far World of Grammar. The Big Oxmox advised her not to do so, because there were thousands of bad Commas, wild Question Marks and devious Semikoli, but the Little Blind Text didn\'t listen. Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts.',
            'excerpt' => 'Discover the top 10 new music releases that are making waves this year. From emerging artists to established stars, this comprehensive list covers the best tracks you need to hear.',
            'featured' => 1,
            'image_text' => 'Top 10 Music'
        ],
        [
            'title' => 'Top 10 Best Movies of 2018 So Far: Great Movies To Watch Now',
            'slug' => 'top-10-best-movies-2018',
            'category' => 'Movie',
            'content' => 'Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean. A small river named Duden flows by their place and supplies it with the necessary regelialia. It is a paradisematic country, in which roasted parts of sentences fly into your mouth. Even the all-powerful Pointing has no control about the blind texts.',
            'excerpt' => 'Check out the best movies of 2018 that you shouldn\'t miss. Our curated list includes action, drama, comedy, and thriller films that have captivated audiences worldwide.',
            'featured' => 1,
            'image_text' => 'Top Movies 2018'
        ],
        [
            'title' => '5 Fashion stories from around the web you might have missed this week',
            'slug' => '5-fashion-stories-around-web-week',
            'category' => 'Fashion',
            'content' => 'Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean. A small river named Duden flows by their place and supplies it with the necessary regelialia.',
            'excerpt' => 'Catch up on the latest fashion trends and stories that made headlines this week. From runway shows to street style, discover what\'s trending in the fashion world.',
            'featured' => 0,
            'image_text' => 'Fashion Stories'
        ],
        [
            'title' => 'Doctors take inspiration from online dating to build organ transplant AI',
            'slug' => 'doctors-online-dating-organ-transplant-ai',
            'category' => 'Health',
            'content' => 'A small river named Duden flows by their place and supplies it with the necessary regelialia. It is a paradisematic country, in which roasted parts of sentences fly into your mouth. Even the all-powerful Pointing has no control about the blind texts it is an almost unorthographic life.',
            'excerpt' => 'Innovative approach using AI technology inspired by dating apps to match organ donors with recipients. This groundbreaking system could revolutionize transplant medicine.',
            'featured' => 0,
            'image_text' => 'Organ Transplant AI'
        ],
        [
            'title' => 'Sony shares a list of 39 titles that will be optimized for the PS4 Pro at launch',
            'slug' => 'sony-39-titles-ps4-pro-optimized',
            'category' => 'Gaming',
            'content' => 'Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean. A small river named Duden flows by their place and supplies it with the necessary regelialia. It is a paradisematic country, in which roasted parts of sentences fly into your mouth.',
            'excerpt' => 'Sony announces 39 games that will take full advantage of the PS4 Pro\'s enhanced capabilities. Get ready for stunning graphics and improved performance.',
            'featured' => 1,
            'image_text' => 'PS4 Pro Games'
        ],
        [
            'title' => 'Why Millennials Need to Save Twice as Much as Boomers Did',
            'slug' => 'millennials-save-twice-much-boomers',
            'category' => 'Business',
            'content' => 'It is a paradisematic country, in which roasted parts of sentences fly into your mouth. Even the all-powerful Pointing has no control about the blind texts it is an almost unorthographic life One day however a small line of blind text by the name of Lorem Ipsum decided to leave for the far World of Grammar.',
            'excerpt' => 'Financial experts explain why younger generations need to save more aggressively for retirement. Economic shifts and changing retirement landscapes require new strategies.',
            'featured' => 1,
            'image_text' => 'Millennials Finance'
        ],
        [
            'title' => 'President Obama Holds his Final Press Conference',
            'slug' => 'president-obama-final-press-conference',
            'category' => 'Politics',
            'content' => 'The Big Oxmox advised her not to do so, because there were thousands of bad Commas, wild Question Marks and devious Semikoli, but the Little Blind Text didn\'t listen. One day however a small line of blind text by the name of Lorem Ipsum decided to leave for the far World of Grammar.',
            'excerpt' => 'Former President Obama addresses the nation in his final press conference as president. Reflecting on his tenure and looking toward the future.',
            'featured' => 0,
            'image_text' => 'Obama Press'
        ],
        [
            'title' => 'Retirees, It May Be Time To Get Your Head Out Of The Sand',
            'slug' => 'retirees-time-head-out-sand',
            'category' => 'Business',
            'content' => 'One day however a small line of blind text by the name of Lorem Ipsum decided to leave for the far World of Grammar. The Big Oxmox advised her not to do so, because there were thousands of bad Commas, wild Question Marks and devious Semikoli.',
            'excerpt' => 'Financial advisors urge retirees to be proactive about their investment strategies. Don\'t ignore market changes - adapt and thrive.',
            'featured' => 0,
            'image_text' => 'Retirement Planning'
        ],
        [
            'title' => 'Washington prepares for Donald Trump\'s big moment',
            'slug' => 'washington-prepares-trump-big-moment',
            'category' => 'Politics',
            'content' => 'Even the all-powerful Pointing has no control about the blind texts it is an almost unorthographic life One day however a small line of blind text. Far far away, behind the word mountains, far from the countries Vokalia and Consonantia, there live the blind texts.',
            'excerpt' => 'The nation\'s capital gears up for a historic presidential inauguration. Security, logistics, and preparations reach final stages.',
            'featured' => 0,
            'image_text' => 'Trump Inauguration'
        ],
        [
            'title' => 'Here\'s Some of the Best Sneakers on Display at London Fashion Week',
            'slug' => 'best-sneakers-london-fashion-week',
            'category' => 'Fashion',
            'content' => 'Far from the countries Vokalia and Consonantia, there live the blind texts. Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean. A small river named Duden flows by their place.',
            'excerpt' => 'Fashion enthusiasts showcase the most stylish sneakers from London Fashion Week. From classic designs to bold statements, see what\'s trending.',
            'featured' => 0,
            'image_text' => 'London Fashion'
        ],
        [
            'title' => 'This Soldier is strengthening bonds and getting the job done',
            'slug' => 'soldier-strengthening-bonds-job-done',
            'category' => 'World',
            'content' => 'A small river named Duden flows by their place and supplies it with the necessary regelialia. It is a paradisematic country, in which roasted parts of sentences fly into your mouth. Even the all-powerful Pointing has no control about the blind texts.',
            'excerpt' => 'A heartwarming story about a soldier making a difference in his community. Building bridges and creating positive change wherever he goes.',
            'featured' => 0,
            'image_text' => 'Soldier Story'
        ],
        [
            'title' => '3 Things Entrepreneurs Need To Do When Dealing With Depression',
            'slug' => '3-things-entrepreneurs-dealing-depression',
            'category' => 'Business',
            'content' => 'The Big Oxmox advised her not to do so, because there were thousands of bad Commas, wild Question Marks and devious Semikoli. One day however a small line of blind text by the name of Lorem Ipsum decided to leave for the far World of Grammar.',
            'excerpt' => 'Mental health experts share advice for entrepreneurs facing depression and burnout. Practical strategies to maintain wellness while building your business.',
            'featured' => 0,
            'image_text' => 'Entrepreneurs Mental Health'
        ],
        [
            'title' => 'With 150 million daily active users, Instagram Stories is launching ads',
            'slug' => 'instagram-stories-launching-ads',
            'category' => 'Tech',
            'content' => 'Separated they live in Bookmarksgrove right at the coast of the Semantics, a large language ocean. A small river named Duden flows by their place and supplies it with the necessary regelialia. It is a paradisematic country.',
            'excerpt' => 'Instagram announces advertising opportunities for its popular Stories feature. Brands can now reach millions of users through immersive story ads.',
            'featured' => 0,
            'image_text' => 'Instagram Stories'
        ],
        [
            'title' => 'Jokowi Seeks Investors for Indonesia\'s Airports to Curb Deficit',
            'slug' => 'jokowi-investors-indonesia-airports',
            'category' => 'Politics',
            'content' => 'A small river named Duden flows by their place and supplies it with the necessary regelialia. It is a paradisematic country, in which roasted parts of sentences fly into your mouth. Even the all-powerful Pointing has no control.',
            'excerpt' => 'Indonesian President seeks foreign investment to improve airport infrastructure. Modernizing transportation hubs to boost economic growth.',
            'featured' => 1,
            'image_text' => 'Indonesia Airports'
        ],
        [
            'title' => 'This Chinese Province Says It Faked Fiscal Data for Several Years',
            'slug' => 'chinese-province-faked-fiscal-data',
            'category' => 'World',
            'content' => 'It is a paradisematic country, in which roasted parts of sentences fly into your mouth. Even the all-powerful Pointing has no control about the blind texts it is an almost unorthographic life One day however a small line of blind text.',
            'excerpt' => 'Shocking revelation about fiscal data manipulation in Chinese province. Transparency and accountability concerns raised by the disclosure.',
            'featured' => 0,
            'image_text' => 'China Fiscal Data'
        ]
    ];
    
    // Get first admin user ID for author_id, or use NULL
    $author_id = null;
    try {
        $authorStmt = $conn->query("SELECT id FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1");
        $author = $authorStmt->fetch(PDO::FETCH_ASSOC);
        if ($author) {
            $author_id = $author['id'];
        }
    } catch (Exception $e) {
        // If no admin user, use NULL (author_id can be NULL)
        $author_id = null;
    }
    
    // Insert sample news with images
    $inserted = 0;
    $stmt = $conn->prepare("
        INSERT INTO news (title, slug, category, content, excerpt, image, author_id, is_published, featured, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE title = title
    ");
    
    foreach ($sampleNews as $index => $news) {
        try {
            // Generate image URL with category-specific color
            $category = $news['category'];
            $bgColor = $categoryColors[$category] ?? '667eea';
            $imageText = $news['image_text'] ?? substr($news['title'], 0, 20);
            
            // Create placeholder image URL
            $imageUrl = generatePlaceholderImage(800, 450, $imageText, $bgColor, 'ffffff');
            
            // Try to download and save image, or use URL directly
            $imagePath = saveImageFromUrl($imageUrl, $news['slug']);
            
            // Set random views for popularity
            $views = rand(100, 5000);
            
            // Create varied publish dates (last 30 days)
            $daysAgo = rand(0, 30);
            $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));
            
            $stmt->execute([
                $news['title'],
                $news['slug'],
                $category,
                $news['content'],
                $news['excerpt'],
                $imagePath,
                $author_id, // author_id (can be NULL)
                1, // is_published
                $news['featured'] ?? 0,
                $createdAt
            ]);
            
            // Update views count
            $updateViewsStmt = $conn->prepare("UPDATE news SET views = ? WHERE slug = ?");
            $updateViewsStmt->execute([$views, $news['slug']]);
            
            $inserted++;
            
        } catch (PDOException $e) {
            // Skip if duplicate
            if ($e->getCode() != 23000) {
                echo "Error inserting {$news['title']}: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    // Display results
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Sample News Created</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
            a { color: #2196F3; text-decoration: none; margin: 0 10px; }
            a:hover { text-decoration: underline; }
            .news-list { margin-top: 20px; }
            .news-item { padding: 10px; border-bottom: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <h1>Sample News Created Successfully!</h1>
        
        <div class='success'>
            <strong>✓ Inserted {$inserted} news articles</strong>
        </div>
        
        <div class='info'>
            <strong>Categories Created:</strong><br>";
    
    // Show category breakdown
    $categoryStmt = $conn->query("SELECT category, COUNT(*) as count FROM news GROUP BY category");
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($categories as $cat) {
        echo "• {$cat['category']}: {$cat['count']} articles<br>";
    }
    
    echo "        </div>
        
        <div style='margin: 30px 0;'>
            <a href='index.php' style='background: #2196F3; color: white; padding: 12px 24px; border-radius: 5px; display: inline-block;'>View Homepage</a>
            <a href='admin/news.php' style='background: #4CAF50; color: white; padding: 12px 24px; border-radius: 5px; display: inline-block;'>Manage News</a>
        </div>
        
        <div class='news-list'>
            <h3>Created Articles:</h3>";
    
    // List all created articles
    $listStmt = $conn->query("SELECT title, category, featured, image FROM news ORDER BY created_at DESC LIMIT 15");
    $articles = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($articles as $article) {
        $featuredBadge = $article['featured'] ? '<span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">FEATURED</span>' : '';
        $hasImage = !empty($article['image']) ? '✓' : '✗';
        echo "<div class='news-item'>
                <strong>{$article['title']}</strong> 
                <span style='color: #666;'>({$article['category']})</span> 
                {$featuredBadge}
                <span style='float: right; color: #999;'>Image: {$hasImage}</span>
              </div>";
    }
    
    echo "        </div>
        
        <div style='margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;'>
            <h4>Note:</h4>
            <p>Featured images have been generated using placeholder.com. Each category has a unique color scheme:</p>
            <ul>
                <li><strong>Politics:</strong> Blue theme</li>
                <li><strong>Business:</strong> Green theme</li>
                <li><strong>Tech:</strong> Purple theme</li>
                <li><strong>Entertainment/Movie/Music:</strong> Red/Orange/Pink themes</li>
                <li><strong>Fashion:</strong> Pink theme</li>
                <li><strong>World:</strong> Dark theme</li>
                <li><strong>Travel/Health:</strong> Teal themes</li>
                <li><strong>Gaming:</strong> Purple theme</li>
            </ul>
            <p>You can replace these placeholder images with actual photos via the Admin Panel → News Management.</p>
        </div>
    </body>
    </html>";
    
} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
