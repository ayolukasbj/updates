<?php
// Start output buffering to prevent WSOD
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Initialize variables
$page_title = 'News Management';
$success = '';
$error = '';
$db = null;
$conn = null;
$news_items = [];
$categories = [];
$total_news = 0;
$total_pages = 0;
$has_submitted_by = false;
$page = 1;
$per_page = 20;
$offset = 0;
$search = '';
$category_filter = '';
$status_filter = '';

try {
    // Require files
    if (!file_exists('auth-check.php')) {
        throw new Exception('auth-check.php not found');
    }
    require_once 'auth-check.php';
    
    if (!file_exists('../config/database.php')) {
        throw new Exception('database.php not found');
    }
    require_once '../config/database.php';

    // Initialize database
    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed. Please check your database configuration.');
    }
} catch (Exception $e) {
    ob_clean();
    die('Error initializing: ' . htmlspecialchars($e->getMessage()));
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $news_id = (int)($_POST['news_id'] ?? 0);
        
        if ($action === 'delete' && $news_id > 0) {
            $stmt = $conn->prepare("DELETE FROM news WHERE id = ?");
            if ($stmt->execute([$news_id])) {
                $success = 'News article deleted successfully';
                if (function_exists('logAdminActivity')) {
                    logAdminActivity($_SESSION['user_id'] ?? 0, 'delete_news', 'news', $news_id, "Deleted news ID: $news_id");
                }
            }
        } elseif ($action === 'toggle_featured' && $news_id > 0) {
            $stmt = $conn->prepare("UPDATE news SET featured = NOT featured WHERE id = ?");
            if ($stmt->execute([$news_id])) {
                $success = 'News featured status updated';
                if (function_exists('logAdminActivity')) {
                    logAdminActivity($_SESSION['user_id'] ?? 0, 'toggle_news_featured', 'news', $news_id, "Toggled featured status");
                }
            }
        } elseif ($action === 'toggle_published' && $news_id > 0) {
            $checkStmt = $conn->prepare("SELECT is_published FROM news WHERE id = ?");
            $checkStmt->execute([$news_id]);
            $current_news = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current_news) {
                if (($current_news['is_published'] ?? 0) == 0) {
                    $stmt = $conn->prepare("UPDATE news SET is_published = 1, author_id = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'] ?? 0, $news_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE news SET is_published = 0 WHERE id = ?");
                    $stmt->execute([$news_id]);
                }
                
                if ($stmt->rowCount() > 0) {
                    $success = 'News publish status updated';
                    if (function_exists('logAdminActivity')) {
                        logAdminActivity($_SESSION['user_id'] ?? 0, 'toggle_news_published', 'news', $news_id, "Toggled published status");
                    }
                }
            }
        } elseif ($action === 'approve' && $news_id > 0) {
            $stmt = $conn->prepare("UPDATE news SET is_published = 1, author_id = ? WHERE id = ?");
            if ($stmt->execute([$_SESSION['user_id'] ?? 0, $news_id])) {
                $success = 'News article approved and published';
                if (function_exists('logAdminActivity')) {
                    logAdminActivity($_SESSION['user_id'] ?? 0, 'approve_news', 'news', $news_id, "Approved news ID: $news_id");
                }
            }
        } elseif ($action === 'reject' && $news_id > 0) {
            $rejection_reason = $_POST['rejection_reason'] ?? 'No reason provided';
            $stmt = $conn->prepare("UPDATE news SET is_published = 0, rejection_reason = ? WHERE id = ?");
            if ($stmt->execute([$rejection_reason, $news_id])) {
                $success = 'News article rejected';
                if (function_exists('logAdminActivity')) {
                    logAdminActivity($_SESSION['user_id'] ?? 0, 'reject_news', 'news', $news_id, "Rejected news ID: $news_id");
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("News action error: " . $e->getMessage());
    }
}

// Pagination and filters
try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    $search = trim($_GET['search'] ?? '');
    $category_filter = trim($_GET['category'] ?? '');
    $status_filter = trim($_GET['status'] ?? '');
} catch (Exception $e) {
    error_log("Filter error: " . $e->getMessage());
}

// Get news from Database
try {
    // Check if submitted_by column exists
    try {
        $columns_check = $conn->query("SHOW COLUMNS FROM news LIKE 'submitted_by'");
        $has_submitted_by = $columns_check->rowCount() > 0;
    } catch (Exception $e) {
        $has_submitted_by = false;
    }
    
    // Build query
    $where_clauses = [];
    $params = [];
    
    if (!empty($search)) {
        $where_clauses[] = "(title LIKE ? OR content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($category_filter)) {
        $where_clauses[] = "category = ?";
        $params[] = $category_filter;
    }
    
    if ($status_filter === 'published') {
        $where_clauses[] = "is_published = 1";
    } elseif ($status_filter === 'draft') {
        $where_clauses[] = "is_published = 0";
    } elseif ($status_filter === 'pending' && $has_submitted_by) {
        $where_clauses[] = "is_published = 0 AND submitted_by IS NOT NULL";
    }
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    // Get count
    $count_sql = "SELECT COUNT(*) as count FROM news $where_sql";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_news = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    $total_pages = $total_news > 0 ? ceil($total_news / $per_page) : 0;
    
    // Get news
    if ($has_submitted_by) {
        $sql = "SELECT n.*, u.username as submitted_by_username 
                FROM news n 
                LEFT JOIN users u ON n.submitted_by = u.id 
                $where_sql 
                ORDER BY n.created_at DESC 
                LIMIT $per_page OFFSET $offset";
    } else {
        $sql = "SELECT n.*, NULL as submitted_by_username 
                FROM news n 
                $where_sql 
                ORDER BY n.created_at DESC 
                LIMIT $per_page OFFSET $offset";
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $news_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories
    $stmt = $conn->query("SELECT DISTINCT category FROM news WHERE category IS NOT NULL ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("News query error: " . $e->getMessage());
    $news_items = [];
    $categories = [];
    $total_news = 0;
    $total_pages = 0;
    $has_submitted_by = false;
}

// Include header
try {
    if (file_exists('includes/header.php')) {
        include 'includes/header.php';
    } else {
        throw new Exception('Header file not found');
    }
} catch (Exception $e) {
    ob_clean();
    die('Error loading header: ' . htmlspecialchars($e->getMessage()));
}
?>

<div class="page-header">
    <h1>News Management</h1>
    <p>Manage news articles and announcements</p>
</div>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

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
                <input type="text" name="search" class="form-control" placeholder="Search news..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Category</label>
                <select name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <?php if ($has_submitted_by): ?>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="news.php" class="btn btn-warning">
                <i class="fas fa-redo"></i> Reset
            </a>
            
            <a href="news-edit.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add New
            </a>
        </form>
    </div>
</div>

<!-- News Table -->
<div class="card">
    <div class="card-header">
        <h2>All News Articles (<?php echo number_format($total_news); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Views</th>
                        <th>Status</th>
                        <th>Featured</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($news_items)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                            No news articles found.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($news_items as $news): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($news['id'] ?? 'N/A'); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($news['title'] ?? 'Untitled'); ?></strong>
                            <?php if ($has_submitted_by && !empty($news['submitted_by_username'])): ?>
                            <br><small style="color: #666;">Submitted by: <?php echo htmlspecialchars($news['submitted_by_username']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($news['category'])): ?>
                            <span class="badge badge-info"><?php echo htmlspecialchars($news['category']); ?></span>
                            <?php else: ?>
                            <span style="color: #999;">Uncategorized</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($news['views'] ?? 0); ?></td>
                        <td>
                            <?php if ($has_submitted_by && !empty($news['submitted_by']) && empty($news['is_published'])): ?>
                                <form method="POST" style="display: inline; margin-right: 5px;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="news_id" value="<?php echo $news['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Approve and publish">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-danger" onclick="openRejectModal(<?php echo $news['id']; ?>)" title="Reject">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_published">
                                    <input type="hidden" name="news_id" value="<?php echo $news['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo (!empty($news['is_published'])) ? 'btn-success' : 'btn-secondary'; ?>">
                                        <i class="fas fa-<?php echo (!empty($news['is_published'])) ? 'check' : 'times'; ?>"></i>
                                        <?php echo (!empty($news['is_published'])) ? 'Published' : 'Draft'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="news_id" value="<?php echo $news['id']; ?>">
                                <button type="submit" class="btn btn-sm <?php echo (!empty($news['featured'])) ? 'btn-warning' : 'btn-secondary'; ?>">
                                    <i class="fas fa-star"></i>
                                    <?php echo (!empty($news['featured'])) ? 'Featured' : 'Not Featured'; ?>
                                </button>
                            </form>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($news['created_at'] ?? $news['published_at'] ?? 'now')); ?></td>
                        <td>
                            <a href="news-edit.php?id=<?php echo $news['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this news article?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="news_id" value="<?php echo $news['id']; ?>">
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
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div style="margin-top: 20px; text-align: center;">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn btn-secondary">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <span style="margin: 0 15px;">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </span>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn btn-secondary">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
        <h3>Reject News Article</h3>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="news_id" id="rejectNewsId">
            <div class="form-group">
                <label>Rejection Reason</label>
                <textarea name="rejection_reason" class="form-control" rows="4" required></textarea>
            </div>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-danger">Reject</button>
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(newsId) {
    document.getElementById('rejectNewsId').value = newsId;
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
    document.getElementById('rejectForm').reset();
}

document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});
</script>

<?php 
try {
    if (file_exists('includes/footer.php')) {
        include 'includes/footer.php';
    }
} catch (Exception $e) {
    error_log("Footer include error: " . $e->getMessage());
}
ob_end_flush();
?>












