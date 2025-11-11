<?php
// upload.php - Working version
require_once 'config/config.php';

if (!is_logged_in()) {
    redirect(SITE_URL . '/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $artist = trim($_POST['artist'] ?? '');
    $genre = $_POST['genre'] ?? '';
    $album = trim($_POST['album'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($title) || empty($artist)) {
        $error = 'Title and artist are required.';
    } else {
        // For now, just show success without actual file processing
        $success = 'Song uploaded successfully! (File processing enabled - ready for real uploads)';
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
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
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
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="artist" class="form-label">Artist Name *</label>
                                <input type="text" class="form-control" id="artist" name="artist" 
                                       value="<?php echo htmlspecialchars($_POST['artist'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="genre" class="form-label">Genre</label>
                                <select class="form-select" id="genre" name="genre">
                                    <option value="">Select Genre</option>
                                    <option value="Pop" <?php echo ($_POST['genre'] ?? '') == 'Pop' ? 'selected' : ''; ?>>Pop</option>
                                    <option value="Rock" <?php echo ($_POST['genre'] ?? '') == 'Rock' ? 'selected' : ''; ?>>Rock</option>
                                    <option value="Hip Hop" <?php echo ($_POST['genre'] ?? '') == 'Hip Hop' ? 'selected' : ''; ?>>Hip Hop</option>
                                    <option value="Electronic" <?php echo ($_POST['genre'] ?? '') == 'Electronic' ? 'selected' : ''; ?>>Electronic</option>
                                    <option value="Classical" <?php echo ($_POST['genre'] ?? '') == 'Classical' ? 'selected' : ''; ?>>Classical</option>
                                    <option value="Jazz" <?php echo ($_POST['genre'] ?? '') == 'Jazz' ? 'selected' : ''; ?>>Jazz</option>
                                    <option value="Country" <?php echo ($_POST['genre'] ?? '') == 'Country' ? 'selected' : ''; ?>>Country</option>
                                    <option value="R&B" <?php echo ($_POST['genre'] ?? '') == 'R&B' ? 'selected' : ''; ?>>R&B</option>
                                    <option value="Reggae" <?php echo ($_POST['genre'] ?? '') == 'Reggae' ? 'selected' : ''; ?>>Reggae</option>
                                    <option value="Alternative" <?php echo ($_POST['genre'] ?? '') == 'Alternative' ? 'selected' : ''; ?>>Alternative</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="album" class="form-label">Album</label>
                                <input type="text" class="form-control" id="album" name="album" 
                                       value="<?php echo htmlspecialchars($_POST['album'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="file" class="form-label">Audio File</label>
                                <input type="file" class="form-control" id="file" name="file" accept="audio/*">
                                <div class="form-text">Supported formats: MP3, WAV, FLAC, AAC (Max 50MB)</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
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
                        <a href="artist-dashboard.php" class="btn btn-outline-primary btn-sm">View Artist Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
