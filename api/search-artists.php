<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $artists = [];
    
    // If ID is provided, fetch by ID
    if ($user_id && $user_id > 0) {
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.email, u.avatar
            FROM users u
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $artist = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($artist) {
            $artists = [$artist];
        }
    }
    // Otherwise, search by username if query is provided
    else if (strlen($query) >= 2) {
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.email, u.avatar
            FROM users u
            WHERE u.username LIKE ?
            ORDER BY u.username ASC
            LIMIT 10
        ");
        $stmt->execute(['%' . $query . '%']);
        $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $results = [];
    foreach ($artists as $artist) {
        $results[] = [
            'id' => $artist['id'],
            'username' => $artist['username'],
            'email' => $artist['email'],
            'avatar' => $artist['avatar'] ?? ''
        ];
    }
    
    echo json_encode($results);
} catch (Exception $e) {
    error_log('Search Artists Error: ' . $e->getMessage());
    echo json_encode([]);
}
?>

