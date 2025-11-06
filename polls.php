<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get active polls
$active_polls = [];
try {
    $pollsStmt = $conn->query("SELECT * FROM polls WHERE status = 'active' AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY created_at DESC");
    $active_polls = $pollsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get options and vote counts for each poll
    foreach ($active_polls as &$poll) {
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
        
        // Check if user has voted
        $hasVoted = false;
        if (isset($_SESSION['user_id'])) {
            $checkVoteStmt = $conn->prepare("SELECT COUNT(*) as count FROM poll_votes WHERE poll_id = ? AND user_id = ?");
            $checkVoteStmt->execute([$poll['id'], $_SESSION['user_id']]);
            $voteCheck = $checkVoteStmt->fetch(PDO::FETCH_ASSOC);
            $hasVoted = $voteCheck && $voteCheck['count'] > 0;
        } else {
            // Check IP for anonymous users
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $checkIpVoteStmt = $conn->prepare("SELECT COUNT(*) as count FROM poll_votes WHERE poll_id = ? AND ip_address = ?");
            $checkIpVoteStmt->execute([$poll['id'], $ip_address]);
            $ipVoteCheck = $checkIpVoteStmt->fetch(PDO::FETCH_ASSOC);
            $hasVoted = $ipVoteCheck && $ipVoteCheck['count'] > 0;
        }
        $poll['hasVoted'] = $hasVoted;
    }
} catch (Exception $e) {
    $active_polls = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opinion Polls - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        body {
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .polls-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-header h1 {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 18px;
            color: #666;
        }
        
        .poll-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .poll-question {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }
        
        .poll-option {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .poll-option:hover {
            border-color: #1e4d72;
            background: #f8f9fa;
        }
        
        .poll-option input[type="radio"] {
            width: 20px;
            height: 20px;
        }
        
        .poll-submit {
            background: #1e4d72;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
        }
        
        .poll-submit:hover {
            background: #163a56;
        }
        
        .no-polls {
            text-align: center;
            padding: 60px 20px;
        }
        
        .no-polls i {
            font-size: 64px;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .no-polls h3 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .no-polls p {
            color: #999;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="polls-container">
        <div class="page-header">
            <h1><i class="fas fa-poll"></i> Opinion Polls</h1>
            <p>Share your opinion on music, artists, and trending topics</p>
        </div>
        
        <?php if (empty($active_polls)): ?>
            <div class="poll-card no-polls">
                <i class="fas fa-vote-yea"></i>
                <h3>No Active Polls</h3>
                <p>Check back soon for new polls and surveys!</p>
            </div>
        <?php else: ?>
            <?php foreach ($active_polls as $poll): ?>
                <div class="poll-card">
                    <h3 class="poll-question"><?php echo htmlspecialchars($poll['question']); ?></h3>
                    <?php if (!empty($poll['description'])): ?>
                    <p style="color: #666; margin-bottom: 20px; font-size: 14px;"><?php echo htmlspecialchars($poll['description']); ?></p>
                    <?php endif; ?>
                    
                    <div id="poll-<?php echo $poll['id']; ?>" data-poll-id="<?php echo $poll['id']; ?>" data-allow-multiple="<?php echo $poll['allow_multiple'] ? '1' : '0'; ?>">
                        <?php if ($poll['hasVoted'] || $poll['total_votes'] > 0): ?>
                            <!-- Show Results -->
                            <div style="margin-bottom: 15px;">
                                <div style="font-size: 14px; color: #666; margin-bottom: 15px;">
                                    Total Votes: <strong><?php echo $poll['total_votes']; ?></strong>
                                </div>
                                <?php foreach ($poll['options'] as $option): ?>
                                <div style="margin-bottom: 15px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                        <span style="font-size: 14px; color: #333; font-weight: 500;"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                        <span style="font-size: 14px; color: #666; font-weight: 600;"><?php echo $option['vote_count']; ?> (<?php echo $option['percentage']; ?>%)</span>
                                    </div>
                                    <div style="width: 100%; height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; width: <?php echo $option['percentage']; ?>%; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 4px; transition: width 0.3s;"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Show Voting Options -->
                            <form id="poll-form-<?php echo $poll['id']; ?>" style="margin-bottom: 15px;">
                                <?php foreach ($poll['options'] as $option): ?>
                                <label style="display: block; padding: 12px; background: #f8f9fa; border-radius: 6px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; border: 2px solid transparent;" onmouseover="this.style.background='#e9ecef'; this.style.borderColor='#667eea';" onmouseout="this.style.background='#f8f9fa'; this.style.borderColor='transparent';">
                                    <input type="<?php echo $poll['allow_multiple'] ? 'checkbox' : 'radio'; ?>" name="poll_option" value="<?php echo $option['id']; ?>" style="margin-right: 10px;">
                                    <span style="font-size: 14px; color: #333;"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                </label>
                                <?php endforeach; ?>
                                <button type="submit" style="padding: 12px 30px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; margin-top: 10px; transition: all 0.3s;" onmouseover="this.style.background='#5568d3';" onmouseout="this.style.background='#667eea';">
                                    <i class="fas fa-check"></i> Vote
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <script>
                (function() {
                    const pollForm = document.getElementById('poll-form-<?php echo $poll['id']; ?>');
                    if (pollForm) {
                        pollForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const formData = new FormData(this);
                            const selectedOptions = formData.getAll('poll_option');
                            
                            if (selectedOptions.length === 0) {
                                alert('Please select an option');
                                return;
                            }
                            
                            const pollId = <?php echo $poll['id']; ?>;
                            const allowMultiple = <?php echo $poll['allow_multiple'] ? 'true' : 'false'; ?>;
                            
                            if (!allowMultiple && selectedOptions.length > 1) {
                                alert('Please select only one option');
                                return;
                            }
                            
                            // Submit vote(s)
                            const votePromises = selectedOptions.map(optionId => {
                                return fetch('api/poll-vote.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({
                                        poll_id: pollId,
                                        option_id: parseInt(optionId)
                                    })
                                }).then(res => res.json());
                            });
                            
                            Promise.all(votePromises).then(results => {
                                if (results[0] && results[0].success) {
                                    // Reload page to show results
                                    window.location.reload();
                                } else {
                                    alert('Error: ' + (results[0]?.error || 'Failed to vote'));
                                }
                            }).catch(err => {
                                console.error('Vote error:', err);
                                alert('Failed to vote. Please try again.');
                            });
                        });
                    }
                })();
                </script>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>

