<?php
// Set headers first
header('Content-Type: application/json');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Load config with proper path resolution
$config_path = __DIR__ . '/../config/config.php';
if (!file_exists($config_path)) {
    $config_path = 'config/config.php';
}
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
    exit;
}

// Load database
$db_path = __DIR__ . '/../config/database.php';
if (!file_exists($db_path)) {
    $db_path = 'config/database.php';
}
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database configuration not found']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Create newsletter_subscribers table if it doesn't exist
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
    
    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id, status FROM newsletter_subscribers WHERE email = ?");
    $checkStmt->execute([$email]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        if ($existing['status'] === 'unsubscribed') {
            // Resubscribe
            $updateStmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'active', subscribed_at = NOW(), unsubscribed_at = NULL WHERE email = ?");
            $updateStmt->execute([$email]);
            echo json_encode(['success' => true, 'message' => 'You have been resubscribed to our newsletter!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'This email is already subscribed']);
        }
    } else {
        // Insert new subscriber
        $stmt = $conn->prepare("INSERT INTO newsletter_subscribers (email, status, source) VALUES (?, 'active', 'news_details')");
        $stmt->execute([$email]);
        echo json_encode(['success' => true, 'message' => 'Thank you for subscribing to our newsletter!']);
    }
    
} catch (Exception $e) {
    error_log("Newsletter subscription error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to subscribe. Please try again later.']);
}


