<?php
require_once 'auth-check.php';
require_once '../config/database.php';

$page_title = 'Opinion Polls Management';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Create polls table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS polls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question VARCHAR(500) NOT NULL,
            description TEXT,
            status ENUM('active', 'inactive', 'closed') DEFAULT 'active',
            allow_multiple BOOLEAN DEFAULT FALSE,
            start_date DATETIME,
            end_date DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_dates (start_date, end_date)
        )
    ");
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS poll_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            poll_id INT NOT NULL,
            option_text VARCHAR(255) NOT NULL,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
            INDEX idx_poll (poll_id)
        )
    ");
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS poll_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            poll_id INT NOT NULL,
            option_id INT NOT NULL,
            user_id INT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
            FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_vote (poll_id, option_id, user_id),
            INDEX idx_poll (poll_id),
            INDEX idx_option (option_id)
        )
    ");
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_poll':
                $question = trim($_POST['question'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $status = trim($_POST['status'] ?? 'active');
                $allow_multiple = isset($_POST['allow_multiple']) ? 1 : 0;
                $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
                $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
                $options = $_POST['options'] ?? [];
                
                if (empty($question)) {
                    $error = 'Question is required!';
                    break;
                }
                
                if (count($options) < 2) {
                    $error = 'At least 2 options are required!';
                    break;
                }
                
                $stmt = $conn->prepare("INSERT INTO polls (question, description, status, allow_multiple, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$question, $description, $status, $allow_multiple, $start_date, $end_date]);
                $poll_id = $conn->lastInsertId();
                
                // Insert options
                $optionStmt = $conn->prepare("INSERT INTO poll_options (poll_id, option_text, display_order) VALUES (?, ?, ?)");
                foreach ($options as $index => $option_text) {
                    if (!empty(trim($option_text))) {
                        $optionStmt->execute([$poll_id, trim($option_text), $index]);
                    }
                }
                
                $success = 'Poll created successfully!';
                logAdminActivity($_SESSION['user_id'], 'create_poll', 'poll', $poll_id, "Created poll: $question");
                break;
                
            case 'update_poll':
                $poll_id = (int)($_POST['poll_id'] ?? 0);
                $question = trim($_POST['question'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $status = trim($_POST['status'] ?? 'active');
                $allow_multiple = isset($_POST['allow_multiple']) ? 1 : 0;
                $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
                $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
                
                if (empty($question)) {
                    $error = 'Question is required!';
                    break;
                }
                
                $stmt = $conn->prepare("UPDATE polls SET question = ?, description = ?, status = ?, allow_multiple = ?, start_date = ?, end_date = ? WHERE id = ?");
                $stmt->execute([$question, $description, $status, $allow_multiple, $start_date, $end_date, $poll_id]);
                
                // Update options
                if (isset($_POST['options'])) {
                    $options = $_POST['options'];
                    $existingOptions = [];
                    $optionStmt = $conn->prepare("SELECT id FROM poll_options WHERE poll_id = ? ORDER BY display_order");
                    $optionStmt->execute([$poll_id]);
                    $existingOptions = $optionStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Update existing options
                    foreach ($options as $index => $option_data) {
                        if (is_array($option_data)) {
                            $option_id = (int)($option_data['id'] ?? 0);
                            $option_text = trim($option_data['text'] ?? '');
                            
                            if ($option_id > 0 && !empty($option_text)) {
                                $updateStmt = $conn->prepare("UPDATE poll_options SET option_text = ?, display_order = ? WHERE id = ?");
                                $updateStmt->execute([$option_text, $index, $option_id]);
                            } else if (!empty($option_text)) {
                                $insertStmt = $conn->prepare("INSERT INTO poll_options (poll_id, option_text, display_order) VALUES (?, ?, ?)");
                                $insertStmt->execute([$poll_id, $option_text, $index]);
                            }
                        } else if (!empty(trim($option_data))) {
                            $insertStmt = $conn->prepare("INSERT INTO poll_options (poll_id, option_text, display_order) VALUES (?, ?, ?)");
                            $insertStmt->execute([$poll_id, trim($option_data), $index]);
                        }
                    }
                }
                
                $success = 'Poll updated successfully!';
                logAdminActivity($_SESSION['user_id'], 'update_poll', 'poll', $poll_id, "Updated poll ID: $poll_id");
                break;
                
            case 'delete_poll':
                $poll_id = (int)($_POST['poll_id'] ?? 0);
                if ($poll_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM polls WHERE id = ?");
                    $stmt->execute([$poll_id]);
                    $success = 'Poll deleted successfully!';
                    logAdminActivity($_SESSION['user_id'], 'delete_poll', 'poll', $poll_id, "Deleted poll ID: $poll_id");
                }
                break;
                
            case 'toggle_status':
                $poll_id = (int)($_POST['poll_id'] ?? 0);
                $new_status = $_POST['new_status'] ?? 'active';
                if ($poll_id > 0) {
                    $stmt = $conn->prepare("UPDATE polls SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $poll_id]);
                    $success = 'Poll status updated!';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all polls
try {
    $stmt = $conn->query("SELECT * FROM polls ORDER BY created_at DESC");
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get options and vote counts for each poll
    foreach ($polls as &$poll) {
        $optionStmt = $conn->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY display_order");
        $optionStmt->execute([$poll['id']]);
        $poll['options'] = $optionStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total votes
        $voteStmt = $conn->prepare("SELECT COUNT(*) as total FROM poll_votes WHERE poll_id = ?");
        $voteStmt->execute([$poll['id']]);
        $poll['total_votes'] = $voteStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Get votes per option
        foreach ($poll['options'] as &$option) {
            $voteCountStmt = $conn->prepare("SELECT COUNT(*) as count FROM poll_votes WHERE option_id = ?");
            $voteCountStmt->execute([$option['id']]);
            $option['vote_count'] = $voteCountStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            $option['percentage'] = $poll['total_votes'] > 0 ? round(($option['vote_count'] / $poll['total_votes']) * 100, 1) : 0;
        }
    }
} catch (Exception $e) {
    $polls = [];
    $error = 'Error fetching polls: ' . $e->getMessage();
}

// Get poll to edit
$edit_poll = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($polls as $poll) {
        if ($poll['id'] == $edit_id) {
            $edit_poll = $poll;
            break;
        }
    }
}

// Get poll answers to view
$view_answers_poll = null;
$poll_votes_details = [];
if (isset($_GET['view_answers'])) {
    $view_id = (int)$_GET['view_answers'];
    try {
        $pollStmt = $conn->prepare("SELECT * FROM polls WHERE id = ?");
        $pollStmt->execute([$view_id]);
        $view_answers_poll = $pollStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($view_answers_poll) {
            // Get poll options
            $optionStmt = $conn->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY display_order");
            $optionStmt->execute([$view_id]);
            $view_answers_poll['options'] = $optionStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all votes with user details
            $votesStmt = $conn->prepare("
                SELECT pv.*, 
                       u.username, 
                       u.email,
                       po.option_text
                FROM poll_votes pv
                LEFT JOIN users u ON pv.user_id = u.id
                LEFT JOIN poll_options po ON pv.option_id = po.id
                WHERE pv.poll_id = ?
                ORDER BY pv.created_at DESC
            ");
            $votesStmt->execute([$view_id]);
            $poll_votes_details = $votesStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $error = 'Error fetching poll answers: ' . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1>Opinion Polls Management</h1>
    <p>Create and manage opinion polls for your website</p>
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

<?php if ($view_answers_poll): ?>
<!-- View Poll Answers -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Poll Answers - <?php echo htmlspecialchars($view_answers_poll['question']); ?></h2>
    </div>
    <div class="card-body">
        <div style="margin-bottom: 20px;">
            <a href="polls.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Polls
            </a>
        </div>
        
        <div style="margin-bottom: 30px; padding: 20px; background: #f9fafb; border-radius: 6px;">
            <h3 style="margin-bottom: 10px;">Poll Statistics</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <strong>Total Votes:</strong> <?php echo count($poll_votes_details); ?>
                </div>
                <div>
                    <strong>Status:</strong> 
                    <span class="badge badge-<?php echo $view_answers_poll['status'] === 'active' ? 'success' : ($view_answers_poll['status'] === 'closed' ? 'danger' : 'secondary'); ?>">
                        <?php echo ucfirst($view_answers_poll['status']); ?>
                    </span>
                </div>
                <div>
                    <strong>Options:</strong> <?php echo count($view_answers_poll['options'] ?? []); ?>
                </div>
            </div>
        </div>
        
        <h3 style="margin-bottom: 15px;">Votes by Option</h3>
        <div style="margin-bottom: 30px;">
            <?php 
            $optionVoteCounts = [];
            foreach ($poll_votes_details as $vote) {
                $option_id = $vote['option_id'];
                if (!isset($optionVoteCounts[$option_id])) {
                    $optionVoteCounts[$option_id] = [
                        'option_text' => $vote['option_text'],
                        'count' => 0,
                        'votes' => []
                    ];
                }
                $optionVoteCounts[$option_id]['count']++;
                $optionVoteCounts[$option_id]['votes'][] = $vote;
            }
            
            foreach ($view_answers_poll['options'] as $option): 
                $option_id = $option['id'];
                $voteCount = $optionVoteCounts[$option_id]['count'] ?? 0;
                $totalVotes = count($poll_votes_details);
                $percentage = $totalVotes > 0 ? round(($voteCount / $totalVotes) * 100, 1) : 0;
            ?>
            <div style="margin-bottom: 20px; padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 6px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong><?php echo htmlspecialchars($option['option_text']); ?></strong>
                    <span style="color: #667eea; font-weight: 600;"><?php echo $voteCount; ?> votes (<?php echo $percentage; ?>%)</span>
                </div>
                <div style="height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                    <div style="height: 100%; background: linear-gradient(135deg, #667eea, #764ba2); width: <?php echo $percentage; ?>%; transition: width 0.3s;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <h3 style="margin-bottom: 15px;">Individual Votes</h3>
        <?php if (empty($poll_votes_details)): ?>
        <p style="color: #999; text-align: center; padding: 40px;">No votes recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                        <th style="padding: 12px; text-align: left; font-weight: 600;">User</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Email</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Selected Option</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">IP Address</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($poll_votes_details as $vote): ?>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px;">
                            <?php if (!empty($vote['username'])): ?>
                                <?php echo htmlspecialchars($vote['username']); ?>
                            <?php else: ?>
                                <span style="color: #999;">Anonymous</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px;">
                            <?php if (!empty($vote['email'])): ?>
                                <?php echo htmlspecialchars($vote['email']); ?>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px;">
                            <strong><?php echo htmlspecialchars($vote['option_text'] ?? 'Unknown'); ?></strong>
                        </td>
                        <td style="padding: 12px;">
                            <?php echo htmlspecialchars($vote['ip_address'] ?? 'N/A'); ?>
                        </td>
                        <td style="padding: 12px;">
                            <?php echo date('M d, Y H:i', strtotime($vote['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($edit_poll): ?>
<!-- Edit Poll Form -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Edit Poll</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_poll">
            <input type="hidden" name="poll_id" value="<?php echo $edit_poll['id']; ?>">
            
            <div class="form-group">
                <label>Question <span style="color: red;">*</span></label>
                <input type="text" name="question" class="form-control" value="<?php echo htmlspecialchars($edit_poll['question']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($edit_poll['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?php echo $edit_poll['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $edit_poll['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="closed" <?php echo $edit_poll['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="allow_multiple" value="1" <?php echo $edit_poll['allow_multiple'] ? 'checked' : ''; ?>>
                    Allow Multiple Selections
                </label>
            </div>
            
            <div class="form-group">
                <label>Start Date</label>
                <input type="datetime-local" name="start_date" class="form-control" value="<?php echo $edit_poll['start_date'] ? date('Y-m-d\TH:i', strtotime($edit_poll['start_date'])) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>End Date</label>
                <input type="datetime-local" name="end_date" class="form-control" value="<?php echo $edit_poll['end_date'] ? date('Y-m-d\TH:i', strtotime($edit_poll['end_date'])) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Options <span style="color: red;">*</span></label>
                <div id="edit-options-list">
                    <?php foreach ($edit_poll['options'] as $option): ?>
                    <div class="option-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <input type="hidden" name="options[<?php echo $option['id']; ?>][id]" value="<?php echo $option['id']; ?>">
                        <input type="text" name="options[<?php echo $option['id']; ?>][text]" class="form-control" value="<?php echo htmlspecialchars($option['option_text']); ?>" required>
                        <button type="button" class="btn btn-danger remove-option">Remove</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-secondary" id="add-edit-option">Add Option</button>
            </div>
            
            <button type="submit" class="btn btn-primary">Update Poll</button>
            <a href="polls.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Create Poll Form -->
<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h2>Create New Poll</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="create_poll">
            
            <div class="form-group">
                <label>Question <span style="color: red;">*</span></label>
                <input type="text" name="question" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="allow_multiple" value="1">
                    Allow Multiple Selections
                </label>
            </div>
            
            <div class="form-group">
                <label>Start Date</label>
                <input type="datetime-local" name="start_date" class="form-control">
            </div>
            
            <div class="form-group">
                <label>End Date</label>
                <input type="datetime-local" name="end_date" class="form-control">
            </div>
            
            <div class="form-group">
                <label>Options <span style="color: red;">*</span> (At least 2 required)</label>
                <div id="options-list">
                    <div class="option-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <input type="text" name="options[]" class="form-control" required>
                        <button type="button" class="btn btn-danger remove-option">Remove</button>
                    </div>
                    <div class="option-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <input type="text" name="options[]" class="form-control" required>
                        <button type="button" class="btn btn-danger remove-option">Remove</button>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" id="add-option">Add Option</button>
            </div>
            
            <button type="submit" class="btn btn-primary">Create Poll</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Polls List -->
<div class="card">
    <div class="card-header">
        <h2>All Polls</h2>
    </div>
    <div class="card-body">
        <?php if (empty($polls)): ?>
        <p>No polls created yet.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Question</th>
                    <th>Options</th>
                    <th>Votes</th>
                    <th>Status</th>
                    <th>Dates</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($polls as $poll): ?>
                <tr>
                    <td><?php echo $poll['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($poll['question']); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars(substr($poll['description'] ?? '', 0, 100)); ?></small>
                    </td>
                    <td><?php echo count($poll['options'] ?? []); ?> options</td>
                    <td><?php echo $poll['total_votes']; ?> votes</td>
                    <td>
                        <span class="badge badge-<?php echo $poll['status'] === 'active' ? 'success' : ($poll['status'] === 'closed' ? 'danger' : 'secondary'); ?>">
                            <?php echo ucfirst($poll['status']); ?>
                        </span>
                    </td>
                    <td>
                        <small>
                            <?php if ($poll['start_date']): ?>
                            Start: <?php echo date('Y-m-d', strtotime($poll['start_date'])); ?><br>
                            <?php endif; ?>
                            <?php if ($poll['end_date']): ?>
                            End: <?php echo date('Y-m-d', strtotime($poll['end_date'])); ?>
                            <?php endif; ?>
                        </small>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="?view_answers=<?php echo $poll['id']; ?>" class="btn btn-sm btn-success">View Answers</a>
                            <a href="?edit=<?php echo $poll['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="poll_id" value="<?php echo $poll['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $poll['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                <button type="submit" class="btn btn-sm btn-secondary">
                                    <?php echo $poll['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this poll?');">
                                <input type="hidden" name="action" value="delete_poll">
                                <input type="hidden" name="poll_id" value="<?php echo $poll['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
// Add option button
document.getElementById('add-option')?.addEventListener('click', function() {
    const optionsList = document.getElementById('options-list');
    const newRow = document.createElement('div');
    newRow.className = 'option-row';
    newRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px;';
    newRow.innerHTML = `
        <input type="text" name="options[]" class="form-control" required>
        <button type="button" class="btn btn-danger remove-option">Remove</button>
    `;
    optionsList.appendChild(newRow);
});

// Remove option button
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-option')) {
        const optionsList = e.target.closest('#options-list') || e.target.closest('#edit-options-list');
        if (optionsList && optionsList.querySelectorAll('.option-row').length > 2) {
            e.target.closest('.option-row').remove();
        } else {
            alert('At least 2 options are required!');
        }
    }
});

// Edit form add option
document.getElementById('add-edit-option')?.addEventListener('click', function() {
    const optionsList = document.getElementById('edit-options-list');
    const index = optionsList.querySelectorAll('.option-row').length;
    const newRow = document.createElement('div');
    newRow.className = 'option-row';
    newRow.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px;';
    newRow.innerHTML = `
        <input type="text" name="options[${index}][text]" class="form-control" required>
        <button type="button" class="btn btn-danger remove-option">Remove</button>
    `;
    optionsList.appendChild(newRow);
});
</script>

<?php include 'includes/footer.php'; ?>

