<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Song Editor & Settings';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_song_stats':
                $song_id = (int)($_POST['song_id'] ?? 0);
                $plays = (int)($_POST['plays'] ?? 0);
                $downloads = (int)($_POST['downloads'] ?? 0);
                
                if ($song_id > 0) {
                    $stmt = $conn->prepare("UPDATE songs SET plays = ?, downloads = ? WHERE id = ?");
                    $stmt->execute([$plays, $downloads, $song_id]);
                    $success = 'Song statistics updated successfully!';
                }
                break;
                
            case 'set_top100_query':
                $query_type = trim($_POST['query_type'] ?? 'plays');
                $limit = (int)($_POST['limit'] ?? 100);
                
                saveSetting('top100_query_type', $query_type);
                saveSetting('top100_limit', $limit);
                $success = 'Top 100 query settings saved successfully!';
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Helper function
function getSetting($key, $default = '') {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function saveSetting($key, $value) {
    global $conn;
    try {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()");
        $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        // Create settings table if needed
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(100) NOT NULL UNIQUE, value TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$key, $value, $value]);
        } catch (Exception $e2) {
            error_log("Error saving setting: " . $e2->getMessage());
        }
    }
}

// Get all songs for editing
try {
    $search = $_GET['search'] ?? '';
    $where = '';
    $params = [];
    
    if (!empty($search)) {
        $where = "WHERE s.title LIKE ? OR s.artist LIKE ?";
        $params = ["%$search%", "%$search%"];
    }
    
    // Check if uploaded_by column exists
    $checkStmt = $conn->query("SHOW COLUMNS FROM songs LIKE 'uploaded_by'");
    $has_uploaded_by = $checkStmt->rowCount() > 0;
    
    if ($has_uploaded_by) {
        $stmt = $conn->prepare("SELECT s.*, COALESCE(u.username, 'Unknown') as uploader_name FROM songs s LEFT JOIN users u ON s.uploaded_by = u.id $where ORDER BY s.id DESC LIMIT 100");
    } else {
        $stmt = $conn->prepare("SELECT s.*, 'Unknown' as uploader_name FROM songs s $where ORDER BY s.id DESC LIMIT 100");
    }
    $stmt->execute($params);
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $songs = [];
    $error = 'Error loading songs: ' . $e->getMessage();
}

// Get top 100 settings
$top100_query_type = getSetting('top100_query_type', 'plays');
$top100_limit = getSetting('top100_limit', 100);

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Song Editor & Settings</h1>
    <p>Edit song statistics, configure Top 100 query, and manage plays/downloads</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Top 100 Query Settings -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Top 100 Chart Query Settings</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="set_top100_query">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Sort By *</label>
                    <select name="query_type" class="form-control" required>
                        <option value="plays" <?php echo $top100_query_type === 'plays' ? 'selected' : ''; ?>>Total Plays</option>
                        <option value="downloads" <?php echo $top100_query_type === 'downloads' ? 'selected' : ''; ?>>Total Downloads</option>
                        <option value="plays_downloads" <?php echo $top100_query_type === 'plays_downloads' ? 'selected' : ''; ?>>Plays + Downloads</option>
                        <option value="recent" <?php echo $top100_query_type === 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Limit *</label>
                    <input type="number" name="limit" class="form-control" value="<?php echo htmlspecialchars($top100_limit); ?>" required min="10" max="500">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Top 100 Settings</button>
        </form>
    </div>
</div>

<!-- Songs List with Edit -->
<div class="card">
    <div class="card-header">
        <h2>Edit Song Statistics (<?php echo count($songs); ?>)</h2>
        <form method="GET" style="display: inline-block; margin-left: 20px;">
            <input type="text" name="search" class="form-control" style="display: inline-block; width: 200px;" placeholder="Search songs..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Artist</th>
                        <th>Plays</th>
                        <th>Downloads</th>
                        <th>Uploader</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($songs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #999;">No songs found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($songs as $song): ?>
                        <tr>
                            <td><?php echo $song['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($song['title'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($song['artist'] ?? '-'); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_song_stats">
                                    <input type="hidden" name="song_id" value="<?php echo $song['id']; ?>">
                                    <input type="number" name="plays" class="form-control" style="width: 80px; display: inline;" value="<?php echo (int)($song['plays'] ?? 0); ?>">
                                    <input type="hidden" name="downloads" value="<?php echo (int)($song['downloads'] ?? 0); ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_song_stats">
                                    <input type="hidden" name="song_id" value="<?php echo $song['id']; ?>">
                                    <input type="hidden" name="plays" value="<?php echo (int)($song['plays'] ?? 0); ?>">
                                    <input type="number" name="downloads" class="form-control" style="width: 80px; display: inline;" value="<?php echo (int)($song['downloads'] ?? 0); ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                </form>
                            </td>
                            <td><?php echo htmlspecialchars($song['uploader_name'] ?? '-'); ?></td>
                            <td>
                                <a href="song-edit.php?id=<?php echo $song['id']; ?>" class="btn btn-sm btn-primary">Full Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

