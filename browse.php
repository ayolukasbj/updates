<?php
// browse.php - Browse music page
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Song.php';
require_once 'classes/Artist.php';

$database = new Database();
$db = $database->getConnection();
$song = new Song($db);
$artist = new Artist($db);

// Get songs and genres
$songs = $song->getSongs(1, 20);
$genres = $artist->getGenres();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Music - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-music"></i> <?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Home</a>
                <a class="nav-link active" href="browse.php">Browse</a>
                <?php if (is_logged_in()): ?>
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                    <a class="nav-link" href="logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-link" href="login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Browse Music</h1>
        
        <div class="row">
            <div class="col-md-3">
                <h5>Genres</h5>
                <div class="list-group">
                    <?php foreach ($genres as $genre): ?>
                        <a href="?genre=<?php echo $genre['id']; ?>" class="list-group-item list-group-item-action">
                            <?php echo htmlspecialchars($genre['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="col-md-9">
                <h5>Songs</h5>
                <div class="row">
                    <?php foreach ($songs as $song_item): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($song_item['title']); ?></h6>
                                    <p class="card-text"><?php echo htmlspecialchars($song_item['artist_name']); ?></p>
                                    <small class="text-muted"><?php echo format_duration($song_item['duration']); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
