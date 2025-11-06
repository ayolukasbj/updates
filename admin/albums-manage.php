<?php
// admin/albums-manage.php - Admin page to manage albums
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Albums Management';

$db = new Database();
$conn = $db->getConnection();

// Handle actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $album_id = (int)($_POST['album_id'] ?? 0);
        if ($album_id > 0) {
            try {
                $deleteStmt = $conn->prepare("DELETE FROM albums WHERE id = ?");
                $deleteStmt->execute([$album_id]);
                $success = 'Album deleted successfully!';
                logAdminActivity($_SESSION['user_id'], 'delete_album', 'album', $album_id, "Deleted album ID: $album_id");
            } catch (Exception $e) {
                $error = 'Error deleting album: ' . $e->getMessage();
            }
        }
    }
}

// Pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search filter
$search = $_GET['search'] ?? '';
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(a.title LIKE ? OR a.description LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Check which column exists in albums table
$albumColumns = $conn->query("SHOW COLUMNS FROM albums");
$albumColumns->execute();
$albumColumnNames = $albumColumns->fetchAll(PDO::FETCH_COLUMN);

$has_user_id = in_array('user_id', $albumColumnNames);
$has_artist_id = in_array('artist_id', $albumColumnNames);

// Determine join condition
if ($has_user_id && $has_artist_id) {
    // Both exist, prefer user_id for joining with users
    $join_condition = "LEFT JOIN users u ON a.user_id = u.id";
} elseif ($has_user_id) {
    $join_condition = "LEFT JOIN users u ON a.user_id = u.id";
} elseif ($has_artist_id) {
    // If only artist_id exists, check if artists.user_id exists
    $checkArtUser = $conn->query("SHOW COLUMNS FROM artists LIKE 'user_id'");
    $has_art_user_id = $checkArtUser->rowCount() > 0;
    
    if ($has_art_user_id) {
        $join_condition = "LEFT JOIN artists art ON a.artist_id = art.id LEFT JOIN users u ON art.user_id = u.id";
    } else {
        $join_condition = "LEFT JOIN artists art ON a.artist_id = art.id";
    }
} else {
    // Neither exists, just left join users (won't match but won't error)
    $join_condition = "LEFT JOIN users u ON 1=0";
}

// Get total count
$countStmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM albums a
    $join_condition
    $where_sql
");
$countStmt->execute($params);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total / $per_page);

// Get albums
$params[] = $per_page;
$params[] = $offset;

// Adjust query based on join condition
if (strpos($join_condition, 'users u') !== false && strpos($join_condition, 'artists art') !== false) {
    // Has both users and artists join
    $stmt = $conn->prepare("
        SELECT a.*, 
               COALESCE(u.username, art.name, 'Unknown Artist') as artist_name,
               COUNT(s.id) as song_count
        FROM albums a
        $join_condition
        LEFT JOIN songs s ON s.album_id = a.id
        $where_sql
        GROUP BY a.id
        ORDER BY a.id DESC
        LIMIT ? OFFSET ?
    ");
} elseif (strpos($join_condition, 'users u') !== false) {
    // Has users join only
    $stmt = $conn->prepare("
        SELECT a.*, 
               COALESCE(u.username, 'Unknown Artist') as artist_name,
               COUNT(s.id) as song_count
        FROM albums a
        $join_condition
        LEFT JOIN songs s ON s.album_id = a.id
        $where_sql
        GROUP BY a.id
        ORDER BY a.id DESC
        LIMIT ? OFFSET ?
    ");
} elseif (strpos($join_condition, 'artists art') !== false) {
    // Has artists join only
    $stmt = $conn->prepare("
        SELECT a.*, 
               COALESCE(art.name, 'Unknown Artist') as artist_name,
               COUNT(s.id) as song_count
        FROM albums a
        $join_condition
        LEFT JOIN songs s ON s.album_id = a.id
        $where_sql
        GROUP BY a.id
        ORDER BY a.id DESC
        LIMIT ? OFFSET ?
    ");
} else {
    // No join
    $stmt = $conn->prepare("
        SELECT a.*, 
               'Unknown Artist' as artist_name,
               COUNT(s.id) as song_count
        FROM albums a
        LEFT JOIN songs s ON s.album_id = a.id
        $where_sql
        GROUP BY a.id
        ORDER BY a.id DESC
        LIMIT ? OFFSET ?
    ");
}
$stmt->execute($params);
$albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="page-content">
    <div class="page-header">
        <h1><i class="fas fa-compact-disc"></i> Albums Management</h1>
        <p>Manage all albums</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Search -->
    <div class="search-bar">
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search albums..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            <?php if ($search): ?>
                <a href="albums-manage.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Albums List -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cover</th>
                    <th>Album Title</th>
                    <th>Artist</th>
                    <th>Songs</th>
                    <th>Release Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($albums)): ?>
                    <?php foreach ($albums as $album): ?>
                        <tr>
                            <td>
                                <?php if (!empty($album['cover_art'])): ?>
                                    <img src="../<?php echo htmlspecialchars($album['cover_art']); ?>" 
                                         alt="<?php echo htmlspecialchars($album['title']); ?>"
                                         style="width: 50px; height: 50px; border-radius: 5px; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: #e9ecef; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-compact-disc" style="color: #999;"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($album['title']); ?></td>
                            <td><?php echo htmlspecialchars($album['artist_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo (int)($album['song_count'] ?? 0); ?></td>
                            <td><?php echo $album['release_date'] ? date('Y-m-d', strtotime($album['release_date'])) : 'N/A'; ?></td>
                            <td>
                                <a href="../albums-manage.php?album_id=<?php echo $album['id']; ?>" 
                                   class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this album?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="album_id" value="<?php echo $album['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                            No albums found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="<?php echo $page == $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

