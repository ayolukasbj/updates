<?php
// admin/debug-news.php
// Diagnostic tool for news page errors

require_once 'auth-check.php';
require_once '../config/config.php';
require_once '../config/database.php';

$page_title = 'Debug News Page';

$slug = 'one-pupil-misses-ple-at-swaria-centre-in-soroti';
$errors = [];
$info = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        $errors[] = 'Database connection failed';
    } else {
        $info[] = 'Database connection: OK';
        
        // Check if news table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'news'");
        if ($checkTable->rowCount() == 0) {
            $errors[] = 'News table does not exist';
        } else {
            $info[] = 'News table exists';
            
            // Try to find the article
            $slug_variations = [
                $slug,
                urldecode($slug),
                str_replace('-', ' ', $slug),
                str_replace('-', '_', $slug)
            ];
            
            $found = false;
            foreach ($slug_variations as $slug_to_try) {
                $stmt = $conn->prepare("
                    SELECT id, title, slug, is_published 
                    FROM news 
                    WHERE slug = ? OR slug LIKE ?
                    LIMIT 1
                ");
                $stmt->execute([$slug_to_try, '%' . $slug_to_try . '%']);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $info[] = "Found article with slug variation: $slug_to_try";
                    $info[] = "Article ID: " . $result['id'];
                    $info[] = "Title: " . $result['title'];
                    $info[] = "Slug in DB: " . $result['slug'];
                    $info[] = "Is Published: " . ($result['is_published'] ? 'Yes' : 'No');
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                // Try title search
                $title_search = str_replace('-', ' ', $slug);
                $stmt = $conn->prepare("
                    SELECT id, title, slug, is_published 
                    FROM news 
                    WHERE title LIKE ?
                    LIMIT 5
                ");
                $stmt->execute(['%' . $title_search . '%']);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if ($results) {
                    $info[] = "Found " . count($results) . " articles with similar title:";
                    foreach ($results as $r) {
                        $info[] = "  - ID: {$r['id']}, Title: {$r['title']}, Slug: {$r['slug']}";
                    }
                } else {
                    $errors[] = "No article found with slug or title matching: $slug";
                }
            }
        }
    }
} catch (Exception $e) {
    $errors[] = 'Error: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Debug News Page</h1>
    <p>Diagnostic tool for news page errors</p>
</div>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Testing Slug: <?php echo htmlspecialchars($slug); ?></h2>
    </div>
    <div class="card-body">
        <?php if (!empty($info)): ?>
        <div style="padding: 15px; background: #d1e7dd; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #0f5132; margin: 0 0 10px 0;">Information:</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($info as $msg): ?>
                <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div style="padding: 15px; background: #f8d7da; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #842029; margin: 0 0 10px 0;">Errors:</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $msg): ?>
                <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Test News Page Directly</h2>
    </div>
    <div class="card-body">
        <p>Test the news page with different methods:</p>
        <a href="../news/<?php echo htmlspecialchars($slug); ?>" target="_blank" class="btn btn-primary">
            <i class="fas fa-external-link-alt"></i> Test via /news/ URL
        </a>
        <a href="../news-details.php?slug=<?php echo urlencode($slug); ?>" target="_blank" class="btn btn-secondary">
            <i class="fas fa-external-link-alt"></i> Test via news-details.php?slug=
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

