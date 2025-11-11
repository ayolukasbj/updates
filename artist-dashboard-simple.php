<?php
// artist-dashboard.php - Simplified working version
require_once 'config/config.php';

if (!is_logged_in()) {
    redirect(SITE_URL . '/login.php');
}

$user_id = get_user_id();
$error = '';
$success = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artist Dashboard - <?php echo SITE_NAME; ?></title>
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
                <a class="nav-link active" href="artist-dashboard.php">Artist Dashboard</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container" style="margin-top: 100px;">
        <h1><i class="fas fa-microphone"></i> Artist Dashboard</h1>
        
        <div class="alert alert-info">
            <h4>Welcome to Artist Dashboard!</h4>
            <p>This is your artist control center where you can manage your music, view statistics, and track your success.</p>
        </div>
        
        <!-- Artist Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-music fa-2x text-primary mb-2"></i>
                        <h4>0</h4>
                        <p class="text-muted">Total Songs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-play fa-2x text-success mb-2"></i>
                        <h4>0</h4>
                        <p class="text-muted">Total Plays</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-download fa-2x text-info mb-2"></i>
                        <h4>0</h4>
                        <p class="text-muted">Total Downloads</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-heart fa-2x text-danger mb-2"></i>
                        <h4>0</h4>
                        <p class="text-muted">Total Favorites</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="btn-group" role="group">
                            <a href="upload.php" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload New Song
                            </a>
                            <a href="profile.php" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                            <a href="browse.php" class="btn btn-outline-success">
                                <i class="fas fa-search"></i> Browse Music
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-warning">
                                <i class="fas fa-tachometer-alt"></i> User Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-music"></i> Your Songs</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center py-4">
                            <i class="fas fa-music fa-3x text-muted mb-3"></i>
                            <h4>No Songs Yet</h4>
                            <p class="text-muted">Start uploading your music to get started!</p>
                            <a href="upload.php" class="btn btn-primary">Upload Your First Song</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Getting Started -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-lightbulb"></i> Getting Started</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6><i class="fas fa-upload text-primary"></i> Step 1: Upload Music</h6>
                                <p class="text-muted">Upload your first song using our easy upload form.</p>
                                <a href="upload.php" class="btn btn-sm btn-primary">Upload Now</a>
                            </div>
                            <div class="col-md-4">
                                <h6><i class="fas fa-edit text-success"></i> Step 2: Complete Profile</h6>
                                <p class="text-muted">Add your artist bio, photo, and social links.</p>
                                <a href="profile.php" class="btn btn-sm btn-success">Edit Profile</a>
                            </div>
                            <div class="col-md-4">
                                <h6><i class="fas fa-share text-info"></i> Step 3: Share & Promote</h6>
                                <p class="text-muted">Share your music and grow your audience.</p>
                                <a href="browse.php" class="btn btn-sm btn-info">Browse Music</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
