<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

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


