<?php
// downloads.php - User downloads page
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Song.php';

if (!is_logged_in()) {
    redirect(SITE_URL . '/login.php');
}

$database = new Database();
$db = $database->getConnection();
$song = new Song($db);

$user_id = get_user_id();

// Get user's download history
$downloads = $song->getUserDownloads($user_id, 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Downloads - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-music"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Home</a>
                <a class="nav-link" href="browse.php">Browse</a>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="downloads.php">Downloads</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container" style="margin-top: 100px;">
        <h1><i class="fas fa-download"></i> My Downloads</h1>
        
        <div class="row">
            <?php foreach ($downloads as $download): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($download['title']); ?></h6>
                            <p class="card-text text-muted">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($download['artist_name']); ?>
                                <br>
                                <i class="fas fa-clock"></i> <?php echo format_duration($download['duration']); ?>
                                <br>
                                <i class="fas fa-calendar"></i> Downloaded on <?php echo date('M j, Y', strtotime($download['downloaded_at'])); ?>
                            </p>
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm" onclick="playSong(<?php echo $download['song_id']; ?>)">
                                    <i class="fas fa-play"></i> Play
                                </button>
                                <a href="api/download.php?id=<?php echo $download['song_id']; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-download"></i> Download Again
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($downloads)): ?>
            <div class="text-center py-5">
                <i class="fas fa-download fa-3x text-muted mb-3"></i>
                <h3>No Downloads Yet</h3>
                <p class="text-muted">Start downloading your favorite songs!</p>
                <a href="browse.php" class="btn btn-primary">Browse Music</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
