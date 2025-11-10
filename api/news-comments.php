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
        
        // Add is_approved column if it doesn't exist
        if (!in_array('is_approved', $columns)) {
            try {
                $conn->exec("ALTER TABLE news_comments ADD COLUMN is_approved BOOLEAN DEFAULT TRUE AFTER comment");
                // Update existing rows to be approved
                $conn->exec("UPDATE news_comments SET is_approved = TRUE WHERE is_approved IS NULL");
            } catch (Exception $e) {
                error_log("Error adding is_approved column: " . $e->getMessage());
            }
        }
        
        // Change user_id to allow NULL if it's currently NOT NULL
        try {
            $conn->exec("ALTER TABLE news_comments MODIFY COLUMN user_id INT NULL");
        } catch (Exception $e) {
            // Column might already allow NULL or have constraints - ignore
        }
    }
} catch (Exception $e) {
    error_log("News comments table setup error: " . $e->getMessage());
}

// Force ensure is_approved column exists - run this BEFORE any queries
try {
    // First check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'news_comments'");
    if ($table_check->rowCount() > 0) {
        // Table exists, check for is_approved column
        $col_check = $conn->query("SHOW COLUMNS FROM news_comments LIKE 'is_approved'");
        if ($col_check->rowCount() == 0) {
            // Column doesn't exist, add it
            try {
                $conn->exec("ALTER TABLE news_comments ADD COLUMN is_approved BOOLEAN DEFAULT TRUE");
                // Set all existing comments as approved
                $conn->exec("UPDATE news_comments SET is_approved = TRUE");
                error_log("Added is_approved column to news_comments table");
            } catch (Exception $e) {
                error_log("Failed to add is_approved column: " . $e->getMessage());
                // Try alternative syntax
                try {
                    $conn->exec("ALTER TABLE news_comments ADD COLUMN is_approved TINYINT(1) DEFAULT 1");
                    $conn->exec("UPDATE news_comments SET is_approved = 1");
                } catch (Exception $e2) {
                    error_log("Failed to add is_approved with alternative syntax: " . $e2->getMessage());
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error ensuring is_approved column exists: " . $e->getMessage());
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
            
            // Check if columns exist
            $has_name_column = false;
            $has_is_approved = false;
            try {
                $col_check = $conn->query("SHOW COLUMNS FROM news_comments");
                $all_columns = $col_check->fetchAll(PDO::FETCH_COLUMN);
                $has_name_column = in_array('name', $all_columns);
                $has_is_approved = in_array('is_approved', $all_columns);
            } catch (Exception $e) {
                $has_name_column = false;
                $has_is_approved = false;
            }
            
            // Build WHERE clause based on available columns
            $where_clause = "nc.news_id = ?";
            if ($has_is_approved) {
                $where_clause .= " AND nc.is_approved = 1";
            }
            
            // Build SELECT based on available columns
            if ($has_name_column) {
                $stmt = $conn->prepare("
                    SELECT nc.*, 
                           COALESCE(u.username, nc.name, 'Anonymous') as display_name,
                           COALESCE(u.avatar, '') as avatar
                    FROM news_comments nc
                    LEFT JOIN users u ON nc.user_id = u.id
                    WHERE $where_clause
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
                    WHERE $where_clause
                    ORDER BY nc.created_at DESC
                ");
            }
            try {
                $stmt->execute([$news_id]);
                $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // If query fails due to missing column, try simpler query
                error_log("Comments query error: " . $e->getMessage());
                try {
                    // Fallback: query without is_approved
                    $fallback_stmt = $conn->prepare("
                        SELECT nc.*, 
                               COALESCE(u.username, 'Anonymous') as display_name,
                               COALESCE(u.avatar, '') as avatar
                        FROM news_comments nc
                        LEFT JOIN users u ON nc.user_id = u.id
                        WHERE nc.news_id = ?
                        ORDER BY nc.created_at DESC
                    ");
                    $fallback_stmt->execute([$news_id]);
                    $comments = $fallback_stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e2) {
                    error_log("Fallback query also failed: " . $e2->getMessage());
                    $comments = [];
                }
            }
            
            echo json_encode([
                'success' => true,
                'comments' => array_values($comments),
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
            
            // Check which columns exist before inserting
            $col_check = $conn->query("SHOW COLUMNS FROM news_comments");
            $all_columns = $col_check->fetchAll(PDO::FETCH_COLUMN);
            $has_name = in_array('name', $all_columns);
            $has_email = in_array('email', $all_columns);
            $has_website = in_array('website', $all_columns);
            $has_is_approved = in_array('is_approved', $all_columns);
            
            // Build insert query based on available columns
            $insert_fields = ['news_id', 'comment'];
            $insert_values = [$news_id, $comment];
            
            if ($has_name) {
                $insert_fields[] = 'name';
                $insert_values[] = $name;
            }
            if ($has_email) {
                $insert_fields[] = 'email';
                $insert_values[] = $email;
            }
            if ($has_website && !empty($website)) {
                $insert_fields[] = 'website';
                $insert_values[] = $website;
            }
            if ($user_id) {
                $insert_fields[] = 'user_id';
                $insert_values[] = $user_id;
            }
            if ($has_is_approved) {
                $insert_fields[] = 'is_approved';
                $insert_values[] = 1;
            }
            
            $fields_str = implode(', ', $insert_fields);
            $placeholders = implode(', ', array_fill(0, count($insert_values), '?'));
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO news_comments ($fields_str)
                    VALUES ($placeholders)
                ");
                $stmt->execute($insert_values);
                
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

