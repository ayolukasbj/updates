<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'User Management';

$db = new Database();
$conn = $db->getConnection();

// Handle actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($action === 'ban') {
        $reason = $_POST['reason'] ?? 'Violated terms of service';
        $stmt = $conn->prepare("UPDATE users SET is_banned = 1, banned_reason = ? WHERE id = ?");
        if ($stmt->execute([$reason, $user_id])) {
            $success = 'User has been banned successfully';
            logAdminActivity($_SESSION['user_id'], 'ban_user', 'user', $user_id, "Banned user ID: $user_id");
        }
    } elseif ($action === 'unban') {
        $stmt = $conn->prepare("UPDATE users SET is_banned = 0, banned_reason = NULL WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success = 'User has been unbanned successfully';
            logAdminActivity($_SESSION['user_id'], 'unban_user', 'user', $user_id, "Unbanned user ID: $user_id");
        }
    } elseif ($action === 'delete' && isSuperAdmin()) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'super_admin'");
        if ($stmt->execute([$user_id])) {
            $success = 'User has been deleted successfully';
            logAdminActivity($_SESSION['user_id'], 'delete_user', 'user', $user_id, "Deleted user ID: $user_id");
        }
    } elseif ($action === 'update_role' && isSuperAdmin()) {
        $new_role = $_POST['role'] ?? 'user';
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        if ($stmt->execute([$new_role, $user_id])) {
            $success = 'User role updated successfully';
            logAdminActivity($_SESSION['user_id'], 'update_user_role', 'user', $user_id, "Changed role to: $new_role");
        }
    }
}

// Pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search filter
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter === 'banned') {
    $where_clauses[] = "is_banned = 1";
} elseif ($status_filter === 'active') {
    $where_clauses[] = "is_banned = 0 AND is_active = 1";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as count FROM users $where_sql";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
$total_pages = ceil($total_users / $per_page);

// Get users
$sql = "SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="page-header">
    <h1>User Management</h1>
    <p>Manage all registered users on the platform</p>
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

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                <label>Search</label>
                <input type="text" name="search" class="form-control" placeholder="Username or email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="">All Roles</option>
                    <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="artist" <?php echo $role_filter === 'artist' ? 'selected' : ''; ?>>Artist</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <a href="users.php" class="btn btn-warning">
                <i class="fas fa-redo"></i> Reset
            </a>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h2>All Users (<?php echo number_format($total_users); ?>)</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Email Verified</th>
                        <th>Joined</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <?php if (isSuperAdmin()): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_role">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="role" class="form-control" style="width: auto; display: inline; padding: 4px 8px;" onchange="this.form.submit()">
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="artist" <?php echo $user['role'] === 'artist' ? 'selected' : ''; ?>>Artist</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <?php if ($user['role'] === 'super_admin'): ?>
                                    <option value="super_admin" selected>Super Admin</option>
                                    <?php endif; ?>
                                </select>
                            </form>
                            <?php else: ?>
                            <span class="badge badge-info"><?php echo ucfirst($user['role']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $is_banned = isset($user['is_banned']) ? $user['is_banned'] : (isset($user['status']) && $user['status'] === 'banned');
                            $is_active = isset($user['is_active']) ? $user['is_active'] : (isset($user['status']) && $user['status'] === 'active');
                            
                            if ($is_banned): ?>
                                <span class="badge badge-danger">Banned</span>
                            <?php elseif (!$is_active): ?>
                                <span class="badge badge-warning">Inactive</span>
                            <?php else: ?>
                                <span class="badge badge-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (($user['email_verified'] ?? 0)): ?>
                                <span class="badge badge-success" title="Email verified">
                                    <i class="fas fa-check-circle"></i> Verified
                                </span>
                            <?php else: ?>
                                <span class="badge badge-warning" title="Email not verified - click Edit to manually verify">
                                    <i class="fas fa-exclamation-circle"></i> Not Verified
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td><?php echo (isset($user['last_login']) && !empty($user['last_login'])) ? date('M d, Y', strtotime($user['last_login'])) : 'Never'; ?></td>
                        <td>
                            <?php if ($user['role'] !== 'super_admin'): ?>
                                <a href="user-edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <a href="login-as-user.php?user_id=<?php echo $user['id']; ?>" 
                                   class="btn btn-info btn-sm" 
                                   title="Login as User"
                                   onclick="return confirm('Are you sure you want to login as <?php echo htmlspecialchars($user['username']); ?>? You can switch back from the dashboard.')">
                                    <i class="fas fa-user-secret"></i>
                                </a>
                                
                                <?php if ($user['is_banned']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="unban">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm" title="Unban User">
                                        <i class="fas fa-unlock"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-warning btn-sm" 
                                        onclick="showBanModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                        title="Ban User">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if (isSuperAdmin()): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete User">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
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
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>" 
               class="<?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Ban Modal -->
<div id="banModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%;">
        <h2 style="margin-bottom: 20px;">Ban User</h2>
        <form method="POST">
            <input type="hidden" name="action" value="ban">
            <input type="hidden" name="user_id" id="banUserId">
            
            <p style="margin-bottom: 15px;">Are you sure you want to ban <strong id="banUsername"></strong>?</p>
            
            <div class="form-group">
                <label>Reason</label>
                <textarea name="reason" class="form-control" required placeholder="Enter ban reason..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-warning" onclick="hideBanModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Ban User</button>
            </div>
        </form>
    </div>
</div>

<script>
function showBanModal(userId, username) {
    document.getElementById('banUserId').value = userId;
    document.getElementById('banUsername').textContent = username;
    document.getElementById('banModal').style.display = 'flex';
}

function hideBanModal() {
    document.getElementById('banModal').style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>

