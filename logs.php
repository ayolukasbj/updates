<?php
// license-server/logs.php
// License Verification Logs

session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';

requireLogin();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $log_id = (int)($_POST['log_id'] ?? 0);
        if ($log_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM license_logs WHERE id = ?");
                $stmt->execute([$log_id]);
                $success = 'Log deleted successfully!';
            } catch (Exception $e) {
                $error = 'Error deleting log: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'delete_selected') {
        $selected_ids = $_POST['selected_logs'] ?? [];
        if (!empty($selected_ids) && is_array($selected_ids)) {
            try {
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                $stmt = $conn->prepare("DELETE FROM license_logs WHERE id IN ($placeholders)");
                $stmt->execute($selected_ids);
                $success = count($selected_ids) . ' log(s) deleted successfully!';
            } catch (Exception $e) {
                $error = 'Error deleting logs: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'delete_verifications') {
        try {
            $stmt = $conn->prepare("DELETE FROM license_logs WHERE action = 'verify'");
            $stmt->execute();
            $deleted_count = $stmt->rowCount();
            $success = "$deleted_count verification log(s) deleted successfully!";
        } catch (Exception $e) {
            $error = 'Error deleting verifications: ' . $e->getMessage();
        }
    }
}

$db = new Database();
$conn = $db->getConnection();

// Filters
$search = trim($_GET['search'] ?? '');
$action_filter = $_GET['action'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Build query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(license_key LIKE ? OR domain LIKE ? OR ip_address LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($action_filter !== 'all') {
    $where[] = "action = ?";
    $params[] = $action_filter;
}

if ($status_filter !== 'all') {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get total count
try {
    $countSql = "SELECT COUNT(*) as count FROM license_logs $where_sql";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    $total_pages = ceil($total / $per_page);
} catch (Exception $e) {
    $total = 0;
    $total_pages = 0;
}

// Get logs
$logs = [];
try {
    $sql = "SELECT * FROM license_logs $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Logs</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .header { background: #1f2937; color: white; padding: 15px 20px; }
        .header-content { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .header h1 { font-size: 20px; }
        .nav { background: white; padding: 12px 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto; }
        .nav-content { max-width: 1200px; margin: 0 auto; display: flex; gap: 15px; flex-wrap: wrap; }
        .nav a { color: #333; text-decoration: none; padding: 8px 12px; border-radius: 4px; white-space: nowrap; font-size: 14px; }
        .nav a.active { background: #3b82f6; color: white; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filters input, .filters select { padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px; }
        .btn { padding: 8px 16px; background: #3b82f6; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; display: inline-block; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table th, table td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        table th { background: #f9fafb; font-weight: 600; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: center; flex-wrap: wrap; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header-content { flex-direction: column; align-items: flex-start; gap: 10px; }
            .header h1 { font-size: 18px; }
            .nav-content { gap: 10px; }
            .nav a { padding: 6px 10px; font-size: 13px; }
            .container { padding: 0 10px; }
            .card { padding: 15px; }
            .filters { flex-direction: column; }
            .filters input, .filters select { width: 100%; }
            table { font-size: 12px; display: block; overflow-x: auto; white-space: nowrap; }
            table th, table td { padding: 8px; }
        }
        
        @media (max-width: 480px) {
            .header h1 { font-size: 16px; }
            table { font-size: 11px; }
        }
        .log-checkbox { width: auto; margin: 0; }
    </style>
    <script>
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.log-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('.log-checkbox');
            checkboxes.forEach(cb => cb.checked = true);
            document.getElementById('selectAll').checked = true;
        }
        
        function selectVerifications() {
            const checkboxes = document.querySelectorAll('.log-checkbox');
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                const actionCell = row.querySelector('td:nth-child(5)');
                if (actionCell && actionCell.textContent.trim().toLowerCase() === 'verify') {
                    cb.checked = true;
                } else {
                    cb.checked = false;
                }
            });
            document.getElementById('selectAll').checked = false;
        }
    </script>
<?php require_once 'includes/header.php'; ?>
    
    <div class="container">
        <div class="card">
            <h2 style="margin-bottom: 20px;">License Logs (<?php echo number_format($total); ?>)</h2>
            
            <form method="GET" class="filters">
                <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="action">
                    <option value="all" <?php echo $action_filter === 'all' ? 'selected' : ''; ?>>All Actions</option>
                    <option value="verify" <?php echo $action_filter === 'verify' ? 'selected' : ''; ?>>Verify</option>
                    <option value="create" <?php echo $action_filter === 'create' ? 'selected' : ''; ?>>Create</option>
                    <option value="activate" <?php echo $action_filter === 'activate' ? 'selected' : ''; ?>>Activate</option>
                </select>
                <select name="status">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="success" <?php echo $status_filter === 'success' ? 'selected' : ''; ?>>Success</option>
                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
                <button type="submit" class="btn">Filter</button>
                <a href="logs.php" class="btn" style="background: #6b7280;">Reset</a>
            </form>
            
            <?php if (isset($error)): ?>
            <div style="padding: 15px; background: #fee2e2; color: #991b1b; border-radius: 6px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if (empty($logs)): ?>
            <p style="text-align: center; padding: 30px; color: #999;">No logs found</p>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>License Key</th>
                            <th>Action</th>
                            <th>Domain</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td><code style="font-size: 11px;"><?php echo htmlspecialchars(substr($log['license_key'] ?? '', 0, 20)) . '...'; ?></code></td>
                            <td><?php echo ucfirst($log['action'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['domain'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $log['status'] === 'success' ? 'success' : 'failed'; ?>">
                                    <?php echo ucfirst($log['status'] ?? 'unknown'); ?>
                                </span>
                            </td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php echo htmlspecialchars($log['message'] ?? ''); ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo $action_filter; ?>&status=<?php echo $status_filter; ?>" class="btn">Previous</a>
                <?php endif; ?>
                
                <span style="padding: 8px 16px;">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo $action_filter; ?>&status=<?php echo $status_filter; ?>" class="btn">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

