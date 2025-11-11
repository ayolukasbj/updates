<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Newsletter Subscribers';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Ensure newsletter_subscribers table exists
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            status ENUM('active', 'unsubscribed') DEFAULT 'active',
            subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at TIMESTAMP NULL,
            source VARCHAR(100) DEFAULT 'news_details',
            INDEX idx_email (email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'unsubscribe':
                $subscriber_id = (int)($_POST['subscriber_id'] ?? 0);
                if ($subscriber_id > 0) {
                    $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?");
                    $stmt->execute([$subscriber_id]);
                    $success = 'Subscriber unsubscribed successfully';
                    logAdminActivity($_SESSION['user_id'], 'unsubscribe_newsletter', 'newsletter', $subscriber_id, "Unsubscribed newsletter subscriber");
                }
                break;
                
            case 'resubscribe':
                $subscriber_id = (int)($_POST['subscriber_id'] ?? 0);
                if ($subscriber_id > 0) {
                    $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'active', subscribed_at = NOW(), unsubscribed_at = NULL WHERE id = ?");
                    $stmt->execute([$subscriber_id]);
                    $success = 'Subscriber resubscribed successfully';
                    logAdminActivity($_SESSION['user_id'], 'resubscribe_newsletter', 'newsletter', $subscriber_id, "Resubscribed newsletter subscriber");
                }
                break;
                
            case 'delete':
                $subscriber_id = (int)($_POST['subscriber_id'] ?? 0);
                if ($subscriber_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
                    $stmt->execute([$subscriber_id]);
                    $success = 'Subscriber deleted successfully';
                    logAdminActivity($_SESSION['user_id'], 'delete_newsletter_subscriber', 'newsletter', $subscriber_id, "Deleted newsletter subscriber");
                }
                break;
                
            case 'bulk_unsubscribe':
                $subscriber_ids = $_POST['subscriber_ids'] ?? [];
                if (!empty($subscriber_ids)) {
                    $placeholders = implode(',', array_fill(0, count($subscriber_ids), '?'));
                    $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id IN ($placeholders)");
                    $stmt->execute($subscriber_ids);
                    $success = count($subscriber_ids) . ' subscribers unsubscribed successfully';
                }
                break;
                
            case 'bulk_delete':
                $subscriber_ids = $_POST['subscriber_ids'] ?? [];
                if (!empty($subscriber_ids)) {
                    $placeholders = implode(',', array_fill(0, count($subscriber_ids), '?'));
                    $stmt = $conn->prepare("DELETE FROM newsletter_subscribers WHERE id IN ($placeholders)");
                    $stmt->execute($subscriber_ids);
                    $success = count($subscriber_ids) . ' subscribers deleted successfully';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query
$where_clauses = [];
$params = [];

if ($filter === 'active') {
    $where_clauses[] = "status = 'active'";
} elseif ($filter === 'unsubscribed') {
    $where_clauses[] = "status = 'unsubscribed'";
}

if (!empty($search)) {
    $where_clauses[] = "email LIKE ?";
    $params[] = "%$search%";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get total count
try {
    $countStmt = $conn->query("SELECT COUNT(*) as count FROM newsletter_subscribers $where_sql");
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    $total_pages = ceil($total / $per_page);
} catch (Exception $e) {
    $total = 0;
    $total_pages = 0;
}

// Get subscribers
$subscribers = [];
try {
    $sql = "SELECT * FROM newsletter_subscribers $where_sql ORDER BY subscribed_at DESC LIMIT $per_page OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $statsStmt = $conn->query("SELECT status, COUNT(*) as count FROM newsletter_subscribers GROUP BY status");
    $stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
    $stats_array = ['active' => 0, 'unsubscribed' => 0];
    foreach ($stats as $stat) {
        $stats_array[$stat['status']] = (int)$stat['count'];
    }
    $stats_array['total'] = $stats_array['active'] + $stats_array['unsubscribed'];
} catch (Exception $e) {
    $subscribers = [];
    $stats_array = ['active' => 0, 'unsubscribed' => 0, 'total' => 0];
    $error = 'Error fetching subscribers: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Newsletter Subscribers</h1>
    <p>Manage newsletter subscribers</p>
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

<!-- Statistics -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 20px; background: #d1e7dd; border-radius: 6px;">
                <h3 style="margin: 0; color: #0f5132;"><?php echo number_format($stats_array['active']); ?></h3>
                <p style="margin: 5px 0 0 0; color: #0f5132;">Active Subscribers</p>
            </div>
            <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 6px;">
                <h3 style="margin: 0; color: #856404;"><?php echo number_format($stats_array['unsubscribed']); ?></h3>
                <p style="margin: 5px 0 0 0; color: #856404;">Unsubscribed</p>
            </div>
            <div style="text-align: center; padding: 20px; background: #cfe2ff; border-radius: 6px;">
                <h3 style="margin: 0; color: #084298;"><?php echo number_format($stats_array['total']); ?></h3>
                <p style="margin: 5px 0 0 0; color: #084298;">Total</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end;">
            <div style="flex: 1; min-width: 200px;">
                <label>Search Email</label>
                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by email...">
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label>Filter</label>
                <select name="filter" class="form-control">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                    <option value="unsubscribed" <?php echo $filter === 'unsubscribed' ? 'selected' : ''; ?>>Unsubscribed Only</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="newsletter-subscribers.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Subscribers Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Subscribers (<?php echo number_format($total); ?>)</h2>
        <div>
            <a href="send-newsletter.php" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Send Newsletter
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($subscribers)): ?>
        <p>No subscribers found.</p>
        <?php else: ?>
        <form method="POST" id="bulk-form">
            <div style="margin-bottom: 15px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll()">Select All</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAll()">Deselect All</button>
                <button type="submit" name="action" value="bulk_unsubscribe" class="btn btn-sm btn-warning" 
                    onclick="return confirm('Are you sure you want to unsubscribe selected subscribers?');">
                    Unsubscribe Selected
                </button>
                <button type="submit" name="action" value="bulk_delete" class="btn btn-sm btn-danger"
                    onclick="return confirm('Are you sure you want to delete selected subscribers? This cannot be undone!');">
                    Delete Selected
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="select-all-checkbox" onchange="toggleAll(this)">
                            </th>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>Subscribed</th>
                            <th>Unsubscribed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscribers as $subscriber): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="subscriber_ids[]" value="<?php echo $subscriber['id']; ?>" class="subscriber-checkbox">
                            </td>
                            <td><?php echo $subscriber['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($subscriber['email']); ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $subscriber['status'] === 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($subscriber['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($subscriber['source'] ?? 'news_details'); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($subscriber['subscribed_at'])); ?></td>
                            <td><?php echo $subscriber['unsubscribed_at'] ? date('M d, Y H:i', strtotime($subscriber['unsubscribed_at'])) : '-'; ?></td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <?php if ($subscriber['status'] === 'active'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="unsubscribe">
                                        <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" 
                                            onclick="return confirm('Unsubscribe this subscriber?');">
                                            Unsubscribe
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="resubscribe">
                                        <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            Resubscribe
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this subscriber?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 10px;">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">Previous</a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
            <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" 
               class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-secondary">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.subscriber-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.subscriber-checkbox');
    checkboxes.forEach(cb => cb.checked = true);
    document.getElementById('select-all-checkbox').checked = true;
}

function deselectAll() {
    const checkboxes = document.querySelectorAll('.subscriber-checkbox');
    checkboxes.forEach(cb => cb.checked = false);
    document.getElementById('select-all-checkbox').checked = false;
}

// Update select-all checkbox when individual checkboxes change
document.querySelectorAll('.subscriber-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        const allChecked = Array.from(document.querySelectorAll('.subscriber-checkbox')).every(c => c.checked);
        document.getElementById('select-all-checkbox').checked = allChecked;
    });
});
</script>

<?php include 'includes/footer.php'; ?>


