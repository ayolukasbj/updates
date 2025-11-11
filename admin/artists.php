<?php
require_once 'auth-check.php';
require_once '../config/database.php';
require_once '../includes/song-storage.php';

$page_title = 'Artist Management';

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
    $artist_id = $_POST['artist_id'] ?? 0;
    
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM artists WHERE id = ?");
        if ($stmt->execute([$artist_id])) {
            $success = 'Artist deleted successfully';
            logAdminActivity($_SESSION['user_id'], 'delete_artist', 'artist', $artist_id, "Deleted artist ID: $artist_id");
        }
    } elseif ($action === 'toggle_verified') {
        $stmt = $conn->prepare("UPDATE artists SET verified = NOT verified WHERE id = ?");
        if ($stmt->execute([$artist_id])) {
            $success = 'Artist verification status updated';
            logAdminActivity($_SESSION['user_id'], 'toggle_artist_verified', 'artist', $artist_id, "Toggled verified status");
        }
    }
}

// Pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$search = $_GET['search'] ?? '';
$verified_filter = $_GET['verified'] ?? '';

// Build query
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "a.name LIKE ?";
    $params[] = "%$search%";
}

if ($verified_filter === 'yes') {
    $where_clauses[] = "a.verified = 1";
} elseif ($verified_filter === 'no') {
    $where_clauses[] = "a.verified = 0";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get artists from Database
try {
    $count_sql = "SELECT COUNT(*) as count FROM artists a $where_sql";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_artists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $total_pages = ceil($total_artists / $per_page);

    // Get artists with song count
    $sql = "
        SELECT a.*, 
               COUNT(DISTINCT s.id) as song_count,
               SUM(s.plays) as total_plays,
               SUM(s.downloads) as total_downloads
        FROM artists a
        LEFT JOIN songs s ON a.id = s.artist_id
        $where_sql
        GROUP BY a.id
        ORDER BY a.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $artists = [];
    $total_artists = 0;
    $total_pages = 0;
    $error = 'Error loading artists: ' . $e->getMessage();
    error_log("Artists query error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Artist Management</h1>
    <p>Manage all artists on the platform</p>
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
                <input type="text" name="search" class="form-control" placeholder="Artist name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Verified</label>
                <select name="verified" class="form-control">
                    <option value="">All</option>
                    <option value="yes" <?php echo $verified_filter === 'yes' ? 'selected' : ''; ?>>Verified</option>
                    <option value="no" <?php echo $verified_filter === 'no' ? 'selected' : ''; ?>>Not Verified</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="artists.php" class="btn btn-warning">
                <i class="fas fa-redo"></i> Reset
            </a>
        </form>
    </div>
</div>

<!-- Artists Table -->
<div class="card">
    <div class="card-header">
        <h2>All Artists (<?php echo number_format($total_artists); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Avatar</th>
                        <th>Name</th>
                        <th>Songs</th>
                        <th>Total Plays</th>
                        <th>Total Downloads</th>
                        <th>Verified</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($artists as $artist): ?>
                    <tr>
                        <td><?php echo $artist['id']; ?></td>
                        <td>
                            <?php if ($artist['avatar']): ?>
                            <img src="../<?php echo htmlspecialchars($artist['avatar']); ?>" alt="Avatar" class="img-thumbnail">
                            <?php else: ?>
                            <div style="width: 60px; height: 60px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                                <i class="fas fa-user" style="font-size: 24px; color: #9ca3af;"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($artist['name']); ?></strong>
                            <?php if ($artist['verified']): ?>
                            <i class="fas fa-check-circle" style="color: #3b82f6;" title="Verified"></i>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($artist['song_count']); ?></td>
                        <td><?php echo number_format($artist['total_plays'] ?? 0); ?></td>
                        <td><?php echo number_format($artist['total_downloads'] ?? 0); ?></td>
                        <td>
                            <?php if (true): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_verified">
                                <input type="hidden" name="artist_id" value="<?php echo $artist['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $artist['verified'] ? 'btn-success' : 'btn-icon'; ?>">
                                    <i class="fas fa-check-circle"></i> <?php echo $artist['verified'] ? 'Verified' : 'Verify'; ?>
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-sm btn-secondary" disabled>
                                <i class="fas fa-times-circle"></i> Not Verified
                            </button>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($artist['created_at'])); ?></td>
                        <td>
                            <?php if (true): ?>
                            <!-- Database Mode: Full Edit Controls -->
                            <a href="artist-edit.php?id=<?php echo $artist['id']; ?>" class="btn btn-primary btn-sm" title="Edit Artist">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="../artist-profile.php?id=<?php echo $artist['id']; ?>" class="btn btn-info btn-sm" title="View Public Profile" target="_blank">
                                <i class="fas fa-eye"></i>
                            </a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this artist? All their songs will be affected.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="artist_id" value="<?php echo $artist['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <!-- JSON Mode: Limited Controls -->
                            <a href="../artist-profile.php?name=<?php echo urlencode($artist['name']); ?>" class="btn btn-info btn-sm" title="View Profile" target="_blank">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button class="btn btn-secondary btn-sm" onclick="alert('📋 Artists are extracted from JSON songs.\n\n✅ To enable editing:\n1. Visit: admin/sync-artists.php\n2. Sync artists to database\n\nThen edit will work!')" title="Edit (Disabled)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="alert('📋 Artists are extracted from JSON.\n\nTo enable deletion, sync to database first.')" title="Delete (Disabled)">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
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
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&verified=<?php echo $verified_filter; ?>">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&verified=<?php echo $verified_filter; ?>" 
               class="<?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&verified=<?php echo $verified_filter; ?>">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

