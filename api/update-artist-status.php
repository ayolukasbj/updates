<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['is_active'])) {
    echo json_encode(['success' => false, 'message' => 'Missing is_active parameter']);
    exit;
}

$user_id = get_user_id();
$is_active = (int)$data['is_active'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $result = $stmt->execute([$is_active, $user_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'is_active' => $is_active
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

