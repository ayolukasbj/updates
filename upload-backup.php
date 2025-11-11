<?php
// upload.php
// Music upload page for artists

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/Song.php';
require_once 'classes/Artist.php';
require_once 'classes/Album.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect(SITE_URL . '/login.php');
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$song = new Song($db);
$artist = new Artist($db);
$album = new Album($db);

// Get user data
$user_data = $user->getUserById(get_user_id());

// Check if user is an artist
if ($user_data['subscription_type'] !== 'artist') {
    $_SESSION['error_message'] = 'You need to be an artist to upload music.';
    redirect(SITE_URL . '/subscription.php');
}

// Get user's artist profile
$artist_data = $artist->getArtistByUserId(get_user_id());
if (!$artist_data) {
    $_SESSION['error_message'] = 'Please complete your artist profile first.';
    redirect(SITE_URL . '/artist-profile.php');
}

// Get user's albums
$user_albums = $album->getArtistAlbums($artist_data['id']);

// Get genres
$genres = $artist->getGenres();

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $upload_data = [
        'title' => sanitize_input($_POST['title']),
        'artist_id' => $artist_data['id'],
        'album_id' => !empty($_POST['album_id']) ? $_POST['album_id'] : null,
        'genre_id' => $_POST['genre_id'],
        'quality' => $_POST['quality'],
        'lyrics' => sanitize_input($_POST['lyrics']),
        'track_number' => !empty($_POST['track_number']) ? $_POST['track_number'] : null
    ];

    // Validate required fields
    if (empty($upload_data['title']) || empty($upload_data['genre_id'])) {
        $_SESSION['error_message'] = 'Title and genre are required.';
    } elseif (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = 'Please select a valid audio file.';
    } else {
        $result = $song->uploadSong($upload_data, $_FILES['audio_file']);
        
        if ($result['success']) {
            $_SESSION['success_message'] = 'Song uploaded successfully!';
            redirect(SITE_URL . '/upload.php');
        } else {
            $_SESSION['error_message'] = $result['error'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Music - <?php echo SITE_NAME; ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link href="assets/css/upload.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-music"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="browse.php">Browse</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="artists.php">Artists</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="playlists.php">Playlists</a>
                    </li>
                </ul>
                
                <!-- User Menu -->
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="my-playlists.php"><i class="fas fa-list"></i> My Playlists</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Upload Content -->
    <div class="upload-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="upload-card">
                        <div class="upload-header">
                            <h2><i class="fas fa-upload"></i> Upload Music</h2>
                            <p>Share your music with the world</p>
                        </div>

                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="upload-form">
                            <!-- File Upload -->
                            <div class="upload-section">
                                <h5>Audio File</h5>
                                <div class="file-upload-area" id="file-upload-area">
                                    <div class="file-upload-content">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <h6>Drop your audio file here</h6>
                                        <p>or <span class="file-browse">browse</span> to select</p>
                                        <input type="file" name="audio_file" id="audio_file" accept=".mp3,.wav,.flac,.aac,.m4a" required>
                                    </div>
                                    <div class="file-info" id="file-info" style="display: none;">
                                        <div class="file-details">
                                            <i class="fas fa-music"></i>
                                            <div>
                                                <h6 id="file-name"></h6>
                                                <p id="file-size"></p>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="remove-file">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="file-requirements">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        Supported formats: MP3, WAV, FLAC, AAC, M4A. Max size: <?php echo format_file_size(MAX_FILE_SIZE); ?>
                                    </small>
                                </div>
                            </div>

                            <!-- Song Information -->
                            <div class="upload-section">
                                <h5>Song Information</h5>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="title" class="form-label">Song Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="track_number" class="form-label">Track Number</label>
                                        <input type="number" class="form-control" id="track_number" name="track_number" min="1">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="genre_id" class="form-label">Genre *</label>
                                        <select class="form-select" id="genre_id" name="genre_id" required>
                                            <option value="">Select a genre</option>
                                            <?php foreach ($genres as $genre): ?>
                                                <option value="<?php echo $genre['id']; ?>"><?php echo htmlspecialchars($genre['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="quality" class="form-label">Quality</label>
                                        <select class="form-select" id="quality" name="quality">
                                            <option value="high">High (320 kbps)</option>
                                            <option value="medium">Medium (192 kbps)</option>
                                            <option value="low">Low (128 kbps)</option>
                                            <option value="lossless">Lossless</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="album_id" class="form-label">Album</label>
                                    <select class="form-select" id="album_id" name="album_id">
                                        <option value="">No album</option>
                                        <?php foreach ($user_albums as $album_item): ?>
                                            <option value="<?php echo $album_item['id']; ?>"><?php echo htmlspecialchars($album_item['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <a href="create-album.php" class="text-decoration-none">Create new album</a>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="lyrics" class="form-label">Lyrics</label>
                                    <textarea class="form-control" id="lyrics" name="lyrics" rows="6" placeholder="Enter song lyrics (optional)"></textarea>
                                </div>
                            </div>

                            <!-- Upload Button -->
                            <div class="upload-actions">
                                <button type="submit" class="btn btn-primary btn-lg" id="upload-btn">
                                    <i class="fas fa-upload"></i> Upload Song
                                </button>
                                <a href="artist-dashboard.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/upload.js"></script>
</body>
</html>
