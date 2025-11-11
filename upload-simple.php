<?php
// upload.php - Music upload page (simplified version)
require_once 'config/config.php';

if (!is_logged_in()) {
    redirect(SITE_URL . '/login.php');
}

$user_id = get_user_id();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $artist = $_POST['artist'] ?? '';
    $genre = $_POST['genre'] ?? '';
    
    if (empty($title) || empty($artist)) {
        $error = 'Title and artist are required.';
    } else {
        $success = 'Song uploaded successfully! (Demo mode - no actual file uploaded)';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Music - <?php echo SITE_NAME; ?></title>
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
                <a class="nav-link active" href="upload.php">Upload</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container" style="margin-top: 100px;">
        <h1><i class="fas fa-upload"></i> Upload Music</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-music"></i> Upload New Song</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Song Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="artist" class="form-label">Artist Name *</label>
                                <input type="text" class="form-control" id="artist" name="artist" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="genre" class="form-label">Genre</label>
                                <select class="form-select" id="genre" name="genre">
                                    <option value="">Select Genre</option>
                                    <option value="Pop">Pop</option>
                                    <option value="Rock">Rock</option>
                                    <option value="Hip Hop">Hip Hop</option>
                                    <option value="Electronic">Electronic</option>
                                    <option value="Classical">Classical</option>
                                    <option value="Jazz">Jazz</option>
                                    <option value="Country">Country</option>
                                    <option value="R&B">R&B</option>
                                    <option value="Reggae">Reggae</option>
                                    <option value="Alternative">Alternative</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="album" class="form-label">Album</label>
                                <input type="text" class="form-control" id="album" name="album">
                            </div>
                            
                            <div class="mb-3">
                                <label for="file" class="form-label">Audio File</label>
                                <input type="file" class="form-control" id="file" name="file" accept="audio/*">
                                <div class="form-text">Supported formats: MP3, WAV, FLAC, AAC</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload Song
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Upload Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> Maximum file size: 50MB</li>
                            <li><i class="fas fa-check text-success"></i> Supported formats: MP3, WAV, FLAC, AAC</li>
                            <li><i class="fas fa-check text-success"></i> High quality audio recommended</li>
                            <li><i class="fas fa-check text-success"></i> Original content only</li>
                            <li><i class="fas fa-check text-success"></i> Proper metadata required</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Upload Stats</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Your Uploads:</strong> 0 songs</p>
                        <p><strong>Total Plays:</strong> 0</p>
                        <p><strong>Total Downloads:</strong> 0</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
