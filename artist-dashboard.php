<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Redirect if not logged in
if (!is_logged_in()) {
    redirect('login.php');
}

// Redirect non-artists to regular dashboard
$db = new Database();
$conn = $db->getConnection();
$user_id = get_user_id();

$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user_data && $user_data['role'] === 'admin') {
    redirect('admin/index.php');
}

// Redirect to mobile profile page
redirect('artist-profile-mobile.php');
?>
