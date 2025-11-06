<?php
// page.php - Dynamic page viewer
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/ads.php';
require_once 'includes/theme-loader.php';

$db = new Database();
$conn = $db->getConnection();

// Get page slug from URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: index.php');
    exit;
}

// Fetch page from database
try {
    $stmt = $conn->prepare("SELECT * FROM pages WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$page) {
        header('HTTP/1.0 404 Not Found');
        include '404.php';
        exit;
    }
} catch (Exception $e) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($page['meta_description'])): ?>
    <meta name="description" content="<?php echo htmlspecialchars($page['meta_description']); ?>">
    <?php endif; ?>
    <title><?php echo htmlspecialchars($page['title']); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php renderThemeStyles(); ?>
    <style>
        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .page-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .page-content {
            background: #fff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .page-title {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }
        .page-body {
            line-height: 1.8;
            color: #555;
        }
        .page-body p {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <?php
    // Display header ad if exists
    $headerAd = displayAd('header');
    if ($headerAd) {
        echo '<div style="max-width: 1400px; margin: 10px auto; padding: 10px 15px;">' . $headerAd . '</div>';
    }
    ?>
    
    <div class="page-container">
        <?php
        // Display content top ad if exists
        $contentAd = displayAd('content_top');
        if ($contentAd) {
            echo '<div style="margin: 20px 0; text-align: center;">' . $contentAd . '</div>';
        }
        ?>
        
        <div class="page-content">
            <h1 class="page-title"><?php echo htmlspecialchars($page['title']); ?></h1>
            <div class="page-body">
                <?php echo $page['content']; ?>
            </div>
        </div>
        
        <?php
        // Display sidebar ad if exists
        $sidebarAd = displayAd('sidebar');
        if ($sidebarAd) {
            echo '<div style="margin: 30px 0; text-align: center;">' . $sidebarAd . '</div>';
        }
        ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

