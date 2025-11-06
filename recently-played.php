<?php
// recently-played.php - User recently played page
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

// Get user's recently played songs
$recently_played = $song->getRecentlyPlayed($user_id, 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recently Played - <?php echo SITE_NAME; ?></title>
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
                <a class="nav-link active" href="recently-played.php">Recently Played</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container" style="margin-top: 100px;">
        <h1><i class="fas fa-history"></i> Recently Played</h1>
        
        <div class="row">
            <?php foreach ($recently_played as $song_item): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($song_item['title']); ?></h6>
                            <p class="card-text text-muted">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($song_item['artist_name']); ?>
                                <br>
                                <i class="fas fa-clock"></i> <?php echo format_duration($song_item['duration']); ?>
                                <br>
                                <i class="fas fa-calendar"></i> Last played <?php echo date('M j, Y g:i A', strtotime($song_item['last_played'])); ?>
                            </p>
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm" onclick="playSong(<?php echo $song_item['id']; ?>)">
                                    <i class="fas fa-play"></i> Play
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="addToFavorites(<?php echo $song_item['id']; ?>)">
                                    <i class="fas fa-heart"></i> Add to Favorites
                                </button>
                                <a href="api/download.php?id=<?php echo $song_item['id']; ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($recently_played)): ?>
            <div class="text-center py-5">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <h3>No Recently Played Songs</h3>
                <p class="text-muted">Start playing some music to see your history here!</p>
                <a href="browse.php" class="btn btn-primary">Browse Music</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function addToFavorites(songId) {
            fetch('api/favorites.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    song_id: songId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Added to favorites!');
                } else {
                    alert('Error adding to favorites: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding to favorites');
            });
        }
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>
