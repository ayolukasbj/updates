<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Email Queue';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Create email_queue table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS email_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            body TEXT NOT NULL,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            attempts INT DEFAULT 0,
            error_message TEXT,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created (created_at)
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
            case 'retry':
                $queue_id = (int)($_POST['queue_id'] ?? 0);
                if ($queue_id > 0) {
                    $stmt = $conn->prepare("UPDATE email_queue SET status = 'pending', attempts = 0, error_message = NULL WHERE id = ?");
                    $stmt->execute([$queue_id]);
                    $success = 'Email queued for retry';
                }
                break;
                
            case 'delete':
                $queue_id = (int)($_POST['queue_id'] ?? 0);
                if ($queue_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM email_queue WHERE id = ?");
                    $stmt->execute([$queue_id]);
                    $success = 'Email deleted from queue';
                }
                break;
                
            case 'clear_sent':
                $stmt = $conn->prepare("DELETE FROM email_queue WHERE status = 'sent'");
                $stmt->execute();
                $success = 'Cleared all sent emails from queue';
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$where_clause = '';
if ($filter === 'pending') {
    $where_clause = "WHERE status = 'pending'";
} elseif ($filter === 'sent') {
    $where_clause = "WHERE status = 'sent'";
} elseif ($filter === 'failed') {
    $where_clause = "WHERE status = 'failed'";
}

// Get email queue
try {
    $stmt = $conn->query("SELECT * FROM email_queue $where_clause ORDER BY created_at DESC LIMIT 100");
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $statsStmt = $conn->query("SELECT status, COUNT(*) as count FROM email_queue GROUP BY status");
    $stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
    $stats_array = ['pending' => 0, 'sent' => 0, 'failed' => 0];
    foreach ($stats as $stat) {
        $stats_array[$stat['status']] = (int)$stat['count'];
    }
} catch (Exception $e) {
    $emails = [];
    $stats_array = ['pending' => 0, 'sent' => 0, 'failed' => 0];
    $error = 'Error fetching queue: ' . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Email Queue</h1>
    <p>View and manage queued emails</p>
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
            <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 6px;">
                <h3 style="margin: 0; color: #856404;"><?php echo $stats_array['pending']; ?></h3>
                <p style="margin: 5px 0 0 0; color: #856404;">Pending</p>
            </div>
            <div style="text-align: center; padding: 20px; background: #d1e7dd; border-radius: 6px;">
                <h3 style="margin: 0; color: #0f5132;"><?php echo $stats_array['sent']; ?></h3>
                <p style="margin: 5px 0 0 0; color: #0f5132;">Sent</p>
            </div>
            <div style="text-align: center; padding: 20px; background: #f8d7da; border-radius: 6px;">
                <h3 style="margin: 0; color: #842029;"><?php echo $stats_array['failed']; ?></h3>
                <p style="margin: 5px 0 0 0; color: #842029;">Failed</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-body">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
            <a href="?filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">Pending (<?php echo $stats_array['pending']; ?>)</a>
            <a href="?filter=sent" class="btn <?php echo $filter === 'sent' ? 'btn-primary' : 'btn-secondary'; ?>">Sent (<?php echo $stats_array['sent']; ?>)</a>
            <a href="?filter=failed" class="btn <?php echo $filter === 'failed' ? 'btn-primary' : 'btn-secondary'; ?>">Failed (<?php echo $stats_array['failed']; ?>)</a>
        </div>
        <?php if ($filter === 'sent'): ?>
        <div style="margin-top: 15px;">
            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all sent emails?');">
                <input type="hidden" name="action" value="clear_sent">
                <button type="submit" class="btn btn-sm btn-danger">Clear All Sent</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Email Queue Table -->
<div class="card">
    <div class="card-header">
        <h2>Email Queue</h2>
    </div>
    <div class="card-body">
        <?php if (empty($emails)): ?>
        <p>No emails in queue.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Created</th>
                        <th>Sent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emails as $email): ?>
                    <tr>
                        <td><?php echo $email['id']; ?></td>
                        <td><?php echo htmlspecialchars($email['to_email']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars(substr($email['subject'], 0, 50)); ?></strong>
                            <?php if (!empty($email['error_message'])): ?>
                            <br><small style="color: #dc3545;">Error: <?php echo htmlspecialchars(substr($email['error_message'], 0, 100)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $email['status'] === 'sent' ? 'success' : 
                                    ($email['status'] === 'failed' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($email['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $email['attempts']; ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($email['created_at'])); ?></td>
                        <td><?php echo $email['sent_at'] ? date('Y-m-d H:i', strtotime($email['sent_at'])) : '-'; ?></td>
                        <td>
                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                <?php if ($email['status'] === 'failed'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="retry">
                                    <input type="hidden" name="queue_id" value="<?php echo $email['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning">Retry</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="queue_id" value="<?php echo $email['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


