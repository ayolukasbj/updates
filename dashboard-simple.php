<?php
// Simplified dashboard
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect(SITE_URL . '/login.php');
}

$user_id = get_user_id();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Dashboard Content -->
    <div class="container" style="margin-top: 100px;">
        <div class="row">
            <div class="col-12">
                <h1>Welcome to Your Dashboard!</h1>
                
                <div class="alert alert-success">
                    <h4>✅ Success!</h4>
                    <p>You are logged in as: <strong><?php echo $_SESSION['username'] ?? 'Unknown'; ?></strong></p>
                    <p>User ID: <strong><?php echo $user_id; ?></strong></p>
                    <p>Email: <strong><?php echo $_SESSION['email'] ?? 'Unknown'; ?></strong></p>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-music fa-3x text-primary mb-3"></i>
                                <h5>Music Library</h5>
                                <p class="text-muted">Manage your music collection</p>
                                <a href="browse.php" class="btn btn-primary">Browse Music</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-upload fa-3x text-success mb-3"></i>
                                <h5>Upload Music</h5>
                                <p class="text-muted">Add new songs to your library</p>
                                <a href="upload.php" class="btn btn-success">Upload</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-cog fa-3x text-warning mb-3"></i>
                                <h5>Settings</h5>
                                <p class="text-muted">Manage your account settings</p>
                                <a href="profile.php" class="btn btn-warning">Settings</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h3>Quick Actions</h3>
                    <div class="btn-group" role="group">
                        <a href="index.php" class="btn btn-outline-primary">Homepage</a>
                        <a href="browse.php" class="btn btn-outline-secondary">Browse Music</a>
                        <a href="upload.php" class="btn btn-outline-success">Upload Music</a>
                        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
