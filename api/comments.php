<?php
// api/comments.php - Handle song comments
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');
// Allow CORS for remote access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Create comments table if doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS song_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            song_id INT NOT NULL,
            user_id INT,
            username VARCHAR(100),
            comment TEXT NOT NULL,
            rating INT DEFAULT 0,
            is_approved TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_song (song_id),
            INDEX idx_user (user_id),
            INDEX idx_approved (is_approved),
            FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    // Table might already exist or foreign key issue - continue
}

// Create ratings table if doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS song_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            song_id INT NOT NULL,
            user_id INT,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_song (user_id, song_id),
            INDEX idx_song (song_id),
            FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    // Table might already exist
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$song_id = (int)($_GET['song_id'] ?? $_POST['song_id'] ?? 0);

try {
    switch ($action) {
        case 'list':
            // Get comments for a song
            if (empty($song_id)) {
                echo json_encode(['error' => 'Song ID required']);
                exit;
            }
            
            $stmt = $conn->prepare("
                SELECT c.*, 
                       COALESCE(u.username, c.username, 'Anonymous') as display_name,
                       COALESCE(u.avatar, '') as avatar
                FROM song_comments c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.song_id = ? AND c.is_approved = 1
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$song_id]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get average rating
            $ratingStmt = $conn->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count
                FROM song_ratings
                WHERE song_id = ?
            ");
            $ratingStmt->execute([$song_id]);
            $ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'comments' => $comments,
                'average_rating' => round($ratingData['avg_rating'] ?? 0, 1),
                'rating_count' => (int)($ratingData['rating_count'] ?? 0)
            ]);
            break;
            
        case 'add':
            // Add a comment
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'Invalid request method']);
                exit;
            }
            
            // Try to get JSON data first, fallback to POST
            $jsonInput = file_get_contents('php://input');
            $data = !empty($jsonInput) ? json_decode($jsonInput, true) : $_POST;
            
            // Get song_id from data or URL parameter
            $song_id = isset($data['song_id']) ? (int)$data['song_id'] : ((int)($_POST['song_id'] ?? $_GET['song_id'] ?? 0));
            $comment = trim($data['comment'] ?? $_POST['comment'] ?? '');
            $username = trim($data['username'] ?? $_POST['username'] ?? 'Anonymous');
            
            // Check if user is logged in - start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            
            if (empty($user_id)) {
                echo json_encode(['success' => false, 'error' => 'You must be logged in to post comments']);
                exit;
            }
            
            if (empty($comment)) {
                echo json_encode(['success' => false, 'error' => 'Comment is required']);
                exit;
            }
            
            if (empty($song_id)) {
                echo json_encode(['success' => false, 'error' => 'Song ID is required']);
                exit;
            }
            
            // Get username from session if not provided
            if (empty($username) && !empty($_SESSION['username'])) {
                $username = $_SESSION['username'];
            }
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO song_comments (song_id, user_id, username, comment, is_approved)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->execute([$song_id, $user_id, $username, $comment]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Comment added successfully',
                    'comment_id' => $conn->lastInsertId()
                ]);
            } catch (Exception $e) {
                error_log("Comment insertion error: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'error' => 'Error saving comment: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'rate':
            // Add or update rating
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'Invalid request method']);
                exit;
            }
            
            // Get data from JSON body or POST
            $jsonInput = file_get_contents('php://input');
            $data = !empty($jsonInput) ? json_decode($jsonInput, true) : $_POST;
            
            // Get song_id from data, POST, or GET
            $song_id = isset($data['song_id']) ? (int)$data['song_id'] : ((int)($_POST['song_id'] ?? $_GET['song_id'] ?? 0));
            $rating = (int)($data['rating'] ?? $_POST['rating'] ?? 0);
            
            // Check if user is logged in - start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            
            if (empty($user_id)) {
                echo json_encode(['success' => false, 'error' => 'You must be logged in to rate songs']);
                exit;
            }
            
            if ($rating < 1 || $rating > 5) {
                echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
                exit;
            }
            
            if (empty($song_id)) {
                echo json_encode(['success' => false, 'error' => 'Song ID required']);
                exit;
            }
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO song_ratings (song_id, user_id, rating)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE rating = ?, updated_at = NOW()
                ");
                $stmt->execute([$song_id, $user_id, $rating, $rating]);
                
                // Get updated average
                $avgStmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count FROM song_ratings WHERE song_id = ?");
                $avgStmt->execute([$song_id]);
                $avgData = $avgStmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Rating saved',
                    'average_rating' => round($avgData['avg_rating'] ?? 0, 1),
                    'rating_count' => (int)($avgData['rating_count'] ?? 0)
                ]);
            } catch (Exception $e) {
                error_log("Rating error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Error saving rating: ' . $e->getMessage()]);
            }
            break;
            
        case 'delete':
            // Delete comment (admin only)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Invalid request method']);
                exit;
            }
            
            $comment_id = (int)($data['comment_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM song_comments WHERE id = ?");
            $stmt->execute([$comment_id]);
            
            echo json_encode(['success' => true, 'message' => 'Comment deleted']);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Comments API error: " . $e->getMessage());
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
