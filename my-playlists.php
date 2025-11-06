<?php
// my-playlists.php - User's playlists page
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Playlist.php';

if (!is_logged_in()) {
    redirect(SITE_URL . '/login.php');
}

$database = new Database();
$db = $database->getConnection();
$playlist = new Playlist($db);

$user_id = get_user_id();
$playlists = $playlist->getUserPlaylists($user_id, 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Playlists - <?php echo SITE_NAME; ?></title>
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
                <a class="nav-link" href="artists.php">Artists</a>
                <a class="nav-link" href="playlists.php">Playlists</a>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="my-playlists.php">My Playlists</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container" style="margin-top: 100px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>My Playlists</h1>
            <a href="create-playlist.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Playlist
            </a>
        </div>
        
        <div class="row">
            <?php foreach ($playlists as $playlist_item): ?>
                <div class="col-md-4 mb-4">
                    <div class="card playlist-card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-list text-primary"></i>
                                <?php echo htmlspecialchars($playlist_item['name']); ?>
                            </h5>
                            <p class="card-text text-muted">
                                <?php echo htmlspecialchars($playlist_item['description'] ?? 'No description'); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-music"></i> <?php echo $playlist_item['song_count']; ?> songs
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-eye"></i> 
                                    <?php echo $playlist_item['is_public'] ? 'Public' : 'Private'; ?>
                                </small>
                            </div>
                            <div class="btn-group w-100" role="group">
                                <a href="playlist.php?id=<?php echo $playlist_item['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-play"></i> Play
                                </a>
                                <a href="edit-playlist.php?id=<?php echo $playlist_item['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <button class="btn btn-outline-danger btn-sm" onclick="deletePlaylist(<?php echo $playlist_item['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($playlists)): ?>
            <div class="text-center py-5">
                <i class="fas fa-list fa-3x text-muted mb-3"></i>
                <h3>No Playlists Yet</h3>
                <p class="text-muted">Create your first playlist to organize your favorite music!</p>
                <a href="create-playlist.php" class="btn btn-primary">Create Playlist</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function deletePlaylist(playlistId) {
            if (confirm('Are you sure you want to delete this playlist?')) {
                fetch('api/playlist.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        playlist_id: playlistId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting playlist: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting playlist');
                });
            }
        }
    </script>
</body>
</html>
