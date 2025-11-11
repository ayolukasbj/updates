<?php
/**
 * Rating API
 * Handles song ratings
 */

require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Create ratings table if doesn't exist
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS song_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            song_id INT NOT NULL,
            user_id INT,
            ip_address VARCHAR(45),
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_song (song_id, user_id),
            UNIQUE KEY unique_ip_song (song_id, ip_address),
            INDEX idx_song_id (song_id),
            FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    // Table might already exist
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $song_id = (int)($data['song_id'] ?? 0);
            $rating = (int)($data['rating'] ?? 0);
            
            if ($song_id > 0 && $rating >= 1 && $rating <= 5) {
                $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                
                try {
                    if ($user_id) {
                        $stmt = $conn->prepare("
                            INSERT INTO song_ratings (song_id, user_id, rating)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE rating = ?, updated_at = NOW()
                        ");
                        $stmt->execute([$song_id, $user_id, $rating, $rating]);
                    } else {
                        $stmt = $conn->prepare("
                            INSERT INTO song_ratings (song_id, ip_address, rating)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE rating = ?, updated_at = NOW()
                        ");
                        $stmt->execute([$song_id, $ip_address, $rating, $rating]);
                    }
                    
                    // Calculate average rating
                    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM song_ratings WHERE song_id = ?");
                    $stmt->execute([$song_id]);
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'average_rating' => round($stats['avg_rating'], 2),
                        'total_ratings' => (int)$stats['total_ratings']
                    ]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'error' => 'Rating already submitted']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid rating']);
            }
            break;
            
        case 'GET':
            $song_id = (int)($_GET['song_id'] ?? 0);
            if ($song_id > 0) {
                $stmt = $conn->prepare("
                    SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings
                    FROM song_ratings
                    WHERE song_id = ?
                ");
                $stmt->execute([$song_id]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get user's rating if logged in
                $user_rating = null;
                if (isset($_SESSION['user_id'])) {
                    $stmt = $conn->prepare("SELECT rating FROM song_ratings WHERE song_id = ? AND user_id = ?");
                    $stmt->execute([$song_id, $_SESSION['user_id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $user_rating = $result ? (int)$result['rating'] : null;
                }
                
                echo json_encode([
                    'success' => true,
                    'average_rating' => round($stats['avg_rating'], 2),
                    'total_ratings' => (int)$stats['total_ratings'],
                    'user_rating' => $user_rating
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Song ID required']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid method']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

