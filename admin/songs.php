<?php
require_once 'auth-check.php';
require_once '../config/database.php';
require_once '../includes/song-storage.php';

$page_title = 'Song Management';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die('Database connection failed. Please check your database configuration.');
}

// Handle actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $song_id = $_POST['song_id'] ?? 0;
    
    if ($action === 'delete') {
        // Get song file path before deleting
        $stmt = $conn->prepare("SELECT file_path FROM songs WHERE id = ?");
        $stmt->execute([$song_id]);
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete song
        $stmt = $conn->prepare("DELETE FROM songs WHERE id = ?");
        if ($stmt->execute([$song_id])) {
            // Try to delete file
            if ($song && file_exists('../' . $song['file_path'])) {
                @unlink('../' . $song['file_path']);
            }
            $success = 'Song deleted successfully';
            logAdminActivity($_SESSION['user_id'], 'delete_song', 'song', $song_id, "Deleted song ID: $song_id");
        }
    } elseif ($action === 'toggle_featured') {
        $stmt = $conn->prepare("UPDATE songs SET is_featured = NOT is_featured WHERE id = ?");
        if ($stmt->execute([$song_id])) {
            $success = 'Song featured status updated';
            logAdminActivity($_SESSION['user_id'], 'toggle_song_featured', 'song', $song_id, "Toggled featured status");
        }
    } elseif ($action === 'update_status') {
        $status = $_POST['status'] ?? 'approved';
        $stmt = $conn->prepare("UPDATE songs SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $song_id])) {
            $success = 'Song status updated successfully';
            logAdminActivity($_SESSION['user_id'], 'update_song_status', 'song', $song_id, "Changed status to: $status");
        }
    }
}

// Pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$featured_filter = $_GET['featured'] ?? '';

// Build query
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(s.title LIKE ? OR a.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_clauses[] = "s.status = ?";
    $params[] = $status_filter;
}

if ($featured_filter === 'yes') {
    $where_clauses[] = "s.is_featured = 1";
} elseif ($featured_filter === 'no') {
    $where_clauses[] = "s.is_featured = 0";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get songs from Database
try {
    $count_sql = "
        SELECT COUNT(*) as count 
        FROM songs s
        LEFT JOIN artists a ON s.artist_id = a.id
        $where_sql
    ";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_songs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $total_pages = ceil($total_songs / $per_page);

    // Get songs
    $sql = "
        SELECT s.*, a.name as artist_name 
        FROM songs s
        LEFT JOIN artists a ON s.artist_id = a.id
        $where_sql
        ORDER BY s.upload_date DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $songs = [];
    $total_songs = 0;
    $total_pages = 0;
    $error = 'Error loading songs: ' . $e->getMessage();
    error_log("Songs query error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Song Management</h1>
    <p>Manage all songs uploaded to the platform</p>
    <div style="margin-top: 10px;">
        <a href="fix-empty-file-paths.php" class="btn btn-warning btn-sm">
            <i class="fas fa-wrench"></i> Fix Empty File Paths
        </a>
    </div>
</div>


<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                <label>Search</label>
                <input type="text" name="search" class="form-control" placeholder="Song title or artist..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Featured</label>
                <select name="featured" class="form-control">
                    <option value="">All</option>
                    <option value="yes" <?php echo $featured_filter === 'yes' ? 'selected' : ''; ?>>Featured</option>
                    <option value="no" <?php echo $featured_filter === 'no' ? 'selected' : ''; ?>>Not Featured</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="songs.php" class="btn btn-warning">
                <i class="fas fa-redo"></i> Reset
            </a>
        </form>
    </div>
</div>

<!-- Songs Table -->
<div class="card">
    <div class="card-header">
        <h2>All Songs (<?php echo number_format($total_songs); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Artist</th>
                        <th>Duration</th>
                        <th>Plays</th>
                        <th>Downloads</th>
                        <th>Status</th>
                        <th>Featured</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($songs as $song): ?>
                    <tr>
                        <td><?php echo $song['id']; ?></td>
                        <td><?php echo htmlspecialchars($song['title']); ?></td>
                        <td><?php echo htmlspecialchars($song['artist_name']); ?></td>
                        <td><?php echo gmdate('i:s', $song['duration']); ?></td>
                        <td><?php echo number_format($song['plays']); ?></td>
                        <td><?php echo number_format($song['downloads']); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="song_id" value="<?php echo $song['id']; ?>">
                                <select name="status" class="form-control" style="width: auto; display: inline; padding: 4px 8px;" onchange="this.form.submit()">
                                    <option value="approved" <?php echo $song['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="pending" <?php echo $song['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="rejected" <?php echo $song['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </form>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="song_id" value="<?php echo $song['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $song['is_featured'] ? 'btn-warning' : 'btn-icon'; ?>" title="<?php echo $song['is_featured'] ? 'Remove from featured' : 'Add to featured'; ?>">
                                    <i class="fas fa-star" style="color: <?php echo $song['is_featured'] ? '#f59e0b' : '#d1d5db'; ?>"></i>
                                </button>
                            </form>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($song['upload_date'])); ?></td>
                        <td>
                            <?php
                            // Generate song slug for URL
                            $songTitleSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['title']));
                            $songTitleSlug = preg_replace('/\s+/', '-', trim($songTitleSlug));
                            $songArtistSlug = strtolower(preg_replace('/[^a-z0-9\s]+/i', '', $song['artist_name'] ?? 'unknown-artist'));
                            $songArtistSlug = preg_replace('/\s+/', '-', trim($songArtistSlug));
                            $songSlug = $songTitleSlug . '-by-' . $songArtistSlug;
                            ?>
                            <a href="../song/<?php echo urlencode($songSlug); ?>" class="btn btn-info btn-sm" title="View" target="_blank">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="song-edit.php?id=<?php echo $song['id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="mp3-tagger.php?id=<?php echo $song['id']; ?>" class="btn btn-success btn-sm" title="Edit MP3 Tags">
                                <i class="fas fa-tags"></i>
                            </a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this song?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="song_id" value="<?php echo $song['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&featured=<?php echo $featured_filter; ?>">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&featured=<?php echo $featured_filter; ?>" 
               class="<?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&featured=<?php echo $featured_filter; ?>">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

