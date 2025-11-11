<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Analytics & Reports';

$db = new Database();
$conn = $db->getConnection();

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Total stats
$stmt = $conn->query("SELECT COUNT(*) as count FROM users");
$total_users = $stmt->fetch()['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM songs");
$total_songs = $stmt->fetch()['count'];

$stmt = $conn->query("SELECT SUM(plays) as total FROM songs");
$total_plays = $stmt->fetch()['total'] ?? 0;

$stmt = $conn->query("SELECT SUM(downloads) as total FROM songs");
$total_downloads = $stmt->fetch()['total'] ?? 0;

// Top artists by plays
$stmt = $conn->query("
    SELECT a.name, SUM(s.plays) as total_plays, COUNT(s.id) as song_count
    FROM artists a
    LEFT JOIN songs s ON a.id = s.artist_id
    GROUP BY a.id
    ORDER BY total_plays DESC
    LIMIT 10
");
$top_artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top songs
$stmt = $conn->query("
    SELECT s.title, a.name as artist_name, s.plays, s.downloads
    FROM songs s
    LEFT JOIN artists a ON s.artist_id = a.id
    ORDER BY s.plays DESC
    LIMIT 10
");
$top_songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// New users per day (last 30 days)
$stmt = $conn->query("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");
$user_growth = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Genre distribution (check if genres table exists)
$genre_distribution = [];
try {
    $checkStmt = $conn->query("SHOW TABLES LIKE 'genres'");
    if ($checkStmt->rowCount() > 0) {
        $stmt = $conn->query("
            SELECT g.name, COUNT(s.id) as count
            FROM genres g
            LEFT JOIN songs s ON g.id = s.genre_id
            GROUP BY g.id
            ORDER BY count DESC
        ");
        $genre_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $genre_distribution = [];
    error_log("Genres table not found: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Analytics & Reports</h1>
    <p>Platform statistics and performance metrics</p>
</div>

<!-- Overall Stats -->
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
            <i class="fas fa-play-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($total_plays); ?></h3>
            <p>Total Plays</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-download"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo number_format($total_downloads); ?></h3>
            <p>Total Downloads</p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Top Artists -->
    <div class="card">
        <div class="card-header">
            <h2>Top Artists by Plays</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Artist</th>
                            <th>Songs</th>
                            <th>Total Plays</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_artists as $index => $artist): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($artist['name']); ?></td>
                            <td><?php echo number_format($artist['song_count']); ?></td>
                            <td><?php echo number_format($artist['total_plays'] ?? 0); ?></td>
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
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Artist</th>
                            <th>Plays</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_songs as $index => $song): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($song['title']); ?></td>
                            <td><?php echo htmlspecialchars($song['artist_name']); ?></td>
                            <td><?php echo number_format($song['plays']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- User Growth -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>User Growth (Last 30 Days)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>New Users</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_growth as $day): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($day['date'])); ?></td>
                        <td><?php echo number_format($day['count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Genre Distribution -->
<div class="card">
    <div class="card-header">
        <h2>Genre Distribution</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Genre</th>
                        <th>Songs</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_genre_songs = array_sum(array_column($genre_distribution, 'count'));
                    foreach ($genre_distribution as $genre): 
                        $percentage = $total_genre_songs > 0 ? ($genre['count'] / $total_genre_songs) * 100 : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($genre['name']); ?></td>
                        <td><?php echo number_format($genre['count']); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                    <div style="height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); width: <?php echo $percentage; ?>%;"></div>
                                </div>
                                <span><?php echo number_format($percentage, 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

