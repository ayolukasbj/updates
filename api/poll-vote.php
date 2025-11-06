<?php
// api/poll-vote.php
// API endpoint for voting on polls

require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['poll_id']) || !isset($input['option_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Poll ID and Option ID are required']);
    exit;
}

$poll_id = (int)$input['poll_id'];
$option_id = (int)$input['option_id'];
$user_id = null;

// Check if user is logged in
session_start();
if (isset($_SESSION['user_id']) && function_exists('is_logged_in') && is_logged_in()) {
    $user_id = (int)$_SESSION['user_id'];
}

// Get user info for tracking
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if poll exists and is active
    $pollStmt = $conn->prepare("SELECT * FROM polls WHERE id = ? AND status = 'active'");
    $pollStmt->execute([$poll_id]);
    $poll = $pollStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$poll) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Poll not found or not active']);
        exit;
    }
    
    // Check if poll has ended
    if (!empty($poll['end_date']) && strtotime($poll['end_date']) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Poll has ended']);
        exit;
    }
    
    // Check if poll has started
    if (!empty($poll['start_date']) && strtotime($poll['start_date']) > time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Poll has not started yet']);
        exit;
    }
    
    // Verify option belongs to poll
    $optionStmt = $conn->prepare("SELECT * FROM poll_options WHERE id = ? AND poll_id = ?");
    $optionStmt->execute([$option_id, $poll_id]);
    $option = $optionStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$option) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Option not found']);
        exit;
    }
    
    // Check if user already voted (if logged in)
    if ($user_id) {
        $existingVoteStmt = $conn->prepare("SELECT * FROM poll_votes WHERE poll_id = ? AND user_id = ?");
        $existingVoteStmt->execute([$poll_id, $user_id]);
        $existingVote = $existingVoteStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingVote && !$poll['allow_multiple']) {
            // User already voted and multiple votes not allowed
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'You have already voted on this poll']);
            exit;
        }
        
        // Check if user voted for this specific option
        $specificVoteStmt = $conn->prepare("SELECT * FROM poll_votes WHERE poll_id = ? AND option_id = ? AND user_id = ?");
        $specificVoteStmt->execute([$poll_id, $option_id, $user_id]);
        $specificVote = $specificVoteStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($specificVote) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'You have already voted for this option']);
            exit;
        }
    } else {
        // For anonymous users, check IP address (basic prevention)
        $ipVoteStmt = $conn->prepare("SELECT * FROM poll_votes WHERE poll_id = ? AND option_id = ? AND ip_address = ?");
        $ipVoteStmt->execute([$poll_id, $option_id, $ip_address]);
        $ipVote = $ipVoteStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ipVote) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'You have already voted for this option']);
            exit;
        }
    }
    
    // Insert vote
    $voteStmt = $conn->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $voteStmt->execute([$poll_id, $option_id, $user_id, $ip_address, $user_agent]);
    
    // Get updated poll results
    $resultsStmt = $conn->prepare("
        SELECT 
            po.id as option_id,
            po.option_text,
            COUNT(pv.id) as vote_count
        FROM poll_options po
        LEFT JOIN poll_votes pv ON po.id = pv.option_id
        WHERE po.poll_id = ?
        GROUP BY po.id, po.option_text
        ORDER BY po.display_order
    ");
    $resultsStmt->execute([$poll_id]);
    $results = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total votes
    $totalVotes = array_sum(array_column($results, 'vote_count'));
    
    // Calculate percentages
    foreach ($results as &$result) {
        $result['percentage'] = $totalVotes > 0 ? round(($result['vote_count'] / $totalVotes) * 100, 1) : 0;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Vote recorded successfully',
        'results' => $results,
        'total_votes' => $totalVotes
    ]);
    
} catch (Exception $e) {
    error_log("Poll vote error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>

