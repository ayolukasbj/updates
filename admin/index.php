<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Admin Dashboard';

// Get statistics
$db = new Database();
$conn = $db->getConnection();

// Total users
$total_users = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_users = $result['count'] ?? 0;
} catch (Exception $e) {
    $total_users = 0;
    error_log("Error getting total users: " . $e->getMessage());
}

// Total songs (check both database and JSON)
try {
    $total_songs = 0;
    $stmt = $conn->query("SHOW TABLES LIKE 'songs'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
        $total_songs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
} catch (Exception $e) {
    $total_songs = 0;
    error_log("Error getting total songs: " . $e->getMessage());
}

// Total artists
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'artists'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM artists");
        $total_artists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } else {
        $total_artists = 0;
    }
} catch (Exception $e) {
    $total_artists = 0;
}

// Total plays (check both database and JSON)
try {
    $total_plays = 0;
    $stmt = $conn->query("SHOW TABLES LIKE 'songs'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("SELECT SUM(plays) as count FROM songs");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_plays = $result['count'] ?? 0;
    }
    
} catch (Exception $e) {
    $total_plays = 0;
    error_log("Error getting total plays: " . $e->getMessage());
}

// Total downloads (check both database and JSON)
try {
    $total_downloads = 0;
    $stmt = $conn->query("SHOW TABLES LIKE 'songs'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("SELECT SUM(downloads) as count FROM songs");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_downloads = $result['count'] ?? 0;
    }
    
} catch (Exception $e) {
    $total_downloads = 0;
    error_log("Error getting total downloads: " . $e->getMessage());
}

// New users this month
$new_users_month = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $new_users_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $new_users_month = 0;
    error_log("Error getting new users: " . $e->getMessage());
}

// Recent users
try {
    // Check if role column exists
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    $roleExists = $stmt->rowCount() > 0;
    
    if ($roleExists) {
        $stmt = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    } else {
        $stmt = $conn->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    }
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_users = [];
}

// Top songs
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'songs'");
    if ($stmt->rowCount() > 0) {
        $stmt = $conn->query("
            SELECT s.id, s.title, a.name as artist_name, s.plays, s.downloads 
            FROM songs s 
            LEFT JOIN artists a ON s.artist_id = a.id 
            ORDER BY s.plays DESC 
            LIMIT 5
        ");
        $top_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $top_songs = [];
    }
} catch (Exception $e) {
    $top_songs = [];
}

// Recent admin activities (check if table exists first)
$recent_activities = [];
try {
    $checkStmt = $conn->query("SHOW TABLES LIKE 'admin_logs'");
    if ($checkStmt->rowCount() > 0) {
        $stmt = $conn->prepare("
            SELECT al.*, u.username 
            FROM admin_logs al 
            LEFT JOIN users u ON al.admin_id = u.id 
            ORDER BY al.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Table doesn't exist, use empty array
    $recent_activities = [];
    error_log("Admin logs table not found: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Dashboard Overview</h1>
    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>! Here's what's happening today.</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($total_users); ?></h3>
            <p>Total Users</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-music"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($total_songs); ?></h3>
            <p>Total Songs</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-microphone"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($total_artists); ?></h3>
            <p>Total Artists</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-play-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($total_plays); ?></h3>
            <p>Total Plays</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-download"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($total_downloads); ?></h3>
            <p>Total Downloads</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-user-plus"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($new_users_month); ?></h3>
            <p>New Users (This Month)</p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Recent Users -->
    <div class="card">
        <div class="card-header">
            <h2>Recent Users</h2>
            <a href="users.php" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="badge badge-info"><?php echo ucfirst($user['role'] ?? 'user'); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Songs -->
    <div class="card">
        <div class="card-header">
            <h2>Top Songs</h2>
            <a href="songs.php" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Artist</th>
                            <th>Plays</th>
                            <th>Downloads</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_songs as $song): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($song['title'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($song['artist_name'] ?? 'Unknown Artist'); ?></td>
                            <td><?php echo number_format($song['plays'] ?? 0); ?></td>
                            <td><?php echo number_format($song['downloads'] ?? 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activities -->
<div class="card">
    <div class="card-header">
        <h2>Recent Admin Activities</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Description</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_activities)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #999;">No activities yet</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($activity['username']); ?></td>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($activity['action']); ?></span></td>
                            <td><?php echo htmlspecialchars($activity['target_type'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($activity['description'] ?? '-'); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

