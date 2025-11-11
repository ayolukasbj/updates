<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Comments & Ratings Management';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'approve_comment':
                $comment_id = (int)($_POST['comment_id'] ?? 0);
                $stmt = $conn->prepare("UPDATE song_comments SET is_approved = 1 WHERE id = ?");
                $stmt->execute([$comment_id]);
                $success = 'Comment approved successfully!';
                break;
                
            case 'delete_comment':
                $comment_id = (int)($_POST['comment_id'] ?? 0);
                $stmt = $conn->prepare("DELETE FROM song_comments WHERE id = ?");
                $stmt->execute([$comment_id]);
                $success = 'Comment deleted successfully!';
                break;
                
            case 'delete_rating':
                $rating_id = (int)($_POST['rating_id'] ?? 0);
                $stmt = $conn->prepare("DELETE FROM song_ratings WHERE id = ?");
                $stmt->execute([$rating_id]);
                $success = 'Rating deleted successfully!';
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get filter parameters
$filter_song = $_GET['song_id'] ?? '';
$filter_status = $_GET['status'] ?? 'all';
$filter_rating = $_GET['min_rating'] ?? '';

// Build queries
$comments_query = "
    SELECT c.*, 
           s.title as song_title,
           s.artist as song_artist,
           COALESCE(u.username, c.username, 'Anonymous') as display_username,
           COALESCE(u.avatar, '') as user_avatar
    FROM song_comments c
    LEFT JOIN songs s ON c.song_id = s.id
    LEFT JOIN users u ON c.user_id = u.id
    WHERE 1=1
";

$ratings_query = "
    SELECT r.*,
           s.title as song_title,
           s.artist as song_artist,
           COALESCE(u.username, 'Anonymous') as display_username
    FROM song_ratings r
    LEFT JOIN songs s ON r.song_id = s.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE 1=1
";

$params = [];

if (!empty($filter_song)) {
    $comments_query .= " AND c.song_id = ?";
    $ratings_query .= " AND r.song_id = ?";
    $params[] = (int)$filter_song;
}

if ($filter_status === 'approved') {
    $comments_query .= " AND c.is_approved = 1";
} elseif ($filter_status === 'pending') {
    $comments_query .= " AND c.is_approved = 0";
}

if (!empty($filter_rating)) {
    $ratings_query .= " AND r.rating >= ?";
    $params[] = (int)$filter_rating;
}

$comments_query .= " ORDER BY c.created_at DESC";
$ratings_query .= " ORDER BY r.created_at DESC";

// Get all comments (check if table exists)
$comments = [];
try {
    $checkStmt = $conn->query("SHOW TABLES LIKE 'song_comments'");
    if ($checkStmt->rowCount() > 0) {
        $stmt = $conn->prepare($comments_query);
        $stmt->execute($params);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $comments = [];
    error_log("Comments table not found: " . $e->getMessage());
}

// Get all ratings (check if table exists)
$ratings = [];
try {
    $checkStmt = $conn->query("SHOW TABLES LIKE 'song_ratings'");
    if ($checkStmt->rowCount() > 0) {
        $rating_params = [];
        if (!empty($filter_song)) {
            $rating_params[] = (int)$filter_song;
        }
        if (!empty($filter_rating)) {
            $rating_params[] = (int)$filter_rating;
        }
        
        $rating_stmt = $conn->prepare($ratings_query);
        $rating_stmt->execute($rating_params);
        $ratings = $rating_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $ratings = [];
    error_log("Song ratings table not found: " . $e->getMessage());
}

// Get all songs for filter dropdown (check if artist column exists)
$all_songs = [];
try {
    $checkArtist = $conn->query("SHOW COLUMNS FROM songs LIKE 'artist'");
    $has_artist = $checkArtist->rowCount() > 0;
    
    if ($has_artist) {
        $songs_stmt = $conn->query("SELECT id, title, artist FROM songs ORDER BY title");
    } else {
        $songs_stmt = $conn->query("SELECT id, title FROM songs ORDER BY title");
    }
    $all_songs = $songs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_songs = [];
    error_log("Error loading songs: " . $e->getMessage());
}

// Statistics
$stats_stmt = $conn->query("
    SELECT 
        COUNT(*) as total_comments,
        SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved_comments,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending_comments
    FROM song_comments
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$rating_stats_stmt = $conn->query("
    SELECT 
        COUNT(*) as total_ratings,
        AVG(rating) as avg_rating,
        COUNT(DISTINCT song_id) as songs_rated
    FROM song_ratings
");
$rating_stats = $rating_stats_stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-comments"></i> Comments & Ratings Management</h1>
    <p>Manage user comments and ratings on songs</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo number_format($stats['total_comments'] ?? 0); ?></div>
            <div style="opacity: 0.9;">Total Comments</div>
        </div>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white;">
        <div style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo number_format($stats['approved_comments'] ?? 0); ?></div>
            <div style="opacity: 0.9;">Approved</div>
        </div>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white;">
        <div style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo number_format($stats['pending_comments'] ?? 0); ?></div>
            <div style="opacity: 0.9;">Pending</div>
        </div>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #e91e63 0%, #c2185b 100%); color: white;">
        <div style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; margin-bottom: 5px;"><?php echo number_format($rating_stats['total_ratings'] ?? 0); ?></div>
            <div style="opacity: 0.9;">Total Ratings</div>
            <div style="font-size: 14px; margin-top: 5px;">Avg: <?php echo round($rating_stats['avg_rating'] ?? 0, 1); ?>/5</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Filters</h2>
    </div>
    <div class="card-body">
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
            <div class="form-group">
                <label>Song</label>
                <select name="song_id" class="form-control">
                    <option value="">All Songs</option>
                    <?php foreach ($all_songs as $song): ?>
                    <option value="<?php echo $song['id']; ?>" <?php echo $filter_song == $song['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($song['title'] . ' - ' . $song['artist']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Comment Status</label>
                <select name="status" class="form-control">
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Min Rating</label>
                <select name="min_rating" class="form-control">
                    <option value="">All Ratings</option>
                    <option value="5" <?php echo $filter_rating == '5' ? 'selected' : ''; ?>>5 Stars</option>
                    <option value="4" <?php echo $filter_rating == '4' ? 'selected' : ''; ?>>4+ Stars</option>
                    <option value="3" <?php echo $filter_rating == '3' ? 'selected' : ''; ?>>3+ Stars</option>
                    <option value="2" <?php echo $filter_rating == '2' ? 'selected' : ''; ?>>2+ Stars</option>
                    <option value="1" <?php echo $filter_rating == '1' ? 'selected' : ''; ?>>1+ Stars</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Comments Tab -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Comments (<?php echo count($comments); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Song</th>
                        <th>User</th>
                        <th>Comment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comments)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #999; padding: 30px;">No comments found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                        <tr>
                            <td><?php echo $comment['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($comment['song_title'] ?? 'Unknown'); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($comment['song_artist'] ?? ''); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($comment['user_avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($comment['user_avatar']); ?>" alt="" style="width: 30px; height: 30px; border-radius: 50%; vertical-align: middle; margin-right: 8px;">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($comment['display_username']); ?>
                            </td>
                            <td style="max-width: 300px;">
                                <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($comment['comment']); ?>">
                                    <?php echo htmlspecialchars($comment['comment']); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($comment['is_approved']): ?>
                                <span class="badge badge-success">Approved</span>
                                <?php else: ?>
                                <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?></td>
                            <td>
                                <?php if (!$comment['is_approved']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve_comment">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                    <input type="hidden" name="action" value="delete_comment">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Ratings Tab -->
<div class="card">
    <div class="card-header">
        <h2>Ratings (<?php echo count($ratings); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Song</th>
                        <th>User</th>
                        <th>Rating</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ratings)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #999; padding: 30px;">No ratings found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($ratings as $rating): ?>
                        <tr>
                            <td><?php echo $rating['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($rating['song_title'] ?? 'Unknown'); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($rating['song_artist'] ?? ''); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($rating['display_username']); ?></td>
                            <td>
                                <div style="color: #ffd700; font-size: 18px;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $rating['rating']): ?>
                                    ★
                                    <?php else: ?>
                                    ☆
                                    <?php endif; ?>
                                    <?php endfor; ?>
                                    <span style="color: #333; font-size: 14px; margin-left: 8px;"><?php echo $rating['rating']; ?>/5</span>
                                </div>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($rating['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this rating?');">
                                    <input type="hidden" name="action" value="delete_rating">
                                    <input type="hidden" name="rating_id" value="<?php echo $rating['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
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

