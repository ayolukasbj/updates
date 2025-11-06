<?php
// Simple dashboard test
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
    <title>Dashboard Test - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>🎵 Dashboard Test</h1>
        
        <div class="alert alert-success">
            <h4>✅ Success!</h4>
            <p>You are logged in as user ID: <strong><?php echo $user_id; ?></strong></p>
            <p>Username: <strong><?php echo $_SESSION['username'] ?? 'Unknown'; ?></strong></p>
            <p>Email: <strong><?php echo $_SESSION['email'] ?? 'Unknown'; ?></strong></p>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <a href="index.php" class="btn btn-primary">Homepage</a>
                        <a href="upload.php" class="btn btn-success">Upload Music</a>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">System Status</h5>
                        <p>✅ PHP Working</p>
                        <p>✅ Database Connected</p>
                        <p>✅ User Authenticated</p>
                        <p>✅ Session Active</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <h3>Next Steps:</h3>
            <ol>
                <li>Test the <a href="debug-dashboard.php">Dashboard Debug</a> to see detailed status</li>
                <li>Try the <a href="index.php">Homepage</a> to see the full site</li>
                <li>Test <a href="upload.php">Music Upload</a> (if you have artist account)</li>
            </ol>
        </div>
    </div>
</body>
</html>
