<?php
// index-simple.php - Simple non-AJAX homepage for testing
require_once 'config/config.php';
require_once 'includes/song-storage.php';

$featured_songs = getFeaturedSongs();
$recent_songs = getRecentSongs();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Music Streaming Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .header { background: white; padding: 15px 0; border-bottom: 1px solid #e9ecef; }
        .logo { font-size: 24px; font-weight: 700; color: #1db954; text-decoration: none; }
        .main-content { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        .song-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .song-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .song-artist { font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <a href="index-simple.php" class="logo">
                <i class="fas fa-music"></i> <?php echo SITE_NAME; ?>
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <h1>Welcome to <?php echo SITE_NAME; ?></h1>
        <p>This is a simple test page to verify the basic setup is working.</p>
        
        <h2>Featured Songs (<?php echo count($featured_songs); ?>)</h2>
        <?php foreach ($featured_songs as $song): ?>
            <div class="song-card">
                <div class="song-title"><?php echo htmlspecialchars($song['title']); ?></div>
                <div class="song-artist"><?php echo htmlspecialchars($song['artist']); ?></div>
            </div>
        <?php endforeach; ?>
        
        <h2>Recent Songs (<?php echo count($recent_songs); ?>)</h2>
        <?php foreach ($recent_songs as $song): ?>
            <div class="song-card">
                <div class="song-title"><?php echo htmlspecialchars($song['title']); ?></div>
                <div class="song-artist"><?php echo htmlspecialchars($song['artist']); ?></div>
            </div>
        <?php endforeach; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #e9ecef; border-radius: 8px;">
            <h3>Debug Information</h3>
            <p><strong>Config loaded:</strong> <?php echo defined('SITE_NAME') ? 'Yes' : 'No'; ?></p>
            <p><strong>SITE_NAME:</strong> <?php echo SITE_NAME; ?></p>
            <p><strong>Featured songs:</strong> <?php echo count($featured_songs); ?></p>
            <p><strong>Recent songs:</strong> <?php echo count($recent_songs); ?></p>
            <p><strong>Data file exists:</strong> <?php echo file_exists('data/songs.json') ? 'Yes' : 'No'; ?></p>
        </div>
    </main>
</body>
</html>
