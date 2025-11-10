<?php
// api/news-comments.php - Handle news article comments
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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = new Database();
$conn = $db->getConnection();

// Create or alter news_comments table to ensure it has all required columns
try {
    // Check if table exists
    $table_exists = false;
    try {
        $conn->query("SELECT 1 FROM news_comments LIMIT 1");
        $table_exists = true;
    } catch (Exception $e) {
        $table_exists = false;
    }
    
    if (!$table_exists) {
        // Create table with all columns
        $conn->exec("
            CREATE TABLE IF NOT EXISTS news_comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                news_id INT NOT NULL,
                user_id INT,
                name VARCHAR(100),
                email VARCHAR(255),
                website VARCHAR(255),
                comment TEXT NOT NULL,
                is_approved BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_news (news_id),
                INDEX idx_user (user_id),
                INDEX idx_approved (is_approved)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        // Table exists - check and add missing columns
        $columns = [];
        try {
            $col_stmt = $conn->query("SHOW COLUMNS FROM news_comments");
            $columns = $col_stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            // Continue
        }
        
        // Add name column if it doesn't exist
        if (!in_array('name', $columns)) {
            try {
                $conn->exec("ALTER TABLE news_comments ADD COLUMN name VARCHAR(100) AFTER user_id");
            } catch (Exception $e) {
                error_log("Error adding name column: " . $e->getMessage());
            }
        }
        
        // Add email column if it doesn't exist
        if (!in_array('email', $columns)) {
            try {
                $conn->exec("ALTER TABLE news_comments ADD COLUMN email VARCHAR(255) AFTER name");
            } catch (Exception $e) {
                error_log("Error adding email column: " . $e->getMessage());
            }
        }
        
        // Add website column if it doesn't exist
        if (!in_array('website', $columns)) {
            try {
                $conn->exec("ALTER TABLE news_comments ADD COLUMN website VARCHAR(255) AFTER email");
            } catch (Exception $e) {
                error_log("Error adding website column: " . $e->getMessage());
            }
        }
        
        // Change user_id to allow NULL if it's currently NOT NULL
        try {
            $conn->exec("ALTER TABLE news_comments MODIFY COLUMN user_id INT NULL");
        } catch (Exception $e) {
            // Column might already allow NULL or have constraints
        }
    }
} catch (Exception $e) {
    error_log("News comments table setup error: " . $e->getMessage());
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$news_id = (int)($_GET['news_id'] ?? $_POST['news_id'] ?? 0);

try {
    switch ($action) {
        case 'list':
            // Get comments for a news article
            if (empty($news_id)) {
                echo json_encode(['error' => 'News ID required']);
                exit;
            }
            
            // Check if name column exists
            $has_name_column = false;
            try {
                $col_check = $conn->query("SHOW COLUMNS FROM news_comments LIKE 'name'");
                $has_name_column = $col_check->rowCount() > 0;
            } catch (Exception $e) {
                $has_name_column = false;
            }
            
            if ($has_name_column) {
                $stmt = $conn->prepare("
                    SELECT nc.*, 
                           COALESCE(u.username, nc.name, 'Anonymous') as display_name,
                           COALESCE(u.avatar, '') as avatar
                    FROM news_comments nc
                    LEFT JOIN users u ON nc.user_id = u.id
                    WHERE nc.news_id = ? AND nc.is_approved = 1
                    ORDER BY nc.created_at DESC
                ");
            } else {
                // Fallback for old schema without name column
                $stmt = $conn->prepare("
                    SELECT nc.*, 
                           COALESCE(u.username, 'Anonymous') as display_name,
                           COALESCE(u.avatar, '') as avatar
                    FROM news_comments nc
                    LEFT JOIN users u ON nc.user_id = u.id
                    WHERE nc.news_id = ? AND nc.is_approved = 1
                    ORDER BY nc.created_at DESC
                ");
            }
            $stmt->execute([$news_id]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'comments' => $comments,
                'count' => count($comments)
            ]);
            break;
            
        case 'add':
            // Add a comment
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'Invalid request method']);
                exit;
            }
            
            // Get data from POST
            $news_id = (int)($_POST['news_id'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $website = trim($_POST['website'] ?? '');
            
            // Check if user is logged in
            $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            
            // If logged in, get name and email from session/user data
            if (!empty($user_id)) {
                try {
                    $user_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
                    $user_stmt->execute([$user_id]);
                    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user_data) {
                        if (empty($name)) {
                            $name = $user_data['username'] ?? '';
                        }
                        if (empty($email)) {
                            $email = $user_data['email'] ?? '';
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error fetching user data: " . $e->getMessage());
                }
            }
            
            // Validation
            if (empty($comment)) {
                echo json_encode(['success' => false, 'error' => 'Comment is required']);
                exit;
            }
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'Name is required']);
                exit;
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Valid email is required']);
                exit;
            }
            
            if (empty($news_id)) {
                echo json_encode(['success' => false, 'error' => 'News ID is required']);
                exit;
            }
            
            // Verify news article exists
            $news_check = $conn->prepare("SELECT id FROM news WHERE id = ?");
            $news_check->execute([$news_id]);
            if (!$news_check->fetch()) {
                echo json_encode(['success' => false, 'error' => 'News article not found']);
                exit;
            }
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO news_comments (news_id, user_id, name, email, website, comment, is_approved)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$news_id, $user_id, $name, $email, $website, $comment]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Comment submitted successfully. It will be visible after approval.',
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
            
        case 'delete':
            // Delete comment (admin only)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['error' => 'Invalid request method']);
                exit;
            }
            
            // Check if user is admin
            if (empty($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
            
            $comment_id = (int)($_POST['comment_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM news_comments WHERE id = ?");
            $stmt->execute([$comment_id]);
            
            echo json_encode(['success' => true, 'message' => 'Comment deleted']);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("News comments API error: " . $e->getMessage());
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>

